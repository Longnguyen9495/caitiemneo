<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <h2>Thanh toán</h2>
            <p>Ghi nhận thanh toán sẽ tính lại hóa đơn và tạo đúng một khoản thu trong sổ thu chi.</p>
        </div>
    </header>

    <div class="admin-summary-grid">
        <div><p>Tạm tính</p><strong><x-admin.money :value="$invoice->subtotal" /></strong></div>
        <div><p>Giảm giá</p><strong><x-admin.money :value="$invoice->discount" /></strong></div>
        <div><p>Tổng thanh toán</p><strong><x-admin.money :value="$invoice->total" /></strong></div>
        <div>
            <p>Thời điểm thanh toán</p>
            <strong>{{ $invoice->paid_at?->format('d/m/Y H:i') ?? 'Chưa thanh toán' }}</strong>
        </div>
    </div>

    <div class="admin-panel-body">
        @if ($invoice->status === App\Enums\InvoiceStatus::Cancelled)
            <p class="admin-muted-text">
                Hóa đơn đã hủy lúc {{ $invoice->cancelled_at?->format('d/m/Y H:i') }}
                bởi {{ $invoice->canceller?->name ?? 'hệ thống' }}.
                @if ($invoice->cancel_reason) Lý do: {{ $invoice->cancel_reason }} @endif
            </p>
        @endif

        <div class="admin-page-actions">
            @can('pay', $invoice)
                <form method="POST" action="{{ route('admin.invoices.pay', $invoice) }}" class="admin-inline-form">
                    @csrf
                    <label class="admin-muted-text">
                        Phương thức
                        <select name="payment_method" required>
                            @foreach ($paymentMethods as $value => $label)
                                <option value="{{ $value }}" @selected($invoice->payment_method?->value === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-admin.submit-button label="Xác nhận thanh toán" />
                </form>
            @endcan

            @can('cancel', $invoice)
                @if ($invoice->status !== App\Enums\InvoiceStatus::Cancelled)
                    <x-admin.confirm-form
                        :action="route('admin.invoices.cancel', $invoice)"
                        method="DELETE"
                        label="Hủy hóa đơn"
                        message="Hủy hóa đơn này? Nếu đã thanh toán, hệ thống sẽ tạo bút toán đảo thay vì xóa giao dịch gốc."
                    >
                        <input type="text" name="cancel_reason" placeholder="Lý do hủy" required maxlength="255">
                    </x-admin.confirm-form>
                @endif
            @endcan
        </div>
    </div>
</section>
