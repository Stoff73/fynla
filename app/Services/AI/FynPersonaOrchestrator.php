<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Constants\QuerySchemas;
use App\Models\AiConversation;
use App\Models\User;
use App\ValueObjects\CaptureContext;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches post-onboarding Fyn chat turns through the persona split.
 *
 * Responsibilities:
 *   - Read ai_conversations.persona_state and pick the right persona.
 *   - Run the classifier fast-path for clear data-entry messages.
 *   - Invoke the chosen persona via FynPersonaInvoker.
 *   - Interpret handoff tool calls (delegate_to_capture / capture_complete)
 *     and transition persona_state accordingly.
 *   - Handle pre-invocation cancellations and capture-mode timeouts.
 *   - Short-circuit preview users before they enter capturing state
 *     (belt-and-braces with CoreIdentity preview-mode prompt text).
 *   - Emit SSE persona_state_change / capture_complete events for the
 *     frontend Vuex store.
 *
 * Approx 250 LOC. Onboarding turns are NOT routed here — the director
 * handles those. AiChatController picks between director / orchestrator /
 * CoordinatingAgent::chat via a 3-way match (Phase 9).
 */
final class FynPersonaOrchestrator
{
    public function __construct(
        private readonly FynPersonaInvoker $invoker,
        private readonly QueryClassifier $classifier,
    ) {}

    /**
     * Main entry point. Yields SSE events to AiChatController::sendMessage
     * for streaming to the client.
     *
     * @return \Generator<array<string, mixed>>
     */
    public function dispatch(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute = null,
    ): \Generator {
        $state = $this->loadState($conversation);

        // Capturing state — data-capture sub-conversation in progress.
        if (($state['current'] ?? 'advice') === 'capturing') {
            yield from $this->handleCapturingTurn($user, $conversation, $message, $currentRoute, $state);

            return;
        }

        // Advice state (default).
        yield from $this->handleAdviceTurn($user, $conversation, $message, $currentRoute, $state);
    }

    // ─── Advice-state turn ──────────────────────────────────────────────

    private function handleAdviceTurn(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute,
        array $state,
    ): \Generator {
        // Classifier fast-path: clear data-entry messages that are not
        // advice-shaped skip advice Fyn and go straight to data_capture.
        if ($this->fastPathShouldSkipAdvice($message)) {
            Log::info('[FynPersonaOrchestrator] Classifier fast-path: data_capture', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'message_length' => mb_strlen($message),
            ]);

            // Preview short-circuit before transitioning to capturing.
            if ($user->is_preview_user) {
                yield from $this->emitPreviewShortCircuit($conversation);

                return;
            }

            $captureContext = new CaptureContext(
                reason: 'classifier fast-path preselected data_capture',
                entityTypes: ['savings_account', 'investment_account', 'dc_pension', 'protection_policy', 'property', 'goal', 'life_event'],
            );

            yield from $this->runCaptureTurn(
                user: $user,
                conversation: $conversation,
                message: $message,
                currentRoute: $currentRoute,
                captureContext: $captureContext,
                pendingAdviceQuestion: null,
                persistUserMessage: true,
            );

            return;
        }

        // Standard advice invocation.
        yield from $this->invoker->invoke(
            persona: FynPersonaRegistry::PERSONA_ADVICE,
            user: $user,
            conversation: $conversation,
            message: $message,
            currentRoute: $currentRoute,
            captureContext: null,
            persistUserMessage: true,
        );

        $handoff = $this->invoker->lastHandoff();

        if ($handoff === null || $handoff['handoff_type'] !== HandoffContract::DELEGATE_TO_CAPTURE) {
            // No handoff — turn is done. Persist state (no change).
            $this->persistState($conversation, $state);

            return;
        }

        // Advice delegated to data-capture. Build CaptureContext.
        if ($user->is_preview_user) {
            // Prompt instruction should have prevented this; belt-and-braces.
            yield from $this->emitPreviewShortCircuit($conversation);

            return;
        }

        $captureContext = $this->buildCaptureContextFromPayload($handoff['payload'], $message);

