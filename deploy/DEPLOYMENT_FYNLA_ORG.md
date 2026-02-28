# Fynla Deployment Guide - fynla.org (SiteGround Shared Hosting)

## Deployment Overview

**Target:** Root of fynla.org
**Hosting:** SiteGround Shared Hosting
**Upload Method:** SiteGround File Manager
**Prerequisites:** Database created, Domain & SSL configured

> **IMPORTANT: LOCAL BUILD REQUIRED**
>
> The server does not have enough memory to run `npm install` or `npm run build`.
> You MUST build the frontend assets locally and include `public/build/` in the deployment package.

---

## CRITICAL: .htaccess File Warning

> **DO NOT upload `public/.htaccess` from your local folder!**
>
> The local `public/.htaccess` is configured for csjones.co/tengo (subdirectory deployment) and will cause **500 Internal Server Error** on fynla.org.
>
> **Always use:** `deploy/fynla-org/.htaccess` → upload to `public_html/public/.htaccess`

The wrong .htaccess causes:
- `<DirectoryMatch not allowed here` error (500 Internal Server Error)
- Wrong `RewriteBase /tengo/` instead of `/`
- CSS/JS MIME type issues in incognito mode

---

## Pre-Deployment: Local Preparation

### Step 1: Build Frontend Assets Locally

**This step is MANDATORY. Do NOT attempt to build on the server.**

Run the build script from your local machine:

```bash
cd /Users/Chris/Desktop/fpsApp/fynla
./deploy/fynla-org/build.sh
```

This script:
1. Sets the correct environment variables for root deployment
2. Builds frontend assets into `public/build/`
3. Creates a deployment-ready ZIP package

**Verify build success:**
```bash
ls -la public/build/
cat public/build/manifest.json
```

You should see:
- `manifest.json`
- Hashed CSS/JS files (e.g., `app-DqF7XY2z.js`, `app-ABC123.css`)
- Various asset files

---

### Step 2: Create Deployment Package

The build script automatically creates the deployment package. If you need to create it manually:

```bash
cd /Users/Chris/Desktop/fpsApp/fynla

# Create deployment directory
rm -rf ../fynla-org-deploy
mkdir -p ../fynla-org-deploy

# Copy all files INCLUDING public/build/ but EXCLUDING unnecessary files
rsync -av --progress . ../fynla-org-deploy/ \
  --exclude '.git' \
  --exclude 'node_modules' \
  --exclude 'tests' \
  --exclude '.env' \
  --exclude 'storage/logs/*.log' \
  --exclude 'storage/framework/cache/data/*' \
  --exclude 'storage/framework/sessions/*' \
  --exclude 'storage/framework/views/*' \
  --exclude 'deploy' \
  --exclude '*.md' \
  --exclude 'CLAUDE.md'

# Copy the production .htaccess files
cp deploy/fynla-org/.htaccess.root ../fynla-org-deploy/.htaccess
cp deploy/fynla-org/.htaccess ../fynla-org-deploy/public/.htaccess

# Create ZIP
cd ../
rm -f fynla-org-deploy.zip
zip -r fynla-org-deploy.zip fynla-org-deploy/
```

**CRITICAL:** The deployment package MUST include:
- `public/build/` directory (built assets)
- `vendor/` directory (PHP dependencies)
- All application code

**The deployment package must NOT include:**
- `node_modules/` (not needed on server)
- `.git/` (not needed on server)
- `tests/` (not needed on server)

---

### Step 3: Production .htaccess

The build script copies the correct `.htaccess` to the deployment package. For reference, the production `.htaccess` for root deployment is located at:

```
deploy/fynla-org/.htaccess
```

Key configuration for ROOT deployment:
- `RewriteBase /` (not `/fynla/`)
- HTTPS enforcement
- Security headers
- Compression and caching

---

### Step 4: Production .env Template

Create `.env` on the server using this template (also available at `deploy/fynla-org/.env.production`):

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

## Server Deployment: SiteGround

### Step 5: Access SiteGround Site Tools

