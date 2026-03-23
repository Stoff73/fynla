# UK Taxes Page Removal — Deploy Notes

**Date:** 22 March 2026
**Commit:** remove: delete UK Taxes page with hardcoded tax values

## What Was Removed

The `/uk-taxes` page displayed UK tax rates, bands, and allowances using hardcoded values inline in the Vue component. Several values were wrong (NI rates, CGT rates, employer thresholds) and it duplicated what `TaxConfigService` provides from the database.

## Files Deleted

```
resources/js/views/UKTaxes/UKTaxesDashboard.vue
resources/js/components/UKTaxes/CalculationsTab.vue
resources/js/components/Tax/TaxStrategiesCard.vue
resources/js/components/Dashboard/UKTaxesAllowancesCard.vue
resources/js/components/Dashboard/UKTaxesOverviewCard.vue
app/Http/Controllers/Api/UKTaxesController.php
```

## Files Modified

```
resources/js/router/index.js (removed route + lazy import)
resources/js/components/SideMenu.vue (removed sidebar item + active section detection)
resources/js/components/Navbar.vue (removed breadcrumb entry)
resources/js/services/analyticsService.js (removed route mapping)
resources/js/components/Dashboard/CrossModuleInsights.vue (tax_optimisation link → /dashboard)
resources/js/views/Dashboard.vue (removed stale comment)
routes/api.php (removed /api/uk-taxes route + use import)
tests/Feature/AdminRBACTest.php (updated admin route tests to use /api/admin/users)
```

## Deploy Steps

1. Rebuild locally: `./deploy/fynla-org/build.sh`
2. Upload `public/build/` to server
3. Delete from server:
   - `app/Http/Controllers/Api/UKTaxesController.php`
4. Upload modified PHP files:
   - `routes/api.php`
5. Clear caches:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize
```

No migrations. No seeding required.

## Tax Config — Single Source of Truth

All UK tax values are managed via:

- **Database:** `tax_configurations` table (seeded by `TaxConfigurationSeeder`)
- **Backend:** `TaxConfigService` (request-scoped singleton)
- **Fyn assistant:** `get_tax_information` tool (queries `TaxConfigService`)
- **Reseed command:** `php artisan db:seed --class=TaxConfigurationSeeder --force`
