# Fynla v0.2.5 - Root Directory Deployment Guide

**Target**: https://csjones.co (ROOT DIRECTORY)
**Server**: SiteGround Shared Hosting
**Deployment Time**: ~45 minutes
**Last Updated**: November 11, 2025

---

## 📋 Pre-Deployment Checklist

- [ ] Production build completed locally (`npm run build`)
- [ ] All tests passing (optional but recommended)
- [ ] Database backup created via admin panel
- [ ] SSH credentials available
- [ ] Database credentials from SiteGround noted
- [ ] Email credentials configured

---

## 🚀 Deployment Steps

### Step 1: Local Build (5 minutes)

```bash
# Open a FRESH terminal (no dev environment variables)
cd /Users/Chris/Desktop/fpsApp/tengo

# Build production assets
NODE_ENV=production npm run build

# Verify build completed
ls -la public/build/

# Install production Composer dependencies
composer install --optimize-autoloader --no-dev

# Create deployment archive (exclude unnecessary files)
cd /Users/Chris/Desktop/fpsApp/
tar -czf tengo-root-deploy.tar.gz \
  --exclude='tengo/node_modules' \
  --exclude='tengo/.git' \
  --exclude='tengo/tests' \
  --exclude='tengo/.env' \
  --exclude='tengo/.env.production' \
  --exclude='tengo/storage/logs/*' \
  --exclude='tengo/storage/framework/cache/*' \
  --exclude='tengo/storage/framework/sessions/*' \
  --exclude='tengo/storage/framework/views/*' \
  tengo/

# Verify archive size (should be 20-50 MB)
ls -lh tengo-root-deploy.tar.gz

# Reinstall dev dependencies for local development
cd tengo
composer install
```

---

### Step 2: Upload to Server (5 minutes)

```bash
# Upload archive to server
scp tengo-root-deploy.tar.gz u163-ptanegf9edny@ssh.csjones.co:~/

# OR if you have SSH alias configured:
scp tengo-root-deploy.tar.gz siteground:~/
```

---

### Step 3: Server Setup (15 minutes)

```bash
# SSH into server
ssh -p 18765 u163-ptanegf9edny@ssh.csjones.co

# OR with alias:
ssh siteground

# Navigate to web root
cd ~/www/csjones.co/public_html/

# IMPORTANT: Backup existing files if any
# Create backup directory
mkdir -p ~/backups/
tar -czf ~/backups/csjones-root-backup-$(date +%Y%m%d-%H%M%S).tar.gz .

# If old site exists, rename it to prevent conflicts
if [ -d "oldsite" ]; then
    mv oldsite oldsite.backup
    echo "Renamed old site directory"
fi

# Extract deployment archive
tar -xzf ~/tengo-root-deploy.tar.gz --strip-components=1
rm ~/tengo-root-deploy.tar.gz

# CRITICAL: Create missing storage directories
mkdir -p storage/framework/sessions
mkdir -p storage/framework/cache
mkdir -p storage/framework/views
mkdir -p storage/logs

# Set correct permissions
chmod -R 755 .
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
chmod 644 .htaccess

# Verify files extracted
ls -la
```

---

### Step 4: Environment Configuration (10 minutes)

```bash
# Still on server in ~/www/csjones.co/public_html/

# Create .env from template
cp .env.production.example .env

# Edit .env file
nano .env
```

**Update these critical values in .env:**

```ini
# Application
APP_NAME="Fynla - Financial Planning System"
APP_ENV=production
APP_KEY=                          # Generate in next step
APP_DEBUG=false
APP_URL=https://csjones.co

# Database (from SiteGround)
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=dbow3dj6o4qnc4       # Your SiteGround database
DB_USERNAME=uixybijdvk3yv        # Your SiteGround user
DB_PASSWORD=YOUR_DB_PASSWORD      # From SiteGround MySQL panel

# Session (ROOT DEPLOYMENT)
SESSION_PATH=/
SESSION_DOMAIN=csjones.co
SESSION_SECURE_COOKIE=true

# Asset URL (empty for root)
ASSET_URL=

# Mail (from SiteGround)
MAIL_HOST=mail.csjones.co
MAIL_PORT=587
MAIL_USERNAME=noreply@csjones.co
MAIL_PASSWORD=YOUR_EMAIL_PASSWORD
MAIL_FROM_ADDRESS=noreply@csjones.co
```

