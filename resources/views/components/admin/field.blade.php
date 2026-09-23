@props(['name', 'label', 'help' => null, 'col' => 'col-12 col-lg-6', 'required' => false, 'id' => null])

@php
    // Trình đọc màn hình cần biết ô này sai và câu nào giải thích vì sao; đặt
    // hai khối cạnh nhau về mặt thị giác là chưa đủ. Id được sinh ở đây rồi
    // gắn vào control bằng aria-describedby.
    // Mặc định id trùng tên ô. Hai biểu mẫu trên cùng một trang có thể gửi
    // cùng một tên — ca cần nghỉ và ca cần đổi đều là `shift_assignment_id` —
    // nên khi đó mỗi ô phải tự khai một id, nếu không nhãn thứ hai sẽ trỏ vào
    // ô của biểu mẫu thứ nhất và bấm vào nhãn lại nhảy sang chỗ khác.
    $fieldId = $id ?? $name;
    $errorId = $fieldId.'-error';
    $helpId = $fieldId.'-help';
    $hasError = $errors->has($name);

    $describedBy = array_filter([
        $hasError ? $errorId : null,
        $help ? $helpId : null,
    ]);
@endphp

{{-- Nhãn luôn nằm trên ô nhập, lỗi nằm dưới. Không dùng placeholder thay nhãn. --}}
<div class="{{ $col }}"
     @if ($describedBy) data-neo-describedby="{{ implode(' ', $describedBy) }}" @endif
     @if ($hasError) data-neo-invalid="{{ $fieldId }}" @endif>
    <label class="form-label" for="{{ $fieldId }}">
        {{ $label }}
        @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif
    </label>

    {{ $slot }}

    {{-- Khối trợ giúp luôn tồn tại (rỗng khi không có nội dung) để
         aria-describedby của control không trỏ vào một id không có thật. --}}
    <div class="form-text @unless ($help) d-none @endunless" id="{{ $helpId }}">{{ $help }}</div>

    @error($name)
        <div class="invalid-feedback d-block" id="{{ $errorId }}">{{ $message }}</div>
    @enderror
</div>
