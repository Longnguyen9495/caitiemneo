@props(['status' => null, 'tone' => null, 'label' => null])

@php
    $resolvedLabel = $label ?? (is_object($status) && method_exists($status, 'label') ? $status->label() : (string) $status);
    $resolvedTone = $tone ?? (is_object($status) && method_exists($status, 'tone') ? $status->tone() : 'is-muted');

    // Các enum của ứng dụng trả về tông riêng; ánh xạ sang lớp màu của Bootstrap.
    $variant = match ($resolvedTone) {
        'is-success' => 'success',
        'is-danger' => 'danger',
        'is-warning' => 'warning',
        'is-active' => 'primary',
        default => 'secondary',
    };
@endphp

<span {{ $attributes->merge(['class' => "badge rounded-pill text-bg-{$variant} bg-opacity-10 text-{$variant}-emphasis border border-{$variant} border-opacity-25"]) }}>{{ $resolvedLabel }}</span>
