<?php

declare(strict_types=1);

/**
 * BS-12 — capture_complete matches assistant-bubble styling
 *
 * Sprint: 0
 * Invariants: INV-2.4.3 (capture_complete bubble matches normal styling)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-12
 * Pest sibling: tests/Feature/Fyn/CaptureCompleteStylingTest.php (S0.15.4)
 * Screenshots: docs/sprint-0-verification/BS-12/
 *
 * Seed: a user mid-conversation; trigger a capture via advice chat per BS-11.
 *
 * Script:
 *   1. Login::asPreviewPersona('young_family')
 *   2. Open chat; type the BS-11 message to trigger an inline capture.
 *   3. browser_press_key('Enter'); wait for capture_complete SSE event
 *      and the rendered bubble to appear.
 *   4. browser_evaluate to pull the rendered capture_complete element's
 *      classList:
 *        () => Array.from(
 *          document.querySelector(
 *            '[data-role="capture_complete"], .msg-capture-complete'
 *          )?.classList ?? []
 *        )
 *   5. browser_evaluate to pull a regular assistant content bubble's
 *      classList in the same conversation.
 *   6. browser_take_screenshot → docs/sprint-0-verification/BS-12/bubbles.png
 *
 * Assertions:
 *   - The two outer-container class sets are equal modulo content-specific
 *     suffixes (timestamp class, role-distinguishing data-* attrs).
 *   - Both contain `bg-savannah-100`, `border-light-gray`, `rounded-lg`,
 *     `flex`, `justify-start` (per AiChatPanel.vue alignment).
 *   - Neither contains `border-horizon-200`, `border-violet-*`,
 *     `border-raspberry-*`, `border-spring-*`, `ring-*`, `outline-*`.
 *   - No SVG icon inside the capture_complete bubble (CLAUDE.md Rule 14).
 *
 * Pass: styling equivalence verified at the DOM level.
 */
it('BS-12 capture_complete matches assistant-bubble styling', function (): void {
    $this->markPendingInteractiveRun('BS-12');
});
