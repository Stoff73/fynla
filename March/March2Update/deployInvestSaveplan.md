# Investment & Savings Plan Rewrite — Deployment Guide

**Branch:** `investmentSavePlan` (merged to `main`)
**PR:** #103
**Prerequisite:** Retirement Plan (`retirementPlanFix`) must be deployed first — this feature shares `CascadingActionChart`, `PlanActionCard`, `PlanGoalSection`, `PlanConclusion`, and `BasePlanService`.

---

## All Files to Upload (22 production files)

### New PHP Files (6)

| File | Purpose |
|------|---------|
| `database/migrations/2026_03_05_000001_create_investment_action_definitions_table.php` | Create `investment_action_definitions` table |
| `database/seeders/InvestmentActionDefinitionSeeder.php` | Seed 21 action definitions (18 agent + 3 goal) |
| `app/Models/InvestmentActionDefinition.php` | Eloquent model with template rendering, scopes, static helpers |
| `app/Services/Investment/InvestmentActionDefinitionService.php` | DB-driven action evaluation — 21 triggers, cascade params, what-if impact |
| `app/Http/Controllers/Api/InvestmentActionDefinitionController.php` | Admin CRUD API for action definitions |
| `app/Http/Requests/StoreInvestmentActionDefinitionRequest.php` | Validation for admin action definition forms |

### Modified PHP Files (7)

| File | Change |
|------|--------|
| `app/Agents/InvestmentAgent.php` | Delegates recommendations to `InvestmentActionDefinitionService` instead of hardcoded logic |
| `app/Services/Plans/InvestmentPlanService.php` | Full rewrite — structured executive summary, personal info, goal actions with funding sources, cascading what-if, conclusion |
| `app/Services/Plans/PlanConfigService.php` | Added `getInvestmentPlanConfig()` returning plan-level settings |
| `app/Traits/HasJointOwnership.php` | Minor fix for joint ownership query |
| `database/seeders/DatabaseSeeder.php` | Added `InvestmentActionDefinitionSeeder` to `run()` |
| `database/seeders/PlanConfigurationSeeder.php` | Added investment plan config entry |
| `routes/api.php` | Added `/api/admin/investment-action-definitions` CRUD routes |

### New Vue Files (5)

| File | Purpose |
|------|---------|
| `resources/js/components/Plans/Investment/InvestmentExecutiveSummary.vue` | Structured summary with goal table and key actions table |
| `resources/js/components/Plans/Investment/InvestmentPersonalInformation.vue` | Personal details, family, financial overview, risk profile |
| `resources/js/components/Admin/AdminInvestmentActions.vue` | Admin panel tab — table of 21 definitions with toggle/edit/delete |
| `resources/js/components/Admin/InvestmentActionModal.vue` | Admin modal for creating/editing action definitions |
| `resources/js/services/adminService.js` | API service for admin investment action CRUD (also used by retirement) |

### Modified Vue Files (4)

| File | Change |
|------|--------|
| `resources/js/components/Plans/Investment/InvestmentGroupedActions.vue` | Rewritten — per-account sections, cascading charts per action, removed redundant `AccountFeeProjectionChart` |
| `resources/js/components/Plans/Investment/InvestmentPlanContent.vue` | Updated layout with new sections, removed `accountProjections` prop |
| `resources/js/components/Plans/Investment/InvestmentWhatIfControls.vue` | Added additional monthly savings metric |
| `resources/js/views/Admin/AdminPanel.vue` | Added Investment Actions tab |
| `resources/js/views/Plans/InvestmentPlan.vue` | Updated to pass new plan data structure |

### Test Files (not needed on production)

| File | Purpose |
|------|---------|
| `tests/Feature/Api/InvestmentActionDefinitionTest.php` | API endpoint tests for admin CRUD |
| `tests/Unit/Services/Investment/InvestmentActionDefinitionServiceTest.php` | Service unit tests — 74 tests, 361 assertions |
| `database/factories/InvestmentActionDefinitionFactory.php` | Test factory |

---

## Deployment Steps

### 1. Local Build

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload Files via SiteGround File Manager

Upload to `~/www/fynla.org/public_html/`:

**PHP files:**
```
app/Agents/InvestmentAgent.php
app/Http/Controllers/Api/InvestmentActionDefinitionController.php
app/Http/Requests/StoreInvestmentActionDefinitionRequest.php
app/Models/InvestmentActionDefinition.php
app/Services/Investment/InvestmentActionDefinitionService.php
app/Services/Plans/InvestmentPlanService.php
app/Services/Plans/PlanConfigService.php
app/Traits/HasJointOwnership.php
database/migrations/2026_03_05_000001_create_investment_action_definitions_table.php
database/seeders/DatabaseSeeder.php
database/seeders/InvestmentActionDefinitionSeeder.php
database/seeders/PlanConfigurationSeeder.php
routes/api.php
```

**Frontend build:**
```
public/build/    (entire directory)
```

### 3. SSH and Run Migration + Seed

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migration to create the table
php artisan migrate

# Seed the 21 investment action definitions
php artisan db:seed --class=InvestmentActionDefinitionSeeder --force

# Seed plan config (if not already done from retirement deploy)
php artisan db:seed --class=PlanConfigurationSeeder --force

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

### 4. Verify

```bash
# Check 21 definitions seeded
php artisan tinker --execute="echo \App\Models\InvestmentActionDefinition::count();"
# Expected: 21

# Check routes registered
php artisan route:list --path=investment-action-definitions
# Expected: 5 routes (index, store, show, update, destroy)
```

---

## What Changed (Summary)

| Area | Before | After |
|------|--------|-------|
| Recommendations | Hardcoded in `InvestmentAgent` (200+ lines) | DB-driven via `InvestmentActionDefinitionService` (21 definitions with trigger_config JSON) |
| Executive Summary | Plain text paragraph | Structured — goal progress table, key actions table, narrative |
| Personal Information | Not shown | Full section — personal details, family, financial overview, risk profile |
| Actions Layout | Flat list | Grouped by account, then portfolio actions, each with cascading chart |
| Charts per Action | 2 (CascadingActionChart + AccountFeeProjectionChart) | 1 (CascadingActionChart only) |
| Goal Actions | Not shown | Tax-aware funding source with dropdown, lump sum calculation |
| What-if | Basic | Current vs Projected with additional monthly savings metric |
| Conclusion | Generic | Priority actions (numbered) + Optional improvements (bulleted) |
| Admin UI | None | Full CRUD — table with toggle/edit/delete, modal form |

---

## Shared Dependencies (from Retirement Plan deploy)

These files must already be on production from the `retirementPlanFix` deployment:

```
resources/js/components/Plans/Retirement/CascadingActionChart.vue
resources/js/components/Plans/Shared/PlanActionCard.vue
resources/js/components/Plans/Shared/PlanGoalSection.vue
resources/js/components/Plans/Shared/PlanConclusion.vue
app/Services/Plans/BasePlanService.php
app/Models/PlanActionFundingSelection.php
```

If the retirement plan has NOT been deployed yet, include these files in the upload as well.

---

## Rollback

If issues arise:

```bash
# Reverse the migration (drops investment_action_definitions table)
php artisan migrate:rollback --step=1

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

Restore the previous versions of modified files from git. The investment plan will not function without the `investment_action_definitions` table populated.
