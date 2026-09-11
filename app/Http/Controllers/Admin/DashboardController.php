<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CashTransactionType;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\Money;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private BranchContext $branchContext) {}

    public function __invoke(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $today = now()->startOfDay();
        $branchIds = $this->branchContext->scopeIds() ?: [0];
        $lowStockBranchId = $this->branchContext->viewingAll() ? null : $this->branchContext->currentId();

        $mayViewRevenue = $user->canViewRevenueFigures();
        $mayViewCash = $user->canViewCashPosition();

        return view('dashboard', [
            'mayViewRevenue' => $mayViewRevenue,
            'mayViewCash' => $mayViewCash,
            // A figure the viewer may not see is never queried, so it cannot
            // leak through a debug bar, a query log or a timing difference.
            'todayRevenue' => $mayViewRevenue ? $this->todayRevenue($branchIds, $today) : null,
            'cashBalance' => $mayViewCash ? $this->cashBalance($branchIds) : null,
            'todayAppointments' => Appointment::query()
                ->whereIn('branch_id', $branchIds)
                ->whereDate('starts_at', $today)
                ->count(),
            'lowStockProducts' => Product::query()
                ->where('is_active', true)
                ->lowStock($lowStockBranchId)
                ->count(),
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

    /** @param  array<int, int>  $branchIds */
    private function todayRevenue(array $branchIds, mixed $today): string
    {
        return (string) Invoice::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', InvoiceStatus::Paid->value)
            ->whereDate('paid_at', $today)
            ->sum('total');
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
