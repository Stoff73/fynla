# Estate Plan Upgrade: Structured Executive Summary, Personal Info, Full IHT Table, Gifting Schedules

## Context
The Estate Plan currently uses a narrative-only executive summary and basic metric cards. It needs to match the structured pattern already implemented for Protection, Retirement, and Investment plans — with a structured exec summary (IHT summary table + actions table), personal information section, full IHT calculation workup table, linked accounts list, and year-by-year gifting schedule detail in recommendations. No goals section (estate has no goals).

## Files to Modify/Create

| File | Action |
|------|--------|
| `app/Services/Plans/EstatePlanService.php` | Modify |
| `app/Services/Estate/GiftingStrategyOptimizer.php` | Modify (minor) |
| `resources/js/components/Plans/Estate/EstatePlanContent.vue` | Modify |
| `resources/js/components/Plans/Estate/EstateCurrentSituation.vue` | Modify |
| `resources/js/components/Plans/Estate/EstateGroupedActions.vue` | Modify |
| `resources/js/components/Plans/Estate/EstateExecutiveSummary.vue` | **New** |
| `resources/js/components/Plans/Estate/EstatePersonalInformation.vue` | **New** |

## Step 1: Backend — `EstatePlanService.php`

### 1a. Inject `EstateAssetAggregatorService`
Add to constructor: `private readonly EstateAssetAggregatorService $assetAggregator`

### 1b. Add `buildPersonalInformation(User $user, array $data): array`
Follow `ProtectionPlanService::buildPersonalInformation()` pattern (lines 196-246). Same fields: `full_name`, `date_of_birth`, `age`, `marital_status`, `spouse_name`, `children[]`, `gross_income`, `net_income`, `annual_expenditure`, `disposable_income`, `monthly_disposable`. Estate-specific 4th quadrant fields:
- `estimated_age_at_death` — from `$data['iht_calculation']['estimated_age_at_death']`
- `years_to_death` — from `$data['iht_calculation']['years_to_death']`
- `marital_status_iht` — `married`/`widowed`/`single` (derived from IHT calc flags)
- `has_will` — `Will::where('user_id', ...)->exists()`

### 1c. Refactor `buildExecutiveSummary()` to structured format
Change signature: `buildExecutiveSummary(User $user, array $data, array $actions)`
Return structured keys: `greeting`, `opening`, `introduction`, `iht_summary[]` (label/value/format/highlight rows), `actions_summary[]` (title/priority — top 5 enabled), `total_actions`, `closing`
Update `buildEmptyExecutiveSummary()` to include `greeting` key for structured detection.

### 1d. Expand `buildCurrentSituation(array $data, User $user)`
Change signature to accept `User $user`. Add to `iht_calculation` key: `total_allowances`, `taxable_estate`, `iht_rate`, `iht_rate_percent`, `iht_rate_type`, `iht_rate_message`, `is_married`, `is_widowed`, `nrb_message`, `rnrb_message`. Add new `linked_accounts` key via `buildLinkedAccountsList(User $user)` — calls `$this->assetAggregator->gatherUserAssets($user)`, returns `[{name, type, value, is_exempt}]` sorted by value desc.

### 1e. Thread gifting data through to actions
In `enrichRecommendations()`, access `$data['gifting_opportunities']['strategies']` and attach:
- For `pet_gifting`: `gift_schedule[]`, `seven_year_cycles`, `amount_per_cycle`
- For `annual_gifting`: `annual_gifting_detail{annual_amount, years, total_gifted, iht_saved}`

Post-process after `prepareActions()` with `attachGiftingDetailToActions($actions, $recommendations)` to re-merge gifting detail by category.

### 1f. Update `generatePlan()` return
Add `'personal_information' => $this->buildPersonalInformation($user, $data)`. Update call sites for changed signatures.

## Step 2: Backend — `GiftingStrategyOptimizer.php` (minor)
In `calculatePETStrategy()`, add `iht_reduction` to each `$giftSchedule[]` entry: `'iht_reduction' => round($amountPerCycle * $ihtRate, 2)`. Avoids hardcoding 40% on the frontend.

## Step 3: New `EstateExecutiveSummary.vue`
Follow `ProtectionExecutiveSummary.vue` exactly. Replace "Coverage Summary" table with "Inheritance Tax Summary" table (2-column: Item / Amount). Each row from `summary.iht_summary[]` — format as currency or percent. IHT Liability row highlighted with `bg-red-50`. Keep identical actions summary table and priority badges.

## Step 4: New `EstatePersonalInformation.vue`
Follow `ProtectionPersonalInformation.vue` exactly. Same 2x2 grid: Personal Details + Family + Financial Overview + **Estate Profile**. Estate Profile shows: Estimated Age at Death, Years to Planning Horizon, Inheritance Tax Status (married/widowed/single), Has Will (Yes/No).

## Step 5: Enhanced `EstateCurrentSituation.vue`
- **Replace** basic IHT metric cards with a full IHT workup table: Gross Estate → Less Liabilities → Net Estate → Less NRB → Less RNRB → Less Spouse Exemption → Total Allowances → Taxable Estate → IHT Rate → IHT Liability (red highlight) → Effective Rate. Show NRB/RNRB explanatory messages below.
- **Add** "Assets Included in Estate" table from `situation.linked_accounts[]`: Asset name, Type badge, Value. Add `formatAssetType()` method and `hasLinkedAccounts` computed.

## Step 6: Enhanced `EstateGroupedActions.vue`
- Add expandable PET gifting schedule table inside `pet_gifting` action cards: Year, Gift Amount, IHT Reduction, Exempt After Year. Use `action.gift_schedule[]` data. Toggle with `expandedSchedule` data property.
- Add annual gifting summary grid inside `annual_gifting` action cards: Annual Amount, Over X years, Total Gifted, IHT Saved. From `action.annual_gifting_detail`.

## Step 7: Wire up `EstatePlanContent.vue`
- Import and use `EstateExecutiveSummary` with `hasStructuredSummary` guard (falls back to `PlanExecutiveSummary` for legacy/cached plans)
- Add `EstatePersonalInformation :info="plan.personal_information"`
- Order: MissingData → ExecutiveSummary → PersonalInfo → JointView → CurrentSituation → GroupedActions → Conclusion

## Implementation Order
1. `EstatePlanService.php` (all backend changes)
2. `GiftingStrategyOptimizer.php` (add `iht_reduction` to PET schedule)
3. `EstateExecutiveSummary.vue` (new)
4. `EstatePersonalInformation.vue` (new)
5. `EstateCurrentSituation.vue` (enhanced)
6. `EstateGroupedActions.vue` (gifting tables)
7. `EstatePlanContent.vue` (wire components)
8. Seed + test in browser

## Verification
1. `php artisan db:seed` to ensure data is fresh
2. Log in and navigate to Estate Plan for peak_earners persona (David & Sarah Mitchell — married with IHT liability)
3. Verify structured executive summary shows IHT summary table + actions
4. Verify personal information shows estate profile quadrant
5. Verify IHT calculation table shows full step-by-step workup
6. Verify linked accounts table shows properties, investments, savings
7. Verify PET gifting action has expandable year-by-year schedule
8. Verify annual gifting action shows detail summary
9. Test widow persona (Margaret Thompson) for widowed IHT status
10. Run `./vendor/bin/pest` to verify no test regressions
