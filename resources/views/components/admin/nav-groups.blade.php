@props(['groups', 'class' => 'neo-navlink', 'prefix' => 'nav'])

@foreach ($groups as $group)
    @php
        $groupId = $prefix . '-group-' . $group['id'];
        $isOpen = $group['isActive'] ? 'show' : '';
        $hasActive = $group['isActive'];
    @endphp
    <div class="neo-nav-group">
        <button type="button"
                class="neo-nav-group__trigger {{ $hasActive ? 'is-active' : '' }}"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $groupId }}"
                aria-expanded="{{ $hasActive ? 'true' : 'false' }}"
                aria-controls="{{ $groupId }}">
            <span class="flex-grow-1">{{ $group['label'] }}</span>
            @if ($group['badge'] > 0)
                <span class="badge rounded-pill text-bg-warning" aria-label="{{ $group['badge'] }} đơn cần xử lý">
                    {{ $group['badge'] }}
                </span>
            @endif
            <x-admin.icon name="chevron-down" class="neo-nav-group__chevron" />
        </button>
        <div id="{{ $groupId }}" class="collapse {{ $isOpen }}">
            <div class="neo-nav-group__items">
                @foreach ($group['items'] as $item)
                    <a
                        href="{{ route($item['route']) }}"
                        @class([$class, 'is-active' => request()->routeIs($item['pattern'])])
                        @if (request()->routeIs($item['pattern'])) aria-current="page" @endif
                    >
                        <x-admin.icon :name="$item['icon']" />
                        <span class="flex-grow-1">{{ $item['label'] }}</span>
                        @if ($item['badge'] > 0)
                            <span class="badge rounded-pill text-bg-warning" aria-label="{{ $item['badge'] }} đơn cần xử lý">
                                {{ $item['badge'] }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endforeach
<!-- Fallback: khi JavaScript không chạy, các nhóm active vẫn hiển thị nhờ class show. Các nhóm không active cần được truy cập qua nút mở rộng. Trên server, không thể xác định trạng thái JS, nên dựa vào progressive enhancement của Bootstrap collapse. -->
<noscript>
    <style>
        .neo-nav-group .collapse {
            display: block !important;
        }
        .neo-nav-group__chevron {
            display: none;
        }
        .neo-nav-group__trigger {
            pointer-events: none;
            opacity: 0.8;
        }
    </style>
</noscript>
