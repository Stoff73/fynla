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
 * Delivered session 90 (2026-04-26) — GREEN end-to-end via canonical seeded
 * advice-mode walk. Supersedes the session-79 (S0.16b) GREEN note which
 * relied on banned factory shortcuts (User::factory + manual ai_chat consent
 * grant + manual onboarding_completed flip). Session 89's four-seeder
 * consent fix means john@example.com (User #352) now has ai_chat,
 * data_processing, privacy, terms granted from the seeder, and his
 * onboarding_fyn_step=null short-circuits AiChatController::sendMessage:174-176
 * to AdviceFyn — no manual DB pokes required.
 *
 * Walk:
 *   1. Login::as('john@example.com', 'password') via /login form fill +
 *      Sign in click; MFA code 218232 fetched from EmailVerificationCode,
 *      typed digit-by-digit via browser_press_key; landed on /dashboard
 *      with the Fyn chat panel auto-open.
 *   2. Clicked "New conversation" to create AiConversation #80 (the prior
 *      activeConversationId carried a stale token-limit fixture row from
 *      session 89 BS-13 — see fixture-cleanup note below).
 *   3. browser_type "Who are you?" into the Ask Fyn... input;
 *      browser_press_key Enter; waited 8s for the done SSE event.
 *
 * Acceptance verified (live):
 *   - Assistant response (AiMessage #108, persona='advice'):
 *     "I'm Fyn, your personal-finance guidance tool in the Fynla app. I help
 *      you, John, make sense of your finances using your actual data, like
 *      your **£75,000** annual income and **£4,504.78** monthly surplus.
 *
 *      What aspect of your finances would you like to explore today?"
 *   - Positive regex /(guidance|help you understand|Fynla)/i
 *       → MATCH ("guidance" + "Fynla" both present).
 *   - Negative regex /(qualified financial planner|i'?m your adviser|
 *       authorised adviser|regulated adviser)/i
 *       → NO MATCH.
 *   - FCA signposting suffix /seek (advice from|professional|regulated|
 *       FCA-authorised|authorised)/i
 *       → NO MATCH (response treated as factual / general per Spec §BS-21,
 *         not advice mode requiring the suffix).
 *   - DB: AiConversation #80, AiMessage #107 (role=user) + #108
 *       (role=assistant, persona='advice', content matches above exactly).
 *   - DB: AiAuditEvent::where('conversation_id', 80)->count() === 0 —
 *       pure text response, no tool dispatches (matches BS-21 expectation
 *       of a CoreIdentity-only reply, no recommendation/risk-engine reads).
 *   - Network: POST /api/ai-chat/conversations/80/messages → 200 OK,
 *       SSE stream completed without errors.
 *
 * Fixture cleanup (NOT a code fix — test scaffolding from prior session):
 *   - Session 89's BS-13 verification seeded
 *     AiDailyUsage{user_id=352, usage_date=2026-04-26, tokens_used=1_000_000}
 *     to drive the token-limit notice. That row (id=14) was still in the DB
 *     at session-90 start (no seeder writes to ai_daily_usage), pinning
 *     john's daily usage at 1M tokens and short-circuiting HasAiChat::chat
 *     pre-model-call. Deleted via tinker (single row); subsequent send
 *     succeeded with tokenLimitReached=false. Going forward, BS-13 setups
 *     should clean up the fixture row at end of test, or use a per-test DB
 *     transaction.
 *
 * Pass: tone assertions hold both ways (positive guidance language present;
 *       negative adviser language absent; no FCA suffix). DB persistence
 *       and zero-tool-dispatch shape match INV-2.10.1.
 */
it('BS-21 CoreIdentity tone', function (): void {
    $this->markPendingInteractiveRun('BS-21');
});
