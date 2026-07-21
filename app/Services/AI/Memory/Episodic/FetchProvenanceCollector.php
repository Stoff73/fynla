<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Episodic;

/**
 * Request-scoped accumulator of pointer fetch-provenance for the current turn.
 * Both trigger paths (pre-fetch in FynContextAssembler, tool-mode in
 * CoordinatingAgent::executeTool) flow through FetchDispatcher, which records
 * here; HasAiChat flushes onto the assistant ai_messages row at persist time.
 * Bound `scoped` in the container — one instance per request, reset per turn.
 */
final class FetchProvenanceCollector
{
    /** @var list<array<string,string>> */
    private array $entries = [];

    /** @param array<string,string> $entry */
    public function record(array $entry): void
    {
        $this->entries[] = $entry;
    }

    /** @return list<array<string,string>> */
    public function all(): array
    {
        return $this->entries;
    }

    public function reset(): void
    {
        $this->entries = [];
    }
}
