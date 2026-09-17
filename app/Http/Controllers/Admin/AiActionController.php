<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AiActionStatus;
use App\Http\Controllers\Controller;
use App\Models\AiActionProposal;
use App\Services\Ai\AiActionExecutor;
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
    ): RedirectResponse {
        Gate::authorize('use-ai-assistant');

        try {
            $result = $executor->execute($proposal, $request->user());
        } catch (ValidationException $exception) {
            return back()->with('error', implode(' ', array_map(
                fn (array $messages): string => implode(' ', $messages),
                $exception->errors()
            )));
        } catch (HttpException $exception) {
            throw $exception;
        } catch (Throwable) {
            return back()->with('error', 'Thao tác thất bại. Vui lòng thử lại hoặc liên hệ hỗ trợ.');
        }

        return back()->with(
            $result->status === AiActionStatus::Executed ? 'success' : 'status',
            $result->status === AiActionStatus::Executed
                ? 'Đã thực hiện thao tác được đề xuất.'
                : 'Đề xuất này đã được xử lý trước đó.',
        );
    }

    public function reject(
        Request $request,
        AiActionProposal $proposal,
        AiActionExecutor $executor,
    ): RedirectResponse {
        Gate::authorize('use-ai-assistant');
        $result = $executor->reject($proposal, $request->user());

        return back()->with(
            $result->status === AiActionStatus::Rejected ? 'success' : 'status',
            $result->status === AiActionStatus::Rejected
                ? 'Đã từ chối đề xuất của trợ lý AI.'
                : 'Đề xuất này đã được xử lý trước đó.',
        );
    }
}
