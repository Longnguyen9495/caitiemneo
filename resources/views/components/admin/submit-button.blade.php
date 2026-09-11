@props(['label' => 'Lưu', 'variant' => 'primary'])

{{--
    Chặn bấm hai lần ở phía trình duyệt; lần bấm đầu vẫn gửi bình thường.
    Máy chủ mới là nơi quyết định: mọi thao tác tài chính đều idempotent.
--}}
<button
    type="submit"
    x-data="submitGuard"
    x-on:click="guard($event)"
    x-bind:aria-busy="submitting"
    {{ $attributes->merge(['class' => 'btn btn-'.$variant]) }}
>
    <span x-show="!submitting">{{ $label }}</span>
    <span x-show="submitting" x-cloak>Đang xử lý…</span>
</button>
