<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
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
}
