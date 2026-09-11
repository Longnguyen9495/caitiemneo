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
    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <h2>Điều chỉnh tổng lương</h2>
                <p>Dùng khi số chốt khác số hệ thống tính. Bắt buộc ghi lý do; hệ thống lưu người duyệt và thời điểm.</p>
            </div>
        </header>

        <form method="POST" action="{{ route('admin.payrolls.variance', $payroll) }}" class="admin-form" style="padding: 1.25rem 1.4rem;">
            @csrf

            <label>
                Chênh lệch so với số hệ thống (VNĐ)
                <input class="admin-money-input" type="number" step="1000" name="approved_manual_adjustment" value="{{ old('approved_manual_adjustment', $payroll->approved_manual_adjustment) }}" required>
                @error('approved_manual_adjustment')<small>{{ $message }}</small>@enderror
            </label>

            <label>
                Lý do
                <input name="variance_reason" value="{{ old('variance_reason', $payroll->variance_reason) }}" required maxlength="255">
                @error('variance_reason')<small>{{ $message }}</small>@enderror
            </label>

            <div class="admin-form-actions admin-form-wide">
                <x-admin.submit-button label="Ghi nhận điều chỉnh" variant="ghost" />
            </div>
        </form>
    </section>
@endif

@if ($canFinalize || $canPay || $canCancel || $canRecalculate)
    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <h2>Thao tác</h2>
                <p>Chốt lương khóa toàn bộ số liệu. Chi trả sẽ tạo đúng một khoản chi tại quỹ chi nhánh trả lương.</p>
            </div>
        </header>

        <div class="admin-panel-body">
            <div class="admin-page-actions">
                @if ($canRecalculate)
                    <form method="POST" action="{{ route('admin.payrolls.recalculate', $payroll) }}" class="admin-inline-form">
                        @csrf
                        <x-admin.submit-button label="Tính lại" variant="ghost" />
                    </form>
                @endif

                @if ($canFinalize)
                    <x-admin.confirm-form
                        :action="route('admin.payrolls.finalize', $payroll)"
                        label="Chốt bảng lương"
                        variant="primary"
                        message="Chốt bảng lương này? Sau khi chốt, số liệu sẽ bị khóa."
                    />
                @endif

                @if ($canPay)
                    <form method="POST" action="{{ route('admin.payrolls.pay', $payroll) }}" class="admin-inline-form">
                        @csrf
                        <label class="admin-muted-text">
                            Phương thức
                            <select name="payment_method" required>
                                @foreach (App\Enums\PaymentMethod::options() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <x-admin.submit-button label="Xác nhận chi trả" />
                    </form>
                @endif

                @if ($canCancel)
                    <x-admin.confirm-form
                        :action="route('admin.payrolls.cancel', $payroll)"
                        method="DELETE"
                        label="Hủy bảng lương"
                        message="Hủy bảng lương này? Dữ liệu vẫn được giữ lại để đối soát."
                    >
                        <input type="text" name="cancel_reason" placeholder="Lý do hủy" required maxlength="255">
                    </x-admin.confirm-form>
                @endif
            </div>
        </div>
    </section>
@endif
