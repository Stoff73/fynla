# Jan 6, 2026 Updates

## Current Application State

**Version**: v0.4.5
**Status**: Production-ready
**Test Suite**: 1075 passed, 0 failed

## Changes Made

### 1. Code Quality Audit & Refactoring

Ran comprehensive code quality audit (score: 82/100) and fixed all medium/low priority issues.

**Vuex Protection Store Refactor** (`resources/js/store/modules/protection.js`):
- Reduced from 712 to 432 lines (40% reduction)
- Created action factory functions for policy CRUD operations
- Extracted `convertPremiumToMonthly` and `calculateMonthlyPremium` helpers
- Eliminates duplicate action code across 5 policy types

**Dead Code Removal**:
- Removed `handleDeletePolicy` from `CurrentSituation.vue` and `ProtectionDashboard.vue`
- PolicyCard doesn't emit 'delete' event, so handler chain was unused
- Removed 7 debug `console.log` statements from `Register.vue`

**Structured Logging**:
- Added `Log` facade import to `ProtectionAgent.php`
- Added `Log` facade import to `RetirementAgent.php`
- Added `Log` facade import to `IHTCalculationService.php`

**Form Request Consolidation**:
- Created `BasePolicyRequest` abstract class with common validation rules
- Provides `commonRules()`, `commonMessages()`, and merge helper methods
- Ready for policy request classes to extend

### 2. Registration Test Fixes

Updated registration tests to match the current `PendingRegistration` flow.

**Problem**: Tests expected old registration flow that created users directly. The registration was updated to use `PendingRegistration` with email verification.

**Fix**:
- Changed `user_id` → `pending_id` in response assertions
- Changed `users` table → `pending_registrations` table assertions
- Updated verification code check to query `PendingRegistration` model

### 3. Email Branding Fix

Updated spouse account emails from "FPS" to "Fynla" branding.

**Files Changed:**
- `app/Mail/SpouseAccountCreated.php` - Subject line
- `app/Mail/SpouseAccountLinked.php` - Subject line
- `resources/views/emails/spouse-account-created.blade.php` - Title, header, body, footer
- `resources/views/emails/spouse-account-linked.blade.php` - Title, body, footer

### 4. Rate Limit Fix

Increased API rate limit from 60 to 300 requests/minute in production.

**Problem**: Dashboard makes ~15 API calls per page load. With multiple users or page refreshes, the 60/minute limit was easily exceeded.

**File Changed:**
- `app/Providers/RouteServiceProvider.php`

### 5. Build Issue Resolution

Fixed MIME type errors on production caused by incorrect `VITE_BASE_PATH`.

**Solution**: Always use the deployment build scripts:
```bash
./deploy/fynla-org/build.sh        # For fynla.org
./deploy/csjones-fynla/build.sh    # For csjones.co/fynla
```

## Files Changed Summary

| File | Change Type |
|------|-------------|
| `resources/js/store/modules/protection.js` | Major refactor (-280 lines) |
| `resources/js/views/Register.vue` | Remove console.log |
| `resources/js/components/Protection/CurrentSituation.vue` | Remove dead code |
| `resources/js/views/Protection/ProtectionDashboard.vue` | Remove dead code |
| `app/Agents/ProtectionAgent.php` | Add Log import |
| `app/Agents/RetirementAgent.php` | Add Log import |
| `app/Services/Estate/IHTCalculationService.php` | Add Log import |
| `app/Http/Requests/Protection/BasePolicyRequest.php` | New file |
| `tests/Feature/Auth/RegistrationTest.php` | Fix for pending registration flow |
| `app/Mail/SpouseAccountCreated.php` | Branding fix |
| `app/Mail/SpouseAccountLinked.php` | Branding fix |
| `resources/views/emails/spouse-account-*.blade.php` | Branding fix |
| `app/Providers/RouteServiceProvider.php` | Rate limit increase |

## Git Commits (Jan 6)

```
9cb4811 fix: Update registration tests to match pending registration flow
5e2c02e refactor: Code quality improvements from audit
1d5621d docs: Add Jan6Updates and document build/rate limit issues
92214e6 feat: Code quality fixes, mobile responsive UI, and production fixes
```

## Production Deployment Checklist

After uploading files to server:

```bash
cd ~/www/fynla.org/public_html

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

## Testing Verification

All tests passing:
- **Total**: 1075 tests, 5139 assertions
- **Duration**: ~106 seconds
- **Failed**: 0
