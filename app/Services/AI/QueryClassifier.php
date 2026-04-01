<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Constants\QuerySchemas;

/**
 * Classifies user messages into query types for the FCA 6-step process.
 *
 * Returns a primary type plus related types. Multi-label — a message
 * can match several areas (e.g. "maximise pension" = retirement + tax + affordability).
 *
 * Priority order:
 *  1. data_entry (adding/updating data — bypasses FCA process)
 *  2. navigation (page navigation — bypasses FCA process)
 *  3. keyword matching against QuerySchemas::KEYWORD_PATTERNS
 *  4. route-based fallback (bias toward current page's module)
 *  5. general (no match — factual query)
 */
class QueryClassifier
{
    /**
     * Classify a user message.
     *
     * @return array{primary: string, related: string[], modules: string[]}
     */
    public function classify(string $message, ?string $currentRoute = null): array
    {
        $message = trim($message);

        if ($message === '') {
            return $this->buildResult(QuerySchemas::GENERAL);
        }

        // 1. Check data_entry first — user is providing data, not asking for advice
        if ($this->matchesType($message, QuerySchemas::DATA_ENTRY)) {
            return $this->buildResult(QuerySchemas::DATA_ENTRY);
        }

        // 2. Check navigation — user wants to go somewhere
        if ($this->matchesType($message, QuerySchemas::NAVIGATION)) {
            return $this->buildResult(QuerySchemas::NAVIGATION);
        }

        // 3. Keyword matching — check all advice types in defined order
        $matches = $this->findAllMatches($message);

        if (! empty($matches)) {
            $primary = $matches[0];

            // Collect additional matches as secondary related types
            $secondaryRelated = array_slice($matches, 1);

            return $this->buildResult($primary, $secondaryRelated);
        }

        // 4. Route-based fallback — if on a module page and no keyword match
        if ($currentRoute) {
            $routeType = $this->inferFromRoute($currentRoute);
            if ($routeType !== null) {
                return $this->buildResult($routeType);
            }
        }

        // 5. Fallback — general factual query
        return $this->buildResult(QuerySchemas::GENERAL);
    }

    /**
     * Check if message matches a specific type's patterns.
     */
    private function matchesType(string $message, string $type): bool
    {
        $patterns = QuerySchemas::KEYWORD_PATTERNS[$type] ?? [];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find all matching advice types (excluding data_entry, navigation, general).
     * Returns types in order of pattern definition (first match = most specific).
     */
    private function findAllMatches(string $message): array
    {
        $matches = [];
        $skipTypes = [QuerySchemas::DATA_ENTRY, QuerySchemas::NAVIGATION, QuerySchemas::GENERAL];

        foreach (QuerySchemas::KEYWORD_PATTERNS as $type => $patterns) {
            if (in_array($type, $skipTypes, true)) {
                continue;
            }

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $message)) {
                    $matches[] = $type;
                    break; // One match per type is enough
                }
            }
        }

        return $matches;
    }

    /**
     * Infer query type from the current route.
     */
    private function inferFromRoute(?string $currentRoute): ?string
    {
        $routeMap = [
            '/protection' => QuerySchemas::PROTECTION_COVER,
            '/net-worth/retirement' => QuerySchemas::RETIREMENT_READINESS,
            '/net-worth/investments' => QuerySchemas::INVESTMENT_PORTFOLIO,
            '/net-worth/cash' => QuerySchemas::SAVINGS_ACCOUNTS,
            '/net-worth/property' => QuerySchemas::PROPERTY,
            '/estate' => QuerySchemas::ESTATE_PLANNING,
            '/estate/will-builder' => QuerySchemas::ESTATE_PLANNING,
            '/estate/power-of-attorney' => QuerySchemas::ESTATE_PLANNING,
            '/goals' => QuerySchemas::GOALS_PROGRESS,
            '/holistic-plan' => QuerySchemas::HOLISTIC_HEALTH,
            '/risk-profile' => QuerySchemas::INVESTMENT_PORTFOLIO,
            '/valuable-info?section=income' => QuerySchemas::INCOME,
            '/valuable-info?section=expenditure' => QuerySchemas::AFFORDABILITY,
            '/net-worth/liabilities' => QuerySchemas::SAVINGS_DEBT,
            '/trusts' => QuerySchemas::ESTATE_PLANNING,
        ];

        return $routeMap[$currentRoute] ?? null;
    }

    /**
     * Build the classification result with implicit related types.
     *
     * @param  string  $primary  The primary query type
     * @param  string[]  $secondaryRelated  Additional matched types from keyword matching
     */
    private function buildResult(string $primary, array $secondaryRelated = []): array
    {
        // Start with implicit related types for the primary
        $related = QuerySchemas::IMPLICIT_RELATED[$primary] ?? [];

        // Add secondary keyword matches (types that also matched)
        $related = array_merge($related, $secondaryRelated);

        // Add implicit related types for each secondary match too
        foreach ($secondaryRelated as $secondary) {
            $implicitForSecondary = QuerySchemas::IMPLICIT_RELATED[$secondary] ?? [];
            $related = array_merge($related, $implicitForSecondary);
        }

        // Deduplicate and remove primary from related
        $related = array_values(array_unique(array_filter($related, fn ($r) => $r !== $primary)));

        // Build module list
        $modules = QuerySchemas::getModulesForClassification([
            'primary' => $primary,
            'related' => $related,
        ]);

        return [
            'primary' => $primary,
            'related' => $related,
            'modules' => $modules,
        ];
    }
}
