<nav class="neo-chips mb-3" aria-label="Khu vực kho vật tư">
    <a href="{{ route('admin.products.index') }}" @class(['neo-chip', 'is-active' => request()->routeIs('admin.products.*')])>Vật tư</a>
    <a href="{{ route('admin.inventory.index') }}" @class(['neo-chip', 'is-active' => request()->routeIs('admin.inventory.*')])>Nhập xuất</a>
    @can('viewAny', App\Models\StockTransfer::class)
        <a href="{{ route('admin.stock-transfers.index') }}" @class(['neo-chip', 'is-active' => request()->routeIs('admin.stock-transfers.*')])>Chuyển kho</a>
    @endcan
    @can('viewAny', App\Models\Supplier::class)
        <a href="{{ route('admin.suppliers.index') }}" @class(['neo-chip', 'is-active' => request()->routeIs('admin.suppliers.*')])>Nhà cung cấp</a>
    @endcan
</nav>
