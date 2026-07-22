<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiAdviceLog;
use App\Models\AiAuditEvent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\AuditChainService;
use App\Services\AI\Memory\Episodic\EpisodeBlobLocator;
use App\Services\AI\Memory\Episodic\EpisodeProjection;
use App\Services\Auth\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
                'assembled_context' => $m->assembled_context,
                'tool_calls' => $m->tool_calls,
                'tool_results' => $m->tool_results,
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

    /**
     * S0.12 — paginated read of the hash-chain audit table for admins.
     * GET /api/admin/ai-audit/chain
     */
    public function chain(Request $request): JsonResponse
    {
        $query = AiAuditEvent::query()->orderByDesc('id');

        if ($userId = (int) $request->query('user_id', '0')) {
            $query->where('user_id', $userId);
        }
        if ($status = (string) $request->query('status', '')) {
            $query->where('status', $status);
        }
        if ($operation = (string) $request->query('operation', '')) {
            $query->where('operation', $operation);
        }

        $events = $query->paginate(50);

        $events->getCollection()->transform(fn (AiAuditEvent $row) => [
            'id' => $row->id,
            'user_id' => $row->user_id,
            'conversation_id' => $row->conversation_id,
            'tool_name' => $row->tool_name,
            'operation' => $row->operation,
            'status' => $row->status,
            'entity_type' => $row->entity_type,
            'entity_id' => $row->entity_id,
            'input_summary' => $row->input_summary,
            'result_summary' => $row->result_summary,
            'prev_hash' => $row->prev_hash,
            'row_hash' => $row->row_hash,
            'signature' => $row->signature,
            'signed_at' => $row->signed_at?->toIso8601String(),
            'created_at' => $row->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * S0.12 — verify the chain end-to-end. Returns the same JSON shape
     * the artisan command emits.
     * GET /api/admin/ai-audit/chain/verify
     */
    public function verifyChain(AuditChainService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $service->verifyChain(),
        ]);
    }

    /**
     * Phase 2 (Task 13) — ADMIN-only paginated list of ALL users' episodes.
     * Read-only. Filters: user_id, from/to (date), module, persona.
     * GET /api/admin/ai-audit/episodes
     */
    public function episodes(Request $request): JsonResponse
    {
        $query = AiMessage::query()
            ->where('role', 'assistant')
            ->with('conversation:id,user_id');

        if ($userId = (int) $request->query('user_id', '0')) {
            $query->whereHas('conversation', fn ($q) => $q->where('user_id', $userId));
        }
        if ($from = (string) $request->query('from', '')) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = (string) $request->query('to', '')) {
            $query->where('created_at', '<=', $to);
        }
        if ($module = (string) $request->query('module', '')) {
            $query->where('metadata->module', $module);
        }
        if ($persona = (string) $request->query('persona', '')) {
            $query->where('persona', $persona);
        }

        $episodes = $query->orderByDesc('id')->paginate(25);

        $episodes->getCollection()->transform(fn (AiMessage $m) => $this->summariseEpisode($m));

        return response()->json([
            'success' => true,
            'data' => $episodes,
        ]);
    }

    /**
     * Phase 2 (Task 13) — episode detail with blob body. Admin (any) or
     * advisor (only when the episode's user is the advisor's client).
     * GET /api/admin/ai-audit/episodes/{id}
     * GET /api/advisor/clients/{clientId}/episodes/{id}
     */
    public function episode(Request $request, EpisodeProjection $projection): JsonResponse
    {
        // Resolved by route name so the leading {clientId} segment on the
        // advisor route cannot shift the positional binding.
        $id = (int) $request->route('id');

        if (! $this->canViewMessage($request->user(), $id)) {
            return $this->episodeForbidden($request->user());
        }

        return response()->json([
            'success' => true,
            'data' => $projection->detail($id),
        ]);
    }

    /**
     * Phase 2 (Task 13) — on-demand single-episode blob/attestation check.
     * POST /api/admin/ai-audit/episodes/{id}/verify
     * Same authorisation as episode().
     */
    public function verifyEpisode(Request $request, EpisodeBlobLocator $locator): JsonResponse
    {
        $id = (int) $request->route('id');

        if (! $this->canViewMessage($request->user(), $id)) {
            return $this->episodeForbidden($request->user());
        }

        return response()->json([
            'success' => true,
            'data' => $this->checkEpisode($id, $locator),
        ]);
    }

    /**
     * Phase 2 (Task 13) — ADVISOR-scoped paginated episode list for ONE client
     * (admins also pass). Mirrors AdvisorController's ownership check: a 404 is
     * returned when the client is not assigned to the advisor.
     * GET /api/advisor/clients/{clientId}/episodes
     */
    public function clientEpisodes(Request $request, int $clientId, EpisodeProjection $projection): JsonResponse
    {
        $user = $request->user();

        if (! $this->permissionService()->isAdmin($user)
            && ! $user->advisorClients()->where('client_id', $clientId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found or not assigned to you.',
            ], 404);
        }

        $page = max(1, (int) $request->query('page', '1'));
        $perPage = 25;
        $rows = $projection->list($clientId, 1000);
        $total = count($rows);
        $slice = array_slice($rows, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => $slice,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    /**
     * Authorise a single-episode read: admins see any; advisors only see an
     * episode whose owning user is one of their assigned clients.
     */
    private function canViewMessage(?User $user, int $messageId): bool
    {
        if ($user === null) {
            return false;
        }
        if ($this->permissionService()->isAdmin($user)) {
            return true;
        }
        if (! $user->is_advisor) {
            return false;
        }

        $ownerId = AiMessage::where('ai_messages.id', $messageId)
            ->join('ai_conversations', 'ai_conversations.id', '=', 'ai_messages.conversation_id')
            ->value('ai_conversations.user_id');

        if ($ownerId === null) {
            return false;
        }

        return $user->advisorClients()->where('client_id', (int) $ownerId)->exists();
    }

    private function episodeForbidden(?User $user): JsonResponse
    {
        // Advisors are told "not found" to match the advisor-ownership pattern;
        // anyone else is plainly forbidden.
        $status = ($user !== null && $user->is_advisor) ? 404 : 403;

        return response()->json([
            'success' => false,
            'message' => $status === 404
                ? 'Episode not found or not assigned to you.'
                : 'Access denied.',
        ], $status);
    }

    /**
     * @return array{verified: bool, state: string}
     */
    private function checkEpisode(int $messageId, EpisodeBlobLocator $locator): array
    {
        $message = AiMessage::find($messageId);
        if ($message === null || $message->blob_md_path === null) {
            return ['verified' => false, 'state' => 'missing'];
        }

        $event = AiAuditEvent::where('tool_name', '__episode__')
            ->where('entity_id', $messageId)
            ->latest('id')
            ->first();

        if ($event === null) {
            return ['verified' => false, 'state' => 'no_attestation'];
        }

        $resolved = $locator->resolve($message->blob_md_path);
        if ($resolved === null) {
            return ['verified' => false, 'state' => 'missing'];
        }

        $actualSha = hash('sha256', (string) Storage::disk('local')->get($resolved));
        $attestedSha = (string) ($event->result_summary['blob_md_sha256'] ?? '');

        if ($attestedSha !== '' && hash_equals($attestedSha, $actualSha)) {
            return ['verified' => true, 'state' => 'verified'];
        }

        return ['verified' => false, 'state' => 'modified'];
    }

    /**
     * @return array<string, mixed>
     */
    private function summariseEpisode(AiMessage $m): array
    {
        return [
            'id' => $m->id,
            'user_id' => $m->conversation?->user_id,
            'conversation_id' => $m->conversation_id,
            'created_at' => $m->created_at?->toIso8601String(),
            'persona' => $m->persona,
            'model_used' => $m->model_used,
            'module' => $m->metadata['module'] ?? null,
            'tool_count' => is_array($m->tool_calls) ? count($m->tool_calls) : 0,
            'has_blob' => $m->blob_md_path !== null,
            'semantic_snapshot_id' => $m->semantic_snapshot_id,
        ];
    }

    private function permissionService(): PermissionService
    {
        return app(PermissionService::class);
    }
}
