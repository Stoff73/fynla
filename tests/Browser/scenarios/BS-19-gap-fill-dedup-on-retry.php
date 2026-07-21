<?php

declare(strict_types=1);

/**
 * BS-19 — gap-fill dedup on retry
 *
 * Sprint: 0
 * Invariants: INV-2.9.5 (gap-fill dedup against DB)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-19
 * Pest sibling: tests/Feature/AI/GapFillDedupTest.php
 * Screenshots: docs/sprint-0-verification/BS-19/
 *
 * --- Session 91 GREEN delivery (2026-04-26) ---
 *
 * Driver: john@example.com (User #352, advice mode — onboarding_completed=false
 * AND onboarding_fyn_step=null, so AiChatController::sendMessage:174-176
 * resolves $inOnboarding=false → AdviceFyn).
 *
 * Walk transcript:
 *   1. db:seed --force; targeted Pest sweep (486 / 1591 / 0).
 *   2. Cleared session 90 carry-over: deleted stale AiDailyUsage row for john
 *      (24913 tokens from previous walk). Logged as fixture-cleanup.
 *   3. Verified john has zero protection rows (clean slate).
 *   4. Login → MFA via DB code → /dashboard.
 *   5. Opened docked chat panel via window.fyn-toggle-chat event (chatCollapsed
 *      flipped via AppLayout::toggleChat).
 *   6. Started AiConversation #86 via aiChat/startNewConversation. Note: the
 *      panel does NOT auto-create a conversation when opened from the toggle —
 *      the user normally types into the input, which triggers the canSend path
 *      → send() → sendMessage action. sendMessage returns early when
 *      currentConversation is null, so the first user keystroke flow has its
 *      own startNewConversation upstream. For the live walk we explicitly
 *      seeded the conversation so the dispatch was deterministic.
 *   7. RUN 1: dispatched aiChat/sendMessage(
 *        "I have Aviva life insurance £300k and Vitality critical illness £100k"
 *      ). Stream completed. Vuex msgs: user + entity_created(life) +
 *      entity_created(ci) + assistant("I've added your Aviva level term life
 *      insurance policy with £300,000 sum assured and your Vitality standalone
 *      critical illness policy with £100,000 sum assured...") + capture_complete
 *      ("Saved 2 records").
 *      DB after RUN 1: LifeInsurancePolicy::whereDate(today)::count = 1
 *        (L#92 Aviva £300k level_term).
 *      DB after RUN 1: CriticalIllnessPolicy::whereDate(today)::count = 1
 *        (C#8 Vitality £100k standalone).
 *   8. Navigated /protection. Two policy cards rendered:
 *        Life Insurance — Level Term — Aviva — £300,000 sum assured.
 *        Critical Illness — Vitality — £100,000 sum assured.
 *      Screenshot: docs/sprint-0-verification/BS-19/01-first-run.png.
 *
 * Bug-fix-in-loop (CLAUDE.md Rule #15) — RecordDuplicateChecker upgrade:
 *
 *   RUN 2 (pre-fix): dispatched the IDENTICAL message in conv #87. RED.
 *     - Stream yielded entity_created(life) + entity_created(ci) +
 *       capture_complete("Saved 2 records").
 *     - DB: LifeInsurancePolicy::whereDate(today)::count = 2 (L#92 + L#93,
 *       both Aviva £300k); CriticalIllnessPolicy::count = 2 (C#8 + C#9, both
 *       Vitality £100k).
 *     - Diagnosis: AdviceFyn::handle:127 calls
 *       RecordDuplicateChecker::alreadyExists before routing to
 *       handleInlineCapture. The previous protectionPolicyExists used a
 *       narrow regex requiring "with/at/from <Provider>" phrasing — does
 *       NOT match "I have <Provider>" — returns false. Inline-capture
 *       fires; LLM (data_capture persona) emits create_protection_policy x2;
 *       handleCreateProtectionPolicy persists both AGAIN. The 24h DB dedup
 *       in AssetCaptureEntityExtractor::findMissing ($user-aware) protects
 *       gap-fill emissions but runs AFTER the LLM stream — by then the
 *       LLM-direct create_* has already written.
 *
 *   Fix: app/Services/AI/RecordDuplicateChecker.php — replace the regex-
 *     based protectionPolicyExists with a generic allEntitiesExist that
 *     calls AssetCaptureEntityExtractor::extractForFocus + ::findMissing
 *     ([] llmEmitted, $user). When $missing === [] (every extracted
 *     entity already in DB <24h), return true → AdviceFyn falls through
 *     to the read-only LLM advice path → no inline-capture → no LLM-direct
 *     write. The Pest sibling (GapFillDedupTest.php) already covers
 *     findMissing's extract+dedup logic in isolation; the fix reuses it
 *     at the routing-gate layer.
 *
 *   Side cleanups in same edit: deleted unused extractProvider /
 *     extractCurrencyAmount helpers (regex helpers used only by the old
 *     protectionPolicyExists). Removed `use App\Models\LifeInsurancePolicy`
 *     since the model is no longer referenced.
 *
 *   Re-verify (RUN 2 post-fix): deleted duplicate rows L#93 and C#9 to
 *     reset state. Started AiConversation #88, dispatched the IDENTICAL
 *     message a third time. GREEN:
 *     - Stream yielded user + assistant only. ZERO entity_created. ZERO
 *       capture_complete. ZERO fill_form. ZERO gap-fill synthesised events.
 *     - Assistant content (advice persona): "Thanks for confirming those
 *       details, John. You have £300,000 level term life insurance with
 *       Aviva and £100,000 standalone critical illness cover with Vitality
 *       – that's a solid foundation for protection..."
 *     - DB: LifeInsurancePolicy::whereDate(today)::count = 1 (L#92 only);
 *       CriticalIllnessPolicy::whereDate(today)::count = 1 (C#8 only).
 *     - /protection page: still exactly 2 cards (Aviva life + Vitality CI).
 *       Screenshot: docs/sprint-0-verification/BS-19/02-after-retry.png.
 *
 *   Pest re-run after fix: 486 / 1591 / 0 (106.35s). No regression. The
 *     RecordDuplicateChecker constructor now requires AssetCaptureEntityExtractor;
 *     Laravel auto-wires both via constructor injection so AdviceFyn's DI is
 *     unchanged.
 *
 * Acceptance — every BS-19 docblock assertion verified:
 *   ✓ After first run: 2 protection cards visible
 *     (Life Insurance Level Term Aviva + Critical Illness Vitality).
 *   ✓ After second identical run: still 2 cards (no doubles).
 *   ✓ LifeInsurancePolicy::where('user_id', $user->id)
 *       ->whereDate('created_at', today())->count() === 1 after both runs.
 *   ✓ CriticalIllnessPolicy::where('user_id', $user->id)
 *       ->whereDate('created_at', today())->count() === 1 after both runs.
 *   ✓ SSE stream of the SECOND run contains zero gap-fill synthesised
 *     events (the AdviceFyn pre-route check short-circuits via the 24h DB
 *     dedup window before handleInlineCapture is invoked).
 *
 * --- Original stub script (for reference) ---
 *
 * Seed: same as BS-17 — young_family preview persona OR factory user
 *       with no existing protection rows.
 *
 * Script:
 *   1. Login::asPreviewPersona('young_family') OR Login::as(...)
 *   2. Open chat panel.
 *   3. browser_type the BS-17 multi-entity protection message:
 *      "I have Aviva life insurance £300k and Vitality critical illness £100k"
 *   4. browser_press_key('Enter'); wait for completion.
 *   5. Navigate to /protection via UI; count visible cards.
 *      browser_take_screenshot → docs/sprint-0-verification/BS-19/01-first-run.png
 *   6. Re-open chat; submit the IDENTICAL message.
 *   7. Wait for completion.
 *   8. Re-navigate to /protection.
 *      browser_take_screenshot → docs/sprint-0-verification/BS-19/02-after-retry.png
 *   9. Capture both runs' network requests via browser_network_requests
 *      to verify SSE event counts for each.
 *
 * Pass: dedup holds across retries from both UI and SSE perspectives.
 */
it('BS-19 gap-fill dedup on retry', function (): void {
    $this->markPendingInteractiveRun('BS-19');
});
