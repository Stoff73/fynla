<?php

declare(strict_types=1);

namespace Tests\Feature\Fyn\Eval;

use RuntimeException;

/**
 * Replays recorded provider streams from JSONL fixtures so Mode 1 (mocked)
 * eval runs are deterministic and free of network calls.
 *
 * Fixtures live at:
 *   tests/Feature/Fyn/Eval/fixtures/{anthropic,xai}/{scenario_id}.jsonl
 *
 * Each line is a single SSE event encoded as JSON, in the order the real
 * provider emitted it. The runner consumes the stream via {@see next()}
 * which yields one decoded event per call, returning null at end-of-stream.
 *
 * Recording happens once per scenario in S1.2 against the real Anthropic
 * + xAI endpoints. The recorded stream is the source of truth from then on.
 */
final class MockedProviderClient
{
    private int $cursor = 0;

    /** @var list<array<string, mixed>> */
    private array $events = [];

    public function __construct(
        public readonly string $provider,
        public readonly string $scenarioId,
        ?string $fixturePath = null,
    ) {
        if (! in_array($provider, ['anthropic', 'xai'], true)) {
            throw new RuntimeException("Unknown provider '{$provider}'.");
        }

        $path = $fixturePath ?? self::defaultFixturePath($provider, $scenarioId);

        if (! is_file($path)) {
            // Fixture missing is not fatal at construction time — caller may
            // detect via hasFixture() and skip the scenario or record one.
            return;
        }

        $this->events = self::parseJsonl($path);
    }

    public static function defaultFixturePath(string $provider, string $scenarioId): string
    {
        return __DIR__."/fixtures/{$provider}/{$scenarioId}.jsonl";
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
     * @return list<array<string, mixed>>
     */
    private static function parseJsonl(string $path): array
    {
        $events = [];

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open fixture {$path}.");
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $trimmed = trim($line);
                if ($trimmed === '') {
                    continue;
                }

                $decoded = json_decode($trimmed, true);
                if (! is_array($decoded)) {
                    throw new RuntimeException("Malformed JSONL line in {$path}: {$trimmed}");
                }

                $events[] = $decoded;
            }
        } finally {
            fclose($handle);
        }

        return $events;
    }
}
