<?php

declare(strict_types=1);

/**
 * BS-13 — system-message token-limit renders distinctly
 *
 * Sprint: 0
 * Invariants: INV-2.4.4 (system messages exempt from handoff-invisibility)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-13
 * Screenshots: docs/sprint-0-verification/BS-13/
 *
 * Seed: factory user with ai_daily_usage.tokens_used set near plan cap
 *       (e.g. 99% of the user's daily budget — see HasAiGuardrails for the
 *       computed cap per persona / plan tier).
 *       Pest setup:
 *         AiDailyUsage::updateOrCreate(
 *           ['user_id' => $user->id, 'usage_date' => today()],
 *           ['tokens_used' => $cap - 100]
 *         );
 *
 * Script:
 *   1. Login::as($email, $password)
 *   2. Open chat panel.
 *   3. browser_type a long enough message to push past the limit (e.g.
 *      a 500-char prompt that triggers a model call larger than 100 tokens).
 *   4. browser_press_key('Enter')
 *   5. browser_wait_for the SSE token_limit event (or the rendered
 *      system-notice element).
 *   6. browser_take_screenshot → docs/sprint-0-verification/BS-13/notice.png
 *   7. Capture network requests via browser_network_requests.
 *
 * Assertions:
 *   - SSE stream contains an event with type='token_limit'
 *     (AssertSseEvents::assertEventTypeEmitted).
 *   - DOM renders a system-notice block visually distinct from the
 *     assistant chat bubble (different background, e.g. violet-50, an
 *     icon-less notice card with reset-time copy).
 *   - The notice text mentions the daily reset window
 *     (regex /(reset|tomorrow|allowance|daily limit)/i).
 *   - Pest-side: ai_daily_usage row was NOT incremented again
 *     (the new request was rejected before model call).
 *
 * Pass: system event present; distinct rendering; no second token charge.
 */
/**
 * Delivery note (2026-04-26 — session 89, S0.16b):
 *   GREEN. Logged in as john@example.com (User #352, advice mode).
 *
 *   Test fixture (mirrors the BS-13 stub's documented Pest setup):
 *     AiDailyUsage::updateOrCreate(
 *       ['user_id' => 352, 'usage_date' => today()],
 *       ['tokens_used' => 1_000_000]   // reaches the daily hard backstop
 *     );
 *
 *   Walked the canonical advice-mode flow at /dashboard, sent
 *   "What's my net worth?" — the daily cap rejects pre-model-call.
 *
 *   Acceptance evidence (live browser, both layouts verified):
 *   - DOM (docked sidebar): violet system notice rendered with
 *     "You've reached your daily Fyn usage limit" + "Your allowance
 *     resets in 8h 0m" — bg-violet-50 + border-violet-200, distinct
 *     from the savannah-100 assistant chat bubble.
 *   - DOM (floating modal at 800x900 viewport): same notice rendered
 *     identically. Both layouts now share the same body via the new
 *     AiChatPanelShell so a single source of truth drives both.
 *   - DOM: notice is icon-less per BS-13 spec + CLAUDE.md Rule #14
 *     ("Fyn chat window — no decorative glyphs"). The previous SVG
 *     clock icon was removed in the same loop.
 *   - DOM: notice text matches /(reset|tomorrow|allowance|daily limit)/i
 *     ("daily Fyn usage limit" + "allowance resets in").
 *   - Vuex: tokenLimitReached=true; the spurious "Fyn couldn't generate
 *     a response..." error no longer overwrites the violet notice
 *     (aiChat.js:641 finally-block now guards on tokenLimitReached +
 *     consentRequired).
 *   - Input: disabled with placeholder "Daily limit reached — resets
 *     at midnight"; send button disabled.
 *   - DB: AiDailyUsage{user_id=352, usage_date=today}.tokens_used
 *     === 1_000_000 (unchanged from the seeded cap value). The new
 *     request was rejected at HasAiChat::chat:101 BEFORE the model
 *     call; recordTokenUsage was never invoked.
 *
 *   Bug-fixes-in-loop per CLAUDE.md Rule #15:
 *   1. Decorative SVG clock icon on the token-limit notice
 *      (AiChatPanel.vue:260-262) — violated Rule #14 + BS-13 spec.
 *      Removed in commit alongside the wider refactor.
 *   2. Docked panel had NO token-limit notice block — only the modal
 *      branch did. The error banner at line 500-505 already documented
 *      the same class of bug ("must mirror the modal error display so
 *      failures... are actually visible to the user"). Refactored the
 *      whole AiChatPanel to render its body ONCE inside a new
 *      AiChatPanelShell.vue slot so docked + modal share every notice,
 *      input, suggestion, and message-list rendering. Both layouts now
 *      benefit equally from any future change.
 *   3. aiChat.js:641 finally-block set 'Fyn couldn't generate a
 *      response...' as SET_ERROR whenever the stream ended without an
 *      assistant message — but token_limit and consent_required both
 *      legitimately end without one. Added !state.tokenLimitReached
 *      and !state.consentRequired guards so the violet notice / the
 *      consent modal aren't overwritten by a spurious raspberry banner.
 *
 *   Screenshots:
 *   - docs/sprint-0-verification/BS-13/01-token-limit-notice-docked.png
 *   - docs/sprint-0-verification/BS-13/02-token-limit-notice-modal.png
 */
it('BS-13 token-limit system message', function (): void {
    $this->markPendingInteractiveRun('BS-13');
});
