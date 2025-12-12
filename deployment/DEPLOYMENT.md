# Fynla v0.1.2.13 - Production Deployment to SiteGround

**DEFINITIVE DEPLOYMENT GUIDE**

**Target Environment**: SiteGround Shared Hosting
**URL**: https://csjones.co/tengo
**Method**: Symlink Setup (Secure Laravel Best Practice)
**Date**: October 30, 2025

---

## 🚨 CRITICAL: Read This First

### Environment Separation Warning

**THE #1 CAUSE OF DEPLOYMENT FAILURES IS ENVIRONMENT VARIABLE CONTAMINATION**

**ALWAYS use a FRESH TERMINAL for deployment:**

```bash
# WRONG ❌ - Reusing terminal from development
# (You've been running dev servers with local env vars)
# Those variables persist and will break deployment

# CORRECT ✅ - Open a NEW terminal window for deployment
# Start fresh with no lingering environment variables
```

### What This Guide Provides

- **Single source of truth** for deploying Fynla to SiteGround
- **Step-by-step instructions** tested on SiteGround UK servers
- **Symlink security setup** (keeps application code outside web root)
- **Troubleshooting guide** for common issues
- **Your specific credentials** (SSH username, host, port)

---

## Table of Contents

1. [Pre-Deployment Checklist](#1-pre-deployment-checklist)
2. [Local Build Preparation](#2-local-build-preparation)
3. [SSH Setup](#3-ssh-setup)
4. [SiteGround Database Setup](#4-siteground-database-setup)
5. [Upload Application Files](#5-upload-application-files)
6. [Create Symlink Structure](#6-create-symlink-structure)
7. [Configure Environment](#7-configure-environment)
8. [Run Migrations](#8-run-migrations)
9. [Optimize for Production](#9-optimize-for-production)
10. [Test Deployment](#10-test-deployment)
11. [Troubleshooting](#11-troubleshooting)
12. [Maintenance](#12-maintenance)

---

## 1. Pre-Deployment Checklist

Before starting, ensure you have:

- [ ] **Fresh terminal session** (no environment variable pollution)
- [ ] **SiteGround credentials**: Username, password, SSH access
- [ ] **Local tests passing**: `./vendor/bin/pest`
- [ ] **Git repo clean**: All changes committed
- [ ] **Database backup** (if updating existing deployment)
- [ ] **30-45 minutes** of uninterrupted time

**Your SSH Credentials**:
- **Username**: `u163-ptanegf9edny`
- **Host**: `ssh.csjones.co`
- **Port**: `18765`
- **Key**: `~/.ssh/siteground_ssh_key.pem` (already saved)

---

## 2. Local Build Preparation

### Step 2.1: Create .env.production File (CRITICAL!)

**CRITICAL**: Vite reads environment variables at BUILD TIME. You MUST create `.env.production` with the correct production API URL BEFORE building:

```bash
cd /Users/Chris/Desktop/fpsApp/tengo

# Create .env.production with production API URL
cat > .env.production << 'EOF'
VITE_APP_NAME="Fynla - Financial Planning System"
VITE_API_BASE_URL=https://csjones.co/tengo
EOF

# Verify the file was created
cat .env.production
```

**Why this is critical**:
- If you build WITHOUT this file, Vite will use `.env` which has `VITE_API_BASE_URL=http://localhost:8000`
- This will cause ALL API calls to go to localhost instead of your production server
- This results in `ERR_CONNECTION_REFUSED` errors on registration/login

### Step 2.2: Build Production Assets

**IMPORTANT**: This MUST be done from a fresh terminal with correct environment:

```bash
# Open a NEW terminal (not one used for dev servers)
cd /Users/Chris/Desktop/fpsApp/tengo

# Build frontend assets for production
NODE_ENV=production npm run build
```

**Expected Output**:
```
vite v4.x.x building for production...
✓ built in XXXXms
```

**Verify build succeeded**:
```bash
ls -la public/build/
# Should see:
# - .vite/ directory
# - assets/ directory (50+ JS/CSS files)
```

**Verify correct API URL was baked in**:
```bash
# This should return results (production URL present)
grep -r "csjones.co/tengo" public/build/assets/*.js | head -3

# This should return nothing (localhost NOT present)
grep -r "localhost:8000" public/build/assets/*.js
```

### Step 2.3: Verify Vite Configuration

Your `vite.config.js` should already be configured correctly:
```javascript
base: process.env.NODE_ENV === 'production' ? '/tengo/build/' : '/',
```

This tells Vite to build assets for the `/tengo` subdirectory.

### Step 2.4: Create Deployment Archive

```bash
# Navigate to parent directory
cd /Users/Chris/Desktop/fpsApp/

# Verify you're in the right location
pwd
# Should output: /Users/Chris/Desktop/fpsApp

# Create tarball (excluding unnecessary files)
tar -czf tengo-deploy.tar.gz \
  --exclude='tengo/node_modules' \
  --exclude='tengo/.git' \
  --exclude='tengo/.github' \
  --exclude='tengo/tests' \
  --exclude='tengo/.claude' \
  --exclude='tengo/docs' \
  --exclude='tengo/tasks' \
  --exclude='tengo/*.md' \
  --exclude='tengo/.env' \
  --exclude='tengo/.env.development' \
  --exclude='tengo/storage/logs/*.log' \
  --exclude='tengo/storage/framework/cache/data/*' \
  --exclude='tengo/storage/framework/sessions/*' \
  --exclude='tengo/storage/framework/views/*' \
  tengo/

# Verify archive size (should be 20-50 MB)
ls -lh tengo-deploy.tar.gz
```

**What's included**:
- ✅ `app/` (Laravel application code)
- ✅ `bootstrap/` (Laravel bootstrap)
- ✅ `config/` (Configuration files)
- ✅ `database/` (Migrations, seeders)
- ✅ `public/` (Including `public/build/` with compiled assets)
- ✅ `resources/` (Views, raw assets)
- ✅ `routes/` (API and web routes)
- ✅ `storage/` (Framework directories, empty logs)
- ✅ `vendor/` (Composer dependencies)
- ✅ `.env.production.example` (Template)
- ✅ `artisan` (CLI tool)
- ✅ `composer.json`, `composer.lock`

**What's excluded**:
- ❌ `node_modules/` (Not needed on server)
- ❌ `.git/` (Version control)
- ❌ `tests/` (Not needed in production)
- ❌ `.env` (Local environment - will create fresh on server)
- ❌ Markdown documentation files
- ❌ Log files and cached data

---

## 3. SSH Setup

### Step 3.1: Create SSH Key for SiteGround

**On your Mac**, open Terminal:

```bash
# Create .ssh directory if it doesn't exist
mkdir -p ~/.ssh

# Your key already exists at:
# ~/.ssh/siteground_ssh_key.pem

# If you need to create a new one:
# nano ~/.ssh/siteground_ssh_key.pem
```

### Step 3.2: Verify Your Existing SSH Key

You already have your SSH key saved at `~/.ssh/siteground_ssh_key.pem`.

**Verify it exists**:
```bash
ls -la ~/.ssh/siteground_ssh_key.pem
```

If you need to regenerate or get a new key:
1. Log in to **SiteGround Site Tools** at https://tools.siteground.com
2. Navigate to **Devs** → **SSH Keys Manager**
3. Copy the **Private Key** if needed

### Step 3.3: Set Correct Permissions

```bash
# CRITICAL: Set permissions (SSH won't work without this)
chmod 600 ~/.ssh/siteground_ssh_key.pem

# Verify permissions
ls -la ~/.ssh/siteground_ssh_key.pem
# Should show: -rw-------
```

### Step 3.4: Create SSH Config (Optional but Recommended)

```bash
# Edit SSH config
nano ~/.ssh/config
```

Add this configuration:
```
Host siteground-tengo
    HostName ssh.csjones.co
    Port 18765
    User u163-ptanegf9edny
    IdentityFile ~/.ssh/siteground_ssh_key.pem
    ServerAliveInterval 60
    ServerAliveCountMax 3
```

Save: `Ctrl+O`, `Enter`, `Ctrl+X`

### Step 3.5: Test SSH Connection

```bash
# Test connection (using config alias)
ssh siteground-tengo

# OR without config:
ssh -p 18765 -i ~/.ssh/siteground_ssh_key.pem u163-ptanegf9edny@ssh.csjones.co

# If prompted about authenticity, type 'yes'
# You should see a prompt like: baseos | csjones.co | u163-ptanegf9edny
```

**Troubleshooting**:
- **"Permission denied (publickey)"**: Run `chmod 600 ~/.ssh/siteground_ssh_key.pem`
- **"Connection refused"**: Check port is `18765`, try `22` if that fails
- **"No such identity"**: Verify key file exists with `ls -la ~/.ssh/siteground_ssh_key.pem`

Once connected, exit for now:
```bash
exit
```

---

## 4. SiteGround Database Setup

### Step 4.1: Create MySQL Database via Site Tools

1. Log in to **SiteGround Site Tools** at https://tools.siteground.com
2. Navigate to **Site** → **MySQL** → **Databases**

**Note**: Your database and user already exist:
- **Database name**: `dbow3dj6o4qnc4`
- **Username**: `uixybijdvk3yv`

If you need to create a new database:
3. Click **Create Database**
4. Create a new user or use existing user `uixybijdvk3yv`
5. Assign user to database with **ALL PRIVILEGES**

**Your database credentials** (you'll need them in Step 7):
```
DB_HOST=localhost
DB_DATABASE=dbow3dj6o4qnc4
DB_USERNAME=uixybijdvk3yv
DB_PASSWORD=YOUR_DATABASE_PASSWORD
```

---

## 5. Upload Application Files

### Step 5.1: Upload Tarball to Server

**From your Mac** (ensure you're NOT SSH'd into server):

```bash
# Check your prompt - should show your Mac, not server
# If you see "baseos" or "siteground" in prompt, type 'exit' first

# Upload tarball (using SSH config alias)
scp tengo-deploy.tar.gz siteground-tengo:~/

# OR without config:
scp -P 18765 -i ~/.ssh/siteground_ssh_key.pem tengo-deploy.tar.gz u163-ptanegf9edny@ssh.csjones.co:~/

# This will take 2-5 minutes depending on connection speed
```

### Step 5.2: Remove Old Symlinks (If Any Exist)

**SSH into server**:
```bash
ssh siteground-tengo
```

**Remove old symlinks**:
```bash
# Navigate to web root
cd ~/www/csjones.co/public_html/

# List to see if old symlink exists
ls -la | grep tengo

# If you see something like: tengo -> some/old/path
# Remove it:
rm tengo

# Verify it's gone
ls -la | grep tengo
# Should return nothing
```

### Step 5.3: Extract Application Files

**Still on server**:
```bash
# Navigate to web root
cd ~/www/csjones.co/public_html/

# Create application directory (NOT the web-accessible one yet)
mkdir -p tengo_laravel

# Extract tarball
tar -xzf ~/tengo-deploy.tar.gz -C tengo_laravel/ --strip-components=1

# Verify extraction
ls -la tengo_laravel/
# Should see: app/, bootstrap/, config/, public/, vendor/, etc.

# Remove tarball to save space
rm ~/tengo-deploy.tar.gz

# Set correct permissions
chmod -R 755 tengo_laravel/
chmod -R 775 tengo_laravel/storage/
chmod -R 775 tengo_laravel/bootstrap/cache/
```

---

## 6. Create Symlink Structure

This is the **most important step** for security and proper Laravel subdirectory deployment.

### Step 6.1: Understanding the Symlink Setup

**Directory Structure**:
```
~/www/csjones.co/public_html/
├── tengo → tengo_laravel/public  # Symbolic link (WEB-ACCESSIBLE)
└── tengo_laravel/                 # Laravel app root (NOT web-accessible)
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── public/                    # Laravel's public directory
    │   ├── index.php              # Front controller
    │   ├── build/                 # Vite assets
    │   └── .htaccess
    ├── storage/
    ├── vendor/
    └── .env
```

**How it works**:
- URL: `https://csjones.co/tengo/` → symlink `tengo/` → actual directory `tengo_laravel/public/`
- Application code in `tengo_laravel/` remains secure (not web-accessible)
- Only `public/` contents are exposed via symlink

### Step 6.2: Create the Symlink

**On server**:
```bash
# Make sure you're in the right directory
cd ~/www/csjones.co/public_html/

# Create symlink
ln -s tengo_laravel/public tengo

# Verify symlink was created
ls -la | grep tengo
# Should show:
# drwxr-xr-x  ... tengo_laravel
# lrwxrwxrwx  ... tengo -> tengo_laravel/public

# Verify symlink works (should show contents of public/)
ls -la tengo/
# Should show: index.php, .htaccess, build/, etc.
```

### Step 6.3: Create Manifest Symlink (CRITICAL for Vite)

Laravel's Vite integration expects `manifest.json` at `public/build/manifest.json`, but Vite creates it at `public/build/.vite/manifest.json`.

**On server**:
```bash
cd ~/www/csjones.co/public_html/tengo_laravel/public/build/

# Create symlink so Laravel can find the manifest
ln -s .vite/manifest.json manifest.json

# Verify
ls -la manifest.json
# Should show: manifest.json -> .vite/manifest.json

# Test it works
cat manifest.json | head -5
# Should show JSON content
```

**This is essential** - without it, you'll get "Vite manifest not found" errors.

---

## 7. Configure Environment

### Step 7.1: Create Production .env File

**On server**:
```bash
cd ~/www/csjones.co/public_html/tengo_laravel/

# Copy example file
cp .env.production.example .env

# Edit .env file
nano .env
```

### Step 7.2: Check Memcached Availability (CRITICAL!)

**BEFORE configuring .env**, check if Memcached is available:

```bash
# Check if Memcached extension is installed
php -m | grep memcached

# Check if Memcached class is available
php -r "if (class_exists('Memcached')) { echo 'Memcached available\n'; } else { echo 'Memcached NOT available\n'; }"
```

**Result**: On SiteGround, Memcached IS available. This is important for the Protection module which requires cache tagging.

### Step 7.3: Update .env Configuration

Update these values in the `.env` file:

```ini
APP_NAME="Fynla - Financial Planning System"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://csjones.co/tengo

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=dbow3dj6o4qnc4
DB_USERNAME=uixybijdvk3yv
DB_PASSWORD=YOUR_DATABASE_PASSWORD_FROM_STEP_4

# CRITICAL: Use memcached (NOT file or array)
# Protection module requires cache tagging which only memcached/redis support
CACHE_DRIVER=memcached
MEMCACHED_HOST=127.0.0.1
MEMCACHED_PORT=11211

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_PATH=/tengo
SESSION_DOMAIN=csjones.co
SESSION_SECURE_COOKIE=true

QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=mail.csjones.co
MAIL_PORT=587
MAIL_USERNAME=noreply@csjones.co
MAIL_PASSWORD=YOUR_EMAIL_PASSWORD
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@csjones.co
MAIL_FROM_NAME="${APP_NAME}"

SANCTUM_STATEFUL_DOMAINS=csjones.co

ASSET_URL=/tengo
```

**Critical settings**:
- `APP_URL=https://csjones.co/tengo` (with HTTPS and /tengo subdirectory)
- `SESSION_PATH=/tengo` (for cookies to work in subdirectory)
- `SESSION_SECURE_COOKIE=true` (required for HTTPS)
- `ASSET_URL=/tengo` (for asset loading)

Save: `Ctrl+O`, `Enter`, `Ctrl+X`

### Step 7.3: Generate Application Key

**On server**:
```bash
cd ~/www/csjones.co/public_html/tengo_laravel/

# Generate unique encryption key
php artisan key:generate --force

# Verify it was added to .env
cat .env | grep APP_KEY
# Should show: APP_KEY=base64:xxxxxxxxxxxxx
```

### Step 7.4: Test Database Connection

```bash
php artisan db:show
```

**Expected output**:
```
MySQL 8.0.x
Database: dbow3dj6o4qnc4
```

If this fails, double-check database credentials in `.env`.

---

## 8. Run Migrations

### Step 8.1: Run Database Migrations

**On server**:
```bash
cd ~/www/csjones.co/public_html/tengo_laravel/

# Run migrations (creates all 40+ tables)
php artisan migrate --force
```

**Expected output**:
```
Migration table created successfully.
Migrating: 2014_10_12_000000_create_users_table
Migrated:  2014_10_12_000000_create_users_table (XX.XXms)
...
(continues for ~40 migrations)
```

### Step 8.2: Seed Tax Configuration

```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
```

**This seeds**:
- UK tax bands (2025/26)
- Personal allowance (£12,570)
- ISA allowance (£20,000)
- Pension annual allowance (£60,000)
- IHT: NRB £325k, RNRB £175k
- Life expectancy tables

### Step 8.3: Create Admin Account

```bash
php artisan tinker
```

**In tinker**:
```php
$admin = new \App\Models\User();
$admin->name = 'Fynla Admin';
$admin->email = 'admin@fps.com';
$admin->password = bcrypt('PixieRebecca2020');
$admin->email_verified_at = now();
$admin->is_admin = true;
$admin->save();

echo "Admin created with ID: " . $admin->id . "\n";
exit
```

**Save these credentials**:
- Email: `admin@fps.com`
- Password: `ChooseSecurePassword123!` (or whatever you chose)

### Step 8.4: Verify Database

```bash
# Check tables were created
php artisan db:table --database=mysql

# Should show 40+ tables including:
# - users
# - protection_profiles, life_insurance_policies
# - savings_accounts, investment_accounts
# - dc_pensions, db_pensions
# - assets, liabilities, properties
# - trusts, gifts, wills
# - letters_to_spouse (NEW in v0.1.2.13)
```

---

## 9. Optimize for Production

### Step 9.1: Cache Configuration

**On server**:
```bash
cd ~/www/csjones.co/public_html/tengo_laravel/

# Clear any existing caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Create missing framework directories (fixes view:cache and session errors)
mkdir -p storage/framework/views
mkdir -p storage/framework/sessions
chmod -R 775 storage/framework/views
chmod -R 775 storage/framework/sessions

# Remove hot file if it exists (critical - prevents Vite dev server errors)
rm -f public/hot

# Cache configuration for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**CRITICAL**: The `public/hot` file tells Laravel to use the Vite development server at `localhost:5173`. If this file exists in production, you'll get `ERR_CONNECTION_REFUSED` errors. Always remove it before caching.

### Step 9.2: Optimize Composer Autoloader

```bash
composer dump-autoload --optimize
```

### Step 9.3: Set Up Cron Job for Queue Worker

**Exit SSH and go to SiteGround Site Tools**:

1. Navigate to **Devs** → **Cron Jobs**
2. Click **Create Cron Job**
3. Configure:
   - **Type**: Custom
   - **Execute**: Every Minute
   - **Command**:
     ```bash
     cd ~/www/csjones.co/public_html/tengo_laravel && php artisan queue:work --stop-when-empty --max-time=3600 >> /dev/null 2>&1
     ```
4. Click **Create**

**This is essential** for Monte Carlo simulations to run in the background.

### Step 9.4: Set Up Daily Cleanup Cron

1. Create another cron job
2. Configure:
   - **Type**: Custom
   - **Execute**: Daily at 2:00 AM
   - **Command**:
     ```bash
     cd ~/www/csjones.co/public_html/tengo_laravel && php artisan queue:prune-batches && php artisan cache:prune-stale-tags >> /dev/null 2>&1
     ```
3. Click **Create**

---

## 10. Test Deployment

### Step 10.1: Test Website Loads

**Open browser** and navigate to: **https://csjones.co/tengo**

**You should see**:
- Fynla landing page
- Hero section with tagline
- Five module cards (Protection, Savings, Investment, Retirement, Estate)
- Login and Register buttons
- Proper styling (no broken CSS)

**Open browser console** (F12 → Console tab):
- Should be NO JavaScript errors
- Should be NO 404 errors for assets

### Step 10.2: Test User Registration

1. Click **Register** button
2. Fill in form:
   - Name: Test User
   - Email: test@example.com
   - Password: password123
   - Confirm Password: password123
3. Click **Register**
4. Should redirect to dashboard or onboarding

### Step 10.3: Test Admin Login

1. Navigate to: https://csjones.co/tengo/login
2. Login with:
   - Email: `admin@fps.com`
   - Password: (the password you set in Step 8.3)
3. Should access dashboard successfully
4. Try navigating to `/admin` (if admin panel exists)

### Step 10.4: Test All Modules

Navigate to each module and verify it loads:
- https://csjones.co/tengo/protection
- https://csjones.co/tengo/savings
- https://csjones.co/tengo/investment
- https://csjones.co/tengo/retirement
- https://csjones.co/tengo/estate

### Step 10.5: Test API Endpoints

**From terminal**:
```bash
# Test health endpoint
curl https://csjones.co/tengo/api/health

# Should return: {"status":"ok",...}
```

### Step 10.6: Check Server Logs

**SSH back into server**:
```bash
ssh siteground-tengo
cd ~/www/csjones.co/public_html/tengo_laravel/

# Check Laravel logs for errors
tail -50 storage/logs/laravel.log

# Should see no ERROR entries (INFO and WARNING are OK)
```

---

## 11. Troubleshooting

### Issue 1: 500 Internal Server Error

**Symptoms**: White screen or generic error page

**Solutions**:

1. **Check Laravel logs**:
   ```bash
   tail -100 storage/logs/laravel.log
   ```

2. **Verify .env file exists**:
   ```bash
   ls -la .env
   ```

3. **Check APP_KEY is set**:
   ```bash
   cat .env | grep APP_KEY
   # Should NOT be empty
   ```

4. **Check permissions**:
   ```bash
   chmod -R 775 storage/
   chmod -R 775 bootstrap/cache/
   ```

5. **Clear all caches**:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

### Issue 2: Vite Manifest Not Found

**Error**: `Vite manifest not found at: /home/.../public/build/manifest.json`

**Cause**: Manifest symlink wasn't created

**Solution**:
```bash
cd ~/www/csjones.co/public_html/tengo_laravel/public/build/

# Create symlink
ln -s .vite/manifest.json manifest.json

# Verify
ls -la manifest.json
# Should show: manifest.json -> .vite/manifest.json

# Clear view cache
cd ~/www/csjones.co/public_html/tengo_laravel/
php artisan view:clear
```

### Issue 3: Assets Not Loading (CSS/JS 404)

**Symptoms**: Page loads but no styling, browser console shows 404 for CSS/JS files

**Solutions**:

1. **Verify build directory exists**:
   ```bash
   ls -la ~/www/csjones.co/public_html/tengo_laravel/public/build/
   # Should see: .vite/, assets/, manifest.json
   ```

2. **Check symlink is correct**:
   ```bash
   ls -la ~/www/csjones.co/public_html/ | grep tengo
   # Should show: tengo -> tengo_laravel/public
   ```

3. **Verify ASSET_URL in .env**:
   ```bash
   cat .env | grep ASSET_URL
   # Should show: ASSET_URL=/tengo
   ```

4. **Check build files are accessible via symlink**:
   ```bash
   ls -la ~/www/csjones.co/public_html/tengo/build/
   # Should show same files as above (accessed through symlink)
   ```

5. **Rebuild assets locally and re-upload** (if build files are missing):
   ```bash
   # On your Mac
   cd /Users/Chris/Desktop/fpsApp/tengo
   NODE_ENV=production npm run build

   # Upload just the build directory
   scp -r -P 18765 -i ~/.ssh/siteground_ssh_key.pem \
     public/build/ \
     u163-ptanegf9edny@ssh.csjones.co:~/www/csjones.co/public_html/tengo_laravel/public/
   ```

### Issue 4: Database Connection Failed

**Error**: `SQLSTATE[HY000] [2002] Connection refused` or `Access denied`

**Solutions**:

1. **Verify database credentials in .env**:
   ```bash
   cat .env | grep DB_
   ```

2. **Test connection**:
   ```bash
   php artisan db:show
   ```

3. **Check database exists in Site Tools**:
   - Log in to Site Tools
   - Go to MySQL → Databases
   - Verify `dbow3dj6o4qnc4` exists

4. **Verify user has privileges**:
   - In Site Tools, check user is assigned to database
   - Ensure ALL PRIVILEGES are granted

### Issue 5: CSRF Token Mismatch (419 Error)

**Symptoms**: Forms submit but return "419 Page Expired"

**Solutions**:

1. **Check session configuration in .env**:
   ```bash
   cat .env | grep SESSION
   # Should show:
   # SESSION_DRIVER=file
   # SESSION_PATH=/tengo
   # SESSION_DOMAIN=csjones.co
   # SESSION_SECURE_COOKIE=true
   ```

2. **Clear session files**:
   ```bash
   rm -rf storage/framework/sessions/*
   php artisan cache:clear
   ```

3. **Verify cookies are enabled** in browser

4. **Check HTTPS is working**:
   - URL should start with `https://`
   - Browser should show padlock icon

### Issue 6: Queue Jobs Not Processing

**Symptoms**: Monte Carlo simulations stuck, jobs don't run

**Solutions**:

1. **Check cron job is configured**:
   - Log in to Site Tools → Devs → Cron Jobs
   - Verify queue worker cron exists and is active

2. **Manually process queue**:
   ```bash
   php artisan queue:work --once
   ```

3. **Check failed jobs**:
   ```bash
   php artisan queue:failed
   ```

4. **Retry failed jobs**:
   ```bash
   php artisan queue:retry all
   ```

### Issue 7: Symlink Not Working

**Symptoms**: 404 error or "File not found" when accessing site

**Solutions**:

1. **Verify symlink exists and points to correct location**:
   ```bash
   ls -la ~/www/csjones.co/public_html/ | grep tengo
   # Should show: tengo -> tengo_laravel/public
   ```

2. **If symlink is broken, recreate it**:
   ```bash
   cd ~/www/csjones.co/public_html/
   rm tengo
   ln -s tengo_laravel/public tengo
   ```

3. **Verify symlink is accessible**:
   ```bash
   ls -la tengo/
   # Should show contents of public/ directory
   ```

### Issue 8: Permission Denied Errors

**Symptoms**: "Permission denied" in logs, can't write to storage/logs

**Solutions**:

```bash
cd ~/www/csjones.co/public_html/tengo_laravel/

# Set correct permissions
chmod -R 755 .
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/

# Make sure storage subdirectories are writable
find storage -type f -exec chmod 664 {} \;
find storage -type d -exec chmod 775 {} \;
find bootstrap/cache -type f -exec chmod 664 {} \;
find bootstrap/cache -type d -exec chmod 775 {} \;
```

---

## 12. Maintenance

### Regular Maintenance Tasks

**Weekly**:
```bash
# Check logs for errors
tail -100 storage/logs/laravel.log

# Check disk space
df -h
```

**Monthly**:
```bash
# Clear old log files
php artisan log:clear

# Prune stale cache
php artisan cache:prune-stale-tags

# Optimize database
php artisan db:prune
```

### Updating to New Version

1. **Create backup** (via Admin panel or SSH):
   ```bash
   # Backup database
   cd ~
   mysqldump -u uixybijdvk3yv -p dbow3dj6o4qnc4 > tengo_backup_$(date +%Y%m%d).sql

   # Backup files
   cd ~/www/csjones.co/public_html/
   tar -czf tengo_backup_$(date +%Y%m%d).tar.gz tengo_laravel/
   ```

2. **Build new version locally**:
   ```bash
   cd /Users/Chris/Desktop/fpsApp/tengo
   git pull origin main
   NODE_ENV=production npm run build
   composer install --optimize-autoloader --no-dev
   ```

3. **Create new deployment archive** (follow Step 2.3)

4. **Upload and extract new files**:
   ```bash
   # Upload tarball
   scp tengo-deploy.tar.gz siteground-tengo:~/

   # SSH in
   ssh siteground-tengo

   # Extract (overwrites existing files)
   cd ~/www/csjones.co/public_html/
   tar -xzf ~/tengo-deploy.tar.gz -C tengo_laravel/ --strip-components=1
   ```

5. **Run migrations** (for database changes):
   ```bash
   cd ~/www/csjones.co/public_html/tengo_laravel/
   php artisan migrate --force
   ```

6. **Clear and rebuild caches**:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

7. **Test application**

### Database Backup Schedule

**Set up automated backups via cron**:

1. In Site Tools → Devs → Cron Jobs
2. Create new cron job:
   - **Execute**: Daily at 3:00 AM
   - **Command**:
     ```bash
     mysqldump -u uixybijdvk3yv -p'YOUR_DB_PASSWORD' dbow3dj6o4qnc4 | gzip > ~/backups/tengo_$(date +\%Y\%m\%d).sql.gz
     ```

---

## Deployment Complete!

Your Fynla application should now be live at: **https://csjones.co/tengo**

### Final Checklist

- [ ] Website loads at https://csjones.co/tengo
- [ ] User registration works
- [ ] Admin login works
- [ ] All 5 modules accessible
- [ ] Assets loading correctly (CSS/JS)
- [ ] Database has 40+ tables
- [ ] Queue worker cron job running
- [ ] Daily cleanup cron job running
- [ ] Backup created
- [ ] Admin credentials saved securely

### Support Resources

- **SiteGround Support**: https://my.siteground.com/support
- **Laravel Docs**: https://laravel.com/docs/10.x/deployment
- **Project Docs**: See `CLAUDE.md` and `README.md`

---

## Appendix: Common Deployment Issues & Solutions

This section documents issues encountered during actual deployment and their solutions.

### Issue 1: API Calls Going to localhost:8000

**Symptoms**:
- Registration/login fails with "Network error. Please check your connection."
- Browser console shows: `POST http://localhost:8000/api/auth/register net::ERR_CONNECTION_REFUSED`

**Cause**: Frontend was built WITHOUT `.env.production` file, so Vite used the development `.env` which has `VITE_API_BASE_URL=http://localhost:8000`

**Solution**:
1. Create `.env.production` file BEFORE building:
   ```bash
   cat > .env.production << 'EOF'
   VITE_APP_NAME="Fynla - Financial Planning System"
   VITE_API_BASE_URL=https://csjones.co/tengo
   EOF
   ```
2. Rebuild: `NODE_ENV=production npm run build`
3. Verify: `grep -r "csjones.co/tengo" public/build/assets/*.js` (should return results)
4. Re-deploy the `public/build/` directory

**Prevention**: ALWAYS create `.env.production` before Step 2.2 (Build Production Assets)

### Issue 2: Vite Dev Server Errors (127.0.0.1:5173)

**Symptoms**:
- Website loads but with broken CSS/JS
- Browser console shows: `GET http://127.0.0.1:5173/@vite/client net::ERR_CONNECTION_REFUSED`

**Cause**: `public/hot` file exists on server, telling Laravel to use Vite dev server

**Solution**:
```bash
# On server
cd ~/www/csjones.co/public_html/tengo_laravel/
rm -f public/hot
php artisan config:clear
php artisan config:cache
php artisan view:clear
php artisan view:cache
```

**Prevention**: ALWAYS remove `public/hot` file in Step 9.1 before caching configs

### Issue 3: "This cache store does not support tagging"

**Symptoms**:
- Dashboard loads but shows 500 errors
- Error message: "Failed to analyze protection coverage: This cache store does not support tagging."

**Cause**: `CACHE_DRIVER` is set to `file` or `array`, which don't support cache tagging. Protection module requires tagging.

**Solution**:
1. Check if Memcached is available: `php -m | grep memcached`
2. If available, update `.env`:
   ```ini
   CACHE_DRIVER=memcached
   MEMCACHED_HOST=127.0.0.1
   MEMCACHED_PORT=11211
   ```
3. Clear and rebuild caches:
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

**Prevention**: Check Memcached availability in Step 7.2 and configure correctly in Step 7.3

### Issue 4: Missing storage/framework/sessions Directory

**Symptoms**:
- Website shows 500 error immediately on load
- Laravel log: `file_put_contents(...sessions/...): Failed to open stream: No such file or directory`

**Cause**: Tarball extraction didn't include empty `storage/framework/sessions/` directory

**Solution**:
```bash
mkdir -p storage/framework/sessions
chmod -R 775 storage/framework/sessions
```

**Prevention**: Create missing directories in Step 9.1 before caching

### Issue 5: Missing storage/framework/views Directory

**Symptoms**:
- `php artisan view:cache` fails with "View path not found"

**Cause**: Tarball extraction didn't include empty `storage/framework/views/` directory

**Solution**:
```bash
mkdir -p storage/framework/views
chmod -R 775 storage/framework/views
```

**Prevention**: Create missing directories in Step 9.1 before caching

### Issue 6: Enum Migration Failure

**Symptoms**:
- `php artisan migrate` fails with: "Unknown column type 'enum' requested"
- Migration: `2025_10_23_154600_update_assets_ownership_type_to_individual`

**Cause**: Doctrine DBAL doesn't recognize enum columns, but database already has correct structure from previous deployment

**Solution**:
```bash
# Manually mark migration as run
mysql -u uixybijdvk3yv -p dbow3dj6o4qnc4 -e "INSERT INTO migrations (migration, batch) VALUES ('2025_10_23_154600_update_assets_ownership_type_to_individual', 1);"

# Verify
php artisan migrate:status | grep "2025_10_23_154600"
```

**Prevention**: If database already has correct structure, manually mark problematic enum migrations as run

---

**Deployment Guide Version**: 3.0
**Fynla Version**: v0.1.2.13
**Last Updated**: October 30, 2025
**Tested On**: SiteGround UK Servers
**Deployment Date**: October 30, 2025 (Successful)

🤖 **Generated with [Claude Code](https://claude.com/claude-code)**
