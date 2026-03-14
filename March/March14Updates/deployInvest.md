# Deploy: Investment Deduplication & Alignment

**Date:** 2026-03-14
**Stage 1 Commit:** `76e8d94`
**Stage 2 Commit:** _(see below)_

---

## Stage 1 — Files to Upload

### New Files (create these)

```
app/Constants/InvestmentDefaults.php
app/Traits/CalculatesOCF.php
database/migrations/2026_03_14_000001_add_sub_type_to_holdings_table.php
```

### Modified PHP Files (replace these)

```
app/Http/Controllers/Api/Investment/RebalancingCalculationController.php
app/Http/Requests/Investment/StoreHoldingRequest.php
app/Http/Requests/Investment/UpdateHoldingRequest.php
app/Models/Investment/Holding.php
app/Services/Investment/AssetAllocationOptimizer.php
app/Services/Investment/DiversificationAnalyzer.php
app/Services/Investment/FeeAnalyzer.php
app/Services/Investment/Fees/OCFImpactCalculator.php
app/Services/Investment/ModelPortfolio/ModelPortfolioBuilder.php
app/Services/Investment/PortfolioAnalyzer.php
app/Services/Investment/PortfolioStrategyService.php
app/Services/Investment/Rebalancing/DriftAnalyzer.php
```

### Frontend (rebuild required)

```
resources/js/components/Investment/HoldingForm.vue
```

---

## Stage 2 — Files to Upload

### Modified PHP Files (replace these)

```
app/Agents/InvestmentAgent.php
app/Services/Investment/AssetAllocationOptimizer.php
app/Services/Investment/ContributionOptimizer.php
app/Services/Investment/DiversificationAnalyzer.php
app/Services/Investment/PortfolioAnalyzer.php
app/Services/Investment/TaxEfficiencyCalculator.php
app/Services/Investment/Tax/TaxOptimizationAnalyzer.php
app/Services/Retirement/PensionPortfolioAnalyzer.php
```

### Deleted Files (remove from server)

```
resources/js/components/Investment/Recommendations.vue
resources/js/components/Investment/WhatIfScenarios.vue
```

### Modified Vue Files (frontend rebuild required)

```
resources/js/components/Investment/InvestmentRecommendationsTracker.vue
resources/js/components/Investment/TaxOptimization.vue
resources/js/components/Investment/TaxOptimizationOverview.vue
resources/js/components/Investment/TaxOptimizationRecommendations.vue
resources/js/components/NetWorth/StrategyDetail.vue
```

---

## Deploy Steps

### 1. Build frontend locally

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload via SiteGround File Manager

Upload all files listed above (both stages) to `~/www/fynla.org/public_html/` maintaining directory structure.

Upload `public/build/` directory to `~/www/fynla.org/public_html/public/build/`.

Delete the two removed shell wrapper files from the server.

### 3. SSH and run migration + cache clear

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate --force
php artisan db:seed
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## What Changed (User-Facing)

### Stage 1

1. **Fund Type dropdown** — When adding a holding with asset type "Fund", a secondary "Fund Type" dropdown now appears (Equity Fund, Bond Fund, Mixed Fund, Income Fund, Index Fund, Money Market Fund, Property Fund)
2. **Portfolio analysis** — Fund holdings without a specified fund type are now treated as mixed (60% equities / 30% bonds / 10% cash) instead of 100% equities
3. **Fee flagging** — High OCF threshold tightened from 0.80% to 0.75%, so slightly more holdings may be flagged as high-fee
4. **Target allocations corrected** — Minor adjustments to risk level allocation targets for consistency across all analysis views

### Stage 2

5. **ISA allowance accuracy** — ISA remaining now includes cash ISA subscriptions from savings module, giving accurate remaining allowance for users with both investment and savings ISAs
6. **Strategy page loads directly** — Investment strategies page no longer requires an intermediate "Load Strategies" click

## Rollback

If issues arise, revert the PHP files from the previous commit and run:

```bash
php artisan migrate:rollback --step=1
php artisan cache:clear && php artisan config:clear && php artisan optimize
```
