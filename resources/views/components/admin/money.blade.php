@props(['value' => 0, 'signed' => false, 'suffix' => true])

@php
    $minor = \App\Support\Money::toMinor($value);
    $prefix = $signed && $minor > 0 ? '+' : '';
    $tone = $signed ? ($minor < 0 ? 'text-danger' : ($minor > 0 ? 'text-success' : '')) : '';
@endphp

<span {{ $attributes->merge(['class' => trim("neo-num {$tone}")]) }}>{{ $prefix }}{{ \App\Support\Money::format($value, $suffix) }}</span>
