# Deployment Guide — 25 March 2026

**Branch:** `dashboard`
**Status:** Frontend-only changes (76 files). No PHP, no migrations, no seeders.

---

## Pre-Deploy: Merge to Main

```bash
git checkout main
git pull origin main
git merge dashboard
git push origin main
```

---

## Step 1: Build Frontend

```bash
./deploy/fynla-org/build.sh
```

This rebuilds `public/build/` with all Vue, JS, CSS, and Tailwind changes.

---

## Step 2: Upload

Upload the entire `public/build/` directory via SiteGround File Manager:

```
Local:  public/build/
Remote: ~/www/fynla.org/public_html/public/build/
```

Also upload `tailwind.config.js` if the server uses it for any SSR/build process:

```
Local:  tailwind.config.js
Remote: ~/www/fynla.org/public_html/tailwind.config.js
```

---

## Step 3: Clear Caches (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## What Changed (Summary)

### Dashboard Home (Batches 8-10)
- Grid breakpoint: `xl:grid-cols-3` for smaller desktops
- Card gradients render below content (z-index fix)
- 3px transparent border on cards, light-blue on hover
- 0% progress bars show "0%" left-aligned in horizon blue
- Empty cards (Protection, Estate) — no gradient or hover
- Cash & Savings card: sparkline chart + collapsible accounts
- Investments card: mirror pattern with sparkline + collapsible accounts
- Allowances: ISA sections link to /net-worth/cash, Pension to /retirement
- Goals bar chart: Horizon blue colour
- Mobile status bar: swipeable carousel with dot indicators

### Navigation & Settings
- Renamed "General" tab to "Settings" in account nav
- Security, Privacy, Assumptions now sub-tabs within Settings
- Removed "Your Information" from Settings (covered in User Profile)
- New SettingsTabBar component

### Module Pages
- CashOverview: account cards get grey gradient, Open Banking card light blue
- Income donut chart: designSystem colours replace hardcoded hex
- SubNavBar, SideMenu, ModuleStatusBar — various UI polish from earlier batches

### New Components
- `DashboardSparkline.vue` — reusable GA-style line chart
- `SettingsTabBar.vue` — shared settings tab navigation

---

## Warnings

- **No PHP changes** — no need to upload PHP files
- **No migrations** — database unchanged
- **No composer changes** — no need to run composer install
- **76 frontend files changed** — full `public/build/` upload recommended
