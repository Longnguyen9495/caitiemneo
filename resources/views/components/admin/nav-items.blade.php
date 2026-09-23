@props(['items', 'class' => 'neo-navlink'])

@foreach ($items as $item)
    <a
        href="{{ route($item['route'], $item['params'] ?? []) }}"
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
