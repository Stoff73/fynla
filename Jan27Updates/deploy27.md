# Deployment Notes - January 27, 2026

---

## UI Fix: Login Timeout Message - Solid Orange Colour

**Branch:** main

**Status:** Ready for deployment

### Description

Changed the session timeout message on the login page from mustard/amber (pastel) to a solid orange background with white text for better visibility.

### Changes Made

Updated the inactivity message styling:

| Element | Before (Amber/Mustard) | After (Solid Orange) |
|---------|------------------------|----------------------|
| Background | `bg-amber-50` | `bg-orange-500` |
| Border | `border-amber-200` | `border-orange-600` |
| Icon | `text-amber-600` | `text-white` |
| Text | `text-amber-800` | `text-white font-medium` |

### Files Changed

**Frontend (Rebuild Required):**

| File | Change Type |
|------|-------------|
| `resources/js/views/Login.vue` | Modified |

---

## Rebuild Required: YES

Frontend Vue file changed. Run the build script before uploading.

```bash
./deploy/fynla-org/build.sh
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

```
~/www/fynla.org/public_html/public/build/
```

### Step 3: Clear Cache (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear
```

---

## UI Consistency Overhaul - Design System Implementation

**Branch:** ui

**Status:** Ready for deployment

### Description

Comprehensive UI audit and fix of the entire application to ensure visual consistency across all 282 Vue components. Created a centralized design system and eliminated hardcoded colors.

### Summary of Changes

| Metric | Before | After |
|--------|--------|-------|
| Hardcoded hex colors | 1,334 | 2 |
| Files with issues | 90 | 2 |
| Reduction | - | **99.85%** |

### New Files Created

| File | Description |
|------|-------------|
| `designStyle.md` | 1,198-line comprehensive design system document |
| `resources/js/constants/designSystem.js` | JavaScript design tokens for chart colors, semantic colors, helper functions |

### Design System Features

**designStyle.md includes:**
- Color system (primary Trust Blue, secondary slate, semantic colors)
- Typography scale and font specifications
- Spacing system (4px-based)
- Border radius and shadow elevation levels
- Button, form, card, table, and modal specifications
- Badge systems (status + account types)
- Loading, empty, and error states
- Animation durations and easing functions
- Chart color configurations (ApexCharts)
- Responsive breakpoints
- Accessibility requirements (WCAG 2.1 AA)
- **FORBIDDEN colors list** (mustard, pastels, neons)

**designSystem.js exports:**
- `PRIMARY_COLORS`, `SECONDARY_COLORS`, `SUCCESS_COLORS`, `ERROR_COLORS`, `WARNING_COLORS`, `INFO_COLORS`
- `CHART_COLORS` - 8-color palette for data visualization
- `ASSET_COLORS` - Category-specific colors for wealth breakdown charts
- `SPENDING_COLORS` - 16 colors for expenditure category charts
- `RISK_COLORS` - Risk level color definitions
- `TEXT_COLORS`, `BG_COLORS`, `BORDER_COLORS`
- `CHART_DEFAULTS` - ApexCharts default configuration
- Helper functions: `getColorByThreshold()`, `getValueColor()`

### Files Changed (118 files)

**CSS:**
- `resources/css/app.css` - Added standardized component classes

**Vue Components (by module):**