**Save and exit** (Ctrl+O, Enter, Ctrl+X)

```bash
# Generate application key
php artisan key:generate --force

# Verify .env configuration
cat .env | grep -E "APP_URL|APP_KEY|DB_DATABASE|ASSET_URL|SESSION_PATH"

# Test database connection
php artisan db:show
```

---

### Step 5: Database Setup (10 minutes)

```bash
# Run migrations (creates 54 tables)
php artisan migrate --force

# Seed tax configuration data
php artisan db:seed --class=TaxConfigurationSeeder --force

# Verify tables created
php artisan db:table users
php artisan db:table tax_configurations

# Create admin account
php artisan tinker
```

**In Tinker:**

```php
$admin = new \App\Models\User();
$admin->name = 'System Administrator';
$admin->email = 'admin@fps.com';
$admin->password = bcrypt('YOUR_SECURE_ADMIN_PASSWORD');
$admin->email_verified_at = now();
$admin->is_admin = true;
$admin->save();

// Verify admin created
\App\Models\User::where('is_admin', true)->count();
// Should output: 1

exit
```

---

### Step 6: Fix Vite Manifest Path (CRITICAL - 2 minutes)

**Issue**: Laravel's Vite helper looks for `public/build/manifest.json` but Vite creates `public/build/.vite/manifest.json`

```bash
# Create symlink so Laravel can find the manifest
ln -s .vite/manifest.json public/build/manifest.json

# Verify symlink created
ls -la public/build/manifest.json
# Should show: public/build/manifest.json -> .vite/manifest.json

# Remove hot files if they exist (causes dev mode issues)
rm -f public/hot
rm -f hot
```

**Why this is needed**: In root deployment with Vite, the manifest is nested in `.vite/` subdirectory but Laravel expects it at the root of the build directory.

---

### Step 7: Laravel Optimization (5 minutes)

```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Build optimized caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize Composer autoloader
composer dump-autoload --optimize

# Verify cache files created
ls -la bootstrap/cache/
```

---

### Step 8: Clean Up Test Data (2 minutes)

**IMPORTANT**: Delete all test users and their data before going live

```bash
php artisan tinker
```

In Tinker:
```php
// Delete all non-admin users (cascades to all related data)
$deleted = \App\Models\User::where('is_admin', false)->delete();
echo "Deleted {$deleted} non-admin users\n";

// Verify only admin remains
\App\Models\User::all(['id', 'name', 'email'])->each(function($u) {
    echo $u->id . ' | ' . $u->email . "\n";
});

exit
```

---

### Step 9: Cron Jobs Setup (5 minutes)

**Access**: SiteGround Site Tools → Devs → Cron Jobs

#### Job 1: Queue Worker (Every Minute)
**Purpose**: Process background jobs (Monte Carlo simulations)
```bash
cd ~/www/csjones.co/public_html && php artisan queue:work --stop-when-empty --max-time=3600 >> /dev/null 2>&1
```

**Schedule**: `* * * * *` (every minute)

#### Job 2: Daily Cleanup (2:00 AM)
**Purpose**: Clean up old queue jobs and stale cache
```bash
cd ~/www/csjones.co/public_html && php artisan queue:prune-batches && php artisan cache:prune-stale-tags >> /dev/null 2>&1
```

**Schedule**: `0 2 * * *` (daily at 2:00 AM)

---

### Step 8: Testing (5 minutes)

Visit: **https://csjones.co**

#### Landing Page Test
- [ ] Landing page loads correctly
- [ ] All styling applied (no unstyled content flash)
- [ ] Navigation menu works
- [ ] Footer displays correctly

#### Registration Test
- [ ] Click "Register" button
- [ ] Fill in registration form
- [ ] Submit form - should create account
- [ ] Redirects to onboarding

#### Admin Login Test
- [ ] Navigate to `/login`
- [ ] Login with `admin@fps.com`
- [ ] Dashboard loads correctly
- [ ] All 5 modules accessible (Protection, Savings, Investment, Retirement, Estate)
- [ ] Admin panel accessible

#### Browser Console Test
- [ ] Open DevTools (F12)
- [ ] Check Console tab
- [ ] Should see no errors
- [ ] All assets loading correctly (check Network tab)

---

