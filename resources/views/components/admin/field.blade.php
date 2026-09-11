@props(['name', 'label', 'help' => null, 'col' => 'col-12 col-lg-6', 'required' => false])

@php
    // Trình đọc màn hình cần biết ô này sai và câu nào giải thích vì sao; đặt
    // hai khối cạnh nhau về mặt thị giác là chưa đủ. Id được sinh ở đây rồi
    // gắn vào control bằng aria-describedby.
    $fieldId = $name;
    $errorId = $name.'-error';
    $helpId = $name.'-help';
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
