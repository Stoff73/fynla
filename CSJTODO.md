# CSJTODO — Fynla

*Last updated: 25 April 2026 — session 80 (CAN-01-EXEC + S0.5.r + S0.5.s recovery complete)*
*Previous session: 25 April 2026 — session 79 (Sprint 0.16b Batch 1 + architectural-gap discovery)*

---

## Next session: drive BS-14 retry via Playwright

The architectural recovery from `taskListFix.md` is **complete**. The Sprint 0 wiring gap is closed and the lying-on-failure prompt is replaced. The next session should:

1. Read this file top-to-bottom.
2. Run `./dev.sh` to start the local dev stack.
3. Drive BS-14 end-to-end per the stub at `tests/Browser/scenarios/BS-14-direct-write-savings-account.php`. Login as `john@example.com`, send "Add a Cash ISA at Nationwide, balance £5,000, 4.5% interest", assert: SSE contains `tool_use(create_savings_account)` + `entity_created`, no user-visible `handoff` event, `SavingsAccount` row exists, UI card appears at `/net-worth/cash`.
4. Update the BS-14 stub docblock RED → GREEN. Reference commit `0973a6b` (S0.5.r) + `b8ceac0` (S0.5.s).
5. Save screenshots under `April/April24Updates/plan/batch1/BS-14/` (gitignored — local audit only).
6. Commit `test(browser): BS-14 GREEN after S0.5.r/s (S0.16b)`.

**Do NOT skip the click + fill + submit + verify discipline** per `critical_browser_testing_law.md`. "Browser tested" means clicked the element in Playwright and verified the result.

After BS-14 GREEN, resume Batch 2 of S0.16b (BS-16 → BS-20 → BS-12 → BS-11) per the original sequence in `April/April24Updates/plan/taskListFix.md`.

---

## Session 80 (25 April evening) — recovery complete

### Completed this session

- [x] **CAN-01-EXEC** — pasted verbatim canonical block at top of 21 workstream `.md` files (12 plan + 9 spec), repo + fynlaBrain vault. `grep -L "FYN HAS TWO STATES"` on both trees returns empty. `taskListFix.md` already carried the block — no re-paste needed there. `April/` is gitignored so the durable copy is the vault.
- [x] **CLAUDE.md alignment** (commit `75dc7d2`) — added `Fyn AI — Two-Fyn architecture (canonical contract)` subsection under Architecture pinning Onboarding Fyn = writer, Advice Fyn = read-only, write intents flow through `delegate_to_capture` → `handleInlineCapture`, no `FynPersonaOrchestrator`, no frontend persona signals.
- [x] **MEMORY.md cleanup** — created `feedback_advice_fyn_is_read_only.md` (with full Why / How to apply / canonical-contract reference) and indexed it in `MEMORY.md` both under "Top laws" and the file list.
- [x] **S0.5.r — Wire advice → capture handoff** (commit `0973a6b`):
  - `AdviceFyn::WRITE_TOOLS` extended with `create_goal`, `create_life_event`, `create_what_if_scenario` (no analytics carve-out — what_if persists a `WhatIfScenario` row via `WhatIfScenarioService::createScenario`, so it routes through Onboarding Fyn like every other create_*).
  - `AdviceFyn::buildToolList` merges `handoffTools()` so `delegate_to_capture` is exposed.
  - New `AdviceFyn::wrapStream` consumes upstream events, intercepts the synthetic `{type: 'handoff', handoff_type: 'delegate_to_capture'}` event, builds a `CaptureContext` from the payload, and `yield from`s `OnboardingChatDirector::handleInlineCapture` into the same SSE stream. Drops the synthetic handoff event itself per INV-2.4.1.
  - `OnboardingChatDirector` injected into `AdviceFyn` constructor (autowire — singleton in `AppServiceProvider`).
  - `AdvicePromptBuilder` Layer 10b implemented (was a comment with no body) — injects the locked `<handoff_guidance>` block in the non-preview path. Wording approved with CSJ (verb list: add/save/record/create/update/delete/remove; no what_if exception clause).
  - `FcaProcessInstructions::getAvailableActions` — CREATING RECORDS verb table replaced with one-line redirection to `<handoff_guidance>`. TOOL ERROR HANDLING split into READ failures (graceful degradation kept) and WRITE failures (surface failure, never fabricate, never auto-retry).
  - `OnboardingChatDirector::captureToolSet` adds `create_what_if_scenario` and `delete_record`.
  - `AdviceFynToolListTest` — `$writeTools` extended with the three new entries; analytics-exception test removed; two positive assertions added (`delegate_to_capture` in the tool list on Anthropic + xAI).
  - `AdviceFynRoutesWritesViaHandoffTest` (new) — pins the wiring with savings-account scenario and what_if scenario. Both assert no user-visible `handoff` event.
