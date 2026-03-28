# Deploy Notes — 26 March 2026

**Branch:** `dashboard`
**Commit:** `1141b9c7` — feat: unify donut charts to SVG ring style + net worth sub-page visual consistency
**Frontend rebuild required:** Yes

## Summary

- Unified all donut/pie charts from ApexCharts to custom SVG ring style (8 chart components)
- Standardised empty states across Property, Liabilities, Business pages
- Added account totals to Bank Accounts page
- Moved Investment page Bloomberg banner below data with Coming Soon badge
- Restructured Retirement page layout (50/50 planner cards, full-width chart, pension cards below)
- Fixed ChattelsList modal z-index with Teleport
- Added import dropdown with CSV template download to Personal Valuables

## Files Changed (15)

### Frontend — Rebuild Required

| File | Change |
|------|--------|
| `resources/js/components/Cash/SpendingDonutChart.vue` | SVG donut replacement |
| `resources/js/components/Investment/AssetAllocationChart.vue` | SVG donut replacement |
| `resources/js/components/Investment/HoldingsTable.vue` | SVG donut + hover state |
| `resources/js/components/Investment/PortfolioOptimizer.vue` | SVG donut replacement |
| `resources/js/components/NetWorth/AssetAllocationDonut.vue` | SVG donut replacement |
| `resources/js/components/NetWorth/BusinessInterestsList.vue` | Empty state: light-blue bg + horizon button |
| `resources/js/components/NetWorth/ChattelsList.vue` | Teleport fix + import dropdown |
| `resources/js/components/NetWorth/InvestmentList.vue` | Banner moved below data + Coming Soon badge |
| `resources/js/components/NetWorth/InvestmentProjections.vue` | SVG donut + mini legend |
| `resources/js/components/NetWorth/LiabilitiesList.vue` | Empty state: light-blue bg + horizon button |
| `resources/js/components/NetWorth/PensionList.vue` | Layout restructure: 50/50 planner cards, full-width chart, eggshell bg, module-gradient on chart/cards |
| `resources/js/components/NetWorth/PropertyList.vue` | Empty state: light-blue bg + horizon button + add property button |
| `resources/js/components/UserProfile/IncomeOccupation.vue` | SVG donut replacement |
| `resources/js/mobile/components/MobileAllocationChart.vue` | SVG donut replacement |
| `resources/js/views/NetWorth/CashOverview.vue` | Account totals per card type |

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
3. Non-palette colours (`pink-*`, `purple-*`, hardcoded hex) in several files
