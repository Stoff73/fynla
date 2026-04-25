<?php

declare(strict_types=1);

/**
 * BS-14 — direct-write create_savings_account from chat
 *
 * Sprint: 0
 * Invariants: INV-2.5.1 (every create_* / update_* / capture_* handler
 *             writes to DB synchronously)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-14
 * Pest siblings: tests/Feature/AI/DirectWrite/CreateSavingsAccountTest.php,
 *                tests/Feature/AI/DirectWriteCoverageTest.php
 * Screenshots: docs/sprint-0-verification/BS-14/
 *
 * Seed: young_saver preview persona OR factory user with no savings rows.
 *
 * Script:
 *   1. Login::asPreviewPersona('young_saver') OR Login::as(...)
 *   2. Open chat panel.
 *   3. browser_type:
 *      "Add a Cash ISA with Nationwide, balance £5,000, interest 4.5%"
 *   4. browser_press_key('Enter')
 *   5. browser_wait_for the assistant ack and the final 'done' SSE.
 *   6. browser_take_screenshot → docs/sprint-0-verification/BS-14/01-chat.png
 *   7. Navigate to /net-worth/cash via the UI menu (Savings dashboard).
 *   8. browser_take_screenshot → docs/sprint-0-verification/BS-14/02-list.png
 *   9. Capture network requests via browser_network_requests.
 *
 * Assertions:
 *   - Savings dashboard shows a card with provider 'Nationwide',
 *     balance £5,000.00, rate 4.5%, type Cash ISA.
 *   - Pest-side: SavingsAccount::where('user_id', $user->id)
 *     ->latest()->first() is the new row with matching values.
 *   - SSE stream contains exactly one tool_use event with
 *     name='create_savings_account'
 *     (AssertSseEvents::assertEventTypeCount($events, 'tool_use', ≥1)
 *      then filter on tool name).
 *   - SSE stream contains one entity_created event referencing the new
 *     SavingsAccount id.
 *   - Pest-side: ai_audit_events has matching dispatched + persisted rows.
 *
 * Pass: record visible in UI + DB; SSE shape matches direct-write contract;
 *       audit chain unbroken.
 */
it('BS-14 direct-write create_savings_account from chat', function (): void {
    $this->markPendingInteractiveRun('BS-14');
});
