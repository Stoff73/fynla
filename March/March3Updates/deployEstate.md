# Estate Plan Rewrite - Deployment Notes

**Date:** 3 March 2026
**Branch:** `main`
**Commits:** `a3565af`, `909be17`
**Scope:** Full IHT calculation table, structured executive summary, personal information section, gifting schedules, redundancy cleanup, estate data trigger fix, tax compliance fixes

---

## Summary

Ten changes in this deployment:

1. **Full IHT Calculation Table** — Replaced the simplified metric cards in Current Situation with the actual `IHTCalculationTable.vue` component from the estate module. Shows expandable per-owner asset/liability breakdowns with joint ownership labels (`(Joint)`, `(Tenancy in Common - X%)`), projected values at life expectancy, expandable allowances section with NRB/RNRB individual + transferred breakdown, and IHT liability highlighted in red. Backend rewritten to inject `IHTFormattingService` and `EstateAssetAggregatorService`, returning the same data shape as `IHTController::calculateIHT()`.

2. **Structured executive summary** — Replaced the narrative-only executive summary with a structured layout: greeting, opening, introduction, key actions table (action/priority badges), and closing paragraph. Falls back to the shared `PlanExecutiveSummary` for legacy/cached plan data.

3. **Personal information section** — Added a new section below the executive summary showing personal details, family, financial overview, and estate profile (estimated age at death, years to planning horizon, Inheritance Tax status, has will) in a 2x2 grid layout.

4. **Year-by-year gifting schedules** — Added expandable PET gifting schedule table to `pet_gifting` action cards showing Year, Gift Amount, Inheritance Tax Reduction, and Exempt After Year for each cycle. Added annual gifting detail summary grid to `annual_gifting` action cards showing Annual Amount, Years, Total Gifted, and Inheritance Tax Saved. Backend threads gifting strategy data from `GiftingStrategyOptimizer` through to action cards.

5. **Removed IHT summary table from executive summary** — The simplified 2-column IHT table (Gross Estate, Liabilities, Net Estate, NRB, RNRB, etc.) was removed from the executive summary card since the full `IHTCalculationTable` is now shown in the Current Situation section below.

6. **Removed Joint Estate Overview section** — The side-by-side estate positions for both partners was removed from the plan since per-owner breakdowns are now shown in the expandable IHT Calculation Table.

7. **Removed "Complete Your Inheritance Tax Profile" action from plans** — Filtered out `category: 'planning'` recommendations at the `EstatePlanService` level. This action made no sense when the IHT profile has already been calculated and displayed.

8. **Linked accounts list** — Added an "Assets Included in Estate" table showing all estate-relevant assets (properties, investments, savings, business interests, personal property) with type badges and values. Excludes pension assets (DC and DB pensions are outside the estate for IHT purposes). Sourced from `EstateAssetAggregatorService`.

9. **Fixed "Add Your Estate Data" trigger in EstateAgent** — The planning recommendation was triggered by checking `$user->ihtProfile !== null` — whether the user had an optional `IhtProfile` model record. This was wrong: the IHT calculation uses actual assets, date of birth, and marital status, not this form. Replaced with a check for `gross_estate > 0` (has assets) and a real date of birth (not the default fallback age). Renamed the action to "Add Your Estate Data" with specific guidance on what's missing. Removed `has_iht_profile` from the profile data array.

10. **Tax compliance fixes** — Fixed wrong config key for charitable IHT rate (`charitable_rate` -> `reduced_rate_charity`). Fixed hardcoded rate percentages in explanatory messages to use `sprintf()` with config-sourced values. Added `iht_reduction` field to PET gift schedule entries in `GiftingStrategyOptimizer` (uses config-sourced IHT rate, not hardcoded 40%).

---

## Files Changed

### New Files (2)

| File | Purpose |
|------|---------|
| `resources/js/components/Plans/Estate/EstateExecutiveSummary.vue` | Structured exec summary with key actions table and priority badges |
| `resources/js/components/Plans/Estate/EstatePersonalInformation.vue` | Personal info with estate profile quadrant (age at death, planning horizon, IHT status, will) |

