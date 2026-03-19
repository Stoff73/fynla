# PreviewUserSeeder Unique Constraint Fix

**Date:** 19 March 2026
**Branch:** `main`
**Commit:** `628db8b`

## Summary

Fixed `php artisan db:seed` failing with `UniqueConstraintViolationException` on the `risk_profiles` table.

## Root Cause

The migration `2026_03_18_100002` added a unique constraint on `risk_profiles.user_id`. The `PreviewUserSeeder::createRiskProfiles()` method used `RiskProfile::create()`, which fails when a record with the same `user_id` already exists (e.g., from a previous seed run where the user was recreated with the same auto-incremented ID).

## Fix

Changed `RiskProfile::create()` to `RiskProfile::updateOrCreate()` for both the primary user and spouse risk profiles:

```php
// Before
RiskProfile::create(['user_id' => $user->id, ...]);

// After
RiskProfile::updateOrCreate(
    ['user_id' => $user->id],
    [...]
);
```

## Files Changed

- `database/seeders/PreviewUserSeeder.php`

## Testing

- `php artisan db:seed` runs successfully with all 18 seeders completing
