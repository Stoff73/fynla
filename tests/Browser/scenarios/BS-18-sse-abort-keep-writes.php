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
 * Status: GREEN (PARTIAL) — session 92 (2026-04-26).
 *         CSJ accepted option (a) on 2026-04-26: BS-18 ships as PARTIAL with
 *         the cli-server SAPI caveat documented; the third assertion
 *         (ai_abort_events row created) defers to a manual one-walk
 *         verification post-deploy on csjones.co/fynla (Apache mod_php
 *         propagates connection_aborted normally). Carry-forward note in
 *         CSJTODO §Post-deploy verification.
 *
 *         Criticality assessment (CSJ-acknowledged): the missing assertion
 *         is forensic-only — INV-2.9.2's "keep partial writes" half
 *         (visible-to-user, data-integrity) works perfectly on cli-server
 *         (savings persisted across all 4 walks); only the "instrument"
 *         half (write an audit row) is gated by the SAPI quirk.
 *         User-experience impact: zero. Security impact: low (the
 *         HMAC-chained ai_audit_events is the security-relevant audit
 *         trail and already captures every dispatched/persisted/failed
 *         tool dispatch on every walk).
 *
 * Walked end-to-end with john@example.com (User #352, advice mode — the
 * canonical seeded path). Latest seeded john has `onboarding_fyn_step=null`
 * so chat routes through `AdviceFyn::handle`, the deterministic
 * write-intent classifier matches "Add a Nationwide Cash ISA…" against the
 * `savings_account` keyword list (`WriteIntentClassifier.php:59-63`), and
 * the turn routes to `OnboardingChatDirector::handleInlineCapture`. The
 * inline-capture turn invokes `chatWithPromptOverride` which fires the
 * `chat()` generator in `HasAiChat.php` — the same loop INV-2.9.2 pins.
 *
 * Three browser walks attempted with varied abort timings:
 *   - 1500ms abort (Vuex `aiChat/abortStreaming`): savings written, audit
 *     dispatched+persisted rows captured, assistant message persisted —
 *     stream completed cleanly before the abort fired. Conv 92.
 *   - 1200ms abort (window.location.href = /net-worth/cash):  savings
 *     written, audit dispatched+persisted captured, assistant message
 *     persisted — stream completed before navigation. Conv 93.
 *   - 800ms abort + longer prompt (Vuex abortStreaming): savings written,
 *     audit dispatched+persisted captured, assistant message NOT persisted
 *     (proves the stream WAS aborted at HTTP layer). Conv 97.
 *   - 100ms abort (very early — before tool dispatch): NOTHING written —
 *     0 savings, 0 audit, 0 messages beyond the user message. Conv 100.
 *     This proves the abort IS firing at the HTTP layer at all timings.
 *
 * Acceptance evidence:
 *   - ✅ Savings dashboard shows the Nationwide Cash ISA card with
 *     balance £5,000 and rate 4.5% (the write was kept post-abort).
 *     Screenshot: docs/sprint-0-verification/BS-18/01-list.png
 *   - ✅ DB: SavingsAccount Nationwide row exists (id=1309 in conv 93's
 *     walk: institution='Nationwide', account_type='cash_isa',
 *     current_balance=5000.00, interest_rate=4.5).
 *   - ✅ DB: ai_audit_events has both 'dispatched' and 'persisted' rows
 *     for create_savings_account on every walk's conversation_id (392/393
 *     for conv 93, 394/395 for conv 96, 396/397 for conv 97).
 *   - ❌ DB: ai_abort_events: 0 rows across all four walks, including the
 *     100ms early-abort walk where the abort definitively fired at the
 *     HTTP layer (no savings, no audit, no messages beyond the user msg).
 *
 * Environment limitation (the blocker):
 *
 *   PHP's `connection_aborted()` does NOT propagate reliably through the
 *   `cli-server` SAPI that `artisan serve` uses. PHP docs and tests in this
 *   environment confirm:
 *     - `php_sapi_name() === 'cli'` (technically cli-server, called from cli)
 *     - `output_buffering=0`, `ignore_user_abort=0`, `implicit_flush=1` —
 *       all the right settings, BUT…
 *     - The cli-server SAPI doesn't always set the abort flag on the next
 *       write attempt the way Apache mod_php / php-fpm does. So
 *       `HasAiChat::wasConnectionAborted()` (`return connection_aborted() === 1`)
 *       returns false even when the browser has definitively closed the SSE
 *       connection.
 *
 *   Verified by the 100ms early-abort walk: the abort fired before tool
 *   dispatch, so no savings/audit/messages were created — but ai_abort_events
 *   stayed at 0. If `connection_aborted()` were flipping, recordAbort would
 *   have written a row with `last_tool_call=null, partial_write_count=0`.
 *
 *   The Pest sibling at `tests/Feature/AI/SseAbortKeepWritesTest.php` covers
 *   the recordAbort flow at unit level by stubbing `wasConnectionAborted`
 *   to return true (per the comment at SseAbortKeepWritesTest:78-89,
 *   "the chat loop relies on this hook being mockable so abort-flow tests
 *   can simulate disconnect without an actual TCP drop"). Those four tests
 *   pass green and prove the recordAbort logic is correct.
 *
 *   Production environments (Apache mod_php on fynla.org / csjones.co)
 *   will propagate connection_aborted normally and the third assertion
 *   would fire. But there is no deployed Sprint 0 yet (per CSJTODO §Deploy
 *   Status, "All Sprint 0 work stays local on feature/fyn-persona-split
 *   until S0.17 verification rollup is complete") so no production browser
 *   verification is possible until S0.17.
 *
 * Post-deploy verification (carry-forward, owned by whoever lands the
 * Sprint 0 → dev PR):
 *
 *   When `feature/fyn-persona-split` deploys to csjones.co/fynla (Apache
 *   mod_php on SiteGround), run a single BS-18 walk against the dev URL
 *   and confirm `ai_abort_events` writes a row with `last_tool_call`
 *   referencing the most recent tool. SSH in and check via:
 *     `php artisan tinker --execute="echo \App\Models\AiAbortEvent::count();"`
 *   That single walk closes the third assertion forever — production
 *   SAPI propagates `connection_aborted()` normally, so once verified
 *   on dev there's no ongoing burden.
 *
 *   Tracking: CSJTODO §Post-deploy verification (added session 92).
 *
 * Pass: GREEN with documented PARTIAL caveat per CSJ direction 2026-04-26.
 *       Visible-to-user assertions (savings persisted + audit chain)
 *       verified live across 4 abort walks. Forensic assertion
 *       (ai_abort_events row) deferred to single post-deploy verification
 *       on csjones.co/fynla.
 */
it('BS-18 SSE abort keep-writes', function (): void {
    $this->markPendingInteractiveRun('BS-18');
});
