---
type: handover
mode: end-of-day
date: 2026-07-25
session: 1
branch: codex/psa-joint-interest-share (merged; dev lives in the fynla-fixes worktree)
previous_session: 2026-07-24 session 1
---

# Handover — 2026-07-25, Session 1

## Where we left off
Big clearing day: everything merged to dev and verified. The morning started on the PSA joint-interest fix; CSJ interrupted reporting the verify loop "gone" on iOS//m — root-caused to the loop having been campaign-only since birth (journey path never had it), CSJ approved extending it, and it shipped the same day with three live-loop capture fixes found and fixed along the way. Evening: CSJ directed "merge all to dev + complete handover tasks" — done, including the round-3 clean pass.

## What shipped today (all merged to dev, deployed + verified on csjones)
- **#670** — yesterday's 8 SaveTax E2E loop fixes (joint capture, ownership deadlock, dedupe blends, spouse-advice fatal).
- **#671 — journey verify loop.** Every data entry on the journey/focus path now runs the announce → Okay → navigate → Continue/Edit → edit-until-happy loop, through the SAME campaign verify machinery (one mechanism). New protection/estate/goals sections in the one `campaignVerifyConfig` map + verify-edit scope/snapshot/tool maps + /m `ONBOARDING_NAV_ROUTES`. Plus three live-loop fixes, each red-first tested: (1) failed richer write suppresses the blind gap-fill create (both paths) + /m `entity_created` no longer clobbers prose bubbles; (2) "not an ISA" is never ISA evidence (one negation-aware vocabulary); (3) the gate adopts owner/share evidence the model dropped from its call (`repaired` channel) + the latest clarification answer always binds across accumulated turns.
- **#672 — PSA joint-interest share attribution** (issue log #21 HIGH). `estimateAnnualInterest` share-aware via `CalculatesOwnershipShare`; new `estimateSpouseJointInterest` feeds both spouse grids with HMRC stacking (PA → Starting Rate → PSA); IsaTopUp share-capped; sole-name filters ownership-type-aware. Tax-compliance reviewer verified the core fix HMRC-correct.
- **iOS (pkg7 branch `37cd69e`)** — native Fyn navigation allowlist gains /protection, /estate, /goals + reducer test. NOT test-run (sim wedged).
- ~1,400 tests green across the affected families through the day; two full live E2E walks (below).

## Live verification (both on csjones, Playwright, click/fill/submit)
- **Journey path** (user 294, `journey-verify-0724@example.com`): register → journey → income verify **including the Edit arm** (£125k → "actually £120,000" → applied + re-shown) → expenditure verify → protection module (Aviva £250k/£30) → savings module (Marcus £8k @ 4.3%) → I'm done → completed. All DB rows exact.
- **Round-3 clean campaign pass** (user 295, `round3-e2e-0724@example.com`): funnel → plan page (maths verified) → register → full section walk → synthesis → tax strategy. **Zero blockers.** The former joint-capture blocker saved first-time. Tax-strategy page confirms the PSA fix live: user grid "£252 used / £248 available of £500"; spouse grid "£252 used / £748 available of £1,000".

## What's in flight (NOT done)
- **iOS leg** — still blocked on the Mac reboot (CoreSimulator wedged host-wide). After reboot: (1) staged LiveJourneyTests per `July/July23Updates/savetax-e2e-run-report.md` §iOS re-run (user `priya-e2e-0723b`), AND (2) `FynEventReducerTests` for today's pkg7 allowlist commit `37cd69e`.
- **PSA follow-up ledger** — the tax-compliance review returned 15 findings (full text in PR #672's session transcript; summary below). None invalidate the merged fix. Priority trio: (1) spouse band/PA-taper determination excludes the newly-attributed joint interest (Medium); (2) the `known` flag now presents a lower-bound usage as confirmed — semantics call for CSJ (Medium); (3) IsaTopUp's joint-account shelter model ~2× optimistic — moving your half of a joint account only removes half your attributed interest (Medium). Notable pre-existing: user-grid PA→SR→PSA stacking absent (the spouse grids now do it — Rule 20 inconsistency); captured `annual_interest_income` has no share semantics (household can exceed 100%); rate heuristic misreads percent rates ≤ 1 (0.5% → 50%); band boundary `>=` at exactly £50,270; non-spouse joint accounts land their complement on the spouse grid; Form 17 semantics unmodelled; trust-held accounts pass sole-name filters.
- **CSJ decision items** (carried): 2026-07-13 joint-hardening relaxation (flagged in #670, now merged — confirm or revert); total-cash share convention (savings page sums full joint balance — seen again in round 3); ISA-ownership question copy; fabricated "Yes, that's right" bubble on verify Continue (cosmetic; the /m transcript reload also merges two bubbles into one paragraph); stale-token registration edge; spouse `known`-flag semantics (new, from the PSA review).
- **Adjacent findings from today's loop (not fixed, by scope discipline):** "owned by me alone" missing from the ownership vocabulary; walk-path gap-fill still creates extractor-quality (degraded) rows when the model emits nothing at all (true-refusal case — the suppression only covers failed attempts); free-tier savings cap fires mid-campaign walk when ISA + joint account fill both slots (designed, but CSJ may want campaign-walk exemption).

## Deploy status
Deployed to dev (csjones.co/fynla) — notes at `July/July25Updates/deploy-2026-07-24.md`. csjones on dev `c1025ab` with current /m bundle. **Prod untouched.**

## Tech debt found this session
`tech-debt-report.md` (repo root, updated): 0 critical, 5 warnings, 4 suggestions. Headlines: CaptureAccuracyGate at 854 lines with FIVE interacting evidence-walk rules — the EvidenceWalk extraction (W1) is overdue and should precede the next touch; rate-normalisation helper + 4 inline copies (Rule 20-class); verify-edit section→model maps duplicated between scope and snapshot; `estimateAnnualInterest` unmemoised against the 50ms budget; the pre-LLM completion-declaration short-circuit (W2) still open.

## Known issues / blockers
- CoreSimulator still wedged (boot time Jul 20) — **reboot the Mac** before any iOS work.
- Nothing else red: both E2E walks green, all targeted suites green, csjones healthy.

## Rules reinforced this session
- The verify loop applies to EVERY data entry, all paths, all surfaces (CSJ directive 2026-07-24, approved plan) — now in code; treat as settled.
- Rule 20 keeps proving itself: today's root causes were per-path copies (campaign-only verify wiring; inline-only clarification-input collection; substring-vs-negation-aware ISA checks). Consolidation was part of every fix.

## Next session should
1. Run `session-start`. If the Mac was rebooted: iOS leg first — sim liveness check, then the staged LiveJourneyTests (run-report §iOS re-run) AND `FynEventReducerTests` on pkg7.
2. Ask CSJ to walk the decision list (above) — several now have fresh evidence attached.
3. If CSJ wants the PSA follow-ups: start with the priority trio, then the user-grid stacking (finding 3) which mirrors the spouse-grid code just written.
4. Next CaptureAccuracyGate touch does the EvidenceWalk extraction FIRST (tech-debt W1).
5. Prod release when CSJ calls it: #670+#671+#672 ride dev→main together; remember the prod-path /m rebuild.

## Context hints
- Active branch type: mainline fix branches, all merged; main dir resting on `codex/psa-joint-interest-share` (= dev tip content for the tax files; dev itself is checked out in the `fynla-fixes` worktree)
- dev tip: `c1025ab` (merge of #672); prod (`main`) unchanged today
- Uncommitted: none, working tree clean
- Last work commit: `643ff56` fix(tax): joint-account interest attributes by ownership share, both grids
