<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeInvoiceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_employee_can_only_see_invoices_for_appointments_they_care_for(): void
    {
        $branch = Branch::factory()->create();
        $employee = User::factory()->employee()->withoutBranch()->atBranch($branch)->create();
        $colleague = User::factory()->employee()->withoutBranch()->atBranch($branch)->create();

        $assignedAppointment = Appointment::factory()->create([
            'branch_id' => $branch->id,
            'employee_id' => $employee->id,
        ]);
        $assignedInvoice = Invoice::factory()->create([
            'branch_id' => $branch->id,
            'appointment_id' => $assignedAppointment->id,
            'customer_name' => 'Khách của tôi',
        ]);

        $otherAppointment = Appointment::factory()->create([
            'branch_id' => $branch->id,
            'employee_id' => $colleague->id,
        ]);
        $otherInvoice = Invoice::factory()->create([
            'branch_id' => $branch->id,
            'appointment_id' => $otherAppointment->id,
            'customer_name' => 'Khách của đồng nghiệp',
        ]);

        $this->actingAs($employee)
            ->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSee($assignedInvoice->number)
            ->assertDontSee($otherInvoice->number);

        $this->actingAs($employee)
            ->get(route('admin.invoices.edit', $assignedInvoice))
            ->assertOk();

        $this->actingAs($employee)
            ->get(route('admin.invoices.edit', $otherInvoice))
            ->assertForbidden();
    }

    public function test_a_care_employee_can_edit_and_pay_only_their_own_draft_invoice(): void
    {
        Storage::fake('local');
        $branch = Branch::factory()->create();
        $employee = User::factory()->employee()->withoutBranch()->atBranch($branch)->create();
        $colleague = User::factory()->employee()->withoutBranch()->atBranch($branch)->create();

        $appointment = Appointment::factory()->create([
            'branch_id' => $branch->id,
            'employee_id' => $employee->id,
        ]);
        $invoice = Invoice::factory()->create([
            'branch_id' => $branch->id,
            'appointment_id' => $appointment->id,
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'employee_id' => $employee->id,
            'unit_price' => 150000,
            'line_total' => 150000,
        ]);

        $otherAppointment = Appointment::factory()->create([
            'branch_id' => $branch->id,
            'employee_id' => $colleague->id,
        ]);
        $otherInvoice = Invoice::factory()->create([
            'branch_id' => $branch->id,
            'appointment_id' => $otherAppointment->id,
        ]);

        $this->actingAs($employee)->withConfirmedPassword()
            ->patch(route('admin.invoices.update', $invoice), [
                'discount' => 0,
                'note' => 'Đã đối chiếu với khách.',
            ])
            ->assertRedirect();

        $this->assertSame('Đã đối chiếu với khách.', $invoice->fresh()->note);

        $this->actingAs($employee)->withConfirmedPassword()
            ->post(route('admin.invoices.pay', $otherInvoice), [
                'payment_method' => PaymentMethod::Cash->value,
                'payment_proof_image' => UploadedFile::fake()->image('other-payment-proof.jpg'),
            ])
            ->assertForbidden();

        $this->actingAs($employee)->withConfirmedPassword()
            ->post(route('admin.invoices.pay', $invoice), [
                'payment_method' => PaymentMethod::Cash->value,
                'payment_proof_image' => UploadedFile::fake()->image('payment-proof.jpg'),
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertTrue($invoice->qualified_for_bill_kpi);
        $this->assertSame($employee->id, $invoice->bill_kpi_verified_by);
        $this->assertNotNull($invoice->bill_image_path);
        $this->assertTrue(Storage::disk('local')->exists($invoice->bill_image_path));
    }
}
