<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Agents\CoordinatingAgent;
use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\FynPersonaOrchestrator;
use App\Services\Onboarding\AssetCaptureEntityExtractor;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiChatController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly CoordinatingAgent $coordinatingAgent,
        private readonly OnboardingChatDirector $onboardingDirector,
        private readonly FynPersonaOrchestrator $orchestrator,
        private readonly AssetCaptureEntityExtractor $entityExtractor,
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
     * Get the user's current token usage and reset time.
     *
     * GET /api/ai-chat/token-usage
     */
    public function tokenUsage(Request $request): JsonResponse
    {
        $usage = $this->coordinatingAgent->getTokenUsageDetails($request->user());

        return response()->json([
            'success' => true,
            'data' => $usage,
        ]);
    }

    /**
     * Send a message and stream the response via SSE.
     *
     * POST /api/ai-chat/conversations/{id}/messages
     *
     * Both preview and real users now use the same chat() method on CoordinatingAgent.
     * Preview users are handled via tool restrictions (getTools(true) excludes write tools)
     * and the preview mode section in the system prompt.
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

        // Three-way dispatch:
        //   1. Onboarding (mid-flow users) → OnboardingChatDirector — the
        //      deterministic state machine.
        //   2. Persona split (post-onboarding + flag on) → FynPersonaOrchestrator.
        //   3. Default (flag off) → CoordinatingAgent::chat (pre-split behaviour).
        $inOnboarding = $user->onboarding_completed === false
            && $user->onboarding_fyn_step !== null
            && (bool) config('onboarding.fyn_flow_enabled', true);

        $splitEnabled = (bool) config('fyn.persona_split_enabled', false);

        return new StreamedResponse(function () use ($user, $conversation, $message, $currentRoute, $inOnboarding, $splitEnabled) {
            try {
                $generator = match (true) {
                    $inOnboarding => $this->onboardingDirector->handleUserMessage($user, $conversation, $message, $currentRoute),
                    $splitEnabled => $this->orchestrator->dispatch($user, $conversation, $message, $currentRoute),
                    default => $this->coordinatingAgent->chat($user, $conversation, $message, $currentRoute),
                };

                // B-1 — multi-entity gap-fill runs on EVERY chat turn regardless
                // of which dispatch branch was taken. The director and invoker
                // paths also gap-fill internally; the extractor's identity-key
                // dedupe ensures we don't double-emit entities those paths
                // already covered. This wrapper exists to cover the default
                // CoordinatingAgent::chat path (flag off) which otherwise has
                // no gap-fill layer — i.e. the normal Fyn Quick Chat on the
                // dashboard today.
                $wrapped = $this->wrapWithMultiEntityGapFill($generator, $user, $message);

                foreach ($wrapped as $event) {
                    echo 'data: '.json_encode($event)."\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } catch (\Exception $e) {
                Log::error('[AiChatController] Streaming error', [
                    'user_id' => $user->id,
                    'conversation_id' => $conversation->id,
                    'in_onboarding' => $inOnboarding,
                    'split_enabled' => $splitEnabled,
                    'error' => $e->getMessage(),
                ]);

                echo 'data: '.json_encode([
                    'type' => 'error',
                    'message' => 'An unexpected error occurred. Please try again.',
                ])."\n\n";

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

    /**
     * Backend-initiated first turn of the Fyn onboarding flow.
     *
     * POST /api/ai-chat/onboarding/start
     *
     * Creates a new Onboarding conversation, sets the user's
     * onboarding_fyn_step to 'path_choice', and streams SSE for the
     * path_choice bubbles. No user message is persisted — Fyn speaks
     * first.
     *
     * Returns non-SSE JSON failures for short-circuit cases:
     *   409 if onboarding_completed is true
     *   503 if the feature flag is off
     *   403 if the user is a preview persona
     *   200 (SSE) with a 'resume' event if the user is already mid-flow
     */
    public function startOnboarding(Request $request): StreamedResponse|JsonResponse
    {
        $user = $request->user();

        if ($user->onboarding_completed === true) {
            return response()->json([
                'success' => false,
                'reason' => 'already_completed',
            ], 409);
        }

        if (! (bool) config('onboarding.fyn_flow_enabled', true)) {
            return response()->json([
                'success' => false,
                'reason' => 'disabled',
            ], 503);
        }

        if ($user->is_preview_user) {
            return response()->json([
                'success' => false,
                'reason' => 'preview_mode',
            ], 403);
        }

        // Already mid-flow? Emit a resume event and point the frontend
        // at the existing conversation instead of creating a new one.
        if ($user->onboarding_fyn_step !== null) {
            $existing = AiConversation::forUser($user->id)
                ->where('title', 'Onboarding')
                ->latest('id')
                ->first();

            return new StreamedResponse(function () use ($existing, $user) {
                $event = [
                    'type' => 'resume',
                    'conversation_id' => $existing?->id,
                    'current_step' => $user->onboarding_fyn_step,
                ];
                echo 'data: '.json_encode($event)."\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        // Fresh start — create the conversation and stream turn 1.
        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'status' => 'active',
            'model_used' => 'director',
            'title' => 'Onboarding',
            'metadata' => [
                'source' => 'fyn_onboarding',
            ],
        ]);

        $user->onboarding_fyn_step = OnboardingStateMachine::STATE_PATH_CHOICE;
        $user->onboarding_started_at = $user->onboarding_started_at ?? now();
        $user->save();

        return new StreamedResponse(function () use ($user, $conversation) {
            // Emit the conversation id first so the frontend can route
            // subsequent /messages calls to this specific conversation.
            $firstEvent = [
                'type' => 'conversation_created',
                'conversation_id' => $conversation->id,
                'title' => $conversation->title,
            ];
            echo 'data: '.json_encode($firstEvent)."\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            try {
                foreach ($this->onboardingDirector->emitFirstTurn($user, $conversation) as $event) {
                    echo 'data: '.json_encode($event)."\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } catch (\Exception $e) {
                Log::error('[AiChatController] Onboarding start error', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                echo 'data: '.json_encode([
                    'type' => 'error',
                    'message' => 'Onboarding is temporarily unavailable. Please try again.',
                ])."\n\n";

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

    /**
     * Return the user's current onboarding status. Used by the frontend
     * on chat open to decide whether to call /start or resume an existing
     * conversation.
     *
     * GET /api/ai-chat/onboarding/status
     */
    public function getOnboardingStatus(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->onboardingDirector->getOnboardingStatus($request->user()),
        ]);
    }

    /**
     * Routed actions against a conversation — replaces the old sentinel-string
     * user-message path (__resume__ / __continue__ / __restart__ / __skip__).
     *
     * POST /api/ai-chat/conversations/{id}/action
     *
     * Body: { action: 'resume' | 'continue' | 'restart' | 'skip' }
     *
     * Actions are NOT persisted as AiMessage rows. Streams director or
     * orchestrator events back to the client via SSE. Covered by the
     * existing api/ai-chat/conversations/* prefix match in
     * PreviewWriteInterceptor::EXCLUDED_ROUTES.
     */
    public function action(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $request->validate([
            'action' => 'required|string|in:resume,continue,restart,skip',
        ]);

        $user = $request->user();
        $conversation = AiConversation::forUser($user->id)->findOrFail($id);
        $action = $request->input('action');

        $inOnboarding = $user->onboarding_completed === false
            && $user->onboarding_fyn_step !== null
            && (bool) config('onboarding.fyn_flow_enabled', true);

        return new StreamedResponse(function () use ($user, $conversation, $action, $inOnboarding) {
            try {
                $generator = $inOnboarding
                    ? $this->onboardingDirector->handleAction($user, $conversation, $action)
                    : (function () use ($action) {
                        // Post-onboarding currently has no action semantics beyond
                        // the director's onboarding-specific ones. Emit a no-op
                        // acknowledgement so the client gets a clean response.
                        yield [
                            'type' => 'content',
                            'text' => "I'm not sure what to do with that right now.",
                        ];
                        yield ['type' => 'done'];
                    })();

                foreach ($generator as $event) {
                    echo 'data: '.json_encode($event)."\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } catch (\Exception $e) {
                Log::error('[AiChatController] Action streaming error', [
                    'user_id' => $user->id,
                    'conversation_id' => $conversation->id,
                    'action' => $action,
                    'error' => $e->getMessage(),
                ]);

                echo 'data: '.json_encode([
                    'type' => 'error',
                    'message' => 'The action could not be completed. Please try again.',
                ])."\n\n";

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

    /**
     * B-1 — multi-entity gap-fill decorator.
     *
     * Wraps any chat generator so that after the LLM's stream completes, we
     * parse the user's message deterministically, compare against the
     * fill_form events the LLM actually emitted, and synthesise extra
     * fill_form events (plus flanking tool_use markers) for every entity
     * the LLM dropped.
     *
     * Focus is inferred from the entity_type on emitted fill_form events:
     *   protection_policy → protection, savings_account → savings,
     *   investment_account/holding → investment, pension → retirement.
     * If no fill_form events were emitted (no LLM tool calls this turn),
     * the wrapper is a no-op — we do NOT speculatively extract from
     * arbitrary messages, only backstop tool calls the LLM already started.
     *
     * Idempotent with respect to upstream gap-fills — director and invoker
     * paths also run their own gap-fill; extractor identity keys dedupe so
     * their synthetic fills look "already emitted" to this wrapper.
     *
     * @param  \Generator<array<string, mixed>>  $generator
     * @return \Generator<array<string, mixed>>
     */
    private function wrapWithMultiEntityGapFill(
        \Generator $generator,
        User $user,
        string $message,
    ): \Generator {
        $emittedFills = []; // list of [focus, fields] tuples
        $doneSeen = false;
        $lastEvent = null;

        foreach ($generator as $event) {
            $type = $event['type'] ?? '';

            if ($type === 'fill_form') {
                $focus = $this->focusFromEntityType((string) ($event['entity_type'] ?? ''));
                if ($focus !== null) {
                    $emittedFills[] = ['focus' => $focus, 'fields' => (array) ($event['fields'] ?? [])];
                }
            }

            if ($type === 'done') {
                // Run gap-fill BEFORE forwarding done so the frontend queues
                // synthetic fills in the same turn.
                yield from $this->runControllerGapFill($user, $message, $emittedFills);
                $doneSeen = true;
            }

            yield $event;
            $lastEvent = $event;
        }

        // Safety net — generator exited without done (e.g. error path).
        if (! $doneSeen && $emittedFills !== []) {
            yield from $this->runControllerGapFill($user, $message, $emittedFills);
        }
    }

    /**
     * Group emitted fills by focus and fire the extractor once per focus,
     * comparing against only that focus's emissions.
     *
     * @param  list<array{focus: string, fields: array<string, mixed>}>  $emittedFills
     * @return \Generator<array<string, mixed>>
     */
    private function runControllerGapFill(User $user, string $message, array $emittedFills): \Generator
    {
        // Group by focus.
        $byFocus = [];
        foreach ($emittedFills as $f) {
            $byFocus[$f['focus']][] = $f['fields'];
        }

        foreach ($byFocus as $focus => $llmEmittedFieldsList) {
            $tool = $this->entityExtractor->toolNameForFocus($focus);
            if ($tool === null) {
                continue;
            }

            try {
                $extracted = $this->entityExtractor->extractForFocus($focus, $message);
                $missing = $this->entityExtractor->findMissing($focus, $extracted, $llmEmittedFieldsList);
            } catch (\Throwable $e) {
                Log::warning('[AiChatController] Gap-fill extraction failed', [
                    'user_id' => $user->id,
                    'focus' => $focus,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($missing === []) {
                continue;
            }

            Log::info('[AiChatController] Gap-fill firing for dropped entities', [
                'user_id' => $user->id,
                'focus' => $focus,
                'llm_emitted' => count($llmEmittedFieldsList),
                'extractor_found' => count($extracted),
                'gap_filling' => count($missing),
            ]);

            foreach ($missing as $input) {
                yield [
                    'type' => 'tool_use',
                    'tool' => $tool,
                    'status' => 'running',
                ];

                try {
                    $result = $this->coordinatingAgent->executeTool($tool, $input, $user);
                } catch (\Throwable $e) {
                    Log::error('[AiChatController] Gap-fill tool execution failed', [
                        'user_id' => $user->id,
                        'tool' => $tool,
                        'input' => $input,
                        'error' => $e->getMessage(),
                    ]);

                    yield [
                        'type' => 'tool_use',
                        'tool' => $tool,
                        'status' => 'complete',
                    ];

                    continue;
                }

                if (! empty($result['error']) || ! empty($result['blocked'])) {
                    yield [
                        'type' => 'tool_use',
                        'tool' => $tool,
                        'status' => 'complete',
                    ];

                    continue;
                }

                if (($result['action'] ?? null) === 'fill_form') {
                    yield [
                        'type' => 'fill_form',
                        'entity_type' => $result['entity_type'] ?? '',
                        'route' => $result['route'] ?? '',
                        'fields' => $result['fields'] ?? [],
                        'mode' => $result['mode'] ?? 'create',
                        'entity_id' => $result['entity_id'] ?? null,
                    ];
                }

                yield [
                    'type' => 'tool_use',
                    'tool' => $tool,
                    'status' => 'complete',
                ];
            }
        }
    }

    private function focusFromEntityType(string $entityType): ?string
    {
        return match ($entityType) {
            'protection_policy', 'life_insurance_policy', 'critical_illness_policy', 'income_protection_policy' => 'protection',
            'savings_account', 'cash_account' => 'savings',
            'pension', 'dc_pension', 'db_pension' => 'retirement',
            'investment_account', 'holding' => 'investment',
            default => null,
        };
    }
}
