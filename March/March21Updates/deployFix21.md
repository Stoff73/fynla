# Deployment Guide — 21 March 2026

**STATUS: DEPLOYED (income fix) | NOT YET DEPLOYED (goals/what-if)**

## Rebuild Required?

**Yes** — Multiple Vue components changed. Run:

```bash
./deploy/fynla-org/build.sh
```

Then upload `public/build/` directory.

## Database Migrations (2 pending)

```bash
php artisan migrate
```

1. `2026_03_21_000001_add_new_life_event_types.php` — Adds divorce, marriage, new_child, job_loss, income_change to life_events enum
2. `2026_03_21_000002_create_what_if_scenarios_table.php` — Creates what_if_scenarios table for persistent scenarios

## Database Seeders

```bash
php artisan db:seed
```

Reseed all data after migrations to ensure preview personas and tax config are current.

## PHP Files to Upload

### New Files (12)

```
app/Models/WhatIfScenario.php
app/Services/WhatIf/WhatIfScenarioService.php
app/Http/Controllers/Api/WhatIfScenarioController.php
app/Http/Requests/StoreWhatIfScenarioRequest.php
app/Http/Resources/WhatIfScenarioResource.php
database/factories/WhatIfScenarioFactory.php
database/migrations/2026_03_21_000001_add_new_life_event_types.php
database/migrations/2026_03_21_000002_create_what_if_scenarios_table.php
tests/Unit/Agents/SavingsAgentGoalsTest.php
tests/Unit/Agents/ProtectionAgentGoalsTest.php
tests/Unit/Agents/EstateAgentGoalsTest.php
tests/Unit/Agents/RetirementAgentGoalsTest.php
```

### Modified Files (18)

```
app/Agents/SavingsAgent.php
app/Agents/ProtectionAgent.php
app/Agents/EstateAgent.php
app/Agents/RetirementAgent.php
app/Agents/CoordinatingAgent.php
app/Services/AI/AiToolDefinitions.php
app/Services/UserProfile/PersonalAccountsService.php
app/Services/UserProfile/UserProfileService.php
app/Services/Goals/LifeEventIntegrationService.php
app/Models/LifeEvent.php
app/Observers/LifeEventMonteCarloObserver.php
app/Traits/HasAiChat.php
routes/api.php
tests/Pest.php
tests/Architecture/Phase02ArchitectureTest.php
tests/Unit/Services/PersonalAccountsServiceTest.php
```

## Frontend (via build)

All compiled into `public/build/` — upload the build directory:

```
resources/js/components/UserProfile/IncomeOccupation.vue
resources/js/components/UserProfile/IncomeStatementTab.vue
resources/js/components/Goals/GoalsOverview.vue
resources/js/components/Goals/GoalCard.vue
resources/js/components/Dashboard/GoalsOverviewCard.vue
resources/js/components/WhatIf/ScenarioCard.vue
resources/js/components/WhatIf/ScenarioDetail.vue
resources/js/components/WhatIf/ModuleComparison.vue
resources/js/views/Planning/WhatIfDashboard.vue
resources/js/views/Planning/WhatIfScenarioDetailView.vue
resources/js/store/modules/whatIf.js
resources/js/store/index.js
resources/js/services/whatIfService.js
resources/js/router/index.js
resources/js/layouts/AppLayout.vue
```

## Upload Order

1. Upload all PHP files to matching paths on server
2. Upload 2 migration files
3. Run `composer dump-autoload` on server (new model + service classes)
4. Run `./deploy/fynla-org/build.sh` locally
5. Upload `public/build/` directory
6. SSH and run migrations + seed + clear caches:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate && php artisan db:seed && php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## What Changed

### Income Fix (PR #147)
- Other Income added to IncomeOccupation.vue (was completely missing)
- `annual_other_income` added to UserProfileService API response + tax calculation
- Interest, Pension, Trust income added to PersonalAccountsService P&L
- Hardcoded frontend tax calculator replaced with backend UKTaxCalculator
- All income lines hidden when zero in view mode

### Goals Module Integration (PR #148)
- SavingsAgent: goal shortfall + emergency fund + life event cash buffer recommendations
- ProtectionAgent: goal commitments in coverage analysis
- EstateAgent: goal liquidity risk flagging
- RetirementAgent: post-retirement goal detection
- GoalCard: inline monthly contribution input
- Goals banner: specific goal names + remaining amounts
- AI create_goal tool: monthly_contribution with affordability assessment
- Chat icon hidden for preview users

### Life Events Expansion (PR #149)
- 5 new event types: divorce, marriage, new_child, job_loss, income_change
- Module cache invalidation on life event changes
- AI context enriched with per-module life event impact summaries

### What-If Scenario System (PR #150, #151)
- New what_if_scenarios table with persistent scenarios
- WhatIfScenarioService: living Now vs What-If comparisons
- create_what_if_scenario AI tool (replaces run_what_if_scenario)
- Card grid list → dedicated detail page with back button
- AI auto-navigation: Fyn creates scenario and navigates user to detail view

## Post-Deploy Verification

1. **Income**: Log in as preview persona → Income tab → verify zero-value types hidden, Other Income editable
2. **Goals**: Goals page → verify behind-schedule banner shows specific goal names with remaining amounts
3. **Chat**: Preview mode → verify no chat icon. Real user → verify docked Fyn chat
4. **What-If**: As real user, ask Fyn "What if I retire at 55?" → verify scenario created, auto-navigated to detail page with AI narrative + module comparisons
5. **What-If list**: Navigate to /planning/what-if → verify scenario card in grid, click navigates to detail page, back button returns to list
6. **Life Events**: Create a new life event → verify affected module caches cleared