### Modified Files (10)

| File | Change |
|------|--------|
| `app/Agents/EstateAgent.php` | Replaced `has_iht_profile` check with actual estate data check (`gross_estate > 0` and real DOB). Renamed action to "Add Your Estate Data" with dynamic actions list. Removed `has_iht_profile` from profile data array. |
| `app/Services/Plans/EstatePlanService.php` | Injected `IHTFormattingService` and `EstateAssetAggregatorService`. Rewrote `buildCurrentSituation()` to return full IHT table data (calculation, assets_breakdown, liabilities_breakdown, iht_summary). Rewrote `buildExecutiveSummary()` to structured format (removed IHT summary table). Removed `joint_estate_view` from plan output. Added filter to exclude `planning` category recommendations. New methods: `buildPersonalInformation()`, `buildLinkedAccountsList()`, `attachGiftingDetailToActions()`. Fixed charitable rate config key. Fixed hardcoded rate messages. |
| `app/Services/Estate/GiftingStrategyOptimizer.php` | Added `iht_reduction` field to PET gift schedule entries in `calculatePETStrategy()` |
| `app/Models/PlanActionFundingSelection.php` | PSR-12 formatting (concat spacing) |
| `app/Services/Plans/RetirementPlanService.php` | PSR-12 formatting (concat spacing) |
| `resources/js/components/Plans/Estate/EstateCurrentSituation.vue` | Replaced simplified metric cards with `IHTCalculationTable` component. Added `tableProps` computed property matching `IHTPlanning.vue` pattern. Removed `ihtLiability` computed. Added NRB/RNRB/rate messages below table. |
| `resources/js/components/Plans/Estate/EstatePlanContent.vue` | Added `EstateExecutiveSummary` and `EstatePersonalInformation` components. Removed `EstateJointView`. Added `hasStructuredSummary` guard for legacy fallback. |
| `resources/js/components/Plans/Estate/EstateGroupedActions.vue` | Added expandable PET gifting schedule table. Added annual gifting detail grid. Added `expandedSchedule` data property and `toggleSchedule()` method. |
| `tests/Unit/Services/Plans/EstatePlanRefactorTest.php` | Updated constructor to include `EstateAssetAggregatorService` and `IHTFormattingService` mocks. Updated Joint Estate View tests (asserts `joint_estate_view` not in plan). Updated executive summary test (asserts `iht_summary` not in executive_summary). Added 7 new test cases. Total: 18 tests, 140 assertions. |
| `tests/Feature/InvestmentModuleTest.php` | Removed unused import (PSR-12) |

---

## Upload to Production

### 1. Build frontend

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload files via SiteGround File Manager

**PHP files (3):**
- `app/Agents/EstateAgent.php`
- `app/Services/Plans/EstatePlanService.php`
- `app/Services/Estate/GiftingStrategyOptimizer.php`

**Frontend build:**
- `public/build/` (entire directory)

### 3. Clear caches via SSH

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

**Note:** No database migrations required. No new seeders needed. `EstateAssetAggregatorService`, `IHTFormattingService`, and `IHTCalculationTable.vue` already exist on production.

---

## Pre-Deployment Verification (Completed)

| Check | Status | Detail |
|-------|--------|--------|
| Full test suite | PASS | 1,603 tests, 6,719 assertions |
| Estate plan tests | PASS | 18 tests, 140 assertions |
| PSR-12 formatting | PASS | 811 files clean via `./vendor/bin/pint` |
| Tax compliance review | PASS | Fixed `charitable_rate` -> `reduced_rate_charity`, fixed hardcoded rate messages |
| Design compliance | PASS | No amber/orange, currencyMixin used, no acronyms in user-facing text, British spelling, no scores |
| Browser - peak_earners | PASS | Multi-column IHT table, expandable per-owner assets with joint labels, expandable liabilities with mortgage addresses, expandable allowances, NRB/RNRB messages, no IHT summary in exec summary, no joint estate view, no planning action |
| Browser - widow | PASS | Single-owner view, transferred NRB/RNRB from deceased spouse, NRB message, PET schedule, annual gifting grid, no IHT summary in exec summary |
| Browser - entrepreneur | PASS | Single-owner view, business interests in assets, NRB (single), no spouse column |
| Browser - retired_couple | PASS | "Estate Plan Not Applicable" (estate below threshold) |

