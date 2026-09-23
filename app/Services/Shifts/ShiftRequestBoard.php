<?php

namespace App\Services\Shifts;

use App\Enums\ShiftRequestStatus;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * What one person is allowed to see on the leave-and-swap board.
 *
 * The same page serves three readers with three different rights — an employee
 * sees their own dealings, a manager sees the shops they run, the owner sees
 * everything — and the narrowing is done in SQL rather than in the view, so a
 * hand-typed query string cannot widen it.
 */
final class ShiftRequestBoard
{
    private const PER_PAGE = 30;

    /** Bộ lọc trạng thái, theo thứ tự hiện trên thanh lọc. */
    public const FILTERS = [
        'can-xu-ly' => 'Cần xử lý',
        'dang-cho' => 'Đang chờ',
        'da-xong' => 'Đã xong',
    ];

    /**
     * The requests this person may read, newest first.
     *
     * @param  array<int, int>  $branchIds  the shops in view for a manager
     * @return LengthAwarePaginator<int, ShiftRequest>
     */
    public function requestsFor(User $user, array $branchIds, ?string $filter = null): LengthAwarePaginator
    {
        return ShiftRequest::query()
            ->when($filter === 'can-xu-ly', fn (Builder $query) => $query->needingAction())
            ->when($filter === 'dang-cho', fn (Builder $query) => $query->open())
            ->when($filter === 'da-xong', fn (Builder $query) => $query->whereNotIn('status', [
                ShiftRequestStatus::PendingApproval,
                ShiftRequestStatus::PendingRecipient,
                ShiftRequestStatus::RecipientConfirmed,
            ]))
            ->with(['requester', 'recipient', 'shiftAssignment', 'counterShiftAssignment', 'replacement.replacementEmployee'])
            ->when($user->isEmployee(), fn (Builder $query) => $query->where(fn (Builder $mine) => $mine
                ->where('requester_id', $user->getKey())
                ->orWhere('recipient_id', $user->getKey())))
            ->unless($user->isEmployee() || $user->isOwner(), fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * The person's own shifts from today onwards — what they can ask about.
     *
     * @return Collection<int, ShiftAssignment>
     */
    public function ownUpcomingShifts(User $user): Collection
    {
        return ShiftAssignment::query()
            ->forEmployee($user)
            ->whereDate('work_date', '>=', now()->toDateString())
            ->with('workShift')
            ->orderBy('planned_start_at')
            ->get();
    }

    /**
     * Colleagues' shifts that could be traded against the person's own.
     *
     * A trade only makes sense within one shop on one day, so the candidates
     * are drawn from the days the person is already rostered on. An empty set
     * of own shifts means there is nothing to trade, and no query to run.
     *
     * @param  Collection<int, ShiftAssignment>  $ownShifts
     * @return Collection<int, ShiftAssignment>
     */
    public function tradableShiftsAgainst(User $user, Collection $ownShifts): Collection
    {
        if ($ownShifts->isEmpty()) {
            return new Collection;
        }

        return ShiftAssignment::query()
            ->where('employee_id', '!=', $user->getKey())
            ->whereHas('employee', fn (Builder $employee) => $employee->active()->staff())
            ->where(fn (Builder $query) => $ownShifts->each(
                fn (ShiftAssignment $own) => $query->orWhere(fn (Builder $sameSlot) => $sameSlot
                    ->where('branch_id', $own->branch_id)
                    ->whereDate('work_date', $own->work_date))
            ))
            ->with(['employee', 'workShift'])
            ->orderBy('work_date')
            ->orderBy('planned_start_at')
            ->get();
    }
}
