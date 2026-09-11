<?php

namespace App\Http\Controllers;

use App\Actions\Appointments\SaveAppointmentAction;
use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use App\Notifications\NewOnlineBookingNotification;
use App\Rules\InBranchCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function store(Request $request, SaveAppointmentAction $saveAppointment): RedirectResponse
    {
        // Chi nhánh được đọc trước để các rule sau soi đúng bảng giá của nó.
        $branchId = $request->integer('branch_id') ?: null;

        $validated = $request->validate([
            'branch_id' => ['required', Rule::exists(Branch::class, 'id')->where('is_active', true)],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'employee_id' => ['nullable', Rule::exists(User::class, 'id')->where('is_active', true)],
            'starts_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'service_ids' => ['nullable', 'array'],
            // Dịch vụ phải nằm trong bảng giá của **đúng chi nhánh khách chọn**.
            // Đây là bề mặt công khai nên mọi giá trị đều do người lạ gửi lên.
            'service_ids.*' => ['integer', new InBranchCatalogue($branchId)],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'branch_id' => 'chi nhánh',
            'customer_name' => 'tên khách hàng',
            'customer_phone' => 'số điện thoại',
            'employee_id' => 'nhân viên',
            'starts_at' => 'thời gian',
            'duration_minutes' => 'thời lượng',
            'service_ids' => 'dịch vụ',
            'service_ids.*' => 'dịch vụ',
            'note' => 'ghi chú',
        ]);

        $appointment = $saveAppointment->handle($validated + ['status' => AppointmentStatus::Pending->value]);

        $recipients = User::query()
            ->active()
            ->whereNotNull('email')
            ->whereIn('role', [UserRole::Owner->value, UserRole::Manager->value])
            ->get();

        Notification::send($recipients, new NewOnlineBookingNotification($appointment));

        return redirect()->route('home')
            ->with('booking_success', 'Tiệm đã nhận lịch hẹn của bạn và sẽ sớm xác nhận.');
    }
}
