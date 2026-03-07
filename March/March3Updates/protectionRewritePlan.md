# Protection Plan Upgrade — Implementation Plan

## Context

The Protection Plan currently uses a narrative-only executive summary, hardcoded recommendation logic in `ProtectionPlanService`, and a custom conclusion format. It needs to be upgraded to match the structured formatting used by the Investment and Retirement plans — while **preserving the existing horizontal bar chart** (`PlanWhatIfChart`) and action toggle system (`PlanActionsList` + `PlanActionCard`) which work correctly.

## What Changes

| Area | Current | After |
|------|---------|-------|
| Executive Summary | Narrative-only (`PlanExecutiveSummary`) | Structured: greeting, coverage gaps table, key actions table, closing (`ProtectionExecutiveSummary`) |
| Personal Information | Not shown | New section with personal details, family, financial overview, protection profile |
| Recommendation Logic | Hardcoded in `ProtectionPlanService` (methods `extractRecommendations` + `ensureGapActions`) | DB-driven via `ProtectionActionDefinitionService` with `protection_action_definitions` table |
| Conclusion | Custom `buildProtectionConclusion()` | Shared `generateDynamicConclusion()` from `BasePlanService` (essential/optional split) |
| Admin UI | None | Protection Actions tab in Admin Panel with CRUD modal |

## What Does NOT Change

- `ProtectionCurrentSituation.vue` — untouched
- `PlanWhatIfChart.vue` (horizontal bar chart) — untouched
- `PlanActionsList.vue` + `PlanActionCard.vue` (toggle system) — untouched
- `PlanWhatIfComparison.vue` + `ProtectionWhatIfControls.vue` — untouched
- `buildWhatIfData()` in `ProtectionPlanService` — untouched
- `ComprehensiveProtectionPlanService` — untouched

---

## Phase 1: Database Layer

### 1a. Migration — `database/migrations/2026_03_05_000002_create_protection_action_definitions_table.php`

Create `protection_action_definitions` table with identical schema to `investment_action_definitions`:
- `key` (string, unique), `source` (string), `title_template`, `description_template`, `action_template` (nullable)
- `category`, `priority`, `scope`, `what_if_impact_type`
- `trigger_config` (JSON), `is_enabled` (boolean), `sort_order` (integer), `notes` (nullable)
- Safety check: `if (Schema::hasTable(...)) return;`

### 1b. Model — `app/Models/ProtectionActionDefinition.php`

Clone `InvestmentActionDefinition` model pattern exactly:
- Same fillable, casts, scopes (`enabled`, `bySource`), static methods (`findByKey`, `getEnabled`, `getEnabledBySource`)
- Same `renderTitle()`, `renderDescription()`, `renderAction()` template methods

### 1c. Factory — `database/factories/ProtectionActionDefinitionFactory.php`

For testing. Follow `InvestmentActionDefinitionFactory` pattern.

### 1d. Seeder — `database/seeders/ProtectionActionDefinitionSeeder.php`

Seed ~10 action definitions using `updateOrCreate` on `key`. These replace the hardcoded logic in `extractRecommendations()` and `ensureGapActions()`:

**Coverage gap actions (source: `gap`):**
1. `life_insurance_gap` — Triggers when `coverage_analysis.life_insurance.gap > 0`
2. `critical_illness_gap` — Triggers when `coverage_analysis.critical_illness.gap > 0`
3. `income_protection_gap` — Triggers when `coverage_analysis.income_protection.gap > 0`

**Strategy actions (source: `agent`):**
4. `increase_life_cover` — From optimized strategy, life insurance recommendations
5. `add_critical_illness` — From optimized strategy, critical illness recommendations
6. `add_income_protection` — From optimized strategy, income protection recommendations
7. `review_existing_policies` — Triggers when policies exist but may be suboptimal
8. `consolidate_policies` — Triggers when multiple overlapping policies detected
9. `protection_profile_missing` — Triggers when no protection profile exists
10. `no_policies_warning` — Triggers when no policies and gaps exist

Each definition has `trigger_config` JSON (e.g., `{"condition": "gap_exists", "coverage_type": "life_insurance", "threshold": 0}`) and template strings with `{placeholder}` tokens (e.g., `{gap_amount}`, `{coverage_type}`).

### 1e. Update `database/seeders/DatabaseSeeder.php`

Add `ProtectionActionDefinitionSeeder::class` to the `run()` method.

---

## Phase 2: Backend Services

### 2a. New — `app/Services/Protection/ProtectionActionDefinitionService.php`

Core service that evaluates DB-driven action definitions against user's protection data. Constructor injects `TaxConfigService`.

**Key method: `evaluateActions(array $comprehensivePlan): array`**
- Loads all enabled `ProtectionActionDefinition` records
- For each definition, evaluates its `trigger_config` against the `$comprehensivePlan` data:
  - Gap conditions check `coverage_analysis.{type}.gap > threshold`
  - Strategy conditions check `optimized_strategy.recommendations` for matching categories
  - Profile conditions check `user_profile` fields