## 📁 Directory Structure (Root Deployment)

```
~/www/csjones.co/public_html/
├── .env                        # Environment config (SECURE)
├── .htaccess                   # Apache rewrite rules
├── index.php                   # Entry point
├── app/                        # Application code
├── bootstrap/                  # Laravel bootstrap
│   └── cache/                  # Cached files (775 perms)
├── config/                     # Configuration files
├── database/                   # Migrations & seeders
├── public/                     # PUBLIC assets (CSS, JS, images)
│   ├── build/                  # Vite production build
│   │   ├── .vite/
│   │   │   └── manifest.json
│   │   ├── assets/             # Compiled CSS/JS
│   │   └── manifest.json
│   └── favicon.ico
├── resources/                  # Vue components, views
├── routes/                     # API & web routes
├── storage/                    # Logs, cache, sessions (775 perms)
│   ├── app/
│   │   └── backups/           # Database backups
│   ├── framework/
│   │   ├── cache/
│   │   ├── sessions/
│   │   └── views/
│   └── logs/
│       └── laravel.log
├── vendor/                     # Composer dependencies
├── artisan                     # CLI tool
└── composer.json               # PHP dependencies
```

---

## 🔐 Security Checklist

- [ ] `.env` file has correct permissions (644)
- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production`
- [ ] `SESSION_SECURE_COOKIE=true` for HTTPS
- [ ] Database password is strong and unique
- [ ] Admin password is strong and unique
- [ ] Storage directories have 775 permissions
- [ ] `.htaccess` file present and correct
- [ ] `vendor/`, `storage/`, `bootstrap/cache/` NOT web-accessible

---

## 🐛 Troubleshooting

### Issue 1: Old Site Loading Instead of Fynla

**Symptom**: Visiting https://csjones.co shows old website content instead of Fynla application

**Cause**: Old site files (e.g., `oldsite/` directory) taking precedence

**Solution**:
```bash
# Find and rename old site directories
ls -la | grep old
mv oldsite oldsite.backup

# Clear browser cache and test
curl -I https://csjones.co
```

---

### Issue 2: 500 Internal Server Error - Missing Sessions Directory

**Symptom**: HTTP 500 error with message about `storage/framework/sessions/` not found

**Cause**: Deployment archive excluded `storage/framework/sessions/` directory

**Solution**:
```bash
# Create missing storage directories
mkdir -p storage/framework/sessions
mkdir -p storage/framework/cache
mkdir -p storage/framework/views
mkdir -p storage/logs

# Set correct permissions
chmod -R 775 storage/

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

**Prevention**: Always include empty storage directories in deployment archives

---

### Issue 3: Blank Page with Development Server URLs

**Symptom**: Blank page, browser console shows CORS errors trying to load from `http://127.0.0.1:5173`

**Cause**: `hot` file exists in production, making Laravel think Vite dev server is running

**Solution**:
```bash
# Remove hot files
rm -f public/hot
rm -f hot

# Clear view cache
php artisan view:clear

# Verify fixed
curl -s https://csjones.co | head -20
# Should show production asset URLs, not localhost:5173
```

**Prevention**: Ensure `hot` files are excluded from deployment archive

---

### Issue 4: Vite Manifest Not Found (CRITICAL)

**Symptom**: Error: `Vite manifest not found at: /path/to/public/build/manifest.json`

**Cause**: Laravel looks for `public/build/manifest.json` but Vite creates `public/build/.vite/manifest.json`

**Solution**:
```bash
# Create symlink to fix path mismatch
ln -s .vite/manifest.json public/build/manifest.json

# Verify symlink created
ls -la public/build/manifest.json
# Should show: manifest.json -> .vite/manifest.json

# Clear caches
php artisan view:clear
php artisan config:clear

# Test assets load
curl -I https://csjones.co/build/assets/app-*.js
# Should return HTTP 200
```

**Why this happens**: In root deployment with Vite's `buildDirectory: 'build'` setting, the manifest ends up nested in `.vite/` subdirectory, but Laravel's Vite helper expects it at the root of the build directory.

---

### Issue 5: General 500 Internal Server Error

**Symptom**: Generic 500 error without specific message

