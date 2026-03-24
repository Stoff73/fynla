# Deploy Guide — fixBugs Branch (PR #159)

**Date:** 24 March 2026
**Branch:** fixBugs → merged to main
**PR:** #159

---

## Changes

### Frontend Only (no PHP changes)

| File | Change |
|------|--------|
| `resources/js/components/NetWorth/InvestmentList.vue` | Replaced purple info banner with hover `i` tooltip icon next to "Investments" heading |
| `resources/js/components/NetWorth/AssetAllocationDonut.vue` | Reduced donut charts by 25% (height 260→195, container 340→255px, mobile 240→180px) |
| `resources/js/store/modules/auth.js` | Always sync lifeStage on login (fixes stale state causing onboarding skip) |
| `resources/js/store/modules/lifeStage.js` | Added resetState mutation |
| `app/Http/Middleware/SecurityHeaders.php` | CSP update for Vite dev server |
| `resources/js/services/api.js` | Dev server base URL fix |
| `vite.config.js` | Dev server config |

---

## Deploy Steps

### 1. Build locally

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload to server

Upload `public/build/` directory to:
```
~/www/fynla.org/public_html/public/build/
```

### 3. Upload changed PHP file

```
app/Http/Middleware/SecurityHeaders.php
```

### 4. Clear caches (SSH)

```bash
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize
```

---

## No database changes required
## No migrations required
## No seeding required
