<?php

declare(strict_types=1);

namespace App\Services\AI\Loop;

use Anthropic\Client;
use Anthropic\Messages\InputJSONDelta;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawContentBlockStartEvent;
use Anthropic\Messages\ToolUseBlock;
use App\Services\AI\Actions\Action;
use App\Services\AI\Actions\ActionType;
use App\Services\AI\XaiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * CoALA Phase 5 item 5 — the planner LLM call (plan §"Decision loop", FR-M6).
 *
 * One call. One typed {@see Action} back. The planner is forced (tool choice) to
 * emit a single `plan` tool call whose input names the action it has chosen and
 * carries that variant's typed fields.
 *
 * Provider-agnostic, exactly like the reasoner: {@see plan()} resolves the active
 * provider (xAI / Anthropic) the same way HasAiGuardrails::getAiProviderForLoop
 * does, and forces the `plan` tool over that provider's streaming API — xAI via
 * `XaiClient->chat()->createStreamed` (OpenAI-shaped tool calls), Anthropic via
 * `app(Client::class)->messages->createStream`. The two paths differ only in
 * wire shape; both decode to the same `{action_type, ...fields}` payload. In
 * tests the `FynStreamHarness` / `ScriptedXaiClient` (tests/Support/Fyn) script
 * the forced call so the whole planner runs without a network call.
 *
 * The planner only chooses the action TYPE and its fields. The write-safety
 * decision is NOT made here — a `ground` action is gated downstream by the
 * GroundGate / SurfaceAllowlist before any tool runs. The cycle cap, planning
 * budget, and dispatch of the returned action live in {@see FynLoop}.
 *
 * Graceful degradation: a malformed or unrecognised plan payload falls back to a
 * default `reason` action (answer the user normally) rather than a hard error,
 * so a planner hiccup degrades to today's single-call behaviour instead of
 * dropping the turn.
 */
final class Planner
{
    /**
     * The single reasoning template v1 ships. A `reason` action with no (or an
     * unknown) prompt_template_id resolves to this; the template registry that
     * lets the planner pick between reasoning modes is deferred until more than
     * one template exists.
     */
    public const DEFAULT_REASON_TEMPLATE = 'advice_default';

    /** A planner action is a few tokens; it never needs a large budget. */
    private const MAX_TOKENS = 1024;

    private const PLAN_DESCRIPTION = 'Choose the single next action for this turn. Emit exactly one action_type and only that variant\'s fields.';

    /**
     * Token usage of the most recent {@see plan()} call, so FynLoop can attribute
     * the planner's own LLM call into the cost ledger (FR-M11). Reset on each call.
     *
     * @var array{input: int, output: int, cache_read: int, cache_creation: int, model: string}
     */
    public array $lastUsage = ['input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_creation' => 0, 'model' => ''];

