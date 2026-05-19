---
type: handover
mode: context-clear
date: 2026-05-18
session: 6
branch: fynPromptRework
trigger: context-handover skill (context tripwire ~209k / 250k budget)
---

# Context Clear Handover — 2026-05-18, Session 6

## Immediate state

Design for the **Fyn system-prompt slim-down is AGREED and unambiguous** —
ready to implement next session. No code written yet for it. PR #335
(`fynPromptRework → dev`) is OPEN with 4 commits, awaiting CSJ review.

## The thread

- Auto-resumed session-5-clear. Did all 3 handover pickup tasks: opened
  **PR #335** (squashed post-#332 delta to one clean commit, force-pushed),
  formalised the legacy-refusal finding (memory
  `reference_legacy_refuses_advice_capture_journey.md` + CSJTODO + classification
  cross-link), ran tech-debt-session (`tech-debt-report.md`: 0 critical,
  1 warning, 1 suggestion).
- CSJ asked to fold the tech-debt warning into PR #335 → done (`99dc84d`:
  extracted `CoordinatingAgent::SAVINGS_ACCOUNT_TYPES` const, both the
  coercion guard and `Rule::in` reference it; pure refactor, Pint/lint/arch
  green).
- Ran vault-sync (Haiku subagent) — clean; verified the canonical-spec
  data-loss risk was already closed session 2 (`VAULT-SYNC-PENDING.md`
  status). May18.md git-history created, all metrics current.
- CSJ asked me to re-explain the KYC delta. Discovered the long-standing
  "CSJ DECISION — Fyn delta #2 (single gate source vs telemetry-only)"
  CSJTODO item is a **misdiagnosis** — Delta 2 was a parity *bug* (unified
  discarded `$kycResult`), already FIXED in PR #335. **Struck it in CSJTODO**
  (`b183a8f`), marked the now-done vault-sync + squash/PR items done.
- CSJ then directed the Fyn system-prompt cleanup. After investigation +
  two rounds of clarification we landed a **firm agreed design** (below).
  Rejected approaches: (a) "graceful degradation on classifier misses" —
  CSJ explicitly rejected, user must edit/amend/delete with ANY framing;
  (b) fully deleting `<handoff_guidance>` — rejected, it kills the LLM
  safety net. Final = slim it, don't delete it.

## Files touched this session

```
origin/dev..HEAD (PR #335, 4 commits + 2 handover-infra commits):
  app/Agents/CoordinatingAgent.php              +const dedup + account_type coercion
  app/Services/AI/Fyn/FynContextAssembler.php   KYC layer (Delta 2 fix)
  app/Services/AI/Fyn/FynTurnContext.php        +kycResult field
  app/Services/Onboarding/OnboardingChatDirector.php  unified capture seam
  app/Traits/HasAiChat.php                      captureTurnCompleteDirective + kycResult thread
  config/fyn.php                                default legacy→unified
  tests/Feature/Fyn/*, tests/Unit/.../FynContextAssemblerTest.php
  CSJTODO.md, tech-debt-report.md, May/May18Updates/* (docs)
This session's own commits: 5337ab9, 93b25ed, 99dc84d, b183a8f (WIP).
```

## WIP commit

- SHA: `b183a8f` — `wip: context-handover snapshot` (CSJTODO KYC strike)
- Pushed: **yes** (`origin/fynPromptRework`)
- Squash note: PR #335 already has a clean feature commit `5337ab9`;
  `93b25ed`/`99dc84d` are legit follow-ups; `b183a8f` is the only WIP to
  squash/amend before the eventual `dev` merge. Do NOT re-squash `5337ab9`.

## Open decisions

