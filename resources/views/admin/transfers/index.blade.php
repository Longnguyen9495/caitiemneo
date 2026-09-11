<x-layouts.admin title="Chuyển kho" heading="Kho vật tư">
    @include('admin.partials.inventory-nav')

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <h2>Phiếu chuyển kho</h2>
                <p>Hoàn tất phiếu sẽ tạo một phiếu xuất tại chi nhánh gửi và một phiếu nhập tại chi nhánh nhận.</p>
            </div>
            @can('create', App\Models\StockTransfer::class)
                <a href="{{ route('admin.stock-transfers.create') }}" class="admin-button">+ Tạo phiếu chuyển</a>
            @endcan
        </header>

        <x-admin.filter-bar :action="route('admin.stock-transfers.index')">
            <label>
                Trạng thái
                <select name="status">
                    <option value="">Tất cả</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </x-admin.filter-bar>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Số phiếu</th><th>Từ chi nhánh</th><th>Đến chi nhánh</th><th class="admin-numeric">Số dòng</th><th>Trạng thái</th><th>Người tạo</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($transfers as $transfer)
                        <tr>
                            <td><strong>{{ $transfer->number }}</strong><p>{{ $transfer->created_at?->format('d/m/Y H:i') }}</p></td>
                            <td><span class="admin-branch-chip">{{ $transfer->sourceBranch?->code }}</span> {{ $transfer->sourceBranch?->name }}</td>
                            <td><span class="admin-branch-chip">{{ $transfer->destinationBranch?->code }}</span> {{ $transfer->destinationBranch?->name }}</td>
                            <td class="admin-numeric">{{ $transfer->items_count }}</td>
                            <td><x-admin.status-badge :status="$transfer->status" /></td>
                            <td>{{ $transfer->creator?->name ?? '—' }}</td>
                            <td><a href="{{ route('admin.stock-transfers.show', $transfer) }}">Xem chi tiết</a></td>
                        </tr>
                    @empty
                        <x-admin.empty-state :colspan="7" title="Chưa có phiếu chuyển kho nào" hint="Tạo phiếu để điều chuyển vật tư giữa hai cơ sở." />
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$transfers" />
    </section>
</x-layouts.admin>
