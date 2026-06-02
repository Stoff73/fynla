<?php

declare(strict_types=1);

namespace Tests\Support\Fyn;

use App\Services\AI\XaiClient;
use Generator;

/**
 * Duck-typed stand-in for {@see XaiClient} used to exercise the
 * planner's xAI path without a network call (CoALA Phase 5 item 5 increment 2).
 *
 * The planner consumes the xAI client exactly as the reasoner does —
 * `app(XaiClient::class)->forConversation($id)->chat()->createStreamed($params)`
 * — and reads OpenAI-streamed responses shaped
 * `$response->choices[0]->delta->toolCalls[*]->function->arguments` plus
 * `$response->choices[0]->finishReason`. This fake replays a queue of scripted
 * turns of exactly that shape; the container instance() binding does not
 * type-check, so it is registered under XaiClient::class.
 */
final class ScriptedXaiClient
{
    /** @var list<list<object>> Queue of turns; each a list of streamed response objects. */
    private array $turns;

    /**
     * @param  list<list<object>>  $turns
     */
    public function __construct(array $turns)
    {
        $this->turns = $turns;
    }

    /**
     * Build a single forced-`plan` tool-call turn: one streamed response whose
     * delta carries the whole arguments JSON in one chunk (the planner's parser
     * accumulates chunks, so one is faithful).
     *
     * @param  array<string, mixed>  $input
     * @return list<object> one turn
     */
    public static function planToolCall(array $input): array
    {
        $arguments = json_encode($input, JSON_THROW_ON_ERROR);

        return [
            (object) [
                'choices' => [
                    (object) [
                        'delta' => (object) [
                            'content' => null,
                            'toolCalls' => [
                                (object) [
                                    'index' => 0,
                                    'id' => 'call_plan',
                                    'function' => (object) [
                                        'name' => 'plan',
                                        'arguments' => $arguments,
                                    ],
                                ],
                            ],
                        ],
                        'finishReason' => 'tool_calls',
                    ],
                ],
            ],
        ];
    }

    public function forConversation(int|string $conversationId): self
    {
        return $this;
    }

    public function chat(): ScriptedXaiChat
    {
        return new ScriptedXaiChat($this->turns);
    }
}

/**
 * The `chat` resource: each createStreamed() call pops and replays the next
 * scripted turn.
 */
final class ScriptedXaiChat
{
    /** @var list<list<object>> */
    private array $turns;

    /**
     * @param  list<list<object>>  $turns
     */
    public function __construct(array $turns)
    {
        $this->turns = $turns;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function createStreamed(array $params): Generator
    {
        $turn = array_shift($this->turns) ?? [];

        yield from $turn;
    }
}
