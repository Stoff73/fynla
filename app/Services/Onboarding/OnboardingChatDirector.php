<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Agents\CoordinatingAgent;
use App\Jobs\ConversationSummariserJob;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\ExpenditureProfile;
use App\Models\FamilyMember;
use App\Models\OnboardingProgress;
use App\Models\User;
use App\Services\AI\AiToolDefinitions;
use App\Services\AI\Fyn\FynPromptMode;
use App\Services\AI\Fyn\FynSystemPrompt;
use App\Services\AI\Loop\FynLoop;
use App\Services\AI\Memory\Episodic\ProceduralVersionHolder;
use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use App\Services\AI\MemoryRetrieverService;
use App\Services\AI\RecordDuplicateChecker;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\Gamification\PointsService;
use App\Services\Stores\InvestmentAccountStore;
use App\Services\Stores\PensionStore;
use App\Services\Stores\SavingsStore;
use App\ValueObjects\CaptureContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the Fyn-driven onboarding flow — the backend-authoritative
 * state machine described in fynOnboardFix.md §2 + §7.
 *
 * The director owns every turn except asset_capture. For asset_capture it
 * delegates to CoordinatingAgent::chat() with a restricted system prompt
 * (OnboardingPromptBuilder under FYN_PROMPT_ARCH=legacy; FynSystemPrompt
 * under =unified — see resolveUnifiedRestrictedPrompt) and the
 * focus-filtered create_* tool list.
 *
 * Control flow:
 *
 *   POST /api/ai-chat/onboarding/start
 *     ├─ create AiConversation
 *     ├─ set user.onboarding_fyn_step = 'path_choice'
 *     └─ emitFirstTurn() — yields SSE for the path_choice bubbles
 *
 *   POST /api/ai-chat/conversations/{id}/messages  (when in onboarding)
 *     └─ handleUserMessage() — matches bubble / parses free text,
 *        writes captured value, advances state, yields SSE for next turn.
 *
 * Every structured turn (bubbles + parsed free text) is deterministic —
 * no LLM call. Only asset_capture invokes Claude.
 */
final class OnboardingChatDirector
{
    /**
     * Hard cap on how many advice turns may auto-advance within a single
     * response. Advice turns chain with no user input between them, so a
     * state-table cycle would otherwise recurse without bound (see the
     * campaign_advice_spouse self-edge incident: 17,509 identical messages
     * persisted at ~41/sec before the worker died). Normal flows chain at most
     * one advice turn before hitting a capture state, so 6 is a generous
     * ceiling that only ever trips on a genuine cycle.
     */
    private const MAX_ADVICE_CHAIN = 6;

    public function __construct(
        private readonly CoordinatingAgent $coordinatingAgent,
        private readonly OnboardingPromptBuilder $promptBuilder,
        private readonly OnboardingFactExtractor $factExtractor,
        private readonly AssetCaptureEntityExtractor $entityExtractor,
        private readonly HouseholdProvisioner $householdProvisioner,
        private readonly MemoryRetrieverService $memory,
        private readonly RecordDuplicateChecker $duplicateChecker,
        private readonly FynLoop $fynLoop,
        private readonly ProceduralVersionHolder $proceduralVersions,
    ) {}

    /**
     * Backend-initiated turn 1 — emits the path_choice bubbles with no
     * preceding user message. Called from AiChatController::startOnboarding
     * after a fresh AiConversation row has been created and the user's
     * onboarding_fyn_step set to 'path_choice'.
     *
     * @return \Generator<array{type: string}>
     */
    public function emitFirstTurn(User $user, AiConversation $conversation, ?string $stateId = null): \Generator
    {
        $stateId = $stateId ?? OnboardingStateMachine::STATE_PATH_CHOICE;

        $state = OnboardingStateMachine::getState($stateId);
        if ($state === null) {
            yield $this->errorEvent('Onboarding is temporarily unavailable.');

            return;
        }

        yield from $this->emitTurnForState($user, $conversation, $stateId, $state);
    }

    /**
     * Handle a user message while the user is in onboarding mode.
     *
     * Contract matches CoordinatingAgent::chat() so AiChatController can
     * swap between the two based on user.onboarding_fyn_step.
     *
     * @return \Generator<array{type: string}>
     */
    public function handleUserMessage(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute = null,
        bool $persistUserMessage = true
    ): \Generator {
        // Persist the user message immediately so the conversation history
        // reflects the real interaction even if the rest of this generator
        // fails. Skipped when re-streaming an already-persisted queued turn
        // (FR-M7 concurrent-turn queue) so we don't duplicate the user row.
        if ($persistUserMessage) {
            $this->saveMessage($conversation, 'user', $message);
        }

        // Phase 11 — OnboardingFactExtractor runs speculatively on every
        // user message and parks structured facts into
        // ai_conversations.onboarding_parked_facts. Writes to users.* and
        // family_members remain the responsibility of the existing
        // grouped_extract tool handlers; parking is consulted downstream
        // by state handlers for gap-filling follow-ups and pause-state
        // confirmations. Extraction is best-effort — swallow any failure
        // rather than blocking the turn.
        try {
            $this->factExtractor->extractAndPark($conversation, $message);
        } catch (\Throwable $e) {
            Log::warning('[OnboardingChatDirector] Fact extractor failed', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }

        $currentStateId = $user->onboarding_fyn_step;
        if ($currentStateId === null) {
            // Shouldn't happen — controller delegation checks this. Fall
            // back to a safe terminal event.
            yield $this->errorEvent('Onboarding state lost. Please reload and try again.');

            return;
        }

        $state = OnboardingStateMachine::getState($currentStateId);
        if ($state === null) {
            yield $this->errorEvent('Unknown onboarding step. Please reload.');

            return;
        }

        // Income-challenge resolution (pending_income_challenge parked by
        // maybeChallengeIncome). The user is answering "is X right?" — handle
        // it before any normal turn routing.
        $context = is_array($user->onboarding_fyn_context) ? $user->onboarding_fyn_context : [];
        if (isset($context['pending_income_challenge'])) {
            $reply = mb_strtolower(trim($message));

            // Clear the flag on every branch — Continue always ends the loop.
            unset($context['pending_income_challenge']);
            $user->onboarding_fyn_context = $context;
            $user->save();

            if ($reply === 'continue') {
                yield from $this->advanceFromState($user, $conversation, $currentStateId, $message);

                return;
            }

            if ($reply === 'change') {
                yield from $this->emitTurnForState($user, $conversation, $currentStateId, $state);

                return;
            }
            // Anything else: the user typed a new figure instead of tapping —
            // fall through to the normal capture path below, which re-captures
            // and re-runs the challenge check.
        }

        // Phase 4e — stamp the active onboarding workflow procedure version onto
        // the turn so persistEpisode can bind it onto the episode. Recorded only
        // when the corpus actually supplies the workflow procedure (the merge
        // path transitionTable() takes); empty corpus → records nothing → null
        // stamp, matching the in-code-table fallback. Never breaks the turn.
        $this->recordActiveWorkflowVersion();

        // SaveTax verify edit (campaign_verify_edit) is a delegated turn, but it
        // must UPDATE the section's existing record/field — NOT run the asset
        // CAPTURE handler. handleAssetCaptureTurn keys off onboarding_fyn_selection
        // and runs the create-oriented gap-fill, so an edit there creates a
        // duplicate (multi-record sections) or no-ops (profile sections) while
        // Fyn falsely advances claiming "I've added that". Route it to a dedicated
        // update-only handler before the generic delegated branch below.
        if ($currentStateId === 'campaign_verify_edit') {
            yield from $this->handleCampaignVerifyEdit($user, $conversation, $message, $currentRoute, $currentStateId, $state);

            return;
        }

        // Asset capture is the delegated turn. Both the journey/focus
        // STATE_ASSET_CAPTURE and the SaveTax campaign STATE_CAMPAIGN_*
        // delegated states share the same handler — it advances via
        // state.next, which gives campaign users the linear walk through
        // OCCUPATIONAL → ISA → BANK → INVESTMENT → PENSION → SPOUSE_WORK
        // while keeping STATE_ASSET_CAPTURE → STATE_ADD_MORE unchanged.
        if (($state['turn_type'] ?? '') === 'delegated') {
            yield from $this->handleAssetCaptureTurn($user, $conversation, $message, $currentRoute, $currentStateId, $state);

            return;
        }

        // Grouped extraction turns (base_personal, base_spouse,
        // base_dependants_detail, base_work) delegate to Claude with a
        // narrow extraction tool, which writes the fields to the DB and
        // returns a capture receipt via SSE.
        //
        // Phase 11 Item 4 — before handing to the LLM, see if parking
        // already has everything the extraction tool would gather. If
        // it does we can apply the parked values synchronously, emit
        // the capture event the state handler expects, and advance —
        // saving an LLM round-trip whenever the user volunteered the
        // facts earlier in the conversation.
        if (($state['turn_type'] ?? '') === 'grouped_extract') {
            $hydrated = $this->hydrateFromParking($user, $conversation, $currentStateId, $state);
            if ($hydrated !== null) {
                yield from $hydrated;

                return;
            }

            yield from $this->handleGroupedExtractTurn($user, $conversation, $message, $currentRoute, $currentStateId, $state);

            return;
        }

        // Interpret the user answer against the current state.
        $interpretation = $this->interpretAnswer($state, $message);

        if (! $interpretation['ok']) {
            // Can't parse the answer — re-ask without advancing.
            yield [
                'type' => 'content',
                'text' => $interpretation['retry_text'] ?? "Sorry, I didn't catch that. Could you try again?",
            ];
            yield from $this->emitTurnForState($user, $conversation, $currentStateId, $state, includeTransitionHeader: false);

            return;
        }

        // Persist the captured value where applicable (profile column,
        // FamilyMember row, or scratch-pad context).
        $this->persistCapture($user, $state, $interpretation);

        // Bubble→tool wiring: when the state declares `bubble_capture`,
        // dispatch the named tool synchronously so its DB writes are
        // visible to the routing callable below. Without this, bubble
        // states with capture_field=null can't influence next-state
        // routing because nothing writes to the user model.
        $this->dispatchBubbleCapture($user, $conversation, $state, $interpretation);

        // INV-2.2.6 — flush the matching parked-facts bucket so the
        // <known_facts> block on the next prompt does not list the same
        // field twice (the values now live on users.* / family_members /
        // expenditure_profiles instead).
        $this->flushParkedFactsForState($conversation, $currentStateId);

        // Record the completed step in onboarding_progress for audit/resume.
        $this->recordProgress($user, $currentStateId, $interpretation['captured_value'] ?? null);

        // Emit a short acknowledgment so the UX doesn't feel abrupt when
        // the state machine jumps straight to the next prompt. One-sentence
        // acks per completed capture — addresses the terse-transition bug.
        $ack = $this->buildCaptureAck($user, $currentStateId, $interpretation);
        if ($ack !== null) {
            yield ['type' => 'content', 'text' => $ack];
        }

        // Decide the next state id (applies skip_if transitively).
        $nextStateId = OnboardingStateMachine::getNextStateId(
            $currentStateId,
            $interpretation['answer_for_transition'] ?? $message,
            $user->refresh()
        );

        if ($nextStateId === null) {
            yield $this->errorEvent('Onboarding state machine reached a dead end.');

            return;
        }

        // Advance the user's step pointer BEFORE emitting the next turn so
        // the frontend sees a consistent state if it races us.
        $user->onboarding_fyn_step = $nextStateId;

        // Terminal state: wrap up onboarding and navigate.
        if ($nextStateId === OnboardingStateMachine::STATE_DONE) {
            yield from $this->emitDoneTurn($user, $conversation);

            return;
        }

        $user->save();

        $nextState = OnboardingStateMachine::getState($nextStateId);
        if ($nextState === null) {
            yield $this->errorEvent('Unknown next state: '.$nextStateId);

            return;
        }

        yield [
            'type' => 'onboarding_advance',
            'from_step' => $currentStateId,
            'to_step' => $nextStateId,
        ];

        // Terminal-with-navigate states (e.g. STATE_CAMPAIGN_TERMINAL → /tax-strategy)
        // own their own route. Treat them like a done turn.
        if (($nextState['turn_type'] ?? '') === 'terminal' && ! empty($nextState['navigate_to'])) {
            yield from $this->emitTerminalNavigationTurn($user, $conversation, $nextStateId, $nextState);

            return;
        }

        yield from $this->emitTurnForState($user, $conversation, $nextStateId, $nextState);
    }

    /**
     * Handle a routed action from the new POST /action endpoint.
     * Replaces the old sentinel-string user-message path. Actions are NOT
     * persisted as AiMessage rows.
     *
     * Supported actions:
     *   - resume:         emit welcome-back greeting referencing the saved step
     *   - continue:       resume at the saved step (re-emit the current state)
     *   - restart:        hard-delete prior AiMessage rows, reset step to path_choice
     *   - skip:           advance past the current state (used for the spouse skip)
     *   - something_else: pause onboarding (preserving path/selection in
     *                     onboarding_fyn_context.paused_at_step) and hand the
     *                     user to free-text mode by emitting "What can I help
     *                     you with?" — subsequent messages route to AdviceFyn.
     *
     * @return \Generator<array{type: string}>
     */
    public function handleAction(User $user, AiConversation $conversation, string $action): \Generator
    {
        $currentStateId = $user->onboarding_fyn_step;

        switch ($action) {
            case 'resume':
                yield from $this->handleResumeAction($user, $conversation, $currentStateId);

                return;

            case 'something_else':
                yield from $this->handleSomethingElseAction($user, $conversation, $currentStateId);

                return;

            case 'continue':
                // Re-emit the current state so the user picks up where they left off.
                if ($currentStateId === null) {
                    yield $this->errorEvent('No onboarding step to continue from.');

                    return;
                }

                $state = OnboardingStateMachine::getState($currentStateId);
                if ($state === null) {
                    yield $this->errorEvent('Unknown onboarding step.');

                    return;
                }

                yield from $this->emitTurnForState($user, $conversation, $currentStateId, $state);

                return;

            case 'restart':
                yield from $this->handleRestartAction($user, $conversation);

                return;

            case 'skip':
                yield from $this->handleSkipAction($user, $conversation, $currentStateId);

                return;

            default:
                yield $this->errorEvent("Unknown action: {$action}");
        }
    }

    /**
     * Emit a welcome-back greeting referencing the saved step and surface
     * Continue / Something else action bubbles. Used by the resume flow on
     * conversation mount (Phase 12).
     */
    private function handleResumeAction(User $user, AiConversation $conversation, ?string $currentStateId): \Generator
    {
        if ($currentStateId === null) {
            yield $this->errorEvent('No onboarding in progress to resume.');

            return;
        }

        $firstName = trim((string) ($user->first_name ?? ''));
        if ($firstName === '') {
            $nameParts = explode(' ', (string) $user->name);
            $firstName = $nameParts[0] !== '' ? $nameParts[0] : 'there';
        }

        $stateLabel = $this->describeStep($currentStateId, $user);
        $greeting = "Welcome back, {$firstName}. Last time we were {$stateLabel}. Would you like to continue from where we left off, or is there something else I can help with?";

        // A resume greeting is a transient re-engagement prompt, not an
        // onboarding turn — only the latest should exist. The web resume flow
        // calls this on every chat open, so without pruning, prior greetings
        // pile up and the mobile resume (which renders the full transcript
        // verbatim via loadConversation) shows a repeated "Welcome back" on
        // startup. Remove any earlier ones before persisting this one.
        $conversation->messages()
            ->where('metadata->is_resume_greeting', true)
            ->delete();

        $message = $this->saveMessage($conversation, 'assistant', $greeting, [
            'metadata' => [
                'onboarding_step' => $currentStateId,
                'is_resume_greeting' => true,
            ],
        ]);

        yield [
            'type' => 'quick_replies',
            'prompt_text' => $greeting,
            'bubbles' => [
                ['id' => 'continue', 'label' => 'Continue'],
                ['id' => 'something_else', 'label' => 'Something else'],
            ],
            'action_bubbles' => true,
        ];

        yield ['type' => 'done', 'message_id' => $message->id];
    }

    /**
     * Hard-delete prior assistant messages in this conversation and reset
     * the user's onboarding_fyn_step to path_choice so they start fresh.
     */
    private function handleRestartAction(User $user, AiConversation $conversation): \Generator
    {
        $conversation->messages()->delete();

        $user->onboarding_fyn_step = OnboardingStateMachine::STATE_PATH_CHOICE;
        $user->onboarding_fyn_path = null;
        $user->onboarding_fyn_selection = null;
        $user->onboarding_fyn_context = null;
        $user->save();

        yield ['type' => 'content', 'text' => "No problem — let's start fresh."];

        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_PATH_CHOICE);
        if ($state !== null) {
            yield from $this->emitTurnForState($user, $conversation, OnboardingStateMachine::STATE_PATH_CHOICE, $state);
        }
    }

