# Fynla Deployment Guide - fynla.org (SiteGround Shared Hosting)

## Deployment Overview

**Target:** Root of fynla.org
**Hosting:** SiteGround Shared Hosting
**Upload Method:** SiteGround File Manager
**Prerequisites:** Database created, Domain & SSL configured

---

## Pre-Deployment: Local Preparation

### Step 1: Update vite.config.js for Root Deployment

**File:** `vite.config.js`

Change line 9 from:
```javascript
base: process.env.NODE_ENV === 'production' ? '/fynla/build/' : '/',
```

To:
```javascript
base: process.env.NODE_ENV === 'production' ? '/build/' : '/',
```

**Why:** Root deployment doesn't need the `/fynla/` prefix.

---

### Step 2: Build Frontend Assets

```bash
cd /Users/Chris/Desktop/fpsApp/fynla
NODE_ENV=production npm run build
```

This creates `public/build/` directory with:
- `manifest.json`
- Hashed CSS/JS files (e.g., `app-DqF7XY2z.js`)

**Verify build success:**
```bash
ls -la public/build/
cat public/build/manifest.json
```

---

### Step 3: Create Production .htaccess

Create a new file `public/.htaccess.fynla-org` with the following content (for ROOT deployment):

```apache
# =============================================================================
# Fynla v0.4.4 - Production .htaccess for ROOT Deployment
# =============================================================================
# Configured for: https://fynla.org (root, not subdirectory)
# =============================================================================

<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # =============================================================================
    # ROOT Deployment - No subdirectory prefix needed
    # =============================================================================
    RewriteBase /

    # =============================================================================
    # Handle Authorization Header
    # =============================================================================
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # =============================================================================
    # Force HTTPS
    # =============================================================================
    RewriteCond %{HTTPS} !=on
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    # =============================================================================
    # Redirect Trailing Slashes If Not A Folder
    # =============================================================================
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # =============================================================================
    # Send Requests To Front Controller
    # =============================================================================
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# =============================================================================
# Security Headers
# =============================================================================
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
    Header unset X-Powered-By
</IfModule>

# =============================================================================
# Prevent Access to Sensitive Files
# =============================================================================
<Files .env>
    Order allow,deny
    Deny from all
</Files>

<DirectoryMatch "\.git">
    Order allow,deny
    Deny from all
</DirectoryMatch>

<FilesMatch "^(composer\.(json|lock)|package(-lock)?\.json)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# =============================================================================
# Block Direct Access to Storage Directory
# =============================================================================
RedirectMatch 403 ^/storage/

# =============================================================================
# Disable Directory Listing
# =============================================================================
Options -Indexes

# =============================================================================
# Compression
# =============================================================================
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/json
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE font/ttf
    AddOutputFilterByType DEFLATE font/woff
    AddOutputFilterByType DEFLATE font/woff2
    AddOutputFilterByType DEFLATE image/svg+xml
</IfModule>

# =============================================================================
# Browser Caching
# =============================================================================
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType font/woff2 "access plus 1 year"
    ExpiresByType font/woff "access plus 1 year"
    ExpiresByType application/json "access plus 0 seconds"
    ExpiresByType text/html "access plus 0 seconds"
</IfModule>

# =============================================================================
# Character Encoding
# =============================================================================
AddDefaultCharset UTF-8
```

---

### Step 4: Create Production .env File

Create `.env.fynla-org` in your local project root:

