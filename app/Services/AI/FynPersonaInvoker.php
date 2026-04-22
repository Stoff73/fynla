<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\Prompts\DataCapturePromptBuilder;
use App\Services\Onboarding\AssetCaptureEntityExtractor;
use App\ValueObjects\CaptureContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Runs a single persona turn end-to-end.
 *
 * Responsibilities:
 *   - Resolve the persona's prompt builder from FynPersonaRegistry.
 *   - Build the system prompt (persona-appropriate input: User for advice,
 *     User + CaptureContext for data-capture).
 *   - Build the tool list by intersecting the persona's allowed_tools with
 *     AiToolDefinitions::getTools($isPreview) and appending the persona's
 *     handoff tools from handoffTools().
 *   - Delegate to CoordinatingAgent::chatWithPromptOverride with those
 *     overrides and the persona tag so persisted assistant messages pick
 *     up the right ai_messages.persona value.
 *   - Stream SSE events through. The internal `handoff` SSE event emitted
 *     by HasAiChat when the LLM calls delegate_to_capture or capture_complete
 *     is intercepted and collected in $lastHandoff so the orchestrator
 *     can act on it after the generator completes.
 *
 * The invoker is invoked by FynPersonaOrchestrator, one call per persona
 * turn. For a KYC-gate round-trip (advice → data-capture → advice), the
 * orchestrator calls invoke() three times with three different personas.
 */
class FynPersonaInvoker
{
    private ?array $lastHandoff = null;

    public function __construct(
        private readonly FynPersonaRegistry $registry,
        private readonly AiToolDefinitions $toolDefinitions,
        private readonly XaiToolDefinitions $xaiToolDefinitions,
        private readonly CoordinatingAgent $coordinatingAgent,
        private readonly AssetCaptureEntityExtractor $entityExtractor,
    ) {}

