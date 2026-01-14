# Retirement Dashboard Changes - January 14, 2025

## Summary

Simplified the Retirement Pensions tab by removing duplicate/incorrect strategy display and removing tab navigation.

---

## Changes Made

### 1. Strategy Card Simplified

**Before:**
- Showed complex before/after comparison cards with made-up projection data
- Same strategy displayed multiple times with different headings
- Displayed incorrect figures that didn't match actual strategy data

**After:**
- Shows a single, clear message based on the primary strategy type
- Human-readable recommendations:
  - Retirement age: "Retire 4 years later (at age 64) to achieve your target"
  - Contributions: "Increase pension contributions by £X/month"
  - Income target: "Adjust your target retirement income to £X/year"
- Shows probability improvement (e.g., from 72% to 95%)
- Clickable card links to full Strategies tab

### 2. Tab Navigation Removed

- Removed the tab buttons (Pensions, Future Value, Strategies, Retirement Income)
- Navigation now happens via clickable cards:
  - Strategy summary card → Strategies tab
  - Target Annual Income card → Retirement Income tab
- Cleaner, more focused interface

---

## Files Modified

### `resources/js/components/NetWorth/PensionList.vue`

**Template changes:**
- Removed `<div class="tab-navigation">` section
- Replaced complex `strategies-grid` with simple `strategy-summary` display

**Script changes:**
- Removed `tabs` data array
- Added computed properties:
  - `primaryStrategy` - Gets the first applicable strategy
  - `primaryStrategyMessage` - Generates human-readable message based on strategy type
  - `primaryStrategyProbability` - Gets the projected probability after strategy
- Removed unused `getCoverageBadgeClass` method

**Style changes:**
- Removed `.tab-navigation`, `.tabs-nav`, `.tab-button` styles
- Removed `.strategies-grid`, `.strategy-comparison-card`, `.comparison-row`, `.comparison-card` styles
- Removed `.coverage-badge` styles
- Added `.strategy-summary`, `.strategy-summary-content`, `.strategy-summary-icon`, `.strategy-summary-text`, `.strategy-cta` styles

---

## Strategy Message Logic

```javascript
primaryStrategyMessage() {
  const strategy = this.primaryStrategy;

  if (strategy.type === 'retirement_age') {
    const yearsDiff = strategy.recommended_value - strategy.current_value;
    return `Retire ${yearsDiff} years later (at age ${strategy.recommended_value}) to achieve your target`;
  }

  if (strategy.type === 'increase_contributions') {
    return `Increase pension contributions by ${formatCurrency(strategy.impact.additional_monthly)}/month`;
  }

  if (strategy.type === 'income_target') {
    return `Adjust your target retirement income to ${formatCurrency(strategy.recommended_value)}/year`;
  }

  return strategy.description;
}
```

---

## Visual Design

The new strategy summary uses:
- Blue gradient background (`#eff6ff` to `#dbeafe`)
- Blue icon circle with lightbulb icon
- Current probability in red, projected probability in green
- "View strategies" CTA on the right
- Entire card is clickable
