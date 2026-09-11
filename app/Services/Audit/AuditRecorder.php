<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Writes the company-wide audit trail.
 *
 * One service so every caller records the same shape, and so the decision about
 * what is *not* stored lives in one place: no passwords, tokens or cookies, no
 * precise GPS coordinates, and the client IP only as a keyed hash — enough to
 * tell two sessions apart when investigating, without keeping a plain address
 * on file for every action a member of staff takes.
 */
class AuditRecorder
{
    /**
     * Correlates every event raised while handling one request.
     *
     * A single submit can change an invoice and several of its lines; sharing
     * one id lets the timeline show that as one action rather than five.
     */
    private ?string $correlationId = null;

    public function __construct(private ?Request $request = null) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        Model|string $subject,
        ?User $actor,
        AuditAction $action,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?int $branchId = null,
        ?string $label = null,
    ): AuditEvent {
        // Không phải sự kiện nào cũng có bản ghi nghiệp vụ đứng sau: một lần
        // xuất dữ liệu là hành động có thật nhưng không tạo ra dòng nào cả.
        $isModel = $subject instanceof Model;

        return AuditEvent::query()->create([
            'auditable_type' => $isModel ? $subject->getMorphClass() : $subject,
            'auditable_id' => $isModel ? $subject->getKey() : null,
            'auditable_label' => $label ?? ($isModel ? $this->labelFor($subject) : null),
            'action' => $action,
            'branch_id' => $branchId ?? ($isModel ? $this->branchIdFor($subject) : null),
            'actor_id' => $actor?->getKey(),
            // Snapshotted so a deactivated or deleted account still names who acted.
            'actor_name' => $actor?->name,
            'reason' => $reason === null ? null : Str::limit($reason, 490, ''),
            'before' => $before,
            'after' => $after,
            'correlation_id' => $this->correlationId(),
            'ip_hash' => $this->ipHash(),
            'user_agent' => $this->userAgent(),
        ]);
    }

    /** Đặt lại id cho một request mới, do middleware sinh ra. */
    public function useCorrelationId(string $id): void
    {
        $this->correlationId = $id;
    }

    /** The id shared by every event raised during this request. */
    public function correlationId(): string
    {
        return $this->correlationId ??= (string) Str::uuid();
    }

    private function labelFor(Model $subject): ?string
    {
        foreach (['number', 'code', 'name', 'shift_name', 'reference'] as $attribute) {
            $value = $subject->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return Str::limit($value, 250, '');
            }
        }

        return null;
    }

    private function branchIdFor(Model $subject): ?int
    {
        $branchId = $subject->getAttribute('branch_id');

        return is_numeric($branchId) ? (int) $branchId : null;
    }

    /**
     * A keyed hash of the client address.
     *
     * Keyed with the application key so the digest cannot be reversed with a
     * rainbow table of the (very small) IPv4 space.
     */
    private function ipHash(): ?string
    {
        $ip = $this->request?->ip();

        if ($ip === null || $ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    private function userAgent(): ?string
    {
        $agent = $this->request?->userAgent();

        return $agent === null || $agent === '' ? null : Str::limit($agent, 250, '');
    }
}
