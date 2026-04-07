<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiAdviceLog;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAuditController extends Controller
{
    /**
     * List users who have AI conversations.
     * GET /api/admin/ai-audit/users
     */
    public function users(Request $request): JsonResponse
    {
        $search = $request->query('search', '');

        $query = User::whereHas('aiConversations')
            ->withCount('aiConversations as conversation_count')
            ->withMax('aiConversations', 'last_message_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('surname', 'like', "%{$search}%");
            });
        }

        $users = $query->orderByDesc('ai_conversations_max_last_message_at')
            ->paginate(25);

        $users->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => trim(($user->first_name ?? '').' '.($user->surname ?? '')),
                'email' => $user->email,
                'is_preview_user' => (bool) $user->is_preview_user,
                'conversation_count' => (int) $user->conversation_count,
                'last_conversation_at' => $user->ai_conversations_max_last_message_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * List conversations for a user.
     * GET /api/admin/ai-audit/users/{userId}/conversations
     */
    public function conversations(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        $conversations = AiConversation::where('user_id', $userId)
            ->where('message_count', '>', 0)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'status' => $c->status,
                'model_used' => $c->model_used,
                'message_count' => $c->message_count,
                'total_input_tokens' => $c->total_input_tokens,
                'total_output_tokens' => $c->total_output_tokens,
                'created_at' => $c->created_at?->toIso8601String(),
                'last_message_at' => $c->last_message_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => trim(($user->first_name ?? '').' '.($user->surname ?? '')),
                    'email' => $user->email,
                ],
                'conversations' => $conversations,
            ],
        ]);
    }

    /**
     * Get full message thread for a conversation with audit data.
     * GET /api/admin/ai-audit/conversations/{conversationId}/messages
     */
    public function messages(int $conversationId): JsonResponse
    {
        $conversation = AiConversation::with('user')->findOrFail($conversationId);
        $user = $conversation->user;

        $messages = AiMessage::where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'system_prompt' => $m->system_prompt,
                'input_tokens' => $m->input_tokens,
                'output_tokens' => $m->output_tokens,
                'model_used' => $m->model_used,
                'metadata' => $m->metadata,
                'created_at' => $m->created_at?->toIso8601String(),
            ]);

        $adviceLog = AiAdviceLog::where('conversation_id', $conversationId)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'user' => [
                        'id' => $user->id,
                        'name' => trim(($user->first_name ?? '').' '.($user->surname ?? '')),
                        'email' => $user->email,
                    ],
                    'model_used' => $conversation->model_used,
                    'total_input_tokens' => $conversation->total_input_tokens,
                    'total_output_tokens' => $conversation->total_output_tokens,
                    'created_at' => $conversation->created_at?->toIso8601String(),
                ],
                'messages' => $messages,
                'advice_log' => $adviceLog ? [
                    'query_type' => $adviceLog->query_type,
                    'classification' => $adviceLog->classification,
                    'kyc_status' => $adviceLog->kyc_status,
                    'tools_called' => $adviceLog->tools_called,
                    'user_data_snapshot' => $adviceLog->user_data_snapshot,
                ] : null,
            ],
        ]);
    }
}
