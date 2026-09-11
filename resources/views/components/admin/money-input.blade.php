@props(['name', 'value' => null, 'step' => '1000', 'min' => '0', 'required' => false])

@php
    $current = old($name, $value !== null
        ? rtrim(rtrim(number_format((float) \App\Support\Money::toMinor($value) / 100, 2, '.', ''), '0'), '.')
        : null);

    // Nối ô nhập với câu lỗi và câu trợ giúp ngay trong HTML, không đợi
    // JavaScript: trình đọc màn hình phải biết ô này sai kể cả khi script hỏng.
    // Id ở đây khớp với id mà x-admin.field sinh ra.
    $hasError = $errors->has($name);
    $describedBy = implode(' ', array_filter([
        $hasError ? $name.'-error' : null,
        $name.'-help',
    ]));
@endphp

<div class="input-group">
    <input
        type="number"
        id="{{ $name }}"
        name="{{ $name }}"
        step="{{ $step }}"
        min="{{ $min }}"
        inputmode="decimal"
        value="{{ $current }}"
        aria-describedby="{{ $describedBy }}"
        @if ($hasError) aria-invalid="true" @endif
        @required($required)
        {{ $attributes->merge(['class' => 'form-control text-end neo-num'.($hasError ? ' is-invalid' : '')]) }}
    >
    <span class="input-group-text">đ</span>
</div>
