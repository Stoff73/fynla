# Brett PR Changes Summary

**Date Range:** 16-19 January 2025
**Total PRs:** 3
**Status:** All merged to main

---

## PR #12: Joint Ownership Share Calculations

**Branch:** `brett`
**Merged:** 16 January 2026
**Changes:** +117 / -25 lines

### Summary
Fix net worth calculations to correctly apply ownership percentages for joint accounts and properties.

### Changes Made
- Show joint account badge and user's percentage share in Cash page
- Calculate `totalSavings`, `emergencyFundTotal`, and `totalISABalance` using ownership percentage
- Fix mortgage amount calculation to use ownership percentage for joint properties
- Allow sidebar navigation to return from detail views when clicking menu items

### Files Changed (5)

| File | Additions | Deletions |
|------|-----------|-----------|
| `resources/js/components/Cash/AccountGroupList.vue` | +68 | -10 |
| `resources/js/components/NetWorth/PropertyCard.vue` | +5 | -7 |
| `resources/js/components/NetWorth/PropertyList.vue` | +13 | -1 |
| `resources/js/store/modules/savings.js` | +23 | -6 |
| `resources/js/views/NetWorth/NetWorthDashboard.vue` | +8 | -1 |

### Test Plan
- Verify joint property values display user's share (e.g., 50% of total value)
- Verify joint savings accounts show user's ownership share
- Confirm individual accounts still display full value
- Test Net Worth dashboard totals reflect correct ownership percentages

---

## PR #13: Financial Statements Tab Split

**Branch:** `brett-valuable-info`
**Merged:** 16 January 2026
**Changes:** +736 / -5 lines

### Summary
Split financial statements into separate tabs for better organization and readability.

### Changes Made
- Add Balance Sheet tab with 3 columns (User, Spouse, Combined) showing assets grouped by Property, Investments, and Cash
- Add Income Statement tab with monthly and annual forecast columns
- Add Cash Flow tab placeholder (coming soon)
- Remove combined Financial Statements tab from Valuable Information page

### Files Changed (4)

| File | Additions | Deletions |
|------|-----------|-----------|
| `resources/js/components/UserProfile/BalanceSheetTab.vue` | +440 | -0 |
| `resources/js/components/UserProfile/CashFlowTab.vue` | +32 | -0 |
| `resources/js/components/UserProfile/IncomeStatementTab.vue` | +251 | -0 |
| `resources/js/views/ValuableInfo.vue` | +13 | -5 |

### New Components Created
1. **BalanceSheetTab.vue** - Displays assets grouped by Property, Investments, and Cash with User/Spouse/Combined columns
2. **CashFlowTab.vue** - Placeholder for cash flow analysis (Coming Soon)
3. **IncomeStatementTab.vue** - Shows income with monthly and annual forecast columns

### Test Plan
- Navigate to Valuable Information page
- Verify Balance Sheet tab displays with correct groupings and sub-totals
- Verify Income Statement tab shows monthly and forecast annual columns
- Verify Cash Flow tab shows "Coming Soon" placeholder

---

## PR #17: Financial Statements Restructure

**Branch:** `brett-18jan`
**Merged:** 19 January 2026
**Changes:** +285 / -129 lines

### Summary
Combine Income Statement and Cash Flow into single tab with improved layout and column alignment.

### Changes Made
- Combine Income Statement and Cash Flow into single tab
- Add Cash Flow section with before/after tax calculations
- Include estimated income tax and capital gains tax (shown as negative)
- Rename "Expenses" to "Outflows" with sorted items (Living Expenses first)
- Add consistent column widths for alignment across all tables
- Move Assets/Liabilities headers inline with column names to reduce whitespace
- Remove standalone Cash Flow tab from navigation

### Files Changed (3)

| File | Additions | Deletions |
|------|-----------|-----------|
| `resources/js/components/UserProfile/BalanceSheetTab.vue` | +73 | -70 |
| `resources/js/components/UserProfile/IncomeStatementTab.vue` | +210 | -53 |
| `resources/js/views/ValuableInfo.vue` | +2 | -6 |

### Test Plan
- Verify Balance Sheet columns align correctly across Assets, Liabilities, and Net Worth sections
- Verify Income Statement/Cash Flow tab displays income, outflows, and cash flow sections
- Confirm estimated taxes display as negative values in annual column
- Check Living Expenses appears before Mortgage Payments in Outflows

---

## Cumulative Impact

### Total Lines Changed
- **Additions:** 1,138 lines
- **Deletions:** 159 lines
- **Net Change:** +979 lines

### Components Created
1. `BalanceSheetTab.vue` - Balance sheet with User/Spouse/Combined columns
2. `CashFlowTab.vue` - Cash flow placeholder (later merged into IncomeStatement)
3. `IncomeStatementTab.vue` - Combined income statement and cash flow

### Components Modified
- `AccountGroupList.vue` - Joint ownership badges and share calculations
- `PropertyCard.vue` - Ownership percentage display
- `PropertyList.vue` - Ownership share calculations
- `NetWorthDashboard.vue` - Joint ownership totals
- `ValuableInfo.vue` - Tab navigation updates

### Store Updates
- `savings.js` - Ownership percentage calculations for totals

### Key Features Added
1. **Joint Ownership Calculations** - All joint accounts/properties now show user's share based on ownership percentage
2. **Balance Sheet** - Professional 3-column layout showing User, Spouse, and Combined totals
3. **Income Statement** - Monthly and annual columns with income/outflows
4. **Cash Flow Section** - Before/after tax calculations with estimated taxes
5. **Improved Alignment** - Consistent column widths across all financial tables
