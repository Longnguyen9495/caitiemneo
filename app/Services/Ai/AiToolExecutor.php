<?php

namespace App\Services\Ai;

use App\Models\Appointment;
use App\Models\AttendanceRecord;
use App\Models\CashTransaction;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\ReportService;
use App\Support\BranchContext;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Chạy các công cụ mà model yêu cầu.
 *
 * Model chỉ nêu tên công cụ và tham số; mọi giới hạn quyền và phạm vi chi nhánh
 * đều được áp lại ở đây. Model không thể nới rộng phạm vi bằng cách đưa
 * branch_id lạ vào tham số — branch_id đó bị giao với danh sách chi nhánh người
 * dùng thực sự được xem.
 */
class AiToolExecutor
{
    public function __construct(
        private BranchContext $branches,
        private ReportService $reports,
        private AiToolRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function run(User $user, string $name, array $arguments): array
    {
        if (! $this->registry->supports($name)) {
            return ['error' => 'Công cụ không tồn tại.'];
        }

        $branchIds = $this->resolveBranchIds($arguments);

        if ($branchIds === []) {
            return ['error' => 'Người dùng không có chi nhánh nào được phép xem.'];
        }

        return match ($name) {
            'get_overview' => $this->overview($user, $arguments, $branchIds),
            'get_invoices' => $this->invoices($arguments, $branchIds),
            'get_appointments' => $this->appointments($arguments, $branchIds),
            'find_appointment' => $this->findAppointment($arguments, $branchIds),
            'find_customer' => $this->findCustomer($arguments, $branchIds),
            'get_services' => $this->services($arguments, $branchIds),
            'get_cash_flow' => $this->cashFlow($user, $arguments, $branchIds),
            'get_inventory' => $this->inventory($arguments, $branchIds),
            'get_attendance' => $this->attendance($arguments, $branchIds),
            'get_payroll' => $this->payroll($user, $arguments, $branchIds),
            'get_employees' => $this->employees($arguments, $branchIds),
            default => ['error' => 'Công cụ chưa được cài đặt.'],
        };
    }

    /**
     * Giao branch_id model đưa ra với phạm vi thật của người dùng.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<int, int>
     */
    private function resolveBranchIds(array $arguments): array
    {
        $allowed = $this->branches->scopeIds();
        $requested = $arguments['branch_id'] ?? null;

        if (! is_numeric($requested)) {
            return $allowed;
        }

        $requested = (int) $requested;

        return in_array($requested, $allowed, true) ? [$requested] : $allowed;
    }

    /** @param array<string, mixed> $arguments */
    private function period(array $arguments, string $fallbackFrom = '', string $fallbackTo = ''): ReportPeriod
    {
        $from = is_string($arguments['from'] ?? null) && $arguments['from'] !== ''
            ? $arguments['from']
            : ($fallbackFrom ?: CarbonImmutable::now()->startOfMonth()->toDateString());

        $to = is_string($arguments['to'] ?? null) && $arguments['to'] !== ''
            ? $arguments['to']
            : ($fallbackTo ?: CarbonImmutable::now()->endOfMonth()->toDateString());

        return ReportPeriod::fromRequest(Request::create('/ai-tool', 'GET', [
            'from' => $from,
            'to' => $to,
        ]));
    }

    /** @param array<string, mixed> $arguments */
    private function limit(array $arguments): int
    {
        $limit = $arguments['limit'] ?? AiToolRegistry::DEFAULT_LIMIT;

        if (! is_numeric($limit)) {
            return AiToolRegistry::DEFAULT_LIMIT;
        }

        return max(1, min(AiToolRegistry::MAX_LIMIT, (int) $limit));
    }

    /** @return array<string, mixed> */
    private function periodEcho(ReportPeriod $period): array
    {
        return [
            'from' => $period->from->toDateString(),
            'to' => $period->to->toDateString(),
            'label' => $period->label(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, int>  $branchIds
     * @return array<string, mixed>
     */
    private function overview(User $user, array $arguments, array $branchIds): array
    {
        $period = $this->period($arguments);
        $summary = $this->reports->summary($period, $branchIds);

        if (! Gate::forUser($user)->allows('view-profit-reports')) {
            unset($summary['inventory_cost'], $summary['payroll_cost'], $summary['gross_margin']);
        }

        if (! $user->canViewCashPosition()) {
            unset($summary['cash_income'], $summary['cash_expense'], $summary['cash_net']);
        }

        return [
            'period' => $this->periodEcho($period),
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
            'low_stock' => $this->lowStockRows($branchIds, null, 15),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, int>  $branchIds
     * @return array<string, mixed>
     */
    private function invoices(array $arguments, array $branchIds): array
    {
        $period = $this->period($arguments);
        $limit = $this->limit($arguments);
        $status = is_string($arguments['status'] ?? null) ? $arguments['status'] : null;

        $created = Invoice::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('created_at', [$period->from, $period->to])
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status));

        $paid = Invoice::query()
            ->whereIn('branch_id', $branchIds)
            ->paidBetween($period->from, $period->to);

        $result = [
            'period' => $this->periodEcho($period),
            'created_count' => (clone $created)->count(),
            'created_total' => (string) (clone $created)->sum('total'),
            'paid_count' => (clone $paid)->count(),
            'paid_revenue' => (string) (clone $paid)->sum('total'),
            'by_status' => (clone $created)->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')->pluck('total', 'status')->all(),
            'invoices' => (clone $created)->with(['branch:id,name', 'employee:id,name'])
                ->latest('created_at')->limit($limit)->get()
                ->map(fn (Invoice $invoice): array => [
                    'number' => $invoice->number,
                    'branch' => $invoice->branch?->name,
                    'customer' => $invoice->customer_name,
                    'employee' => $invoice->employee?->name,
                    'status' => $invoice->status->value,
                    'total' => (string) $invoice->total,
                    'created_at' => $invoice->created_at?->toDateTimeString(),
                    'paid_at' => $invoice->paid_at?->toDateTimeString(),
                ])->all(),
        ];

        if (($arguments['group_by_day'] ?? false) === true) {
            $result['paid_revenue_by_day'] = (clone $paid)
                ->selectRaw('DATE(paid_at) as day, COUNT(*) as invoice_count, COALESCE(SUM(total), 0) as revenue')
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->map(fn (object $row): array => [
                    'day' => (string) $row->day,
                    'invoice_count' => (int) $row->invoice_count,
                    'revenue' => (string) $row->revenue,
                ])->all();
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, int>  $branchIds
     * @return array<string, mixed>
     */
    private function appointments(array $arguments, array $branchIds): array
    {
        $period = $this->period($arguments);
        $limit = $this->limit($arguments);
        $status = is_string($arguments['status'] ?? null) ? $arguments['status'] : null;

        $base = Appointment::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('starts_at', [$period->from, $period->to])
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status));

        return [
            'period' => $this->periodEcho($period),
            'count' => (clone $base)->count(),
            'by_status' => (clone $base)->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')->pluck('total', 'status')->all(),
            'appointments' => $this->mapAppointments(
                (clone $base)->with(['branch:id,name', 'employee:id,name', 'services.service:id,name'])
                    ->orderBy('starts_at')->limit($limit)->get()
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, int>  $branchIds
     * @return array<string, mixed>
     */
    private function findAppointment(array $arguments, array $branchIds): array
    {
        $keyword = trim((string) ($arguments['keyword'] ?? ''));

        if ($keyword === '') {
            return ['error' => 'Cần nêu tên hoặc số điện thoại khách để tìm.'];
        }

        $now = CarbonImmutable::now();
        $from = is_string($arguments['from'] ?? null) && $arguments['from'] !== ''
            ? CarbonImmutable::parse($arguments['from'])->startOfDay()
            : $now->subDays(30)->startOfDay();
        $to = is_string($arguments['to'] ?? null) && $arguments['to'] !== ''
            ? CarbonImmutable::parse($arguments['to'])->endOfDay()
            : $now->addDays(90)->endOfDay();

        $escaped = $this->escapeLike($keyword);

        $matches = Appointment::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('starts_at', [$from, $to])
            ->where(fn (Builder $query) => $query
                ->where('customer_name', 'like', "%{$escaped}%")
                ->orWhere('customer_phone', 'like', "%{$escaped}%"))
            ->with(['branch:id,name', 'employee:id,name', 'services.service:id,name'])
            ->orderBy('starts_at')
            ->limit($this->limit($arguments))
            ->get();

        return [
            'keyword' => $keyword,
            'searched_between' => [$from->toDateString(), $to->toDateString()],
            'match_count' => $matches->count(),
            'appointments' => $this->mapAppointments($matches),
        ];
    }

    /**
     * @param  Collection<int, Appointment>|\Illuminate\Database\Eloquent\Collection<int, Appointment>  $appointments
     * @return array<int, array<string, mixed>>
     */
    private function mapAppointments($appointments): array
    {
        return $appointments->map(fn (Appointment $appointment): array => [
            'id' => (int) $appointment->id,
            'branch_id' => (int) $appointment->branch_id,
            'branch' => $appointment->branch?->name,
            'customer_name' => $appointment->customer_name,
            'customer_phone' => $appointment->customer_phone,
            'employee_id' => $appointment->employee_id,
            'employee' => $appointment->employee?->name,
            'starts_at' => $appointment->starts_at?->toIso8601String(),
            'starts_at_human' => $appointment->starts_at?->format('H:i d/m/Y'),
            'duration_minutes' => (int) $appointment->duration_minutes,
            'status' => $appointment->status->value,
            'service_ids' => $appointment->services->pluck('service_id')
                ->map(fn (mixed $id): int => (int) $id)->values()->all(),
            'services' => $appointment->services->map(fn ($item) => $item->service?->name)
                ->filter()->values()->all(),
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, int>  $branchIds
     * @return array<string, mixed>
     */
    private function findCustomer(array $arguments, array $branchIds): array
    {
        $keyword = trim((string) ($arguments['keyword'] ?? ''));

        if ($keyword === '') {
            return ['error' => 'Cần nêu tên hoặc số điện thoại để tìm khách.'];
        }

        $escaped = $this->escapeLike($keyword);

        $customers = Customer::query()
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$escaped}%")
                ->orWhere('phone', 'like', "%{$escaped}%"))
            ->withCount(['appointments as appointment_count' => fn (Builder $query) => $query->whereIn('branch_id', $branchIds)])
            ->withMax(['appointments as last_appointment_at' => fn (Builder $query) => $query->whereIn('branch_id', $branchIds)], 'starts_at')
            ->orderByDesc('appointment_count')
            ->limit($this->limit($arguments))
            ->get();

        return [
            'keyword' => $keyword,
            'match_count' => $customers->count(),
            'customers' => $customers->map(fn (Customer $customer): array => [
                'id' => (int) $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'appointment_count' => (int) $customer->appointment_count,
                'last_appointment_at' => $customer->last_appointment_at,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, int>  $branchIds
     * @return array<string, mixed>
     */
    private function services(array $arguments, array $branchIds): array
    {
        $period = $this->period($arguments);

        return [
            'period' => $this->periodEcho($period),
            'top_services' => $this->reports->revenueByService($period, $this->limit($arguments), $branchIds)
                ->map(fn (object $row): array => [
                    'name' => $row->service_name,
                    'quantity' => (string) $row->quantity_total,
                    'revenue' => (string) $row->revenue_total,
                ])->values()->all(),
            'bookable_services' => Service::query()
                ->where('is_active', true)
                ->orderBy('display_order')
                ->orderBy('name')
                ->limit(AiToolRegistry::MAX_LIMIT)
                ->get(['id', 'name', 'price', 'price_min', 'price_max'])
                ->map(fn (Service $service): array => [
                    'id' => (int) $service->id,
                    'name' => $service->name,
                    'price' => (string) $service->price,
                    'price_min' => $service->price_min !== null ? (string) $service->price_min : null,
                    'price_max' => $service->price_max !== null ? (string) $service->price_max : null,
                ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, int>  $branchIds
     * @return array<string, mixed>
     */
    private function cashFlow(User $user, array $arguments, array $branchIds): array
    {
        if (! $user->canViewCashPosition()) {
            return ['error' => 'Người dùng không có quyền xem sổ quỹ.'];
        }

        $period = $this->period($arguments);

        return [
            'period' => $this->periodEcho($period),
            'summary' => $this->reports->cashFlow($period, $branchIds),
            'transactions' => CashTransaction::query()->active()
                ->whereIn('branch_id', $branchIds)
                ->whereBetween('occurred_at', [$period->from, $period->to])
                ->with('branch:id,name')
                ->latest('occurred_at')
                ->limit($this->limit($arguments))
                ->get()
                ->map(fn (CashTransaction $transaction): array => [
                    'branch' => $transaction->branch?->name,
                    'type' => $transaction->type->value,
                    'category' => $transaction->category->value,
                    'amount' => (string) $transaction->amount,
                    'payment_method' => $transaction->payment_method?->value,
                    'occurred_at' => $transaction->occurred_at?->toDateTimeString(),
                ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, int>  $branchIds
     * @return array<string, mixed>
     */
    private function inventory(array $arguments, array $branchIds): array
    {
        $keyword = is_string($arguments['keyword'] ?? null) && $arguments['keyword'] !== ''
            ? $arguments['keyword']
            : null;

        return [
            'low_stock' => $this->lowStockRows($branchIds, $keyword, $this->limit($arguments)),
        ];
    }

    /**
     * Tồn kho theo từng chi nhánh.
     *
     * Vòng lặp theo chi nhánh là cố ý: các scope tồn kho nhận một branch_id để
     * tính tồn và định mức riêng cho chi nhánh đó, không gộp được thành một câu
     * truy vấn. Bù lại số chi nhánh luôn nhỏ và mỗi vòng chỉ chạy một truy vấn
     * có giới hạn, nên chi phí vẫn nằm trong tầm kiểm soát.
     *
     * @param  array<int, int>  $branchIds
     * @return array<int, array<string, mixed>>
     */
    private function lowStockRows(array $branchIds, ?string $keyword, int $limit): array
    {
        $branchNames = $this->branches->available()->pluck('name', 'id');
        $rows = [];

        foreach ($branchIds as $branchId) {
            if (count($rows) >= $limit) {
                break;
            }

            $products = Product::query()
                ->where('is_active', true)
                ->when($keyword !== null, fn (Builder $query) => $query
                    ->where('name', 'like', '%'.$this->escapeLike($keyword).'%'))
                ->stockedAt($branchId)
                ->withCurrentStock($branchId)
                ->withBranchMinimum($branchId)
                ->when($keyword === null, fn (Builder $query) => $query->lowStock($branchId))
                ->orderBy('name')
                ->limit($limit - count($rows))
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

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, int>  $branchIds
     * @return array<string, mixed>
     */
    private function attendance(array $arguments, array $branchIds): array
    {
        $period = $this->period($arguments);
        $keyword = is_string($arguments['employee_keyword'] ?? null) && $arguments['employee_keyword'] !== ''
            ? $arguments['employee_keyword']
            : null;

        $base = AttendanceRecord::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('work_date', [$period->from->toDateString(), $period->to->toDateString()])
            ->when($keyword !== null, fn (Builder $query) => $query
                ->whereHas('employee', fn (Builder $employee) => $employee
                    ->where('name', 'like', '%'.$this->escapeLike($keyword).'%')));

        return [
            'period' => $this->periodEcho($period),
            'count' => (clone $base)->count(),
            'late_count' => (clone $base)->where('late_minutes', '>', 0)->count(),
            'missing_checkout_count' => (clone $base)->missingCheckOut()->count(),
            'by_status' => (clone $base)->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')->pluck('total', 'status')->all(),
            'records' => (clone $base)->with(['branch:id,name', 'employee:id,name'])
                ->orderByDesc('work_date')
                ->limit($this->limit($arguments))
                ->get()
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

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, int>  $branchIds
     * @return array<string, mixed>
     */
    private function payroll(User $user, array $arguments, array $branchIds): array
    {
        if (! Gate::forUser($user)->allows('view-profit-reports')) {
            return ['error' => 'Người dùng không có quyền xem bảng lương.'];
        }

        $period = $this->period($arguments);

        $base = Payroll::query()
            ->where(fn (Builder $query) => $query
                ->whereIn('paying_branch_id', $branchIds)
                ->orWhereHas('allocations', fn (Builder $allocation) => $allocation->whereIn('branch_id', $branchIds)))
            ->whereDate('period_start', '<=', $period->to)
            ->whereDate('period_end', '>=', $period->from);

        return [
            'period' => $this->periodEcho($period),
            'count' => (clone $base)->count(),
            'total' => (string) (clone $base)->sum('final_total'),
            'payrolls' => (clone $base)->with(['employee:id,name', 'payingBranch:id,name'])
                ->latest('period_end')
                ->limit($this->limit($arguments))
                ->get()
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
     * @param  array<string, mixed>  $arguments
     * @param  array<int, int>  $branchIds
     * @return array<string, mixed>
     */
    private function employees(array $arguments, array $branchIds): array
    {
        $keyword = is_string($arguments['keyword'] ?? null) && $arguments['keyword'] !== ''
            ? $arguments['keyword']
            : null;

        $employees = User::query()
            ->where('is_active', true)
            ->when($keyword !== null, fn (Builder $query) => $query
                ->where('name', 'like', '%'.$this->escapeLike($keyword).'%'))
            ->whereHas('branches', fn (Builder $query) => $query->whereIn('branches.id', $branchIds))
            ->orderBy('name')
            ->limit($this->limit($arguments))
            ->get(['id', 'name', 'role']);

        return [
            'employees' => $employees->map(fn (User $employee): array => [
                'id' => (int) $employee->id,
                'name' => $employee->name,
                'role' => $employee->role->value,
            ])->values()->all(),
        ];
    }

    /**
     * Vô hiệu hóa ký tự đại diện của LIKE.
     *
     * Không thoát thì một từ khóa chỉ gồm "%" quét toàn bộ bảng, và người dùng
     * gõ dấu gạch dưới trong tên lại khớp nhầm sang bản ghi khác.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
