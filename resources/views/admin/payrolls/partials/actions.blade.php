@php
    use App\Enums\PayrollStatus;

    $user = auth()->user();
    $canFinalize = $payroll->status === PayrollStatus::Draft && $user->can('finalize', $payroll);
    $canPay = $payroll->status === PayrollStatus::Finalized && $user->can('pay', $payroll);
    $canCancel = ! in_array($payroll->status, [PayrollStatus::Paid, PayrollStatus::Cancelled], true) && $user->can('cancel', $payroll);
    $canRecalculate = $payroll->status === PayrollStatus::Draft && $user->can('update', $payroll);
    $canApproveVariance = $payroll->status === PayrollStatus::Draft && $user->can('approveVariance', $payroll);
@endphp

@if ($canApproveVariance)
    <section class="card mb-3">
        <div class="card-header">
            <h2 class="neo-display fs-5 mb-0">Điều chỉnh tổng lương</h2>
            <p class="mb-0 small text-body-secondary">Dùng khi số chốt khác số hệ thống tính. Bắt buộc ghi lý do; hệ thống lưu người duyệt và thời điểm.</p>
        </div>

        <form method="POST" action="{{ route('admin.payrolls.variance', $payroll) }}" class="card-body">
            @csrf

            <div class="row g-3">
                <x-admin.field name="approved_manual_adjustment" label="Chênh lệch so với số hệ thống" required>
                    <input class="form-control text-end neo-num @error('approved_manual_adjustment') is-invalid @enderror"
                           id="approved_manual_adjustment" type="number" step="1000" name="approved_manual_adjustment"
                           value="{{ old('approved_manual_adjustment', $payroll->approved_manual_adjustment) }}" required>
                </x-admin.field>

                <x-admin.field name="variance_reason" label="Lý do" required>
                    <input class="form-control @error('variance_reason') is-invalid @enderror" id="variance_reason"
                           name="variance_reason" value="{{ old('variance_reason', $payroll->variance_reason) }}" required maxlength="255">
                </x-admin.field>
            </div>

            <div class="d-grid d-lg-flex justify-content-lg-end mt-3">
                <x-admin.submit-button label="Ghi nhận điều chỉnh" variant="outline-primary" />
            </div>
        </form>
    </section>
@endif

@if ($canFinalize || $canPay || $canCancel || $canRecalculate)
    <section class="card">
        <div class="card-header">
            <h2 class="neo-display fs-5 mb-0">Thao tác</h2>
            <p class="mb-0 small text-body-secondary">Chốt lương khóa toàn bộ số liệu. Chi trả tạo đúng một khoản chi tại quỹ chi nhánh trả lương.</p>
        </div>

        <div class="card-body d-grid d-lg-flex flex-lg-wrap gap-2">
            @if ($canRecalculate)
                <form method="POST" action="{{ route('admin.payrolls.recalculate', $payroll) }}" class="d-grid">
                    @csrf
                    <x-admin.submit-button label="Tính lại" variant="outline-secondary" />
                </form>
            @endif

            @if ($canPay)
                <form method="POST" action="{{ route('admin.payrolls.pay', $payroll) }}" class="d-flex gap-2 flex-grow-1">
                    @csrf
                    <label class="visually-hidden" for="payroll_payment_method">Phương thức chi trả</label>
                    <select class="form-select" id="payroll_payment_method" name="payment_method" required style="max-width:11rem">
                        @foreach (App\Enums\PaymentMethod::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-admin.submit-button label="Xác nhận chi trả" class="flex-grow-1" />
                </form>
            @endif

            @if ($canFinalize || $canCancel)
                <div class="payroll-actions__pair">
                    @if ($canFinalize)
                        <x-admin.confirm-form
                            :action="route('admin.payrolls.finalize', $payroll)"
                            label="Chốt bảng lương"
                            variant="primary"
                            size=""
                            message="Chốt bảng lương này? Sau khi chốt, số liệu sẽ bị khóa."
                        />
                    @endif

                    @if ($canCancel)
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelPayroll">
                            Hủy bảng lương
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </section>

    @if ($canCancel)
        <div class="modal fade" id="cancelPayroll" tabindex="-1" aria-labelledby="cancelPayrollLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('admin.payrolls.cancel', $payroll) }}" class="modal-content">
                    @csrf
                    @method('DELETE')

                    <div class="modal-header">
                        <h2 class="modal-title fs-6 fw-semibold" id="cancelPayrollLabel">Hủy bảng lương</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>

                    <div class="modal-body">
                        <p class="small text-body-secondary">Dữ liệu vẫn được giữ lại để đối soát.</p>
                        <label class="form-label" for="payroll_cancel_reason">Lý do hủy</label>
                        <input class="form-control" id="payroll_cancel_reason" name="cancel_reason" required maxlength="255">
                    </div>

                    <div class="modal-footer gap-2 flex-nowrap">
                        <button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">Quay lại</button>
                        <x-admin.submit-button label="Hủy bảng lương" variant="danger" class="flex-fill" />
                    </div>
                </form>
            </div>
        </div>
    @endif
@endif
