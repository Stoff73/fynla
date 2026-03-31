# Deploy Notes — 29 March 2026

## Summary

Frontend-only changes across 9 Vue/JS files (2 commits). No PHP, no migrations, no seeders. Build already completed.

## Changed Files (additional to March 28)

| File | Change |
|------|--------|
| `resources/js/views/Public/FeaturesPage.vue` | Competitor comparison table with harvey balls, column borders |
| `resources/js/views/Public/CalculatorsPage.vue` | Two-column sidebar layout, horizon title boxes, button colours |
| `resources/js/views/Public/LandingPage.vue` | Homepage image v3, features link under How Fyn section |
| `resources/js/views/Public/stages/StartingOutPage.vue` | Feature section title + hover fix |
| `resources/js/views/Public/stages/BuildingFoundationsPage.vue` | Feature section title + hover fix |
| `resources/js/views/Public/stages/ProtectingAndGrowingPage.vue` | Feature section title + hover fix |
| `resources/js/views/Public/stages/PlanningYourFuturePage.vue` | Feature section title + hover fix |
| `resources/js/views/Public/stages/EnjoyingYourWealthPage.vue` | Feature section title + hover fix |

## Deploy Steps

### 1. Build already completed
Build is at `public/build/` (6.8MB) — ready to upload.

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
- Build already completed — just upload `public/build/`
- PR #170 is open — merge before deploying
- Mockup HTML files already deleted from public/
