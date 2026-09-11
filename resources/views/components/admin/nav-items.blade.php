@props(['items', 'class' => 'neo-navlink'])

@foreach ($items as $item)
    <a
        href="{{ route($item['route']) }}"
        @class([$class, 'is-active' => request()->routeIs($item['pattern'])])
        @if (request()->routeIs($item['pattern'])) aria-current="page" @endif
    >
        <x-admin.icon :name="$item['icon']" />
        <span>{{ $item['label'] }}</span>
    </a>
@endforeach
