<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nobody writes their own hours by hand.
 *
 * Attendance feeds pay, so a manager who can create, amend or delete their own
 * shift is writing their own payslip. Approving your own overtime was already
 * blocked; the same reasoning has to cover the record itself, otherwise the
 * approval gate is simply walked around by editing the row instead.
 */
class AttendanceSelfDealingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->manager = User::factory()->manager()->atBranch($this->branch)->create();
    }

    public function test_a_manager_may_not_create_attendance_for_themselves(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.attendance.store'), $this->payload($this->manager))
            ->assertForbidden();

        $this->assertDatabaseMissing('attendance_records', ['employee_id' => $this->manager->getKey()]);
    }

    public function test_a_manager_may_not_edit_their_own_shift(): void
    {
        $record = $this->shiftFor($this->manager);

        $this->actingAs($this->manager)
            ->put(route('admin.attendance.update', $record), $this->payload($this->manager, [
                'shift_value' => 2,
                'reason' => 'Tu sua ca cua chinh minh',
            ]))
            ->assertForbidden();

        $this->assertSame('1.00', $record->fresh()->shift_value);
    }

    public function test_a_manager_may_not_delete_their_own_shift(): void
    {
        $record = $this->shiftFor($this->manager);

        $this->actingAs($this->manager)
            ->delete(route('admin.attendance.destroy', $record), ['reason' => 'Tu xoa ca cua chinh minh'])
            ->assertForbidden();

        $this->assertDatabaseHas('attendance_records', ['id' => $record->getKey()]);
    }

    public function test_a_manager_may_still_manage_somebody_elses_shift(): void
    {
        $employee = User::factory()->employee()->atBranch($this->branch)->create();

        $this->actingAs($this->manager)
            ->post(route('admin.attendance.store'), $this->payload($employee))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attendance_records', ['employee_id' => $employee->getKey()]);
    }

    /** A second manager is a valid reviewer of the first one's hours. */
    public function test_another_manager_may_record_the_first_managers_shift(): void
    {
        $second = User::factory()->manager()->atBranch($this->branch)->create();

        $this->actingAs($second)
            ->post(route('admin.attendance.store'), $this->payload($this->manager))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attendance_records', ['employee_id' => $this->manager->getKey()]);
    }

    /**
     * The owner is the one account that can be alone in the company, so a hard
     * block would lock them out of their own records entirely. They keep the
     * ability, but the entry is flagged as self-recorded for review.
     */
    public function test_an_owner_recording_their_own_shift_is_flagged(): void
    {
        $owner = User::factory()->owner()->atBranch($this->branch)->create();

        $this->actingAs($owner)
            ->post(route('admin.attendance.store'), $this->payload($owner))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attendance_records', [
            'employee_id' => $owner->getKey(),
            'is_self_recorded' => true,
        ]);
    }

    /** A shift written for somebody else is not flagged. */
    public function test_a_shift_written_for_somebody_else_is_not_flagged(): void
    {
        $employee = User::factory()->employee()->atBranch($this->branch)->create();

        $this->actingAs($this->manager)
            ->post(route('admin.attendance.store'), $this->payload($employee))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attendance_records', [
            'employee_id' => $employee->getKey(),
            'is_self_recorded' => false,
        ]);
    }

    /** The review queue has to surface self-recorded rows, or the flag is decoration. */
    public function test_the_review_queue_highlights_self_recorded_rows(): void
    {
        $owner = User::factory()->owner()->atBranch($this->branch)->create();

        $this->actingAs($owner)->post(route('admin.attendance.store'), $this->payload($owner));

        $this->actingAs($owner)
            ->get(route('admin.attendance.review'))
            ->assertOk()
            ->assertSee('Tự chấm');
    }

    private function shiftFor(User $employee): AttendanceRecord
    {
        return AttendanceRecord::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'employee_id' => $employee->getKey(),
            'work_date' => now()->toDateString(),
            'shift_name' => 'Ca chinh',
            'shift_value' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(User $employee, array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $employee->getKey(),
            'work_date' => now()->toDateString(),
            'shift_name' => 'Ca chinh',
            'shift_value' => 1,
            'status' => AttendanceStatus::Present->value,
            'reason' => 'Nhap bu ca sang',
        ], $overrides);
    }
}
