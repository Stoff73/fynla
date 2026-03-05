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
16. Estate module: all cards use `border border-light-gray`, no coloured heavy borders
17. Estate module: no `gray-*`, `green-*`, `red-*`, `blue-*`, `indigo-*`, `purple-*`, `pink-*`, `emerald-*`, `yellow-*`, `teal-*` tokens remaining

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

### Estate Module Palette Migration (26 files)

All estate components migrated to `fynlaDesignGuide.md` v1.2.0 palette:

| Token Replaced | New Token |
|---------------|-----------|
| `gray-200` | `light-gray` |
| `green-*` | `spring-*` |
| `red-*` | `raspberry-*` |
| `blue-*` | `horizon-*` / `violet-*` |
| `indigo-*` | `violet-*` / `horizon-*` |
| `purple-*` | `violet-*` |
| `pink-*` | `raspberry-*` |
| `emerald-*` | `spring-*` |
| `yellow-*` | `violet-*` |
| `teal-*` | `horizon-*` |
| `error-*` | `raspberry-*` |
| `success-*` | `spring-*` |
| `border-l-4` | `border` (uniform borders) |
| `border-2 border-{color}-500` | `border border-light-gray` (consistent card style) |

**Files changed:** AssetsLiabilities, CashFlow, DualGiftingTimeline, EstateOverviewCard, EstateProjectionComparison, GiftCard, GiftForm, GiftingStrategy, GiftingTimelineChart, IHTAssetBreakdown, IHTCalculationTable, IHTLiabilityBreakdown, IHTMitigationStrategies, IHTPlanning, IntestacyRules, LiabilityForm, LifeCoverRecommendations, LifePolicyStrategy, MissingDataAlert, NRBRNRBTracker, SpouseExemptionNotice, TrustForm, TrustPlanning, TrustPlanningStrategy, WillPlanning, EstateDashboard

### IHT Summary Card Fix (uiUpdates branch)

Fixed mismatch between the IHT summary card at the top of the estate module and the calculation table below it.

**Root cause (3 issues):**
1. **`IHTController.php`** — Controller recalculated projected IHT with hardcoded `* 0.40`, ignoring the charitable 36% rate from `IHTCalculationService`
2. **`IHTPlanning.vue` summary card** — Read values from separate data properties (`ihtData`, `projection`) instead of `standardTableProps` (the same computed object that feeds the calculation table)
3. **`IHTPlanning.vue`** — Redundant frontend `adjustedIHTLiability*` computed properties recalculated IHT independently, diverging from backend values

**Fix:**
- Controller now uses `$calculation['iht_rate']` instead of hardcoded `0.40`
- Summary card and life policy card now read from `standardTableProps` — guaranteed to match the table
- Removed 9 unused computed properties (`effectiveIHTRate`, `adjustedIHTLiability`, `adjustedIHTLiabilityProjected`, `adjustedIHTLiabilityMinus5`, `adjustedIHTLiabilityPlus5`, `adjustedSecondDeathIHTLiabilityNow`, `adjustedSecondDeathIHTLiabilityProjected`, `adjustedSecondDeathIHTLiabilityMinus5`, `adjustedSecondDeathIHTLiabilityPlus5`)

**Files changed:**
```
app/Http/Controllers/Api/Estate/IHTController.php  (1 line)
resources/js/components/Estate/IHTPlanning.vue      (6 added, 53 removed)
```

### Side Menu Reorganisation (uiUpdates branch)

Restructured sidebar navigation headings and link order.

**New menu structure:**
| Section | Items |
|---------|-------|
| Main | Dashboard, Net Worth |
| Current | Cash, Protection, Investments, Retirement, Estate Planning, Trusts, Business, Personal Valuables, Income, Expenditure, Risk Profile. Will + Expression of Wishes shown here for single users. |
| Planning | Holistic Plan, Plans, Goals, Life Events, Actions |
| Family | Will, Letter to Spouse *(only shown when user has spouse/partner)* |
| Account | User Profile, Settings |
| Support | Help, Feedback, Bug Report |
| Admin | Admin Panel, UK Taxes *(admin only)* |

