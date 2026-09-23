{{--
    Một nút bấm là một hành động POST.

    Bảng danh sách nào cũng có vài nút kiểu này — Duyệt, Từ chối, Hủy — và mỗi
    nút là một form riêng vì chúng đổi dữ liệu chứ không phải đi tới trang khác.
    Viết tay thì mỗi chỗ lại quên một thứ: khi thì `@csrf`, khi thì `type` của
    nút, khi thì cỡ chữ lệch hẳn so với các nút bên cạnh.

    Không kèm hộp xác nhận: thao tác cần hỏi lại thì dùng `x-admin.confirm-form`.
--}}
@props([
    'action',
    'label',
    'variant' => 'outline-secondary',
    'size' => 'sm',
    // Các giá trị đi kèm hành động, ví dụ ['approved' => 1].
    'fields' => [],
])

<form method="POST" action="{{ $action }}">
    @csrf
    @foreach ($fields as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <button type="submit" {{ $attributes->merge(['class' => "btn btn-{$size} btn-{$variant}"]) }}>{{ $label }}</button>
</form>
