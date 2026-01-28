# Deployment Notes - January 28, 2026

---

## UI Consistency Overhaul - Design System Implementation

**Branch:** main (merged from `ui`)

**PR:** #38

**Status:** Ready for deployment

### Description

Comprehensive UI audit and fix of the entire Fynla application. Created a centralized design system and eliminated 1,332 hardcoded hex colors across 120+ Vue components.

### Summary

| Metric           | Before | After      |
|------------------|--------|------------|
| Hardcoded colors | 1,334  | 2          |
| Files with issues| 90     | 2          |
| Reduction        | -      | **99.85%** |

### New Files Created

| File                                      | Lines | Description                                              |
|-------------------------------------------|-------|----------------------------------------------------------|
| `designStyle.md`                          | 1,198 | Comprehensive design system document - single source of truth |
| `resources/js/constants/designSystem.js`  | 319   | JavaScript design tokens for charts, colors, helper functions |

### Bug Fixes Included

1. **Circular CSS class definitions** - Fixed `.text-gray-500 { @apply text-gray-500; }` patterns
2. **Invalid Tailwind classes** - Changed `border-3` to `border-4` (Tailwind only supports 0, 2, 4, 8)
3. **Malformed @apply syntax** - Fixed `border-@apply` to `@apply border-`

---

## Rebuild Required: YES

All frontend Vue components changed. Full rebuild required.

```bash
./deploy/fynla-org/build.sh
```

---

## Files to Upload (Manual - Not in Build)

These files are NOT compiled into the build and must be uploaded manually:

```text
designStyle.md                           → ~/www/fynla.org/public_html/designStyle.md
.claude/agents/premium-ui-designer.md    → ~/www/fynla.org/public_html/.claude/agents/premium-ui-designer.md
```

**Note:** The `designStyle.md` file is documentation only and not required for the application to function. Upload is optional.

---

## Files Changed (Included in Build)

All files below are compiled into `public/build/` during the build process. No manual upload required for these - just upload the built assets.

### CSS (1 file)

```text
resources/css/app.css
```

### JavaScript Constants (1 file)

```text
resources/js/constants/designSystem.js
```

### Vue Components - Actions (1 file)

```text
resources/js/components/Actions/RecommendationFilters.vue
```

### Vue Components - Auth (2 files)

```text
resources/js/components/Auth/MFASetupModal.vue
resources/js/components/Auth/MFAVerifyModal.vue
```

### Vue Components - Cash (5 files)

```text
resources/js/components/Cash/AccountGroupList.vue
resources/js/components/Cash/AccountSummaryPanel.vue
resources/js/components/Cash/BalanceTrendChart.vue
resources/js/components/Cash/CashActionsPanel.vue
resources/js/components/Cash/SpendingDonutChart.vue
```

### Vue Components - Common (1 file)

```text
resources/js/components/Common/PrintHeader.vue
```

### Vue Components - Dashboard (4 files)

```text
resources/js/components/Dashboard/ActionsOverviewCard.vue
resources/js/components/Dashboard/FinancialHealthScore.vue
resources/js/components/Dashboard/NetWorthOverviewCard.vue
resources/js/components/Dashboard/RetirementOverviewCard.vue
```

### Vue Components - Estate (13 files)

```text
resources/js/components/Estate/AssetForm.vue
resources/js/components/Estate/CashFlowProjectionChart.vue
resources/js/components/Estate/DualGiftingTimeline.vue
resources/js/components/Estate/EstateOverviewCard.vue
resources/js/components/Estate/GiftCard.vue
resources/js/components/Estate/GiftForm.vue
resources/js/components/Estate/GiftingStrategy.vue
resources/js/components/Estate/GiftingTimelineChart.vue
resources/js/components/Estate/IHTLiabilityGauge.vue
resources/js/components/Estate/LiabilityForm.vue
resources/js/components/Estate/NRBRNRBTracker.vue
resources/js/components/Estate/NetWorthWaterfallChart.vue
resources/js/components/Estate/WillPlanning.vue
```

### Vue Components - Holistic (3 files)

```text
resources/js/components/Holistic/CashFlowAllocationChart.vue
resources/js/components/Holistic/NetWorthProjectionChart.vue
resources/js/components/Holistic/RiskAssessment.vue
```

### Vue Components - Investment (21 files)

```text
resources/js/components/Investment/AccountStrategyCard.vue
resources/js/components/Investment/AllocationComparison.vue
resources/js/components/Investment/AssetAllocationChart.vue
resources/js/components/Investment/AssetLocationOptimizer.vue
resources/js/components/Investment/BenchmarkComparison.vue
resources/js/components/Investment/ComprehensiveInvestmentPlan.vue
resources/js/components/Investment/ContributionPlanner.vue
resources/js/components/Investment/CorrelationMatrix.vue
resources/js/components/Investment/EfficientFrontier.vue
resources/js/components/Investment/FeeSavingsCalculator.vue
resources/js/components/Investment/GeographicAllocationMap.vue
resources/js/components/Investment/GoalProjection.vue
resources/js/components/Investment/HoldingsTable.vue
resources/js/components/Investment/InvestmentProjectionChart.vue
resources/js/components/Investment/MonteCarloResults.vue
resources/js/components/Investment/Performance.vue
resources/js/components/Investment/PerformanceAttribution.vue
resources/js/components/Investment/PerformanceLineChart.vue
resources/js/components/Investment/PortfolioOptimizer.vue
resources/js/components/Investment/PortfolioOverview.vue
resources/js/components/Investment/WrapperOptimizer.vue
```

### Vue Components - Legal (1 file)

