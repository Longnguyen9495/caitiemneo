<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\BranchService;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Database\Factories\BranchFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(User $employee, string $startsAt, array $extra = []): array
    {
        return array_merge([
            'branch_id' => BranchFactory::resolveId(),
            'customer_name' => 'Khách online',
            'customer_phone' => '0911222333',
            'employee_id' => $employee->id,
            'starts_at' => $startsAt,
            'duration_minutes' => 60,
        ], $extra);
    }

    public function test_a_visitor_can_book_a_free_slot(): void
    {
        $employee = User::factory()->employee()->create();
        $service = Service::factory()->create();

        // The shop only books what its own catalogue offers.
        BranchService::factory()->create([
            'branch_id' => BranchFactory::resolveId(),
            'service_id' => $service->id,
            'price' => 250000,
        ]);

        $this->post(route('booking.store'), $this->payload(
            $employee,
            now()->addDays(2)->startOfHour()->format('Y-m-d H:i:s'),
            ['service_ids' => [$service->id]],
        ))->assertRedirect(route('home'))->assertSessionHas('booking_success');

        $appointment = Appointment::query()->firstOrFail();

        $this->assertSame(AppointmentStatus::Pending, $appointment->status);
        $this->assertNotNull($appointment->ends_at);
        $this->assertSame(60, (int) $appointment->starts_at->diffInMinutes($appointment->ends_at));
        $this->assertSame(1, $appointment->services()->count());
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_the_public_form_rejects_a_slot_the_employee_already_holds(): void
    {
        $employee = User::factory()->employee()->create();
        $startsAt = now()->addDays(2)->startOfHour();

        $this->post(route('booking.store'), $this->payload($employee, $startsAt->format('Y-m-d H:i:s')));

        $this->from(route('home'))
            ->post(route('booking.store'), $this->payload($employee, $startsAt->copy()->addMinutes(30)->format('Y-m-d H:i:s')))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(1, Appointment::query()->count());
    }

    public function test_a_booking_in_the_past_is_rejected(): void
    {
        $employee = User::factory()->employee()->create();

        $this->from(route('home'))
            ->post(route('booking.store'), $this->payload($employee, now()->subHour()->format('Y-m-d H:i:s')))
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(0, Appointment::query()->count());
    }
}
