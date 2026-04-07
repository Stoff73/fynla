<?php

declare(strict_types=1);

namespace App\Services\AI\Prompts;

use App\Constants\FinancialPlanningKnowledge;
use App\Constants\QuerySchemas;

/**
 * Layer 8: Query Knowledge — per-domain knowledge retrieval based on classification.
 *
 * Instead of injecting ALL financial knowledge into every prompt (~1,800 tokens),
 * this returns only the domains relevant to the classified query type.
 *
 * holistic_health → ALL domains
 * data_entry / navigation / general → empty (no knowledge needed)
 * advice types → union of domains from primary + related types
 */
final class QueryKnowledge
{
    /**
     * Get knowledge text for a classification.
     *
     * @return string Knowledge text, or empty string if none needed
     */
    public static function getForClassification(?array $classification): string
    {
        if ($classification === null) {
            // No classification — return all knowledge (backward compat)
            return FinancialPlanningKnowledge::getSystemPromptKnowledge();
        }

        $primary = $classification['primary'];

        // Bypass types need no knowledge
        if (QuerySchemas::isBypassType($primary) || $primary === QuerySchemas::GENERAL) {
            return '';
        }

        // Holistic health gets everything
        if ($primary === QuerySchemas::HOLISTIC_HEALTH) {
            return FinancialPlanningKnowledge::getSystemPromptKnowledge();
        }

        // Collect domains from primary + related types
        $domains = self::getDomainsForClassification($classification);

        if (empty($domains)) {
            return '';
        }

        // Call each domain method and merge (deduplicated)
        $parts = [];
        $seen = [];

        foreach ($domains as $method) {
            if (isset($seen[$method])) {
                continue;
            }
            $seen[$method] = true;

            if (method_exists(FinancialPlanningKnowledge::class, $method)) {
                $parts[] = FinancialPlanningKnowledge::$method();
            }
        }

        if (empty($parts)) {
            return '';
        }

        return implode("\n\n", $parts);
    }

    /**
     * Get all domain method names for a classification.
     */
    private static function getDomainsForClassification(array $classification): array
    {
        $domains = QuerySchemas::KNOWLEDGE_DOMAINS[$classification['primary']] ?? [];

        foreach ($classification['related'] ?? [] as $related) {
            $relatedDomains = QuerySchemas::KNOWLEDGE_DOMAINS[$related] ?? [];
            $domains = array_merge($domains, $relatedDomains);
        }

        return array_values(array_unique($domains));
    }
}
