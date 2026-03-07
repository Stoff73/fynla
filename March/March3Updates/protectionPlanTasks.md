# Protection Plan Rewrite — Task List

**Source plan:** `March3Updates/protectionRewritePlan.md`
**Branch:** `protectionPlan`
**Prerequisite:** Investment & Savings Plan (`investmentSavePlan`) merged to main (DONE)

---

## Pre-flight

- [x] **Confirm branch is correct**: `git branch --show-current` should show `protectionPlan`
- [x] **Confirm investment code is available**: Check `app/Services/Investment/InvestmentActionDefinitionService.php` exists
- [ ] **Seed database**: `php artisan db:seed`
- [ ] **Run existing tests**: `./vendor/bin/pest tests/Unit/Services/Plans/` — all pass

---

## Phase 1: Database Layer

**Agent/Skill:** Use `/feature-dev` for guided implementation. Use `database-optimizer` agent to review schema design.

### 1.1 Migration — `protection_action_definitions` table

- [x] **Read reference migration**: Read `database/migrations/*_create_investment_action_definitions_table.php`
- [x] **Create migration file**: `database/migrations/2026_03_05_000002_create_protection_action_definitions_table.php`
  - Table name: `protection_action_definitions`
  - Columns: `id`, `key` (string, unique), `source` (string, indexed), `title_template` (text), `description_template` (text), `action_template` (text, nullable), `category` (string), `priority` (enum: critical, high, medium, low), `scope` (string), `what_if_impact_type` (string), `trigger_config` (JSON), `is_enabled` (boolean, default true, indexed), `sort_order` (integer, default 0, indexed), `notes` (text, nullable), `timestamps`
  - Indexes: `source`, `is_enabled`, `sort_order`
  - Safety check: `if (Schema::hasTable('protection_action_definitions')) return;`
- [ ] **Run migration**: `php artisan migrate`
- [ ] **Verify table exists**: `php artisan tinker` → `Schema::hasTable('protection_action_definitions')`

### 1.2 Model — `ProtectionActionDefinition`

- [x] **Read reference model**: Read `app/Models/InvestmentActionDefinition.php` (115 lines)
- [x] **Create model file**: `app/Models/ProtectionActionDefinition.php`
  - Mirror `InvestmentActionDefinition` exactly
  - `$casts`: `trigger_config` → `array`, `is_enabled` → `boolean`
  - Scopes: `enabled()`, `bySource($source)`
  - Static helpers: `findByKey($key)`, `getEnabled()`, `getEnabledBySource($source)`
  - Template methods: `renderTitle($vars)`, `renderDescription($vars)`, `renderAction($vars)` — replace `{placeholder}` tokens with values
- [ ] **Verify model loads**: `php artisan tinker` → `new \App\Models\ProtectionActionDefinition()`

### 1.3 Seeder — ~10 action definitions

- [x] **Read reference seeder**: Read `database/seeders/InvestmentActionDefinitionSeeder.php`
- [x] **Read current hardcoded logic**: Read `app/Services/Plans/ProtectionPlanService.php` methods `extractRecommendations()` and `ensureGapActions()` — extract text and conditions
- [x] **Create seeder file**: `database/seeders/ProtectionActionDefinitionSeeder.php`
  - Use `updateOrCreate` on `key` field for idempotency
  - ~10 definitions:
    - Gap-sourced (3): `life_insurance_gap`, `critical_illness_gap`, `income_protection_gap`
    - Agent-sourced strategy (4): `increase_life_cover`, `add_critical_illness`, `add_income_protection`, `review_existing_policies`
    - Agent-sourced general (3): `consolidate_policies`, `protection_profile_missing`, `no_policies_warning`
  - Each definition must include: key, source, title_template (with `{placeholders}`), description_template, action_template, category, priority, scope, what_if_impact_type, trigger_config (JSON), is_enabled, sort_order
  - Template text: British spelling, no acronyms (Rule 10)
  - **Reference existing hardcoded text** from `ProtectionPlanService::extractRecommendations()` and `ensureGapActions()` for realistic wording
- [ ] **Run seeder**: `php artisan db:seed --class=ProtectionActionDefinitionSeeder`
- [ ] **Verify rows**: `php artisan tinker` → `\App\Models\ProtectionActionDefinition::count()`

### 1.4 Factory

