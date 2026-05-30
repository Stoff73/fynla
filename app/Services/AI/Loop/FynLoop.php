<?php

declare(strict_types=1);

namespace App\Services\AI\Loop;

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\AdviceFyn;
use App\Services\AI\HandoffContract;
use App\Services\AI\HandoffPayloadValidator;
use App\Services\Onboarding\OnboardingChatDirector;
use App\ValueObjects\CaptureContext;
use Illuminate\Support\Facades\Log;

/**
 * Shared per-turn chat loop (CoALA Phase 5 item 4, Option B).
 *
 * Per the canonical Two-Fyn contract Fyn has one prompt and two write states.
 * Under Option B those states are thin shells over ONE loop. FynLoop owns the
 * shared machinery of a streamed turn at two levels:
 *
 *   - {@see stream()} — the raw streamed LLM turn primitive: it sets the
 *     per-turn unified onboarding focus on THIS service's CoordinatingAgent
 *     instance, runs {@see CoordinatingAgent::chatWithPromptOverride}, and
 *     clears the focus afterwards. Every Fyn LLM turn (advice, asset capture,
 *     grouped extraction, inline capture) funnels through here so the
 *     focus-set-then-stream pairing always happens on the SAME agent instance
 *     (CoordinatingAgent is container-transient — splitting set and stream
 *     across two instances would silently drop the focus and the model would
 *     fall back to the security refusal).
 *
 *   - {@see run()} — the advice turn: {@see stream()} in the read-only advice
 *     persona, wrapped in {@see interceptHandoff()}. When the read-only state's
 *     LLM emits a write intent, the synthetic `delegate_to_capture` SSE event is
 *     consumed here and the turn is routed through
 *     {@see OnboardingChatDirector::handleInlineCapture} into the same stream,
 *     so the user never sees the switch (INV-2.4.1).
 *
 * The advice-mode pre-LLM bypasses (query classification, out-of-remit refusal,
 * duplicate acknowledgement, deterministic write-intent routing) stay in the
 * {@see AdviceFyn} shell; the onboarding state machine and the
 * per-turn post-processing (buffered content filter, gap-fill synthesis,
 * capture-event detection) stay in {@see OnboardingChatDirector}. Only the
 * streamed-turn primitive and the advice handoff are shared.
 *
 * Source of truth: April/April24Updates/spec/00-canonical.md.
 */
final class FynLoop
{
    public function __construct(
        private readonly CoordinatingAgent $coordinatingAgent,
    ) {}

    /**
     * The raw streamed LLM turn primitive. Sets the per-turn unified onboarding
     * focus on this service's agent instance (so the focus and the stream that
     * reads it share one instance), streams the turn, and always clears the
     * focus afterwards — including the generator-throw / early-abandon path.
     *
     * This is intentionally a thin pass-through over chatWithPromptOverride: the
     * shells own everything else (prompts, tool lists, persona choice, and all
     * post-processing). It exists so the focus/stream instance pairing and the
     * clear-in-finally discipline live in exactly one place.
     *
     * @param  ?array<int, array<string, mixed>>  $allowedTools
     * @param  ?array<int, array<string, mixed>>  $toolsListOverride
     * @return \Generator<array<string, mixed>>
     */
    public function stream(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute,
        ?string $persona,
        ?string $systemPromptOverride = null,
        ?array $allowedTools = null,
        bool $persistUserMessage = true,
        ?array $toolsListOverride = null,
        ?string $unifiedFocus = null,
    ): \Generator {
        if ($unifiedFocus !== null) {
            $this->coordinatingAgent->setUnifiedOnboardingFocus($unifiedFocus);
        }

        try {
            yield from $this->coordinatingAgent->chatWithPromptOverride(
                user: $user,
                conversation: $conversation,
                message: $message,
                currentRoute: $currentRoute,
                systemPromptOverride: $systemPromptOverride,
                allowedTools: $allowedTools,
                persistUserMessage: $persistUserMessage,
                toolsListOverride: $toolsListOverride,
                personaOverride: $persona,
            );
        } finally {
            if ($unifiedFocus !== null) {
                $this->coordinatingAgent->setUnifiedOnboardingFocus(null);
            }
        }
    }

    /**
     * Run one advice turn and yield its user-visible SSE events. The streamed
     * LLM turn is driven in the mode's persona; the `delegate_to_capture`
     * handoff (if the model emits one) is intercepted and routed through inline
     * capture invisibly.
     *
     * @param  ?array<int, array<string, mixed>>  $allowedTools
     * @return \Generator<array<string, mixed>>
     */
    public function run(
        SessionMode $mode,
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute,
        ?array $allowedTools,
    ): \Generator {
        $upstream = $this->stream(
            $user,
            $conversation,
            $message,
            $currentRoute,
            $mode->persona(),
            allowedTools: $allowedTools,
        );

        yield from $this->interceptHandoff($upstream, $user, $conversation, $message, $currentRoute);
    }

