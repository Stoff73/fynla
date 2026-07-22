<?php

declare(strict_types=1);

namespace Tests\Support\Fyn;

use Generator;

/**
 * Duck-typed stand-in for Anthropic\Client used by {@see FynStreamHarness}.
 *
 * HasAiChat consumes the client purely as `app(Client::class)->messages
 * ->createStream(...)` with no type enforcement at the call site, so a plain
 * object exposing a `messages` property with a `createStream` method is a
 * sufficient (and SDK-version-robust) replacement. The container's instance()
 * binding does not type-check, so this is registered under Client::class.
 */
final class ScriptedAnthropicClient
{
    public ScriptedAnthropicMessages $messages;

    /**
     * @param  list<list<object>>  $turns  Queue of turns; each a list of SDK events.
     */
    public function __construct(array $turns)
    {
        $this->messages = new ScriptedAnthropicMessages($turns);
    }
}

/**
 * The `messages` resource: each createStream() call pops and replays the next
 * scripted turn (one turn per tool-use loop iteration).
 */
final class ScriptedAnthropicMessages
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
     * Mirrors Anthropic\Messages::createStream — accepts the same named args
     * (maxTokens, messages, model, system, tools, toolChoice) and ignores them;
     * the response is the next scripted turn, replayed as a generator.
     */
    public function createStream(mixed ...$args): Generator
    {
        $turn = array_shift($this->turns) ?? [];

        yield from $turn;
    }
}
