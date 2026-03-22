# Full Deployment Guide — All Pending Changes

**Date:** 22 March 2026
**Covers:** Everything on main branch not yet deployed to production
**Previous deploys:** Income fix (PR #147) deployed. Everything else pending.

---

## Quick Reference

| Change | PR | Type | Rebuild? | Migration? |
| ------ | --- | ---- | -------- | ---------- |
| Goals module integration | #148 | Feature | Yes | No |
| Life events expansion | #149 | Feature | Yes | Yes (1) |
| What-If scenario system | #150, #151 | Feature | Yes | Yes (1) |
| Fyn AI navigation | #152 | Fix | Yes | No |
| Fyn AI goals/life events context | #153 | Feature | Yes | No |
| Chat history fix | #154 | Fix | Yes | No |
| Chat scroll anchor | #155 | Fix | Yes | No |
| Tool error handling | — | Fix | Yes | No |
| AI form fill | #156 | Feature | Yes | No |
| Onboarding updates | #157 | Feature | Yes | Yes (1) |
| Sidebar revert + info guide fixes + dead code cleanup | #158 | Fix | Yes | No |

---

## Step 1: Build Locally

```bash
./deploy/fynla-org/build.sh
```

This compiles all Vue/JS changes into `public/build/`.

## Step 2: Upload PHP Files

### New Files

```
app/Models/WhatIfScenario.php
app/Services/WhatIf/WhatIfScenarioService.php
app/Http/Controllers/Api/WhatIfScenarioController.php
app/Http/Requests/StoreWhatIfScenarioRequest.php
app/Http/Resources/WhatIfScenarioResource.php
database/factories/WhatIfScenarioFactory.php
database/migrations/2026_03_21_000001_add_new_life_event_types.php
database/migrations/2026_03_21_000002_create_what_if_scenarios_table.php
database/migrations/2026_03_21_214000_change_focus_area_to_varchar.php
```

### Modified PHP Files

```
# Agents
app/Agents/SavingsAgent.php
app/Agents/ProtectionAgent.php
app/Agents/EstateAgent.php
app/Agents/RetirementAgent.php
app/Agents/CoordinatingAgent.php

# Services
app/Services/AI/AiToolDefinitions.php
app/Services/PrerequisiteGateService.php
app/Services/UserProfile/PersonalAccountsService.php
app/Services/UserProfile/UserProfileService.php
app/Services/UserProfile/ModuleDataRequirementsService.php
app/Services/Goals/LifeEventIntegrationService.php
app/Services/Onboarding/OnboardingService.php
app/Services/Onboarding/JourneyStateService.php
app/Services/LifeStage/LifeStageService.php
app/Services/Estate/WillDocumentService.php

# Controllers
app/Http/Controllers/Api/OnboardingController.php
app/Http/Controllers/Api/EstateController.php

# Models
app/Models/LifeEvent.php

# Observers
app/Observers/LifeEventMonteCarloObserver.php

# Traits
app/Traits/HasAiChat.php

# Routes
routes/api.php
```

## Step 3: Upload Frontend Build

Upload `public/build/` directory to server, replacing existing build.

All Vue/JS changes are compiled into this directory:
- SideMenu.vue (sidebar revert, dead code cleanup)
- Dashboard.vue (removed suggested goals card, unused GoalsCard import)
- GoalsCard.vue (removed suggested goals section)
- InfoGuidePanel.vue (fixed business interests route)
- lifeStage.js store (removed dead getters)
- lifeStageConfig.js (removed suggestedGoals from all stages)
- OnboardingWizard.vue (clickable steps, liabilities step, top nav bar)
- WillBuilderWizard.vue (draft resume, success modal)
- WillPlanning.vue (full document display, View Will button)
- LiabilitiesList.vue, LiabilityCard.vue, LiabilityDetailInline.vue
- ExpenditureCategoryCard.vue, ExpenditureForm.vue, CurrencyInputField.vue
- DCPensionForm.vue, PropertyForm.vue, FamilyMemberFormModal.vue
- SaveAccountModal.vue, UsefulResources.vue
- AiChatPanel.vue, GoalsOverview.vue, GoalCard.vue
- ScenarioCard.vue, ScenarioDetail.vue, ModuleComparison.vue
- WhatIfDashboard.vue, WhatIfScenarioDetailView.vue
- whatIf.js store, whatIfService.js, aiFormFill.js store, aiChat.js store
- chatNavigationRouter.js, router/index.js, store/index.js
- AppLayout.vue, app.css
- + 12 onboarding step files (removed inline UsefulResources)
- + 10 page components (AI form fill watchers)
- + 15 form components (AI form fill highlight bindings)

## Step 4: Run on Server

SSH to production:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
```

Run in this order:

```bash
# 1. Autoload new classes
composer dump-autoload

# 2. Run migrations (3 pending)
php artisan migrate

# 3. Seed all data
php artisan db:seed

# 4. Clear all caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

### Migrations (3 pending)

1. `2026_03_21_000001_add_new_life_event_types` — Adds divorce, marriage, new_child, job_loss, income_change to life_events enum
2. `2026_03_21_000002_create_what_if_scenarios_table` — Creates what_if_scenarios table
3. `2026_03_21_214000_change_focus_area_to_varchar` — Changes onboarding_progress.focus_area and users.onboarding_focus_area from enum to varchar(50)

---

## What Changed (by area)

### Sidebar & Navigation (PR #158)

- Removed journey-based sidebar filtering — all items always visible under headings
- Fixed all "What powers this view?" navigation links (income → /valuable-info?section=income, savings → /net-worth/cash, investments → /net-worth/investments, pensions → /net-worth/retirement, liabilities → /net-worth/liabilities, property → /net-worth/property, business → /net-worth/business)
- Fixed InfoGuidePanel business interests route (/net-worth/business-interests → /net-worth/business)
- Removed "Suggested for You" goals card from dashboard (static, no personalisation)
- Cleaned up ~215 lines of dead code across SideMenu.vue, lifeStage.js, lifeStageConfig.js, Dashboard.vue

### Onboarding (PR #157 — 50 commits)

- Journey resumption — goes to first incomplete step, not beginning
- Clickable step indicators in progress bar
- Replaced legacy `onboarding_focus_area` with `life_stage` (enum → varchar migration)
- Dashboard refreshes journey completeness on mount
- Multiple executors in will planning and onboarding
- Goals step: skip confirmation modal instead of validation error
- Assets step: close forms on tab switch
- NS&I: hide irrelevant fields
- Useful resources moved to sidebar card below learning milestones
- Family member form: red asterisks on required fields
- DC pension: planned access age field for SIPP/personal pensions
- Scroll to top on step and tab changes (mobile fix)
- Property form: leasehold expiry date with auto-calculated remaining years
- Expenditure step added to all 5 life stage journeys
- Expenditure inputs: fixed display (parseFloat + displayValue normalisation)
- Partner vs Spouse based on marital status
- Family members card layout fix
- Will builder: draft save/resume, success modal, dashboard shows full WillDocument data
- "View Will" button links to /estate/will-builder?view=document
- Liabilities dashboard with sidebar nav and mortgage integration
- Liabilities onboarding step in journeys 3 and 4
- Top navigation bar on onboarding wizard

### AI Form Fill (PR #156)

- All 16 `handleCreate*` methods return `fill_form` instead of saving directly
- New `aiFormFill` Vuex store with field-by-field fill + 250ms highlight animation
- `fill_form` SSE event type
- 10 page components with pending fill watchers
- 15 form components with highlight bindings + auto-submit
- Chat panel preserves conversation on cross-page navigation
- Renamed create_estate_liability → create_liability, create_estate_asset → create_asset

### What-If Scenarios (PR #150, #151)

- New what_if_scenarios table with persistent scenarios
- WhatIfScenarioService: living now vs what-if comparisons
- create_what_if_scenario AI tool
- Card grid list → dedicated detail page
- AI auto-navigation to scenario detail view

### Goals Module Integration (PR #148)

- SavingsAgent: goal shortfall + emergency fund + life event cash buffer
- ProtectionAgent: goal commitments in coverage analysis
- EstateAgent: goal liquidity risk flagging
- RetirementAgent: post-retirement goal detection
- GoalCard: inline monthly contribution input
- Goals banner: specific goal names + remaining amounts
- AI create_goal tool with affordability assessment

### Life Events (PR #149)

- 5 new event types: divorce, marriage, new_child, job_loss, income_change
- Module cache invalidation on life event changes
- AI context enriched with per-module life event impact summaries

### Fyn AI Improvements (PR #152, #153, #154, #155)

- Navigation: query string parsing, keyword matching for all 37 sidebar pages
- Goals/life events context in system prompt with IDs, progress, status
- New list_goals and list_life_events tools
- Chat history drawer added to docked panel
- Chat scroll anchors to top of response, not bottom
- Tool error handling: answers from knowledge with caveat instead of showing errors

---

## Post-Deploy Verification

1. **Sidebar**: All menu items visible under headings regardless of life stage
2. **Info Guide**: Click "Add now" on missing liabilities → goes to /net-worth/liabilities
3. **Info Guide**: Click "Add now" on missing pensions → goes to /net-worth/retirement
4. **Info Guide**: Click "Add now" on income requirement → goes to /valuable-info?section=income
5. **Info Guide**: Navigate to /net-worth/business → info guide auto-detects module
6. **Dashboard**: No "Suggested for You" card
7. **Onboarding**: Start a journey → verify clickable step indicators, expenditure step, top nav bar
8. **Will Builder**: Start will → close → reopen → verify resumes at correct step
9. **Liabilities**: Sidebar → Liabilities → verify mortgage integration, filter dropdown
10. **Income**: Preview persona → Income tab → verify zero-value types hidden, Other Income editable
11. **Goals**: Goals page → verify behind-schedule banner with specific goal names
12. **Chat**: Real user → docked Fyn chat → send message → click history icon → verify conversations load
13. **Chat scroll**: Send a message → response anchors at top, not bottom
14. **What-If**: Ask Fyn "What if I retire at 55?" → verify scenario created and navigated to detail
15. **AI form fill**: Ask Fyn "I have a savings account with Barclays" → verify form opens, fields fill with highlights
16. **Navigation**: Ask Fyn "show me my income" → verify navigates to /valuable-info?section=income
17. **Life Events**: Create new life event → verify module caches cleared

---

## Rollback

All changes are discrete PRs. To rollback any specific feature:
- Revert the PR on GitHub
- Rebuild locally and re-upload public/build/
- Run `php artisan migrate:rollback --step=N` if migrations need reverting (only for PRs #149, #150, #157)
