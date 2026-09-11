<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RiskReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewRiskFlagRequest;
use App\Models\RiskFlag;
use App\Services\Risk\RiskDetector;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Hàng đợi những việc đáng xem lại.
 *
 * Mỗi cờ là một câu hỏi chứ không phải lời buộc tội, nên màn hình này chỉ có
 * một việc: đưa nó tới trước mắt một người và ghi lại kết luận của họ.
 */
class RiskFlagController extends Controller
{
    public function __construct(private BranchContext $branchContext) {}

    public function index(Request $request, RiskDetector $detector): View
    {
        $this->authorize('viewAny', RiskFlag::class);

        $branchIds = $this->branchContext->scopeIds() ?: [0];

        // Quét lại khi mở màn hình: tiệm nhỏ chưa chạy scheduler, và một hàng
        // đợi chỉ đầy khi có người chủ động chạy lệnh thì sẽ không ai thấy gì.
        $detector->sweep(now()->subDays(7));

        $status = $request->string('status')->toString() ?: RiskReviewStatus::Open->value;

        return view('admin.risk.index', [
            'flags' => RiskFlag::query()
                ->forBranches($branchIds)
                ->when($status !== 'all', fn ($query) => $query->where('review_status', $status))
                ->with(['actor:id,name', 'branch:id,name', 'reviewer:id,name'])
                ->orderByDesc('detected_at')
                ->paginate(25)
                ->withQueryString(),
            'statuses' => ['all' => 'Tất cả'] + RiskReviewStatus::options(),
            'currentStatus' => $status,
            'openCount' => RiskFlag::query()->forBranches($branchIds)->open()->count(),
        ]);
    }

    public function update(ReviewRiskFlagRequest $request, RiskFlag $riskFlag): RedirectResponse
    {
        $riskFlag->forceFill([
            'review_status' => $request->validated('review_status'),
            'review_note' => $request->validated('review_note'),
            'reviewed_by' => $request->user()->getKey(),
            'reviewed_at' => now(),
        ])->save();

        return back()->with('success', 'Đã ghi nhận kết luận cho cảnh báo này.');
    }
}
