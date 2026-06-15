<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Recall;

use App\Services\AI\Memory\SemanticRetriever;

/**
 * Token-overlap relevance over the episode body. Same grammar as
 * FynMemoryStore::matchingProcedures / SemanticRetriever (§8.2 lean-prompt law):
 * stopword-dropped query tokens, word-boundary counting with ±"s" variants,
 * scored by distinct-term matches. Stable sort: equal scores keep input order
 * (callers pass episodes recency-first, so recency is the tiebreak).
 */
final class SparseRecallScorer implements RecallScorer
{
    public function rank(string $query, array $episodes): array
    {
        $terms = array_values(array_diff($this->tokenise($query), SemanticRetriever::STOPWORDS));
        if ($terms === [] || $episodes === []) {
            return $episodes;
        }

        $scored = [];
        foreach ($episodes as $i => $episode) {
            $counts = $this->termCounts((string) ($episode['body'] ?? ''));
            $distinct = 0;
            foreach ($terms as $term) {
                if ($this->termHits($term, $counts) > 0) {
                    $distinct++;
                }
            }
            $scored[] = ['i' => $i, 'score' => $distinct, 'episode' => $episode];
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?: $a['i'] <=> $b['i']);

        return array_map(static fn (array $s): array => $s['episode'], $scored);
    }

    /** @return list<string> */
    private function tokenise(string $text): array
    {
        preg_match_all('/[a-z0-9]{3,}/', mb_strtolower($text), $m);

        return array_values(array_unique($m[0]));
    }

    /** @return array<string, int> */
    private function termCounts(string $haystack): array
    {
        preg_match_all('/[a-z0-9]{3,}/', mb_strtolower($haystack), $m);

        return array_count_values($m[0]);
    }

    /** @param array<string, int> $counts */
    private function termHits(string $term, array $counts): int
    {
        $hits = ($counts[$term] ?? 0) + ($counts[$term.'s'] ?? 0);
        if (str_ends_with($term, 's')) {
            $hits += $counts[substr($term, 0, -1)] ?? 0;
        }

        return $hits;
    }
}
