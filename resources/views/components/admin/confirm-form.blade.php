@props([
    'action',
    'method' => 'POST',
    'label' => 'Xác nhận',
    'message' => 'Bạn có chắc chắn muốn thực hiện thao tác này?',
    'variant' => 'danger',
])

<form
    method="POST"
    action="{{ $action }}"
    class="admin-inline-form"
    x-data="{ submitting: false }"
    x-on:submit="if (submitting) { $event.preventDefault(); return; } if (! window.confirm(@js($message))) { $event.preventDefault(); return; } submitting = true"
>
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif

    {{ $slot }}

    <button type="submit" class="admin-button is-{{ $variant }}" x-bind:disabled="submitting">
        <span x-show="!submitting">{{ $label }}</span>
        <span x-show="submitting" x-cloak>Đang xử lý…</span>
    </button>
</form>
