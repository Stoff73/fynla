<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Agents\CoordinatingAgent;
use App\Constants\QuerySchemas;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\ValueObjects\CaptureContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Post-onboarding Fyn — read-only half of the canonical Two-Fyn contract.
 *
 * AdviceFyn handles every post-onboarding chat turn. It exposes ZERO
 * write/create/edit/delete tools to the LLM. When the user asks AdviceFyn
 * to add, save, record, create, update, delete or remove any persistent
 * record, the LLM emits the internal `delegate_to_capture` tool. wrapStream
 * intercepts the resulting synthetic `handoff` SSE event and routes the
 * turn through OnboardingChatDirector::handleInlineCapture, which is the
 * ONLY state in the system that enters or edits information.
 *
 * The user never sees the handoff: the synthetic `handoff` event is
 * stripped before reaching the frontend (INV-2.4.1), and no
 * `persona_state_change` event is ever emitted.
 *
 * Source of truth: April/April24Updates/spec/00-canonical.md.
 */
final class AdviceFyn
{
    /**
     * S1.8 — Response-mode classifier (INV-2.3.1). Every `QuerySchemas`
     * constant maps to exactly one of three modes:
     *
     *  - `recommendation` — the turn invokes the engine (holistic or
     *    module-scoped). The LLM's text response carries the engine
     *    output; figures, breakdowns, and recommendations are produced
     *    via tool results validated by `ToolResultContract`.
     *  - `factual` — the turn answers from module services or static
     *    knowledge with no engine call.
     *  - `out_of_remit` — the turn short-circuits to the canonical
     *    refusal per INV-2.3.4.
     *
     * INCOME is the one advice-type constant in factual mode — it
     * answers from `get_tax_information(income_tax)` only, never
     * orchestrateAnalysis or a single-agent analyze().
     */
    private const RESPONSE_MODE_MAP = [
        QuerySchemas::PROTECTION_COVER => 'recommendation',
        QuerySchemas::PROTECTION_POLICY => 'recommendation',
        QuerySchemas::SAVINGS_EMERGENCY => 'recommendation',
        QuerySchemas::SAVINGS_ACCOUNTS => 'recommendation',
        QuerySchemas::SAVINGS_DEBT => 'recommendation',
        QuerySchemas::RETIREMENT_CONTRIBUTION => 'recommendation',
        QuerySchemas::RETIREMENT_READINESS => 'recommendation',
        QuerySchemas::RETIREMENT_DECUMULATION => 'recommendation',
        QuerySchemas::INVESTMENT_PORTFOLIO => 'recommendation',
        QuerySchemas::INVESTMENT_FEES => 'recommendation',
        QuerySchemas::INVESTMENT_TAX => 'recommendation',
        QuerySchemas::ESTATE_IHT => 'recommendation',
        QuerySchemas::ESTATE_PLANNING => 'recommendation',
        QuerySchemas::GOALS_PROGRESS => 'recommendation',
        QuerySchemas::TAX_OPTIMISATION => 'recommendation',
        QuerySchemas::PROPERTY => 'recommendation',
        QuerySchemas::HOLISTIC_HEALTH => 'recommendation',
        QuerySchemas::AFFORDABILITY => 'recommendation',
        QuerySchemas::INCOME => 'factual',
        QuerySchemas::GENERAL => 'factual',
        QuerySchemas::DATA_ENTRY => 'factual',
        QuerySchemas::NAVIGATION => 'factual',
        QuerySchemas::BILLING => 'factual',
        QuerySchemas::OUT_OF_REMIT => 'out_of_remit',
    ];

