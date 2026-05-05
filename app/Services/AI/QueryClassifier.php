<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Constants\QuerySchemas;
use App\Events\Eval\AgentDecision;

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
 *  4. out_of_remit (S0.14 / INV-2.3.4) — message has strong non-financial signal
 *     AND no financial keyword fired in step 3
 *  5. route-based fallback (bias toward current page's module)
 *  6. general (no match — factual query)
 */
class QueryClassifier
{
    /**
     * S0.14 — labelled patterns for non-financial topics. Each detected_topic
     * is shaped to fit naturally in the canonical refusal:
     *   "I'm able to help you with your finances. {detected_topic} is out of scope."
     *
     * Patterns are deliberately narrow: a message that mentions any financial
     * keyword (pension, ISA, mortgage, etc.) will already have matched an
     * advice type in step 3 of `classify()` and will never reach this layer.
     */
    private const OUT_OF_REMIT_PATTERNS = [
        'Medical advice' => [
            '/\b(headache|migraine|symptom|symptoms|fever|cough|illness|sick|prescription|medication|medicine|diagnos\w+|nausea|pain|injury|injured|surgery|cancer|covid|flu|infection)\b/i',
            '/\b(doctor|gp|nurse|hospital|a\s*&\s*e|a\s*and\s*e|nhs|consultant|paramedic|paediatric)\b/i',
            '/\b(vaccin\w+|antibiotic|antidepressant|prescribe|pharma\w*)\b/i',
        ],
        'Legal advice' => [
            '/\b(divorce|custody|sue|lawsuit|prosecut\w+|criminal charges?|bail|arrested|police|court order|injunction|restraining order)\b/i',
            '/\b(landlord dispute|tenancy dispute|eviction|harassment|defamation|libel|slander)\b/i',
            '/\b(immigration|visa|deportation|asylum|citizenship application|right to remain)\b/i',
        ],
        'Emotional support' => [
            '/\b(depress\w+|anxious|anxiety|panic attack|suicid\w+|self.?harm|lonely|loneliness|grief|grieving|bereave\w+)\b/i',
            '/\b(my (marriage|relationship) is|hate my life|can.?t cope|breakdown|nervous breakdown|crying every|cry every)\b/i',
        ],
        'General knowledge' => [
            '/\b(recipe|cooking|how to (cook|bake)|football score|cricket score|premier league|world cup|olympics)\b/i',
            '/\b(weather (today|tomorrow|forecast)|will it rain|temperature outside|weather in [a-z])\b/i',
            '/\b(translate|in (french|spanish|german|italian|chinese|japanese))\b/i',
            '/\b(capital of|population of|when did .{1,40} (die|born|invade|happen)|history of)\b/i',
            '/\b(joke|tell me a joke|funny story|sing me|write a poem|haiku)\b/i',
        ],
    ];

    /**
     * Classify a user message.
     *
     * @return array{primary: string, related: string[], modules: string[], detected_topic?: string}
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

        // 4. S0.14 — non-financial topic detection. Only fires when no advice
        // keyword matched, so financial questions that incidentally mention a
        // doctor or legal matter still get routed to the right advice type.
        $detectedTopic = $this->detectOutOfRemitTopic($message);
        if ($detectedTopic !== null) {
            return $this->buildResult(QuerySchemas::OUT_OF_REMIT) + [
                'detected_topic' => $detectedTopic,
            ];
        }

        // 5. Route-based fallback — if on a module page and no keyword match
        if ($currentRoute) {
            $routeType = $this->inferFromRoute($currentRoute);
            if ($routeType !== null) {
                return $this->buildResult($routeType);
            }
        }

        // 6. Fallback — general factual query
        return $this->buildResult(QuerySchemas::GENERAL);
    }

    /**
     * Walk the OUT_OF_REMIT_PATTERNS table and return the first matching
     * detected_topic label. Returns null when nothing matches.
     */
    private function detectOutOfRemitTopic(string $message): ?string
    {
        foreach (self::OUT_OF_REMIT_PATTERNS as $topic => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $message)) {
                    return $topic;
                }
            }
        }

        return null;
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

        $result = [
            'primary' => $primary,
            'related' => $related,
            'modules' => $modules,
        ];

        event(new AgentDecision(
            agent: 'CoordinatingAgent',
            decisionPoint: 'classify_query',
            outcome: 'success',
            context: $result,
            atMicrotime: microtime(true),
        ));

        return $result;
    }
}
