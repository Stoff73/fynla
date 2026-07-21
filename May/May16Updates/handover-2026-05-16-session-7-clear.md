---
type: handover
mode: context-clear
date: 2026-05-16
session: 7
branch: fynPromptRework
trigger: context-handover skill (context tripwire >97.5% / ~336k tokens)
---

# Context Clear Handover — 2026-05-16, Session 7

## Immediate state

Executing `docs/superpowers/plans/2026-05-16-fyn-prompt-rework.md` via `superpowers:subagent-driven-development`. **Plan Tasks 1–8 are DONE** (each implemented + spec-reviewed + code-quality-reviewed + committed + pushed). **Plan Task 9 (eval parity gate) is the active Rule #15 loop and is RED** — full suite under `FYN_PROMPT_ARCH=unified` is at **11 failed / 3714 passed / 1 skipped** (was 17 failed before this session's partial fix). Tripwire fired mid-loop, clean break, nothing in flight.

## The thread

- Session-start auto-continued from session-6 handover. Plan execution resumed under subagent-driven-development: fresh implementer subagent per task → spec-compliance reviewer → code-quality reviewer, two-stage gate per task.
- **Tasks 4–8 shipped + reviewed + pushed:** T4 `FynCaptureTurnInstructions` (`5d771f6`), T5 `FynContextSelector` (`ef56598`), T6 `FynContextAssembler` (`190dc30`), T7 advice seam `HasAiChat` (`1960db5`, incl. an **accepted** code-review fix adding `$unifiedOnboardingFocus=null` to `clearChatOverrides()` — this is load-bearing for Task 8's error-path safety), T8 onboarding seam `OnboardingChatDirector` (`550a107`).
- Every reviewer recommendation adjudicated under `superpowers:receiving-code-review` rigour. Pattern held throughout: **accept** fixes that complete a deliverable's own contract; **decline** recs that would mutate spec-locked skeleton / legacy / break plan test-parity; **route** legitimate-but-out-of-scope concerns to the task that owns them. Plan-defect test constants in T5/T6 (`'BILLING'/'RETIREMENT'` are unmapped placeholders → corrected to canonical `QuerySchemas` values `'billing'/'retirement_contribution'`) handled via the Task-3 precedent (fix the mis-specified test, never the locked impl).
- **One concern routed to Task 10** (in task-tracker #7 description): document the implicit cross-class focus-reset contract (onboarding error path relies on `chatWithPromptOverride`'s `finally`→`clearChatOverrides` nulling `$unifiedOnboardingFocus`) in `prompts/fyn-system-prompt.md` — NOT as an edit to the untouched legacy `catch` block.
- **Task 9 reality gap → CSJ decision made this session:** the plan's/spec §10's parity instrument ("run the existing eval suite under both flags, diff per-scenario") **does not exist** — `EvalRunner::run` is a deliberate Sprint-1 S1.1 scaffold hard-error (PR #242, ~18 days pre-dating this branch); the HTTP-driven eval rewrite that would make it runnable shipped on `feature/fyn-persona-split` with 4 unresolved "Task 16 blockers" and was parked, never landing an automated per-scenario runner on `dev`. **CSJ chose: "Step 5 + Step 6 as the gate"** — the full 3725-test suite under both flags (Step 5) + Playwright browser verification under unified (Step 6) IS the parity proof for this plan; automated eval-corpus parity is deferred to separate work (building EvalRunner); flag stays default-legacy, flip only after Step 5 + Step 6 are green. **Do NOT re-litigate this — it is final.** Do NOT fabricate a parity number from the no-op eval suite (Rule #15: no fabricated success).
- **Rule #15 loop on Step 5 (in progress):** ran `FYN_PROMPT_ARCH=unified ./vendor/bin/pest --testsuite=Unit,Feature,Architecture` → **17 failed**. systematic-debugging root-caused: under unified the spec-locked T8 seam legitimately calls `setUnifiedOnboardingFocus` (arm `OnboardingChatDirector.php:1732`, reset `:1859`) on `CoordinatingAgent`, but strict `Mockery::mock(CoordinatingAgent::class)` doubles only modelled `chatWithPromptOverride` → `BadMethodCallException`. Defect is in the **test doubles**, not the (approved) production seam. Applied the codebase-idiomatic non-weakening fix (mirrors the pre-existing `invalidateUserCache->zeroOrMoreTimes()` pattern in `ChildrenDOBFallbackTest:59`): `$mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();` at **5 sites** (`AssetCaptureMultiEntityTest:68` ×1; `AssetCaptureGapFillTest` ×4 at the L74/196/256/317 mock blocks). Verified: minimal hypothesis test flipped red→green; **all 83 onboarding tests pass under unified** (302 assertions); pint clean.
- **Re-ran full suite under unified → still RED: 11 failed / 3714 / 1 skipped.** The 6 onboarding AssetCapture cases are now green; **11 DIFFERENT coupling defects remain elsewhere** (NOT onboarding AssetCapture — those are fixed). Not yet diagnosed — tripwire fired here.

## Files touched this session

```
tests/Feature/Onboarding/AssetCaptureGapFillTest.php       (+12, 4 mock sites — setUnifiedOnboardingFocus zeroOrMoreTimes)
tests/Feature/Onboarding/AssetCaptureMultiEntityTest.php   (+3,  1 mock site — same)
```
5 feature commits pushed earlier (`5d771f6`, `ef56598`, `190dc30`, `1960db5`, `550a107`). The 2 test-double files are in the WIP commit below (NOT yet a proper commit — they are a partial Task 9 root-cause fix; squash into the Task 9 Step-7 commit once the loop is green).

## WIP commit

- SHA: `ee73271` (`wip: context-handover snapshot`) — the 5 test-double edits across 2 files.
- Pushed: **yes** (`origin/fynPromptRework`).
- Note: `043a243` (session-4 wip) still sits below the session-5 pointer with unrelated pre-existing files — leave it, do NOT squash into prompt-rework (carried forward).

## Open decisions

None blocking. The Task-9 gate definition ("Step 5 + Step 6") is CSJ-decided and FINAL — do not re-ask. The eval-corpus automated-runner build is explicitly out of scope for this plan (separate future work).

## Pick up from here (auto-continue contract)

1. **Continue the Task 9 Rule #15 loop — it is RED, do NOT stop.** Re-run to get the current 11 failures with full detail:
   `FYN_PROMPT_ARCH=unified ./vendor/bin/pest --testsuite=Unit,Feature,Architecture 2>&1 | tee /tmp/u.txt` then `grep -nE "FAILED|⨯|Exception|but no expectations" /tmp/u.txt`.
   (Prior run output was at `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/793209a7-900c-4c0b-834f-f75953de0849/tasks/brh8lyo8t.output` — may be GC'd; re-run is authoritative.)
2. For each of the 11: `superpowers:systematic-debugging`. Strong hypothesis (verify, don't assume): same defect class — other strict mocks of `CoordinatingAgent` or HasAiChat-using agents elsewhere not modelling the unified-mode calls (`setUnifiedOnboardingFocus` and/or `injectUnifiedTurnContext`/the `chat()` seam), OR a different unified coupling (e.g. a test asserting legacy prompt internals). Apply the SAME minimal codebase-idiomatic non-weakening fix (`->shouldReceive(...)->zeroOrMoreTimes()`), or fix the real coupling — never weaken behavioural assertions, never silence, never touch the spec-locked production seam or legacy path.
3. Loop diagnose→fix→re-run until **full suite under unified == legacy parity** (target **3725 passed / 1 skipped** — that is the legacy baseline at `550a107` from Task 8's regression).
4. Then run full suite under **legacy** (default, NO env var): `./vendor/bin/pest --testsuite=Unit,Feature,Architecture` → must be **3725 / 1 skipped** (proves the `zeroOrMoreTimes` test-double additions don't disturb the default path; they're zero-call-satisfied in legacy).
5. Commit the accumulated root-cause fixes as plan Task 9 Step 7 (squashing/absorbing `ee73271`): `git add -A && git commit -m "test(fyn): unified prompt parity gate green vs legacy baseline"` + push. Also create `April/May16Updates/fyn-prompt-rework-parity.md` recording the Step 5 legacy-vs-unified result table (legacy 3725/1 baseline; unified after-fix 3725/1; note the eval-corpus runner is scaffold-only, gate = Step 5 + Step 6 per CSJ decision).
6. **Step 6 — Playwright browser verification under unified (Rule #15 NON-NEGOTIABLE).** Restart dev server with `FYN_PROMPT_ARCH=unified` in env (it currently runs legacy; `.env` has no `FYN_PROMPT_ARCH`). Local dev → fetch MFA code from DB per CLAUDE.md. Three journeys: (a) advice "How is my pension doing?" → personalised hedged answer + FCA signposting, no IDs/routes leaked; (b) write-intent "Add a Cash ISA with Nationwide, £5,000, 4.5%" → DB row persisted via handoff, no fabricated success; (c) fresh-register onboarding, pick a focus, multi-entity "Halifax ISA £10k and Nationwide saver £5k" → 2 rows in one turn + ≤15-word ack. Record in the parity file. Any failure → back to step 2.
7. Then **plan Task 10** (docs/canonical/tag) — full text plan lines 1272-1316 + the routed contract-doc item already in task-tracker #7 description. Then `superpowers:finishing-a-development-branch`.

## What the next Claude needs to know

- **subagent-driven-development is the execution skill.** Tasks 4–8 used it (implementer→spec-reviewer→code-quality-reviewer per task). Task 9 is the Rule #15 gate — driven DIRECTLY by the controller (not delegated; the loop-until-green + browser-test contract is the controller's to own per CLAUDE.md Rule #15). Task tracker (TaskCreate/Update) IDs: #1–#5 = plan Tasks 4–8 (completed), #6 = plan Task 9 (in_progress), #7 = plan Task 10 (pending, has the routed-doc note in its description).
- **D4 spine still applies to Task 10**: prompt text is byte-locked; only documented deltas. Task 10 is docs/tag only.
- The fix idiom for unified-mode mock coupling is **`->shouldReceive('<method>')->zeroOrMoreTimes()`** mirroring `ChildrenDOBFallbackTest:59` (`invalidateUserCache`). It is non-weakening (zero-call-satisfied in legacy, other methods stay strict, behavioural assertions untouched). This is "fix the real coupling defect" per plan Step 5, NOT silencing — defect is genuinely in stale test doubles that don't model the new flag-gated collaborator contract.
- `ChildrenDOBFallbackTest`/`StateMachineWalkthroughTest` were NOT among the failures (reflection / non-seam paths) — they passed untouched. Scope discipline: only fix sites the evidence shows failing; don't pre-emptively edit passing tests.
- Legacy path is byte-untouched through T7/T8 (spec-reviewed, confirmed). Don't disturb it. Flag default stays `legacy` until Step 5 + Step 6 green.
- Vite canonical :5173 — don't `pkill -f vite`. DB seeded at session-start; don't reseed. `User::factory()->make()`/`->create()` fine in these feature tests (RefreshDatabase + TaxConfigurationSeeder in beforeEach where needed).
- Two-stage-review templates: `/Users/CSJ/.claude/plugins/cache/claude-plugins-official/superpowers/5.1.0/skills/subagent-driven-development/{implementer,spec-reviewer,code-quality-reviewer}-prompt.md`.

## Branch / deploy state

- Branch: `fynPromptRework` (off `dev`)
- Ahead of origin: 0 (all pushed incl. WIP `ee73271`)
- Behind origin: 0
- Deploy status: **Not deployed** — feature-flagged, default legacy, no behaviour change until Step 5 + Step 6 parity gate green and CSJ flips the flag. Docs/code/tests only.
