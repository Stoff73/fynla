# Jan 2, 2026 - Deployment Fixes

## Summary

Fixed deployment documentation and configuration files for fynla.org deployment on SiteGround shared hosting.

---

## Issues Fixed

### 1. SSH Connection Details Updated

**File:** `DEPLOYMENT_FYNLA_ORG.md`

Updated SSH connection command with correct credentials:
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
```

SSH key file changed from `~/.ssh/siteground_key` to `~/.ssh/production`.

---

### 2. Correct Server Path Structure

**File:** `DEPLOYMENT_FYNLA_ORG.md`

The actual server path is:
```
~/www/fynla.org/public_html/
```

NOT `~/public_html/` as previously documented.

Full structure:
```
~/www/fynla.org/
├── logs/
├── public_html/        <-- Laravel app goes here
│   ├── .htaccess       <-- Root redirect to public/
│   ├── app/
│   ├── public/
│   │   ├── .htaccess   <-- Laravel routing
│   │   └── index.php
│   └── ...
└── webstats/
```

---

### 3. Dual .htaccess Setup

**Files:**
- `deploy/fynla-org/.htaccess.root` (NEW)
- `deploy/fynla-org/.htaccess` (updated)
- `deploy/fynla-org/build.sh` (updated)

Two `.htaccess` files are required:

1. **Root htaccess** (`public_html/.htaccess`) - Redirects all requests to `public/`:
   ```apache
   <IfModule mod_rewrite.c>
       RewriteEngine On
       RewriteRule ^(.*)$ public/$1 [L]
   </IfModule>
   ```

2. **Laravel htaccess** (`public_html/public/.htaccess`) - Handles routing, security, caching.

The build script now copies both files automatically.

---

### 4. DirectoryMatch Not Allowed on Shared Hosting

**File:** `deploy/fynla-org/.htaccess`

**Problem:** The `<DirectoryMatch>` directive causes a 500 error on SiteGround shared hosting:
```
[apache][core:alert] .htaccess: <DirectoryMatch not allowed here
```

**Solution:** Replaced with RewriteRule:

Before (BROKEN):
```apache
<DirectoryMatch "\.git">
    Order allow,deny
    Deny from all
</DirectoryMatch>
```

After (FIXED):
```apache
# Block access to .git directories (using RewriteRule for shared hosting compatibility)
RewriteRule ^\.git - [F,L]
```

---

### 5. Deployment Package Naming Consistency

**Files:** `DEPLOYMENT_FYNLA_ORG.md`, `deploy/fynla-org/build.sh`

All references now consistently use `fynla-org-deploy.zip` and `fynla-org-deploy/` folder name.

---

## Files Changed

| File | Change |
|------|--------|
| `DEPLOYMENT_FYNLA_ORG.md` | SSH credentials, server paths, directory structure, dual .htaccess docs |
| `deploy/fynla-org/.htaccess` | Removed DirectoryMatch, replaced with RewriteRule |
| `deploy/fynla-org/.htaccess.root` | NEW - Root redirect file |
| `deploy/fynla-org/build.sh` | Now copies both .htaccess files |

---

## Server Fix (if already deployed)

If you already deployed and are getting 500 errors, fix the `.htaccess` on the server:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org

# Remove the DirectoryMatch directive
sed -i '/<DirectoryMatch/,/<\/DirectoryMatch>/d' ~/www/fynla.org/public_html/public/.htaccess

# Clear cache
cd ~/www/fynla.org/public_html
php artisan cache:clear
```
