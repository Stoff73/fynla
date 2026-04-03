# Deploy Guide — Retirement Income Consistency + Estate IHT Projection Fix

**Date:** 3 April 2026
**Branch:** bugs
**Generated from:** `git diff --name-only main..bugs`

---

## What Changed

### Estate IHT Projections (backend)
- Fixed unrealistic future value numbers (entrepreneur showing £250M, chris £36M)
- Root cause: Monte Carlo rate extraction included contributions, inflating growth rate
- Now uses Monte Carlo p20 value directly at death age — no recalculation
- Cash projected with inflation-adjusted income/expenses + life events
- Cash can go negative for honest estate totals

### Retirement Income Consistency (frontend + backend)
- All three retirement views now show consistent gross income figures
- Single source of truth: `RequiredCapitalCalculator` for target income (was 3 different calculations)
- Removed duplicate `getTargetRetirementIncome()` from `RetirementProjectionService`
- Dashboard uses backend projection data instead of hardcoded 4% SWR and £11,502 state pension

### Retirement UI Improvements (frontend)
- Income and Capital planner tabs: header card pattern matching pension detail view
- Summary metrics moved inside header card
- Removed blue "Agentic AI scaffolding" development banner
- Income sources and other assets moved below fund depletion chart (click to reveal)
- Removed redundant "View income breakdown" link and Annual Pension Allowance from cards
- Fund depletion chart changed from stacked area to stacked bar
- Consistent labels: "Required Capital" / "Projected Capital" (no colons, no vague text)

## Files to Upload

### Modified PHP Files (2 files)

```
app/Services/Estate/IHTCalculationService.php
app/Services/Retirement/RetirementProjectionService.php
```

### Modified Frontend Files (5 files) — build required

```
resources/js/components/NetWorth/PensionList.vue
resources/js/components/Retirement/CapitalAdequacyTab.vue
resources/js/components/Retirement/FundDepletionChart.vue
resources/js/components/Retirement/RetirementIncomeTab.vue
resources/js/views/Dashboard.vue
```

### Frontend Build Required

```bash
./deploy/fynla-org/build.sh
```

Upload `public/build/` directory.

## Post-Upload SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Clear all caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize

# Clear stored IHT calculations so they recalculate with new logic
php artisan tinker --execute="App\Models\Estate\IHTCalculation::truncate(); echo 'IHT cache cleared';"
```

## Verification

1. **Estate Planning**: Check any user's estate page. The "Age XX" column should show realistic values (single-digit millions, not hundreds of millions)
2. **Dashboard retirement card**: Income and capital figures should match the retirement page
3. **Retirement "Will I have enough"**: Click into income tab — should show "Income Planner" header card with 4 metrics inside, bar chart below
4. **Retirement "Am I saving enough"**: Click into capital tab — should show "Capital Planner" header card with 4 metrics inside
