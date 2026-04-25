# CSJTODO — Fynla

*Last updated: 25 April 2026 — session 81 (BS-14 GREEN + S0.5.t hardening rollup + LOOP UNTIL CORRECT rule)*
*Previous session: 25 April 2026 — session 80 (CAN-01-EXEC + S0.5.r + S0.5.s recovery handover)*

---

## Next session: resume S0.16b Batch 2 — BS-16 → BS-20 → BS-12 → BS-11

BS-14 is GREEN. Sprint 0 plan §S0.16b Batch 2 is the next four scenarios per the original sequence in `April/April24Updates/plan/taskListFix.md` and `10-sprint-0-plan.md`. The next session should:

1. Read this file top-to-bottom.
2. Run `./dev.sh` to start the local dev stack (Laravel :8000 + Vite :5174). If Vite errors with "Port 5174 already in use", an instance is already running — check `lsof -i :5174`.
3. **Drive each BS-NN end-to-end via Playwright** per the docblock in `tests/Browser/scenarios/BS-NN-*.php`. CLAUDE.md Rule #15 (LOOP UNTIL CORRECT) is now non-negotiable — when a test is RED, loop diagnose → fix → re-verify in browser until GREEN. Don't hand back. No apologies without fixes.
4. Per scenario:
   - Login as the seed user the stub specifies (mostly `john@example.com` / password — fetch verification code from DB via `php artisan tinker --execute="..."`).
   - Drive the scripted interactions with REAL Playwright clicks/fills/keystrokes — **never** `browser_evaluate` to inject values, **never** snapshot-without-interaction.
   - Verify all docblock assertions: DB row + audit chain + SSE events + UI render + no fabricated success + no leaked synthetic events.
   - Save screenshots under `April/April24Updates/plan/batch1/BS-NN/` (gitignored — local audit only).
   - Update the stub docblock RED → GREEN with delivery note (commit refs + evidence summary).
5. After each scenario, commit `test(browser): BS-NN GREEN (S0.16b)` and continue to the next.
6. After BS-16 → BS-20 → BS-12 → BS-11 are all GREEN, move to Batch 3 (12 remaining scenarios).

**Read these before starting:**
- `April/April24Updates/plan/10-sprint-0-plan.md` §S0.16b — the spec for interactive execution.
- `tests/Browser/scenarios/BS-14-direct-write-savings-account.php` — exemplar GREEN delivery note format.
- `MEMORY.md` "Top laws" — `feedback_loop_until_correct.md`, `critical_browser_testing_law.md`, `feedback_advice_fyn_is_read_only.md`.
- Bug-fix sub-tasks routed inside the loop follow the S0.5.t pattern: append a `Sx.x.y` section to `10-sprint-0-plan.md` listing the eight (or N) sub-fixes itemised inside the BS-NN delivery note.

---

## Session 81 (25 April night-time) — BS-14 GREEN + LOOP UNTIL CORRECT

### Completed this session

- [x] **BS-14 GREEN end-to-end via Playwright** (commit `231b846`) — DB SavingsAccount row + delegate_to_capture/create_savings_account audit chain + single honest data_capture assistant message + URL stayed on /dashboard + UI card visible at /net-worth/cash. Targeted Pest 218/0.
- [x] **S0.5.t — BS-14 hardening rollup, eight sub-fixes folded into the same loop:**
  - [x] S0.5.t.1 — `CaptureContext::fromArray` synthesises `reason` from entity_types when LLM omits it.
  - [x] S0.5.t.2 — Stripped legacy `<data_creation_guidance>` from `FcaProcessInstructions` (non-preview path).
  - [x] S0.5.t.3 — Hardened `<handoff_guidance>` (`AdvicePromptBuilder`): promoted to Layer 3b, anti-pattern list, required-args reminder, concrete required-pattern example. Extracted as `getHandoffGuidance()`.
  - [x] S0.5.t.4 — `navigate_to_page` added to `AdviceFyn::WRITE_TOOLS` (escape hatch eliminated).
  - [x] S0.5.t.5 — `AdviceFyn::wrapStream` terminates with `return` after `handleInlineCapture` (no duplicate confirmation message).
  - [x] S0.5.t.6 — Removed auto-emission of `navigation` SSE event on blocked tool results (`HasAiChat`) — no more force-redirect to /profile.
  - [x] S0.5.t.7 — Persona enum mismatch fixed (`onboarding_inline` → `data_capture` in OnboardingChatDirector + HasAiChat docs + AdviceFynRoutesWritesViaHandoffTest stub).
  - [x] S0.5.t.8 — `handleInlineCapture` `persistUserMessage: true` → `false` (no duplicate user rows).
- [x] **CLAUDE.md Rule #15 LOOP UNTIL CORRECT** added — when CSJ points at a plan and says "make this work", loop until GREEN. No early stop, no apologies without fixes. Mirrored to:
  - `MEMORY.md` Top Laws + index entry
  - `feedback_loop_until_correct.md` (memory file, full Why + How to apply)
  - `fynlaBrain/LoopUntilCorrect.md` (vault note)
