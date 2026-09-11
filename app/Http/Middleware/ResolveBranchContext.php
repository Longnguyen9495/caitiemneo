<?php

namespace App\Http\Middleware;

use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-derive the active branch on every admin request.
 *
 * Running this per request, rather than trusting whatever the session or a
 * hidden field says, is what stops a user from widening their own access by
 * editing a value.
 */
class ResolveBranchContext
{
    public function __construct(private BranchContext $branchContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->branchContext->resolve($request->user());

        if ($request->user() !== null && ! $request->user()->isOwner() && $this->branchContext->available()->isEmpty()) {
            abort(403, 'Tài khoản của bạn chưa được phân công chi nhánh nào.');
        }

        return $next($request);
    }
}
