<?php

declare(strict_types=1);

/**
 * BS-13 — system-message token-limit renders distinctly
 *
 * Sprint: 0
 * Invariants: INV-2.4.4 (system messages exempt from handoff-invisibility)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-13
 * Screenshots: docs/sprint-0-verification/BS-13/
 *
 * Seed: factory user with ai_daily_usage.tokens_used set near plan cap
 *       (e.g. 99% of the user's daily budget — see HasAiGuardrails for the
 *       computed cap per persona / plan tier).
 *       Pest setup:
 *         AiDailyUsage::updateOrCreate(
 *           ['user_id' => $user->id, 'usage_date' => today()],
 *           ['tokens_used' => $cap - 100]
 *         );
 *
 * Script:
 *   1. Login::as($email, $password)
 *   2. Open chat panel.
 *   3. browser_type a long enough message to push past the limit (e.g.
 *      a 500-char prompt that triggers a model call larger than 100 tokens).
 *   4. browser_press_key('Enter')
 *   5. browser_wait_for the SSE token_limit event (or the rendered
 *      system-notice element).
 *   6. browser_take_screenshot → docs/sprint-0-verification/BS-13/notice.png
 *   7. Capture network requests via browser_network_requests.
 *
 * Assertions:
 *   - SSE stream contains an event with type='token_limit'
 *     (AssertSseEvents::assertEventTypeEmitted).
 *   - DOM renders a system-notice block visually distinct from the
 *     assistant chat bubble (different background, e.g. violet-50, an
 *     icon-less notice card with reset-time copy).
 *   - The notice text mentions the daily reset window
 *     (regex /(reset|tomorrow|allowance|daily limit)/i).
 *   - Pest-side: ai_daily_usage row was NOT incremented again
 *     (the new request was rejected before model call).
 *
 * Pass: system event present; distinct rendering; no second token charge.
 */
it('BS-13 token-limit system message', function (): void {
    $this->markPendingInteractiveRun('BS-13');
});
