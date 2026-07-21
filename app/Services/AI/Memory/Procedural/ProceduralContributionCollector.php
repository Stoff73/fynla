<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Procedural;

/**
 * Request-scoped accumulator of the procedural contributions (overlay /
 * fca_block procedures) injected into the current turn's prompt by
 * FynContextAssembler. Each entry is
 * ['procedure_id' => …, 'kind' => …, 'module' => …, 'version' => int].
 * Exact mirror of FetchProvenanceCollector / SemanticSnapshotHolder: the
 * assembler records here as it wraps each contributed procedure; Phase 4e
 * reads it at persistEpisode time and binds it onto the episode attestation.
 * Bound `scoped` in the container — one instance per request, reset per turn.
 */
final class ProceduralContributionCollector
{
    /** @var list<array{procedure_id:string,kind:string,module:string,version:int}> */
    private array $entries = [];

    /** @param array{procedure_id:string,kind:string,module:string,version:int} $entry */
    public function record(array $entry): void
    {
        $this->entries[] = $entry;
    }

    /** @return list<array{procedure_id:string,kind:string,module:string,version:int}> */
    public function all(): array
    {
        return $this->entries;
    }

    public function reset(): void
    {
        $this->entries = [];
    }
}
