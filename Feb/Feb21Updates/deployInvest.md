# Investment Module Fixes - Deployment Notes

**Date:** 21 February 2026
**Branch:** `invest`
**Status:** DEPLOYED 21 February 2026

---

## Changes Summary

| # | Fix | What Changed |
|---|-----|-------------|
| A3 | ISA ownership validation gap | `updateAccount()` now rejects attempts to change an ISA to joint/trust ownership, matching the existing check in `storeAccount()`. |
| A4 | Cache invalidation gaps | Added `clearCache()` calls to goal CRUD (store/update/destroy), joint owner cache clearing to holding CRUD and toggle-retirement, ensuring stale data is never served. |
| S5 | Proper UK dividend tax | New `DividendTaxCalculator` service with band-splitting (dividends sit on top of non-dividend income, PA taper above 100k). `TaxEfficiencyCalculator` delegates to it. Hardcoded 0.20 CGT rate replaced with `TaxConfigService` lookup. |
| S1 | Time-filtered portfolio returns | `PortfolioAnalyzer::calculateReturns()` now calculates real YTD and 1-year returns by filtering holdings on `purchase_date` instead of returning total return for all periods. |
| S4 | Platform-specific transaction costs | New `config/investment_platforms.php` with dealing costs for 6 UK platforms. `FeeAnalyzer::estimateTransactionCosts()` uses platform-specific fixed costs when available, falls back to percentage-based estimate. |
| S2 | Fund/ETF look-through allocation | New `calculateAssetAllocationWithLookThrough()` on `PortfolioAnalyzer` decomposes funds/ETFs into underlying asset classes using name-based heuristics. |
| S3 | Multi-asset Monte Carlo with correlation | Cholesky decomposition added to `MatrixOperations`. New `runMultiAssetSimulation()` on `MonteCarloSimulator` generates correlated returns across asset classes. Default equity/bond/cash correlation matrix provided. |

---

## Files to Upload

### New PHP Files

```
app/Services/Investment/DividendTaxCalculator.php
config/investment_platforms.php
```

### Modified PHP Files

```
app/Http/Controllers/Api/InvestmentController.php
app/Services/Investment/FeeAnalyzer.php
app/Services/Investment/MonteCarloSimulator.php
app/Services/Investment/PortfolioAnalyzer.php
app/Services/Investment/TaxEfficiencyCalculator.php
app/Services/Investment/Utilities/MatrixOperations.php
```

### Frontend Files

```
(none)
```

### Rebuild Required: NO

No Vue components were changed. Backend-only changes.

---

## Database Migrations Required: NO

No migrations. All changes are to PHP services, controllers, and config.

---

## Post-Upload Commands

```bash
php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan optimize
```

Config clear is important because `config/investment_platforms.php` is a new config file that needs to be picked up.

---

## Test Results

All 138 investment unit tests passing (346 assertions).

---

## Notes

- `DividendTaxCalculator` is injected into `TaxEfficiencyCalculator` via Laravel's container auto-resolution -- no service provider binding needed.
- The `config/investment_platforms.php` platform keys use snake_case (`hargreaves_lansdown`, `aj_bell`, etc.). The `FeeAnalyzer` normalises the `platform_name` from the account record automatically.
- `runMultiAssetSimulation()` and `calculateAssetAllocationWithLookThrough()` are new public methods -- no existing callers are affected. They are available for future frontend integration.
