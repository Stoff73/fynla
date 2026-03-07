# Deploy — March 6 (Tech Debt Audit, Security Fixes & Simplify)

**Date:** 6 March 2026
**Deployed:** 7 March 2026
**Commits:** `b839bea` (tech debt audit), `ce8f992` (security fixes) + simplify changes

---

## Summary

### Tech Debt Audit (b839bea)
- Comprehensive fixes across 146 files — controllers, models, services, factories, Vue components
- Consistent error handling, type safety, and code quality improvements

### Security Fixes (ce8f992)
- Upgraded html2pdf.js (0.12 to 0.14), jspdf (3 to 4.2), dompurify (3.3.1 to 3.3.2)
- Updated symfony/http-foundation, symfony/process, psy/psysh, phpunit
- npm audit and composer audit now report 0 vulnerabilities

### Simplify (uncommitted)
- **CSS typo:** `bg-eggshell-5000` corrected to `bg-eggshell-500` in Protection badge fallbacks
- **Stale CGT allowance:** Hardcoded `12300` (2022/23 rate) replaced with TaxConfigService lookups
- **Hardcoded tax values replaced:** Income tax bands, ISA allowance, IHT NRB/RNRB now use TaxConfigService/TaxDefaults
- **Performance:** Removed `mousemove` listener from session inactivity detection, added 30-second throttle

---

## Files to Upload

### PHP Controllers (upload to `~/www/fynla.org/public_html/`)

```
app/Http/Controllers/Api/AiChatController.php
app/Http/Controllers/Api/AuthController.php
app/Http/Controllers/Api/BugReportController.php
app/Http/Controllers/Api/ChattelController.php
app/Http/Controllers/Api/GDPRController.php
app/Http/Controllers/Api/HolisticPlanningController.php
app/Http/Controllers/Api/InfoGuideController.php
app/Http/Controllers/Api/Investment/RebalancingCalculationController.php
app/Http/Controllers/Api/InvestmentProjectionController.php
app/Http/Controllers/Api/JointAccountLogController.php
app/Http/Controllers/Api/LetterToSpouseController.php
app/Http/Controllers/Api/MFAController.php
app/Http/Controllers/Api/OccupationController.php
app/Http/Controllers/Api/PasswordResetController.php
app/Http/Controllers/Api/PersonalAccountsController.php
app/Http/Controllers/Api/PostcodeLookupController.php
app/Http/Controllers/Api/ProfileCompletenessController.php
app/Http/Controllers/Api/SessionController.php
app/Http/Controllers/Api/SpousePermissionController.php
app/Http/Controllers/Api/TaxProductInfoController.php
app/Http/Controllers/Api/UKTaxesController.php
app/Http/Controllers/Api/WebhookController.php
```

### PHP Models (upload to `~/www/fynla.org/public_html/`)

```
app/Models/ExpenditureProfile.php
app/Models/ISAAllowanceTracking.php
app/Models/Investment/InvestmentGoal.php
app/Models/Investment/InvestmentScenario.php
app/Models/Investment/RiskProfile.php
app/Models/ProtectionProfile.php
app/Models/RetirementProfile.php
app/Models/UserAssumption.php
```

### PHP Services (upload to `~/www/fynla.org/public_html/`)

```
app/Services/AI/AiSimulatedResponseBuilder.php
app/Services/Coordination/ConflictResolver.php
app/Services/Coordination/RecommendationsAggregatorService.php
app/Services/Investment/AssetLocation/AssetLocationOptimizer.php
app/Services/Investment/Rebalancing/TaxAwareRebalancer.php
app/Services/Retirement/ContributionOptimizer.php
app/Services/Retirement/RetirementStrategyService.php
```

### Composer Dependencies (upload to `~/www/fynla.org/public_html/`)

```
composer.lock
```

### Frontend Files (included in build — no separate upload needed)

```
resources/js/components/Admin/DatabaseBackup.vue
resources/js/components/Goals/GoalContributionStreak.vue
resources/js/components/Investment/AccountForm.vue
resources/js/components/Investment/AssetLocationOptimizer.vue
resources/js/components/Investment/BenchmarkComparison.vue
resources/js/components/Investment/ContributionPlanner.vue
resources/js/components/Investment/CorrelationMatrix.vue
resources/js/components/Investment/FeeSavingsCalculator.vue
resources/js/components/Investment/GoalCard.vue
resources/js/components/Investment/GoalProjection.vue
resources/js/components/Investment/Goals.vue
resources/js/components/Investment/MonteCarloResults.vue
resources/js/components/Investment/Performance.vue
resources/js/components/Investment/PerformanceAttribution.vue
resources/js/components/Investment/PrivateInvestmentFields.vue
resources/js/components/Investment/RebalancingActions.vue
resources/js/components/Investment/TaxFees.vue
resources/js/components/Investment/WrapperOptimizer.vue
resources/js/components/Legal/StrategyDisclaimer.vue
resources/js/components/NetWorth/Property/PropertyTaxCalculator.vue
resources/js/components/Plans/Shared/PlanGoalSection.vue
resources/js/components/Protection/CurrentSituation.vue
resources/js/components/Protection/GapAnalysis.vue
resources/js/components/Retirement/AnnualAllowanceTracker.vue
resources/js/components/Retirement/RetirementIncomeTab.vue
resources/js/components/Retirement/TaxBreakdownCard.vue
resources/js/components/Risk/FactorBreakdownCard.vue
resources/js/components/Risk/InvestmentTypesAccordion.vue
resources/js/components/Risk/RiskProfileSummary.vue
resources/js/components/Shared/CountrySelector.vue
resources/js/components/Shared/RiskLevelSelector.vue
resources/js/components/UserProfile/LetterToSpouse.vue
resources/js/components/UserProfile/SpouseDataSharing.vue
resources/js/services/sessionLifecycleService.js
resources/js/store/modules/investment.js
resources/js/views/Settings/PrivacySettings.vue
```

---

## Deploy Steps

### 1. Build locally

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload to SiteGround

Upload the PHP files (22 controllers + 8 models + 7 services = 37 files) and `composer.lock` to their respective paths under `~/www/fynla.org/public_html/`.

Upload the built frontend:
```
public/build/ → ~/www/fynla.org/public_html/public/build/
```

### 3. Install updated composer dependencies via SSH

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
composer install --no-dev --optimize-autoloader
```

### 4. Clear caches via SSH

```bash
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Verification

After deploy, verify:

1. **Protection module** — Badge colours render correctly (no missing background on severity badges)
2. **Investment rebalancing** — CGT allowance shows current year value (not 12,300)
3. **Session timeout** — Inactivity logout still works after 15 minutes idle
4. **Plans** — All plan types generate without errors
5. **General** — Spot-check a few pages across modules for any regressions from tech debt changes

---

## File Counts

| Category | Count |
|----------|-------|
| PHP Controllers | 22 |
| PHP Models | 8 |
| PHP Services | 7 |
| Composer Dependencies | 1 |
| **Total files to upload** | **38** |
| Frontend (in build) | 36 |
| Build output | `public/build/` (separate) |
