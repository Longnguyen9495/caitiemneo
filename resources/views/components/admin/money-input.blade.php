@props(['name', 'value' => null, 'step' => '1000', 'min' => '0', 'required' => false])

@php
    $current = old($name, $value !== null
        ? rtrim(rtrim(number_format((float) \App\Support\Money::toMinor($value) / 100, 2, '.', ''), '0'), '.')
        : null);
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
        @required($required)
        {{ $attributes->merge(['class' => 'form-control text-end neo-num']) }}
    >
    <span class="input-group-text">đ</span>
</div>
