# Deployment Guide: Design System Overhaul v1.2.0

**Date:** 05 March 2026
**Branches:** `centraliseUI`, `uiUpdates`
**Scope:** 479+ files changed

## What Changed

Complete visual rebrand from `designStyle.md` (v1.1.0) to `fynlaDesignGuide.md` (v1.2.0):

| Element | Old | New |
|---------|-----|-----|
| CTA buttons | Trust Blue (#1257A0) | Raspberry (#E83E6D) |
| Text/navigation | Slate grays | Horizon (#1F2A44) |
| Page background | Cool gray (#F9FAFB) | Warm eggshell (#F7F6F4) |
| Success states | Green | Spring Green (#20B486) |
| Warning states | Blue | Violet (#5854E6) |
| Error states | Red | Raspberry (#E83E6D) |
| Focus rings | Primary blue | Violet (#5854E6) |
| Font stack | Inter | Segoe UI, Inter (fallback) |
| Hover states | gray-50 | Savannah (#FDFAF7) |
| Logos | Scattered across 4+ directories | Consolidated to `public/images/logos/` |
| Module life events | Shown on retirement, investment, estate dashboards | Removed from all three |
| Recommended strategies | Cards on retirement + investment dashboards | Removed (StrategiesTab, strategy card, PortfolioStrategyPanel) |
| EstateLifeEventsImpact | `success-*`/`error-*` tokens, `border-l-4` side highlights | `spring-*`/`raspberry-*` palette, uniform borders |

## Pre-Deployment Checklist

- [x] `./deploy/fynla-org/build.sh` passes
- [x] `./vendor/bin/pest` — 1603 tests pass (7043 assertions)
- [x] `php artisan db:seed` runs cleanly
- [x] 0 orphaned `primary-*`, `gray-*`, `#1257A0`, `#F59E0B` references
- [x] All logos consolidated to `public/images/logos/`
- [x] Old `resources/js/assets/` directory removed

## Files to Upload

### 1. Frontend Build (required)
Upload entire `public/build/` directory:
```
~/www/fynla.org/public_html/public/build/
```

### 2. Tailwind Config
```
tailwind.config.js
```

### 3. CSS
```
resources/css/app.css
```

### 4. JavaScript Constants
```
resources/js/constants/designSystem.js
resources/js/constants/eventIcons.js
```

### 5. All Vue Components (~400+ files)
Due to the scale (443 files), upload the entire `resources/js/` directory:
```
resources/js/
```

### 6. Logo Assets (new consolidated directory)
Upload entire `public/images/logos/` directory:
```
public/images/logos/LogoHiResFynlaDark.png   (nav logos)
public/images/logos/LogoHiResFynlaLight.png  (footer logos)
public/images/logos/logoTransparent.png      (legacy — no longer referenced)
public/images/logos/logoMain.png             (email templates)
public/images/logos/favicon.png              (browser tab, collapsed sidebar)
public/images/logos/favicon.ico              (browser tab)
```

### 7. Blade Templates (favicon + email logo paths)
```
resources/views/app.blade.php
resources/views/emails/*.blade.php  (all 11 email templates)
```

### 8. Documentation (optional for production)
```
fynlaDesignGuide.md
designStyle-legacy.md (renamed from designStyle.md)
CLAUDE.md
```

## Deployment Steps

1. **Build locally:**
   ```bash
   ./deploy/fynla-org/build.sh
   ```

2. **Upload via SiteGround File Manager:**
   - Upload `public/build/` to `~/www/fynla.org/public_html/public/build/`
   - Upload `public/images/logos/` to `~/www/fynla.org/public_html/public/images/logos/`
   - Upload `resources/js/` to `~/www/fynla.org/public_html/resources/js/`
   - Upload `resources/css/app.css` to `~/www/fynla.org/public_html/resources/css/`
   - Upload `resources/views/app.blade.php` to `~/www/fynla.org/public_html/resources/views/`
   - Upload `resources/views/emails/` to `~/www/fynla.org/public_html/resources/views/emails/`
   - Upload `tailwind.config.js` to `~/www/fynla.org/public_html/`

3. **SSH and clear caches:**
   ```bash
   ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
   cd ~/www/fynla.org/public_html
   php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
   ```

4. **Reseed (if needed):**
   ```bash
   php artisan db:seed
   ```

## Post-Deployment Verification

1. Login and Register pages show new hi-res logo (`LogoHiResFynlaDark.png`) and raspberry CTAs
2. Page background is warm eggshell (#F7F6F4), not cool gray
3. Navigation bar uses dark logo, footer uses light logo
4. Form inputs have violet focus rings
5. Success messages use spring green
6. Warning badges/alerts use violet
7. No amber or orange colours visible anywhere
8. Charts render with new colour palette
9. All preview personas load correctly
10. Retirement dashboard: no life events, no goal strategies, no recommended strategies card
11. Investment dashboard: no life events, no goal strategies, no strategy card
12. Estate dashboard: no ModuleLifeEvents card at top
13. EstateLifeEventsImpact: spring/raspberry colours, no side highlight bars
14. Print headers and PDF exports use new hi-res logo
15. Onboarding focus area selection shows new logo

## Rollback

If rollback needed, revert to the previous `public/build/` directory and source files. The database schema is unchanged — no migrations were involved.

### Files to Remove from Server

These old files are no longer used and can be deleted:
```
public/favicon.png                              (moved to public/images/logos/)
public/favicon.ico                              (moved to public/images/logos/)
public/images/logoMain.png                      (moved to public/images/logos/)
resources/js/components/Holistic/               (entire directory — 8 files, replaced by Plans/Holistic/)
resources/js/services/holisticService.js        (replaced by individual module plan services)
resources/js/store/modules/holistic.js          (removed from Vuex store)
```

## No Backend Logic Changes

No PHP controllers, services, models, or migrations were modified. Changes are limited to:
- Frontend (Vue components, CSS, Tailwind config, JS constants)
- Blade templates (favicon paths in `app.blade.php`, logo paths in email templates)
- Static assets (logo consolidation to `public/images/logos/`)

## Logo Reference Map

All logo references now use the centralised `public/images/logos/` directory:

| Logo File | Used By |
|-----------|---------|
| `LogoHiResFynlaDark.png` | Navbar, Login, Register, Onboarding, PrintHeader, planPrintMixin, LetterToSpouse, PublicLayout (header) |
| `LogoHiResFynlaLight.png` | Footer, PublicLayout (footer) |
| `favicon.png` | `app.blade.php` |
| `favicon.ico` | `app.blade.php` |
| `logoMain.png` | Email templates (11 blade files) |
| `logoTransparent.png` | No longer referenced — legacy only |

## UI Cleanup (uiUpdates branch)

Removed dashboard clutter from retirement, investment, and estate modules:

| Component | What was removed |
|-----------|-----------------|
| `PensionList.vue` | ModuleLifeEvents, ModuleGoalStrategies, Recommended Strategies card, StrategiesTab, all strategy computed/styles (~460 lines) |
| `InvestmentList.vue` | ModuleLifeEvents, ModuleGoalStrategies, Strategy card, PortfolioStrategyPanel import, strategy methods/computed |
| `EstateDashboard.vue` | ModuleLifeEvents |
| `EstateLifeEventsImpact.vue` | Migrated `success-*`/`error-*` → `spring-*`/`raspberry-*`, removed `border-l-4` side highlights |
