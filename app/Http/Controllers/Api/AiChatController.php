<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Agents\CoordinatingAgent;
use App\Enums\AiMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AI\SendAiChatMessageRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\AiConversation;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\AI\AdviceFyn;
use App\Services\AI\Loop\ConcurrentTurnQueue;
use App\Services\AI\Loop\ResumptionService;
use App\Services\Eval\EvalTraceCollector;
use App\Services\Gamification\LevelService;
use App\Services\Gamification\LevelUpCollector;
use App\Services\GDPR\ConsentService;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        private readonly ConcurrentTurnQueue $queue,
        private readonly ResumptionService $resumption,
    ) {}

    /**
     * List user's conversations.
     *
     * GET /api/ai-chat/conversations
     */
    public function index(Request $request): JsonResponse
    {
        // FR-M10 — paused (idle) conversations stay in history so the user can
        // return to them; sending reopens them. Only soft-deleted / archived
        // conversations drop out.
        $conversations = AiConversation::forUser($request->user()->id)
            ->whereIn('status', ['active', 'paused'])
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
     * Build the terminal `level_up` SSE frame from the request-scoped collector,
     * or null if no level-up occurred this turn.
     */
    public static function levelUpFrame(
        LevelUpCollector $collector,
        LevelService $levels,
        User $user,
    ): ?array {
        if (! $collector->hasLevelUp()) {
            return null;
        }
        $top = $collector->highest();

        return [
            'type' => 'level_up',
            'level' => $top['level'],
            'level_name' => $top['level_name'],
            'next_actions' => $levels->nextActions($user),
        ];
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
    public function sendMessage(SendAiChatMessageRequest $request, int $id): StreamedResponse|JsonResponse
    {
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

        // FR-M10 — sending reopens a paused (idle) conversation.
        if ($conversation->status === 'paused') {
            $conversation->update(['status' => 'active']);
        }

        $message = $request->input('message');
        $currentRoute = $request->input('current_route');

        // CoALA Phase 5 item 6 (FR-M7) — concurrent-turn queue. A per-conversation
        // lock marks the in-flight turn. If one is already streaming, this turn is
        // queued (or rejected past the depth cap) instead of opening a second
        // stream; the frontend renders it greyed and streams it once the in-flight
        // turn finishes (frontend-driven transport). The 5-minute lock TTL
        // auto-clears if a stream dies without releasing, so a conversation can
        // never wedge permanently.
        $inflightLock = Cache::lock('fyn:inflight:'.$conversation->id, 300);

        if (! $inflightLock->get()) {
            if ($this->queue->isFull($conversation)) {
                return response()->json([
                    'error' => 'queue_full',
                    'message' => 'You have a few messages already waiting — let Fyn answer those first.',
                ], 429);
            }

            $queued = $this->queue->enqueue($conversation, $message);

            return response()->json([
                'status' => 'queued',
                'message_id' => $queued->id,
                'queue_position' => $this->queue->queuedDepth($conversation),
            ], 202);
        }

        // Two-way dispatch after the Sprint 0 two-Fyn collapse:
        //   1. Onboarding (mid-flow users with a saved step) → OnboardingChatDirector
        //      — the deterministic state machine (handleInlineCapture handles
        //      any mid-flow capture intent).
        //   2. Otherwise (post-onboarding OR paused via the welcome-back
        //      "Something else" handoff which nulls onboarding_fyn_step) →
        //      AdviceFyn — read-only tools only. Write intents surface from
        //      onboarding, not from chat.
        //   Campaign re-entry (map §4, canonical contract amendment): a completed
        //   user with an active_campaign set is also routed to OnboardingChatDirector
        //   — the one write state — so they walk the campaign funnel. The
        //   onboarding_completed flag is never modified by re-entry; re-entry is
        //   signalled purely by active_campaign being non-null. A null
        //   onboarding_fyn_step (paused mid-campaign) falls back to advice so a
        //   paused user can still get answers without their step being lost.
        //   The predicate is centralised in routesToOnboardingDirector() and
        //   shared by streamQueuedMessage and action to keep all three in sync.
        $inOnboarding = $this->routesToOnboardingDirector($user);

        return new StreamedResponse(function () use ($user, $conversation, $message, $currentRoute, $inOnboarding, $inflightLock) {
            try {
                $generator = $inOnboarding
                    ? $this->onboardingDirector->handleUserMessage($user, $conversation, $message, $currentRoute)
                    : $this->adviceFyn->handle($user, $conversation, $message, $currentRoute);

                // W1-L (REVIEW §4 High #23). The S0.9 contract requires the
                // stream to terminate if the user withdraws ai_chat consent
                // mid-flight (PUT /api/user/consents → consented=false). The
                // pre-W1-L implementation queried `hasConsent` on every SSE
                // event, which for token-streamed responses is ~50–100 queries
                // per second per stream.
                //
                // We bound that to at most one query per
                // `consent_recheck_interval_seconds` (default 2.0). The
                // entry-point gate at line 149 already confirmed consent for
                // this request, so we start the in-stream loop with
                // `$consentValid = true` and only re-query after the interval
                // elapses. Withdrawal latency is bounded by the interval —
                // 2s is well within "without undue delay" (ICO guidance for
                // GDPR Art. 7(3)) and imperceptible against the user gesture
                // of toggling consent in another tab. Tests override via
                // `config()->set('ai_chat.consent_recheck_interval_seconds', 0)`
                // to assert the strict per-event behaviour.
                $consentRecheckInterval = (float) config('ai_chat.consent_recheck_interval_seconds', 2.0);
                $lastConsentCheckAt = microtime(true);
                $consentValid = true;

                foreach ($generator as $event) {
                    $now = microtime(true);
                    if ($now - $lastConsentCheckAt >= $consentRecheckInterval) {
                        $consentValid = $this->consentService->hasConsent($user, UserConsent::TYPE_AI_CHAT);
                        $lastConsentCheckAt = $now;
                    }

                    if (! $consentValid) {
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

                $frame = self::levelUpFrame(
                    app(LevelUpCollector::class),
                    app(LevelService::class),
                    $user,
                );
                if ($frame !== null) {
                    echo 'data: '.json_encode($frame)."\n\n";
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

                // FR-M9 — a fatal mid-stream error leaves the turn unfinished;
                // record a resumption so the next session can offer to pick it up.
                $this->resumption->flag(
                    $conversation,
                    'fatal_error',
                    'We hit a snag while answering last time — want to pick up where we left off?',
                );
            } finally {
                // Eval trace hand-off (P0.1). The bypass-preview-mode token
                // gate has already filtered out non-eval traffic via
                // EvalTraceListener::shouldCapture, so the collector is
                // empty for real-user traffic and persistForConversation
                // becomes a no-op. We persist unconditionally to keep the
                // controller insulated from token-shape details — see the
                // listener for the security gate.
                app(EvalTraceCollector::class)->persistForConversation($conversation->id);

                // FR-M7 — release the in-flight lock so a queued (or next) turn
                // for this conversation can stream. The frontend, on `done`,
                // streams the next queued turn (frontend-driven transport).
                $inflightLock->release();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Stream a turn that was queued behind an in-flight one (FR-M7).
     *
     * POST /api/ai-chat/conversations/{id}/messages/{messageId}/stream
     *
     * The frontend calls this on the previous stream's `done` event
     * (frontend-driven transport). The queued ai_messages row already holds the
     * user's turn, so the dispatch runs with persistUserMessage:false to avoid a
     * duplicate row; the row transitions queued → processing → answered, keeping
     * a stable message id for the frontend bubble.
     *
     * Mirrors sendMessage's stream contract (consent gate, in-flight lock,
     * consent re-check loop, eval-trace hand-off) for the queued turn — keep the
     * two in sync.
     */
    public function streamQueuedMessage(Request $request, int $id, int $messageId): StreamedResponse|JsonResponse
    {
        $user = $request->user();

        if (! $this->consentService->hasConsent($user, UserConsent::TYPE_AI_CHAT)) {
            return response()->json([
                'error' => 'consent_required',
                'required' => 'ai_chat',
            ], 403);
        }

        $conversation = AiConversation::forUser($user->id)->findOrFail($id);

        $queued = $conversation->messages()
            ->where('id', $messageId)
            ->where('status', AiMessageStatus::Queued->value)
            ->first();

        if ($queued === null) {
            return response()->json([
                'error' => 'not_queued',
                'message' => 'That message is no longer waiting to be answered.',
            ], 409);
        }

        $inflightLock = Cache::lock('fyn:inflight:'.$conversation->id, 300);
        if (! $inflightLock->get()) {
            return response()->json([
                'error' => 'busy',
                'message' => 'Another turn is still in progress — try again in a moment.',
            ], 409);
        }

        // Pop the queued turn to processing; release the lock if anything below
        // the stream open fails.
        $queued->update(['status' => AiMessageStatus::Processing]);

        $message = $queued->content;
        $currentRoute = $request->input('current_route');
        $inOnboarding = $this->routesToOnboardingDirector($user);

        return new StreamedResponse(function () use ($user, $conversation, $message, $currentRoute, $inOnboarding, $inflightLock, $queued) {
            try {
                // persistUserMessage:false — the queued row IS the user turn.
                $generator = $inOnboarding
                    ? $this->onboardingDirector->handleUserMessage($user, $conversation, $message, $currentRoute, false)
                    : $this->adviceFyn->handle($user, $conversation, $message, $currentRoute, false);

                $consentRecheckInterval = (float) config('ai_chat.consent_recheck_interval_seconds', 2.0);
                $lastConsentCheckAt = microtime(true);
                $consentValid = true;

                foreach ($generator as $event) {
                    $now = microtime(true);
                    if ($now - $lastConsentCheckAt >= $consentRecheckInterval) {
                        $consentValid = $this->consentService->hasConsent($user, UserConsent::TYPE_AI_CHAT);
                        $lastConsentCheckAt = $now;
                    }

                    if (! $consentValid) {
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

                $frame = self::levelUpFrame(
                    app(LevelUpCollector::class),
                    app(LevelService::class),
                    $user,
                );
                if ($frame !== null) {
                    echo 'data: '.json_encode($frame)."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } catch (\Exception $e) {
                Log::error('[AiChatController] Queued-turn streaming error', [
                    'user_id' => $user->id,
                    'conversation_id' => $conversation->id,
                    'message_id' => $queued->id,
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
            } finally {
                app(EvalTraceCollector::class)->persistForConversation($conversation->id);

                // The queued turn has been answered; release the in-flight lock
                // so the frontend can stream the next queued turn (if any).
                $this->queue->completeTurn($queued);
                $inflightLock->release();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Cancel a turn that is still queued (FR-M7 scenario 4).
     *
     * DELETE /api/ai-chat/conversations/{id}/messages/{messageId}
     *
     * Only a still-`queued` turn can be cancelled — once it is `processing`
     * (or terminal) the request is a no-op (409). The queue pop skips cancelled
     * rows, so the turn is simply never answered.
     */
    public function cancelQueuedMessage(Request $request, int $id, int $messageId): JsonResponse
    {
        $user = $request->user();
        $conversation = AiConversation::forUser($user->id)->findOrFail($id);

        $turn = $conversation->messages()->where('id', $messageId)->first();

        if ($turn === null || ! $this->queue->cancel($turn)) {
            return response()->json([
                'error' => 'not_cancellable',
                'message' => 'That message is no longer waiting and cannot be cancelled.',
            ], 409);
        }

        return response()->json([
            'status' => 'cancelled',
            'message_id' => $turn->id,
        ]);
    }

    /**
     * Resumption check (FR-M9). The user's most recent conversation that ended
     * without finishing, if any — surfaced on chat open as "last time we didn't
     * finish — continue, or start fresh?".
     *
     * GET /api/ai-chat/resumption
     */
    public function getResumption(Request $request): JsonResponse
    {
        $conversation = $this->resumption->latestForUser($request->user()->id);

        if ($conversation === null) {
            return response()->json(['success' => true, 'data' => ['has_pending' => false]]);
        }

        $pending = $this->resumption->pending($conversation) ?? [];

        return response()->json([
            'success' => true,
            'data' => [
                'has_pending' => true,
                'conversation_id' => $conversation->id,
                'reason' => $pending['reason'] ?? null,
                'message' => $pending['message'] ?? null,
            ],
        ]);
    }

    /**
     * Acknowledge a resumption (continue OR start fresh both clear the flag).
     *
     * DELETE /api/ai-chat/conversations/{id}/resumption
     */
    public function clearResumption(Request $request, int $id): JsonResponse
    {
        $conversation = AiConversation::forUser($request->user()->id)->findOrFail($id);
        $this->resumption->clear($conversation);

        return response()->json(['success' => true]);
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

        // Resolve the re-entry campaign before the completed check so a completed
        // user arriving via a reentry-enabled campaign can bypass the 409 gate
        // and start a fresh campaign session (or resume a mid-campaign one).
        $from = $request->input('from');
        $campaignMap = (array) config('onboarding.campaign_map', []);
        $reentryCampaign = is_string($from)
            && isset($campaignMap[$from])
            && ($campaignMap[$from]['reentry'] ?? false)
            ? $campaignMap[$from] : null;

        // Pause-resume (audit flags S2/P4): "Something else" parks the walk in
        // onboarding_fyn_context.paused_at_step and nulls the step, preserving
        // path + selection. A later /start resumes that campaign at the parked
        // step — with or without a from= param — instead of restarting at the
        // entry state, or 409ing a completed re-entrant whose pause cleared
        // active_campaign.
        $pausedContext = is_array($user->onboarding_fyn_context) ? $user->onboarding_fyn_context : [];
        $pausedStep = $pausedContext['paused_at_step'] ?? null;
        $pausedSelection = $user->onboarding_fyn_selection;
        $pausedCampaign = null;
        if (is_string($pausedStep)
            && $user->onboarding_fyn_path === 'campaign'
            && is_string($pausedSelection)
            && isset($campaignMap[$pausedSelection])
            && OnboardingStateMachine::getState($pausedStep) !== null) {
            $pausedCampaign = $campaignMap[$pausedSelection];
        }

        // A completed user resuming their own paused re-entry campaign bypasses
        // the 409 exactly like an explicit from=<campaign> re-entry.
        if ($reentryCampaign === null && $pausedCampaign !== null && ($pausedCampaign['reentry'] ?? false)) {
            $reentryCampaign = $pausedCampaign;
        }

        // A completed user already MID-campaign (active_campaign + step set —
        // e.g. the /m dashboard re-probing /start on a reload) must reach the
        // resume branch below, not the 409: without this the surface falls to
        // a generic greeting while the walk silently waits.
        if ($reentryCampaign === null
            && $user->active_campaign !== null
            && $user->onboarding_fyn_step !== null
            && isset($campaignMap[$user->active_campaign])) {
            $reentryCampaign = $campaignMap[$user->active_campaign];
        }

        // 409 only when the user is completed AND no reentry-enabled campaign matched.
        // Completed users with a valid reentry campaign fall through to the resume
        // branch (mid-campaign, step non-null) or the fresh re-entry path below.
        if ($user->onboarding_completed === true && $reentryCampaign === null) {
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
        // Pivot on metadata.source via the `onboarding` scope so the lookup
        // survives the first user message — the title is mutable; the
        // metadata flag set at creation is the immutable identifier.
        if ($user->onboarding_fyn_step !== null) {
            $existing = AiConversation::forUser($user->id)
                ->onboarding()
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

        // INV-2.2.5 — entry-source dispatch. When the request carries a
        // `from` value (landing-page CTA, lifecycle email, deep link) it is
        // looked up in two config maps in priority order:
        //
        //   1. config('onboarding.campaign_map') — marketing campaigns.
        //      Match → onboarding_fyn_path='campaign', selection=<id>, lands
        //      the user at STATE_BASE_PERSONAL with a campaign-specific
        //      welcome (see OnboardingStateMachine::buildPersonalPrompt).
        //   2. config('onboarding.journey_map') — life-stage journeys.
        //      Match → onboarding_fyn_path='journey', selection=<id>, lands
        //      at STATE_BASE_PERSONAL.
        //
        // Campaign check happens FIRST so a future campaign id never gets
        // misclassified as a life-stage journey. Unknown / missing `from`
        // falls through to STATE_PATH_CHOICE. Adding a new entry source
        // requires only a config change — no controller change.
        //
        // $from and $campaignMap are already resolved above the completed-user
        // gate (re-entry gate reads them to derive $reentryCampaign).
        $journeyMap = (array) config('onboarding.journey_map', []);
        $campaignEntry = is_string($from) && isset($campaignMap[$from]) ? $campaignMap[$from] : null;
        $matchedCampaign = is_array($campaignEntry) ? ($campaignEntry['selection'] ?? null) : null;
        $matchedJourney = is_string($from) && $matchedCampaign === null && isset($journeyMap[$from]) ? $journeyMap[$from] : null;

        // Paused-campaign fallback: resolves BEFORE the funnel fallback so a
        // paused pensioncheck re-entrant is not misrouted to their original
        // (savetax) funnel campaign on a bare /start.
        if ($matchedCampaign === null && $matchedJourney === null && $pausedCampaign !== null) {
            $campaignEntry = $pausedCampaign;
            $matchedCampaign = $pausedCampaign['selection'] ?? null;
        }

        // Funnel fallback: a user who arrived via an acquisition funnel carries
        // durable funnel_answers. The transient `from=<campaign>` query is lost
        // across the mobile handoff (the iframe is replaced with /m/app), so
        // key the campaign off funnel_answers['campaign'] instead — both mobile
        // and desktop funnel users then get the right campaign onboarding.
        // Legacy rows that predate the stamp default to 'savetax'.
        if ($matchedCampaign === null && $matchedJourney === null && ! empty($user->funnel_answers)) {
            $rawCampaign = $user->funnel_answers['campaign'] ?? null;
            $funnelCampaign = is_string($rawCampaign) ? $rawCampaign : 'savetax';
            $campaignEntry = $campaignMap[$funnelCampaign] ?? null;
            $matchedCampaign = is_array($campaignEntry) ? ($campaignEntry['selection'] ?? null) : null;
        }

        if ($matchedCampaign !== null) {
            $user->onboarding_fyn_path = 'campaign';
            $user->onboarding_fyn_selection = $matchedCampaign;
            // Campaign entry state: completed users re-entering a campaign land at
            // reentry_entry (if the map carries one) rather than the plain entry —
            // e.g. pensioncheck re-entrants open at campaign2_existing_recap so Fyn
            // recaps their known data first. Fresh (incomplete) users always use entry
            // even when the campaign is reentry-enabled.
            $stepId = ($reentryCampaign !== null && $user->onboarding_completed === true)
                ? ($reentryCampaign['reentry_entry'] ?? $campaignEntry['entry'])
                : $campaignEntry['entry'];
            // Pause-resume: same campaign as the parked one → resume at the
            // parked step (and consume the pointer) instead of the entry state.
            if ($pausedCampaign !== null
                && $matchedCampaign === ($pausedCampaign['selection'] ?? null)
                && is_string($pausedStep)) {
                $stepId = $pausedStep;
                unset($pausedContext['paused_at_step']);
                $user->onboarding_fyn_context = $pausedContext === [] ? null : $pausedContext;
            }
            $user->onboarding_fyn_step = $stepId;
            $startStateId = $stepId;
            // Re-entry: stamp active_campaign so routesToOnboardingDirector routes
            // subsequent messages from this completed user to the director while
            // the campaign session is in progress.
            if ($reentryCampaign !== null) {
                $user->active_campaign = $matchedCampaign;
            }
        } elseif ($matchedJourney !== null) {
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

        // Conversation: a paused-campaign resume continues in the existing
        // onboarding conversation so the transcript carries on (never a fresh
        // box); everything else creates a new one. Re-entry sessions carry an
        // additional metadata.campaign key so the onboarding scope query and
        // post-session tooling can distinguish them from first-time flows.
        $conversation = null;
        if ($pausedCampaign !== null && $matchedCampaign === ($pausedCampaign['selection'] ?? null)) {
            $conversation = AiConversation::forUser($user->id)->onboarding()->latest('id')->first();
        }
        if ($conversation === null) {
            $conversationMetadata = ['source' => 'fyn_onboarding'];
            if ($reentryCampaign !== null) {
                $conversationMetadata['campaign'] = $reentryCampaign['selection'];
            }
            $conversation = AiConversation::create([
                'user_id' => $user->id,
                'status' => 'active',
                'model_used' => 'director',
                'title' => 'Onboarding',
                'metadata' => $conversationMetadata,
            ]);
        }

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

        $inOnboarding = $this->routesToOnboardingDirector($user);

        return new StreamedResponse(function () use ($user, $conversation, $action, $inOnboarding) {
            try {
                $generator = $inOnboarding
                    ? $this->onboardingDirector->handleAction($user, $conversation, $action)
                    : (function () {
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

                $frame = self::levelUpFrame(
                    app(LevelUpCollector::class),
                    app(LevelService::class),
                    $user,
                );
                if ($frame !== null) {
                    echo 'data: '.json_encode($frame)."\n\n";
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
     * The Fyn dispatch predicate: does this user's message belong to the
     * onboarding director (the one write state)? True mid-onboarding, and
     * during campaign re-entry (active_campaign set by onboarding/start;
     * cleared at campaign terminal and on the "Something else" pause).
     * Canonical contract: April/April24Updates/spec/00-canonical.md.
     *
     * Used by sendMessage, streamQueuedMessage, and action — all three seams
     * must honour the same predicate, so a single helper replaces the old
     * sync-by-comment pattern.
     */
    private function routesToOnboardingDirector(User $user): bool
    {
        return ($user->onboarding_completed === false || $user->active_campaign !== null)
            && $user->onboarding_fyn_step !== null
            && (bool) config('onboarding.fyn_flow_enabled', true);
    }
}
