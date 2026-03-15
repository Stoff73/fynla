# Deploy: Decision Engine Upgrade + Login Fix + Code Review Remediation

**Dates:** 2026-03-14 to 2026-03-15
**Branch:** `engineUpgrade` → merged to `main` (PR #124)
**Version:** v0.8.3 → v0.9.0
**Tests:** 1,873 passed, 0 failures
**Scope:** Decision Engine Upgrade (5 modules, 31 tasks), login bug fix, full code review remediation (13 issues), plan endpoint fixes, investment plan cross-module isolation

---

## Overview

This is a combined deployment covering the Decision Engine Upgrade (March 14) and the subsequent bug fixes, code review remediation, and plan isolation work (March 15). All changes have been merged to `main` via PR #124.

---

## Part 1: Decision Engine Upgrade (March 14)

### Savings Module
1. **Personal Savings Allowance analysis** — shows tax-free interest allowance usage, breach warnings, ISA recommendations
2. **Financial Services Compensation Scheme exposure** — alerts when savings at one institution group exceed £85,000
3. **Employment-based emergency fund targets** — self-employed 9 months, employed 6, retired 3
4. **41 recommendation triggers** — database-driven covering emergency fund, tax efficiency, rate optimisation, debt comparison, children's savings, spouse coordination
5. **Maturity and rate expiry alerts** — daily notifications at 90/30/7 days
6. **ISA allowance reminders** — year-end warnings when unused

### Estate Planning Module
7. **2027 pension Inheritance Tax amendment** — dual-scenario projection showing current vs post-April 2027 liability
8. **14-year rule enforcement** — historical chargeable lifetime transfers correctly reduce Nil Rate Band for later potentially exempt transfers
9. **Enhanced life insurance checks** — warnings for policies not in trust, single-life policies for married users
10. **Liquidity reclassification** — investments now semi-liquid, pensions now illiquid
11. **Trust Nil Rate Band avoidance projection** — year-by-year growth trajectory
12. **Gift and trust alerts** — 7-year exemption reminders and 10-year anniversary notifications

### Investment Module
13. **Full recommendation pipeline** — 9-phase sequential pipeline: safety checks, life event assessment, goal mapping, contribution waterfall (11 steps), transfer scans (13 types), spouse optimisation (7 strategies), conflict resolution
14. **Data readiness gates** — blocking checks prevent analysis with incomplete data
15. **Decision paths** — "What Drives This" display showing reasoning

### Protection Module
16. **Employer benefits integration** — death-in-service, group income protection, group critical illness deducted from gaps
17. **State benefits offset** — Statutory Sick Pay correctly calculated (self-employed excluded)
18. **17 new recommendation triggers** — employer reliance, policy trust status, expiry, income protection, mortgage cover, education funding gaps
19. **Score removal** — numeric adequacy scores replaced with descriptive ratings (Excellent/Good/Fair/Critical)

### Retirement Module
20. **Salary sacrifice analysis** — National Insurance savings calculator with floor checks
21. **Auto-enrolment compliance** — qualifying earnings, minimum contribution rates
22. **Enhanced annuity assessment** — smoker/health status wired into annuity estimates
23. **8 new recommendation triggers** — salary sacrifice, auto-enrolment, enhanced annuity, care costs, State Pension forecast

### Cross-Module
24. **All hardcoded tax values centralised** — every constant from TaxConfigService
25. **Data readiness gates on all 5 modules** — no assumptions or auto-created profiles
26. **Emergency fund ownership** — Savings engine owns emergency fund; Investment only gates surplus

---

## Part 2: Login Fix + Code Review Remediation (March 15)

### Login Bug Fix (Critical)
- **Root cause:** Challenge tokens stored in Laravel cache. When cache driver resolved to `array` (in-memory only), tokens were lost between requests → 422 on every verification code entry.
- **Fix:** Moved challenge token storage to `email_verification_codes` database table.
- **Security:** Removed `user_id` from pre-auth API responses to prevent user enumeration.

### Code Review Remediation (13 issues)
| Severity | Issue | Fix |
|----------|-------|-----|
| Critical | Hardcoded IHT tax values in 4 Vue components | Replaced with `taxConfig.js` imports (`IHT_NIL_RATE_BAND`, `IHT_STANDARD_RATE`, etc.) |
| High | Tax efficiency score badge shows percentage | Replaced with descriptive labels ("Well Sheltered", "Partially Sheltered") |
| High | 14+ hardcoded hex colors in 5 components | Replaced with `designSystem.js` (`ASSET_COLORS`, `TEXT_COLORS`, `CONFETTI_COLORS`) |
| High | PreviewWriteInterceptor echoes passwords | Added `SENSITIVE_FIELDS` filter |
| High | Hardcoded ISA allowance `= 20000` | Changed to `TaxConfigService` fallback |
| Medium | Dead `computePreviewStrategy()` (97 lines) | Removed |
| Medium | Duplicate `@keyframes` in 2 components | Extracted to `app.css` |
| Medium | No arch tests for Models/Controllers | Added strict types tests |
| Medium | `PropertyController.store()` unwrapped response | Wrapped in standard `{ success, data }` format |
| Medium | Score text in Help/Version pages | Replaced with descriptive text |
| Medium | No query limits on Estate/Goals/Savings | Added `.limit(100)` |
| Medium | Orphaned `CoverageAdequacyGauge.vue` | Deleted |
| Medium | `DebugEnv` route in production | Already guarded (no change needed) |

### Plan Endpoint Fixes (3 bugs)
| Bug | Fix |
|-----|-----|
| Investment plan 500 for all personas | `ConflictResolutionService::priorityRank()` accepts `string\|int` |
| Investment plan 500 for entrepreneur/young_saver | `ContributionWaterfallService` accesses `lifetime_isa.annual_allowance` correctly |
| Retirement plan 500 for retired_couple | `RetirementActionDefinitionService` guards against null profile |

### Investment Plan Isolation
- Individual investment plan was mixing in pension contributions, savings allowance, and marriage allowance actions from the spouse/transfer pipeline
- These cross-module actions now only appear in the **holistic plan**
- Generic "Recommendation" titles fixed — `BasePlanService` now maps `headline` → `title` and `explanation` → `description`

### Property Page Fix
- `PropertyList.vue` updated for wrapped API response format after `PropertyController.store()` change

---

## All Files to Deploy

### New Files (create these)

```
app/Console/Commands/SendEstateAlerts.php
app/Console/Commands/SendProtectionAlerts.php
app/Console/Commands/SendSavingsAlerts.php
app/Models/SavingsActionDefinition.php
app/Notifications/EmergencyFundAlertNotification.php
app/Notifications/GiftExemptionNotification.php
app/Notifications/ISAAllowanceWarningNotification.php
app/Notifications/ProtectionAlertNotification.php
app/Notifications/SavingsMaturityAlertNotification.php
app/Notifications/SavingsRateExpiryNotification.php
app/Notifications/TrustAnniversaryNotification.php
app/Services/Estate/EstateDataReadinessService.php
app/Services/Investment/Recommendation/ConflictResolutionService.php
app/Services/Investment/Recommendation/ContributionWaterfallService.php
app/Services/Investment/Recommendation/DataReadinessService.php
app/Services/Investment/Recommendation/GoalAssessmentService.php
app/Services/Investment/Recommendation/LifeEventAssessmentService.php
app/Services/Investment/Recommendation/RecommendationOutputFormatter.php
app/Services/Investment/Recommendation/SafetyCheckService.php
app/Services/Investment/Recommendation/SpouseOptimisationService.php
app/Services/Investment/Recommendation/TransferRecommendationService.php
app/Services/Investment/Recommendation/UserContextBuilder.php
app/Services/Plans/SavingsPlanService.php
app/Services/Protection/ProtectionDataReadinessService.php
app/Services/Retirement/RetirementDataReadinessService.php
app/Services/Retirement/SalarySacrificeAnalyzer.php
app/Services/Savings/FSCSAssessor.php
app/Services/Savings/PSACalculator.php
app/Services/Savings/SavingsActionDefinitionService.php
app/Services/Savings/SavingsDataReadinessService.php
config/banking_licence_groups.php
database/migrations/2026_03_14_100001_create_savings_action_definitions_table.php
database/migrations/2026_03_14_100002_create_goal_savings_account_table.php
database/migrations/2026_03_14_100003_add_employer_benefits_to_protection_profiles.php
database/migrations/2026_03_14_100004_add_joint_life_to_life_insurance_policies.php
database/migrations/2026_03_14_100005_migrate_goal_savings_account_links.php
database/migrations/2026_03_14_100006_add_estate_alerts_to_notification_preferences.php
database/migrations/2026_03_15_074247_add_challenge_token_to_email_verification_codes_table.php
database/seeders/SavingsActionDefinitionSeeder.php
resources/js/components/Estate/PensionAmendmentBanner.vue
resources/js/components/Investment/InvestmentReadinessGate.vue
resources/js/components/Retirement/SalarySacrificeDisplay.vue
resources/js/components/Savings/MissingDataCard.vue
resources/js/components/Savings/SavingsDecisionPath.vue
```

### Modified PHP Files (replace these)

```
app/Agents/CoordinatingAgent.php
app/Agents/EstateAgent.php
app/Agents/InvestmentAgent.php
app/Agents/ProtectionAgent.php
app/Agents/RetirementAgent.php
app/Agents/SavingsAgent.php
app/Console/Kernel.php
app/Constants/EstateDefaults.php
app/Http/Controllers/Api/AuthController.php
app/Http/Controllers/Api/EstateController.php
app/Http/Controllers/Api/GoalsController.php
app/Http/Controllers/Api/PropertyController.php
app/Http/Controllers/Api/SavingsController.php
app/Http/Middleware/PreviewWriteInterceptor.php
app/Models/CashAccount.php
app/Models/EmailVerificationCode.php
app/Models/Goal.php
app/Models/LifeInsurancePolicy.php
app/Models/NotificationPreference.php
app/Models/ProtectionProfile.php
app/Models/SavingsAccount.php
app/Observers/SavingsAccountGoalObserver.php
app/Services/Estate/AssetLiquidityAnalyzer.php
app/Services/Estate/FutureValueCalculator.php
app/Services/Estate/GiftingStrategyOptimizer.php
app/Services/Estate/IHTCalculationService.php
app/Services/Estate/LifeCoverCalculator.php
app/Services/Estate/LifePolicyStrategyService.php
app/Services/Estate/PersonalizedGiftingStrategyService.php
app/Services/Estate/PersonalizedTrustStrategyService.php
app/Services/Estate/SpouseNRBTrackerService.php
app/Services/Investment/Analytics/HoldingsDataExtractor.php
app/Services/Investment/AssetLocation/AssetLocationOptimizer.php
app/Services/Investment/AssetLocation/TaxDragCalculator.php
app/Services/Investment/ContributionOptimizer.php
app/Services/Investment/FeeAnalyzer.php
app/Services/Investment/Fees/OCFImpactCalculator.php
app/Services/Investment/Goals/GoalProgressAnalyzer.php
app/Services/Investment/Goals/ShortfallAnalyzer.php
app/Services/Investment/InvestmentActionDefinitionService.php
app/Services/Investment/PortfolioStrategyService.php
app/Services/Investment/Tax/BedAndISACalculator.php
app/Services/Investment/Tax/ISAAllowanceOptimizer.php
app/Services/Investment/Tax/TaxOptimizationAnalyzer.php
app/Services/Onboarding/EstateOnboardingFlow.php
app/Services/Plans/BasePlanService.php
app/Services/Plans/EstatePlanService.php
app/Services/Plans/GoalPlanService.php
app/Services/Plans/InvestmentPlanService.php
app/Services/Plans/ProtectionPlanService.php
app/Services/Protection/AdequacyScorer.php
app/Services/Protection/ComprehensiveProtectionPlanService.php
app/Services/Protection/CoverageGapAnalyzer.php
app/Services/Protection/ProtectionActionDefinitionService.php
app/Services/Protection/RecommendationEngine.php
app/Services/Protection/ScenarioBuilder.php
app/Services/Retirement/ContributionOptimizer.php
app/Services/Retirement/DecumulationPlanner.php
app/Services/Retirement/PensionProjector.php
app/Services/Retirement/RequiredCapitalCalculator.php
app/Services/Retirement/RetirementActionDefinitionService.php
app/Services/Retirement/RetirementIncomeService.php
app/Services/Retirement/RetirementProjectionService.php
app/Services/Retirement/RetirementStrategyService.php
app/Services/Savings/EmergencyFundCalculator.php
app/Services/Savings/RateComparator.php
app/Services/Settings/AssumptionsService.php
app/Services/Tax/TaxOptimisationService.php
app/Services/Tax/TaxProductInfoService.php
app/Services/TaxConfigService.php
app/Services/UKTaxCalculator.php
app/Traits/TracksGoalContributions.php
database/seeders/DatabaseSeeder.php
database/seeders/ProtectionActionDefinitionSeeder.php
database/seeders/RetirementActionDefinitionSeeder.php
database/seeders/TaxConfigurationSeeder.php
```

### Modified Frontend Files (frontend rebuild required)

```
resources/css/app.css
resources/js/components/Dashboard/FinancialHealthScore.vue
resources/js/components/Estate/IHTCalculationTable.vue
resources/js/components/Estate/IHTPlanning.vue
resources/js/components/Estate/LifePolicyStrategy.vue
resources/js/components/NetWorth/InvestmentProjections.vue
resources/js/components/NetWorth/PropertyList.vue
resources/js/components/Onboarding/steps/BudgetingCompletionStep.vue
resources/js/components/Onboarding/steps/JourneyCompletionStep.vue
resources/js/constants/designSystem.js
resources/js/mobile/components/MobileProjectionChart.vue
resources/js/mobile/goals/MilestoneOverlay.vue
resources/js/mobile/views/EstateDetail.vue
resources/js/mobile/views/MobileLoginScreen.vue
resources/js/store/modules/estate.js
resources/js/store/modules/investment.js
resources/js/store/modules/protection.js
resources/js/store/modules/retirement.js
resources/js/store/modules/savings.js
resources/js/views/Help.vue
resources/js/views/Investment/AccountHoldingsPanel.vue
resources/js/views/Investment/AccountPerformancePanel.vue
resources/js/views/Login.vue
resources/js/views/Trusts/TrustsDashboard.vue
resources/js/views/Version.vue
```

### Deleted Files (remove from server)

```
resources/js/components/Protection/CoverageAdequacyGauge.vue
```

---

## Deploy Steps

### 1. Build frontend locally

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload via SiteGround File Manager

Upload all files listed above to `~/www/fynla.org/public_html/` maintaining directory structure.

Upload `public/build/` directory to `~/www/fynla.org/public_html/public/build/`.

Delete `resources/js/components/Protection/CoverageAdequacyGauge.vue` from server.

### 3. SSH and run migrations + seed + cache clear

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run all migrations (7 new: 6 from March 14 + 1 from March 15)
php artisan migrate --force

# Reseed (adds triggers, disables overlaps, updates tax config)
php artisan db:seed --force

# Clear all caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

**CRITICAL:** The `SavingsActionDefinitionSeeder` disables 7 overlapping Investment triggers. Always run `db:seed` after migrations.

---

## Fynla Brain Update — v0.9.0

### What the Fynla Brain now does

The Fynla Brain is the proprietary engine powering all financial analysis and recommendations. This upgrade transforms it from static rule-based advice to a dynamic, data-aware decision engine.

**Before (v0.8.3):** Static recommendations from hardcoded rules. No data validation. Modules operated independently. Tax values scattered across codebase.

**After (v0.9.0):**

| Capability | Detail |
|-----------|--------|
| **Data Readiness Gates** | All 5 modules validate data completeness before analysis. Users see exactly what's missing with links to input forms. No more false results from incomplete data. |
| **Centralised Tax Engine** | Every UK tax value (Nil Rate Band, ISA, Pension Annual Allowance, Personal Savings Allowance, Capital Gains Tax exemption, etc.) sourced from `TaxConfigService`. Tax year changes update everywhere automatically. |
| **Investment Pipeline** | 9-phase sequential engine: safety checks → life events → goal mapping → contribution waterfall (11 priority steps: Lifetime ISA → ISA → Pension → Premium Bonds → General Investment Account) → transfer scans (13 types) → spouse optimisation (7 strategies) → conflict resolution |
| **Savings Engine** | 41 database-driven triggers covering emergency fund, ISA optimisation, rate comparison, Personal Savings Allowance breach, Financial Services Compensation Scheme exposure, children's savings, and debt comparison |
| **Protection Engine** | 28 triggers with employer benefits integration, state benefits offset, and descriptive ratings (no numeric scores) |
| **Retirement Engine** | 18 triggers with salary sacrifice analysis, auto-enrolment compliance, and enhanced annuity assessment |
| **Estate Engine** | 2027 pension amendment modelling, 14-year gift rule, liquidity analysis, trust projections |
| **Cross-Module Coordination** | Emergency fund owned by Savings only. ISA allowance shared. Individual plans stay module-focused. Only the holistic plan coordinates across modules. |
| **Module Isolation** | Individual plans (Investment, Retirement, Protection, Estate) contain only their own module's recommendations. Cross-module optimisation (pension contributions, savings allowance, marriage allowance) appears only in the holistic plan. |
| **Security Hardening** | Challenge tokens in database (not cache), user ID removed from pre-auth responses, sensitive fields filtered from preview echo, query limits on all endpoints |

### Design System Compliance
- All hardcoded hex colours replaced with `designSystem.js` constants
- All hardcoded IHT tax values replaced with `taxConfig.js` constants
- Score badges replaced with descriptive text labels
- Orphaned components removed
- Duplicate CSS extracted to global stylesheet
- Architecture tests enforce strict types on all Models and Controllers

---

## Rollback

If issues arise:

```bash
# Revert to previous main
git checkout main~1 -- app/ config/ database/ resources/

# Rollback migrations (7 new migrations)
php artisan migrate:rollback --step=7

# Re-seed to restore original trigger states
php artisan db:seed --force

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan optimize
```

---

## Post-Deploy Verification

```bash
# Verify database state
php artisan tinker --execute="
echo 'TaxConfig: ' . \App\Models\TaxConfiguration::where('is_active', true)->count();
echo 'Savings triggers: ' . \App\Models\SavingsActionDefinition::where('is_enabled', true)->count();
echo 'Investment enabled: ' . \App\Models\InvestmentActionDefinition::where('is_enabled', true)->count();
echo 'Investment disabled: ' . \App\Models\InvestmentActionDefinition::where('is_enabled', false)->count();
echo 'Protection triggers: ' . \App\Models\ProtectionActionDefinition::where('is_enabled', true)->count();
echo 'Retirement triggers: ' . \App\Models\RetirementActionDefinition::count();
"
```

**Expected:** TaxConfig: 1, Savings: 41, Investment enabled: 14, disabled: 7, Protection: 25+, Retirement: 18

```bash
# Verify login works
curl -s -X POST https://fynla.org/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"test"}' | python3 -m json.tool
```

Should return 401 (invalid credentials), not 500. If login verification works, the challenge token migration succeeded.
