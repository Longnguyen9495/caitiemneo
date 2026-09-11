<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\BranchService;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Notifications\NewOnlineBookingNotification;
use Carbon\Carbon;
use Database\Factories\BranchFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
            now()->addDays(2)->startOfHour()->format('Y-m-d\TH:i'),
            ['service_ids' => [$service->id]],
        ))->assertRedirect(route('home'))->assertSessionHas('booking_success');

        $appointment = Appointment::query()->firstOrFail();

        $this->assertSame(AppointmentStatus::Pending, $appointment->status);
        $this->assertNotNull($appointment->ends_at);
        $this->assertSame(60, (int) $appointment->starts_at->diffInMinutes($appointment->ends_at));
        $this->assertSame(1, $appointment->services()->count());
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_an_online_booking_notifies_active_owners_and_managers_only(): void
    {
        Notification::fake();

        $employee = User::factory()->employee()->create();
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->manager()->create();
        $inactiveManager = User::factory()->manager()->inactive()->create();
        $staffMember = User::factory()->employee()->create();

        $this->post(route('booking.store'), $this->payload(
            $employee,
            now()->addDays(2)->startOfHour()->format('Y-m-d\TH:i'),
        ))->assertRedirect(route('home'));

        Notification::assertSentTo([$owner, $manager], NewOnlineBookingNotification::class);
        Notification::assertNotSentTo([$inactiveManager, $staffMember], NewOnlineBookingNotification::class);
    }

    public function test_the_public_form_rejects_a_slot_the_employee_already_holds(): void
    {
        $employee = User::factory()->employee()->create();
        $startsAt = now()->addDays(2)->startOfHour();

        $this->post(route('booking.store'), $this->payload($employee, $startsAt->format('Y-m-d\TH:i')));

        $this->from(route('home'))
            ->post(route('booking.store'), $this->payload($employee, $startsAt->copy()->addMinutes(30)->format('Y-m-d\TH:i')))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(1, Appointment::query()->count());
    }

    public function test_an_invalid_online_booking_does_not_send_a_notification(): void
    {
        Notification::fake();

        $employee = User::factory()->employee()->create();
        User::factory()->owner()->create();

        $this->from(route('home'))
            ->post(route('booking.store'), $this->payload($employee, now()->subHour()->format('Y-m-d\TH:i')))
            ->assertSessionHasErrors('starts_at');

        Notification::assertNothingSent();
    }

    public function test_a_datetime_local_booking_is_saved_as_vietnam_time(): void
    {
        $employee = User::factory()->employee()->create();
        $startsAt = Carbon::create(2026, 9, 15, 10, 30, 0, 'Asia/Ho_Chi_Minh');

        $this->travelTo(Carbon::create(2026, 9, 11, 9, 0, 0, 'UTC'));

        $this->post(route('booking.store'), $this->payload($employee, '2026-09-15T10:30'))
            ->assertRedirect(route('home'));

        $appointment = Appointment::query()->firstOrFail();

        $this->assertSame($startsAt->utc()->format('Y-m-d H:i:s'), $appointment->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-15 10:30', $appointment->starts_at->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i'));
    }

    public function test_a_booking_in_the_past_is_rejected(): void
    {
        $employee = User::factory()->employee()->create();

        $this->from(route('home'))
            ->post(route('booking.store'), $this->payload($employee, now()->subHour()->format('Y-m-d\TH:i')))
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_a_vietnam_local_time_in_the_past_is_rejected_with_a_clear_message(): void
    {
        $employee = User::factory()->employee()->create();

        $this->travelTo(Carbon::create(2026, 9, 11, 9, 0, 0, 'UTC'));

        $this->from(route('home'))
            ->post(route('booking.store'), $this->payload($employee, '2026-09-11T15:30'))
            ->assertSessionHasErrors([
                'starts_at' => 'Thời gian đặt lịch phải ở trong tương lai theo giờ Việt Nam.',
            ]);

        $this->assertSame(0, Appointment::query()->count());
    }
}
