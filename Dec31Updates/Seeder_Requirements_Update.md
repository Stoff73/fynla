# Seeder Requirements Update

**Date:** 2025-12-31

## Summary

Updated seeder classification to make `AdminUserSeeder` and `PreviewUserSeeder` required seeders instead of optional development-only seeders.

## Rationale

The application requires:
- Admin users for system administration and demo purposes
- Preview personas for the preview/demo functionality that is core to the app

Without these seeders, the application cannot function properly as users cannot:
- Log in with demo credentials (demo@fps.com, admin@fps.com)
- Use the preview mode to explore the application

## Changes Made

### 1. DatabaseSeeder.php

**Before:**
- Phase 1 (Required): Tax config, life tables only
- Phase 2 (Dev only): Admin, Preview, Household, Test users

**After:**
- Phase 1 (Required): Tax config, life tables, Admin, Preview
- Phase 2 (Optional): Household, Test users only

### 2. seedMigration.md

- Updated "Phase 1: Reference Data" to "Phase 1: Required Seeders (MUST RUN)"
- Moved AdminUserSeeder and PreviewUserSeeder to Phase 1 table
- Updated all command examples to include all 6 required seeders
- Added "Scenario 6: Admin/Demo Login Not Working" troubleshooting section
- Added "Symptoms of Missing Seeders" quick reference table
- Updated Production Deployment section

### 3. CLAUDE.md

- Updated all seeder command blocks to include all 6 required seeders
- Added quick reference table for common issues
- Updated "After Running Pest Tests" section

## Required Seeders (6 total)

| Seeder | Purpose |
|--------|---------|
| `TaxConfigurationSeeder` | UK tax rates, allowances, thresholds (2025/26) |
| `TaxProductReferenceSeeder` | Tax treatment info for ISAs, GIAs, bonds |
| `UKLifeExpectancySeeder` | ONS life expectancy by age/gender |
| `ActuarialLifeTablesSeeder` | Detailed mortality tables for calculations |
| `AdminUserSeeder` | Admin account (admin@fps.com) and demo user |
| `PreviewUserSeeder` | Preview personas with full financial data |

## Optional Seeders (2 total)

| Seeder | Purpose |
|--------|---------|
| `HouseholdSeeder` | Creates household records for multi-user testing |
| `TestUsersSeeder` | Creates additional test user accounts |

## Commands

### Seed all required data:
```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=TaxProductReferenceSeeder --force
php artisan db:seed --class=UKLifeExpectancySeeder --force
php artisan db:seed --class=ActuarialLifeTablesSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
```

### Or run all at once (includes optional seeders in dev):
```bash
php artisan db:seed
```

## Files Modified

1. `database/seeders/DatabaseSeeder.php`
2. `seedMigration.md`
3. `CLAUDE.md`
