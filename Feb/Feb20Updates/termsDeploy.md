# Deploy: Terms of Service Page

**Date:** 20 February 2026
**Branch:** `main`

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
| `resources/js/views/Public/TermsOfServicePage.vue` | Not uploaded (compiled into build) |

### Modified Files (4)

| Local Path | Upload To |
|------------|-----------|
| `resources/js/router/index.js` | Not uploaded (compiled into build) |
| `resources/js/components/Footer.vue` | Not uploaded (compiled into build) |
| `resources/js/layouts/PublicLayout.vue` | Not uploaded (compiled into build) |
| `resources/js/views/Public/SitemapPage.vue` | Not uploaded (compiled into build) |

All changes are frontend-only (Vue/JS). No PHP, config, or migration files were modified.

---

## What to Upload

Only the compiled build output:

| Local Path | Upload To |
|------------|-----------|
| `public/build/` (entire directory) | `~/www/fynla.org/public_html/public/build/` |

---

## What Changed

| Change | Files |
|--------|-------|
| **Terms of Service page** (`/terms`) | `TermsOfServicePage.vue`, `router/index.js` |
| **Footer links updated** | `Footer.vue` (Terms of Service link), `PublicLayout.vue` (Terms of Service link) |
| **Sitemap updated** | `SitemapPage.vue` (Terms of Service added) |

---

## Post-Upload

No cache clears required for frontend-only changes. The new build assets have hashed filenames and will be picked up automatically.
