# Estate Plan Rewrite — Task List

**Source plan:** `March3Updates/estateRewritePlan.md`
**Branch:** `estate`
**Prerequisite:** Protection Plan (`protectionPlan`) merged to main (DONE)

---

## Pre-flight

- [x] **Confirm branch is correct**: Working on `main` branch (no estate branch needed)
- [x] **Confirm protection code is available**: Check `app/Services/Protection/ProtectionActionDefinitionService.php` exists
- [x] **Seed database**: `php artisan db:seed`
- [x] **Run existing tests**: `./vendor/bin/pest` — all 1,597 pass

---

## Phase 1: Backend — `EstatePlanService.php`

**Agent/Skill:** Use `/feature-dev` for guided implementation. Use `tax-compliance-reviewer` agent to review IHT data exposure. Use `Explore` agent to verify data shapes from `EstateAgent::analyze()`.

### 1.1 Inject `EstateAssetAggregatorService`

- [x] **Read current constructor**: Read `app/Services/Plans/EstatePlanService.php` lines 17-23
- [x] **Add dependency**: Add `private readonly EstateAssetAggregatorService $assetAggregator` to constructor
- [x] **Add import**: Add `use App\Services\Estate\EstateAssetAggregatorService;`
- [x] **Verify service resolves**: `php artisan tinker` → `app(App\Services\Plans\EstatePlanService::class)` — resolves cleanly

### 1.2 Add `buildPersonalInformation(User $user, array $data): array`

- [x] **Read reference**: Read `app/Services/Plans/ProtectionPlanService.php` lines 196-246 (the pattern to follow)
- [x] **Create method**: New private method `buildPersonalInformation(User $user, array $data)`
- [x] **Common fields** (identical to Protection):
  - `full_name` — from `$user->first_name` + `$user->surname`
  - `date_of_birth` — from `$user->date_of_birth`
  - `age` — calculated from DOB
  - `marital_status` — from `$user->marital_status`
  - `spouse_name` — from `$user->spouse` if married/civil_partnership
  - `children[]` — from `$user->familyMembers()->where('relationship', 'child')`
  - `gross_income` — sum of all income fields
  - `net_income` — from `$this->disposableIncome->getForUser($user)`
  - `annual_expenditure` — from disposable income accessor
  - `disposable_income` — annual disposable
  - `monthly_disposable` — monthly disposable
- [x] **Estate-specific 4th quadrant fields**:
  - `estimated_age_at_death` — from `$data['iht_calculation']['estimated_age_at_death']` (default 85)
  - `years_to_death` — from `$data['iht_calculation']['years_to_death']`
  - `marital_status_iht` — derived: `married`/`widowed`/`single` from `$ihtCalc['is_married']` and `$user->marital_status`
  - `has_will` — `Will::where('user_id', $user->id)->exists()` (already imported)
- [x] **Verify**: Check output shape matches `ProtectionPersonalInformation.vue` prop expectations

### 1.3 Refactor `buildExecutiveSummary()` to structured format

- [x] **Read current method**: Read `app/Services/Plans/EstatePlanService.php` lines 326-409
- [x] **Read reference**: Read `app/Services/Plans/ProtectionPlanService.php` executive summary builder
- [x] **Change signature**: From `buildExecutiveSummary(User $user, array $data, int $recCount)` to `buildExecutiveSummary(User $user, array $data, array $actions)`
- [x] **Return structured keys**:
  - `greeting` — "Dear {firstName},"
  - `opening` — "Thank you for using Fynla..." (same text as current narrative line 1)
  - `introduction` — "Below you will find..." paragraph
  - `iht_summary[]` — Array of `{label, value, format, highlight}` rows:
    - Gross Estate, Less Liabilities, Net Estate, NRB, RNRB, Spouse Exemption (conditional), Total Allowances, Taxable Estate, IHT Rate, IHT Liability (highlight:true), Effective Rate
  - `actions_summary[]` — Top 5 enabled actions `{title, priority}`
  - `total_actions` — count of all actions
  - `closing` — context-aware closing text
