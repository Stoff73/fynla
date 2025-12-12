# EMERGENCY FIX - Fynla Blank Page Issue Resolution

**Date**: November 12, 2025
**Issue**: Blank page with 1 byte response
**Status**: 🔥 CRITICAL FIXES APPLIED - READY TO DEPLOY

---

## 🎯 ROOT CAUSES IDENTIFIED

After comprehensive analysis of your deployment, I found **THREE CRITICAL ISSUES**:

### 1. **HOT FILE EXISTS** (Most Critical)
- **File**: `public/hot`
- **Problem**: Tells Laravel to load development server at `http://127.0.0.1:5173` instead of production assets
- **Result**: Blank page because Vite dev server not running on production
- **Status**: ✅ **FIXED** - Removed from local build

### 2. **MISSING MANIFEST SYMLINK**
- **Expected**: `public/build/manifest.json`
- **Actual**: Only `public/build/.vite/manifest.json` existed
- **Problem**: Laravel can't find the manifest, can't load any assets
- **Result**: Blank page with no CSS or JavaScript
- **Status**: ✅ **FIXED** - Symlink created

### 3. **NO ERROR VISIBILITY**
- **Problem**: Production mode with `APP_DEBUG=false` hides all errors
- **Result**: You see blank page instead of actual error messages
- **Status**: ✅ **FIXED** - Created `index-debug.php` for troubleshooting

---

## 📦 WHAT'S IN THE FIXED PACKAGE

**File**: `tengo-v0.2.7-FIXED.tar.gz` (77MB)

This package contains:
- ✅ **NO** `hot` file (removed)
- ✅ Fresh production build with correct manifest symlink
- ✅ Debug index.php for troubleshooting
- ✅ Server diagnostic script
- ✅ Correct `.htaccess` files
- ✅ All vendor dependencies (optimized)

---

## 🚀 DEPLOYMENT INSTRUCTIONS

### OPTION A: Quick Fix (If You Already Deployed)

If the application is already on the server but showing blank page:

```bash
# 1. SSH to server
ssh -p 18765 u163-ptanegf9edny@ssh.csjones.co

# 2. Navigate to tengo directory
cd ~/www/csjones.co/public_html/tengo

# 3. Remove hot files (CRITICAL)
rm -f hot
rm -f public/hot
rm -f public/build/hot

# 4. Create manifest symlink (CRITICAL)
cd public/build
ln -sf .vite/manifest.json manifest.json
cd ../..

# 5. Verify symlink created
ls -la public/build/manifest.json
# Should show: manifest.json -> .vite/manifest.json

# 6. Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 7. Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Test immediately
curl -I https://csjones.co/tengo
```

**Expected Result**: You should now see a proper HTTP response with headers, not just 1 byte.

---

### OPTION B: Fresh Deployment (Recommended)

Deploy the new fixed package:

#### Step 1: Upload the Fixed Package

