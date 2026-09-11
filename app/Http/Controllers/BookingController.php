<?php

namespace App\Http\Controllers;

use App\Actions\Appointments\SaveAppointmentAction;
use App\Enums\AppointmentStatus;
use App\Models\Branch;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Closure;
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
            'starts_at' => [
                'bail',
                'required',
                'date_format:Y-m-d\\TH:i',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $startsAt = Carbon::createFromFormat('Y-m-d\\TH:i', (string) $value, 'Asia/Ho_Chi_Minh');

                    if ($startsAt->lessThanOrEqualTo(now('Asia/Ho_Chi_Minh'))) {
                        $fail('Thời gian đặt lịch phải ở trong tương lai theo giờ Việt Nam.');
                    }
                },
            ],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => [Rule::exists(Service::class, 'id')->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'starts_at.date_format' => 'Thời gian đặt lịch không hợp lệ.',
        ], [
            'branch_id' => 'chi nhánh',
            'customer_name' => 'tên khách hàng',
            'customer_phone' => 'số điện thoại',
            'starts_at' => 'thời gian',
        ]);

        $validated['starts_at'] = Carbon::createFromFormat(
            'Y-m-d\\TH:i',
            $validated['starts_at'],
            'Asia/Ho_Chi_Minh',
        )->utc();

        $saveAppointment->handle($validated + ['status' => AppointmentStatus::Pending->value]);

        return redirect()->route('home')
            ->with('booking_success', 'Tiệm đã nhận lịch hẹn của bạn và sẽ sớm xác nhận.');
    }
}
