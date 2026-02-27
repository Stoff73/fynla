<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\AI\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiChatController extends Controller
{
    public function __construct(
        private readonly AiChatService $chatService,
    ) {}

    /**
     * List user's conversations.
     *
     * GET /api/ai-chat/conversations
     */
    public function index(Request $request): JsonResponse
    {
        $conversations = AiConversation::forUser($request->user()->id)
            ->active()
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get(['id', 'title', 'message_count', 'last_message_at', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ]);
    }

    /**
     * Start a new conversation.
     *
     * POST /api/ai-chat/conversations
     */
    public function create(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'status' => 'active',
            'model_used' => '',
            'metadata' => [
                'current_route' => $request->input('current_route'),
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $conversation,
        ], 201);
    }

    /**
     * Load a conversation with its messages.
     *
     * GET /api/ai-chat/conversations/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $conversation = AiConversation::forUser($request->user()->id)
            ->findOrFail($id);

        $messages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'metadata', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => $conversation,
                'messages' => $messages,
            ],
        ]);
    }

    /**
     * Soft-delete a conversation.
     *
     * DELETE /api/ai-chat/conversations/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $conversation = AiConversation::forUser($request->user()->id)
            ->findOrFail($id);

        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted',
        ]);
    }

    /**
     * Send a message and stream the response via SSE.
     *
     * POST /api/ai-chat/conversations/{id}/messages
     */
    public function sendMessage(Request $request, int $id): StreamedResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'current_route' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $conversation = AiConversation::forUser($user->id)->findOrFail($id);

        $message = $request->input('message');
        $currentRoute = $request->input('current_route');

        return new StreamedResponse(function () use ($user, $conversation, $message, $currentRoute) {
            try {
                $generator = $this->chatService->sendMessage($user, $conversation, $message, $currentRoute);

                foreach ($generator as $event) {
                    echo 'data: ' . json_encode($event) . "\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } catch (\Exception $e) {
                Log::error('[AiChatController] Streaming error', [
                    'user_id' => $user->id,
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                ]);

                echo 'data: ' . json_encode([
                    'type' => 'error',
                    'message' => 'An unexpected error occurred. Please try again.',
                ]) . "\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
