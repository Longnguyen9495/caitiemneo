<?php

namespace App\Support;

use App\Enums\ShiftRequestStatus;
use App\Enums\ShiftRequestType;
use App\Models\Appointment;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\CashTransaction;
use App\Models\GalleryItem;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\RiskFlag;
use App\Models\Service;
use App\Models\ShiftRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The admin menu, filtered to what the signed-in account may actually reach.
 *
 * Kept out of the Blade layout so the phone tab bar and the desktop sidebar
 * always agree on what exists and what is active.
 */
final class AdminNavigation
{
    /**
     * Every destination the user can open, in menu order.
     *
     * @return Collection<int, array{label: string, short: string, route: string, pattern: array<int, string>|string, icon: string, primary: bool, badge: int, group: string}>
     */
    public static function for(User $user): Collection
    {
        $shiftRequestBadge = self::shiftRequestBadge($user);

        return collect([
            [
                'label' => 'Tổng quan', 'short' => 'Tổng quan',
                'route' => 'admin.dashboard', 'pattern' => 'admin.dashboard',
                'icon' => 'dashboard', 'primary' => true,
                'visible' => true,
                'group' => 'quick',
            ],
            [
                // Với nhân viên đây là màn hình dùng nhiều nhất trong ngày nên
                // được một ô cố định ở thanh dưới; quản lý vẫn vào từ menu đầy đủ.
                'label' => 'Chấm công của tôi', 'short' => 'Vào ca',
                'route' => 'attendance.board', 'pattern' => 'attendance.board',
                'icon' => 'pin', 'primary' => $user->isEmployee(),
                'visible' => true,
                'group' => 'quick',
            ],
            [
                'label' => 'Lịch hẹn', 'short' => 'Lịch hẹn',
                'route' => 'admin.appointments.index', 'pattern' => 'admin.appointments.*',
                'icon' => 'calendar', 'primary' => true,
                'visible' => $user->can('viewAny', Appointment::class),
                'group' => 'quick',
            ],
            [
                'label' => 'Hóa đơn & thu chi', 'short' => 'Hóa đơn',
                'route' => 'admin.invoices.index', 'pattern' => ['admin.invoices.*', 'admin.cash.*'],
                'icon' => 'receipt', 'primary' => true,
                'visible' => $user->can('viewAny', Invoice::class) || $user->can('viewAny', CashTransaction::class),
                'group' => 'sales',
            ],
            [
                // Nhân viên chỉ có thể xem bảng lương của chính mình. Không dẫn
                // họ đến màn hình chấm công quản trị vì policy sẽ trả về 403.
                'label' => $user->can('viewAny', AttendanceRecord::class) ? 'Chấm công & lương' : 'Lương của tôi',
                'short' => $user->can('viewAny', AttendanceRecord::class) ? 'Chấm công' : 'Lương',
                'route' => $user->can('viewAny', AttendanceRecord::class) ? 'admin.attendance.index' : 'admin.payrolls.index',
                'pattern' => $user->can('viewAny', AttendanceRecord::class) ? ['admin.attendance.*', 'admin.payrolls.*'] : 'admin.payrolls.*',
                'icon' => 'clock', 'primary' => true,
                'visible' => $user->can('viewAny', Payroll::class),
                'group' => 'hr',
            ],
            [
                'label' => 'Đơn ca & nghỉ', 'short' => 'Đơn ca',
                'route' => 'admin.shift-requests.index', 'pattern' => 'admin.shift-requests.*',
                'icon' => 'alert', 'primary' => false,
                'visible' => $user->can('viewAny', ShiftRequest::class),
                'badge' => $shiftRequestBadge,
                'group' => 'hr',
            ],
            [
                'label' => 'Nhân sự', 'short' => 'Nhân sự',
                'route' => 'admin.employees.index', 'pattern' => 'admin.employees.*',
                'icon' => 'people', 'primary' => false,
                'visible' => $user->can('viewAny', User::class),
                'group' => 'hr',
            ],
            [
                'label' => 'Kho vật tư', 'short' => 'Kho',
                'route' => 'admin.products.index',
                'pattern' => ['admin.products.*', 'admin.suppliers.*', 'admin.inventory.*', 'admin.stock-transfers.*'],
                'icon' => 'box', 'primary' => false,
                'visible' => $user->can('viewAny', Product::class),
                'group' => 'ops',
            ],
            [
                'label' => 'Dịch vụ', 'short' => 'Dịch vụ',
                'route' => 'admin.services.index', 'pattern' => 'admin.services.*',
                'icon' => 'inbox', 'primary' => false,
                'visible' => $user->can('viewAny', Service::class),
                'group' => 'ops',
            ],
            [
                'label' => 'Album trang chủ', 'short' => 'Album',
                'route' => 'admin.gallery.index', 'pattern' => 'admin.gallery.*',
                'icon' => 'image', 'primary' => false,
                'visible' => $user->can('viewAny', GalleryItem::class),
                'group' => 'ops',
            ],
            [
                'label' => 'Chi nhánh', 'short' => 'Chi nhánh',
                'route' => 'admin.branches.index', 'pattern' => 'admin.branches.*',
                'icon' => 'branch', 'primary' => false,
                'visible' => $user->can('viewAny', Branch::class),
                'group' => 'ops',
            ],
            [
                'label' => 'Cảnh báo', 'short' => 'Cảnh báo',
                'route' => 'admin.risk-flags.index', 'pattern' => 'admin.risk-flags.*',
                'icon' => 'pin', 'primary' => false,
                'visible' => $user->can('viewAny', RiskFlag::class),
                'group' => 'control',
            ],
            [
                'label' => 'Báo cáo', 'short' => 'Báo cáo',
                'route' => 'admin.reports.index', 'pattern' => 'admin.reports.*',
                'icon' => 'chart', 'primary' => false,
                'visible' => Gate::forUser($user)->allows('view-reports'),
                'group' => 'sales',
            ],
        ])->map(fn (array $item): array => $item + ['badge' => 0])
            ->where('visible', true)
            ->values();
    }

