<?php

namespace App\Services\Ai;

use App\Models\Appointment;
use App\Models\AttendanceRecord;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\User;
use App\Services\ReportService;
use App\Support\BranchContext;
use App\Support\ReportPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * Ảnh chụp sơ bộ tình hình kinh doanh, gửi kèm prompt để model định hướng.
 *
 * Trước đây lớp này đổ tới 50 bản ghi chi tiết cho mỗi miền dữ liệu vào prompt,
 * khiến một câu hỏi chạm hai miền đã ngốn hàng chục nghìn token. Giờ chi tiết
 * nằm ở các công cụ model tự gọi, nên ở đây chỉ còn số tổng hợp: đủ để model
 * biết có gì mà tra, không đủ để làm phình prompt.
 */
class AiBusinessContext
{
    /**
     * Số bản ghi mẫu kèm theo cho mỗi miền.
     *
     * Vài dòng đầu giúp model biết dữ liệu trông ra sao và có tồn tại hay không;
     * cần đầy đủ thì nó gọi công cụ.
     */
    private const SAMPLE_LIMIT = 5;

    private const CACHE_SECONDS = 60;

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
            'today' => now()->toDateString(),
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
                'period_phrase' => $plan->periodPhrase,
                'period_inferred' => $plan->periodInferred,
                'sample_limit_per_domain' => self::SAMPLE_LIMIT,
                'note' => 'Đây chỉ là ảnh chụp sơ bộ. Gọi công cụ để lấy số liệu đầy đủ và chính xác.',
            ],
            'data' => [],
        ];

        if ($branchIds === []) {
            $context['warning'] = 'Người xem không có phạm vi chi nhánh khả dụng.';

            return $context;
        }

        $context['data'] = $this->cachedDomains($user, $plan->domains, $period, $branchIds);

        // Giữ cấu trúc context tổng quan cũ để các consumer nội bộ hiện hữu vẫn
        // hoạt động trong khi dữ liệu theo miền nằm dưới `data`.
        if (isset($context['data']['overview'])) {
            $context = array_merge($context, $context['data']['overview']);
        }

        return $context;
    }

    /**
     * Dựng dữ liệu từng miền, có nhớ tạm.
     *
     * Người dùng thường hỏi nối nhau vài câu quanh cùng một khoảng thời gian, và
     * mỗi lần dựng lại là một loạt truy vấn tổng hợp y hệt lần trước. Nhớ tạm một
     * phút cắt hẳn phần lặp đó mà vẫn đủ mới cho số liệu vận hành.
     *
     * @param  array<int, string>  $domains
     * @param  array<int, int>  $branchIds
     * @return array<string, mixed>
     */
    private function cachedDomains(User $user, array $domains, ReportPeriod $period, array $branchIds): array
    {
        sort($domains);
        sort($branchIds);

        $key = 'ai-context:'.hash('sha256', implode('|', [
            $user->id,
            implode(',', $domains),
            implode(',', $branchIds),
            $period->from->toDateString(),
            $period->to->toDateString(),
        ]));

        return Cache::remember($key, self::CACHE_SECONDS, function () use ($user, $domains, $period, $branchIds): array {
            $data = [];

            foreach ($domains as $domain) {
                $data[$domain] = match ($domain) {
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
            }

            return $data;
        });
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
            'top_employees' => $this->reports->revenueByEmployee($period, 5, $branchIds)
                ->map(fn (object $row): array => [
                    'name' => $row->employee_name,
                    'revenue' => (string) $row->revenue_total,
                ])->values()->all(),
            'low_stock' => $this->lowStockRows($branchIds),
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
            'sample_invoices' => (clone $base)->with(['branch:id,name'])
                ->latest('created_at')->limit(self::SAMPLE_LIMIT)->get()
                ->map(fn (Invoice $invoice): array => [
                    'number' => $invoice->number,
                    'branch' => $invoice->branch?->name,
                    'customer' => $invoice->customer_name,
                    'status' => $invoice->status->value,
                    'total' => (string) $invoice->total,
                    'created_at' => $invoice->created_at?->toDateTimeString(),
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
            'sample_appointments' => (clone $base)->with(['branch:id,name', 'employee:id,name'])
                ->orderBy('starts_at')->limit(self::SAMPLE_LIMIT)->get()
                ->map(fn (Appointment $appointment): array => [
                    'id' => (int) $appointment->id,
                    'branch_id' => (int) $appointment->branch_id,
                    'branch' => $appointment->branch?->name,
                    'customer_name' => $appointment->customer_name,
                    'customer_phone' => $appointment->customer_phone,
                    'employee' => $appointment->employee?->name,
                    'starts_at' => $appointment->starts_at?->toIso8601String(),
                    'duration_minutes' => (int) $appointment->duration_minutes,
                    'status' => $appointment->status->value,
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
            'top_customers' => (clone $appointments)
                ->selectRaw('customer_id, customer_name, customer_phone, COUNT(*) as appointment_count')
                ->groupBy('customer_id', 'customer_name', 'customer_phone')
                ->orderByDesc('appointment_count')
                ->limit(self::SAMPLE_LIMIT)
                ->get()
                ->toArray(),
        ];
    }

    /** @param array<int, int> $branchIds */
    private function serviceContext(ReportPeriod $period, array $branchIds): array
    {
        return [
            'top_services' => $this->reports->revenueByService($period, self::SAMPLE_LIMIT, $branchIds)
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
        return ['summary' => $this->reports->cashFlow($period, $branchIds)];
    }

    /** @param array<int, int> $branchIds */
    private function inventoryContext(array $branchIds): array
    {
        $rows = $this->lowStockRows($branchIds);

        return [
            'low_stock_count' => count($rows),
            'sample_low_stock' => array_slice($rows, 0, self::SAMPLE_LIMIT),
        ];
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
        ];
    }

    /**
     * Hàng sắp hết theo từng chi nhánh.
     *
     * Vòng lặp theo chi nhánh là cố ý: các scope tồn kho nhận một branch_id để
     * tính tồn và định mức riêng cho chi nhánh đó nên không gộp được thành một
     * câu truy vấn. Bù lại số chi nhánh luôn nhỏ và kết quả được nhớ tạm cùng
     * phần còn lại của ngữ cảnh.
     *
     * @param  array<int, int>  $branchIds
     * @return array<int, array<string, mixed>>
     */
    private function lowStockRows(array $branchIds): array
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
