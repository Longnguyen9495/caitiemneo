<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CashTransactionType;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\Product;
use App\Support\BranchContext;
use App\Support\Money;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private BranchContext $branchContext) {}

    public function __invoke(): View
    {
        $today = now()->startOfDay();
        $branchIds = $this->branchContext->scopeIds() ?: [0];
        $lowStockBranchId = $this->branchContext->viewingAll() ? null : $this->branchContext->currentId();

        return view('dashboard', [
            'todayRevenue' => Invoice::query()
                ->whereIn('branch_id', $branchIds)
                ->where('status', InvoiceStatus::Paid->value)
                ->whereDate('paid_at', $today)
                ->sum('total'),
            'todayAppointments' => Appointment::query()
                ->whereIn('branch_id', $branchIds)
                ->whereDate('starts_at', $today)
                ->count(),
            'lowStockProducts' => Product::query()
                ->where('is_active', true)
                ->lowStock($lowStockBranchId)
                ->count(),
            'cashBalance' => $this->cashBalance($branchIds),
            'upcomingAppointments' => Appointment::query()
                ->with(['employee', 'branch'])
                ->whereIn('branch_id', $branchIds)
                ->active()
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->limit(6)
                ->get(),
            'branchLabel' => $this->branchContext->viewingAll()
                ? 'Tất cả chi nhánh'
                : ($this->branchContext->current()?->name ?? 'Chưa chọn chi nhánh'),
        ]);
    }

    /**
     * Net cash position of the selected branches, aggregated in a single row.
     *
     * @param  array<int, int>  $branchIds
     */
    private function cashBalance(array $branchIds): string
    {
        $row = CashTransaction::query()
            ->active()
            ->whereIn('branch_id', $branchIds)
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as income_total', [CashTransactionType::Income->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as expense_total', [CashTransactionType::Expense->value])
            ->first();

        return Money::toDecimal(
            Money::toMinor($row?->income_total ?? 0) - Money::toMinor($row?->expense_total ?? 0)
        );
    }
}
