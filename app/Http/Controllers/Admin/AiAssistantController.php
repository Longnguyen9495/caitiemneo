<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\Ai\AiConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
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

    public function store(Request $request, AiConversationService $assistant): RedirectResponse
    {
        Gate::authorize('use-ai-assistant');

        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer'],
            'message' => ['required', 'string', 'max:'.config('ai.max_message_length')],
        ], [], [
            'message' => 'câu hỏi',
        ]);

        $conversation = isset($validated['conversation_id'])
            ? AiConversation::query()
                ->where('user_id', $request->user()->id)
                ->findOrFail($validated['conversation_id'])
            : $assistant->createConversation($request->user());

        try {
            $assistant->reply($request->user(), $conversation, trim($validated['message']));
        } catch (Throwable $exception) {
            report($exception);

            return to_route('admin.ai.index', ['conversation' => $conversation->id])
                ->with('error', app()->isProduction()
                    ? 'Trợ lý AI tạm thời chưa phản hồi. Vui lòng thử lại sau.'
                    : $exception->getMessage());
        }

        return to_route('admin.ai.index', ['conversation' => $conversation->id]);
    }
}
