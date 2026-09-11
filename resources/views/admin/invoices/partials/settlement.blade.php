{{-- Đặt ngay đầu trang: trên điện thoại, thu tiền là việc cần làm trước. --}}
<section class="card mb-3">
    <div class="card-body">
        <div class="row g-2 text-center text-lg-start">
            <div class="col-4">
                <p class="mb-0 small text-body-secondary">Tạm tính</p>
                <p class="mb-0 fw-semibold"><x-admin.money :value="$invoice->subtotal" /></p>
            </div>
            <div class="col-4">
                <p class="mb-0 small text-body-secondary">Giảm giá</p>
                <p class="mb-0 fw-semibold"><x-admin.money :value="$invoice->discount" /></p>
            </div>
            <div class="col-4">
                <p class="mb-0 small text-body-secondary">Tổng thanh toán</p>
                <p class="mb-0 fs-5 fw-bold text-primary"><x-admin.money :value="$invoice->total" /></p>
            </div>
        </div>

        @if ($invoice->paid_at)
            <p class="mb-0 mt-3 small text-body-secondary">
                Đã thanh toán lúc {{ $invoice->paid_at->format('d/m/Y H:i') }}
                @if ($invoice->payment_method) · {{ $invoice->payment_method->label() }} @endif
            </p>
        @endif

        @if ($invoice->status === App\Enums\InvoiceStatus::Cancelled)
            <p class="mb-0 mt-3 small text-body-secondary">
                Đã hủy lúc {{ $invoice->cancelled_at?->format('d/m/Y H:i') }}
                bởi {{ $invoice->canceller?->name ?? 'hệ thống' }}.
                @if ($invoice->cancel_reason) Lý do: {{ $invoice->cancel_reason }} @endif
            </p>
        @endif

        @canany(['pay', 'cancel'], $invoice)
            <div class="d-grid d-lg-flex gap-2 mt-3">
                @can('pay', $invoice)
                    <form method="POST" action="{{ route('admin.invoices.pay', $invoice) }}" class="d-flex gap-2 flex-grow-1">
                        @csrf
                        <label class="visually-hidden" for="payment_method">Phương thức thanh toán</label>
                        <select class="form-select" id="payment_method" name="payment_method" required style="max-width:11rem">
                            @foreach ($paymentMethods as $value => $label)
                                <option value="{{ $value }}" @selected($invoice->payment_method?->value === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-admin.submit-button label="Xác nhận thanh toán" class="flex-grow-1" />
                    </form>
                @endcan

                @can('cancel', $invoice)
                    @if ($invoice->status !== App\Enums\InvoiceStatus::Cancelled)
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelInvoice">
                            Hủy hóa đơn
                        </button>
                    @endif
                @endcan
            </div>
        @endcanany
    </div>
</section>

@can('cancel', $invoice)
    @if ($invoice->status !== App\Enums\InvoiceStatus::Cancelled)
        {{-- Hủy hóa đơn cần lý do nên dùng hộp thoại riêng, không dùng hộp xác nhận chung. --}}
        <div class="modal fade" id="cancelInvoice" tabindex="-1" aria-labelledby="cancelInvoiceLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('admin.invoices.cancel', $invoice) }}" class="modal-content">
                    @csrf
                    @method('DELETE')

                    <div class="modal-header">
                        <h2 class="modal-title fs-6 fw-semibold" id="cancelInvoiceLabel">Hủy hóa đơn {{ $invoice->number }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>

                    <div class="modal-body">
                        <p class="small text-body-secondary">
                            Nếu hóa đơn đã thanh toán, giao dịch gốc được giữ nguyên và hệ thống ghi thêm một bút toán đảo.
                        </p>
                        <label class="form-label" for="cancel_reason">Lý do hủy</label>
                        <input class="form-control" id="cancel_reason" name="cancel_reason" required maxlength="255" placeholder="Ví dụ: khách đổi ý">
                    </div>

                    <div class="modal-footer gap-2 flex-nowrap">
                        <button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">Quay lại</button>
                        <x-admin.submit-button label="Hủy hóa đơn" variant="danger" class="flex-fill" />
                    </div>
                </form>
            </div>
        </div>
    @endif
@endcan
