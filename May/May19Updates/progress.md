# Progress Log

## Session 2026-05-19

- Session-start bootstrap done: branch `fynPromptRework`, 0/0 vs origin,
  DB seeded, handover-12 read (PR #335 open → dev, billing fix GREEN).
- Task received: audit unified Fyn impl vs plan + optimise prompts,
  deliver to May/May19Updates.
- Created task_plan.md / findings.md / progress.md.
- Phase 1 starting: locate + read all implementation files.
- Phase 1–4 DONE. Read all 8 Fyn files + 2 seams + parity + canonical.
  30/30 unified tests green. Audit verdict: CORRECT, 0 defects, 6
  deliberate documented improvements over the plan.
- Measured prompt waste: static prompt 3,706 tok (cached); per-turn block
  ~1,233–1,289 tok (uncached). Prime waste = `<data_completeness>` ~595 tok
  of byte-identical STATIC rule text in the uncached per-turn channel.
- Deliverable written: `May/May19Updates/unified-fyn-audit-and-prompt-optimisation.md`
  (Part A audit map, Part B/C measured waste + ranked C1–C4 plan w/ token
  deltas, Part D risks). No code changed. Awaiting CSJ on execution scope.
- Cleanup: /tmp/measure*.php /tmp/dump_*.php are throwaway scratch.