```text
resources/js/components/Legal/StrategyDisclaimer.vue
```

### Vue Components - NetWorth (25 files)

```text
resources/js/components/NetWorth/AssetAllocationDonut.vue
resources/js/components/NetWorth/AssetBreakdownBar.vue
resources/js/components/NetWorth/BusinessInterestCard.vue
resources/js/components/NetWorth/BusinessInterestsList.vue
resources/js/components/NetWorth/ChattelCard.vue
resources/js/components/NetWorth/ChattelDetailInline.vue
resources/js/components/NetWorth/ChattelsList.vue
resources/js/components/NetWorth/FeesDetail.vue
resources/js/components/NetWorth/HoldingsDetail.vue
resources/js/components/NetWorth/InvestmentDetailInline.vue
resources/js/components/NetWorth/InvestmentList.vue
resources/js/components/NetWorth/InvestmentProjections.vue
resources/js/components/NetWorth/JointAccountHistory.vue
resources/js/components/NetWorth/NetWorthOverview.vue
resources/js/components/NetWorth/NetWorthTrendChart.vue
resources/js/components/NetWorth/NetWorthWealthSummary.vue
resources/js/components/NetWorth/PensionDetailInline.vue
resources/js/components/NetWorth/PensionList.vue
resources/js/components/NetWorth/Property/PropertyDetailInline.vue
resources/js/components/NetWorth/PropertyCard.vue
resources/js/components/NetWorth/PropertyForm.vue
resources/js/components/NetWorth/PropertyList.vue
resources/js/components/NetWorth/StrategyDetail.vue
resources/js/components/NetWorth/TaxEfficiencyDetail.vue
resources/js/components/NetWorth/WealthSummary.vue
```

### Vue Components - Onboarding (1 file)

```text
resources/js/components/Onboarding/steps/AssetsStep.vue
```

### Vue Components - Protection (9 files)

```text
resources/js/components/Protection/CoverageAdequacyGauge.vue
resources/js/components/Protection/CoverageGapChart.vue
resources/js/components/Protection/CoverageTimelineChart.vue
resources/js/components/Protection/PolicyDetail.vue
resources/js/components/Protection/PolicyFormModal.vue
resources/js/components/Protection/PremiumBreakdownChart.vue
resources/js/components/Protection/ProtectionOverviewCard.vue
resources/js/components/Protection/ScenarioBuilder.vue
resources/js/components/Protection/WhatIfScenarios.vue
```

### Vue Components - Retirement (15 files)

```text
resources/js/components/Retirement/AccumulationChart.vue
resources/js/components/Retirement/DBPensionForm.vue
resources/js/components/Retirement/DCPensionForm.vue
resources/js/components/Retirement/DrawdownSimulator.vue
resources/js/components/Retirement/FundDepletionChart.vue
resources/js/components/Retirement/FutureValueTab.vue
resources/js/components/Retirement/IncomeDrawdownChart.vue
resources/js/components/Retirement/IncomeProjectionChart.vue
resources/js/components/Retirement/IncomeSourceSlider.vue
resources/js/components/Retirement/PensionPotProjectionChart.vue
resources/js/components/Retirement/RetirementIncomeTab.vue
resources/js/components/Retirement/StrategiesTab.vue
resources/js/components/Retirement/StrategyCard.vue
resources/js/components/Retirement/TargetIncomeDrawdownChart.vue
resources/js/components/Retirement/TaxBreakdownCard.vue
```

### Vue Components - Savings (6 files)

```text
resources/js/components/Savings/CurrentSituation.vue
resources/js/components/Savings/EmergencyFund.vue
resources/js/components/Savings/EmergencyFundGauge.vue
resources/js/components/Savings/InterestRateComparisonChart.vue
resources/js/components/Savings/SaveAccountModal.vue
resources/js/components/Savings/SaveGoalModal.vue
```

### Vue Components - Shared (2 files)

```text
resources/js/components/Shared/CountrySelector.vue
resources/js/components/Shared/RiskLevelSelector.vue
```

### Vue Components - Trusts (2 files)

```text
resources/js/components/Trusts/TrustCard.vue
resources/js/components/Trusts/TrustsOverviewCard.vue
```

### Vue Views (5 files)

```text
resources/js/views/Investment/AccountDetailView.vue
resources/js/views/Investment/AccountHoldingsPanel.vue
resources/js/views/Retirement/RetirementReadiness.vue
resources/js/views/Settings/PrivacySettings.vue
resources/js/views/Trusts/TrustsDashboard.vue
```

---

## Upload Checklist

### Step 1: Run Build

```bash
cd /Users/Chris/Desktop/fynla
./deploy/fynla-org/build.sh
```

### Step 2: Upload Built Assets

Upload the entire `public/build/` directory to:

```text
~/www/fynla.org/public_html/public/build/
```

### Step 3: Upload Manual Files (Optional)

```text
designStyle.md → ~/www/fynla.org/public_html/designStyle.md
```

### Step 4: Clear Cache (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear
```

---

## Verification

After deployment, verify:

1. **No Console Errors**: Check browser console for any CSS/Vite errors
2. **Visual Consistency**: Navigate through all modules:
   - Dashboard
   - Protection
   - Savings
   - Investment
   - Retirement (including Pensions tab)
   - Estate Planning
   - Trusts
3. **Chart Colors**: Verify charts use consistent color palette
4. **No Mustard/Pastel Colors**: All colors should be solid, professional tones
5. **Loading States**: Spinners should display correctly (border-4 styling)

---

## Rollback

If issues occur, restore previous `public/build/` directory from backup.

---
