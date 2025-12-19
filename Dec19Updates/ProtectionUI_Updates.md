# Protection Module UI Updates - December 19, 2025

## Branch: `protectionUI`

## Summary

Simplified the Protection module's Policy Overview tab UI, added a new Coverage Summary section with accurate debt and income metrics, added a back button to the policy detail view, and redesigned the Gap Analysis tab with 5 protection shortfall cards including UK Statutory Sick Pay (SSP) integration.

---

## Changes Made

### 1. Version Update to v0.4.1

**Files Modified:**
- `resources/js/layouts/PublicLayout.vue` - Added version banner in white band above footer
- `resources/js/components/Footer.vue` - Updated v0.2.20 → v0.4.1
- `resources/js/views/Version.vue` - Updated version, date (19 Dec 2025), feature title
- `CLAUDE.md` - Updated v0.2.20 → v0.4.1
- `README.md` - Updated version and dates to v0.4.1 / Dec 19, 2025

---

### 2. Policy Overview Tab Simplification

**File:** `resources/js/components/Protection/CurrentSituation.vue`

#### Removed:
- "Policy Portfolio" title replaced with dynamic "Policy" (1) or "Policies" (2+)
- Filter dropdowns (policy type and sort options)
- Coverage summary tags (Life, CI, IP, Disability, Sickness labels)
- Premium Breakdown chart card
- Coverage Timeline chart card

#### Kept:
- Policy cards grid
- Add New Policy button
- Upload Document button
- Risk Exposure section (renamed to Coverage Summary)

#### Code Cleanup:
- Removed unused imports: `PremiumBreakdownChart`, `CoverageTimelineChart`
- Removed unused data properties: `filterType`, `sortBy`
- Removed unused computed properties: `coverageSummary`
- Removed unused methods: `calculateCoverageByType`
- Simplified `filteredPolicies` to just sort by coverage (no filtering)

---

### 3. New Coverage Summary Section

**File:** `resources/js/components/Protection/CurrentSituation.vue`

Replaced the old "Risk Exposure" metrics with new Coverage Summary showing:

| Metric | Display | Data Source |
|--------|---------|-------------|
| **Debt Coverage** | Percentage with cover/debt amounts | Life insurance sum assured vs user profile liabilities |
| **Income Protected** | Percentage with cover/income amounts | Income protection policies vs user gross income |
| **Critical Illness** | Lump sum amount | Critical illness policies sum_assured |
| **Sickness Cover** | Annual amount | Sickness/Illness policies (annualised) |
| **Disability Cover** | Annual amount | Disability policies (annualised) |

#### Debt Coverage
- Shows: `XX%` with `£cover / £debt` beneath
- Colour coding: Green (≥100%), Amber (≥75%), Red (<75%)
- Data: Fetched from `/api/user/profile` → `liabilities_summary.total`
- Includes: Mortgages + other liabilities (same as User Profile page)

#### Income Protected
- Shows: `XX%` with `£cover / £income p.a.` beneath
- Colour coding: Green (≥50%), Amber (≥25%), Red (<25%)
- Target: 50-70% of income is typical recommendation
- Data: Income from protection analysis → `needs.gross_income`

#### Critical Illness
- Shows: `£amount` lump sum
- Colour: Pink (text-pink-600)
- Data: Sum of `sum_assured` from critical illness policies

#### New Computed Properties Added:
```javascript
totalDebt()              // Fetched from user profile API
annualIncome()           // From protection analysis or auth user
debtCoverage()           // Sum of life insurance sum_assured
debtCoveragePercent()    // debtCoverage / totalDebt * 100
debtCoverageColour()     // Green/Amber/Red based on percentage
incomeProtected()        // Annual benefit from IP policies
incomeProtectedPercent() // incomeProtected / annualIncome * 100
incomeProtectedColour()  // Green/Amber/Red based on percentage
criticalIllnessCover()   // Sum of CI policies sum_assured
sicknessCover()          // Annual benefit from sickness policies
disabilityCover()        // Annual benefit from disability policies
```

#### New Data Fetching:
```javascript
async mounted() {
  await this.fetchLiabilities();
}

async fetchLiabilities() {
  const response = await userProfileService.getProfile();
  this.fetchedTotalDebt = response.data?.liabilities_summary?.total || 0;
}
```

---

### 4. Policy Detail View - Back Button

**File:** `resources/js/components/Protection/PolicyDetail.vue`

Added a back button to the policy detail view for navigation back to the Protection module.

#### Implementation:
- Matches existing app design pattern (same as InvestmentDetailInline, PensionDetailInline, PropertyDetailInline)
- White background with subtle border
- Left arrow icon with "Back to Policies" text
- Hover effect with gray background
- Navigates to `/protection` on click

```vue
<button @click="$router.push('/protection')" class="back-button mb-4">
  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
  </svg>
  Back to Policies
</button>
```

#### CSS Added:
```css
.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  color: #4b5563;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.back-button:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
}
```

---

## Technical Details

