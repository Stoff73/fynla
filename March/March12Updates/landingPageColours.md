# Landing Page Colour Scheme Update (WIP)

**Date:** 2026-03-12
**Branch:** uiImprovements
**Commit:** bd911dc
**Status:** Work in progress — solutions section needs refinement

## Files Changed

| File | Change |
|------|--------|
| `resources/js/views/Public/LandingPage.vue` | Hero gradient, dark feature cards, solutions rework |
| `resources/js/layouts/PublicLayout.vue` | Footer copyright update |

## Frontend Build Required

```bash
./deploy/fynla-org/build.sh
```

Upload `public/build/` to `~/www/fynla.org/public_html/public/build/`

## SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Notes

- Solutions section still needs design matching — do NOT deploy until finalised
- No PHP backend changes — frontend-only update
