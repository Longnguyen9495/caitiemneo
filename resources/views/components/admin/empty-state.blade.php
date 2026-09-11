@props(['colspan' => null, 'title' => 'Chưa có dữ liệu', 'hint' => null, 'icon' => 'inbox'])

@php
    $body = view('components.admin.empty-body', ['title' => $title, 'hint' => $hint, 'icon' => $icon, 'slot' => $slot]);
@endphp

@if ($colspan)
    <tr>
        <td colspan="{{ $colspan }}" class="neo-table__empty p-0">{{ $body }}</td>
    </tr>
@else
    {{ $body }}
@endif
