<?php

namespace App\Services\Ai;

use App\Models\Product;
use App\Models\User;
use App\Services\ReportService;
use App\Support\BranchContext;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AiBusinessContext
{
    public function __construct(
        private BranchContext $branches,
        private ReportService $reports,
    ) {}

    /** @return array<string, mixed> */
    public function build(User $user): array
    {
        $branchIds = $this->branches->scopeIds() ?: [0];
        $period = ReportPeriod::fromRequest(Request::create('/ai-context'));
        $summary = $this->reports->summary($period, $branchIds);

        if (! Gate::forUser($user)->allows('view-profit-reports')) {
            unset($summary['inventory_cost'], $summary['payroll_cost'], $summary['gross_margin']);
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'timezone' => (string) config('app.timezone'),
            'viewer' => [
                'role' => $user->role->value,
                'may_view_profit' => Gate::forUser($user)->allows('view-profit-reports'),
                'may_view_cash' => $user->canViewCashPosition(),
            ],
            'scope' => [
                'viewing_all' => $this->branches->viewingAll(),
                'branch_ids' => $branchIds,
                'branches' => $this->branches->available()
                    ->whereIn('id', $branchIds)
                    ->map(fn ($branch): array => [
                        'id' => (int) $branch->id,
                        'name' => $branch->name,
                    ])->values()->all(),
            ],
            'period' => [
                'label' => $period->label(),
                'from' => $period->from->toDateString(),
                'to' => $period->to->toDateString(),
            ],
            'summary' => $summary,
            'appointments_by_status' => $this->reports->appointmentsByStatus($period, $branchIds),
            'top_services' => $this->reports->revenueByService($period, 10, $branchIds)
                ->map(fn (object $row): array => [
                    'name' => $row->service_name,
                    'quantity' => (string) $row->quantity_total,
                    'revenue' => (string) $row->revenue_total,
                ])->values()->all(),
            'top_employees' => $this->reports->revenueByEmployee($period, 10, $branchIds)
                ->map(fn (object $row): array => [
                    'name' => $row->employee_name,
                    'revenue' => (string) $row->revenue_total,
                ])->values()->all(),
            'low_stock' => $this->lowStockByBranch($branchIds),
        ];
    }

    /** @return array<int, mixed> */
    private function lowStockByBranch(array $branchIds): array
    {
        $branchNames = $this->branches->available()->pluck('name', 'id');
        $rows = [];

        foreach ($branchIds as $branchId) {
            $products = Product::query()
                ->where('is_active', true)
                ->stockedAt($branchId)
                ->withCurrentStock($branchId)
                ->withBranchMinimum($branchId)
                ->lowStock($branchId)
                ->orderBy('name')
                ->limit(20)
                ->get();

            foreach ($products as $product) {
                $rows[] = [
                    'branch_id' => $branchId,
                    'branch' => $branchNames->get($branchId),
                    'product_id' => (int) $product->id,
                    'product' => $product->name,
                    'unit' => $product->unit,
                    'stock' => $product->current_stock,
                    'minimum' => $product->effective_minimum_stock,
                ];
            }
        }

        return $rows;
    }
}
