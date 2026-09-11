<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Switches the branch the admin area is working in.
 *
 * The submitted value is checked against the branches this account is actually
 * posted to; anything else is rejected rather than silently ignored.
 */
class BranchSwitchController extends Controller
{
    public function __invoke(Request $request, BranchContext $branchContext): RedirectResponse
    {
        $allowed = $branchContext->available()->pluck('id')->map(fn ($id): string => (string) $id)->all();

        if ($branchContext->mayViewAll()) {
            $allowed[] = BranchContext::ALL;
        }

        $validated = $request->validate([
            'branch' => ['required', Rule::in($allowed)],
        ], [
            'branch.in' => 'Bạn không có quyền truy cập chi nhánh này.',
        ], [
            'branch' => 'chi nhánh',
        ]);

        $request->session()->put(BranchContext::SESSION_KEY, $validated['branch'] === BranchContext::ALL
            ? BranchContext::ALL
            : (int) $validated['branch']);

        return back()->with('success', 'Đã chuyển chi nhánh làm việc.');
    }
}