- None blocking. The system-prompt design is fully agreed (see "Pick up
  from here"). CSJ confirmed: NO graceful degradation; keep LLM safety net;
  add classifier-miss telemetry + iteratively widen the classifier.

## Pick up from here (auto-continue contract)

**Implement the agreed Fyn system-prompt slim-down on `fynPromptRework`
(folds into PR #335). The design is final — do not re-litigate, just build.**

1. **`app/Services/AI/Fyn/FynSystemPrompt.php`:**
   - **Delete `<billing_guidance>`** (lines ~168-183) entirely. No
     cross-references exist (verified by grep — it's only in this file).
   - **Gut `<handoff_guidance>`** (lines ~145-166) down to a minimal
     safety-net instruction — DO NOT delete it. Keep ~3-5 load-bearing
     lines: *any add/change/delete intent the application has not already
     handled → emit `delegate_to_capture` with `reason` + `entity_types`
     (both REQUIRED); never fabricate a save; never navigate the user to a
     form instead.* Strip the verbose anti-pattern list, the worked
     example, the duplicated trigger-verb sentence. Rationale: the
     deterministic `WriteIntentClassifier` (`AdviceFyn.php:268`, pre-LLM)
     is the fast Tier-1 path; this slim block is the Tier-2 LLM safety net
     so ANY framing the classifier misses still captures (CSJ: no graceful
     degradation, ever).
   - **Rewrite `<available_actions>:122`** ("CREATING RECORDS …"): drop the
     dead `<handoff_guidance>` cross-ref verbosity; keep the still-true
     load-bearing constraint — Advice Fyn is read-only, never call
     `create_*`/`update_*`/`delete_*` directly (not in tool list), never
     fabricate a confirmation. Point tersely at the slim handoff block.
   - Preserve every compliance/security sentence verbatim (file header
     mandate). Only touch the two named blocks + line 122.
2. **Add Tier-2 telemetry** in `app/Services/AI/AdviceFyn.php` at the
   `wrapStream` `delegate_to_capture` branch (~line 459, where it
   `yield from handleInlineCapture`): a distinct
   `Log::info('[AdviceFyn] LLM-fallback write-intent caught (classifier miss)', [...])`
   with the verbatim `$message`, `entity_types`, `reason`. This is the
   harvest feed — the deterministic classifier (`AdviceFyn.php:268`,
   `WriteIntentClassifier`) only logs `[AdviceFyn] Deterministic
   write-intent routed` on Tier-1 hits (`:325`); we need the symmetric
   Tier-2-caught log so misses are reviewable. Do NOT change classifier
   logic this session — telemetry only; widening patterns is the ongoing
   follow-up loop.
3. **Re-run the Fyn eval / regression suite** — the file header mandates
   re-validation. Run BS-11/14/17 specifically (they were flaky on exactly
   this LLM-mediated write path per the `AdviceFyn.php:262-265` comment),
   plus parity (`--testsuite=Unit,Feature,Architecture` under both
   `FYN_PROMPT_ARCH` flags). LOOP UNTIL GREEN (Rule #15) per the BS-NN
   docblock contracts.
4. Commit as a focused `refactor(fyn): slim handoff guidance to Tier-2
   safety net + classifier-miss telemetry` onto `fynPromptRework`, push
   (auto-updates PR #335). No self-approve / no auto-merge.

## What the next Claude needs to know

- **Two-tier write architecture (verified this session, file:line):**
  Tier-1 = `WriteIntentClassifier::classify()` at `AdviceFyn.php:268`,
  runs server-side BEFORE the LLM, on a hit routes straight to
  `OnboardingChatDirector::handleInlineCapture` and `return`s (LLM never
  sees the turn) — `AdviceFyn.php:307-344`. Tier-2 = LLM emits
  `delegate_to_capture`, caught by `wrapStream` (~`:459`) → same
  `handleInlineCapture`. Classifier is deliberately conservative;
  `null` → falls through to LLM (`:266-267`). `<handoff_guidance>` is the
  ONLY thing driving Tier-2 — that's why it must be slimmed, not deleted.
- The capture turn inside `handleInlineCapture` is the **data_capture
  persona** (field extraction via `FynCaptureTurnInstructions`), unrelated
  to `<handoff_guidance>`; slimming the block does not touch it.
- `delegate_to_capture` `reason` is REQUIRED — omitting it breaks the
  handoff (`wrapStream` has a recovery path `AdviceFyn.php:430` but don't
  rely on it). Keep `reason`+`entity_types` mandatory in the slim block.
- Delta 2 is CLOSED — do NOT touch FynContextAssembler KYC code or
  resurface a "KYC decision". Authoritative: `fyn-canonical-vs-implementation-delta.md`
  §Delta 2 ("parity restored … no CSJ decision required").
- Memory directly relevant: `feedback_advice_fyn_is_read_only`,
  `feedback_loop_until_correct`, `critical_browser_testing_law`,
  `reference_legacy_refuses_advice_capture_journey` (legacy emergency-
  rollback already security-refuses this whole journey — separate, logged,
  not this work's blocker).
- Dev server was running this session (:8000 php, :5173 vite, `public/hot`
  fresh). session-start Phase 1e restarts cleanly (unified default — do
  NOT pass FYN_PROMPT_ARCH; .env unset, config default unified).
- Worktree `.claude/worktrees/tender-bassi-375ee8` on `freemium` (HEAD
  5a5478b, clean) — sub-project 2, leave intact.
- File-header rule on `FynSystemPrompt.php`: "DO NOT reword
  compliance/security sentences. Any change here must be re-validated
  against the Fyn eval suite." Honour both.

## Branch / deploy state

- Branch: `fynPromptRework`
- Behind origin: 0
- Ahead of origin: 0 (all pushed incl. WIP `b183a8f`)
- PR: **#335 OPEN** → `dev`, awaiting CSJ review (NOT self-approved/merged)
- Deploy status: Not deployed (feature branch)
