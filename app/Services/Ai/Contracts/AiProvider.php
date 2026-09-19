<?php

namespace App\Services\Ai\Contracts;

use App\Models\User;
use App\Services\Ai\AiProviderResult;
use App\Services\Ai\AiStreamEvent;
use Closure;

interface AiProvider
{
    /** @param array<int, array<string, mixed>> $messages */
    public function chat(array $messages): AiProviderResult;

    /**
     * Trả lời có kèm vòng lặp công cụ và phát tiến trình ra ngoài.
     *
     * $onEvent nhận AiStreamEvent mỗi khi có chữ mới hoặc model gọi một công cụ,
     * để giao diện vẽ dần thay vì chờ trọn vẹn phản hồi.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  Closure(AiStreamEvent): void|null  $onEvent
     */
    public function converse(array $messages, User $user, ?Closure $onEvent = null): AiProviderResult;
}
