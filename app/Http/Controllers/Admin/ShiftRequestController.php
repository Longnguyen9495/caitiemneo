<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Shifts\AssignShiftReplacementAction;
use App\Actions\Shifts\ManageShiftRequestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShiftRequests\StoreLeaveRequest;
use App\Http\Requests\ShiftRequests\StoreSwapRequest;
use App\Models\ShiftRequest;
use App\Models\User;
use App\Services\Shifts\ShiftRequestBoard;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ShiftRequestController extends Controller
{
    public function __construct(
        private BranchContext $branchContext,
        private ShiftRequestBoard $board,
    ) {}

    public function index(Request $request, AssignShiftReplacementAction $replacementAction): View
    {
        $this->authorize('viewAny', ShiftRequest::class);

        $user = $request->user();
        $filter = $request->string('loc')->toString();
        $requests = $this->board->requestsFor($user, $this->branchContext->scopeIds(), $filter);
        $ownShifts = $user->isEmployee() ? $this->board->ownUpcomingShifts($user) : collect();

        return view('admin.shift-requests.index', [
            'requests' => $requests,
            'ownAssignments' => $ownShifts,
            'swapAssignments' => $user->isEmployee()
                ? $this->board->tradableShiftsAgainst($user, $ownShifts)
                : collect(),
            'canManage' => $user->isLeadership(),
            'filters' => ShiftRequestBoard::FILTERS,
            'activeFilter' => $filter,
            'replacementCandidates' => $this->replacementCandidates($user, $requests->getCollection(), $replacementAction),
        ]);
    }

    public function storeLeave(StoreLeaveRequest $request, ManageShiftRequestAction $action): RedirectResponse
    {
        $action->createLeave($request->user(), (int) $request->validated('shift_assignment_id'), $request->validated('reason'));

        return back()->with('success', 'Đã gửi đơn xin nghỉ.');
    }

    public function storeSwap(StoreSwapRequest $request, ManageShiftRequestAction $action): RedirectResponse
    {
        $action->createSwap(
            $request->user(),
            (int) $request->validated('shift_assignment_id'),
            (int) $request->validated('recipient_id'),
            (int) $request->validated('counter_shift_assignment_id'),
            $request->validated('reason'),
        );

        return back()->with('success', 'Đã gửi đề nghị đổi ca.');
    }

    public function cancel(Request $request, ShiftRequest $shiftRequest, ManageShiftRequestAction $action): RedirectResponse
    {
        $this->authorize('cancel', $shiftRequest);
        $action->cancel($request->user(), $shiftRequest, $request->string('note')->toString());

        return back()->with('success', 'Đã hủy đơn.');
    }

    public function respond(Request $request, ShiftRequest $shiftRequest, ManageShiftRequestAction $action): RedirectResponse
    {
        $this->authorize('respond', $shiftRequest);
        $action->respond($request->user(), $shiftRequest, $request->boolean('accepted'), $request->string('note')->toString());

        return back()->with('success', 'Đã phản hồi đề nghị đổi ca.');
    }

    public function decide(Request $request, ShiftRequest $shiftRequest, ManageShiftRequestAction $action): RedirectResponse
    {
        $this->authorize('decide', $shiftRequest);
        $action->decide($request->user(), $shiftRequest, $request->boolean('approved'), $request->string('note')->toString());

        return back()->with('success', 'Đã cập nhật trạng thái đơn.');
    }

    public function assignReplacement(Request $request, ShiftRequest $shiftRequest, AssignShiftReplacementAction $action): RedirectResponse
    {
        $this->authorize('assignReplacement', $shiftRequest);
        $request->validate(['replacement_employee_id' => ['required', 'integer', 'exists:users,id']]);
        $replacement = User::query()->findOrFail($request->integer('replacement_employee_id'));
        $action->handle($request->user(), $shiftRequest, $replacement);

        return back()->with('success', 'Đã phân nhân viên thay ca.');
    }

    /**
     * Who could cover each approved absence, keyed by request.
     *
     * Only worked out for the rows that still need somebody, because each one
     * costs a candidate search.
     *
     * @param  Collection<int, ShiftRequest>  $requests
     * @return Collection<int, Collection<int, User>>
     */
    private function replacementCandidates(User $user, Collection $requests, AssignShiftReplacementAction $action): Collection
    {
        if (! $user->isLeadership()) {
            return collect();
        }

        return $requests
            ->filter(fn (ShiftRequest $request): bool => $request->awaitingReplacement())
            ->mapWithKeys(fn (ShiftRequest $request): array => [
                $request->getKey() => $action->candidates($user, $request),
            ]);
    }
}
