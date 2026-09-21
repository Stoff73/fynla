---
type: handover
mode: session-end
date: 2026-09-21
session: 1
repo: fynla
branch: feature/threshold-position
---

# Session Handover — 2026-09-21, Session 1

## Where things stand

The threshold-position feature is built, walked live on csjones (web and `/m`, two personas, 49 items by interaction) and open as PR #919 into `dev`, not merged: https://github.com/Stoff73/fynla/pull/919. Branch tip e0204fe2f, all commits pushed; csjones runs this branch at that tip. The final whole-branch review found three Important defects, all fixed and re-reviewed; my own runs after the fixes: 68 Pest tests and 6 Vitest tests green. The native card exists with unit tests but has never been seen on screen, because both iOS schemes read fynla.org.

CSJ's instruction at session end: **the adjacent defects surfaced by this work are the priority next session.** They are ranked below, worst first.

## Priorities for the next session

1. **BLOCKED ON CSJ — merge or hold PR #919, and after merge switch csjones back to dev** (`git checkout dev && git pull origin dev` on the server) and delete the SDD workspace `.superpowers/sdd/2026-09-21-threshold-position/`. The release also needs the migration, both seeders with `--force`, `route:clear`, config cache only, and both bundles rebuilt; the PR body carries the full operator notes.
2. **Gift Aid: two defects, one silent and historic.** (a) `GiftAidHigherRateReliefStrategy.php:37` values relief off `annual_charitable_donations` alone and never reads `is_gift_aid`, while `IncomeDefinitionsService::calculateGiftAidGrossUp` does; a donor without Gift Aid is told to reclaim relief they cannot have. (b) Every user who ticked Gift Aid before fd60af65d had the flag dropped by `PUT /api/user/profile/personal`; the save is fixed, the lost preferences are not. Decide whether to reach out or re-ask on the expenditure form.
3. **`GET /api/investment` omits `units_unvested`**, so `EmployeeShareSchemeDetail.vue:139,189` reads "Fully vested, 0 unvested" for an RSU with 800 unvested units, contradicting the strip on the same record. No API Resource under `app/Http/Resources/` carries the field. One key on the resource.
4. **`TaperedAnnualAllowanceStrategy` computes adjusted income as threshold income plus employer contributions**, without the FA 2004 s228ZA employee add-back: £15,000 low on a £300,000 fixture. The threshold line no longer reads it (it uses the definitions' own `adjusted_allowances`), but other callers do.
5. **No form renders `vesting_frequency_months`** (web `AccountForm.vue:365` holds it in data but never renders it; onboarding neither). The resolver now derives the cadence from `vesting_type`, so this is a capture gap rather than a wrong figure; add the input or drop the column.
6. **Salary-sacrifice National Insurance has a third implementation**: `SalarySacrificeNiStrategy.php:59-75` still prices at a flat rate after the analyser and the threshold line were made to agree through the calculator. Rule 20: consolidate onto the analyser's `classOne()` path. Also `SalarySacrificeAnalyzer` is handed `annual_employment_income` without `employment_income_basis` (a net-of-sacrifice recorder gets a band one sacrifice too low); `RetirementActionDefinitionService.php:~1377` fallback is now unreachable.
7. **Free tier cannot capture childcare spend or Gift Aid** (`DETAILED_EXPENDITURE_FIELDS`, `UserProfileController.php:39-59`), so the Tax-Free Childcare row and the band extension never reach a free user. Product decision for CSJ, not a bug.
8. **From-scratch migration path is broken**: `database/migrations/2026_07_13_100000_create_pipeline_articles_table.php:13` adds a foreign key to `insight_articles` before that table exists (error 1824). Invisible while `database/schema/mysql-schema.sql` loads; found when the test database had to be rebuilt.
9. **Two suites red before this branch and still red**: `tests/Unit/Database/ValidationMaxFitsColumnPrecisionTest.php` (`StoreProtectionProfileRequest::annual_income` vs the `employments` column precision) and `tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php` (`OnboardingChatDirector` imports `DCPension`/`DBPension` directly).
10. **Smaller adjacent items**: onboarding rejects a phone number containing a space with no field-level message; the saved RSU card on the onboarding assets tab shows the raw enum "rsu" and "Current Value £0"; `route:list` fails on csjones with a `SymfonyAppleBridgeClient invalid_configuration`; csjones php.ini has `serialize_precision = 100` (float noise on every JSON number there); `FamilyMember` has no disability flag so the seeded higher Tax-Free Childcare cap is unreachable; the salary-sacrifice NI saving is not priced by any threshold lever.
11. **Design points for CSJ on the branch itself**: the native card takes the 128pt hero clearance and the focus areas drop theirs when it is present (spacing CSJ tuned 2026-09-18); the pension lever reads "Pay £X into your pension" not "Salary sacrifice" because sacrifice is not priced; recorded salary is assumed to exclude vests (a payslip figure often includes them).

## Context to load

- `docs/superpowers/specs/2026-09-21-threshold-position-design.md` — the agreed design; every ruling below resolves against it.
- `docs/superpowers/plans/2026-09-21-threshold-position.md` — the sixteen tasks as executed; amended in place for every ruling.
- `.superpowers/sdd/2026-09-21-threshold-position/progress.md` — the ledger: every ruling, deferred minor and adjacent defect with file:line; git-ignored, read it before touching `app/Services/Tax/Thresholds/`.
- `tests/Persona/threshold-position/reports/2026-09-21.md` — the live walk, what each item's interaction and payload were, the two test accounts.
- `app/Services/Tax/Thresholds/Lines/MoneyLine.php` — the shared lever sizing (`affordableAmount`, `constraintNote`, `mechanismFor`); any new income line goes through it.
- `deploy/DEPLOY.md` (csjones section) plus memory `feedback_deploy_gate_csjones_before_admin_merge` — the deploy-feature-then-merge order that governs item 1.

## Completed this session

- Recovered the 2026-09-17 spec (Claude Doc) and design canvas; wrote and committed the spec and plan (bb61147c4 and after).
- Tasks 1–16 via subagent-driven development, one implementer at a time, each reviewed and re-reviewed: DC drawdown capture on every surface (c8caafe27, eb1a59486, 0c6ada82f, 1e6c6405c); vest resolver and ninth income component (378c7b061, 70694f3a9); conflict pair, Gift Aid extension across every strategy, NI cap year from config, childcare rates (824194ad5…8a8fbe059); contracts and cost delta (86eab1050…a72bb311f); childcare entitlements (ee2557935, a1a966618); nine lines and copy (dedd8e3aa…4dc41a44a); evaluator (fa50e1a94, 47eca965d); endpoint (ba097bb73, d3502f2c6); web strip (c4d2ab826, 112b3113a); `/m` strip (a57cbf79a); native card (47e55e259); live-walk fixes (572e424e5); Gift Aid save fix (fd60af65d); final-review wave (0e9c4d134, e0204fe2f); evidence pack (50e9c5c86).
- csjones deployed on the branch three times (full with bundles at a57cbf79a, then PHP-only at 572e424e5, fd60af65d, e0204fe2f).
- `verify-m` skill gained the credentials-omit clause for identity probes.
- Memory: `project_threshold_position_branch_2026_09_21.md` and the reference to the spec artifacts.

## Verification state

- Pest, four covering paths (`tests/Unit/Services/Tax/Thresholds`, `VestScheduleResolverTest`, `SalarySacrificeAnalyzerTest`, `Feature/Api/ThresholdControllerTest`): 68 passed (219 assertions) at e0204fe2f, on a quiet test database.
- Vitest: web and `/m` strip specs 6 passed at e0204fe2f.
- Native: `ThresholdClientTests`, `DashboardModelTests`, `DashboardClientTests` 11 passed on `Fynla iPhone 11` at 47e55e259 (implementer's run, not mine).
- Live: 49 items by interaction on csjones, web and `/m`, at 572e424e5 and fd60af65d; my own server reads of Persona A's payload agreed three times.
- Not verified: the full Pest suite (lean cadence; never run this session); the native card on screen (I COULD NOT TEST THIS: schemes read production); any surface at the final tip e0204fe2f live on csjones beyond the API reads (the last wave changed lever sizing, the child-benefit item and copy percentages; re-walk the strip once before merge if you want the belt and braces).

## Decisions and dead ends

- All 28 rulings are in the ledger with the cost if wrong; the ones that bind design: lines apply per user only; a leverless line may lead when nothing else applies; the lever names the next vest; pension lever wording is "Pay £X into your pension"; ISA lever needs remaining allowance ≥ excess; banded lines stay visible past their top with "past" wording; minimum pension age seeded 55 (57 from 2028); DfE rates as seeded; middot in date headlines allowed.
- CSJ decisions during the session: dynamic per-user lines (never a fixed set); NI cap is 2027; vest join and both tax-strategy bugs in scope; DC drawdown capture in scope; all income types mixed with the full breakdown on web and end figures on `/m` and iOS; csjones switched to the feature branch; keys unlocked in ssh-agent.
- Dead ends: the plan's persona arithmetic was stale (salary plus drawdown plus vest lands past £125,140); my Revolut sandbox card number was wrong, the repo's `docs/archive/revolut/revolutTestCards.md` Visa works; an `/m` identity probe with a bearer header still sends the desktop cookie unless `credentials: 'omit'`.
- Process: one implementer at a time on one checkout; two agents running Pest at once corrupted `laravel_testing` (recreated; dev database untouched); never amend commits (SHAs in reviews went stale twice).

## Things that will bite you

- csjones is on `feature/threshold-position`, not `dev`. Any other session deploying dev there will overwrite it.
- `route:list` fails on csjones (Apple bridge config); use `grep` on `routes/api.php` there instead.
- The test database is shared by every Pest process on this machine; never run two at once.
- The ssh keys were unlocked by CSJ this session; a new session will need `ssh-add` again.

## Tech debt deferred

No separate tech-debt pass was run at session end; the final whole-branch review (opus, five passes over 89 files) served as the audit and its can-wait list is in the ledger. Items: NI rows always £0 under both mechanisms; web cost rows keyed by label; `/m` toggle lacks `aria-expanded`; duplicate `fmt()` helper on `/m`; a thrown thresholds fetch on `/m` shows a spurious banner; Tax-Free Childcare capped on aggregate spend not per child (`ChildcareEntitlements.php:42-46`); pension ceiling at gross relevant earnings without netting contributions already made (`ThresholdCostCalculator.php:74-83`); band lines position on `totalIncome()` while `bandRateFor` bands on `taxableIncomeFor()` (needs a decision); no JSON fixture decodes a literal null lever natively; the native threshold request runs serially after the snapshot; the xAI tool-schema golden master is assembled from the neutral corpus; dead `step_child` branch; cliff date coinciding with a ladder date sums both.

## Branch and deploy state

- Branch: feature/threshold-position (tip e0204fe2f), PR #919 → dev, open
- Unpushed commits: none
- Deploy status: csjones.co/fynla on this branch at e0204fe2f (migration and both seeders applied there); production untouched
- Working tree: four modified files and eleven untracked files predate this session (diagrams, workforce logs and briefs, the 15 September handover) and were left alone
