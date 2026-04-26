<?php

declare(strict_types=1);

/**
 * BS-20 — generateTitle sanitation
 *
 * Sprint: 0
 * Invariants: INV-2.9.6 (HasAiChat::generateTitle strips tags + truncates)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-20
 * Pest sibling: tests/Unit/Traits/GenerateTitleSanitisationTest.php
 * Screenshots: docs/sprint-0-verification/BS-20/
 *
 * Seed: any user with no prior conversation (so the next message triggers
 *       title generation).
 *
 * Script:
 *   1. Login::as($email, $password)
 *   2. Open chat panel; ensure no prior conversation exists.
 *   3. browser_type: "<script>alert(1)</script> hello"
 *   4. browser_press_key('Enter')
 *   5. browser_wait_for the assistant response and the conversation title
 *      to appear in the sidebar.
 *   6. Click the conversation history icon to expand the sidebar.
 *   7. browser_take_screenshot → docs/sprint-0-verification/BS-20/01-sidebar.png
 *   8. browser_console_messages — capture any JS console output.
 *
 * Assertions:
 *   - Sidebar entry shows the conversation title; the visible text
 *     contains NO `<script>` tag, NO `<` or `>` characters from the
 *     original injection.
 *   - browser_console_messages does NOT contain an alert dialogue or
 *     any uncaught script execution.
 *   - Pest-side: AiConversation::find($cid)->title is the sanitised
 *     value (strip_tags applied, length ≤ 100 chars per
 *     HasAiChat::generateTitle contract).
 *   - The sanitised title still contains the substring "hello".
 *
 * Pass: no script execution; DB title cleaned; sidebar text safe.
 *
 * Delivery note (2026-04-26 — S0.16b Batch 2):
 *   PARTIAL — backend invariant GREEN via the Pest sibling
 *   `tests/Unit/Traits/GenerateTitleSanitisationTest.php` (7/7 passing,
 *   15 assertions): strip_tags applied, length capped at 100,
 *   ellipsis only on overflow, no markup chars survive, multibyte
 *   safe. The `HasAiChat::generateTitle` contract that BS-20 pins is
 *   fully covered.
 *
 *   UI portion NOT run — same Playwright environmental blocker as
 *   BS-16 prevented the chat textarea from receiving keystrokes in
 *   this session. The sidebar-render assertion (visible title text
 *   contains no `<` or `>`, no JS alert dialog fired) remains
 *   outstanding pending a clean Playwright run. Flagged in CSJTODO.
 *
 * Delivery note (2026-04-26 — S0.16b Batch 2 RE-RUN, full GREEN):
 *   Re-driven end-to-end via REAL Playwright keystrokes. Logged in as
 *   john@example.com, clicked "New conversation", typed
 *   `<script>alert(1)</script> hello` via browser_type slowly + Enter.
 *
 *   First-pass UI run uncovered a NEW UX bug not anticipated in the
 *   stub: the optimistic user-message bubble in
 *   `resources/js/store/modules/aiChat.js sendMessage` (line ~358)
 *   pushed the raw user-typed string into `state.messages` before the
 *   API round-trip, so the bubble rendered the literal text
 *   "<script>alert(1)</script> hello" (Vue auto-escapes `{{ }}`, so
 *   no XSS, but ugly UX — the user saw their own injection echoed
 *   back). The DB-stored copy was already sanitised by SanitizeInput
 *   middleware (`'alert(1) hello'`); the optimistic bubble bypassed
 *   that.
 *
 *   Folded into S0.5.v in the Sprint 0 plan (per §S0.16b: "any
 *   failures route through dedicated bug-fix sub-tasks against the
 *   relevant Sprint 0 file"):
 *     - CREATE `resources/js/utils/stripTags.js` — narrow tag-shaped
 *       regex that mirrors PHP `strip_tags()` (preserves "<3",
 *       "< 5 > 3" cases the same way).
 *     - MODIFY `resources/js/store/modules/aiChat.js sendMessage`:
 *       optimistic ADD_MESSAGE now uses `stripTags(message)` so the
 *       bubble matches what the DB stores. Defence-in-depth on top of
 *       Vue's auto-escaping.
 *
 *   After the fix, re-ran end-to-end:
 *     ✅ Sidebar entry title "alert(1) hello" — no `<` or `>` chars
 *     ✅ User-bubble text "alert(1) hello" — no `<` or `>` chars
 *     ✅ document.body.innerText contains zero `<` or `>` anywhere
 *        (page-wide regex check: `bodyContainsLt: false`,
 *        `bodyContainsGt: false`, `bodyContainsLtScript: false`)
 *     ✅ document.querySelectorAll('script') filtered for
 *        `alert(1)` returns 0 (no injected script element)
 *     ✅ browser_console_messages: zero alert dialog, zero uncaught
 *        script execution (only unrelated 401 from initial login
 *        attempt + 403 on /api/estate/trusts unrelated to this run)
 *     ✅ DB: AiConversation::find(latest)->title = 'alert(1) hello'
 *        (14 chars, ≤100, contains "hello", no markup)
 *     ✅ DB: first user message content = 'alert(1) hello' (matches
 *        what the optimistic bubble shows now — bubble + DB consistent)
 *
 *   Test user: john@example.com (id=12), conv id=9 (latest after
 *   reseed + the BS-16 conv id=2 + interim "New conversation" rows).
 *
 *   Screenshot: April/April24Updates/plan/batch2/BS-20/01-bubble-and-sidebar.png
 *     (gitignored under April/April24Updates/plan/batch2/)
 */
it('BS-20 generateTitle sanitation', function (): void {
    $this->markPendingInteractiveRun('BS-20');
});
