<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Episodic;

/**
 * Request-scoped accumulator of the procedure versions that produced the
 * current turn. Each consumer (4b tool schemas, 4c prompt overlays / FCA
 * blocks, 4d onboarding workflow) records each active procedure it resolved
 * via add($procedureId, $version); HasAiChat::persistEpisode reads all() at
 * persist time and binds the "procedure_id@version" list onto the episode
 * blob frontmatter, the ai_messages.procedural_version column, and the v2
 * __episode__ audit result_summary. Empty list → null everywhere.
 *
 * Exact mirror of SemanticSnapshotHolder / ProceduralContributionCollector:
 * a plain in-memory VO (add/all/reset cannot throw), bound `scoped` in the
 * container — one instance per request, reset per turn alongside the others.
 */
final class ProceduralVersionHolder
{
    /** @var list<string> */
    private array $versions = [];

    public function add(string $procedureId, int $version): void
    {
        $stamp = "{$procedureId}@{$version}";
        if (! in_array($stamp, $this->versions, true)) {
            $this->versions[] = $stamp;
        }
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->versions;
    }

    public function reset(): void
    {
        $this->versions = [];
    }
}