- [x] **Update `buildEmptyExecutiveSummary()`**: Added `greeting` key (null) so `hasStructuredSummary` detection works.
- [x] **Update call site in `generatePlan()`**: Changed to pass `$actions` instead of count
- [x] **Verify**: No acronyms in user-facing text (Rule 10), British spelling throughout

### 1.4 Expand `buildCurrentSituation()` — Full IHT table + linked accounts

- [x] **Read current method**: Read `app/Services/Plans/EstatePlanService.php`
- [x] **Change signature**: From `buildCurrentSituation(array $data)` to `buildCurrentSituation(array $data, User $user)`
- [x] **Expand `iht_calculation` key** — all fields computed from TaxConfigService, not hardcoded:
  - `total_allowances`, `taxable_estate`, `iht_rate`, `iht_rate_percent`, `iht_rate_type`, `iht_rate_message`, `is_married`, `is_widowed`, `nrb_message`, `rnrb_message`
- [x] **Add `linked_accounts` key**: Call new method `buildLinkedAccountsList(User $user)`
- [x] **Update call site in `generatePlan()`**: Changed to pass `$user` as second argument

### 1.5 Add `buildLinkedAccountsList(User $user): array`

- [x] **Create private method**
- [x] Call `$this->assetAggregator->gatherUserAssets($user)` — returns Collection of stdClass objects
- [x] Filter to estate-relevant types: `property`, `investment`, `cash`, `business`, `chattel` (exclude `dc_pension`, `db_pension`)
- [x] Map each to `{name, type, value, is_exempt}`
- [x] Sort by value descending
- [x] Return as plain array
- [x] **Verify**: Fields verified against EstateAssetAggregatorService

### 1.6 Thread gifting data through to actions

- [x] **Read `enrichRecommendations()` method**
- [x] **Read `GiftingStrategyOptimizer::calculateOptimalGiftingStrategy()` return shape**
- [x] **In `enrichRecommendations()`**, extract gifting strategies from data
- [x] **For `pet_gifting` category**: Attach `gift_schedule`, `seven_year_cycles`, `amount_per_cycle` from PET strategy
- [x] **For `annual_gifting` category**: Attach `annual_gifting_detail` (annual_amount, years, total_gifted, iht_saved) from Annual Exemption strategy
- [x] **Create `attachGiftingDetailToActions(array $actions, array $data): array`**: Post-process actions after `prepareActions()` to re-merge gifting detail by matching on `category`
- [x] **Update `generatePlan()` call flow**: Call `attachGiftingDetailToActions()` after `prepareActions()`
- [x] **Verify**: Action cards for `pet_gifting` contain `gift_schedule[]`, `annual_gifting` cards contain `annual_gifting_detail`

### 1.7 Update `generatePlan()` return

- [x] **Add `personal_information` key**: `'personal_information' => $this->buildPersonalInformation($user, $data)`
- [x] **Update `executive_summary` call**: Pass `$actions` instead of `count($recommendations)`
- [x] **Update `current_situation` call**: Pass `$user` as second argument
- [x] **Apply gifting detail**: Call `attachGiftingDetailToActions()` on `$actions` before building what-if
- [x] **Verify final return shape**: All keys present

### Phase 1 Checkpoint

- [x] **Run existing tests**: `./vendor/bin/pest` — all 1,597 pass
- [x] **Code review (Phase 1)**: Backend changes reviewed — all methods use TaxConfigService, no hardcoded values, correct data shapes
- [x] **Tax compliance review**: `tax-compliance-reviewer` agent completed — found and fixed wrong key name (`charitable_rate` → `reduced_rate_charity`), fixed hardcoded rate messages to use sprintf with config values
- [x] **Security review**: `security-reviewer` agent completed — no security issues in new code. Pre-existing low-severity items noted (user_id in metadata, debug logging in EstateAssetAggregator)
- [x] **Reseed**: `php artisan db:seed`

---

