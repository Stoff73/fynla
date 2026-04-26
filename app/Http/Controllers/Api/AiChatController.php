<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Agents\CoordinatingAgent;
use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\AiConversation;
use App\Models\UserConsent;
use App\Services\AI\AdviceFyn;
use App\Services\GDPR\ConsentService;
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
        private readonly AdviceFyn $adviceFyn,
        private readonly ConsentService $consentService,
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
    public function sendMessage(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'current_route' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        // S0.9 — runtime consent gate (INV-2.10.3). Block at entry so the
        // SSE stream never opens for users without ai_chat consent.
        if (! $this->consentService->hasConsent($user, UserConsent::TYPE_AI_CHAT)) {
            return response()->json([
                'error' => 'consent_required',
                'required' => 'ai_chat',
            ], 403);
        }

        $conversation = AiConversation::forUser($user->id)->findOrFail($id);

        $message = $request->input('message');
        $currentRoute = $request->input('current_route');

        // Two-way dispatch after the Sprint 0 two-Fyn collapse:
        //   1. Onboarding (mid-flow users with a saved step) → OnboardingChatDirector
        //      — the deterministic state machine (handleInlineCapture handles
        //      any mid-flow capture intent).
        //   2. Otherwise (post-onboarding OR paused via the welcome-back
        //      "Something else" handoff which nulls onboarding_fyn_step) →
        //      AdviceFyn — read-only tools only. Write intents surface from
        //      onboarding, not from chat. Matches the /action endpoint check
        //      at AiChatController::postAction so a paused user does not
        //      silently no-op when they ask a free-text question.
        $inOnboarding = $user->onboarding_completed === false
            && $user->onboarding_fyn_step !== null
            && (bool) config('onboarding.fyn_flow_enabled', true);

        return new StreamedResponse(function () use ($user, $conversation, $message, $currentRoute, $inOnboarding) {
            try {
                $generator = $inOnboarding
                    ? $this->onboardingDirector->handleUserMessage($user, $conversation, $message, $currentRoute)
                    : $this->adviceFyn->handle($user, $conversation, $message, $currentRoute);

                foreach ($generator as $event) {
                    // S0.9 — mid-stream consent re-check. If the user
                    // withdraws ai_chat consent while the stream is in
                    // flight (PUT /api/user/consents → consented=false),
                    // emit a terminal consent_required event and close
                    // the stream. hasConsent() runs a fresh indexed
                    // EXISTS query so it picks up the withdrawal even
                    // though $user is closed-over.
                    if (! $this->consentService->hasConsent($user, UserConsent::TYPE_AI_CHAT)) {
                        echo 'data: '.json_encode([
                            'type' => 'consent_required',
                            'required' => 'ai_chat',
                        ])."\n\n";

                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();

                        return;
                    }

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

        // S0.9 — runtime consent gate (INV-2.10.3). Same shape as the
        // sendMessage guard so the frontend can hand both 403s to the
        // same modal trigger.
        if (! $this->consentService->hasConsent($user, UserConsent::TYPE_AI_CHAT)) {
            return response()->json([
                'error' => 'consent_required',
                'required' => 'ai_chat',
            ], 403);
        }

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

        // INV-2.2.5 — entry-source journey map. When the request carries a
        // `from` value (e.g. landing-page CTA, lifecycle email, deep link)
        // and the value matches a key in config('onboarding.journey_map'),
        // pre-select that life-stage journey and skip STATE_PATH_CHOICE +
        // STATE_JOURNEY_SELECTION so the user lands at base personal capture.
        // Unknown / missing `from` falls through to the default path_choice
        // bubbles. Adding a new entry source requires only a config change.
        $from = $request->input('from');
        $journeyMap = (array) config('onboarding.journey_map', []);
        $matchedJourney = is_string($from) && isset($journeyMap[$from]) ? $journeyMap[$from] : null;

        if ($matchedJourney !== null) {
            $user->onboarding_fyn_path = 'journey';
            $user->onboarding_fyn_selection = $matchedJourney;
            $user->onboarding_fyn_step = OnboardingStateMachine::STATE_BASE_PERSONAL;
            $startStateId = OnboardingStateMachine::STATE_BASE_PERSONAL;
        } else {
            $user->onboarding_fyn_step = OnboardingStateMachine::STATE_PATH_CHOICE;
            $startStateId = OnboardingStateMachine::STATE_PATH_CHOICE;
        }

        $user->onboarding_started_at = $user->onboarding_started_at ?? now();
        $user->save();

        return new StreamedResponse(function () use ($user, $conversation, $startStateId) {
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
                foreach ($this->onboardingDirector->emitFirstTurn($user, $conversation, $startStateId) as $event) {
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
     * Body: { action: 'resume' | 'continue' | 'restart' | 'skip' | 'something_else' }
     *
     * Actions are NOT persisted as AiMessage rows. Streams director
     * events back to the client via SSE. Covered by the existing
     * api/ai-chat/conversations/* prefix match in
     * PreviewWriteInterceptor::EXCLUDED_ROUTES.
     */
    public function action(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $request->validate([
            'action' => 'required|string|in:resume,continue,restart,skip,something_else',
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

}
