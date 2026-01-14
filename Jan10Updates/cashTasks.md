# Cash Overview Implementation Tasks

## Phase 1: Core Structure
- [x] Create `resources/js/views/NetWorth/CashOverview.vue` main container
- [x] Create `resources/js/components/Cash/` directory
- [x] Create `resources/js/components/Cash/mockData.js` with static data
- [x] Update `resources/js/router/index.js` to use CashOverview for `/net-worth/cash`

## Phase 2: Left Panel - Account Summary
- [x] Create `AccountSummaryPanel.vue`
  - [x] Balance summary card (Positive/Negative/Net)
  - [x] "Manage Accounts" button linking to SaveAccountModal
- [x] Create `AccountGroupList.vue`
  - [x] Group accounts by type (Current, Savings, Credit Cards)
  - [x] Account items with name, provider, color-coded balance
  - [x] Click handler to view account details

## Phase 3: Center Panel - Insights
- [x] Create `CashInsightsPanel.vue` container
- [x] Create `SpendingDonutChart.vue`
  - [x] ApexCharts donut with mock spending categories
  - [x] Category labels and totals
- [x] Create Payday widget (static countdown)
- [x] Create Credit Card spending widget (mock total)
- [x] Create `BalanceTrendChart.vue`
  - [x] ApexCharts area chart with mock 30-day history

## Phase 4: Right Panel - Actions
- [x] Create `CashActionsPanel.vue` container
- [x] Create Clear Cash widget (mock prediction)
- [x] Create Money In/Out widget (mock income/expenses)
- [x] Integrate My Goals section (real data from savings store)
  - [x] Progress bars for top 3 goals
  - [x] Link to full goals view
- [x] Create Alerts section (placeholder)
- [x] Create Tips section (static content)

## Phase 5: Integration & Polish
- [x] Fetch credit cards from liabilities API
- [x] Wire up "Manage Accounts" to existing SaveAccountModal
- [x] Add loading states
- [x] Add error handling
- [ ] Test responsive layout (mobile/tablet/desktop)
- [ ] Ensure sidebar navigation still works
- [x] Test with preview personas (young_family, peak_earners)

## Phase 6: Financial Commitments Integration
- [x] Fix Payday card to show real income data
  - Added `fetchProfile()` in mounted hook
  - Uses `totalAnnualIncome` getter from userProfile store
- [x] Include ALL expenses in spending chart
  - [x] Discovered individual module APIs return empty data
  - [x] Found dedicated `/api/user/financial-commitments` endpoint
  - [x] Updated CashOverview.vue to use `userProfileService.getFinancialCommitments()`
  - [x] Updated AffordabilityOverviewCard.vue to use same API
- [x] Verify spending chart shows £9,800+ total (was £3,540)
  - Property Expenses: £3,302
  - Pension Contributions: £2,000
  - Protection Premiums: £210
  - Loan Payments: £750
- [x] Update Current Account Money In/Out to use real data
  - [x] CashOverview passes `monthlyIncome` and `currentAccountExpenditure` to AccountSummaryPanel
  - [x] AccountSummaryPanel uses props instead of mock data
  - [x] Money In = Monthly income from user profile
  - [x] Money Out = Discretionary spending + Financial commitments (excludes credit card)
  - [x] Net shows surplus/deficit correctly

## Files Summary

### New Files
| File | Purpose |
|------|---------|
| `views/NetWorth/CashOverview.vue` | Main 3-column layout |
| `components/Cash/AccountSummaryPanel.vue` | Left panel |
| `components/Cash/AccountGroupList.vue` | Grouped account display |
| `components/Cash/CashInsightsPanel.vue` | Center panel |
| `components/Cash/SpendingDonutChart.vue` | Spending donut chart |
| `components/Cash/BalanceTrendChart.vue` | Balance line chart |
| `components/Cash/CashActionsPanel.vue` | Right panel |
| `components/Cash/mockData.js` | Static mock data |

### Modified Files
| File | Change |
|------|--------|
| `router/index.js` | Update cash route component |
| `views/NetWorth/CashOverview.vue` | Use `userProfileService.getFinancialCommitments()` for financial commitments; pass income/expenditure to AccountSummaryPanel |
| `components/Dashboard/AffordabilityOverviewCard.vue` | Use `userProfileService.getFinancialCommitments()` for Money Out calculation |
| `components/Cash/CashActionsPanel.vue` | Added `fetchProfile()` for Payday income data |
| `components/Cash/AccountSummaryPanel.vue` | Use real monthlyIncome and monthlyExpenditure props instead of mock data for Money In/Out |

## API Notes

**Important**: For financial commitments data, always use:
```javascript
import userProfileService from '@/services/userProfileService';
const response = await userProfileService.getFinancialCommitments();
```

Do NOT use individual module APIs (`/api/retirement`, `/api/estate`, `/api/protection`) for aggregated financial data - they may return empty results. See `FinancialCommitments_Jan10.md` for details.
