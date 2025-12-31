# December 31, 2025 Updates

## Summary

This folder documents changes made on December 31, 2025.

## Changes

### 1. Information Guide Feature
**File:** `InfoGuide_Feature.md`

Added a floating help button that shows users what data is needed for each module, with context-aware requirements and plain-language explanations.

### 2. Seeder Requirements Update
**File:** `Seeder_Requirements_Update.md`

Updated seeder classification to make `AdminUserSeeder` and `PreviewUserSeeder` required seeders (Phase 1) instead of optional development-only seeders.

### 3. Documentation Cleanup

Removed outdated documentation from Dec14-Dec30 folders:
- 50+ markdown files consolidated/archived
- Reference PDFs moved elsewhere
- Word documents removed

## Required Seeders (Updated)

After these changes, the following 6 seeders are required for the app to function:

```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=TaxProductReferenceSeeder --force
php artisan db:seed --class=UKLifeExpectancySeeder --force
php artisan db:seed --class=ActuarialLifeTablesSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
```

## Commits

| Hash | Description |
|------|-------------|
| `f81489d` | docs: Update seeder requirements and clean up old documentation |
| `786e2d0` | feat: Add Information Guide feature for data requirements |
