---
type: handover
mode: end-of-day
date: 2026-07-24
session: 1
branch: codex/savetax-e2e-capture-fixes
previous_session: 2026-07-23 session 1 (clear)
---

# Handover — 2026-07-24, Session 1

## Where we left off
Ran CSJ's mandated test loop on the SaveTax campaign: full `/m` journey as a fresh user, log every deviation, fix, redeploy, retest. The web journey is now GREEN end-to-end (user 293, `priya-e2e-0723b@example.com`, fully onboarded through to the Tax Strategy page). The iOS pull-through leg could NOT run — CoreSimulator wedged host-wide (frozen sim clock, hung launches on two devices, service restart didn't help); the Mac likely needs a reboot.

## What shipped today
- fix(onboarding): joint accounts save mid-campaign and capture recovery cannot deadlock
- fix(mobile): fresh campaign registrants count as mid-walk before the first turn stamps the step
- fix(savings): SavingsStore accepts a joint account with no linked co-owner — one reciprocal-spouse rule
- fix(fyn): a dedupe skip is never a failed write — one predicate, every consumer
- fix(onboarding): a completion declaration closes a zero-output capture turn
- fix(onboarding): ownership evidence survives the intervening detail sentence the prompt itself asks for
- fix(onboarding): import TaxConfigService — the spouse-advice fallback fatally resolved a namespace-relative class
- fix(pensions): salary_sacrifice=false survives PensionNormaliser
- docs: SaveTax E2E run report + issue log
All on `codex/savetax-e2e-capture-fixes`, pushed; **PR #670 open to dev**; every fix live-verified on csjones.

## What's in flight (NOT done)
- **iOS LiveJourneyTests / native pull-through verification** — blocked on the wedged simulator. Fully staged: xctestrun built, code-relay script ready, Priya-b's account populated. See memory `project_ios_sim_wedged_2026-07-23.md` and the run report's "iOS re-run" section. **Reboot the Mac first**, then verify `simctl launch <udid> com.apple.Preferences` returns in seconds before starting.
- **Round-3 clean pass** of the /m journey (fresh persona, expect zero blockers) — the loop's final confirmation, worth doing after #670 merges.
- Open defects from the loop that need CSJ decisions or an /m rebuild — see below.

## Deploy status
Deployed to dev-staging csjones — but **csjones is on the FIX BRANCH `codex/savetax-e2e-capture-fixes` (32473ca), not dev**. After merging #670: checkout dev + pull + cache:clear on csjones. Notes: `July/July24Updates/deploy-2026-07-24.md`. Backend-only; no rebuild, no migrations.

## Tech debt found this session
- `CaptureAccuracyGate.php` at 792 lines; `evidenceForEntity` now carries 4 interacting walk rules — extract an `EvidenceWalk` object next touch (`tech-debt-report.md` W1).
- Completion/negative declarations still round-trip the LLM (~£0.04 + refusal risk) before the zero-output guard rescues — deterministic pre-LLM short-circuit is the cheap win (W2).
- Two entity-noun regex alternations in the gate should derive from one `ENTITY_NOUNS` constant (S4).

## Known issues / blockers
- **[HIGH — tax modelling]** PSA attributes the FULL joint-account interest (£504) to the primary owner instead of the HMRC 50/50 split; spouse's savings allowance ignores the known share. Net worth DOES share-adjust — cross-surface inconsistency. Needs `CalculatesOwnershipShare` in the tax-strategy interest calc + tax-compliance-reviewer pass.
- `/m` savings rows show "Unknown" (institution default) instead of account_name — fix specified at `resources/mobile/views/modules/Savings.vue:41,67,155`, needs /m rebuild.
- CSJ decision items: 2026-07-13 joint hardening relaxation (flagged in PR #670), total-cash share convention, ISA-ownership question copy, fabricated "Yes, that's right" bubble, stale-token registration edge.
- Full list: `July/July23Updates/savetax-e2e-issue-log.md` (22 numbered items).

## Rules reinforced this session
- **Follow the test-loop brief literally**: stop at the FIRST error — no "recovery probing" past a failure; log → fix → redeploy → retest. CSJ was explicit and angry when I deviated. (Conduct rule; no new memory file — it is the existing loop/discipline laws applied.)
- Simulator wedge state saved: `project_ios_sim_wedged_2026-07-23.md`.

## Next session should
1. Run `session-start`. If the Mac was rebooted, do the iOS leg first: verify sim liveness, then the exact commands in `July/July23Updates/savetax-e2e-run-report.md` §"iOS re-run" (user `priya-e2e-0723b@example.com`, expected amounts listed there).
2. CSJ reviews/merges PR #670, then on csjones: `git checkout dev && git pull origin dev && php artisan cache:clear`.
3. Round-3 clean /m pass with a fresh persona (savetax funnel, same answers) — expect zero blockers; any deviation goes through the loop again.
4. Start the PSA joint-interest share fix (tax-compliance-relevant) — trace where savings interest enters the tax-strategy allowance usage calc.
5. Decide the CSJ decision items above.

## Context hints
- Active branch type: mainline fix branch (backend only)
- Ahead of origin/dev by: 9 commits (all pushed)
- Uncommitted: none, working tree clean
- Last commit: `32473ca` docs: SaveTax E2E run report + issue log (2026-07-23 test loop)
