@props(['name', 'size' => null])

{{--
    Bộ icon vẽ tay, dùng chung một độ dày nét 1.6 và cùng viewBox 24.
    Nhúng thẳng SVG thay vì tải font icon: không thêm request, không nhấp nháy
    chữ trước khi font về, và đổi màu theo currentColor.
--}}
@php
    $paths = [
        'dashboard' => '<path d="M4 13h6V4H4v9Zm10 7h6v-9h-6v9ZM4 20h6v-4H4v4Zm10-11h6V4h-6v5Z"/>',
        'calendar' => '<rect x="3.5" y="5.5" width="17" height="15" rx="2.5"/><path d="M8 3.5v4M16 3.5v4M3.5 10.5h17"/>',
        'receipt' => '<path d="M6 3.5h12a1 1 0 0 1 1 1v16l-3-1.6-2.5 1.6L11 18.9 8.5 20.5 5 18.9V4.5a1 1 0 0 1 1-1Z"/><path d="M9 8.5h6M9 12.5h6"/>',
        'box' => '<path d="M12 3.5 20 8v8l-8 4.5L4 16V8l8-4.5Z"/><path d="M4 8l8 4.5L20 8M12 12.5v8"/>',
        'people' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20c0-3.1 2.5-5.2 5.5-5.2s5.5 2.1 5.5 5.2"/><path d="M16 5.2a3 3 0 0 1 0 5.9M17.5 14.9c1.8.6 3 2.2 3 4.1"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-6M22 20H2"/>',
        'branch' => '<path d="M12 3.5 4 7v13h16V7l-8-3.5Z"/><path d="M9.5 20v-6h5v6"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'wallet' => '<rect x="3.5" y="6.5" width="17" height="13" rx="2.5"/><path d="M3.5 10.5h17M16 15h1.5"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'more' => '<circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/>',
        'filter' => '<path d="M4 6h16l-6.2 7.3V19l-3.6 1.6v-7.3L4 6Z"/>',
        'logout' => '<path d="M15 5.5V4a1.5 1.5 0 0 0-1.5-1.5h-8A1.5 1.5 0 0 0 4 4v16a1.5 1.5 0 0 0 1.5 1.5h8A1.5 1.5 0 0 0 15 20v-1.5"/><path d="M19.5 12H9m10.5 0-3-3m3 3-3 3"/>',
        'inbox' => '<path d="M3.5 13.5h4l1.5 3h6l1.5-3h4"/><path d="M5.6 5.2 3.5 13.5V19a1.5 1.5 0 0 0 1.5 1.5h14a1.5 1.5 0 0 0 1.5-1.5v-5.5l-2.1-8.3A1.5 1.5 0 0 0 16.9 4H7.1a1.5 1.5 0 0 0-1.5 1.2Z"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7"/>',
        'pin' => '<path d="M12 21s6.5-6.1 6.5-10.5a6.5 6.5 0 1 0-13 0C5.5 14.9 12 21 12 21Z"/><circle cx="12" cy="10.5" r="2.4"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'alert' => '<path d="M12 4.5 21 20H3l9-15.5Z"/><path d="M12 10v4M12 17h.01"/>',
        'bell' => '<path d="M18 10a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 22h4"/>',
        'external' => '<path d="M14 4h6v6M20 4l-8.5 8.5"/><path d="M18 14v5.5A1.5 1.5 0 0 1 16.5 21h-11A1.5 1.5 0 0 1 4 19.5v-11A1.5 1.5 0 0 1 5.5 7H11"/>',
        'image' => '<rect x="3.5" y="5" width="17" height="14" rx="2.5"/><circle cx="9" cy="10" r="1.6"/><path d="m4 17 4.5-4.5 3 3 3-2.5L20 17"/>',
        'sparkles' => '<path d="m12 3 1.2 3.8L17 8l-3.8 1.2L12 13l-1.2-3.8L7 8l3.8-1.2L12 3Z"/><path d="m18.5 13 .7 2.3 2.3.7-2.3.7-.7 2.3-.7-2.3-2.3-.7 2.3-.7.7-2.3ZM5.5 13l.8 2.7 2.7.8-2.7.8L5.5 20l-.8-2.7-2.7-.8 2.7-.8.8-2.7Z"/>',
    ];
@endphp

<svg
    {{ $attributes->merge(['class' => 'neo-icon', 'aria-hidden' => 'true', 'focusable' => 'false']) }}
    @if ($size) width="{{ $size }}" height="{{ $size }}" @endif
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.6"
    stroke-linecap="round"
    stroke-linejoin="round"
>{!! $paths[$name] ?? $paths['more'] !!}</svg>
