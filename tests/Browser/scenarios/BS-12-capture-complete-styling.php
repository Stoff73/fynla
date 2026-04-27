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
 *   6. browser_take_screenshot → docs/sprint-0-verification/BS-12/01-bubbles.png
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
 *
 * Delivery note (2026-04-26 — S0.16b Batch 2):
 *   PARTIAL — backend/styling invariant GREEN via the Pest sibling
 *   `tests/Feature/Fyn/CaptureCompleteStylingTest.php` (3/3 passing):
 *     - capture_complete bubbles render with the same bg-savannah-100
 *       + border-light-gray + rounded-lg as a normal assistant bubble
 *     - no capture-mode badge/ring/icon/distinct border surfaces
 *     - same outer flex/justify-start alignment
 *   INV-2.4.3 contract coverage is intact.
 *
 *   UI portion NOT run — same Playwright environmental blocker.
 *   The DOM classList read from a live capture_complete bubble was
 *   not captured in this session because the chat textarea was
 *   unresponsive to keystrokes (typing the BS-11 trigger message).
 *   Flagged in CSJTODO.
 *
 * Delivery note (2026-04-26 — S0.16b Batch 2 RE-RUN, full GREEN):
 *   Re-driven end-to-end via REAL Playwright keystrokes alongside
 *   BS-11 (same trigger turn). First-pass UI run revealed the
 *   advice→capture handoff path was emitting `entity_created` SSE
 *   events but NOT `capture_complete` — the latter only fired in
 *   the legacy onboarding orchestrator, which was deleted in S0.3.
 *   So the BS-12 contract assertion ("capture_complete bubble
 *   matches assistant-bubble styling") had no rendered bubble to
 *   test against, and the existing `entity_created` rendering was
 *   producing a stray "Aviva" mini-bubble — poor UX (CSJ flagged:
 *   "details matter").
 *
 *   Folded into S0.5.w (one fix landed both BS-11 and BS-12):
 *     - `OnboardingChatDirector::handleInlineCapture` now tracks
 *       every `entity_created` event yielded during the capture
 *       turn and emits a closing `capture_complete` SSE event with
 *       a `records_created` array (one entry per persisted record).
 *       Suppressed when nothing was actually written.
 *     - `AiChatPanel.vue` v-for now `v-show="msg.role !==
 *       'entity_created'"` — the entity_created event remains in the
 *       Vuex store for downstream consumers (cache invalidation,
 *       recommendation refresh) but no longer renders as a stray
 *       single-name bubble. The richer `capture_complete` card is
 *       the canonical UX surface.
 *
 *   After fix, on the BS-11 turn (conv id=17, policy id=10):
 *     ✅ msg.role === 'capture_complete' bubble rendered with
 *        classes: max-w-[85%] w-full rounded-lg bg-savannah-100
 *        border border-light-gray px-3 py-2 space-y-2 text-sm.
 *     ✅ Bubble content: "Saved to your records" heading + one
 *        sub-row showing entity type "life_insurance_policy" + "View"
 *        link.
 *     ✅ No SVG icon inside the bubble (CLAUDE.md Rule 14
 *        functional-only icons).
 *     ✅ Bubble shares bg-savannah-100 + border-light-gray with the
 *        regular assistant bubble template; rounded-lg + flex
 *        justify-start present (BS-12 contract held).
 *     ✅ No stray "Aviva" mini-bubble visible (entity_created hidden
 *        from render but preserved in store).
 *     ✅ Page-wide `<` / `>` character count = 0 (no markup leak from
 *        the function_call stripper applied at persistence).
 *
 *   Screenshot: docs/sprint-0-verification/BS-12/02-classifier-green.png
 *     (cross-referenced from BS-11; the same capture_complete card
 *     shown there also covers BS-12 evidence — committed to both dirs
 *     for self-contained reading. Migrated from gitignored legacy
 *     path 2026-04-27 session 97.)
 *
 * Delivery note (2026-04-26 — S0.5.y, BS-12 record-card UX fix):
 *   First fully-rendered capture_complete card surfaced two more bugs
 *   CSJ flagged on inspection:
 *     (a) Card row showed the raw entity_type slug "life_insurance_policy"
 *         instead of a personalised label.
 *     (b) The "View" button click fell through to /dashboard because the
 *         routeMap in AiChatPanel.vue only had the historical short keys
 *         ("life_insurance"), not the canonical long keys the
 *         create_protection_policy handler emits ("life_insurance_policy").
 *
 *   Folded into S0.5.y in the Sprint 0 plan:
 *     - Extended routeMap + formatEntityType labels to cover every
 *       canonical entity_type the create_* handlers emit (alongside the
 *       historical short keys for back-compat). Replaced "LPA" with
 *       "Lasting Power of Attorney" per CLAUDE.md Rule #10 (no
 *       acronyms).
 *     - Added formatRecordCardLabel(record) helper — combines record.name
 *       (entity-specific human label like "Aviva") with the friendly
 *       type label, producing "Aviva — Life insurance" in the card
 *       row instead of the bare "life_insurance_policy" slug.
 *
 *   After fix:
 *     ✅ Card row reads "Aviva — Life insurance" (personalised + no
 *        acronyms).
 *     ✅ "View" click navigates to /protection — the destination page
 *        renders the new policy in the Policy section ("Aviva / Level
 *        Term / Sum Assured £300,000 / Premium £30/monthly") and the
 *        Existing Life Insurance Coverage / Coverage Summary blocks
 *        update accordingly.
 *
 *   Screenshot: docs/sprint-0-verification/BS-12/01-bubbles.png
 *     (migrated from gitignored legacy path 2026-04-27 session 97;
 *     the legacy 02-protection-page.png from the destination-page
 *     navigation step was walk residue and is not committed.)
 */
it('BS-12 capture_complete matches assistant-bubble styling', function (): void {
    $this->markPendingInteractiveRun('BS-12');
});
