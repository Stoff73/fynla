<?php

declare(strict_types=1);

namespace Tests\Support\Fyn;

use Anthropic\Client;
use Anthropic\Messages\InputJSONDelta;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawContentBlockStartEvent;
use Anthropic\Messages\RawContentBlockStopEvent;
use Anthropic\Messages\RawMessageDeltaEvent;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\TextDelta;
use Anthropic\Messages\ToolUseBlock;
use Illuminate\Support\Facades\Cache;

/**
 * CoALA Phase 5 item 4 — scripted Anthropic stream harness.
 *
 * Binds a duck-typed fake Anthropic\Client whose messages->createStream replays
 * a queue of scripted assistant turns as real SDK event objects, so HasAiChat's
 * tool-use loop can be exercised end-to-end in tests without a network call.
 * Each createStream() call (one per loop iteration) pops the next turn.
 *
 * Usage:
 *   FynStreamHarness::fake()
 *       ->toolTurn('create_savings_account', ['provider' => 'Halifax']) // turn 1
 *       ->textTurn('Done — added it.')                                   // turn 2
 *       ->bind();
 */
final class FynStreamHarness
{
    /** @var list<list<object>> Queue of turns; each turn is a list of SDK events. */
    private array $turns = [];

    public static function fake(): self
    {
        return new self;
    }

    /**
     * A final assistant turn that streams plain text and ends (stop_reason
     * stays end_turn — no tool calls, so the loop exits after this turn).
     */
    public function textTurn(string $text): self
    {
        $this->turns[] = [
            RawContentBlockStartEvent::with(TextBlock::with(null, ''), 0),
            RawContentBlockDeltaEvent::with(TextDelta::with($text), 0),
            RawContentBlockStopEvent::with(0),
        ];

        return $this;
    }

    /**
     * An assistant turn that emits a single tool_use block and stops with
     * stop_reason=tool_use, so the loop dispatches the tool and re-enters the
     * planner (consuming the next scripted turn).
     *
     * @param  array<string, mixed>  $input
     */
    public function toolTurn(string $tool, array $input = [], string $id = 'toolu_test'): self
    {
        $this->turns[] = [
            RawContentBlockStartEvent::with(ToolUseBlock::with(id: $id, input: [], name: $tool), 0),
            RawContentBlockDeltaEvent::with(InputJSONDelta::with(json_encode($input, JSON_THROW_ON_ERROR)), 0),
            RawContentBlockStopEvent::with(0),
            RawMessageDeltaEvent::with(['stop_reason' => 'tool_use'], ['output_tokens' => 20]),
        ];

        return $this;
    }

    /**
     * Install the fake client into the container. HasAiChat resolves the
     * provider via app(Client::class), so this overrides it for the test.
     */
    public function bind(): void
    {
        // Force the Anthropic provider so the loop consumes this scripted stream
        // rather than the production xAI path (HasAiChat reads the provider via
        // Cache::get('ai_provider', config(...))). The loop's downstream logic —
        // tool dispatch, ground gate, persistence — is provider-agnostic, so
        // this is a faithful proof of the loop behaviour FynLoop encapsulates.
        Cache::put('ai_provider', 'anthropic');

        app()->instance(Client::class, new ScriptedAnthropicClient($this->turns));
    }
}