    /**
     * Run one persona turn. Yields SSE events to the caller. Strips the
     * internal `handoff` event so it never reaches the frontend — the
     * orchestrator consumes the captured handoff via lastHandoff()
     * after this generator completes.
     *
     * @param  string  $persona  One of FynPersonaRegistry::PERSONA_*.
     * @param  CaptureContext|null  $captureContext  Required when persona = data_capture.
     * @return \Generator<array<string, mixed>>
     */
    public function invoke(
        string $persona,
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute = null,
        ?CaptureContext $captureContext = null,
        bool $persistUserMessage = true,
    ): \Generator {
        $this->lastHandoff = null;

        if (! $this->registry->has($persona)) {
            throw new InvalidArgumentException("Unknown persona: {$persona}");
        }

        $systemPrompt = $this->buildPrompt($persona, $user, $captureContext);
        $toolsList = $this->buildToolList($persona, $user);

        $internalToolNames = HandoffContract::internalToolNames();

        $isDataCapture = $persona === FynPersonaRegistry::PERSONA_DATA_CAPTURE;

        try {
            $generator = $this->coordinatingAgent->chatWithPromptOverride(
                user: $user,
                conversation: $conversation,
                message: $message,
                currentRoute: $currentRoute,
                systemPromptOverride: $systemPrompt,
                allowedTools: null,
                persistUserMessage: $persistUserMessage,
                toolsListOverride: $toolsList,
                personaOverride: $persona,
            );

            // B-9 — buffer content deltas for the data_capture persona so we
            // can apply a one-sentence-max filter at flush time. Advice Fyn
            // streams content through untouched. See truncateCaptureAck().
            $contentBuffer = '';

            // B-1 — track fill_form events the LLM emits during data_capture
            // so we can run the deterministic gap-fill at flush time. Without
            // this, the same LLM that drops entities during onboarding asset
            // capture drops them post-onboarding in Fyn chat too.
            $llmEmittedFills = [];

            $flushCapture = function () use (&$contentBuffer): ?array {
                if ($contentBuffer === '') {
                    return null;
                }

                $truncated = $this->truncateCaptureAck($contentBuffer);
                $contentBuffer = '';

                return $truncated === '' ? null : ['type' => 'content', 'text' => $truncated];
            };

            foreach ($generator as $event) {
                $type = $event['type'] ?? '';

                // Intercept and capture the synthetic handoff SSE event emitted
                // by HasAiChat when the LLM calls delegate_to_capture / capture_complete.
                if ($type === 'handoff') {
                    $this->lastHandoff = [
                        'handoff_type' => $event['handoff_type'] ?? 'unknown',
                        'payload' => $event['payload'] ?? [],
                    ];

                    Log::info('[FynPersonaInvoker] Handoff captured', [
                        'persona' => $persona,
                        'handoff_type' => $this->lastHandoff['handoff_type'],
                        'user_id' => $user->id,
                        'conversation_id' => $conversation->id,
                    ]);

                    // Do NOT forward the handoff event to SSE — it's internal.
                    continue;
                }

                // Strip any tool_use events for internal handoff tools — the
                // frontend should never see these tools surfacing.
                if ($type === 'tool_use' && in_array($event['tool'] ?? '', $internalToolNames, true)) {
                    continue;
                }

                // B-1 — record every fill_form event the LLM emits on data_capture
                // turns so the gap-check has a baseline for comparison.
                if ($isDataCapture && $type === 'fill_form') {
                    $llmEmittedFills[] = (array) ($event['fields'] ?? []);
                    yield $event;

                    continue;
                }

                // B-9 — on the data_capture persona, buffer content deltas and
                // flush the truncated first sentence just before `done`. This
                // stops multi-paragraph advice-style responses from leaking
                // out of capture turns even when the LLM ignores the prompt
                // guardrail ("EXACTLY ONE short sentence per capture").
                if ($isDataCapture && $type === 'content') {
                    $contentBuffer .= (string) ($event['text'] ?? '');

                    continue;
                }

                if ($isDataCapture && $type === 'done') {
                    $flushEvent = $flushCapture();
                    if ($flushEvent !== null) {
                        yield $flushEvent;
                    }

                    // B-1 — synthesise fill_form events for entities the LLM
                    // dropped. Fires BEFORE the done marker so the frontend
                    // aiFormFill queue sees them in the same turn.
                    yield from $this->emitGapFillFromCaptureContext(
                        $user,
                        $captureContext,
                        $message,
                        $llmEmittedFills,
                    );

                    yield $event;

                    continue;
                }

                yield $event;
            }

            // Safety net — generator exited without a done marker (e.g. the
            // upstream stream dropped it). Flush any buffered ack so it isn't
            // silently lost, and still run gap-fill.
            if ($isDataCapture && $contentBuffer !== '') {
                $flushEvent = $flushCapture();
                if ($flushEvent !== null) {
                    yield $flushEvent;
                }
            }
            if ($isDataCapture) {
                yield from $this->emitGapFillFromCaptureContext(
                    $user,
                    $captureContext,
                    $message,
                    $llmEmittedFills,
                );
            }
        } catch (\Throwable $e) {
            Log::error('[FynPersonaInvoker] Persona turn failed', [
                'persona' => $persona,
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Returns the last captured handoff from an invoke() run, or null if
     * the LLM did not emit delegate_to_capture or capture_complete.
     *
     * @return array{handoff_type: string, payload: array<string, mixed>}|null
     */
    public function lastHandoff(): ?array
    {
        return $this->lastHandoff;
    }

    /**
     * B-1 — post-onboarding multi-entity gap-fill.
     *
     * The post-onboarding Fyn chat hits the same LLM as the onboarding
     * asset_capture turn, and drops the same multi-entity tool calls.
     * When the user types "I have Aviva life £300k and Vitality CI £100k"
     * into the dashboard chat and advice Fyn delegates to data_capture,
     * the LLM still emits only one create_protection_policy. This method
     * runs AssetCaptureEntityExtractor on the user's message, compares it
     * against the fill_form events the LLM actually emitted this turn,
     * and synthesises tool_use + fill_form + tool_use events for every
     * entity that was dropped — same mechanism as
     * OnboardingChatDirector::emitGapFillToolCalls.
     *
     * Focus is inferred from CaptureContext::entityTypes (e.g.
     * 'protection_policy' → 'protection'). For mixed-focus capture contexts
     * the extractor runs once per inferred focus.
     *
     * @param  list<array<string, mixed>>  $llmEmittedFills
     * @return \Generator<array<string, mixed>>
     */
    private function emitGapFillFromCaptureContext(
        User $user,
        ?CaptureContext $captureContext,
        string $message,
        array $llmEmittedFills,
    ): \Generator {
        if ($captureContext === null) {
            return;
        }

        $focuses = $this->inferFocusesFromEntityTypes($captureContext->entityTypes);

        foreach ($focuses as $focus) {
            $tool = $this->entityExtractor->toolNameForFocus($focus);
            if ($tool === null) {
                continue;
            }

            try {
                $extracted = $this->entityExtractor->extractForFocus($focus, $message);
                $missing = $this->entityExtractor->findMissing($focus, $extracted, $llmEmittedFills);
            } catch (\Throwable $e) {
                Log::warning('[FynPersonaInvoker] Gap-fill extraction failed', [
                    'user_id' => $user->id,
                    'focus' => $focus,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($missing === []) {
                continue;
            }

            Log::info('[FynPersonaInvoker] Gap-fill firing for dropped entities', [
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
                    Log::error('[FynPersonaInvoker] Gap-fill tool execution failed', [
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

    /**
     * Translate CaptureContext::entityTypes (post-onboarding vocabulary —
     * 'protection_policy', 'savings_account', 'dc_pension', 'investment_account')
     * into the focus strings the extractor understands ('protection',
     * 'savings', 'retirement', 'investment'). Silently drops entity types
     * the extractor doesn't cover (e.g. 'goal', 'life_event', 'property').
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
                default => null,
            };
            if ($focus !== null && ! in_array($focus, $focuses, true)) {
                $focuses[] = $focus;
            }
        }

        return $focuses;
    }

    /**
     * B-9 — keep data-capture persona acknowledgments short.
     *
     * The data_capture prompt says "EXACTLY ONE short sentence per capture",
     * but the LLM occasionally ignores it and emits multi-paragraph advice-
     * shaped prose. That text then leaks into the chat and breaks the
     * persona-split contract (advice stays in advice Fyn).
     *
     * Strategy: take the first sentence (terminated by `.` / `!` / `?` /
     * newline), cap at 25 words, and strip markdown headers / bullet
     * points. Empty input returns empty. Handles the common "Got it —
     * recording those now." shape cleanly.
     */
    private function truncateCaptureAck(string $raw): string
    {
        $text = trim($raw);
        if ($text === '') {
            return '';
        }

        // Strip markdown headers, bullet points, and code fences — the
        // capture persona should never emit structured prose.
        $text = preg_replace('/^#{1,6}\s+/m', '', $text) ?? $text;
        $text = preg_replace('/^[\-*+]\s+/m', '', $text) ?? $text;
        $text = preg_replace('/^```.*?```/ms', '', $text) ?? $text;

        // First sentence only. The lookahead keeps the terminator so the
        // output reads naturally.
        if (preg_match('/^([^.!?\n]*[.!?])/u', $text, $m) === 1) {
            $first = trim($m[1]);
        } else {
            // No terminator — take up to the first newline or the full string.
            $first = trim(explode("\n", $text, 2)[0] ?? $text);
        }

        if ($first === '') {
            return '';
        }

        // 25-word hard cap. Anything longer is almost certainly advice
        // prose leaking through. Trim to the cap and add an ellipsis only
        // if we actually truncated.
        $words = preg_split('/\s+/u', $first) ?: [];
        if (count($words) > 25) {
            $first = rtrim(implode(' ', array_slice($words, 0, 25)), '.,!?;:').'…';
        }

        return $first;
    }

    private function buildPrompt(string $persona, User $user, ?CaptureContext $captureContext): string
    {
        $builderClass = $this->registry->promptBuilderClass($persona);
        $builder = app($builderClass);

        if ($persona === FynPersonaRegistry::PERSONA_DATA_CAPTURE) {
            if ($captureContext === null) {
                throw new InvalidArgumentException('data_capture persona requires a CaptureContext.');
            }

            if (! $builder instanceof DataCapturePromptBuilder) {
                throw new InvalidArgumentException(sprintf(
                    'data_capture persona builder must be DataCapturePromptBuilder, got %s',
                    get_class($builder),
                ));
            }

            return $builder->build($user, $captureContext);
        }

        // Advice persona — AdvicePromptBuilder has a richer signature that
        // HasAiChat normally assembles via buildSystemPrompt. Here we call
        // it with the same defaults so the persona-split path produces an
        // equivalent prompt to the classic CoordinatingAgent::chat path.
        if ($persona === FynPersonaRegistry::PERSONA_ADVICE) {
            $classifier = app(QueryClassifier::class);
            $classification = $classifier->classify($user->is_preview_user ? '' : '', null);

            return $builder->build(
                user: $user,
                classification: null,
                kycResult: null,
                currentRoute: null,
                isPreview: $user->is_preview_user,
                orchestrateAnalysis: fn (int $userId) => $this->coordinatingAgent->orchestrateAnalysis($userId),
            );
        }

        // Future personas — assume a `build(User $user)` signature.
        return $builder->build($user);
    }

    /**
     * Build the tool list to hand to the LLM for this persona turn:
     *   - intersect registry's allowed_tools with preview-filtered getTools()
     *   - append the persona's handoff tools (never exposed by default)
     *
     * The tool list is formatted per the active provider (Anthropic or xAI).
     *
     * @return list<array<string, mixed>>
     */
    private function buildToolList(string $persona, User $user): array
    {
        $provider = Cache::get('ai_provider', config('services.ai_provider', 'anthropic'));

        $allowed = $this->registry->allowedTools($persona);
        $handoffNames = $this->registry->handoffTools($persona);

        // Base universe from the active provider's getTools, preview-filtered.
        $universe = $provider === 'xai'
            ? $this->xaiToolDefinitions->getTools($user->is_preview_user)
            : $this->toolDefinitions->getTools($user->is_preview_user);

        $extractName = static function (array $tool): ?string {
            return $tool['name'] ?? ($tool['function']['name'] ?? null);
        };

        // Intersect with allowed_tools.
        $allowedFlip = array_flip($allowed);
        $filtered = array_values(array_filter($universe, function (array $tool) use ($extractName, $allowedFlip): bool {
            $name = $extractName($tool);

            return $name !== null && isset($allowedFlip[$name]);
        }));

        // Append persona's handoff tools (always, regardless of preview).
        $handoffUniverse = $provider === 'xai'
            ? $this->xaiToolDefinitions->handoffTools()
            : $this->toolDefinitions->handoffTools($provider);

        $handoffFlip = array_flip($handoffNames);
        $handoffFiltered = array_values(array_filter($handoffUniverse, function (array $tool) use ($extractName, $handoffFlip): bool {
            $name = $extractName($tool);

            return $name !== null && isset($handoffFlip[$name]);
        }));

        return array_merge($filtered, $handoffFiltered);
    }
}
