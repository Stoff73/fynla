# Deploy: DC Pension Holdings & Fees

**Branch:** `main`
**Date:** 25 March 2026

## Summary

Added inline holdings editor and fee fields (platform fee + advisor fee) to the DC pension form, matching the investment account pattern. Also fixed InlineHoldingsEditor to auto-populate Amount Invested when entering allocation %.

## Changes

### Modified Files

| File | Change |
|------|--------|
| `resources/js/components/Retirement/DCPensionForm.vue` | Added InlineHoldingsEditor, platform fee (% or £ with frequency), advisor fee fields |
| `resources/js/components/Investment/InlineHoldingsEditor.vue` | Auto-populate Amount Invested (cost_basis) when allocation % is entered |
| `app/Http/Controllers/Api/RetirementController.php` | Holdings created in DB transaction alongside pension; eager-load holdings in index; added DB import |
| `app/Http/Requests/Retirement/StoreDCPensionRequest.php` | Added validation for holdings array and fee fields |
| `app/Models/DCPension.php` | Added fee fields to $fillable and $casts |

### New Files

| File | Purpose |
|------|---------|
| `database/migrations/2026_03_25_164053_add_fee_fields_to_dc_pensions_table.php` | Adds platform_fee_type, platform_fee_amount, platform_fee_frequency, advisor_fee_percent columns |

## Deploy Steps

### 1. Build frontend locally

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload to production via SiteGround File Manager

Upload the following to `~/www/fynla.org/public_html/`:

```
public/build/                              (entire directory — replace existing)
app/Http/Controllers/Api/RetirementController.php
app/Http/Requests/Retirement/StoreDCPensionRequest.php
app/Models/DCPension.php
database/migrations/2026_03_25_164053_add_fee_fields_to_dc_pensions_table.php
```

### 3. SSH to server — run migration and clear caches

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

### 4. Reseed (after migration)

```bash
php artisan db:seed
```

## Rollback

```bash
php artisan migrate:rollback --step=1
```

Then re-upload previous PHP files and `public/build/`.

## Testing Checklist

- [ ] Add DC pension (SIPP) with fund value — holdings editor appears
- [ ] Add holding with allocation % — Amount Invested auto-populates
- [ ] Cash remainder auto-created when allocation < 100%
- [ ] Platform fee: enter % value, switch to £ fixed, change frequency
- [ ] Advisor fee: enter percentage
- [ ] Submit pension — saves with holdings and fees in DB
- [ ] Edit pension — existing holdings load in editor, fees pre-filled
- [ ] Workplace pension type — fee fields still visible