- [x] **Read reference factory**: Read `database/factories/InvestmentActionDefinitionFactory.php`
- [x] **Create factory file**: `database/factories/ProtectionActionDefinitionFactory.php`
  - Default state with sensible values
  - `disabled()` state: `is_enabled => false`
  - `gapSourced()` state: `source => 'gap'`
- [x] **Verify factory works**: All states verified

### 1.5 Register in DatabaseSeeder

- [x] **Read current DatabaseSeeder**: Read `database/seeders/DatabaseSeeder.php`
- [x] **Add seeder call**: Add `$this->call(ProtectionActionDefinitionSeeder::class)` after `InvestmentActionDefinitionSeeder`
- [ ] **Full reseed**: `php artisan db:seed`
- [ ] **Verify both tables seeded**: investment + protection definitions present

### Phase 1 Checkpoint

- [ ] **Run all existing tests**: `./vendor/bin/pest tests/Unit/Services/Plans/` — all pass
- [ ] **Code review (Phase 1)**: Use `/code-review` skill — review migration, model, seeder, factory
- [ ] **Schema review**: Use `database-optimizer` agent — review schema design and indexes
- [ ] **Reseed**: `php artisan db:seed`

---

## Phase 2: Backend Service

**Agent/Skill:** Use `/feature-dev` for guided implementation. Read investment reference files first, then implement protection equivalents.

### 2.1 ProtectionActionDefinitionService

- [x] **Read primary reference**: Read `app/Services/Investment/InvestmentActionDefinitionService.php` — this is THE pattern to follow
- [x] **Read supporting files for context**:
  - Read `app/Services/Plans/ProtectionPlanService.php` — understand `extractRecommendations()` and `ensureGapActions()` logic to replace
  - Read `app/Services/Protection/ComprehensiveProtectionPlanService.php` — understand comprehensive plan data shapes
  - Read `app/Agents/ProtectionAgent.php` — understand current recommendation pipeline
- [x] **Create service file**: `app/Services/Protection/ProtectionActionDefinitionService.php`

  **Constructor dependencies:**
  - ~~`TaxConfigService`~~ **DEVIATION**: No constructor; TaxConfigService not needed (no tax calculations in protection evaluations). Uses `FormatsCurrency` trait instead.

  **Public method: `evaluateActions()`**
  - [x] Signature: `evaluateActions(array $comprehensivePlan): array`
  - [x] Load enabled definitions: `ProtectionActionDefinition::getEnabled()`
  - [x] Dispatch each definition to appropriate trigger evaluator via `match()` on `trigger_config['condition']`
  - [x] Return array of recommendation arrays matching existing format: `priority`, `category`, `action`, `rationale`, `impact`, `estimated_cost`, `impact_parameters`, `timeframe`

  **Private trigger evaluators — Coverage gaps (3):**
  - [x] ~~`evaluateLifeInsuranceGap($definition, $coverageAnalysis)`~~ — Consolidated into generic `evaluateGapCondition()` that handles all 3 gap types via `coverage_type` field. Functionally equivalent.
  - [x] ~~`evaluateCriticalIllnessGap($definition, $coverageAnalysis)`~~ — Same (handled by `evaluateGapCondition()`)
  - [x] ~~`evaluateIncomeProtectionGap($definition, $coverageAnalysis)`~~ — Same (handled by `evaluateGapCondition()`)

  **Private trigger evaluators — Strategy (4):**
  - [x] ~~`evaluateIncreaseLifeCover($definition, $strategyRecs)`~~ — Consolidated into generic `evaluateStrategyCondition()` that matches on `category_match`. Functionally equivalent.
  - [x] ~~`evaluateAddCriticalIllness($definition, $strategyRecs)`~~ — Same
  - [x] ~~`evaluateAddIncomeProtection($definition, $strategyRecs)`~~ — Same
  - [x] ~~`evaluateReviewPolicies($definition, $comprehensivePlan)`~~ — Handled by `evaluateStrategyCondition()` path

  **Private trigger evaluators — General (3):**
  - [x] ~~`evaluateConsolidatePolicies($definition, $comprehensivePlan)`~~ — Named `evaluateMultiplePolicies()` instead
  - [x] `evaluateProfileMissing($definition, $comprehensivePlan)` — no protection profile
  - [x] ~~`evaluateNoPoliciesWarning($definition, $comprehensivePlan)`~~ — Named `evaluateNoPoliciesWithGaps()` instead

  **Template variable helpers:**
  - [x] Build template variables from comprehensive plan data (gap amounts, coverage amounts, need amounts, monthly costs, etc.)
  - [x] Use `renderTitle($vars)`, `renderDescription($vars)` on each definition. Note: `renderAction($vars)` exists on model but is not called in the service.

