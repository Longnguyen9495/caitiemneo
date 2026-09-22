<?php

namespace App\Services\Ai;

use App\Enums\AppointmentStatus;
use App\Enums\CashTransactionCategory;
use App\Enums\CashTransactionType;
use App\Enums\PaymentMethod;
use App\Models\AiActionProposal;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\Ai\Actions\ActionRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Bảng "dữ liệu sẽ áp dụng" là màn hình người dùng bấm duyệt, nên phải đọc được
 * như một mẩu giấy ghi việc: không tên trường, không khóa enum, không số ID.
 * Payload giữ nguyên tên kỹ thuật cho executor; lớp này chỉ lo phần hiển thị.
 */
final class AiPayloadPresenter
{
    /** @var array<string, string> */
    private array $resolved = [];

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function rows(AiActionProposal $proposal): array
    {
        $rows = [];
        $fieldLabels = $this->fieldLabels($proposal->type);

        foreach ($proposal->payload ?? [] as $key => $value) {
            $label = $fieldLabels[$key] ?? $this->label($key);

            if ($label === null) {
                continue;
            }

            $rows[] = [
                'label' => $label,
                'value' => $this->value($key, $value),
            ];
        }

        return $rows;
    }

    /**
     * Nhãn lấy thẳng từ bản khai của thao tác, nên một đối tượng mới không cần
     * chép lại tên trường vào đây nữa.
     *
     * @return array<string, string>
     */
    private function fieldLabels(string $type): array
    {
        $action = app(ActionRegistry::class)->find($type);

        if ($action === null) {
            return [];
        }

        $labels = [];

        foreach ($action->fields(null) as $field) {
            $labels[$field->key] = $field->label;
        }

        return $labels;
    }

    /**
     * Bảng dự phòng cho khóa không có ô nhập, ví dụ chi nhánh.
     *
     * Trả về null cho các khóa chỉ có ý nghĩa nội bộ: chúng đã nằm trong phần
     * tóm tắt dưới dạng tên thật, in thêm ID chỉ làm người duyệt phân tâm.
     */
    private function label(string $key): ?string
    {
        return match ($key) {
            'branch_id' => 'Chi nhánh',
            'type' => 'Loại',
            'category' => 'Hạng mục',
            'amount' => 'Số tiền',
            'payment_method' => 'Hình thức thanh toán',
            'reference' => 'Chứng từ',
            'note' => 'Ghi chú',
            'occurred_at' => 'Thời điểm',
            'product_id' => 'Vật tư',
            'adjustment_mode' => 'Cách điều chỉnh',
            'quantity' => 'Số lượng',
            'unit_cost' => 'Đơn giá',
            'appointment_id' => 'Lịch hẹn',
            'customer_name' => 'Khách hàng',
            'customer_phone' => 'Số điện thoại',
            'customer_email' => 'Email',
            'starts_at' => 'Bắt đầu',
            'duration_minutes' => 'Thời lượng',
            'status' => 'Trạng thái',
            'employee_id' => 'Kỹ thuật viên',
            'service_ids' => 'Dịch vụ',
            'reason' => 'Lý do hủy',
            default => null,
        };
    }

    private function value(string $key, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '—';
        }

        return match ($key) {
            'branch_id' => $this->branchName((int) $value),
            'product_id' => $this->modelName(Product::class, (int) $value, 'vật tư'),
            'employee_id' => $this->modelName(User::class, (int) $value, 'nhân viên'),
            'appointment_id' => $this->appointmentName((int) $value),
            'service_ids' => $this->serviceNames((array) $value),
            'type' => $this->typeLabel((string) $value),
            'category' => CashTransactionCategory::tryFrom((string) $value)?->label() ?? (string) $value,
            'payment_method' => PaymentMethod::tryFrom((string) $value)?->label() ?? (string) $value,
            'status' => AppointmentStatus::tryFrom((string) $value)?->label() ?? (string) $value,
            'adjustment_mode' => $value === 'absolute' ? 'Đặt lại số tồn' : 'Cộng thêm hoặc trừ bớt',
            'amount', 'unit_cost' => number_format((float) $value, 0, ',', '.').'đ',
            'duration_minutes' => (int) $value.' phút',
            'starts_at', 'occurred_at' => $this->dateTime((string) $value),
            default => is_array($value) ? implode(', ', $value) : (string) $value,
        };
    }

    /**
     * `type` dùng chung cho khoản thu chi và điều chỉnh kho nên phải tra cả hai
     * bảng nghĩa trước khi đành trả lại giá trị thô.
     */
    private function typeLabel(string $value): string
    {
        if ($value === 'adjustment') {
            return 'Điều chỉnh tồn kho';
        }

        return CashTransactionType::tryFrom($value)?->label() ?? $value;
    }

    private function dateTime(string $value): string
    {
        try {
            return Carbon::parse($value)->timezone(config('app.timezone'))->format('H:i \n\g\à\y d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function branchName(int $id): string
    {
        return $this->remember("branch:{$id}", fn (): string => Branch::query()->whereKey($id)->value('name') ?? "Chi nhánh #{$id}");
    }

    /** @param  class-string<Model>  $model */
    private function modelName(string $model, int $id, string $noun): string
    {
        return $this->remember($model.':'.$id, fn (): string => $model::query()->whereKey($id)->value('name') ?? ucfirst($noun)." #{$id}");
    }

    private function appointmentName(int $id): string
    {
        return $this->remember("appointment:{$id}", function () use ($id): string {
            $appointment = Appointment::query()->whereKey($id)->first(['customer_name', 'starts_at']);

            if (! $appointment) {
                return "Lịch hẹn #{$id}";
            }

            return trim($appointment->customer_name.' · '.$appointment->starts_at?->format('H:i \n\g\à\y d/m/Y'), ' ·');
        });
    }

    /** @param  array<int, mixed>  $ids */
    private function serviceNames(array $ids): string
    {
        $names = Service::query()
            ->whereIn('id', array_map('intval', $ids))
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return $names === [] ? '—' : implode(', ', $names);
    }

    /** @param  callable(): string  $resolver */
    private function remember(string $key, callable $resolver): string
    {
        return $this->resolved[$key] ??= $resolver();
    }
}