**Using SiteGround File Manager**:
1. Go to [Site Tools → File Manager](https://my.siteground.com/)
2. Navigate to: `~/www/csjones.co/public_html/`
3. Upload: `tengo-v0.2.7-FIXED.tar.gz`
4. Wait for upload to complete (77MB, may take a few minutes)

**OR using SCP from your Mac**:
```bash
scp -P 18765 ~/Desktop/tengo-v0.2.7-FIXED.tar.gz \
  u163-ptanegf9edny@ssh.csjones.co:~/www/csjones.co/public_html/
```

#### Step 2: SSH to Server

```bash
ssh -p 18765 u163-ptanegf9edny@ssh.csjones.co
```

**Troubleshooting SSH**:
- If you get "Permission denied (publickey)" error, you need to set up SSH keys
- Quick fix: Use SiteGround's Site Tools → SSH Keys Manager to add your public key
- OR use SiteGround's web-based SSH terminal in Site Tools → Dev → SSH Keys Manager

#### Step 3: Backup Current Installation (IMPORTANT)

```bash
cd ~/www/csjones.co/public_html/

# Create backup with timestamp
BACKUP_DIR=~/backups/tengo-$(date +%Y%m%d-%H%M%S)
mkdir -p $BACKUP_DIR

# Backup current installation (if exists)
if [ -d "tengo" ]; then
    cp -r tengo/ $BACKUP_DIR/
    echo "Backup created at: $BACKUP_DIR"
fi

# Verify backup
ls -lh $BACKUP_DIR/
```

#### Step 4: Extract New Package

```bash
cd ~/www/csjones.co/public_html/

# Remove old installation
rm -rf tengo/

# Create new tengo directory
mkdir tengo
cd tengo/

# Extract the fixed package
tar -xzf ../tengo-v0.2.7-FIXED.tar.gz

# Verify extraction
ls -la
```

**Expected**: You should see Laravel directory structure:
```
app/
artisan
bootstrap/
config/
database/
public/
resources/
routes/
storage/
vendor/
.env.example
.htaccess
```

#### Step 5: Create Required Directories

```bash
cd ~/www/csjones.co/public_html/tengo/

# Create all storage directories
mkdir -p storage/framework/sessions
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p storage/app/backups

# Set correct permissions
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/

# Verify
ls -la storage/
```

#### Step 6: Configure Environment

**If upgrading** (restore your previous .env):
```bash
cp ~/backups/tengo-YYYYMMDD-HHMMSS/tengo/.env .env
```

**If fresh install** (create new .env):
```bash
cp .env.example .env
nano .env
```

**CRITICAL .env settings**:
```env
APP_NAME="Fynla - Financial Planning System"
APP_ENV=production
APP_KEY=                                    # Generate in next step
APP_DEBUG=false
APP_URL=https://csjones.co/tengo

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=dbow3dj6o4qnc4
DB_USERNAME=uixybijdvk3yv
DB_PASSWORD=PixieRebecca2020

CACHE_DRIVER=array                         # CRITICAL for SiteGround
SESSION_DRIVER=file
SESSION_PATH=/tengo                        # CRITICAL for subfolder

ASSET_URL=https://csjones.co/tengo
VITE_API_BASE_URL=https://csjones.co/tengo
```

**Generate APP_KEY**:
```bash
php artisan key:generate
```

**Verify configuration**:
```bash
# Check APP_KEY was generated
grep APP_KEY .env

# Should show: APP_KEY=base64:...
```

#### Step 7: Verify Critical Files

```bash
cd ~/www/csjones.co/public_html/tengo/

# 1. Verify NO hot files exist
ls -la hot 2>/dev/null && echo "⚠️ WARNING: hot file exists!" || echo "✓ No hot file"
ls -la public/hot 2>/dev/null && echo "⚠️ WARNING: public/hot exists!" || echo "✓ No public/hot"

# 2. Verify manifest symlink
ls -la public/build/manifest.json
# Should show: manifest.json -> .vite/manifest.json

# If symlink missing, create it:
cd public/build && ln -sf .vite/manifest.json manifest.json && cd ../..

# 3. Verify .htaccess files
cat .htaccess | grep "RewriteRule"
# Should show: RewriteRule ^(.*)$ public/$1 [L,QSA]

cat public/.htaccess | grep "RewriteRule" | tail -1
# Should show: RewriteRule ^ index.php [L]
```

#### Step 8: Database Setup

**Test connection**:
```bash
php artisan db:show
```

**Expected**:
```
MySQL
Database: dbow3dj6o4qnc4
Host: localhost
```

**If fresh database**, run migrations:
```bash
php artisan migrate --force
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=ActuarialLifeTablesSeeder --force
```

#### Step 9: Clear and Optimize

```bash
# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Verify optimization
php artisan optimize:status
```

#### Step 10: Test Application

```bash
# Test with curl
curl -I https://csjones.co/tengo

# Should return:
# HTTP/2 200
# content-type: text/html; charset=UTF-8
# ... (not just 1 byte!)
```

**Open in browser**: https://csjones.co/tengo

**Expected**: Landing page should load with:
- Fynla branding
- "Get Started" button
- "Login" link
- Full CSS styling
- No console errors

---

## 🔍 TROUBLESHOOTING

### If Still Showing Blank Page

#### Enable Debug Mode Temporarily

```bash
cd ~/www/csjones.co/public_html/tengo/

# Rename current index.php
mv public/index.php public/index-production.php

# Use debug version
cp public/index-debug.php public/index.php

# Clear caches
php artisan config:clear

# Visit site in browser - you should now see detailed errors
```

**After fixing issues**, restore production index:
```bash
mv public/index-production.php public/index.php
```

#### Run Diagnostic Script

```bash
cd ~/www/csjones.co/public_html/tengo/

# Run comprehensive diagnostics
bash deployment/server-diagnostic.sh

# Review the output for any ✗ (failed) checks
```

The diagnostic will check:
1. Directory structure
2. File permissions
3. Hot files (should not exist)
4. Vite build assets
5. .htaccess configuration
6. Environment variables
7. PHP version and extensions
8. Database connection
9. Laravel routes
10. Storage directories
11. Recent errors in logs

#### Check Laravel Logs

```bash
cd ~/www/csjones.co/public_html/tengo/

# View recent errors
tail -50 storage/logs/laravel.log

# Search for today's errors
grep "$(date +%Y-%m-%d)" storage/logs/laravel.log | grep ERROR
```

### Common Issues and Fixes

#### Issue: "500 Internal Server Error"

**Cause**: Usually permissions or missing APP_KEY

**Fix**:
```bash
# Check APP_KEY
grep APP_KEY .env

# If empty, generate it
php artisan key:generate

# Fix permissions
chmod -R 775 storage/ bootstrap/cache/

# Clear caches
php artisan config:clear
```

#### Issue: "404 Not Found"

**Cause**: .htaccess not working

**Fix**:
```bash
# Verify mod_rewrite is enabled (should be on SiteGround)
# Check .htaccess exists
ls -la .htaccess public/.htaccess

# Verify redirect rule
cat .htaccess | grep RewriteRule
```

#### Issue: Assets Return 404

**Cause**: Manifest missing or wrong path

**Fix**:
```bash
cd ~/www/csjones.co/public_html/tengo/public/build/

# Check manifest
ls -la manifest.json .vite/manifest.json

# Recreate symlink if needed
ln -sf .vite/manifest.json manifest.json
```

#### Issue: "Cache store does not support tagging"

**Cause**: Wrong CACHE_DRIVER in .env

**Fix**:
```bash
nano .env
# Change to: CACHE_DRIVER=array

php artisan config:clear
php artisan cache:clear
php artisan config:cache
```

---

## 📊 VERIFICATION CHECKLIST

After deployment, verify these items:

### Server-Side Checks
- [ ] No `hot` files exist anywhere
- [ ] `manifest.json` symlink created in `public/build/`
- [ ] Storage directories exist with 775 permissions
- [ ] `.env` file configured with correct values
- [ ] `APP_KEY` is generated
- [ ] Database connection works (`php artisan db:show`)
- [ ] Routes are loaded (`php artisan route:list`)
- [ ] All caches cleared and optimized

### Browser Checks
- [ ] Landing page loads at `https://csjones.co/tengo`
- [ ] CSS styles applied correctly
- [ ] JavaScript functioning (buttons work)
- [ ] Browser console shows no errors (F12 → Console)
- [ ] Network tab shows assets loading (F12 → Network)
- [ ] Login page accessible
- [ ] Can log in successfully

### Functional Tests
- [ ] Admin login works (`admin@fps.com` / `admin123456`)
- [ ] Dashboard loads after login
- [ ] Navigation works between pages
- [ ] Module dashboards load (Protection, Savings, etc.)
- [ ] API endpoints return data (check Network tab)
- [ ] No PHP errors in logs

---

## 🆘 IF ALL ELSE FAILS

### Nuclear Option: Enable Full Error Display

**Only use this temporarily for debugging:**

```bash
cd ~/www/csjones.co/public_html/tengo/

# Edit .env
nano .env

# Change these lines:
APP_ENV=local
APP_DEBUG=true
LOG_LEVEL=debug

# Save and clear cache
php artisan config:clear

# Visit site - you'll see detailed error messages
```

**IMPORTANT**: After fixing issues, immediately set:
```env
APP_ENV=production
APP_DEBUG=false
```

### Contact SiteGround Support

If you've tried everything and still have issues:

1. Take screenshots of:
   - Browser console errors (F12)
   - Network tab showing failed requests
   - Output of `php artisan route:list`
   - Output of `php artisan db:show`
   - Last 50 lines of `storage/logs/laravel.log`

2. Open ticket with SiteGround:
   - Mention: "Laravel 10 deployment in subfolder (/tengo)"
   - Ask: "Is mod_rewrite enabled and working?"
   - Ask: "Are there any PHP restrictions preventing Laravel?"
   - Provide: PHP version needed (8.2+)

---

## 📝 POST-DEPLOYMENT TASKS

After successful deployment:

1. **Change admin password**:
   - Login as `admin@fps.com` / `admin123456`
   - Go to User Profile
   - Change password immediately

2. **Disable debug mode** (if enabled):
   ```bash
   nano .env
   # Set: APP_DEBUG=false
   php artisan config:clear && php artisan config:cache
   ```

3. **Monitor logs**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

4. **Test all modules**:
   - Create test user
   - Add sample data to each module
   - Run calculations
   - Generate reports

5. **Set up backups** (cron job):
   ```bash
   # Add to crontab:
   0 2 * * * cd ~/www/csjones.co/public_html/tengo && php artisan backup:run
   ```

---

## 📞 SUPPORT INFORMATION

### Key URLs
- **Application**: https://csjones.co/tengo
- **Login**: https://csjones.co/tengo/login
- **SiteGround Control**: https://my.siteground.com/

### Admin Credentials
- **Email**: admin@fps.com
- **Password**: admin123456 (⚠️ CHANGE IMMEDIATELY)

### Server Details
- **SSH**: `ssh -p 18765 u163-ptanegf9edny@ssh.csjones.co`
- **App Root**: `~/www/csjones.co/public_html/tengo/`
- **Web Root**: `~/www/csjones.co/public_html/tengo/public/`
- **Logs**: `~/www/csjones.co/public_html/tengo/storage/logs/laravel.log`

### Files Included in This Package
- ✅ `deployment/server-diagnostic.sh` - Comprehensive server diagnostic
- ✅ `public/index-debug.php` - Debug version of index with error display
- ✅ `deployment/EMERGENCY_FIX_DEPLOYMENT.md` - This file

---

## ✅ SUMMARY OF FIXES

| Issue | Status | Fix Applied |
|-------|--------|-------------|
| `public/hot` file exists | ✅ Fixed | Removed from build |
| Missing manifest symlink | ✅ Fixed | Created `build/manifest.json → .vite/manifest.json` |
| No error visibility | ✅ Fixed | Added `index-debug.php` |
| Production build | ✅ Fixed | Rebuilt with NODE_ENV=production |
| Deployment docs | ✅ Fixed | Created this comprehensive guide |
| Diagnostic tools | ✅ Fixed | Added server diagnostic script |

---

**Version**: 1.0
**Created**: November 12, 2025, 6:15 PM GMT
**Package**: tengo-v0.2.7-FIXED.tar.gz (77MB)
**Status**: 🚀 READY TO DEPLOY

---

🤖 **Generated with [Claude Code](https://claude.com/claude-code)**
