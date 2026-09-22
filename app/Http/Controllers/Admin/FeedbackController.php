<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FeedbackStatus;
use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Hàng đợi duyệt feedback khách gửi từ trang chủ.
 *
 * Mặc định chỉ hiện cái đang chờ, vì đó là việc phải làm; đã duyệt hay đã ẩn
 * xem lại qua bộ lọc.
 */
class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Feedback::class);

        $status = $request->string('status')->toString() ?: FeedbackStatus::Pending->value;

        return view('admin.feedback.index', [
            'items' => Feedback::query()
                ->with('reviewer')
                ->when(
                    $status !== 'all',
                    fn ($query) => $query->where('status', $status),
                )
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
            'statuses' => FeedbackStatus::options(),
            'selectedStatus' => $status,
            'pendingCount' => Feedback::query()->where('status', FeedbackStatus::Pending)->count(),
        ]);
    }

    public function update(Request $request, Feedback $feedback): RedirectResponse
    {
        $this->authorize('moderate', $feedback);

        $validated = $request->validate([
            'status' => ['required', Rule::in([FeedbackStatus::Published->value, FeedbackStatus::Rejected->value])],
        ], [], ['status' => 'trạng thái']);

        $isPublished = $validated['status'] === FeedbackStatus::Published->value;

        $feedback->update([
            'status' => $validated['status'],
            // Mốc đăng đặt lại mỗi lần cho lên trang, để feedback vừa duyệt
            // đứng đầu danh sách công khai dù khách gửi nó từ tháng trước.
            'published_at' => $isPublished ? now() : null,
            'reviewed_by' => $request->user()->getKey(),
        ]);

        return redirect($this->backToList($request, 'admin.feedback.index'))
            ->with('success', $isPublished
                ? 'Feedback đã hiện trên trang chủ.'
                : 'Đã gỡ feedback khỏi trang chủ.');
    }

    public function destroy(Request $request, Feedback $feedback): RedirectResponse
    {
        $this->authorize('delete', $feedback);

        $feedback->delete();

        return redirect($this->backToList($request, 'admin.feedback.index'))
            ->with('success', 'Đã xóa feedback.');
    }
}
