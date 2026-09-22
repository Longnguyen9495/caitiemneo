<?php

namespace App\Services\Ai;

use App\Models\AiActionProposal;
use App\Services\Ai\Actions\ActionRegistry;
use App\Services\Ai\Actions\Field;
use App\Support\BranchContext;
use Illuminate\Support\Carbon;

/**
 * Dựng phiếu xác nhận cho một đề xuất.
 *
 * Mô tả ô nhập nằm trong bản khai của từng thao tác; lớp này chỉ ghép nó với
 * dữ liệu trợ lý đã điền và chi nhánh đang làm việc, rồi trả ra mảng cho Blade.
 *
 * @phpstan-type FormField array{
 *     key: string,
 *     label: string,
 *     widget: string,
 *     value: mixed,
 *     required: bool,
 *     help: string|null,
 *     options: array<string, string>,
 *     attributes: array<string, string|int>,
 * }
 */
final class AiActionFormBuilder
{
    public function __construct(
        private BranchContext $branches,
        private ActionRegistry $registry,
    ) {}

    /**
     * Khóa payload được phép nhận từ phiếu. Mọi khóa khác bị bỏ, kể cả khi
     * trợ lý đã đặt sẵn trong đề xuất.
     *
     * @return array<int, string>
     */
    public function editableKeys(string $type): array
    {
        return $this->registry->find($type)?->keys() ?? [];
    }

    /** @return array<int, FormField> */
    public function fields(AiActionProposal $proposal): array
    {
        $action = $this->registry->find($proposal->type);

        if ($action === null) {
            return [];
        }

        $payload = $proposal->payload ?? [];
        $branchId = $this->branchId($proposal);

        return array_map(
            function (Field $field) use ($branchId, $payload): array {
                $spec = $field->toArray($branchId);
                $spec['value'] = $this->currentValue($spec, $payload);

                return $spec;
            },
            $action->fields($branchId),
        );
    }

    /**
     * Thao tác trên danh mục dùng chung không thuộc chi nhánh nào, nên phiếu
     * của chúng mở được cả khi người dùng đang xem toàn công ty.
     */
    public function requiresBranch(AiActionProposal $proposal): bool
    {
        return $this->registry->find($proposal->type)?->branchScoped ?? true;
    }

    public function branchName(AiActionProposal $proposal): ?string
    {
        return $this->branches->available()->firstWhere('id', $this->branchId($proposal))?->name;
    }

    /**
     * Chi nhánh của đề xuất là chi nhánh đang làm việc lúc hỏi; danh sách nhân
     * viên, dịch vụ và vật tư trong phiếu đều bám theo nó.
     *
     * Trả về null khi người dùng đang xem toàn công ty mà đề xuất chưa gắn chi
     * nhánh nào: cũng như mọi form tạo mới khác, phải chọn một chi nhánh cụ thể
     * trước đã.
     */
    public function branchId(AiActionProposal $proposal): ?int
    {
        foreach ([$proposal->branch_id, $proposal->payload['branch_id'] ?? null, $this->branches->requireWritableBranchId()] as $candidate) {
            if (is_numeric($candidate)) {
                return (int) $candidate;
            }
        }

        return null;
    }

    /**
     * @param  FormField  $field
     * @param  array<string, mixed>  $payload
     */
    private function currentValue(array $field, array $payload): mixed
    {
        $value = $payload[$field['key']] ?? null;

        if ($field['widget'] === 'checkboxes') {
            return array_map('strval', is_array($value) ? $value : []);
        }

        if ($value === null || $value === '') {
            return $field['value'];
        }

        return match ($field['widget']) {
            'datetime' => $this->localFormat((string) $value, 'Y-m-d\TH:i'),
            'date' => $this->localFormat((string) $value, 'Y-m-d'),
            'toggle' => (bool) $value,
            'money', 'number' => is_numeric($value) ? $value + 0 : $value,
            default => $value,
        };
    }

    private function localFormat(string $value, string $format): string
    {
        try {
            return Carbon::parse($value)->timezone(config('app.timezone'))->format($format);
        } catch (\Throwable) {
            return '';
        }
    }
}
