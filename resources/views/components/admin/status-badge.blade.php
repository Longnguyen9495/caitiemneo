@props(['status' => null, 'tone' => null, 'label' => null])

@php
    $resolvedLabel = $label ?? (is_object($status) && method_exists($status, 'label') ? $status->label() : (string) $status);
    $resolvedTone = $tone ?? (is_object($status) && method_exists($status, 'tone') ? $status->tone() : 'is-muted');
@endphp

<span {{ $attributes->merge(['class' => 'admin-status '.$resolvedTone]) }}>{{ $resolvedLabel }}</span>
