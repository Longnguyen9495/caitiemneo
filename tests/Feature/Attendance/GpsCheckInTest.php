<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\GpsVerification;
use App\Enums\OvertimeStatus;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GpsCheckInTest extends TestCase
{
    use RefreshDatabase;

    /** The shop. */
    private const SHOP_LAT = 10.7769000;

    private const SHOP_LNG = 106.7009000;

    /** Roughly 89 m due north of the shop: inside a 100 m radius. */
    private const INSIDE_LAT = 10.7777000;

    /** Roughly 245 m due north: comfortably outside it. */
    private const OUTSIDE_LAT = 10.7791000;

    private Branch $branch;

    private User $employee;

    private WorkShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create([
            'code' => 'CN-GPS',
            'latitude' => self::SHOP_LAT,
            'longitude' => self::SHOP_LNG,
            'attendance_radius_meters' => 100,
            'attendance_accuracy_limit_meters' => 150,
            'gps_attendance_enabled' => true,
        ]);

        $this->employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();

        $this->shift = WorkShift::factory()->atBranch($this->branch)->spanning('09:00:00', '19:00:00')->create([
            'name' => 'Ca chính',
            'grace_minutes' => 5,
        ]);
    }

    public function test_a_guest_cannot_reach_the_clock_in_page_or_endpoints(): void
    {
        $this->get(route('attendance.board'))->assertRedirect(route('login'));
        $this->post(route('attendance.check-in'), $this->fix())->assertRedirect(route('login'));
        $this->post(route('attendance.check-out'), $this->fix())->assertRedirect(route('login'));
    }

    public function test_an_employee_sees_only_their_own_shift_on_the_board(): void
    {
        $colleague = User::factory()->employee()->withoutBranch()->atBranch($this->branch)
            ->create(['name' => 'Đồng nghiệp khác ca']);

        $this->travelTo(Carbon::parse('2026-09-14 08:30:00'));

        $this->roster($this->employee, '2026-09-14');
        $this->roster($colleague, '2026-09-14');

        $this->actingAs($this->employee)
            ->get(route('attendance.board'))
            ->assertOk()
            ->assertSee('Ca chính')
            ->assertDontSee('Đồng nghiệp khác ca');
    }

    public function test_without_a_rostered_shift_there_is_nothing_to_clock_into(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 09:00:00'));

        $this->actingAs($this->employee)
            ->from(route('attendance.board'))
            ->post(route('attendance.check-in'), $this->fix(self::INSIDE_LAT))
            ->assertSessionHasErrors('gps');

        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_a_branch_without_gps_configured_refuses_with_a_clear_message(): void
    {
        $this->branch->update(['gps_attendance_enabled' => false]);
        $this->travelTo(Carbon::parse('2026-09-14 09:00:00'));
        $this->roster($this->employee, '2026-09-14');

        $this->actingAs($this->employee)
            ->from(route('attendance.board'))
            ->post(route('attendance.check-in'), $this->fix(self::INSIDE_LAT))
            ->assertSessionHasErrors(['gps' => 'Chi nhánh này chưa bật chấm công GPS. Hãy báo quản lý cấu hình vị trí cửa hàng.']);

        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_a_fix_inside_the_radius_and_accurate_enough_starts_the_shift(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 08:55:00'));
        $assignment = $this->roster($this->employee, '2026-09-14');

        $this->actingAs($this->employee)
            ->post(route('attendance.check-in'), $this->fix(self::INSIDE_LAT, accuracy: 20))
            ->assertRedirect(route('attendance.board'))
            ->assertSessionHas('success');

        $record = AttendanceRecord::query()->firstOrFail();

        $this->assertSame(AttendanceStatus::Present, $record->status);
        $this->assertSame(AttendanceSource::Gps, $record->source);
        $this->assertSame(GpsVerification::Verified, $record->check_in_verification);
        $this->assertSame(0, $record->late_minutes);
        $this->assertSame($assignment->id, $record->shift_assignment_id);
        $this->assertSame($this->branch->id, $record->branch_id);
        $this->assertSame('2026-09-14 08:55:00', $record->checked_in_at->toDateTimeString());
        // Distance is recomputed server-side, never taken from the browser.
        $this->assertGreaterThan(80, $record->check_in_distance_meters);
        $this->assertLessThan(100, $record->check_in_distance_meters);
    }

    public function test_a_fix_outside_the_radius_is_refused(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 09:00:00'));
        $this->roster($this->employee, '2026-09-14');

        $this->actingAs($this->employee)
            ->from(route('attendance.board'))
            ->post(route('attendance.check-in'), $this->fix(self::OUTSIDE_LAT, accuracy: 10))
            ->assertSessionHasErrors('gps');

        $this->assertStringContainsString('ngoài phạm vi cho phép', session('errors')->first('gps'));
        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_a_fix_with_poor_accuracy_is_refused_even_when_it_is_close(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 09:00:00'));
        $this->roster($this->employee, '2026-09-14');

        $this->actingAs($this->employee)
            ->from(route('attendance.board'))
            ->post(route('attendance.check-in'), $this->fix(self::INSIDE_LAT, accuracy: 400))
            ->assertSessionHasErrors('gps');

        $this->assertStringContainsString('vượt mức cho phép', session('errors')->first('gps'));
        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    /**
     * The endpoint takes no identity of any kind from the request body, so
     * extra fields are simply not read.
     */
    public function test_an_employee_id_or_branch_id_in_the_payload_is_ignored(): void
    {
        $otherBranch = Branch::factory()->create(['code' => 'CN-OTHER']);
        $victim = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();

        $this->travelTo(Carbon::parse('2026-09-14 09:00:00'));
        $this->roster($this->employee, '2026-09-14');
        $this->roster($victim, '2026-09-14');

        $this->actingAs($this->employee)
            ->post(route('attendance.check-in'), $this->fix(self::INSIDE_LAT) + [
                'employee_id' => $victim->id,
                'branch_id' => $otherBranch->id,
                'shift_assignment_id' => 999,
                'checked_in_at' => '2020-01-01 00:00:00',
            ])
            ->assertRedirect(route('attendance.board'));

        $record = AttendanceRecord::query()->firstOrFail();

        $this->assertSame($this->employee->id, $record->employee_id);
        $this->assertSame($this->branch->id, $record->branch_id);
        $this->assertSame('2026-09-14 09:00:00', $record->checked_in_at->toDateTimeString());
        $this->assertSame(0, AttendanceRecord::query()->where('employee_id', $victim->id)->count());
    }

    public function test_arriving_within_the_grace_period_still_counts_as_present(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 09:04:00'));
        $this->roster($this->employee, '2026-09-14');

        $this->actingAs($this->employee)->post(route('attendance.check-in'), $this->fix(self::INSIDE_LAT));

        $record = AttendanceRecord::query()->firstOrFail();

        $this->assertSame(AttendanceStatus::Present, $record->status);
        $this->assertSame(0, $record->late_minutes);
    }

    /**
     * Grace decides whether the arrival is late at all; the minutes reported
     * are measured from the planned start, not from the end of the grace.
     */
    public function test_arriving_past_the_grace_period_records_late_and_the_full_minutes(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 09:12:00'));
        $this->roster($this->employee, '2026-09-14');

        $this->actingAs($this->employee)->post(route('attendance.check-in'), $this->fix(self::INSIDE_LAT));

        $record = AttendanceRecord::query()->firstOrFail();

        $this->assertSame(AttendanceStatus::Late, $record->status);
        $this->assertSame(12, $record->late_minutes);
    }

    public function test_clocking_in_before_the_early_window_opens_is_refused(): void
    {
        // The window opens 30 minutes before a 09:00 start.
        $this->travelTo(Carbon::parse('2026-09-14 08:15:00'));
        $this->roster($this->employee, '2026-09-14');

        $this->actingAs($this->employee)
            ->from(route('attendance.board'))
            ->post(route('attendance.check-in'), $this->fix(self::INSIDE_LAT))
            ->assertSessionHasErrors('gps');

        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_the_same_shift_cannot_be_clocked_into_twice(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 09:00:00'));
        $this->roster($this->employee, '2026-09-14');

        $this->actingAs($this->employee)->post(route('attendance.check-in'), $this->fix(self::INSIDE_LAT));

        $this->travelTo(Carbon::parse('2026-09-14 09:30:00'));

        $this->actingAs($this->employee)
            ->from(route('attendance.board'))
            ->post(route('attendance.check-in'), $this->fix(self::INSIDE_LAT))
            ->assertSessionHasErrors('gps');

        $this->assertSame(1, AttendanceRecord::query()->count());
        $this->assertSame('2026-09-14 09:00:00', AttendanceRecord::query()->firstOrFail()->checked_in_at->toDateTimeString());
    }

    public function test_a_posting_that_ended_before_the_shift_date_blocks_the_clock_in(): void
    {
        $leaver = User::factory()->employee()->withoutBranch()
            ->atBranch($this->branch, true, '2026-01-01', '2026-09-10')
            ->create();

        $this->travelTo(Carbon::parse('2026-09-14 09:00:00'));

        // Rostered while still employed at the branch, worked after leaving.
        ShiftAssignment::factory()
            ->forEmployee($leaver)
            ->atBranch($this->branch)
            ->usingShift($this->shift)
            ->on('2026-09-14')
            ->create();

        $this->actingAs($leaver)
            ->from(route('attendance.board'))
            ->post(route('attendance.check-in'), $this->fix(self::INSIDE_LAT))
            ->assertSessionHasErrors('gps');

        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_a_shift_a_manager_already_marked_as_leave_cannot_be_clocked_into(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 09:00:00'));
        $assignment = $this->roster($this->employee, '2026-09-14');

        AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee->id,
            'work_date' => '2026-09-14',
            'shift_name' => $assignment->shift_name,
            'status' => AttendanceStatus::Leave,
        ]);

        $this->actingAs($this->employee)
            ->from(route('attendance.board'))
            ->post(route('attendance.check-in'), $this->fix(self::INSIDE_LAT))
            ->assertSessionHasErrors('gps');

        $this->assertSame(1, AttendanceRecord::query()->count());
        $this->assertSame(AttendanceStatus::Leave, AttendanceRecord::query()->firstOrFail()->status);
    }

    public function test_a_check_in_writes_an_audit_entry(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 09:00:00'));
        $this->roster($this->employee, '2026-09-14');

        $this->actingAs($this->employee)->post(route('attendance.check-in'), $this->fix(self::INSIDE_LAT));

        $log = AttendanceRecord::query()->firstOrFail()->auditLogs()->firstOrFail();

        $this->assertSame($this->employee->id, $log->actor_id);
        $this->assertSame('check_in', $log->action->value);
        $this->assertSame(AttendanceStatus::Present->value, $log->after['status']);
    }

    /** Coordinates must not leak into the audit trail. */
    public function test_the_audit_entry_does_not_contain_coordinates(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 09:00:00'));
        $this->roster($this->employee, '2026-09-14');

        $this->actingAs($this->employee)->post(route('attendance.check-in'), $this->fix(self::INSIDE_LAT));

        $log = AttendanceRecord::query()->firstOrFail()->auditLogs()->firstOrFail();

        $this->assertArrayNotHasKey('check_in_latitude', $log->after);
        $this->assertArrayNotHasKey('check_in_longitude', $log->after);
        $this->assertStringNotContainsString((string) self::INSIDE_LAT, json_encode($log->after));
    }

    public function test_an_overnight_shift_rolls_the_planned_end_into_the_next_day(): void
    {
        $night = WorkShift::factory()->atBranch($this->branch)->overnight()->create(['name' => 'Ca đêm']);

        $this->travelTo(Carbon::parse('2026-09-14 21:00:00'));

        $assignment = ShiftAssignment::factory()
            ->forEmployee($this->employee)
            ->atBranch($this->branch)
            ->usingShift($night)
            ->on('2026-09-14')
            ->create();

        $this->assertSame('2026-09-14 21:00:00', $assignment->planned_start_at->toDateTimeString());
        $this->assertSame('2026-09-15 05:00:00', $assignment->planned_end_at->toDateTimeString());

        $this->actingAs($this->employee)
            ->post(route('attendance.check-in'), $this->fix(self::INSIDE_LAT))
            ->assertRedirect(route('attendance.board'));

        $record = AttendanceRecord::query()->firstOrFail();

        $this->assertSame(AttendanceStatus::Present, $record->status);
        // The row belongs to the business date it started on.
        $this->assertSame('2026-09-14', $record->work_date->toDateString());

        // Closing it after midnight is still on time, not overtime.
        $this->travelTo(Carbon::parse('2026-09-15 04:50:00'));

        $this->actingAs($this->employee)
            ->post(route('attendance.check-out'), $this->fix(self::INSIDE_LAT))
            ->assertRedirect(route('attendance.board'));

        $record->refresh();

        $this->assertSame('2026-09-15 04:50:00', $record->checked_out_at->toDateTimeString());
        $this->assertSame(0, $record->overtime_minutes);
        $this->assertSame(OvertimeStatus::None, $record->overtime_status);
    }

    /** @return array<string, string|float> */
    private function fix(?float $latitude = null, float $accuracy = 15): array
    {
        return [
            'latitude' => $latitude ?? self::SHOP_LAT,
            'longitude' => self::SHOP_LNG,
            'accuracy' => $accuracy,
        ];
    }

    private function roster(User $employee, string $workDate): ShiftAssignment
    {
        return ShiftAssignment::factory()
            ->forEmployee($employee)
            ->atBranch($this->branch)
            ->usingShift($this->shift)
            ->on($workDate)
            ->create();
    }
}