## Phase 2: Backend — `GiftingStrategyOptimizer.php` (minor)

**Agent/Skill:** Use `tax-compliance-reviewer` agent to verify IHT rate usage.

### 2.1 Add `iht_reduction` to PET gift schedule

- [x] **Read current method**: Read `app/Services/Estate/GiftingStrategyOptimizer.php` `calculatePETStrategy()`
- [x] **Add field**: Added `'iht_reduction' => round($amountPerCycle * $ihtRate, 2)` to `$giftSchedule[]` loop
- [x] **Verify**: `$ihtRate` variable already available in scope
- [x] **No frontend hardcoding**: Frontend uses `row.iht_reduction` from backend

### Phase 2 Checkpoint

- [x] **Run existing estate tests**: `./vendor/bin/pest` — all 1,597 pass
- [x] **Reseed**: `php artisan db:seed`

---

## Phase 3: Frontend — New Components

**Agent/Skill:** Use `/feature-dev` for guided implementation. Use `premium-ui-designer` agent for UI polish. Use `Explore` agent to verify design system compliance against `designStyle.md`.

### 3.1 `EstateExecutiveSummary.vue` (NEW)

- [x] **Read reference**: Read `resources/js/components/Plans/Protection/ProtectionExecutiveSummary.vue`
- [x] **Create component**: `resources/js/components/Plans/Estate/EstateExecutiveSummary.vue`
- [x] **Template structure**: Greeting, opening, introduction, IHT summary table (2-col: Item|Amount with currency/percent formatting, IHT Liability highlighted with `bg-red-50`), Key Actions table with priority badges, closing paragraph
- [x] **Script**: `currencyMixin`, `priorityClass()`, `capitalise()` methods
- [x] **Design compliance**: No amber/orange, no acronyms, British spelling, `currencyMixin` only

### 3.2 `EstatePersonalInformation.vue` (NEW)

- [x] **Read reference**: Read `resources/js/components/Plans/Protection/ProtectionPersonalInformation.vue`
- [x] **Create component**: `resources/js/components/Plans/Estate/EstatePersonalInformation.vue`
- [x] **Template structure**: 2x2 grid (Personal Details + Family + Financial Overview + Estate Profile)
  - Estate Profile: Estimated Age at Death, Years to Planning Horizon, Inheritance Tax Status (with `formatIhtStatus()`), Has Will
- [x] **Script**: `currencyMixin`, `childrenDisplay` computed, `formatDateOfBirth()`, `formatIhtStatus()` methods
- [x] **Card wrapper**: `bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6`
- [x] **Design compliance**: Consistent spacing, typography classes

### Phase 3 Checkpoint

- [x] **Files exist**: Both new `.vue` files created
- [x] **No syntax errors**: Vite compiles without errors
- [x] **Design compliance check**: Verified against design system conventions
- [x] **Reseed**: `php artisan db:seed`

---

## Phase 4: Frontend — Enhanced Components

**Agent/Skill:** Use `/feature-dev` for guided implementation. Use `premium-ui-designer` agent for table polish.

### 4.1 Enhanced `EstateCurrentSituation.vue`

- [x] **Read current component**: Full rewrite of EstateCurrentSituation.vue
- [x] **Replace IHT metric cards with full IHT workup table**: Gross Estate → Less Liabilities → Net Estate (bold) → Less NRB → Less RNRB (v-if) → Less Spouse Exemption (v-if) → Total Allowances (bold) → Taxable Estate → IHT Rate (with percent) → IHT Liability (`bg-red-50`, `text-red-700`) → Effective Rate. NRB/RNRB messages below table.
- [x] **Add "Assets Included in Estate" section**: Conditional table with Asset|Type|Value columns, type badges, exempt badge
- [x] **Add computed**: `hasLinkedAccounts`
- [x] **Add method**: `formatAssetType()` — property→Property, investment→Investment, cash→Savings, business→Business Interest, chattel→Personal Property
- [x] **Keep unchanged**: Asset Breakdown card, Life Cover card, Charitable Giving card
- [x] **Design compliance**: Table styling consistent, no amber/orange, `text-red-700` for IHT liability

