<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Procedural;

use Illuminate\Support\Carbon;

/**
 * One immutable procedural-memory procedure version (frontmatter + body).
 * Mirrors SemanticFact. Effective-dating is answered here so the corpus can
 * resolve the active version for a given date.
 */
final class Procedure
{
    public function __construct(
        public readonly string $procedureId,
        public readonly string $kind,
        public readonly string $module,
        public readonly int $version,
        public readonly bool $active,
        public readonly Carbon $effectiveFrom,
        public readonly ?Carbon $effectiveTo,
        public readonly string $body,
    ) {}

    /** True when this version is in force on $on. */
    public function effectiveOn(Carbon $on): bool
    {
        if ($on->lt($this->effectiveFrom)) {
            return false;
        }
        if ($this->effectiveTo !== null && $on->gt($this->effectiveTo)) {
            return false;
        }

        return true;
    }
}