- [ ] **Verify service instantiates**: `php artisan tinker` → resolve from container

### 2.2 Modify ProtectionPlanService

- [x] **Read current file**: Read `app/Services/Plans/ProtectionPlanService.php` (652 lines)
- [x] **Read BasePlanService**: Read `app/Services/Plans/BasePlanService.php` — understand `generateDynamicConclusion()` + `buildPersonalInformation()` patterns

**Inject new dependency:**
- [x] Add `ProtectionActionDefinitionService` to constructor

**Update `generatePlan()` return array:**
- [x] Add `personal_information` key (via new `buildPersonalInformation()`)
- [x] Add `linked_goals` and `unlinked_goals` keys
- [x] Update `executive_summary` to structured format
- [x] Replace `$this->buildProtectionConclusion()` with `$this->generateDynamicConclusion()`

**Replace `extractRecommendations()`:**
- [x] Delegate to `$this->actionDefinitionService->evaluateActions($comprehensivePlan)` — Method renamed to `getRecommendations()` (line 73), delegates correctly at line 80
- [x] Remove hardcoded recommendation building logic

**Remove dead methods:**
- [x] `ensureGapActions()` — now handled by DB definitions
- [x] `buildProtectionConclusion()` — replaced by `generateDynamicConclusion()`
- [x] `describeActions()` — only used by old conclusion
- [x] `prefixWithArticle()` — only used by old exec summary
- [x] `buildEmptyExecutiveSummary()` — replace with structured empty response

**New method: `buildExecutiveSummary()` (rewrite):**
- [x] Return structured array: `greeting`, `opening`, `introduction`, `coverage_summary` (array of {name, need, coverage, gap, status}), `actions_summary` (top actions with title/priority), `total_actions`, `closing`
- [x] Reference `InvestmentPlanService::buildExecutiveSummary()` for pattern

**New method: `buildPersonalInformation()`:**
- [x] Return: `full_name`, `date_of_birth`, `age`, `marital_status`, `spouse_name`, `children`, `gross_income`, `net_income`, `annual_expenditure`, `disposable_income`, `monthly_disposable`, `occupation`, `smoker_status`, `health_status`, `retirement_age`
- [x] Reference `InvestmentPlanService::buildPersonalInformation()` for pattern

**Keep unchanged:**
- [x] `buildWhatIfData()` — powers horizontal bar chart
- [x] `buildCurrentSituation()` — feeds ProtectionCurrentSituation.vue
- [x] `checkDataCompleteness()` — data completeness checks
- [x] `getRecommendations()` — update to delegate to service

### 2.3 Update BasePlanService

- [x] **Read `generateDynamicConclusion()`** — check if "retirement goal" text is hardcoded
- [x] **Make plan-type aware**: Update summary text to reference the correct plan type (protection/investment/retirement) instead of hardcoding "retirement goal"

### Phase 2 Checkpoint

- [ ] **Run all existing tests**: `./vendor/bin/pest tests/Unit/Services/Plans/` — all pass
- [ ] **Run protection-specific tests**: `./vendor/bin/pest tests/Unit/Services/Protection/` — all pass
- [ ] **Code review (Phase 2)**: Use `/code-review` skill — review service, plan service changes, and BasePlanService update
- [ ] **Security review**: Use `security-reviewer` agent — review new service for input validation, data exposure
- [ ] **Tax compliance review**: Use `tax-compliance-reviewer` agent — verify no hardcoded tax values in new service
- [ ] **Reseed**: `php artisan db:seed`

---

## Phase 3: Admin Panel

**Agent/Skill:** Use `/feature-dev` for guided implementation. Mirror investment admin pattern exactly.

### 3.1 Backend — Controller

- [x] **Read reference controller**: Read `app/Http/Controllers/Api/InvestmentActionDefinitionController.php` (153 lines)
- [x] **Create controller**: `app/Http/Controllers/Api/ProtectionActionDefinitionController.php`
  - Mirror investment controller exactly, replacing `InvestmentActionDefinition` with `ProtectionActionDefinition`
  - Methods: index(), show(), store(), update(), destroy(), toggleEnabled()

### 3.2 Backend — Form Request

