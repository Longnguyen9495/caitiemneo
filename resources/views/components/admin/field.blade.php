@props(['name', 'label', 'help' => null, 'col' => 'col-12 col-lg-6', 'required' => false])

{{-- Nhãn luôn nằm trên ô nhập, lỗi nằm dưới. Không dùng placeholder thay nhãn. --}}
<div class="{{ $col }}">
    <label class="form-label" for="{{ $name }}">
        {{ $label }}
        @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif
    </label>

    {{ $slot }}

    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif

    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
