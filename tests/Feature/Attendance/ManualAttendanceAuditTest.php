<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceAuditAction;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\OvertimeStatus;
use App\Enums\PayrollStatus;
use App\Models\AttendanceAuditLog;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Payroll;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ManualAttendanceAuditTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $manager;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        // Các test này dùng ngày cố định; đóng băng đồng hồ để chúng không
        // trôi ra ngoài cửa sổ ghi lùi khi thời gian thật đi qua.
        Carbon::setTestNow(Carbon::parse('2026-09-20 09:00:00'));

        $this->branch = Branch::factory()->create(['code' => 'CN-MAN']);
        $this->manager = User::factory()->manager()->withoutBranch()->atBranch($this->branch)->create();
        $this->employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_manager_can_still_record_a_forgotten_shift_by_hand(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.attendance.store'), $this->payload())
            ->assertRedirect();

        $record = AttendanceRecord::query()->firstOrFail();

        $this->assertSame(AttendanceSource::Manual, $record->source);
        $this->assertSame(AttendanceStatus::Present, $record->status);
        $this->assertNull($record->check_in_verification);
    }

    public function test_a_manual_entry_without_a_reason_is_refused(): void
    {
        $payload = $this->payload();
        unset($payload['reason']);

        $this->actingAs($this->manager)
            ->from(route('admin.attendance.index'))
            ->post(route('admin.attendance.store'), $payload)
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_a_manual_entry_records_who_why_and_when(): void
    {
        $this->actingAs($this->manager)->post(route('admin.attendance.store'), $this->payload());

        $log = AttendanceAuditLog::query()->firstOrFail();

        $this->assertSame('manual_create', $log->action->value);
        $this->assertSame($this->manager->id, $log->actor_id);
        $this->assertSame($this->branch->id, $log->branch_id);
        $this->assertSame('Nhân viên quên bấm vào ca', $log->reason);
        $this->assertNull($log->before);
        $this->assertSame('2026-09-14', $log->after['work_date']);
        $this->assertNotNull($log->created_at);
    }

    public function test_a_manual_edit_records_the_values_before_and_after(): void
    {
        $record = AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'work_date' => '2026-09-14',
            'shift_name' => 'Ca chính',
            'status' => AttendanceStatus::Present,
        ]);

        $this->actingAs($this->manager)
            ->put(route('admin.attendance.update', $record), $this->payload([
                'status' => AttendanceStatus::Late->value,
                'reason' => 'Nhân viên báo kẹt xe',
            ]))
            ->assertRedirect();

        $log = $record->auditLogs()->firstOrFail();

        $this->assertSame('manual_update', $log->action->value);
        $this->assertSame('Nhân viên báo kẹt xe', $log->reason);
        $this->assertSame(AttendanceStatus::Present->value, $log->before['status']);
        $this->assertSame(AttendanceStatus::Late->value, $log->after['status']);
        $this->assertArrayHasKey('status', $log->changes());
    }

    public function test_deleting_a_shift_leaves_an_audit_entry_behind(): void
    {
        $record = AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'work_date' => '2026-09-14',
            'shift_name' => 'Ca chính',
        ]);

        $this->actingAs($this->manager)
            ->delete(route('admin.attendance.destroy', $record), ['reason' => 'Ghi nhầm sang nhân viên khác'])
            ->assertRedirect();

        $this->assertSame(0, AttendanceRecord::query()->count());

        // The shift row is gone, but the evidence of who removed it — and what
        // it held — must not go with it. The relation is nulled, not cascaded,
        // and the snapshot columns keep the entry readable on its own.
        $log = AttendanceAuditLog::query()->latest('id')->firstOrFail();

        $this->assertNull($log->attendance_record_id);
        $this->assertSame($this->employee->id, $log->employee_id_snapshot);
        $this->assertSame('Ca chính', $log->shift_name_snapshot);
        $this->assertSame('2026-09-14', $log->work_date_snapshot?->toDateString());
        $this->assertSame($this->manager->id, $log->actor_id);
        $this->assertSame('Ghi nhầm sang nhân viên khác', $log->reason);
        $this->assertSame(AttendanceAuditAction::ManualDelete, $log->action);
    }

    public function test_a_closed_payroll_blocks_a_manual_create(): void
    {
        Payroll::factory()->create([
            'employee_id' => $this->employee->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'status' => PayrollStatus::Paid,
        ]);

        $this->actingAs($this->manager)
            ->from(route('admin.attendance.index'))
            ->post(route('admin.attendance.store'), $this->payload())
            ->assertSessionHasErrors('work_date');

        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_a_closed_payroll_blocks_a_manual_edit(): void
    {
        $record = AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'work_date' => '2026-09-14',
            'shift_name' => 'Ca chính',
            'status' => AttendanceStatus::Present,
        ]);

        Payroll::factory()->create([
            'employee_id' => $this->employee->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'status' => PayrollStatus::Finalized,
        ]);

        $this->actingAs($this->manager)
            ->from(route('admin.attendance.edit', $record))
            ->put(route('admin.attendance.update', $record), $this->payload(['status' => AttendanceStatus::Absent->value]))
            ->assertSessionHasErrors('work_date');

        $this->assertSame(AttendanceStatus::Present, $record->fresh()->status);
    }

    /** Moving hours out of a closed period is blocked from both ends. */
    public function test_a_closed_payroll_blocks_moving_a_shift_into_the_locked_period(): void
    {
        $record = AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'work_date' => '2026-10-05',
            'shift_name' => 'Ca chính',
        ]);

        Payroll::factory()->create([
            'employee_id' => $this->employee->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'status' => PayrollStatus::Finalized,
        ]);

        $this->actingAs($this->manager)
            ->from(route('admin.attendance.edit', $record))
            ->put(route('admin.attendance.update', $record), $this->payload(['work_date' => '2026-09-14']))
            ->assertSessionHasErrors('work_date');

        $this->assertSame('2026-10-05', $record->fresh()->work_date->toDateString());
    }

    /**
     * Correcting the clock-out changes how much overtime there is, so any
     * previous approval is withdrawn and the new figure goes back to pending.
     */
    public function test_editing_the_clock_out_recalculates_overtime_and_resets_the_approval(): void
    {
        $shift = WorkShift::factory()->atBranch($this->branch)->spanning('09:00:00', '19:00:00')->create();

        $assignment = ShiftAssignment::factory()
            ->forEmployee($this->employee)->atBranch($this->branch)
            ->usingShift($shift)->on('2026-09-14')->create();

        $record = AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'shift_assignment_id' => $assignment->id,
            'employee_id' => $this->employee->id,
            'work_date' => '2026-09-14',
            'shift_name' => $assignment->shift_name,
            'checked_in_at' => '2026-09-14 09:00:00',
        ]);

        $record->forceFill([
            'overtime_minutes' => 30,
            'approved_overtime_minutes' => 30,
            'overtime_status' => OvertimeStatus::Approved,
            'overtime_approved_by' => $this->manager->id,
        ])->save();

        $this->actingAs($this->manager)
            ->put(route('admin.attendance.update', $record), $this->payload([
                'shift_name' => $assignment->shift_name,
                'checked_in_at' => '2026-09-14 09:00:00',
                'checked_out_at' => '2026-09-14 20:00:00',
                'reason' => 'Nhân viên quên bấm ra ca',
            ]))
            ->assertRedirect();

        $record->refresh();

        $this->assertSame(60, $record->overtime_minutes);
        $this->assertSame(0, $record->approved_overtime_minutes);
        $this->assertSame(OvertimeStatus::Pending, $record->overtime_status);
        $this->assertNull($record->overtime_approved_by);
    }

    public function test_an_employee_cannot_reach_the_manual_form(): void
    {
        $this->actingAs($this->employee)->get(route('admin.attendance.index'))->assertForbidden();
        $this->actingAs($this->employee)
            ->post(route('admin.attendance.store'), $this->payload())
            ->assertForbidden();
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $this->employee->id,
            'work_date' => '2026-09-14',
            'shift_name' => 'Ca chính',
            'shift_value' => 1,
            'status' => AttendanceStatus::Present->value,
            'reason' => 'Nhân viên quên bấm vào ca',
        ], $overrides);
    }
}