    /**
     * S1.8 — Engine call granularity (INV-2.3.6). Holistic queries call
     * `CoordinatingAgent::orchestrateAnalysis`; module-scoped queries
     * call the single `{Module}Agent::analyze` for their primary
     * module; factual queries make no engine call and answer from the
     * relevant module service directly (or static `QueryKnowledge`).
     *
     *  - `holistic` — only HOLISTIC_HEALTH. The single caller of
     *    `orchestrateAnalysis` per the canonical contract.
     *  - `module` — every advice-type query whose `MODULE_MAP` resolves
     *    to a single module, including TAX_OPTIMISATION and
     *    AFFORDABILITY (whose primary single-agent caller is the savings
     *    or tax module).
     *  - `factual` — INCOME, GENERAL, BILLING, DATA_ENTRY, NAVIGATION,
     *    OUT_OF_REMIT. No engine cycle is spent on these turns.
     */
    private const ENGINE_CALL_LEVEL_MAP = [
        QuerySchemas::HOLISTIC_HEALTH => 'holistic',
        QuerySchemas::PROTECTION_COVER => 'module',
        QuerySchemas::PROTECTION_POLICY => 'module',
        QuerySchemas::SAVINGS_EMERGENCY => 'module',
        QuerySchemas::SAVINGS_ACCOUNTS => 'module',
        QuerySchemas::SAVINGS_DEBT => 'module',
        QuerySchemas::RETIREMENT_CONTRIBUTION => 'module',
        QuerySchemas::RETIREMENT_READINESS => 'module',
        QuerySchemas::RETIREMENT_DECUMULATION => 'module',
        QuerySchemas::INVESTMENT_PORTFOLIO => 'module',
        QuerySchemas::INVESTMENT_FEES => 'module',
        QuerySchemas::INVESTMENT_TAX => 'module',
        QuerySchemas::ESTATE_IHT => 'module',
        QuerySchemas::ESTATE_PLANNING => 'module',
        QuerySchemas::GOALS_PROGRESS => 'module',
        QuerySchemas::TAX_OPTIMISATION => 'module',
        QuerySchemas::PROPERTY => 'module',
        QuerySchemas::AFFORDABILITY => 'module',
        QuerySchemas::INCOME => 'factual',
        QuerySchemas::GENERAL => 'factual',
        QuerySchemas::DATA_ENTRY => 'factual',
        QuerySchemas::NAVIGATION => 'factual',
        QuerySchemas::BILLING => 'factual',
        QuerySchemas::OUT_OF_REMIT => 'factual',
    ];

    /**
     * Tools that mutate persistent records — stripped from the advice
     * tool list. Every entry here has a corresponding handler in
     * OnboardingChatDirector::captureToolSet so the handoff path can
     * dispatch it.
     */
    private const WRITE_TOOLS = [
        'create_savings_account', 'create_investment_account', 'create_holding',
        'create_pension', 'create_property', 'create_mortgage',
        'create_protection_policy', 'create_asset', 'create_liability',
        'create_estate_gift', 'create_chattel', 'create_business_interest',
        'create_trust', 'create_family_member', 'create_will', 'update_will',
        'create_power_of_attorney', 'update_power_of_attorney',
        'update_record', 'delete_record', 'update_profile', 'set_expenditure',
        'capture_personal_details', 'capture_spouse_details',
        'capture_dependants', 'capture_work_details',
        // S0.5.r — every persistent record-creation tool now flows through
        // the delegate_to_capture handoff. No analytics carve-out:
        // create_what_if_scenario persists a WhatIfScenario row and must
        // therefore be written by Onboarding Fyn like every other create_*.
        'create_goal', 'create_life_event', 'create_what_if_scenario',
        // S0.5.t — strip navigate_to_page from advice mode. BS-14 caught the
        // LLM repeatedly choosing navigate_to_page as an escape hatch for
        // write intents (sending the user to a page so they fill the form
        // themselves), then fabricating "I've added X" success text. The
        // hardened <handoff_guidance> prompt did not deter it. Removing the
        // tool entirely eliminates the escape hatch — write intents now
        // only have one viable path: delegate_to_capture. Page navigation
        // remains user-initiated via the menu.
        'navigate_to_page',
    ];

