<?php

declare(strict_types=1);

/**
 * BS-21 — CoreIdentity tone
 *
 * Sprint: 0
 * Invariants: INV-2.10.1 (CoreIdentity rewrite — guidance only, not adviser)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-21
 * Pest sibling: tests/Architecture/CoreIdentityFramingTest.php
 * Screenshots: docs/sprint-0-verification/BS-21/
 *
 * Seed: young_family preview persona (or any onboarded factory user).
 *
 * Script:
 *   1. Login::asPreviewPersona('young_family') OR Login::as(...)
 *   2. Open chat panel.
 *   3. browser_type "Who are you?" → browser_press_key('Enter')
 *   4. browser_wait_for the assistant response (final 'done' SSE).
 *   5. browser_take_screenshot → docs/sprint-0-verification/BS-21/01-response.png
 *   6. browser_evaluate to grab the assistant bubble's textContent.
 *
 * Assertions:
 *   - Response text matches the regex /(guidance|help you understand|Fynla)/i
 *     (positive: tool self-describes as a guidance tool).
 *   - Response text does NOT match
 *     /(qualified financial planner|i'?m your adviser|authorised adviser|regulated adviser)/i
 *     (negative: no professional-role framing per INV-2.10.1).
 *   - Response text does NOT include the FCA signposting suffix
 *     ("Who are you?" classifies as factual / general, not advice mode).
 *
 * Pass: tone assertions hold both ways.
 *
 * Delivery note (2026-04-25 — S0.16b):
 *   GREEN. Logged in as john@example.com (factory user, advice mode after
 *   onboarding_completed=true), opened AiChatPanel, sent "Who are you?".
 *   Response: "I'm Fyn, your personal-finance guide in the Fynla app.
 *   I help you, John, make sense of your finances with clear, personalised
 *   insights based on your data. What would you like to explore first —
 *   perhaps your top recommendations, cashflow, or adding some details
 *   like income and spending?"
 *   - Positive regex /(guidance|help you understand|Fynla)/i  → MATCH ("Fynla")
 *   - Negative regex /qualified financial planner|i'?m your adviser|...
 *     authorised adviser|regulated adviser/i                  → NO MATCH
 *   - FCA signposting suffix                                  → ABSENT
 *   Screenshots: April/April24Updates/plan/batch1/BS-21/
 *     07-after-send-15s.png + 08-final-pass.png (canonical pass evidence).
 *   Pre-flight notes: preview persona path BLOCKED — AppLayout.vue:120-121
 *   gates AiChatButton + AiChatPanel behind `!isPreviewMode`. Switched to
 *   factory user. Also had to grant ai_chat consent
 *   (ConsentService::recordConsent($u, UserConsent::TYPE_AI_CHAT, true))
 *   and flip $u->onboarding_completed=true to dispatch through AdviceFyn
 *   rather than the onboarding flow.
 */
it('BS-21 CoreIdentity tone', function (): void {
    $this->markPendingInteractiveRun('BS-21');
});