- Renders templates with actual values (`{gap_amount}` → formatted currency)
- Returns array of recommendation arrays matching the existing format: `priority`, `category`, `action`, `rationale`, `impact`, `estimated_cost`, `impact_parameters`, `timeframe`

### 2b. Modify — `app/Services/Plans/ProtectionPlanService.php`

**Changes:**
1. Add `ProtectionActionDefinitionService` to constructor injection
2. Add `personal_information` and `linked_goals`/`unlinked_goals` to the return array in `generatePlan()`
3. Replace `extractRecommendations()` — delegate to `$this->actionDefinitionService->evaluateActions($comprehensivePlan)` instead of hardcoded logic
4. Remove `ensureGapActions()` — now handled by DB definitions
5. Replace `buildExecutiveSummary()` — return structured array: `greeting`, `opening`, `introduction`, `coverage_summary` (array of gaps with name/need/coverage/gap/status), `actions_summary` (top actions with title/priority), `total_actions`, `closing`
6. Add `buildPersonalInformation(User $user, array $comprehensivePlan): array` — returns `full_name`, `date_of_birth`, `age`, `marital_status`, `spouse_name`, `children`, `gross_income`, `net_income`, `annual_expenditure`, `disposable_income`, `monthly_disposable`, `occupation`, `smoker_status`, `health_status`, `retirement_age`
7. Replace `buildProtectionConclusion()` call with `$this->generateDynamicConclusion()` from `BasePlanService`, passing `'protection'` as plan type
8. Remove `describeActions()`, `buildProtectionConclusion()`, `prefixWithArticle()`, `buildEmptyExecutiveSummary()` helper methods
9. Keep `buildWhatIfData()`, `buildCurrentSituation()`, `checkDataCompleteness()`, `getRecommendations()` unchanged

**Updated `generateDynamicConclusion()`:** The BasePlanService method references "retirement goal" in its text. Need to make it plan-type aware — update the summary text to use generic language or pass plan type. Check if this is already parameterised (it takes `$planType` param but the text says "retirement goal"). Will update BasePlanService summary text to be plan-type aware.

---

## Phase 3: Admin Panel

### 3a. Controller — `app/Http/Controllers/Api/ProtectionActionDefinitionController.php`

Clone `InvestmentActionDefinitionController` exactly, replacing model references with `ProtectionActionDefinition`.

### 3b. Form Request — `app/Http/Requests/StoreProtectionActionDefinitionRequest.php`

Clone `StoreInvestmentActionDefinitionRequest`, updating class name.

### 3c. Routes — `routes/api.php`

Add after investment-actions block:
```php
Route::middleware(['auth:sanctum', 'permission:admin.access', 'throttle:30,1'])
    ->prefix('admin/protection-actions')->group(function () {
        // index, show, store, update, destroy, toggleEnabled
    });
```

### 3d. Admin Service — `resources/js/services/adminService.js`

Add 5 methods following the investment pattern:
- `getProtectionActions()`, `createProtectionAction(data)`, `updateProtectionAction(id, data)`, `deleteProtectionAction(id)`, `toggleProtectionAction(id)`

### 3e. Admin Vue — `resources/js/components/Admin/AdminProtectionActions.vue`

Clone `AdminInvestmentActions.vue`, updating labels to "Protection Action Definitions" and API calls to use `adminService.getProtectionActions()` etc.

### 3f. Admin Modal — `resources/js/components/Admin/ProtectionActionModal.vue`

Clone `InvestmentActionModal.vue`, updating title/labels for protection context.

### 3g. AdminPanel.vue — Add Protection Actions tab

Add tab `{ id: 'protection-actions', label: 'Protection Actions' }`, import and register `AdminProtectionActions`, add icon, add short label.

---

## Phase 4: Frontend Components

### 4a. New — `resources/js/components/Plans/Protection/ProtectionExecutiveSummary.vue`

Follow `InvestmentExecutiveSummary.vue` pattern (108 lines). Sections:
- Greeting paragraph
- Opening paragraph (if present)
- Introduction paragraph
- **Coverage Summary Table** (instead of Goals table): columns Name, Need, Coverage, Gap, Status — with green/red badges for "Adequate"/"Gap"
- **Key Actions Table**: columns Action, Priority — with priority badges
- "Showing top X of Y actions" note if truncated
- Closing paragraph

Uses `currencyMixin` for formatting.

### 4b. New — `resources/js/components/Plans/Protection/ProtectionPersonalInformation.vue`

