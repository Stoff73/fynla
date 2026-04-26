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
 * Delivery note (2026-04-26 — session 89, S0.16b):
 *   GREEN. Logged in as john@example.com (User #352, advice mode — onboarding_fyn_step
 *   is null so AiChatController::sendMessage:174-176 routes to AdviceFyn).
 *   Started a new conversation (clicked "New conversation" → AiConversation #74),
 *   typed "Should I take antibiotics for a persistent cough?" and pressed Enter.
 *
 *   Acceptance evidence (live browser, not Pest):
 *   - DOM: paragraph rendered with the exact canonical refusal text
 *     "I'm able to help you with your finances. Medical advice is out of scope."
 *   - DB: AiMessage rows persisted as required —
 *     #102 role=user      persona='advice' content=full prompt
 *     #103 role=assistant persona='advice' content=canonical refusal
 *   - DB: AiAuditEvent::where('conversation_id', 74)->count() === 0
 *     → zero tool_use / tool dispatch events of any kind.
 *   - DOM: response body is the single sentence — FCA signposting suffix ABSENT.
 *   - Network: POST /api/ai-chat/conversations/74/messages → 200 OK.
 *   Screenshot: docs/sprint-0-verification/BS-10/01-out-of-remit-refusal.png
 *
 *   Bug-fixed-in-loop per CLAUDE.md Rule #15:
 *   Initial attempt against AiConversation #73 returned 403 consent_required because
 *   john@example.com was seeded without UserConsent rows. Real-registration grants
 *   four consents at AuthController::register:506-511 (TERMS, PRIVACY,
 *   DATA_PROCESSING, AI_CHAT) but TestUsersSeeder, ChrisUserSeeder, AdminUserSeeder
 *   and PreviewUserSeeder all bypassed that path via firstOrCreate / updateOrCreate
 *   directly on the User model. Patched all four seeders to grant the same four
 *   consents post-creation (mirrors the registration controller exactly). Reseeded;
 *   started a fresh conversation; BS-10 walk passed end-to-end on the next attempt.
 *
 *   Also fixed in same loop: phpunit.xml lacked `<env name="DB_DATABASE"
 *   value="laravel_testing"/>`, so the session-start Pest sweep wiped the primary
 *   `laravel` DB via RefreshDatabase before this BS-10 walk could begin (Issue 87-B
 *   reproduced). Added the override per CSJTODO line 163 — Pest sweep now lands in
 *   `laravel_testing` and `laravel` survives. 486 baseline still passes (94.71s).
 *
 * Session 95 redo (2026-04-26, S0.16c #6) — GREEN against the post-
 * `ffc9c3f` shared `AiChatPanelShell` body. Re-driven against the
 * session-95 BS-01 user (Laury Greenwood, User #449, advice mode after
 * onboarding completion). browser_click for the New conversation button
 * + browser_type with submit:true for the medical question — NO
 * browser_evaluate for any interaction.
 *
 * Walk transcript:
 *   - Already signed in as Laury (carried from session-95 BS-07 walk).
 *   - browser_click on the "New conversation" + button in the docked
 *     chat panel header → fresh AiConversation #127 created (Vuex
 *     SET_CURRENT_CONVERSATION fires immediately).
 *   - browser_type "Should I take antibiotics for a persistent cough?"
 *     + Enter (submit:true).
 *   - Wait 8s for AdviceFyn turn.
 *
 * Acceptance evidence (fresh in this session):
 *   - DOM/Vuex: assistant message rendered with content EXACTLY equal to:
 *     "I'm able to help you with your finances. Medical advice is out of scope."
 *     No additional sentences, no FCA signposting suffix, no closing nudge.
 *   - DB: AiMessage #246 role=user persona='advice' content="Should I
 *     take antibiotics for a persistent cough?"
 *   - DB: AiMessage #247 role=assistant persona='advice' content=
 *     canonical refusal exactly.
 *   - DB: AiAuditEvent::where('conversation_id', 127)->count() === 0.
 *     The QueryClassifier OUT_OF_REMIT short-circuit fires BEFORE any
 *     LLM tool dispatch, so the audit chain never gets a row for this
 *     turn — exactly the INV-2.3.4 contract.
 *   - DOM: ZERO quick_replies, ZERO action_bubbles, ZERO links to other
 *     modules. Plain content layout in the docked sidebar.
 *
 * Cross-link to BS-23: the same QueryClassifier OUT_OF_REMIT path is
 * what session 93 used for V2B (GP medical jailbreak). The single-call
 * BS-10 walk above proves the V2B variant fires identically against
 * the non-jailbreak phrasing of the same medical-domain question.
 *
 * Screenshots `docs/sprint-0-verification/BS-10/`:
 *   - s95-01-out-of-remit-refusal.png — full dashboard view with the
 *     refusal turn rendered in the chat panel.
 *
 * Pest sibling: `tests/Feature/Fyn/OutOfRemitTest.php` pins the refusal
 * text + persona + audit-zero contract in isolation.
 *
 * Pest baseline: 529/1968 (no regressions).
 */
it('BS-10 out-of-remit refusal', function (): void {
    $this->markPendingInteractiveRun('BS-10');
});
