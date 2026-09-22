<?php

namespace App\Services\Ai;

use App\Services\Ai\Actions\ActionRegistry;

/**
 * Cửa vào danh sách thao tác cho phần còn lại của ứng dụng.
 *
 * Giữ nguyên chữ ký cũ để các lớp gọi không phải đổi, nhưng nội dung giờ đọc
 * từ ActionRegistry thay vì một mảng viết tay.
 */
final class AiActionCatalog
{
    public function __construct(private ?ActionRegistry $registry = null)
    {
        $this->registry ??= app(ActionRegistry::class);
    }

    /** @return array<int, string> */
    public function allowedTypes(): array
    {
        return $this->registry->keys();
    }

    public function supports(string $type): bool
    {
        return $this->registry->find($type) !== null;
    }

    /** @return array{label: string, operation: string, resource: string, destructive: bool}|null */
    public function definition(string $type): ?array
    {
        $action = $this->registry->find($type);

        return $action === null ? null : [
            'label' => $action->label,
            'operation' => $action->operation,
            'resource' => $action->resource,
            'destructive' => $action->destructive,
        ];
    }

    /** @return array<string, array{label: string, operation: string, resource: string, destructive: bool}> */
    public function definitions(): array
    {
        $definitions = [];

        foreach ($this->registry->keys() as $key) {
            $definitions[$key] = $this->definition($key);
        }

        return $definitions;
    }
}
