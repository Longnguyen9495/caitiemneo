<?php

namespace App\Http\Controllers\Concerns;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The week or month a listing screen is looking at.
 *
 * These values travel in the query string, which means they also arrive from
 * pasted links, stale bookmarks and hand-typed URLs. A browsing parameter is
 * not worth an error page, so anything unparseable simply lands on the current
 * period instead of raising.
 */
trait ReadsPeriodFromRequest
{
    protected function requestedDate(Request $request, string $key): Carbon
    {
        $raw = $request->string($key)->toString();

        if ($raw === '') {
            return now();
        }

        try {
            return Carbon::parse($raw);
        } catch (InvalidFormatException) {
            return now();
        }
    }

    /** Weeks run Monday to Sunday, which is how the shop talks about them. */
    protected function requestedWeekStart(Request $request, string $key = 'week'): Carbon
    {
        return $this->requestedDate($request, $key)->startOfWeek(Carbon::MONDAY);
    }

    /**
     * Immutable because a month is carried around and asked for its own end;
     * a mutating `endOfMonth()` would quietly move the start with it.
     */
    protected function requestedMonthStart(Request $request, string $key = 'month'): CarbonImmutable
    {
        return $this->requestedDate($request, $key)->toImmutable()->startOfMonth();
    }
}
