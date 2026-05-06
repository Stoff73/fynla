<?php

declare(strict_types=1);

/**
 * BS-22 — consent-required mid-session
 *
 * Sprint: 0
 * Invariants: INV-2.10.3 (runtime consent gate via ConsentService)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-22
 * Pest sibling: tests/Feature/AI/ConsentRuntimeCheckTest.php
 * Screenshots: docs/sprint-0-verification/BS-22/
 *
 * Seed: factory user with ai_chat consent granted at start
 *       (ConsentService::recordConsent($user, UserConsent::TYPE_AI_CHAT, true)).
 *
 * Script:
 *   1. Login::as($email, $password)
 *   2. Open chat panel; send a benign message
 *      (e.g. "Hi") and confirm it succeeds.
 *   3. browser_take_screenshot → docs/sprint-0-verification/BS-22/01-pre.png
 *   4. Open a NEW tab via browser_tabs (do NOT close the first tab —
 *      memory feedback_never_close_browser.md).
 *      browser_navigate('http://localhost:8000') in the new tab.
 *   5. Click avatar → "Settings" → "Privacy" tab.
 *   6. Toggle off "AI chat consent".
 *   7. Switch back to the FIRST tab via browser_tabs.
 *   8. browser_type a new message into the chat.
 *   9. browser_press_key('Enter')
 *  10. browser_wait_for the consent-required system event.
 *  11. browser_take_screenshot → docs/sprint-0-verification/BS-22/02-blocked.png
 *
 * Assertions:
 *   - SSE stream contains a consent_required event (or the API returns 403
 *     with body {error: 'consent_required', required: 'ai_chat'}).
 *   - DOM renders a consent gate modal with a "Re-grant consent" CTA.
 *   - Pest-side: AiMessage::where('conversation_id', $cid)
 *     ->latest()->first()->content does NOT include the message that was
 *     blocked (no row written for the blocked turn).
 *
 * Pass: gate renders; no chat-message DB write for the blocked turn.
 */
it('BS-22 consent-required mid-session', function (): void {
    $this->markPendingInteractiveRun('BS-22');
});
