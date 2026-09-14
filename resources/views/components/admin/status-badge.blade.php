@props(['status' => null, 'tone' => null, 'label' => null])

@php
    $resolvedLabel = $label ?? (is_object($status) && method_exists($status, 'label') ? $status->label() : (string) $status);
    $resolvedTone = $tone ?? (is_object($status) && method_exists($status, 'tone') ? $status->tone() : 'is-muted');

    // Các enum của ứng dụng trả về tông riêng; ánh xạ sang lớp màu của Bootstrap.
    //
    // Trong ứng dụng có hai quy ước tông cùng tồn tại: `is-success` cho chip
    // dùng chung, và tên màu trần (`warning`, `danger`) ở nhóm enum rủi ro vốn
    // nội suy thẳng vào `text-bg-*`. Bỏ tiền tố rồi mới khớp, để một enum viết
    // theo quy ước kia không lặng lẽ rơi về xám khi được đưa vào đây.
    $variant = match (preg_replace('/^is-/', '', $resolvedTone)) {
        'success' => 'success',
        'danger' => 'danger',
        'warning' => 'warning',
        'info' => 'info',
        'active', 'primary' => 'primary',
        default => 'secondary',
    };
@endphp

<span {{ $attributes->merge(['class' => "badge rounded-pill text-bg-{$variant} bg-opacity-10 text-{$variant}-emphasis border border-{$variant} border-opacity-25"]) }}>{{ $resolvedLabel }}</span>
