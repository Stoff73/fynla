# Deploy — March 7 (Information Journey Improvements + Onboarding Redesign)

**Date:** 7 March 2026
**Branch:** `onboarding` (PR #112)
**Commits:** 13 commits (`0c41369` through `ca37158`)
**Scope:** 10 workstreams from userJourney.md analysis + 8 workstreams from userJourneyTasks.md — 154 files changed, 18,744 insertions

---

## Summary

### WS1: Dead Data Cleanup
- Removed unused Investment fields (company_sector, voting/dividend rights) from forms and validation
- Removed National Insurance number from user profile forms (security improvement)
- Removed education_status display from family member cards
- Confirmed Protection, Goals, Estate fields are actively used (kept)
- Audited ESS module (69 fields, 12 active — kept for development)

### WS2: Activate Decumulation
- Wired existing `DecumulationPlanner` into `RetirementAgent`
- New `DecumulationController` with analysis endpoint
- New `DecumulationStrategyCard.vue` frontend component
- Pension drawdown strategy cards on retirement dashboard

### WS3: Letter to Spouse Integration
- New `LetterEstateValidationService` cross-validates letter data against estate records
- Checks: executor named in will, insurance covers IHT, assets match net worth
- New `LetterEstateWarnings.vue` component and API endpoint

### WS4: Tax Optimisation Agent
- New `TaxOptimisationAgent` registered in `CoordinatingAgent` at priority 65
- `TaxOptimisationService` with ISA sequencing, pension relief, spousal transfers, CGT planning
- `TaxActionDefinition` model with 5 database-driven evaluators and seeder
- New `TaxStrategiesCard.vue` tab in UK Taxes dashboard

### WS5: Progressive Onboarding
- Quick 3-step onboarding flow (personal info, focus areas, quick assets)
- `ProfileCompletionCards.vue` on dashboard for deferred data entry
- New `QuickAssetsStep.vue` component
- Migration for onboarding mode and asset flags on users table

### WS6: Household Coordination
- `HouseholdPlanningService` for married/partnered users
- Combined net worth, spousal tax optimisations, death-of-spouse scenario modelling
- 3 new dashboard components: `HouseholdNetWorth`, `SpousalOptimisations`, `DeathOfSpouseScenario`
- New API routes under `/api/household/`

### WS7: Risk Profile Enhancement
- `AutoRiskCalculator` expanded from 7 to 9 factors (age + income stability)
- `LifeEventRiskObserver` triggers recalculation on life events
- `RiskMismatchWarning.vue` for manual vs auto-calculated risk conflicts

### WS8: Cross-Module Strategies
- `CrossModuleStrategyService` with 10 strategy types (emergency fund first, pension before ISA, etc.)
- `CrossModuleInsights.vue` dashboard component
- Wired into `CoordinatingAgent`

### WS9: Recommendation Personalisation
- `RecommendationPersonaliser` adds family, estate, and investment context to recommendations
- Personalised urgency, descriptions, and impact estimates
- Enhanced `RecommendationCard` and `PlanActionCard` components

### WS10: Missing Data Points
- Life expectancy override (user-configurable, 60-110 range, actuarial fallback)
- Care cost assumptions on retirement profile (annual cost + start age)
- Will review date tracking with 3-year stale warning
- Goal dependencies via pivot table

### Onboarding Redesign — Focus-Area-Driven User Journeys

Replaced single-path onboarding (Quick/Full Setup) with 8 selectable focus areas. Users choose which modules matter to them and complete tailored journeys with deduplicated fields.

#### WS1-OB: Journey State Management
- `JourneyStateService` — manages 8 journey states (not_started/in_progress/completed)
- `JourneyFieldResolver` — field deduplication across journey selections, step generation
- `DashboardPromptService` — post-journey dashboard prompts with dismissal tracking
- Migration adding `journey_states`, `journey_selections`, `dismissed_prompts` JSON columns to users
- Migration expanding `onboarding_focus_area` enum on users and `focus_area` on onboarding_progress

#### WS2-OB: Journey API Endpoints
- `JourneyController` with 8 endpoints (selections CRUD, steps, start/complete, prompts, dismiss)
- `StoreJourneySelectionsRequest` form request validation

#### WS3-OB: Welcome Page Redesign
- `FocusAreaGrid.vue` — 4×2 responsive grid (2-col mobile, 3 tablet, 4 desktop) with animated checkmarks
- `JourneyPreview.vue` — collapsible preview panel showing field counts, estimated minutes, InfoTooltips

#### WS4-OB: Journey Step Flow
- Journey mode added to `OnboardingWizard.vue` alongside quick/full/module modes
- `BudgetingSteps.vue`, `GoalSetupStep.vue`, `JourneyCompletionStep.vue` step components
- `journeys` Vuex store module with full state management
- `journeyService.js` API wrapper
- Routes: `/onboarding/welcome`, `/onboarding/journey/:journey`

#### WS5-OB: Info Tooltips
- `InfoTooltip.vue` — accessible tooltip (desktop hover, mobile tap, first-encounter pulse)
- `ModuleDataRequirementsService` updated with 4 new modules and `how_used` field

#### WS6-OB: Dashboard Integration
- `JourneyCard.vue` — 3-state journey cards (not_started/in_progress/completed) with progress bar
- `PostJourneyPrompt.vue` — dismissible prompt banners guiding next steps
- `EmptyDashboard.vue` — welcome card for users with no data
- `ProfileCompletionCards.vue` updated with journey-aware routing

#### WS7-OB: Preview Personas
- `PreviewUserSeeder` updated with persona-specific journey states and selections

#### WS8-OB: Testing
- 39 onboarding-specific tests (174 assertions) covering services, API, and full flow

---

## Database Migrations (7 new)

Run in this order:

```bash
php artisan migrate
```

| Migration | Purpose |
|-----------|---------|
| `2026_03_07_100806_add_progressive_onboarding_fields_to_users_table` | onboarding_mode, onboarding_asset_flags, onboarding_completed_at |
| `2026_03_07_120001_add_life_expectancy_override_to_users_table` | life_expectancy_override (nullable integer) |
| `2026_03_07_120002_add_care_cost_fields_to_retirement_profiles_table` | care_cost_annual, care_start_age |
| `2026_03_07_120003_add_last_reviewed_date_to_wills_table` | last_reviewed_date |
| `2026_03_07_150001_create_tax_action_definitions_table` | New table for tax action definitions |
| `2026_03_07_200001_add_journey_fields_to_users_table` | journey_states, journey_selections, dismissed_prompts JSON columns |
| `2026_03_07_200002_expand_onboarding_focus_area_enum` | Expands focus_area enums on users + onboarding_progress |

After migrations, reseed:

```bash
php artisan db:seed
```

---

## New API Routes (16)

| Method | Route | Controller | Purpose |
|--------|-------|------------|---------|
| POST | `/api/onboarding/complete-quick` | OnboardingController | Complete quick onboarding |
| GET | `/api/estate/letter-validation` | LetterValidationController | Letter-to-spouse cross-validation |
| GET | `/api/retirement/decumulation-analysis` | DecumulationController | Drawdown strategy analysis |
| GET | `/api/household/net-worth` | HouseholdController | Combined household net worth |
| GET | `/api/household/optimisations` | HouseholdController | Spousal tax optimisations |
| GET | `/api/household/death-scenario` | HouseholdController | Death-of-spouse scenario |
| GET | `/api/tax/optimisation-analysis` | TaxOptimisationController | Tax allowance analysis |
| GET | `/api/tax/strategies` | TaxOptimisationController | Tax optimisation strategies |
| GET | `/api/journeys/selections` | JourneyController | Get journey selections + states |
| POST | `/api/journeys/selections` | JourneyController | Save journey selections |
| GET | `/api/journeys/steps` | JourneyController | Get steps for selected journeys |
| POST | `/api/journeys/{journey}/start` | JourneyController | Start a journey |
| POST | `/api/journeys/{journey}/complete` | JourneyController | Complete a journey |
| POST | `/api/journeys/preview` | JourneyController | Preview fields for journey combo |
| GET | `/api/journeys/dashboard-prompts` | JourneyController | Get dashboard prompts |
| POST | `/api/journeys/dismiss-prompt` | JourneyController | Dismiss a dashboard prompt |

---

## Files to Upload

### PHP Agents (upload to `~/www/fynla.org/public_html/`)

```
app/Agents/CoordinatingAgent.php
app/Agents/EstateAgent.php
app/Agents/ProtectionAgent.php
app/Agents/RetirementAgent.php
app/Agents/TaxOptimisationAgent.php                          (NEW)
```

### PHP Controllers (upload to `~/www/fynla.org/public_html/`)

```
app/Http/Controllers/Api/AuthController.php
app/Http/Controllers/Api/ChattelController.php
app/Http/Controllers/Api/Estate/LetterValidationController.php   (NEW)
app/Http/Controllers/Api/HouseholdController.php                 (NEW)
app/Http/Controllers/Api/JourneyController.php                      (NEW)
app/Http/Controllers/Api/OnboardingController.php
app/Http/Controllers/Api/PersonalAccountsController.php
app/Http/Controllers/Api/Retirement/DecumulationController.php   (NEW)
app/Http/Controllers/Api/Tax/TaxOptimisationController.php       (NEW)
```

### PHP Models (upload to `~/www/fynla.org/public_html/`)

```
app/Models/Estate/Will.php
app/Models/RetirementProfile.php
app/Models/TaxActionDefinition.php                               (NEW)
app/Models/User.php
```

### PHP Services (upload to `~/www/fynla.org/public_html/`)

```
app/Services/AI/AiSimulatedResponseBuilder.php
app/Services/Coordination/CrossModuleStrategyService.php         (NEW)
app/Services/Coordination/HouseholdPlanningService.php           (NEW)
app/Services/Coordination/RecommendationPersonaliser.php         (NEW)
app/Services/Coordination/RecommendationsAggregatorService.php
app/Services/Estate/FutureValueCalculator.php
app/Services/Estate/LetterEstateValidationService.php            (NEW)
app/Services/Onboarding/DashboardPromptService.php                   (NEW)
app/Services/Onboarding/JourneyFieldResolver.php                     (NEW)
app/Services/Onboarding/JourneyStateService.php                      (NEW)
app/Services/Onboarding/OnboardingService.php
app/Services/Plans/EstatePlanService.php
app/Services/Plans/InvestmentPlanService.php
app/Services/Retirement/DecumulationPlanner.php
app/Services/Risk/AutoRiskCalculator.php
app/Services/Risk/RiskPreferenceService.php
app/Services/Tax/TaxActionDefinitionService.php                  (NEW)
app/Services/Tax/TaxOptimisationService.php                      (NEW)
app/Services/UserProfile/UserProfileService.php
```

### PHP Form Requests (upload to `~/www/fynla.org/public_html/`)

```
app/Http/Requests/Estate/StoreWillRequest.php
app/Http/Requests/Investment/StoreRiskProfileRequest.php
app/Http/Requests/StoreInvestmentAccountRequest.php
app/Http/Requests/UpdateInvestmentAccountRequest.php
app/Http/Requests/UpdatePersonalInfoRequest.php
app/Http/Requests/Onboarding/StoreJourneySelectionsRequest.php       (NEW)
```

### PHP Other (upload to `~/www/fynla.org/public_html/`)

```
app/Observers/LifeEventRiskObserver.php                          (NEW)
app/Providers/EventServiceProvider.php
routes/api.php
```

### Database Files (upload to `~/www/fynla.org/public_html/`)

```
database/migrations/2026_03_07_100806_add_progressive_onboarding_fields_to_users_table.php   (NEW)
database/migrations/2026_03_07_120001_add_life_expectancy_override_to_users_table.php        (NEW)
database/migrations/2026_03_07_120002_add_care_cost_fields_to_retirement_profiles_table.php   (NEW)
database/migrations/2026_03_07_120003_add_last_reviewed_date_to_wills_table.php               (NEW)
database/migrations/2026_03_07_150001_create_tax_action_definitions_table.php                  (NEW)
database/migrations/2026_03_07_200001_add_journey_fields_to_users_table.php                   (NEW)
database/migrations/2026_03_07_200002_expand_onboarding_focus_area_enum.php                   (NEW)
database/seeders/DatabaseSeeder.php
database/seeders/TaxActionDefinitionSeeder.php                                                (NEW)
database/factories/LetterToSpouseFactory.php                                                  (NEW)
database/factories/TaxActionDefinitionFactory.php                                             (NEW)
```

### Frontend Files (included in build — no separate upload needed)

```
resources/js/components/Dashboard/CrossModuleInsights.vue        (NEW)
resources/js/components/Dashboard/DeathOfSpouseScenario.vue      (NEW)
resources/js/components/Dashboard/EmptyDashboard.vue             (NEW)
resources/js/components/Dashboard/HouseholdNetWorth.vue          (NEW)
resources/js/components/Dashboard/JourneyCard.vue                (NEW)
resources/js/components/Dashboard/PostJourneyPrompt.vue          (NEW)
resources/js/components/Dashboard/ProfileCompletionCards.vue     (NEW)
resources/js/components/Dashboard/SpousalOptimisations.vue      (NEW)
resources/js/components/Estate/IHTPlanning.vue
resources/js/components/Estate/LetterEstateWarnings.vue         (NEW)
resources/js/components/Estate/WillPlanning.vue
resources/js/components/Investment/AccountForm.vue
resources/js/components/Investment/PrivateInvestmentFields.vue
resources/js/components/Investment/RiskMismatchWarning.vue      (NEW)
resources/js/components/NetWorth/InvestmentList.vue
resources/js/components/NetWorth/PensionList.vue
resources/js/components/Onboarding/FocusAreaGrid.vue             (NEW)
resources/js/components/Onboarding/FocusAreaSelection.vue
resources/js/components/Onboarding/JourneyPreview.vue            (NEW)
resources/js/components/Onboarding/OnboardingWizard.vue
resources/js/components/Onboarding/steps/BudgetingSteps.vue      (NEW)
resources/js/components/Onboarding/steps/GoalSetupStep.vue       (NEW)
resources/js/components/Onboarding/steps/JourneyCompletionStep.vue (NEW)
resources/js/components/Onboarding/steps/PersonalInfoStep.vue
resources/js/components/Onboarding/steps/QuickAssetsStep.vue    (NEW)
resources/js/components/Shared/InfoTooltip.vue                   (NEW)
resources/js/components/Plans/Shared/PlanActionCard.vue
resources/js/components/Protection/RecommendationCard.vue
resources/js/components/Retirement/DecumulationStrategyCard.vue (NEW)
resources/js/components/Tax/TaxStrategiesCard.vue               (NEW)
resources/js/components/UserProfile/FamilyMembers.vue
resources/js/components/UserProfile/LetterToSpouse.vue
resources/js/components/UserProfile/PersonalInformation.vue
resources/js/router/index.js
resources/js/services/journeyService.js                         (NEW)
resources/js/services/householdService.js                       (NEW)
resources/js/services/investmentService.js
resources/js/services/onboardingService.js
resources/js/services/retirementService.js
resources/js/services/riskService.js
resources/js/services/taxOptimisationService.js                 (NEW)
resources/js/store/index.js
resources/js/store/modules/journeys.js                          (NEW)
resources/js/store/modules/household.js                         (NEW)
resources/js/store/modules/onboarding.js
resources/js/store/modules/retirement.js
resources/js/store/modules/taxOptimisation.js                   (NEW)
resources/js/store/modules/userProfile.js
resources/js/views/Dashboard.vue
resources/js/views/Investment/PrivateInvestmentDetail.vue
resources/js/views/Onboarding/OnboardingFullView.vue            (NEW)
resources/js/views/Onboarding/OnboardingModuleView.vue          (NEW)
resources/js/views/Risk/RiskProfilePage.vue
resources/js/views/Settings/AssumptionsSettings.vue             (NEW)
resources/js/views/UKTaxes/UKTaxesDashboard.vue
```

---

## Deploy Steps

### 1. Build locally

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload to SiteGround

Upload all PHP files listed above to their respective paths under `~/www/fynla.org/public_html/`.

Upload the built frontend:
```
public/build/ -> ~/www/fynla.org/public_html/public/build/
```

### 3. Run migrations and seed via SSH

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate
php artisan db:seed
```

### 4. Clear caches

```bash
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Verification

After deploy, verify:

1. **Onboarding (Journey Mode)** — New user registration shows 4×2 focus area grid; selecting areas reveals field preview with info tooltips; starting journeys loads tailored step flow
2. **Dashboard (Journeys)** — Journey cards show correct states (not_started/in_progress/completed); post-journey prompts appear after completion; empty dashboard shows for new users with no data
3. **Onboarding (Legacy)** — Quick Setup and Full Setup still work for users who don't select focus areas
4. **Dashboard** — Profile completion cards appear for quick-onboarded users with missing data
3. **Dashboard (married)** — Household net worth, spousal optimisations, death scenario cards visible for `peak_earners`
4. **Dashboard** — Cross-module insights card shows strategy recommendations
5. **Retirement** — Decumulation strategy card appears on retirement dashboard with drawdown projections
6. **Estate** — Letter to Spouse page shows cross-validation warnings when data mismatches exist
7. **Estate** — Will planning shows "Last Reviewed" date field and stale warning (3+ years)
8. **UK Taxes** — Tax Strategies tab shows ISA/pension/CGT/spousal recommendations
9. **Investment** — Private investment form no longer shows company sector, voting rights, dividend rights
10. **Risk profile** — Shows 9 factors (was 7), risk mismatch warning appears when manual differs from auto
11. **User profile** — National Insurance number no longer shown in personal information
12. **Settings** — Assumptions page shows life expectancy override option
13. **Preview personas** — All 6 personas load and display correctly

---

## File Counts

| Category | New | Modified | Total |
|----------|-----|----------|-------|
| PHP Agents | 1 | 4 | 5 |
| PHP Controllers | 5 | 4 | 9 |
| PHP Models | 1 | 3 | 4 |
| PHP Services | 10 | 9 | 19 |
| PHP Requests | 1 | 5 | 6 |
| PHP Other | 1 | 1 | 2 |
| Routes | 0 | 1 | 1 |
| Migrations | 7 | 0 | 7 |
| Seeders | 1 | 1 | 2 |
| Factories | 2 | 0 | 2 |
| Vue Components | 21 | 15 | 36 |
| Vue Views | 3 | 6 | 9 |
| JS Services | 3 | 4 | 7 |
| JS Stores | 3 | 4 | 7 |
| JS Router | 0 | 1 | 1 |
| **Total PHP to upload** | | | **55** |
| **Total frontend (in build)** | | | **60** |