    /**
     * Pause onboarding without losing it. Stores the current step into
     * onboarding_fyn_context.paused_at_step and nulls onboarding_fyn_step
     * so AiChatController::sendMessage routes the user's next message to
     * AdviceFyn. Path + selection are preserved so the dashboard can offer a
     * Continue Onboarding CTA when the user is ready to come back.
     */
    private function handleSomethingElseAction(User $user, AiConversation $conversation, ?string $currentStateId): \Generator
    {
        if ($currentStateId !== null) {
            $context = is_array($user->onboarding_fyn_context) ? $user->onboarding_fyn_context : [];
            $context['paused_at_step'] = $currentStateId;
            $user->onboarding_fyn_context = $context;
            $user->onboarding_fyn_step = null;
            $user->save();
        }

        $prompt = 'Of course — what can I help you with?';
        $message = $this->saveMessage($conversation, 'assistant', $prompt, [
            'metadata' => [
                'paused_at_step' => $currentStateId,
                'is_pause_handoff' => true,
            ],
        ]);

        yield ['type' => 'content', 'text' => $prompt];
        yield ['type' => 'done', 'message_id' => $message->id];
    }

    /**
     * Skip the current state (primary use: the spouse block). Advances to
     * the state's configured next without persisting any captured value.
     * Phase 10 wires this to the actual state-machine skip_next hooks.
     */
    private function handleSkipAction(User $user, AiConversation $conversation, ?string $currentStateId): \Generator
    {
        if ($currentStateId === null) {
            yield $this->errorEvent('No onboarding step to skip.');

            return;
        }

        // Supported skip targets (Phase 10 extension point). For now, the
        // only sanctioned skip is from base_spouse to base_dependants.
        $skipTargets = [
            OnboardingStateMachine::STATE_BASE_SPOUSE => OnboardingStateMachine::STATE_BASE_DEPENDANTS,
        ];

        if (! isset($skipTargets[$currentStateId])) {
            yield $this->errorEvent('This step cannot be skipped.');

            return;
        }

        $nextStateId = $skipTargets[$currentStateId];

        $this->recordProgress($user, $currentStateId, ['skipped' => true]);

        $user->onboarding_fyn_step = $nextStateId;
        $user->save();

        yield [
            'type' => 'onboarding_advance',
            'from_step' => $currentStateId,
            'to_step' => $nextStateId,
            'skipped' => true,
        ];

        $nextState = OnboardingStateMachine::getState($nextStateId);
        if ($nextState !== null) {
            yield from $this->emitTurnForState($user, $conversation, $nextStateId, $nextState);
        }
    }

    /**
     * Human-readable label for a state id, used in the resume greeting.
     * Pass the user so we can interpolate name / selection where relevant.
     */
    private function describeStep(string $stateId, ?User $user = null): string
    {
        return match ($stateId) {
            OnboardingStateMachine::STATE_PATH_CHOICE => 'choosing how to get started',
            OnboardingStateMachine::STATE_JOURNEY_SELECTION => 'picking a life-stage journey',
            OnboardingStateMachine::STATE_FOCUS_SELECTION => 'picking a module to focus on first',
            OnboardingStateMachine::STATE_BASE_PERSONAL => 'capturing your date of birth and marital status',
            OnboardingStateMachine::STATE_BASE_SPOUSE => 'capturing your partner\'s details',
            OnboardingStateMachine::STATE_BASE_DEPENDANTS => 'noting whether you have dependants',
            OnboardingStateMachine::STATE_BASE_DEPENDANTS_DETAIL => 'noting your dependants',
            OnboardingStateMachine::STATE_BASE_EMPLOYMENT => 'noting your employment situation',
            OnboardingStateMachine::STATE_BASE_WORK => 'capturing your income',
            OnboardingStateMachine::STATE_BASE_RETIREMENT_DATE => 'noting when you retired',
            OnboardingStateMachine::STATE_BASE_EXPENDITURE => 'noting your monthly expenditure',
            OnboardingStateMachine::STATE_BASE_EMPLOYMENT_MORE => 'noting whether you have another role to add',
            OnboardingStateMachine::STATE_BASE_RETIREMENT_DATE => 'noting when you retired',
            OnboardingStateMachine::STATE_PROFILE_REVIEW_FAMILY => 'reviewing your family details',
            OnboardingStateMachine::STATE_PROFILE_REVIEW_EXPENDITURE => 'reviewing your full profile',
            OnboardingStateMachine::STATE_ASSET_CAPTURE => 'mapping your '.($user?->onboarding_fyn_selection ?? 'financial').' records',
            OnboardingStateMachine::STATE_ADD_MORE => 'choosing whether to add another module',
            default => 'mid-onboarding',
        };
    }

    /**
     * Return the onboarding status for the authenticated user. Used by the
     * frontend on chat open to decide whether to call /start or resume an
     * existing conversation.
     */
    public function getOnboardingStatus(User $user): array
    {
        if ($user->onboarding_completed) {
            return ['in_progress' => false];
        }

        $step = $user->onboarding_fyn_step;
        if ($step === null) {
            return ['in_progress' => false];
        }

        // Pivot on metadata.source via the `onboarding` scope — the title
        // legitimately changes as the conversation evolves, so the prior
        // `where('title','Onboarding')` filter started returning null after
        // the first user message and broke welcome-back resume on every
        // re-login past base_personal.
        $conversation = AiConversation::forUser($user->id)
            ->onboarding()
            ->latest('id')
            ->first();

        return [
            'in_progress' => true,
            'current_step' => $step,
            'path' => $user->onboarding_fyn_path,
            'selection' => $user->onboarding_fyn_selection,
            'conversation_id' => $conversation?->id,
        ];
    }

    // ─── Turn emission ────────────────────────────────────────────────────

    /**
     * Emit SSE events for the given state. Yields prompt_text (and bubbles
     * if applicable), persists a corresponding assistant AiMessage row so
     * the conversation history reflects Fyn's output, then yields a `done`
     * marker.
     *
     * Phase 10 — also emits `onboarding_layout_change` with the state's
     * declared layout ('wide' default, 'standard' for profile-review
     * pauses). Bubble states may include a `skip_link` metadata object
     * that the frontend renders as a raspberry-500 inline link calling
     * POST /api/ai-chat/conversations/{id}/action {action:'skip'}.
     */
    private function emitTurnForState(
        User $user,
        AiConversation $conversation,
        string $stateId,
        array $state,
        bool $includeTransitionHeader = true,
        int $adviceDepth = 0
    ): \Generator {
        $turnType = $state['turn_type'] ?? 'free_text';

        // Advice turns are read-only and auto-advancing: Fyn relays the relevant
        // tax-engine recommendation for the section just completed, then we
        // continue straight to the next section's prompt in the same response.
        if ($turnType === 'advice') {
            yield from $this->emitAdviceTurn($user, $conversation, $stateId, $state, $adviceDepth);

            return;
        }

        $promptText = OnboardingStateMachine::resolvePromptText($state, $user, '', $conversation);
        $layoutMode = (string) ($state['layout'] ?? 'wide');
        $skipLink = $state['skip_link'] ?? null;

        // Emit the layout mode up-front so the frontend can shrink / expand
        // the chat container and blur / un-blur the dashboard in one pass.
        yield [
            'type' => 'onboarding_layout_change',
            'mode' => $layoutMode,
        ];

        if ($turnType === 'bubbles') {
            $bubbles = $this->filterBubbles($user, $stateId, $state);

            $event = [
                'type' => 'quick_replies',
                'prompt_text' => $promptText,
                'bubbles' => $bubbles,
            ];
            if (is_array($skipLink) && ! empty($skipLink)) {
                $event['skip_link'] = $skipLink;
            }
            yield $event;

            $metadata = ['bubbles' => $bubbles, 'onboarding_step' => $stateId];
            if (is_array($skipLink) && ! empty($skipLink)) {
                $metadata['skip_link'] = $skipLink;
            }

            $assistantMessage = $this->saveMessage(
                $conversation,
                'assistant',
                $promptText,
                ['metadata' => $metadata]
            );

            // Verify-navigate: a bubbles state can carry a navigate_to (closure or
            // string). When it resolves to a route, emit a navigation event so the
            // /m chat minimises + routes to the section's screen while these bubbles
            // wait for the user to reopen. Null route = inline confirm (no nav).
            $navigateTo = $state['navigate_to'] ?? null;
            $route = is_callable($navigateTo) ? $navigateTo($user) : $navigateTo;
            if (is_string($route) && $route !== '') {
                $ctx = is_array($user->onboarding_fyn_context) ? $user->onboarding_fyn_context : [];
                yield [
                    'type' => 'navigation',
                    'route_path' => $route,
                    'description' => $stateId,
                    // The section being verified (income/spouse/…); the /m surface
                    // uses it to label the screen (e.g. income vs spouse income).
                    'section' => ((string) ($ctx['verify_section'] ?? '')) ?: null,
                ];
            }
        } else {
            // free_text / grouped_extract / terminal — content event(s). A
            // prompt may carry BUBBLE_BREAK markers to render as separate chat
            // bubbles (e.g. a "what we've heard" recap then the question). Each
            // part is its own saved message so the transcript re-renders the
            // same bubbles on resume; an onboarding_advance between parts makes
            // the /m chat open a fresh bubble. Single-part prompts behave
            // exactly as before (one content event, one saved message).
            $metadata = ['onboarding_step' => $stateId];
            if (is_array($skipLink) && ! empty($skipLink)) {
                $metadata['skip_link'] = $skipLink;
            }

            $parts = array_values(array_filter(
                array_map('trim', explode(OnboardingStateMachine::BUBBLE_BREAK, $promptText)),
                static fn (string $p): bool => $p !== ''
            ));
            if ($parts === []) {
                $parts = [$promptText];
            }

            $assistantMessage = null;
            foreach ($parts as $i => $part) {
                if ($i > 0) {
                    yield ['type' => 'onboarding_advance', 'from_step' => $stateId, 'to_step' => $stateId];
                }
                yield ['type' => 'content', 'text' => $part];
                $assistantMessage = $this->saveMessage(
                    $conversation,
                    'assistant',
                    $part,
                    ['metadata' => $metadata]
                );
            }

            // For grouped_extract states, the frontend needs the skip_link
            // (and any other action affordances) out-of-band — emit a
            // separate event so the UI can render it alongside the content.
            if (is_array($skipLink) && ! empty($skipLink)) {
                yield [
                    'type' => 'skip_link',
                    'skip_link' => $skipLink,
                ];
            }
        }

        yield [
            'type' => 'done',
            'message_id' => $assistantMessage->id,
        ];
    }

    /**
     * Emit a per-section advice turn: relay the relevant tax-engine
     * recommendation for the section just completed, then auto-advance to the
     * next section's prompt in the same SSE response (no user input required).
     */
    private function emitAdviceTurn(User $user, AiConversation $conversation, string $stateId, array $state, int $adviceDepth = 0): \Generator
    {
        $section = (string) ($state['advice_section'] ?? '');
        $text = $this->buildSectionAdvice($user, $section);

        if ($text !== null && $text !== '') {
            yield ['type' => 'content', 'text' => $text];
            $this->saveMessage($conversation, 'assistant', $text, [
                'metadata' => ['onboarding_step' => $stateId, 'advice_section' => $section],
            ]);
        }

        $nextStateId = OnboardingStateMachine::getNextStateId($stateId, '', $user->refresh());
        if ($nextStateId === null) {
            yield $this->errorEvent('Onboarding reached a dead end after advice.');

            return;
        }

        // Defense-in-depth against an advice auto-advance cycle. Advice turns
        // chain with no user input between them, so a state whose `next` resolves
        // back to itself (or a longer cycle) would recurse without bound,
        // persisting an identical message each pass until the worker dies. A
        // self-transition or an over-long chain is always a state-table bug —
        // log it loudly and complete onboarding gracefully instead of spinning.
        if ($nextStateId === $stateId || $adviceDepth + 1 > self::MAX_ADVICE_CHAIN) {
            Log::error('[OnboardingChatDirector] Advice auto-advance cycle detected — forcing completion', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'state' => $stateId,
                'next_state' => $nextStateId,
                'advice_depth' => $adviceDepth,
            ]);
            $user->onboarding_fyn_step = OnboardingStateMachine::STATE_DONE;
            $user->save();
            yield from $this->emitDoneTurn($user, $conversation);

            return;
        }

        $user->onboarding_fyn_step = $nextStateId;

        if ($nextStateId === OnboardingStateMachine::STATE_DONE) {
            yield from $this->emitDoneTurn($user, $conversation);

            return;
        }

        $user->save();

        $nextState = OnboardingStateMachine::getState($nextStateId);
        if ($nextState === null) {
            yield $this->errorEvent('Unknown next state after advice: '.$nextStateId);

            return;
        }

        yield ['type' => 'onboarding_advance', 'from_step' => $stateId, 'to_step' => $nextStateId];

        if (($nextState['turn_type'] ?? '') === 'terminal' && ! empty($nextState['navigate_to'])) {
            yield from $this->emitTerminalNavigationTurn($user, $conversation, $nextStateId, $nextState);

            return;
        }

