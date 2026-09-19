<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\AiConversationService;
use App\Services\Ai\AiStreamEvent;
use App\Services\Ai\Contracts\AiProvider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AiAssistantController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('use-ai-assistant');

        $conversations = AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->latest('last_message_at')
            ->limit(30)
            ->get();

        $conversation = null;
        $requestedId = $request->integer('conversation');

        if ($requestedId > 0) {
            $conversation = AiConversation::query()
                ->where('user_id', $request->user()->id)
                ->with(['messages' => fn ($query) => $query
                    ->with('actionProposals')
                    ->oldest('id')])
                ->findOrFail($requestedId);
        }

        return view('admin.ai.index', [
            'conversations' => $conversations,
            'conversation' => $conversation,
            'aiEnabled' => (bool) config('ai.enabled'),
        ]);
    }

    /**
     * Gửi câu hỏi theo lối tải lại trang.
     *
     * Giữ lại cho trình duyệt không chạy JavaScript và cho các kiểm thử hiện có;
     * giao diện thường đi qua stream().
     */
    public function store(Request $request, AiConversationService $assistant): RedirectResponse
    {
        Gate::authorize('use-ai-assistant');

        $validated = $this->validateMessage($request);
        $conversation = $this->resolveConversation($request, $assistant, $validated['conversation_id'] ?? null);

        try {
            $assistant->reply($request->user(), $conversation, trim($validated['message']));
        } catch (Throwable $exception) {
            report($exception);

            return to_route('admin.ai.index', ['conversation' => $conversation->id])
                ->with('error', $this->failureMessage($exception));
        }

        return to_route('admin.ai.index', ['conversation' => $conversation->id]);
    }

    /**
     * Gửi câu hỏi và phát câu trả lời dần về trình duyệt.
     *
     * Model mất từ vài giây tới vài chục giây để soạn xong; chờ trọn vẹn rồi mới
     * vẽ khiến người dùng nhìn màn hình đứng im suốt quãng đó. Phát dần cho chữ
     * hiện ngay khi có, và báo luôn từng bước tra dữ liệu đang chạy.
     */
    public function stream(Request $request, AiConversationService $assistant, AiProvider $provider): StreamedResponse
    {
        Gate::authorize('use-ai-assistant');

        $validated = $this->validateMessage($request);
        $conversation = $this->resolveConversation($request, $assistant, $validated['conversation_id'] ?? null);
        $question = trim($validated['message']);
        $user = $request->user();

        return response()->eventStream(function () use ($assistant, $provider, $user, $conversation, $question) {
            yield new StreamedEvent('conversation', json_encode([
                'conversation_id' => $conversation->id,
            ], JSON_UNESCAPED_UNICODE));

            $messages = $assistant->buildMessages($user, $conversation, $question);
            $assistant->recordQuestion($conversation, $question);

            // Provider báo tiến trình qua callback, còn eventStream lại cần một
            // generator. Callback ghi thẳng ra output thay vì dồn lại chờ xong:
            // dồn lại thì người dùng vẫn ngồi nhìn màn hình đứng im, đúng thứ mà
            // việc phát dần sinh ra để tránh.
            $emit = function (AiStreamEvent $event): void {
                $payload = match ($event->type) {
                    AiStreamEvent::Delta => ['event' => 'delta', 'data' => ['text' => $event->text]],
                    AiStreamEvent::ToolCall => ['event' => 'tool', 'data' => ['label' => $event->toolLabel]],
                    default => null,
                };

                if ($payload === null) {
                    return;
                }

                echo 'event: '.$payload['event']."\n";
                echo 'data: '.json_encode($payload['data'], JSON_UNESCAPED_UNICODE)."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            try {
                $result = $provider->converse($messages, $user, $emit);
            } catch (Throwable $exception) {
                report($exception);

                yield new StreamedEvent('failed', json_encode([
                    'message' => $this->failureMessage($exception),
                ], JSON_UNESCAPED_UNICODE));

                return;
            }

            $message = $assistant->storeAnswer($user, $conversation, $result);

            yield new StreamedEvent('message', json_encode([
                'conversation_id' => $conversation->id,
                'html' => view('admin.ai.partials.message', [
                    'message' => $message->load('actionProposals'),
                ])->render(),
            ], JSON_UNESCAPED_UNICODE));
        }, [
            // Tắt đệm của proxy, nếu không sự kiện bị giữ lại cho tới khi xong
            // và toàn bộ công sức phát dần thành vô nghĩa.
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache, no-transform',
        ]);
    }

    /**
     * Vẽ lại một tin nhắn sau khi đề xuất trong đó được duyệt hoặc từ chối.
     */
    public function message(Request $request, AiMessage $message): JsonResponse
    {
        Gate::authorize('use-ai-assistant');

        abort_unless(
            $message->conversation()->where('user_id', $request->user()->id)->exists(),
            404,
        );

        return response()->json([
            'html' => view('admin.ai.partials.message', [
                'message' => $message->load('actionProposals'),
            ])->render(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validateMessage(Request $request): array
    {
        return $request->validate([
            'conversation_id' => ['nullable', 'integer'],
            'message' => ['required', 'string', 'max:'.config('ai.max_message_length')],
        ], [], [
            'message' => 'câu hỏi',
        ]);
    }

    private function resolveConversation(
        Request $request,
        AiConversationService $assistant,
        mixed $conversationId,
    ): AiConversation {
        if ($conversationId === null) {
            return $assistant->createConversation($request->user());
        }

        return AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($conversationId);
    }

    private function failureMessage(Throwable $exception): string
    {
        if ($exception instanceof RequestException && $exception->response->serverError()) {
            return 'Máy chủ AI đang tạm thời quá tải hoặc bảo trì. Câu hỏi của bạn đã được lưu; vui lòng thử gửi lại sau.';
        }

        return app()->isProduction()
            ? 'Trợ lý AI tạm thời chưa phản hồi. Câu hỏi của bạn đã được lưu; vui lòng thử lại sau.'
            : $exception->getMessage();
    }
}
