<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Shifts\AssignShiftReplacementAction;
use App\Actions\Shifts\ManageShiftRequestAction;
use App\Enums\ShiftRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShiftRequests\StoreLeaveRequest;
use App\Http\Requests\ShiftRequests\StoreSwapRequest;
use App\Models\ShiftAssignment;
use App\Models\ShiftRequest;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftRequestController extends Controller
{
    public function __construct(private BranchContext $branchContext) {}

    public function index(Request $request, AssignShiftReplacementAction $replacementAction): View
    {
        $this->authorize('viewAny', ShiftRequest::class);
        $user = $request->user();
        $branchIds = $this->branchContext->scopeIds();

        $requests = ShiftRequest::query()
            ->with(['requester', 'recipient', 'shiftAssignment', 'counterShiftAssignment', 'replacement.replacementEmployee'])
            ->when($user->isEmployee(), fn ($query) => $query->where(fn ($inner) => $inner->where('requester_id', $user->getKey())->orWhere('recipient_id', $user->getKey())))
            ->when(! $user->isEmployee() && ! $user->isOwner(), fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->latest()
            ->paginate(30);

        $replacementCandidates = collect();

        if ($user->isOwner() || $user->isManager()) {
            $requests->getCollection()
                ->filter(fn (ShiftRequest $shiftRequest): bool => $shiftRequest->status === ShiftRequestStatus::Approved
                    && $shiftRequest->type->value === 'leave'
                    && $shiftRequest->replacement === null)
                ->each(fn (ShiftRequest $shiftRequest) => $replacementCandidates->put(
                    $shiftRequest->getKey(),
                    $replacementAction->candidates($user, $shiftRequest),
                ));
        }

        $ownAssignments = $user->isEmployee()
            ? ShiftAssignment::query()
                ->where('employee_id', $user->getKey())
                ->whereDate('work_date', '>=', now()->toDateString())
                ->with('workShift')
                ->orderBy('planned_start_at')
                ->get()
            : collect();

        $swapAssignments = $user->isEmployee() && $ownAssignments->isNotEmpty()
            ? ShiftAssignment::query()
                ->where('employee_id', '!=', $user->getKey())
                ->whereHas('employee', fn ($query) => $query->active()->staff())
                ->where(function ($query) use ($ownAssignments) {
                    $ownAssignments->each(fn (ShiftAssignment $assignment) => $query->orWhere(fn ($inner) => $inner
                        ->where('branch_id', $assignment->branch_id)
                        ->whereDate('work_date', $assignment->work_date)));
                })
                ->with(['employee', 'workShift'])
                ->orderBy('work_date')
                ->orderBy('planned_start_at')
                ->get()
            : collect();

        return view('admin.shift-requests.index', [
            'requests' => $requests,
            'ownAssignments' => $ownAssignments,
            'swapAssignments' => $swapAssignments,
            'canManage' => $user->isOwner() || $user->isManager(),
            'replacementCandidates' => $replacementCandidates,
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
}
