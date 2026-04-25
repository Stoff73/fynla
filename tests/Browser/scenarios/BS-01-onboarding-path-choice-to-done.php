<?php

declare(strict_types=1);

/**
 * BS-01 — onboarding path-choice-to-done
 *
 * Sprint: 0
 * Invariants: INV-2.2.1 (state machine drives onboarding)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-01
 * Screenshots: docs/sprint-0-verification/BS-01/
 *
 * Seed: factory user `{onboarding_completed: false, onboarding_fyn_step: null}`,
 *       email + password noted for login.
 *
 * Script (interactive, Claude executes via Playwright MCP):
 *   1. browser_navigate('http://localhost:8000')
 *   2. browser_snapshot() — landing page rest state
 *      browser_click on "Sign in" (role=link)
 *   3. browser_fill_form { email, password } → browser_press_key('Enter')
 *   4. If MFA prompt: $code = Login::latestVerificationCode($email);
 *                     browser_type($code) → browser_press_key('Enter')
 *   5. browser_wait_for text="Hi" or "Good afternoon" (first onboarding turn)
 *   6. browser_snapshot — assert two bubbles: "Follow a journey" + "Pick a focus"
 *   7. browser_click "Follow a journey"
 *   8. browser_wait_for next state; assert bubbles
 *      "Starting Out", "Building Foundations", "Protecting What Matters",
 *      "Planning Your Future" all visible
 *   9. browser_click "Protecting What Matters"
 *   10. Walk through each grouped-extract state in turn:
 *       - base_personal: type "DOB 12 Jan 1985, married" → submit
 *       - base_spouse:   type "Angela, 12 Jan 1985, angela@example.com" → submit
 *       - base_dependants: click "No"
 *       - base_employment: click "Full-time"
 *       - base_work: type "ACME Ltd, Engineer, £75,000" → submit
 *       - base_expenditure: type "£2,500" → submit
 *       - profile_review_*: click "Looks correct"
 *       - asset_capture: type "Aviva life cover £300k" → submit, wait for capture
 *   11. add_more state: click "Finish for now" (or matching terminal bubble)
 *   12. browser_wait_for the dashboard heading (role=main)
 *
 * Assertions:
 *   - Each intermediate transition emits its expected SSE event type
 *     (capture via browser_network_requests + AssertSseEvents).
 *   - Pest-side post-run: User::find($id)->onboarding_completed === true.
 *   - browser_take_screenshot per state into docs/sprint-0-verification/BS-01/.
 *
 * Pass: scenario reaches dashboard without error, screenshots captured,
 *       onboarding_completed flipped, no SSE error events in stream.
 */
it('BS-01 onboarding path-choice-to-done', function (): void {
    $this->markPendingInteractiveRun('BS-01');
});
