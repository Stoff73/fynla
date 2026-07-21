---
type: handover
mode: context-clear
date: 2026-05-18
session: 7
branch: fynPromptRework
trigger: context-handover skill (context tripwire ~243k / 250k budget)
---

# Context Clear Handover — 2026-05-18, Session 7

## Immediate state

**Fyn system-prompt slim-down is DONE and shipped to PR #335** (commit
`a33f2fb`, pushed). All 4 handover-6 pickup tasks complete and GREEN per
the plan. A trailing one-line stale-comment fix (`grok-4-1-fast` →
`grok-4.3` at `AdviceFyn.php:263`, CSJ-requested) is in WIP commit
`20ac500` (pushed). PR #335 OPEN, NOT self-approved/merged.

## The thread

- Auto-resumed handover-6. Implemented the agreed Fyn prompt slim-down
  exactly per the final design (no re-litigation):
  - `FynSystemPrompt.php`: deleted `<billing_guidance>`; gutted
    `<handoff_guidance>` to a 3-line Tier-2 LLM safety net (NOT deleted);
    rewrote `<available_actions>` line 122; all compliance/security
    verbatim. 17,632 → 14,822 bytes. Snapshot regenerated; block-list
    test updated to drop `<billing_guidance>`.
  - `AdviceFyn.php`: added symmetric Tier-2 harvest log `[AdviceFyn]
    LLM-fallback write-intent caught (classifier miss)` in `wrapStream`'s
    `delegate_to_capture` branch (pairs with the Tier-1 log at `:325`).
- Re-validated: 431 AI Pest ×2 flags (unified+legacy), 97 Architecture,
  5 FynSystemPromptTest — all green. Live browser: BS-14 ✅ (row
  persisted, invisible handoff), BS-11 ✅ core docblock contract
  (LifeInsurancePolicy provider=Aviva £300k created today, placeholder
  invariant held, Tier-1 classifier fired). Test residue cleaned off
  seeded user sarah.
- Did a baseline A/B (temporarily restored verbose `<handoff_guidance>`,
  re-probed): verbose prompt is equal-or-WORSE — it triggered the
  `navigate_to_page` anti-pattern. Proved the slim-down does NOT regress
  the write path. Restored slim version, re-confirmed snapshot green.
- Committed `a33f2fb`, pushed, PR #335 → 5 commits, OPEN, not merged.
  Wrote memory `reference_unified_prompt_has_no_billing_layer` + MEMORY
  index line.
- CSJ corrected my model-name parroting: live model is **grok-4.3**
  (`config/services.php:42-44`, `XaiClient.php:19`), grok-4-1-fast is
  retired. Fixed the one stale code comment at `AdviceFyn.php:263` per
  CSJ's explicit request (WIP `20ac500`).
- Rejected/retired during this session: the "grok-4-1-fast" naming in
  my findings prose (corrected to grok-4.3, substance unchanged).

## Files touched this session

```
PR #335 commit a33f2fb (4 files):
  app/Services/AI/Fyn/FynSystemPrompt.php          slim (−34 net)
  app/Services/AI/AdviceFyn.php                    +16 Tier-2 telemetry
  docs/superpowers/specs/fyn-system-prompt.snapshot.txt  regenerated
  tests/Unit/Services/AI/Fyn/FynSystemPromptTest.php     −1 (block list)
WIP 20ac500 (1 file):
  app/Services/AI/AdviceFyn.php                    :263 grok-4.3 comment
Memory (not in repo):
  memory/reference_unified_prompt_has_no_billing_layer.md  + MEMORY.md line
```

## WIP commit

- SHA: `20ac500` — `wip: context-handover snapshot` (the grok-4.3
  comment fix only)
- Pushed: **yes** (`origin/fynPromptRework`)
- Squash note: `a33f2fb` is the clean feature commit — do NOT re-squash
  it. `20ac500` is the only WIP to fold into a follow-up chore commit
  (or into PR #335) before the eventual `dev` merge. PR #335's earlier
  WIP `b183a8f` was already accounted for in handover-6.

## Open decisions

- **None blocking.** The slim-down deliverable is complete and GREEN per
  the plan's definition (Pest parity ×2 flags + BS-NN docblock core
  contracts). PR #335 awaits CSJ review/merge (not self-approved).
