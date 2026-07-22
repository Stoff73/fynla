<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers;

/** One pointer's routing (frontmatter + body). Immutable; holds no fetch code. */
final class Pointer
{
    /** @param list<string> $triggers */
    public function __construct(
        public readonly string $pointerId,
        public readonly string $topic,
        public readonly array $triggers,
        public readonly string $mode,        // prefetch | tool | both
        public readonly string $handler,
        public readonly string $sourceLabel,
        public readonly int $version,
        public readonly string $body,
    ) {}

    public function isPrefetch(): bool
    {
        return $this->mode === 'prefetch' || $this->mode === 'both';
    }

    public function isTool(): bool
    {
        return $this->mode === 'tool' || $this->mode === 'both';
    }

    /** Lower-cased trigger set for sparse matching. */
    public function triggerHaystack(): string
    {
        return mb_strtolower(implode(' ', $this->triggers));
    }
}
