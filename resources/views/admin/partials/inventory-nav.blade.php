<nav class="admin-module-nav" aria-label="Khu vực kho vật tư">
    <a href="{{ route('admin.products.index') }}" @class(['is-active' => request()->routeIs('admin.products.*')])>Vật tư</a>
    <a href="{{ route('admin.inventory.index') }}" @class(['is-active' => request()->routeIs('admin.inventory.*')])>Nhập xuất kho</a>
    @can('viewAny', App\Models\Supplier::class)
        <a href="{{ route('admin.suppliers.index') }}" @class(['is-active' => request()->routeIs('admin.suppliers.*')])>Nhà cung cấp</a>
    @endcan
</nav>
