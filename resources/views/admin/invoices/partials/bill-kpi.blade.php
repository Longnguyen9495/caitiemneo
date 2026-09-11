@can('verifyBillKpi', $invoice)
    <section class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
            <div>
                <h2 class="neo-display fs-5 mb-0">KPI số bill</h2>
                <p class="mb-0 small text-body-secondary">Xác nhận hóa đơn này được tính vào chỉ tiêu số bill của nhân viên.</p>
            </div>
            <x-admin.status-badge
                :tone="$invoice->qualified_for_bill_kpi ? 'is-success' : 'is-muted'"
                :label="$invoice->qualified_for_bill_kpi ? 'Đã xác nhận' : 'Chưa xác nhận'"
            />
        </div>

        <form method="POST" action="{{ route('admin.invoices.bill-kpi', $invoice) }}" class="card-body">
            @csrf
            @method('PATCH')

            <div class="row g-3">
                <x-admin.field name="qualified_for_bill_kpi" label="Trạng thái">
                    <select class="form-select" id="qualified_for_bill_kpi" name="qualified_for_bill_kpi" required>
                        <option value="1" @selected($invoice->qualified_for_bill_kpi)>Tính vào KPI</option>
                        <option value="0" @selected(! $invoice->qualified_for_bill_kpi)>Không tính</option>
                    </select>
                </x-admin.field>

                <x-admin.field name="bill_kpi_note" label="Ghi chú">
                    <input class="form-control" id="bill_kpi_note" name="bill_kpi_note"
                           value="{{ old('bill_kpi_note', $invoice->bill_kpi_note) }}" maxlength="255">
                </x-admin.field>
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                @if ($invoice->bill_kpi_verified_at)
                    <small class="text-body-secondary">
                        Xác nhận bởi {{ $invoice->billKpiVerifier?->name ?? 'quản lý' }}
                        lúc {{ $invoice->bill_kpi_verified_at->format('d/m/Y H:i') }}
                    </small>
                @else
                    <span></span>
                @endif
                <x-admin.submit-button label="Lưu xác nhận" variant="outline-primary" />
            </div>
        </form>
    </section>
@endcan
