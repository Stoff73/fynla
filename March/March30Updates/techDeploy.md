# Deploy Guide — Tech Debt Fixes (30 March 2026)

## Summary

Tech debt quick wins + batch fixes: `$hidden` on account models, `$fillable` hardening, timer leak fixes, dead file removal, `console.error`→`logger.error` (154 files), `Intl.NumberFormat`→`currencyMixin` (15 files), API response standardisation (3 controllers), duplicate service class renames, version bump to v0.9.3.2.

**Frontend + backend changes.** Requires rebuild, PHP upload, cache clear. No new migrations.

**IMPORTANT:** This deploys ON TOP of the code review fixes (reviewDeploy.md). If reviewDeploy.md has not been deployed yet, deploy BOTH together — this guide includes all additional files.

## Pre-Deploy: Rebuild

```bash
./deploy/fynla-org/build.sh
```

## Upload

### 1. Frontend (compiled build)

Upload the entire `public/build/` directory:

```
~/www/fynla.org/public_html/public/build/
```

### 2. PHP Files to Upload (additional to reviewDeploy.md)

**Models (6 files — new or changed):**

```
app/Models/AdvisorClient.php
app/Models/CashAccount.php
app/Models/Goal.php
app/Models/Investment/InvestmentAccount.php
app/Models/Investment/InvestmentScenario.php
app/Models/Mortgage.php
app/Models/SavingsAccount.php
```

**Controllers (3 files):**

```
app/Http/Controllers/Api/AgentInternalController.php
app/Http/Controllers/Api/PaymentController.php
app/Http/Controllers/Api/WebhookController.php
```

**Services (4 files — 2 renamed, 2 updated):**

```
app/Services/Investment/SimpleAssetAllocationOptimizer.php  (NEW — renamed from AssetAllocationOptimizer.php)
app/Services/Retirement/PensionContributionOptimizer.php    (NEW — renamed from ContributionOptimizer.php)
app/Services/Retirement/PensionPortfolioAnalyzer.php        (updated import)
app/Services/Retirement/RetirementActionDefinitionService.php (updated import)
```

**Agents (2 files — updated imports):**

```
app/Agents/InvestmentAgent.php
app/Agents/RetirementAgent.php
```

**DELETE these files from server (renamed):**

```
app/Services/Investment/AssetAllocationOptimizer.php    (replaced by SimpleAssetAllocationOptimizer.php)
app/Services/Retirement/ContributionOptimizer.php       (replaced by PensionContributionOptimizer.php)
```

**DELETE these files from server (unused):**

```
app/Http/Requests/Investment/CalculateEfficientFrontierRequest.php
app/Http/Requests/Investment/OptimizePortfolioRequest.php
database/migrations/2026_01_18_000001_create_goals_table.php
database/migrations/2026_01_18_000002_create_goal_contributions_table.php
```

### 3. Vue/JS Files (170+ files)

All compiled into `public/build/` — no individual uploads needed. The build includes:
- 154 files: `console.error` → `logger.error`
- 15 files: `Intl.NumberFormat` → `currencyMixin`
- Timer leak fixes (DBPensionForm, AccountForm)
- Version bump (Footer, Version page)
- All code review fixes from reviewDeploy.md

## Post-Deploy: SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Delete renamed/unused PHP files
rm -f app/Services/Investment/AssetAllocationOptimizer.php
rm -f app/Services/Retirement/ContributionOptimizer.php
rm -f app/Http/Requests/Investment/CalculateEfficientFrontierRequest.php
rm -f app/Http/Requests/Investment/OptimizePortfolioRequest.php

# Clear all caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

**Note:** No `php artisan migrate` needed for this deploy — the Payment SoftDeletes migration from reviewDeploy.md is the only pending migration.

## Verification Checklist

- [ ] Rebuild: `./deploy/fynla-org/build.sh`
- [ ] Upload `public/build/`
- [ ] Upload 7 model files
- [ ] Upload 3 controller files
- [ ] Upload 4 service files + 2 agent files
- [ ] Delete 4 old PHP files on server
- [ ] SSH: clear caches
- [ ] Verify footer shows v0.9.3.2
- [ ] Verify version page shows 30 March 2026
- [ ] Verify webhook endpoint responds (no 500)
- [ ] Verify payment flow works (no regression from response format change)
- [ ] Verify protection dashboard (David Mitchell — Life £500k, CI £200k, Disability £7,250)
- [ ] Verify no console.error in browser dev tools on dashboard
- [ ] Delete `public/mockup-starting-out.html` if present on server