        yield from $this->emitTurnForState($user, $conversation, $nextStateId, $nextState, true, $adviceDepth + 1);
    }

    /**
     * Strategy types voiced per campaign section (plan order wins within the
     * section — this map documents which types belong to each section, not
     * their relative priority). Max two strategies voiced per section; the
     * synthesis turn collects the rest.
     *
     * The 'giving' and 'expenditure' sections are not voiced here (null
     * returned) — gift_aid_higher_rate_relief requires a live charitable-giving
     * figure and is covered in the synthesis turn.
     */
    private const SECTION_STRATEGY_TYPES = [
        'income' => ['pa_taper_rescue', 'additional_rate_avoidance', 'tapered_annual_allowance'],
        'savings' => ['isa_topup_vs_psa', 'joint_savings_psa_split', 'lifetime_isa'],
        'investments' => ['bed_and_isa', 'dividend_allowance_harvest'],
        'pensions' => ['salary_sacrifice_ni', 'pension_aa_carry_forward'],
        'giving' => ['gift_aid_higher_rate_relief'],
        'spouse' => ['non_earner_spouse_pension', 'savings_to_spouse', 'isa_topup_spouse', 'marriage_allowance_transfer', 'gia_to_spouse', 'gia_rebalance', 'isa_coordination'],
    ];

    /**
     * Build the conversational advice for a completed section from the composed
     * tax plan catalogue. Numbers come from the engine (Rule #2); Fyn only
     * phrases them. Plan order is respected — the composer's sequencing and
     * conflict resolution are already applied. Returns null when there are no
     * applicable strategies for the section.
     */
    private function buildSectionAdvice(User $user, string $section): ?string
    {
        if ($section === 'synthesis') {
            return $this->buildSynthesisAdvice($user);
        }

        $wanted = self::SECTION_STRATEGY_TYPES[$section] ?? null;
        if ($wanted === null) {
            return null;
        }

        try {
            $plan = app(ComposedTaxPlanService::class)->forUser($user);
        } catch (\Throwable $e) {
            Log::warning('[OnboardingChatDirector] Section advice calculation failed', [
                'user_id' => $user->id,
                'section' => $section,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        // Spouse section: one short line + the saving figure (CSJ 2.8/10),
        // rather than voicing the individual strategy titles.
        if ($section === 'spouse') {
            return $this->buildSpouseAdvice($plan, $wanted);
        }

        $lines = [];
        foreach ($plan['items'] as $item) {
            if (! in_array($item['type'] ?? '', $wanted, true)) {
                continue;
            }
            // Tiered voicing: mechanical strategies stated directly; judgement
            // strategies hedged so Fyn does not over-promise uncertain outcomes.
            $prefix = ($item['claim_tier'] ?? 'judgement') === 'mechanical' ? '' : 'You may want to consider: ';
            $title = trim((string) ($item['title'] ?? ''));
            $desc = trim((string) ($item['description'] ?? ''));
            $lines[] = $prefix.$title.'.'.($desc !== '' ? ' '.$desc : '');
            if (count($lines) >= 2) {
                break;
            }
        }

        return $lines === [] ? null : implode("\n\n", $lines);
    }

    /**
     * Spouse-section advice (CSJ 2.8/10): a single short line plus a figure.
     * Primary figure is the combined annual tax saving of the spouse strategies
     * in the plan; when the engine has no quantified saving yet, fall back to
     * the spouse's headline unused allowances (Personal Allowance + ISA) from
     * TaxConfigService (Rule #2). Returns null when there is no spouse
     * opportunity at all, so Fyn never promises a saving that doesn't exist.
     *
     * @param  array<string,mixed>  $plan
     * @param  list<string>  $wanted
     */
    private function buildSpouseAdvice(array $plan, array $wanted): ?string
    {
        $hasSpouseStrategy = false;
        $saving = 0.0;
        foreach ($plan['items'] as $item) {
            if (in_array($item['type'] ?? '', $wanted, true)) {
                $hasSpouseStrategy = true;
                $saving += (float) ($item['estimated_annual_tax_saved'] ?? 0);
            }
        }

        if (! $hasSpouseStrategy) {
            return null;
        }

        if ($saving > 0) {
            $figure = sprintf(' — around £%s a year', number_format((int) round($saving)));
        } else {
            $tax = app(TaxConfigService::class);
            $allowance = (float) ($tax->getIncomeTax()['personal_allowance'] ?? 0)
                + (float) ($tax->getISAAllowances()['annual_allowance'] ?? 0);
            $figure = $allowance > 0
                ? sprintf(' — they have around £%s of unused allowances', number_format((int) round($allowance)))
                : '';
        }

        return "You can definitely save money with your spouse's allowances{$figure}. "
            ."We've added this to your actions list which we'll take you to shortly.";
    }

    /**
     * A4 — the consolidated plan voiced at the end of the savetax flow:
     * every eligible strategy in composer order (nothing dropped by section
     * caps), numbered, with conflict notes, a combined realisable total, at
     * most ONE locked-strategy tease, and the FCA signposting line. The
     * /tax-strategy page renders the same composed plan, so chat and page
     * cannot disagree.
     *
     * Conflict notes contain raw strategy-type tokens (e.g. "savings_to_spouse")
     * from the composer. We humanise them by swapping the token for the title of
     * the matching item when one exists, then fall back to space-replacing
     * underscores. This keeps the text user-friendly without duplicating the
     * composer's conflict-resolution logic.
     */
    private function buildSynthesisAdvice(User $user): ?string
    {
        // Recompute on the freshly written records (not the model loaded at the
        // start of the turn) so the plan voiced here is identical to the one
        // /tax-strategy renders next — chat and dashboard must never disagree.
        $user->refresh();

        try {
            $plan = app(ComposedTaxPlanService::class)->forUser($user);
        } catch (\Throwable $e) {
            Log::warning('[OnboardingChatDirector] Synthesis advice calculation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($plan['items'] === []) {
            return null;
        }

        // Mirror the /tax-strategy dashboard exactly. That page renders
        // composed_plan.items as "title — saves £X a year" (TaxStrategy.vue), and
        // shows NEITHER the locked strategies NOR conflict notes. We voice the same
        // items, in the same order, as markdown "- " bullets (the format every other
        // recap screen uses — see buildFunnelRecapPrompt), so the summary reflects
        // exactly what the user entered and matches the page they tap through to.
        $bullets = [];
        foreach ($plan['items'] as $item) {
            $title = trim((string) ($item['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $saving = $item['estimated_annual_tax_saved'] ?? null;
            $savingFormatted = is_numeric($saving) ? number_format((int) round((float) $saving)) : '';
            // Skip the suffix when the title already quotes the amount.
            $savingText = $savingFormatted !== '' && (float) $saving > 0
                && ! str_contains($title, '£'.$savingFormatted)
                ? sprintf(' — saves around £%s a year', $savingFormatted)
                : '';
            $bullets[] = sprintf('- %s%s', $title, $savingText);
        }

        if ($bullets === []) {
            return null;
        }

        // Blank line before the bullet block so markdown renders it as a list on
        // both surfaces (web AiMessageContent + /m renderFynText).
        $lines = ["Here's your tax plan, built from what you told me — in the order I'd tackle it:", ''];
        foreach ($bullets as $bullet) {
            $lines[] = $bullet;
        }

        $total = (float) ($plan['combined_annual_saving'] ?? 0.0);
        if ($total > 0) {
            $lines[] = '';
            $lines[] = sprintf('Together these are worth roughly £%s a year.', number_format((int) round($total)));
        }

        $lines[] = '';
        $lines[] = 'For regulated advice personal to your circumstances, speak to a qualified financial adviser.';

        return implode("\n", $lines);
    }

    /**
     * For the add_more state, strip focuses the user has already visited so
     * we don't offer the same option twice. The "I'm done" bubble is always
     * last. All other states return the static bubble config unchanged.
     *
     * Bubbles are {id, label} only — see the NO ICONS rule in CLAUDE.md §14.
     *
     * @return list<array{id: string, label: string}>
     */
    private function filterBubbles(User $user, string $stateId, array $state): array
    {
        $bubbles = $state['bubbles'] ?? [];

        if ($stateId !== OnboardingStateMachine::STATE_ADD_MORE) {
            return $bubbles;
        }

        $visited = (array) ($user->onboarding_fyn_context['visited_focuses'] ?? []);
        $currentSelection = $user->onboarding_fyn_selection;
        if ($currentSelection !== null && ! in_array($currentSelection, $visited, true)) {
            $visited[] = $currentSelection;
        }

        $filtered = [];
        foreach ($bubbles as $bubble) {
            $id = $bubble['id'] ?? '';
            if ($id === 'done') {
                continue; // append at the end
            }
            if (in_array($id, $visited, true)) {
                continue;
            }
            $filtered[] = $bubble;
        }

        // Always append the "I'm done" bubble
        $filtered[] = ['id' => 'done', 'label' => "I'm done"];

        return $filtered;
    }

    // ─── Answer interpretation ────────────────────────────────────────────

    /**
     * Parse the user's answer against the current state.
     *
     * Returns:
     *   ok:                      whether the answer is acceptable
     *   captured_value:          the value to persist (or null for scratch)
     *   answer_for_transition:   what to pass to nextFromX callables
     *   retry_text:              error text shown if ok=false
     *   scratch_context:         optional array to merge into onboarding_fyn_context
     *
     * @return array{ok: bool, captured_value?: mixed, answer_for_transition?: string, retry_text?: string, scratch_context?: array<string, mixed>}
     */
    private function interpretAnswer(array $state, string $message): array
    {
        $turnType = $state['turn_type'] ?? 'free_text';

        if ($turnType === 'bubbles') {
            // Find the matching bubble by label/id/substring.
            $bubbleId = OnboardingStateMachine::matchBubble(
                $this->resolveStateId($state),
                $message
            );
            if ($bubbleId === null) {
                return [
                    'ok' => false,
                    'retry_text' => "Sorry, I didn't catch that. Please pick one of the options above.",
                ];
            }

            // Some bubble states run the captured value through a parser
            // (e.g. parseMaritalFromText normalises label variants).
            $parser = $state['value_parser'] ?? null;
            $capturedValue = $bubbleId;
            if ($parser !== null && method_exists(OnboardingValueInterpreter::class, $parser)) {
                $parsed = OnboardingValueInterpreter::$parser($message);
                if ($parsed !== null) {
                    $capturedValue = $parsed;
                }
            }

            return [
                'ok' => true,
                'captured_value' => $capturedValue,
                'answer_for_transition' => $message,
            ];
        }

        // free_text
        $parser = $state['value_parser'] ?? null;
        if ($parser === null) {
            // No parser — store the raw trimmed string (e.g. occupation)
            $trimmed = trim($message);

            return [
                'ok' => $trimmed !== '',
                'captured_value' => $trimmed !== '' ? $trimmed : null,
                'answer_for_transition' => $message,
                'retry_text' => 'Please type a short answer and I will record it.',
            ];
        }

        if (! method_exists(OnboardingValueInterpreter::class, $parser)) {
            Log::warning('[OnboardingChatDirector] Unknown parser', ['parser' => $parser]);

            return [
                'ok' => false,
                'retry_text' => 'Something went wrong on my side. Please try again.',
            ];
        }

        $parsed = OnboardingValueInterpreter::$parser($message);
        if ($parsed === null) {
            return [
                'ok' => false,
                'retry_text' => $this->retryTextForParser($parser),
            ];
        }

        return [
            'ok' => true,
            'captured_value' => $parsed,
            'answer_for_transition' => $message,
        ];
    }

    private function retryTextForParser(string $parser): string
    {
        return match ($parser) {
            'parseDateOfBirth' => "Sorry, I didn't catch that as a date. Try something like '12 January 1985' or '12/01/1985'.",
            'parseRetirementDate' => "Sorry, I didn't catch that as a date. A year alone is fine — something like '2020'.",
            'parseIncomeAmount' => "Sorry, I didn't catch that as an amount. Try something like '£75,000' or '75k'.",
            'parseExpenditureAmount' => "Sorry, I didn't catch that as an amount. Try something like '£2,500' or '2.5k'.",
            default => "Sorry, I didn't catch that. Could you try again?",
        };
    }

    // ─── Persistence ──────────────────────────────────────────────────────

    /**
     * Dispatch a side-effect tool when a bubble state declares `bubble_capture`.
     *
     * State config shape:
     *   'bubble_capture' => [
     *       'tool' => 'capture_spouse_work_status',
     *       'input_for_bubble' => [
     *           'yes' => ['spouse_works' => true],
     *           'no'  => ['spouse_works' => false],
     *       ],
     *   ]
     *
     * Runs synchronously after persistCapture so the tool's DB writes are
     * visible to the routing callable in getNextStateId. Goes through
     * CoordinatingAgent::executeTool to keep preview gating + audit + xAI
     * parity intact. No-op when bubble_capture is absent or the bubble id
     * doesn't appear in input_for_bubble.
     */
    private function dispatchBubbleCapture(
        User $user,
        AiConversation $conversation,
        array $state,
        array $interpretation
    ): void {
        $config = $state['bubble_capture'] ?? null;
        if (! is_array($config)) {
            return;
        }

        $tool = $config['tool'] ?? null;
        $inputMap = $config['input_for_bubble'] ?? [];
        $bubbleId = $interpretation['captured_value'] ?? null;

        if (! is_string($tool) || ! is_string($bubbleId) || ! isset($inputMap[$bubbleId]) || ! is_array($inputMap[$bubbleId])) {
            return;
        }

        $this->coordinatingAgent->executeTool($tool, $inputMap[$bubbleId], $user, $conversation->id);
    }

    /**
     * Write the captured value to the right place:
     * - capture_field set → update users.$column directly
     * - base_spouse state → create FamilyMember row (free text parsed by director)
     * - base_dependants_detail → create FamilyMember rows for each dependant
     * - base_dependants → set onboarding_fyn_context.has_dependants
     * - path_choice / selection states → update the onboarding_fyn_* columns
     */
    private function persistCapture(User $user, array $state, array $interpretation): void
    {
        $stateId = $this->resolveStateId($state);
        $capturedValue = $interpretation['captured_value'] ?? null;
        $captureField = $state['capture_field'] ?? null;

        // Specialised handlers first
        if ($stateId === OnboardingStateMachine::STATE_BASE_SPOUSE) {
            $this->createSpouseFamilyMember($user, (string) $capturedValue);

            return;
        }

        if ($stateId === OnboardingStateMachine::STATE_BASE_DEPENDANTS_DETAIL) {
            $this->createDependantFamilyMembers($user, (string) $capturedValue);

            return;
        }

        if ($stateId === OnboardingStateMachine::STATE_BASE_DEPENDANTS) {
            $context = $user->onboarding_fyn_context ?? [];
            $context['has_dependants'] = ($capturedValue === 'yes');
            $user->onboarding_fyn_context = $context;
            $user->save();

            return;
        }

        // add_more: capture_field is null, but the bubble id drives the next
        // asset_capture turn. Without this branch the user's new focus pick
        // (e.g. "Savings" after completing the family step) is silently
        // dropped and asset_capture re-runs with the stale journey selection.
        if ($stateId === OnboardingStateMachine::STATE_ADD_MORE) {
            $bubbleId = $capturedValue;
            if (is_string($bubbleId) && $bubbleId !== '' && $bubbleId !== 'done') {
                $user->onboarding_fyn_selection = $bubbleId;

                $context = $user->onboarding_fyn_context ?? [];
                $visited = (array) ($context['visited_focuses'] ?? []);
                if (! in_array($bubbleId, $visited, true)) {
                    $visited[] = $bubbleId;
                }
                $context['visited_focuses'] = $visited;
                $user->onboarding_fyn_context = $context;
                $user->save();
            }

            return;
        }

        if ($captureField === null) {
            return;
        }

        // users.* writes including the onboarding_fyn_* columns
        $user->{$captureField} = $capturedValue;

        // Bookkeeping for base_employment → income routing
        if ($stateId === OnboardingStateMachine::STATE_BASE_EMPLOYMENT) {
            $user->{$captureField} = $capturedValue;
        }

        // Record the visited focus so filterBubbles skips it on add_more
        if ($stateId === OnboardingStateMachine::STATE_FOCUS_SELECTION
            || $stateId === OnboardingStateMachine::STATE_JOURNEY_SELECTION) {
            $context = $user->onboarding_fyn_context ?? [];
            $visited = (array) ($context['visited_focuses'] ?? []);
            if (! in_array($capturedValue, $visited, true)) {
                $visited[] = $capturedValue;
            }
            $context['visited_focuses'] = $visited;
            $user->onboarding_fyn_context = $context;
        }

        $user->save();

        // Mirror monthly_expenditure into the ExpenditureProfile row so the
        // dashboard and IHTCalculationService (which both read
        // total_monthly_expenditure off the profile) pick it up without the
        // user needing a post-onboarding "my expenses aren't showing" turn.
        // The user can still break it into categories later via the
        // expenditure form; this write only populates the total.
        if ($captureField === 'monthly_expenditure' && is_numeric($capturedValue) && (float) $capturedValue > 0) {
            ExpenditureProfile::updateOrCreate(
                ['user_id' => $user->id],
                ['total_monthly_expenditure' => (float) $capturedValue],
            );
        }
    }

    /**
     * Create a FamilyMember row with relationship='spouse' from the
     * user's free-text spouse description. The director does a
     * best-effort extraction of first_name, date_of_birth, and
     * annual_income — anything it can't parse goes into notes.
     *
     * If users.marital_status is 'civil_partnership' we still use
     * relationship='spouse' because family_members.relationship has no
     * dedicated partner value (see commit 95a1dd3).
     */
    private function createSpouseFamilyMember(User $user, string $rawText): void
    {
        // B-2 — create a Household and persist household_id on the user
        // BEFORE the FamilyMember insert so the spouse row inherits the
        // real id, not NULL.
        $householdId = $this->householdProvisioner->ensureFor($user);

        $firstName = $this->extractFirstName($rawText);
        $dob = $this->extractDate($rawText);
        $income = OnboardingValueInterpreter::parseIncomeAmount(
            $this->extractPotentialAmount($rawText)
        );

        FamilyMember::create([
            'user_id' => $user->id,
            'household_id' => $householdId,
            'relationship' => 'spouse',
            'first_name' => $firstName ?: 'Spouse',
            'date_of_birth' => $dob,
            'annual_income' => $income,
            'is_dependent' => false,
            'notes' => 'Added via Fyn onboarding. Raw: '.mb_substr($rawText, 0, 200),
        ]);
    }

    /**
     * Parse a "how many kids and what ages" free-text reply and create
     * FamilyMember rows. Deliberately forgiving — Claude could be used
     * for a richer parse in a follow-up iteration but for MVP the
     * director handles it deterministically.
     */
    private function createDependantFamilyMembers(User $user, string $rawText): void
    {
        // B-2 — ensure household exists before any dependant insert.
        $householdId = $this->householdProvisioner->ensureFor($user);

        // Ages are the most reliable signal. Pull every integer between 0
        // and 25 from the message; treat each as one dependant's age.
        if (preg_match_all('/\b(\d{1,2})\b/', $rawText, $matches) === false) {
            return;
        }

        $ages = array_values(array_filter(
            array_map('intval', $matches[1] ?? []),
            fn (int $n): bool => $n >= 0 && $n <= 25
        ));

        if (count($ages) === 0) {
            // Record the raw text as notes on a single placeholder row so
            // the intent is not lost.
            FamilyMember::create([
                'user_id' => $user->id,
                'household_id' => $householdId,
                'relationship' => 'child',
                'first_name' => 'Dependant',
                'is_dependent' => true,
                'education_status' => 'not_applicable',
                'notes' => 'Added via Fyn onboarding — ages not parsed. Raw: '.mb_substr($rawText, 0, 200),
            ]);

            return;
        }

        foreach ($ages as $age) {
            FamilyMember::create([
                'user_id' => $user->id,
                'household_id' => $householdId,
                'relationship' => $age < 18 ? 'child' : 'other_dependent',
                'first_name' => 'Dependant',
                'date_of_birth' => now()->subYears($age)->startOfYear()->toDateString(),
                'is_dependent' => true,
                'education_status' => $this->educationStatusForAge($age),
                'notes' => 'Added via Fyn onboarding. Age '.$age.' inferred from "'
                    .mb_substr($rawText, 0, 200).'"',
            ]);
        }
    }

    private function educationStatusForAge(int $age): string
    {
        return match (true) {
            $age < 5 => 'pre_school',
            $age < 11 => 'primary',
            $age < 18 => 'secondary',
            $age < 25 => 'higher_education',
            default => 'not_applicable',
        };
    }

    private function extractFirstName(string $text): ?string
    {
        // Match "called X", "named X", or a leading proper noun
        if (preg_match('/\b(?:called|named|is)\s+([A-Z][a-zA-Z]+)/', $text, $m) === 1) {
            return $m[1];
        }
        if (preg_match('/\b([A-Z][a-zA-Z]{1,20})\b/', $text, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function extractDate(string $text): ?string
    {
        // Very loose — any 4-digit year tends to be a DOB context here
        if (preg_match('/\b(\d{1,2}\s+[A-Za-z]+\s+\d{4})\b/', $text, $m) === 1) {
            return OnboardingValueInterpreter::parseRetirementDate($m[1]);
        }
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function extractPotentialAmount(string $text): ?string
    {
        // Prefer currency-marked amounts over bare numbers to avoid
        // matching years in "born 3 March 1986" as £1,986 income.
        if (preg_match('/£([\d,]+(?:\.\d+)?)\s*(k|m)?/i', $text, $m) === 1) {
            return '£'.$m[1].($m[2] ?? '');
        }

        // Bare "75k" / "75m" suffix
        if (preg_match('/\b(\d+(?:\.\d+)?)(k|m)\b/i', $text, $m) === 1) {
            return $m[1].$m[2];
        }

        return null;
    }

    // ─── Parking-driven hydration (Phase 11 Item 4) ──────────────────────

    /**
     * For the current grouped_extract state, check whether the conversation's
     * onboarding_parked_facts already carry every required field. If so,
     * call the capture tool handler directly with the parked values, yield
     * the capture SSE event the normal flow would emit, and advance state.
     *
     * Returns null when parking is insufficient — callers then fall through
     * to the standard LLM-backed handleGroupedExtractTurn. This is strictly
     * an optimisation; wrong answers (the user contradicting parked facts
     * in-chat) are still handled by the retraction block in
     * OnboardingPromptBuilder because parking only fires when the user
     * has NOT typed anything new for the state.
     *
     * Only capture_personal_details is hydrated for now — other buckets
     * need more careful field mapping (DOB from age_hint, relationship
     * normalisation) and are deferred to a follow-up.
     *
     * @return \Generator<array<string, mixed>>|null
     */
    private function hydrateFromParking(
        User $user,
        AiConversation $conversation,
        string $currentStateId,
        array $state,
    ): ?\Generator {
        $extractionTool = (string) ($state['extraction_tool'] ?? '');
        $parked = $conversation->onboarding_parked_facts ?? [];

        if (! is_array($parked) || $parked === []) {
            return null;
        }

        $input = match ($extractionTool) {
            'capture_personal_details' => $this->buildPersonalInputFromParking($parked, $user),
            default => null,
        };

        if ($input === null || $input === []) {
            return null;
        }

        Log::info('[OnboardingChatDirector] Parking hydration fires', [
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'state' => $currentStateId,
            'tool' => $extractionTool,
            'parked_fields' => array_keys($input),
        ]);

        $result = $this->coordinatingAgent->executeTool($extractionTool, $input, $user, $conversation->id);

        if (($result['error'] ?? false) === true) {
            Log::info('[OnboardingChatDirector] Parking hydration rejected — falling through to LLM', [
                'user_id' => $user->id,
                'state' => $currentStateId,
                'reason' => $result['message'] ?? 'unknown',
            ]);

            return null;
        }

        // Emit the same shape handleGroupedExtractTurn produces on a
        // successful capture so downstream consumers (frontend store) see
        // no difference.
        return (function () use ($user, $conversation, $currentStateId, $result) {
            if (($result['onboarding_capture'] ?? false) === true) {
                yield [
                    'type' => 'onboarding_field_captured',
                    'field_group' => $result['field_group'] ?? 'unknown',
                    'summary' => $result['summary'] ?? 'Captured.',
                    'details' => $result['details'] ?? [],
                    'hydrated_from_parking' => true,
                ];
            }

            $this->recordProgress($user, $currentStateId, ['hydrated_from_parking' => true]);

            // INV-2.2.6 — parking hydration has just committed the bucket
            // to users.* via the executeTool call above; clear the bucket
            // so the next prompt does not surface it again as a parked
            // fact and trigger duplicate ask-asks.
            $this->flushParkedFactsForState($conversation, $currentStateId);

            $user->refresh();

            $nextStateId = OnboardingStateMachine::getNextStateId(
                $currentStateId,
                '',
                $user,
            );

            if ($nextStateId === null) {
                return;
            }

            $user->onboarding_fyn_step = $nextStateId;

            if ($nextStateId === OnboardingStateMachine::STATE_DONE) {
                yield from $this->emitDoneTurn($user, $conversation);

                return;
            }

            $user->save();

            $nextState = OnboardingStateMachine::getState($nextStateId);
            if ($nextState === null) {
                return;
            }

            yield [
                'type' => 'onboarding_advance',
                'from_step' => $currentStateId,
                'to_step' => $nextStateId,
                'hydrated_from_parking' => true,
            ];

            yield from $this->emitTurnForState($user, $conversation, $nextStateId, $nextState);
        })();
    }

    /**
     * INV-2.2.6 — remove parked-fact buckets that have just been committed
     * to backing tables so the next prompt's <known_facts> block does not
     * re-list values the user already volunteered. Keys outside the state's
     * bucket set stay intact (e.g. parked spouse facts survive a
     * base_personal commit).
     *
     * Mapping mirrors INV-2.2.6: the state at which each bucket's data
     * gets written to its destination row.
     */
    private function flushParkedFactsForState(AiConversation $conversation, string $stateId): void
    {
        $buckets = match ($stateId) {
            OnboardingStateMachine::STATE_BASE_PERSONAL => ['personal'],
            OnboardingStateMachine::STATE_BASE_SPOUSE => ['spouse'],
            OnboardingStateMachine::STATE_BASE_DEPENDANTS_DETAIL => ['dependants'],
            OnboardingStateMachine::STATE_BASE_WORK => ['employment'],
            OnboardingStateMachine::STATE_BASE_EXPENDITURE => ['expenditure'],
            default => [],
        };

        if ($buckets === []) {
            return;
        }

        $parked = (array) ($conversation->onboarding_parked_facts ?? []);
        if ($parked === []) {
            return;
        }

        $changed = false;
        foreach ($buckets as $bucket) {
            if (array_key_exists($bucket, $parked)) {
                unset($parked[$bucket]);
                $changed = true;
            }
        }

        if ($changed) {
            $conversation->update([
                'onboarding_parked_facts' => $parked === [] ? null : $parked,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $parked
     * @return array<string, mixed>
     */
    private function buildPersonalInputFromParking(array $parked, User $user): array
    {
        $personal = is_array($parked['personal'] ?? null) ? $parked['personal'] : [];
        $input = [];

        // Only provide fields the user has NOT already got in the DB; the
        // tool handler rejects empty payloads which gracefully falls
        // through when the state would have been skipped anyway.
        if (empty($user->date_of_birth) && ! empty($personal['date_of_birth'])) {
            $input['date_of_birth'] = $personal['date_of_birth'];
        }

        if (empty($user->marital_status) && ! empty($personal['marital_status'])) {
            $input['marital_status'] = $personal['marital_status'];
        }

        return $input;
    }

    // ─── Grouped-extract delegation ───────────────────────────────────────

    /**
     * Handle a grouped_extract turn (base_personal, base_spouse,
     * base_dependants_detail, base_work). The director delegates to
     * Claude with a SINGLE extraction tool (filtered by the state config)
     * and a restricted system prompt. Claude parses the user's free-text
     * reply, calls the tool, and the CoordinatingAgent handler writes
     * fields to the DB + returns an `onboarding_capture` receipt that
     * HasAiChat yields as an `onboarding_field_captured` SSE event.
     *
     * If the capture event arrives, director advances state. Otherwise
     * it emits a retry message and stays on the current state so the
     * user can try again.
     */
    private function handleGroupedExtractTurn(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute,
        string $currentStateId,
        array $state
    ): \Generator {
        $toolName = (string) ($state['extraction_tool'] ?? '');
        if ($toolName === '') {
            yield $this->errorEvent('Onboarding state is missing its extraction tool.');

            return;
        }

        $toolDefinitions = app(AiToolDefinitions::class);
        // Match the active provider so the tools ship in the correct
        // format. xAI expects the OpenAI function-calling wrapper,
        // Anthropic expects the flattened input_schema shape.
        $provider = Cache::get(
            'ai_provider',
            config('services.ai_provider', 'anthropic')
        );
        $allExtractionTools = $toolDefinitions->onboardingExtractionTools(provider: $provider);

        // Filter to the single tool this state needs. The filter key
        // lookup differs between providers — xAI wraps the name inside
        // function.name, Anthropic has it at the top level.
        $filtered = array_values(array_filter(
            $allExtractionTools,
            function (array $tool) use ($toolName): bool {
                $candidate = $tool['name']
                    ?? ($tool['function']['name'] ?? null);

                return $candidate === $toolName;
            }
        ));

        if (count($filtered) === 0) {
            Log::error('[OnboardingChatDirector] extraction tool not found', [
                'tool' => $toolName,
                'state' => $currentStateId,
            ]);
            yield $this->errorEvent('Onboarding is temporarily unavailable.');

            return;
        }

        $systemPrompt = $this->buildGroupedExtractPrompt($user, $currentStateId, $toolName, $conversation);

        $captureReceived = false;
        $captureDetails = [];
        $captureError = null;

        // A1 — answer-the-user-first on a grouped_extract turn. When the
        // user's message asks a question, buffer the model's prose so a
        // definitional answer can be delivered alongside the scripted re-ask
        // when no extraction lands. Without a question the prose is swallowed
        // exactly as before (the extraction tool is meant to fire silently).
        $userAskedQuestion = $this->userAskedQuestion($message);
        $answerBuffer = '';

        try {
            $generator = $this->fynLoop->stream(
                $user,
                $conversation,
                $message,
                $currentRoute,
                persona: null,
                systemPromptOverride: $systemPrompt,
                allowedTools: null,
                persistUserMessage: false,
                toolsListOverride: $filtered,
            );

            foreach ($generator as $event) {
                // Swallow the per-turn `title` event — the title is already
                // set to "Onboarding" when the conversation was created.
                if (($event['type'] ?? '') === 'title') {
                    continue;
                }

                if (($event['type'] ?? '') === 'onboarding_field_captured') {
                    $captureReceived = true;
                    $captureDetails = $event['details'] ?? [];

                    // Stop consuming the delegated generator immediately.
                    // LLMs occasionally re-call the extraction tool after
                    // the first success because the system prompt does not
                    // communicate termination — the max-tool-calls limit
                    // catches it but wastes latency. We have everything we
                    // need; abandon the rest of the delegation.
                    break;
                }

                // FR-M13 — structured onboarding capture error (e.g. the
                // spouse email is already bound to another household).
                // Halt the delegation and hand off to emitTerminalError
                // below with the handler's friendly copy.
                if (($event['type'] ?? '') === 'onboarding_capture_error') {
                    $captureError = [
                        'error_type' => (string) ($event['error_type'] ?? 'unknown'),
                        'message' => (string) ($event['message'] ?? ''),
                    ];
                    break;
                }

                // Don't forward the `done` event from the delegated chat
                // — the director emits its own `done` after the next
                // turn so the frontend doesn't think we've finished.
                if (($event['type'] ?? '') === 'done') {
                    continue;
                }

                // Swallow tool_use status events — they leak implementation
                // details to the frontend. The director's own onboarding_advance
                // + quick_replies / content events tell the user what's happening.
                if (($event['type'] ?? '') === 'tool_use') {
                    continue;
                }

                // Swallow conversational text from the delegated model. The
                // restricted system prompt instructs the model to call the
                // extraction tool silently, but Grok-4.1 and occasionally
                // Claude emit chatty text alongside the tool call. Letting
                // that text through stacks two assistant messages (model
                // text + director retry) on the user on failed captures.
                //
                // A1 — exception: when the user asked a question, keep the
                // prose in $answerBuffer so a definitional answer can be
                // emitted before the re-ask if no extraction lands.
                if (($event['type'] ?? '') === 'content') {
                    if ($userAskedQuestion) {
                        $answerBuffer .= (string) ($event['text'] ?? '');
                    }

                    continue;
                }

                yield $event;
            }
        } catch (\Throwable $e) {
            Log::error('[OnboardingChatDirector] Grouped extract delegation failed', [
                'user_id' => $user->id,
                'state' => $currentStateId,
                'tool' => $toolName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            yield from $this->emitRetry($conversation, $state, $currentStateId);

            return;
        }

        // FR-M13 — emit a targeted terminal error instead of the generic
        // retry when the handler surfaced a distinct error_type. State is
        // left on the current grouped_extract so the user's next message
        // re-enters this same handler.
        if ($captureError !== null) {
            yield from $this->emitTerminalError($conversation, $currentStateId, $captureError);

            return;
        }

        if (! $captureReceived) {
            // Claude didn't call the tool or the handler errored. Stay on
            // the current state so the user can retry.
            Log::warning('[OnboardingChatDirector] Grouped extract completed without capture event', [
                'user_id' => $user->id,
                'state' => $currentStateId,
                'tool' => $toolName,
            ]);

            // A1 — the user asked a question that yielded no extraction.
            // Deliver the definitional answer (personal figures stripped)
            // before the scripted re-ask so the question is not ignored.
            if ($userAskedQuestion && $answerBuffer !== '') {
                $answer = $this->filterOffScriptContent($answerBuffer, $currentStateId, allowAnswer: true);
                if ($answer !== '') {
                    $answerMessage = $this->saveMessage($conversation, 'assistant', $answer, [
                        'metadata' => [
                            'onboarding_step' => $currentStateId,
                            'is_question_answer' => true,
                        ],
                    ]);
                    yield ['type' => 'content', 'text' => $answer, 'message_id' => $answerMessage->id];
                }
            }

            // Carry-forward disambiguation (director side). The model often
            // declines to call capture_pension_history for a lone figure like
            // "Around £90,000" — judging, correctly, that it can't tell whether
            // it's a three-year total or a per-year amount. When it declines,
            // the handler-level guard never runs, so the generic retry_text
            // alone would re-ask without ever clarifying the total-vs-per-year
            // ambiguity. States that opt in via `clarify_single_figure` emit
            // the real clarifying question here instead, staying on-state so
            // the user's disambiguated reply re-enters this handler.
            if (($state['clarify_single_figure'] ?? false) === true
                && $this->messageIsAmbiguousSingleFigure($message)) {
                yield from $this->emitSingleFigureClarification($conversation, $currentStateId);

                return;
            }

            yield from $this->emitRetry($conversation, $state, $currentStateId);

            return;
        }

        // Partial capture — tool handler saved what it could but flagged
        // missing required fields. Ask only for what's still missing and
        // stay on the current state so the next user reply re-enters this
        // same grouped_extract path.
        $missing = (array) ($captureDetails['missing'] ?? []);
        if (count($missing) > 0) {
            yield from $this->emitPartialRetry($conversation, $currentStateId, $toolName, $missing);

            return;
        }

        $this->recordProgress($user, $currentStateId, $captureDetails);

        // INV-2.2.6 — same flush as the free-text path above. The grouped
        // capture handler has just written the bucket's fields to users.*
        // / family_members so leaving the parked copy would re-list those
        // values in the next <known_facts> block.
        $this->flushParkedFactsForState($conversation, $currentStateId);

        // Refresh the user so skip_if helpers on the next state see the
        // freshly-written columns.
        $user->refresh();

        // Cross-check the captured income against the funnel band the user
        // picked on the website. On a contradiction, challenge + hold here.
        $challenged = yield from $this->maybeChallengeIncome($user, $conversation, $currentStateId, $captureDetails);
        if ($challenged) {
            return;
        }

        yield from $this->advanceFromState($user, $conversation, $currentStateId, $message);
    }

    /**
     * Advance from a just-completed capture state to the next state and emit
     * its turn. Extracted from handleGroupedExtractTurn so the income-challenge
     * Continue branch can resume the advance after the user confirms.
     */
    private function advanceFromState(User $user, AiConversation $conversation, string $currentStateId, string $message): \Generator
    {
        $nextStateId = OnboardingStateMachine::getNextStateId(
            $currentStateId,
            $message,
            $user
        );

        if ($nextStateId === null) {
            yield $this->errorEvent('Onboarding state machine reached a dead end after grouped capture.');

            return;
        }

        $user->onboarding_fyn_step = $nextStateId;

        if ($nextStateId === OnboardingStateMachine::STATE_DONE) {
            yield from $this->emitDoneTurn($user, $conversation);

            return;
        }

        $user->save();

        $nextState = OnboardingStateMachine::getState($nextStateId);
        if ($nextState === null) {
            yield $this->errorEvent('Unknown next state: '.$nextStateId);

            return;
        }

        // Emit a capture ack for the just-completed grouped_extract state
        // (spouse, dependants, employment, expenditure). Fires between the
        // handler's persistence and the next state's prompt so transitions
        // don't feel abrupt.
        $ack = $this->buildCaptureAck($user, $currentStateId, []);
        if ($ack !== null) {
            yield ['type' => 'content', 'text' => $ack];
        }

        yield [
            'type' => 'onboarding_advance',
            'from_step' => $currentStateId,
            'to_step' => $nextStateId,
        ];

        // Terminal-with-navigate states (e.g. STATE_CAMPAIGN_TERMINAL → /tax-strategy)
        // own their own route and must mark onboarding_completed. Free-text
        // path has the same branch at the main turn-router (handleUserMessage);
        // grouped_extract needs its own copy because it returns from inside
        // this private method.
        if (($nextState['turn_type'] ?? '') === 'terminal' && ! empty($nextState['navigate_to'])) {
            yield from $this->emitTerminalNavigationTurn($user, $conversation, $nextStateId, $nextState);

            return;
        }

        yield from $this->emitTurnForState($user, $conversation, $nextStateId, $nextState);
    }

    /**
     * Emit a retry turn for a failed grouped_extract. The retry text is
     * saved as a real assistant message (so it survives the frontend's
     * streamingText finally block that clears unflushed text), then a
     * content + done event are yielded to close the turn cleanly. The
     * user stays on the current state so they can try again.
     */
    private function emitRetry(AiConversation $conversation, array $state, string $currentStateId): \Generator
    {
        $retryText = (string) ($state['retry_text'] ?? "Sorry, I didn't catch that. Could you try again?");

        $message = $this->saveMessage($conversation, 'assistant', $retryText, [
            'metadata' => [
                'onboarding_step' => $currentStateId,
                'is_retry' => true,
            ],
        ]);

        yield ['type' => 'content', 'text' => $retryText];
        yield ['type' => 'done', 'message_id' => $message->id];
    }

    /**
     * FR-M13 — emit a targeted terminal error that replaces the generic
     * retry_text with the handler's own copy (e.g. the spouse-collision
     * message). State is left unchanged so the next user reply routes
     * back through the same grouped_extract handler.
     *
     * @param  array{error_type: string, message: string}  $captureError
     */
    private function emitTerminalError(
        AiConversation $conversation,
        string $currentStateId,
        array $captureError
    ): \Generator {
        $text = $captureError['message'] !== ''
            ? $captureError['message']
            : 'Something went wrong. Could you try again?';

        $message = $this->saveMessage($conversation, 'assistant', $text, [
            'metadata' => [
                'onboarding_step' => $currentStateId,
                'is_terminal_error' => true,
                'error_type' => $captureError['error_type'] ?? 'unknown',
            ],
        ]);

        yield ['type' => 'content', 'text' => $text];
        yield ['type' => 'done', 'message_id' => $message->id];
    }

    /**
     * Emit the carry-forward total-vs-per-year clarifying question, staying on
     * the current grouped_extract state so the user's disambiguated reply
     * re-enters this handler. Voiced verbatim so the phrasing is deterministic
     * (a single lone figure for the three-year pension window is catastrophic
     * to mis-read: a per-year £90k is an annual-allowance charge, the same
     * figure spread across three years is unused headroom to top up).
     */
    private function emitSingleFigureClarification(
        AiConversation $conversation,
        string $currentStateId
    ): \Generator {
        $text = 'Just to be sure I read that correctly — is that the total across the three tax years, or roughly that amount each year? It changes whether you have unused allowance to top up.';

        $message = $this->saveMessage($conversation, 'assistant', $text, [
            'metadata' => [
                'onboarding_step' => $currentStateId,
                'is_clarification' => true,
                'clarification_type' => 'pension_history_total_vs_per_year',
            ],
        ]);

        yield ['type' => 'content', 'text' => $text];
        yield ['type' => 'done', 'message_id' => $message->id];
    }

    /**
     * Director-side mirror of CoordinatingAgent's pension-history ambiguity
     * check: does the user reply give a SINGLE monetary figure for the window
     * with no per-year or whole-window-total cue? Used when the model declined
     * to call the extraction tool, so the handler guard never ran.
     */
    private function messageIsAmbiguousSingleFigure(string $message): bool
    {
        $matches = [];
        preg_match_all('/£?\s?\d[\d,]*(?:\.\d+)?\s?(?:k|m)?(?![\d.])/iu', $message, $matches);
        $figures = array_filter(
            array_map('trim', $matches[0] ?? []),
            static function (string $tok): bool {
                $hasMoneyMarker = (bool) preg_match('/[£,km.]/i', $tok);
                $isYearLike = (bool) preg_match('/^(?:19|20)\d{2}$/', preg_replace('/[^\d]/', '', $tok) ?? '');

                return $hasMoneyMarker || ! $isYearLike;
            }
        );
        if (count($figures) !== 1) {
            return false;
        }

        $lower = mb_strtolower($message);
        $resolvingCues = [
            'each year', 'per year', 'a year', 'every year', 'each of', 'each tax year',
            'per annum', 'annually', '/yr', 'p.a.', 'pa ', 'each of the', 'for each',
            'first year', 'second year', 'third year', 'last year', 'year before',
            'in total', 'total of', 'altogether', 'across', 'combined', 'between them',
            'all three', 'over the three', 'over three', 'spread', 'split',
        ];
        foreach ($resolvingCues as $cue) {
            if (str_contains($lower, $cue)) {
                return false;
            }
        }

        return preg_match('/\b20\d{2}\s*\/\s*\d{2}\b/', $message) !== 1;
    }

    /**
     * Emit a targeted retry listing only the fields the tool handler
     * reported as still missing. Keeps the user on the current state so
     * the next reply re-enters the grouped_extract flow, this time
     * carrying the fields we asked for. Previously-saved fields stay
     * saved — the tool handler only writes non-empty values.
     *
     * @param  list<string>  $missing  field names from the handler
     */
    private function emitPartialRetry(
        AiConversation $conversation,
        string $currentStateId,
        string $toolName,
        array $missing
    ): \Generator {
        $retryText = $this->composePartialRetryText($toolName, $missing);

        $message = $this->saveMessage($conversation, 'assistant', $retryText, [
            'metadata' => [
                'onboarding_step' => $currentStateId,
                'is_partial_retry' => true,
                'missing_fields' => $missing,
            ],
        ]);

        yield ['type' => 'content', 'text' => $retryText];
        yield ['type' => 'done', 'message_id' => $message->id];
    }

    /**
     * Compose a friendly single-sentence retry naming exactly the fields
     * we still need. Falls back to the generic retry text on unknown
     * combinations so we never emit a blank prompt.
     *
     * @param  list<string>  $missing
     */
    private function composePartialRetryText(string $toolName, array $missing): string
    {
        $friendlyMap = match ($toolName) {
            'capture_work_details' => [
                'employer' => 'the company or trade name',
                'occupation' => 'your role or position',
                'annual_income' => 'your gross annual income in GBP',
            ],
            'capture_personal_details' => [
                'date_of_birth' => 'your date of birth',
                'marital_status' => 'your marital status',
            ],
            'capture_spouse_details' => [
                'first_name' => 'their first name',
                'date_of_birth' => 'their date of birth',
                'email' => 'their email address',
            ],
            default => [],
        };

        $friendly = array_values(array_filter(array_map(
            fn (string $field): ?string => $friendlyMap[$field] ?? null,
            $missing
        )));

        if (count($friendly) === 0) {
            return 'I still need a couple of things to move on — could you share them again?';
        }

        $list = match (count($friendly)) {
            1 => $friendly[0],
            2 => $friendly[0].' and '.$friendly[1],
            default => implode(', ', array_slice($friendly, 0, -1)).', and '.end($friendly),
        };

        return 'Thanks — I still need '.$list.'. Could you share '.(count($friendly) === 1 ? 'that' : 'those').'?';
    }

    /**
     * Cross-check a just-captured income figure against the band the user
     * picked on the SaveTax funnel. Returns the mismatch payload to challenge,
     * or null when there is nothing to challenge (no funnel band, unknown band,
     * no figure captured, or the figure is in-band).
     *
     * @return array{field: string, band: string, entered: float}|null
     */
    private function detectIncomeFunnelMismatch(User $user, string $stateId, array $captureDetails): ?array
    {
        $funnel = is_array($user->funnel_answers ?? null) ? $user->funnel_answers : [];
        if ($funnel === []) {
            return null;
        }

        if ($stateId === OnboardingStateMachine::STATE_BASE_WORK) {
            $field = 'self';
            $band = (string) ($funnel['income'] ?? '');
        } elseif ($stateId === OnboardingStateMachine::STATE_BASE_SPOUSE) {
            $field = 'spouse';
            $band = (string) ($funnel['spouseIncome'] ?? '');
        } else {
            return null;
        }

        if (! FunnelIncomeBand::isKnown($band)) {
            return null;
        }

        // $captureDetails is the handler's `details` array (assigned from
        // $event['details'] in handleGroupedExtractTurn), so annual_income is a
        // direct key here — NOT nested under another 'details'.
        $enteredRaw = $captureDetails['annual_income'] ?? null;
        if ($enteredRaw === null) {
            // Spouse income is optional; user-income absence is handled by the
            // income-required retry, not here.
            return null;
        }
        $entered = (float) $enteredRaw;

        if (FunnelIncomeBand::inBand($band, $entered)) {
            return null;
        }

        return ['field' => $field, 'band' => $band, 'entered' => $entered];
    }

    /**
     * Plain-text challenge naming what the user told the funnel and what they
     * just entered. No icons (Rule #15); British spelling.
     *
     * @param  array{field: string, band: string, entered: float}  $mismatch
     */
    private function buildIncomeChallenge(array $mismatch, User $user): string
    {
        $bandLabel = FunnelIncomeBand::label($mismatch['band']);
        $entered = '£'.number_format($mismatch['entered']);

        if ($mismatch['field'] === 'spouse') {
            $whose = "your spouse's income was {$bandLabel}";
            $question = "is {$entered} right for them?";
        } else {
            $whose = "your income was {$bandLabel}";
            $question = "is {$entered} right?";
        }

        return "Earlier you told us {$whose}, but you've entered {$entered}. "
            ."That changes your tax-saving calculation — {$question}";
    }

    /**
     * If the just-captured income contradicts the funnel band, park a
     * pending_income_challenge flag, emit the challenge + Continue/Change
     * bubbles, and yield nothing further. Returns true when it challenged
     * (caller must NOT advance), false otherwise.
     */
    private function maybeChallengeIncome(User $user, AiConversation $conversation, string $stateId, array $captureDetails): \Generator
    {
        $mismatch = $this->detectIncomeFunnelMismatch($user, $stateId, $captureDetails);
        if ($mismatch === null) {
            return false;
        }

        $context = is_array($user->onboarding_fyn_context) ? $user->onboarding_fyn_context : [];
        $context['pending_income_challenge'] = $mismatch;
        $user->onboarding_fyn_context = $context;
        $user->save();

        $text = $this->buildIncomeChallenge($mismatch, $user);
        $this->saveMessage($conversation, 'assistant', $text, [
            'metadata' => ['onboarding_step' => $stateId, 'income_challenge' => true],
        ]);

        yield [
            'type' => 'quick_replies',
            'prompt_text' => $text,
            'bubbles' => [
                ['id' => 'continue', 'label' => 'Continue'],
                ['id' => 'change', 'label' => 'Change'],
            ],
        ];

        return true;
    }

    /**
     * Build the restricted system prompt for grouped-extract turns. Must
     * stay narrow — we do not want Claude to answer the user, we just
     * want it to call the single extraction tool with parsed fields.
     */
    private function buildGroupedExtractPrompt(User $user, string $stateId, string $toolName, ?AiConversation $conversation = null): string
    {
        $firstName = trim((string) ($user->first_name ?? ''));
        if ($firstName === '') {
            $nameParts = explode(' ', (string) $user->name);
            $firstName = $nameParts[0] ?: 'there';
        }

        // S1.4 — known_facts block; injected after <instructions> below
        // (rendered into the heredoc) so the LLM never re-asks for fields
        // already known from layers 1-4.
        $knownFactsBlock = $this->memory->renderKnownFactsBlock($user, $conversation);

        $instructions = match ($toolName) {
            'capture_personal_details' => 'Extract the user\'s date of birth and marital status from their message. Map phrases exactly: "civil partnership" / "civil partner" → civil_partnership; "married" → married; "single" → single; "divorced" / "separated" → divorced; "widowed" → widowed.',
            'capture_spouse_details' => 'Extract the user\'s spouse or partner details. You need their first name, date of birth, and email address. If they mention an annual income, extract it too. Do NOT invent missing fields — if the user did not provide all three required fields, return an error.',
            'capture_dependants' => 'Extract a list of the user\'s dependants. Each entry needs an age and a relationship (child, parent, or other_dependent). First names are optional. Map phrases: "son", "daughter", "step-daughter", "step-son", "kid", "child" → child. "mother", "father", "mum", "dad", "mum-in-law", etc. → parent. Sibling, nephew, elderly relative, friend → other_dependent. If the user says "two kids aged 4 and 7" return two entries with relationship=child.',
            'capture_work_details' => 'Extract the user\'s employer or trade name, their role/position, and their gross annual income in GBP. Strip currency symbols and commas before returning the number. "75k" means 75000. Do not invent fields.',
            'capture_pension_history' => 'Extract the user\'s gross pension contributions for the recent tax years they mention. Strip currency symbols and commas ("90k" means 90000). The three most recent UK tax years are 2024/25, 2023/24, 2022/23. If the user gives a per-year breakdown, map each figure to its year. If they give a SINGLE figure with no per-year breakdown (e.g. "around £90,000"), do NOT guess a split across years and do NOT divide it — call the tool ONCE with that single figure under the most recent tax year (2024/25); the system will clarify total-vs-per-year with the user. "Zero" or "none" means a single entry of 0 for the most recent year. Always call the tool — never reply conversationally.',
            default => 'Extract the user\'s reply using the provided tool.',
        };

        $prompt = <<<PROMPT
<identity>
You are an extraction helper for the Fynla onboarding flow. {$firstName} is a new user setting up their account. Your ONLY job this turn is to extract structured fields from their plain-English reply and call the `{$toolName}` tool exactly once. Do not answer, greet, analyse, or respond conversationally — just call the tool.
</identity>

<instructions>
{$instructions}

Rules:
- Call `{$toolName}` exactly ONCE per turn with the fields extracted from the user's most recent message.
- Do not call any other tool. Do not emit a text response.
- If the user's reply is missing a required field, still call the tool but leave the missing field empty or return an error via the tool's standard result — the director will retry.
- Dates must be in YYYY-MM-DD format.
- Numbers must be plain integers or decimals without currency symbols, commas, or units.
</instructions>
PROMPT;

        if ($knownFactsBlock !== '') {
            $prompt .= "\n\n".$knownFactsBlock;
        }

        return $prompt;
    }

    // ─── Asset capture delegation ─────────────────────────────────────────

    /**
     * Delegated turn — hand off to CoordinatingAgent::chat() with a
     * restricted system prompt and focus-filtered tools. The multi-entity
     * fix from Phase 1a lives in the frontend queue, which still applies
     * because Claude is emitting the same fill_form / create_* tool_use
     * blocks CoordinatingAgent normally processes.
     *
     * After the delegation completes, the director advances state to
     * add_more so the next user turn routes back through handleUserMessage.
     */
    /**
     * @param  array<string, mixed>  $state
     */
    private function handleAssetCaptureTurn(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute,
        string $currentStateId = OnboardingStateMachine::STATE_ASSET_CAPTURE,
        array $state = []
    ): \Generator {
        $selection = $user->onboarding_fyn_selection ?? 'savings';

        // April30Updates F-11 — duplicate-check guard.
        //
        // During multi-turn onboarding (especially the SaveTax 6-8 turn flow)
        // the user may re-mention records they already described in an earlier
        // turn ("the Aviva life cover I told you about"). The known_facts
        // block reduces re-asking, but even at temperature 0 the LLM can
        // still re-emit a create_* tool. The advice path is protected by
        // RecordDuplicateChecker; until now the onboarding path was not.
        // Mirror the same guard here so multi-turn capture cannot create
        // duplicates. We map the focus to the entity_type the checker
        // recognises; estate / business / savetax fall through (no checker
        // mapping — handler-level dedup remains the floor for those).
        $entityType = match ($selection) {
            'savings', 'budgeting' => 'savings_account',
            'investment' => 'investment_account',
            'retirement' => 'pension',
            'protection' => 'protection_policy',
            'goals' => 'goal',
            default => null,
        };
        if ($entityType !== null) {
            $intent = ['entity_type' => $entityType];
            if ($this->duplicateChecker->alreadyExists($user, $intent, $message)) {
                Log::info('[OnboardingChatDirector] Duplicate capture suppressed', [
                    'user_id' => $user->id,
                    'conversation_id' => $conversation->id,
                    'focus' => $selection,
                    'entity_type' => $entityType,
                ]);

                yield ['type' => 'content', 'text' => 'Already on file — nothing to add there.'];
                yield ['type' => 'done'];

                return;
            }
        }

        // Swap the coordinating agent's system prompt for this turn only.
        // The streamed turn (and its unified-focus set/clear) runs through
        // FynLoop::stream so the focus and the stream that reads it share one
        // CoordinatingAgent instance — see FynLoop::stream().
        [$restrictedPrompt, $unifiedFocus] = $this->resolveUnifiedRestrictedPrompt($user, $selection, $conversation);
        $allowedTools = OnboardingPromptBuilder::toolsForFocus($selection);

        try {
            $generator = $this->fynLoop->stream(
                $user,
                $conversation,
                $message,
                $currentRoute,
                // May18 tripled-ack Fix A only fires under the data_capture
                // persona (HasAiChat::captureTurnCompleteDirective gates on it).
                // This IS a capture turn, so opt in — otherwise the xai model
                // re-narrates "Got it — recording those now." on every agent-loop
                // continuation and the ack stacks ×N. The persona only tags
                // the assistant message + enables the directive (FynLoop::stream
                // forwards it as personaOverride); tool gating and the prompt
                // come from the override args above, so it is safe here.
                persona: 'data_capture',
                systemPromptOverride: $restrictedPrompt,
                allowedTools: $allowedTools,
                persistUserMessage: false, // already saved at top of handleUserMessage
                unifiedFocus: $unifiedFocus,
            );

            // FR-M14 — buffered sentence-level content filter.
            //
            // The delegated generator streams content as token-sized deltas
            // (see HasAiChat::chat()), so a per-event sentence split would
            // fire keyword/question checks on partial words. Instead we
            // accumulate every content delta into $contentBuffer for the
            // whole turn, then flush through filterOffScriptContent() just
            // before forwarding the generator's `done` marker. Tool events
            // stream in real time so the UI can show tool status as it
            // happens; only the LLM's prose is held back. Zero-tool-call
            // turns drop the buffer entirely — the director's subsequent
            // add_more turn gives the user a clear next step and any LLM
            // prose in that case is almost always off-script.
            $toolCallsSeen = 0;
            $contentBuffer = '';
            $flushed = false;

            // A1 — answer-the-user-first. When the user's message asks a
            // question, the capture turn is allowed to answer it before
            // resuming capture (QUESTION EXCEPTION). That changes three things:
            // (1) a zero-tool-call turn must NOT drop its buffer — the answer
            //     is the whole point of the turn;
            // (2) the off-script filter runs in allowAnswer mode so a
            //     definitional answer survives, while personal figures are
            //     still stripped; and
            // (3) a question turn that captured nothing stays on this state
            //     instead of advancing (see the gate before the advance below).
            $userAskedQuestion = $this->userAskedQuestion($message);

            // B-1 gap-check — track the fields dict of every fill_form the
            // LLM emitted so we can compare it to the deterministic entity
            // extractor's view of the user message and fill in any gaps
            // after the stream is done. fill_form is the single canonical
            // source of the "what entity was just captured" data; tool_use
            // status events don't carry the input payload.
            $llmEmittedFills = [];

            $flushBuffer = function () use (&$contentBuffer, &$toolCallsSeen, &$flushed, $selection, $userAskedQuestion) {
                $flushed = true;
                if ($contentBuffer === '' || ($toolCallsSeen === 0 && ! $userAskedQuestion)) {
                    $contentBuffer = '';

                    return null;
                }
                $cleaned = $this->filterOffScriptContent($contentBuffer, $selection, allowAnswer: $userAskedQuestion);
                $cleaned = $this->dedupeAckSentences($cleaned);
                $contentBuffer = '';
                if ($cleaned === '') {
                    return null;
                }

                return ['type' => 'content', 'text' => $cleaned];
            };

            foreach ($generator as $event) {
                $type = $event['type'] ?? '';

                if ($type === 'content') {
                    $contentBuffer .= (string) ($event['text'] ?? '');

                    continue;
                }

                if ($type === 'tool_use' || $type === 'tool_success') {
                    $toolCallsSeen++;
                    yield $event;

                    continue;
                }

                if ($type === 'fill_form') {
                    $llmEmittedFills[] = (array) ($event['fields'] ?? []);
                    yield $event;

                    continue;
                }

                if ($type === 'done') {
                    // Flush the buffered ack content now, but DON'T forward the
                    // delegated chat's own `done` — the director emits the next
                    // turn (emitTurnForState / emitTerminalNavigationTurn) after
                    // this method advances state, and that turn carries the single
                    // terminal `done`. Forwarding this inner done emits TWO dones
                    // in one SSE stream; SSE consumers (mobile apiStream, desktop)
                    // stop at the FIRST done, so the next state's prompt was never
                    // rendered. Mirrors the grouped_extract path's done-swallow.
                    $flushEvent = $flushBuffer();
                    if ($flushEvent !== null) {
                        yield $flushEvent;
                    }

                    // B-1 — synthesize tool calls for entities the LLM
                    // dropped BEFORE the done marker so the frontend's
                    // aiFormFill queue sees them in a single turn.
                    yield from $this->emitGapFillToolCalls($user, $selection, $message, $llmEmittedFills);

                    continue;
                }

                yield $event;
            }

            // Safety net — generator exited without emitting `done` (e.g.
            // the model responded but the harness dropped the marker).
            // Without this, a successful tool-call turn would lose its ack.
            if (! $flushed) {
                $flushEvent = $flushBuffer();
                if ($flushEvent !== null) {
                    yield $flushEvent;
                }
                yield from $this->emitGapFillToolCalls($user, $selection, $message, $llmEmittedFills);
            }
        } catch (\Throwable $e) {
            Log::error('[OnboardingChatDirector] Asset capture delegation failed', [
                'user_id' => $user->id,
                'selection' => $selection,
                'error' => $e->getMessage(),
            ]);

            yield [
                'type' => 'content',
                'text' => 'I had trouble reading that. Could you try listing them one at a time?',
            ];

            return;
        }
        // The unified onboarding focus is set and cleared inside
        // FynLoop::stream (on the same agent instance it streams on), so no
        // focus-clear is needed here — FynLoop's own finally covers the
        // generator-throw path.

        // A1 — honour the QUESTION EXCEPTION's "Do NOT advance past it". A
        // question turn that captured nothing must stay on the current
        // capture state and re-ask the scripted question; the unconditional
        // advance below would bump the user to add_more ("Anything else?")
        // with their question's place in the flow lost. Tool-bearing turns
        // (the user answered AND asked) advance as normal — the answer was
        // captured, so the flow moves on.
        //
        // Exception: linear scripted delegated states that create no entity
        // record (campaign workplace-pension / personal-pension steps) opt in
        // via `advance_on_answered_question`. There, "captured nothing" is the
        // norm even on a clean answer, so a side-question ("3% and matched.
        // What's salary sacrifice?") would otherwise stall the walk forever.
        // We advance once the user gave a substantive answer alongside the
        // question; a bare question with no answer still re-asks.
        $capturedSomething = $toolCallsSeen > 0 || count($llmEmittedFills) > 0;
        $advanceOnAnsweredQuestion = ($state['advance_on_answered_question'] ?? false) === true
            && $this->messageHasSubstantiveAnswer($message);
        if ($userAskedQuestion && ! $capturedSomething && ! $advanceOnAnsweredQuestion) {
            $this->recordProgress(
                $user,
                $currentStateId,
                ['selection' => $selection, 'raw_message' => mb_substr($message, 0, 500)]
            );
            yield from $this->emitTurnForState($user, $conversation, $currentStateId, $state);

            return;
        }

        // Record the step in onboarding_progress (best-effort — tool calls
        // that actually created records already persisted their own rows).
        // Records under the actual state ID so campaign delegated states
        // (campaign_occupational_scheme, campaign_isa_holdings, etc.) appear
        // as their own rows, not as STATE_ASSET_CAPTURE.
        $this->recordProgress(
            $user,
            $currentStateId,
            ['selection' => $selection, 'raw_message' => mb_substr($message, 0, 500)]
        );

        // Advance via state.next so the journey/focus path stays at
        // STATE_ASSET_CAPTURE → STATE_ADD_MORE while the campaign branch
        // walks through its 5 delegated states sequentially.
        $nextStateId = OnboardingStateMachine::getNextStateId(
            $currentStateId,
            $message,
            $user->refresh()
        );

        if ($nextStateId === null) {
            return;
        }

        $user->onboarding_fyn_step = $nextStateId;
        $user->save();

        yield [
            'type' => 'onboarding_advance',
            'from_step' => $currentStateId,
            'to_step' => $nextStateId,
        ];

        $nextState = OnboardingStateMachine::getState($nextStateId);
        if ($nextState === null) {
            return;
        }

        // SaveTax campaign terminal: emit the celebration text, fire the
        // navigate SSE event using state.navigate_to, mark onboarding
        // complete. emitTurnForState alone only renders the prompt — it
        // doesn't know about navigate_to or onboarding_complete chains.
        if (($nextState['turn_type'] ?? '') === 'terminal' && ! empty($nextState['navigate_to'])) {
            yield from $this->emitTerminalNavigationTurn($user, $conversation, $nextStateId, $nextState);

            return;
        }

        yield from $this->emitTurnForState($user, $conversation, $nextStateId, $nextState);
    }

    /**
     * SaveTax verify edit (campaign_verify_edit). The user answered the Gate-2
     * "is this correct?" with NO and described the correction. UPDATE the
     * existing record/field for the section being verified — never create a new
     * one. The section's existing records (with their ids) are surfaced in the
     * prompt and the tool set is restricted to update_record / update_profile /
     * set_expenditure, so a create (and the duplicate it would produce) is
     * impossible. Only advances to re-show Gate 2 when an update actually
     * applied; otherwise re-asks, so Fyn never claims a change it did not make.
     */
    private function handleCampaignVerifyEdit(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute,
        string $currentStateId,
        array $state
    ): \Generator {
        $context = is_array($user->onboarding_fyn_context) ? $user->onboarding_fyn_context : [];
        $section = (string) (($context['verify_section'] ?? '') ?: '');

        $toolCallsSeen = 0;
        $contentBuffer = '';

        try {
            $generator = $this->fynLoop->stream(
                $user,
                $conversation,
                $message,
                $currentRoute,
                persona: 'data_capture',
                systemPromptOverride: $this->buildVerifyEditPrompt($user, $section),
                // The capture bucket (FynCaptureTurnInstructions) is create-oriented
                // and references the focus's create_ tools; stripping them to
                // update-only confuses the model into the security refusal. Keep the
                // full focus tool set (its Retraction clause already routes a
                // correction to update_record/update_profile) and supply the record
                // ids it normally lacks via the prompt appendix below.
                allowedTools: OnboardingPromptBuilder::toolsForFocus($this->verifyEditFocus($section)),
                persistUserMessage: false, // already saved at top of handleUserMessage
                unifiedFocus: $this->verifyEditFocus($section),
            );

            foreach ($generator as $event) {
                $type = $event['type'] ?? '';

                if ($type === 'content') {
                    $contentBuffer .= (string) ($event['text'] ?? '');

                    continue;
                }

                if ($type === 'tool_use' || $type === 'tool_success') {
                    $toolCallsSeen++;
                    yield $event;

                    continue;
                }

                if ($type === 'fill_form') {
                    yield $event;

                    continue;
                }

                // Swallow the delegated chat's own `done` — the terminal `done`
                // is emitted by the next turn (or the re-ask) below. Mirrors
                // handleAssetCaptureTurn's done-swallow.
                if ($type === 'done') {
                    continue;
                }

                yield $event;
            }
        } catch (\Throwable $e) {
            Log::error('[OnboardingChatDirector] Verify edit delegation failed', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'verify_section' => $section,
                'error' => $e->getMessage(),
            ]);

            yield ['type' => 'content', 'text' => 'I had trouble applying that change. Could you tell me the specific value, for example "change my Cash ISA balance to £25,000"?'];
            yield from $this->emitTurnForState($user, $conversation, $currentStateId, $state, includeTransitionHeader: false);

            return;
        }

        $ack = trim($contentBuffer);

        // Honesty gate — if the model applied no update this turn, do NOT advance
        // AND do NOT surface its acknowledgement. grok will sometimes narrate a
        // change ("Got it — updated your balance to £X") without actually calling
        // a write tool; showing that ack would have Fyn claim a change it never
        // made. Suppress it, re-ask for the specific value, and stay on the edit
        // state so the next turn cannot falsely claim success.
        if ($toolCallsSeen === 0) {
            yield ['type' => 'content', 'text' => "I wasn't able to apply that change. Could you tell me the specific value, for example \"change my Cash ISA balance to £25,000\"?"];
            yield from $this->emitTurnForState($user, $conversation, $currentStateId, $state, includeTransitionHeader: false);

            return;
        }

        // A write tool ran — safe to surface the model's acknowledgement, then
        // advance to re-show Gate 2 (campaign_verify_navigate).
        if ($ack !== '') {
            yield ['type' => 'content', 'text' => $ack];
        }

        $this->recordProgress(
            $user,
            $currentStateId,
            ['verify_section' => $section, 'raw_message' => mb_substr($message, 0, 500)]
        );

        $nextStateId = OnboardingStateMachine::getNextStateId($currentStateId, $message, $user->refresh());
        if ($nextStateId === null) {
            return;
        }

        $user->onboarding_fyn_step = $nextStateId;
        $user->save();

        yield ['type' => 'onboarding_advance', 'from_step' => $currentStateId, 'to_step' => $nextStateId];

        $nextState = OnboardingStateMachine::getState($nextStateId);
        if ($nextState === null) {
            return;
        }

        yield from $this->emitTurnForState($user, $conversation, $nextStateId, $nextState);
    }

    /**
     * Build the system prompt for a verify-edit turn: the unified Fyn prompt
     * plus an explicit instruction to UPDATE the section's existing data, with
     * the section's records (and their ids) listed so update_record can target
     * the right row. Under legacy prompt mode the unified prompt is empty, so we
     * fall back to the asset-capture prompt as the base.
     */
    private function buildVerifyEditPrompt(User $user, string $section): string
    {
        $base = FynPromptMode::isUnified()
            ? FynSystemPrompt::text()
            : $this->promptBuilder->buildAssetCapturePrompt($user, $this->verifyEditFocus($section), null);

        $records = $this->verifyEditRecordContext($user, $section);

        // The capture bucket's Retraction clause already routes a correction to
        // update_record/update_profile; its only gap is that it "will not have
        // prior record ids in this turn". Supply those ids here as plain
        // REFERENCE DATA (not directive instructions — an instruction-style
        // override block trips the model's prompt-injection refusal). The
        // Retraction clause then has what it needs to update the existing row.
        $instruction = "\n\nReference — the user's existing ".$this->sectionLabelForEdit($section)
            ." (the user just said one of these was wrong and will correct it; update the matching record/field with update_record/update_profile rather than adding a new one):\n"
            .$records;

        return $base.$instruction;
    }

    /**
     * Human-readable list of the section's existing records (with ids/entity
     * types) for the verify-edit prompt, or a note that the section is stored
     * as profile fields (update_profile).
     */
    private function verifyEditRecordContext(User $user, string $section): string
    {
        switch ($section) {
            case 'savings':
                // Read through the canonical SavingsStore (store-boundary rule):
                // the director never queries App\Models\SavingsAccount directly.
                $rows = app(SavingsStore::class)->forUser($user)
                    ->map(fn ($a): string => "- entity_type: savings_account, entity_id: {$a->id} — \"{$a->account_name}\" at {$a->institution}, current balance £".number_format((float) $a->current_balance).($a->is_isa ? ' (ISA)' : ''))
                    ->implode("\n");

                return "Their savings accounts:\n".($rows !== '' ? $rows : '- (none on file)');

            case 'investments':
                $rows = app(InvestmentAccountStore::class)->forUser($user)
                    ->map(fn ($a): string => "- entity_type: investment_account, entity_id: {$a->id} — \"{$a->account_name}\"")
                    ->implode("\n");

                return "Their investment accounts:\n".($rows !== '' ? $rows : '- (none on file)');

            case 'pensions':
                $rows = app(PensionStore::class)->forUserByType($user, 'dc')
                    ->map(fn ($p): string => "- entity_type: dc_pension, entity_id: {$p->id} — \"{$p->scheme_name}\" (".($p->provider ?: 'provider unknown').'), current value £'.number_format((float) $p->current_fund_value))
                    ->implode("\n");

                return "Their pensions:\n".($rows !== '' ? $rows : '- (none on file)');

            case 'income':
                return 'Their income is stored on their profile (use update_profile): '
                    .'annual_employment_income £'.number_format((float) $user->annual_employment_income).', '
                    .'annual_self_employment_income £'.number_format((float) $user->annual_self_employment_income).', '
                    .'annual_rental_income £'.number_format((float) $user->annual_rental_income).', '
                    .'annual_dividend_income £'.number_format((float) $user->annual_dividend_income).'.';

            case 'expenditure':
                return 'Their expenditure is stored on their profile (use update_profile or set_expenditure): '
                    .'monthly_expenditure £'.number_format((float) $user->monthly_expenditure).' per month.';

            case 'giving':
                return 'Their charitable giving is stored on their profile (use update_profile).';

            case 'spouse':
                return "Their spouse's details are stored on the linked spouse profile (use update_profile).";

            default:
                return 'Update the relevant existing record or profile field for this section.';
        }
    }

    /** Onboarding capture focus for a verify-edit section (keeps the turn in capture mode). */
    private function verifyEditFocus(string $section): string
    {
        return match ($section) {
            'savings' => 'savings',
            'investments' => 'investment',
            'pensions' => 'retirement',
            default => 'savetax',
        };
    }

    /** Human label for a verify section, for the edit prompt. */
    private function sectionLabelForEdit(string $section): string
    {
        return [
            'income' => 'income', 'savings' => 'savings', 'investments' => 'investments',
            'pensions' => 'pensions', 'giving' => 'charitable giving',
            'spouse' => 'spouse details', 'expenditure' => 'expenditure',
        ][$section] ?? 'details';
    }

    /**
     * Emit a terminal turn that owns its own navigation route via
     * state.navigate_to (currently used by STATE_CAMPAIGN_TERMINAL → /tax-strategy).
     * Mirrors emitDoneTurn but uses the state's own navigate_to so a campaign
     * branch can land the user on a bespoke route while still triggering the
     * onboarding_complete chain that flips users.onboarding_completed.
     *
     * @param  array<string, mixed>  $state
     */
    private function emitTerminalNavigationTurn(
        User $user,
        AiConversation $conversation,
        string $stateId,
        array $state
    ): \Generator {
        $selection = $user->onboarding_fyn_selection ?? '';
        $nextRoute = (string) $state['navigate_to'];
        $celebration = OnboardingStateMachine::resolvePromptText($state, $user, '', $conversation);

        // The user taps a button to view their strategy rather than being
        // auto-navigated, so the "we've created your tax strategy" message lands
        // first. The route-carrying bubble navigates on tap (handled in the /m
        // chooseBubble + web handleQuickReplySelect) — no auto navigation event.
        $bubbles = [[
            'id' => 'view_strategy',
            'label' => 'Take me to my tax strategy',
            'route' => $nextRoute,
        ]];

        yield [
            'type' => 'quick_replies',
            'prompt_text' => $celebration,
            'bubbles' => $bubbles,
        ];

        $assistantMessage = $this->saveMessage(
            $conversation,
            'assistant',
            $celebration,
            ['metadata' => ['onboarding_step' => $stateId, 'bubbles' => $bubbles]]
        );

        yield [
            'type' => 'onboarding_complete',
            'selection' => $selection,
            'nextRoute' => $nextRoute,
        ];

        yield ['type' => 'done', 'message_id' => $assistantMessage->id];

        $user->onboarding_completed = true;
        $user->onboarding_completed_at = now();
        $user->onboarding_fyn_step = null;
        $user->onboarding_fyn_path = null;
        $user->onboarding_fyn_selection = null;
        $user->onboarding_fyn_context = null;
        $user->save();

        $this->recordProgress($user, $stateId, ['next_route' => $nextRoute]);
    }

    /**
     * Generate a one-sentence acknowledgment for a just-completed capture.
     *
     * The state machine previously advanced silently between capture and
     * the next prompt, which felt abrupt ("My wife is Jane, born 1983" →
     * immediately "Any children or dependants to add?"). A brief "Got it"
     * line between the two lands the previous answer before the next ask.
     *
     * Returns null for states that don't merit an ack (bubble confirmations,
     * terminal states) so we don't double up on noise.
     *
     * @param  array<string, mixed>  $interpretation
     */
    private function buildCaptureAck(User $user, string $stateId, array $interpretation): ?string
    {
        return match ($stateId) {
            OnboardingStateMachine::STATE_BASE_SPOUSE => $this->spouseAck($user),
            OnboardingStateMachine::STATE_BASE_DEPENDANTS_DETAIL => $this->dependantsAck($user),
            OnboardingStateMachine::STATE_BASE_EMPLOYMENT => 'Thanks — I\'ve noted your work details.',
            OnboardingStateMachine::STATE_BASE_WORK => $this->incomeAck($user),
            OnboardingStateMachine::STATE_BASE_EXPENDITURE => 'Thanks — I\'ve noted your monthly spending.',
            OnboardingStateMachine::STATE_CAMPAIGN_CHARITABLE_GIVING => $this->charitableGivingAck($user),
            default => null,
        };
    }

    /**
     * "What we've heard" recap after the income capture — echoes the gross
     * annual figure so the next question (the savings/ISA section) reads as a
     * fresh bubble rather than an abrupt jump.
     */
    private function incomeAck(User $user): string
    {
        $income = (float) ($user->annual_employment_income ?? 0)
            + (float) ($user->annual_self_employment_income ?? 0);
        if ($income <= 0) {
            return 'Got it — thanks.';
        }

        return sprintf('Got it — £%s a year, noted.', number_format($income, 0));
    }

    /**
     * Gift Aid is the one campaign capture with neither a delegated-LLM ack
     * nor an immediate strategy turn after it — without an ack the flow
     * jumps straight into the spouse section and feels abrupt.
     */
    private function charitableGivingAck(User $user): string
    {
        $amount = (float) ($user->annual_charitable_donations ?? 0);
        if ($amount <= 0) {
            return 'Got it — no Gift Aid donations.';
        }

        return sprintf('Recorded — around £%s a year through Gift Aid.', number_format($amount, 0));
    }

    private function spouseAck(User $user): string
    {
        $spouse = FamilyMember::where('user_id', $user->id)
            ->where('relationship', 'spouse')
            ->latest()
            ->first();

        if ($spouse === null || $spouse->first_name === null) {
            return 'Got it — your spouse is now on file.';
        }

        return "Got it — I've added {$spouse->first_name} and linked the two of you.";
    }

    private function dependantsAck(User $user): string
    {
        $count = FamilyMember::where('user_id', $user->id)
            ->whereIn('relationship', ['child', 'other_dependent'])
            ->count();

        if ($count === 0) {
            return 'Got it.';
        }
        if ($count === 1) {
            return 'Got it — 1 dependant added.';
        }

        return "Got it — {$count} dependants added.";
    }

    /**
     * B-1 — synthesize tool calls for multi-entity messages the LLM dropped.
     *
     * Runs AFTER the LLM's asset_capture stream has finished. Feeds the
     * user's original message to AssetCaptureEntityExtractor, compares the
     * extracted entity set to the fill_form payloads the LLM actually
     * emitted, and yields synthetic tool_use / fill_form / tool_use events
     * for every entity the LLM missed. Those synthetic events flow into
     * the aiFormFill Vuex queue exactly the same way as LLM-emitted ones,
     * so each missing entity still drives a form navigation + auto-save
     * on the frontend.
     *
     * The extractor is conservative — it only fires when it's confident
     * about both the entity type and its identity, so a completely
     * unrecognised phrasing degrades gracefully to "the LLM's single
     * tool call wins" rather than misclassifying.
     *
     * @param  list<array<string, mixed>>  $llmEmittedFills  fields[] from each
     *                                                       LLM-emitted fill_form
     * @return \Generator<array{type: string}>
     */
    private function emitGapFillToolCalls(
        User $user,
        string $selection,
        string $message,
        array $llmEmittedFills
    ): \Generator {
        $tool = $this->entityExtractor->toolNameForFocus($selection);
        if ($tool === null) {
            return;
        }

        try {
            $extracted = $this->entityExtractor->extractForFocus($selection, $message);
            $missing = $this->entityExtractor->findMissing($selection, $extracted, $llmEmittedFills, $user);
        } catch (\Throwable $e) {
            Log::warning('[OnboardingChatDirector] Gap-fill extraction failed', [
                'user_id' => $user->id,
                'selection' => $selection,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($missing === []) {
            return;
        }

        Log::info('[OnboardingChatDirector] Gap-fill firing for dropped entities', [
            'user_id' => $user->id,
            'selection' => $selection,
            'llm_emitted' => count($llmEmittedFills),
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
                Log::error('[OnboardingChatDirector] Gap-fill tool execution failed', [
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
                // Handler refused. Don't propagate a bad fill_form to the
                // frontend — just close out the tool_use status so the UI
                // doesn't think it's still running.
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

    /**
     * Unified mode: the system prompt is the static FynSystemPrompt and the
     * onboarding focus is carried separately so HasAiChat can build the
     * capture-turn context. Legacy mode: the verbatim asset-capture prompt.
     *
     * @return array{0:string,1:?string} [systemPrompt, onboardingFocusOrNull]
     */
    private function resolveUnifiedRestrictedPrompt(User $user, string $selection, ?AiConversation $conversation = null): array
    {
        if (FynPromptMode::isUnified()) {
            return [FynSystemPrompt::text(), $selection];
        }

        return [$this->promptBuilder->buildAssetCapturePrompt($user, $selection, $conversation), null];
    }

    /**
     * A1 — does the user's message ask a question? Single heuristic shared by
     * both capture handlers (delegated asset capture + grouped_extract) so the
     * two call sites cannot drift. A literal "?" counts, and so does a leading
     * interrogative/imperative ("explain salary sacrifice please", "what is
     * a SIPP") — users routinely drop the question mark.
     */
    private function userAskedQuestion(string $message): bool
    {
        return str_contains($message, '?')
            || preg_match('/^(explain|what|why|how|when|tell me|define|describe)\b/i', trim($message)) === 1;
    }

    /**
     * Decide whether a (possibly question-bearing) message also carries a
     * substantive answer to the scripted prompt. Used by the
     * `advance_on_answered_question` gate so a linear delegated step advances
     * when the user answered AND asked ("3% and it's matched. What's salary
     * sacrifice?") but re-asks on a bare question with no answer ("What's
     * salary sacrifice?").
     *
     * Substantive = any figure/percentage/currency, OR a clear yes/no answer,
     * OR meaningful prose before the first interrogative clause.
     */
    private function messageHasSubstantiveAnswer(string $message): bool
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return false;
        }

        // A number, percentage, or currency amount is always an answer.
        if (preg_match('/\d/', $trimmed) === 1) {
            return true;
        }

        $lower = mb_strtolower($trimmed);

        // Explicit yes/no/none answers to a "do you…?" scripted prompt. Kept
        // deliberately tight to genuine answer signals — topic nouns (e.g.
        // "salary sacrifice") are excluded because they appear inside questions
        // ABOUT the topic, which are not answers.
        $answerTokens = [
            'yes', 'yep', 'yeah', 'no ', ' no.', 'none', 'nope', 'nothing',
            "don't have", 'do not have', 'not got', "haven't got",
            "it's matched", 'is matched', 'and matched', 'employer matches',
            'i contribute', 'i pay', 'i put in',
        ];
        foreach ($answerTokens as $token) {
            if (str_contains($lower, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * FR-M14 — strip off-script sentences from an asset_capture content
     * event. Splits the text on sentence terminators (`.`, `!`, `?`, newline)
     * and drops any sentence that poses a question (with or without a `?`
     * — any `?` in the sentence is disqualifying) or mentions a topic
     * outside the current selection's scope. Protection and estate
     * selections are permissive because their tool lists legitimately
     * reference income / property / mortgage concepts; every other
     * selection (family, savings, investment, retirement, business,
     * goals, budgeting) is strict.
     *
     * Returns the rejoined surviving sentences, or '' when nothing
     * survived the filter.
     *
     * A1 — when $allowAnswer is true the user asked a direct question, so the
     * model is permitted to answer it before resuming capture (QUESTION
     * EXCEPTION). On an answer turn the question-mark and off-script-topic
     * strips are relaxed (a definitional answer legitimately re-asks the
     * capture question and may mention income/pension concepts), but the
     * personal-figures rule stays ACTIVE: the model must never quote or
     * compute the user's own numbers in a capture turn. With $allowAnswer
     * false (the default) behaviour is byte-identical to the original
     * FR-M14 filter — the personal-figures rule does not run, so the
     * established off-script path is unchanged.
     */
    private function filterOffScriptContent(string $text, string $selection, bool $allowAnswer = false): string
    {
        if ($text === '') {
            return '';
        }

        $allowOffScriptTerms = in_array($selection, ['protection', 'estate'], true);

        // Split after sentence-ending punctuation or newlines so each
        // chunk is a self-contained sentence. Empty chunks (e.g. blank
        // paragraph separators) are trimmed below.
        $chunks = preg_split('/(?<=[.!?\n])/u', $text);
        if ($chunks === false) {
            return $text;
        }

        $kept = [];
        foreach ($chunks as $chunk) {
            $trimmed = trim($chunk);
            if ($trimmed === '') {
                continue;
            }

            // A1 — personal-figures rule. Even inside the QUESTION EXCEPTION
            // the model must never quote/compute the user's own numbers in a
            // capture turn (FCA — no figures-based personal advice mid-capture).
            // Catches £ amounts, 4+ digit bare numbers, k-shorthand ("2.2k"),
            // and written-out "pounds"/"GBP". Statutory definitional figures
            // are carved out — naming an allowance/limit/threshold/band ("the
            // ISA allowance is £20,000") is definition, not personal advice.
            if ($allowAnswer
                && preg_match('/£\s?\d|\b\d{4,}\b|\b\d+(?:\.\d+)?k\b|\bpounds?\b|\bgbp\b/iu', $trimmed) === 1
                && preg_match('/\b(allowance|limit|threshold|nil[- ]rate|band)\b/i', $trimmed) !== 1
            ) {
                continue;
            }

            // Questions are never legitimate on an asset_capture turn — UNLESS
            // the user asked one, in which case the answer turn may re-ask the
            // capture question (QUESTION EXCEPTION).
            if (! $allowAnswer && str_contains($trimmed, '?')) {
                continue;
            }

            // Off-script topics (property/mortgage/income/…) are stripped on a
            // normal capture turn, but a definitional answer to the user's
            // question may legitimately reference those concepts, so the rule
            // is relaxed on an answer turn.
            if (! $allowAnswer && ! $allowOffScriptTerms && preg_match(
                '/\b(propert(?:y|ies)|mortgages?|rents?|incomes?|homes?|address(?:es)?|ownership|valuations?)\b/i',
                $trimmed
            ) === 1) {
                continue;
            }

            $kept[] = $trimmed;
        }

        return implode(' ', $kept);
    }

    /**
     * A2 — collapse the repeated short acks the model emits once per
     * agent-loop pass ("Got it — recording those now.Recorded." family).
     * On each continuation the model re-narrates its acknowledgment and the
     * buffered prose stacks; the May-18 captureTurnCompleteDirective dampens
     * this but does not eliminate it.
     *
     * A consecutive sentence is dropped when, against its predecessor, it is:
     *   (a) identical (case-insensitive); or
     *   (b) a short (<=6 words) textual prefix-duplicate of it
     *       ("Recorded the ISA." + "Recorded."); or
     *   (c) a bare standalone acknowledgment ("Recorded.", "Got it.",
     *       "Done.", "Noted.") following ANY prior sentence — a bare ack
     *       carries no information regardless of what preceded it; the
     *       closed set in isBareAck() never matches informative content.
     *
     * The split pattern uses `\s*` (not `\s+`) so "now.Recorded." splits into
     * two sentences despite the missing space. Legitimate multi-sentence
     * answers survive untouched: a substantive follow-on ("It saves Y.", "Now
     * your savings outside an ISA.") is neither a prefix-duplicate nor a bare
     * ack, and the new record-stating ack ("Recorded — two ISAs …") is long
     * enough never to qualify as the short predecessor in (c).
     */
    private function dedupeAckSentences(string $text): string
    {
        // Split after sentence punctuation followed by whitespace OR directly
        // by an uppercase/£ start ("now.Recorded." has no space). A full stop
        // followed by a digit or lowercase is NOT a boundary — "3.25%" and
        // "e.g. rates" must survive intact (live-browser regression 2026-06-11:
        // the old `\s*` split decimals and re-joined them as "3. 25%").
        $sentences = preg_split('/(?<=[.!?])\s+|(?<=[.!?])(?=[A-Z£])/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $deduped = [];
        foreach ($sentences as $s) {
            $prev = $deduped === [] ? null : end($deduped);
            if ($prev !== null && (strcasecmp($prev, $s) === 0
                || (str_word_count($s) <= 6 && str_word_count($prev) <= 6
                    && (str_starts_with(strtolower($prev), strtolower(rtrim($s, '.!?')))
                        || str_starts_with(strtolower($s), strtolower(rtrim($prev, '.!?')))))
                || $this->isBareAck($s))) {
                continue;
            }
            $deduped[] = $s;
        }

        return implode(' ', $deduped);
    }

    /**
     * A2 — is the sentence a bare standalone acknowledgment with no payload?
     * The closed re-narration family the model stacks ("Recorded.", "Got it.",
     * "Done.", "Noted.", "Got it — recording those now."). Matched on the
     * sentence's lowercased alphabetic content so trailing punctuation, the
     * em-dash, and casing don't matter. A record-stating ack that names what
     * was captured ("Recorded — two ISAs totalling £22,000.") carries extra
     * words and is NOT bare, so it never matches.
     */
    private function isBareAck(string $sentence): bool
    {
        $core = strtolower(trim(preg_replace('/[^\p{L}\s]+/u', ' ', $sentence) ?? ''));
        $core = trim(preg_replace('/\s+/', ' ', $core) ?? '');

        return in_array($core, [
            'recorded',
            'got it',
            'done',
            'noted',
            'got it recording those now',
        ], true);
    }

    // ─── Terminal state ───────────────────────────────────────────────────

    /**
     * Emit the done turn: celebration message, navigation event, and
     * onboarding_complete summary. Marks the user as onboarded and clears
     * onboarding_fyn_step so subsequent Fyn messages go through the normal
     * CoordinatingAgent path.
     */
    private function emitDoneTurn(User $user, AiConversation $conversation): \Generator
    {
        $selection = $user->onboarding_fyn_selection ?? '';
        $visitedFocuses = (array) ($user->onboarding_fyn_context['visited_focuses'] ?? []);

        // Route: single focus → module page, multi-focus → dashboard
        $nextRoute = count($visitedFocuses) > 1
            ? '/dashboard'
            : $this->routeForSelection($selection);

        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_DONE) ?? [];
        $celebration = OnboardingStateMachine::resolvePromptText($state, $user);

        yield ['type' => 'content', 'text' => $celebration];

        $assistantMessage = $this->saveMessage(
            $conversation,
            'assistant',
            $celebration,
            ['metadata' => ['onboarding_step' => OnboardingStateMachine::STATE_DONE]]
        );

        yield [
            'type' => 'navigation',
            'route_path' => $nextRoute,
            'description' => 'Your '.($selection !== '' ? $selection : 'module').' dashboard',
        ];

        yield [
            'type' => 'onboarding_complete',
            'selection' => $selection,
            'nextRoute' => $nextRoute,
        ];

        yield ['type' => 'done', 'message_id' => $assistantMessage->id];

        // Finalise user state. Clear ALL the onboarding_fyn_* scratch
        // columns so any future re-open of the chat reads the user as
        // fully complete and drops straight into the normal Fyn chat
        // path. The onboarding_progress rows are the audit trail; the
        // user row is runtime state only.
        $user->onboarding_completed = true;
        $user->onboarding_completed_at = now();
        $user->onboarding_fyn_step = null;
        $user->onboarding_fyn_path = null;
        $user->onboarding_fyn_selection = null;
        $user->onboarding_fyn_context = null;
        $user->save();

        $this->recordProgress($user, OnboardingStateMachine::STATE_DONE, ['next_route' => $nextRoute]);

        ConversationSummariserJob::dispatch($conversation->id);
    }

    private function routeForSelection(string $selection): string
    {
        return match ($selection) {
            'savings' => '/net-worth/cash',
            'investment' => '/net-worth/investments',
            'retirement' => '/net-worth/retirement',
            'protection' => '/protection',
            'estate' => '/estate',
            'family' => '/valuable-info?section=letter',
            'business' => '/net-worth/business',
            'goals' => '/goals',
            'budgeting' => '/dashboard',
            default => '/dashboard',
        };
    }

    // ─── Shared helpers ───────────────────────────────────────────────────

    private function recordProgress(User $user, string $stateId, mixed $stepData): void
    {
        try {
            OnboardingProgress::create([
                'user_id' => $user->id,
                'focus_area' => $user->onboarding_fyn_selection ?? '__setup__',
                'step_name' => $stateId,
                'step_data' => is_array($stepData) ? $stepData : ['value' => $stepData],
                'completed' => true,
                'completed_at' => now(),
            ]);

            // Gamification: award points once per completed onboarding/savetax
            // step. recordProgress is the single persistence seam every path
            // (interpret, skip, parking-hydrate, grouped extract, asset
            // capture, terminal, done) funnels through, so awarding here covers
            // every persisted answer. Dedup key onboarding:{stateId} ensures
            // re-answering the same step does not re-award. PointsService::award
            // is preview-safe and never throws.
            app(PointsService::class)->award(
                $user,
                'onboarding',
                "onboarding:{$stateId}",
                (int) config('gamification.points.onboarding_answer'),
                ['step' => $stateId],
            );
        } catch (\Throwable $e) {
            // Progress logging is best-effort — never break the flow
            Log::warning('[OnboardingChatDirector] Progress record failed', [
                'user_id' => $user->id,
                'state' => $stateId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function saveMessage(
        AiConversation $conversation,
        string $role,
        string $content,
        array $extra = []
    ): AiMessage {
        /** @var AiMessage $message */
        $message = $conversation->messages()->create(array_merge([
            'role' => $role,
            'content' => $content,
        ], $extra));

        return $message;
    }

    private function errorEvent(string $text): array
    {
        return ['type' => 'content', 'text' => $text];
    }

    /**
     * Resolve a state id from a state config array by matching against the
     * known states map. Used when we have the array but not the id (for
     * example after getState() returns). Returns empty string on miss.
     */
    private function resolveStateId(array $state): string
    {
        foreach (OnboardingStateMachine::states() as $id => $candidate) {
            if ($candidate === $state) {
                return $id;
            }
        }

        return '';
    }

    /**
     * Two-Fyn inline capture: handle a mid-onboarding turn where the user
     * volunteered data that needs to be persisted via create_/update_ tools.
     *
     * Strips layout/quick_reply events from the stream so the handoff is
     * invisible to the frontend (INV-2.4.1, INV-2.4.2). Tracks fill_form
     * events emitted by the LLM so the extractor-driven gap-fill below
     * can dedup against them. After the LLM stream completes, runs the
     * multi-entity gap-fill on every focus inferred from the CaptureContext.
     *
     * @return \Generator<array<string, mixed>>
     */
    public function handleInlineCapture(
        User $user,
        AiConversation $conversation,
        string $message,
        CaptureContext $context,
        ?string $currentRoute = null,
    ): \Generator {
        $allowedTools = $this->captureToolSet($context);

        // Unified prompt seam: handleInlineCapture IS an asset-capture turn
        // (the advice-mode write handoff target — both the deterministic
        // AdviceFyn route and the LLM delegate_to_capture path land here).
        // HasAiChat::injectUnifiedTurnContext keys the CAPTURE bucket off the
        // agent's unifiedOnboardingFocus; without it the turn is framed as
        // advice, FynCaptureTurnInstructions is never injected, and the model
        // falls back to the security refusal instead of calling create_*.
        // Mirror handleAssetCaptureTurn: derive the focus from the
        // CaptureContext and carry it for the duration of the turn (no-op
        // under legacy — the property is only read on the unified path).
        // The `?? 'savings'` fallback is the deflection guarantee: a turn that
        // reached handleInlineCapture is a write the classifier or the LLM
        // delegate_to_capture path has ALREADY cleared, so it must stay in
        // capture mode even when the entity type has no module focus (e.g. an
        // LLM-emitted entity the map below doesn't know). A null focus here
        // demotes the turn to advice mode, the CAPTURE bucket is dropped, and
        // the model deflects with the security refusal (June13 §6c).
        $unifiedFocus = FynPromptMode::isUnified()
            ? ($this->inferFocusesFromEntityTypes($context->entityTypes)[0] ?? 'savings')
            : null;

        /** @var list<array<string, mixed>> $llmEmittedFills */
        $llmEmittedFills = [];

        /** @var list<array{type: string, id: int|string|null, name: string}> $recordsCreated */
        $recordsCreated = [];

        // S0.5.t: persistUserMessage MUST be false — the outer Advice Fyn
        // chat() turn that emitted delegate_to_capture already saved the
        // user message. Re-saving from inside the inline-capture turn would
        // produce a duplicate row in ai_messages. The streamed turn (and its
        // unified-focus set/clear) runs through FynLoop::stream so the focus
        // and the stream that reads it share one CoordinatingAgent instance.
        $generator = $this->fynLoop->stream(
            $user,
            $conversation,
            $message,
            $currentRoute,
            persona: 'data_capture',
            allowedTools: $allowedTools,
            persistUserMessage: false,
            unifiedFocus: $unifiedFocus,
        );

        foreach ($generator as $event) {
            $type = $event['type'] ?? '';

            if (in_array($type, ['onboarding_layout_change', 'quick_replies'], true)) {
                continue;
            }

            if ($type === 'fill_form') {
                $llmEmittedFills[] = (array) ($event['fields'] ?? []);
            }

            // Track every record persisted by a create_* / direct-write handler
            // so the closing capture_complete event carries the full list.
            if ($type === 'entity_created') {
                $recordsCreated[] = [
                    'type' => (string) ($event['entity_type'] ?? ''),
                    'id' => $event['entity_id'] ?? null,
                    'name' => (string) ($event['name'] ?? ''),
                ];
            }

            yield $event;
        }

        // Gamification: the inline-capture path (advice-mode write handoff, incl.
        // the savetax campaign onboarding) is the seam recordProgress does NOT
        // run through, so without this the per-answer onboarding award never
        // fires for savetax users (they have onboarding_fyn_step = null and so
        // never enter the bubble flow). Created records already award via the
        // AwardsDataEntryPoints model observers; this covers the profile-field
        // answers (date of birth, marital status, income, etc.) that update
        // users.* and so emit no created event. Award once per distinct captured
        // answer — dedup on a content signature so a retry of the same answer
        // does not re-award, while each new answer levels the user up "as they
        // go". PointsService::award is preview-safe and never throws.
        if ($llmEmittedFills !== [] || $recordsCreated !== []) {
            $capturedSignature = md5((string) json_encode([
                'fills' => $llmEmittedFills,
                'records' => array_map(
                    static fn (array $r): string => ($r['type'] ?? '').':'.($r['id'] ?? ''),
                    $recordsCreated,
                ),
            ]));
            app(PointsService::class)->award(
                $user,
                'onboarding',
                "onboarding:inline:{$capturedSignature}",
                (int) config('gamification.points.onboarding_answer'),
                ['inline' => true],
            );
        }

        yield from $this->emitGapFillFromCaptureContext(
            $user,
            $conversation,
            $context,
            $message,
            $llmEmittedFills,
        );

        // Emit a single closing capture_complete event so AiChatPanel.vue can
        // render the rich record-card bubble (one card per record) instead of
        // leaving the user with the bare entity_created mini-bubble plus the
        // assistant's prose. Suppressed when nothing was actually written —
        // an empty card would just be visual noise.
        if ($recordsCreated !== []) {
            yield [
                'type' => 'capture_complete',
                'summary' => $this->buildCaptureCompleteSummary($recordsCreated),
                'records_created' => $recordsCreated,
            ];
        }
    }

    /**
     * One-line summary heading for the capture_complete record-card bubble.
     * The card itself shows each record on its own row; this is the bold
     * prefix above them.
     *
     * @param  list<array{type: string, id: int|string|null, name: string}>  $records
     */
    private function buildCaptureCompleteSummary(array $records): string
    {
        if (count($records) === 1) {
            return 'Saved to your records';
        }

        return sprintf('Saved %d records', count($records));
    }

    /**
     * Tool whitelist for an inline-capture turn. Matches the post-collapse
     * data_capture scope — every create_/update_ write the user can trigger
     * mid-onboarding plus a handful of update helpers.
     *
     * @return list<string>
     */
    private function captureToolSet(CaptureContext $context): array
    {
        return [
            'create_savings_account', 'create_investment_account', 'create_holding',
            'create_pension', 'create_property', 'create_mortgage',
            'create_protection_policy', 'create_family_member',
            'create_goal', 'create_life_event', 'create_trust',
            'create_will', 'update_will',
            'create_power_of_attorney', 'update_power_of_attorney',
            'create_asset', 'create_liability', 'create_estate_gift',
            'create_chattel', 'create_business_interest',
            'update_record', 'update_profile', 'set_expenditure',
            // S0.5.r — what-if scenarios persist a WhatIfScenario row, so
            // they route through the handoff like every other create_*.
            'create_what_if_scenario',
            // S0.5.r — delete is allowed in inline-capture so the user can
            // ask Advice Fyn to remove a record and have the handoff dispatch
            // delete_record for them.
            'delete_record',
            // SaveTax campaign sections 4-6 — used during the campaign-only
            // post-expenditure state-machine branch. Always whitelisted; the
            // state machine itself gates which states can call which tool.
            'capture_salary_sacrifice',
            'capture_spouse_work_status',
            'capture_spouse_household_data',
            'capture_spouse_non_working_assets',
        ];
    }

    /**
     * Deterministic multi-entity gap-fill — ported from the retired persona
     * invoker. Runs AssetCaptureEntityExtractor on the user's message once
     * per focus inferred from the CaptureContext, compares against the
     * fill_form events the LLM already emitted this turn, and synthesises
     * tool_use + fill_form + tool_use events for every entity the LLM
     * dropped.
     *
     * Silently drops entity types the extractor does not cover (goals,
     * life events, property). Per-focus extractor failures are logged and
     * skipped rather than aborting the turn.
     *
     * @param  list<array<string, mixed>>  $llmEmittedFills
     * @return \Generator<array<string, mixed>>
     */
    private function emitGapFillFromCaptureContext(
        User $user,
        AiConversation $conversation,
        CaptureContext $context,
        string $message,
        array $llmEmittedFills,
    ): \Generator {
        $focuses = $this->inferFocusesFromEntityTypes($context->entityTypes);

        foreach ($focuses as $focus) {
            yield from $this->runExtractorForFocus($user, $focus, $message, $llmEmittedFills);
        }
    }

    /**
     * Run the extractor for a single focus and emit gap-fill events for any
     * entities the LLM dropped.
     *
     * @param  list<array<string, mixed>>  $llmEmittedFills
     * @return \Generator<array<string, mixed>>
     */
    private function runExtractorForFocus(
        User $user,
        string $focus,
        string $message,
        array $llmEmittedFills,
    ): \Generator {
        $tool = $this->entityExtractor->toolNameForFocus($focus);
        if ($tool === null) {
            return;
        }

        try {
            $extracted = $this->entityExtractor->extractForFocus($focus, $message);
            $missing = $this->entityExtractor->findMissing($focus, $extracted, $llmEmittedFills, $user);
        } catch (\Throwable $e) {
            Log::warning('[OnboardingChatDirector] Inline-capture gap-fill extraction failed', [
                'user_id' => $user->id,
                'focus' => $focus,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($missing === []) {
            return;
        }

        Log::info('[OnboardingChatDirector] Inline-capture gap-fill firing for dropped entities', [
            'user_id' => $user->id,
            'focus' => $focus,
            'llm_emitted' => count($llmEmittedFills),
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
                Log::error('[OnboardingChatDirector] Inline-capture gap-fill tool execution failed', [
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

    /**
     * Translate CaptureContext::entityTypes into extractor focus strings.
     * Silently drops entity types the extractor does not cover (goal,
     * life_event, property).
     *
     * @param  list<string>  $entityTypes
     * @return list<string>
     */
    private function inferFocusesFromEntityTypes(array $entityTypes): array
    {
        $focuses = [];
        foreach ($entityTypes as $entity) {
            $focus = match ($entity) {
                'protection_policy', 'life_insurance_policy', 'critical_illness_policy', 'income_protection_policy' => 'protection',
                'savings_account', 'cash_account' => 'savings',
                'dc_pension', 'db_pension', 'pension' => 'retirement',
                'investment_account', 'holding' => 'investment',
                'goal', 'life_event' => 'goals',
                'business_interest', 'business' => 'business',
                // Net-worth + estate-planning records share the Estate focus —
                // its tool hint (create_property/asset/liability/gift/chattel)
                // is the closest match and, critically, keeps the turn in
                // capture mode so FynCaptureTurnInstructions are injected.
                'property', 'mortgage', 'asset', 'liability',
                'estate_gift', 'gift', 'chattel',
                'will', 'power_of_attorney', 'trust' => 'estate',
                default => null,
            };
            if ($focus !== null && ! in_array($focus, $focuses, true)) {
                $focuses[] = $focus;
            }
        }

        return $focuses;
    }

    /**
     * Resolve and record the active onboarding workflow procedure_id@version
     * into the request-scoped ProceduralVersionHolder. Degrades silently — a
     * missing/malformed corpus records nothing and never throws.
     */
    private function recordActiveWorkflowVersion(): void
    {
        try {
            $procedure = app(ProceduralCorpusLoader::class)
                ->load()
                ->active('onboarding.workflow.fyn-onboarding', asOf: Carbon::now());

            if ($procedure !== null) {
                $this->proceduralVersions->add($procedure->procedureId, $procedure->version);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
