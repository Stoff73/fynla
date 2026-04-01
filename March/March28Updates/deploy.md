# Deploy Notes — 28 March 2026

## Summary

Frontend-only changes across 15 Vue/JS files (3 commits). No PHP, no migrations, no seeders.

## Changed Files

| File | Change |
|------|--------|
| `resources/js/views/Public/stages/StartingOutPage.vue` | Full redesign — template page |
| `resources/js/views/Public/stages/BuildingFoundationsPage.vue` | Full redesign matching template |
| `resources/js/views/Public/stages/ProtectingAndGrowingPage.vue` | Full redesign matching template |
| `resources/js/views/Public/stages/PlanningYourFuturePage.vue` | Full redesign matching template |
| `resources/js/views/Public/stages/EnjoyingYourWealthPage.vue` | Full redesign matching template |
| `resources/js/layouts/PublicLayout.vue` | Rename life stage to personal journey, mega menu eggshell bg |
| `resources/js/views/Public/LandingPage.vue` | Rename Scenarios to Your personal journey |
| `resources/js/components/Preview/PreviewBanner.vue` | White exit button, raspberry sign up, left-align, referrer nav |
| `resources/js/store/modules/preview.js` | Store/restore referrer and real user token on demo exit |
| `resources/js/services/api.js` | Add /preview/exit to auth endpoint exceptions |
| `resources/js/views/Login.vue` | Light-blue box, register link, homepage link, wishlist link |
| `resources/js/views/Register.vue` | Light-blue box, homepage link, wishlist link, T&C links |
| `resources/js/views/Public/PricingPage.vue` | Auth-aware CTAs, upgrade flow, features link |

## Deploy Steps

### 1. Build frontend locally
```bash
./deploy/fynla-org/build.sh
```

### 2. Upload via SiteGround File Manager
Upload `public/build/` directory to:
```
~/www/fynla.org/public_html/public/build/
```

### 3. SSH cache clear
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Notes
- No PHP files changed — no PHP upload needed
- No migrations — no database changes
- No new dependencies — no composer install needed
- Frontend build is required (Vite)
- Delete `public/mockup-starting-out.html` before deploying (dev artifact)
