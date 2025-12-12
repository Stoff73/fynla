# Fynla Deployment - Quick Reference

**Version**: v0.1.2.13
**Target**: SiteGround (https://csjones.co/tengo)
**Method**: Symlink Setup

---

## 🚀 Quick Deploy (30 minutes)

### 1. Build Locally (5 min)

```bash
# IMPORTANT: Open a FRESH terminal (no dev env vars)
cd /Users/Chris/Desktop/fpsApp/tengo
NODE_ENV=production npm run build

# Create deployment archive
cd /Users/Chris/Desktop/fpsApp/
tar -czf tengo-deploy.tar.gz \
  --exclude='tengo/node_modules' \
  --exclude='tengo/.git' \
  --exclude='tengo/tests' \
  --exclude='tengo/.env' \
  tengo/

# Verify size (should be 20-50 MB)
ls -lh tengo-deploy.tar.gz
```

### 2. Upload to Server (5 min)

```bash
# Upload (use your alias or full command)
scp tengo-deploy.tar.gz siteground-tengo:~/
# OR:
scp -P 18765 -i ~/.ssh/siteground_ssh_key.pem tengo-deploy.tar.gz u163-ptanegf9edny@ssh.csjones.co:~/
```

### 3. Extract & Setup on Server (10 min)

```bash
# SSH in
ssh siteground-tengo
# OR:
ssh -p 18765 -i ~/.ssh/siteground_ssh_key.pem u163-ptanegf9edny@ssh.csjones.co

# Navigate to web root
cd ~/www/csjones.co/public_html/

# Remove old symlink if exists
rm -f tengo

# Extract files
mkdir -p tengo_laravel
tar -xzf ~/tengo-deploy.tar.gz -C tengo_laravel/ --strip-components=1
rm ~/tengo-deploy.tar.gz

# Set permissions
chmod -R 755 tengo_laravel/
chmod -R 775 tengo_laravel/storage/
chmod -R 775 tengo_laravel/bootstrap/cache/

# Create main symlink
ln -s tengo_laravel/public tengo

# Create manifest symlink (CRITICAL)
cd tengo_laravel/public/build/
ln -s .vite/manifest.json manifest.json
cd ~/www/csjones.co/public_html/tengo_laravel/
```

### 4. Configure Environment (5 min)

```bash
# Still on server
cd ~/www/csjones.co/public_html/tengo_laravel/

# Create .env from template
cp .env.production.example .env
nano .env

# Update these values:
# - APP_URL=https://csjones.co/tengo
# - DB_DATABASE=dbow3dj6o4qnc4
# - DB_USERNAME=uixybijdvk3yv
# - DB_PASSWORD=YOUR_PASSWORD_FROM_SITEGROUND
# - MAIL_PASSWORD=YOUR_EMAIL_PASSWORD

# Generate app key
php artisan key:generate --force

# Test database connection
php artisan db:show
```

### 5. Run Migrations & Optimize (5 min)

```bash
# Run migrations (creates 40+ tables)
php artisan migrate --force

# Seed tax config
php artisan db:seed --class=TaxConfigurationSeeder --force

# Create admin account
php artisan tinker
```

```php
$admin = new \App\Models\User();
$admin->name = 'Fynla Admin';
$admin->email = 'admin@fps.com';
$admin->password = bcrypt('YOUR_SECURE_PASSWORD');
$admin->email_verified_at = now();
$admin->is_admin = true;
$admin->save();
exit
```

```bash
# Cache everything
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer dump-autoload --optimize
```

### 6. Set Up Cron Jobs (Site Tools)

**Queue Worker** (Every Minute):
```bash
cd ~/www/csjones.co/public_html/tengo_laravel && php artisan queue:work --stop-when-empty --max-time=3600 >> /dev/null 2>&1
```

**Daily Cleanup** (2:00 AM):
```bash
cd ~/www/csjones.co/public_html/tengo_laravel && php artisan queue:prune-batches && php artisan cache:prune-stale-tags >> /dev/null 2>&1
```

### 7. Test

Visit: **https://csjones.co/tengo**

✅ Landing page loads with styling
✅ Register new user works
✅ Admin login works (`admin@fps.com`)
✅ All 5 modules accessible
✅ No console errors (F12)

---

## 📋 SiteGround Database Setup

**Your existing database credentials**:
- **Database**: `dbow3dj6o4qnc4`
- **Username**: `uixybijdvk3yv`
- **Password**: Your database password from SiteGround

These credentials are ready to use in your `.env` file!

If you need to create a new database:
1. **Site Tools** → **MySQL** → **Databases**
2. **Create Database** and assign user `uixybijdvk3yv`
3. Grant **ALL PRIVILEGES**

---

## 🔑 Your SSH Credentials

```
Username: u163-ptanegf9edny
Host: ssh.csjones.co
Port: 18765
Key: ~/.ssh/siteground_ssh_key.pem
```

**SSH Config** (`~/.ssh/config`):
```
Host siteground-tengo
    HostName ssh.csjones.co
    Port 18765
    User u163-ptanegf9edny
    IdentityFile ~/.ssh/siteground_ssh_key.pem
```

Then connect with: `ssh siteground-tengo`

---

## 🐛 Quick Troubleshooting

### 500 Error
```bash
tail -100 storage/logs/laravel.log
chmod -R 775 storage/ bootstrap/cache/
php artisan cache:clear && php artisan config:clear
```

### Vite Manifest Not Found
```bash
cd ~/www/csjones.co/public_html/tengo_laravel/public/build/
ln -s .vite/manifest.json manifest.json
php artisan view:clear
```

### Assets 404 (CSS/JS)
```bash
# Check symlink
ls -la ~/www/csjones.co/public_html/ | grep tengo
# Should show: tengo -> tengo_laravel/public

# Check ASSET_URL
cat .env | grep ASSET_URL
# Should show: ASSET_URL=/tengo

# Check build files exist
ls -la public/build/
```

### Database Connection Error
```bash
# Verify credentials
cat .env | grep DB_

# Test connection
php artisan db:show
```

### CSRF Token Error (419)
```bash
# Check session config
cat .env | grep SESSION
# Should have: SESSION_PATH=/tengo, SESSION_DOMAIN=csjones.co

# Clear sessions
rm -rf storage/framework/sessions/*
php artisan cache:clear
```

---

## 📁 Directory Structure

```
~/www/csjones.co/public_html/
├── tengo → tengo_laravel/public  # Symlink (WEB-ACCESSIBLE)
└── tengo_laravel/                 # App root (SECURE)
    ├── app/
    ├── config/
    ├── public/
    │   ├── index.php
    │   └── build/
    │       ├── .vite/
    │       │   └── manifest.json
    │       ├── assets/
    │       └── manifest.json → .vite/manifest.json
    ├── storage/
    └── .env
```

---

## 🔄 Updating Deployment

```bash
# 1. Build locally
cd /Users/Chris/Desktop/fpsApp/tengo
NODE_ENV=production npm run build

# 2. Create archive (follow step 1 above)

# 3. Upload and extract
scp tengo-deploy.tar.gz siteground-tengo:~/
ssh siteground-tengo
cd ~/www/csjones.co/public_html/
tar -xzf ~/tengo-deploy.tar.gz -C tengo_laravel/ --strip-components=1

# 4. Run migrations
cd tengo_laravel/
php artisan migrate --force

# 5. Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## ✅ Final Deployment Checklist

- [ ] **Local build** completed (`npm run build`)
- [ ] **Tarball created** and uploaded
- [ ] **Old symlinks removed** from public_html
- [ ] **Files extracted** to `tengo_laravel/`
- [ ] **Symlinks created** (`tengo` + `manifest.json`)
- [ ] **Permissions set** (755 general, 775 storage)
- [ ] **.env configured** with database credentials
- [ ] **APP_KEY generated**
- [ ] **Database migrations** run (40+ tables)
- [ ] **Tax config seeded**
- [ ] **Admin account** created
- [ ] **Caches built** (config, route, view)
- [ ] **Cron jobs** set up (queue + cleanup)
- [ ] **Website loads** at https://csjones.co/tengo
- [ ] **User registration** works
- [ ] **Admin login** works
- [ ] **All modules** accessible
- [ ] **No console errors**

---

## 📚 Full Documentation

See **DEPLOYMENT.md** for complete step-by-step guide with explanations.

---

**Guide Version**: 2.0
**Fynla Version**: v0.1.2.13
**Deployment Time**: ~30 minutes
**Last Updated**: October 30, 2025

🤖 **Generated with [Claude Code](https://claude.com/claude-code)**
