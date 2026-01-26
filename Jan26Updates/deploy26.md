# Deployment Notes - January 26, 2026

## Security Enhancement: Session Management & Data Protection

This update implements strict session security to ensure users must authenticate on every visit with no persistent local data.

---

## Summary of Changes

| Category | Description |
|----------|-------------|
| Token Storage | Migrated from localStorage to sessionStorage |
| Financial Data | Removed all localStorage caching |
| Session Lifecycle | 15-min inactivity timeout; sessionStorage auto-clears on close |
| Logout UX | Added confirmation modal, dropdown closes on logout |
| Login UX | Removed "Remember me" checkbox (incompatible with session-only auth) |
| Backend | New beacon logout endpoint (kept for manual logout) |

## Session Behaviour

| Action | Session Result |
|--------|----------------|
| Page refresh (F5) | **Persists** |
| Tab switching | **Persists** |
| Browser/tab close | **Ends** (sessionStorage auto-clears) |
| 15 min inactivity | **Ends** |
| Manual logout | **Ends** |

---

## Rebuild Required: YES

Frontend JavaScript/Vue files have changed. Run the build script before uploading.

```bash
./deploy/fynla-org/build.sh
```

---

## Files Changed

### Frontend (JavaScript/Vue) - REBUILD REQUIRED

| File | Change Type |
|------|-------------|
| `resources/js/services/authService.js` | Modified |
| `resources/js/services/api.js` | Modified |
| `resources/js/services/sessionLifecycleService.js` | **NEW** |
| `resources/js/store/modules/userProfile.js` | Modified |
| `resources/js/store/modules/auth.js` | Modified |
| `resources/js/app.js` | Modified |
| `resources/js/components/Navbar.vue` | Modified (logout modal + dropdown close) |
| `resources/js/components/Auth/LogoutSuccessModal.vue` | **NEW** |
| `resources/js/views/Login.vue` | Modified (inactivity msg + removed "Remember me") |

### Backend (PHP) - Upload Directly

| File | Change Type |
|------|-------------|
| `app/Http/Controllers/Api/AuthController.php` | Modified |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | Modified |
| `routes/api.php` | Modified |

---

## Upload Checklist

### Step 1: Run Build
```bash
cd /Users/Chris/Desktop/fynla
./deploy/fynla-org/build.sh
```

### Step 2: Upload Built Assets
Upload the entire `public/build/` directory to:
```
~/www/fynla.org/public_html/public/build/
```

### Step 3: Upload PHP Files
Upload these files via SiteGround File Manager:

```
app/Http/Controllers/Api/AuthController.php
app/Http/Middleware/PreviewWriteInterceptor.php
routes/api.php
```

### Step 4: Clear Cache (SSH)
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear
php artisan route:clear
php artisan config:clear
```

---

## Verification Steps

1. **Logout flow**: Click logout → modal appears → redirected to login
2. **Browser close**: Login, close browser, reopen → should see login page
3. **Tab close**: Login in tab, close tab, open new tab → should see login page
4. **Inactivity**: Login, wait 15+ minutes idle → auto-logout with message
5. **Page refresh**: Login, press F5 → should stay logged in (sessionStorage survives refresh)
6. **No data leakage**: Login as User A, logout, login as User B → no User A data visible
7. **Check storage**: Open DevTools → Application → sessionStorage should have token, localStorage should have NO financial data

---

## Rollback Plan

If issues occur, restore previous versions of:
- `app/Http/Controllers/Api/AuthController.php`
- `routes/api.php`
- `public/build/` directory

The new sessionLifecycleService and LogoutSuccessModal are additive and won't break existing functionality if the old build is restored.
