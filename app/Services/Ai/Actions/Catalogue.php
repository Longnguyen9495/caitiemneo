<?php

namespace App\Services\Ai\Actions;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AttendanceRecord;
use App\Models\BranchProduct;
use App\Models\BranchService;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\RiskFlag;
use App\Models\Service;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequest;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\BranchContext;
use App\Support\Money;

/**
 * Danh sách cho các ô chọn trên phiếu xác nhận.
 *
 * Mọi truy vấn ở đây đều giới hạn theo chi nhánh đang làm việc, nên một ô chọn
 * không bao giờ bày ra bản ghi của tiệm khác — dù trợ lý có đoán bừa ID thì
 * lớp validate và policy vẫn chặn, nhưng không nên để người duyệt nhìn thấy
 * thứ họ không có quyền động tới.
 *
 * Danh sách cố ý cắt ngắn: phiếu nằm trong khung chat trên điện thoại, một ô
 * chọn vài trăm dòng là không bấm nổi.
 */
final class Catalogue
{
    /** @return array<string, string> */
    public static function employees(?int $branchId): array
    {
        if ($branchId === null) {
            return [];
        }

        return User::query()
            ->active()
            ->where('role', UserRole::Employee)
            ->postedTo([$branchId])
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<string, string> */
    public static function services(?int $branchId): array
    {
        if ($branchId === null) {
            return [];
        }

        return BranchService::query()
            ->with('service')
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (BranchService $row): string => (string) $row->service?->name)
            ->mapWithKeys(fn (BranchService $row): array => [
                (string) $row->service_id => trim(($row->service?->name ?? 'Dịch vụ').' · '.Money::format($row->price).' · '.$row->duration_minutes.' phút'),
            ])
            ->all();
    }

    /** @return array<string, string> */
    public static function products(?int $branchId): array
    {
        if ($branchId === null) {
            return [];
        }

        return BranchProduct::query()
            ->with('product')
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (BranchProduct $row): bool => (bool) $row->product?->is_active)
            ->sortBy(fn (BranchProduct $row): string => (string) $row->product?->name)
            ->mapWithKeys(fn (BranchProduct $row): array => [
                (string) $row->product_id => (string) ($row->product?->name ?? 'Vật tư'),
            ])
            ->all();
    }

    /** @return array<string, string> */
    public static function appointments(?int $branchId): array
    {
        if ($branchId === null) {
            return [];
        }

        return Appointment::query()
            ->where('branch_id', $branchId)
            ->where('starts_at', '>=', now()->subDays(7)->startOfDay())
            ->orderBy('starts_at')
            ->limit(80)
            ->get(['id', 'customer_name', 'starts_at'])
            ->mapWithKeys(fn (Appointment $row): array => [
                (string) $row->id => $row->customer_name.' · '.$row->starts_at?->format('H:i \n\g\à\y d/m'),
            ])
            ->all();
    }

    /** @return array<string, string> */
    public static function invoices(?int $branchId): array
    {
        if ($branchId === null) {
            return [];
        }

        return Invoice::query()
            ->where('branch_id', $branchId)
            ->latest('id')
            ->limit(60)
            ->get(['id', 'number', 'total', 'customer_name'])
            ->mapWithKeys(fn (Invoice $row): array => [
                (string) $row->id => trim($row->number.' · '.Money::format($row->total).' · '.($row->customer_name ?: 'Khách lẻ')),
            ])
            ->all();
    }

    /** @return array<string, string> */
    public static function cashEntries(?int $branchId): array
    {
        if ($branchId === null) {
            return [];
        }

        return CashTransaction::query()
            ->where('branch_id', $branchId)
            ->whereNull('voided_at')
            ->latest('occurred_at')
            ->limit(60)
            ->get(['id', 'type', 'amount', 'occurred_at', 'note'])
            ->mapWithKeys(fn (CashTransaction $row): array => [
                (string) $row->id => $row->type->label().' · '.Money::format($row->amount).' · '.$row->occurred_at?->format('d/m'),
            ])
            ->all();
    }

    /** @return array<string, string> */
    public static function stockTransfers(?int $branchId): array
    {
        if ($branchId === null) {
            return [];
        }

        return StockTransfer::query()
            ->where(fn ($query) => $query->where('source_branch_id', $branchId)->orWhere('destination_branch_id', $branchId))
            ->latest('id')
            ->limit(40)
            ->get(['id', 'number', 'status'])
            ->mapWithKeys(fn (StockTransfer $row): array => [
                (string) $row->id => $row->number.' · '.$row->status->label(),
            ])
            ->all();
    }

    /** @return array<string, string> */
    public static function attendanceRecords(?int $branchId): array
    {
        if ($branchId === null) {
            return [];
        }

        return AttendanceRecord::query()
            ->with('employee')
            ->where('branch_id', $branchId)
            ->latest('work_date')
            ->limit(60)
            ->get()
            ->mapWithKeys(fn (AttendanceRecord $row): array => [
                (string) $row->id => ($row->employee?->name ?? 'Nhân viên').' · '.$row->work_date?->format('d/m').' · '.$row->shift_name,
            ])
            ->all();
    }

    /** @return array<string, string> */
    public static function shiftAssignments(?int $branchId): array
    {
        if ($branchId === null) {
            return [];
        }

        return ShiftAssignment::query()
            ->with(['employee', 'workShift'])
            ->where('branch_id', $branchId)
            ->where('work_date', '>=', now()->subDays(7)->toDateString())
            ->orderBy('work_date')
            ->limit(80)
            ->get()
            ->mapWithKeys(fn (ShiftAssignment $row): array => [
                (string) $row->id => ($row->employee?->name ?? 'Nhân viên').' · '.$row->work_date?->format('d/m').' · '.($row->workShift?->name ?? 'Ca'),
            ])
            ->all();
    }

    /** @return array<string, string> */
    public static function shiftRequests(?int $branchId): array
    {
        if ($branchId === null) {
            return [];
        }

        return ShiftRequest::query()
            ->with('employee')
            ->where('branch_id', $branchId)
            ->latest('id')
            ->limit(40)
            ->get()
            ->mapWithKeys(fn (ShiftRequest $row): array => [
                (string) $row->id => ($row->employee?->name ?? 'Nhân viên').' · '.$row->type->label().' · '.$row->status->label(),
            ])
            ->all();
    }

    /** @return array<string, string> */
    public static function riskFlags(?int $branchId): array
    {
        if ($branchId === null) {
            return [];
        }

        return RiskFlag::query()
            ->where('branch_id', $branchId)
            ->latest('id')
            ->limit(40)
            ->get(['id', 'rule', 'summary', 'review_status', 'detected_at'])
            ->mapWithKeys(fn (RiskFlag $row): array => [
                (string) $row->id => $row->detected_at?->format('d/m').' · '.$row->summary,
            ])
            ->all();
    }

    /**
     * Dịch vụ, vật tư, nhà cung cấp và ca làm là danh mục dùng chung toàn tiệm,
     * không cắt theo chi nhánh.
     *
     * @return array<string, string>
     */
    public static function allServices(): array
    {
        return Service::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    public static function allProducts(): array
    {
        return Product::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    public static function suppliers(): array
    {
        return Supplier::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    public static function workShifts(): array
    {
        return WorkShift::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Chi nhánh đang làm việc, dùng khi một thao tác cần chọn chi nhánh đích. */
    public static function branchesInScope(): array
    {
        $context = app(BranchContext::class);

        return $context->available()
            ->whereIn('id', $context->scopeIds())
            ->pluck('name', 'id')
            ->all();
    }
}
