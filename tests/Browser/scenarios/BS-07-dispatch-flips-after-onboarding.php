<?php

declare(strict_types=1);

/**
 * BS-07 — dispatch flips to AdviceFyn after onboarding_completed
 *
 * Sprint: 0
 * Invariants: INV-2.1.1 (dispatch path), INV-2.1.3 (dispatch condition)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-07
 * Screenshots: docs/sprint-0-verification/BS-07/
 *
 * Seed: factory user at terminal state add_more, with onboarding_completed
 *       still false. Confirms "finish" then asks an Advice-mode question.
 *
 * Script:
 *   1. Login::as($email, $password)
 *   2. Chat panel opens at add_more.
 *   3. browser_click "Finish for now" (or matching terminal bubble).
 *   4. browser_wait_for chat panel exit-onboarding state.
 *   5. Navigate to dashboard via UI.
 *   6. Open chat panel via the floating button.
 *   7. browser_type "What's my net worth?" → browser_press_key('Enter')
 *   8. browser_wait_for the assistant response and the final 'done' SSE.
 *   9. Capture network requests via browser_network_requests.
 *
 * Assertions:
 *   - Pest-side: User::find($id)->onboarding_completed === true.
 *   - SSE stream contains zero quick_replies events for this turn
 *     (advice mode never emits quick_replies — that's onboarding-only).
 *   - SSE stream contains either one advice_response event (recommendation
 *     mode) OR a content + navigation pair (factual mode). For
 *     "what's my net worth" the classifier routes to net_worth_query
 *     (factual) — expect content + optional navigation, zero advice_response.
 *   - DOM: chat panel shows the assistant reply rendered as a normal
 *     content bubble (no onboarding-specific layout).
 *
 * Pass: SSE shape matches Advice Fyn expectations; onboarding_completed
 *       persisted; no onboarding events.
 */
it('BS-07 dispatch flips to AdviceFyn after onboarding_completed', function (): void {
    $this->markPendingInteractiveRun('BS-07');
});
