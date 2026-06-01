<?php

declare(strict_types=1);

namespace App\Services\AI\Memory;

use Illuminate\Support\Carbon;

/**
 * Sparse keyword retrieval over the semantic corpus. No embeddings — dense is
 * deferred until ~500 concurrent users (CSJ, 2026-06-01). Effective-date filter
 * is applied BEFORE ranking so a historical query returns historically-correct
 * facts.
 */
final class SemanticRetriever
{
    public function __construct(private readonly SemanticCorpusLoader $loader) {}

    /**
     * @param  list<string>|null  $categories  null = all categories
     * @return list<SemanticFact> highest score first, capped at top_k
     */
    public function retrieve(string $query, Carbon $effectiveDate, ?array $categories = null): array
    {
        $terms = $this->tokenise($query);
        if ($terms === []) {
            return [];
        }

        $scored = [];
        foreach ($this->loader->all() as $fact) {
            if ($categories !== null && ! in_array($fact->category, $categories, true)) {
                continue;
            }
            if (! $fact->effectiveOn($effectiveDate)) {
                continue; // filter BEFORE ranking
            }

            $score = $this->score($terms, $fact->haystack());
            if ($score > 0) {
                $scored[] = ['score' => $score, 'fact' => $fact];
            }
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?: strcmp($a['fact']->factId, $b['fact']->factId));

        $topK = (int) config('fyn.memory.semantic_top_k', 4);

        return array_map(fn (array $r): SemanticFact => $r['fact'], array_slice($scored, 0, $topK));
    }

    /** Deterministic provenance id for the returned set (audit trail). */
    public function snapshotId(array $facts): string
    {
        $pairs = array_map(fn (SemanticFact $f): string => $f->factId.'@'.$f->version, $facts);
        sort($pairs);

        return hash('sha256', implode(',', $pairs));
    }

    /** @return list<string> */
    private function tokenise(string $text): array
    {
        preg_match_all('/[a-z0-9]{3,}/', mb_strtolower($text), $m);

        return array_values(array_unique($m[0]));
    }

    /** @param list<string> $terms */
    private function score(array $terms, string $haystack): int
    {
        $score = 0;
        foreach ($terms as $term) {
            $score += substr_count($haystack, $term);
        }

        return $score;
    }
}
