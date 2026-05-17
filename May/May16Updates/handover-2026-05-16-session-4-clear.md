---
type: handover
mode: context-clear
date: 2026-05-16
session: 4
branch: fynPromptRework
trigger: context-handover skill (context tripwire ~207k tokens)
---

# Context Clear Handover — 2026-05-16, Session 4

## Immediate state

Just finished the brainstorming → spec → plan cycle for the **Fyn prompt rework**. Plan is written, committed, and CSJ has chosen the execution approach. Tripwire fired immediately after CSJ answered "1" (Subagent-Driven execution). **Next session starts executing the plan, Task 1, via subagent-driven-development.**

## The thread

- CSJ asked to rework the Fyn prompt: kill the two-prompt system (Onboarding Fyn / Advice Fyn), one **static** system prompt (identity, scope, response format, rules), dynamic context + user message + tools in the **user turn**. Archive the old architecture for recovery.
- Ran `superpowers:brainstorming`. 6 decisions locked via AskUserQuestion:
  - **D1 Scope:** prompt + turn only — KEEP dispatch branch, both directors, CoordinatingAgent handlers, audit chain, prerequisite gate, preview blocking, `delegate_to_capture`. (Rejected: full rebuild, dispatch unification.)
  - **D2 Context selection:** lean 4-bucket selector reusing existing `QueryClassifier` signal. (Rejected: always-full-snapshot, minimal+tools-pull.)
  - **D3 Static-ness:** fully static system prompt, ZERO interpolation; firstName + taxYear move to the user turn. (Rejected: keep name/tax slots.)
  - **D4 Authoring:** restructure + PRESERVE wording — do not reword compliance/security text. (Rejected: rewrite lean/fresh.)
  - **D5 Recovery:** feature flag `FYN_PROMPT_ARCH=legacy|unified` (default legacy). (Rejected: git-tag clean cut, archive dir.)
  - **D6 Canonical:** CSJ said "(c) write the spec" → **rewrite** `00-canonical.md`, not annotate.
- Spec written, self-reviewed (caught known-facts mode-independence regression, fixed), committed `6007163`; lint table-truncation fix `d8c2908`. CSJ: "looks good".
- Ran `superpowers:writing-plans`. Explored real plumbing: single advice seam = `HasAiChat::buildSystemPrompt()` (:794, only caller of `AdvicePromptBuilder::build`); single onboarding seam = `OnboardingChatDirector:1727`; context must inject into last user message of `$messageHistory` (:152) in-memory only (not persisted). 10-task TDD plan written + self-reviewed, committed `977f2e0`.
- CSJ chose **execution option 1 — Subagent-Driven** (fresh subagent per task, review between).

## Files touched this session

- `docs/superpowers/specs/2026-05-16-fyn-prompt-rework-design.md` (created — design spec, rewritten canonical in §9)
- `docs/superpowers/plans/2026-05-16-fyn-prompt-rework.md` (created — 10-task impl plan)
- `prompts/`, `tools/`, `personas/`, `fyn/`, `campaigns/` (committed earlier `fc5b0bf` — Fyn prompt workspace, reference material)
- WIP snapshot `043a243`: pre-existing unrelated files (.goal, FCA*, techDebt.md note, prior handovers) — NOT this session's work, just tree-clean.

## WIP commit

- SHA: `043a243` — `wip: context-handover snapshot`
- Pushed: yes (branch `fynPromptRework` now tracks origin)
- Contains only pre-existing/unrelated files (.goal mission contract, FCA sandbox docs, techDebt.md PR#303 note, earlier handovers). Next session: leave these or sort separately — they are NOT part of the prompt rework. Do NOT squash them into prompt-rework feature commits.

## Open decisions

None blocking. CSJ has decided execution = **Subagent-Driven**. Default direction of travel: begin plan execution immediately.

## Pick up from here (auto-continue contract)

1. Invoke `superpowers:subagent-driven-development` to execute `docs/superpowers/plans/2026-05-16-fyn-prompt-rework.md`.
2. Start at **Task 1: Feature flag + mode helper** (create `config/fyn.php`, `app/Services/AI/Fyn/FynPromptMode.php`, test). Plan tasks are fully spec'd with code + commands — no design decisions remain.
3. Fresh subagent per task, two-stage review between tasks, as the skill dictates. Tasks 1–6 build units; 7–8 wire the two seams behind the flag; 9 is the eval parity gate (Rule #15 loop — measure legacy baseline, then unified, fix until ≥); 10 rewrites canonical + tags `fyn-two-prompt-pre-unify`.
4. Default flag stays `legacy` throughout — nothing changes behaviour until eval parity proven in Task 9.

## What the next Claude needs to know

- **D4 is the highest-risk constraint:** Tasks 3 & 4 paste prompt text VERBATIM from pinned `file:line` ranges (`CoreIdentity.php:18-67`, `ComplianceRules.php:18-41`, `FcaProcessInstructions.php:39-92`, `AdvicePromptBuilder::getHandoffGuidance/getBillingGuidance/buildFcaSignpostingBlock`, `prompts/onboarding-system-prompt.md:136-199`). ONLY 2 wording deltas allowed (firstName → "the user"/context, taxYear → "given in your turn context"). Any other reword = eval regression.
- The legacy path must stay **byte-untouched** — changes are only the two `if (FynPromptMode::isUnified())` guards. Task 7/8 regression steps catch disturbance.
- Eval suite: `tests/Feature/Fyn/Eval` driven by `EvalRunner::run()`, config `config/fyn_eval.php`, own testsuite (excluded from default Feature). Canonical scenario category `09-canonical-behaviour` = zero-regression gate.
- `UserContentSanitiser` class path: confirm namespace with `grep -rn "class UserContentSanitiser" app/` before the `use` in Task 6 (plan notes this).
- Vite canonical port :5173 — don't `pkill -f vite`. DB seed each session per CLAUDE.md.
- Memory/CLAUDE.md Rule #16 (icons forward-only), Rule #15 (loop until green) apply to Task 9.

## Branch / deploy state

- Branch: `fynPromptRework` (off `dev`)
- Ahead of origin: 0 (just pushed, in sync)
- Behind origin: 0
- Deploy status: Not deployed — docs/planning only, no code yet, flag defaults legacy
