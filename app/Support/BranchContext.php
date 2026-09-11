<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * The branch the current request is working in.
 *
 * Deliberately not a global Eloquent scope: a blind global scope silently
 * corrupts console commands, queued jobs, migrations and company-wide reports.
 * Instead this object answers "which branches may this user touch right now"
 * and the query layer applies that answer explicitly, which is easy to read and
 * easy to test.
 *
 * The selection is re-validated against the user's assignments on every request,
 * so a stale session value or a hand-typed query string can never widen access.
 */
class BranchContext
{
    public const SESSION_KEY = 'admin.current_branch_id';

    public const ALL = 'all';

    private ?User $user = null;

    private ?Branch $current = null;

    /** @var Collection<int, Branch>|null */
    private ?Collection $available = null;

    private bool $viewingAll = false;

    private bool $resolved = false;

    public function __construct(private Request $request) {}

    /**
     * Work out the active branch from the request, then the session, then the
     * user's own posting, and remember it for the rest of the request.
     */
    public function resolve(?User $user): void
    {
        $this->user = $user;
        $this->current = null;
        $this->available = null;
        $this->viewingAll = false;
        $this->resolved = true;

        if ($user === null) {
            return;
        }

        $this->available = Branch::query()
            ->active()
            ->whereIn('id', $user->accessibleBranchIds())
            ->orderBy('code')
            ->get();

        $requested = $this->requestedValue();

        if ($requested === self::ALL && $this->mayViewAll()) {
            $this->viewingAll = true;
            $this->remember(self::ALL);

            return;
        }

        $this->current = $this->available->firstWhere('id', (int) $requested)
            ?? $this->available->first();

        if ($this->current !== null) {
            $this->remember($this->current->getKey());
        }
    }

    public function current(): ?Branch
    {
        return $this->current;
    }

    public function currentId(): ?int
    {
        return $this->current?->getKey();
    }

    /** True when the user asked for, and is allowed, a company-wide view. */
    public function viewingAll(): bool
    {
        return $this->viewingAll;
    }

    /** Only the owner may look at every branch at once. */
    public function mayViewAll(): bool
    {
        return (bool) $this->user?->isOwner();
    }

    /** @return Collection<int, Branch> */
    public function available(): Collection
    {
        return $this->available ?? new Collection;
    }

    /**
     * The branch ids a query should be limited to.
     *
     * In the company-wide view this is every branch the user may see, so the
     * caller never has to special-case "all".
     *
     * @return array<int, int>
     */
    public function scopeIds(): array
    {
        if ($this->viewingAll) {
            return $this->available()->pluck('id')->all();
        }

        return $this->current === null ? [] : [$this->current->getKey()];
    }

    /**
     * The branch a write must be attributed to.
     *
     * Returns null in the company-wide view, which is why every create form
     * requires one concrete branch before it will submit.
     */
    public function requireWritableBranchId(): ?int
    {
        return $this->viewingAll ? null : $this->currentId();
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    /**
     * Remember the choice for the next request.
     *
     * A console command or a queued job has no session, and that is fine: the
     * selection simply does not persist there.
     */
    private function remember(int|string $value): void
    {
        if ($this->request->hasSession()) {
            $this->request->session()->put(self::SESSION_KEY, $value);
        }
    }

    private function requestedValue(): int|string|null
    {
        $fromQuery = $this->request->query('branch');

        if ($fromQuery !== null && $fromQuery !== '') {
            return is_string($fromQuery) && strtolower($fromQuery) === self::ALL
                ? self::ALL
                : (int) $fromQuery;
        }

        $stored = $this->request->hasSession()
            ? $this->request->session()->get(self::SESSION_KEY)
            : null;

        if ($stored === self::ALL) {
            return self::ALL;
        }

        return $stored === null ? null : (int) $stored;
    }
}
