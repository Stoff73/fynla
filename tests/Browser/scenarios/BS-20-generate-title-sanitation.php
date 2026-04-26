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
 */
it('BS-20 generateTitle sanitation', function (): void {
    $this->markPendingInteractiveRun('BS-20');
});
