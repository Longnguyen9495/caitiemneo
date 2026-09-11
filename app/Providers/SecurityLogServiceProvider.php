<?php

namespace App\Providers;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * A written record of who tried to get in, and who was turned away.
 *
 * The audit trail covers what people did once inside; this covers the doorway.
 * Both matter: a burst of failed sign-ins against one account is the only
 * warning that somebody is guessing at it, and it leaves no trace anywhere else.
 *
 * Deliberately never logs the password attempt, the session id or the full
 * address — enough to investigate, not enough to become a second breach if the
 * log file is read by the wrong person.
 */
class SecurityLogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(Failed::class, function (Failed $event): void {
            Log::channel('security')->warning('auth.failed', [
                'identifier' => $this->maskIdentifier($event->credentials['email'] ?? $event->credentials['username'] ?? null),
                'guard' => $event->guard,
                'ip_hash' => $this->ipHash(),
            ]);
        });

        Event::listen(Lockout::class, function (): void {
            // Đây là tín hiệu đáng chú ý nhất ở cửa vào: có người đang thử đoán.
            Log::channel('security')->error('auth.lockout', [
                'ip_hash' => $this->ipHash(),
            ]);
        });

        Event::listen(Login::class, function (Login $event): void {
            Log::channel('security')->info('auth.login', [
                'user_id' => $event->user->getAuthIdentifier(),
                'ip_hash' => $this->ipHash(),
            ]);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            Log::channel('security')->info('auth.logout', [
                'user_id' => $event->user?->getAuthIdentifier(),
            ]);
        });
    }

    /**
     * Enough of the identifier to recognise a pattern, not enough to harvest.
     *
     * A log full of complete addresses is a mailing list for whoever reads it.
     */
    private function maskIdentifier(?string $identifier): ?string
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        $length = mb_strlen($identifier);

        return $length <= 3
            ? str_repeat('*', $length)
            : mb_substr($identifier, 0, 2).str_repeat('*', $length - 3).mb_substr($identifier, -1);
    }

    /** Keyed hash, same reasoning as the audit trail: identify, do not store. */
    private function ipHash(): ?string
    {
        $ip = request()?->ip();

        return $ip === null ? null : hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