- [x] **CSJ tightened the rule** post-commit: applies to ALL tests (not only plan-specific), references `/systematic-debugging` skill for step 1. Mirrored to memory + vault.
- [x] **BS-14 stub** (`tests/Browser/scenarios/BS-14-direct-write-savings-account.php`) updated with GREEN delivery note (eight sub-fixes itemised, all DB/audit/SSE/UI evidence captured).
- [x] **Sprint 0 plan** (`10-sprint-0-plan.md` + vault mirror) updated with new S0.5.t section + checklist row.
- [x] **`AdviceFynRoutesWritesViaHandoffTest`** persona stub key renamed `'onboarding_inline'` → `'data_capture'`.
- [x] **`CaptureContextTest`** updated: removed "throws when reason missing" expectation, added two new tests for synthesised-reason behaviour (whitespace-only treated as missing, entity_types-derived label).

### Test results (cumulative session 81)

- Targeted regression sweep (Fyn + AI + Onboarding + ValueObjects + Architecture + UpdateRecordSecurity): **218 passed, 0 failed**.
- Full Pest deferred to S0.17 verification rollup. The post-S0.16a baseline is 2,640 passing; session 80 hit 2,938 passing; session 81 added 2 new tests + updated 1, so the baseline floor is **2,940 passing minimum** for the full sweep.

### NOT Done — Outstanding (in execution order)

- [ ] **S0.16b Batch 2** — BS-16, BS-20, BS-12, BS-11 (next session start here).
- [ ] **S0.16b Batches 3–5** — 12 remaining scenarios (BS-01, 02, 04, 05, 06, 07, 10, 13, 17, 18, 19, 21, 22, 23 — minus the 4 already in Batch 2 and the 4 already GREEN).
- [ ] **S0.17** — Sprint 0 verification rollup (full Pest + audit chain + Browser 20/20 + Rubric-A re-score 13–15/40).
- [ ] **Cosmetic carry-over** — the prerequisite-gate text "Monthly expenditure is required to calculate savings capacity" still appears as a chat chip on the FIRST user send when an analysis tool fires before delegate_to_capture. Harmless (the auto-redirect was the load-bearing bug and is fixed) but pollutes the chat. Defer to a small frontend filter task in S0.17 or beyond.

### Known issues

- **None this session.** Tech debt audit on changed files: 0 issues across all 10 files (see `tech-debt-report.md`).
- Pre-existing 30%-flake on `WillBuilderApiTest::GET /estate/will-builder/pre-populate` (faker `middle_name`) — pinned with `'middle_name' => null` in commit `5771687` (session 75); should be stable.

### Deploy status

- **Nothing to deploy this session.** Work is on `feature/fyn-persona-split`, hasn't merged to `dev` yet. When the persona-split branch is ready, the dev → main flow per CLAUDE.md "Deployment" section applies.

---

## Outstanding — Tech Debt Deferred (carried from earlier sessions)

- [ ] `handleModuleAnalysis` still wraps via `summariseToolAnalysis` — INV-2.6.1 partial (deferred from session 78, flagged in plan + tech-debt report). May get folded into S0.17 verification rollup.

## Sprint 0 progress

| Task | Status | Commit |
|------|--------|--------|
| S0.1 → S0.4 | ✅ | various |
| S0.5 (a-q rollup) | ✅ | b7a881d → 71aa98a |
| S0.5.r (advice→capture handoff) | ✅ | 0973a6b |
| S0.5.s (assistant honesty Pest pin) | ✅ | b8ceac0 |
| **S0.5.t (BS-14 hardening rollup)** | ✅ | **231b846** |
| S0.6 → S0.15 | ✅ | various |
| S0.16a (browser harness + 20 stubs) | ✅ | bc855fd |
| **S0.16b Batch 1 (BS-14 GREEN; 4 prior GREEN)** | 🟡 in progress | BS-14 GREEN this session |
| S0.16b Batches 2–5 (15 scenarios) | ⏳ pending | next session start with Batch 2 |
| S0.17 (Sprint 0 verification rollup) | ⏳ pending | after S0.16b complete |

---

## Context for Next Session

You are in the middle of S0.16b Batch 2 work on `feature/fyn-persona-split`. BS-14 is GREEN end-to-end — start by reading `tests/Browser/scenarios/BS-16-billing-where-is-my-invoice.php`, follow the same disciplined loop (real Playwright clicks, full assertion coverage per docblock, fix bugs in the same loop), then BS-20, BS-12, BS-11. The LOOP UNTIL CORRECT rule (CLAUDE.md Rule #15) and the eight S0.5.t sub-fixes have removed most of the architectural friction the earlier batches uncovered — Batch 2 should run cleaner. Test user is `john@example.com` for most scenarios; check the stub's "Seed:" line for exceptions.
