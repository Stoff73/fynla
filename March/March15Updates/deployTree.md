# Deploy: Actions Dashboard + Decision Tree Traceability

**Date:** 2026-03-15
**Branch:** `decisionView` → merged to `main` (PR #129)
**Tests:** 1,873 passed, 0 failures

---

## What Changed

### Actions Dashboard Rebuild
- **Removed:** Old recommendations-based dashboard with summary cards, search bar, and filter dropdowns
- **Added:** Two-column grid layout sourcing actions directly from module plans (`/api/plans/{type}`)
- **Module sections:** Protection, Investment, Retirement, Estate — displayed in fixed order, skipping modules with no actions
- **Each action:** Clickable card showing priority badge, title, category, and estimated impact
- **Route:** `/actions/:planType/:actionId` added for detail views

### Decision Tree Traceability
Every recommendation across all 5 modules now includes a `decision_trace` array showing the complete data trail — every piece of user data gathered, every check made, every calculation performed, and how each drove the outcome.

**158 methods across 8 service files** were instrumented with granular traces showing:
- User profile (name, date of birth, age, employment status, income, marital status)
- Specific account/policy details (provider name, fund value, contribution rates, premiums)
- Calculation inputs shown explicitly (e.g. "£145,000 x 8% = £11,600/year")
- Each decision check with the user's actual values vs thresholds
- The outcome and recommended action

### Detail View
New `/actions/:planType/:actionId` page shows two side-by-side panels:
- **Decision Tree** — visual flowchart with pass (green) / fail (red) nodes connected by lines
- **Decision Trace** — vertical timeline with step-by-step reasoning

### April 2029 Salary Sacrifice National Insurance Cap
- From April 2029, only the first £2,000 of employee salary sacrifice is exempt from National Insurance (per gov.uk)
- `SalarySacrificeAnalyzer` now calculates both current and post-2029 National Insurance savings
- Salary sacrifice recommendations show dual-scenario impact in the decision trace
- Warning displayed when sacrifice exceeds £2,000 cap
- Employer contributions remain fully exempt; Income Tax relief unaffected

### Other Fixes
- Renamed "Early Retirement Fund" goal to "Pre-Pension Bridge Fund" (investment bridge, not pension goal)
- `BasePlanService.structureActions()` passes `decision_trace` through to plan actions

---

## New Frontend Files

```
resources/js/components/Actions/ActionSummaryCard.vue
resources/js/components/Actions/DecisionTreeDiagram.vue
resources/js/components/Actions/DecisionTraceTimeline.vue
resources/js/views/Actions/ActionDetailView.vue
```

## Modified PHP Files

```
app/Agents/EstateAgent.php
app/Services/Investment/InvestmentActionDefinitionService.php
app/Services/Investment/Recommendation/ContributionWaterfallService.php
app/Services/Investment/Recommendation/SpouseOptimisationService.php
app/Services/Investment/Recommendation/TransferRecommendationService.php
app/Services/Plans/BasePlanService.php
app/Services/Protection/ProtectionActionDefinitionService.php
app/Services/Retirement/RetirementActionDefinitionService.php
app/Services/Retirement/SalarySacrificeAnalyzer.php
app/Services/Savings/SavingsActionDefinitionService.php
database/seeders/TaxConfigurationSeeder.php
```

## Modified Frontend Files

```
resources/js/data/personas/peak_earners.json
resources/js/router/index.js
resources/js/views/Actions/ActionsDashboard.vue
```

---

## Deploy Steps

### 1. Build frontend locally

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload

- `public/build/` directory
- All PHP files listed above
- `database/seeders/TaxConfigurationSeeder.php` (contains £2,000 NIC cap config)

### 3. SSH commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Reseed tax config (adds salary sacrifice NIC exemption cap)
php artisan db:seed --class=TaxConfigurationSeeder --force

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

No new migrations required — all changes are code and seed data.
