# Fynla v0.2.7 - Quick Start Deployment Guide

**For**: SiteGround Hosting at https://csjones.co/tengo
**Time Required**: 30-45 minutes (first deployment)

---

## Before You Start

**Have Ready**:
- [ ] SiteGround account credentials
- [ ] SSH access to csjones.co
- [ ] FTP/SFTP client (FileZilla, Cyberduck, etc.)
- [ ] This deployment package on local machine

**Files Needed**:
- `tengo-v0.2.7-deployment.tar.gz` (application code)
- `public/build/` directory (built assets - ~100 files)
- `.env.production.example` (environment template)
- `.htaccess.production` (Apache configuration)

---

## Step-by-Step Quick Guide

### 1. SiteGround Setup (5 minutes)

**PHP Version**:
```
Site Tools > Devs > PHP Manager
Set to: PHP 8.2 or 8.3
```

**Database**:
```
Site Tools > MySQL > Databases
Create: tengo_production
Create User: tengo_user
Grant ALL PRIVILEGES
Save credentials!
```

**SSH Access**:
```
Site Tools > Devs > SSH Keys Manager
Enable SSH access
```

---

### 2. Upload Application (10 minutes)

**Via File Manager**:
```
1. Upload tengo-v0.2.7-deployment.tar.gz to ~/
2. Extract to ~/tengo-app/
```

**Via SSH**:
```bash
ssh [username]@csjones.co -p18765

# Create directory structure
cd ~
mkdir tengo-app
cd tengo-app

# Upload via FTP, then extract:
tar -xzf ../tengo-v0.2.7-deployment.tar.gz
```

---

### 3. Create Public Symlink (1 minute)

**REQUIRED - Via SSH**:
```bash
cd ~/public_html
ln -s ~/tengo-app/public tengo
ls -la | grep tengo
# Should show: tengo -> /home/.../tengo-app/public
```

---

### 4. Configure Environment (5 minutes)

**Create .env file**:
```bash
cd ~/tengo-app
cp .env.production.example .env
nano .env
```

**Update these values**:
```env
APP_KEY=                          # Leave blank, will generate
APP_URL=https://csjones.co/tengo

DB_DATABASE=[your_prefixed_db_name]
DB_USERNAME=[your_prefixed_username]
DB_PASSWORD=[your_secure_password]

VITE_API_BASE_URL=https://csjones.co/tengo

MAIL_HOST=smtp.siteground.com
MAIL_USERNAME=your_email@csjones.co
MAIL_PASSWORD=[your_email_password]
```

**Generate APP_KEY**:
```bash
php artisan key:generate
```

---

### 5. Install Dependencies (5 minutes)

**Composer**:
```bash
cd ~/tengo-app
composer install --no-dev --optimize-autoloader
```

**Upload Built Assets** (via FTP):
```
Local: /Users/Chris/Desktop/fpsApp/tengo/public/build/
Upload to: ~/tengo-app/public/build/
```

---

### 6. Database Setup (5 minutes)

**Run Migrations**:
```bash
cd ~/tengo-app
php artisan migrate --force
```

**Seed Critical Data**:
```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
```

---

### 7. Configure Apache (2 minutes)

**Update .htaccess**:
```bash
cd ~/tengo-app/public
cp .htaccess .htaccess.backup
nano .htaccess
```

**Add after `RewriteEngine On`**:
```apache
RewriteBase /tengo/
```

Or replace entire file with `.htaccess.production` content.

---

### 8. Optimize Laravel (3 minutes)

**Run All Optimizations**:
```bash
cd ~/tengo-app

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

chmod -R 775 storage bootstrap/cache
```

---

### 9. Verify Deployment (5 minutes)

**Test URLs**:
- Homepage: https://csjones.co/tengo
- Login: https://csjones.co/tengo/login
- Admin: admin@fps.com / admin123

**Check Logs**:
```bash
tail -50 ~/tengo-app/storage/logs/laravel.log
```

**Site Tools**:
```
Site Tools > Statistics > Error Log
(should show no errors)
```

---

## Common Issues & Quick Fixes

### 500 Error
```bash
# Check permissions
chmod -R 775 storage bootstrap/cache

# Regenerate key
php artisan key:generate

# Test database
php artisan db:show
```

### Assets Not Loading (404)
```bash
# Verify assets exist
ls ~/tengo-app/public/build/manifest.json

# Check .env
grep VITE ~/tengo-app/.env
# Should be: VITE_API_BASE_URL=https://csjones.co/tengo

# Clear config
php artisan config:clear && php artisan config:cache
```

### Routes Not Working
```bash
# Check .htaccess
cat ~/tengo-app/public/.htaccess | grep RewriteBase
# Should show: RewriteBase /tengo/

# Clear route cache
php artisan route:clear && php artisan route:cache
```

### Can't Login
```bash
# Check session config in .env
SESSION_DRIVER=database
SESSION_PATH=/tengo
SESSION_DOMAIN=.csjones.co

# Clear config
php artisan config:clear && php artisan config:cache
```

---

## Quick Commands Reference

```bash
# Connect to server
ssh [username]@csjones.co -p18765

# Navigate to app
cd ~/tengo-app

# View logs
tail -50 storage/logs/laravel.log

# Clear all caches
php artisan optimize:clear

# Optimize for production
php artisan config:cache && php artisan route:cache

# Check database
php artisan db:show

# Test cache
php artisan tinker
Cache::put('test', 'value', 60);
Cache::get('test');
exit
```

---

## Support

**Full Documentation**:
- Comprehensive Guide: `DEPLOYMENT_GUIDE_SITEGROUND.md`
- Detailed Checklist: `DEPLOYMENT_CHECKLIST.md`
- Build Verification: `BUILD_VERIFICATION_REPORT.md`

**SiteGround Support**:
- Site Tools: https://my.siteground.com
- 24/7 Chat Support available

**Test Accounts**:
- Admin: admin@fps.com / admin123
- Demo: demo@fps.com / password

---

**Quick Start Version**: 1.0
**Application**: Fynla v0.2.7
**Target**: https://csjones.co/tengo

Built with Claude Code by Anthropic
