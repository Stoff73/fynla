<?php

declare(strict_types=1);

/**
 * BS-11 — handoff invisibility (no persona_state_change, no capturing
 * pill, no inline bubbles)
 *
 * Sprint: 0
 * Invariants: INV-2.4.1 (zero persona_state_change SSE),
 *             INV-2.4.2 (inline capture emits conversational only)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-11
 * Pest siblings: tests/Feature/Fyn/HandoffInvisibilityTest.php,
 *                tests/Feature/Onboarding/InlineCaptureSilenceTest.php
 * Screenshots: docs/sprint-0-verification/BS-11/
 *
 * Seed: young_family preview persona (already onboarded).
 *
 * Script:
 *   1. Login::asPreviewPersona('young_family')
 *   2. Open chat panel.
 *   3. browser_snapshot — capture rest-state DOM and the input
 *      placeholder text via browser_evaluate
 *      `() => document.querySelector('input[type=text],textarea')?.placeholder`.
 *   4. browser_take_screenshot → docs/sprint-0-verification/BS-11/01-rest.png
 *   5. browser_type:
 *      "I need life cover advice — also, please add a life policy with
 *       Aviva for £300k"
 *   6. browser_press_key('Enter')
 *   7. browser_wait_for the final 'done' SSE event.
 *   8. browser_take_screenshot → docs/sprint-0-verification/BS-11/02-after.png
 *   9. Capture network requests via browser_network_requests; decode SSE
 *      via AssertSseEvents::fromNetworkRequests.
 *  10. Re-evaluate the input placeholder text.
 *  11. Navigate to /protection via UI menu (NOT typed URL).
 *
 * Assertions:
 *   - AssertSseEvents::assertNoEventType($events, 'persona_state_change').
 *   - AssertSseEvents::windowBetween($events, 'content', 'done') contains
 *     zero 'quick_replies' events (inline capture must not surface bubbles).
 *   - DOM: no element matching capturing-pill selector
 *     (e.g. .capturing-pill, [data-capturing], etc. — none should exist).
 *   - Input placeholder text in step 10 equals the value captured in step 3
 *     (no "Capturing..." swap).
 *   - /protection page shows an Aviva life policy row with sum_assured £300k.
 *   - Pest-side: LifeInsurancePolicy::where(['user_id' => $user->id,
 *     'provider' => 'Aviva'])->whereDate('created_at', today())->exists()
 *     === true.
 *
 * Pass: all SSE / DOM / DB assertions hold.
 */
it('BS-11 handoff invisibility', function (): void {
    $this->markPendingInteractiveRun('BS-11');
});