**Diagnosis**:
```bash
# Enable debug mode temporarily
sed -i 's/APP_DEBUG=false/APP_DEBUG=true/g' .env
php artisan config:clear

# Check error in browser or via curl
curl -s https://csjones.co 2>&1 | head -50

# Check Laravel logs
tail -100 storage/logs/laravel.log

# REMEMBER: Disable debug after fixing
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/g' .env
php artisan config:clear
```

**Common fixes**:
```bash
# Fix permissions
chmod -R 775 storage/ bootstrap/cache/

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache
```

### Assets 404 (CSS/JS not loading)

```bash
# Verify build directory exists
ls -la public/build/assets/

# Check .htaccess file exists
cat public/.htaccess

# Verify base path in compiled JS
head -20 public/build/assets/app-*.js | grep "csjones.co"
```

### Database Connection Error

```bash
# Test database connection
php artisan db:show

# Verify credentials
cat .env | grep DB_

# Test MySQL connection directly
mysql -u uixybijdvk3yv -p -h localhost dbow3dj6o4qnc4
```

### CSRF Token Mismatch (419 Error)

```bash
# Verify session configuration
cat .env | grep SESSION

# Should have:
# SESSION_PATH=/
# SESSION_DOMAIN=csjones.co
# SESSION_SECURE_COOKIE=true

# Clear session storage
rm -rf storage/framework/sessions/*
php artisan cache:clear
```

### Route Not Found / 404 Errors

```bash
# Clear and rebuild route cache
php artisan route:clear
php artisan route:cache

# List all routes to verify
php artisan route:list

# Check .htaccess file exists in public/
ls -la public/.htaccess
```

---

## 🔄 Updating Deployment

When deploying updates to production:

```bash
# 1. Build locally (fresh terminal)
cd /Users/Chris/Desktop/fpsApp/tengo
NODE_ENV=production npm run build

# 2. Create new deployment archive
cd /Users/Chris/Desktop/fpsApp/
tar -czf tengo-update-$(date +%Y%m%d).tar.gz \
  --exclude='tengo/node_modules' \
  --exclude='tengo/.git' \
  --exclude='tengo/tests' \
  --exclude='tengo/.env' \
  tengo/

# 3. Upload to server
scp tengo-update-$(date +%Y%m%d).tar.gz siteground:~/

# 4. On server - backup, extract, migrate
ssh siteground
cd ~/www/csjones.co/public_html/

# Backup existing .env
cp .env .env.backup

# Extract update (preserves .env)
tar -xzf ~/tengo-update-$(date +%Y%m%d).tar.gz --strip-components=1

# Restore .env if overwritten
mv .env.backup .env

# Run migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 📞 Support Information

### SiteGround Credentials
- **Account**: u163-ptanegf9edny
- **SSH Host**: ssh.csjones.co
- **SSH Port**: 18765
- **Database**: dbow3dj6o4qnc4
- **DB User**: uixybijdvk3yv

### Application URLs
- **Live Site**: https://csjones.co
- **Admin Panel**: https://csjones.co/admin
- **API Base**: https://csjones.co/api

### Important Paths
- **Web Root**: `~/www/csjones.co/public_html/`
- **Logs**: `~/www/csjones.co/public_html/storage/logs/laravel.log`
- **Backups**: `~/backups/`

---

## ✅ Final Deployment Checklist

- [ ] Local production build completed
- [ ] Deployment archive uploaded to server
- [ ] Files extracted to public_html/
- [ ] Permissions set correctly (755 files, 775 storage)
- [ ] .env file configured with production credentials
- [ ] APP_KEY generated
- [ ] Database connection verified
- [ ] Migrations run successfully (54 tables)
- [ ] Tax configuration seeded
- [ ] Admin account created
- [ ] Caches built (config, route, view)
- [ ] Cron jobs configured (queue + cleanup)
- [ ] Website loads at https://csjones.co
- [ ] Landing page displays correctly
- [ ] User registration works
- [ ] Admin login works
- [ ] All 5 modules accessible
- [ ] Browser console shows no errors
- [ ] Assets loading correctly (CSS, JS)
- [ ] Database queries executing successfully

---

**Deployment Guide Version**: 1.0
**Fynla Application Version**: v0.2.5
**Target Environment**: SiteGround Shared Hosting (Root Directory)
**Expected Deployment Time**: 45 minutes
**Guide Last Updated**: November 11, 2025

🤖 **Generated with [Claude Code](https://claude.com/claude-code)**
