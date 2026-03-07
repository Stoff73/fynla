# Centralise Retirement Plan Actions

**Date:** 2 March 2026
**Branch:** `retirementPlanFix`
**Scope:** Move all retirement action definitions from hardcoded PHP into a database-driven, admin-configurable system

---

## Context

The retirement plan currently generates 10 action types through hardcoded logic spread across `RetirementAgent`, `ContributionOptimizer`, and `BasePlanService`. Trigger thresholds (5% employer match, 10% income gap, etc.), action text, priorities, and what-if impact types are all embedded in PHP code. This makes it impossible to add/remove/edit actions without code changes.

**Goal:** Move all action definitions into a database table, make them fully configurable via a new Admin Panel tab, and consolidate the goal-sourced actions into the same system. Fix the known `Employer_match` what-if bug in the process.

---

## Phase 1: Database Layer

### 1.1 Migration — `retirement_action_definitions` table

**File:** `database/migrations/2026_03_03_000001_create_retirement_action_definitions_table.php`

```
id                    — PK
key                   — string(50), unique — slug like 'employer_match', 'goal_behind_schedule'
source                — string(20) — 'agent' or 'goal'
title_template        — string — supports {placeholders} for goal_name, scheme_name, etc.
description_template  — text — supports {placeholders} for dynamic values
action_template       — string, nullable — guidance text
category              — string(50) — e.g. 'Employer_match', 'Tax Planning', 'Goal'
priority              — enum(critical, high, medium, low)
scope                 — enum(account, portfolio)
what_if_impact_type   — string(30) — 'contribution', 'consolidation', 'tax_optimisation', 'default'
trigger_config        — JSON — structured trigger conditions + editable thresholds
is_enabled            — boolean, default true
sort_order            — smallint, default 100
notes                 — string, nullable — admin notes
timestamps
```

The `trigger_config` JSON stores per-action thresholds:
- `employer_match`: `{ condition: "employee_contribution_percent_below", threshold: 5.0 }`
- `tax_relief`: `{ condition: "higher_rate_taxpayer_below_allowance", threshold: 40000 }`
- `adjust_retirement_age`: `{ condition: "income_gap_exceeds_percentage_of_target", threshold: 0.10, max_suggested_age: 70, age_increase: 3 }`
- `goal_deadline_approaching`: `{ condition: "goal_months_remaining_below_and_progress_below", months_threshold: 6, progress_threshold: 75 }`
- etc.

### 1.2 Model — `RetirementActionDefinition`

**File:** `app/Models/RetirementActionDefinition.php`

- `$casts`: `trigger_config` → array, `is_enabled` → boolean
- Scopes: `getEnabled()`, `getBySource($source)`, `findByKey($key)`
- Template helpers: `renderTitle($vars)`, `renderDescription($vars)`, `renderAction($vars)` — replaces `{placeholder}` with values

### 1.3 Seeder — `RetirementActionDefinitionSeeder`

**File:** `database/seeders/RetirementActionDefinitionSeeder.php`

Seeds all 10 existing action types using `updateOrCreate` on `key`. Add to `DatabaseSeeder.php` after `PlanConfigurationSeeder`. Includes:
- 7 agent-sourced: employer_match, start_contributions, contribution_increase, tax_relief, annual_allowance_exceeded, ni_gaps, adjust_retirement_age
- 3 goal-sourced: goal_no_contribution, goal_behind_schedule, goal_deadline_approaching

**Key fix:** `employer_match` gets `what_if_impact_type: 'contribution'` (currently falls to default 1% gain due to str_contains bug)

### 1.4 Factory — `RetirementActionDefinitionFactory`

**File:** `database/factories/RetirementActionDefinitionFactory.php`

States: `disabled()`, `goalSourced()`

---

## Phase 2: Backend Service

### 2.1 RetirementActionDefinitionService

**File:** `app/Services/Retirement/RetirementActionDefinitionService.php`

Core methods:
- `evaluateAgentActions(array $analysisData): array` — loads enabled agent-sourced definitions, evaluates each trigger against user data, returns recommendations in the existing array format consumed by `structureActions()`
- `evaluateGoalActions(array $linkedGoals): array` — loads enabled goal-sourced definitions, evaluates each against linked goals, returns recommendations
- `getWhatIfImpactType(string $category): string` — looks up the `what_if_impact_type` for a category from definitions (replaces brittle `str_contains()` chain)

