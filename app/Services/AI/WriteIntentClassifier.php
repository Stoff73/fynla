<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Server-side write-intent classifier.
 *
 * Detects deterministically when a user message expresses an intent to add a
 * persistent record (a policy, account, pension, property, etc.) so the
 * advice→capture handoff doesn't depend on the LLM correctly emitting
 * `delegate_to_capture`. The LLM is unreliable on multi-intent messages and
 * occasionally emits the tool call as plain `<function_call>` text rather
 * than a real tool_use block; this classifier removes that dependency by
 * routing the write deterministically server-side.
 *
 * The classifier is conservative — it returns null when the intent is
 * ambiguous. False negatives (missing a real write intent) are recoverable
 * (the LLM may still emit delegate_to_capture). False positives (firing
 * inline-capture when the user only asked for advice) are not — they would
 * spawn unwanted capture turns. Hence the strict verb+entity matching.
 */
final class WriteIntentClassifier
{
    /**
     * Verbs that signal an explicit write intent. Imperative ("add a policy")
     * and declarative ("I have a policy" / "we bought a policy") both count.
     */
    private const WRITE_VERB_PATTERNS = [
        'add', 'create', 'save', 'record', 'log', 'register',
        'i have', 'we have', "i've got", 'we\'ve got', "i've added",
        'i bought', 'we bought', "i've bought", "we've bought",
        'i opened', 'we opened', "i've opened", "we've opened",
        'i started', 'we started', "i've started",
        'i took out', 'we took out',
        'i set up', 'we set up',
        'i pay', 'we pay',
        'i hold', 'we hold',
        'i own', 'we own',
    ];

    /**
     * Entity-keyword lookup. Each entry maps a single entity_type the
     * inline-capture extractor accepts to the surface phrases that name it
     * in user English. Order matters within an entity (longer / more
     * specific phrases first) so "stocks and shares isa" wins over "isa".
     *
     * @var array<string, list<string>>
     */
    private const ENTITY_KEYWORDS = [
        'protection_policy' => [
            'life insurance', 'life cover', 'life policy', 'life assurance',
            'critical illness', 'ci cover',
            'income protection', 'income protection policy',
            'whole of life', 'term assurance',
            'mortgage protection',
        ],
        'savings_account' => [
            'cash isa', 'help to buy isa', 'lifetime isa', 'lisa',
            'savings account', 'easy access account', 'fixed-rate account',
            'current account', 'instant access',
        ],
        'investment_account' => [
            'stocks and shares isa', 'investment isa', 'investment account',
            'gia', 'general investment account', 'brokerage account',
        ],
        'pension' => [
            'sipp', 'personal pension', 'workplace pension',
            'defined benefit pension', 'defined contribution pension',
            'pension', // catch-all last
        ],
        'property' => [
            'main residence', 'second home', 'buy to let', 'buy-to-let',
            'rental property', 'investment property', 'property',
            'house', 'flat', 'apartment',
        ],
        'mortgage' => [
            'mortgage', 'home loan', 'remortgage',
        ],
        'liability' => [
            'credit card', 'personal loan', 'student loan', 'car finance',
            'overdraft', 'loan',
        ],
        'goal' => [
            'savings goal', 'goal', 'target',
        ],
    ];

    /**
     * @return array{entity_type: string, matched_verb: string, matched_entity_keyword: string, fields_needed: list<string>, reason: string}|null
     */
    public function classify(string $userMessage): ?array
    {
        $normalised = strtolower(trim($userMessage));
        if ($normalised === '') {
            return null;
        }

        $matchedVerb = $this->firstMatch($normalised, self::WRITE_VERB_PATTERNS);
        if ($matchedVerb === null) {
            return null;
        }

        foreach (self::ENTITY_KEYWORDS as $entityType => $keywords) {
            $matchedEntity = $this->firstMatch($normalised, $keywords);
            if ($matchedEntity !== null) {
                return [
                    'entity_type' => $entityType,
                    'matched_verb' => $matchedVerb,
                    'matched_entity_keyword' => $matchedEntity,
                    'fields_needed' => $this->fieldsNeededFor($entityType),
                    'reason' => sprintf(
                        'Detected write intent (%s) for %s — phrase "%s".',
                        $matchedVerb,
                        $entityType,
                        $matchedEntity,
                    ),
                ];
            }
        }

        // Verb matched but no entity matched — ambiguous, return null so the
        // LLM still owns the turn. We do NOT fabricate an entity_type guess.
        return null;
    }

    private function firstMatch(string $haystack, array $needles): ?string
    {
        foreach ($needles as $needle) {
            $needleLower = strtolower($needle);
            // Word-boundary match for short verbs so "added" doesn't match "add"
            // when the user wrote "I've added a policy" (but it WILL match
            // "i've added" via the multi-word entry above). Multi-word
            // patterns fall back to substring match.
            if (str_contains($needleLower, ' ')) {
                if (str_contains($haystack, $needleLower)) {
                    return $needle;
                }
                continue;
            }

            // Single-word verb — require word boundary
            $pattern = '/\b' . preg_quote($needleLower, '/') . '\b/';
            if (preg_match($pattern, $haystack) === 1) {
                return $needle;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function fieldsNeededFor(string $entityType): array
    {
        return match ($entityType) {
            'protection_policy' => ['provider', 'policy_type', 'sum_assured', 'premium_amount', 'policy_term_years', 'policy_start_date'],
            'savings_account' => ['provider', 'account_type', 'current_balance', 'interest_rate'],
            'investment_account' => ['provider', 'account_type', 'current_value'],
            'pension' => ['provider', 'pension_type', 'current_value', 'monthly_contribution'],
            'property' => ['property_type', 'current_value', 'address'],
            'mortgage' => ['provider', 'outstanding_balance', 'interest_rate', 'mortgage_type'],
            'liability' => ['liability_type', 'outstanding_balance', 'interest_rate'],
            'goal' => ['goal_name', 'target_amount', 'target_date'],
            default => [],
        };
    }
}
