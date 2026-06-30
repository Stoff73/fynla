---
type: handover
mode: context-clear
date: 2026-06-30
session: 3
branch: main (all session-2 work landed on dev via worktrees/PRs)
note: written post-/clear to replace the minimal PreCompact safety-net handover (session-2-clear-precompact)
---

# Context Clear Handover — 2026-06-30, Session 3

> Reconstructs **session 2** (the SaveTax-onboarding / ISA / gamification / pension
> push). Session 2 was auto-compacted by the PreCompact hook before a structured
> handover was written, so its only prior record was the minimal
> `handover-2026-06-30-session-2-clear-precompact.md` (last user prompts + a stale
> commit list). This handover is the real one — sourced from `.remember/` logs and
> the merged-PR history, since the session-2 transcript is no longer in context.

## Immediate state
Session 2 wrapped cleanly: **9 PRs (#583–#591) merged to `dev` and deployed to csjones `/m`**, all browser-verified. Working tree on local `main` is clean and in sync with `origin/main`. Nothing in flight. Natural next move is a **`dev → main` release decision** (CSJ's call) — prod is untouched.

## The thread (what session 2 shipped to dev)
- **#583** — collapse "spouse or partner" → "spouse" on the two spouse-only sites (`ProtectionActionDefinitionService`, `ModuleDataRequirementsService`). Clears 2 of the 5 stragglers from the #580 sweep.
- **#584** — personalise the 60% PA-taper trap advice (£100k–125k band) into two figures: PA-reclaim component + 40%-rate component (`IncomeBandStrategy`). Unit tests pass (£120k validates).
- **#585** — onboarding routes Cash vs Stocks & Shares ISA, captures this-year subscription, skips the investment ask for Cash ISA, names accounts correctly.
- **#586** — drop the pension carry-forward question from onboarding.
- **#587** — show the **spouse's** income on the spouse verify screen (`UserProfileService`, `Income.vue`).
- **#588** — gamification hero card shows **dynamic actions-to-next-level** (0%→4, 25%→3, 50%→2, 75%→1; max 4). NOT a banned score (Rule #12 carve-out — CSJ-approved gamification).
- **#589** — pension account cards moved under the hero on retirement `/m`.
- **#590** — present ISAs as ISAs, not "bank accounts". Root cause was `OnboardingStateMachine:1076` `sectionLabel` mapping (DB routing was already correct); also split mobile `Savings.vue` grouping (Bank accounts vs Cash ISA Accounts) and made the onboarding ack context-aware.
- **#591** — ISA wording fix: **"Cash ISA Accounts"** — singular, NOT pluralised acronym ("ISAs"/"ISA's"). This is what CSJ was (loudly) asking for at compact-time.

## What the next Claude needs to know
- **ISA wording is sensitive.** ISA is the one allowed acronym (Rule #9) but **never pluralise it** in screen labels — write "Cash ISA Accounts", not "ISAs"/"ISA's". CSJ pushed back hard; #591 is the fix.
- **Don't half-ship a rename** (the #582 lesson, reinforced): when renaming a screen, sweep Fyn's narration + every label + ack + verify message too. Browser-verify the whole flow, not one label.
- **The #588 gamification fields (`level`, `percentile`, actions-to-next-level) are NOT banned scores** — CSJ-approved engagement mechanic. Never strip or flag them in audits. `removeScores()` only strips *financial-quality* scores.
- **Stale-`/m`-from-main trap:** during the session-2 E2E walk, a csjones dashboard auth failure was caused by a stale `/m` bundle built off `main` (61 commits behind `dev`). Fix was rebuild from `dev` + redeploy. If `/m` misbehaves on csjones, check the bundle is built from `dev`.
- **All work was on `dev` via worktrees.** Local `main` dir stays on `main` and has no code changes — that's expected, not a lost-work problem.

## Files touched
- None uncommitted in this `main` checkout (session-2 code is all merged PRs on `dev`).
- This session committed: the untracked `handover-2026-06-30-session-2-clear-precompact.md` safety-net file + this handover + CSJTODO.

## Deploy status
- **dev (csjones.co/fynla):** has everything — `dev` tip `0a42695` (Merge #591), `/m` bundle deployed, verified live.
- **prod (fynla.org):** UNTOUCHED. PRs #581–#591 are all dev-only. A `dev → main` release would carry: SaveTax recap/two-bubble, bank-accounts current-account default + /m screen, spouse→spouse copy, PA-taper personalised advice, Cash/S&S ISA routing, drop pension carry-forward Q, spouse-income verify, gamification actions-to-next-level, pension card reorder, and the ISA-presentation/"Cash ISA Accounts" fixes.

## Deferred (raised, not actioned)
- **`dev → main` release** — CSJ's call. Everything is verified and ready.
- **3 remaining "spouse or partner" stragglers** (#583 cleared 2 of 5): `JourneyFieldResolver:287`, `public/pages/savetax-v2.php:210` and `:237`. Quick, low-risk.
- **Anthropic provider** `create_savings_account.md` schema still divergent (no `current_account`) — left as-is; xAI is the live provider.

## Pick up from here
1. Ask CSJ whether to open the periodic **`dev → main` release PR** (carries #581–#591). If yes: build prod assets (`./deploy/fynla-org/build.sh`), upload `public/build` + `public/m-build` + changed PHP + corpus, `migrate --force` (none new) + cache clears, monitor logs.
2. If continuing feature work, branch off `dev` (`0a42695`) in a worktree — main dir stays on `main`.
3. If desired, sweep the 3 remaining "spouse or partner" stragglers above.

## Context hints
- Active branch type: mainline (main dir on `main`; all feature work via worktrees off `dev`).
- Behind origin/main by: 0. Ahead by: 0 (in sync).
- Uncommitted: none after this skill's final commit.
- Last commit (main): `4852d65` docs(session): eod handover 2026-06-30-session-1.
- dev tip: `0a42695` Merge PR #591.
- csjones: on `dev` at `0a42695`.
