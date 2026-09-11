<x-layouts.admin title="Nhập xuất kho" heading="Kho vật tư">
    @include('admin.partials.inventory-nav')

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <h2>Lịch sử nhập xuất</h2>
                <p>Số lượng âm là xuất kho, số dương là nhập kho hoặc điều chỉnh tăng.</p>
            </div>
            <div class="admin-page-actions">
                <a href="{{ route('admin.reports.export.inventory', request()->query()) }}" class="admin-button is-ghost">Xuất CSV</a>
                @can('create', App\Models\InventoryMovement::class)
                    <a href="{{ route('admin.inventory.create') }}" class="admin-button">+ Tạo phiếu kho</a>
                @endcan
            </div>
        </header>

        <x-admin.filter-bar :action="route('admin.inventory.index')">
            <label>
                Vật tư
                <select name="product_id">
                    <option value="">Tất cả</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Nhà cung cấp
                <select name="supplier_id">
                    <option value="">Tất cả</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) request('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Loại phiếu
                <select name="type">
                    <option value="">Tất cả</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Tham chiếu<input type="search" name="reference" value="{{ request('reference') }}"></label>
            <label>Từ ngày<input type="date" name="from" value="{{ request('from') }}"></label>
            <label>Đến ngày<input type="date" name="to" value="{{ request('to') }}"></label>
        </x-admin.filter-bar>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Thời điểm</th>
                        <th>Vật tư</th>
                        <th>Loại</th>
                        <th class="admin-numeric">Thay đổi</th>
                        <th class="admin-numeric">Đơn giá</th>
                        <th>Nhà cung cấp</th>
                        <th>Tham chiếu</th>
                        <th>Người tạo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr>
                            <td><strong>{{ $movement->occurred_at?->format('d/m/Y') }}</strong><p>{{ $movement->occurred_at?->format('H:i') }}</p></td>
                            <td>
                                <strong>{{ $movement->product?->name }}</strong>
                                @if ($movement->note)<p>{{ $movement->note }}</p>@endif
                            </td>
                            <td><x-admin.status-badge :status="$movement->type" /></td>
                            <td class="admin-numeric">
                                <span class="admin-money {{ (float) $movement->quantity < 0 ? 'is-negative' : 'is-positive' }}">
                                    {{ (float) $movement->quantity > 0 ? '+' : '' }}{{ rtrim(rtrim((string) $movement->quantity, '0'), '.') }}
                                </span>
                                <p>{{ $movement->product?->unit }}</p>
                            </td>
                            <td class="admin-numeric"><x-admin.money :value="$movement->unit_cost" /></td>
                            <td>{{ $movement->supplier?->name ?? '—' }}</td>
                            <td>{{ $movement->reference ?: '—' }}</td>
                            <td>{{ $movement->creator?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-admin.empty-state :colspan="8" title="Chưa có phiếu kho nào" hint="Tạo phiếu nhập đầu tiên để bắt đầu theo dõi tồn kho." />
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$movements" />
    </section>
</x-layouts.admin>
