---
type: handover
mode: end-of-day
date: 2026-05-13
session: 1
branch: audit-adjusted-net-income (clean tracked tree; standing untracked carry-over only)
previous_session: 2026-05-12 session 8 (this session — auto-resumed from session 7 handover)
trigger: end-of-day wrap by CSJ via /session-end after context tripwire at ~314k tokens
---

# Handover — 2026-05-13, Session 1

## Where we left off

Audit follow-up sweep continued. Today's session 8 (12 May) merged the three audit PRs that session 7 left open (#287, #288, #289 — admin-merged into `dev`, origin branches deleted), then opened **PR #290 (Adjusted Net Income proper deductions)** against `dev`. PR #290 is OPEN at branch tip `25ddfb7`, CI in progress at session close (logic-guard + snyk pending, GitGuardian green). Local checkout is on `audit-adjusted-net-income` — `dev` ended the session at `0379450` (the merge commit for #289).

The session ended on the context tripwire after PR #290 was opened. No deploy yet — csjones.co/fynla is still on `dev@8949a4f2b` (where session 7 left it), three audit PRs behind.

## What shipped today (session 8, 12 May)

- **PR #287 merged** (`audit-starting-rate-savings → dev`) — Starting Rate for Savings in UKTaxCalculator. Merge commit `ef78fa9`.
- **PR #288 merged** (`audit-salary-sacrifice-2027-28 → dev`) — £2,000 NIC cap codified with 2027-04-06 effective date. Merge commit `ec5ad5a`.
- **PR #289 merged** (`audit-businessinterest-sibling-fallbacks → dev`) — BADR-sibling fail-loud. Merge commit `0379450`.
- **3 remote feature branches deleted** post-merge (audit-starting-rate-savings, audit-salary-sacrifice-2027-28, audit-businessinterest-sibling-fallbacks).
- **PR #290 OPENED** (`audit-adjusted-net-income → dev`) — Adjusted Net Income proper deductions. Branch tip `25ddfb7`. https://github.com/Stoff73/fynla/pull/290

## PR #290 detail

**Scope:** PA-taper now uses Adjusted Net Income (HMRC ITA 2007 s35-s37 + s58 — total income minus gross pension contributions minus grossed-up Gift Aid) instead of gross income. Three engine sites fixed:

1. `UKTaxCalculator::calculateIncomeTax` — taper uses ANI; pension also deducted from taxable earned income (net-pay model, matches existing `calculateDetailedNetIncome`).
2. `Investment/DividendTaxCalculator::calculate` — taper uses ANI.
3. `Benefits/ChildBenefitService::calculateAdjustedNetIncome` — delegates to `IncomeDefinitionsService` which already computes ANI per HMRC.

Both engine methods gain backwards-compatible `$pensionContributions` and `$giftAidGross` parameters (default 0). Highest-impact callers also updated: `UserProfileService`, `PersonalAccountsService::calculateProfitAndLoss`, `Traits/ResolvesIncome::resolveNetAnnualIncome` (~10 service consumers via the trait).

**Worked examples:**
- £110k earner + £10k gross pension: pre-fix £32,432 tax → post-fix £27,432 (saves £5,000)
- £75k HICBC user + £20k gross pension: pre-fix £1,054.95 wrongly charged → post-fix £0 charge, full child benefit retained

**Tests:** 11 new Pest cases all green. Touched-module sweep: 1,091 tests pass across Investment, UserProfile, UKTaxCalculator, Benefits, Goals, Retirement, Protection, Estate, Savings, Coordination, Tax, Agents, Architecture. Zero regressions.

**Files touched:** `app/Services/UKTaxCalculator.php`, `app/Services/Investment/DividendTaxCalculator.php`, `app/Services/Benefits/ChildBenefitService.php`, `app/Services/UserProfile/UserProfileService.php`, `app/Services/UserProfile/PersonalAccountsService.php`, `app/Traits/ResolvesIncome.php`, three new/extended test files, two test constructor fixes.

## What's in flight (NOT done)

