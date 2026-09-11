<?php

namespace App\Http\Controllers;

use App\Actions\Appointments\SaveAppointmentAction;
use App\Enums\AppointmentStatus;
use App\Models\Branch;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function store(Request $request, SaveAppointmentAction $saveAppointment): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', Rule::exists(Branch::class, 'id')->where('is_active', true)],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'employee_id' => ['nullable', Rule::exists(User::class, 'id')->where('is_active', true)],
            'starts_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => [Rule::exists(Service::class, 'id')->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'branch_id' => 'chi nhánh',
            'customer_name' => 'tên khách hàng',
            'customer_phone' => 'số điện thoại',
            'starts_at' => 'thời gian',
        ]);

        $saveAppointment->handle($validated + ['status' => AppointmentStatus::Pending->value]);

        return redirect()->route('home')
            ->with('booking_success', 'Tiệm đã nhận lịch hẹn của bạn và sẽ sớm xác nhận.');
    }
}
