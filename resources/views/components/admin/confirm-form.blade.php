@props([
    'action',
    'method' => 'POST',
    'label' => 'Xác nhận',
    'message' => 'Bạn có chắc chắn muốn thực hiện thao tác này?',
    'variant' => 'outline-danger',
    'size' => 'sm',
])

<form method="POST" action="{{ $action }}" class="d-inline-flex align-items-center gap-2">
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif

    {{ $slot }}

    <button type="submit" class="btn btn-{{ $size }} btn-{{ $variant }}" data-neo-confirm="{{ $message }}">
        {{ $label }}
    </button>
</form>
