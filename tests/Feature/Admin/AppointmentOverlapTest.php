<?php

namespace Tests\Feature\Admin;

use App\Actions\Appointments\SaveAppointmentAction;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\BranchFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AppointmentOverlapTest extends TestCase
{
    use RefreshDatabase;

    private function action(): SaveAppointmentAction
    {
        return app(SaveAppointmentAction::class);
    }

    /** @return array<string, mixed> */
    private function payload(User $employee, string $startsAt, int $duration): array
    {
        return [
            'branch_id' => BranchFactory::resolveId(),
            'customer_name' => 'Khách thử',
            'customer_phone' => '0900000001',
            'employee_id' => $employee->id,
            'starts_at' => $startsAt,
            'duration_minutes' => $duration,
            'status' => AppointmentStatus::Confirmed->value,
        ];
    }

    public function test_ends_at_is_kept_in_sync_with_duration(): void
    {
        $employee = User::factory()->employee()->create();

        $appointment = $this->action()->handle($this->payload($employee, '2026-10-01 09:00:00', 90));

        $this->assertSame('2026-10-01 10:30:00', $appointment->ends_at->format('Y-m-d H:i:s'));

        $this->action()->handle($this->payload($employee, '2026-10-01 09:00:00', 30), $appointment);

        $this->assertSame('2026-10-01 09:30:00', $appointment->fresh()->ends_at->format('Y-m-d H:i:s'));
    }

    public function test_an_overlapping_slot_for_the_same_employee_is_rejected(): void
    {
        $employee = User::factory()->employee()->create();
        $this->action()->handle($this->payload($employee, '2026-10-01 09:00:00', 60));

        $this->expectException(ValidationException::class);

        $this->action()->handle($this->payload($employee, '2026-10-01 09:30:00', 60));
    }

    public function test_back_to_back_slots_are_allowed(): void
    {
        $employee = User::factory()->employee()->create();
        $this->action()->handle($this->payload($employee, '2026-10-01 09:00:00', 60));
        $this->action()->handle($this->payload($employee, '2026-10-01 10:00:00', 60));

        $this->assertSame(2, Appointment::query()->count());
    }

    public function test_a_cancelled_appointment_frees_the_slot(): void
    {
        $employee = User::factory()->employee()->create();
        $first = $this->action()->handle($this->payload($employee, '2026-10-01 09:00:00', 60));
        $first->update(['status' => AppointmentStatus::Cancelled]);

        $second = $this->action()->handle($this->payload($employee, '2026-10-01 09:00:00', 60));

        $this->assertTrue($second->exists);
    }

    public function test_another_employee_may_take_the_same_slot(): void
    {
        $first = User::factory()->employee()->create();
        $second = User::factory()->employee()->create();

        $this->action()->handle($this->payload($first, '2026-10-01 09:00:00', 60));
        $this->action()->handle($this->payload($second, '2026-10-01 09:00:00', 60));

        $this->assertSame(2, Appointment::query()->count());
    }

    public function test_editing_an_appointment_does_not_clash_with_itself(): void
    {
        $employee = User::factory()->employee()->create();
        $appointment = $this->action()->handle($this->payload($employee, '2026-10-01 09:00:00', 60));

        $updated = $this->action()->handle($this->payload($employee, '2026-10-01 09:15:00', 60), $appointment);

        $this->assertSame('2026-10-01 09:15:00', $updated->starts_at->format('Y-m-d H:i:s'));
    }

    public function test_the_overlap_scope_uses_portable_sql(): void
    {
        $employee = User::factory()->employee()->create();
        $this->action()->handle($this->payload($employee, '2026-10-01 09:00:00', 60));

        $sql = Appointment::query()
            ->active()
            ->where('employee_id', $employee->id)
            ->overlapping(Carbon::parse('2026-10-01 09:30:00'), Carbon::parse('2026-10-01 10:30:00'))
            ->toSql();

        $this->assertStringNotContainsString('datetime(', $sql);
        $this->assertStringNotContainsString('||', $sql);
        $this->assertStringContainsString('"starts_at" <', $sql);
        $this->assertStringContainsString('"ends_at" >', $sql);
    }

    public function test_the_admin_form_reports_a_conflict_to_the_user(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->employee()->create();
        $this->action()->handle($this->payload($employee, '2026-10-01 09:00:00', 60));

        $this->actingAs($owner)
            ->from(route('admin.appointments.create'))
            ->post(route('admin.appointments.store'), $this->payload($employee, '2026-10-01 09:30:00', 60))
            ->assertRedirect(route('admin.appointments.create'))
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(1, Appointment::query()->count());
    }
}