### API Used for Debt Data
- Endpoint: `GET /api/user/profile`
- Response: `{ success: true, data: { liabilities_summary: { total: 525000, mortgages: {...}, other: {...} } } }`
- Service: `UserProfileService.getCompleteProfile()`
- Calculation: `mortgages.outstanding_balance + liabilities.current_balance`

### Why This Approach?
- Uses the same calculation as the User Profile page (consistent figures)
- Includes all liability types: mortgages, loans, credit cards, etc.
- Single source of truth for debt figures across the app

---

## Files Modified

| File | Changes |
|------|---------|
| `resources/js/components/Protection/CurrentSituation.vue` | Major refactor - simplified UI, new Coverage Summary |
| `resources/js/components/Protection/PolicyDetail.vue` | Added back button matching app design pattern |
| `resources/js/layouts/PublicLayout.vue` | Added version banner |
| `resources/js/components/Footer.vue` | Version update |
| `resources/js/views/Version.vue` | Version update |
| `CLAUDE.md` | Version update |
| `README.md` | Version update |

---

## Testing

1. Navigate to Protection module → Policy Overview tab
2. Verify "Policies" header (or "Policy" if only one)
3. Verify Coverage Summary shows (5 columns):
   - Debt Coverage % with correct £cover / £debt
   - Income Protected % with correct £cover / £income
   - Critical Illness lump sum amount
   - Sickness Cover annual amount
   - Disability Cover annual amount
4. Verify debt figure matches User Profile → Liabilities total
5. Click on a policy card to open detail view
6. Verify "Back to Policies" button appears at top of detail view
7. Click back button and verify navigation returns to Protection module

---

### 5. Gap Analysis Tab Simplification

**File:** `resources/js/components/Protection/GapAnalysis.vue`

#### Removed:
- Coverage Adequacy Gauge (overall score display)
- Protection Needs Calculation card (showing how needs are calculated)

#### Retained/Enhanced:
- Your Existing Life Insurance Coverage section (with allocation breakdown)
- Protection Shortfall section (expanded to 5 cards)
- Affordability Assessment section

---

### 6. Protection Shortfall Section Redesign

**File:** `resources/js/components/Protection/GapAnalysis.vue`

Redesigned the Protection Shortfall section with 5 cards:

| Card | Current Cover | Target | Shortfall Calculation |
|------|---------------|--------|----------------------|
| **Debt Protection** | Life insurance allocated to debt | Mortgage + Other Liabilities | Total Debt - Life Cover |
| **Income Replacement** | Excess life cover after debt | 75% of Annual Income | Target - Excess Cover |
| **Critical Illness** | CI policies sum_assured | 2x Annual Income | Target - CI Cover |
| **Sickness Cover** | SSP + Sickness policies (annualised) | 50% of Annual Income | Target - Total Cover |
| **Disability Cover** | Disability policies (annualised) | 50% of Annual Income | Target - Disability Cover |

#### Data Fetching:
```javascript
async fetchUserData() {
  const response = await userProfileService.getProfile();
  const data = response.data || response;

  // Liabilities breakdown
  const liabilities = data.liabilities_summary || {};
  this.fetchedMortgageDebt = liabilities.mortgages?.total || 0;
  this.fetchedOtherDebt = liabilities.other?.total || 0;

  // Income data
  const income = data.income_occupation || {};
  this.fetchedAnnualIncome = parseFloat(income.annual_employment_income || 0) +
                             parseFloat(income.annual_self_employment_income || 0);
  this.fetchedNetAnnualIncome = parseFloat(income.net_income || 0);
}
```

#### Computed Properties Added:
```javascript
// Debt Protection
mortgageDebt()           // From user profile API
otherDebt()              // From user profile API
totalDebt()              // mortgageDebt + otherDebt
debtCovered()            // Life cover allocated to debt (capped at totalDebt)
debtProtectionGap()      // totalDebt - totalLifeCoverage
debtGapSeverity()        // none/low/medium/high

// Income Replacement
humanCapitalCovered()    // Excess life cover after debt
incomeReplacementNeed()  // 75% of annual income
incomeReplacementGap()   // need - humanCapitalCovered
incomeGapSeverity()      // none/low/medium/high

// Critical Illness
criticalIllnessCover()   // Sum of CI policies sum_assured
criticalIllnessNeed()    // 2x annual income
criticalIllnessGap()     // need - cover
criticalIllnessGapSeverity()

// Sickness Cover (includes SSP)
isEmployee()             // true if user has employment income
sspWeeklyRate()          // £118.75 (UK SSP rate 2024/25)
sspAnnualEquivalent()    // £6,175 (£118.75 × 52) - only for employees
privateSicknessCover()   // Annual benefit from private sickness policies
totalSicknessCover()     // SSP + private policies
sicknessCover()          // Alias for totalSicknessCover
sicknessNeed()           // 50% of annual income
sicknessGap()            // need - totalSicknessCover
sicknessGapSeverity()

// Disability Cover
disabilityCover()        // Annual benefit from disability policies
disabilityNeed()         // 50% of annual income
disabilityGap()          // need - cover
disabilityGapSeverity()
```

