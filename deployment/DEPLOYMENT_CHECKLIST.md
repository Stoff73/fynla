# Fynla v0.2.7 - SiteGround Deployment Checklist

**Target URL**: https://csjones.co/tengo
**Deployment Date**: _________________
**Deployed By**: _________________

---

## Pre-Deployment Phase

### Local Environment Verification

- [ ] **Git Status Clean**
  - [ ] All changes committed
  - [ ] On `main` branch
  - [ ] No uncommitted or untracked critical files
  ```bash
  git status
  git branch --show-current
  ```

- [ ] **Fix Vite Manifest Location** (CRITICAL - prevents 500 errors)
  ```bash
  # Check current config
  cat vite.config.js | grep -A 10 "build:"

  # Must show: manifest: 'manifest.json'
  # NOT: manifest: true
  ```
  - [ ] vite.config.js has `manifest: 'manifest.json'` (not `manifest: true`)
  - [ ] Stop any running dev servers: `pkill -f "vite"`
  - [ ] Remove hot file: `rm -f public/hot`

- [ ] **Production Build Test**
  - [ ] Clean previous builds: `rm -rf public/build/*`
  - [ ] Run production build: `NODE_ENV=production npm run build`
  - [ ] Build completes successfully (~15-20 seconds)
  - [ ] `public/build/manifest.json` exists **at build root** (NOT in .vite/ subdirectory)
  - [ ] `public/build/assets/` contains 100+ files
  ```bash
  ls -lh public/build/manifest.json
  # Must succeed (manifest at root)

  ls -lh public/build/.vite/manifest.json 2>/dev/null && echo "❌ WRONG LOCATION" || echo "✅ Correct"
  # Should show "✅ Correct" (manifest NOT in .vite/)

  ls public/build/assets/ | wc -l
  # Should show 100+
  ```

- [ ] **Migration Review**
  - [ ] Review all pending migrations
  - [ ] No destructive operations (dropColumn, dropTable)
  - [ ] All migrations have `down()` methods
  - [ ] Migrations tested in local environment
  ```bash
  php artisan migrate:status
  ls -la database/migrations/
  ```

- [ ] **Environment Variable Check**
  - [ ] No production env vars in local shell
  - [ ] `.env` is for development (APP_ENV=local)
  - [ ] `.env.production.example` reviewed and ready
  ```bash
  printenv | grep -E "^APP_|^DB_"
  ```

- [ ] **Dependency Audit**
  - [ ] `composer.json` reviewed
  - [ ] `package.json` reviewed
  - [ ] No risky or outdated dependencies
  - [ ] PHP version requirement met (8.1+)

- [ ] **Create Deployment Package**

  **⚠️ CRITICAL**: Never include `.env.production` or `.env.development` (contain credentials!)

  ```bash
  cd /Users/Chris/Desktop/fpsApp/tengo

  # Remove old archive first
  rm -f tengo-v0.2.7-deployment.tar.gz

  # Create secure deployment package
  # COPYFILE_DISABLE=1 prevents macOS extended attribute files (._* files)
  COPYFILE_DISABLE=1 tar -czf tengo-v0.2.7-deployment.tar.gz \
    --exclude='tengo-v0.2.7-deployment.tar.gz' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='.git' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='.env' \
    --exclude='.env.local' \
    --exclude='.env.production' \
    --exclude='.env.development' \
    --exclude='public/hot' \
    --exclude='.claude' \
    --exclude='*.old' \
    --exclude='*.log' \
    --exclude='.DS_Store' \
    --exclude='public/.htaccess.laravel-default' \
    .
  ```

  - [ ] Old archive removed before creating new one
  - [ ] Archive created successfully
  - [ ] Archive size reasonable (~2-3 MB)

  ```bash
  ls -lh tengo-v0.2.7-deployment.tar.gz
  ```

