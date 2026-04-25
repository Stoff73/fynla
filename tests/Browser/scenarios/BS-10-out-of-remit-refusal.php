<?php

declare(strict_types=1);

/**
 * BS-10 — out-of-remit canonical refusal
 *
 * Sprint: 0
 * Invariants: INV-2.3.4 (out-of-remit canonical refusal)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-10
 * Pest sibling: tests/Feature/Fyn/OutOfRemitTest.php
 * Screenshots: docs/sprint-0-verification/BS-10/
 *
 * Seed: any advice-mode user (preview persona or onboarded factory user).
 *
 * Script:
 *   1. Login::asPreviewPersona('young_family') OR Login::as(...)
 *   2. Open chat panel.
 *   3. browser_type "Should I take antibiotics for a persistent cough?"
 *      (chosen because the data_entry classifier doesn't steal "I have a..."
 *      — see OutOfRemitTest delivery note).
 *   4. browser_press_key('Enter')
 *   5. browser_wait_for the assistant response.
 *   6. Capture network requests via browser_network_requests.
 *
 * Assertions:
 *   - Response body text exactly matches:
 *     "I'm able to help you with your finances. Medical advice is out of scope."
 *     (or matching detected_topic — Medical advice / Legal advice /
 *     Emotional support / General knowledge).
 *   - SSE stream has zero tool_use events
 *     (AssertSseEvents::assertNoEventType($events, 'tool_use')).
 *   - Response does NOT include the FCA signposting suffix
 *     ("For regulated advice personal to your circumstances...").
 *   - Pest-side: AiMessage rows contain the user message + assistant
 *     refusal both with persona='advice'.
 *
 * Pass: exact refusal string in DOM; zero tool calls; no FCA suffix.
 *
 * Delivery note (2026-04-25 — S0.16b):
 *   GREEN. Logged in as john@example.com (advice mode), sent
 *   "Should I take antibiotics for a persistent cough?".
 *   Response body (rendered, exact):
 *     "I'm able to help you with your finances. Medical advice is out of scope."
 *   - SSE / DB: AiAuditEvent::where('conversation_id', 88)->count() === 0
 *     → zero tool_use events as required.
 *   - DB: AiMessage rows 127 (user, persona='advice') + 128 (assistant,
 *     persona='advice') — both persisted as required.
 *   - FCA signposting suffix: ABSENT.
 *   Screenshot: April/April24Updates/plan/batch1/BS-10/01-refusal.png
 */
it('BS-10 out-of-remit refusal', function (): void {
    $this->markPendingInteractiveRun('BS-10');
});