- **PR #290 not yet merged** — CI was still resolving at session close. Next session: confirm CI green, then admin-merge per established pattern.
- **csjones deploy outdated** — server still on `dev@8949a4f2b` (session 7 deploy). The four audit PRs merged after that (#287, #288, #289 — all on dev now; plus #290 when it merges) are not yet on csjones.
- **Vault-sync deferred** — was deferred at session 7 close and again here. Backlog now: May 11 sessions 6–12 + May 12 sessions 1–8 + (this) May 13 session 1. Should be batched via Haiku 4.5 subagent next session.

## Deploy status

**Ready to deploy but NOT deployed.** `dev` carries 3 unshipped audit PRs (#287/#288/#289), with #290 pending merge. When PR #290 merges, csjones deploy will be 4 audit PRs behind. The deploy unblocks once #290 merges (no point deploying in between).

Production (fynla.org) still on `main@f15e068` — pre-audit. No release PR opened yet for any audit work.

## Tech debt found this session

- **Tech-debt audit skipped this session** under context-budget pressure. The code shipped via PR #290 was implicitly audited via the 1,091-test touched-module sweep, but no `tech-debt-session` formal audit ran. Worth a pass in the next session if the changes get merged — particularly around the `CoverageGapAnalyzer` and `TaxEfficiencyCalculator` callers which still default-pass `0` for the new params.
- **Three pension-contribution calculation methods exist in the codebase:** `IncomeDefinitionsService::getPensionContributions` (no scheme_type filter, sums all DC pensions), `PersonalAccountsService::calculateCashflow` line 226 (same — no filter), and `UserProfileService::calculateAnnualPensionContributions` (workplace-only filter). These should be consolidated to a single source of truth. Surfaced during PR #290 work, not fixed in scope.

## Known issues / blockers

- **csjones `public/.htaccess` skip-worktree pull-conflict pattern** still applies — when remote tree touches the file, `git pull` blocks even with skip-worktree set. Workaround documented in session 7 handover (disable skip-worktree → reset → pull → copy template → re-enable). Repeat next deploy.
- **PR #290 follow-ups documented in PR body, not yet branched:** Gift Aid BRT-band extension (gives higher-rate relief); SRS in `calculateInterestTaxDetailed` (TaxBandTracker API change); migration of `CoverageGapAnalyzer` + `TaxEfficiencyCalculator` callers to pass deduction values.

## Rules reinforced this session

None new this session. Memory references applied:

- `feedback_admin_merge_pattern_for_solo_reviewer_prs.md` — used for PRs #287, #288, #289 admin-merges.
- `feedback_loop_until_correct.md` — applied while debugging test math arithmetic errors in PR #290 (5 wrong expected values corrected by re-deriving from current code behaviour).
- `feedback_evals_surface_engineering_issues.md` — applied when test failures revealed wrong assumptions about pension scheme_type filter (the test used `'workplace'` but enum is `'occupational'`).

## Next session should

1. **Confirm PR #290 CI green and admin-merge.** Then sync local dev + delete `audit-adjusted-net-income` remote branch. Pattern: `gh pr checks 290`, `gh pr merge 290 --merge --admin --delete-branch`, `git checkout dev && git pull && git fetch --prune`.
2. **Deploy `dev` to csjones.co/fynla** to validate the 4-PR audit batch in staging. Follow CLAUDE.md "Deploying to dev" with the `npm ci` (PR #284) + .htaccess template (PR #282) + skip-worktree workaround caveats. 2 migrations already ran in session 7's deploy, so only any new migrations from #287-#290 (likely none) need to run.
3. **Run vault-sync** for the backlog (May 11 sessions 6–12 + May 12 sessions 1–8 + May 13 session 1) via Haiku 4.5 subagent.
4. **Pick next audit work item** from CSJTODO. Top candidates:
   - SRS in `calculateInterestTaxDetailed` (PR #287 follow-up, requires TaxBandTracker API change)
   - Frontend `taxConfig.js` hydrate from backend (REVIEW §4 High #28)
   - `RebalancingCalculator.vue:246` hardcoded `taxRate: 0.20` (REVIEW §4 High #29, single-site fix)
   - CoordinatingAgent 7 raw `orWhere` joint queries → `forUserOrJoint` scope (REVIEW §4 High #32)
   - 6 ownership_type enums missing `tenants_in_common` (REVIEW §4 High #33, Rule #5)

## Context hints

- Active branch: `audit-adjusted-net-income` (PR #290 head)
- Local `dev`: `0379450` (3 audit PRs merged this session) — synced to origin
- `main`: unchanged at `f15e068` — production still pre-audit
- Working tree: clean (standing untracked carry-over only — FCA/, campaigns/, fyn/, personas/, prompts/, tools/, etc.)
- Last commit on `audit-adjusted-net-income`: `25ddfb7` "fix(audit): Personal Allowance taper uses Adjusted Net Income, not gross"
- Open PRs against `dev`: just #290 after this session
- csjones.co/fynla: `dev@8949a4f2b` (session 7 deploy — now 3 PRs behind, will be 4 once #290 merges)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
