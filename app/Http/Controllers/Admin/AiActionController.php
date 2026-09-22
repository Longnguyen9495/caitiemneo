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

        $submitted = $request->input('payload');

        try {
            $result = $executor->execute($proposal, $request->user(), is_array($submitted) ? $submitted : null);
        } catch (ValidationException $exception) {
            // Lỗi từ executor mang tên trường trần (customer_name); phiếu lại
            // đặt tên ô là payload[customer_name]. Đổi khóa để lỗi hiện ngay
            // dưới ô sai thay vì gom thành một dòng đỏ ở đầu trang.
            return back()
                ->withInput()
                ->withErrors($this->fieldErrors($exception))
                ->with('error', 'Phiếu còn thiếu hoặc sai thông tin. Bạn kiểm tra lại các ô được đánh dấu nhé.');
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

    /**
     * @return array<string, array<int, string>>
     */
    private function fieldErrors(ValidationException $exception): array
    {
        $errors = [];

        foreach ($exception->errors() as $key => $messages) {
            $errors['payload.'.$key] = $messages;

            // "service_ids.2" là lỗi của một dòng trong danh sách; ô hiện trên
            // phiếu là cả nhóm, nên gắn thêm lỗi vào tên nhóm để nó hiện ra.
            if (str_contains($key, '.')) {
                $parent = 'payload.'.explode('.', $key)[0];
                $errors[$parent] = array_merge($errors[$parent] ?? [], $messages);
            }
        }

        return $errors;
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
