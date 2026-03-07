# Deploy: CSS Centralisation

**Date:** 4 March 2026
**Branch:** centraliseUI
**Build:** `./deploy/fynla-org/build.sh` (passed)
**Tests:** 1603 passed (6827 assertions)

## Summary

Centralised all reusable CSS patterns into `app.css`, eliminated duplicated styles from ~65 component files, fixed CSS bugs, removed banned colours, and established CSS governance rules. Net result: **-1,110 lines** of duplicated CSS removed.

## What Changed

### CSS Infrastructure
- **`resources/css/app.css`** — Added badge classes, scrollbar utilities, animation utilities, range slider styling, expand/accordion transitions
- **`resources/css/badges.css`** — **DELETED** (orphaned file, never imported; merged into app.css)

### Bug Fixes (3 files)
- `components/Investment/RebalancingActions.vue` — Fixed `background-colour` (British spelling)
- `components/Estate/GiftingStrategy.vue` — Fixed `backgroundColour` in `:style=`
- `views/Help.vue` — Fixed `::v-deep mark` to `:deep(mark)`

### Colour Violations Fixed (8 files)
- `views/Actions/ActionsDashboard.vue` — Amber to blue, indigo hex to primary
- `views/Investment/AccountHoldingsPanel.vue` — Amber to blue
- `views/Investment/AccountFeesPanel.vue` — Amber to blue
- `views/Trusts/TrustDetailView.vue` — Amber to blue
- `views/Trusts/TrustsDashboard.vue` — Amber to blue
- `views/NetWorth/CashOverview.vue` — Amber to blue
- `components/NetWorth/BusinessInterestsList.vue` — Fuchsia to primary
- `views/Retirement/WhatIfScenarios.vue` — Indigo hex to primary

### Removed Duplicated CSS Patterns (~55 files)
- Removed `@keyframes fadeIn` from 17 files (using global `.animate-fade-in` / `.animate-fade-in-slide`)
- Removed `@keyframes spin` / `.spinner` from 13 files (using Tailwind `animate-spin`)
- Removed scrollbar CSS from 8 files (using global `.scrollbar-hide` / `.scrollbar-thin`)
- Removed range slider CSS from 7 files (using global styling)
- Removed expand/accordion CSS from 3 files (using global `.expand-*`)
- Removed `.line-clamp-2` CSS from 3 files (using Tailwind built-in)

### Back Button Standardisation (18 files)
Migrated `.back-button` to global `.detail-inline-back` in all inline detail views:
- `components/Protection/PolicyDetail.vue`
- `components/NetWorth/ChattelDetailInline.vue`
- `components/NetWorth/BusinessInterestDetailInline.vue`
- `components/NetWorth/TaxEfficiencyDetail.vue`
- `components/NetWorth/FeesDetail.vue`
- `components/NetWorth/PensionDetailInline.vue`
- `components/NetWorth/HoldingsDetail.vue`
- `components/NetWorth/InvestmentDetailInline.vue`
- `components/NetWorth/LiabilityDetailInline.vue`
- `components/NetWorth/InvestmentProjections.vue`
- `components/NetWorth/Property/PropertyDetailInline.vue`
- `components/NetWorth/StrategyDetail.vue`
- `components/Retirement/FutureValueTab.vue`
- `components/Retirement/CapitalAdequacyTab.vue`
- `components/Retirement/StrategiesTab.vue`
- `components/Retirement/RequiredCapitalDetail.vue`
- `components/Retirement/RetirementIncomeTab.vue`
- `views/Savings/SavingsAccountDetailInline.vue`

### Hardcoded Hex Replaced with @apply (7 files)
- `views/Trusts/TrustDetailView.vue`
- `views/Trusts/TrustsDashboard.vue`
- `views/Investment/AccountHoldingsPanel.vue`
- `views/Investment/AccountFeesPanel.vue`
- `views/Investment/AccountPerformancePanel.vue`
- `views/Investment/AccountSummaryPanel.vue`
- `views/NetWorth/CashOverview.vue`

### Documentation
- **`CLAUDE.md`** — Added rule #12 "CSS Governance"
- **`fynlaDesignGuide.md`** — Added "Global CSS Utilities" section

## Files to Upload

### 1. Build assets (required)
Upload entire `public/build/` directory to server.

### 2. Delete on server
```
resources/css/badges.css
```

### 3. No PHP changes
This is a frontend-only change. No PHP files need uploading.

## Post-Deploy SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Verification Checklist

- [ ] No amber/orange visible anywhere in the app
- [ ] Back buttons work in all inline detail views
- [ ] Spinners animate correctly (loading states)
- [ ] Range sliders styled correctly (WhatIfScenarios, DrawdownSimulator)
- [ ] Accordions expand/collapse smoothly (InvestmentTypes, LifeEvents, GoalStrategies)
- [ ] Badges display correctly (account types, status, risk levels)
- [ ] Fade-in animations work on page transitions
- [ ] Trusts module uses violet accent (not hardcoded hex)
- [ ] Scrollbars thin in modals/panels, hidden in nav tabs
