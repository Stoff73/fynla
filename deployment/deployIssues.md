# Fynla Deployment Issues & Resolutions

**Date**: October 29, 2025
**Target Server**: SiteGround (csjones.co/tengo)
**Status**: 🟡 Partially Deployed - Registration API Issue

---

## Table of Contents
1. [Deployment Summary](#deployment-summary)
2. [Successfully Resolved Issues](#successfully-resolved-issues)
3. [Current Unresolved Issue](#current-unresolved-issue)
4. [Server Configuration](#server-configuration)
5. [File Locations](#file-locations)
6. [Next Steps](#next-steps)

---

## Deployment Summary

### What's Working ✅
- Landing page loads at `https://csjones.co/tengo/`
- Login page loads correctly
- Production build files deployed and serving correctly
- Database connection working
- Asset loading from `/tengo/build/` working
- Vue Router configured for `/tengo/` base path
- HTTPS working
- Sessions configured for subdirectory

### What's NOT Working ❌
- **Registration API endpoint** - POST requests to `/api/auth/register` return 405 Method Not Allowed
- Error message: "The POST method is not supported for route tengo/api/auth/register. Supported methods: GET, HEAD."
- Apache is converting POST to GET during `.htaccess` redirect

---

## Successfully Resolved Issues

### Issue 1: Missing APP_KEY (500 Error)
**Problem**: Application encryption key not set
**Error**: `No application encryption key has been specified`
**Solution**:
```bash
php artisan key:generate
php artisan config:clear
```
**Status**: ✅ RESOLVED

---

### Issue 2: Corrupted APP_KEY
**Problem**: APP_KEY had two base64 keys concatenated
**Error**: `APP_KEY=base64:...base64:...` (two keys stuck together)
**Solution**: Manually edited `.env` to keep only one key:
```bash
# Changed from:
APP_KEY=base64:GzMXAmqQcDymI5DMaFdbW1gX/X+4or6UkqOYe+ngG0A=base64:0W/VOUin57ayBYmOgETvpiLrgUI359PrtPdtst+K914=

# To:
APP_KEY=base64:0W/VOUin57ayBYmOgETvpiLrgUI359PrtPdtst+K914=
```
**Status**: ✅ RESOLVED

---

### Issue 3: Database Connection Failed
**Problem**: Wrong database credentials in `.env`
**Error**: `Access denied for user 'csjones_fpsuser'@'localhost'`
**Solution**: Verified correct credentials:
```env
DB_DATABASE=dbow3dj6o4qnc4
DB_USERNAME=uixybijdvk3yv
DB_PASSWORD=PixieRebecca2020
```
**Status**: ✅ RESOLVED

---

### Issue 4: Vite Development Server CORS Errors
**Problem**: Production site trying to load assets from `http://127.0.0.1:5173`
**Error**: `Access to script at 'http://127.0.0.1:5173/@vite/client' blocked by CORS`
**Root Cause**: `public/hot` file existed, telling Laravel to use dev server
**Solution**:
```bash
rm -f public/hot
rm -f public/build/hot
php artisan config:clear
```
**Status**: ✅ RESOLVED

---

### Issue 5: Missing manifest.json
**Problem**: Production build missing `manifest.json` in build root
**Error**: Assets not loading, 404 errors
**Root Cause**: Vite creates manifest in `.vite/manifest.json` not build root
**Solution**:
```bash
# Locally
cp public/build/.vite/manifest.json public/build/manifest.json

# Rebuilt production assets
rm -rf public/build/
export NODE_ENV=production
npm run build

# Create tarball with manifest
cd public/build/
tar -czf ~/Desktop/build-files.tar.gz manifest.json .vite/ assets/

# Upload to server via SiteGround File Manager
# Extract on server:
cd ~/www/csjones.co/public_html/tengo/public/build/
tar -xzf ~/www/csjones.co/build-files.tar.gz
```
**Status**: ✅ RESOLVED

---

### Issue 6: 404 on Production Assets
**Problem**: Assets returning 404, looking for `/tengo/build/assets/...`
**Error**: `GET https://csjones.co/tengo/build/assets/app-CF0wJsx4.js 404`
**Root Cause**: Application accessed via `/tengo-link/` but configured for `/tengo/`
**Solution**: Created `.htaccess` in `/tengo/` directory:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Redirect all requests to public subdirectory
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ public/$1 [L,QSA]
</IfModule>
```
**Status**: ✅ RESOLVED

---

### Issue 7: 403 Forbidden on /tengo/
**Problem**: Directory listing blocked
**Error**: `Failed to load resource: 403 Forbidden`
**Root Cause**: Web server pointing to `/tengo/` instead of `/tengo/public/`
**Solution**: Created `.htaccess` redirect (see Issue 6)
**Status**: ✅ RESOLVED

---

### Issue 8: Domain Name Mismatch
**Problem**: Documentation used `c.jones.co` instead of `csjones.co`
**Solution**: Global find/replace across all files:
```bash
find . -type f \( -name "*.md" -o -name ".env.production*" \) -exec sed -i '' 's/c\.jones\.co/csjones.co/g' {} \;
```
**Files Updated**:
- `.env.production`
- `.env.production.example`
- `DEPLOYMENT_GUIDE.md`
- `DEPLOYMENT_READY.md`
- `DEPLOYMENT_QUICK_REFERENCE.md`

**Status**: ✅ RESOLVED

---

### Issue 9: Wrong HTTPS Redirect in .htaccess
**Problem**: `.htaccess` had redirect that interfered with routing
**Old Rule**:
```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}/tengo/$1 [L,R=301]
```
**Solution**: Removed HTTPS redirect (SiteGround handles at server level)
**Status**: ✅ RESOLVED

---

## Current Unresolved Issue

### Issue 10: POST Requests Converted to GET (CRITICAL)
**Problem**: Registration API endpoint not accepting POST requests
**Error Message**:
```
The POST method is not supported for route tengo/api/auth/register.
Supported methods: GET, HEAD.
```
**HTTP Status**: 405 Method Not Allowed

**Browser Console Error**:
```javascript
POST https://csjones.co/tengo/api/auth/register 405 (Method Not Allowed)
```

**Verified Facts**:
1. ✅ Route exists in Laravel: `php artisan route:list | grep register` shows POST route
2. ✅ APP_URL correct: `https://csjones.co/tengo`
3. ✅ Frontend sending to correct URL
4. ✅ Database connection working
5. ✅ Session path configured: `SESSION_PATH=/tengo`
6. ❌ Apache converting POST to GET during `.htaccess` redirect

**Root Cause Hypothesis**:
When Apache does an internal redirect from `/tengo/api/auth/register` to `/tengo/public/index.php`, it's converting the POST request to GET. This is a known Apache behavior with 301/302 redirects.

**Attempted Fixes** (All Failed):
1. ❌ Added `QSA` flag to preserve query string
2. ❌ Added `PT` (passthrough) flag
3. ❌ Changed to RewriteBase
4. ❌ Added conditions to not rewrite files/dirs
5. ❌ Cleared all Laravel caches

**Current .htaccess in `/tengo/`**:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Redirect all requests to public subdirectory, preserving the request method
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ public/$1 [L,QSA]
</IfModule>
```

**Current .htaccess in `/tengo/public/`**:
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

**Possible Solutions to Try**:

#### Option A: Use Apache Alias (Requires Server Config Access)
```apache
# In SiteGround control panel or httpd.conf
Alias /tengo /home/customer/www/csjones.co/public_html/tengo/public
<Directory /home/customer/www/csjones.co/public_html/tengo/public>
    AllowOverride All
    Require all granted
</Directory>
```

#### Option B: Restructure Deployment (Move Everything)
```bash
# Move Laravel to a non-public location
# Make /tengo/ itself the public directory
mv tengo tengo-app
mv tengo-app/public/* tengo/
# Update paths in index.php
```

#### Option C: Use Symbolic Link at Server Level
```bash
# In SiteGround control panel, configure subdirectory
# Path: /tengo
# Document Root: /home/customer/www/csjones.co/public_html/tengo/public
```

#### Option D: Proxy Pass (If Available)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ /tengo/public/$1 [P,L,QSA]
```

#### Option E: Change Frontend API Base URL
```javascript
// In .env.production
VITE_API_BASE_URL=https://csjones.co/tengo/public

// This bypasses the first .htaccess redirect
```

---

## Server Configuration

### SiteGround Details
- **Host**: `uk71.siteground.eu`
- **Port**: `18765`
- **Username**: `u163-ptanegf9edny`
- **Domain**: `csjones.co`
- **Subdirectory**: `/tengo`

### Directory Structure
```
~/www/csjones.co/
├── public_html/
│   ├── tengo/                      # Laravel root
│   │   ├── .htaccess              # Redirects to public/
│   │   ├── app/
│   │   ├── bootstrap/
│   │   ├── config/
│   │   ├── database/
│   │   ├── public/                # Web root (should be)
│   │   │   ├── .htaccess         # Laravel routing
│   │   │   ├── index.php
│   │   │   └── build/            # Vite assets
│   │   │       ├── manifest.json
│   │   │       ├── .vite/
│   │   │       └── assets/
│   │   ├── resources/
│   │   ├── routes/
│   │   ├── storage/
│   │   └── vendor/
│   └── tengo-link -> tengo/public # Symbolic link (working)
└── build-files.tar.gz             # Uploaded build
```

### Database Configuration
```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=dbow3dj6o4qnc4
DB_USERNAME=uixybijdvk3yv
DB_PASSWORD=PixieRebecca2020
```

### Application Configuration (.env)
```env
APP_NAME="Fynla - Financial Planning System"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:0W/VOUin57ayBYmOgETvpiLrgUI359PrtPdtst+K914=
APP_URL=https://csjones.co/tengo

ASSET_URL=https://csjones.co/tengo
SESSION_PATH=/tengo

VITE_API_BASE_URL=https://csjones.co/tengo
```

### Verified Routes
```bash
php artisan route:list | grep register
# Output:
POST      api/auth/register ......... Api\AuthController@register
```

---

## File Locations

### Local Development (Mac)
- **Project Root**: `/Users/Chris/Desktop/fpsV2/`
- **Build Files**: `/Users/Chris/Desktop/fpsV2/public/build/`
- **Tarball**: `/Users/Chris/Desktop/build-files.tar.gz`

### Production Server
- **Application**: `~/www/csjones.co/public_html/tengo/`
- **Web Root**: `~/www/csjones.co/public_html/tengo/public/`
- **Build Files**: `~/www/csjones.co/public_html/tengo/public/build/`
- **Logs**: `~/www/csjones.co/public_html/tengo/storage/logs/laravel.log`

### Key Files Modified
1. **vite.config.js** - Base path: `/tengo/build/`
2. **resources/js/router/index.js** - Base path: `/tengo/`
3. **.env.production** - All URLs updated to `csjones.co/tengo`
4. **public/.htaccess** - Laravel routing rules
5. **tengo/.htaccess** - Redirect to public/ subdirectory

---

## Next Steps

### Immediate Actions Required
1. **Test Option E First** (Quickest to try):
   - Change VITE_API_BASE_URL to include `/public`
   - Rebuild assets locally
   - Upload new build-files.tar.gz
   - Extract on server
   - Test registration

2. **If Option E Fails, Try Option C**:
   - Contact SiteGround support or use control panel
   - Configure subdirectory `/tengo` → `/tengo/public`
   - This is the proper Laravel deployment method

3. **If Option C Not Available, Try Option B**:
   - Restructure deployment to avoid nested .htaccess
   - Make `/tengo/` itself the public directory

4. **Last Resort - Option A**:
   - Requires Apache config access (may need support ticket)

### Testing Checklist
Once POST issue is resolved:
- [ ] User registration works
- [ ] User login works
- [ ] Dashboard loads after login
- [ ] All module pages accessible
- [ ] API endpoints responding correctly
- [ ] Asset loading from `/tengo/build/` works
- [ ] Sessions persisting correctly
- [ ] Database operations working
- [ ] File uploads (if applicable)
- [ ] Email functionality (if configured)

### Documentation Updates Needed
Once deployment is successful:
- [ ] Update DEPLOYMENT_GUIDE.md with final working configuration
- [ ] Document the specific SiteGround setup steps
- [ ] Add troubleshooting section for common issues
- [ ] Create DEPLOYMENT_CHECKLIST.md
- [ ] Update README.md with production URL

---

## Commands Reference

### Cache Clearing (Run After Config Changes)
```bash
cd ~/www/csjones.co/public_html/tengo/
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

### Verify Configuration
```bash
# Check APP_KEY
grep APP_KEY .env

# Check database connection
php artisan db:show

# List routes
php artisan route:list | grep register

# Check config values
php artisan tinker --execute="echo config('app.url');"
```

### File Permissions (If Needed)
```bash
chmod 644 .env
chmod 644 .htaccess
chmod 644 public/.htaccess
chmod -R 775 storage bootstrap/cache
```

### Upload New Build Files
```bash
# Local Mac
cd /Users/Chris/Desktop/fpsV2/public/build/
tar -czf ~/Desktop/build-files.tar.gz manifest.json .vite/ assets/

# Upload via SiteGround File Manager to ~/www/csjones.co/

# Server
cd ~/www/csjones.co/public_html/tengo/public/build/
rm -rf assets .vite manifest.json
tar -xzf ~/www/csjones.co/build-files.tar.gz
```

---

## Error Logs

### Check Laravel Logs
```bash
# Latest errors
tail -50 ~/www/csjones.co/public_html/tengo/storage/logs/laravel.log

# Today's errors
grep "$(date +%Y-%m-%d)" ~/www/csjones.co/public_html/tengo/storage/logs/laravel.log
```

### Check Apache Logs (If Available)
SiteGround provides logs in control panel under Site Tools → Statistics → Error Log

---

## Contact Information

### SiteGround Support
- **Control Panel**: https://my.siteground.com/
- **Site Tools**: Access via control panel
- **Support Ticket**: Use if Apache Alias or DocumentRoot change needed

### Developer Notes
- All routing configured for `/tengo/` subdirectory
- Production build uses Vite with base path `/tengo/build/`
- Vue Router uses base path `/tengo/`
- Session cookies scoped to `/tengo` path
- API endpoints expect base URL `https://csjones.co/tengo`

---

**Last Updated**: October 29, 2025, 12:15 PM GMT
**Document Version**: 1.0
**Next Review**: After POST issue resolution