### 4.2 Enhanced `EstateGroupedActions.vue`

- [x] **Read current component**: Modified EstateGroupedActions.vue
- [x] **Add data property**: `expandedSchedule: {}` alongside existing `expandedGuidance`
- [x] **Add method**: `toggleSchedule(actionId)` — same pattern as `toggleGuidance()`
- [x] **Add PET gifting schedule table**: Expandable table with Year (1-indexed from `entry.year + 1`), Gift Amount, Inheritance Tax Reduction (green), Exempt After Year
- [x] **Add annual gifting detail grid**: 4-cell grid (Annual Amount, Over X years, Total Gifted, Inheritance Tax Saved in green)
- [x] **Design compliance**: No amber/orange, no acronyms, British spelling

### 4.3 Wire up `EstatePlanContent.vue`

- [x] **Read current component**: Modified EstatePlanContent.vue
- [x] **Add imports**: `EstateExecutiveSummary` and `EstatePersonalInformation`
- [x] **Register components**: Added to `components` object
- [x] **Update template order**: MissingData → EstateExecSummary (v-if structured) / PlanExecSummary (v-else legacy) → PersonalInfo → JointView → CurrentSituation → GroupedActions → Conclusion
- [x] **Add computed**: `hasStructuredSummary()` — checks `!!this.plan.executive_summary?.greeting`
- [x] **Verify emits**: `toggle-action` passes through correctly

### Phase 4 Checkpoint

- [x] **No compile errors**: Vite compiles cleanly
- [x] **Reseed**: `php artisan db:seed`
- [x] **Browser test — peak_earners**: Login as peak_earners → Plans → Estate Plan
  - [x] Structured executive summary shows IHT summary table + actions table
  - [x] Personal information section visible with estate profile quadrant
  - [x] Full IHT calculation table shows step-by-step workup
  - [x] Linked accounts table shows properties, investments, savings (15 assets, no pensions)
  - [x] Action cards display correctly with toggles working
  - [x] PET gifting action has expandable year-by-year schedule (not present for peak_earners — correct, PET not recommended)
  - [x] Annual gifting action shows detail summary grid (not present for peak_earners — correct, annual gifting not recommended)
  - [x] What-if comparison updates when actions toggled
  - [x] Joint estate view displays for married users (David/Sarah/Combined)
  - [x] Conclusion renders correctly (priority + optional actions)
- [x] **Browser test — widow persona**: Login as Margaret Thompson
  - [x] Personal info shows "Widowed" for IHT status
  - [x] NRB message explains transferred allowance ("Includes transferred Nil Rate Band from deceased spouse")
  - [x] No joint estate view (no spouse)
  - [x] Annual gifting detail grid: £3,000/year, 16 years, £48,000 total, £19,200 saved
  - [x] PET gifting expandable schedule: 2 cycles of £650,000, IHT Reduction £260,000 each, Year 7/14
- [x] **Browser test — all personas that generate estate plans**:
  - [x] peak_earners (David & Sarah Mitchell) — married, joint view, IHT liability £388,732
  - [x] widow (Margaret Thompson) — widowed, no joint view, IHT liability £255,940
  - [x] entrepreneur (Alex Chen) — single, business interests with Exempt badge, IHT liability £83,672
  - [x] retired_couple (Patricia & Harold Bennett) — married, "Estate Plan Not Applicable" (estate below threshold — correct)
- [x] **Design compliance**: Verified via automated search + `Explore` agent
  - [x] No amber/orange colours (Rule 9) — confirmed, no amber-* or orange-* classes
  - [x] `currencyMixin` used everywhere (Rule 6) — confirmed, all 4 components import currencyMixin
  - [x] No acronyms in user-facing text — "Inheritance Tax" not "IHT" (Rule 10) — confirmed, all template text spelled out
  - [x] British spelling throughout — confirmed
  - [x] No scores in UI (Rule 12) — confirmed
