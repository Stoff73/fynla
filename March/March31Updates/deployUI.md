# Deploy Guide — Dashboard UI Updates

**Branch:** `dashboardUI`
**Date:** 31 March 2026

## Changes Summary

1. **Preview banner positioning** — moved above SubNavBar so it stays directly below the top nav on all module screens
2. **Session timeout fix** — await preview store init before session lifecycle, so persona users no longer get the 15-minute inactivity timeout
3. **Upgrade Now / Sign Up Now** — added to navbar (hidden when trial upgrade already showing) and updated sidebar; shows "Sign Up Now" linking to `/register` in preview mode, "Upgrade Now" linking to `/pricing` for real users
4. **Fyn chat icon** — centred in top navbar, opens docked chat panel on click (hidden in preview mode)

## Files Changed (Frontend Only)

| File | Change |
|------|--------|
| `resources/js/app.js` | Await `preview/initFromStorage` before session lifecycle init |
| `resources/js/components/Navbar.vue` | Upgrade/Sign Up link, centred Fyn chat button, `open-chat` emit, `isPreviewMode` computed |
| `resources/js/components/SideMenu.vue` | Upgrade/Sign Up context-aware label and route, expose `isPreviewMode` |
| `resources/js/layouts/AppLayout.vue` | Move PreviewBanner above SubNavBar, add `openChat` method, listen for `@open-chat` on Navbar |

## Deployment Steps

### 1. Build

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload via SiteGround File Manager

Upload `public/build/` to:
```
~/www/fynla.org/public_html/public/build/
```

No PHP files changed — frontend build only.

### 3. Clear Caches (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## No Database Changes

No migrations or seeders required.