### Unit Tests (18 total)

| Test | Assertion |
|------|-----------|
| Estate Plan Redundancy Elimination [1.T1] | Single `analyze()` call, no redundant API calls |
| Joint Estate View Removed [4A.T2] | `joint_estate_view` key not present in plan output |
| Funding Source - charitable [funding.T1] | Includes funding source for charitable bequest actions |
| Funding Source - gifting [funding.T2] | Includes funding source for gifting recommendations |
| Life Cover Affordability - affordable [cover.T1] | Marks affordable life cover with no warning |
| Life Cover Affordability - unaffordable [cover.T2] | Flags unaffordable life cover with warning |
| Health Score Removal [score.T1] | No `health_score` key in IHT summary current data |
| Gate Checks - age gate [gate.T1] | Returns `not_applicable` for user under age gate |
| Gate Checks - failure [gate.T2] | Returns error plan when analysis fails |
| Gate Checks - zero IHT [gate.T3] | Returns `not_applicable` when IHT liability is zero |
| Personal Information - married [5.1.T1] | Returns full shape with `marital_status_iht=married`, `spouse_name` present |
| Personal Information - widowed [5.1.T2] | Returns `marital_status_iht=widowed`, `spouse_name` null |
| Structured Executive Summary [5.1.T3] | Returns greeting, actions_summary, total_actions; no `iht_summary` key |
| Current Situation - full IHT table [5.1.T4] | Includes `calculation`, `assets_breakdown`, `liabilities_breakdown`, `iht_summary` with current/projected shapes |
| Current Situation - supplementary [5.1.T5] | Includes `linked_accounts`, `asset_breakdown`, `life_cover`, `charitable_giving` |
| Gifting - PET schedule [5.1.T6] | Merges `gift_schedule`, `seven_year_cycles`, `amount_per_cycle` for `pet_gifting` actions |
| Gifting - annual detail [5.1.T7] | Merges `annual_gifting_detail` for `annual_gifting` actions |
| Detailed Action Guidance [guidance.T1] | Includes step-by-step guidance for each action |

---

## What Changed (Detail)

### Current Situation - Before
- Basic metric cards showing gross estate, net estate, IHT liability, NRB, RNRB, effective rate
- Simplified 2-column layout with no breakdown
- No individual asset rows or joint ownership labels
- No projected values at life expectancy

### Current Situation - After
- Full `IHTCalculationTable` component (700+ lines, reused from estate module)
- Expandable per-owner asset rows (David's Assets / Sarah's Assets) with individual assets listed
- Joint ownership labels: `(Joint)`, `(Joint - X%)`, `(Tenancy in Common - X%)`
- Asset type groups: Property, Investment, Cash, Business, Personal Valuables
- Expandable per-owner liability rows (mortgages with property address, other liabilities)
- Expandable allowances section with NRB + RNRB individual + transferred breakdown
- Multi-column: Now | Age X (Life Expectancy)
- IHT Liability row highlighted in red
- Effective Rate row
- NRB/RNRB/rate explanatory messages below table
- Asset Breakdown, Life Cover, Charitable Giving cards unchanged below

### Executive Summary - Before
- Included IHT summary table (item/amount rows) with currency formatting
- Included key actions table

### Executive Summary - After
- IHT summary table removed (redundant with Current Situation)
- Key actions table retained
- Greeting, opening, introduction, closing text retained

### Joint Estate View - Before
- Side-by-side cards showing primary and spouse estate positions

### Joint Estate View - After
- Removed entirely. Per-owner breakdowns now shown in expandable IHT Calculation Table rows

