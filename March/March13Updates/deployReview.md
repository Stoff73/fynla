# Deploy Notes — Code Review Remediation — 13 March 2026

**Status:** NOT YET DEPLOYED
**Branch:** `main`
**Commit:** `daece06`
**Type:** PHP files + frontend rebuild + database migration

## Summary

Full codebase remediation: 69 files changed across security, tax compliance, query safety, frontend compliance, and database fixes. All 1866 tests pass.

---

## Pre-Deploy: Build Frontend

The frontend must be rebuilt — 16 Vue components changed.

```bash
./deploy/fynla-org/build.sh
```

Then upload the entire `public/build/` directory.

---

## PHP Files to Upload

Upload via SiteGround File Manager to `~/www/fynla.org/public_html/`.

### Agents (3 files)

| Local | Remote |
|-------|--------|
| `app/Agents/GoalsAgent.php` | `~/www/fynla.org/public_html/app/Agents/` |
| `app/Agents/RetirementAgent.php` | `~/www/fynla.org/public_html/app/Agents/` |
| `app/Agents/SavingsAgent.php` | `~/www/fynla.org/public_html/app/Agents/` |

### Controllers (10 files)

| Local | Remote |
|-------|--------|
| `app/Http/Controllers/Api/AdminController.php` | `~/www/fynla.org/public_html/app/Http/Controllers/Api/` |
| `app/Http/Controllers/Api/AuthController.php` | `~/www/fynla.org/public_html/app/Http/Controllers/Api/` |
| `app/Http/Controllers/Api/BusinessInterestController.php` | `~/www/fynla.org/public_html/app/Http/Controllers/Api/` |
| `app/Http/Controllers/Api/ChattelController.php` | `~/www/fynla.org/public_html/app/Http/Controllers/Api/` |
| `app/Http/Controllers/Api/GoalsController.php` | `~/www/fynla.org/public_html/app/Http/Controllers/Api/` |
| `app/Http/Controllers/Api/InvestmentController.php` | `~/www/fynla.org/public_html/app/Http/Controllers/Api/` |
| `app/Http/Controllers/Api/MFAController.php` | `~/www/fynla.org/public_html/app/Http/Controllers/Api/` |
| `app/Http/Controllers/Api/PropertyController.php` | `~/www/fynla.org/public_html/app/Http/Controllers/Api/` |
| `app/Http/Controllers/Api/SavingsController.php` | `~/www/fynla.org/public_html/app/Http/Controllers/Api/` |
| `app/Http/Controllers/Api/Investment/AssetLocationController.php` | `~/www/fynla.org/public_html/app/Http/Controllers/Api/Investment/` |

### Middleware (1 file)

| Local | Remote |
|-------|--------|
| `app/Http/Middleware/SecurityHeaders.php` | `~/www/fynla.org/public_html/app/Http/Middleware/` |

### Services — Chattel (1 file)

| Local | Remote |
|-------|--------|
| `app/Services/Chattel/ChattelCGTService.php` | `~/www/fynla.org/public_html/app/Services/Chattel/` |

### Services — Coordination (2 files)

| Local | Remote |
|-------|--------|
| `app/Services/Coordination/CrossModuleStrategyService.php` | `~/www/fynla.org/public_html/app/Services/Coordination/` |
| `app/Services/Coordination/HouseholdPlanningService.php` | `~/www/fynla.org/public_html/app/Services/Coordination/` |

### Services — Estate (4 files)

| Local | Remote |
|-------|--------|
| `app/Services/Estate/ComprehensiveEstatePlanService.php` | `~/www/fynla.org/public_html/app/Services/Estate/` |
| `app/Services/Estate/EstateAssetAggregatorService.php` | `~/www/fynla.org/public_html/app/Services/Estate/` |
| `app/Services/Estate/IntestacyCalculator.php` | `~/www/fynla.org/public_html/app/Services/Estate/` |
| `app/Services/Estate/PersonalizedGiftingStrategyService.php` | `~/www/fynla.org/public_html/app/Services/Estate/` |

### Services — Goals (3 files)

| Local | Remote |
|-------|--------|
| `app/Services/Goals/GoalsProjectionService.php` | `~/www/fynla.org/public_html/app/Services/Goals/` |
| `app/Services/Goals/LifeEventAllocationService.php` | `~/www/fynla.org/public_html/app/Services/Goals/` |
| `app/Services/Goals/LifeEventService.php` | `~/www/fynla.org/public_html/app/Services/Goals/` |

### Services — Investment (4 files)

| Local | Remote |
|-------|--------|
| `app/Services/Investment/AssetLocation/AssetLocationOptimizer.php` | `~/www/fynla.org/public_html/app/Services/Investment/AssetLocation/` |
| `app/Services/Investment/DividendTaxCalculator.php` | `~/www/fynla.org/public_html/app/Services/Investment/` |
| `app/Services/Investment/PortfolioStrategyService.php` | `~/www/fynla.org/public_html/app/Services/Investment/` |
| `app/Services/Investment/TaxEfficiencyCalculator.php` | `~/www/fynla.org/public_html/app/Services/Investment/` |

### Services — NetWorth (1 file)

| Local | Remote |
|-------|--------|
| `app/Services/NetWorth/NetWorthService.php` | `~/www/fynla.org/public_html/app/Services/NetWorth/` |

