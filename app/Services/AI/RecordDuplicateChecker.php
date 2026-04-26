<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\User;
use App\Services\Onboarding\AssetCaptureEntityExtractor;

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
    public function __construct(
        private readonly AssetCaptureEntityExtractor $extractor,
    ) {}

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
            'protection_policy' => $this->allEntitiesExist($user, 'protection', $userMessage),
            default => false,
        };
    }

    /**
     * Suppress the inline-capture route when EVERY entity the deterministic
     * extractor finds in the user message is already persisted in the
     * target module within the 24h dedup window (S0.11.5 / INV-2.9.5).
     *
     * BS-19 contract: a user retrying the same multi-entity message
     * ("I have Aviva life insurance £300k and Vitality critical illness £100k")
     * must not produce duplicate rows. The Pest sibling
     * (tests/Feature/AI/GapFillDedupTest.php) already covers
     * AssetCaptureEntityExtractor::findMissing in isolation; reusing it
     * here is what stops the LLM-direct path (handleInlineCapture invokes
     * the LLM with create_* tools allowed) from re-firing a duplicate
     * create_protection_policy on the second turn.
     *
     * Returns true ONLY when extractedEntities > 0 AND findMissing(user)
     * returns []. Partial cases (some new + some existing) fall through
     * to inline-capture so the new entities still persist via gap-fill;
     * the existing-entity duplicate that the LLM may emit on the partial
     * path is the residual edge case the gap-fill in-message dedup
     * already partially mitigates.
     */
    private function allEntitiesExist(User $user, string $focus, string $userMessage): bool
    {
        $extracted = $this->extractor->extractForFocus($focus, $userMessage);

        if ($extracted === []) {
            return false;
        }

        $missing = $this->extractor->findMissing($focus, $extracted, [], $user);

        return $missing === [];
    }
}
