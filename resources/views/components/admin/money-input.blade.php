@props(['name', 'value' => null, 'step' => '1000', 'min' => '0', 'required' => false])

<input
    type="number"
    name="{{ $name }}"
    step="{{ $step }}"
    min="{{ $min }}"
    inputmode="decimal"
    value="{{ old($name, $value !== null ? rtrim(rtrim(number_format((float) \App\Support\Money::toMinor($value) / 100, 2, '.', ''), '0'), '.') : null) }}"
    @required($required)
    {{ $attributes->merge(['class' => 'admin-money-input']) }}
>