        $newState = [
            'current' => 'capturing',
            'pending_advice_question' => $message,
            'capture_context' => $captureContext->toArray(),
            'turns_in_capture' => 0,
        ];

        $this->persistState($conversation, $newState);
        yield $this->personaStateChangeEvent('capturing');

        // Immediately invoke data_capture in the same HTTP request.
        yield from $this->runCaptureTurn(
            user: $user,
            conversation: $conversation,
            message: $this->buildCaptureKickoffMessage($captureContext),
            currentRoute: $currentRoute,
            captureContext: $captureContext,
            pendingAdviceQuestion: $message,
            persistUserMessage: false,
        );
    }

    // ─── Capturing-state turn ──────────────────────────────────────────

    private function handleCapturingTurn(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute,
        array $state,
    ): \Generator {
        // Pre-invocation cancel check.
        if ($this->isCancelMessage($message)) {
            Log::info('[FynPersonaOrchestrator] User cancelled capture', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
            ]);

            $this->persistState($conversation, $this->defaultState());
            yield $this->personaStateChangeEvent('advice');

            // Invoke advice with a cancellation-aware message so it
            // acknowledges naturally and asks what else the user needs.
            yield from $this->invoker->invoke(
                persona: FynPersonaRegistry::PERSONA_ADVICE,
                user: $user,
                conversation: $conversation,
                message: $message,
                currentRoute: $currentRoute,
                captureContext: null,
                persistUserMessage: true,
            );

            return;
        }

        // Timeout check.
        $turnsInCapture = (int) ($state['turns_in_capture'] ?? 0);
        $maxTurns = (int) config('fyn.capture_max_turns', 6);

        if ($turnsInCapture >= $maxTurns) {
            Log::warning('[FynPersonaOrchestrator] Capture timeout — forcing return to advice', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'turns_in_capture' => $turnsInCapture,
            ]);

            $this->persistState($conversation, $this->defaultState());
            yield $this->personaStateChangeEvent('advice');
            yield [
                'type' => 'content',
                'text' => "Let me come back to what you were asking — it's easier if you add those details on the page rather than here. I'll take you there.",
            ];

            return;
        }

        // Increment turn counter.
        $state['turns_in_capture'] = $turnsInCapture + 1;
        $this->persistState($conversation, $state);

        $captureContext = isset($state['capture_context']) && is_array($state['capture_context'])
            ? CaptureContext::fromArray($state['capture_context'])
            : new CaptureContext(reason: 'continued capture', entityTypes: ['savings_account']);

        yield from $this->runCaptureTurn(
            user: $user,
            conversation: $conversation,
            message: $message,
            currentRoute: $currentRoute,
            captureContext: $captureContext,
            pendingAdviceQuestion: $state['pending_advice_question'] ?? null,
            persistUserMessage: true,
        );
    }

    /**
     * Invoke the data-capture persona and, if it emits capture_complete,
     * emit the capture_complete SSE event, reset persona_state, and
     * optionally re-invoke the advice persona with the saved
     * pending_advice_question.
     */
    private function runCaptureTurn(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute,
        CaptureContext $captureContext,
        ?string $pendingAdviceQuestion,
        bool $persistUserMessage,
    ): \Generator {
        yield from $this->invoker->invoke(
            persona: FynPersonaRegistry::PERSONA_DATA_CAPTURE,
            user: $user,
            conversation: $conversation,
            message: $message,
            currentRoute: $currentRoute,
            captureContext: $captureContext,
            persistUserMessage: $persistUserMessage,
        );

        $handoff = $this->invoker->lastHandoff();

        if ($handoff === null || $handoff['handoff_type'] !== HandoffContract::CAPTURE_COMPLETE) {
            // Data-capture did not complete — stay in capturing for the
            // next user turn. State was already persisted at the entry
            // point of the turn.
            return;
        }

        // Capture complete — emit public event for record cards in the UI.
        $summary = (string) ($handoff['payload']['summary'] ?? '');
        $recordsCreated = (array) ($handoff['payload']['records_created'] ?? []);

        yield [
            'type' => 'capture_complete',
            'summary' => $summary,
            'records_created' => $recordsCreated,
        ];

        // Reset state to advice.
        $this->persistState($conversation, $this->defaultState());
        yield $this->personaStateChangeEvent('advice');

        // If advice had been waiting on this capture, re-invoke it to
        // answer the original question with the fresh records.
        if ($pendingAdviceQuestion !== null && $pendingAdviceQuestion !== '') {
            $reInvokeMessage = sprintf(
                "[Capture complete. The user's original question was: %s. Data-capture has just recorded: %s. Answer the original question now using the updated records visible in your context.]",
                $pendingAdviceQuestion,
                $summary,
            );

            yield from $this->invoker->invoke(
                persona: FynPersonaRegistry::PERSONA_ADVICE,
                user: $user,
                conversation: $conversation,
                message: $reInvokeMessage,
                currentRoute: $currentRoute,
                captureContext: null,
                persistUserMessage: false,
            );
        }
    }

    // ─── Helpers ───────────────────────────────────────────────────────

    private function fastPathShouldSkipAdvice(string $message): bool
    {
        if (! (bool) config('fyn.classifier_fast_path_enabled', true)) {
            return false;
        }

        if (str_word_count($message) > 40) {
            return false;
        }

        if (QuerySchemas::isAdviceShaped($message)) {
            return false;
        }

        $classification = $this->classifier->classify($message, null);

        return ($classification['primary'] ?? '') === QuerySchemas::DATA_ENTRY;
    }

    private function isCancelMessage(string $message): bool
    {
        $patterns = (array) config('fyn.cancel_patterns', []);

        foreach ($patterns as $pattern) {
            if (is_string($pattern) && @preg_match($pattern, $message) === 1) {
                return true;
            }
        }

        return false;
    }

    private function buildCaptureContextFromPayload(array $payload, string $originalMessage): CaptureContext
    {
        return new CaptureContext(
            reason: (string) ($payload['reason'] ?? 'advice delegated to data-capture'),
            entityTypes: array_values((array) ($payload['entity_types'] ?? ['savings_account'])),
            fieldsNeeded: array_values((array) ($payload['fields_needed'] ?? [])),
            pendingAdviceQuestion: $originalMessage,
            originatingFocus: null,
        );
    }

    private function buildCaptureKickoffMessage(CaptureContext $captureContext): string
    {
        $entity = $captureContext->entityTypes[0] ?? 'record';
        $fieldsHint = $captureContext->fieldsNeeded !== []
            ? ' Fields needed: '.implode(', ', $captureContext->fieldsNeeded).'.'
            : '';

        return sprintf(
            "[Internal prompt: data-capture has just been invoked for reason: %s. Ask the user a single concise question to gather %s details.%s]",
            $captureContext->reason,
            $entity,
            $fieldsHint,
        );
    }

    private function emitPreviewShortCircuit(AiConversation $conversation): \Generator
    {
        $text = "I can't save data in preview mode — but if you sign up, I'll capture this straight away.";

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $text,
            'persona' => FynPersonaRegistry::PERSONA_ADVICE,
            'metadata' => ['preview_short_circuit' => true],
        ]);

        yield ['type' => 'content', 'text' => $text];
        yield [
            'type' => 'preview_cta',
            'label' => 'Sign up',
            'route' => '/register',
        ];
        yield ['type' => 'done'];
    }

    private function personaStateChangeEvent(string $current): array
    {
        return [
            'type' => 'persona_state_change',
            'current' => $current,
        ];
    }

    private function loadState(AiConversation $conversation): array
    {
        $state = $conversation->persona_state;

        if (! is_array($state)) {
            return $this->defaultState();
        }

        return array_merge($this->defaultState(), $state);
    }

    private function defaultState(): array
    {
        return [
            'current' => 'advice',
            'pending_advice_question' => null,
            'capture_context' => null,
            'turns_in_capture' => 0,
        ];
    }

    private function persistState(AiConversation $conversation, array $state): void
    {
        $conversation->update(['persona_state' => $state]);
    }
}
