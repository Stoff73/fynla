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

/**
 * CoALA Phase 5 item 5 — the planner LLM call (plan §"Decision loop", FR-M6).
 *
 * One call. One typed {@see Action} back. The planner is forced (toolChoice) to
 * emit a single `plan` tool_use whose input names the action it has chosen and
 * carries that variant's typed fields. {@see plan()} streams that one tool call
 * over the same `app(Client::class)` path the reasoner uses — so the existing
 * `FynStreamHarness` (tests/Support/Fyn) scripts it with `toolTurn('plan', [...])`
 * and the whole planner is exercised without a network call.
 *
 * The planner only chooses the action TYPE and its fields. The write-safety
 * decision is NOT made here — a `ground` action is gated downstream by the
 * GroundGate / SurfaceAllowlist before any tool runs. The cycle cap, planning
 * budget, and dispatch of the returned action live in {@see FynLoop} (item 5
 * increment 2), not in the planner.
 *
 * Graceful degradation: a malformed or unrecognised `plan` payload falls back to
 * a default `reason` action (answer the user normally) rather than a hard error,
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

    /**
     * Make one forced `plan` tool_use call and return the chosen typed Action.
     *
     * @param  array<int, array<string, mixed>>  $messages  Provider-shaped chat history for the planner call.
     */
    public function plan(string $system, array $messages, string $model): Action
    {
        $stream = app(Client::class)->messages->createStream(
            maxTokens: self::MAX_TOKENS,
            model: $model,
            system: $system,
            messages: $messages,
            tools: [self::planTool()],
            toolChoice: ['type' => 'tool', 'name' => 'plan'],
        );

        $accumulatedJson = '';

        foreach ($stream as $event) {
            if ($event instanceof RawContentBlockStartEvent && $event->contentBlock instanceof ToolUseBlock) {
                $accumulatedJson = '';
            } elseif ($event instanceof RawContentBlockDeltaEvent && $event->delta instanceof InputJSONDelta) {
                $accumulatedJson .= $event->delta->partialJSON ?? '';
            }
        }

        $input = [];
        if ($accumulatedJson !== '') {
            $decoded = json_decode($accumulatedJson, true);
            $input = is_array($decoded) ? $decoded : [];
        }

        return $this->toAction($input);
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
                is_array($input['payload'] ?? null) ? $input['payload'] : [],
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
     * The `plan` tool the planner is forced to call. The closed action vocabulary
     * mirrors {@see ActionType}; the per-variant fields
     * mirror {@see Action}'s named constructors.
     *
     * @return array<string, mixed>
     */
    private static function planTool(): array
    {
        return [
            'name' => 'plan',
            'description' => 'Choose the single next action for this turn. Emit exactly one action_type and only that variant\'s fields.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action_type' => [
                        'type' => 'string',
                        'enum' => ['reason', 'retrieve', 'learn', 'ground', 'no_action'],
                        'description' => 'reason: answer the user now. retrieve: recall from memory. learn: write to memory. ground: run a tool / write surface. no_action: stop, come back later.',
                    ],
                    'prompt_template_id' => ['type' => 'string', 'description' => 'reason only — which reasoning template to stream.'],
                    'working_memory_fields' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'reason only — working-memory fields the reasoner needs.'],
                    'store' => ['type' => 'string', 'enum' => ['episodic', 'semantic'], 'description' => 'retrieve / learn only — which memory store.'],
                    'query' => ['type' => 'string', 'description' => 'retrieve only — what to recall.'],
                    'filters' => ['type' => 'object', 'description' => 'retrieve only — optional filters.'],
                    'payload' => ['type' => 'object', 'description' => 'learn only — what to persist.'],
                    'surface' => ['type' => 'string', 'description' => 'ground only — the tool / write surface to invoke.'],
                    'args' => ['type' => 'object', 'description' => 'ground only — arguments for the surface.'],
                ],
                'required' => ['action_type'],
            ],
        ];
    }
}
