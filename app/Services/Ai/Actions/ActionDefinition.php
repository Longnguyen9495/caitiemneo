<?php

namespace App\Services\Ai\Actions;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Bản khai duy nhất của một thao tác trợ lý được phép đề xuất.
 *
 * Trước đây mỗi thao tác phải khai ở sáu chỗ — danh mục, prompt, luật validate,
 * chỗ chạy thật, mẫu phiếu và bảng nhãn hiển thị — nên thêm một đối tượng mới
 * là sáu lần sửa và sáu cơ hội quên. Giờ mọi thứ đọc từ đây.
 */
final class ActionDefinition
{
    /**
     * @param  'create'|'update'|'delete'  $operation
     * @param  Closure(?int $branchId): array<int, Field>  $fields
     * @param  Closure(array<string, mixed>, User, ?int, ?Model): array<string, mixed>  $validator
     * @param  Closure(array<string, mixed>, User, ?Model): Model  $handler
     * @param  Closure(array<string, mixed>): Model|null  $subject  Bản ghi đích của
     *                                                              thao tác sửa/hủy.
     * @param  bool  $branchScoped  Phiếu có ô chi nhánh ẩn và phải khớp chi nhánh
     *                              của đề xuất. Đặt false cho đối tượng dùng chung
     *                              toàn tiệm, hoặc khi chi nhánh đã nằm sẵn trong
     *                              bản ghi đích.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $operation,
        public readonly string $resource,
        public readonly bool $destructive,
        private readonly Closure $fields,
        private readonly Closure $validator,
        private readonly Closure $handler,
        private readonly ?Closure $subject = null,
        public readonly bool $branchScoped = true,
        public readonly ?string $hint = null,
    ) {}

    /** @return array<int, Field> */
    public function fields(?int $branchId): array
    {
        return ($this->fields)($branchId);
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_map(fn (Field $field): string => $field->key, $this->fields(null));
    }

    /** @param array<string, mixed> $payload */
    public function subject(array $payload): ?Model
    {
        return $this->subject === null ? null : ($this->subject)($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validate(array $payload, User $actor, ?int $branchId, ?Model $subject): array
    {
        return ($this->validator)($payload, $actor, $branchId, $subject);
    }

    /** @param array<string, mixed> $payload */
    public function handle(array $payload, User $actor, ?Model $subject): Model
    {
        return ($this->handler)($payload, $actor, $subject);
    }

    /**
     * Dòng mô tả gửi vào prompt hệ thống.
     *
     * Tên trường ở đây là tên kỹ thuật, và đó là chủ ý: model cần biết đặt khóa
     * nào trong payload. Luật cấm lộ tên trường chỉ áp cho phần model nói với
     * người dùng, không áp cho bản thân payload.
     */
    public function promptSpec(): string
    {
        $describe = function (Field $field): string {
            $note = $field->isRequired() ? '' : ' (có thể bỏ trống)';
            $choices = $field->staticOptions();
            $options = $choices === null || $choices === []
                ? ''
                : ' ['.implode(', ', array_map(
                    fn (string $value, string $label): string => "{$value} = {$label}",
                    array_keys($choices),
                    $choices,
                )).']';

            return $field->key.$note.$options;
        };

        $line = "{$this->key} — {$this->label}. Payload: ".implode('; ', array_map($describe, $this->fields(null))).'.';

        return $this->hint === null ? $line : $line.' '.$this->hint;
    }
}
