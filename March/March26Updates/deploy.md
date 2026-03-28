# Deploy Notes — 26 March 2026

**Branch:** `dashboard`
**Commits:** `1141b9c7`, `af98b79d`, `e6d655af` (3 commits)
**Frontend rebuild required:** Yes

## Summary

### Commit 1: Donut chart unification + net worth sub-page consistency
- Unified all donut/pie charts from ApexCharts to custom SVG ring style (8 chart components)
- Standardised empty states across Property, Liabilities, Business pages
- Added account totals to Bank Accounts page
- Moved Investment page Bloomberg banner below data with Coming Soon badge
- Restructured Retirement page layout (50/50 planner cards, full-width chart, pension cards below)
- Fixed ChattelsList modal z-index with Teleport
- Added import dropdown with CSV template download to Personal Valuables

### Commit 2: Eggshell backgrounds, module-gradient cards, layout restructures
- Replaced hardcoded hex (#FAD6E0/#F5B3C5) with light-pink palette tokens (6 files)
- Eggshell backgrounds on: Income, Property, Liabilities, Valuables, Risk Profile, Business, Investments
- Module-gradient on individual cards: PropertyCard, LiabilityCard, ChattelCard, BusinessInterestCard, Risk sections
- Grey hover borders replacing pink/raspberry across all card components
- Teleport fix on Property, Liabilities, Investment modals
- Sub-nav CTAs: Add Liability, Add Valuable, Import, Add Business
- Removed page titles (Liabilities, Valuables, Business, Risk Profile), left-aligned filters
- All empty states: bg-light-blue-100 + horizon-500 buttons (16 files)
- Wealth Summary: light-pink hover, larger text hierarchy, light-blue section headers
- Dashboard sparkline: larger markers (size 6), white fill + coloured border
- Analytics coming soon box with Bloomberg/Morningstar/FE Analytics pills

### Commit 3: Net worth page — pie chart + bar chart
- Pie chart: cursor-following hover tooltip coloured to match segment
- Assets & Liabilities bar chart (positive above x-axis, negative below)
- Layout: pie chart + bar chart side by side, wealth summary full-width below
- Pie chart card height matches bar chart card height

## Files Changed (48)

### Frontend — Rebuild Required

| File | Change |
|------|--------|
| `resources/js/components/Cash/AccountGroupList.vue` | Hex → light-pink tokens |
| `resources/js/components/Cash/SpendingDonutChart.vue` | SVG donut replacement |
| `resources/js/components/Dashboard/DashboardSparkline.vue` | Larger markers, stronger gradient |
| `resources/js/components/Estate/DualGiftingTimeline.vue` | Empty state styling |
| `resources/js/components/Investment/AssetAllocationChart.vue` | SVG donut replacement |
| `resources/js/components/Investment/HoldingsTable.vue` | SVG donut + hover |
| `resources/js/components/Investment/Performance.vue` | Empty state styling |
| `resources/js/components/Investment/PortfolioOptimizer.vue` | SVG donut replacement |
| `resources/js/components/Investment/PortfolioOverview.vue` | Empty state + button styling |
| `resources/js/components/NetWorth/AssetAllocationDonut.vue` | SVG donut, hover tooltip, sizing |
| `resources/js/components/NetWorth/AssetBreakdownBar.vue` | New: assets vs liabilities bar chart |
| `resources/js/components/NetWorth/BusinessInterestCard.vue` | Module-gradient + grey hover |
| `resources/js/components/NetWorth/BusinessInterestsList.vue` | Eggshell bg, sub-nav CTA |
| `resources/js/components/NetWorth/ChattelCard.vue` | Module-gradient + grey hover |
| `resources/js/components/NetWorth/ChattelsList.vue` | Teleport, import, sub-nav CTA, eggshell |
| `resources/js/components/NetWorth/InvestmentList.vue` | Full-width chart, eggshell, Analytics box |
| `resources/js/components/NetWorth/InvestmentProjections.vue` | SVG donut + mini legend |
| `resources/js/components/NetWorth/LiabilitiesList.vue` | Teleport, sub-nav CTA, eggshell |
| `resources/js/components/NetWorth/LiabilityCard.vue` | Module-gradient + grey hover |
| `resources/js/components/NetWorth/NetWorthWealthSummary.vue` | Layout: charts row + summary below |
| `resources/js/components/NetWorth/PensionList.vue` | Layout restructure, eggshell, module-gradient |
| `resources/js/components/NetWorth/PropertyCard.vue` | Module-gradient + grey hover |
| `resources/js/components/NetWorth/PropertyList.vue` | Teleport, eggshell, empty state |
| `resources/js/components/NetWorth/WealthSummary.vue` | Pink hover, text sizes, section headers |
| `resources/js/components/Retirement/CapitalAdequacyTab.vue` | Empty state styling |
| `resources/js/components/Retirement/FutureValueTab.vue` | Empty state styling |
| `resources/js/components/Retirement/RetirementIncomeTab.vue` | Empty state styling |
| `resources/js/components/Savings/CurrentSituation.vue` | Hex tokens + empty state |
| `resources/js/components/Shared/ModuleStatusBar.vue` | Hex → light-pink-100 class |
| `resources/js/components/UserProfile/IncomeOccupation.vue` | SVG donut, module-gradient cards |
| `resources/js/constants/subNavConfig.js` | Add CTAs for Liabilities, Valuables, Business |
| `resources/js/mobile/components/MobileAllocationChart.vue` | SVG donut replacement |
| `resources/js/views/Investment/AccountHoldingsPanel.vue` | Empty state styling |
| `resources/js/views/Investment/AccountPerformancePanel.vue` | Empty state styling |
| `resources/js/views/NetWorth/CashOverview.vue` | Totals + hex tokens |
| `resources/js/views/Risk/RiskProfilePage.vue` | Eggshell, module-gradient cards |
| `resources/js/views/ValuableInfo.vue` | Eggshell bg, module-gradient wrapper |

### No PHP Changes

No backend files changed in this session.

## Build & Deploy Steps

```bash
# 1. Build frontend locally
./deploy/fynla-org/build.sh

# 2. Upload public/build/ via SiteGround File Manager
# Target: ~/www/fynla.org/public_html/public/build/

# 3. SSH to clear caches
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Tech Debt Notes

28 issues found (see `tech-debt-report.md`). Top 3:
1. `lightenColor` duplicated in 8 files — extract to `utils/color.js`
2. `this._uid` in AssetAllocationDonut — should be `this.$.uid` for Vue 3
3. Non-palette colours (`pink-*`, `purple-*`) still in several files
