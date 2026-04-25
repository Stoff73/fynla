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
 */
it('BS-21 CoreIdentity tone', function (): void {
    $this->markPendingInteractiveRun('BS-21');
});
