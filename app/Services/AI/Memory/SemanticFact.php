<?php

declare(strict_types=1);

namespace App\Services\AI\Memory;

use Illuminate\Support\Carbon;

/**
 * One immutable semantic-memory fact (frontmatter + body). Effective-dating is
 * answered here so the retriever filters before ranking.
 */
final class SemanticFact
{
    public function __construct(
        public readonly string $factId,
        public readonly string $category,
        public readonly string $title,
        public readonly string $source,
        public readonly int $version,
        public readonly ?Carbon $validFrom,
        public readonly ?Carbon $validTo,
        public readonly string $body,
    ) {}

    /** True when this fact is in force on $on. */
    public function effectiveOn(Carbon $on): bool
    {
        if ($this->validFrom !== null && $on->lt($this->validFrom)) {
            return false;
        }
        if ($this->validTo !== null && $on->gt($this->validTo)) {
            return false;
        }

        return true;
    }

    /** Lower-cased "title body" haystack for sparse matching. */
    public function haystack(): string
    {
        return mb_strtolower($this->title.' '.$this->body);
    }
}