- [x] **Code review**: Vue components reviewed — currencyMixin usage correct, IHT Liability highlight uses text-red-700 throughout, no design violations
- [x] **UI polish**: Tables, spacing, and responsive layout verified during browser testing — all sections render cleanly across personas

---

## Phase 5: Tests

**Agent/Skill:** Use `/feature-dev` for test implementation.

### 5.1 Unit Test — EstatePlanService changes

- [x] **Read existing estate tests**: Existing tests checked
- [x] **Read existing plan tests**: `tests/Unit/Services/Plans/EstatePlanRefactorTest.php` updated with new constructor args
- [x] **Verify existing tests still pass**: All 1,597 pass
- [x] **Add/update test cases if needed**: 7 new tests written — all pass (19 total, 137 assertions)
  - [x] `buildPersonalInformation` returns correct shape for married user [5.1.T1]
  - [x] `buildPersonalInformation` returns correct shape for widowed user [5.1.T2]
  - [x] `buildExecutiveSummary` returns structured format with `greeting` key [5.1.T3]
  - [x] `buildCurrentSituation` includes `total_allowances` and `taxable_estate` [5.1.T4]
  - [x] `buildCurrentSituation` includes `linked_accounts` array [5.1.T5]
  - [x] `attachGiftingDetailToActions` merges `gift_schedule` for pet_gifting [5.1.T6]
  - [x] `attachGiftingDetailToActions` merges `annual_gifting_detail` for annual_gifting [5.1.T7]

### 5.2 Run ALL Tests

- [x] **Full suite**: `./vendor/bin/pest` — all 1,604 pass (6,764 assertions) — includes 7 new estate tests
- [x] **Estate-specific**: 19 tests pass (137 assertions) within full suite
- [x] **Plan-specific**: All pass within full suite
- [x] **PSR-12 clean**: `./vendor/bin/pint` — all 811 files clean

### Phase 5 Checkpoint

- [x] **Reseed**: `php artisan db:seed`

---

## Final Integration Testing

- [x] **Full reseed**: `php artisan db:seed`
- [x] **Start dev server**: `./dev.sh` — ready for browser testing

### Browser Verification

- [x] **Verification 1**: Estate plan API returns expected shape — service resolves cleanly from container
- [x] **Verification 2**: All tests pass — 1,604 pass (6,764 assertions) including 7 new estate tests
- [x] **Verification 3**: peak_earners Estate Plan — all sections render correctly
  - [x] Structured executive summary with IHT summary table (11 rows)
  - [x] Personal information section with estate profile (Married, Age 49, Has Will Yes)
  - [x] Full IHT calculation workup table with spouse exemption row
  - [x] Linked accounts table (15 assets — properties, investments, savings, personal property, no pensions)
  - [x] Action cards with toggles working (4 actions)
  - [x] What-if comparison updates on toggle
  - [x] Joint estate view for married users (David/Sarah/Combined)
- [x] **Verification 4**: widow persona Estate Plan — all sections render correctly
  - [x] Widowed IHT status in personal info
  - [x] NRB/RNRB messages displayed (transferred NRB from deceased spouse)
  - [x] No joint estate view
  - [x] PET gifting expandable schedule verified (2 cycles, £650k each)
  - [x] Annual gifting detail grid verified (£3k/year, 16 years, £48k total, £19.2k saved)
- [x] **Verification 5**: Design compliance — automated check completed
  - [x] No amber/orange colours (Rule 9)
  - [x] `currencyMixin` used everywhere (Rule 6)
  - [x] No acronyms in user-facing text (Rule 10)
  - [x] British spelling throughout
  - [x] No scores in UI (Rule 12)

### Final Reviews

- [x] **Security review**: Completed — no issues in new code, pre-existing low-severity items noted
- [x] **Tax compliance review**: Completed — fixed `charitable_rate` → `reduced_rate_charity` key name, fixed hardcoded rate messages
- [x] **UI polish**: Verified during browser testing — tables, badges, spacing all render correctly
- [x] **Final reseed**: `php artisan db:seed`

