<?php

namespace App\Services\Ai\Actions;

use Closure;

/**
 * Một ô trên phiếu xác nhận của trợ lý.
 *
 * Cùng một mô tả được dùng cho ba việc: vẽ ô nhập, lọc dữ liệu gửi lên, và
 * đặt tên tiếng Việt khi kể lại thao tác. Nhờ vậy thêm một đối tượng mới chỉ
 * là khai thêm vài dòng ở đây, không phải sửa sáu chỗ như trước.
 */
final class Field
{
    private mixed $default = null;

    private bool $required = false;

    private ?string $help = null;

    /** @var array<string, string|int> */
    private array $attributes = [];

    /** @var Closure(?int): array<string, string>|null */
    private ?Closure $options = null;

    /**
     * Danh sách phụ thuộc dữ liệu trong kho (nhân viên, dịch vụ, lịch hẹn…)
     * thay vì một enum cố định. Prompt không liệt kê loại này: vừa dài vừa
     * thừa, model đã có ID cần dùng trong BUSINESS_CONTEXT.
     */
    private bool $dynamicOptions = false;

    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $widget,
    ) {}

    public static function text(string $key, string $label): self
    {
        return new self($key, $label, 'text');
    }

    public static function phone(string $key, string $label): self
    {
        return new self($key, $label, 'tel');
    }

    public static function email(string $key, string $label): self
    {
        return new self($key, $label, 'email');
    }

    public static function number(string $key, string $label): self
    {
        return new self($key, $label, 'number');
    }

    public static function money(string $key, string $label): self
    {
        return new self($key, $label, 'money');
    }

    public static function datetime(string $key, string $label): self
    {
        return new self($key, $label, 'datetime');
    }

    public static function date(string $key, string $label): self
    {
        return new self($key, $label, 'date');
    }

    public static function textarea(string $key, string $label): self
    {
        return new self($key, $label, 'textarea');
    }

    public static function toggle(string $key, string $label): self
    {
        return new self($key, $label, 'toggle');
    }

    /** @param array<string, string>|Closure(?int $branchId): array<string, string> $options */
    public static function select(string $key, string $label, array|Closure $options): self
    {
        return (new self($key, $label, 'select'))->options($options);
    }

    /** @param array<string, string>|Closure(?int $branchId): array<string, string> $options */
    public static function checkboxes(string $key, string $label, array|Closure $options): self
    {
        return (new self($key, $label, 'checkboxes'))->options($options);
    }

    /** Trường payload cần có nhưng người duyệt không chọn, ví dụ loại phiếu kho. */
    public static function fixed(string $key, string $label, mixed $value): self
    {
        return (new self($key, $label, 'hidden'))->default($value)->required();
    }

    /** @param array<string, string>|Closure(?int $branchId): array<string, string> $options */
    private function options(array|Closure $options): self
    {
        $this->dynamicOptions = $options instanceof Closure;
        $this->options = $options instanceof Closure ? $options : fn (): array => $options;

        return $this;
    }

    /** @return array<string, string>|null Null khi danh sách phải tra trong kho dữ liệu. */
    public function staticOptions(): ?array
    {
        return $this->options === null || $this->dynamicOptions ? null : ($this->options)(null);
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;

        return $this;
    }

    public function help(string $help): self
    {
        $this->help = $help;

        return $this;
    }

    /** @param array<string, string|int> $attributes */
    public function attributes(array $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function defaultValue(): mixed
    {
        return $this->default;
    }

    /**
     * @return array{
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
    public function toArray(?int $branchId): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'widget' => $this->widget,
            'value' => $this->default,
            'required' => $this->required,
            'help' => $this->help,
            'options' => $this->options === null ? [] : ($this->options)($branchId),
            'attributes' => $this->attributes,
        ];
    }
}