    /**
     * Group labels keyed by group id.
     *
     * @return array<string, string>
     */
    public static function groupLabels(): array
    {
        return [
            'quick' => 'Truy cập nhanh',
            'sales' => 'Bán hàng & tài chính',
            'hr' => 'Nhân sự & ca làm',
            'ops' => 'Vận hành cửa hàng',
            'control' => 'Kiểm soát',
        ];
    }

    /**
     * Group the filtered navigation into sections.
     *
     * Uses the already-built collection so badge queries are not repeated.
     * Empty groups are omitted.
     *
     * @param  Collection<int, array<string, mixed>>  $navigation
     * @return Collection<int, array{id: string, label: string, items: Collection<int, array<string, mixed>>, badge: int, isActive: bool}>
     */
    public static function groups(Collection $navigation): Collection
    {
        $labels = self::groupLabels();

        return $navigation
            ->groupBy('group')
            ->map(function (Collection $items, string $groupId) use ($labels): array {
                $badge = $items->sum('badge');
                $isActive = $items->contains(
                    fn (array $item): bool => request()->routeIs(
                        is_array($item['pattern']) ? $item['pattern'] : [$item['pattern']]
                    )
                );

                return [
                    'id' => $groupId,
                    'label' => $labels[$groupId] ?? $groupId,
                    'items' => $items,
                    'badge' => $badge,
                    'isActive' => $isActive,
                ];
            })
            ->values();
    }

    private static function shiftRequestBadge(User $user): int
    {
        if (! $user->isOwner() && ! $user->isManager()) {
            return 0;
        }

        $branchIds = app(BranchContext::class)->scopeIds();

        if ($branchIds === []) {
            return 0;
        }

        // Hai nhóm dưới đây rời nhau về mặt trạng thái nên đếm hợp của chúng
        // bằng một truy vấn cho ra đúng tổng của hai lần đếm riêng lẻ.
        return ShiftRequest::query()
            ->whereIn('branch_id', $branchIds)
            ->where(function (Builder $query): void {
                $query->whereIn('status', [
                    ShiftRequestStatus::PendingApproval,
                    ShiftRequestStatus::RecipientConfirmed,
                ])->orWhere(function (Builder $approved): void {
                    $approved->where('type', ShiftRequestType::Leave)
                        ->where('status', ShiftRequestStatus::Approved)
                        ->whereDoesntHave('replacement');
                });
            })
            ->count();
    }

    /**
     * The four destinations that get a slot in the phone tab bar.
     *
     * The fifth slot is always "more", which opens the full menu, so a user
     * never loses access to anything that did not fit. Takes the menu that
     * {@see self::for()} already built so the badge queries are not repeated.
     *
     * @param  Collection<int, array<string, mixed>>  $navigation
     * @return Collection<int, array<string, mixed>>
     */
    public static function primary(Collection $navigation): Collection
    {
        return $navigation->where('primary', true)->take(4)->values();
    }
}
