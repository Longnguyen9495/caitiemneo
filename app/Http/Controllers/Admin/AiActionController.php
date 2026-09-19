<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AiActionStatus;
use App\Http\Controllers\Controller;
use App\Models\AiActionProposal;
use App\Services\Ai\AiActionExecutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AiActionController extends Controller
{
    public function confirm(
        Request $request,
        AiActionProposal $proposal,
        AiActionExecutor $executor,
    ): RedirectResponse|JsonResponse {
        Gate::authorize('use-ai-assistant');

        try {
            $result = $executor->execute($proposal, $request->user());
        } catch (ValidationException $exception) {
            $message = implode(' ', array_map(
                fn (array $messages): string => implode(' ', $messages),
                $exception->errors()
            ));

            return $this->failure($request, $proposal, $message);
        } catch (HttpException $exception) {
            throw $exception;
        } catch (Throwable) {
            return $this->failure($request, $proposal, 'Thao tác thất bại. Vui lòng thử lại hoặc liên hệ hỗ trợ.');
        }

        $executed = $result->status === AiActionStatus::Executed;

        return $this->respond(
            $request,
            $result,
            $executed ? 'success' : 'status',
            $executed
                ? 'Đã thực hiện thao tác được đề xuất.'
                : 'Đề xuất này đã được xử lý trước đó.',
        );
    }

    public function reject(
        Request $request,
        AiActionProposal $proposal,
        AiActionExecutor $executor,
    ): RedirectResponse|JsonResponse {
        Gate::authorize('use-ai-assistant');
        $result = $executor->reject($proposal, $request->user());

        $rejected = $result->status === AiActionStatus::Rejected;

        return $this->respond(
            $request,
            $result,
            $rejected ? 'success' : 'status',
            $rejected
                ? 'Đã từ chối đề xuất của trợ lý AI.'
                : 'Đề xuất này đã được xử lý trước đó.',
        );
    }

    /**
     * Trả về HTML tin nhắn đã cập nhật cho lời gọi nền, hoặc quay lại trang cho
     * lời gọi biểu mẫu thường.
     *
     * Duyệt một đề xuất chỉ đổi trạng thái của đúng một khối nhỏ; tải lại cả
     * trang cho việc đó làm mất vị trí cuộn và nháy trắng màn hình.
     */
    private function respond(
        Request $request,
        AiActionProposal $proposal,
        string $level,
        string $message,
    ): RedirectResponse|JsonResponse {
        if (! $request->expectsJson()) {
            return back()->with($level, $message);
        }

        return response()->json([
            'status' => $level,
            'message' => $message,
            'html' => $this->messageHtml($proposal),
        ]);
    }

    private function failure(
        Request $request,
        AiActionProposal $proposal,
        string $message,
    ): RedirectResponse|JsonResponse {
        if (! $request->expectsJson()) {
            return back()->with('error', $message);
        }

        return response()->json([
            'status' => 'error',
            'message' => $message,
            'html' => $this->messageHtml($proposal->fresh() ?? $proposal),
        ], 422);
    }

    private function messageHtml(AiActionProposal $proposal): string
    {
        return view('admin.ai.partials.message', [
            'message' => $proposal->message()->with('actionProposals')->firstOrFail(),
        ])->render();
    }
}
