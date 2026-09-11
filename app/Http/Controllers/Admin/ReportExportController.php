<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Queries\CashTransactionQuery;
use App\Queries\InventoryMovementQuery;
use App\Queries\InvoiceQuery;
use App\Queries\PayrollQuery;
use App\Services\CsvExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV exports that reuse the exact filters of the screen they are launched from.
 */
class ReportExportController extends Controller
{
    public function __construct(private CsvExporter $exporter) {}

    public function invoices(Request $request, InvoiceQuery $invoices): StreamedResponse
    {
        $this->authorize('viewAny', Invoice::class);

        return $this->exporter->stream(
            $this->filename('hoa-don'),
            ['Chi nhánh', 'Số hóa đơn', 'Ngày tạo', 'Khách hàng', 'Điện thoại', 'Trạng thái', 'Phương thức', 'Tạm tính', 'Giảm giá', 'Tổng', 'Ngày thanh toán', 'KPI bill', 'Người tạo'],
            $invoices->build($request)->with(['creator', 'branch'])->orderBy('invoices.id'),
            fn (Invoice $invoice): array => [
                $invoice->branch?->code,
                $invoice->number,
                $invoice->created_at?->format('d/m/Y H:i'),
                $invoice->customer_name,
                $invoice->customer_phone,
                $invoice->status->label(),
                $invoice->payment_method?->label(),
                $invoice->subtotal,
                $invoice->discount,
                $invoice->total,
                $invoice->paid_at?->format('d/m/Y H:i'),
                $invoice->qualified_for_bill_kpi ? 'Hợp lệ' : '',
                $invoice->creator?->name,
            ],
        );
    }

    public function cash(Request $request, CashTransactionQuery $transactions): StreamedResponse
    {
        Gate::authorize('view-cash-book');

        return $this->exporter->stream(
            $this->filename('thu-chi'),
            ['Chi nhánh', 'Thời điểm', 'Loại', 'Hạng mục', 'Số tiền', 'Phương thức', 'Tham chiếu', 'Ghi chú', 'Người tạo', 'Trạng thái'],
            $transactions->build($request)->with(['creator', 'branch'])->orderBy('cash_transactions.id'),
            fn (CashTransaction $transaction): array => [
                $transaction->branch?->code,
                $transaction->occurred_at?->format('d/m/Y H:i'),
                $transaction->type->label(),
                $transaction->category->label(),
                $transaction->amount,
                $transaction->payment_method?->label(),
                $transaction->reference,
                $transaction->note,
                $transaction->creator?->name,
                $transaction->isVoided() ? 'Đã hủy' : 'Hiệu lực',
            ],
        );
    }

    public function inventory(Request $request, InventoryMovementQuery $movements): StreamedResponse
    {
        Gate::authorize('view-inventory');

        return $this->exporter->stream(
            $this->filename('kho-vat-tu'),
            ['Chi nhánh', 'Thời điểm', 'Vật tư', 'Loại phiếu', 'Số lượng', 'Đơn vị', 'Đơn giá', 'Nhà cung cấp', 'Tham chiếu', 'Ghi chú', 'Người tạo'],
            $movements->build($request)->with(['product', 'supplier', 'creator', 'branch'])->orderBy('inventory_movements.id'),
            fn (InventoryMovement $movement): array => [
                $movement->branch?->code,
                $movement->occurred_at?->format('d/m/Y H:i'),
                $movement->product?->name,
                $movement->type->label(),
                $movement->quantity,
                $movement->product?->unit,
                $movement->unit_cost,
                $movement->supplier?->name,
                $movement->reference,
                $movement->note,
                $movement->creator?->name,
            ],
        );
    }

    public function payrolls(Request $request, PayrollQuery $payrolls): StreamedResponse
    {
        Gate::authorize('manage-payroll');

        return $this->exporter->stream(
            $this->filename('bang-luong'),
            ['Nhân viên', 'Từ ngày', 'Đến ngày', 'Lương cứng', 'Số ca', 'Đơn giá ca', 'Lương ca', 'Chuyên cần', 'HH trong giờ', 'HH ngoài giờ', 'KPI ngày', 'KPI bill', 'Phụ cấp', 'Khấu trừ', 'Tổng hệ thống', 'Làm tròn', 'Thực lĩnh', 'Trạng thái', 'Ngày trả'],
            $payrolls->build($request, $request->user())->with('employee')->orderBy('payrolls.id'),
            fn (Payroll $payroll): array => [
                $payroll->employee?->name,
                $payroll->period_start->format('d/m/Y'),
                $payroll->period_end->format('d/m/Y'),
                $payroll->base_salary,
                $payroll->shift_count,
                $payroll->shift_rate,
                $payroll->shift_pay,
                $payroll->attendance_bonus,
                $payroll->regular_commission_pay,
                $payroll->overtime_commission_pay,
                $payroll->daily_kpi_bonus,
                $payroll->bill_kpi_bonus,
                $payroll->adjustment,
                $payroll->deduction,
                $payroll->calculated_total,
                $payroll->rounding_adjustment,
                $payroll->final_total,
                $payroll->status->label(),
                $payroll->paid_at?->format('d/m/Y H:i'),
            ],
        );
    }

    private function filename(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd-His').'.csv';
    }
}
