# Deploy Guide — Tech Debt Audit Fixes

**DEPLOYED** 9 April 2026

**Date:** 9 April 2026
**Commit:** `4f959be` (main)
**Commit range:** `f27a46f..4f959be`

---

## Summary

45 tech debt issues fixed: PSACalculator bug, $toast registration, 21 hardcoded tax values replaced, dead code removed (-1,204 lines), Mockery cleanup, TaxYearController extracted, component names fixed, factories created.

---

## Files to Upload

### Frontend Build (required)

```
public/build/ --> ~/www/fynla.org/public_html/public/build/
```

### PHP Files (17 files — upload all)

**New file:**
```
app/Http/Controllers/Api/TaxYearController.php
```

**Modified services (10):**
```
app/Services/Estate/PersonalizedTrustStrategyService.php
app/Services/Estate/TrustService.php
app/Services/Investment/AssetLocation/AssetLocationOptimizer.php
app/Services/Investment/Recommendation/ContributionWaterfallService.php
app/Services/Investment/Tax/CGTHarvestingCalculator.php
app/Services/Retirement/DecumulationPlanner.php
app/Services/Retirement/RetirementActionDefinitionService.php
app/Services/Savings/PSACalculator.php
app/Services/Savings/SavingsActionDefinitionService.php
app/Services/UKTaxCalculator.php
```

**Modified models (5):**
```
app/Models/Goal.php
app/Models/Investment/Holding.php
app/Models/Investment/InvestmentScenario.php
app/Models/Invoice.php
app/Models/LoginAttempt.php
```

**Modified routes:**
```
routes/api.php
```

### Delete on Server

```
app/Services/PythonAgentBridge.php
```

### Optional (factories + tests — not needed on production)

```
database/factories/DiscountCodeUsageFactory.php
database/factories/ReferralFactory.php
tests/  (not deployed)
```

---

## Full File List

| File | Change | Upload? |
|------|--------|---------|
| `public/build/*` | Rebuilt | Yes (full directory) |
| `app/Http/Controllers/Api/TaxYearController.php` | New | Yes |
| `app/Services/Estate/PersonalizedTrustStrategyService.php` | Modified | Yes |
| `app/Services/Estate/TrustService.php` | Modified | Yes |
| `app/Services/Investment/AssetLocation/AssetLocationOptimizer.php` | Modified | Yes |
| `app/Services/Investment/Recommendation/ContributionWaterfallService.php` | Modified | Yes |
| `app/Services/Investment/Tax/CGTHarvestingCalculator.php` | Modified | Yes |
| `app/Services/Retirement/DecumulationPlanner.php` | Modified | Yes |
| `app/Services/Retirement/RetirementActionDefinitionService.php` | Modified | Yes |
| `app/Services/Savings/PSACalculator.php` | Modified | Yes |
| `app/Services/Savings/SavingsActionDefinitionService.php` | Modified | Yes |
| `app/Services/UKTaxCalculator.php` | Modified | Yes |
| `app/Models/Goal.php` | Modified | Yes |
| `app/Models/Investment/Holding.php` | Modified | Yes |
| `app/Models/Investment/InvestmentScenario.php` | Modified | Yes |
| `app/Models/Invoice.php` | Modified | Yes |
| `app/Models/LoginAttempt.php` | Modified | Yes |
| `routes/api.php` | Modified | Yes |
| `app/Services/PythonAgentBridge.php` | Deleted | Delete on server |
| `database/factories/DiscountCodeUsageFactory.php` | New | Optional |
| `database/factories/ReferralFactory.php` | New | Optional |
| Frontend Vue/JS files (40) | Modified/Deleted | No (compiled into build) |
| Test files (7) | Modified | No (not deployed) |

---

## SSH Commands (post-upload)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Delete removed file
rm -f app/Services/PythonAgentBridge.php

# Clear all caches (route changed)
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize

# Reseed tax config (PSACalculator now handles non_taxpayer band)
php artisan db:seed --class=TaxConfigurationSeeder --force
```

---

## No Migrations

No database changes in this commit.

---

## Notes

- **PSACalculator** now returns `'non_taxpayer'` for below-PA earners. The `assessPSAPosition()` method handles this with unlimited PSA. Verify savings analysis pages show correct PSA for low-income preview personas.
- **$toast global** now registered — Settings pages (Security, Privacy, Assumptions) and MFA setup will show success/error notifications for the first time.
- **TaxYearController** replaces inline route closure — route caching now works for `tax-year/current`.
- **No composer changes** — no `composer install` needed.
- **No frontend behaviour changes** — hardcoded values replaced with identical config values. Tax calculations produce same results.