- **Scope-boundary decision flagged for CSJ (not blocking this PR):**
  two PRE-EXISTING, out-of-scope defects surfaced during live probing,
  logged per `reference_legacy_refuses_advice_capture_journey` precedent
  ("log separately, proceed per contract"):
  - **F1** — grok-4.3 Tier-2 path unreliably emits `delegate_to_capture`
    on classifier-*missing* phrasing (slim narrates; verbose navigates —
    proven by baseline A/B). Pre-existing; Tier-1 deterministic
    classifier is the architectural mitigation; the new Tier-2 telemetry
    is the harvest feed for the handover-named "iteratively widen the
    classifier" follow-up loop.
  - **F2** — grok-4.3 capture-extraction non-determinism leaves
    blank/zero companion duplicate rows (BS-14 row blank provider; BS-11
    correct L#64 + empty L#63). Documented pre-existing in BS-17's own
    docblock. Tier-1 → governed by `FynCaptureTurnInstructions` (NOT
    touched by this PR).
  Default direction of travel: F1/F2 are NOT this PR's blockers; they
  are a separate grok-reliability / classifier-widening workstream.

## Pick up from here (auto-continue contract)

PR #335 deliverable is complete — there is no in-flight implementation
to resume. Next session, in priority order:

1. **Fold WIP `20ac500` into a clean commit** — either `git commit
   --amend`-style squash into a `chore(fyn): correct stale grok model
   name in comment` on `fynPromptRework`, or leave it as a discrete
   trivial commit on PR #335. Do NOT re-squash `a33f2fb`.
2. **Scan for other stale `grok-4-1-fast` references** and update to
   `grok-4.3` (read-only first): `grep -rn "grok-4-1-fast" app/ tests/
   docs/ April/ 2>/dev/null`. Several known stale doc/comment sites
   exist (handover-6 text, possibly BS-NN docblocks, memory files).
   This is a small chore — surface the list to CSJ before bulk-editing
   docblocks (BS-NN docblocks are test contracts).
3. **Await CSJ on PR #335** — do not self-approve/merge (Rule:
   `feedback_no_self_approval`, `feedback_main_via_dev_only`).
4. If CSJ wants F1/F2 actioned: that's the classifier-widening
   workstream — open it as its own plan/sub-project, not folded into
   #335.

## What the next Claude needs to know

- **Live model is `grok-4.3`** — `config/services.php:42-44`
  (`XAI_CHAT_MODEL`/`ADVANCED`/`VISION` all default `grok-4.3`),
  `XaiClient.php:19`/`104`/`112`/`120`, `ConversationSummariser.php:30`.
  grok-4-1-fast is the RETIRED predecessor. Don't parrot the old name
  from stale comments/handover text — check config.
- **Unified has NO billing layer on any turn** post-#335 — see memory
  `reference_unified_prompt_has_no_billing_layer`. FynContextAssembler
  adds none; legacy gates `<billing_guidance>` on BILLING classification.
  Pest can't see the gap (sibling tests the legacy builder). If BS-16
  billing shape is needed under unified, re-add as a per-turn assembler
  layer, NOT to the static prompt.
- **Two-tier write arch (unchanged):** Tier-1 = `WriteIntentClassifier`
  (`AdviceFyn.php:268`, pre-LLM, verb+entity match) → straight to
  `handleInlineCapture`. Tier-2 = LLM emits `delegate_to_capture` via
  the slim `<handoff_guidance>` → `wrapStream` (~:459) → same handler.
  My slim change only affects Tier-2 (LLM-text); Tier-1 bypasses the
  prompt entirely.
- Dev server was running this session (php :8000, vite :5173,
  `public/hot` = http://127.0.0.1:5173). session-start Phase 1e restarts
  cleanly. Do NOT pass FYN_PROMPT_ARCH (unified is config default).
- Worktree `.claude/worktrees/tender-bassi-375ee8` on `freemium` (HEAD
  5a5478b, clean) — sub-project 2, leave intact.
- `FynSystemPrompt.php` file-header rule still applies: never reword
  compliance/security; re-validate against the Fyn eval suite on any
  change. The slim version is the current snapshot baseline.
- Memory directly relevant: `reference_unified_prompt_has_no_billing_layer`
  (new), `reference_legacy_refuses_advice_capture_journey`,
  `feedback_advice_fyn_is_read_only`, `feedback_loop_until_correct`,
  `critical_browser_testing_law`, `feedback_no_self_approval`.

## Branch / deploy state

- Branch: `fynPromptRework`
- Behind origin: 0
- Ahead of origin: 0 (all pushed incl. WIP `20ac500`)
- PR: **#335 OPEN** → `dev`, 6 commits, awaiting CSJ review (NOT
  self-approved/merged)
- Deploy status: Not deployed (feature branch)
