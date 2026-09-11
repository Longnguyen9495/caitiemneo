<?php

namespace Tests\Feature\Attendance;

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

class GpsCheckOutTest extends TestCase
{
    use RefreshDatabase;

    private const SHOP_LAT = 10.7769000;

    private const SHOP_LNG = 106.7009000;

    private const INSIDE_LAT = 10.7777000;

    private const OUTSIDE_LAT = 10.7791000;

    private Branch $branch;

    private User $employee;

    private WorkShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create([
            'code' => 'CN-OUT',
            'latitude' => self::SHOP_LAT,
            'longitude' => self::SHOP_LNG,
            'attendance_radius_meters' => 100,
            'attendance_accuracy_limit_meters' => 150,
            'gps_attendance_enabled' => true,
        ]);

        $this->employee = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
        $this->shift = WorkShift::factory()->atBranch($this->branch)->spanning('09:00:00', '19:00:00')->create(['name' => 'Ca chính']);
    }

    public function test_clocking_out_without_an_open_shift_is_refused(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 19:00:00'));

        $this->actingAs($this->employee)
            ->from(route('attendance.board'))
            ->post(route('attendance.check-out'), $this->fix(self::INSIDE_LAT))
            ->assertSessionHasErrors(['gps' => 'Bạn chưa vào ca nên chưa thể ra ca.']);
    }

    public function test_a_valid_check_out_stores_the_server_time_and_the_gps_evidence(): void
    {
        $record = $this->workingSince('2026-09-14 09:00:00');

        $this->travelTo(Carbon::parse('2026-09-14 18:45:00'));

        $this->actingAs($this->employee)
            ->post(route('attendance.check-out'), $this->fix(self::INSIDE_LAT, accuracy: 18))
            ->assertRedirect(route('attendance.board'));

        $record->refresh();

        $this->assertSame('2026-09-14 18:45:00', $record->checked_out_at->toDateTimeString());
        $this->assertSame(GpsVerification::Verified, $record->check_out_verification);
        $this->assertSame(18, $record->check_out_accuracy_meters);
        $this->assertNotNull($record->check_out_distance_meters);
        $this->assertSame(0, $record->overtime_minutes);
        $this->assertSame(OvertimeStatus::None, $record->overtime_status);
    }

    public function test_a_check_out_from_outside_the_radius_is_refused(): void
    {
        $record = $this->workingSince('2026-09-14 09:00:00');

        $this->travelTo(Carbon::parse('2026-09-14 18:45:00'));

        $this->actingAs($this->employee)
            ->from(route('attendance.board'))
            ->post(route('attendance.check-out'), $this->fix(self::OUTSIDE_LAT))
            ->assertSessionHasErrors('gps');

        $this->assertNull($record->fresh()->checked_out_at);
    }

    public function test_the_same_shift_cannot_be_clocked_out_of_twice(): void
    {
        $record = $this->workingSince('2026-09-14 09:00:00');

        $this->travelTo(Carbon::parse('2026-09-14 18:45:00'));
        $this->actingAs($this->employee)->post(route('attendance.check-out'), $this->fix(self::INSIDE_LAT));

        $this->travelTo(Carbon::parse('2026-09-14 19:30:00'));
        $this->actingAs($this->employee)
            ->from(route('attendance.board'))
            ->post(route('attendance.check-out'), $this->fix(self::INSIDE_LAT))
            ->assertSessionHasErrors('gps');

        $this->assertSame('2026-09-14 18:45:00', $record->fresh()->checked_out_at->toDateTimeString());
    }

    public function test_working_past_the_planned_end_records_pending_overtime(): void
    {
        $record = $this->workingSince('2026-09-14 09:00:00');

        $this->travelTo(Carbon::parse('2026-09-14 20:25:00'));

        $this->actingAs($this->employee)
            ->post(route('attendance.check-out'), $this->fix(self::INSIDE_LAT))
            ->assertRedirect(route('attendance.board'));

        $record->refresh();

        $this->assertSame(85, $record->overtime_minutes);
        $this->assertSame(OvertimeStatus::Pending, $record->overtime_status);
        // Nothing is approved until a manager says so.
        $this->assertSame(0, $record->approved_overtime_minutes);
        $this->assertSame(0, $record->payableOvertimeMinutes());
    }

    public function test_a_check_out_writes_an_audit_entry(): void
    {
        $record = $this->workingSince('2026-09-14 09:00:00');

        $this->travelTo(Carbon::parse('2026-09-14 18:45:00'));
        $this->actingAs($this->employee)->post(route('attendance.check-out'), $this->fix(self::INSIDE_LAT));

        $log = $record->auditLogs()->firstOrFail();

        $this->assertSame('check_out', $log->action->value);
        $this->assertSame($this->employee->id, $log->actor_id);
        $this->assertNull($log->before['checked_out_at']);
        $this->assertSame('2026-09-14 18:45:00', $log->after['checked_out_at']);
    }

    public function test_one_employee_cannot_close_another_employees_open_shift(): void
    {
        $colleague = User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create();
        $theirRecord = $this->workingSince('2026-09-14 09:00:00');

        $this->travelTo(Carbon::parse('2026-09-14 18:45:00'));

        // The other employee has no open shift of their own, so there is
        // simply nothing for the endpoint to act on.
        $this->actingAs($colleague)
            ->from(route('attendance.board'))
            ->post(route('attendance.check-out'), $this->fix(self::INSIDE_LAT))
            ->assertSessionHasErrors('gps');

        $this->assertNull($theirRecord->fresh()->checked_out_at);
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

    /** Clock the employee in through the real endpoint, then return the row. */
    private function workingSince(string $moment): AttendanceRecord
    {
        $this->travelTo(Carbon::parse($moment));

        ShiftAssignment::factory()
            ->forEmployee($this->employee)
            ->atBranch($this->branch)
            ->usingShift($this->shift)
            ->on(Carbon::parse($moment)->toDateString())
            ->create();

        $this->actingAs($this->employee)
            ->post(route('attendance.check-in'), $this->fix(self::INSIDE_LAT))
            ->assertRedirect(route('attendance.board'));

        return AttendanceRecord::query()->where('employee_id', $this->employee->id)->firstOrFail();
    }
}
