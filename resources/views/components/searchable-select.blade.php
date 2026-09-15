@props([
    'name',
    'options' => [],
    'value' => null,
    'placeholder' => 'Chọn…',
    'searchPlaceholder' => 'Gõ để tìm…',
    'emptyText' => 'Không tìm thấy mục nào phù hợp.',
    'clearLabel' => 'Bỏ chọn',
])

{{--
    Ô chọn một giá trị trong danh sách dài, kèm tìm kiếm.

    Giá trị thật nằm ở `<input type="hidden">` nên biểu mẫu gửi đi y như khi
    dùng `<select>`; phần nhìn thấy chỉ là nút mở và danh sách lọc được.

    Cố ý KHÔNG đặt trong `<label>`: nhấn vào một mục nằm bên trong label sẽ
    được trình duyệt chuyển tiếp thành một cú nhấn lên chính nút mở, và ô chọn
    sẽ bật lại ngay sau khi vừa đóng.
--}}
<div
    class="searchable-select"
    x-data="searchableSelect({ value: @js((string) $value), options: @js($options) })"
    x-on:click.outside="close()"
    x-on:keydown.escape.prevent.stop="dismiss()"
>
    <input type="hidden" name="{{ $name }}" x-model="value">

    <button
        type="button"
        x-ref="control"
        {{ $attributes->merge(['class' => 'searchable-select__control']) }}
        x-bind:class="{ 'is-placeholder': value === '' }"
        role="combobox"
        aria-haspopup="listbox"
        x-bind:aria-expanded="open"
        aria-controls="{{ $name }}-options"
        x-on:click="toggle()"
        x-on:keydown.down.prevent="show()"
    >
        <span x-text="label || @js($placeholder)">{{ $value ?: $placeholder }}</span>
        <svg class="searchable-select__caret" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>

    <div class="searchable-select__panel" x-show="open" x-cloak>
        <input
            type="search"
            aria-label="{{ $searchPlaceholder }}"
            class="searchable-select__search"
            x-ref="search"
            x-model="query"
            x-on:input="filter()"
            placeholder="{{ $searchPlaceholder }}"
            autocomplete="off"
            x-on:keydown.down.prevent="move(1)"
            x-on:keydown.up.prevent="move(-1)"
            x-on:keydown.enter.prevent="pickActive()"
        >

        <ul class="searchable-select__list" id="{{ $name }}-options" role="listbox" x-ref="list">
            <template x-for="(option, index) in filtered" :key="option.value">
                <li
                    class="searchable-select__option"
                    role="option"
                    x-bind:aria-selected="option.value === value"
                    x-bind:class="{ 'is-active': index === activeIndex, 'is-selected': option.value === value }"
                    x-on:click="choose(option)"
                    x-on:mousemove="activeIndex = index"
                >
                    <span class="searchable-select__option-label" x-text="option.label"></span>
                    <span class="searchable-select__option-hint" x-text="option.hint"></span>
                </li>
            </template>
        </ul>

        <p class="searchable-select__empty" x-show="filtered.length === 0">{{ $emptyText }}</p>

        <button type="button" class="searchable-select__clear" x-show="value !== ''" x-on:click="clear()">
            {{ $clearLabel }}
        </button>
    </div>
</div>
