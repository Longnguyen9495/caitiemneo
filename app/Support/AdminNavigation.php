<?php

namespace App\Support;

use App\Enums\ShiftRequestStatus;
use App\Enums\ShiftRequestType;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\RiskFlag;
use App\Models\Service;
use App\Models\ShiftRequest;
use App\Models\User;
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
     * @return Collection<int, array{label: string, short: string, route: string, pattern: array<int, string>|string, icon: string, primary: bool, badge: int}>
     */
    public static function for(User $user): Collection
    {
        $shiftRequestBadge = self::shiftRequestBadge($user);
        $notificationBadge = $user->unreadNotifications()->count();

        return collect([
            [
                'label' => 'Tổng quan', 'short' => 'Tổng quan',
                'route' => 'admin.dashboard', 'pattern' => 'admin.dashboard',
                'icon' => 'dashboard', 'primary' => true,
                'visible' => true,
            ],
            [
                // Với nhân viên đây là màn hình dùng nhiều nhất trong ngày nên
                // được một ô cố định ở thanh dưới; quản lý vẫn vào từ menu đầy đủ.
                'label' => 'Chấm công của tôi', 'short' => 'Vào ca',
                'route' => 'attendance.board', 'pattern' => 'attendance.board',
                'icon' => 'pin', 'primary' => $user->isEmployee(),
                'visible' => true,
            ],
            [
                'label' => 'Thông báo', 'short' => 'Thông báo',
                'route' => 'admin.notifications.index', 'pattern' => 'admin.notifications.*',
                'icon' => 'alert', 'primary' => false,
                'visible' => true,
                'badge' => $notificationBadge,
            ],
            [
                'label' => 'Lịch hẹn', 'short' => 'Lịch hẹn',
                'route' => 'admin.appointments.index', 'pattern' => 'admin.appointments.*',
                'icon' => 'calendar', 'primary' => true,
                'visible' => $user->can('viewAny', Appointment::class),
            ],
            [
                'label' => 'Hóa đơn & thu chi', 'short' => 'Hóa đơn',
                'route' => 'admin.invoices.index', 'pattern' => ['admin.invoices.*', 'admin.cash.*'],
                'icon' => 'receipt', 'primary' => true,
                'visible' => $user->can('viewAny', Invoice::class) || $user->can('viewAny', CashTransaction::class),
            ],
            [
                'label' => 'Chấm công & lương', 'short' => 'Chấm công',
                'route' => 'admin.attendance.index', 'pattern' => ['admin.attendance.*', 'admin.payrolls.*'],
                'icon' => 'clock', 'primary' => true,
                'visible' => $user->can('viewAny', Payroll::class),
            ],
            [
                'label' => 'Đơn ca & nghỉ', 'short' => 'Đơn ca',
                'route' => 'admin.shift-requests.index', 'pattern' => 'admin.shift-requests.*',
                'icon' => 'alert', 'primary' => false,
                'visible' => $user->can('viewAny', ShiftRequest::class),
                'badge' => $shiftRequestBadge,
            ],
            [
                'label' => 'Kho vật tư', 'short' => 'Kho',
                'route' => 'admin.products.index',
                'pattern' => ['admin.products.*', 'admin.suppliers.*', 'admin.inventory.*', 'admin.stock-transfers.*'],
                'icon' => 'box', 'primary' => false,
                'visible' => $user->can('viewAny', Product::class),
            ],
            [
                'label' => 'Dịch vụ', 'short' => 'Dịch vụ',
                'route' => 'admin.services.index', 'pattern' => 'admin.services.*',
                'icon' => 'inbox', 'primary' => false,
                'visible' => $user->can('viewAny', Service::class),
            ],
            [
                'label' => 'Nhân sự', 'short' => 'Nhân sự',
                'route' => 'admin.employees.index', 'pattern' => 'admin.employees.*',
                'icon' => 'people', 'primary' => false,
                'visible' => $user->can('viewAny', User::class),
            ],
            [
                'label' => 'Cảnh báo', 'short' => 'Cảnh báo',
                'route' => 'admin.risk-flags.index', 'pattern' => 'admin.risk-flags.*',
                'icon' => 'pin', 'primary' => false,
                'visible' => $user->can('viewAny', RiskFlag::class),
            ],
            [
                'label' => 'Báo cáo', 'short' => 'Báo cáo',
                'route' => 'admin.reports.index', 'pattern' => 'admin.reports.*',
                'icon' => 'chart', 'primary' => false,
                'visible' => Gate::forUser($user)->allows('view-reports'),
            ],
            [
                'label' => 'Chi nhánh', 'short' => 'Chi nhánh',
                'route' => 'admin.branches.index', 'pattern' => 'admin.branches.*',
                'icon' => 'branch', 'primary' => false,
                'visible' => $user->can('viewAny', Branch::class),
            ],
        ])->map(fn (array $item): array => $item + ['badge' => 0])
            ->where('visible', true)
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

        $awaitingDecision = ShiftRequest::query()
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', [
                ShiftRequestStatus::PendingApproval,
                ShiftRequestStatus::RecipientConfirmed,
            ])
            ->count();

        $awaitingReplacement = ShiftRequest::query()
            ->whereIn('branch_id', $branchIds)
            ->where('type', ShiftRequestType::Leave)
            ->where('status', ShiftRequestStatus::Approved)
            ->whereDoesntHave('replacement')
            ->count();

        return $awaitingDecision + $awaitingReplacement;
    }

    /**
     * The four destinations that get a slot in the phone tab bar.
     *
     * The fifth slot is always "more", which opens the full menu, so a user
     * never loses access to anything that did not fit.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function primaryFor(User $user): Collection
    {
        return self::for($user)->where('primary', true)->take(4)->values();
    }
}