| Module | Files Modified |
|--------|----------------|
| Actions | RecommendationFilters.vue |
| Auth | MFASetupModal.vue, MFAVerifyModal.vue |
| Cash | AccountGroupList.vue, AccountSummaryPanel.vue, BalanceTrendChart.vue, CashActionsPanel.vue, SpendingDonutChart.vue |
| Common | PrintHeader.vue |
| Dashboard | ActionsOverviewCard.vue, FinancialHealthScore.vue, NetWorthOverviewCard.vue, RetirementOverviewCard.vue |
| Estate | AssetForm.vue, CashFlowProjectionChart.vue, DualGiftingTimeline.vue, EstateOverviewCard.vue, GiftCard.vue, GiftForm.vue, GiftingStrategy.vue, GiftingTimelineChart.vue, IHTLiabilityGauge.vue, LiabilityForm.vue, NRBRNRBTracker.vue, NetWorthWaterfallChart.vue, WillPlanning.vue |
| Holistic | CashFlowAllocationChart.vue, NetWorthProjectionChart.vue, RiskAssessment.vue |
| Investment | AccountStrategyCard.vue, AllocationComparison.vue, AssetAllocationChart.vue, AssetLocationOptimizer.vue, BenchmarkComparison.vue, ComprehensiveInvestmentPlan.vue, ContributionPlanner.vue, CorrelationMatrix.vue, EfficientFrontier.vue, FeeSavingsCalculator.vue, GeographicAllocationMap.vue, GoalProjection.vue, HoldingsTable.vue, InvestmentProjectionChart.vue, MonteCarloResults.vue, Performance.vue, PerformanceAttribution.vue, PerformanceLineChart.vue, PortfolioOptimizer.vue, PortfolioOverview.vue, WrapperOptimizer.vue |
| Legal | StrategyDisclaimer.vue |
| NetWorth | AssetAllocationDonut.vue, AssetBreakdownBar.vue, BusinessInterestCard.vue, BusinessInterestsList.vue, ChattelCard.vue, ChattelDetailInline.vue, ChattelsList.vue, FeesDetail.vue, HoldingsDetail.vue, InvestmentDetailInline.vue, InvestmentList.vue, InvestmentProjections.vue, JointAccountHistory.vue, NetWorthOverview.vue, NetWorthTrendChart.vue, NetWorthWealthSummary.vue, PensionDetailInline.vue, PensionList.vue, PropertyCard.vue, PropertyDetailInline.vue, PropertyForm.vue, PropertyList.vue, StrategyDetail.vue, TaxEfficiencyDetail.vue, WealthSummary.vue |
| Onboarding | AssetsStep.vue |
| Protection | CoverageAdequacyGauge.vue, CoverageGapChart.vue, CoverageTimelineChart.vue, PolicyDetail.vue, PolicyFormModal.vue, PremiumBreakdownChart.vue, ProtectionOverviewCard.vue, ScenarioBuilder.vue, WhatIfScenarios.vue |
| Retirement | AccumulationChart.vue, DBPensionForm.vue, DCPensionForm.vue, DrawdownSimulator.vue, FundDepletionChart.vue, FutureValueTab.vue, IncomeDrawdownChart.vue, IncomeProjectionChart.vue, IncomeSourceSlider.vue, PensionPotProjectionChart.vue, RetirementIncomeTab.vue, StrategiesTab.vue, StrategyCard.vue, TargetIncomeDrawdownChart.vue, TaxBreakdownCard.vue |
| Savings | CurrentSituation.vue, EmergencyFund.vue, EmergencyFundGauge.vue, InterestRateComparisonChart.vue, SaveAccountModal.vue, SaveGoalModal.vue |
| Shared | CountrySelector.vue, RiskLevelSelector.vue |
| Trusts | TrustCard.vue, TrustsOverviewCard.vue |

**Views:**
- AccountDetailView.vue, AccountHoldingsPanel.vue, RetirementReadiness.vue, PrivacySettings.vue, TrustsDashboard.vue

---

## Rebuild Required: YES

Frontend Vue files changed. Run the build script before uploading.

```bash
./deploy/fynla-org/build.sh
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

```
~/www/fynla.org/public_html/public/build/
```

### Step 3: Clear Cache (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear
```

---

## Verification

### Login Timeout Message
1. Navigate to `/login?reason=inactivity`
2. The timeout message should display with solid orange background and white text

### UI Consistency
1. Navigate through all modules (Protection, Savings, Investment, Retirement, Estate)
2. Verify consistent styling:
   - All buttons use primary blue (`#1257A0`)
   - All cards have consistent border radius and shadows
   - Charts use the defined color palette
   - No mustard or pastel colors visible
   - Text colors are consistent (gray-900 headings, gray-700 body, gray-500 muted)

---
