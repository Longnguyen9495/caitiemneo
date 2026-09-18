<?php

namespace App\Services\Ai;

final class AiActionCatalog
{
    /** @return array<int, string> */
    public function allowedTypes(): array
    {
        return array_keys($this->definitions());
    }

    public function supports(string $type): bool
    {
        return array_key_exists($type, $this->definitions());
    }

    /** @return array{label: string, operation: string, resource: string, destructive: bool}|null */
    public function definition(string $type): ?array
    {
        return $this->definitions()[$type] ?? null;
    }

    /** @return array<string, array{label: string, operation: string, resource: string, destructive: bool}> */
    public function definitions(): array
    {
        return [
            'create_cash_entry' => [
                'label' => 'Tạo khoản thu/chi',
                'operation' => 'create',
                'resource' => 'cash_transaction',
                'destructive' => false,
            ],
            'adjust_stock' => [
                'label' => 'Điều chỉnh tồn kho',
                'operation' => 'update',
                'resource' => 'inventory',
                'destructive' => false,
            ],
            'create_appointment' => [
                'label' => 'Tạo lịch hẹn',
                'operation' => 'create',
                'resource' => 'appointment',
                'destructive' => false,
            ],
            'update_appointment' => [
                'label' => 'Sửa lịch hẹn',
                'operation' => 'update',
                'resource' => 'appointment',
                'destructive' => false,
            ],
            'cancel_appointment' => [
                'label' => 'Hủy lịch hẹn',
                'operation' => 'delete',
                'resource' => 'appointment',
                'destructive' => true,
            ],
        ];
    }
}
