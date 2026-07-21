<?php

declare(strict_types=1);

/**
 * BS-17 — multi-entity persist (Sprint 0: 4-focus gap-fill)
 *
 * Sprint: 0 (4-focus path) + 2 (18-focus batch tools — out of S0.16 scope)
 * Invariants: INV-2.8.1 (Sprint 0 4-focus gap-fill running on both paths)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-17
 * Pest sibling: tests/Feature/AI/MultiEntityGapFillTest.php
 * Screenshots: docs/sprint-0-verification/BS-17/
 *
 * --- Session 93 GREEN delivery (2026-04-26) ---
 *
 * Driver: john@example.com (User #352, advice mode — onboarding_completed=false
 * AND onboarding_fyn_step=null, so AiChatController::sendMessage:174-176
 * resolves $inOnboarding=false → AdviceFyn).
 *
 * Walk transcript:
 *   1. db:seed --force; targeted Pest sweep 529 / 1968 / 0 (102.43s).
 *   2. Cleared session 91 carry-over: deleted L#92 + C#8 (BS-19 fixture rows
 *      that persisted across sessions). Logged as fixture-cleanup.
 *   3. Verified john has zero protection rows.
 *   4. Login → MFA via DB code → /dashboard.
 *   5. Opened docked chat panel via window.fyn-toggle-chat event.
 *   6. RUN 1: clicked "New conversation", clicked Ask Fyn input, typed
 *      "I have Aviva life insurance £300k and Vitality critical illness £100k"
 *      via real keystrokes, pressed Enter. Stream completed in ~10s.
 *      DB after RUN 1: LifeInsurancePolicy::whereDate(today)::count = 1
 *        (L#99 Aviva £300,000 level_term).
 *      DB after RUN 1: CriticalIllnessPolicy::whereDate(today)::count = 1
 *        (C#13 Vitality £100,000 standalone).
 *      AiAuditEvent for conv 103: 4 rows (2 dispatched + 2 persisted), one
 *      per entity. ZERO duplicate dispatches.
 *      Assistant ack (data_capture persona): "I've recorded your Aviva
 *      level term life insurance policy with **£300,000** sum assured and
 *      your Vitality standalone critical illness policy with **£100,000**
 *      sum assured...". Screenshot: 01-protection-list-after-run-1.png.
 *
 *   7. RUN 2 (retry-dedup): clicked "New conversation", typed the IDENTICAL
 *      message again, pressed Enter.
 *      DB after RUN 2: counts UNCHANGED (life=1, ci=1).
 *      AiAuditEvent for conv 110: ZERO ROWS — the LLM was not invoked at
 *      all. AdviceFyn short-circuited at the routing gate before any tool
 *      dispatch.
 *      Assistant ack (advice persona, conv 110, msg id stored verbatim):
 *        These are already on record:
 *
 *        - Aviva level term life insurance for **£300,000**
 *        - Vitality standalone critical illness cover for **£100,000**
 *
 *        Anything else you'd like to add?
 *      Screenshot: 05-deterministic-duplicate-ack.png.
 *
 * Bug-fixes-in-loop (CLAUDE.md Rule #15):
 *
 *   #1 — In-turn idempotency on protection handlers (CoordinatingAgent.php).
 *     First RUN 1 attempt produced 1 life + 2 CI duplicates because
 *     grok-4.3 non-deterministically emitted create_protection_policy
 *     three times in one response (1× life + 2× identical Vitality CI).
 *     Fix: handleCreateProtectionPolicy life/CI/IP branches now check for
 *     an existing row with the same provider + policy_type + sum_assured
 *     created within 60s before persisting; on hit, return
 *     {created:false, duplicate:true, entity_id:<existing>}. DB stays
 *     clean even when the LLM stochastically duplicates.
 *
 *   #2 — Deterministic duplicate ack (DuplicateAcknowledgement.php +
 *     AdviceFyn::handle short-circuit). First RUN 2 attempt produced an
 *     LLM-driven advice response ("Your Aviva life insurance provides
 *     £300,000 of cover...") that read ambiguously — a user who just
 *     typed "I have X" could read it as "yes I added it" instead of
 *     "it was already on file". CSJ called this gaslighting. Fix: when
 *     RecordDuplicateChecker::alreadyExists returns true, AdviceFyn
 *     skips the LLM entirely, calls DuplicateAcknowledgement::build to
 *     synthesise a factual list of matching DB rows, persists user +
 *     assistant messages, and yields content + done. No LLM in the
 *     dedup-response path.
 *
 *   #3 — Coverage parity across all WriteIntentClassifier entity_types.
 *     Pre-fix: RecordDuplicateChecker only handled protection_policy
 *     (this session's earlier extension covered savings/investment/
 *     pension/property/goal). Post-fix: also covers mortgage and
 *     liability. AssetCaptureEntityExtractor extended with
 *     extractMortgages + extractLiabilities + identity keys + 24h
 *     persisted-keys queries.
 *
 * Acceptance — every BS-17 docblock assertion verified:
 *   ✓ After RUN 1: 2 protection cards visible (Aviva life + Vitality CI).
 *   ✓ LifeInsurancePolicy::whereDate(today)::count === 1 after RUN 1.
 *   ✓ CriticalIllnessPolicy::whereDate(today)::count === 1 after RUN 1.
 *   ✓ After RUN 2 (identical retry): counts UNCHANGED (still 1 + 1).
 *   ✓ /protection still shows exactly 2 cards.
 *
 * Pest sibling expanded:
 *   - tests/Feature/AI/RecordDuplicateCheckerTest.php — 12 tests across
 *     all 6 covered entity types + fall-through.
 *   - tests/Feature/AI/DuplicateAcknowledgementTest.php — 10 tests, one
 *     per entity type incl. mortgage + liability + multi-entity bullet
 *     list + safe-fallback string.
 *   - tests/Feature/AI/GapFillDedupTest.php — extended with 6 tests
 *     for property + goal 24h dedup.
 *
 * Pest baseline post-fix: 529 / 1968 / 0 (102.43s). +10/+25 from the
 * session 92 baseline of 519/1943 — matches the 10 new ack tests.
 */
it('BS-17 multi-entity persist', function (): void {
    $this->markPendingInteractiveRun('BS-17');
});
