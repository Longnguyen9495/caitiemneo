@props([
    'action',
    'method' => 'POST',
    'label' => 'Xác nhận',
    'message' => 'Bạn có chắc chắn muốn thực hiện thao tác này?',
    'variant' => 'outline-danger',
    'size' => 'sm',
    // Tên input mà hộp xác nhận sẽ điền lý do vào. Bỏ trống nghĩa là thao tác
    // không cần lý do; khi có, người dùng bắt buộc phải tự gõ.
    'reasonField' => null,
    'reasonLabel' => 'Lý do',
    'reasonMin' => 10,
])

<form method="POST" action="{{ $action }}" class="d-inline-flex align-items-center gap-2">
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif

    {{ $slot }}

    <button type="submit" class="btn btn-{{ $size }} btn-{{ $variant }}"
            data-neo-confirm="{{ $message }}"
            @if ($reasonField)
                data-neo-confirm-reason="{{ $reasonField }}"
                data-neo-confirm-reason-label="{{ $reasonLabel }}"
                data-neo-confirm-reason-min="{{ $reasonMin }}"
            @endif
    >
        {{ $label }}
    </button>
</form>
