---
type: handover
mode: end-of-day
date: 2026-07-01
session: 1
branch: main (feature work landed on dev via worktrees/PRs)
previous_session: 2026-06-30 session-3-clear
---

# Handover — 2026-07-01, Session 1

## Where we left off
Closed 30 June having shipped **two fixes to dev** (PRs #592 + #593), both **deployed to csjones and live-verified in the browser**. Working tree on `main` is clean and in sync with origin. **Prod (fynla.org) is untouched.** The standing open item remains a `dev → main` release (now #581–#593) — CSJ's call.

## What shipped today
*(The main checkout only shows the docs commit `f7af670`; the feature commits live on `dev`.)*
- **PR #592 — savetax `/m` final synthesis recap** (`OnboardingChatDirector::buildSynthesisAdvice`): the closing "Here is your plan…" Fyn message now renders the **same `composed_plan.items` the `/tax-strategy` dashboard shows, as markdown bullets** — dropped the numbered list, conflict notes, and the nonsensical "tell me about your GIA holdings" locked-tease; recomputes on a refreshed user. Chat ≡ dashboard by construction. Verified live on csjones `/m` (drove a user to a 7-item, £24,834 plan that matched the dashboard exactly). dev `22674e4`.
- **PR #593 — spouse Personal Allowance card note** (`SaveTaxEstimateService`): removed the inaccurate "Automatically used against your income." subtext from the **spouse** PA card on the unregistered/generalised SaveTax estimate (`public/pages/savetax-plan.php`, JS renders `a.note`). Suppressed for the spouse card only; the user's own card and all taper/unused notes unchanged. Reproduced + verified gone live on csjones. dev `03b851f`.
- Earlier: context-clear handover (`f7af670`) reconstructing the prior session's PRs #583–#591.

## What's in flight (NOT done)
- **Nothing code-wise** — both fixes merged + browser-verified.
- **`dev → main` release** of #581–#593 — pending CSJ's decision (prod untouched).

## Deploy status
- **dev (csjones.co/fynla):** has everything — `dev` at `03b851f`, deployed (git pull + cache clear), both fixes live-verified.
- **prod (fynla.org):** **UNTOUCHED.** ⚠️ Open question: for #593 CSJ was testing "the web page before auth" — if he was on **prod**, the spouse-PA fix isn't there yet (it's csjones-only until a prod release). Confirm next session.

## Tech debt found this session
- None new. Changes were small + tested: SaveTaxEstimate suite **17 passed**, onboarding suite **97 passed**, synthesis test **4 passed**. Pint clean.

## Known issues / blockers
- **Historical transcripts keep the OLD synthesis message.** e.g. test user "Eve" (`isae2e4@example.com`) still shows the old numbered "1. Reclaim PA… 2. Hold GIA…" message — that's a persisted chat row; we don't rewrite history. **New** generations use the new bullet format (verified). Not a bug; flag if CSJ expects retro-update.

## Rules reinforced this session (the painful ones)
- **Never report "done" until it is deployed AND browser-verified live.** Saying "change made/tested" while it sat on an unmerged branch was the core frustration — CSJ tested the live page and the old text was still there. "Done" = on the env being tested + seen working.
- **Check git, PRs, memory, CLAUDE.md, handovers, spec BEFORE acting — don't guess or wander.** Wasted turns spelunking the backend/state-machine when the target was a specific `/m` Fyn message; CSJ had to redirect repeatedly. Ground in the authoritative sources first.
- **The savetax synthesis recap MUST mirror the `/tax-strategy` dashboard** (`composed_plan.items`) — chat and page cannot disagree. (Candidate memory.)
- **Playwright IS available for live `/m` walks on csjones** — no excuses about not verifying. Auth `/m` via `localStorage['m_scaffold_token']` + a tinker Sanctum token; drive the funnel for guest pages.

## Next session should
1. **Confirm where CSJ tested #593** (csjones vs prod). If prod, the spouse-PA fix + the rest of #581–#593 need a prod release.
2. **Decide the `dev → main` release** (#581–#593, all verified on csjones). If shipping: build prod assets, upload `public/build` + `public/m-build` + changed PHP + corpus, `migrate --force` (none new) + cache clears, monitor logs.
3. **Clean up worktrees** — 7 feature worktrees back now-merged PRs and can be removed (keep `fynla-coala`): `fynla-recap` (fix-spouse-pa-note, has a 109M vendor copy), `fynla-isa`, `fynla-isafix`, `fynla-pension`, `fynla-gamif`, `fynla-cf`, `fynla-spouse`. Verify each is clean before `git worktree remove`.

## Context hints
- Active branch type: mainline (main dir on `main`; all feature work via worktrees off `dev`).
- Behind origin/main by: 0. Ahead by: 0 (in sync).
- Uncommitted: none, working tree clean.
- Last commit (main): `f7af670` docs(session): context-clear handover session-3.
- dev tip: `03b851f` (Merge PR #593). csjones: on `dev` at `03b851f`.
