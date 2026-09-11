<nav class="admin-module-nav" aria-label="Khu vực hóa đơn và thu chi">
    @can('viewAny', App\Models\Invoice::class)
        <a href="{{ route('admin.invoices.index') }}" @class(['is-active' => request()->routeIs('admin.invoices.*')])>Hóa đơn</a>
    @endcan
    @can('viewAny', App\Models\CashTransaction::class)
        <a href="{{ route('admin.cash.index') }}" @class(['is-active' => request()->routeIs('admin.cash.*')])>Sổ thu chi</a>
    @endcan
</nav>