#### UK Statutory Sick Pay (SSP) Integration:

The Sickness Cover card now includes UK Statutory Sick Pay for employees:

| Attribute | Value |
|-----------|-------|
| Weekly Rate | £118.75 |
| Duration | Up to 28 weeks |
| Annual Equivalent | £6,175 (for comparison) |
| Eligibility | Employees only (not self-employed) |

**How it works:**
- If user has employment income (`annual_employment_income > 0`), they're classified as an employee
- Employees see SSP in blue with the weekly rate shown
- Self-employed users see "Self-employed not eligible" in amber
- Total sickness cover = SSP (if employee) + Private policies
- Shortfall calculation uses the total cover

#### Affordability Assessment:
- Shows monthly income (after-tax using `net_income`)
- Shows current premium spend
- Shows % of income with colour coding (Green ≤10%, Amber ≤15%, Red >15%)

---

### 7. Protection Shortfall UI Refinements

**File:** `resources/js/components/Protection/GapAnalysis.vue`

#### 3-Column Grid Layout:
- Changed from 2-column to 3-column grid on large screens
- Responsive: 1 column (mobile) → 2 columns (md) → 3 columns (lg)
- CSS: `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4`

#### Per Annum (p.a.) Labels:
Added "p.a." suffix to all yearly figures for clarity:

| Card | Fields with p.a. |
|------|------------------|
| Income Replacement | Annual Income, 75% Target, Shortfall |
| Critical Illness | Annual Income only (cover is lump sum) |
| Sickness Cover | SSP, Private Policies, Total Cover, Income, Target, Shortfall |
| Disability Cover | Cover, Income, Target, Shortfall |

Note: Debt Protection and Critical Illness shortfalls remain without p.a. as they are lump sum amounts.

#### Monthly Shortfall Display:
For income-based shortfalls, added monthly equivalent below the annual figure:
- Income Replacement: Shows `/month` if gap > 0
- Sickness Cover: Shows `/month` if gap > 0
- Disability Cover: Shows `/month` if gap > 0

Example display:
```
Shortfall
£24,000 p.a.
£2,000/month
```

#### Reduced Font Size:
- Changed shortfall amounts from `text-2xl` to `text-lg` for better visual balance
- Applies to all 5 shortfall cards

---

## Files Modified

| File | Changes |
|------|---------|
| `resources/js/components/Protection/CurrentSituation.vue` | Major refactor - simplified UI, new Coverage Summary |
| `resources/js/components/Protection/GapAnalysis.vue` | Removed gauge/calculation cards, redesigned shortfall section with 5 cards in 3-column grid, added SSP integration, p.a. labels, monthly shortfalls |
| `resources/js/components/Protection/PolicyDetail.vue` | Added back button matching app design pattern |
| `resources/js/layouts/PublicLayout.vue` | Added version banner |
| `resources/js/components/Footer.vue` | Version update to v0.4.1 |
| `resources/js/views/Version.vue` | Version update to v0.4.1 |
| `CLAUDE.md` | Version update to v0.4.1 |
| `README.md` | Version update to v0.4.1 |

---

## Testing

### Policy Overview Tab
1. Navigate to Protection module → Policy Overview tab
2. Verify "Policies" header (or "Policy" if only one)
3. Verify Coverage Summary shows (5 columns):
   - Debt Coverage % with correct £cover / £debt
   - Income Protected % with correct £cover / £income
   - Critical Illness lump sum amount
   - Sickness Cover annual amount
   - Disability Cover annual amount
4. Verify debt figure matches User Profile → Liabilities total
5. Click on a policy card to open detail view
6. Verify "Back to Policies" button appears at top of detail view
7. Click back button and verify navigation returns to Protection module

### Gap Analysis Tab
1. Navigate to Protection module → Gap Analysis tab
2. Verify "Your Existing Life Insurance Coverage" section shows allocation breakdown
3. Verify "Protection Shortfall" section shows 5 cards:
   - Debt Protection (with mortgage and other liabilities breakdown)
   - Income Replacement (75% of income target)
   - Critical Illness (2x income target)
   - Sickness Cover (50% of income target)
   - Disability Cover (50% of income target)
4. Verify each card shows current cover, target, and shortfall
5. Verify severity badges (none/low/medium/high) are colour-coded
6. Verify "Affordability Assessment" shows after-tax monthly income
7. Verify data matches values in User Profile page

---

## Screenshots

### Policy Overview Tab
Before: Policy Portfolio with filters, coverage tags, Premium Breakdown and Coverage Timeline charts

After: Clean Policies header with Coverage Summary showing Debt Coverage, Income Protected, Critical Illness, Sickness Cover, Disability Cover metrics (5 columns)

### Gap Analysis Tab
Before: Coverage Adequacy Gauge, Protection Needs Calculation card, 2 shortfall cards

After: Life Insurance Allocation breakdown, 5 Protection Shortfall cards (Debt, Income, CI, Sickness, Disability), Affordability Assessment
