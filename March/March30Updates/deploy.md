# Deploy Notes — 30 March 2026

## Summary

Frontend-only changes across 50 Vue/JS files (2 commits on resources-pages branch). No PHP, no migrations, no seeders.

## Key Changes

- All resource page headers updated to match pricing/calculator style (48 pages)
- Journey maps with curvy SVG path on all 5 journey pages
- "What do I need to start my journey?" section with onboarding cards
- Persona selection modal redesigned with category grouping
- Footer social media icons (YouTube, Facebook, Instagram, TikTok)
- Insights page redesigned with article cards and category filters
- Article pages updated with back links, badges, light blue related links

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
- Frontend build is required (Vite)
- Delete mockup HTML files from public/ before deploying
- PR needs creating from resources-pages to main
