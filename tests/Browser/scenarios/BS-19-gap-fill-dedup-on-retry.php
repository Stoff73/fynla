<?php

declare(strict_types=1);

/**
 * BS-19 — gap-fill dedup on retry
 *
 * Sprint: 0
 * Invariants: INV-2.9.5 (gap-fill dedup against DB)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-19
 * Pest sibling: tests/Feature/AI/GapFillDedupTest.php
 * Screenshots: docs/sprint-0-verification/BS-19/
 *
 * Seed: same as BS-17 — young_family preview persona OR factory user
 *       with no existing protection rows.
 *
 * Script:
 *   1. Login::asPreviewPersona('young_family') OR Login::as(...)
 *   2. Open chat panel.
 *   3. browser_type the BS-17 multi-entity protection message:
 *      "I have Aviva life insurance £300k and Vitality critical illness £100k"
 *   4. browser_press_key('Enter'); wait for completion.
 *   5. Navigate to /protection via UI; count visible cards.
 *      browser_take_screenshot → docs/sprint-0-verification/BS-19/01-first-run.png
 *   6. Re-open chat; submit the IDENTICAL message.
 *   7. Wait for completion.
 *   8. Re-navigate to /protection.
 *      browser_take_screenshot → docs/sprint-0-verification/BS-19/02-after-retry.png
 *   9. Capture both runs' network requests via browser_network_requests
 *      to verify SSE event counts for each.
 *
 * Assertions:
 *   - After first run: 2 protection cards visible
 *     (1 LifeInsurancePolicy + 1 CriticalIllnessPolicy).
 *   - After second identical run: still 2 cards (no doubles).
 *   - Pest-side: LifeInsurancePolicy::where('user_id', $user->id)
 *     ->whereDate('created_at', today())->count() === 1 after both runs.
 *   - Pest-side: CriticalIllnessPolicy::where('user_id', $user->id)
 *     ->whereDate('created_at', today())->count() === 1 after both runs.
 *   - SSE stream of the SECOND run contains zero gap-fill synthesised
 *     events (the AssetCaptureEntityExtractor short-circuits via the
 *     24h DB dedup window).
 *
 * Pass: dedup holds across retries from both UI and SSE perspectives.
 */
it('BS-19 gap-fill dedup on retry', function (): void {
    $this->markPendingInteractiveRun('BS-19');
});
