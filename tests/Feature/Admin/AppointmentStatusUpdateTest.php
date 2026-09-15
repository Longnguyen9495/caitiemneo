<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authorized_manager_can_update_an_appointment_status(): void
    {
        $manager = User::factory()->manager()->create();
        $appointment = Appointment::factory()->create([
            'branch_id' => $manager->primaryBranchId(),
            'status' => AppointmentStatus::Pending,
        ]);

        $this->actingAs($manager)
            ->from(route('admin.appointments.index'))
            ->patch(route('admin.appointments.status', $appointment), [
                'status' => AppointmentStatus::Confirmed->value,
            ])
            ->assertRedirect(route('admin.appointments.index'))
            ->assertSessionHas('success', 'Đã cập nhật trạng thái lịch hẹn.');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::Confirmed->value,
        ]);
    }

    public function test_assigned_employee_can_view_and_update_only_their_appointment_status(): void
    {
        $employee = User::factory()->employee()->create(['can_manage_appointments' => false]);
        $colleague = User::factory()->employee()->create();
        $startsAt = Carbon::today()->setTime(10, 0);
        $assignedAppointment = Appointment::factory()->at($startsAt)->create([
            'branch_id' => $employee->primaryBranchId(),
            'employee_id' => $employee->id,
            'customer_name' => 'Khách được phân công',
            'status' => AppointmentStatus::Pending,
        ]);
        $otherAppointment = Appointment::factory()->at($startsAt->copy()->addHour())->create([
            'branch_id' => $employee->primaryBranchId(),
            'employee_id' => $colleague->id,
            'customer_name' => 'Khách của đồng nghiệp',
        ]);

        $this->actingAs($employee)
            ->get(route('admin.appointments.index', ['date' => $startsAt->toDateString()]))
            ->assertOk()
            ->assertSee('Khách được phân công')
            ->assertDontSee('Khách của đồng nghiệp');

        $this->actingAs($employee)
            ->patch(route('admin.appointments.status', $assignedAppointment), [
                'status' => AppointmentStatus::Confirmed->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Đã cập nhật trạng thái lịch hẹn.');

        $this->assertDatabaseHas('appointments', [
            'id' => $assignedAppointment->id,
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        $this->actingAs($employee)
            ->patch(route('admin.appointments.status', $otherAppointment), [
                'status' => AppointmentStatus::Confirmed->value,
            ])
            ->assertForbidden();
    }
}
