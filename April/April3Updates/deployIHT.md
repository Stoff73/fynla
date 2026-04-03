# Deploy Guide — Estate IHT Projection Fix

**Date:** 3 April 2026
**Branch:** bugs
**Generated from:** `git diff --name-only`

---

## What Changed

Estate IHT projections at life expectancy were showing unrealistic numbers (entrepreneur: £250M, chris: £36M). Root cause was `getMonteCarloAnnualRate()` reverse-engineering a growth rate from Monte Carlo projections that included contributions, then recompounding that inflated rate for 40+ years.

**Fix:**
- Use Monte Carlo p20 projected value directly at death age (no rate extraction)
- Replace integrated drawdown model with inflation-adjusted income/expenses year-by-year
- Life events injected at their specific ages
- Cash can now go negative for honest estate totals

**After fix:** entrepreneur £7.2M, chris £4.3M — realistic numbers.

## Files to Upload

### Modified PHP Files (1 file)

```
app/Services/Estate/IHTCalculationService.php
```

### No Frontend Build Required

Backend-only change. No Vue/JS files modified.

## Post-Upload SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Clear all caches (important — clears old cached IHT calculations)
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize

# Clear stored IHT calculations so they recalculate with new logic
php artisan tinker --execute="App\Models\Estate\IHTCalculation::truncate(); echo 'IHT cache cleared';"
```

## Verification

After deploy, check the estate planning page for any user. The "Age XX" column should show realistic projected values:
- Properties: current value compounded at ~3% per year
- Investments: Monte Carlo p20 value (conservative estimate)
- Cash: inflation-adjusted income minus expenses over projection period
- Total estate should be in the single-digit millions, not hundreds of millions
