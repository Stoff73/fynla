<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Episodic;

/**
 * Request-scoped holder for the semantic-snapshot id of the current turn.
 * FynContextAssembler computes SemanticRetriever::snapshotId() over the facts
 * it served and stamps it here; HasAiChat::persistEpisode reads it at persist
 * time, binding it onto the episode blob frontmatter and the v2 __episode__
 * audit attestation. Null when no knowledge facts were retrieved this turn.
 * Bound `scoped` in the container — one instance per request, reset per turn.
 */
final class SemanticSnapshotHolder
{
    private ?string $snapshotId = null;

    public function set(?string $snapshotId): void
    {
        $this->snapshotId = $snapshotId;
    }

    public function get(): ?string
    {
        return $this->snapshotId;
    }

    public function reset(): void
    {
        $this->snapshotId = null;
    }
}
