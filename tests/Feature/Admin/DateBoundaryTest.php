<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\AttendanceStatus;
use App\Enums\InventoryMovementType;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Dates that cannot happen, and dates that should not be typed by hand.
 *
 * Two different problems share this shape. A booking in the past or five years
 * out is a typo, and catching it saves somebody a phone call. A transaction
 * backdated into a closed period is something else: that is how a figure is
 * moved into a month whose numbers have already been agreed.
 */
class DateBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00'));

        $this->branch = Branch::factory()->create();
        $this->manager = User::factory()->manager()->atBranch($this->branch)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_an_appointment_cannot_be_booked_in_the_past(): void
    {
        $this->actingAs($this->manager)
            ->from(route('admin.appointments.create'))
            ->post(route('admin.appointments.store'), $this->appointment([
                'starts_at' => '2026-09-10 09:00:00',
            ]))
            ->assertSessionHasErrors('starts_at');
    }

    /** A booking years away is a typo, not a plan. */
    public function test_an_appointment_cannot_be_booked_absurdly_far_ahead(): void
    {
        $this->actingAs($this->manager)
            ->from(route('admin.appointments.create'))
            ->post(route('admin.appointments.store'), $this->appointment([
                'starts_at' => '2031-09-12 09:00:00',
            ]))
            ->assertSessionHasErrors('starts_at');
    }

    public function test_a_normal_appointment_is_accepted(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.appointments.store'), $this->appointment([
                'starts_at' => '2026-09-13 09:00:00',
            ]))
            ->assertSessionHasNoErrors();
    }

    /** Editing an old appointment must stay possible; the rule is for new ones. */
    public function test_an_existing_past_appointment_can_still_be_edited(): void
    {
        $appointment = Appointment::factory()->create([
            'branch_id' => $this->branch->getKey(),
            'starts_at' => '2026-09-01 09:00:00',
            'status' => AppointmentStatus::Completed,
        ]);

        $this->actingAs($this->manager)
            ->put(route('admin.appointments.update', $appointment), $this->appointment([
                'starts_at' => '2026-09-01 09:00:00',
                'status' => AppointmentStatus::Completed->value,
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_a_cash_entry_cannot_be_backdated_beyond_the_allowed_window(): void
    {
        $this->actingAs($this->manager)
            ->from(route('admin.cash.create'))
            ->post(route('admin.cash.store'), [
                'type' => 'expense',
                'category' => 'rent',
                'amount' => 500000,
                'occurred_at' => '2026-06-01 09:00:00',
                'note' => 'Ghi bu tien thue thang 6',
            ])
            ->assertSessionHasErrors('occurred_at');
    }

    public function test_a_cash_entry_within_the_window_is_accepted(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.cash.store'), [
                'type' => 'expense',
                'category' => 'rent',
                'amount' => 500000,
                'occurred_at' => '2026-09-10 09:00:00',
                'note' => 'Ghi bu tien thue',
            ])
            ->assertSessionHasNoErrors();
    }

    /** A transaction dated in the future has not happened yet. */
    public function test_a_cash_entry_cannot_be_dated_in_the_future(): void
    {
        $this->actingAs($this->manager)
            ->from(route('admin.cash.create'))
            ->post(route('admin.cash.store'), [
                'type' => 'expense',
                'category' => 'rent',
                'amount' => 500000,
                'occurred_at' => '2026-09-20 09:00:00',
                'note' => 'Chi truoc cho thang sau',
            ])
            ->assertSessionHasErrors('occurred_at');
    }

    public function test_a_stock_movement_cannot_be_dated_in_the_future(): void
    {
        $this->actingAs($this->manager)
            ->from(route('admin.inventory.create'))
            ->post(route('admin.inventory.store'), [
                'product_id' => Product::factory()->create()->getKey(),
                'type' => InventoryMovementType::In->value,
                'quantity' => 5,
                'occurred_at' => '2026-09-20 09:00:00',
            ])
            ->assertSessionHasErrors('occurred_at');
    }

    public function test_attendance_cannot_be_recorded_for_a_future_day(): void
    {
        $employee = User::factory()->employee()->atBranch($this->branch)->create();

        $this->actingAs($this->manager)
            ->from(route('admin.attendance.index'))
            ->post(route('admin.attendance.store'), [
                'employee_id' => $employee->getKey(),
                'work_date' => '2026-09-20',
                'shift_name' => 'Ca sang',
                'shift_value' => 1,
                'status' => AttendanceStatus::Present->value,
                'reason' => 'Nhap truoc cho tuan sau',
            ])
            ->assertSessionHasErrors('work_date');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function appointment(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Khach le',
            'customer_phone' => '0900000000',
            'duration_minutes' => 60,
            'status' => AppointmentStatus::Pending->value,
            'service_ids' => [Service::factory()->create()->getKey()],
        ], $overrides);
    }
}
