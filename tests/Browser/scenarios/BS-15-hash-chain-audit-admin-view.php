<?php

declare(strict_types=1);

/**
 * BS-15 — hash-chain audit admin view
 *
 * Sprint: 0
 * Invariants: INV-2.5.4 (audit trail matches reality),
 *             INV-2.10.2 (hash-chain audit table)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-15
 * Pest siblings: tests/Feature/Audit/HashChainTest.php,
 *                tests/Feature/Audit/HmacSigningTest.php,
 *                tests/Feature/Audit/ChainTamperDetectionTest.php
 * Screenshots: docs/sprint-0-verification/BS-15/
 *
 * Seed: admin user chris@fynla.org + 20 ai_audit_events rows seeded via:
 *         AiAuditEvent::factory(20)->create() OR loop AuditChainService::append.
 *       Both must produce a valid chain (hash_chain test green pre-run).
 *
 * Script:
 *   1. Login::as('chris@fynla.org', 'Password1!')
 *      MFA: ASK USER for the production code per CLAUDE.md (this is the
 *      one persona with admin rights). Local-dev factories of admin users
 *      can use Login::latestVerificationCode().
 *   2. Click avatar → "Admin" menu item.
 *   3. browser_wait_for the admin landing page.
 *   4. Click the "AI Audit" tab.
 *   5. Click the new "Chain view" sub-tab.
 *   6. browser_take_screenshot → docs/sprint-0-verification/BS-15/01-list.png
 *   7. browser_evaluate to read the integrity banner text.
 *   8. Click "Re-verify" button on the banner.
 *   9. browser_wait_for the banner to refresh.
 *  10. Run `php artisan ai:audit:verify-chain` via the bash tool.
 *
 * Assertions:
 *   - Table lists 20 audit rows with columns: timestamp, user_id, tool,
 *     operation, status, hash.
 *   - Banner shows "Chain valid" with row_count=20 and a 64-char tip_hash.
 *   - The artisan command (step 10) returns exit 0 with JSON body
 *     {chain_valid: true, tip_hash: <hash>, row_count: 20}.
 *   - The tip_hash in the banner matches the artisan output's tip_hash.
 *   - Pest-side: AiAuditEvent::count() === 20.
 *
 * Pass: UI banner matches artisan output exactly; row count matches DB.
 */
it('BS-15 hash-chain audit admin view', function (): void {
    $this->markPendingInteractiveRun('BS-15');
});
