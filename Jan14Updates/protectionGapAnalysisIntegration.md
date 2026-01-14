# Protection Dashboard - Gap Analysis Integration

## Summary

Moved all Gap Analysis tab content into the Policy Overview tab, placing it below the policy cards and above the coverage summary card.

---

## Changes Made

### `resources/js/components/Protection/CurrentSituation.vue`

**New Sections Added (in order):**

1. **Existing Coverage & Allocation** (lines 118-174)
   - Shows total life insurance coverage
   - Displays allocation breakdown:
     - Amount allocated to cover debts
     - Excess available for beneficiary income (converted using 4.7% withdrawal rate)
     - Any unused excess
   - Income replacement policies summary

2. **Protection Shortfall Cards** (lines 176-410)
   - 5 gap analysis cards in a responsive grid:
     - **Debt Protection** - Compares life cover to total debt
     - **Income Replacement** - 75% of income target
     - **Critical Illness** - 2x income target (lump sum)
     - **Sickness Cover** - 50% of income target, includes SSP for employees
     - **Disability Cover** - 50% of income target
   - Each card shows severity badge (none/low/medium/high)
   - Shows current cover, target need, and shortfall

3. **Affordability Assessment** (lines 412-441)
   - Monthly income display
   - Current premium spend
   - Premium as percentage of income (with colour coding)
   - Recommended range guidance (5-10%)

**Data Properties Added:**
```javascript
fetchedTotalDebt: 0,
fetchedMortgageDebt: 0,
fetchedOtherDebt: 0,
fetchedNetAnnualIncome: 0,
fetchedEmploymentIncome: 0,
fetchedSelfEmploymentIncome: 0,
spouseName: null,
```

**Computed Properties Added:**
- `mortgageDebt`, `otherDebt` - Debt breakdown
- `existingLifeCoverage`, `debtCoveredAmount` - Life cover allocation
- `humanCapitalCovered`, `humanCapitalCoveredAnnual` - Excess for income
- `excessUnused` - Remaining unallocated cover
- `incomeReplacementCoverageAnnual` - From IP policies
- `debtProtectionGap`, `incomeReplacementNeed`, `incomeReplacementGap`
- `criticalIllnessNeed`, `criticalIllnessGap`
- `isEmployee`, `sspWeeklyRate`, `sspAnnualEquivalent`, `totalSicknessCover`
- `sicknessNeed`, `sicknessGap`, `disabilityNeed`, `disabilityGap`
- `debtGapSeverity`, `incomeGapSeverity`, `criticalIllnessGapSeverity`, `sicknessGapSeverity`, `disabilityGapSeverity`
- `monthlyNetIncome`, `premiumPercentage`, `premiumPercentageColour`

**Methods Added:**
```javascript
async fetchUserData() {
  // Fetches liabilities breakdown, income data, and spouse name from user profile
}

calculateSeverity(amount) {
  if (amount === 0) return 'none';
  if (amount < 50000) return 'low';
  if (amount < 150000) return 'medium';
  return 'high';
}

getSeverityBadgeClass(severity) {
  // Returns Tailwind classes for severity badge colours
}
```

---

## Key Calculations

### Life Cover Allocation Priority
1. First covers total debt (mortgages + other liabilities)
2. Excess converts to income using 4.7% sustainable withdrawal rate
3. Any remaining excess is noted as unused

### Income Targets
- **Income Replacement**: 75% of gross annual income
- **Critical Illness**: 2x gross annual income (lump sum)
- **Sickness Cover**: 50% of gross annual income
- **Disability Cover**: 50% of gross annual income

### SSP for Employees
- Weekly rate: £118.75
- Annual equivalent: £6,175 (£118.75 × 52 weeks)
- Self-employed: Not eligible for SSP

### Affordability Guidelines
- Green (good): ≤10% of net income
- Amber (caution): 10-15% of net income
- Red (high): >15% of net income

---

## Visual Layout

```
┌─────────────────────────────────────────┐
│ Policy Cards Grid (existing)            │
├─────────────────────────────────────────┤
│ Existing Coverage & Allocation          │
│ (only shows if life cover exists)       │
├─────────────────────────────────────────┤
│ Protection Shortfall                    │
│ ┌─────────┬─────────┬─────────┐        │
│ │  Debt   │ Income  │Critical │        │
│ │ Protect │ Replace │ Illness │        │
│ ├─────────┼─────────┼─────────┤        │
│ │Sickness │Disability│         │        │
│ │ Cover   │ Cover   │         │        │
│ └─────────┴─────────┴─────────┘        │
├─────────────────────────────────────────┤
│ Affordability Assessment                │
├─────────────────────────────────────────┤
│ Coverage Summary (existing)             │
└─────────────────────────────────────────┘
```

---

## Files Modified

| File | Change |
|------|--------|
| `resources/js/components/Protection/CurrentSituation.vue` | Added gap analysis content, data properties, computed properties, methods |
| `resources/js/views/Protection/ProtectionDashboard.vue` | Removed tab navigation, now shows Policy Overview directly |

---

## Tab Navigation Removed

The Protection Dashboard previously had 3 tabs:
- Policy Overview
- Gap Analysis
- Strategy

Since all Gap Analysis content is now in the Policy Overview, the tabs have been removed. The dashboard now displays the Policy Overview content directly without tab navigation.

**Removed from ProtectionDashboard.vue:**
- Tab navigation UI (`<nav>` with tab buttons)
- `tabs` data array
- `activeTab` data property
- GapAnalysis and Recommendations component imports
- Tab-related CSS styles
