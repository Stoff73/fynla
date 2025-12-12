# SiteGround Laravel Deployment Summary

This file contains essential information extracted from SiteGround's official Laravel installation guide and adapted for the Fynla deployment.

---

## Server Requirements

**PHP Requirements**:
- PHP >= 8.2 (Fynla requirement, higher than SiteGround's base 7.4.28)
- Required Extensions:
  - OpenSSL
  - PDO & PDO_MySQL
  - Mbstring
  - Tokenizer
  - XML
  - Ctype
  - JSON
  - BCMath (for financial calculations)
  - cURL
  - Fileinfo
  - Zip

**Update PHP Version**:
- Go to **Site Tools** → **Devs** → **PHP Manager**
- Select **PHP 8.2** or higher
- Enable all required extensions

---

## Directory Structure for Subdirectory Deployment

**Target URL**: `https://csjones.co/tengo`

**SiteGround Recommended Structure** (Secure Symlink Setup):

```
~/www/csjones.co/public_html/
├── tengo → tengo_laravel/public  # Symbolic link (WEB-ACCESSIBLE)
└── tengo_laravel/                 # Laravel app root (SECURE, not web-accessible)
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── database/
    ├── public/                    # Laravel's public directory
    │   ├── index.php              # Front controller
    │   ├── build/                 # Vite compiled assets
    │   │   ├── .vite/
    │   │   │   └── manifest.json
    │   │   ├── assets/
    │   │   └── manifest.json → .vite/manifest.json (symlink)
    │   └── .htaccess
    ├── resources/
    ├── routes/
    ├── storage/
    ├── vendor/
    ├── .env
    ├── artisan
    └── composer.json
```

**Why Symlink Setup**:
- ✅ **Secure**: Application code (`app/`, `config/`, etc.) is NOT web-accessible
- ✅ **Laravel Best Practice**: Only `public/` directory exposed
- ✅ **Subdirectory Support**: Works perfectly with `csjones.co/tengo` URL structure
- ✅ **Easy Updates**: Replace `tengo_laravel/` files without touching web-accessible symlink

**How It Works**:
1. User visits: `https://csjones.co/tengo/`
2. Web server follows symlink: `tengo` → `tengo_laravel/public/`
3. Serves: `tengo_laravel/public/index.php` (Laravel front controller)
4. Laravel routes request through app code in `tengo_laravel/`

---

## Connection Details (Your Server)

**SSH**:
- **Username**: `u163-ptanegf9edny`
- **Host**: `ssh.csjones.co`
- **Port**: `18765`
- **Key**: `~/.ssh/siteground_ssh_key.pem` (already saved)

**Connection Command**:
```bash
ssh -p 18765 -i ~/.ssh/siteground_ssh_key.pem u163-ptanegf9edny@ssh.csjones.co
```

**Or use SSH config** (`~/.ssh/config`):
```
Host siteground-tengo
    HostName ssh.csjones.co
    Port 18765
    User u163-ptanegf9edny
    IdentityFile ~/.ssh/siteground_ssh_key.pem
```

Then connect with: `ssh siteground-tengo`

---

## Essential SiteGround Steps (Summary)

### 1. Enable SSH Access

Your SSH key is already saved at `~/.ssh/siteground_ssh_key.pem`.

**Verify permissions**:
```bash
chmod 600 ~/.ssh/siteground_ssh_key.pem
ls -la ~/.ssh/siteground_ssh_key.pem  # Should show: -rw-------
```

If you need to regenerate:
1. Log in to **Site Tools** at https://tools.siteground.com
2. Navigate to **Devs** → **SSH Keys Manager**
3. Click **Generate New Key**
4. Copy the **Private Key** if needed

### 2. MySQL Database Credentials

**Your existing database credentials**:
```ini
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=dbow3dj6o4qnc4
DB_USERNAME=uixybijdvk3yv
DB_PASSWORD=your_database_password
```

These credentials are already set up in your SiteGround account and ready to use.

**If you need to create a new database**:
1. **Site Tools** → **MySQL** → **Databases**
2. Click **Create Database**
3. Assign user `uixybijdvk3yv` with **ALL PRIVILEGES**

### 3. Upload Laravel Application

**Recommended Method**: SSH with tarball upload (fastest)

```bash
# Create tarball locally
cd /Users/Chris/Desktop/fpsApp/
tar -czf tengo-deploy.tar.gz \
  --exclude='tengo/node_modules' \
  --exclude='tengo/.git' \
  --exclude='tengo/tests' \
  tengo/

# Upload to server
scp -P 18765 -i ~/.ssh/siteground_ssh_key.pem tengo-deploy.tar.gz u163-ptanegf9edny@ssh.csjones.co:~/

# SSH in and extract
ssh -p 18765 -i ~/.ssh/siteground_ssh_key.pem u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/public_html/
mkdir -p tengo_laravel
tar -xzf ~/tengo-deploy.tar.gz -C tengo_laravel/ --strip-components=1
rm ~/tengo-deploy.tar.gz
```

### 4. Create Symlinks

**Main Symlink** (makes Laravel accessible):
```bash
cd ~/www/csjones.co/public_html/
ln -s tengo_laravel/public tengo

# Verify
ls -la | grep tengo
# Should show: tengo -> tengo_laravel/public
```

**Manifest Symlink** (CRITICAL for Vite):
```bash
cd ~/www/csjones.co/public_html/tengo_laravel/public/build/
ln -s .vite/manifest.json manifest.json

# Verify
ls -la manifest.json
# Should show: manifest.json -> .vite/manifest.json
```

### 5. Configure .env File

```bash
cd ~/www/csjones.co/public_html/tengo_laravel/
cp .env.production.example .env
nano .env
```

**Essential Settings**:
```ini
APP_NAME="Fynla - Financial Planning System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://csjones.co/tengo

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=dbow3dj6o4qnc4
DB_USERNAME=uixybijdvk3yv
DB_PASSWORD=YOUR_DATABASE_PASSWORD

SESSION_PATH=/tengo
SESSION_DOMAIN=csjones.co
SESSION_SECURE_COOKIE=true

ASSET_URL=/tengo
```

**Generate App Key**:
```bash
php artisan key:generate --force
```

### 6. Run Database Migrations

```bash
cd ~/www/csjones.co/public_html/tengo_laravel/

# Create tables
php artisan migrate --force

# Seed UK tax configuration
php artisan db:seed --class=TaxConfigurationSeeder --force
```

### 7. Set Permissions

```bash
cd ~/www/csjones.co/public_html/tengo_laravel/

chmod -R 755 .
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
```

### 8. Optimize for Production

```bash
# Cache config, routes, views
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize
```

### 9. Set Up Cron Jobs

**In Site Tools** → **Devs** → **Cron Jobs**:

**Queue Worker** (every minute):
```bash
cd ~/www/csjones.co/public_html/tengo_laravel && php artisan queue:work --stop-when-empty --max-time=3600 >> /dev/null 2>&1
```

**Daily Cleanup** (2:00 AM):
```bash
cd ~/www/csjones.co/public_html/tengo_laravel && php artisan queue:prune-batches && php artisan cache:prune-stale-tags >> /dev/null 2>&1
```

---

## Critical Configuration Notes

### Front Controller

**Location**: `tengo_laravel/public/index.php`

Laravel's front controller handles all requests. With the symlink setup:
- Web request: `https://csjones.co/tengo/dashboard`
- Web server resolves: `public_html/tengo/index.php` → `public_html/tengo_laravel/public/index.php`
- Laravel handles routing internally

**No special web server configuration needed** - `.htaccess` in `public/` handles everything.

### Asset Loading (Vite)

**Critical Configuration in `vite.config.js`**:
```javascript
base: process.env.NODE_ENV === 'production' ? '/tengo/build/' : '/',
```

This tells Vite to build assets with correct paths for the `/tengo` subdirectory.

**Critical Configuration in `.env`**:
```ini
APP_URL=https://csjones.co/tengo
ASSET_URL=/tengo
```

**Asset URLs will be**:
- `https://csjones.co/tengo/build/assets/app-xxx.js`
- `https://csjones.co/tengo/build/assets/app-xxx.css`

### Session Management

For subdirectory deployments, session configuration is critical:

```ini
SESSION_DRIVER=file
SESSION_PATH=/tengo          # Cookie path matches subdirectory
SESSION_DOMAIN=csjones.co    # Domain for cookie
SESSION_SECURE_COOKIE=true   # HTTPS required
```

**Why `SESSION_PATH=/tengo`**:
- Without it, cookies set at `/` won't work properly in `/tengo`
- Ensures CSRF tokens work correctly
- Prevents 419 "Page Expired" errors

---

## Common Issues & Solutions

### Issue: 500 Internal Server Error

**Causes**:
- Missing `.env` file
- `APP_KEY` not set
- Permissions incorrect on `storage/` or `bootstrap/cache/`

**Solutions**:
```bash
# Check .env exists
ls -la .env

# Generate APP_KEY if missing
php artisan key:generate --force

# Fix permissions
chmod -R 775 storage/ bootstrap/cache/

# Check logs
tail -100 storage/logs/laravel.log
```

### Issue: Vite Manifest Not Found

**Error**: `Vite manifest not found at: /home/.../public/build/manifest.json`

**Cause**: Manifest symlink not created

**Solution**:
```bash
cd ~/www/csjones.co/public_html/tengo_laravel/public/build/
ln -s .vite/manifest.json manifest.json
php artisan view:clear
```

### Issue: Assets Return 404 (CSS/JS Not Loading)

**Causes**:
- Build files not uploaded
- Symlink incorrect
- `ASSET_URL` not set in `.env`

**Solutions**:
```bash
# Check build exists
ls -la public/build/

# Check main symlink
ls -la ~/www/csjones.co/public_html/ | grep tengo
# Should show: tengo -> tengo_laravel/public

# Check ASSET_URL
cat .env | grep ASSET_URL
# Should show: ASSET_URL=/tengo

# Rebuild and upload if needed
# (on local machine)
cd /Users/Chris/Desktop/fpsApp/tengo
NODE_ENV=production npm run build
# Then upload public/build/ to server
```

### Issue: Database Connection Failed

**Error**: `SQLSTATE[HY000] Access denied` or `Connection refused`

**Solutions**:
```bash
# Verify credentials
cat .env | grep DB_

# Test connection
php artisan db:show

# Check database exists in Site Tools
# Site Tools → MySQL → Databases
# Verify dbow3dj6o4qnc4 exists

# Check user has privileges
# Site Tools → MySQL → Databases → User Privileges
```

### Issue: CSRF Token Mismatch (419 Page Expired)

**Causes**:
- Session configuration incorrect
- Cookies not being set
- HTTPS issues

**Solutions**:
```bash
# Check session config
cat .env | grep SESSION
# Must have: SESSION_PATH=/tengo, SESSION_DOMAIN=csjones.co

# Clear sessions
rm -rf storage/framework/sessions/*
php artisan cache:clear

# Rebuild config cache
php artisan config:cache
```

---

## Access Your Application

**Production URL**: https://csjones.co/tengo

**Test URLs**:
- Landing page: `https://csjones.co/tengo/`
- Login: `https://csjones.co/tengo/login`
- Register: `https://csjones.co/tengo/register`
- Dashboard: `https://csjones.co/tengo/dashboard` (requires login)
- Protection: `https://csjones.co/tengo/protection`
- Savings: `https://csjones.co/tengo/savings`
- Investment: `https://csjones.co/tengo/investment`
- Retirement: `https://csjones.co/tengo/retirement`
- Estate: `https://csjones.co/tengo/estate`

---

## MySQL Connection from Command Line (Optional)

If you need to access MySQL directly:

```bash
# Connect to database
mysql -u uixybijdvk3yv -p dbow3dj6o4qnc4

# Enter password when prompted

# Common commands
SHOW TABLES;
SELECT COUNT(*) FROM users;
DESCRIBE users;
EXIT;
```

---

## Additional Resources

**Official SiteGround Documentation**:
- Laravel Installation Guide: https://my.siteground.com/support/kb/install-laravel/
- Site Tools Guide: https://my.siteground.com/support/kb/site-tools/
- SSH Access: https://my.siteground.com/support/kb/ssh-access/
- MySQL Database Management: https://my.siteground.com/support/kb/mysql/

**Laravel Documentation**:
- Deployment: https://laravel.com/docs/10.x/deployment
- Configuration: https://laravel.com/docs/10.x/configuration
- Database Migrations: https://laravel.com/docs/10.x/migrations

**Fynla Project Documentation**:
- Full Deployment Guide: `DEPLOYMENT.md`
- Quick Reference: `DEPLOYMENT_QUICK_REFERENCE.md`
- Development Guide: `CLAUDE.md`

---

## Summary of SiteGround-Specific Requirements

✅ **PHP 8.2+** configured via PHP Manager
✅ **Symlink setup** for secure subdirectory deployment
✅ **MySQL database** created with proper user privileges
✅ **SSH access** enabled with private key authentication
✅ **Cron jobs** configured for queue worker and cleanup
✅ **File permissions** set correctly (755/775)
✅ **Session configuration** adjusted for subdirectory (`SESSION_PATH=/tengo`)
✅ **Asset URLs** configured for subdirectory (`ASSET_URL=/tengo`)
✅ **SSL/HTTPS** enabled (SiteGround provides free Let's Encrypt)

---

**Last Updated**: October 30, 2025
**Fynla Version**: v0.1.2.13
**Tested On**: SiteGround UK Shared Hosting

🤖 **Generated with [Claude Code](https://claude.com/claude-code)**
