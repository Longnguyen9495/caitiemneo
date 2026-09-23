{{--
    Một ô chọn kèm nhãn, trợ giúp và thông báo lỗi.

    Cùng một khối `field` + `select` + `@error` lặp lại ở mọi biểu mẫu xếp ca.
    Chép tay thì lệch dần: có chỗ quên `is-invalid` nên ô sai không đỏ lên, có
    chỗ đặt id khác tên ô nên nhãn trỏ vào chỗ trống.

    Các thẻ `<option>` do nơi gọi truyền vào, vì mỗi danh sách tự biết cách gọi
    tên thứ nó đang liệt kê.
--}}
@props([
    'name',
    'label',
    'col' => 'col-12 col-lg-6',
    'required' => false,
    'help' => null,
    // Chỉ cần khi trên một trang có hai ô cùng tên, ví dụ hai biểu mẫu đơn ca.
    'id' => null,
    // Dòng đầu tiên, để ô chọn bắt đầu ở trạng thái chưa chọn gì.
    'placeholder' => null,
])

@php $fieldId = $id ?? $name; @endphp

<x-admin.field :name="$name" :label="$label" :col="$col" :required="$required" :help="$help" :id="$fieldId">
    <select class="form-select @error($name) is-invalid @enderror" id="{{ $fieldId }}" name="{{ $name }}" @required($required)>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        {{ $slot }}
    </select>
</x-admin.field>
