# DashboardApiTest 500 Error Fix

**Date:** 19 March 2026
**Branch:** `main`
**Commit:** `fcba5d7`

## Summary

Fixed 10 failing dashboard feature tests that were returning 500 errors. Root cause was `AdvisorImpersonationMiddleware` accessing `TransientToken::$id` which doesn't exist when using `actingAs()` in tests.

## Root Cause

`AdvisorImpersonationMiddleware` (line 27) accessed `$user->currentAccessToken()?->id`. When tests use `$this->actingAs($user)`, Sanctum provides a `TransientToken` instead of a `PersonalAccessToken`. `TransientToken` has no `id` property, causing a PHP deprecation that was converted to an error in PHP 8.5.

## Fix

Changed the middleware to explicitly check the token type:

```php
// Before
$tokenId = $user->currentAccessToken()?->id;

// After
$token = $user->currentAccessToken();
if (! $token || ! ($token instanceof \Laravel\Sanctum\PersonalAccessToken)) {
    return $next($request);
}
$tokenId = $token->id;
```

Also added `TaxConfigurationSeeder` to `DashboardApiTest::beforeEach()` as a secondary fix.

## Files Changed

- `app/Http/Middleware/AdvisorImpersonationMiddleware.php`
- `tests/Feature/Dashboard/DashboardApiTest.php`

## Test Results

- Before: 10 failed, 4 passed
- After: 14 passed, 0 failed (+ 13 integration tests pass)
