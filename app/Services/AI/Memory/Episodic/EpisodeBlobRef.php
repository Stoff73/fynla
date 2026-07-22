<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Episodic;

/** Where a written episode blob lives + its content hash. Immutable. */
final class EpisodeBlobRef
{
    public function __construct(
        public readonly string $path,    // relative, e.g. episodic/2026/06/01/1/1.md
        public readonly string $sha256,
    ) {}
}