- [x] **S0.5.s — Assistant honesty on write-tool failure** (commit `b8ceac0`) — prompt-side change rode along in S0.5.r; this commit lands the dedicated Pest pin: `AssistantHonestyOnWriteFailureTest` (4 cases, 15 assertions): WRITE block has surface-failure guidance + concrete example wording, WRITE block forbids fabricated success and silent auto-retry, READ block still has graceful-degradation guidance, AdviceFyn passes assistant honesty content events through unchanged.
- [x] **Plan + vault sync** — `April/April24Updates/plan/10-sprint-0-plan.md` (and vault mirror) updated with S0.5.r section between S0.5 and S0.6, S0.5.s section after that, S0.3 spec-omission amendment line, status checklist rows for both new tasks with commit SHAs.

### Test results (cumulative session 80)

- AdviceFynToolListTest: 4 passed (was 3 — added 1, replaced 1).
- AdviceFynRoutesWritesViaHandoffTest: 2 passed (new).
- AssistantHonestyOnWriteFailureTest: 4 passed (new).
- Targeted regression sweep (Fyn + AI + Onboarding + Architecture): **608 passed, 0 failed**.
- Full Pest sweep: **2,938 passed, 20 skipped (browser stubs), 0 failed, 12,346 assertions**. Above the post-S0.16a baseline of 2,640.
- 0 source-code regressions across all suites.

### NOT Done — Outstanding (in execution order)

- [ ] **BS-14 retry** via Playwright — full click + fill + submit + verify per the stub. Update RED → GREEN. Commit `test(browser): BS-14 GREEN after S0.5.r/s (S0.16b)`.
- [ ] **Resume S0.16b Batch 2** — BS-16, BS-20, BS-12, BS-11.
- [ ] **S0.16b Batches 3–5** — 12 remaining scenarios.
- [ ] **S0.17** — Sprint 0 verification rollup (full Pest + audit chain + Browser 20/20 + Rubric-A re-score 13–15/40).

### Plan + spec status (Sprint 0)

```
✓ S0.1   Rebase onto main
✓ S0.2   Delete OpenAI sidecar
✓ S0.3   Two-Fyn collapse                              ← spec wiring omission amended
✓ S0.4   Remove visible-handoff UI
✓ S0.5   17 fill_form → direct-write (a-q)
✓ S0.5.r Wire advice → capture handoff                  ← session 80
✓ S0.5.s Assistant honesty on write-tool failure        ← session 80
✓ S0.6–S0.15  (no change)
✓ S0.16a Browser harness + 20 scenario stubs
☐ S0.16b Interactive Playwright execution               ← Batch 1: 4/20 done (3 GREEN, 1 RED). BS-14 retry pending.
☐ S0.17  Verification rollup + Rubric-A re-score
```

### Context for next session

The architectural picture is now complete and aligned with the canonical Two-Fyn contract. AdviceFyn is genuinely read-only. Write intents in advice mode flow through `delegate_to_capture` → `wrapStream` → `handleInlineCapture` → existing direct-write handlers, invisibly to the user. The lying-on-failure prompt is gone. CLAUDE.md, MEMORY.md and 22 workstream `.md` files all carry the canonical contract.

BS-14 was the test that caught the gap; it's now the test that proves the fix. Run it per the critical browser testing law — actually click, fill, submit, and verify the SavingsAccount row + UI card. Anything else (regex match on a transcript, "I see the response in the snapshot") is not a browser test.

---

## Outstanding — Tech Debt (deferred from session 78)

