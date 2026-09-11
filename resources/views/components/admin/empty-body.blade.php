<div class="neo-empty">
    <x-admin.icon :name="$icon" size="40" />
    <strong>{{ $title }}</strong>
    @if ($hint)<p>{{ $hint }}</p>@endif
    @if (trim($slot ?? '') !== '')<div class="mt-3">{!! $slot !!}</div>@endif
</div>