- [ ] **SECURITY CHECK: Verify No Credentials in Archive**
  ```bash
  # Check .env files in archive
  tar -tzf tengo-v0.2.7-deployment.tar.gz | grep "\.env"

  # Should ONLY show:
  # ./.env.example
  # ./.env.production.example
  ```

  - [ ] Only `.env.example` and `.env.production.example` present
  - [ ] **CRITICAL**: NO `.env.production` or `.env.development` in archive
  - [ ] If credentials found: STOP and recreate archive

- [ ] **Verify Archive Contents**
  ```bash
  # Verify production .htaccess included
  tar -tzf tengo-v0.2.7-deployment.tar.gz | grep "public/.htaccess"
  # Should show: ./public/.htaccess

  # Verify build assets included
  tar -tzf tengo-v0.2.7-deployment.tar.gz | grep "public/build" | head -5
  ```

  - [ ] Production `.htaccess` present (with RewriteBase /tengo/)
  - [ ] Built assets in `public/build/` directory
  - [ ] No `node_modules` or `vendor` directories

---

## SiteGround Account Setup

### Access & Configuration

- [ ] **Login to SiteGround**
  - [ ] URL: https://my.siteground.com
  - [ ] Navigate to Site Tools for csjones.co

- [ ] **PHP Version Configuration**
  - [ ] Go to: Site Tools > Devs > PHP Manager
  - [ ] PHP version set to **8.2** or **8.3**
  - [ ] Required extensions enabled:
    - [ ] mbstring
    - [ ] xml
    - [ ] curl
    - [ ] zip
    - [ ] pdo_mysql
    - [ ] tokenizer
    - [ ] json
    - [ ] bcmath
    - [ ] ctype
    - [ ] fileinfo

- [ ] **SSH Access Enabled**
  - [ ] Go to: Site Tools > Devs > SSH Keys Manager
  - [ ] SSH key generated/uploaded (if needed)
  - [ ] SSH access confirmed
  ```bash
  ssh [username]@csjones.co -p18765
  ```

---

## Database Setup

- [ ] **Create MySQL Database**
  - [ ] Go to: Site Tools > MySQL > Databases
  - [ ] Database name: `tengo_production` (will be prefixed)
  - [ ] Collation: `utf8mb4_unicode_ci`
  - [ ] Note full database name (with prefix): `___________________`

- [ ] **Create Database User**
  - [ ] Go to: Site Tools > MySQL > Users
  - [ ] Username: `tengo_user` (will be prefixed)
  - [ ] Strong password generated (16+ characters)
  - [ ] Password saved securely: `___________________`
  - [ ] Note full username (with prefix): `___________________`

- [ ] **Grant User Permissions**
  - [ ] Go to: Site Tools > MySQL > Databases > Manage Users
  - [ ] User `tengo_user` added to `tengo_production`
  - [ ] **ALL PRIVILEGES** granted

- [ ] **Database Credentials Documented**
  ```
  DB_HOST=localhost
  DB_PORT=3306
  DB_DATABASE=[prefixed_database_name]
  DB_USERNAME=[prefixed_username]
  DB_PASSWORD=[secure_password]
  ```

---

## File Upload & Structure

- [ ] **Upload Application Archive**
  - [ ] Method: File Manager / FTP / SFTP
  - [ ] Upload to: `/home/customer/www/csjones.co/`
  - [ ] Extract archive
  - [ ] Rename to: `tengo-app`
  - [ ] Verify structure:
  ```bash
  ls -la ~/tengo-app/
  # Should show: app/, bootstrap/, config/, database/, public/, etc.
  ```

- [ ] **Create Public Symlink** (Requires SSH)
  ```bash
  ssh [username]@csjones.co -p18765
  cd ~/public_html
  ln -s ~/tengo-app/public tengo
  ls -la | grep tengo
  # Should show: tengo -> /home/.../tengo-app/public
  exit
  ```
  - [ ] Symlink created successfully
  - [ ] Points to correct directory

