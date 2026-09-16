@props(['groups', 'class' => 'neo-navlink'])

@foreach ($groups as $group)
    @php
        $groupId = 'nav-group-' . $group['id'];
        $isOpen = $group['isActive'] ? 'show' : '';
    @endphp
    <div class="neo-nav-group">
        <button type="button"
                class="neo-nav-group__trigger {{ $group['isActive'] ? 'is-active' : '' }}"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $groupId }}"
                aria-expanded="{{ $group['isActive'] ? 'true' : 'false' }}"
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
