<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Send the user back to the list they came from, filters and all.
     *
     * A manager who narrowed the cash book to one week, found the wrong entry
     * and fixed it should land back on that week. Dropping the filter on every
     * action is the small friction that ends with people batching corrections
     * "for later" — which is when they stop happening.
     *
     * Only a URL pointing at the same list is honoured, so the referer cannot
     * be used to bounce somebody somewhere else.
     */
    protected function backToList(Request $request, string $routeName): string
    {
        $previous = $request->headers->get('referer');
        $listUrl = route($routeName);

        if ($previous === null) {
            return $listUrl;
        }

        $base = strtok($previous, '?');

        return $base === $listUrl ? $previous : $listUrl;
    }

    /**
     * Demand a fresh password confirmation before continuing.
     *
     * The `password.confirm` middleware covers whole routes, but some endpoints
     * are only sensitive for part of their traffic — cancelling a draft invoice
     * is everyday counter work, while cancelling a paid one reverses real
     * money. Those cases ask here instead.
     *
     * @throws HttpResponseException
     */
    protected function requirePasswordConfirmation(Request $request): void
    {
        $confirmedAt = $request->session()->get('auth.password_confirmed_at', 0);

        if (time() - $confirmedAt < config('auth.password_timeout', 900)) {
            return;
        }

        $request->session()->put('url.intended', url()->previous());

        throw new HttpResponseException(
            redirect()->route('password.confirm')
        );
    }
}
