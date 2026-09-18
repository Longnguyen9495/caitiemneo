<?php

namespace App\Services\Ai;

use App\Models\Appointment;
use App\Models\AttendanceRecord;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\User;
use App\Services\ReportService;
use App\Support\BranchContext;
use App\Support\ReportPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AiBusinessContext
{
    private const DETAIL_LIMIT = 50;

    public function __construct(
        private BranchContext $branches,
        private ReportService $reports,
    ) {}

    /** @return array<string, mixed> */
    public function build(User $user, string $question = ''): array
    {
        $branchIds = $this->branches->scopeIds();
        $plan = AiContextPlan::fromQuestion($question);
        $period = ReportPeriod::fromRequest(Request::create('/ai-context', 'GET', [
            'from' => $plan->from->toDateString(),
            'to' => $plan->to->toDateString(),
        ]));

        $context = [
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
            'query_plan' => [
                'domains' => $plan->domains,
                'period' => [
                    'label' => $period->label(),
                    'from' => $period->from->toDateString(),
                    'to' => $period->to->toDateString(),
                ],
                'detail_limit_per_domain' => self::DETAIL_LIMIT,
            ],
            'data' => [],
        ];

        if ($branchIds === []) {
            $context['warning'] = 'Người xem không có phạm vi chi nhánh khả dụng.';

            return $context;
        }

        foreach ($plan->domains as $domain) {
            $data = match ($domain) {
                'invoices' => $this->invoiceContext($period, $branchIds),
                'appointments' => $this->appointmentContext($period, $branchIds),
                'customers' => $this->customerContext($period, $branchIds),
                'services' => $this->serviceContext($period, $branchIds),
                'cash' => $user->canViewCashPosition()
                    ? $this->cashContext($period, $branchIds)
                    : ['access_denied' => true],
                'inventory' => $this->inventoryContext($branchIds),
                'attendance' => $this->attendanceContext($period, $branchIds),
                'payroll' => Gate::forUser($user)->allows('view-profit-reports')
                    ? $this->payrollContext($period, $branchIds)
                    : ['access_denied' => true],
                default => $this->overviewContext($user, $period, $branchIds),
            };

            $context['data'][$domain] = $data;

            // Giữ cấu trúc context tổng quan cũ để các consumer nội bộ hiện hữu
            // vẫn hoạt động trong khi dữ liệu theo miền nằm dưới `data`.
            if ($domain === 'overview') {
                $context = array_merge($context, $data);
            }
        }

        return $context;
    }

    /** @param array<int, int> $branchIds */
    private function overviewContext(User $user, ReportPeriod $period, array $branchIds): array
    {
        $summary = $this->reports->summary($period, $branchIds);

        if (! Gate::forUser($user)->allows('view-profit-reports')) {
            unset($summary['inventory_cost'], $summary['payroll_cost'], $summary['gross_margin']);
        }

        if (! $user->canViewCashPosition()) {
            unset($summary['cash_income'], $summary['cash_expense'], $summary['cash_net']);
        }

        return [
            'summary' => $summary,
            'appointments_by_status' => $this->reports->appointmentsByStatus($period, $branchIds),
            'top_services' => $this->serviceContext($period, $branchIds)['top_services'],
            'top_employees' => $this->reports->revenueByEmployee($period, 10, $branchIds)
                ->map(fn (object $row): array => [
                    'name' => $row->employee_name,
                    'revenue' => (string) $row->revenue_total,
                ])->values()->all(),
            'low_stock' => $this->lowStockByBranch($branchIds),
        ];
    }

    /** @param array<int, int> $branchIds */
    private function invoiceContext(ReportPeriod $period, array $branchIds): array
    {
        $base = Invoice::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('created_at', [$period->from, $period->to]);

        $paid = Invoice::query()
            ->whereIn('branch_id', $branchIds)
            ->paidBetween($period->from, $period->to);

        return [
            'created_count' => (clone $base)->count(),
            'created_total' => (string) (clone $base)->sum('total'),
            'paid_count' => (clone $paid)->count(),
            'paid_revenue' => (string) (clone $paid)->sum('total'),
            'by_status' => (clone $base)->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')->pluck('total', 'status')->all(),
            'invoices' => (clone $base)->with(['branch:id,name', 'employee:id,name'])
                ->latest('created_at')->limit(self::DETAIL_LIMIT)->get()
                ->map(fn (Invoice $invoice): array => [
                    'number' => $invoice->number,
                    'branch' => $invoice->branch?->name,
                    'customer' => $invoice->customer_name,
                    'employee' => $invoice->employee?->name,
                    'status' => $invoice->status->value,
                    'payment_method' => $invoice->payment_method?->value,
                    'subtotal' => (string) $invoice->subtotal,
                    'discount' => (string) $invoice->discount,
                    'total' => (string) $invoice->total,
                    'created_at' => $invoice->created_at?->toDateTimeString(),
                    'paid_at' => $invoice->paid_at?->toDateTimeString(),
                ])->all(),
        ];
    }

    /** @param array<int, int> $branchIds */
    private function appointmentContext(ReportPeriod $period, array $branchIds): array
    {
        $base = Appointment::query()->whereIn('branch_id', $branchIds)
            ->whereBetween('starts_at', [$period->from, $period->to]);

        return [
            'count' => (clone $base)->count(),
            'by_status' => (clone $base)->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')->pluck('total', 'status')->all(),
            'appointments' => (clone $base)->with(['branch:id,name', 'employee:id,name', 'services.service:id,name'])
                ->orderBy('starts_at')->limit(self::DETAIL_LIMIT)->get()
                ->map(fn (Appointment $appointment): array => [
                    'id' => $appointment->id,
                    'branch_id' => $appointment->branch_id,
                    'branch' => $appointment->branch?->name,
                    'customer' => $appointment->customer_name,
                    'employee_id' => $appointment->employee_id,
                    'employee' => $appointment->employee?->name,
                    'starts_at' => $appointment->starts_at?->toDateTimeString(),
                    'duration_minutes' => $appointment->duration_minutes,
                    'status' => $appointment->status->value,
                    'service_ids' => $appointment->services->pluck('service_id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
                    'services' => $appointment->services->map(fn ($item) => $item->service?->name)->filter()->values()->all(),
                ])->all(),
        ];
    }

    /** @param array<int, int> $branchIds */
    private function customerContext(ReportPeriod $period, array $branchIds): array
    {
        $appointments = Appointment::query()->whereIn('branch_id', $branchIds)
            ->whereBetween('starts_at', [$period->from, $period->to]);

        return [
            'unique_customers' => (clone $appointments)->distinct()->count('customer_id'),
            'customers' => (clone $appointments)->selectRaw('customer_id, customer_name, customer_phone, COUNT(*) as appointment_count')
                ->groupBy('customer_id', 'customer_name', 'customer_phone')
                ->orderByDesc('appointment_count')->limit(self::DETAIL_LIMIT)->get()->toArray(),
        ];
    }

    /** @param array<int, int> $branchIds */
    private function serviceContext(ReportPeriod $period, array $branchIds): array
    {
        return [
            'top_services' => $this->reports->revenueByService($period, self::DETAIL_LIMIT, $branchIds)
                ->map(fn (object $row): array => [
                    'name' => $row->service_name,
                    'quantity' => (string) $row->quantity_total,
                    'revenue' => (string) $row->revenue_total,
                ])->values()->all(),
        ];
    }

    /** @param array<int, int> $branchIds */
    private function cashContext(ReportPeriod $period, array $branchIds): array
    {
        return [
            'summary' => $this->reports->cashFlow($period, $branchIds),
            'transactions' => CashTransaction::query()->active()->whereIn('branch_id', $branchIds)
                ->whereBetween('occurred_at', [$period->from, $period->to])
                ->with('branch:id,name')->latest('occurred_at')->limit(self::DETAIL_LIMIT)->get()
                ->map(fn (CashTransaction $transaction): array => [
                    'branch' => $transaction->branch?->name,
                    'type' => $transaction->type->value,
                    'category' => $transaction->category->value,
                    'amount' => (string) $transaction->amount,
                    'payment_method' => $transaction->payment_method?->value,
                    'reference' => $transaction->reference,
                    'occurred_at' => $transaction->occurred_at?->toDateTimeString(),
                ])->all(),
        ];
    }

    /** @param array<int, int> $branchIds */
    private function inventoryContext(array $branchIds): array
    {
        return ['low_stock' => $this->lowStockByBranch($branchIds)];
    }

    /** @param array<int, int> $branchIds */
    private function attendanceContext(ReportPeriod $period, array $branchIds): array
    {
        $base = AttendanceRecord::query()->whereIn('branch_id', $branchIds)
            ->whereBetween('work_date', [$period->from->toDateString(), $period->to->toDateString()]);

        return [
            'count' => (clone $base)->count(),
            'late_count' => (clone $base)->where('late_minutes', '>', 0)->count(),
            'missing_checkout_count' => (clone $base)->missingCheckOut()->count(),
            'by_status' => (clone $base)->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')->pluck('total', 'status')->all(),
            'records' => (clone $base)->with(['branch:id,name', 'employee:id,name'])
                ->orderByDesc('work_date')->limit(self::DETAIL_LIMIT)->get()
                ->map(fn (AttendanceRecord $record): array => [
                    'branch' => $record->branch?->name,
                    'employee' => $record->employee?->name,
                    'work_date' => $record->work_date?->toDateString(),
                    'shift' => $record->shift_name,
                    'status' => $record->status->value,
                    'checked_in_at' => $record->checked_in_at?->toDateTimeString(),
                    'checked_out_at' => $record->checked_out_at?->toDateTimeString(),
                    'late_minutes' => (int) $record->late_minutes,
                    'overtime_minutes' => (int) $record->overtime_minutes,
                ])->all(),
        ];
    }

    /** @param array<int, int> $branchIds */
    private function payrollContext(ReportPeriod $period, array $branchIds): array
    {
        $base = Payroll::query()->where(fn (Builder $query) => $query
            ->whereIn('paying_branch_id', $branchIds)
            ->orWhereHas('allocations', fn (Builder $allocation) => $allocation->whereIn('branch_id', $branchIds)))
            ->whereDate('period_start', '<=', $period->to)
            ->whereDate('period_end', '>=', $period->from);

        return [
            'count' => (clone $base)->count(),
            'total' => (string) (clone $base)->sum('final_total'),
            'payrolls' => (clone $base)->with(['employee:id,name', 'payingBranch:id,name'])
                ->latest('period_end')->limit(self::DETAIL_LIMIT)->get()
                ->map(fn (Payroll $payroll): array => [
                    'employee' => $payroll->employee?->name,
                    'paying_branch' => $payroll->payingBranch?->name,
                    'period_start' => $payroll->period_start?->toDateString(),
                    'period_end' => $payroll->period_end?->toDateString(),
                    'status' => $payroll->status->value,
                    'total' => (string) $payroll->final_total,
                    'paid_at' => $payroll->paid_at?->toDateTimeString(),
                ])->all(),
        ];
    }

    /**
     * @param  array<int, int>  $branchIds
     * @return array<int, mixed>
     */
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