- [x] **Read reference request**: Read `app/Http/Requests/StoreInvestmentActionDefinitionRequest.php`
- [x] **Create request**: `app/Http/Requests/StoreProtectionActionDefinitionRequest.php`
  - Update class name, protection-specific what_if_impact_type values if needed

### 3.3 Backend — Routes

- [x] **Read current routes**: Read `routes/api.php`
- [x] **Add protection admin routes**: 6 routes under `admin/protection-actions` with `auth:sanctum` + `permission:admin.access` + `throttle:30,1` middleware
- [ ] **Verify routes register**: `php artisan route:list --path=protection-action` — 6 routes

### 3.4 Frontend — Admin Table

- [x] **Read reference component**: Read `resources/js/components/Admin/AdminInvestmentActions.vue`
- [x] **Create component**: `resources/js/components/Admin/AdminProtectionActions.vue`
  - Clone investment admin table, update labels to "Protection Action Definitions"
  - API calls use `adminService.getProtectionActions()` etc.

### 3.5 Frontend — Admin Modal

- [x] **Read reference modal**: Read `resources/js/components/Admin/InvestmentActionModal.vue`
- [x] **Create component**: `resources/js/components/Admin/ProtectionActionModal.vue`
  - Clone investment modal, update title/labels for protection context
  - Protection-specific condition types in dropdown

### 3.6 Frontend — Admin Panel Tab

- [x] **Read current AdminPanel**: Read `resources/js/views/Admin/AdminPanel.vue`
- [x] **Add "Protection Actions" tab**: After Investment Actions tab with shield icon
- [x] **Import and register** `AdminProtectionActions` component
- [x] **Add icon** in `getTabIcon()` and short label in `getTabShortLabel()`

### 3.7 Frontend — Admin Service Methods

- [x] **Read current adminService**: Read `resources/js/services/adminService.js`
- [x] **Add 5 CRUD methods**: `getProtectionActions()`, `createProtectionAction(data)`, `updateProtectionAction(id, data)`, `deleteProtectionAction(id)`, `toggleProtectionAction(id)`

### Phase 3 Checkpoint

- [ ] **Run existing tests**: All plan and protection tests pass
- [ ] **Security review**: Use `security-reviewer` agent — review admin controller, routes, form request validation
- [x] **Design compliance**: No amber/orange colours, British spelling in labels
- [ ] **Reseed**: `php artisan db:seed`

---

## Phase 4: Frontend Plan Components

**Agent/Skill:** Use `/feature-dev` for guided implementation. Use `premium-ui-designer` agent for UI polish after functional implementation. Use `Explore` agent to verify design system compliance.

### 4.1 ProtectionExecutiveSummary.vue (NEW)

- [x] **Read reference component**: Read `resources/js/components/Plans/Investment/InvestmentExecutiveSummary.vue` (108 lines)
- [x] **Create component**: `resources/js/components/Plans/Protection/ProtectionExecutiveSummary.vue`
  - Greeting, opening, introduction paragraphs
  - **Coverage Summary Table**: columns ~~Name~~ **"Type"** (DEVIATION — spec says "Name", code says "Type"), Need, Coverage, Gap, Status — green/red badges
  - **Key Actions Table**: columns Action, Priority — priority badges
  - "Showing top X of Y actions" note if truncated
  - Closing paragraph
  - Uses `currencyMixin`

### 4.2 ProtectionPersonalInformation.vue (NEW)

- [x] **Read reference component**: Read `resources/js/components/Plans/Investment/InvestmentPersonalInformation.vue` (128 lines)
- [x] **Create component**: `resources/js/components/Plans/Protection/ProtectionPersonalInformation.vue`
  - 2x2 grid layout:
    - **Personal Details**: Full Name, Date of Birth, Age, Marital Status
    - **Family**: Spouse, Children
    - **Financial Overview**: Gross Income, Net Income, Annual Expenditure, Disposable Income (annual), Disposable Income (monthly)
    - **Protection Profile**: Occupation, Smoker Status, Health Status, Planned Retirement Age
  - Uses `currencyMixin`

### 4.3 ProtectionPlanContent.vue (UPDATE)

