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
 * Status: GREEN — session 92 (2026-04-26)
 *
 * Walked end-to-end with chris@fynla.org (User #1, role=admin) on local dev.
 * Seeded 20 audit events via `AuditChainService::append` (canonical path,
 * no factory shortcut), covering a mix of read/write/dispatched/persisted/
 * failed states across list_records, get_module_analysis, create_protection_policy,
 * create_savings_account, set_expenditure, get_recommendations,
 * get_subscription_status. Tip hash:
 *   ad21969118b3bedf510b258fc2dd3bd70ef604e5c2cc9c5b49707d56f522e02f
 *
 * Walk steps verified live in Playwright:
 *   1. Sign in chris@fynla.org / Password1!.
 *   2. MFA via local DB code (per CLAUDE.md "Local dev — get the code yourself").
 *   3. Navigate to /admin → click AI in admin tabs → click "AI Audit" submenu.
 *   4. Click "Chain view" sub-tab (top of the AI Audit panel).
 *   5. Banner renders "Chain valid · 20 rows · tip ad21969118b3…" with full
 *      64-char tip_hash exposed via the `data-tip-hash` attribute and the
 *      `title` tooltip ("tip <full hash>").
 *   6. Table shows all 20 rows with the expected columns
 *      (#, Tool, Op, Status, User, Entity, Hash, When), DESC by id.
 *   7. Click "Re-verify" → banner refreshes, banner text + tip_hash unchanged.
 *   8. `php artisan ai:audit:verify-chain` returned exit 0 with body
 *      `{"chain_valid":true,"tip_hash":"ad21969118b3…f522e02f","row_count":20}` —
 *      banner tip_hash matches artisan tip_hash byte-for-byte.
 *
 * Acceptance evidence:
 *   - Banner DOM data-tip-hash:
 *     ad21969118b3bedf510b258fc2dd3bd70ef604e5c2cc9c5b49707d56f522e02f
 *   - Artisan tip_hash:
 *     ad21969118b3bedf510b258fc2dd3bd70ef604e5c2cc9c5b49707d56f522e02f
 *   - DOM rowCount: 20    Artisan row_count: 20    DB AiAuditEvent::count(): 20
 *   - Screenshot: docs/sprint-0-verification/BS-15/01-list.png
 *
 * Bugs fixed in the same loop (per CLAUDE.md Rule #15 LOOP UNTIL CORRECT):
 *
 * (1) AuditChainService canonical-JSON hash fix (production INV-2.10.2 violation).
 *     Loading the chain on session start surfaced
 *     `{chain_valid:false, broken_at:337, row_count:0}` against 16 real rows
 *     written during sessions 89-91. Re-creating the same row payload via
 *     `append()` then immediately calling `verifyChain()` reproduced the
 *     mismatch in-process — a deterministic, repeatable INV-2.10.2 break.
 *
 *     Root cause: MySQL JSON column type reorders object keys on storage —
 *     keys are sorted by length ascending, ties by insertion order. The
 *     write-time hash uses the PHP array's iteration order; the verify-time
 *     hash uses MySQL's reordered cast-back. For any payload where the PHP
 *     order doesn't already match MySQL's sort, the hashes diverge and
 *     verify reports `broken_at`. The Pest sibling HashChainTest happened
 *     to use `['index', 'preview']` (length 5 vs 7 — already MySQL-sorted)
 *     so the bug was masked from the test suite.
 *
 *     Fix: `AuditChainService::computeRowHash` now canonicalises the
 *     payload via `canonicaliseForHash` (recursive deep-ksort on associative
 *     arrays; numeric lists preserve order) before json_encode. Applies to
 *     both write and verify, making the hash input independent of either
 *     MySQL's internal sort or PHP's array iteration order. Spec INV-2.10.2
 *     requires "serialised(fields_except_hashes)" but does not specify
 *     byte-level ordering, so canonicalising is consistent.
 *
 *     Regression test added at `tests/Feature/Audit/HashChainTest.php`
 *     ("verifies chains whose input_summary keys MySQL would reorder")
 *     with the canonical reproducer payloads `['preview', 'q']` and
 *     `['preview', 'q', 'provider', 'sum_assured', 'policy_type']`.
 *
 * (2) AiAudit.vue Chain view banner missing tip_hash.
 *     The pre-fix banner read only "Chain valid · {row_count} rows" with no
 *     tip_hash anywhere in the DOM, blocking the BS-15 assertion that the
 *     banner tip_hash matches artisan output. Banner now appends "· tip
 *     {first-12-chars}…" visually and exposes the full 64-char hash via
 *     both `:data-tip-hash` (for headless DOM scraping) and `:title` (for
 *     user-facing tooltip on hover).
 *
 * (3) loadChain/verifyChain payload double-unwrap bug.
 *     `aiAuditService.getChain()` already returns `response.data` (axios
 *     unwrapped), so the component should read `response.data` as the
 *     paginator object and `paginator.data` as the rows array. The
 *     pre-fix code did `response.data?.data || response.data` which
 *     returned the rows array as `payload`, then read `payload.data` (an
 *     array has no `.data`) → empty. Verified live: pre-fix table showed
 *     "No audit rows match these filters" against an API returning 20
 *     rows. Fix simplified to `paginator = response.data || {}` and
 *     `chainEvents = paginator.data || []`. Same single-unwrap pattern
 *     applied to verifyChain (was working by accidental fall-through).
 *
 * Pest baseline: 501/1924 GREEN (was 486/1591 pre-Audit-suite-add and pre-fix;
 * the +15 tests are 14 from `tests/Feature/Audit/` previously not in the
 * standard sweep + 1 new regression added in this session).
 */
it('BS-15 hash-chain audit admin view', function (): void {
    $this->markPendingInteractiveRun('BS-15');
});
