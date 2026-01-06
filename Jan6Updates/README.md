# Jan 6, 2026 Updates

## Current Application State

**Version**: v0.4.5
**Status**: Production-ready
**Last Deploy**: Jan 6, 2026

## Changes Made

### 1. Email Branding Fix

Updated spouse account emails from "FPS" to "Fynla" branding.

**Files Changed:**
- `app/Mail/SpouseAccountCreated.php` - Subject line
- `app/Mail/SpouseAccountLinked.php` - Subject line
- `resources/views/emails/spouse-account-created.blade.php` - Title, header, body, footer
- `resources/views/emails/spouse-account-linked.blade.php` - Title, body, footer

### 2. Rate Limit Fix

Increased API rate limit from 60 to 300 requests/minute in production.

**Problem**: Dashboard makes ~15 API calls per page load. With multiple users or page refreshes, the 60/minute limit was easily exceeded, causing 429 errors.

**Solution**: Increased limit to 300/minute in `app/Providers/RouteServiceProvider.php`.

**File Changed:**
- `app/Providers/RouteServiceProvider.php`

### 3. Build Issue Resolution

Fixed MIME type errors on production caused by incorrect `VITE_BASE_PATH`.

**Problem**: Assets at `/assets/` returning 503/HTML instead of JS/CSS because build was done without `VITE_BASE_PATH=/build/` set.

**Solution**: Always use the deployment build scripts which properly set environment variables:
```bash
./deploy/fynla-org/build.sh        # For fynla.org
./deploy/csjones-fynla/build.sh    # For csjones.co/fynla
```

## Production Deployment Checklist

After uploading files to server:

```bash
cd ~/www/fynla.org/public_html

# Clear all caches (REQUIRED after RouteServiceProvider change)
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

## Testing Verification

1. **Email Branding**: Create a spouse account via Family Members - email should say "Fynla" not "FPS"
2. **Rate Limiting**: Refresh dashboard multiple times rapidly - should not get 429 errors
3. **Assets Loading**: Check browser Network tab - all JS/CSS should load with 200 status

## Files for Deployment

See `Jan5Updates/DEPLOYMENT_FILES.md` for the complete list of files to upload (30 PHP/Blade files + frontend build).