- [x] **Read current component**: Read `resources/js/components/Plans/Protection/ProtectionPlanContent.vue` (48 lines)
- [x] **Update template section order**:
  1. `PlanMissingDataPrompt` (keep)
  2. `ProtectionExecutiveSummary` (NEW — conditional with `hasStructuredSummary`)
  3. `PlanExecutiveSummary` (KEEP as fallback for legacy narrative data)
  4. `ProtectionPersonalInformation` (NEW)
  5. `PlanGoalSection` (NEW — linked/unlinked goals)
  6. `ProtectionCurrentSituation` (keep — unchanged)
  7. `PlanActionsList` (keep — unchanged)
  8. `PlanWhatIfComparison` with chart (keep — unchanged)
  9. `PlanConclusion` (keep — now receives essential_actions/optional_actions format)
- [x] **Add imports**: `ProtectionExecutiveSummary`, `ProtectionPersonalInformation`, `PlanGoalSection`
- [x] **Add computed**: `hasStructuredSummary` — check for `greeting` field (same pattern as investment)
- [x] **Verify bar chart and toggle system still work**: No changes to PlanActionsList, PlanWhatIfComparison, PlanWhatIfChart, ProtectionWhatIfControls

### Phase 4 Checkpoint

- [ ] **Run backend tests**: All plan and protection tests pass
- [ ] **Reseed**: `php artisan db:seed`
- [ ] **Start dev server**: `./dev.sh`
- [ ] **Browser test — peak_earners**: Login as peak_earners → Plans → Protection Plan
  - [ ] Executive summary shows structured format with coverage gaps table and actions table
  - [ ] Personal information section visible below executive summary
  - [ ] Current situation section displays coverage analysis correctly
  - [ ] Actions list with toggle buttons working
  - [ ] Horizontal bar chart updates when actions toggled on/off
  - [ ] Conclusion shows essential/optional actions split
- [ ] **Browser test — all personas**: Test each persona loads protection plan without errors
  - [ ] young_family (James Carter)
  - [ ] peak_earners (David Mitchell)
  - [ ] widow (Margaret Thompson)
  - [ ] entrepreneur (Alex Chen)
  - [ ] young_saver (John Morgan)
  - [ ] retired_couple (Patricia Bennett)
- [x] **Design compliance check**: Use `Explore` agent to verify against `designStyle.md`
  - [x] No amber/orange colours (Rule 9)
  - [x] All currency via `currencyMixin` (Rule 6)
  - [x] No acronyms in user-facing text (Rule 10)
  - [x] British spelling in all labels
  - [x] No scores in UI (Rule 12)
  - [ ] Read `designStyle.md` and verify component patterns match (Rule 11)
- [ ] **Code review (Phase 4)**: Use `/code-review` skill — review all new/modified Vue components for design system compliance, correct prop types, proper event handling
- [ ] **UI polish**: Use `premium-ui-designer` agent — review table styling, badge colours, spacing, responsive layout

---

## Phase 5: Tests

**Agent/Skill:** Use `/feature-dev` for test implementation.

### 5.1 Unit Tests — ProtectionActionDefinitionServiceTest

- [x] **Read reference tests**: Read `tests/Unit/Services/Investment/InvestmentActionDefinitionServiceTest.php`
- [x] **Create test file**: `tests/Unit/Services/Protection/ProtectionActionDefinitionServiceTest.php`

**Test cases:**
- [x] Gap triggers: life_insurance_gap (fire + not fire), critical_illness_gap (fire + not fire), income_protection_gap (fire + not fire) — Note: "not fire" for CI and IP covered via shared test, not individually isolated
- [~] Strategy triggers: increase_life_cover ✅, ~~add_critical_illness~~ ❌ MISSING, ~~add_income_protection~~ ❌ MISSING
- [x] General triggers: review_existing_policies, consolidate_policies, protection_profile_missing, no_policies_warning
- [x] Disabled definitions: skipped correctly
- [x] Template rendering: title, description, missing placeholders cleaned up
- [ ] Empty data: no errors when comprehensive plan has no coverage analysis — **MISSING TEST**

### 5.2 Feature Tests — Admin CRUD

- [x] **Read reference tests**: Read `tests/Feature/Api/InvestmentActionDefinitionTest.php`
- [x] **Create test file**: `tests/Feature/Api/ProtectionActionDefinitionTest.php`

**Test cases:**
- [x] Admin list (all definitions), show, create, update, toggle, delete
- [x] Non-admin 403 on all 6 endpoints
- [x] Validation errors (required fields)
- [x] Duplicate key constraint on create
- [x] 404 for non-existent definition

### 5.3 Run ALL Tests

