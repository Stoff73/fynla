---
type: handover
mode: session-end
date: 2026-09-30
session: 3
repo: fynla
branch: dev
---

# Session Handover — 2026-09-30, Session 3

## Where things stand

**Released to fynla.org today:**
- **Release #1015** (`main` `266c35c1b`) shipped #1013 and #1014.
- **Release #1017** (`main` `a58497914`) shipped #1016: a retired partner's income was counted as pay and pension at once, found walking #1015.
- Both were walked on fynla.org, web 1440px and `/m` 390px.

**On `dev`, NOT released.** Five ice-cube follow-up fixes are merged, each walked on csjones on web and `/m`:
- **#1018:** interest rates of 1% or less were read as 100 times too much.
- **#1019:** the retention purge now deletes every user table.
- **#1020:** no more £0 "gift savings" or "ISA in your spouse's name" cards, and savings interest now uses each allowance once, in order.
- **#1021:** the partner's pension card shows the relief limit, not the Annual Allowance.

**Open, not walked:** PR #1022, the desktop Tax Strategy rates note.

**Not started:** items 6 and 7.

**Patch notes:** the combined notes for 29 and 30 September are committed. They cover everything through #1017.

## Priorities for the next session

1. **Walk and merge PR #1022 (item 5).**
   - Branch `fix/web-tax-basis-note`.
   - Build both csjones bundles: `./deploy/csjones-fynla/build.sh` (web and `/m`). Upload `public/build` and `public/m-build`, keeping the old chunks. On the server: `git fetch && git checkout -B fix/web-tax-basis-note origin/fix/web-tax-basis-note`.
   - Walk the Tax Strategy page on web (1440px) and `/m` (390px). Web must now show "Income Tax bands use England, Wales and Northern Ireland rates…" above the allowances; `/m` must still show it.
   - Then merge (admin) and move csjones back to `dev`.
2. **Item 6: the "spouse's income is known" check.**
   - `HouseholdFinancialContext::spouseIncomeKnown` (`app/Services/Coordination/HouseholdFinancialContext.php:302`) reads only the typed `spouse_annual_income`.
   - It should use `TaxStrategyMath::linkedSpouseWithIncome`, as `spouse_income_amount` at line 71 already does.
   - It gates `savings_to_spouse` (`database/seeders/TaxActionDefinitionSeeder.php:179`).
   - Test, walk on csjones on web and `/m`, then PR.
3. **Item 7: the partner questions re-ask what the linked account already holds.**
   - The working-spouse and "Now your spouse" forms ask a linked partner's ISAs, pension and investments, which are already on the partner's own account. This was ice-cube's #978 follow-up 3, and today's fynla.org walk showed it: Pat was asked about Sam's ISAs, pensions and investments.
   - The forms live in `CaptureForms` / `OnboardingStateMachine`. Find which state renders "Do they have any of the following?".
