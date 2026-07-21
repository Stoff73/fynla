---
type: handover
mode: context-clear
date: 2026-05-12
session: 7
branch: dev (clean tracked tree; standing untracked carry-over only) — work landed on 3 feature branches (audit-starting-rate-savings, audit-salary-sacrifice-2027-28, audit-businessinterest-sibling-fallbacks)
trigger: context tripwire at ~288k tokens (>97.5% of 200k Fynla budget)
previous_session: 2026-05-12 session 6 (Wave 1/2 batch shipped as PRs #283–#286)
---

# Context Clear Handover — 2026-05-12, Session 7

## Immediate state

Three new audit follow-up PRs opened against `dev` this session (Phase B priority order from the REVIEW.md audit map):

- **PR #287** — Starting Rate for Savings applied in UKTaxCalculator (Phase B #4)
- **PR #288** — Salary sacrifice £2,000 NIC cap codified with 2027-04-06 effective date (Tier 1 #4)
- **PR #289** — BADR-sibling CGT fail-loud on missing higher_rate/basic_rate

All three pushed; all CI-clean at branch tip; all on top of merged-dev state from session 6. Local `dev` checkout is at `8949a4f2b` (unchanged since session-6 close — none of #287/#288/#289 merged yet).

The six audit PRs from sessions 5–6 (#281, #282, #283, #284, #285, #286) ALL merged into `dev` earlier this session via the established `gh pr merge <N> --merge --admin` pattern. **Dev → csjones.co/fynla deploy was completed this session** — see "Deploy status" below for what's live.

## The thread

1. Auto-resumed from session 6 handover. CSJ's natural-language ask was "merge the audit PRs". Merged #281 through #286 in suggested order (independent PRs, sequence chosen for risk gradient).
2. **One conflict on #281** — `CSJTODO.md` between `audit-criticals`' session-5 log and dev's session-6 log. Resolved by taking dev's version (strict superset). Merge commit `a73d008`.
3. All six remote feature branches (audit-criticals, audit-quickwins, audit-bugreport-hardening, audit-npm-deps, audit-consent-sse-cache, audit-wave2) deleted on origin after merge.
4. **Deployed `dev` → csjones.co/fynla** end-to-end:
   - `npm ci` locally (required so PR #284's `serialize-javascript ^6.0.2` override resolves; ~8 vulnerabilities remain as deferrals per `docs/security/npm-audit-deferrals.md`)
   - `./deploy/csjones-fynla/build.sh` produced fresh `public/build/` (8.9M, manifest at 16:02 BST)
   - `scp` upload via preserve-old-chunks pattern (rm `build.old`, mv `build` → `build.old`, scp new tree, `cp -rn build.old/. build/`)
   - `git pull origin dev` on csjones server. **Hit conflict on `public/.htaccess` even with skip-worktree set** — git pull blocks the merge regardless when remote tree touches the file. Disabled skip-worktree, reset, pulled, re-enabled skip-worktree, then `cp deploy/csjones-fynla/.htaccess public/.htaccess` to apply PR #282's XFO/XSS header changes.
   - 2 migrations ran: `convert_users_expenditure_columns_to_decimal` + `add_audit_logs_event_type_created_idx`. Both clean.
   - Standard cache:clear + config:clear + view:clear + route:clear + composer dump-autoload -o + artisan optimize
   - Smoke test: HTTP 200 on `/`, `/login`, `/build/manifest.json`. Response headers verified `x-frame-options: DENY` (PR #282 canonical value live) + Revolut sandbox URLs in CSP (PR #284 sandbox config holding).
   - Browser login smoke with chris@fynla.org → MFA `715919` (fetched via SSH tinker) → `/fynla/dashboard`. Net Worth £598,250 canonical. Zero console errors.
5. CSJ asked what's outstanding from the review. Mapped the 6-PR-merged state against REVIEW.md and identified Phase A 8/8 done; Phase B ~60% done. Surfaced the top remaining Phase B / High-severity items in priority order.
6. CSJ asked for "the next 3 in priority order". Shipped:
   - **PR #287 — Starting Rate for Savings.** `UKTaxCalculator::calculateIncomeTax` did not apply the £5k SRS band (HMRC ITA 2007 s12). Fix matches HMRC ordering: non-savings consumes PA → SRS 0% (tapered £1-for-£1 by non-savings above PA) → PSA 0% → bands. SRS-consumed amount added to band-position cursor so taxable remainder stacks correctly above 0%-rated portions. SRS value from `income_tax.starting_rate_for_savings.band` (already seeded). 5 Pest cases pin the boundaries. 170 Tax-module tests green.
   - **PR #288 — Salary sacrifice 2027/28 codification.** Seeder's `nic_exemption_cap_effective_date` corrected from `'2029-04-06'` to `'2027-04-06'` (CSJ-confirmed Budget date). `RetirementStrategyService::calculateNetCostOfContribution` now reads cap + effective date from TaxConfigService and date-gates: pre-2027 = no cap (zero-cost full sacrifice), post-2027 = £2,000 cap. **Behaviour change:** users today contributing > £2k/year via salary sacrifice will see £0 net cost (was: over-stated). Will flip at 2027-04-06 to the £2k-cap behaviour automatically. 4 Pest cases pin both sides via `Carbon::setTestNow`. 67 Retirement-module tests green.
   - **PR #289 — BADR-sibling fallbacks.** `BusinessInterestService` had `higher_rate ?? 0.20` / `basic_rate ?? 0.10` (pre-30-October-2024 statutory CGT rates) as siblings to the BADR `?? 0.10` fixed in Wave 2.5. Same defect shape. Replaced with fail-loud `FinancialCalculationException::taxConfigError`. 2 new Pest cases. 4 Business-module tests green.

## Files touched this session

**Modified / created (per branch):**

`audit-starting-rate-savings` (#287, tip `6fa4bf7`):
- `app/Services/UKTaxCalculator.php` — SRS applied in `calculateIncomeTax`
- `tests/Unit/Services/UKTaxCalculatorStartingRateSavingsTest.php` (new, 5 cases)

`audit-salary-sacrifice-2027-28` (#288, tip `55708e9`):
- `database/seeders/TaxConfigurationSeeder.php` — effective date corrected
- `app/Services/Retirement/RetirementStrategyService.php` — `calculateNetCostOfContribution` date-gated
- `tests/Unit/Services/Retirement/SalarySacrificeNicCapTest.php` (new, 4 cases)

`audit-businessinterest-sibling-fallbacks` (#289, tip `c5c2165`):
- `app/Services/Business/BusinessInterestService.php` — fail-loud on missing CGT rates
- `tests/Unit/Services/Business/BusinessInterestBADRTest.php` — 2 new sibling cases

**On `dev` (from the 6 merge commits):** All sessions 5/6 audit work — Tier 1-3 criticals, security headers, admin promo removal, constant-time secrets, eval guard, router gating, DashboardAggregator isolation, npm audit fix + deferrals, bounded-TTL consent recheck, purple→violet bulk, RISK_TAILWIND_CLASSES, arch tests Rules #5+#9, IHT save-on-read, BADR fail-loud, TaxDragCalculator yields-from-config + joint scope, BugReportController hardening + HttpResponseException handler fix.

## What the next Claude needs to know

- **Three open PRs (#287, #288, #289) — none merged.** Independent. Suggested order is the priority order they were written (greater user-impact first). All authored solo; admin-merge pattern applies per `feedback_admin_merge_pattern_for_solo_reviewer_prs.md`.
- **PR #288 changes current behaviour.** Users contributing > £2k/year via salary sacrifice will see lower net cost immediately after merge (pre-2027 = no cap). At 2027-04-06 the cap reactivates automatically — no code change needed at the boundary. Worth flagging in any release-note copy.
- **PR #287 only covers the primary tax engine.** `calculateInterestTaxDetailed` (the TaxBandTracker-routed detailed flow) is NOT modified — would require a tracker API surface change and is flagged as follow-up. Any caller using the detailed flow still over-taxes savings income.
- **csjones is now on `dev@8949a4f2b`.** Production unchanged (still on `main@f15e068`). If/when CSJ wants to ship the session 6 audit batch to prod, open release PR `dev → main`, build via `./deploy/fynla-org/build.sh`, run `npm ci` first (override caveat applies to prod too), and copy `deploy/fynla-org/.htaccess` to `public/.htaccess` after pull (PR #282 changed it).
- **csjones `public/.htaccess` skip-worktree didn't prevent the pull conflict** — even with `git ls-files -v` showing `S`, git pull blocked because the incoming change touched the file. Workaround: temporarily disable skip-worktree → reset → pull → copy template → re-enable. Adding to known-issue patterns.
- **Vault-sync deferred.** Carry forward to next eod wrap. Batch via Haiku 4.5 subagent. Backlog now: May 11 sessions 6–12 + May 12 sessions 1–7.

## Branch / deploy state

- `audit-starting-rate-savings` at `6fa4bf7` (PR #287 OPEN) — pushed
- `audit-salary-sacrifice-2027-28` at `55708e9` (PR #288 OPEN) — pushed
- `audit-businessinterest-sibling-fallbacks` at `c5c2165` (PR #289 OPEN) — pushed
- `dev` at `8949a4f` (carries all 6 merged audit PRs from sessions 5/6)
- `main` unchanged (`f15e068`) — production still pre-audit
- **csjones.co/fynla updated to `dev@8949a4f2b`** + .htaccess template applied (XFO/XSS headers per PR #282). 2 migrations ran. Smoke tested chris@fynla.org login → dashboard.
- fynla.org unchanged

## PRs opened this session

- **#287** — Starting Rate for Savings — https://github.com/Stoff73/fynla/pull/287
- **#288** — Salary sacrifice 2027/28 codification — https://github.com/Stoff73/fynla/pull/288
- **#289** — BADR-sibling fail-loud — https://github.com/Stoff73/fynla/pull/289

## PRs merged this session

- #281 audit-criticals (Tier 1-3 critical findings)
- #282 audit-quickwins (Wave 1 security headers + 9 other quickwins)
- #283 audit-bugreport-hardening (+ app-wide HttpResponseException handler fix)
- #284 audit-npm-deps (+ deferral docs)
- #285 audit-consent-sse-cache (W1-L bounded-TTL recheck)
- #286 audit-wave2 (6 commits)

## Tests added this session

11 new Pest cases across the 3 new PRs:
- `tests/Unit/Services/UKTaxCalculatorStartingRateSavingsTest.php` — 5 cases (SRS boundaries: full apply, taper, full elimination, higher-rate no-apply, no-over-credit)
- `tests/Unit/Services/Retirement/SalarySacrificeNicCapTest.php` — 4 cases (Carbon::setTestNow: pre-2027 zero-cost, 2027-04-06 cap activates, 2028 cap persists, within-cap zero)
- `tests/Unit/Services/Business/BusinessInterestBADRTest.php` — +2 cases (sibling fail-loud for higher_rate + basic_rate)

Touched-module sweeps green: 170 Tax-module, 67 Retirement-module, 4 Business-module. Zero regressions.

## Pick up from here

The expected next action depends on CSJ's intent:

**If merging PRs:** ship #287 → #288 → #289 in that order (independent; the order is just risk-gradient). Each `gh pr checks <N>` to confirm CI green, then `gh pr merge <N> --merge --admin`. After merges, sync local dev (`git pull`) and delete the three remote feature branches.

**If continuing audit work:** the next natural follow-up by impact is **Adjusted Net Income proper deductions** (REVIEW §4 High #35) — currently computed as gross, affects PA-taper accuracy for high earners. Branch `audit-adjusted-net-income` off latest dev. Pair with a follow-up to fix `calculateInterestTaxDetailed` (SRS in the detailed flow — out of scope for #287).

**If deploying to csjones again:** wait until #287/#288/#289 merge — there's no need to re-deploy in between.

**If shipping the session 6 batch to production (fynla.org):** open release PR `dev → main`. Body should call out PR #284's `npm ci` requirement and PR #282's `.htaccess` template change. Production deploy steps in CLAUDE.md "Deploying to production". The 2 dev migrations need to run on prod (`convert_users_expenditure_columns_to_decimal`, `add_audit_logs_event_type_created_idx`).

## What the next session should NOT do

- Do NOT run `npm audit fix --force` after pulling — the override in PR #284's package.json must hold. Use `npm ci`.
- Do NOT touch the `calculateInterestTaxDetailed` SRS in this session — it's a follow-up requiring TaxBandTracker API change. Document but don't conflate.
- Do NOT re-merge #281–#286 or re-deploy csjones — already done this session.
- Do NOT delete the audit-starting-rate-savings / audit-salary-sacrifice-2027-28 / audit-businessinterest-sibling-fallbacks branches before their PRs merge.
- Do NOT touch the existing strict-termination test (`ConsentRuntimeCheckTest:166`) without setting `interval = 0` first (S0.9 guard).
- Do NOT strip pre-existing emoji/Unicode/icon violations during audits or PRs (Rule #16 forward-only, grandfathered).

## Vault sync

**Deferred** this session due to tripwire pressure. Carry forward to next eod wrap alongside May 11 sessions 6–12 and May 12 sessions 1–7 backlog.

## Memory files touched this session

- No new memory files written. All discoveries documented in PR bodies (#287/#288/#289) and this handover.
- Memory references applied:
  - `project_salary_sacrifice_2k_upcoming_law.md` — drove PR #288 implementation; effective date locked at 2027-04-06 as previously confirmed.
  - `feedback_admin_merge_pattern_for_solo_reviewer_prs.md` — used for all 6 PR admin-merges this session.
  - `feedback_csjones_deploy_via_git_pull.md` — applied for csjones deploy; surfaced new edge case (skip-worktree doesn't stop pull conflict on touched files).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
