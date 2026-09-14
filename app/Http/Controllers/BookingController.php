<?php

namespace App\Http\Controllers;

use App\Actions\Appointments\SaveAppointmentAction;
use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use App\Notifications\NewOnlineBookingNotification;
use App\Rules\InBranchCatalogue;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function store(Request $request, SaveAppointmentAction $saveAppointment): RedirectResponse
    {
        // Chi nhánh được đọc trước để các rule sau soi đúng bảng giá của nó.
        $branchId = $request->integer('branch_id') ?: null;

        try {
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
                // Dịch vụ phải nằm trong bảng giá của **đúng chi nhánh khách chọn**.
                // Đây là bề mặt công khai nên mọi giá trị đều do người lạ gửi lên.
                'service_ids.*' => ['integer', new InBranchCatalogue($branchId)],
                'note' => ['nullable', 'string', 'max:2000'],
            ], [
                'starts_at.date_format' => 'Thời gian đặt lịch không hợp lệ.',
            ], [
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
        } catch (ValidationException $exception) {
            $exception->redirectTo(route('home').'#dat-lich');

            throw $exception;
        }

        // Giờ khách gõ trong ô datetime-local là giờ Việt Nam, không kèm múi
        // giờ. Dựng lại mốc thời gian với đúng múi giờ đó thay vì để PHP đoán
        // theo trình duyệt người đặt.
        //
        // Không gọi ->utc() ở đây: ứng dụng đã chạy trên Asia/Ho_Chi_Minh nên
        // Eloquent tự quy đổi khi lưu; chuyển thêm một lần nữa sẽ lệch 7 tiếng.
        $validated['starts_at'] = Carbon::createFromFormat(
            'Y-m-d\TH:i',
            $validated['starts_at'],
            'Asia/Ho_Chi_Minh',
        );

        try {
            $appointment = $saveAppointment->handle($validated + ['status' => AppointmentStatus::Pending->value]);
        } catch (ValidationException $exception) {
            $exception->redirectTo(route('home').'#dat-lich');

            throw $exception;
        }

        $recipients = User::query()
            ->active()
            ->whereNotNull('email')
            ->whereIn('role', [UserRole::Owner->value, UserRole::Manager->value])
            ->get();

        Notification::send($recipients, new NewOnlineBookingNotification($appointment));

        return redirect()->to(route('home').'#dat-lich')
            ->with('booking_success', 'Tiệm đã nhận lịch hẹn của bạn và sẽ sớm xác nhận.');
    }
}