4. **Release #1018 to #1022 together.**
   - Needs app code plus both bundles (#1022 is the only front-end change). No migration, seeder or config.
   - Write the script from `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/d9ab8715-3a7b-4b5d-a520-e4e5f309143f/scratchpad/release-prod-2026-09-30-s3b.sh`, adding the bundle build and upload from the session-2 script (`.../e2e2738b-.../scratchpad/release-prod-2026-09-30-s2.sh`, steps 2 and 4).
   - Ask CSJ first: they run it with `!`.
   - Then walk fynla.org and update the patch notes: add a 1 October section, or extend the 29–30 September notes if it ships the same day.
5. **Features CSJ approved today, to build after 1 to 4**, each spec'd with sources first:
   - a savings-shift strategy for dual earners (move savings to the lower-rate partner, whose Personal Savings Allowance is £1,000 against £500);
   - pension relief at 40% offered below £100,000, down to £50,270;
   - an affordability check on every spouse pension top-up;
   - Marriage Allowance widened from gov.uk's test to ITA 2007 s55C(1)(c). The gates are in `TaxStrategyMath::marriageAllowance`; see ice-cube's #982.
6. **Then the session-2 list:**
   - the Retirement page for someone already retired (`RetirementProjectionService::projectPensionPot:94` clamps years to go at `max(1, …)`, and `RetirementAgeResolver` never reads `retirement_date`);
   - the retirement how-to batch;
   - `CSJTODO.md` NEXT.

## Context to load

- `CSJTODO.md`, the "ice-cube issues checked 2026-09-30" and "CSJ decisions 2026-09-30" entries under NEXT: the state of items 1 to 7, the approved features, and the old-wizard mortgage.
- `app/Services/Coordination/HouseholdFinancialContext.php:55-90, 296-315`: item 6, both flags side by side.
- `app/Services/Tax/TaxStrategyMath.php` (`linkedSpouseWithIncome`): the one rule item 6 must reuse (Rule 20).
- `docs/tech-debt-report.md`: this session's debt, including the critical invented mortgage in the old wizard and more mappers that may store fractions.
- `September/September30Updates/patch-notes-2026-09-29-30.md`: extend it at the next release.

## Completed this session

- **Release #1015 (`266c35c1b`):** #1013 and #1014 are live and were walked on fynla.org.
  - The partner's pension step opens the transferred pension.
  - Found on the walk: a partner given £30,000 with no status, who then answered "Retired", kept the £30,000 as estimated pay alongside the drawdown. That meant £60,000 of income, higher-rate tax, National Insurance, and "pay £9,700 into your pension".
- **#1016, shipped in release #1017 (`a58497914`):**
  - `SpouseEstimateStatusObserver` and `SpouseHoldingTransfer::restateEstimateForStatus` turn the estimate into drawdown (retired) or other income (not working).
  - It also fixed #1013's `WalkFormPrefill`/`RecordEditForms` reading models directly, which had turned StoreBoundary red.
  - Walked on csjones and fynla.org, web and `/m`: "Pension income £30,000", no National Insurance.
- **Patch notes:** one set for 29 and 30 September, with all ice-cube PRs (`2e76239d7`). The single-day files were removed. Two false claims were corrected:
  - #1008 was walked on local copies, not the test site;
  - when a partner's status is unknown, their income is carried across as estimated pay, not dropped.
- **Ice-cube issues checked, per CSJ's "check first".** 20 items across 11 PRs, reported. CSJ then said to fix 1 to 7 in order.
  - **#1018:** `App\Support\SavingsInterestRate` is now the one reading of the rate; there had been six copies of the "at most 1 is a fraction" guess. The old wizard and document upload now store percentages (`AbstractFieldMapper::parseRatePercent`).
  - **#1019:** `RetentionPurgeService` now deletes the 38 missed user tables. `ANONYMISED_TABLES` covers `ai_cost_attribution`. A guard test fails when a new user table is in neither list. The 4 production release-walk accounts (751 to 755) that still held 7 conversations were force-deleted.
  - **#1020:** the gift card needs at least £1 saved. The spouse ISA card needs the user's interest to be taxed, or a General Investment Account. `TaxStrategyCalculator::stackInterest` now stacks interest for all three grids; it was found on the walk that the same £160 counted against three allowances.
  - **#1021:** `spousePensionPosition` shows £3,600 "without earnings" for a retired partner, and the `?? 3600` fallback is removed.
- **Account 645 (production):** its savings row went from `0.0400` to `4.0000`, as CSJ said "obviously 4%". Done through `SavingsStore`; projected interest unchanged at £124.
- **Walk accounts:** 766 to 769 on fynla.org were purged. csjones walk accounts 440 to 444 remain and are free to reuse or purge; 441 was purged in the #1019 check.

## Verification state

- **Named test files only** (CSJ rule), all green at each PR's head:
  - #1016: 85 plus 39;
  - #1018: 446 across 37 files;
  - #1019: 17;
  - #1020: 205;
  - #1021: 251;
  - #1022: 1 PHP plus 6 Vitest.
- Every new regression test was shown to fail without its fix.
- **Full suites:** not run. CI was not waited on.
- **Walked on csjones, web 1440px and `/m` 390px, as a user by clicking through:** #1016, #1018, #1020, #1021. For #1019 there is no page to walk: the purge was run on csjones against account 441 and the rows counted.
- **Walked on fynla.org:** #1015 and #1017.
- **Not verified:**
  - #1022 in the browser;
  - #1018 to #1022 on fynla.org (not released);
  - the iPhone app, for everything.

## Decisions and dead ends

- **CSJ's decisions:**
  - A retired partner may have a working spouse, so "Does your spouse work?" stays.
  - Build all four approved features (see priority 5).
  - Release only work already done before starting anything new, unless it's a fix to something from the last three sessions or raised by ice-cube. That's why the retirement page work was stopped.
- **Unknown partner status still carries the income as estimated pay** (ruling 50, `644d4cd86`). #1016 restates it when the partner answers; the ruling is not undone.
- **The partner's pension card uses earnings only when they're known:** given, or implied by status. Unknown earnings keep the Annual Allowance, marked "not confirmed". The "unknown counts as none" rule from 28 September applies to pricing a promise (`NonEarnerSpousePensionStrategy`), not to a display card.
- **The retention purge must keep soft-delete plus email.** It's the documented design so the person can register again (`RetentionPurgeService.php:104`). So the database cascades never run, and every table has to be listed; the guard test enforces it.
- **The rates-note sentence lives on the server** (`TaxStrategyOutputDTO::TAX_BASIS_NOTE`), not copied into the desktop app. That follows our rule that labels live on the server.

## Things that will bite you

- **`tests/Unit/DataTransferObjects` is not bound to the Laravel TestCase**: `$this->seed()` is undefined there. Put database tests under `tests/Unit/Services/...`.
- **The pre-tool hook blocks Pest runs built from `$(...)` or variables** when it can't see the file names. Name the files literally.
- **Don't use `git checkout <file>` to restore after a stash test** if that file holds uncommitted new tests; it wiped them once today. Stash only `app/`.
- **The desktop sign-in session and the `/m` session are separate.** `/m` on csjones keeps the last account signed in, so sign out from its menu first. On fynla.org, `/m` picked up the desktop session.
- **The desktop Planning menu toggles.** Clicking "Planning" when it's already open closes it.
- **Carried from session 2:**
  - never `git reset --hard` on csjones (`public/.htaccess`);
  - csjones checks out only part of the repo;
  - production writes go through the `ssh-fynla` MCP tool or a script CSJ runs with `!`.

## Tech debt deferred

See `docs/tech-debt-report.md`, session 3:
- **Critical:** the old wizard invents a mortgage (`OnboardingService.php:598-613`).
- **Warnings:**
  - `platform_fee_percent` and `indexation_rate` mappers may store fractions (`DCPensionMapper.php:39`, `InvestmentAccountMapper.php:32`, `LifeInsuranceMapper.php:38`);
  - the status lists are written out twice (`SpouseHoldingTransfer.php:28-31`, `TaxStrategyCalculator.php:482-483`);
  - the split `if` blocks in `AssetShiftingBundleStrategy.php:86-135`;
  - `ANONYMISED_TABLES` does not drive the purge's Phase 6.

## Branch and deploy state

- **Branch:** `dev`, pushed. `fix/web-tax-basis-note` (PR #1022) is pushed and not merged.
- **Worktrees:** none.
- **Production:** `main` `a58497914`.
- **csjones:** `dev` `4bd1582e8` (before the TODO commits), with `.htaccess` intact.
- **Local uncommitted files are CSJ's own and were left alone:** `docs/diagrams/*.excalidraw`, `workforce/ops/log/*`, and the session-1 handover edit.
- **Walk screenshots:** moved out of the repo to the session scratchpad.
