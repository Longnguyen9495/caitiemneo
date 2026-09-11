<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceAuditLog;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The demo data builder is a developer tool, but it writes to the same tables
 * as the real flow, so it has to keep producing rows the app can render.
 */
class AttendanceDemoCommandTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'CN-DEMO']);

        WorkShift::factory()->shared()->spanning('09:00:00', '19:00:00')->create(['name' => 'Ca 09:00 – 19:00']);
        WorkShift::factory()->shared()->spanning('10:00:00', '20:00:00')->create(['name' => 'Ca 10:00 – 20:00']);
        WorkShift::factory()->shared()->spanning('09:00:00', '20:00:00')->create(['name' => 'Ca dài', 'shift_value' => 1.1]);

        User::factory()->manager()->withoutBranch()->atBranch($this->branch)->create(['name' => 'Quản lý demo']);
        User::factory()->employee()->withoutBranch()->atBranch($this->branch)->create(['name' => 'Nhân viên demo']);
    }

    public function test_it_builds_a_roster_and_past_attendance(): void
    {
        $this->artisan('attendance:demo', ['--weeks' => 2, '--ahead' => 1, '--force' => true])
            ->assertExitCode(0);

        $this->assertGreaterThan(0, ShiftAssignment::query()->count());
        $this->assertGreaterThan(0, AttendanceRecord::query()->count());
        $this->assertGreaterThan(0, AttendanceAuditLog::query()->count());

        // Today is rostered but deliberately left unclocked so the board still
        // offers the Vào ca button.
        $this->assertGreaterThan(0, ShiftAssignment::query()->whereDate('work_date', today())->count());
        $this->assertSame(0, AttendanceRecord::query()->whereDate('work_date', today())->count());

        // Nothing in the future is pre-clocked either.
        $this->assertSame(0, AttendanceRecord::query()->whereDate('work_date', '>', today())->count());
    }

    public function test_every_shift_it_rosters_is_one_per_person_per_day(): void
    {
        $this->artisan('attendance:demo', ['--weeks' => 2, '--force' => true])->assertExitCode(0);

        $duplicates = ShiftAssignment::query()
            ->selectRaw('employee_id, work_date, count(*) as total')
            ->groupBy('employee_id', 'work_date')
            ->havingRaw('count(*) > 1')
            ->get();

        $this->assertCount(0, $duplicates, 'Mỗi nhân viên chỉ được một ca mỗi ngày.');
    }

    /** The shop does not tolerate lateness, so late rows must stay rare. */
    public function test_most_shifts_are_on_time(): void
    {
        $this->artisan('attendance:demo', ['--weeks' => 3, '--force' => true])->assertExitCode(0);

        $total = AttendanceRecord::query()->count();
        $present = AttendanceRecord::query()->where('status', AttendanceStatus::Present->value)->count();

        $this->assertGreaterThan(0, $total);
        $this->assertGreaterThan($total * 0.8, $present);
    }

    public function test_it_is_deterministic_so_a_rerun_reproduces_the_same_data(): void
    {
        $this->artisan('attendance:demo', ['--weeks' => 2, '--force' => true])->assertExitCode(0);
        $first = AttendanceRecord::query()->orderBy('work_date')->orderBy('employee_id')
            ->get(['employee_id', 'work_date', 'status', 'late_minutes', 'overtime_minutes'])->toJson();

        $this->artisan('attendance:demo', ['--weeks' => 2, '--force' => true])->assertExitCode(0);
        $second = AttendanceRecord::query()->orderBy('work_date')->orderBy('employee_id')
            ->get(['employee_id', 'work_date', 'status', 'late_minutes', 'overtime_minutes'])->toJson();

        $this->assertSame($first, $second);
    }

    public function test_clear_removes_everything_it_made(): void
    {
        $this->artisan('attendance:demo', ['--weeks' => 2, '--force' => true])->assertExitCode(0);
        $this->assertGreaterThan(0, ShiftAssignment::query()->count());

        $this->artisan('attendance:demo', ['--weeks' => 2, '--clear' => true, '--force' => true])->assertExitCode(0);

        $this->assertSame(0, ShiftAssignment::query()->count());
        $this->assertSame(0, AttendanceRecord::query()->count());
        $this->assertSame(0, AttendanceAuditLog::query()->count());
    }

    public function test_approved_overtime_never_exceeds_what_was_detected(): void
    {
        $this->artisan('attendance:demo', ['--weeks' => 3, '--force' => true])->assertExitCode(0);

        $invalid = AttendanceRecord::query()
            ->whereColumn('approved_overtime_minutes', '>', 'overtime_minutes')
            ->count();

        $this->assertSame(0, $invalid);
    }
}
