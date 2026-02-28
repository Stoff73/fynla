# DEPLOYED: Privacy Policy Page

**Date:** 20 February 2026
**Branch:** `main`
**Status:** Built and deployed
**Build:** Production build completed (`./deploy/fynla-org/build.sh`)

---

## Rebuild Required?

**Yes, frontend rebuild required.** Vue components and router were modified.

```bash
./deploy/fynla-org/build.sh
```

Upload `public/build/` directory to `~/www/fynla.org/public_html/public/build/`

---

## Files Changed

### New Files (1)

| Local Path | Upload To |
|------------|-----------|
| `resources/js/views/Public/PrivacyPolicyPage.vue` | Not uploaded (compiled into build) |

### Modified Files (4)

| Local Path | Upload To |
|------------|-----------|
| `resources/js/router/index.js` | Not uploaded (compiled into build) |
| `resources/js/components/Footer.vue` | Not uploaded (compiled into build) |
| `resources/js/layouts/PublicLayout.vue` | Not uploaded (compiled into build) |
| `resources/js/views/Public/SitemapPage.vue` | Not uploaded (compiled into build) |

### Modified PHP Files (1)

| Local Path | Upload To |
|------------|-----------|
| `app/Http/Middleware/SecurityHeaders.php` | `~/www/fynla.org/public_html/app/Http/Middleware/SecurityHeaders.php` |

---

## What to Upload

Only the compiled build output:

| Local Path | Upload To |
|------------|-----------|
| `public/build/` (entire directory) | `~/www/fynla.org/public_html/public/build/` |
| `app/Http/Middleware/SecurityHeaders.php` | `~/www/fynla.org/public_html/app/Http/Middleware/SecurityHeaders.php` |

---

## What Changed

| Change | Files |
|--------|-------|
| **Privacy Policy page** (`/privacy`) | `PrivacyPolicyPage.vue`, `router/index.js` |
| **Footer links updated** | `Footer.vue` (Privacy Policy link), `PublicLayout.vue` (Privacy Policy link, Cookie Policy removed) |
| **Sitemap updated** | `SitemapPage.vue` (Privacy Policy added) |
| **CSP fix** | `SecurityHeaders.php` (added Vite to img-src, Google Fonts to style-src/font-src) |
| **Footer wording** | `Footer.vue`, `PublicLayout.vue` ("Financial Planning System" → "Financial Freedom Mapping", "financial planning" → "financial freedom") |

---

## Post-Upload

PHP file changed — clear caches after upload:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```
