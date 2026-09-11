@props(['value' => 0, 'signed' => false, 'suffix' => true])

@php
    $minor = \App\Support\Money::toMinor($value);
    $prefix = $signed && $minor > 0 ? '+' : '';
@endphp

<span {{ $attributes->merge(['class' => 'admin-money'.($signed && $minor < 0 ? ' is-negative' : ($signed && $minor > 0 ? ' is-positive' : ''))]) }}>{{ $prefix }}{{ \App\Support\Money::format($value, $suffix) }}</span>