- [ ] **Create Required Storage Directories** (CRITICAL - prevents 500 errors)
  ```bash
  ssh [username]@csjones.co -p18765
  cd ~/www/csjones.co/tengo-app

  # Create all required storage subdirectories
  mkdir -p storage/framework/cache
  mkdir -p storage/framework/sessions
  mkdir -p storage/framework/views
  mkdir -p storage/framework/cache/data

  # Set writable permissions
  chmod -R 775 storage/framework

  # Verify structure
  ls -la storage/framework/
  exit
  ```
  - [ ] `storage/framework/cache/` directory created
  - [ ] `storage/framework/sessions/` directory created
  - [ ] `storage/framework/views/` directory created
  - [ ] `storage/framework/cache/data/` directory created
  - [ ] All storage/framework directories have 775 permissions

- [ ] **Set File Permissions**
  ```bash
  ssh [username]@csjones.co -p18765
  cd ~/tengo-app
  find storage bootstrap/cache -type d -exec chmod 775 {} \;
  find storage bootstrap/cache -type f -exec chmod 664 {} \;
  ls -la storage/
  exit
  ```
  - [ ] Storage directories writable (775)
  - [ ] Bootstrap cache writable (775)

---

## ⚠️ CRITICAL: .htaccess Configuration

**Based on Security Audit & Deployment Testing - November 13, 2025**

**⚠️ CRITICAL FINDING**: The `.htaccess.production` file contains `<DirectoryMatch>` directives which are NOT allowed on SiteGround shared hosting!

