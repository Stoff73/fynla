<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\FamilyMember;
use App\Models\OnboardingProgress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the Fyn-driven onboarding flow — the backend-authoritative
 * state machine described in fynOnboardFix.md §2 + §7.
 *
 * The director owns every turn except asset_capture. For asset_capture it
 * delegates to CoordinatingAgent::chat() with a restricted system prompt
 * (OnboardingPromptBuilder) and the focus-filtered create_* tool list.
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
    public function __construct(
        private readonly CoordinatingAgent $coordinatingAgent,
        private readonly OnboardingPromptBuilder $promptBuilder,
    ) {}

    /**
     * Backend-initiated turn 1 — emits the path_choice bubbles with no
     * preceding user message. Called from AiChatController::startOnboarding
     * after a fresh AiConversation row has been created and the user's
     * onboarding_fyn_step set to 'path_choice'.
     *
     * @return \Generator<array{type: string}>
     */
    public function emitFirstTurn(User $user, AiConversation $conversation): \Generator
    {
        $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_PATH_CHOICE);
        if ($state === null) {
            yield $this->errorEvent('Onboarding is temporarily unavailable.');

            return;
        }

        yield from $this->emitTurnForState($user, $conversation, OnboardingStateMachine::STATE_PATH_CHOICE, $state);
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
        ?string $currentRoute = null
    ): \Generator {
        // Persist the user message immediately so the conversation history
        // reflects the real interaction even if the rest of this generator
        // fails.
        $this->saveMessage($conversation, 'user', $message);

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

        // Asset capture is the delegated turn.
        if (($state['turn_type'] ?? '') === 'delegated') {
            yield from $this->handleAssetCaptureTurn($user, $conversation, $message, $currentRoute);

            return;
        }

        // Grouped extraction turns (base_personal, base_spouse,
        // base_dependants_detail, base_work) delegate to Claude with a
        // narrow extraction tool, which writes the fields to the DB and
        // returns a capture receipt via SSE.
        if (($state['turn_type'] ?? '') === 'grouped_extract') {
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

        // Record the completed step in onboarding_progress for audit/resume.
        $this->recordProgress($user, $currentStateId, $interpretation['captured_value'] ?? null);

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

        yield from $this->emitTurnForState($user, $conversation, $nextStateId, $nextState);
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

        $conversation = AiConversation::forUser($user->id)
            ->where('title', 'Onboarding')
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
     */
    private function emitTurnForState(
        User $user,
        AiConversation $conversation,
        string $stateId,
        array $state,
        bool $includeTransitionHeader = true
    ): \Generator {
        $turnType = $state['turn_type'] ?? 'free_text';
        $promptText = OnboardingStateMachine::resolvePromptText($state, $user);

        if ($turnType === 'bubbles') {
            $bubbles = $this->filterBubbles($user, $stateId, $state);

            $event = [
                'type' => 'quick_replies',
                'prompt_text' => $promptText,
                'bubbles' => $bubbles,
            ];
            yield $event;

            $assistantMessage = $this->saveMessage(
                $conversation,
                'assistant',
                $promptText,
                ['metadata' => ['bubbles' => $bubbles, 'onboarding_step' => $stateId]]
            );
        } else {
            // free_text / terminal — plain content event
            yield ['type' => 'content', 'text' => $promptText];

            $assistantMessage = $this->saveMessage(
                $conversation,
                'assistant',
                $promptText,
                ['metadata' => ['onboarding_step' => $stateId]]
            );
        }

        yield [
            'type' => 'done',
            'message_id' => $assistantMessage->id,
        ];
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
        $firstName = $this->extractFirstName($rawText);
        $dob = $this->extractDate($rawText);
        $income = OnboardingValueInterpreter::parseIncomeAmount(
            $this->extractPotentialAmount($rawText)
        );

        FamilyMember::create([
            'user_id' => $user->id,
            'household_id' => $user->household_id,
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
                'household_id' => $user->household_id,
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
                'household_id' => $user->household_id,
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

        $toolDefinitions = app(\App\Services\AI\AiToolDefinitions::class);
        // Match the active provider so the tools ship in the correct
        // format. xAI expects the OpenAI function-calling wrapper,
        // Anthropic expects the flattened input_schema shape.
        $provider = \Illuminate\Support\Facades\Cache::get(
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

        $systemPrompt = $this->buildGroupedExtractPrompt($user, $currentStateId, $toolName);

        $captureReceived = false;
        $captureDetails = [];

        try {
            $generator = $this->coordinatingAgent->chatWithPromptOverride(
                $user,
                $conversation,
                $message,
                $currentRoute,
                $systemPrompt,
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

                yield $event;
            }
        } catch (\Throwable $e) {
            Log::error('[OnboardingChatDirector] Grouped extract delegation failed', [
                'user_id' => $user->id,
                'state' => $currentStateId,
                'tool' => $toolName,
                'error' => $e->getMessage(),
            ]);

            yield [
                'type' => 'content',
                'text' => (string) ($state['retry_text'] ?? "Sorry, I couldn't read that. Could you try again?"),
            ];

            return;
        }

        if (! $captureReceived) {
            // Claude didn't call the tool or the handler errored. Stay
            // on the current state so the user can retry.
            yield [
                'type' => 'content',
                'text' => (string) ($state['retry_text'] ?? "Sorry, I didn't catch that. Could you try again?"),
            ];

            return;
        }

        $this->recordProgress($user, $currentStateId, $captureDetails);

        // Refresh the user so skip_if helpers on the next state see the
        // freshly-written columns.
        $user->refresh();

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

        yield [
            'type' => 'onboarding_advance',
            'from_step' => $currentStateId,
            'to_step' => $nextStateId,
        ];

        yield from $this->emitTurnForState($user, $conversation, $nextStateId, $nextState);
    }

    /**
     * Build the restricted system prompt for grouped-extract turns. Must
     * stay narrow — we do not want Claude to answer the user, we just
     * want it to call the single extraction tool with parsed fields.
     */
    private function buildGroupedExtractPrompt(User $user, string $stateId, string $toolName): string
    {
        $nameParts = explode(' ', (string) $user->name);
        $firstName = $nameParts[0] ?: 'there';

        $instructions = match ($toolName) {
            'capture_personal_details' => 'Extract the user\'s date of birth and marital status from their message. Map phrases exactly: "civil partnership" / "civil partner" → civil_partnership; "married" → married; "single" → single; "divorced" / "separated" → divorced; "widowed" → widowed.',
            'capture_spouse_details' => 'Extract the user\'s spouse or partner details. You need their first name, date of birth, and email address. If they mention an annual income, extract it too. Do NOT invent missing fields — if the user did not provide all three required fields, return an error.',
            'capture_dependants' => 'Extract a list of the user\'s dependants. Each entry needs an age and a relationship (child, parent, or other_dependent). First names are optional. Map phrases: "son", "daughter", "step-daughter", "step-son", "kid", "child" → child. "mother", "father", "mum", "dad", "mum-in-law", etc. → parent. Sibling, nephew, elderly relative, friend → other_dependent. If the user says "two kids aged 4 and 7" return two entries with relationship=child.',
            'capture_work_details' => 'Extract the user\'s employer or trade name, their role/position, and their gross annual income in GBP. Strip currency symbols and commas before returning the number. "75k" means 75000. Do not invent fields.',
            default => 'Extract the user\'s reply using the provided tool.',
        };

        return <<<PROMPT
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
    private function handleAssetCaptureTurn(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute
    ): \Generator {
        $selection = $user->onboarding_fyn_selection ?? 'savings';

        // Swap the coordinating agent's system prompt for this turn only.
        // We do this by calling chat() with a short-lived prompt override —
        // see CoordinatingAgent::chatWithPromptOverride() below.
        $restrictedPrompt = $this->promptBuilder->buildAssetCapturePrompt($user, $selection);
        $allowedTools = OnboardingPromptBuilder::toolsForFocus($selection);

        try {
            $generator = $this->coordinatingAgent->chatWithPromptOverride(
                $user,
                $conversation,
                $message,
                $currentRoute,
                $restrictedPrompt,
                $allowedTools,
                persistUserMessage: false, // already saved at top of handleUserMessage
            );

            foreach ($generator as $event) {
                yield $event;
            }
        } catch (\Throwable $e) {
            Log::error('[OnboardingChatDirector] Asset capture delegation failed', [
                'user_id' => $user->id,
                'selection' => $selection,
                'error' => $e->getMessage(),
            ]);

            yield [
                'type' => 'content',
                'text' => "I had trouble reading that. Could you try listing them one at a time?",
            ];

            return;
        }

        // Record the step in onboarding_progress (best-effort — tool calls
        // that actually created records already persisted their own rows)
        $this->recordProgress(
            $user,
            OnboardingStateMachine::STATE_ASSET_CAPTURE,
            ['selection' => $selection, 'raw_message' => mb_substr($message, 0, 500)]
        );

        // Advance the user's step to add_more for the next turn.
        $user->onboarding_fyn_step = OnboardingStateMachine::STATE_ADD_MORE;
        $user->save();

        yield [
            'type' => 'onboarding_advance',
            'from_step' => OnboardingStateMachine::STATE_ASSET_CAPTURE,
            'to_step' => OnboardingStateMachine::STATE_ADD_MORE,
        ];

        $nextState = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_ADD_MORE);
        if ($nextState !== null) {
            yield from $this->emitTurnForState($user, $conversation, OnboardingStateMachine::STATE_ADD_MORE, $nextState);
        }
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
}
