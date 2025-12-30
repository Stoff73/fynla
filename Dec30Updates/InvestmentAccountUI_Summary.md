# Investment Account UI Improvements

## Date: 30 December 2025

## Overview
UI improvements to the investment account detail view, including layout changes and adding account name support.

## Changes Made

### 1. Top Metrics Row Layout Change

**File:** `resources/js/components/NetWorth/InvestmentDetailInline.vue`

**Before:**
- 3-column grid: Current Value | Annualised Return | Holdings
- Separate full-width ISA Contributions card below (for ISA accounts)

**After:**
- 3-column grid: Current Value | Annualised Return | ISA Contributions (for ISA accounts)
- 3-column grid: Current Value | Annualised Return | Holdings (for non-ISA accounts)

The ISA contributions card now replaces the Holdings card for ISA accounts, providing more relevant information at a glance. Non-ISA accounts (GIA, VCT, bonds, etc.) still show the Holdings count.

### 2. Account Name Database Support

**New Migration:** `database/migrations/2025_12_30_160326_add_account_name_to_investment_accounts.php`
- Adds `account_name` column (nullable string) to `investment_accounts` table

**Model Update:** `app/Models/Investment/InvestmentAccount.php`
- Added `account_name` to `$fillable` array

**Seeder Update:** `database/seeders/PreviewUserSeeder.php`
- Added `account_name` field mapping when creating investment accounts from persona JSON

### 3. Persona Account Names

All personas now have descriptive account names:

| Persona | Account Names |
|---------|--------------|
| young_family | James's S&S ISA |
| peak_earners | David's S&S ISA, Joint GIA, VCT Holdings |
| widow | Stocks & Shares ISA, Offshore Bond, General Investment Account |
| entrepreneur | Stocks & Shares ISA, General Investment Account |

## Files Modified

1. `resources/js/components/NetWorth/InvestmentDetailInline.vue` - UI layout change
2. `app/Models/Investment/InvestmentAccount.php` - Added account_name to fillable
3. `database/seeders/PreviewUserSeeder.php` - Added account_name field mapping

## Files Created

1. `database/migrations/2025_12_30_160326_add_account_name_to_investment_accounts.php`

## Testing

After deployment, run:
```bash
php artisan migrate
php artisan db:seed --class=PreviewUserSeeder --force
```

Note: The seeder skips existing preview users. To force reseed:
```bash
php artisan tinker --execute="App\Models\User::where('is_preview_user', true)->delete()"
php artisan db:seed --class=PreviewUserSeeder --force
```
