@props(['colspan' => null, 'title' => 'Chưa có dữ liệu', 'hint' => null])

@if ($colspan)
    <tr>
        <td colspan="{{ $colspan }}" class="admin-empty">
            <strong>{{ $title }}</strong>
            @if ($hint)<p>{{ $hint }}</p>@endif
            {{ $slot }}
        </td>
    </tr>
@else
    <div class="admin-empty">
        <strong>{{ $title }}</strong>
        @if ($hint)<p>{{ $hint }}</p>@endif
        {{ $slot }}
    </div>
@endif
