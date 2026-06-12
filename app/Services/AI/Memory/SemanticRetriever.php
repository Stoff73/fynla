<?php

declare(strict_types=1);

namespace App\Services\AI\Memory;

use Illuminate\Support\Carbon;

/**
 * Sparse keyword retrieval over the semantic corpus. No embeddings — dense is
 * deferred until ~500 concurrent users (CSJ, 2026-06-01). Effective-date filter
 * is applied BEFORE ranking so a historical query returns historically-correct
 * facts.
 *
 * §8.2 conformance (lean-prompt law): query stopwords are dropped, scoring is
 * word-boundary exact-token counting (plus simple ±"s" plural variants — no
 * deeper stemming), and a fact is only admitted when it matches at least
 * min(2, distinct content tokens) DISTINCT query tokens. Without these, the
 * substring scorer loaded ~4 full strategy bodies on every English turn ("the"
 * alone scored 50–67 per house_view file).
 */
final class SemanticRetriever
{
    /**
     * Conservative query-side stopword set (articles, pronouns, auxiliaries,
     * question words). Domain words (tax, save, pension, isa, income, …) must
     * never be added here. Haystack tokens are NOT filtered — a stopworded
     * query token simply never looks up. Public so FynMemoryStore's
     * procedures relevance filter shares the ONE list (never duplicated).
     */
    public const STOPWORDS = [
        'the', 'a', 'an', 'and', 'or', 'but',
        'how', 'what', 'when', 'where', 'which', 'who', 'why',
        'should', 'would', 'could', 'can',
        'do', 'does', 'did', 'is', 'are', 'was', 'were', 'have', 'has', 'had',
        'i', 'me', 'my', 'we', 'our', 'you', 'your', 'they', 'their',
        'it', 'its', 'this', 'that', 'these', 'those',
        'to', 'of', 'in', 'on', 'at', 'for', 'with', 'about', 'from', 'into', 'over', 'under',
        'any', 'some', 'all', 'not', 'no', 'yes',
        'please', 'tell', 'show',
    ];

    public function __construct(private readonly SemanticCorpusLoader $loader) {}

    /**
     * @param  list<string>|null  $categories  null = all categories
     * @return list<SemanticFact> highest score first, capped at top_k
     */
    public function retrieve(string $query, Carbon $effectiveDate, ?array $categories = null): array
    {
        $terms = array_values(array_diff($this->tokenise($query), self::STOPWORDS));
        if ($terms === []) {
            return [];
        }

        // Relevance gate: one-token queries still work; generic chatter must
        // hit at least two DISTINCT content tokens to admit a fact.
        $required = min(2, count($terms));

        $scored = [];
        foreach ($this->loader->all() as $fact) {
            if ($categories !== null && ! in_array($fact->category, $categories, true)) {
                continue;
            }
            if (! $fact->effectiveOn($effectiveDate)) {
                continue; // filter BEFORE ranking
            }

            $counts = $this->termCounts($fact->haystack());
            $score = 0;
            $distinct = 0;
            foreach ($terms as $term) {
                $hits = $this->termHits($term, $counts);
                if ($hits > 0) {
                    $distinct++;
                    $score += $hits;
                }
            }

            if ($distinct >= $required) {
                $scored[] = ['score' => $score, 'fact' => $fact];
            }
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?: strcmp($a['fact']->factId, $b['fact']->factId));

        $topK = max(0, (int) config('fyn.memory.semantic_top_k', 4));

        return array_map(fn (array $r): SemanticFact => $r['fact'], array_slice($scored, 0, $topK));
    }

    /**
     * Deterministic provenance id for the returned set (audit trail).
     *
     * @param  list<SemanticFact>  $facts
     */
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

    /**
     * Word-boundary token frequency map of the haystack (same token grammar as
     * the query side), so "the" never matches inside "them"/"rather".
     *
     * @return array<string, int>
     */
    private function termCounts(string $haystack): array
    {
        preg_match_all('/[a-z0-9]{3,}/', mb_strtolower($haystack), $m);

        return array_count_values($m[0]);
    }

    /**
     * Exact-token hits for a query term plus its simple plural variants
     * (term + trailing "s", term minus trailing "s"). No deeper stemming.
     *
     * @param  array<string, int>  $counts
     */
    private function termHits(string $term, array $counts): int
    {
        $hits = ($counts[$term] ?? 0) + ($counts[$term.'s'] ?? 0);
        if (str_ends_with($term, 's')) {
            $hits += $counts[substr($term, 0, -1)] ?? 0;
        }

        return $hits;
    }
}