- [ ] **Create SiteGround-Compatible .htaccess** (CRITICAL - prevents 500 errors)
  ```bash
  ssh [username]@csjones.co -p18765
  cd ~/www/csjones.co/tengo-app

  # Create SiteGround-compatible .htaccess (NO DirectoryMatch directives)
  cat > public/.htaccess << 'EOF'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>
    RewriteEngine On
    RewriteBase /tengo/
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
<FilesMatch "(composer\.json|composer\.lock|package\.json|package-lock\.json|\.env)$">
    Require all denied
</FilesMatch>
EOF

  # Remove .htaccess.production template (not compatible with SiteGround)
  rm .htaccess.production 2>/dev/null || true

  # Remove root .htaccess (MUST NOT EXIST)
  rm .htaccess 2>/dev/null || true

  # Set correct permissions
  chmod 644 public/.htaccess
  ```
  - [ ] SiteGround-compatible .htaccess created
  - [ ] NO `<DirectoryMatch>` directives (causes HTTP 500 on SiteGround)
  - [ ] Root `.htaccess` deleted (verified doesn't exist)
  - [ ] `.htaccess.production` template removed
  - [ ] Permissions set to 644

- [ ] **Verify .htaccess Configuration**
  ```bash
  # Check RewriteBase is set correctly
  cat ~/tengo-app/public/.htaccess | grep "RewriteBase"
  # MUST show: RewriteBase /tengo/

  # Verify root .htaccess doesn't exist
  ls ~/tengo-app/.htaccess
  # Should show: No such file or directory

  # Check permissions
  ls -la ~/tengo-app/public/.htaccess
  # Should show: -rw-r--r-- (644)
  ```
  - [ ] `RewriteBase /tengo/` present in public/.htaccess
  - [ ] Root directory has NO .htaccess file
  - [ ] File permissions are 644

- [ ] **Set Complete File Permissions** (Audit-Recommended)
  ```bash
  cd ~/tengo-app

  # Standard file permissions (644)
  find . -type f -exec chmod 644 {} \;

  # Standard directory permissions (755)
  find . -type d -exec chmod 755 {} \;

  # Writable directories (775)
  chmod 775 storage bootstrap/cache
  chmod -R 775 storage/app storage/framework storage/logs
  chmod 775 storage/framework/cache storage/framework/sessions storage/framework/views
  ```
  - [ ] All files set to 644
  - [ ] All directories set to 755
  - [ ] Writable directories set to 775
  - [ ] Verified critical permissions:
    ```bash
    ls -la public/.htaccess    # Should be: -rw-r--r-- (644)
    ls -la public/index.php    # Should be: -rw-r--r-- (644)
    ls -ld storage/            # Should be: drwxrwxr-x (775)
    ls -ld bootstrap/cache/    # Should be: drwxrwxr-x (775)
    ```

---

## Configuration

- [ ] **Create Production .env File**
  ```bash
  ssh [username]@csjones.co -p18765
  cd ~/tengo-app
  nano .env
  # Copy contents from .env.production.example
  # Update all placeholders with actual values
  ```

- [ ] **.env Configuration Complete**
  - [ ] `APP_NAME="Fynla - Financial Planning System"`
  - [ ] `APP_ENV=production`
  - [ ] `APP_KEY=` (will be generated)
  - [ ] `APP_DEBUG=false` (CRITICAL)
  - [ ] `APP_URL=https://csjones.co/tengo`
  - [ ] `DB_DATABASE=` [your prefixed database name]
  - [ ] `DB_USERNAME=` [your prefixed username]
  - [ ] `DB_PASSWORD=` [your secure password]
  - [ ] `CACHE_DRIVER=memcached` (or `file` if unavailable)
  - [ ] `SESSION_DRIVER=database`
  - [ ] `SESSION_PATH=/tengo`
  - [ ] `SESSION_DOMAIN=.csjones.co`
  - [ ] `QUEUE_CONNECTION=database`
  - [ ] `VITE_API_BASE_URL=https://csjones.co/tengo`
  - [ ] Mail settings configured

- [ ] **Generate Application Key**
  ```bash
  cd ~/tengo-app
  php artisan key:generate
  cat .env | grep APP_KEY
  # Should show: APP_KEY=base64:...
  ```

- [ ] **Verify .env Security**
  ```bash
  ls -la ~/tengo-app/.env              # Should exist
  ls -la ~/tengo-app/public/.env       # Should NOT exist
  chmod 644 ~/tengo-app/.env
  ```

- [ ] **Check Memcached Availability**
  ```bash
  php -m | grep memcached
  # If not available, update .env: CACHE_DRIVER=file
  ```

---

## Build & Optimization

- [ ] **Install Composer Dependencies**
  ```bash
  cd ~/tengo-app
  composer install --no-dev --optimize-autoloader
  ls -la vendor/
  # Should show vendor directory with 50-100 packages
  ```
  - [ ] Installation completed successfully
  - [ ] No errors or warnings
  - [ ] Time: ~2-3 minutes

- [ ] **Upload Built Assets**
  - [ ] On local machine, assets built: `public/build/`
  - [ ] Upload method: FTP/SFTP
  - [ ] Upload `public/build/` to `~/tengo-app/public/build/`
  - [ ] Verify on server:
  ```bash
  ls -la ~/tengo-app/public/build/manifest.json
  ls ~/tengo-app/public/build/assets/ | wc -l
  # Should show 100+ files
  ```

- [ ] **Run Database Migrations**
  ```bash
  cd ~/tengo-app
  php artisan migrate --force
  ```
  - [ ] All migrations ran successfully
  - [ ] No errors in output
  - [ ] Database tables created

- [ ] **Seed Critical Data**
  ```bash
  # Tax configuration (CRITICAL)
  php artisan db:seed --class=TaxConfigurationSeeder --force

  # Admin account (for testing)
  php artisan db:seed --class=AdminUserSeeder --force

  # Demo user (optional)
  php artisan db:seed --class=DemoUserSeeder --force
  ```
  - [ ] Tax configurations seeded
  - [ ] Admin account created: admin@fps.com / admin123
  - [ ] Demo user created (optional): demo@fps.com / password

- [ ] **Optimize Laravel for Production**
  ```bash
  cd ~/tengo-app

  # Clear all caches first
  php artisan optimize:clear

  # Cache configuration
  php artisan config:cache

  # Cache routes
  php artisan route:cache

  # Cache views
  php artisan view:cache

  # Overall optimization
  php artisan optimize

  # Verify
  ls -la bootstrap/cache/
  # Should show: config.php, routes-v7.php, services.php
  ```

- [ ] **Configure .htaccess for Subdirectory**
  ```bash
  cd ~/tengo-app/public
  cp .htaccess .htaccess.backup
  nano .htaccess
  # Add after RewriteEngine On:
  # RewriteBase /tengo/
  # Or replace entire file with .htaccess.production content
  ```
  - [ ] `RewriteBase /tengo/` added
  - [ ] Security headers configured
  - [ ] File saved and verified

---

## Post-Deployment Verification

### Smoke Tests (Browser)

- [ ] **Homepage Loads**
  - [ ] URL: https://csjones.co/tengo
  - [ ] Page displays correctly
  - [ ] No 404 or 500 errors
  - [ ] CSS/JS assets loaded (check browser DevTools Network tab)

- [ ] **Login Page Works**
  - [ ] URL: https://csjones.co/tengo/login
  - [ ] Form displays correctly
  - [ ] No console errors (check browser DevTools Console)

- [ ] **Admin Login Test**
  - [ ] Login: admin@fps.com / admin123
  - [ ] Login succeeds
  - [ ] Redirects to dashboard
  - [ ] Dashboard loads without errors

- [ ] **Test Navigation**
  - [ ] Protection module: https://csjones.co/tengo/protection
  - [ ] Savings module: https://csjones.co/tengo/savings
  - [ ] Investment module: https://csjones.co/tengo/investment
  - [ ] Retirement module: https://csjones.co/tengo/retirement
  - [ ] Estate module: https://csjones.co/tengo/estate
  - [ ] All modules load correctly

### ⚠️ CRITICAL: 403 Forbidden Error Testing

**Verify no 403 errors on legitimate requests AND confirm security blocking works**

- [ ] **Test Root URL Accessibility**
  ```bash
  curl -I https://csjones.co/tengo
  # Expected: HTTP/2 200 OK
  ```
  - [ ] Returns 200 (not 403)

- [ ] **Test API Routes Accessibility**
  ```bash
  curl -I https://csjones.co/tengo/api/auth/login
  # Expected: HTTP/2 405 or 422 (not 403 or 404)
  ```
  - [ ] Returns 405 or 422 (routing working)
  - [ ] Does NOT return 403 (access not blocked)
  - [ ] Does NOT return 404 (RewriteBase working)

- [ ] **Test Assets Load Successfully**
  ```bash
  curl -I https://csjones.co/tengo/build/manifest.json
  # Expected: HTTP/2 200 OK
  ```
  - [ ] Manifest.json returns 200
  - [ ] CSS/JS files accessible
  - [ ] No 403 errors on assets

- [ ] **Test Storage Blocking (SHOULD Return 403)**
  ```bash
  curl -I https://csjones.co/tengo/storage/logs/laravel.log
  # Expected: HTTP/2 403 Forbidden (CORRECT - security working)
  ```
  - [ ] Returns 403 or 404 (blocked - correct)
  - [ ] Does NOT return 200 (would be security issue)

- [ ] **Test Sensitive Files Blocking (SHOULD Return 403)**
  ```bash
  # Test .env file
  curl -I https://csjones.co/tengo/.env
  # Expected: HTTP/2 403 Forbidden (CORRECT)

  # Test composer.json
  curl -I https://csjones.co/tengo/composer.json
  # Expected: HTTP/2 403 Forbidden (CORRECT)

  # Test .git directory
  curl -I https://csjones.co/tengo/.git/config
  # Expected: HTTP/2 403 Forbidden (CORRECT)
  ```
  - [ ] .env file returns 403 (blocked - correct)
  - [ ] composer.json returns 403 (blocked - correct)
  - [ ] .git directory returns 403 (blocked - correct)

- [ ] **Browser DevTools Check**
  - [ ] Open https://csjones.co/tengo
  - [ ] Open DevTools (F12) → Network tab
  - [ ] Filter by status "403"
  - [ ] No 403 errors on CSS, JS, or API calls
  - [ ] Console tab shows no CORS or 403 errors

- [ ] **Optional: Run Comprehensive Test Script**
  ```bash
  # Download and run test script (from local machine)
  # See DEPLOYMENT_GUIDE_SITEGROUND.md Section 7.2
  chmod +x test-403-errors.sh
  ./test-403-errors.sh
  ```
  - [ ] All tests pass
  - [ ] Summary shows expected results

**If Any 403 Errors on Legitimate Routes**:
```bash
# Via SSH - troubleshoot .htaccess
cd ~/tengo-app

# Verify RewriteBase
cat public/.htaccess | grep "RewriteBase"
# MUST show: RewriteBase /tengo/

# Verify root .htaccess doesn't exist
ls .htaccess
# Should show: No such file or directory

# Check permissions
ls -la public/.htaccess
# Should show: -rw-r--r-- (644)
```

### Server-Side Checks (SSH)

- [ ] **Check Laravel Logs**
  ```bash
  cd ~/tengo-app
  tail -50 storage/logs/laravel.log
  # Look for errors or warnings
  ```
  - [ ] No critical errors
  - [ ] No database connection errors
  - [ ] No file permission errors

- [ ] **Check Web Server Logs**
  - [ ] Go to: Site Tools > Statistics > Error Log
  - [ ] Review recent errors
  - [ ] No 404 errors on assets
  - [ ] No 500 errors

- [ ] **Test Database Connection**
  ```bash
  php artisan db:show
  # Should show database details
  ```
  - [ ] Connection successful
  - [ ] Correct database displayed

- [ ] **Verify Cache Working**
  ```bash
  php artisan tinker
  # In tinker:
  Cache::put('test', 'value', 60);
  Cache::get('test');
  # Should return: "value"
  exit
  ```
  - [ ] Cache read/write successful

### Functional Tests

- [ ] **User Registration**
  - [ ] Register new test user
  - [ ] Email verification (if enabled)
  - [ ] Login with new user

- [ ] **Protection Module**
  - [ ] Add life insurance policy
  - [ ] View coverage analysis
  - [ ] Calculations display correctly

- [ ] **Savings Module**
  - [ ] Add savings account
  - [ ] Check ISA tracker
  - [ ] Emergency fund calculation works

- [ ] **Investment Module**
  - [ ] Add investment account
  - [ ] Add holdings
  - [ ] Portfolio analysis displays

- [ ] **Retirement Module**
  - [ ] Add DC pension
  - [ ] View projections
  - [ ] State pension integration works

- [ ] **Estate Module**
  - [ ] Add property
  - [ ] View IHT calculation
  - [ ] Gifting features work

### Performance Tests

- [ ] **Page Load Times**
  - [ ] Homepage < 2 seconds
  - [ ] Dashboard < 3 seconds
  - [ ] Module pages < 3 seconds

- [ ] **Asset Loading**
  - [ ] No 404s in browser Network tab
  - [ ] CSS/JS files load from /tengo/build/
  - [ ] Images load correctly

---

## Security Verification

- [ ] **Environment Security**
  - [ ] `APP_DEBUG=false` in .env (CRITICAL)
  - [ ] `APP_ENV=production` in .env
  - [ ] `.env` file NOT web-accessible (in ~/tengo-app/, not public/)
  - [ ] Strong database password (16+ characters)
  - [ ] `APP_KEY` generated and unique

- [ ] **Session Security**
  - [ ] `SESSION_SECURE_COOKIE=true` (forces HTTPS)
  - [ ] `SESSION_HTTP_ONLY=true`
  - [ ] `SESSION_SAME_SITE=lax`

- [ ] **HTTPS Enabled**
  - [ ] Site loads with https://
  - [ ] No mixed content warnings
  - [ ] SSL certificate valid (Let's Encrypt)

- [ ] **File Permissions**
  - [ ] storage/ directories: 775
  - [ ] .env file: 644
  - [ ] Public assets: 644

- [ ] **Access Restrictions**
  - [ ] .env not accessible via URL
  - [ ] storage/ not accessible via URL
  - [ ] vendor/ not accessible via URL

---

## Backup & Monitoring Setup

- [ ] **Database Backup Configuration**
  - [ ] Login: https://csjones.co/tengo/admin
  - [ ] Go to: Database Backups
  - [ ] Configure automatic backups:
    - [ ] Frequency: Daily
    - [ ] Retention: 30 days
    - [ ] Time: 01:00 AM
  - [ ] Test manual backup creation

- [ ] **Initial Manual Backup**
  ```bash
  php artisan backup:run
  ls -la storage/app/backups/
  ```
  - [ ] Backup file created
  - [ ] Backup size reasonable

- [ ] **Log Rotation Cron Job**
  - [ ] Go to: Site Tools > Cron Jobs
  - [ ] Add daily cron (02:00 AM):
  ```bash
  cd ~/tengo-app && find storage/logs/ -name "*.log" -mtime +7 -delete
  ```
  - [ ] Cron job created

- [ ] **Cache Clear Cron Job** (Optional)
  - [ ] Add weekly cron (03:00 AM Sunday):
  ```bash
  cd ~/tengo-app && php artisan optimize:clear && php artisan config:cache
  ```
  - [ ] Cron job created (optional)

---

## Documentation & Handover

- [ ] **Deployment Notes**
  - [ ] Document deployment date
  - [ ] Note any issues encountered
  - [ ] Record database credentials securely
  - [ ] Note SSH credentials

- [ ] **Access Credentials Documented**
  - [ ] Admin access: admin@fps.com / admin123
  - [ ] Demo user: demo@fps.com / password
  - [ ] Database credentials saved securely
  - [ ] SSH credentials saved securely
  - [ ] SiteGround account credentials verified

- [ ] **Support Resources**
  - [ ] Deployment guide provided: `DEPLOYMENT_GUIDE_SITEGROUND.md`
  - [ ] .env example provided: `.env.production.example`
  - [ ] .htaccess example provided: `.htaccess.production`
  - [ ] Troubleshooting section reviewed

---

## Rollback Plan (If Needed)

- [ ] **Rollback Preparation**
  - [ ] Note current git commit:
  ```bash
  git rev-parse HEAD
  # Commit: _________________
  ```
  - [ ] Database backup created BEFORE deployment
  - [ ] Previous deployment archive saved (if applicable)

- [ ] **If Rollback Required**
  - [ ] Stop deployment immediately
  - [ ] Document issue encountered
  - [ ] Follow rollback procedures in deployment guide
  - [ ] Restore database backup if migrations ran
  - [ ] Clear all caches

---

## Sign-Off

### Pre-Deployment

- [ ] **Technical Review Complete**
  - Reviewed by: _________________
  - Date: _________________
  - Approved: [ ] Yes [ ] No

### Post-Deployment

- [ ] **Deployment Complete**
  - Deployed by: _________________
  - Date: _________________
  - Time: _________________

- [ ] **Verification Complete**
  - Verified by: _________________
  - Date: _________________
  - All tests passing: [ ] Yes [ ] No

- [ ] **Ready for Production**
  - Approved by: _________________
  - Date: _________________
  - Status: [ ] Live [ ] Issues Found

---

## Notes

**Issues Encountered:**

_____________________________________________________________________________

_____________________________________________________________________________

_____________________________________________________________________________


**Resolutions Applied:**

_____________________________________________________________________________

_____________________________________________________________________________

_____________________________________________________________________________


**Follow-Up Actions Required:**

_____________________________________________________________________________

_____________________________________________________________________________

_____________________________________________________________________________

---

**Deployment Checklist Version**: 1.0
**Application Version**: Fynla v0.2.7
**Last Updated**: November 12, 2025

Built with Claude Code by Anthropic
