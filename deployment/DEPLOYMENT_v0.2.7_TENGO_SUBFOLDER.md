# Fynla v0.2.7 - Production Deployment to /tengo Subfolder

**Date**: November 12, 2025
**Target**: https://csjones.co/tengo
**Server**: SiteGround Shared Hosting
**Package**: `tengo-v0.2.7-deployment.tar.gz` (51MB)

---

## Table of Contents

1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [Step-by-Step Deployment Instructions](#step-by-step-deployment-instructions)
3. [Post-Deployment Configuration](#post-deployment-configuration)
4. [Verification & Testing](#verification--testing)
5. [Troubleshooting Common Issues](#troubleshooting-common-issues)
6. [Rollback Procedure](#rollback-procedure)

---

## Pre-Deployment Checklist

### Local Environment ✅

- [x] Version updated to v0.2.7 in Footer.vue
- [x] vite.config.js configured with base: '/tengo/build/'
- [x] Production build completed (`npm run build`)
- [x] Deployment package created (`tengo-v0.2.7-deployment.tar.gz`)
- [x] All changes committed to git repository
- [x] Changes pushed to remote repository

### Server Credentials

- **SSH Host**: `ssh.csjones.co` (uk71.siteground.eu)
- **SSH Port**: `18765`
- **SSH User**: `u163-ptanegf9edny`
- **Database**: `dbow3dj6o4qnc4`
- **DB User**: `uixybijdvk3yv`
- **DB Password**: `PixieRebecca2020`

### Important Notes

⚠️ **CRITICAL**: This deployment is to the `/tengo` SUBFOLDER, not root directory
⚠️ **BACKUP**: Always backup the current production before deploying
⚠️ **TESTING**: Test each step before proceeding to the next

---

## Step-by-Step Deployment Instructions

### Phase 1: Connect to Server and Prepare

#### Step 1.1: SSH Connection

Open a separate terminal window and connect:

```bash
ssh -p 18765 u163-ptanegf9edny@ssh.csjones.co
```

**Expected Output**:
```
Welcome to SiteGround
Last login: ...
```

#### Step 1.2: Navigate to Web Root

```bash
cd ~/www/csjones.co/public_html/
pwd
```

**Expected Output**:
```
/home/customer/www/csjones.co/public_html
```

#### Step 1.3: Check Current State

```bash
# Check if tengo directory exists
ls -la tengo/

# Check disk space
df -h .
```

**Expected**: Should see existing `tengo/` directory if upgrading, or should be ready to create new directory

#### Step 1.4: Backup Current Production (If Exists)

**CRITICAL**: Only run if upgrading existing installation

```bash
# Create backup directory with timestamp
BACKUP_DIR=~/backups/tengo-$(date +%Y%m%d-%H%M%S)
mkdir -p $BACKUP_DIR

# Backup current installation
cp -r tengo/ $BACKUP_DIR/
echo "Backup created at: $BACKUP_DIR"

# Backup database
cd tengo/
php artisan db:dump --path="$BACKUP_DIR/database-backup.sql"
cd ..
```

**Verification**:
```bash
ls -lh $BACKUP_DIR/
```

**Expected Output**: Directory with tengo/ folder and database-backup.sql file

---

### Phase 2: Upload and Extract Deployment Package

#### Step 2.1: Upload Deployment Package

Using **separate SFTP client** or **SiteGround File Manager**:

1. Navigate to: `~/www/csjones.co/public_html/`
2. Upload file: `tengo-v0.2.7-deployment.tar.gz`
3. Verify upload completed successfully

**Alternative using scp (from your Mac)**:
```bash
# Run this on YOUR LOCAL MACHINE (not server)
cd /Users/Chris/Desktop/fpsApp/tengo/
scp -P 18765 tengo-v0.2.7-deployment.tar.gz u163-ptanegf9edny@ssh.csjones.co:~/www/csjones.co/public_html/
```

#### Step 2.2: Verify Upload

**On server**:
```bash
cd ~/www/csjones.co/public_html/
ls -lh tengo-v0.2.7-deployment.tar.gz
```

**Expected Output**:
```
-rw-r--r-- 1 user user 51M Nov 12 15:04 tengo-v0.2.7-deployment.tar.gz
```

#### Step 2.3: Remove Old Installation (If Upgrading)

**WARNING**: Only run if backup was completed successfully in Step 1.4

```bash
# Confirm backup exists first
ls -la ~/backups/tengo-*/

# Remove old installation
rm -rf tengo/
```

#### Step 2.4: Extract Deployment Package

```bash
# Create tengo directory
mkdir tengo
cd tengo/

# Extract deployment package
tar -xzf ../tengo-v0.2.7-deployment.tar.gz

# Verify extraction
ls -la
```

**Expected Output**: Should see Laravel directory structure:
```
app/
artisan
bootstrap/
composer.json
config/
database/
public/
resources/
routes/
storage/
vendor/
.env.example
```

#### Step 2.5: Clean Up

```bash
cd ..
rm tengo-v0.2.7-deployment.tar.gz
```

---

### Phase 3: Create Required Directories

#### Step 3.1: Create Storage Directories

**CRITICAL**: These directories are often missing in archives

```bash
cd tengo/

# Create all required storage directories
mkdir -p storage/framework/sessions
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/views
mkdir -p storage/framework/testing
mkdir -p storage/logs
mkdir -p storage/app/public

# Create backup directory
mkdir -p storage/app/backups
```

#### Step 3.2: Set Correct Permissions

```bash
# Set permissions for storage directories
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/

# Verify permissions
ls -la storage/
```

**Expected Output**: All directories should show `drwxrwxr-x`

---

### Phase 4: Environment Configuration

#### Step 4.1: Create .env File

**Option A: Copy from existing backup (if upgrading)**:
```bash
# If you have a backup with existing .env
cp ~/backups/tengo-YYYYMMDD-HHMMSS/tengo/.env .env
```

**Option B: Create new .env file**:
```bash
cp .env.example .env
nano .env
```

#### Step 4.2: Configure .env File

Edit the `.env` file with these **EXACT** values:

```env
APP_NAME="Fynla - Financial Planning System"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://csjones.co/tengo

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=dbow3dj6o4qnc4
DB_USERNAME=uixybijdvk3yv
DB_PASSWORD=PixieRebecca2020

BROADCAST_DRIVER=log
CACHE_DRIVER=array
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_PATH=/tengo

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_APP_NAME="${APP_NAME}"
VITE_API_BASE_URL="${APP_URL}"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"

ASSET_URL=https://csjones.co/tengo
```

**Save and exit**: `Ctrl+O`, `Enter`, `Ctrl+X` (in nano)

#### Step 4.3: Generate Application Key

```bash
php artisan key:generate
```

**Expected Output**:
```
Application key set successfully.
```

#### Step 4.4: Verify .env Configuration

```bash
# Check APP_KEY was generated
grep APP_KEY .env

# Check database credentials
grep DB_ .env | head -5

# Check URLs
grep URL .env
```

**Expected**:
- APP_KEY should have a value like `base64:...`
- All URLs should contain `csjones.co/tengo`
- SESSION_PATH should be `/tengo`

---

### Phase 5: Database Setup

#### Step 5.1: Test Database Connection

```bash
php artisan db:show
```

**Expected Output**:
```
MySQL
Database: dbow3dj6o4qnc4
Host: localhost
Port: 3306
Username: uixybijdvk3yv
```

**If connection fails**: Double-check database credentials in `.env`

#### Step 5.2: Check if Database is Empty or Has Data

```bash
# List all tables
mysql -u uixybijdvk3yv -p'PixieRebecca2020' dbow3dj6o4qnc4 -e "SHOW TABLES;"
```

**If tables exist**:
- Skip to Step 5.4 (don't run migrations again)
- You're upgrading an existing installation

**If no tables exist**:
- Continue to Step 5.3 (run migrations and seeds)
- This is a fresh installation

#### Step 5.3: Run Migrations and Seeds (FRESH INSTALL ONLY)

⚠️ **WARNING**: Only run these if database is EMPTY

```bash
# Run all migrations
php artisan migrate --force

# Seed tax configuration data
php artisan db:seed --class=TaxConfigurationSeeder --force

# Seed actuarial life tables
php artisan db:seed --class=ActuarialLifeTablesSeeder --force
```

**Expected Output**:
```
Migration table created successfully.
Migrating: 2014_10_12_000000_create_users_table
Migrated:  2014_10_12_000000_create_users_table (45.23ms)
...
Database seeding completed successfully.
```

#### Step 5.4: Create Admin Account (FRESH INSTALL ONLY)

```bash
php artisan tinker
```

**In Tinker**, run:
```php
$admin = new App\Models\User();
$admin->name = 'Admin User';
$admin->email = 'admin@fps.com';
$admin->password = bcrypt('admin123456');
$admin->email_verified_at = now();
$admin->is_admin = true;
$admin->save();
exit
```

**Expected Output**:
```
=> App\Models\User {#...
     id: 1,
     name: "Admin User",
     email: "admin@fps.com",
     ...
   }
```

---

### Phase 6: Configure .htaccess Files

#### Step 6.1: Create Root .htaccess in /tengo/

**CRITICAL**: This redirects all requests to the public/ subdirectory

```bash
cd ~/www/csjones.co/public_html/tengo/
nano .htaccess
```

**Paste this EXACT content**:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /tengo/

    # Redirect all requests to public subdirectory
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ public/$1 [L,QSA]
</IfModule>
```

**Save and exit**: `Ctrl+O`, `Enter`, `Ctrl+X`

#### Step 6.2: Verify public/.htaccess Exists

```bash
cat public/.htaccess
```

**Expected**: Standard Laravel .htaccess file should already exist from deployment package

**If missing**, create it:
```bash
nano public/.htaccess
```

**Paste**:
```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

---

### Phase 7: Vite Assets Configuration (CRITICAL)

#### Step 7.1: Remove Hot Files

**CRITICAL**: These files cause production to load dev server

```bash
cd ~/www/csjones.co/public_html/tengo/

# Remove all hot files
rm -f hot
rm -f public/hot
rm -f public/build/hot

# Verify they're gone
ls -la hot 2>/dev/null || echo "✓ hot file not found (correct)"
ls -la public/hot 2>/dev/null || echo "✓ public/hot not found (correct)"
```

**Expected**: All commands should show "not found" - this is correct

#### Step 7.2: Create Vite Manifest Symlink

**CRITICAL**: Laravel expects manifest at different path than Vite creates it

```bash
cd public/build/

# Verify .vite/manifest.json exists
ls -la .vite/manifest.json
```

**Expected Output**:
```
-rw-r--r-- 1 user user 21K Nov 12 15:04 .vite/manifest.json
```

**If file exists**, create symlink:
```bash
ln -sf .vite/manifest.json manifest.json

# Verify symlink
ls -la manifest.json
```

**Expected Output**:
```
lrwxrwxrwx 1 user user 19 Nov 12 15:20 manifest.json -> .vite/manifest.json
```

#### Step 7.3: Verify Build Assets

```bash
# Check manifest is readable
cat manifest.json | head -20

# Check assets directory exists
ls -la assets/ | head -10

# Return to tengo root
cd ~/www/csjones.co/public_html/tengo/
```

**Expected**: Should see JSON content from manifest and numerous .js/.css files in assets/

---

### Phase 8: Laravel Optimization

#### Step 8.1: Clear All Caches

```bash
cd ~/www/csjones.co/public_html/tengo/

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Clear compiled class files
php artisan clear-compiled
```

**Expected Output**: Each command should return `Configuration/Cache/Route/View cache cleared successfully.`

#### Step 8.2: Optimize for Production

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize Composer autoloader
composer dump-autoload --optimize --no-dev
```

**Expected**: Each command completes successfully

**NOTE**: If `composer dump-autoload` hangs or fails, skip it - the deployment already has optimized autoloader

---

### Phase 9: Final Configuration

#### Step 9.1: Verify File Permissions

```bash
# Check storage permissions
ls -la storage/

# Check bootstrap/cache permissions
ls -la bootstrap/cache/

# Fix if needed
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
```

#### Step 9.2: Disable Debug Mode

```bash
# Edit .env
nano .env

# Change this line:
APP_DEBUG=false

# Save: Ctrl+O, Enter, Ctrl+X

# Clear config cache
php artisan config:clear
php artisan config:cache
```

#### Step 9.3: Verify Configuration

```bash
# Check all critical config values
php artisan tinker --execute="
echo 'APP_URL: ' . config('app.url') . PHP_EOL;
echo 'APP_DEBUG: ' . (config('app.debug') ? 'true' : 'false') . PHP_EOL;
echo 'DB_DATABASE: ' . config('database.connections.mysql.database') . PHP_EOL;
echo 'CACHE_DRIVER: ' . config('cache.default') . PHP_EOL;
echo 'SESSION_PATH: ' . config('session.path') . PHP_EOL;
exit;
"
```

**Expected Output**:
```
APP_URL: https://csjones.co/tengo
APP_DEBUG: false
DB_DATABASE: dbow3dj6o4qnc4
CACHE_DRIVER: array
SESSION_PATH: /tengo
```

---

## Post-Deployment Configuration

### Check Application Status

#### Test 1: Landing Page

Open browser and navigate to:
```
https://csjones.co/tengo
```

**Expected**: Should see Fynla landing page, no errors

**If blank page**: Check browser console for errors
**If 500 error**: Check `storage/logs/laravel.log`
**If 404 error**: Check .htaccess configuration

#### Test 2: Admin Login

Navigate to:
```
https://csjones.co/tengo/login
```

**Expected**: Should see login form

**Login with**:
- Email: `admin@fps.com`
- Password: `admin123456`

**Expected**: Should redirect to dashboard after successful login

#### Test 3: Dashboard

After login, check:
- [ ] Dashboard loads without errors
- [ ] No console errors in browser DevTools
- [ ] Assets loading correctly (check Network tab)
- [ ] Navigation works
- [ ] Module cards display correctly

#### Test 4: API Endpoints

Open browser DevTools → Network tab

Navigate through application:
- Dashboard
- User Profile
- Any module (Protection, Savings, etc.)

**Expected**: All API calls should return 200 status codes

**If 405 errors**: Check .htaccess configuration
**If 500 errors**: Check Laravel logs

---

## Verification & Testing

### Comprehensive Test Checklist

Run through each of these tests:

#### Authentication Tests
- [ ] Admin login works
- [ ] User registration works (create test account)
- [ ] Logout works
- [ ] Password reset flow works

#### Module Tests
- [ ] Protection module loads
- [ ] Savings module loads
- [ ] Investment module loads
- [ ] Retirement module loads
- [ ] Estate module loads
- [ ] Net Worth module loads

#### Data Entry Tests
- [ ] Can create protection policy
- [ ] Can create savings account
- [ ] Can create investment account
- [ ] Can enter property details
- [ ] Can run IHT calculation

#### Asset Loading Tests
- [ ] CSS styles applied correctly
- [ ] JavaScript functioning
- [ ] No 404 errors in console
- [ ] All images loading
- [ ] ApexCharts rendering (if on dashboard)

### Check Logs for Errors

```bash
cd ~/www/csjones.co/public_html/tengo/

# View latest errors
tail -100 storage/logs/laravel.log

# Check for today's errors
grep "$(date +%Y-%m-%d)" storage/logs/laravel.log | grep ERROR
```

**Expected**: No errors or only minor notices

---

## Troubleshooting Common Issues

### Issue 1: Blank White Page

**Symptoms**: Browser shows blank page, no content

**Diagnosis**:
```bash
# Check browser console for errors
# Common errors:
# - "Failed to load module" → Asset path issue
# - CORS errors → Hot file present or wrong base URL
```

**Solutions**:

**A) Hot file exists**:
```bash
rm -f public/hot public/build/hot hot
php artisan view:clear
```

**B) Wrong asset base URL**:
```bash
grep ASSET_URL .env
# Should be: ASSET_URL=https://csjones.co/tengo

# If wrong, fix it:
nano .env
# Change ASSET_URL
php artisan config:clear
php artisan config:cache
```

---

### Issue 2: 500 Internal Server Error

**Symptoms**: Any page returns "500 Internal Server Error"

**Diagnosis**:
```bash
# Enable debug mode temporarily
nano .env
# Change: APP_DEBUG=true
php artisan config:clear

# Reload page in browser
# Read error message shown

# Check Laravel logs
tail -50 storage/logs/laravel.log
```

**Common Causes**:

**A) Missing APP_KEY**:
```bash
grep APP_KEY .env
# Should have: APP_KEY=base64:...

# If empty:
php artisan key:generate
php artisan config:clear
```

**B) Missing storage directories**:
```bash
mkdir -p storage/framework/sessions
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/views
mkdir -p storage/logs
chmod -R 775 storage/
```

**C) Wrong file permissions**:
```bash
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
```

**D) Database connection failed**:
```bash
php artisan db:show
# If fails, check DB credentials in .env
```

**After fixing**, disable debug:
```bash
nano .env
# Change: APP_DEBUG=false
php artisan config:clear
php artisan config:cache
```

---

### Issue 3: Assets Return 404 Errors

**Symptoms**: Browser console shows 404 errors for `/build/assets/app-*.js`

**Diagnosis**:
```bash
# Check if build assets exist
ls -la public/build/assets/ | head -10

# Check if manifest exists
ls -la public/build/manifest.json
```

**Solutions**:

**A) Manifest missing or wrong location**:
```bash
cd public/build/

# Check if .vite/manifest.json exists
ls -la .vite/manifest.json

# Create symlink
ln -sf .vite/manifest.json manifest.json

# Verify
ls -la manifest.json
```

**B) Wrong base path in Vite config**:
```bash
# This should already be correct from build
# Verify base URL in generated assets:
grep -r "tengo/build" public/build/assets/*.js | head -5
```

**C) .htaccess not redirecting correctly**:
```bash
# Verify .htaccess in /tengo/ directory
cat .htaccess | grep RewriteRule

# Should show: RewriteRule ^(.*)$ public/$1 [L,QSA]
```

---

### Issue 4: "This cache store does not support tagging"

**Symptoms**: Error when creating Protection policies or using certain features

**Cause**: Wrong CACHE_DRIVER in .env

**Solution**:
```bash
nano .env

# Change this line:
CACHE_DRIVER=array

# NOT: file, database, or redis (they don't support tags on shared hosting)

# Save and clear cache
php artisan config:clear
php artisan cache:clear
php artisan config:cache
```

---

### Issue 5: POST Requests Failing (405 Error)

**Symptoms**: Forms submit but return "Method Not Allowed"

**Diagnosis**:
```bash
# Check routes exist
php artisan route:list | grep POST

# Check .htaccess preserves method
cat .htaccess
```

**Solution**:

Verify `/tengo/.htaccess` has correct rewrite rule:
```apache
RewriteRule ^(.*)$ public/$1 [L,QSA]
```

**NOT**:
```apache
RewriteRule ^(.*)$ public/$1 [L,R=301]  # ← R=301 converts POST to GET!
```

If wrong, fix it:
```bash
nano .htaccess
# Remove R=301, keep only [L,QSA]
```

---

### Issue 6: Sessions Not Persisting

**Symptoms**: Login succeeds but immediately logged out, or CSRF token errors

**Diagnosis**:
```bash
# Check session configuration
grep SESSION .env

# Check sessions directory exists
ls -la storage/framework/sessions/
```

**Solutions**:

**A) SESSION_PATH wrong**:
```bash
nano .env

# Should be:
SESSION_PATH=/tengo

# Save and cache config
php artisan config:clear
php artisan config:cache
```

**B) Sessions directory missing**:
```bash
mkdir -p storage/framework/sessions
chmod 775 storage/framework/sessions
```

**C) Sessions directory full**:
```bash
# Clear old sessions
rm storage/framework/sessions/*
```

---

### Issue 7: Database Connection Refused

**Symptoms**: "SQLSTATE[HY000] [2002] Connection refused"

**Solutions**:

**A) Wrong host**:
```bash
nano .env

# Should be:
DB_HOST=localhost

# NOT: 127.0.0.1 (may not work on some shared hosts)
```

**B) Wrong credentials**:
```bash
# Verify credentials with direct connection
mysql -u uixybijdvk3yv -p'PixieRebecca2020' dbow3dj6o4qnc4 -e "SELECT 1;"

# If fails, credentials are wrong - check SiteGround control panel
```

**C) Database server down**:
```bash
# Contact SiteGround support
```

---

## Rollback Procedure

If deployment fails and you need to rollback:

### Step 1: Stop Using New Version

```bash
cd ~/www/csjones.co/public_html/

# Rename failed deployment
mv tengo tengo-failed-v0.2.7
```

### Step 2: Restore from Backup

```bash
# Find your backup
ls -la ~/backups/

# Restore from backup
BACKUP_DIR=~/backups/tengo-YYYYMMDD-HHMMSS  # Use actual timestamp
cp -r $BACKUP_DIR/tengo tengo/
```

### Step 3: Restore Database (If Needed)

```bash
cd tengo/
php artisan db:restore --path="$BACKUP_DIR/database-backup.sql"

# Or manually:
mysql -u uixybijdvk3yv -p'PixieRebecca2020' dbow3dj6o4qnc4 < $BACKUP_DIR/database-backup.sql
```

### Step 4: Clear Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Step 5: Verify Application Works

Test the application at https://csjones.co/tengo

---

## Post-Deployment Tasks

### Immediate (Required)

- [ ] Change admin password from default
- [ ] Test all critical user flows
- [ ] Monitor logs for errors
- [ ] Verify backup exists

### Short-Term (Recommended)

- [ ] Setup monitoring/alerts
- [ ] Configure proper email settings
- [ ] Setup scheduled database backups
- [ ] Document any custom configuration

### Maintenance Commands

```bash
# Clear all caches (run after config changes)
php artisan config:clear && php artisan cache:clear && php artisan view:clear

# Check disk space
df -h .

# Check database size
mysql -u uixybijdvk3yv -p'PixieRebecca2020' dbow3dj6o4qnc4 -e "
SELECT
  table_schema AS 'Database',
  ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.tables
WHERE table_schema='dbow3dj6o4qnc4'
GROUP BY table_schema;"

# View application logs
tail -50 storage/logs/laravel.log

# Clear old logs (if needed)
> storage/logs/laravel.log
```

---

## Support Information

### Key URLs

- **Application**: https://csjones.co/tengo
- **Login**: https://csjones.co/tengo/login
- **Admin Panel**: https://csjones.co/tengo/admin

### Admin Credentials

- **Email**: admin@fps.com
- **Password**: admin123456 (⚠️ CHANGE THIS IMMEDIATELY)

### Server Information

- **SSH**: `ssh -p 18765 u163-ptanegf9edny@ssh.csjones.co`
- **Document Root**: `~/www/csjones.co/public_html/tengo/`
- **Logs**: `~/www/csjones.co/public_html/tengo/storage/logs/laravel.log`
- **Backups**: `~/backups/`

### Critical Files

- **Application .env**: `~/www/csjones.co/public_html/tengo/.env`
- **Root .htaccess**: `~/www/csjones.co/public_html/tengo/.htaccess`
- **Public .htaccess**: `~/www/csjones.co/public_html/tengo/public/.htaccess`
- **Vite manifest**: `~/www/csjones.co/public_html/tengo/public/build/manifest.json` (symlink)

---

## Version Information

- **Application Version**: v0.2.7
- **Laravel Version**: 10.x
- **PHP Version**: 8.2+
- **MySQL Version**: 8.0+
- **Node Version**: 18.x (for local builds)
- **Deployment Date**: November 12, 2025

---

## Deployment Checklist Summary

Use this quick checklist during deployment:

- [ ] SSH connected to server
- [ ] Navigated to correct directory
- [ ] Backup created (if upgrading)
- [ ] Deployment package uploaded
- [ ] Old installation removed
- [ ] New package extracted
- [ ] Storage directories created
- [ ] Permissions set (775 for storage)
- [ ] .env file configured
- [ ] APP_KEY generated
- [ ] Database connection tested
- [ ] Migrations run (if fresh install)
- [ ] Admin account created (if fresh install)
- [ ] .htaccess files configured
- [ ] Hot files removed
- [ ] Manifest symlink created
- [ ] All caches cleared
- [ ] Production optimizations run
- [ ] Debug mode disabled
- [ ] Landing page loads
- [ ] Admin login works
- [ ] Dashboard displays correctly
- [ ] No console errors
- [ ] All modules accessible

---

**Deployment Guide Version**: 1.0
**Last Updated**: November 12, 2025
**For**: Fynla v0.2.7 deployment to /tengo subfolder

🤖 **Generated with [Claude Code](https://claude.com/claude-code)**