    /**
     * Make one forced `plan` tool call over the active provider and return the
     * chosen typed Action.
     *
     * @param  array<int, array<string, mixed>>  $messages  Provider-shaped chat history for the planner call.
     */
    public function plan(string $system, array $messages, ?string $model = null): Action
    {
        $provider = $this->resolveProvider();
        $model ??= $this->resolveModel($provider);
        $this->lastUsage = ['input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_creation' => 0, 'model' => $model];

        // The planner runs on every advice turn ("replace dispatch now"), so a
        // provider failure (timeout, 5xx, missing key) must NEVER break the turn.
        // Degrade to a default reason action — the reasoner then answers as it
        // did before the planner existed — rather than letting the exception
        // propagate and error the whole chat.
        try {
            $input = $provider === 'xai'
                ? $this->planViaXai($system, $messages, $model)
                : $this->planViaAnthropic($system, $messages, $model);
        } catch (\Throwable $e) {
            Log::warning('[Planner] plan call failed — degrading to default reason', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return Action::reason(self::DEFAULT_REASON_TEMPLATE);
        }

        return $this->toAction($input);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    private function planViaAnthropic(string $system, array $messages, string $model): array
    {
        $stream = app(Client::class)->messages->createStream(
            maxTokens: self::MAX_TOKENS,
            model: $model,
            system: $system,
            messages: $messages,
            tools: [self::planToolAnthropic()],
            toolChoice: ['type' => 'tool', 'name' => 'plan'],
        );

        $accumulatedJson = '';

        foreach ($stream as $event) {
            if ($event instanceof RawContentBlockStartEvent && $event->contentBlock instanceof ToolUseBlock) {
                $accumulatedJson = '';
            } elseif ($event instanceof RawContentBlockDeltaEvent && $event->delta instanceof InputJSONDelta) {
                $accumulatedJson .= $event->delta->partialJSON ?? '';
            } elseif ($event instanceof RawMessageStartEvent) {
                $usage = $event->message->usage ?? null;
                if ($usage !== null) {
                    $this->lastUsage['input'] += (int) ($usage->inputTokens ?? 0);
                    $this->lastUsage['cache_read'] += (int) ($usage->cacheReadInputTokens ?? 0);
                    $this->lastUsage['cache_creation'] += (int) ($usage->cacheCreationInputTokens ?? 0);
                }
            } elseif ($event instanceof RawMessageDeltaEvent) {
                $this->lastUsage['output'] += (int) ($event->usage->outputTokens ?? 0);
            }
        }

        return $this->decode($accumulatedJson);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    private function planViaXai(string $system, array $messages, string $model): array
    {
        $params = [
            'model' => $model,
            'messages' => array_merge([['role' => 'system', 'content' => $system]], $messages),
            'max_completion_tokens' => self::MAX_TOKENS,
            'temperature' => 0,
            'stream' => true,
            'stream_options' => ['include_usage' => true],
            'tools' => [self::planToolOpenAi()],
            'tool_choice' => ['type' => 'function', 'function' => ['name' => 'plan']],
        ];

        $stream = app(XaiClient::class)->chat()->createStreamed($params);

        $accumulatedJson = '';

        foreach ($stream as $response) {
            if (isset($response->usage)) {
                $this->lastUsage['input'] += (int) ($response->usage->promptTokens ?? $response->usage->prompt_tokens ?? 0);
                $this->lastUsage['output'] += (int) ($response->usage->completionTokens ?? $response->usage->completion_tokens ?? 0);
                $this->lastUsage['cache_read'] += (int) ($response->usage->promptTokensDetails->cachedTokens ?? 0);
            }

            $delta = $response->choices[0]->delta ?? null;
            if ($delta === null) {
                continue;
            }

            foreach (($delta->toolCalls ?? []) as $toolCall) {
                $accumulatedJson .= $toolCall->function->arguments ?? '';
            }
        }

        return $this->decode($accumulatedJson);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Resolve the active provider the same way the reasoner does
     * (HasAiGuardrails::getAiProviderForLoop) so the planner and reasoner always
     * agree on the provider within a turn.
     */
    private function resolveProvider(): string
    {
        $version = (int) Cache::get('ai_provider_version', 0);

        if ($version > 0) {
            return (string) Cache::get("ai_provider:v{$version}", config('services.ai_provider', 'anthropic'));
        }

        return (string) Cache::get('ai_provider', config('services.ai_provider', 'anthropic'));
    }

    private function resolveModel(string $provider): string
    {
        return $provider === 'xai'
            ? (string) config('services.xai.chat_model', 'grok-4.3')
            : (string) config('services.anthropic.chat_model', 'claude-haiku-4-5-20251001');
    }

    /**
     * Map a decoded `plan` tool input into a typed Action. Unknown action types
     * and missing required fields degrade to a default reason action.
     *
     * @param  array<string, mixed>  $input
     */
    private function toAction(array $input): Action
    {
        $type = is_string($input['action_type'] ?? null) ? $input['action_type'] : '';

        return match ($type) {
            'reason' => Action::reason(
                $this->nonEmptyString($input['prompt_template_id'] ?? null) ?? self::DEFAULT_REASON_TEMPLATE,
                is_array($input['working_memory_fields'] ?? null) ? $input['working_memory_fields'] : [],
            ),
            'retrieve' => Action::retrieve(
                (string) ($input['store'] ?? 'episodic'),
                (string) ($input['query'] ?? ''),
                is_array($input['filters'] ?? null) ? $input['filters'] : [],
            ),
            'learn' => Action::learn(
                $this->nonEmptyString($input['store'] ?? null) ?? 'episodic',
                array_merge(
                    is_array($input['payload'] ?? null) ? $input['payload'] : [],
                    is_array($input['amendment'] ?? null) ? $input['amendment'] : [],
                ),
            ),
            'ground' => $this->groundOrDefault($input),
            'no_action' => Action::noAction(),
            default => Action::reason(self::DEFAULT_REASON_TEMPLATE),
        };
    }

    /**
     * A ground action requires a surface. A ground payload with no surface is
     * malformed — degrade to a default reason rather than throw mid-turn.
     *
     * @param  array<string, mixed>  $input
     */
    private function groundOrDefault(array $input): Action
    {
        $surface = $this->nonEmptyString($input['surface'] ?? null);
        if ($surface === null) {
            return Action::reason(self::DEFAULT_REASON_TEMPLATE);
        }

        return Action::ground($surface, is_array($input['args'] ?? null) ? $input['args'] : []);
    }

    private function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function planToolAnthropic(): array
    {
        return [
            'name' => 'plan',
            'description' => self::PLAN_DESCRIPTION,
            'input_schema' => self::planSchema(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function planToolOpenAi(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'plan',
                'description' => self::PLAN_DESCRIPTION,
                'parameters' => self::planSchema(),
            ],
        ];
    }

    /**
     * The closed action vocabulary mirrors {@see ActionType};
     * the per-variant fields mirror {@see Action}'s named constructors. Shared by
     * both providers' tool definitions so they can never drift.
     *
     * @return array<string, mixed>
     */
    private static function planSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action_type' => [
                    'type' => 'string',
                    'enum' => ['reason', 'retrieve', 'learn', 'ground', 'no_action'],
                    'description' => 'reason: answer the user now. retrieve: recall from memory. learn: write to memory. ground: run a tool / write surface. no_action: stop, come back later.',
                ],
                'prompt_template_id' => ['type' => 'string', 'description' => 'reason only — which reasoning template to stream.'],
                'working_memory_fields' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'reason only — working-memory fields the reasoner needs.'],
                'store' => ['type' => 'string', 'enum' => ['episodic', 'semantic', 'procedural'], 'description' => 'retrieve / learn only — which memory store. episodic: write an episode. semantic: stage a proposed semantic fact (pending, never auto-applied). procedural: stage a proposed procedure amendment (pending, never auto-applied).'],
                'query' => ['type' => 'string', 'description' => 'retrieve only — what to recall.'],
                'filters' => ['type' => 'object', 'description' => 'retrieve only — optional filters.'],
                'payload' => ['type' => 'object', 'description' => 'learn only — what to persist. For store=semantic: {fact_id, title, body}. For store=episodic: episode fields. For store=procedural use the amendment field instead.'],
                'amendment' => ['type' => 'object', 'description' => 'learn store=procedural only — {procedure_id, problem_observed, proposed_fix, rationale, failure_type}.'],
                'surface' => ['type' => 'string', 'description' => 'ground only — the tool / write surface to invoke.'],
                'args' => ['type' => 'object', 'description' => 'ground only — arguments for the surface.'],
            ],
            'required' => ['action_type'],
        ];
    }
}
