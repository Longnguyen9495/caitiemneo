<?php

namespace App\Services\Ai\Actions;

/**
 * Toàn bộ thao tác trợ lý được phép đề xuất.
 *
 * Thêm một đối tượng mới nghĩa là khai thêm một ActionDefinition rồi cắm vào
 * danh sách này — danh mục, prompt, phiếu xác nhận, luật validate và nhãn hiển
 * thị tự bám theo. Không có chỗ thứ hai nào phải nhớ sửa.
 *
 * Ba nhóm cố ý đứng ngoài: tài khoản và phân quyền, lương, chi nhánh cùng danh
 * mục theo chi nhánh. Chúng đổi được quyền của người khác hoặc đẩy tiền thật ra
 * khỏi tiệm, nên vẫn làm tay qua màn hình quản trị.
 */
final class ActionRegistry
{
    /** @var array<string, ActionDefinition>|null */
    private ?array $definitions = null;

    /** @return array<string, ActionDefinition> */
    public function all(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $definitions = array_merge(
            AppointmentActions::all(),
            InvoiceActions::all(),
            CashActions::all(),
            InventoryActions::all(),
            CatalogueActions::all(),
            WorkforceActions::all(),
            ShiftRequestActions::all(),
        );

        $keyed = [];

        foreach ($definitions as $definition) {
            $keyed[$definition->key] = $definition;
        }

        return $this->definitions = $keyed;
    }

    public function find(string $key): ?ActionDefinition
    {
        return $this->all()[$key] ?? null;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->all());
    }
}