    /**
     * Consume the upstream generator. When the LLM emits `delegate_to_capture`,
     * CoordinatingAgent yields a synthetic
     * `{type: 'handoff', handoff_type: 'delegate_to_capture', payload: ...}`
     * event. We intercept that event, build a CaptureContext from the payload,
     * and `yield from` OnboardingChatDirector::handleInlineCapture into the same
     * SSE stream so the user never sees the switch.
     *
     * The `handoff` event itself is dropped — INV-2.4.1 forbids it from reaching
     * the frontend.
     *
     * OnboardingChatDirector is resolved lazily here rather than constructor-
     * injected: the director constructor-injects FynLoop (so its capture turns
     * can use {@see stream()}), and ctor-injecting the director back would form a
     * container cycle. The handoff branch is rare, so the late resolution costs
     * nothing on the common path.
     *
     * @param  \Generator<array<string, mixed>>  $upstream
     * @return \Generator<array<string, mixed>>
     */
    public function interceptHandoff(
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

                // April30Updates F-1 / INV-2.4.5 — payload-shape validation
                // BEFORE we let CaptureContext synthesise a fallback. The
                // validator returns a typed error key on malformed input;
                // we surface a single user-facing `handoff_error` SSE event
                // so the user is told their request didn't land instead of
                // seeing a half-response with no explanation.
                //
                // Note: the validator requires `reason` strictly; we check
                // its result independently from CaptureContext::fromArray
                // (which is now resilient and synthesises a reason) so the
                // two layers can diverge on policy without one masking the
                // other.
                $validationError = HandoffPayloadValidator::validateDelegateToCapture($payload);
                if ($validationError !== null && $validationError !== 'missing_or_invalid_reason') {
                    // Hard malformed payload (entity_types missing or wrong
                    // type). The handoff cannot proceed — emit handoff_error
                    // and fall through to terminate this Advice Fyn turn.
                    Log::warning('[FynLoop] delegate_to_capture payload failed validation', [
                        'user_id' => $user->id,
                        'conversation_id' => $conversation->id,
                        'validation_error' => $validationError,
                        'payload_keys' => array_keys($payload),
                    ]);

                    yield [
                        'type' => 'handoff_error',
                        'reason' => $validationError,
                        'message' => "I couldn't pick up that request — could you try again?",
                    ];
                    yield ['type' => 'done'];

                    return;
                }

                if ($validationError === 'missing_or_invalid_reason') {
                    // Soft malformed — `reason` is missing but `entity_types`
                    // is present. Log at notice level so the rate of LLM
                    // prompt-non-compliance is visible in logs but recover
                    // via the resilient CaptureContext::fromArray path.
                    Log::notice('[FynLoop] delegate_to_capture payload missing reason — recovering via CaptureContext synthesis', [
                        'user_id' => $user->id,
                        'conversation_id' => $conversation->id,
                        'entity_types' => $payload['entity_types'] ?? [],
                    ]);
                }

                try {
                    $context = CaptureContext::fromArray($payload);
                } catch (\InvalidArgumentException $e) {
                    // Should be unreachable now that validator catches the
                    // hard cases, but keep as a defensive last line.
                    Log::warning('[FynLoop] CaptureContext rejected delegate_to_capture payload', [
                        'user_id' => $user->id,
                        'conversation_id' => $conversation->id,
                        'error' => $e->getMessage(),
                        'payload_keys' => array_keys($payload),
                    ]);

                    yield [
                        'type' => 'handoff_error',
                        'reason' => 'capture_context_rejected',
                        'message' => "I couldn't pick up that request — could you try again?",
                    ];
                    yield ['type' => 'done'];

                    return;
                }

                // Tier-2 harvest feed. The deterministic classifier
                // (writeIntentClassifier) logs '[AdviceFyn] Deterministic
                // write-intent routed' on Tier-1 hits. Reaching here means the
                // classifier returned null (a miss) and the LLM safety-net
                // <handoff_guidance> caught the write intent instead. Log it
                // symmetrically with the verbatim message so misses are
                // reviewable and the classifier patterns can be widened
                // iteratively.
                Log::info('[FynLoop] LLM-fallback write-intent caught (classifier miss)', [
                    'user_id' => $user->id,
                    'conversation_id' => $conversation->id,
                    'message' => $message,
                    'entity_types' => $payload['entity_types'] ?? [],
                    'reason' => $payload['reason'] ?? null,
                ]);

                yield from app(OnboardingChatDirector::class)->handleInlineCapture(
                    $user,
                    $conversation,
                    $message,
                    $context,
                    $currentRoute,
                );

                // S0.5.t — terminate the outer turn after the inline-capture
                // handoff completes. Without this `return`, the upstream
                // generator continues with the delegate_to_capture tool_result
                // and generates a second assistant message echoing the
                // inline-capture's confirmation. BS-14 caught the
                // duplicate-response regression: the user saw two
                // near-identical "I've added your Cash ISA" messages because
                // both the advice persona and the data_capture persona streamed
                // text. The inline-capture turn IS the final response — the
                // user must never feel the switch.
                return;
            }

            if ($type === 'handoff') {
                // INV-2.4.1 — no `handoff` event may reach the frontend.
                // The DELEGATE_TO_CAPTURE branch above is the only handoff
                // type that has a defined consumer in advice mode; every
                // other handoff_type (e.g. `capture_complete` exposed via
                // handoffTools() so the LLM can signal end-of-capture) is
                // an internal contract with no UI representation. Drop the
                // event with a warning so we'd notice if a misrouted
                // handoff started leaking, instead of letting it slip
                // through silently.
                Log::warning('[FynLoop] dropped non-delegate handoff event', [
                    'handoff_type' => $handoffType,
                    'user_id' => $user->id,
                    'conversation_id' => $conversation->id,
                ]);

                continue;
            }

            yield $event;
        }
    }
}
