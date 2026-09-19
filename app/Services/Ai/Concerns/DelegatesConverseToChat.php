<?php

namespace App\Services\Ai\Concerns;

use App\Models\User;
use App\Services\Ai\AiProviderResult;
use App\Services\Ai\AiStreamEvent;
use Closure;

/**
 * Cho một provider đơn giản thỏa mãn hợp đồng đầy đủ.
 *
 * Provider nào không tự chạy vòng lặp công cụ thì chỉ cần cài chat(); trait này
 * dựng converse() bằng cách gọi chat() rồi phát nội dung ra một lần. Nhờ vậy các
 * bản giả trong kiểm thử và các provider thay thế không phải chép lại phần
 * stream mà chúng vốn không dùng tới.
 */
trait DelegatesConverseToChat
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  Closure(AiStreamEvent): void|null  $onEvent
     */
    public function converse(array $messages, User $user, ?Closure $onEvent = null): AiProviderResult
    {
        $result = $this->chat($messages);

        if ($onEvent !== null) {
            $onEvent(AiStreamEvent::delta($result->content));
        }

        return $result;
    }
}
