<?php

namespace App\Http\Controllers;

use App\Enums\FeedbackStatus;
use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FeedbackController extends Controller
{
    /**
     * Nhận một feedback từ trang công khai.
     *
     * Lưu ở trạng thái chờ duyệt, không có đường nào để người gửi tự cho nó
     * lên trang: `status` không đọc từ biểu mẫu mà do đây quyết định.
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            // Túi lỗi riêng. Trang chủ còn một biểu mẫu đặt lịch, và hai biểu
            // mẫu dùng chung một túi thì gõ sai ở đây làm biểu mẫu kia đỏ theo.
            $validated = $request->validateWithBag('feedback', [
                'author_name' => ['required', 'string', 'max:255'],
                'rating' => ['required', 'integer', 'min:1', 'max:5'],
                'content' => ['required', 'string', 'min:10', 'max:1000'],
            ], [], [
                'author_name' => 'tên của bạn',
                'rating' => 'số sao',
                'content' => 'nhận xét',
            ]);
        } catch (ValidationException $exception) {
            $exception->redirectTo(route('home').'#feedback');

            throw $exception;
        }

        Feedback::query()->create($validated + ['status' => FeedbackStatus::Pending->value]);

        return redirect()->to(route('home').'#feedback')
            ->with('feedback_success', 'Cảm ơn bạn! Tiệm sẽ đọc và đăng lên trang sau khi xem qua.');
    }
}