1. Log in to SiteGround (https://my.siteground.com)
2. Go to **Websites** > Select fynla.org > **Site Tools**

---

### Step 6: Upload and Extract Files

#### Option A: File Manager (Small Archives < 50MB)

1. In Site Tools, go to **Site** > **File Manager**
2. Navigate to the root directory (usually `public_html` or the website root)
3. **DELETE** all existing files (if any) - but keep backups if needed
4. Click **Upload** > Select `fynla-org-deploy.zip`
5. Wait for upload to complete
6. Right-click the ZIP file > **Extract**
7. Move all files from `fynla-org-deploy/` to the root (select all, cut, go up one level, paste)
8. Delete the empty `fynla-org-deploy/` folder and the ZIP file

#### Option B: SSH Extraction (Recommended for Large Archives)

> **Use this method if File Manager fails to extract the archive.**
> The deployment package is typically 100-150MB, which may exceed File Manager's limits.

**Step 1: Upload via File Manager**
1. In Site Tools, go to **Site** > **File Manager**
2. Navigate to `public_html`
3. Click **Upload** > Select `fynla-org-deploy.zip`
4. Wait for upload to complete (do NOT try to extract in File Manager)

**Step 2: Extract via SSH**

1. **Generate SSH key on SiteGround**:
   - In Site Tools, go to **Devs** > **SSH Keys Manager**
   - Click **Generate** to create a new key pair
   - Give it a name (e.g., "MacBook")
   - Click **Create**

2. **Copy the private key**:
   - Click on your key to view it
   - Copy the **Private Key** to your clipboard

3. **Save the private key on your local machine (Mac)**:

   The private key looks like this:
   ```
   -----BEGIN RSA PRIVATE KEY-----
   MIIEpAIBAAKCAQEA3Z7...
   (many lines of random characters)
   ...Xk2mQ==
   -----END RSA PRIVATE KEY-----
   ```

   **Save it (run these 3 commands in Terminal):**

   ```bash
   mkdir -p ~/.ssh
   pbpaste > ~/.ssh/production
   chmod 600 ~/.ssh/production
   ```

   That's it. `pbpaste` takes whatever is in your clipboard and writes it to the file.

4. **Get connection details** from **Devs** > **SSH Access**:
   - SSH Username (e.g., `u123-abc12def34gh`)
   - Hostname (e.g., `ssh.fynla.org` or server IP)
   - Port: `18765`

5. **Connect via Terminal**:
   ```bash
   ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
   ```

6. **Extract and move files**:
```bash
# Navigate to public_html
cd ~/www/fynla.org/public_html

# Extract the ZIP archive
unzip fynla-org-deploy.zip

# Move all files from the extracted folder to public_html
mv fynla-org-deploy/* .
mv fynla-org-deploy/.* . 2>/dev/null  # Move hidden files (ignore errors)

# Clean up
rmdir fynla-org-deploy
rm fynla-org-deploy.zip
```

**Alternative: Using tar.gz (if available)**

If you created a `.tar.gz` instead of `.zip`:
```bash
cd ~/www/fynla.org/public_html
tar -xzf fynla-org-deploy.tar.gz
mv fynla-org-deploy/* .
mv fynla-org-deploy/.* . 2>/dev/null
rmdir fynla-org-deploy
rm fynla-org-deploy.tar.gz
```

**Server Directory Structure:**
```
~/www/fynla.org/
├── logs/
├── public_html/        <-- Web root, Laravel app goes here
│   ├── .htaccess       <-- Root htaccess (redirects to public/)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/
│   │   ├── build/      <-- CRITICAL: Must contain built assets
│   │   │   ├── manifest.json
│   │   │   └── assets/
│   │   ├── .htaccess   <-- Laravel htaccess (routing, security, caching)
│   │   └── index.php
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── vendor/         <-- CRITICAL: Must contain PHP dependencies
│   ├── .env            <-- Create this on server
│   ├── artisan
│   └── composer.json
└── webstats/
```

---

### Step 7: Verify .htaccess Files

The build script includes two `.htaccess` files that are automatically placed in the deployment package:

**1. Root htaccess: `public_html/.htaccess`**

Redirects all requests to the `public/` subdirectory:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

**2. Laravel htaccess: `public_html/public/.htaccess`**

Handles Laravel routing, HTTPS enforcement, security headers, and caching. This file is automatically included from `deploy/fynla-org/.htaccess`.

**Verify both files exist after extraction:**
```bash
ls -la ~/www/fynla.org/public_html/.htaccess
ls -la ~/www/fynla.org/public_html/public/.htaccess
```

If missing, create them manually or re-extract from the deployment package.

---

### Step 8: Create .env File on Server

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

### Step 9: Set File Permissions via SSH

1. In Site Tools, go to **Devs** > **SSH Keys Manager**
2. Create an SSH key if you don't have one
3. Connect via SSH using Terminal:

```bash
ssh your_username@fynla.org

# Navigate to your application
cd ~/www/fynla.org/public_html

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

### Step 10: Generate Application Key

Still in SSH:

```bash
cd ~/www/fynla.org/public_html
php artisan key:generate --force
```

This will update your `.env` file with a proper `APP_KEY`.

---

### Step 11: Run Database Migrations

```bash
php artisan migrate --force
```

Type `yes` when prompted.

---

### Step 12: Seed Required Data

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

### Step 13: Create Storage Symlink

```bash
php artisan storage:link
```

This creates: `public/storage` -> `../storage/app/public`

---

### Step 14: Clear and Cache Configuration

```bash
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

---

## Post-Deployment Verification

### Step 15: Test the Application

1. **Homepage:** Visit https://fynla.org - should see the landing page
2. **API Health:** Visit https://fynla.org/api/health (if endpoint exists) or check Network tab
3. **Login:** Try logging in with admin credentials:
   - Email: `admin@fps.com`
   - Password: `admin123`
4. **Preview Personas:** Try logging in as a preview user from the landing page

---

### Step 16: Check for Errors

If something isn't working:

**Check Laravel Logs:**
```bash
cat ~/www/fynla.org/public_html/storage/logs/laravel.log
```

**Check SiteGround Error Logs:**
- Site Tools > Statistics > Error Log

**Common Issues:**

| Issue | Solution |
|-------|----------|
| 500 Internal Server Error | Check `.htaccess` syntax, check `storage/logs/laravel.log` |
| 500 + "DirectoryMatch not allowed" | Remove `<DirectoryMatch>` from `public/.htaccess` - not allowed on shared hosting. See fix below. |
| 404 on all routes | Verify `RewriteBase /` in `.htaccess`, check document root is `public/` |
| Assets not loading | Verify `public/build/manifest.json` exists, check build was included |
| Database connection error | Verify DB credentials in `.env`, check MySQL is accessible |
| Session/login issues | Run `php artisan config:clear` and `php artisan cache:clear` |
| Missing CSS/JS files | You forgot to include `public/build/` - rebuild locally and re-upload |
| PHP version mismatch | Change PHP version in Site Tools > Devs > PHP Manager to 8.3+ |

**Fix for DirectoryMatch error:**
```bash
sed -i '/<DirectoryMatch/,/<\/DirectoryMatch>/d' ~/www/fynla.org/public_html/public/.htaccess
```

---

## Important Notes

### DO NOT Run These Commands on Server

The following commands require too much memory and will fail on shared hosting:

```bash
# DO NOT RUN ON SERVER - will fail due to memory limits
npm install        # Requires 1-2GB RAM
npm run build      # Requires 1-2GB RAM
composer install   # May work, but vendor/ should be included in package
```

### What MUST Be Built Locally

| Component | Build Locally? | Include in Package? |
|-----------|----------------|---------------------|
| `public/build/` | YES | YES |
| `vendor/` | YES (if updated) | YES |
| `node_modules/` | YES | NO (not needed on server) |

---

## Files Summary

| File | Location | Deployed To | Purpose |
|------|----------|-------------|---------|
| Build script | `deploy/fynla-org/build.sh` | N/A | Builds assets + creates ZIP |
| Root .htaccess | `deploy/fynla-org/.htaccess.root` | `public_html/.htaccess` | Redirects to public/ |
| Laravel .htaccess | `deploy/fynla-org/.htaccess` | `public_html/public/.htaccess` | Routing, security, caching |
| .env template | `deploy/fynla-org/.env.production` | `public_html/.env` | Environment template |

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
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
```

**All Post-Upload Commands (run in order):**
```bash
cd ~/www/fynla.org/public_html
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
