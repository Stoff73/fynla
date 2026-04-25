<?php

declare(strict_types=1);

/**
 * BS-18 — SSE abort keeps in-flight writes
 *
 * Sprint: 0
 * Invariants: INV-2.9.2 (SSE-abort policy: keep partial writes, instrument)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-18
 * Pest sibling: tests/Feature/AI/SseAbortKeepWritesTest.php
 * Screenshots: docs/sprint-0-verification/BS-18/
 *
 * Seed: factory user with an active conversation (and ai_chat consent
 *       granted via ConsentService).
 *
 * Script:
 *   1. Login::as($email, $password); open chat.
 *   2. browser_type:
 *      "Add a Nationwide Cash ISA, balance £5,000, rate 4.5%"
 *   3. browser_press_key('Enter')
 *   4. Mid-stream (BEFORE the 'done' event, while assistant is still
 *      typing): use browser_navigate('http://localhost:8000/dashboard')
 *      OR open a new tab and close the chat tab. The aim is to trigger
 *      connection_aborted() server-side. Do NOT use browser_close
 *      (banned per memory feedback_never_close_browser.md).
 *   5. Reopen chat in the same tab; navigate back via the floating button.
 *   6. Navigate to /net-worth/cash via UI menu.
 *   7. browser_take_screenshot → docs/sprint-0-verification/BS-18/01-list.png
 *
 * Assertions:
 *   - Savings dashboard shows the Nationwide Cash ISA card with
 *     balance £5,000 and rate 4.5% (the write was kept post-abort).
 *   - Pest-side: SavingsAccount::where(['user_id' => $user->id,
 *     'institution' => 'Nationwide'])->latest()->first() exists.
 *   - Pest-side: AiAbortEvent::where('conversation_id', $cid)
 *     ->latest()->first() exists with last_tool_call referencing
 *     'create_savings_account' or the most recent tool name.
 *   - Pest-side: ai_audit_events has a 'persisted' row for
 *     create_savings_account on this conversation_id.
 *
 * Pass: abort logged + record persisted + audit row present.
 */
it('BS-18 SSE abort keep-writes', function (): void {
    $this->markPendingInteractiveRun('BS-18');
});
