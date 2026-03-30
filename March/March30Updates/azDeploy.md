# Deploy Guide — Content Branch Merge (30 March 2026)

## Summary

PR #170 merged (`content` branch). Website redesign: personal journey pages, features comparison page, calculators page, preview banner, login/register styling, pricing page auth-awareness, mega menu updates, dashboard overhaul, and chart component updates across all modules.

**Frontend-only deploy** — no PHP, migration, or seeder changes.

**Note:** The brett-v1 merge (PR #169, 28 March) build was also not yet uploaded. This deploy covers BOTH PR #169 and #170. A rebuild is required before upload.

## Pre-Deploy: Rebuild

The existing `public/build/` was built before PR #170 was merged. You must rebuild:

```bash
./deploy/fynla-org/build.sh
```

## Upload

Upload the entire `public/build/` directory to production:

```
~/www/fynla.org/public_html/public/build/
```

This is the only upload needed. All changed Vue/JS/CSS files are compiled into the Vite build output.

## Post-Deploy: Clear Caches

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Changed Files (PR #170 — 70 files, +2,957/-2,024)

### New Files
- `resources/js/views/Public/FeaturesPage.vue` — competitor comparison page with harvey ball table

### Major Changes
- `resources/js/views/Dashboard.vue` — extensive restructure (+1,083 lines)
- `resources/js/layouts/PublicLayout.vue` — mega menu, nav, footer updates (+399 lines)
- `resources/js/views/Public/HowItWorksPage.vue` — redesigned (-552 lines rewrite)
- `resources/js/views/Public/CalculatorsPage.vue` — two-column sidebar layout (+430 lines)
- `resources/js/views/Public/PricingPage.vue` — auth-aware CTAs (+259 lines)

### Personal Journey Pages (redesigned with consistent template)
- `resources/js/views/Public/stages/StartingOutPage.vue`
- `resources/js/views/Public/stages/BuildingFoundationsPage.vue`
- `resources/js/views/Public/stages/ProtectingAndGrowingPage.vue`
- `resources/js/views/Public/stages/PlanningYourFuturePage.vue`
- `resources/js/views/Public/stages/EnjoyingYourWealthPage.vue`

### Auth Pages
- `resources/js/views/Login.vue` — light-blue rounded box with homepage/wishlist links
- `resources/js/views/Register.vue` — matching redesign

### Navigation & Preview
- `resources/js/components/Navbar.vue` — "Your personal journey" mega menu, eggshell backgrounds
- `resources/js/components/Preview/PreviewBanner.vue` — white exit button, raspberry sign up
- `resources/js/components/Footer.vue` — minor update
- `resources/js/store/modules/preview.js` — demo exit returns to referrer page
- `resources/js/router/index.js` — new /features route
- `resources/js/services/api.js` — minor update

### Public Pages
- `resources/js/views/Public/LandingPage.vue` — updated header image, features link

### Settings Pages
- `resources/js/views/Settings.vue`
- `resources/js/views/Settings/AssumptionsSettings.vue`
- `resources/js/views/Settings/PrivacySettings.vue`
- `resources/js/views/Settings/SecuritySettings.vue`

### Dashboard Components
- `resources/js/components/Dashboard/DashboardCard.vue`
- `resources/js/components/Dashboard/GoalsProjectionChartDashboard.vue`
- `resources/js/components/Dashboard/GoalsProjectionChartMini.vue`
- `resources/js/components/Dashboard/LifeTimelineCard.vue`

### Chart Components (designSystem.js import updates)
- `resources/js/components/Cash/BalanceTrendChart.vue`
- `resources/js/components/Estate/CashFlowProjectionChart.vue`
- `resources/js/components/Estate/GiftingTimelineChart.vue`
- `resources/js/components/Estate/IHTLiabilityGauge.vue`
- `resources/js/components/Estate/NetWorthWaterfallChart.vue`
- `resources/js/components/Goals/GoalsProjectionChart.vue`
- `resources/js/components/Investment/AllocationComparison.vue`
- `resources/js/components/Investment/AssetLocationOptimizer.vue`
- `resources/js/components/Investment/BenchmarkComparison.vue`
- `resources/js/components/Investment/ContributionPlanner.vue`
- `resources/js/components/Investment/CorrelationMatrix.vue`
- `resources/js/components/Investment/EfficientFrontier.vue`
- `resources/js/components/Investment/FeeSavingsCalculator.vue`
- `resources/js/components/Investment/GeographicAllocationMap.vue`
- `resources/js/components/Investment/GoalProjection.vue`
- `resources/js/components/Investment/InvestmentProjectionChart.vue`
- `resources/js/components/Investment/MonteCarloResults.vue`
- `resources/js/components/Investment/PerformanceAttribution.vue`
- `resources/js/components/Investment/PerformanceLineChart.vue`
- `resources/js/components/Investment/WrapperOptimizer.vue`
- `resources/js/components/Plans/Investment/AccountFeeProjectionChart.vue`
- `resources/js/components/Plans/Retirement/CascadingActionChart.vue`
- `resources/js/components/Plans/Retirement/PensionGrowthProjectionChart.vue`
- `resources/js/components/Plans/Retirement/RetirementGroupedActions.vue`
- `resources/js/components/Plans/Shared/PlanWhatIfChart.vue`
- `resources/js/components/Protection/CoverageGapChart.vue`
- `resources/js/components/Protection/CoverageTimelineChart.vue`
- `resources/js/components/Protection/PremiumBreakdownChart.vue`
- `resources/js/components/Protection/WhatIfScenarios.vue`
- `resources/js/components/Retirement/AccumulationChart.vue`
- `resources/js/components/Retirement/DrawdownSimulator.vue`
- `resources/js/components/Retirement/FundDepletionChart.vue`
- `resources/js/components/Retirement/IncomeDrawdownChart.vue`
- `resources/js/components/Retirement/IncomeProjectionChart.vue`
- `resources/js/components/Retirement/PensionPotProjectionChart.vue`
- `resources/js/components/Retirement/StrategyCard.vue`
- `resources/js/components/Retirement/TargetIncomeDrawdownChart.vue`
- `resources/js/components/Savings/EmergencyFundGauge.vue`
- `resources/js/components/Savings/InterestRateComparisonChart.vue`

### Mobile
- `resources/js/mobile/charts/NetWorthSparkline.vue`
- `resources/js/mobile/components/MobileProjectionChart.vue`

### CSS
- `resources/css/app.css` — minor updates

### Not for Upload (dev-only)
- `.claude/settings.json` — local Claude Code config
- `CSJTODO.md` — local tracking file

## Checklist

- [ ] Rebuild: `./deploy/fynla-org/build.sh`
- [ ] Upload `public/build/` to `~/www/fynla.org/public_html/public/build/`
- [ ] SSH and clear caches
- [ ] Verify homepage loads (new header image, features link)
- [ ] Verify /features page (harvey ball table)
- [ ] Verify /calculators (sidebar layout)
- [ ] Verify all 5 journey pages (gradient hero, demo buttons)
- [ ] Verify /login and /register (light-blue box)
- [ ] Verify /pricing (auth-aware CTAs)
- [ ] Verify preview banner (white exit, raspberry sign up)
- [ ] Verify demo entry/exit (returns to referrer)
- [ ] Check dashboard loads correctly
- [ ] Delete `public/mockup-starting-out.html` if present on server
