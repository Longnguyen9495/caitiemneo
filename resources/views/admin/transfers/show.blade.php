<x-layouts.admin :title="'Phiếu '.$transfer->number" heading="Kho vật tư">
    @include('admin.partials.inventory-nav')

    <x-admin.page-header
        :title="$transfer->number"
        :description="$transfer->sourceBranch?->name.' → '.$transfer->destinationBranch?->name"
        :breadcrumbs="['Chuyển kho' => route('admin.stock-transfers.index'), $transfer->number => null]"
    >
        <x-slot:actions>
            <x-admin.status-badge :status="$transfer->status" />
        </x-slot:actions>
    </x-admin.page-header>

    <section class="admin-panel">
        <header class="admin-panel-header">
            <div><h2>Nội dung phiếu</h2><p>Đơn giá được chụp lại tại thời điểm lập phiếu.</p></div>
        </header>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Vật tư</th><th class="admin-numeric">Số lượng</th><th class="admin-numeric">Đơn giá</th><th class="admin-numeric">Giá trị</th></tr></thead>
                <tbody>
                    @forelse ($transfer->items as $item)
                        <tr>
                            <td><strong>{{ $item->product?->name }}</strong><p>{{ $item->product?->unit }}</p></td>
                            <td class="admin-numeric">{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}</td>
                            <td class="admin-numeric"><x-admin.money :value="$item->unit_cost" /></td>
                            <td class="admin-numeric"><x-admin.money :value="(float) $item->quantity * (float) $item->unit_cost" /></td>
                        </tr>
                    @empty
                        <x-admin.empty-state :colspan="4" title="Phiếu chưa có dòng vật tư" />
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-panel-body">
            <dl class="admin-definition-grid">
                <div><dt>Người tạo</dt><dd>{{ $transfer->creator?->name ?? '—' }}</dd></div>
                <div><dt>Người hoàn tất</dt><dd>{{ $transfer->completer?->name ?? 'Chưa hoàn tất' }}</dd></div>
                <div><dt>Thời điểm chuyển</dt><dd>{{ $transfer->transferred_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            </dl>

            @if ($transfer->note)
                <p class="admin-muted-text" style="margin-top: 1rem;">Ghi chú: {{ $transfer->note }}</p>
            @endif

            <div class="admin-page-actions" style="margin-top: 1.2rem;">
                @can('complete', $transfer)
                    <x-admin.confirm-form
                        :action="route('admin.stock-transfers.complete', $transfer)"
                        label="Hoàn tất chuyển kho"
                        variant="primary"
                        message="Hoàn tất phiếu này? Tồn kho hai chi nhánh sẽ được cập nhật ngay."
                    />
                @endcan

                @can('cancel', $transfer)
                    <x-admin.confirm-form
                        :action="route('admin.stock-transfers.cancel', $transfer)"
                        method="DELETE"
                        label="Hủy phiếu"
                        message="Hủy phiếu chuyển kho này?"
                    />
                @endcan
            </div>
        </div>
    </section>
</x-layouts.admin>
