# Financial Commitments in Spending Chart - January 10, 2026

## Problem

The spending donut chart on the Cash Overview page (`/net-worth/cash`) was only showing discretionary spending (£3,540), missing all financial commitments:
- Pension contributions
- Mortgage/property expenses
- Protection premiums
- Loan payments

The user expected to see **ALL** expenses totaling approximately £9,800/month.

---

## Root Cause Investigation

### Initial Approach (Failed)

The initial implementation tried to fetch financial commitments from separate module APIs:

```javascript
// CashOverview.vue - Original approach
import estateService from '@/services/estateService';
import retirementService from '@/services/retirementService';
import protectionService from '@/services/protectionService';

// These API calls returned EMPTY data:
const estateData = await estateService.getEstateData();      // 0 liabilities
const retirementData = await retirementService.getRetirementData();  // 0 dc_pensions
const protectionData = await protectionService.getProtectionData();  // 0 policies
```

### API Investigation Findings

Direct API testing revealed that the individual module endpoints return empty data:

| API Endpoint | Expected | Actual |
|--------------|----------|--------|
| `/api/retirement` | DC pensions with contributions | `{ dc_pensions: [] }` |
| `/api/estate` | Liabilities including mortgages | `{ liabilities: [] }` |
| `/api/protection` | Life/CI/IP policies | `{ life_policies: [], ... }` |

**However**, the User Profile > Expenditure tab correctly displayed all financial commitments:
- David's SIPP: £2,000/month
- 15 Chestnut Lane mortgage: £2,350/month
- Flat 42 mortgage: £952/month
- Life Insurance: £85/month
- Critical Illness: £125/month
- School Fees Loan: £750/month
- **Total: £6,262/month**

### Discovery: Dedicated API Endpoint

The Expenditure tab uses a **dedicated endpoint** that aggregates all financial commitments:

```javascript
// userProfileService.js
async getFinancialCommitments() {
  const response = await api.get('/user/financial-commitments');
  return response.data;
}
```

This endpoint returns properly structured data:

```javascript
{
  commitments: {
    retirement: [...],   // DC pensions with monthly_amount
    properties: [...],   // Properties with mortgage payments
    investments: [...],  // Investment accounts
    protection: [...],   // Protection policies with premiums
    liabilities: [...],  // Loans with monthly payments
  },
  totals: {
    total: 6262,
    retirement: 2000,
    properties: 3302,
    investments: 0,
    protection: 210,
    liabilities: 750,
  }
}
```

---

## Solution

### Files Modified

1. **`resources/js/views/NetWorth/CashOverview.vue`**
   - Replaced separate service imports with `userProfileService`
   - Added `loadFinancialCommitments()` method using `/api/user/financial-commitments`
   - Simplified `financialCommitments` computed property to use API totals

2. **`resources/js/components/Dashboard/AffordabilityOverviewCard.vue`**
   - Same changes - use `userProfileService.getFinancialCommitments()` instead of separate APIs

### Code Changes

#### CashOverview.vue

```javascript
// Before
import estateService from '@/services/estateService';
import retirementService from '@/services/retirementService';
import protectionService from '@/services/protectionService';

data() {
  return {
    liabilities: [],
    dcPensions: [],
    protectionPolicies: [],
  };
},

// After
import userProfileService from '@/services/userProfileService';

data() {
  return {
    financialCommitmentsData: null,
  };
},

async loadFinancialCommitments() {
  const response = await userProfileService.getFinancialCommitments();
  if (response.success) {
    this.financialCommitmentsData = response.data;
  }
},

financialCommitments() {
  const commitments = {};
  if (!this.financialCommitmentsData?.totals) return commitments;

  const totals = this.financialCommitmentsData.totals;

  if (totals.properties > 0) {
    commitments['Property Expenses'] = totals.properties;
  }
  if (totals.retirement > 0) {
    commitments['Pension Contributions'] = totals.retirement;
  }
  if (totals.protection > 0) {
    commitments['Protection Premiums'] = totals.protection;
  }
  if (totals.liabilities > 0) {
    commitments['Loan Payments'] = totals.liabilities;
  }

  return commitments;
}
```

---

## Result

The spending chart now shows **£9,802.45** total (up from £3,540.45):

| Category | Amount |
|----------|--------|
| Property Expenses | £3,302 |
| Pension Contributions | £2,000 |
| Protection Premiums | £210 |
| Loan Payments | £750 |
| Food & Groceries | £800 |
| Transport & Fuel | £600 |
| Entertainment & Dining | £500 |
| Other Expenditure | £248 |
| Mock Data (Savings/CC) | ~£1,400 |
| **Total** | **£9,802.45** |

---

## API Issue Notes

### Why Individual Module APIs Return Empty

This needs further investigation. Possible causes:
1. The individual APIs may be filtering by ownership differently
2. The UserProfileService aggregates data using different query logic
3. Preview user data may not be fully linked to individual module queries

### Recommendation

For any feature needing aggregated financial commitments data, use:
```javascript
import userProfileService from '@/services/userProfileService';
const response = await userProfileService.getFinancialCommitments();
```

Do **NOT** try to aggregate from individual module APIs:
- `/api/retirement` - May return empty
- `/api/estate` - May return empty
- `/api/protection` - May return empty

The dedicated `/api/user/financial-commitments` endpoint properly aggregates all data.

---

## Verification

1. Navigate to `/net-worth/cash`
2. Spending chart should show ~£9,800 total
3. Hover over chart segments to verify:
   - Property Expenses: £3,302
   - Pension Contributions: £2,000
   - Protection Premiums: £210
   - Loan Payments: £750
4. Navigate to Dashboard - Affordability card should show correct Money Out value
