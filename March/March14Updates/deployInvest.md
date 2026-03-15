# Deploy: Decision Engine Upgrade

**Date:** 2026-03-14
**Branch:** `engineUpgrade`
**Scope:** 5 modules (Cash/Savings, Estate Planning, Investment, Protection, Retirement)
**Tests:** 1871 passed, 0 failures (8179 assertions)
**Deployed to production:** 2026-03-15 (combined with March 15 fixes — see `March/March15Updates/deploy.md`)

---

## Stage 3 — Decision Engine Upgrade Files

### New PHP Files (create these)

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
database/seeders/SavingsActionDefinitionSeeder.php
```

### New Vue Files (frontend rebuild required)

```
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
app/Http/Controllers/Api/SavingsController.php
app/Models/CashAccount.php
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

### Modified Vue/JS Files (frontend rebuild required)

```
resources/js/components/Dashboard/FinancialHealthScore.vue
resources/js/store/modules/estate.js
resources/js/store/modules/investment.js
resources/js/store/modules/protection.js
resources/js/store/modules/retirement.js
resources/js/store/modules/savings.js
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

### 3. SSH and run migrations + seed + cache clear

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate --force
php artisan db:seed --force
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

**CRITICAL:** The migrations create 4 new tables and add columns. The seeder adds 41 savings triggers, 17 protection triggers, and 8 retirement triggers. The SavingsActionDefinitionSeeder also disables 7 overlapping Investment triggers.

---

## What Changed (User-Facing)

### Savings Module
1. **Personal Savings Allowance analysis** — Shows tax-free interest allowance usage, breach warnings, and ISA recommendations
2. **Financial Services Compensation Scheme exposure** — Alerts when savings at one institution group exceed the £85,000 protection limit
3. **Employment-based emergency fund targets** — Self-employed/contractors get 9-month target; employed get 6; retired get 3
4. **41 recommendation triggers** — Database-driven savings recommendations covering emergency fund, tax efficiency, rate optimisation, debt comparison, children's savings, and spouse coordination
5. **Maturity and rate expiry alerts** — Daily notifications for fixed-rate maturity and promotional rate expiry at 90/30/7 days
6. **ISA allowance reminders** — Year-end warnings when ISA allowance remains unused

### Estate Planning Module
7. **2027 pension Inheritance Tax amendment** — Dual-scenario projection showing current vs post-April 2027 liability with notification banner
8. **14-year rule enforcement** — Historical chargeable lifetime transfers now correctly reduce Nil Rate Band for later potentially exempt transfers
9. **Enhanced life insurance checks** — Warnings for policies not in trust, single-life policies for married users, term policies approaching expiry
10. **Liquidity reclassification** — Investments now semi-liquid, pensions now illiquid (from liquid/semi-liquid)
11. **Trust Nil Rate Band avoidance projection** — Year-by-year growth trajectory for planned trust settlements
12. **Gift and trust alerts** — 7-year exemption reminders and 10-year anniversary notifications

### Investment Module
13. **Full recommendation pipeline** — 9-phase sequential pipeline: safety checks, life event assessment, goal mapping, contribution waterfall (11 steps: Lifetime ISA to General Investment Account), transfer scans (13 types), spouse optimisation (7 strategies), conflict resolution
14. **Data readiness gates** — Blocking checks prevent analysis with incomplete data; actionable missing data cards link to input forms
15. **Decision paths** — "What Drives This" display showing the reasoning behind each recommendation

### Protection Module
16. **Employer benefits integration** — Death-in-service, group income protection, and group critical illness deducted from coverage gaps
17. **State benefits offset** — Statutory Sick Pay correctly calculated (weekly rate x max weeks, not annualised; self-employed excluded)
18. **17 new recommendation triggers** — Employer reliance warnings, policy trust status, expiry alerts, income protection definition checks, mortgage cover, education funding gaps
19. **Score removal** — Numeric adequacy scores replaced with descriptive ratings (Excellent/Good/Fair/Critical) per design rules

### Retirement Module
20. **Salary sacrifice analysis** — National Insurance savings calculator with floor checks and warnings
21. **Auto-enrolment compliance** — Checks qualifying earnings, minimum contribution rates, employer/employee splits
22. **Enhanced annuity assessment** — Smoker/health status wired into annuity rate estimates
23. **8 new recommendation triggers** — Salary sacrifice, auto-enrolment, enhanced annuity, care costs, State Pension forecast, consolidation

### Cross-Module
24. **All hardcoded tax values centralised** — Every financial constant now sourced from TaxConfigService
25. **Data readiness gates on all 5 modules** — No more assumptions or auto-created profiles
26. **Vuex store guards** — Frontend handles `can_proceed = false` gracefully across all modules
27. **Emergency fund ownership** — Savings engine owns emergency fund recommendations; Investment only gates surplus

---

## Rollback

If issues arise:

```bash
# Revert to previous commit
git checkout main -- app/ config/ database/ resources/

# Rollback migrations (6 new migrations)
php artisan migrate:rollback --step=6

# Re-seed to restore original trigger states
php artisan db:seed --force

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan optimize
```