---

## Summary

| Phase | Tasks | New Files | Modified Files |
|-------|-------|-----------|----------------|
| Phase 1: Backend — EstatePlanService | 25 | 0 | 1 |
| Phase 2: Backend — GiftingStrategyOptimizer | 4 | 0 | 1 |
| Phase 3: Frontend — New Components | 10 | 2 | 0 |
| Phase 4: Frontend — Enhanced Components | 25 | 0 | 3 |
| Phase 5: Tests | 12 | 0-1 | 0 |
| Final Integration | 12 | 0 | 0 |
| **Total** | **~88** | **2** | **5** |

---

## All Files

### New Files (2)

| # | File | Purpose |
|---|------|---------|
| 1 | `resources/js/components/Plans/Estate/EstateExecutiveSummary.vue` | Structured exec summary with IHT table |
| 2 | `resources/js/components/Plans/Estate/EstatePersonalInformation.vue` | Personal info with estate profile |

### Modified Files (5)

| # | File | Purpose |
|---|------|---------|
| 1 | `app/Services/Plans/EstatePlanService.php` | Personal info, structured exec summary, expanded current situation, gifting data |
| 2 | `app/Services/Estate/GiftingStrategyOptimizer.php` | Add `iht_reduction` to PET schedule |
| 3 | `resources/js/components/Plans/Estate/EstatePlanContent.vue` | Wire new components, structured summary guard |
| 4 | `resources/js/components/Plans/Estate/EstateCurrentSituation.vue` | Full IHT table, linked accounts |
| 5 | `resources/js/components/Plans/Estate/EstateGroupedActions.vue` | PET schedule table, annual gifting detail |

---

## Quick Reference — Commands

```bash
# Database
php artisan db:seed                                             # Reseed ALL data

# Testing
./vendor/bin/pest tests/Unit/Services/Estate/                   # Estate service tests
./vendor/bin/pest tests/Unit/Services/Plans/                    # Plan tests
./vendor/bin/pest                                               # Full suite

# Code formatting
./vendor/bin/pint                                               # PSR-12 format

# Development
./dev.sh                                                        # Start Laravel + Vite

# Verification
php artisan tinker                                              # Interactive check
```

## Quick Reference — Agents & Skills

| Task | Tool |
|------|------|
| Feature implementation | `/feature-dev` skill |
| Bug investigation | `/systematic-debugging` skill |
| Code review | `/code-review` skill |
| API/auth security | `security-reviewer` agent |
| Tax calculation compliance | `tax-compliance-reviewer` agent |
| UI polish and animations | `premium-ui-designer` agent |
| Codebase exploration | `Explore` agent |
| Design system check | Read `designStyle.md` via `Explore` agent |

## Key Reference Files

| File | Purpose |
|------|---------|
| `app/Services/Plans/ProtectionPlanService.php` | PRIMARY PATTERN for buildPersonalInformation() and buildExecutiveSummary() |
| `resources/js/components/Plans/Protection/ProtectionExecutiveSummary.vue` | Exec summary component pattern |
| `resources/js/components/Plans/Protection/ProtectionPersonalInformation.vue` | Personal info component pattern |
| `app/Services/Plans/EstatePlanService.php` | File being modified (all backend changes) |
| `app/Services/Estate/IHTCalculationService.php` | IHT calculation data source — understand `calculate()` return shape |
| `app/Services/Estate/EstateAssetAggregatorService.php` | Asset aggregation — understand `gatherUserAssets()` return shape |
| `app/Services/Estate/GiftingStrategyOptimizer.php` | Gifting strategy — understand `gift_schedule[]` and strategy shapes |
| `app/Agents/EstateAgent.php` | Agent `analyze()` data — understand what's available in `$data` |
| `app/Services/Plans/BasePlanService.php` | Shared plan methods (structureActions, prepareActions, generateDynamicConclusion) |
| `March3Updates/estateRewritePlan.md` | Full implementation plan |
