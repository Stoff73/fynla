# Deployment Guide — 21 March 2026

**STATUS: DEPLOYED — 21 March 2026**

## Rebuild Required?

**Yes** — `IncomeOccupation.vue` and `IncomeStatementTab.vue` changed. Run:

```bash
./deploy/fynla-org/build.sh
```

Then upload `public/build/` directory.

## Database Migrations

None.

## Database Seeders

None required — no new seed data.

## PHP Files to Upload (2)

```
app/Services/UserProfile/PersonalAccountsService.php
app/Services/UserProfile/UserProfileService.php
```

**Note:** `PersonalAccountsService.php` now has a constructor dependency on `UKTaxCalculator`. Laravel auto-resolves this via the service container — no registration needed. `composer dump-autoload` should have been run yesterday for the NetWorthCacheObserver.

## Frontend (via build)

These are compiled into `public/build/` — upload the build directory:

```
resources/js/components/UserProfile/IncomeOccupation.vue
resources/js/components/UserProfile/IncomeStatementTab.vue
```

## Upload Order

1. Upload 2 PHP files to matching paths on server
2. Run `./deploy/fynla-org/build.sh` locally
3. Upload `public/build/` directory
4. SSH and clear caches:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## What Changed

### IncomeOccupation.vue — Other Income was completely missing + zero-value cleanup

The Income page (`/valuable-info?section=income`) had no support for Other Income at all — no display line, no edit field, not in the form data, not in the total, not sent on save. Now fully wired up: view line (shown when > 0), edit field with helper text, included in total and submit.

All income line items in view mode now hide when their value is zero. Previously Employment, Self-Employment, Dividend, Interest, and Trust always showed even at £0, cluttering the page. Now only income types the user actually has appear. All fields remain visible in edit mode.

### UserProfileService.php — Other Income missing from API + tax

The backend API that feeds the Income page was also missing `annual_other_income`:
- Not returned in the response (frontend couldn't display it even if it tried)
- Not included in `$totalAnnualIncome` (total was wrong)
- Not passed to `UKTaxCalculator` (tax calculation was wrong)

Now included in all three.

### PersonalAccountsService.php — 3 missing income types + backend tax

Added Interest Income, Pension Income (from DB pensions + state pension), and Trust Income to P&L and cashflow statements. Injected `UKTaxCalculator` to return proper tax data instead of relying on frontend hardcoded values. This fixes the orphaned `IncomeStatementTab.vue` for when it's wired into a view.

### IncomeStatementTab.vue — Hardcoded tax removed

Replaced 42-line JavaScript tax calculator (hardcoded PA £12,570, rates 20%/40%/45%) with 2-line computed that reads backend tax data from `TaxConfigService`.

## Post-Deploy Verification

1. Log in as peak_earners (David Mitchell) → Income page → verify Employment £145,000 and Rental £14,290 shown, zero-value types (Self-Employment, Dividend, Interest, Trust, Other) hidden
2. Click Edit → verify all 8 income fields visible including Other Income
3. Verify tax breakdown shows correct 2025/26 bands (PA, Basic, Higher, Additional) with Section 24 credit
4. Log in as retired_couple (Patricia Bennett) → Income page → verify Pension Income £30,000 shown
5. Verify "No NI" badge on Patricia's tax card (pension income doesn't attract NI)
6. Verify zero-amount income types are hidden where appropriate (Rental, Pension, Other only show when > 0)

## Also Pending from Yesterday

The following files from the 20 March session were uploaded today (confirmed by user):

- [x] `app/Observers/NetWorthCacheObserver.php`
- [x] `app/Providers/EventServiceProvider.php`
- [x] `app/Http/Controllers/Api/MortgageController.php`
- [x] `app/Http/Controllers/Api/PropertyController.php`
- [x] `composer dump-autoload` on server