**Key changes:**
- "Planning" section renamed to "Current"
- "Advanced" section removed — items redistributed
- "Plans & Actions" renamed to "Planning" — Goals and Life Events now separate links
- New "Family" section (conditional on `hasSpouse`) with Will and Letter to Spouse
- Single users see Will + Expression of Wishes under Current instead
- Income → `/valuable-info?section=income`, Expenditure → `/valuable-info?section=expenditure`
- Will → `/valuable-info?section=will`, Letter to Spouse → `/valuable-info?section=letter`
- Life Events → `/goals?tab=events` (switches to events tab on Goals dashboard)
- Valuable Info removed from Account section (content accessible via individual sidebar links)
- `SideMenuItem` `to` prop now accepts `String | Object` for query param navigation
- `hasSpouse` checks `preview/hasSpouse` in preview mode, `spousePermission/hasSpouse` otherwise

**Files changed:**
```
resources/js/components/SideMenu.vue        (menu structure, hasSpouse logic, active state helpers)
resources/js/components/SideMenuIcon.vue    (5 new icons: currency-pound, arrow-up-tray, calendar, document-check, envelope)
resources/js/components/SideMenuItem.vue    (to prop accepts String|Object)
resources/js/views/ValuableInfo.vue         (watcher for route query → active tab sync)
resources/js/views/Goals/GoalsDashboard.vue (tab query param support + watcher)
```

### Actions Dashboard Rebuild (uiUpdates branch)

Rebuilt the Actions page to properly load and display recommendations from all modules.

**Problems fixed:**
1. **`ActionsDashboard.vue`** — Missing `AppLayout` wrapper so page rendered without nav/sidebar. Raw CSS with hardcoded hex instead of Tailwind/design system. Imported `RecommendationFilters` component unnecessarily.
2. **`recommendations.js` store** — API calls used `/api/recommendations` but axios `baseURL` is already `/api`, causing double prefix `/api/api/recommendations` → 404.
3. **`RecommendationsAggregatorService.php`** — Passed raw agent response arrays (containing `[success, message, data, timestamp]`) to `formatRecommendations()` instead of unwrapping the `data` payload. Each module's recommendations lived in different structures that the aggregator never read.

**Aggregator fixes per module:**
- **Protection** — reads `$analysis['data']['recommendations']` + extracts coverage gaps
- **Savings** — extracts emergency fund recommendation (skips "Excellent" status) and ISA remaining allowance
- **Retirement** — unwraps agent response, generates recommendation from income shortfall
- **Estate** — reads `implementation_timeline` actions with IHT savings/costs (was looking for non-existent `recommendations` key)

**Files changed:**
```
resources/js/views/Actions/ActionsDashboard.vue                 (full rewrite — AppLayout, Tailwind, currencyMixin, inline filters)
resources/js/store/modules/recommendations.js                   (fixed /api/ double prefix on all endpoints)
app/Services/Coordination/RecommendationsAggregatorService.php  (proper data extraction from each module)
```

### Life Event Icons on Monte Carlo Charts (uiUpdates branch)

Replaced text labels on life event annotations in retirement and investment Monte Carlo projection charts with emoji icons. Added hover tooltips showing event name and amount.

**Changes:**
- Annotation labels now show SVG icons (Heroicons from `eventIconSvgs.js`) via ApexCharts point annotations with `image` property
- Icons rendered as data URI SVGs via point annotations, positioned at 93% of max chart value (spaced from top)
- xaxis annotations provide the dotted vertical lines (no labels), point annotations provide the icons
- Hover tooltip appears when mouse is near an icon, showing event name and formatted amount
- Icon colour: spring green (income events) or red (expense events)
- Uses `LIFE_EVENT_ICONS` for icon selection and `EVENT_ICON_SVGS` for SVG path data

**Files changed:**
```
resources/js/components/Retirement/PensionPotProjectionChart.vue   (icon annotations, tooltip, getEventIcon, handleChartMouseMove)
resources/js/components/Investment/InvestmentProjectionChart.vue    (same changes as retirement chart)
```
