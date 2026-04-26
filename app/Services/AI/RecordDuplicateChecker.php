<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\LifeInsurancePolicy;
use App\Models\User;

/**
 * Conservative duplicate-checker for write-intent routing.
 *
 * When `WriteIntentClassifier` decides a user message is a write intent,
 * we run this checker BEFORE firing the inline-capture handoff so we
 * don't blindly insert a second copy of a record the user is just
 * referring to ("I have an Aviva life policy for £300k" — they may have
 * told us about it already; don't add it again).
 *
 * Match rules per entity_type are intentionally narrow — we only suppress
 * when there is high-confidence evidence the user is referring to an
 * existing record. False negatives (firing inline-capture even though the
 * record exists) are then caught by the inline-capture extractor's own
 * idempotency guards.
 *
 * Initial scope: protection_policy (BS-11). Extended per entity type as
 * each BS-NN brings the next case under test.
 */
final class RecordDuplicateChecker
{
    /**
     * Returns true if there's already a record matching the rough
     * shape of the user's message. Conservative — only checks fields
     * that can be extracted with confidence from typical user phrasing.
     *
     * @param  array{entity_type: string, matched_verb: string, matched_entity_keyword: string, fields_needed: list<string>, reason: string}  $intent
     */
    public function alreadyExists(User $user, array $intent, string $userMessage): bool
    {
        return match ($intent['entity_type']) {
            'protection_policy' => $this->protectionPolicyExists($user, $userMessage),
            default => false,
        };
    }

    /**
     * Match on (provider × sum_assured ±1%) for life-category policies.
     * Both fields are extractable with reasonable confidence from typical
     * user phrasing ("Aviva for £300k", "Legal & General £150,000 cover").
     */
    private function protectionPolicyExists(User $user, string $userMessage): bool
    {
        $provider = $this->extractProvider($userMessage);
        $sumAssured = $this->extractCurrencyAmount($userMessage);

        if ($provider === null || $sumAssured === null) {
            return false;
        }

        $tolerance = max(1.0, $sumAssured * 0.01);

        return LifeInsurancePolicy::where('user_id', $user->id)
            ->whereRaw('LOWER(provider) = ?', [strtolower($provider)])
            ->whereBetween('sum_assured', [$sumAssured - $tolerance, $sumAssured + $tolerance])
            ->exists();
    }

    /**
     * Extract a provider name from "with X", "at X", "from X" phrasing.
     * Conservative: only matches names of 1-3 capitalised words to avoid
     * picking up arbitrary nouns.
     */
    private function extractProvider(string $userMessage): ?string
    {
        if (preg_match('/\b(?:with|at|from)\s+([A-Z][A-Za-z&\'\- ]{1,40}?)(?=\s+(?:for|of|£|valued|worth|paying|costing|term|monthly|yearly|annually|per|that|which|started|starting|covering|sum|covers|paying|due|over|until)|[\.,;\?\!]|$)/u', $userMessage, $m) === 1) {
            $provider = trim($m[1]);

            // Strip trailing words that look like sentence continuation
            $provider = preg_replace('/\s+(?:policy|insurance|cover|isa|sipp|account)$/i', '', $provider) ?? $provider;

            if (strlen($provider) >= 2) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Extract the first £-prefixed currency amount. Handles "£300k",
     * "£300,000", "£10.99", "£1.5m" (returns 1_500_000.00).
     */
    private function extractCurrencyAmount(string $userMessage): ?float
    {
        if (preg_match('/£\s*([0-9]+(?:[\.,][0-9]+)*)\s*([kKmM])?/u', $userMessage, $m) !== 1) {
            return null;
        }

        $rawNumber = str_replace(',', '', $m[1]);
        $value = (float) $rawNumber;
        $suffix = strtolower($m[2] ?? '');

        if ($suffix === 'k') {
            $value *= 1000;
        } elseif ($suffix === 'm') {
            $value *= 1_000_000;
        }

        return $value;
    }
}