### "Complete Your IHT Profile" Action - Before
- Triggered by `$user->ihtProfile !== null` — checking for an optional `IhtProfile` model record
- Fired for users like the Mitchells who had complete estate data but no `IhtProfile` record
- Title: "Complete Your Inheritance Tax Profile" with generic actions

### "Complete Your IHT Profile" Action - After
- **Agent trigger fixed**: Now checks `gross_estate > 0` (has assets) and real date of birth (not default fallback age)
- Renamed to "Add Your Estate Data" with dynamic actions (only lists what's actually missing)
- Additionally filtered out at `EstatePlanService` level (safety net for plans, since users with no data hit the age/IHT gates first)
- `has_iht_profile` removed from profile data array entirely

### Gifting Schedules - Before
- Action cards showed title, description, estimated impact, and funding source
- No year-by-year breakdown for PET gifting
- No detail summary for annual gifting

### Gifting Schedules - After
- PET gifting action cards include expandable year-by-year schedule table
- Annual gifting action cards include detail summary grid
- `iht_reduction` field added to PET schedule using config-sourced IHT rate

### Tax Compliance Fixes
- **Charitable rate config key**: `charitable_rate` -> `reduced_rate_charity`
- **Hardcoded rate messages**: Now uses `sprintf()` with config-sourced rates
- **PET iht_reduction**: Backend provides `iht_reduction` per gift schedule entry

---

## Test Checklist

### IHT Calculation Table (NEW)
- [x] peak_earners shows multi-column layout (Now | Projected at Life Expectancy)
- [x] Expandable asset rows per owner (David's assets, Sarah's assets)
- [x] Individual assets listed with joint ownership labels: `(Joint)`, `(Tenancy in Common - X%)`
- [x] Asset type groups: Property, Investment, Cash, Business, Personal Valuables
- [x] Expandable liability rows per owner (mortgages with property address)
- [x] Expandable allowances section with NRB + RNRB breakdown
- [x] IHT Liability row highlighted in red
- [x] Effective Rate row
- [x] NRB/RNRB/rate messages below table

### Redundancy Cleanup (NEW)
- [x] No IHT summary table in executive summary card
- [x] No Joint Estate Overview section in plan
- [x] No "Complete Your Inheritance Tax Profile" action

### Structured Executive Summary
- [x] peak_earners shows greeting, key actions table, closing
- [x] Actions table shows priority badges with correct colours
- [x] Legacy plan data (without `greeting` field) falls back to `PlanExecutiveSummary`

### Personal Information
- [x] Personal details section shows full name, date of birth, age, marital status
- [x] Family section shows spouse name and children
- [x] Financial overview shows income, expenditure, disposable income
- [x] Estate profile shows estimated age at death, years to planning horizon, IHT status, has will
- [x] Married persona (peak_earners) shows "Married" for IHT status
- [x] Widowed persona (widow) shows "Widowed" for IHT status

### Gifting Schedules
- [x] PET gifting action card shows "Show year-by-year gifting schedule" button
- [x] Clicking shows expandable table with Year, Gift Amount, IHT Reduction (green), Exempt After Year
- [x] Annual gifting action card shows 4-cell detail grid

### Cross-Persona Testing
- [x] peak_earners (David & Sarah Mitchell) - married, expandable per-owner assets/liabilities, joint labels, 3 actions
- [x] widow (Margaret Thompson) - widowed, single-owner view, transferred NRB/RNRB, 4 actions
- [x] entrepreneur (Alex Chen) - single, business interests, NRB (single), no spouse column
- [x] retired_couple (Patricia & Harold Bennett) - "Estate Plan Not Applicable" (estate below threshold)

### General
- [x] Full test suite: `./vendor/bin/pest` - all 1,603 tests pass
- [x] No amber/orange colours present (Rule 9)
- [x] Currency formatted via currencyMixin (Rule 6)
- [x] No acronyms in user-facing text - "Inheritance Tax" not "IHT" (Rule 10)
- [x] British spelling throughout
- [x] No scores in UI (Rule 12)
