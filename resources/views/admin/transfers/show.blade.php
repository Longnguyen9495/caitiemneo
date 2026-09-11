<x-layouts.admin :title="'Phiếu '.$transfer->number" heading="Chuyển kho">
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

    <section class="card mb-3">
        <div class="card-header">
            <h2 class="neo-display fs-5 mb-0">Nội dung phiếu</h2>
            <p class="mb-0 small text-body-secondary">Đơn giá được chụp lại tại thời điểm lập phiếu.</p>
        </div>

        <table class="table neo-table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Vật tư</th>
                    <th scope="col" class="text-end">Số lượng</th>
                    <th scope="col" class="text-end">Đơn giá</th>
                    <th scope="col" class="text-end">Giá trị</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transfer->items as $item)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $item->product?->name }}</span>
                            <small class="d-block text-body-secondary">{{ $item->product?->unit }}</small>
                        </td>
                        <td data-label="Số lượng" class="text-end neo-num">{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}</td>
                        <td data-label="Đơn giá" class="text-end"><x-admin.money :value="$item->unit_cost" /></td>
                        <td data-label="Giá trị" class="text-end fw-semibold"><x-admin.money :value="(float) $item->quantity * (float) $item->unit_cost" /></td>
                    </tr>
                @empty
                    <x-admin.empty-state :colspan="4" title="Phiếu chưa có dòng vật tư" />
                @endforelse
            </tbody>
        </table>

        <div class="card-body border-top">
            <dl class="row g-3 mb-0">
                <div class="col-6 col-lg-4"><dt class="small text-body-secondary">Người tạo</dt><dd class="mb-0">{{ $transfer->creator?->name ?? '—' }}</dd></div>
                <div class="col-6 col-lg-4"><dt class="small text-body-secondary">Người hoàn tất</dt><dd class="mb-0">{{ $transfer->completer?->name ?? 'Chưa hoàn tất' }}</dd></div>
                <div class="col-12 col-lg-4"><dt class="small text-body-secondary">Thời điểm chuyển</dt><dd class="mb-0 neo-num">{{ $transfer->transferred_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            </dl>

            @if ($transfer->note)
                <p class="mt-3 mb-0 small text-body-secondary">Ghi chú: {{ $transfer->note }}</p>
            @endif
        </div>
    </section>

    @canany(['complete', 'cancel'], $transfer)
        <section class="card">
            <div class="card-body d-grid d-lg-flex gap-2">
                @can('complete', $transfer)
                    <x-admin.confirm-form
                        :action="route('admin.stock-transfers.complete', $transfer)"
                        label="Hoàn tất chuyển kho"
                        variant="primary"
                        size=""
                        message="Hoàn tất phiếu này? Tồn kho hai chi nhánh sẽ được cập nhật ngay."
                    />
                @endcan

                @can('cancel', $transfer)
                    <x-admin.confirm-form
                        :action="route('admin.stock-transfers.cancel', $transfer)"
                        method="DELETE"
                        label="Hủy phiếu"
                        size=""
                        message="Hủy phiếu chuyển kho này?"
                    />
                @endcan
            </div>
        </section>
    @endcanany
</x-layouts.admin>
