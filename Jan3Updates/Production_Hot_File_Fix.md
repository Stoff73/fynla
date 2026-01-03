# Jan 3, 2026 - Production Site Fix: public/hot File

## Summary

Fixed production site at fynla.org showing blank page due to `public/hot` file being accidentally deployed to server.

---

## Root Cause

The `public/hot` file was deployed to production, causing Laravel to think the Vite development server was running.

### How It Happened

1. Running `npm run dev` locally creates `public/hot` containing `http://127.0.0.1:5173`
2. The build script (`deploy/fynla-org/build.sh`) did NOT exclude this file
3. During deployment, `public/hot` was included in the deployment package
4. On the production server, Laravel's Vite integration found `public/hot`
5. Laravel generated development URLs (`http://127.0.0.1:5173/...`) instead of production URLs (`/build/assets/...`)
6. The browser couldn't reach `127.0.0.1:5173` on the server, so all assets failed to load

### Technical Details

Laravel's Vite integration checks for the hot file:

```php
// vendor/laravel/framework/src/Illuminate/Foundation/Vite.php
public function isRunningHot()
{
    return is_file($this->hotFile());  // Checks for public/hot
}
```

If `public/hot` exists, Vite reads its contents and prefixes all asset URLs with that value.

---

## Symptoms

- Site loads HTML but shows blank white page
- Browser DevTools Network tab shows requests to:
  - `http://127.0.0.1:5173/@vite/client` (503 Service Unavailable)
  - `http://127.0.0.1:5173/resources/css/app.css` (503)
  - `http://127.0.0.1:5173/resources/js/app.js` (503)

---

## Fix Applied

### 1. Server Fix (Immediate)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
rm public/hot
php artisan config:clear
php artisan view:clear
php artisan cache:clear
```

### 2. Build Script Fix (Prevention)

Updated both build scripts to exclude `public/hot`:

**deploy/fynla-org/build.sh:**
```bash
rsync -a --progress "$PROJECT_ROOT/" "$DEPLOY_DIR/" \
  ...
  --exclude 'public/hot'  # Added
```

**deploy/csjones-fynla/build.sh:**
```bash
rsync -a --progress "$PROJECT_ROOT/" "$DEPLOY_DIR/" \
  ...
  --exclude 'public/hot'  # Added
```

### 3. Local Cleanup

Deleted local `public/hot` file to prevent future accidental deployments.

---

## Files Changed

| File | Change |
|------|--------|
| Server: `public/hot` | DELETED |
| `deploy/fynla-org/build.sh` | Added `--exclude 'public/hot'` |
| `deploy/csjones-fynla/build.sh` | Added `--exclude 'public/hot'` |
| Local: `public/hot` | DELETED |

---

## Prevention

1. **Build scripts now exclude `public/hot`** - Even if it exists locally, it won't be deployed
2. **`.gitignore` already includes `public/hot`** - Verified at line 4
3. **Pre-deployment check** - Before deploying, verify `public/hot` is NOT in the deployment package

### Pre-Deployment Checklist

Before deploying, verify the deployment package does not contain `public/hot`:

```bash
# After running build script, check the contents
unzip -l ../fynla-org-deploy.zip | grep "public/hot"
# Should return nothing
```

---

## Lessons Learned

1. The `public/hot` file is created by `npm run dev` and should NEVER be deployed
2. Build scripts must explicitly exclude development artifacts
3. Always verify deployment package contents before uploading to production
