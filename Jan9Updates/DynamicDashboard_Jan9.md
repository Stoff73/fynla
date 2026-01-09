# Dynamic Dashboard Updates - January 9, 2026

This document summarizes the dashboard improvements made today, focusing on the Investments and Tax Allowances cards.

## Overview

Continued work on the dynamic dashboard redesign, specifically improving the Investments Overview Card and Tax Allowances Card to show accurate, relevant data.

---

## Feature #1: Investments Overview Card Improvements

### Changes Made

1. **Account List Display**
   - Now shows each investment account with name, type badge, and value
   - Account type badges (ISA, SIPP, GIA) with solid border styling
   - Each account shows its annualised return percentage

2. **Annualised Returns Calculation**
   - Backend calculates annualised return per account from holdings
   - Uses `purchase_date` from holdings to calculate holding period
   - Defaults to 3 years if no purchase date is set
   - Formula: `((1 + total_return)^(1/years) - 1) * 100`

3. **Portfolio Summary**
   - Shows total portfolio value
   - Displays weighted average annualised return across all accounts
   - Risk level and diversification score shown when available

### Files Modified

**Backend:**
- `app/Http/Controllers/Api/InvestmentController.php`
  - Added `calculateAccountAnnualisedReturn()` method
  - Returns `annualised_return` field for each account in API response

**Frontend:**
- `resources/js/components/Dashboard/InvestmentsOverviewCard.vue`
  - Added account list with type badges and returns
  - Added `portfolioAnnualisedReturn` computed property (weighted average)
  - Added `accountsList` computed property
  - Added `getAccountTypeBadgeClass()` and `formatAccountType()` methods

- `resources/js/views/Dashboard.vue`
  - Added `investment/analyseInvestment` action to load analysis data

---

## Feature #2: Tax Allowances Card Conditional Display

### Changes Made

1. **Conditional Allowance Display**
   - CGT allowance only shown if user has GIA (non-ISA) investment accounts
   - Dividend allowance only shown if user has dividend income
   - Pension allowance always shown (can carry forward)

2. **Expiring Allowances Warning**
   - Shows warning near tax year end (within 3 months of April 5)
   - Lists ISA, CGT (if applicable), and Dividend (if applicable) as expiring
   - Pension NOT included in expiring warning (can carry forward)

### Files Modified

**Frontend:**
- `resources/js/components/Dashboard/TaxOptimisationCard.vue`
  - Added `hasDividendIncome` computed property
  - Added `hasNonIsaInvestments` computed property
  - Updated `hasExpiringAllowances` to exclude pension
  - Updated `expiringMessage` to only include relevant allowances

---

## Feature #3: Protection Card Gap Analysis Fix

### Changes Made

Fixed protection shortfalls to match exactly what the GapAnalysis.vue component shows:

1. **Five Coverage Categories:**
   - Debt Protection: Total debt minus life coverage
   - Income Replacement: 75% income minus excess life cover (at 4.7% draw rate)
   - Critical Illness: 2x income minus CI coverage
   - Sickness Cover: 50% income minus (SSP + sickness/illness policies)
   - Disability Cover: 50% income minus disability policies

2. **Local Calculations**
   - Card now fetches user profile data directly (same as GapAnalysis)
   - Calculates all gaps locally rather than relying on store analysis

### Files Modified

**Backend:**
- `resources/js/store/modules/protection.js`
  - Added `sicknessIllnessPolicies` getter
  - Fixed data paths for `coverageGaps` and `adequacyScore`

**Frontend:**
- `resources/js/components/Protection/ProtectionOverviewCard.vue`
  - Complete rewrite to calculate gaps locally
  - Added props for all policy types including `sicknessIllnessPolicies`
  - Added computed properties matching GapAnalysis calculations

- `resources/js/views/Dashboard.vue`
  - Added `sicknessIllnessPolicies` prop to ProtectionOverviewCard

---

## All Commits (Chronological)

```
da7de18 feat: Update investments card with account list and YTD values
d629c95 fix: Load investment analysis to show YTD returns on dashboard
aa765fc fix: Calculate YTD return per investment account from holdings
e053a9d fix: Show YTD return as percentage instead of currency amount
0aca975 feat: Calculate annualised returns for investment accounts
2d5eebc feat: Conditionally show tax allowances based on user situation
007006e fix: Match protection shortfalls exactly to GapAnalysis component
ff46d42 fix: Correctly separate life insurance and income protection shortfalls
f33021b fix: Calculate protection shortfalls locally like GapAnalysis component
95463ec fix: Correct protection store getters to access nested data path
be1ff62 feat: Show protection shortfalls with amounts on dashboard
```

---

## Files Changed Summary

| File | Type | Changes |
|------|------|---------|
| `app/Http/Controllers/Api/InvestmentController.php` | Modified | Added annualised return calculation |
| `resources/js/components/Dashboard/InvestmentsOverviewCard.vue` | Modified | Account list, annualised returns display |
| `resources/js/components/Dashboard/TaxOptimisationCard.vue` | Modified | Conditional display logic |
| `resources/js/components/Protection/ProtectionOverviewCard.vue` | Modified | Local gap calculations |
| `resources/js/store/modules/protection.js` | Modified | Added getters, fixed data paths |
| `resources/js/views/Dashboard.vue` | Modified | Added analyseInvestment action, sicknessIllnessPolicies prop |

---

## Testing

1. **Investments Card:**
   - Verify annualised returns match the Portfolio Overview page
   - Check weighted average calculation across multiple accounts
   - Confirm account type badges display correctly

2. **Tax Allowances Card:**
   - Test with user having GIA account - CGT should show
   - Test with user having ISA only - CGT should be hidden
   - Test with user having dividend income - Dividend allowance should show
   - Verify expiring warning excludes pension

3. **Protection Card:**
   - Compare shortfall values with GapAnalysis.vue
   - Verify all 5 categories calculate correctly
