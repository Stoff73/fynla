<?php

declare(strict_types=1);

/**
 * BS-16 — billing: "where's my invoice"
 *
 * Sprint: 0
 * Invariants: INV-2.7.2 (billing tools present on both providers)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-16
 * Pest siblings: tests/Feature/AI/BillingToolsTest.php,
 *                tests/Architecture/ToolCatalogueParityTest.php
 * Screenshots: docs/sprint-0-verification/BS-16/
 *
 * Seed: user with an active Subscription + 3 Invoice rows
 *       (Subscription::factory()->create(['user_id' => $user->id, 'status' => 'active']);
 *        Invoice::factory(3)->create(['user_id' => $user->id]);).
 *
 * Script:
 *   1. Login::as($email, $password)
 *   2. Open chat panel.
 *   3. browser_type "Where's my invoice?" → browser_press_key('Enter')
 *   4. browser_wait_for the assistant response and final 'done' SSE.
 *   5. Capture network requests via browser_network_requests.
 *   6. browser_take_screenshot → docs/sprint-0-verification/BS-16/01-chat.png
 *   7. Click the navigation CTA the assistant emits (e.g. "View invoices").
 *   8. browser_wait_for the Subscription Management page.
 *   9. browser_take_screenshot → docs/sprint-0-verification/BS-16/02-page.png
 *
 * Assertions:
 *   - SSE stream contains tool_use events for both 'get_subscription_status'
 *     and 'list_invoices'.
 *   - Response text mentions the active subscription status + invoice count
 *     (regex /3 invoice/i or /three invoices/i).
 *   - SSE stream contains one navigation event with route matching
 *     /\/settings\/(subscription|invoices)/.
 *   - The destination page shows the 3 invoices (matching invoice_number,
 *     amount, status).
 *   - Pest-side: invoice rows in DOM correlate by id with Invoice::where(
 *     'user_id', $user->id)->get().
 *
 * Pass: both tools called; navigation works; invoice list matches DB.
 */
it('BS-16 billing where is my invoice', function (): void {
    $this->markPendingInteractiveRun('BS-16');
});