- [ ] **Existing plan tests**: All pass
- [ ] **New service tests**: All pass
- [ ] **New feature tests**: All pass
- [ ] **Combined run**: All protection + plan tests pass
- [ ] **PSR-12 clean**: `./vendor/bin/pint`

### Phase 5 Checkpoint

- [ ] **Reseed**: `php artisan db:seed`

---

## Final Integration Testing

- [ ] **Full reseed**: `php artisan db:seed`
- [ ] **Start dev server**: `./dev.sh`

### Browser Verification

- [ ] **Verification 1**: Correct row count in `protection_action_definitions` table
  - Command: `php artisan tinker` → `\App\Models\ProtectionActionDefinition::count()`
- [ ] **Verification 2**: All existing plan tests pass
  - Command: `./vendor/bin/pest tests/Unit/Services/Plans/`
- [ ] **Verification 3**: New service + feature tests pass
  - Command: `./vendor/bin/pest tests/Unit/Services/Protection/ tests/Feature/Api/ProtectionActionDefinitionTest.php`
- [ ] **Verification 4**: peak_earners Protection Plan
  - [ ] Structured executive summary with coverage gaps table
  - [ ] Personal information section with protection profile
  - [ ] Current situation with coverage analysis
  - [ ] Actions list with toggle working
  - [ ] Horizontal bar chart updating on toggle
  - [ ] Conclusion with essential/optional actions
- [ ] **Verification 5**: Admin Panel → Protection Actions tab
  - [ ] Definitions visible with correct sort order
  - [ ] Toggle an action off/on
  - [ ] Edit a definition
  - [ ] Create a new definition
- [ ] **Verification 6**: All preview personas load protection plan without errors
  - [ ] young_family (James Carter)
  - [ ] peak_earners (David Mitchell)
  - [ ] widow (Margaret Thompson)
  - [ ] entrepreneur (Alex Chen)
  - [ ] young_saver (John Morgan)
  - [ ] retired_couple (Patricia Bennett)
- [ ] **Verification 7**: Design compliance
  - [ ] No amber/orange colours (Rule 9)
  - [ ] `currencyMixin` used everywhere (Rule 6)
  - [ ] No acronyms in user-facing text (Rule 10)
  - [ ] British spelling throughout
  - [ ] No scores in UI (Rule 12)

### Final Reviews

- [ ] **Security review**: Use `security-reviewer` agent — review admin endpoints, input validation
- [ ] **Tax compliance review**: Use `tax-compliance-reviewer` agent — verify no hardcoded tax values
- [ ] **Final reseed**: `php artisan db:seed`

---

## Summary

| Phase | Tasks | New Files | Modified Files |
|-------|-------|-----------|----------------|
| Phase 1: Database Layer | 18 | 4 | 1 |
| Phase 2: Backend Service | 30 | 1 | 2 |
| Phase 3: Admin Panel | 14 | 4 | 3 |
| Phase 4: Frontend Components | 20 | 2 | 1 |
| Phase 5: Tests | 15 | 2 | 0 |
| Final Integration | 15 | 0 | 0 |
| **Total** | **~112** | **13** | **7** |

---

## Code Review Findings

### All Files Created (13/13)

| # | File | Status |
|---|------|--------|
| 1 | `database/migrations/2026_03_05_000002_create_protection_action_definitions_table.php` | DONE |
| 2 | `app/Models/ProtectionActionDefinition.php` | DONE |
| 3 | `database/seeders/ProtectionActionDefinitionSeeder.php` | DONE |
| 4 | `database/factories/ProtectionActionDefinitionFactory.php` | DONE |
| 5 | `app/Services/Protection/ProtectionActionDefinitionService.php` | DONE |
| 6 | `app/Http/Controllers/Api/ProtectionActionDefinitionController.php` | DONE |
| 7 | `app/Http/Requests/StoreProtectionActionDefinitionRequest.php` | DONE |
| 8 | `resources/js/components/Admin/AdminProtectionActions.vue` | DONE |
| 9 | `resources/js/components/Admin/ProtectionActionModal.vue` | DONE |
| 10 | `resources/js/components/Plans/Protection/ProtectionExecutiveSummary.vue` | DONE |
| 11 | `resources/js/components/Plans/Protection/ProtectionPersonalInformation.vue` | DONE |
| 12 | `tests/Unit/Services/Protection/ProtectionActionDefinitionServiceTest.php` | DONE |
| 13 | `tests/Feature/Api/ProtectionActionDefinitionTest.php` | DONE |

