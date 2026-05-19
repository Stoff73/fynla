<?php

declare(strict_types=1);

namespace Tests\Feature\Fyn\Eval;

use RuntimeException;

/**
 * Replays recorded provider streams from JSONL fixtures so Mode 1 (mocked)
 * eval runs are deterministic and free of network calls.
 *
 * Fixtures live at:
 *   tests/Feature/Fyn/Eval/fixtures/{provider}/{model}/{scenario_id}.jsonl
 *
 * The {provider}/{model} layout lets the same scenario be recorded against
 * multiple Anthropic models (haiku-4-5, sonnet-4-6, …) and multiple xAI
 * models (grok-4.3, …) for
 * cross-model comparison per CSJ's direction.
 *
 * Each fixture begins with a `__meta` event:
 *   {"__meta": {"provider": "anthropic", "model": "claude-haiku-4-5-20251001",
 *               "scenario_id": "advice_protection_cover", "recorded_at": "2026-04-27T08:14:00Z",
 *               "fynla_branch": "feature/fyn-persona-split", "fynla_sha": "0bb878c..."}}
 * Subsequent lines are the SSE events the provider emitted, in order.
 *
 * The runner consumes the stream via {@see next()} which yields one decoded
 * event per call (skipping the meta header), returning null at end-of-stream.
 */
final class MockedProviderClient
{
    private int $cursor = 0;

    /** @var list<array<string, mixed>> */
    private array $events = [];

    /** @var array<string, mixed>|null */
    private ?array $meta = null;

    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        public readonly string $scenarioId,
        ?string $fixturePath = null,
    ) {
        if (! in_array($provider, ['anthropic', 'xai'], true)) {
            throw new RuntimeException("Unknown provider '{$provider}'.");
        }

        $path = $fixturePath ?? self::defaultFixturePath($provider, $model, $scenarioId);

        if (! is_file($path)) {
            // Fixture missing is not fatal at construction time — caller may
            // detect via hasFixture() and skip the scenario or record one.
            return;
        }

        [$meta, $events] = self::parseJsonl($path);
        $this->meta = $meta;
        $this->events = $events;
    }

    public static function defaultFixturePath(string $provider, string $model, string $scenarioId): string
    {
        return __DIR__."/fixtures/{$provider}/{$model}/{$scenarioId}.jsonl";
    }

    /**
     * @return array<string, mixed>|null
     */
    public function meta(): ?array
    {
        return $this->meta;
    }

    public function hasFixture(): bool
    {
        return $this->events !== [];
    }

    /**
     * Yield the next decoded event from the recorded stream.
     *
     * @return array<string, mixed>|null
     */
    public function next(): ?array
    {
        if ($this->cursor >= count($this->events)) {
            return null;
        }

        return $this->events[$this->cursor++];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->events;
    }

    public function rewind(): void
    {
        $this->cursor = 0;
    }

    /**
     * Parse a fixture into [meta, events]. The first non-empty line MAY be a
     * `__meta` header — when present it's returned separately and not yielded
     * to the consumer. Recordings without a header still load (meta is null).
     *
     * @return array{0: array<string, mixed>|null, 1: list<array<string, mixed>>}
     */
    private static function parseJsonl(string $path): array
    {
        $meta = null;
        $events = [];

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open fixture {$path}.");
        }

        try {
            $isFirst = true;
            while (($line = fgets($handle)) !== false) {
                $trimmed = trim($line);
                if ($trimmed === '') {
                    continue;
                }

                $decoded = json_decode($trimmed, true);
                if (! is_array($decoded)) {
                    throw new RuntimeException("Malformed JSONL line in {$path}: {$trimmed}");
                }

                if ($isFirst && array_key_exists('__meta', $decoded)) {
                    $meta = $decoded['__meta'];
                    $isFirst = false;

                    continue;
                }

                $isFirst = false;
                $events[] = $decoded;
            }
        } finally {
            fclose($handle);
        }

        return [$meta, $events];
    }
}
