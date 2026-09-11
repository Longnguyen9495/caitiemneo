<?php

namespace Tests\Feature\Branch;

use App\Actions\Appointments\SaveAppointmentAction;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BranchAppointmentTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;

    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchA = Branch::factory()->create(['code' => 'CN-A']);
        $this->branchB = Branch::factory()->create(['code' => 'CN-B']);
    }

    private function action(): SaveAppointmentAction
    {
        return app(SaveAppointmentAction::class);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(Branch $branch, ?User $employee, string $startsAt, array $extra = []): array
    {
        return array_merge([
            'branch_id' => $branch->id,
            'customer_name' => 'Khách thử',
            'customer_phone' => '0900000001',
            'employee_id' => $employee?->id,
            'starts_at' => $startsAt,
            'duration_minutes' => 60,
            'status' => AppointmentStatus::Confirmed->value,
        ], $extra);
    }

    public function test_an_employee_can_only_be_booked_where_they_are_posted(): void
    {
        $employee = User::factory()->employee()->withoutBranch()->atBranch($this->branchA)->create();

        $appointment = $this->action()->handle($this->payload($this->branchA, $employee, '2026-10-01 09:00:00'));
        $this->assertSame($this->branchA->id, $appointment->branch_id);

        $this->expectException(ValidationException::class);
        $this->action()->handle($this->payload($this->branchB, $employee, '2026-10-02 09:00:00'));
    }

    public function test_the_posting_is_checked_against_the_appointment_date_not_today(): void
    {
        $employee = User::factory()->employee()->withoutBranch()
            ->atBranch($this->branchA, true, '2026-01-01', '2026-06-30')
            ->atBranch($this->branchB, true, '2026-07-01')
            ->create();

        $inA = $this->action()->handle($this->payload($this->branchA, $employee, '2026-05-10 09:00:00'));
        $inB = $this->action()->handle($this->payload($this->branchB, $employee, '2026-08-10 09:00:00'));

        $this->assertSame($this->branchA->id, $inA->branch_id);
        $this->assertSame($this->branchB->id, $inB->branch_id);

        $this->expectException(ValidationException::class);
        $this->action()->handle($this->payload($this->branchB, $employee, '2026-05-11 09:00:00'));
    }

    public function test_one_employee_cannot_hold_overlapping_slots_in_two_branches(): void
    {
        $employee = User::factory()->employee()->withoutBranch()
            ->atBranch($this->branchA)
            ->atBranch($this->branchB, false)
            ->create();

        $this->action()->handle($this->payload($this->branchA, $employee, '2026-10-01 09:00:00'));

        $this->expectException(ValidationException::class);
        $this->action()->handle($this->payload($this->branchB, $employee, '2026-10-01 09:30:00'));
    }

    public function test_the_service_price_is_snapshotted_from_the_branch_catalogue(): void
    {
        $employee = User::factory()->employee()->withoutBranch()->atBranch($this->branchA)->create();
        $service = Service::factory()->create(['price' => 200000]);

        BranchService::factory()->create([
            'branch_id' => $this->branchA->id,
            'service_id' => $service->id,
            'price' => 260000,
            'duration_minutes' => 75,
        ]);

        BranchService::factory()->create([
            'branch_id' => $this->branchB->id,
            'service_id' => $service->id,
            'price' => 310000,
        ]);

        $appointment = $this->action()->handle(
            $this->payload($this->branchA, $employee, '2026-10-01 09:00:00', ['service_ids' => [$service->id]])
        );

        $this->assertSame('260000.00', $appointment->services()->value('price'));
    }

    public function test_an_invoice_inherits_the_branch_of_its_appointment(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->employee()->withoutBranch()->atBranch($this->branchB)->create();
        $service = Service::factory()->create(['price' => 150000]);

        BranchService::factory()->create([
            'branch_id' => $this->branchB->id,
            'service_id' => $service->id,
            'price' => 150000,
        ]);

        $appointment = $this->action()->handle($this->payload(
            $this->branchB,
            $employee,
            '2026-10-01 09:00:00',
            ['status' => AppointmentStatus::Completed->value, 'service_ids' => [$service->id]],
        ));

        $this->actingAs($owner)->post(route('admin.appointments.convert-to-invoice', $appointment))->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame($this->branchB->id, $invoice->branch_id);
        $this->assertStringContainsString('CNB', $invoice->number);
        $this->assertSame('150000.00', $invoice->total);
    }

    public function test_an_appointment_with_an_invoice_cannot_change_branch(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->employee()->withoutBranch()
            ->atBranch($this->branchA)
            ->atBranch($this->branchB, false)
            ->create();

        $appointment = $this->action()->handle($this->payload(
            $this->branchA,
            $employee,
            '2026-10-01 09:00:00',
            ['status' => AppointmentStatus::Completed->value],
        ));

        $this->actingAs($owner)->post(route('admin.appointments.convert-to-invoice', $appointment));

        $this->expectException(ValidationException::class);

        $this->action()->handle(
            $this->payload($this->branchB, $employee, '2026-10-01 09:00:00'),
            $appointment->fresh(),
        );
    }

    public function test_the_appointment_list_only_shows_the_active_branch(): void
    {
        $manager = User::factory()->manager()->withoutBranch()->atBranch($this->branchA)->create();

        Appointment::factory()->create([
            'branch_id' => $this->branchA->id,
            'customer_name' => 'Khách chi nhánh A',
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => '2026-10-01 10:00:00',
        ]);

        Appointment::factory()->create([
            'branch_id' => $this->branchB->id,
            'customer_name' => 'Khách chi nhánh B',
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => '2026-10-01 10:00:00',
        ]);

        $this->actingAs($manager)
            ->get(route('admin.appointments.index', ['date' => '2026-10-01']))
            ->assertOk()
            ->assertSee('Khách chi nhánh A')
            ->assertDontSee('Khách chi nhánh B');
    }
}
