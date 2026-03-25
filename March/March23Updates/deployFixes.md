# Deployment Guide — 23 March 2026

**Status: ALL DEPLOYED & VERIFIED ON PRODUCTION**

## What Changed

Frontend only — no PHP changes, no migrations, no database changes.

| File | Fix | Status |
|------|-----|--------|
| `resources/js/components/NetWorth/Property/PropertyForm.vue` | Property edit 422 — clean non-scalar payload values | Deployed & verified |
| `resources/js/views/Dashboard.vue` | Dashboard cards — `isStudentPersona` now preview-only | Deployed & verified |
| `resources/js/components/SideMenu.vue` | Sidebar 0% flash — hide progress until data loaded | Deployed & verified |

## Production Verification Results (23 March 2026)

1. **Property edit** — Clicked 15 Amherst Place → Edit → Save without changes → saved successfully, no 422 error
2. **Dashboard cards** — All module cards now visible with "Starting Out" journey: Net Worth, Protection, Cash & Savings, Investments, Estate Planning, Retirement, Allowances, Goals, Life Timeline
3. **Journey progress** — "Starting Out · 5 of 6 steps complete" showing correctly at 88%
4. **Sidebar progress** — Shows 88% without 0% flash on page load
5. **Journey change** — Changed from university to retirement via API, sidebar and dashboard updated correctly to 100% / "Enjoying Your Wealth", then changed back successfully

## Build & Upload (for reference)

```bash
# Build
./deploy/fynla-org/build.sh

# Upload public/build/ to:
~/www/fynla.org/public_html/public/build/

# Clear caches:
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear
```