Trigger evaluation uses a `match()` on `trigger_config.condition`:
- `employee_contribution_percent_below` → checks each DC pension's employee % against threshold
- `zero_contribution_with_fund_value` → checks fund > 0 but annual contribution <= 0
- `income_gap_positive_and_additional_contribution_required` → checks analysis summary
- `higher_rate_taxpayer_below_allowance` → checks income + contribution totals against threshold
- `annual_allowance_has_excess` → checks analysis annual_allowance.has_excess
- `ni_years_wont_reach_required_by_spa` → checks NI years from StatePension
- `income_gap_exceeds_percentage_of_target` → checks gap vs threshold % of target
- `linked_goal_no_monthly_contribution` → checks goal contribution == 0
- `linked_goal_off_track` → checks is_on_track == false
- `goal_months_remaining_below_and_progress_below` → checks months + progress against thresholds

Each evaluator renders the definition's title/description templates with real values (scheme names, amounts, percentages) and returns the standard recommendation structure.

### 2.2 Integration — RetirementAgent

**File:** `app/Agents/RetirementAgent.php`

- Add `RetirementActionDefinitionService` to constructor
- Replace `generateRecommendations()` body (lines ~142-255): delegate to `$this->actionDefinitionService->evaluateAgentActions($analysisData)`
- `ContributionOptimizer` remains as a calculation helper (its math methods are still used by the service's evaluators) but no longer directly produces recommendations

### 2.3 Integration — RetirementPlanService

**File:** `app/Services/Plans/RetirementPlanService.php`

- Add `RetirementActionDefinitionService` to constructor
- Replace `$goalRecommendations = $this->buildGoalRecommendations(...)` with `$this->actionDefinitionService->evaluateGoalActions($goals['linked'])`
- In `buildWhatIfData()`: replace `str_contains($category, ...)` chain with `$this->actionDefinitionService->getWhatIfImpactType($action['category'])` → `match ($impactType) { ... }`

### 2.4 BasePlanService — No changes

`buildGoalRecommendations()` stays for Investment/Estate plans. Retirement overrides it via the new service.

---

## Phase 3: Admin API

### 3.1 Controller

**File:** `app/Http/Controllers/Api/RetirementActionDefinitionController.php`

Standard CRUD following `AdminController` pattern:
- `index()` — list all definitions ordered by sort_order
- `show($id)` — single definition
- `store(Request)` — create new, validate unique key
- `update($id, Request)` — update existing
- `destroy($id)` — delete
- `toggleEnabled($id)` — quick enable/disable toggle

### 3.2 Routes

**File:** `routes/api.php`

Add within the existing admin middleware group:
```
Route::prefix('admin/retirement-actions')->group(function () {
    Route::get('/', [RetirementActionDefinitionController::class, 'index']);
    Route::get('/{id}', [RetirementActionDefinitionController::class, 'show']);
    Route::post('/', [RetirementActionDefinitionController::class, 'store']);
    Route::put('/{id}', [RetirementActionDefinitionController::class, 'update']);
    Route::delete('/{id}', [RetirementActionDefinitionController::class, 'destroy']);
    Route::patch('/{id}/toggle', [RetirementActionDefinitionController::class, 'toggleEnabled']);
});
```

### 3.3 Validation

**File:** `app/Http/Requests/StoreRetirementActionDefinitionRequest.php`

Validates: key (unique, slug format), source (in: agent,goal), title_template (required), description_template (required), category (required), priority (in: critical,high,medium,low), scope (in: account,portfolio), what_if_impact_type (in: contribution,consolidation,tax_optimisation,default), trigger_config (required, json/array), is_enabled (boolean), sort_order (integer)

---

## Phase 4: Admin Frontend

### 4.1 Admin Panel tab

**File to modify:** `resources/js/views/AdminPanel.vue`

Add "Retirement Actions" tab alongside existing tabs (Dashboard, User Management, Database Backups, Tax Settings).

### 4.2 AdminRetirementActions component

**File:** `resources/js/components/Admin/AdminRetirementActions.vue`

Table listing all action definitions with columns:
- Sort order (drag or input)
- Title template
- Source (agent/goal badge)
- Category
- Priority (badge)
- Scope (badge)
- What-if impact type
- Enabled toggle switch
- Trigger thresholds (key values from trigger_config displayed inline)
- Edit / Delete buttons

### 4.3 RetirementActionModal component

**File:** `resources/js/components/Admin/RetirementActionModal.vue`

Form modal for create/edit with fields:
- Key (slug, unique — readonly on edit)
- Source (agent/goal select)
- Title template (with placeholder hint)
- Description template (textarea with placeholder hint)
- Action template (optional)
- Category
- Priority (select)
- Scope (select)
- What-if impact type (select)
- Trigger config — dynamic form based on condition type, showing editable thresholds
- Enabled toggle
- Sort order
- Notes

### 4.4 Admin service methods

**File to modify:** `resources/js/services/adminService.js`

Add methods: `getRetirementActions()`, `createRetirementAction(data)`, `updateRetirementAction(id, data)`, `deleteRetirementAction(id)`, `toggleRetirementAction(id)`

---

## Phase 5: Testing

### Unit tests

**File:** `tests/Unit/Services/Retirement/RetirementActionDefinitionServiceTest.php`

- Seeds definitions, provides mock analysis data, asserts correct actions produced
- Tests trigger threshold evaluation (e.g. employer match at 4% triggers, at 6% doesn't)
- Tests disabled definitions are skipped
- Tests goal evaluation (no contribution, behind schedule, deadline approaching)
- Tests what-if impact type lookup
- Tests template rendering with placeholders

### Feature tests

**File:** `tests/Feature/Api/RetirementActionDefinitionTest.php`

- Admin CRUD endpoints (create, read, update, delete, toggle)
- Non-admin gets 403
- Validation errors on bad input

### Existing test updates

- `tests/Unit/Services/Plans/` — existing plan tests should still pass (seed the new table in beforeEach)

---

## Files to Create (9)

| File | Purpose |
|------|---------|
| `database/migrations/2026_03_03_000001_create_retirement_action_definitions_table.php` | Table schema |
| `database/seeders/RetirementActionDefinitionSeeder.php` | Seed 10 default actions |
| `database/factories/RetirementActionDefinitionFactory.php` | Test factory |
| `app/Models/RetirementActionDefinition.php` | Eloquent model with template rendering |
| `app/Services/Retirement/RetirementActionDefinitionService.php` | Core trigger evaluation + recommendation generation |
| `app/Http/Controllers/Api/RetirementActionDefinitionController.php` | Admin CRUD API |
| `app/Http/Requests/StoreRetirementActionDefinitionRequest.php` | Validation rules |
| `resources/js/components/Admin/AdminRetirementActions.vue` | Admin table view |
| `resources/js/components/Admin/RetirementActionModal.vue` | Admin create/edit modal |

## Files to Modify (6)

| File | Change |
|------|--------|
| `database/seeders/DatabaseSeeder.php` | Add RetirementActionDefinitionSeeder |
| `app/Agents/RetirementAgent.php` | Inject service, delegate generateRecommendations |
| `app/Services/Plans/RetirementPlanService.php` | Inject service, use for goals + what-if |
| `resources/js/views/AdminPanel.vue` | Add Retirement Actions tab |
| `resources/js/services/adminService.js` | Add CRUD methods |
| `routes/api.php` | Add admin retirement-actions routes |

---

## Execution Order

1. Migration + Model + Factory + Seeder (database layer)
2. Run migration + seed
3. RetirementActionDefinitionService (new service)
4. RetirementAgent integration (swap generateRecommendations)
5. RetirementPlanService integration (swap goal recs + what-if mapping)
6. Run existing plan tests — verify all 42 pass
7. Admin controller + routes + validation
8. Admin frontend (tab + table + modal)
9. New tests (unit + feature)
10. Reseed + browser test

---

## Verification

1. `php artisan migrate && php artisan db:seed` — table created, 10 rows seeded
2. `./vendor/bin/pest tests/Unit/Services/Plans/` — all 42 existing tests pass
3. Browser: login as admin → Admin Panel → Retirement Actions tab → see 10 actions
4. Browser: toggle one action off → generate retirement plan → that action absent
5. Browser: edit a threshold (e.g. employer match 5% → 8%) → regenerate plan → observe changed trigger behaviour
6. Browser: create a new action → see it appear in the retirement plan
7. `./vendor/bin/pest` — all tests pass
