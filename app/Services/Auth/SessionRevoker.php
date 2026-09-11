<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cut short every other signed-in session belonging to one account.
 *
 * Deactivating somebody, changing their role or resetting their password are
 * all statements that the previous state of that account is no longer trusted.
 * If their existing sessions keep working, none of those actions take effect
 * until the person happens to log out — which, for a dismissal, is exactly when
 * it matters most.
 *
 * Only works while sessions are stored in the database; with a cookie driver
 * there is nothing on the server to revoke, and the caller is told as much.
 */
class SessionRevoker
{
    /**
     * @param  string|null  $keepSessionId  session to spare, e.g. the actor's own
     * @return int number of sessions ended
     */
    public function revokeFor(User $user, ?string $keepSessionId = null): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }

        $table = config('session.table', 'sessions');

        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return 0;
        }

        return DB::table($table)
            ->where('user_id', $user->getKey())
            ->when($keepSessionId !== null, fn ($query) => $query->where('id', '!=', $keepSessionId))
            ->delete();
    }

    /**
     * The signed-in sessions of one account, newest first.
     *
     * Shown to the person themselves so an unfamiliar device is something they
     * can notice and end, rather than something only discovered afterwards.
     *
     * @return array<int, array{id: string, is_current: bool, ip_address: string|null, user_agent: string|null, last_active_at: Carbon}>
     */
    public function listFor(User $user, ?string $currentSessionId = null): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        $table = config('session.table', 'sessions');

        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return [];
        }

        return DB::table($table)
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($row): array => [
                'id' => $row->id,
                'is_current' => $row->id === $currentSessionId,
                'ip_address' => $row->ip_address,
                'user_agent' => $row->user_agent,
                'last_active_at' => Carbon::createFromTimestamp($row->last_activity),
            ])
            ->all();
    }
}
