<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Các mốc và danh sách dùng chung của bộ dữ liệu demo.
 *
 * Gom vào một chỗ để mọi seeder demo nhìn cùng một khung thời gian: dữ liệu
 * bắt đầu từ đầu tháng trước (đủ một kỳ lương đã khép) và kéo tới hai tuần
 * tương lai (đủ lịch hẹn và lịch ca sắp tới).
 */
final class DemoData
{
    /** Ngày đầu tiên có dữ liệu: đầu tháng trước. */
    public static function start(): CarbonInterface
    {
        return Carbon::today()->subMonthNoOverflow()->startOfMonth();
    }

    /** Ngày cuối cùng có dữ liệu: hai tuần nữa. */
    public static function end(): CarbonInterface
    {
        return Carbon::today()->addDays(14);
    }

    public static function today(): CarbonInterface
    {
        return Carbon::today();
    }

    /** Kỳ lương đã khép lại gần nhất. */
    public static function lastPeriodStart(): CarbonInterface
    {
        return self::start();
    }

    public static function lastPeriodEnd(): CarbonInterface
    {
        return self::start()->copy()->endOfMonth();
    }

    /**
     * Người đứng tên mọi thao tác demo.
     *
     * Là chủ tiệm vì đây là vai duy nhất chắc chắn qua được mọi policy; các
     * seeder khác vẫn gán nhân viên thật vào từng hóa đơn và từng ca.
     */
    public static function actor(): User
    {
        return User::query()
            ->where('role', UserRole::Owner)
            ->orderBy('id')
            ->firstOrFail();
    }

    /** @return Collection<int, Branch> */
    public static function branches(): Collection
    {
        return Branch::query()->where('is_active', true)->orderBy('code')->get();
    }

    /**
     * Nhân sự trực quầy: quản lý và nhân viên, bỏ qua tài khoản đã nghỉ.
     *
     * @return Collection<int, User>
     */
    public static function staff(): Collection
    {
        return User::query()
            ->whereIn('role', [UserRole::Manager, UserRole::Employee])
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    /**
     * Chi nhánh nhân viên đang thuộc về vào một ngày cụ thể.
     *
     * Trả về null khi người đó chưa (hoặc không còn) được phân về đâu, để phía
     * gọi bỏ qua ngày đó thay vì xếp ca vào một cơ sở họ không thuộc.
     */
    public static function branchIdFor(User $employee, CarbonInterface $date): ?int
    {
        return $employee->primaryBranchId($date->toDateString());
    }

    /** Ngày làm việc của tiệm: nghỉ thứ Hai hằng tuần. */
    public static function isOpen(CarbonInterface $date): bool
    {
        return ! $date->isMonday();
    }
}