Follow `InvestmentPersonalInformation.vue` pattern (128 lines). 2x2 grid:
- **Personal Details**: Full Name, Date of Birth, Age, Marital Status
- **Family**: Spouse, Children
- **Financial Overview**: Gross Income, Net Income, Annual Expenditure, Disposable Income (annual), Disposable Income (monthly)
- **Protection Profile** (replaces Risk Profile): Occupation, Smoker Status, Health Status, Planned Retirement Age

Uses `currencyMixin`.

### 4c. Modify — `resources/js/components/Plans/Protection/ProtectionPlanContent.vue`

Update the template to add new sections while keeping existing ones:
1. `PlanMissingDataPrompt` (keep)
2. `ProtectionExecutiveSummary` (NEW — conditional, with fallback to `PlanExecutiveSummary` for legacy data)
3. `ProtectionPersonalInformation` (NEW)
4. `PlanGoalSection` (NEW — linked/unlinked goals, same as investment)
5. `ProtectionCurrentSituation` (keep — unchanged)
6. `PlanActionsList` (keep — unchanged)
7. `PlanWhatIfComparison` with `PlanWhatIfChart` (keep — unchanged)
8. `PlanConclusion` (keep — now receives `generateDynamicConclusion` format with essential_actions/optional_actions)

Add imports for `ProtectionExecutiveSummary`, `ProtectionPersonalInformation`, `PlanGoalSection`.
Add `hasStructuredSummary` computed (check for `greeting` field, same pattern as investment).

---

## Phase 5: Tests

### 5a. Feature — `tests/Feature/Api/ProtectionActionDefinitionTest.php`

Admin CRUD endpoint tests following `InvestmentActionDefinitionTest.php` pattern.

### 5b. Unit — `tests/Unit/Services/Protection/ProtectionActionDefinitionServiceTest.php`

Test trigger evaluation logic: gap detection, strategy matching, template rendering, disabled definitions skipped.

---

## Files Summary

### New Files (13)
| File | Purpose |
|------|---------|
| `database/migrations/2026_03_05_000002_create_protection_action_definitions_table.php` | Migration |
| `database/seeders/ProtectionActionDefinitionSeeder.php` | Seed ~10 definitions |
| `database/factories/ProtectionActionDefinitionFactory.php` | Test factory |
| `app/Models/ProtectionActionDefinition.php` | Eloquent model |
| `app/Services/Protection/ProtectionActionDefinitionService.php` | DB-driven action evaluation |
| `app/Http/Controllers/Api/ProtectionActionDefinitionController.php` | Admin CRUD API |
| `app/Http/Requests/StoreProtectionActionDefinitionRequest.php` | Validation |
| `resources/js/components/Admin/AdminProtectionActions.vue` | Admin table |
| `resources/js/components/Admin/ProtectionActionModal.vue` | Admin edit modal |
| `resources/js/components/Plans/Protection/ProtectionExecutiveSummary.vue` | Structured summary |
| `resources/js/components/Plans/Protection/ProtectionPersonalInformation.vue` | Personal info section |
| `tests/Feature/Api/ProtectionActionDefinitionTest.php` | API tests |
| `tests/Unit/Services/Protection/ProtectionActionDefinitionServiceTest.php` | Service tests |

### Modified Files (7)
| File | Change |
|------|--------|
| `app/Services/Plans/ProtectionPlanService.php` | Delegate to DB service, structured exec summary, personal info, dynamic conclusion |
| `app/Services/Plans/BasePlanService.php` | Make `generateDynamicConclusion()` summary text plan-type aware |
| `database/seeders/DatabaseSeeder.php` | Add `ProtectionActionDefinitionSeeder` |
| `routes/api.php` | Add protection-action-definitions CRUD routes |
| `resources/js/services/adminService.js` | Add 5 protection action API methods |
| `resources/js/views/Admin/AdminPanel.vue` | Add Protection Actions tab |
| `resources/js/components/Plans/Protection/ProtectionPlanContent.vue` | Add new sections, keep bar chart and toggle system |

---

## Verification

1. `php artisan migrate` — creates `protection_action_definitions` table
2. `php artisan db:seed --class=ProtectionActionDefinitionSeeder` — seeds ~10 definitions
3. `php artisan tinker --execute="echo \App\Models\ProtectionActionDefinition::count();"` — should return ~10
4. `php artisan route:list --path=protection-action` — should show 6 routes
5. `./vendor/bin/pest tests/Feature/Api/ProtectionActionDefinitionTest.php` — API tests pass
6. `./vendor/bin/pest tests/Unit/Services/Protection/ProtectionActionDefinitionServiceTest.php` — service tests pass
7. Browser test: Login as David Mitchell (peak_earners) — protection plan shows structured executive summary, personal info, coverage table, current situation, actions with horizontal bar chart updating on toggle, conclusion with essential/optional split
8. Browser test: Admin panel shows Protection Actions tab with ~10 definitions, toggle/edit/delete working
9. `php artisan db:seed` — final reseed
