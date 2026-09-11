@props(['label' => 'Lưu', 'variant' => 'primary'])

{{--
    Client side guard against a double click: the second click is swallowed, the
    first submit always goes through. The server stays the source of truth —
    every financial action is idempotent on its own.
--}}
<button
    type="submit"
    x-data="{ submitting: false }"
    x-on:click="if (submitting) { $event.preventDefault(); return; } if ($el.form && !$el.form.reportValidity()) { return; } submitting = true"
    x-bind:aria-busy="submitting"
    {{ $attributes->merge(['class' => 'admin-button is-'.$variant]) }}
>
    <span x-show="!submitting">{{ $label }}</span>
    <span x-show="submitting" x-cloak>Đang xử lý…</span>
</button>
