# Retirement Module Deployment Notes

## Branch: retirement
## Date: 2026-02-21

## Summary
7 fixes from retirement module audit (17.1-17.7): removed hardcoded tax values, proper DB pension revaluation, MPAA tracking, 3-year carry forward, standardised default retirement age to 67, state pension from TaxConfigService, removed deprecated risk_tolerance column.

## Files Changed

### Services (6 files)
- `app/Services/Retirement/ContributionOptimizer.php` - Tax relief uses TaxConfigService instead of hardcoded bands
- `app/Services/Retirement/PensionProjector.php` - DB pension revaluation by inflation type, state pension from TaxConfigService, TaxConfigService injected
- `app/Services/Retirement/AnnualAllowanceChecker.php` - Real 3-year carry forward from RetirementProfile, MPAA queries DCPension.has_flexibly_accessed
- `app/Services/Retirement/RetirementProjectionService.php` - DEFAULT_RETIREMENT_AGE 68 -> 67
- `app/Services/Retirement/RetirementIncomeService.php` - DEFAULT_RETIREMENT_AGE 68 -> 67
- `app/Services/Retirement/RequiredCapitalCalculator.php` - DEFAULT_RETIREMENT_AGE 68 -> 67

### Models (2 files)
- `app/Models/DCPension.php` - Added has_flexibly_accessed, flexible_access_date to fillable/casts
- `app/Models/RetirementProfile.php` - Replaced risk_tolerance with prior_year_unused_allowance (array cast)

### Validation (1 file)
- `app/Http/Requests/Retirement/StoreDCPensionRequest.php` - Added has_flexibly_accessed, flexible_access_date rules

### Migrations (3 files) - MUST RUN
- `database/migrations/2026_02_21_130000_add_mpaa_fields_to_dc_pensions.php`
- `database/migrations/2026_02_21_130001_add_carry_forward_fields_to_retirement_profiles.php`
- `database/migrations/2026_02_21_130002_remove_risk_tolerance_from_retirement_profiles.php`

### Tests & Factory (3 files - DO NOT DEPLOY)
- `tests/Unit/Services/Retirement/PensionProjectorTest.php`
- `tests/Unit/Services/Retirement/AnnualAllowanceCheckerTest.php`
- `tests/Feature/RetirementIntegrationTest.php`
- `database/factories/RetirementProfileFactory.php`

## Deployment Steps
1. Upload all files listed above (except tests & factory)
2. SSH and run migrations: `php artisan migrate`
3. Reseed: `php artisan db:seed`
4. Clear caches: `php artisan cache:clear && php artisan route:clear && php artisan config:clear`

## Frontend Rebuild
Not required - no Vue/JS changes.
