# Deploy Notes — SaveTax Sections 4-6

**Branch:** `feature/fyn-persona-split`
**Spec:** `April/April29Updates/savetax-section4-6-spec.md`

---

## File list

### New PHP backend (16 files)

```
app/DataTransferObjects/TaxStrategyOutputDTO.php
app/DataTransferObjects/TaxStrategyOverridesDTO.php
app/Http/Controllers/Api/TaxStrategyController.php
app/Http/Requests/TaxStrategyCalculateRequest.php
app/Models/TaxStrategyHouseholdInput.php
app/Services/Tax/TaxStrategyCalculator.php
app/Services/Tax/TaxStrategyService.php
database/migrations/2026_05_03_000001_add_tax_strategy_columns_to_users.php
database/migrations/2026_05_03_000002_add_salary_sacrifice_to_dc_pensions.php
database/migrations/2026_05_03_000003_create_tax_strategy_household_inputs_table.php
```

### Modified PHP backend (5 files)

```
app/Agents/CoordinatingAgent.php          (4 new tool dispatches + handlers)
app/Models/DCPension.php                  (salary_sacrifice in $fillable + $casts)
app/Models/User.php                       (marriage_allowance_eligible cast + taxStrategyHouseholdInput relation)
app/Services/AI/AiToolDefinitions.php     (new campaignSaveTaxTools method)
app/Services/AI/XaiToolDefinitions.php    (xAI parity for the 4 new tools)
app/Services/Onboarding/OnboardingChatDirector.php  (4 new tools whitelisted)
app/Services/Onboarding/OnboardingStateMachine.php  (9 new states + routing + skip_if helpers)
routes/api.php                            (2 new tax-strategy routes)
```

### New frontend (10 files)

```
resources/js/components/TaxStrategy/AllowanceCard.vue
resources/js/components/TaxStrategy/AllowanceGrid.vue
resources/js/components/TaxStrategy/AssetShiftingPanel.vue
resources/js/components/TaxStrategy/HouseholdView.vue
resources/js/components/TaxStrategy/StrategyRecommendationList.vue
resources/js/components/TaxStrategy/StrategySliderPanel.vue
resources/js/components/TaxStrategy/TaxYearHeader.vue
resources/js/services/taxStrategyService.js
resources/js/store/modules/taxStrategy.js
resources/js/views/TaxStrategy/TaxStrategyDashboard.vue
```

### Modified frontend (3 files)

```
resources/js/router/index.js              (new /tax-strategy route)
resources/js/store/index.js               (taxStrategy module registered)
resources/js/views/Actions/ActionsDashboard.vue  (Tax Strategy tile for savetax users)
```

### Tests (8 files — informational, do not need uploading)

```
tests/Browser/scenarios/BS-26-savetax-single-employed.php
tests/Browser/scenarios/BS-27-savetax-married-spouse-works.php
tests/Browser/scenarios/BS-28-savetax-married-spouse-no-work.php
tests/Feature/AI/DirectWrite/CaptureSalarySacrificeTest.php
tests/Feature/AI/DirectWrite/CaptureSpouseHouseholdDataTest.php
tests/Feature/AI/DirectWrite/CaptureSpouseNonWorkingAssetsTest.php
tests/Feature/AI/DirectWrite/CaptureSpouseWorkStatusTest.php
tests/Feature/Api/TaxStrategy/CalculateEndpointTest.php
tests/Feature/Api/TaxStrategy/ShowEndpointTest.php
tests/Unit/Services/Onboarding/CampaignStateMachineBranchTest.php
tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php
```

---

## Deploy to dev (csjones.co/fynla)

```bash
# 1. Merge feature branch to dev (open PR; requires Stoff73 approval)
# 2. Local build for dev environment
./deploy/csjones-fynla/build.sh

# 3. Upload via SiteGround File Manager / rsync to:
#    ~/www/csjones.co/public_html/fynla/
#    - All modified PHP files
#    - 3 new migrations
#    - public/build/ (full directory — Vite output)

# 4. SSH in and finalise (CSJ password-protected key ~/.ssh/fynlaDev)
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/public_html/fynla
php artisan migrate --force
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan optimize

# 5. Smoke test — see "Smoke test plan" below
```

**No db:seed required** — schema additions only, no seeded reference data changes. Existing `tax_configurations` rows already include all the allowance values the calculator needs.

---

## Deploy to production (fynla.org)

After dev green:

```bash
# 1. Merge dev → main (open PR; requires Stoff73 approval)
# 2. Local build for production
./deploy/fynla-org/build.sh

# 3. Upload to ~/www/fynla.org/public_html/
# 4. SSH + finalise
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate --force
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan optimize

# 5. Monitor storage/logs/laravel.log for 10-15 minutes
```

---

## Smoke test plan

### Backend (no auth required)

```bash
# Confirm new routes registered
php artisan route:list --path=tax-strategy
# Expected: 2 routes (GET /api/tax-strategy, POST /api/tax-strategy/calculate)

# Confirm new schema
php artisan tinker --execute="echo json_encode(['users' => Schema::hasColumn('users', 'household_calculation_mode'), 'dc' => Schema::hasColumn('dc_pensions', 'salary_sacrifice'), 'household' => Schema::hasTable('tax_strategy_household_inputs')]);"
# Expected: {"users":true,"dc":true,"household":true}
```

### Frontend (browser)

1. Navigate `/savetax?utm_source=linkedin`. Confirm landing page loads with all CTAs.
2. Click any CTA → `/register?from=savetax`. Fill form, submit, MFA.
3. Land on dashboard with Fyn auto-opened: campaign welcome bubble visible.
4. Walk through onboarding: DOB, marital, dependants, employment, income, expenditure, "Looks correct".
5. Confirm new campaign branch fires: occupational scheme question appears (NOT the asset_capture intro).
6. Walk through ISA, bank, investment, pension states.
7. (If married) spouse_work bubble appears. Choose either option.
8. STATE_CAMPAIGN_TERMINAL → SSE navigate event → land on `/tax-strategy`.
9. Dashboard renders (single grid OR HouseholdView per path).
10. Drag pension contribution slider; verify Pension Annual Allowance card updates within 300ms.
11. (Path C only) Confirm AssetShiftingPanel shows asset-shifting recommendations.

### DB state per path

```sql
-- Path A: single + employed
SELECT id, household_calculation_mode, marriage_allowance_eligible, onboarding_fyn_path, onboarding_fyn_selection, onboarding_completed
  FROM users WHERE email = '...';
-- Expected: household_calculation_mode IS NULL OR 'single', marriage_allowance_eligible IS NULL,
--           onboarding_fyn_path='campaign', onboarding_fyn_selection='savetax', onboarding_completed=1

-- Path B: married + spouse works
SELECT u.id, u.household_calculation_mode, u.marriage_allowance_eligible,
       h.spouse_annual_income, h.spouse_psa_band
  FROM users u JOIN tax_strategy_household_inputs h ON h.user_id = u.id WHERE u.email = '...';
-- Expected: household_calculation_mode='dual_earner', marriage_allowance_eligible=0,
--           spouse_annual_income > 0, spouse_existing_isa_balance IS NULL

-- Path C: married + spouse not working
SELECT u.id, u.household_calculation_mode, u.marriage_allowance_eligible,
       h.spouse_existing_isa_balance, h.spouse_annual_income
  FROM users u JOIN tax_strategy_household_inputs h ON h.user_id = u.id WHERE u.email = '...';
-- Expected: household_calculation_mode='single_earner_couple', marriage_allowance_eligible=1,
--           spouse_existing_isa_balance >= 0, spouse_annual_income IS NULL
```

---

## Rollback procedure

If a critical issue surfaces post-deploy:

```bash
# 1. SSH in
cd <env path>

# 2. Roll back the 3 migrations in reverse order
php artisan migrate:rollback --step=3 --force

# 3. Re-upload the previous version of the 8 modified PHP files
#    (use git show <prev-sha>:path/to/file.php to retrieve)

# 4. Re-upload the previous public/build/ from a stored backup tarball

# 5. Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan optimize
```

The migrations are non-destructive (additive columns + new table); rollback is safe and preserves all user data.

---

## Known follow-ups

| # | Item | Owner | Plan |
|---|---|---|---|
| F1 | Eval YAML scenarios for the 9 new states (Sprint 1 S1.7.c) | gated on S1.7.a | `April/April27Updates/eval-expectations-rewrite.md` |
| F2 | Drive BS-26/27/28 in live browser per Rule #15 | manual run | `tests/Browser/scenarios/BS-2[678]-*.php` |
| F3 | Subscription gating decision for `/tax-strategy` | CSJ | default applied: `auth:sanctum` only |
| F4 | Mobile rendering of /tax-strategy | future | Capacitor app — out of scope for v1 |
