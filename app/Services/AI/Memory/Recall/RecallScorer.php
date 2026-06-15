<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Recall;

/**
 * Ranks recalled episodes by relevance to a query. The sparse implementation
 * (token overlap) ships in Phase 6; a dense (embedding) implementation is the
 * deferred drop-in (CSJ 2026-06-01: dense deferred until ~500 concurrent users).
 */
interface RecallScorer
{
    /**
     * @param  list<array{body: string, ...}>  $episodes
     * @return list<array{body: string, ...}> same shape, relevance-ranked (stable for ties)
     */
    public function rank(string $query, array $episodes): array;
}