    public function __construct(
        private readonly CoordinatingAgent $coordinatingAgent,
        private readonly AiToolDefinitions $toolDefinitions,
        private readonly XaiToolDefinitions $xaiToolDefinitions,
        private readonly QueryClassifier $queryClassifier,
        private readonly OnboardingChatDirector $onboardingChatDirector,
        private readonly WriteIntentClassifier $writeIntentClassifier,
        private readonly RecordDuplicateChecker $duplicateChecker,
        private readonly DuplicateAcknowledgement $duplicateAcknowledgement,
    ) {}

    public function handle(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute = null,
    ): \Generator {
        // S0.14 — short-circuit non-financial topics with the canonical
        // refusal. The classifier only flags out_of_remit when no advice
        // keyword fired, so financial questions that incidentally mention
        // a non-financial term still route through the normal advice path.
        $classification = $this->queryClassifier->classify($message, $currentRoute);

        // Fire response_mode + engine_call_level decisions for the eval trace.
        // Map-presence guards avoid throwing on out-of-remit / data-entry types
        // whose handlers below already cover those branches.
        $traceablePrimary = $classification['primary'] ?? null;
        if ($traceablePrimary !== null && isset(self::RESPONSE_MODE_MAP[$traceablePrimary])) {
            event(new \App\Events\Eval\AgentDecision(
                agent: 'AdviceFyn',
                decisionPoint: 'response_mode',
                outcome: self::RESPONSE_MODE_MAP[$traceablePrimary],
                context: ['primary' => $traceablePrimary],
                atMicrotime: microtime(true),
            ));
        }
        if ($traceablePrimary !== null && isset(self::ENGINE_CALL_LEVEL_MAP[$traceablePrimary])) {
            event(new \App\Events\Eval\AgentDecision(
                agent: 'AdviceFyn',
                decisionPoint: 'engine_call_level',
                outcome: self::ENGINE_CALL_LEVEL_MAP[$traceablePrimary],
                context: ['primary' => $traceablePrimary],
                atMicrotime: microtime(true),
            ));
        }

        if (($classification['primary'] ?? null) === QuerySchemas::OUT_OF_REMIT) {
            $context = $classification['detected_topic'] ?? 'General queries';
            $text = "I'm able to help you with your finances. {$context} is out of scope.";

            // Persist the user's message so the conversation transcript stays
            // honest — chatWithPromptOverride would normally do this, but
            // we're short-circuiting that path. Persist the assistant refusal
            // alongside it so the next turn's history loader sees both.
            $conversation->messages()->create([
                'role' => 'user',
                'content' => $message,
                'persona' => 'advice',
            ]);

            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $text,
                'persona' => 'advice',
            ]);

            yield ['type' => 'content', 'text' => $text];
            yield ['type' => 'done'];