### Services — Retirement (2 files)

| Local | Remote |
|-------|--------|
| `app/Services/Retirement/ContributionOptimizer.php` | `~/www/fynla.org/public_html/app/Services/Retirement/` |
| `app/Services/Retirement/RetirementIncomeService.php` | `~/www/fynla.org/public_html/app/Services/Retirement/` |

### Services — Shared (1 file)

| Local | Remote |
|-------|--------|
| `app/Services/Shared/CrossModuleAssetAggregator.php` | `~/www/fynla.org/public_html/app/Services/Shared/` |

### Services — Tax (1 file)

| Local | Remote |
|-------|--------|
| `app/Services/Tax/TaxProductInfoService.php` | `~/www/fynla.org/public_html/app/Services/Tax/` |

### Services — Root (1 file)

| Local | Remote |
|-------|--------|
| `app/Services/UKTaxCalculator.php` | `~/www/fynla.org/public_html/app/Services/` |

### Services — UserProfile (1 file)

| Local | Remote |
|-------|--------|
| `app/Services/UserProfile/PersonalAccountsService.php` | `~/www/fynla.org/public_html/app/Services/UserProfile/` |

### Routes (1 file)

| Local | Remote |
|-------|--------|
| `routes/api.php` | `~/www/fynla.org/public_html/routes/` |

### Database Migration (1 file)

| Local | Remote |
|-------|--------|
| `database/migrations/2026_03_13_200002_fix_savings_accounts_joint_owner_foreign_key.php` | `~/www/fynla.org/public_html/database/migrations/` |

---

## Frontend Build (Upload `public/build/`)

After running `./deploy/fynla-org/build.sh`, upload the entire `public/build/` directory.

**16 Vue components changed:**

| Component | What Changed |
|-----------|-------------|
| `Dashboard/FinancialHealthScore.vue` | Score removal, status dots |
| `Dashboard/GoalsProjectionChartDashboard.vue` | Hex colour fixes |
| `Dashboard/UKTaxesAllowancesCard.vue` | gray-200 to neutral-200 |
| `Goals/GoalsProjectionChart.vue` | Hex colour fixes |
| `Guidance/GuidanceTooltip.vue` | Hex colour fix (violet) |
| `Investment/AllocationComparison.vue` | Hex scrollbar colours |
| `Investment/CorrelationMatrix.vue` | Score removal |
| `Investment/DiversificationTab.vue` | Score removal, status labels |
| `Legal/StrategyDisclaimer.vue` | localStorage wrapper |
| `NetWorth/ChattelsList.vue` | Hex colour fix (raspberry) |
| `NetWorth/Property/AmortizationScheduleView.vue` | gray-200 to neutral-200 |
| `NetWorth/Property/PropertyTaxCalculator.vue` | gray-200 to neutral-200 |
| `Onboarding/steps/AssetsStep.vue` | Property response envelope |
| `Onboarding/steps/IncomeStep.vue` | Property response envelope |
| `Plans/Shared/PlanGoalSection.vue` | Hex border to violet-200 |
| `Preview/PreviewBanner.vue` | gray-500/600 to neutral |
| `Protection/CoverageAdequacyGauge.vue` | Score removal |
| `Retirement/DecumulationStrategyCard.vue` | gray-100 to savannah-100 |
| `Shared/InfoTooltip.vue` | localStorage wrapper |
| `SideMenu.vue` | localStorage wrapper |
| `UserProfile/PersonalAccounts.vue` | localStorage wrapper |
| `layouts/AppLayout.vue` | localStorage wrapper |
| `views/Dashboard.vue` | localStorage wrapper |
| `views/Public/PrivacyPolicyPage.vue` | gray-100 to savannah-100 |
| `views/Public/TermsOfServicePage.vue` | gray-100 to savannah-100 |
| `views/Register.vue` | localStorage wrapper |
| `utils/storage.js` | NEW — centralised localStorage wrapper |

---

## Files NOT to Upload (Dev Only)

These files changed but are not needed on production:

| File | Reason |
|------|--------|
| `.env.example` | Template only — not used at runtime |
| `package.json` | Dev dependency removal (html2pdf.js) |
| `package-lock.json` | Dev dependency lock |
| `tests/Feature/Api/PropertyControllerTest.php` | Test file |
| `tests/Unit/Services/Estate/IntestacyCalculatorTest.php` | Test file |

---

## Post-Deploy: SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run the new migration
php artisan migrate

# Clear all caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Upload Checklist

- [ ] Run `./deploy/fynla-org/build.sh` locally
- [ ] Upload `public/build/` directory
- [ ] Upload 3 Agent files
- [ ] Upload 10 Controller files
- [ ] Upload 1 Middleware file
- [ ] Upload 20 Service files
- [ ] Upload `routes/api.php`
- [ ] Upload 1 migration file
- [ ] SSH: `php artisan migrate`
- [ ] SSH: Clear caches and optimise
- [ ] Verify login works (IDOR fix touches AuthController)
- [ ] Verify MFA flow works (token invalidation change)
- [ ] Verify property listing loads (envelope change)
- [ ] Verify investment diversification page (score removal)
- [ ] Verify estate intestacy calculator (threshold change)

---

## Total: 36 PHP files + 1 migration + frontend build
