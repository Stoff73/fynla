# Investment Dashboard Redesign

**Branch:** `investDash`
**Merged:** PR #15
**Date:** January 17, 2026

## Summary

Redesigned the investment account performance dashboard to replace tab navigation with a sidebar card navigation system. Added clickable insight cards that provide quick summaries and navigate to detailed views.

## Files Changed

| File | Changes |
|------|---------|
| `resources/js/views/Investment/AccountPerformancePanel.vue` | Major redesign with sidebar cards layout |
| `resources/js/components/NetWorth/InvestmentDetailInline.vue` | Tab navigation removed, default to Performance tab |
| `resources/js/components/Investment/InvestmentProjectionChart.vue` | Removed 85% probability band |
| `resources/js/components/Investment/Performance.vue` | Simplified layout, removed summary cards |

## New Features

### 1. Sidebar Card Navigation

Replaced horizontal tab navigation with clickable insight cards in a left sidebar:

- **Diversification Insights Card** - Shows top 3 recommendations with icons, click to go to Holdings tab
- **Rebalancing Status Card** - Displays drift score with visual progress bars for Equities/Bonds allocation
- **Fees Summary Card** - Shows total annual fee rate and cost, click to go to Fees tab

### 2. Asset Allocation Stacked Bar

Added a visual stacked bar chart showing asset allocation breakdown with:
- Colour-coded segments for each asset type
- Inline legend with percentages
- Click to navigate to Holdings tab

### 3. Tax Status Summary Card

New card showing tax treatment information:
- Product type label
- Tax items grid (first 4 items)
- Status icons and colour coding
- Click to navigate to Tax Status tab

### 4. Enhanced Current Value Display

Added estimated monthly contribution calculation to the Current Value card:
- Calculates from YTD contributions based on UK tax year (starts April 6)
- Shows as "+X/month" in green below the current value

### 5. Simplified Monte Carlo Chart

- Removed 50% (85% probability) band from the chart
- Now shows only 80%, 90%, and 95% probability bands
- Cleaner, less cluttered visualisation
- Updated colours array from 4 to 3 colours

### 6. Projection Year Selector

Moved the projection years dropdown into the "Projected Value (95%)" card:
- Options: 5, 10, 20, 30 years
- Compact inline design
- Immediately updates the projection display

## UI/UX Changes

### Layout
- Two-column layout: Sidebar cards (left) + Chart area (right)
- Cards have hover effects (`hover:shadow-md transition-shadow`)
- All cards are clickable and navigate to relevant tabs

### Default Tab
- Changed default active tab from `overview` to `performance`
- Tab navigation bar removed (users navigate via sidebar cards)

### Visual Indicators

**Drift Score Colouring:**
- Green background: On track (drift < 5%)
- Amber background: Needs attention (drift >= 5%)

**Fee Rate Colouring:**
- Green: Low fees (< 0.5%)
- Amber: Moderate fees (0.5% - 1%)
- Red: High fees (> 1%)

**Recommendation Types:**
- Warning (amber): Action needed
- Info (blue): Informational
- Success (green): Positive status

## Code Changes

### AccountPerformancePanel.vue

New methods added:
- `goToDiversificationTab()` - Emits `change-tab` with 'holdings'
- `goToRebalancingTab()` - Emits `change-tab` with 'rebalancing'
- `goToFeesTab()` - Emits `change-tab` with 'fees'
- `goToHoldingsTab()` - Emits `change-tab` with 'holdings'
- `goToTaxStatusTab()` - Emits `change-tab` with 'tax-status'
- `getDriftBgClass()` - Returns background class based on drift status
- `getDriftStatusClass()` - Returns text colour class for drift score
- `getTotalFeeBgClass()` - Returns background class based on fee level
- `getTotalFeeClass()` - Returns text colour class for fees
- `getAssetColor()` - Returns colour for asset type in allocation bar
- `formatAssetType()` - Formats asset type for display

New computed properties:
- `assetAllocationSummary` - Aggregates holdings by asset type with percentages
- `totalFeePercent` - Calculates total fee percentage
- `totalAnnualFees` - Calculates annual fees in currency
- `formatProjectedValue95` - Formats 95th percentile projected value

### InvestmentDetailInline.vue

- Removed tab navigation bar HTML
- Added `@change-tab` event listener on `AccountPerformancePanel`
- Added `handleTabChange(tabId)` method
- Added `estimatedMonthlyContribution` computed property
- Changed default `activeTab` from `'overview'` to `'performance'`

### InvestmentProjectionChart.vue

- Removed `percentile_15` (85% probability) series
- Updated colours array: `['#1e3a5f', '#2563eb', '#3b82f6', '#60a5fa']` to `['#1e3a5f', '#2563eb', '#60a5fa']`
- Updated stroke width array from 4 values to 3

### Performance.vue

- Removed top summary cards grid (Total Value, Holdings Count, Portfolio Health, Diversification)
- Removed standalone projection years dropdown
- Added inline projection cards layout (Current Portfolio + Future Value)
- Moved year selector into the Future Value card
- Removed bottom projection summary cards (95%, 80%, 50%, 10% probability)

## CSS Classes

New classes in AccountPerformancePanel.vue:
```css
.chart-with-sidebar - Flex container for two-column layout
.sidebar-cards - Left sidebar containing insight cards
.insight-card - Individual clickable card styling
.chart-container - Right side chart area
.asset-allocation-card - Asset allocation summary card
.stacked-bar - Container for stacked allocation bar
.bar-segment - Individual segment in stacked bar
.allocation-legend - Legend container
.legend-item-inline - Inline legend item
.legend-dot - Colour dot in legend
.tax-status-card - Tax information card
.tax-items-grid - Grid for tax items
.tax-item-mini - Individual tax item in grid
```

## Testing Notes

1. Verify sidebar cards display correct data from analysis/rebalancing endpoints
2. Test all card click navigation works correctly
3. Verify Monte Carlo chart renders with 3 probability bands
4. Check estimated monthly contribution calculation is accurate
5. Test projection year selector updates chart immediately
6. Verify responsive behaviour on mobile devices
