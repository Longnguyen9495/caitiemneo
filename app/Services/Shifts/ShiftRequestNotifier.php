<?php

namespace App\Services\Shifts;

use App\Models\ShiftRequest;
use App\Models\User;
use App\Notifications\ShiftRequestNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

final class ShiftRequestNotifier
{
    /**
     * Queue notifications after the surrounding business transaction commits.
     * Notification delivery is deliberately best effort: it must never undo an
     * approved request, schedule update, or replacement assignment.
     *
     * @param  Collection<int, User>|array<int, User>  $recipients
     */
    public function afterCommit(ShiftRequest $request, Collection|array $recipients, string $message): void
    {
        $recipientIds = collect($recipients)
            ->filter(fn (mixed $recipient): bool => $recipient instanceof User && $recipient->is_active)
            ->map(fn (User $recipient): int => $recipient->getKey())
            ->unique()
            ->values()
            ->all();

        if ($recipientIds === []) {
            return;
        }

        DB::afterCommit(function () use ($request, $recipientIds, $message): void {
            try {
                $freshRequest = ShiftRequest::query()->find($request->getKey());
                $recipients = User::query()->active()->whereKey($recipientIds)->get();

                if ($freshRequest === null || $recipients->isEmpty()) {
                    return;
                }

                Notification::send($recipients, new ShiftRequestNotification($freshRequest, $message));
            } catch (\Throwable $exception) {
                Log::warning('Không thể gửi thông báo đơn ca làm.', [
                    'shift_request_id' => $request->getKey(),
                    'recipient_ids' => $recipientIds,
                    'exception' => $exception,
                ]);
            }
        });
    }

    /** @return Collection<int, User> */
    public function managersFor(ShiftRequest $request): Collection
    {
        return User::query()
            ->active()
            ->get()
            ->filter(fn (User $user): bool => ($user->isOwner() || $user->isManager())
                && $user->canAccessBranch($request->branch_id, $request->work_date))
            ->values();
    }
}
