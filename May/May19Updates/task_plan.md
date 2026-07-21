# Task Plan — Unified Fyn Prompt: Implementation Audit + Prompt Optimisation

## Goal
1. Map the implemented unified Fyn AI architecture against the plan
   `docs/superpowers/plans/2026-05-16-fyn-prompt-rework.md` (10 tasks) and
   verify every task was implemented correctly.
2. Identify and optimise waste in the system / user / context prompts sent
   to the LLM (token bloat, redundant blocks, cache-busting patterns).
3. Deliver an audit + optimisation report into `May/May19Updates/`.

## Constraints
- Audit must be grounded in the code as it is NOW (no speculation).
- Prompt optimisation must preserve every FCA compliance invariant, the
  security clause, and the two-Fyn write-isolation guarantee.
- Byte-stability of the static system prompt = prefix cache hit. Any
  optimisation must keep it static + arg-free.
- Branch: fynPromptRework. PR #335 open → dev. Do NOT deploy/merge.
- Scope discipline: report findings; only change code if CSJ approves
  the optimisation phase.

## Phases

### Phase 1 — Read plan + locate implementation  [complete]
- [x] Read the full 10-task plan
- [x] Confirm every planned file exists in the tree
- [x] Read all 8 Fyn unified files + 2 modified seams

### Phase 2 — Task-by-task conformance map  [complete]
- [x] All 10 tasks verified. 30/30 unified tests green. Parity recorded.
      CONCLUSION: correct + conformant; 6 deliberate documented deviations,
      all improvements. No defects.

### Phase 3 — Prompt waste analysis  [complete]
- [x] Static prompt = 3,706 tok (cached). Per-turn = ~1,233–1,289 tok (uncached).
- [x] PRIME WASTE: `<data_completeness>` ~695 tok/turn, ~595 of which is
      byte-identical STATIC rule text living in the uncached per-turn block.
- [x] Quantified; concrete relocation plan with token deltas.

### Phase 4 — Deliverable  [in_progress]
- [ ] Write audit + optimisation report → May/May19Updates/
- [ ] Present optimisation plan; AskUserQuestion on execution scope

## Decisions
- Optimisation = report + proposal first. No prompt edits until CSJ approves
  (compliance-sensitive, cache-sensitive).

## Errors Encountered
| Error | Attempt | Resolution |
|-------|---------|------------|
| — | — | — |
