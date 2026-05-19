---
type: handover
mode: context-clear
date: 2026-05-16
session: 6
branch: fynPromptRework
trigger: context-handover skill (context tripwire >90% / ~186k tokens)
---

# Context Clear Handover — 2026-05-16, Session 6

## Immediate state

Executing `docs/superpowers/plans/2026-05-16-fyn-prompt-rework.md` via `superpowers:subagent-driven-development`. **Tasks 1, 2, 3 are DONE** (each implemented + spec-reviewed + code-quality-reviewed + committed + pushed). Next action: **start Task 4 (FynCaptureTurnInstructions)**. Tripwire fired immediately after Task 3 closed — clean break, nothing in flight.

## The thread

- Session-start auto-continued from session-5 pointer → session-4 handover. Plan execution began under subagent-driven-development: fresh implementer subagent per task, then a spec-compliance reviewer, then a code-quality reviewer, two-stage gate per task.
- **Task 1** (`7e74172`): `config/fyn.php` + `App\Services\AI\Fyn\FynPromptMode::isUnified()` (fail-safe flag, default legacy). Spec ✅ + quality ✅, zero issues.
- **Task 2** (`03046d3`): `ContextBucket` enum (IDENTITY/POSITION/READINESS/CAPTURE) + immutable `FynTurnContext` VO with `make()` named-ctor + invalid-mode guard. Spec ✅ + quality ✅. Reviewer probed `InvalidArgumentException` vs the repo's `FinancialCalculationException` domain pattern and concluded the SPL exception is the correct house pattern for a structural arg guard (matches `CaptureContext`/`InsightImageService`) — no change.
- **Task 3** (`3c27d9b`, the highest-risk D4 task): static byte-stable `FynSystemPrompt::text()`. **A real plan defect surfaced and was routed through the loop (Rule #15), not handed back:** the planned test asserted `substr_count($p,'<handoff_guidance>')===1`, but the verbatim `<available_actions>` source (`FcaProcessInstructions.php:71`) contains an inline backtick cross-ref `` `<handoff_guidance>` `` → 2 substring hits. The first implementer "fixed" this by editing the prompt text (an undocumented Delta 3). **That was rejected and reverted** — reasoning: legacy's assembled prompt has the exact same double-occurrence and passes evals, so byte-verbatim is the zero-eval-risk path; D4 forbids any 3rd prompt delta. Correct fix applied instead: prompt restored to byte-verbatim (only the 2 documented deltas), and the **planned test bug** fixed — block-count assertion changed to a line-anchored `preg_match_all('/^TAG$/m',...)===1` (true intent: each block opens once on its own line; excludes mid-sentence backtick refs; still catches missing/duplicated for all 13 tags). Independent spec-reviewer did a full 12-block byte-fidelity audit → all 12 byte-identical with exactly the 2 deltas, Delta 3 confirmed reverted, `getPreviewMode()` confirmed excluded.
- Code-quality review of Task 3 returned "approve w/ 2 recs". Applied receiving-code-review rigour: **rejected** the trailing-newline rec (would break the snapshot==`text()` byte-mirror invariant and force a `trim()` fudge; the Task 9 risk it cited is speculative); **accepted** strengthening tautological test 1 into a real snapshot-regression tripwire (`text()` must byte-equal `docs/superpowers/specs/fyn-system-prompt.snapshot.txt`); **skipped** cosmetic minors per scope discipline. Amended into `3c27d9b`, 5/5 tests pass.

## Files touched this session

```
config/fyn.php                                              (new, T1)
app/Services/AI/Fyn/FynPromptMode.php                       (new, T1)
app/Services/AI/Fyn/ContextBucket.php                       (new, T2)
app/Services/AI/Fyn/FynTurnContext.php                      (new, T2)
app/Services/AI/Fyn/FynSystemPrompt.php                     (new, T3)
docs/superpowers/specs/fyn-system-prompt.snapshot.txt       (new, T3 — 17,672 bytes, byte-mirror of text())
tests/Unit/Services/AI/Fyn/FynPromptModeTest.php            (new, T1, 3 pass)
tests/Unit/Services/AI/Fyn/FynTurnContextTest.php           (new, T2, 3 pass)
tests/Unit/Services/AI/Fyn/FynSystemPromptTest.php          (new, T3, 5 pass)
```

3 feature commits: `7e74172` (T1) · `03046d3` (T2) · `3c27d9b` (T3). All pushed to `origin/fynPromptRework`.

## WIP commit

- None this handover — working tree is clean (every task self-committed per the plan's per-task commit step). The 3 feature commits ARE the work; do NOT squash them, they are properly scoped per-task.
- Note: `043a243 wip: context-handover snapshot` (from session 4) still sits in history below the session-5 pointer commit — it contains pre-existing UNRELATED files (.goal, FCA docs, techDebt.md note, old handovers), NOT prompt-rework work. Leave it / sort separately. Do NOT squash it into prompt-rework commits (carried forward from session-4 handover).

## Open decisions

None blocking. The Task 3 plan-defect (substr_count false-positive) was resolved by decision in-session (fix the test, not the prompt) with full reasoning above — treat as final, do NOT re-litigate. D1–D6 from session-4 still locked.

## Pick up from here (auto-continue contract)

1. Re-invoke `superpowers:subagent-driven-development` to continue executing `docs/superpowers/plans/2026-05-16-fyn-prompt-rework.md`.
2. Tasks 1–3 are complete (commits `7e74172`, `03046d3`, `3c27d9b` on `fynPromptRework`, pushed). **Start at Task 4: FynCaptureTurnInstructions** (plan lines 473–553).
3. Per-task loop: fresh implementer subagent (full task text inlined, do NOT make it read the plan file) → spec-compliance reviewer → code-quality reviewer → fix loop → mark done → next. Task list IDs #1–#10 already exist in the TaskCreate tracker (#1–#3 completed, #4 next).
4. Model selection used so far: haiku for mechanical fully-specified tasks (T1, T2), sonnet for D4-sensitive verbatim work (T3). Task 4 is verbatim-paste from `prompts/onboarding-system-prompt.md:136-199` (mirrors `OnboardingPromptBuilder` Layer 3) → **use sonnet, same D4 verbatim discipline as Task 3** (only the two `%1$s`/`%2$s` slot params; everything else byte-verbatim). Tasks 6/7/8 (assembler + two seams) are integration/judgement → sonnet. Task 9 (eval parity loop) is the Rule #15 gate.
5. **Task 3 precedent for Task 4:** the source onboarding block may likewise contain tag-like or `$`-like text — use a nowdoc, paste verbatim, and if a planned test assertion false-positives on verbatim source text, fix the TEST to assert true intent (don't mutate the prompt). Verbatim fidelity > passing a mis-specified assertion.

## What the next Claude needs to know

- **D4 is the spine of this plan.** Tasks 3 & 4 paste prompt text VERBATIM from pinned source ranges; ONLY the documented slot/delta substitutions are allowed. Any other reword = eval regression at Task 9. Verified source ranges (already de-risked this session): `CoreIdentity.php:21-66`, `ComplianceRules.php:18-41`, `FcaProcessInstructions.php` getFcaProcess `40-54`/getAvailableActions `61-92`, `AdvicePromptBuilder.php` getHandoffGuidance `253-274`/getBillingGuidance `301-316`/buildFcaSignpostingBlock `1152-1158`. `getModuleContext` at `AdvicePromptBuilder.php:1164` is ALREADY public and returns `?string` — Task 6's `moduleContextFor` passthrough should wrap it as `(string) ($this->getModuleContext($route) ?? '')` (no legacy change needed; the plan's "if private" branch does not apply).
- Reviewer subagents must NOT trust the implementer's report — both review templates enforce independent code reading; keep dispatching them that way.
- Legacy path must stay byte-untouched through Tasks 7/8 — only the two `if (FynPromptMode::isUnified())` guards. Task 7/8 regression steps catch disturbance.
- Flag stays `legacy` default until Task 9 proves parity. Nothing behaves differently yet — no deploy, no smoke needed mid-build.
- Vite canonical :5173 — don't `pkill -f vite`. DB already seeded this session (don't reseed at session-start; running tests is the work, not bootstrap). `User::factory()->make()` in unit tests is fine (no DB hit) — used in T2.
- Plan self-review (plan lines 1320-1336) maps every spec section to a task — use it as the Task 9 coverage checklist.
- Two-stage-review prompt templates live at `/Users/CSJ/.claude/plugins/cache/claude-plugins-official/superpowers/5.1.0/skills/subagent-driven-development/{implementer,spec-reviewer,code-quality-reviewer}-prompt.md`.

## Branch / deploy state

- Branch: `fynPromptRework` (off `dev`)
- Ahead of origin: 0 (Tasks 1–3 pushed: `565131c..3c27d9b`)
- Behind origin: 0
- Deploy status: Not deployed — feature-flagged, default legacy, no behaviour change until Task 9 parity gate. Docs/code only.