            return;
        }

        // Server-side write-intent classifier — runs BEFORE the LLM stream.
        // If the user message clearly says "add / I have / we bought + a
        // policy / account / pension / etc." AND no matching record already
        // exists, route the turn through OnboardingChatDirector::handleInlineCapture
        // directly. This decouples the write path from LLM tool-call
        // reliability — grok-4-1-fast occasionally emits delegate_to_capture
        // as plain `<function_call>` text instead of using the structured
        // tool API, which made BS-11/14/17 flaky on the LLM-mediated path.
        // The classifier is conservative: ambiguous messages fall through
        // to the normal LLM advice flow.
        $intent = $this->writeIntentClassifier->classify($message);

        // Full-duplicate short-circuit: when the user reasserts records
        // that all already exist (RecordDuplicateChecker matches every
        // extracted entity to a recent DB row), we do NOT involve the
        // LLM. Its phrasing is non-deterministic and on the previous
        // walk it produced "Your X provides £Y of cover" — ambiguous
        // enough that a user who just typed "I have X" could read it
        // as "yes I added it" instead of "it was already on file"
        // (gaslighting). Build a deterministic acknowledgement from the
        // matching DB rows and yield it as plain content.
        if ($intent !== null && $this->duplicateChecker->alreadyExists($user, $intent, $message)) {
            $ack = $this->duplicateAcknowledgement->build($user, $intent, $message);

            $conversation->messages()->create([
                'role' => 'user',
                'content' => $message,
                'persona' => 'advice',
            ]);

            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $ack['text'],
                'persona' => 'advice',
            ]);

            Log::info('[AdviceFyn] Deterministic duplicate ack', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'entity_type' => $intent['entity_type'],
                'descriptor_count' => count($ack['descriptors']),
            ]);

            yield ['type' => 'content', 'text' => $ack['text']];
            yield ['type' => 'done'];

            return;
        }

        if ($intent !== null) {
            // Persist the user message ourselves — handleInlineCapture's
            // chatWithPromptOverride call uses persistUserMessage:false on
            // purpose (it normally rides on top of an Advice-Fyn turn that
            // already saved the user message). On this deterministic path
            // there IS no preceding advice turn, so we save it here.
            $conversation->messages()->create([
                'role' => 'user',
                'content' => $message,
                'persona' => 'advice',
            ]);

            $captureContext = CaptureContext::fromArray([
                'reason' => $intent['reason'],
                'entity_types' => [$intent['entity_type']],
                'fields_needed' => $intent['fields_needed'],
            ]);

            Log::info('[AdviceFyn] Deterministic write-intent routed', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'entity_type' => $intent['entity_type'],
                'matched_verb' => $intent['matched_verb'],
                'matched_entity_keyword' => $intent['matched_entity_keyword'],
            ]);

            yield from $this->onboardingChatDirector->handleInlineCapture(
                $user,
                $conversation,
                $message,
                $captureContext,
                $currentRoute,
            );

            yield ['type' => 'done'];

            return;
        }

        $allowedTools = $this->buildToolList($user);

        $upstream = $this->coordinatingAgent->chatWithPromptOverride(
            user: $user,
            conversation: $conversation,
            message: $message,
            currentRoute: $currentRoute,
            systemPromptOverride: null,
            allowedTools: $allowedTools,
            persistUserMessage: true,
            toolsListOverride: null,
            personaOverride: 'advice',
        );

        yield from $this->wrapStream($upstream, $user, $conversation, $message, $currentRoute);
    }

    /**
     * Consume the upstream advice-mode generator. When the LLM emits
     * `delegate_to_capture`, CoordinatingAgent yields a synthetic
     * `{type: 'handoff', handoff_type: 'delegate_to_capture', payload: ...}`
     * event. We intercept that event, build a CaptureContext from the
     * payload, and `yield from` OnboardingChatDirector::handleInlineCapture
     * into the same SSE stream so the user never sees the switch.
     *
     * The `handoff` event itself is dropped — INV-2.4.1 forbids it from
     * reaching the frontend.
     *
     * @param  \Generator<array<string, mixed>>  $upstream
     * @return \Generator<array<string, mixed>>
     */
    private function wrapStream(
        \Generator $upstream,
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute,
    ): \Generator {
        foreach ($upstream as $event) {
            $type = $event['type'] ?? '';
            $handoffType = $event['handoff_type'] ?? '';

            if ($type === 'handoff' && $handoffType === HandoffContract::DELEGATE_TO_CAPTURE) {
                $payload = (array) ($event['payload'] ?? []);

                try {
                    $context = CaptureContext::fromArray($payload);
                } catch (\InvalidArgumentException $e) {
                    Log::warning('[AdviceFyn] Malformed delegate_to_capture payload — dropping handoff', [
                        'user_id' => $user->id,
                        'conversation_id' => $conversation->id,
                        'error' => $e->getMessage(),
                        'payload_keys' => array_keys($payload),
                    ]);

                    continue;
                }

                // S0.5.t — observability: log when the LLM omitted `reason`
                // and CaptureContext::fromArray synthesised one from
                // entity_types. Tracking how often this fires tells us
                // whether the prompt-side fix to require `reason` has
                // landed with the model.
                if (! isset($payload['reason']) || trim((string) ($payload['reason'] ?? '')) === '') {
                    Log::notice('[AdviceFyn] delegate_to_capture payload missing reason — auto-synthesised', [
                        'user_id' => $user->id,
                        'conversation_id' => $conversation->id,
                        'synthesised_reason' => $context->reason,
                        'entity_types' => $context->entityTypes,
                    ]);
                }

                yield from $this->onboardingChatDirector->handleInlineCapture(
                    $user,
                    $conversation,
                    $message,
                    $context,
                    $currentRoute,
                );

                // S0.5.t — terminate the outer Advice Fyn turn after the
                // inline-capture handoff completes. Without this `return`,
                // the upstream Advice Fyn generator continues with the
                // delegate_to_capture tool_result and generates a second
                // assistant message echoing the inline-capture's
                // confirmation. BS-14 caught the duplicate-response
                // regression: the user saw two near-identical "I've added
                // your Cash ISA" messages because both Advice Fyn (advice
                // persona) and Onboarding Fyn (data_capture persona)
                // streamed text. The inline-capture turn IS the final
                // response — the user must never feel the switch.
                return;
            }

            yield $event;
        }
    }

    /**
     * S1.8 — Map a `QuerySchemas` constant to its response mode
     * (INV-2.3.1). Returns `'factual'`, `'recommendation'`, or
     * `'out_of_remit'`. Throws if the type is unmapped — exhaustive
     * coverage is enforced by AdviceFynResponseModeTest.
     *
     * @return 'factual'|'recommendation'|'out_of_remit'
     */
    public static function classifyResponseMode(string $queryType): string
    {
        if (! isset(self::RESPONSE_MODE_MAP[$queryType])) {
            throw new \InvalidArgumentException(
                "AdviceFyn::classifyResponseMode has no entry for query type '{$queryType}'."
            );
        }

        return self::RESPONSE_MODE_MAP[$queryType];
    }

    /**
     * S1.8 — Map a `QuerySchemas` constant to its engine call level
     * (INV-2.3.6). Returns `'holistic'`, `'module'`, or `'factual'`.
     * Holistic turns call `CoordinatingAgent::orchestrateAnalysis`;
     * module turns call a single `{Module}Agent::analyze`; factual
     * turns make no engine call. Throws if unmapped — exhaustive
     * coverage is enforced by AdviceFynEngineCallLevelTest.
     *
     * @return 'holistic'|'module'|'factual'
     */
    public static function engineCallLevel(string $queryType): string
    {
        if (! isset(self::ENGINE_CALL_LEVEL_MAP[$queryType])) {
            throw new \InvalidArgumentException(
                "AdviceFyn::engineCallLevel has no entry for query type '{$queryType}'."
            );
        }

        return self::ENGINE_CALL_LEVEL_MAP[$queryType];
    }

    /** @return list<string> */
    public function buildToolList(User $user): array
    {
        $provider = Cache::get('ai_provider', config('services.ai_provider', 'anthropic'));
        $definitions = $provider === 'xai' ? $this->xaiToolDefinitions : $this->toolDefinitions;
        $allTools = $definitions->getTools((bool) $user->is_preview_user);

        // S0.5.r — expose handoffTools (delegate_to_capture, capture_complete)
        // alongside the base catalogue so the LLM can route writes through
        // the handoff. handoffTools is provider-aware.
        $handoffTools = $definitions->handoffTools($provider === 'xai' ? 'xai' : 'anthropic');
        $allTools = array_merge($allTools, $handoffTools);

        $names = array_filter(array_map(
            fn (array $t) => $t['name'] ?? ($t['function']['name'] ?? null),
            $allTools,
        ));

        return array_values(array_diff($names, self::WRITE_TOOLS));
    }
}