### All Modified Files (7/7)

| # | File | Status |
|---|------|--------|
| 1 | `database/seeders/DatabaseSeeder.php` | DONE |
| 2 | `app/Services/Plans/ProtectionPlanService.php` | DONE |
| 3 | `app/Services/Plans/BasePlanService.php` | DONE |
| 4 | `routes/api.php` | DONE |
| 5 | `resources/js/views/Admin/AdminPanel.vue` | DONE |
| 6 | `resources/js/services/adminService.js` | DONE |
| 7 | `resources/js/components/Plans/Protection/ProtectionPlanContent.vue` | DONE |

### Deviations from Spec (Acceptable)

1. **Service method consolidation**: The 10 individual evaluator methods were consolidated into generic handlers (`evaluateGapCondition`, `evaluateStrategyCondition`, `evaluateMultiplePolicies`, `evaluateProfileMissing`, `evaluateNoPoliciesWithGaps`). This is a better design — less code duplication, same functionality.
2. **No TaxConfigService in ProtectionActionDefinitionService**: Omitted because protection evaluations don't need tax lookups (unlike Investment which checks ISA/pension allowances). Correct simplification.
3. **Method renamed**: `extractRecommendations()` → `getRecommendations()` in ProtectionPlanService. Delegates correctly to the action definition service.
4. **`renderAction()` not called in service**: Exists on the model but the service uses `renderTitle`/`renderDescription` only. The action text comes from the template directly in the recommendation output.

### Issues to Fix

1. **Coverage Summary table header says "Type" instead of "Name"** — `ProtectionExecutiveSummary.vue` line 19. Minor label mismatch with spec.
2. **Missing unit tests for `add_critical_illness` and `add_income_protection` strategy triggers** — Only `increase_life_cover` strategy has a dedicated test.
3. **Missing unit test for empty/null coverage_analysis** — No test verifying `evaluateActions([])` or a plan with no coverage_analysis throws no errors.

### Remaining Unchecked Items

All unchecked items are **runtime verification tasks** (seeding, running tests, browser testing, running dev server) that require executing commands. These cannot be verified by code review alone.

---

## Quick Reference — Commands

```bash
# Database
php artisan migrate                                             # Run new migration
php artisan db:seed                                             # Reseed ALL data
php artisan db:seed --class=ProtectionActionDefinitionSeeder    # Seed only protection actions

# Testing
./vendor/bin/pest tests/Unit/Services/Plans/                    # Plan tests
./vendor/bin/pest tests/Unit/Services/Protection/               # Protection service tests
./vendor/bin/pest tests/Feature/Api/ProtectionActionDefinitionTest.php  # Admin tests
./vendor/bin/pest                                               # Full suite

# Development
./dev.sh                                                        # Start Laravel + Vite

# Verification
php artisan tinker                                              # Interactive check
php artisan route:list --path=protection-action                 # Verify routes
```

## Quick Reference — Agents & Skills

| Task | Tool |
|------|------|
| Feature implementation | `/feature-dev` skill |
| Bug investigation | `/systematic-debugging` skill |
| Code review | `/code-review` skill |
| Schema design review | `database-optimizer` agent |
| API/auth security | `security-reviewer` agent |
| Tax calculation compliance | `tax-compliance-reviewer` agent |
| UI polish and animations | `premium-ui-designer` agent |
| Codebase exploration | `Explore` agent |

## Key Reference Files

| File | Purpose |
|------|---------|
| `app/Services/Investment/InvestmentActionDefinitionService.php` | PRIMARY PATTERN for service |
| `app/Models/InvestmentActionDefinition.php` | Model pattern |
| `database/seeders/InvestmentActionDefinitionSeeder.php` | Seeder pattern |
| `app/Services/Plans/InvestmentPlanService.php` | Plan service integration pattern |
| `app/Services/Plans/BasePlanService.php` | Shared plan methods (generateDynamicConclusion, buildPersonalInformation) |
| `resources/js/components/Plans/Investment/InvestmentExecutiveSummary.vue` | Exec summary pattern |
| `resources/js/components/Plans/Investment/InvestmentPersonalInformation.vue` | Personal info pattern |
| `resources/js/components/Admin/AdminInvestmentActions.vue` | Admin table pattern |
| `resources/js/components/Admin/InvestmentActionModal.vue` | Admin modal pattern |
| `March3Updates/protectionRewritePlan.md` | Full implementation plan |
