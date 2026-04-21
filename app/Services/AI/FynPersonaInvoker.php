<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\Prompts\DataCapturePromptBuilder;
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

                yield $event;
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