- [ ] **W1 — Generic global helper function names (collision risk).** `function invokeProtectedMethod(...)` in `tests/Feature/AI/ReadCompletenessTest.php:121` and `function makeUserAtState(...)` in `tests/Feature/Onboarding/ParkedFactsFlushTest.php:25`. Both reusable-sounding names with no scenario-prefix. Fix: rename to scenario-prefixed forms OR hoist `invokeProtectedMethod` into `Tests\TestCase`.
- [ ] **W2 — `handleModuleAnalysis` carry-over (INV-2.6.1 partial).** Handler still wraps via `summariseToolAnalysis` at `app/Agents/CoordinatingAgent.php:1512` rather than returning raw `$analysis`. Open a follow-up sub-task before S0.17 verification rollup.

## Outstanding — Tech Debt carried from earlier sessions

From session 77 tech-debt-report.md:

- [ ] **W1 (session 77)** — `summariseInput` records tool input verbatim on the audit chain. PII (DOB, email, postcode) lands in `ai_audit_events.input_summary` for `capture_personal_details` / `update_profile` / `update_record`. **Critical to do BEFORE the chain accumulates real production data — once written, redacting later breaks every subsequent hash.** Add per-tool field redaction list inside `summariseInput` before the value reaches the chain row.
- [ ] **S1 (session 77)** — `appendAuditEvent` swallows all `Throwable`. Add an alert path so weekly `ai:audit:verify-chain` health check can surface append-failure counts. Sprint 1.
- [ ] **S2 (session 77)** — Onboarding gap-fill call sites pass `conversation_id = null` at `OnboardingChatDirector:1747 + 2148`. Thread `AiConversation $conversation` through both. Sprint 1.
- [ ] **S3 (session 77)** — `border-3` in `AiAudit.vue` spinners is a non-standard Tailwind class. Replace with `border-4`. One-line cleanup.

From session 76 tech-debt-report.md:

- [ ] **S1 (session 76)** — Duplicated provider-resolution lookup in `AdminController::getAiProvider`. Extract to `ProviderResolver::current()` static helper. Sprint 1.
- [ ] **S2 (session 76)** — `AssetCaptureEntityExtractor` now 828 lines. Split when Sprint 1's eval harness opens it.
- [ ] **S3 (session 76)** — `$fromLlm` parameter on identity-key methods is dead (pre-existing). Drop in the same split as S2.
- [ ] **S4 (session 76)** — `ai:usage:backfill` artisan command silently `updateOrCreate`s every row on rerun. Add `--dry-run` + counters when next touched.

From session 75 tech-debt-report.md:

- [ ] **S1 (session 75)** — Mid-stream consent re-check fires one DB query per SSE event. Throttle when next touched.
- [ ] **S2 (session 75)** — Duplicated "grant ai_chat consent" helper across 5 test files. **Threshold reached** — extract to a test trait next session.

## Known Issues

- [ ] **BS-14 retry pending.** Code fix shipped in `0973a6b` + `b8ceac0`. Browser verification owed before claiming GREEN.
- [x] ~~**BS-14 RED — Sprint 0 architectural gap.**~~ Closed by S0.5.r + S0.5.s. Browser retry still owed.
- [x] ~~**CAN-01 acceptance test failing across the workstream**~~ — closed by CAN-01-EXEC paste-pass (21 files repo + 21 files vault).

## Deploy Status

- `feature/fyn-persona-split` pushed to `origin/feature/fyn-persona-split` at session-end. New tip: `b8ceac0` (S0.5.s test pin).
- **3 new commits this session** atop `719ec63` (session 79 end): `75dc7d2` (CLAUDE.md), `0973a6b` (S0.5.r), `b8ceac0` (S0.5.s).
- **Not yet on `dev`.** Per memory `feedback_main_via_dev_only.md`, work flows `feature/fyn-persona-split → dev → main`. Open PR `feature/fyn-persona-split → dev` only AFTER Sprint 0 closes (BS-14 retry + Batches 2-5 + S0.17 complete with browser matrix + Rubric-A re-score).
- **No deploy guide for session 80** — all changes are on the feature branch, not yet ready for dev/staging. The branch is in mid-Sprint-0 state.

---

*Generated by `/session-end` skill — 25 April 2026, session 80.*
