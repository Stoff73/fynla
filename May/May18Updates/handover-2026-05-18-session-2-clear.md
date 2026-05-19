---
type: handover
mode: context-clear
date: 2026-05-18
session: 2
branch: fynPromptRework
previous_session: 2026-05-18 session 1 (end-of-day)
---

# Context Clear Handover — 2026-05-18, Session 2

## Immediate state

Clean stop after a Claude Code tooling + Fyn-documentation session. All my work is
committed and pushed on `fynPromptRework` (`0171d5f`, `b4e976f`). No app code touched.
Working tree has ONLY pre-existing, not-mine changes left (see below) — deliberately
untouched.

## The thread

This session was **not** Fyn feature work — it was tooling + a documentation/mapping ask:

1. Ran `/claude-code-setup:claude-automation-recommender` → recommended automations for
   this mature setup.
2. Built a **PreToolUse Bash guard** (`.claude/hooks/dangerous-command-guard.sh`):
   hard-blocks the 4 documented NEVER-rule command classes (destructive DB migrations,
   artisan testing-env, vite-killing pkill, raw frontend production build). Fail-closed,
   unit+live verified, 0 false positives on 10 legit commands. Wired into
   `.claude/settings.json` PreToolUse.
3. Built a **PostToolUse Pint auto-format hook** (`.claude/hooks/pint-format.sh`):
   formats changed `*.php` with Laravel Pint, fail-open, non-blocking. Live-proven.
4. Retuned **context-watch.sh** 200k→250k budget (tiers 80/90/97.5% = 200k/225k/243k)
   and synced the `context-handover` skill description at
   `~/.claude/skills/context-handover/SKILL.md` (HOME dir — NOT in repo, can't commit).
5. **Mapped the Fyn unified prompt/response pipeline** (file:line verified, one
   general-purpose agent traced runtime dispatch) →
   `May/May18Updates/fyn-prompt-and-response-process-map.md` (system prompt verbatim,
   user-prompt assembly, context buckets, KYC gates, classification, tools, handoff).
6. Produced the **canonical-vs-implementation delta** →
   `fyn-canonical-vs-implementation-delta.md`, and the **`VAULT-SYNC-PENDING.md`** flag.

## Files touched (all committed unless noted)

- `0171d5f` — `.claude/settings.json` (+PreToolUse/+PostToolUse), `.claude/hooks/
  context-watch.sh` (250k), new `dangerous-command-guard.sh`, new `pint-format.sh`
- `b4e976f` — `May/May18Updates/{fyn-prompt-and-response-process-map,
  fyn-canonical-vs-implementation-delta,VAULT-SYNC-PENDING}.md`
- **NOT committed (outside repo):** `~/.claude/skills/context-handover/SKILL.md`
  threshold-description edit — lives in HOME, no repo path. Re-apply manually if that
  machine/profile is reset.
- **NOT committed (pre-existing, NOT mine, untouched):** `D fynlaFeatuuresModules/
  accDeletion/{accDeletion,design,plan}.md` + `?? fynlaFeaturesModules/` — looks like a
  `fynlaFeatuuresModules`→`fynlaFeaturesModules` rename someone did outside this
  session. Left exactly as found. **CSJ to decide** whether to commit/discard.

## What the next Claude needs to know

- **vault-sync is overdue and NOT run again this session** — deliberately deferred
  because context hit ~215k+ (>85% of 250k); session-1's handover documents that heavy
  vault-sync under context exhaustion corrupts the sync. `May/May18Updates/
  VAULT-SYNC-PENDING.md` enumerates the carry set: (1) `April/April24Updates/spec/
  00-canonical.md` [overdue ~6 sessions, gitignored `/April/` tree — data-loss risk],
  (2) the Fyn process map, (3) the delta doc. **This is priority 1 in fresh context.**
- **Fyn delta has 1 open CSJ decision** (delta #2): under `unified`,
  `KycGateChecker::check()` runs every advice turn but its `prompt_text` is discarded;
  the gate is re-derived softly via the READINESS bucket. Not a breach. CSJ must pick:
  (a) single gate source via KycGateChecker, or (b) telemetry-only. Detail in the delta
  doc §"Delta 2".
- **Fyn delta #1 is a doc-fix, not code:** `00-canonical.md` says dispatch is keyed
  purely on `users.onboarding_completed`; real predicate is 3-part (adds
  `onboarding_fyn_step !== null` + `onboarding.fyn_flow_enabled`). Code is correct;
  amend the canonical wording during the vault carry.
- The PreToolUse guard matches banned tokens **anywhere in the Bash command string** —
  including commit-message heredocs. Expect it to block commits whose message quotes
  `migrate:fresh` etc.; reword the message (this happened once this session, handled).
- The Fyn unified-prompt landing work from session-1's handover (legacy rollback sanity
  run, squash `9c19dcc`, new PR `fynPromptRework → dev`) is **still outstanding** —
  this session did none of it (different task). Carry forward.

## Pick up from here

1. **Fresh context, do FIRST:** `Skill: vault-sync`. Confirm all three files in
   `May/May18Updates/VAULT-SYNC-PENDING.md` land in the vault (esp. `00-canonical.md`
   from the gitignored `/April/` tree). Tick the checkbox in that flag file, then it can
   be deleted.
2. Put delta #2 (KYC dead-text-under-unified) in front of CSJ for the (a)/(b) decision.
3. Resume the session-1 Fyn landing checklist: `FYN_PROMPT_ARCH=legacy
   ./vendor/bin/pest --compact` rollback sanity → squash `9c19dcc` → new PR to `dev`.
4. Decide what to do with the pre-existing `fynlaFeatu*Modules` working-tree changes.
