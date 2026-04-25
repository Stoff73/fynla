<?php

declare(strict_types=1);

/**
 * BS-17 — multi-entity persist (Sprint 0: 4-focus gap-fill)
 *
 * Sprint: 0 (4-focus path) + 2 (18-focus batch tools — out of S0.16 scope)
 * Invariants: INV-2.8.1 (Sprint 0 4-focus gap-fill running on both paths)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-17
 * Pest sibling: tests/Feature/AI/MultiEntityGapFillTest.php
 * Screenshots: docs/sprint-0-verification/BS-17/
 *
 * Seed: young_family preview persona OR factory user with no protection.
 *
 * Script:
 *   1. Login::asPreviewPersona('young_family') OR Login::as(...)
 *   2. Open chat panel.
 *   3. browser_type:
 *      "I have Aviva life insurance £300k and Vitality critical illness £100k"
 *   4. browser_press_key('Enter')
 *   5. browser_wait_for assistant ack and final 'done' SSE.
 *   6. browser_take_screenshot → docs/sprint-0-verification/BS-17/01-chat.png
 *   7. Navigate to /protection via UI menu.
 *   8. browser_take_screenshot → docs/sprint-0-verification/BS-17/02-list.png
 *   9. Re-open chat; submit the IDENTICAL message a second time.
 *  10. browser_wait_for completion.
 *  11. Re-navigate to /protection.
 *  12. browser_take_screenshot → docs/sprint-0-verification/BS-17/03-after-retry.png
 *
 * Assertions:
 *   - After step 7: 2 protection cards visible — Aviva life £300k and
 *     Vitality CI £100k.
 *   - Pest-side after step 6: LifeInsurancePolicy::where('user_id', $user->id)
 *     ->whereDate('created_at', today())->count() === 1
 *     AND CriticalIllnessPolicy::where('user_id', $user->id)
 *     ->whereDate('created_at', today())->count() === 1.
 *   - After step 10: Pest-side counts UNCHANGED (still 1 + 1, total 2 cards).
 *     This pins the gap-fill DB dedup invariant from S0.11 (also covered
 *     by BS-19 from the SSE side).
 *
 * Pass: 2 records persisted on first run; retry leaves count at 2 (no doubles).
 */
it('BS-17 multi-entity persist', function (): void {
    $this->markPendingInteractiveRun('BS-17');
});