```bash
# =============================================================================
# Fynla Production Configuration - fynla.org
# =============================================================================

APP_NAME="Fynla"
APP_ENV=production
APP_KEY=base64:GENERATE_THIS_ON_SERVER
APP_DEBUG=false
APP_URL=https://fynla.org
APP_TIMEZONE=Europe/London

# =============================================================================
# Database Configuration
# =============================================================================
# Get these values from SiteGround Site Tools > MySQL > Databases
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=YOUR_DATABASE_NAME
DB_USERNAME=YOUR_DATABASE_USERNAME
DB_PASSWORD=YOUR_DATABASE_PASSWORD

# =============================================================================
# Cache & Session
# =============================================================================
CACHE_DRIVER=file
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true

# =============================================================================
# Queue (use sync for shared hosting)
# =============================================================================
QUEUE_CONNECTION=sync

# =============================================================================
# Logging
# =============================================================================
LOG_CHANNEL=single
LOG_LEVEL=error

# =============================================================================
# Mail Configuration
# =============================================================================
# Get SMTP settings from SiteGround Site Tools > Email > Email Accounts
MAIL_MAILER=smtp
MAIL_HOST=mail.fynla.org
MAIL_PORT=465
MAIL_USERNAME=noreply@fynla.org
MAIL_PASSWORD=YOUR_EMAIL_PASSWORD
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="noreply@fynla.org"
MAIL_FROM_NAME="Fynla"

# =============================================================================
# Frontend Configuration
# =============================================================================
VITE_APP_NAME="Fynla"
VITE_API_BASE_URL=https://fynla.org

# =============================================================================
# Security
# =============================================================================
SANCTUM_STATEFUL_DOMAINS=fynla.org,www.fynla.org
BCRYPT_ROUNDS=12

# =============================================================================
# Anthropic API (Document Extraction)
# =============================================================================
ANTHROPIC_API_KEY=YOUR_ANTHROPIC_API_KEY
```

---

### Step 5: Create Deployment Package

Create a ZIP archive containing ALL application files EXCEPT:
- `.git/` directory
- `node_modules/` directory
- `.env` (will be created on server)
- `tests/` directory (optional, not needed in production)

**Using terminal:**
```bash
cd /Users/Chris/Desktop/fpsApp/fynla

# Create deployment directory
mkdir -p ../fynla-deploy

# Copy all files except excluded directories
rsync -av --progress . ../fynla-deploy/ \
  --exclude '.git' \
  --exclude 'node_modules' \
  --exclude 'tests' \
  --exclude '.env' \
  --exclude 'storage/logs/*.log' \
  --exclude 'storage/framework/cache/data/*' \
  --exclude 'storage/framework/sessions/*' \
  --exclude 'storage/framework/views/*'

# Create ZIP
cd ../
zip -r fynla-deploy.zip fynla-deploy/
```

---

## Server Deployment: SiteGround

### Step 6: Access SiteGround Site Tools

