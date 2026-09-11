@can('verifyBillKpi', $invoice)
    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <h2>KPI số bill</h2>
                <p>Xác nhận hóa đơn này được tính vào chỉ tiêu số bill của nhân viên trong kỳ.</p>
            </div>
            <x-admin.status-badge
                :tone="$invoice->qualified_for_bill_kpi ? 'is-success' : 'is-muted'"
                :label="$invoice->qualified_for_bill_kpi ? 'Đã xác nhận' : 'Chưa xác nhận'"
            />
        </header>

        <form method="POST" action="{{ route('admin.invoices.bill-kpi', $invoice) }}" class="admin-form" style="padding: 1.25rem 1.4rem;">
            @csrf
            @method('PATCH')

            <label>
                Trạng thái
                <select name="qualified_for_bill_kpi" required>
                    <option value="1" @selected($invoice->qualified_for_bill_kpi)>Tính vào KPI</option>
                    <option value="0" @selected(! $invoice->qualified_for_bill_kpi)>Không tính</option>
                </select>
                @error('qualified_for_bill_kpi')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Ghi chú
                <input name="bill_kpi_note" value="{{ old('bill_kpi_note', $invoice->bill_kpi_note) }}" maxlength="255">
                @error('bill_kpi_note')<small>{{ $message }}</small>@enderror
            </label>

            <div class="admin-form-actions admin-form-wide">
                @if ($invoice->bill_kpi_verified_at)
                    <span class="admin-muted-text">
                        Xác nhận bởi {{ $invoice->billKpiVerifier?->name ?? 'quản lý' }}
                        lúc {{ $invoice->bill_kpi_verified_at->format('d/m/Y H:i') }}
                    </span>
                @endif
                <x-admin.submit-button label="Lưu xác nhận" variant="ghost" />
            </div>
        </form>
    </section>
@endcan