1. Log in to SiteGround (https://my.siteground.com)
2. Go to **Websites** > Select fynla.org > **Site Tools**

---

### Step 7: Upload Files via File Manager

1. In Site Tools, go to **Site** > **File Manager**
2. Navigate to the root directory (usually `public_html` or the website root)
3. **DELETE** all existing files (if any) - but keep backups if needed
4. Click **Upload** > Select `fynla-deploy.zip`
5. Wait for upload to complete
6. Right-click the ZIP file > **Extract**
7. Move all files from `fynla-deploy/` to the root (select all, cut, go up one level, paste)
8. Delete the empty `fynla-deploy/` folder and the ZIP file

**Directory Structure Should Be:**
```
public_html/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/
│   ├── build/
│   ├── .htaccess
│   └── index.php
├── resources/
├── routes/
├── storage/
├── vendor/
├── .env (create this)
├── artisan
└── composer.json
```

---

### Step 8: Configure Document Root

**CRITICAL:** Laravel requires the document root to point to the `public/` directory.

1. In Site Tools, go to **Site** > **Site Settings**
2. Find **Document Root** setting
3. Change it to: `public_html/public` (or your equivalent path + `/public`)

**Alternative if you cannot change document root:**
Create an `.htaccess` in the root `public_html/` that redirects to `public/`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

---

### Step 9: Create .env File on Server

1. In File Manager, navigate to the application root (where `artisan` is)
2. Click **New File** > Name it `.env`
3. Edit the file and paste your production configuration from Step 4
4. **Update the following values with your actual SiteGround credentials:**
   - `DB_DATABASE` - Your database name
   - `DB_USERNAME` - Your database username
   - `DB_PASSWORD` - Your database password
   - `MAIL_PASSWORD` - Your email password (if using)
   - `ANTHROPIC_API_KEY` - Your Anthropic API key

---

### Step 10: Replace .htaccess in public/

1. Navigate to `public_html/public/` (or wherever your public folder is)
2. Delete the existing `.htaccess`
3. Upload or create a new `.htaccess` with the ROOT deployment content from Step 3

---

### Step 11: Set File Permissions via SSH

1. In Site Tools, go to **Devs** > **SSH Keys Manager**
2. Create an SSH key if you don't have one
3. Connect via SSH using Terminal:

```bash
ssh your_username@fynla.org

# Navigate to your application
cd ~/public_html

# Set storage permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Ensure .env is readable
chmod 644 .env

# Verify storage subdirectories exist
mkdir -p storage/app/public
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
```

---

### Step 12: Generate Application Key

Still in SSH:

```bash
cd ~/public_html
php artisan key:generate --force
```

This will update your `.env` file with a proper `APP_KEY`.

---

### Step 13: Run Database Migrations

```bash
php artisan migrate --force
```

Type `yes` when prompted.

---

### Step 14: Seed Required Data

**CRITICAL:** These seeders MUST be run for the application to function:

```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=TaxProductReferenceSeeder --force
php artisan db:seed --class=UKLifeExpectancySeeder --force
php artisan db:seed --class=ActuarialLifeTablesSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
```

---

### Step 15: Create Storage Symlink

```bash
php artisan storage:link
```

This creates: `public/storage` -> `../storage/app/public`

---

### Step 16: Clear and Cache Configuration

```bash
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

---

## Post-Deployment Verification

### Step 17: Test the Application

1. **Homepage:** Visit https://fynla.org - should see the landing page
2. **API Health:** Visit https://fynla.org/api/health (if endpoint exists) or check Network tab
3. **Login:** Try logging in with admin credentials:
   - Email: `admin@fps.com`
   - Password: `admin123`
4. **Preview Personas:** Try logging in as a preview user from the landing page

---

### Step 18: Check for Errors

If something isn't working:

**Check Laravel Logs:**
```bash
cat ~/public_html/storage/logs/laravel.log
```

**Check SiteGround Error Logs:**
- Site Tools > Statistics > Error Log

**Common Issues:**

| Issue | Solution |
|-------|----------|
| 500 Internal Server Error | Check `.htaccess` syntax, check `storage/logs/laravel.log` |
| 404 on all routes | Verify `RewriteBase /` in `.htaccess`, check document root is `public/` |
| Assets not loading | Verify `public/build/manifest.json` exists, check VITE_API_BASE_URL |
| Database connection error | Verify DB credentials in `.env`, check MySQL is accessible |
| Session/login issues | Run `php artisan config:clear` and `php artisan cache:clear` |

---

## Files to Modify (Summary)

| File | Change Required |
|------|-----------------|
| `vite.config.js` | Change base to `/build/` (from `/fynla/build/`) |
| `public/.htaccess` | Change RewriteBase to `/` and remove subdirectory references |
| `.env` | Create new with fynla.org configuration |

---

## Rollback Plan

If deployment fails:

1. Keep a backup of working files before deployment
2. SiteGround has automatic daily backups in Site Tools > Security > Backups
3. To rollback: Restore from backup or re-upload previous working version

---

## Security Checklist

- [ ] `APP_DEBUG=false` in .env
- [ ] `APP_ENV=production` in .env
- [ ] .env file is NOT in public directory
- [ ] .htaccess blocks access to .env and .git
- [ ] HTTPS is enforced
- [ ] SESSION_SECURE_COOKIE=true
- [ ] Strong database password used
- [ ] Admin password should be changed from default after first login
- [ ] ANTHROPIC_API_KEY is set (if using document extraction)

---

## Quick Reference Commands

**SSH Connection:**
```bash
ssh your_username@fynla.org
```

**All Post-Upload Commands (run in order):**
```bash
cd ~/public_html
chmod -R 775 storage bootstrap/cache
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=TaxProductReferenceSeeder --force
php artisan db:seed --class=UKLifeExpectancySeeder --force
php artisan db:seed --class=ActuarialLifeTablesSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

**Troubleshooting Commands:**
```bash
# Clear all caches
php artisan optimize:clear

# Check logs
tail -100 storage/logs/laravel.log

# Test database connection
php artisan tinker --execute="DB::connection()->getPdo();"

# Verify routes are cached
php artisan route:list | head -20
```
