# Investment Strategy: Portfolio vs Account-Level

**Generated:** 23 January 2026
**Purpose:** Document how dashboard strategies work, compare to account-level detail, and specify an account-level strategy card for the detail view.

---

## Table of Contents

1. [Current Dashboard Strategy System](#1-current-dashboard-strategy-system)
2. [How investStrategyDetail.md Relates](#2-how-investstrategydetailmd-relates)
3. [Gap Analysis: Dashboard vs Account Detail](#3-gap-analysis-dashboard-vs-account-detail)
4. [Account-Level Strategy Card Specification](#4-account-level-strategy-card-specification)
5. [Implementation Plan](#5-implementation-plan)

---

## 1. Current Dashboard Strategy System

### Architecture Flow

```
InvestmentList.vue (Dashboard)
  └─> Vuex: investment/fetchRecommendations
      └─> investmentService.analyzeInvestment()
          └─> POST /api/investment/analyze
              └─> InvestmentController::analyze()
                  └─> InvestmentAgent::analyze($userId)
                      ├─> PortfolioAnalyzer (returns, allocation, diversification, risk)
                      ├─> FeeAnalyzer (total fees, high-fee holdings, low-cost comparison)
                      ├─> TaxEfficiencyCalculator (unrealised gains, efficiency score, harvesting)
                      └─> AssetAllocationOptimizer (target allocation, deviation)
                  └─> InvestmentAgent::generateRecommendations($analysis)
                      └─> Returns prioritised recommendation list
```

### Source Files

| Layer | File | Purpose |
|-------|------|---------|
| Vue Display | `resources/js/components/Investment/StrategyRecommendationCard.vue` | Individual card rendering |
| Vue Container | `resources/js/components/Investment/InvestmentRecommendationsTracker.vue` | List + stats dashboard |
| Vuex Store | `resources/js/store/modules/investment.js` | State: `recommendations`, `investmentRecommendations` |
| API Service | `resources/js/services/investmentService.js` | `analyzeInvestment()`, `getRecommendations()` |
| Controller | `app/Http/Controllers/Api/InvestmentController.php` | `analyze()`, `recommendations()` |
| Agent | `app/Agents/InvestmentAgent.php` | `analyze()`, `generateRecommendations()` |
| Analysis | `app/Services/Investment/PortfolioAnalyzer.php` | Returns, allocation, diversification, risk |
| Fees | `app/Services/Investment/FeeAnalyzer.php` | Fee breakdown, high-fee flagging, comparison |
| Tax | `app/Services/Investment/TaxEfficiencyCalculator.php` | Gains, efficiency score, harvesting |
| Allocation | `app/Services/Investment/AssetAllocationOptimizer.php` | Target allocation, deviation |

### Dashboard Recommendation Triggers

These recommendations analyse the **entire portfolio** (all accounts combined):

| # | Recommendation | Trigger Condition | Threshold | Data Source |
|---|----------------|-------------------|-----------|-------------|
| 1 | Complete Your Risk Profile | No risk profile set | `hasRiskProfile === false` | RiskProfile model |
| 2 | Add Your Holdings | Accounts exist, no holdings | `accounts_count > 0 AND holdings_count === 0` | Accounts + Holdings |
| 3 | Improve Portfolio Diversification | Poor diversification + has holdings | `holdings_count > 0 AND diversification_score < 70` | PortfolioAnalyzer |
| 4 | Reduce Investment Fees | Savings available (holdings) | `annual_saving > £50` | FeeAnalyzer low-cost comparison |
| 5 | Review High-Fee Holdings | Holdings with high OCF | `ocf_percent > 0.8%` (excl. advisory) | FeeAnalyzer |
| 6 | Review Platform Fees | Platform fee above threshold | `platform_fee percent_of_portfolio > 0.8%` | FeeAnalyzer fee_breakdown |
| 7 | Rebalance Portfolio | Drift from target + has holdings | `holdings_count > 0 AND needs_rebalancing === true` | AssetAllocationOptimizer |
| 8a | Open a Stocks & Shares ISA | Has GIA but no ISA | `has_gia AND NOT has_isa` | tax_wrappers |
| 8b | Use Your ISA Allowance | Has ISA with remaining allowance + GIA | `has_isa AND isa_remaining > 0 AND has_gia` | tax_wrappers |
| 8c | Consider Tax-Efficient Bonds | ISA used, significant GIA, no bonds | `has_gia AND gia_value > £50k AND NOT has_onshore/offshore_bond` | tax_wrappers |
| 9 | Tax Loss Harvesting | Losses available to harvest | `opportunities_count > 0 AND loss > £100` | TaxEfficiencyCalculator |

### Key Characteristic: Portfolio-Wide Scope

The dashboard strategies aggregate across ALL accounts:
- **Diversification score** uses combined holdings from every account
- **Fee savings** compares total portfolio OCF vs 0.15% benchmark
- **Platform fees** checks account-level platform fee percentages
- **Tax efficiency** uses a practical hierarchy: ISA availability → ISA allowance usage → bond wrappers
- **Rebalancing** compares combined asset allocation vs risk profile target (requires holdings)
- **Returns** are weighted across all holdings

### Bugs Fixed (23 January 2026)

Three bugs were discovered and fixed in `app/Agents/InvestmentAgent.php`:

#### Bug 1: Rebalance Recommendation Showing Without Holdings

**Problem:** `AssetAllocationOptimizer::calculateDeviation()` compares current allocation (0% for all classes when no holdings) against targets (e.g. medium = 50% equity, 40% bonds, 10% cash). This gives `totalDeviation = 100%`, exceeding the 15% threshold, so `needs_rebalancing = true` even with no holdings.

**Fix:** Added `$holdingsCount > 0` guard to the rebalancing recommendation condition.

#### Bug 2: Platform Fees Not Flagged in Recommendations

**Problem:** The existing fee recommendations only checked:
- `compareToLowCostAlternatives($holdings)` - compares holding OCFs vs 0.15% benchmark
- `identifyHighFeeHoldings($holdings)` - flags holdings with OCF > 0.8%

Both operate on holdings only. Account-level `platform_fee_percent` was never evaluated for recommendations, even though `calculateTotalFees()` correctly includes it in `fee_breakdown`.

**Fix:** Added new recommendation that reads `fee_breakdown[type=Platform Fees].percent_of_portfolio` and triggers when > 0.8%.

#### Bug 3: Tax Efficiency Recommendations Redesigned

**Problem:** The original approach showed a generic score ("Your tax efficiency score is 80/100") which gave users no practical guidance. With 0% ISA usage, the score was exactly 80 and didn't even trigger due to an exclusive threshold (`< 80`).

**Fix:** Replaced the score-based approach with a practical tax efficiency hierarchy:

| Priority | Condition | Recommendation |
|----------|-----------|----------------|
| 1st | Has GIA but no ISA | "Open a Stocks & Shares ISA" - explains tax shelter benefits |
| 2nd | Has ISA with remaining allowance + GIA | "Use Your ISA Allowance" - shows remaining allowance and GIA value to transfer |
| 3rd | ISA used, GIA > £50k, no bonds | "Consider Tax-Efficient Bonds" - explains onshore (5% withdrawal, tax-deferred) and offshore (gross roll-up) |

Only one recommendation from this hierarchy is shown (the highest priority match). Added `tax_wrappers` section to analysis response with ISA/GIA/bond account status.

---

## 2. How investStrategyDetail.md Relates

The `investStrategyDetail.md` document covers the **individual account detail view** features. These are NOT strategies/recommendations - they are analysis tools:

| Feature | Purpose | Scope |
|---------|---------|-------|
| Diversification Insights | Score, HHI, concentration warnings | Single account |
| Rebalancing Status | Drift analysis, buy/sell actions | Single account |
| Total Fees | Platform + OCF + advisory breakdown | Single account |
| Annualised Return | Cost basis vs current value | Single account |
| Monte Carlo Simulation | Projected growth with probability bands | Single account |

These features provide **data and analysis** but do NOT generate **actionable strategy recommendations** for the individual account.

---

## 3. Gap Analysis: Dashboard vs Account Detail

### What the Dashboard Provides (Portfolio-Wide)

| Strategy | Example Output |
|----------|---------------|
| Diversification | "Your diversification score is 45/100. Consider spreading across more asset classes." |
| Fees | "You could save £230/year by switching to low-cost alternatives." |
| Tax | "Your tax efficiency score is 65/100. Consider using more of your ISA allowance." |
| Rebalancing | "Your portfolio has drifted from target. Consider rebalancing." |

### What the Account Detail Provides

| Feature | Example Output |
|---------|---------------|
| Diversification Tab | HHI score, concentration metrics, asset breakdown chart |
| Rebalancing Tab | Drift percentage, buy/sell amounts |
| Fees Tab | Fee breakdown, 10-year impact projection |
| Performance Tab | Monte Carlo chart, returns |

### The Gap

The account detail view has rich **data** but no **actionable strategy card** summarising what the user should do with THIS specific account. The dashboard strategies are too broad - they don't tell the user what's wrong with the specific account they're looking at.

**Example of the gap:**
- Dashboard says: "Improve Portfolio Diversification" (but which account is the problem?)
- Account detail shows: HHI = 0.45 (data, but no "so what?")
- **Missing:** "This account is concentrated in UK equities (72%). Consider adding international or bond exposure to reduce risk."

---

## 4. Account-Level Strategy Card Specification

### Overview

A strategy card in the account detail view that generates account-specific recommendations. These differ from dashboard strategies because they:
- Focus on the single account being viewed
- Use account-specific thresholds (not portfolio aggregate)
- Provide specific, actionable guidance for that account
- Reference the account's own holdings and fees

### Display Location

Add as a card in the Performance tab sidebar (below the existing Diversification Insights and Rebalancing Status cards) OR as a new dedicated section above the chart area.

**Recommended:** Place as the FIRST card in the sidebar, above Diversification Insights:

```
┌─────────────────────┐    ┌───────────────────────────────────┐
│  Account Strategy   │    │                                   │
│  ─────────────────  │    │                                   │
│  • Rec 1            │    │      Monte Carlo Chart            │
│  • Rec 2            │    │                                   │
│  • Rec 3            │    │                                   │
├─────────────────────┤    │                                   │
│  Diversification    │    │                                   │
│  Insights           │    └───────────────────────────────────┘
├─────────────────────┤
│  Rebalancing Status │
├─────────────────────┤
│  Total Fees         │
└─────────────────────┘
```

### Account-Level Recommendation Triggers

| # | Recommendation | Trigger | Threshold | Data Source |
|---|----------------|---------|-----------|-------------|
| 1 | Add Holdings | No holdings in account | `holdings.length === 0` | Account holdings |
| 2 | High Concentration | Single holding dominates | `top_holding_percent > 40%` | DiversificationAnalyzer |
| 3 | Limited Asset Classes | Too few asset types | `asset_classes_used <= 1` | Holdings asset_type |
| 4 | High Account Fees | Fees above threshold | `total_fee_percent > 0.8%` (excl. advisory) | Account fees + holding OCFs |
| 5 | High-Fee Holdings | Individual high-cost holdings | `holding.ocf_percent > 0.8%` (excl. advisory) | Holdings in this account |
| 6 | Needs Rebalancing | Drift from target allocation | `drift_score > 5%` on any asset class | DriftAnalyzer for this account |
| 7 | ISA Allowance Available | ISA not fully utilised | `account_type === 'isa' AND isa_remaining > £5,000` | Account ISA subscription |
| 8 | No Contributions | Account has no regular contributions | `monthly_contribution === 0 AND account_value < £100k` | Account contributions_ytd |
| 9 | Tax Loss Opportunity | Holdings with unrealised losses | `account_type === 'gia' AND holding has loss > £500` | Holdings in GIA accounts |
| 10 | Missing Risk Profile | No risk profile set (affects allocation targets) | `hasRiskProfile === false` | RiskProfile model |

### Detailed Trigger Logic

#### Recommendation 1: Add Holdings
```
IF account.holdings.length === 0
THEN show: "Add your holdings to unlock diversification analysis, fee insights, and rebalancing strategies."
PRIORITY: 1
ACTION: Opens HoldingForm modal
```

#### Recommendation 2: High Concentration
```
IF account.holdings.length > 0
AND top_holding.allocation_percent > 40
THEN show: "{holding_name} represents {X}% of this account. Consider spreading across more holdings to reduce single-stock risk."
PRIORITY: 2
ACTION: Links to Diversification tab
```

#### Recommendation 3: Limited Asset Classes
```
IF account.holdings.length > 0
AND distinct_asset_classes <= 1
THEN show: "All holdings in this account are {asset_class}. Adding other asset classes (bonds, international) can reduce volatility."
PRIORITY: 3
ACTION: Links to Diversification tab
```

#### Recommendation 4: High Account Fees
```
IF (platform_fee + weighted_ocf) > 0.8% (excluding advisory fees)
THEN show: "This account's total fees are {X}%, costing £{annual_cost}/year. The industry average for a passive portfolio is around 0.3%."
PRIORITY: 2
ACTION: Links to Fees tab
```

#### Recommendation 5: High-Fee Holdings
```
IF any holding.ocf_percent > 0.8% (excluding advisory fee)
THEN show: "{count} holding(s) have fees above 0.8%. Consider lower-cost index alternatives."
PRIORITY: 3
ACTION: Links to Fees tab
```

#### Recommendation 6: Needs Rebalancing
```
IF risk_profile exists
AND any asset_class deviation > 5% from target range
THEN show: "Your {asset_class} allocation is {current}% (target: {target_min}-{target_max}%). Consider rebalancing."
PRIORITY: 2
ACTION: Links to Rebalancing tab (if has holdings) or opens HoldingForm (if no holdings)
```

#### Recommendation 7: ISA Allowance Available
```
IF account.account_type === 'isa'
AND (£20,000 - isa_subscription_current_year) > £5,000
THEN show: "You have £{remaining} of ISA allowance remaining this tax year. Consider maximising your tax-free contributions before 5 April."
PRIORITY: 3
ACTION: Informational (no specific link)
```

#### Recommendation 8: No Contributions
```
IF account.contributions_ytd === 0
AND account.current_value < £100,000
AND account.account_type NOT IN ('nsi')
THEN show: "No contributions recorded this tax year. Regular contributions benefit from pound-cost averaging."
PRIORITY: 4
ACTION: Opens Account Edit modal
```

#### Recommendation 9: Tax Loss Opportunity (GIA only)
```
IF account.account_type === 'gia'
AND any holding has unrealised_loss > £500
  (where unrealised_loss = cost_basis - current_value, when negative)
THEN show: "You have {count} holding(s) with unrealised losses. Selling and repurchasing after 30 days could offset capital gains tax."
PRIORITY: 3
ACTION: Links to Holdings tab
```

#### Recommendation 10: Missing Risk Profile
```
IF user has no risk profile set
THEN show: "Complete your risk profile to get personalised allocation targets and rebalancing recommendations for this account."
PRIORITY: 1
ACTION: Links to Risk section
```

### Maximum Recommendations Shown

- Show maximum **3 recommendations** in the card
- Prioritise by priority number (1 = highest)
- If more than 3, show "View all insights" link that expands or navigates

### Card Design

```html
<div class="insight-card">
  <h4 class="text-sm font-semibold text-gray-900 mb-3">Account Strategy</h4>

  <!-- Recommendations -->
  <div class="space-y-2">
    <div v-for="rec in accountRecommendations.slice(0, 3)"
         class="border rounded-lg p-2 cursor-pointer hover:bg-gray-50"
         :class="getPriorityBorderClass(rec.priority)"
         @click="handleRecommendationAction(rec)">
      <p class="text-xs font-medium text-gray-900">{{ rec.title }}</p>
      <p class="text-xs text-gray-500 mt-0.5">{{ rec.description }}</p>
    </div>
  </div>

  <!-- All good state -->
  <div v-if="accountRecommendations.length === 0" class="text-center py-4">
    <p class="text-sm text-green-600 font-medium">Looking Good</p>
    <p class="text-xs text-gray-500 mt-1">No recommendations for this account</p>
  </div>
</div>
```

### Priority Border Colors (matching existing UI palette)

| Priority | Color | Tailwind Class |
|----------|-------|----------------|
| 1 (Critical) | Violet | `border-l-4 border-l-violet-500` |
| 2 (Important) | Blue | `border-l-4 border-l-blue-500` |
| 3 (Moderate) | Blue lighter | `border-l-4 border-l-blue-300` |
| 4 (Low) | Gray | `border-l-4 border-l-gray-300` |

### Data Requirements

The account-level strategy card needs the following data already available in the detail view:

| Data Point | Available From | Already Loaded? |
|------------|---------------|-----------------|
| Holdings list | `account.holdings` | YES (in account prop) |
| Account type | `account.account_type` | YES |
| Fees (platform, advisory) | `account.platform_fee_percent`, `account.advisor_fee_percent` | YES |
| Holdings OCF | `holding.ocf_percent` | YES |
| ISA subscription | `account.isa_subscription_current_year` | YES |
| Contributions YTD | `account.contributions_ytd` | YES |
| Current value | `account.current_value` | YES |
| Risk profile | Vuex `risk/riskPreference` | YES (loaded on app init) |
| Diversification data | API call to `/investment/accounts/{id}/diversification` | Loaded by DiversificationTab |
| Rebalancing data | API call to `/investment/accounts/{id}/rebalancing` | Loaded by AccountPerformancePanel |

**Key point:** Most data is already available in the `account` prop passed to the detail view. Only diversification and rebalancing data require API calls (which are already made by existing tabs).

### Approach: Frontend-Only Calculation

Since all required data is already available in the frontend, the account-level strategy card can calculate recommendations **client-side** without a new API endpoint:

```javascript
computed: {
  accountRecommendations() {
    const recs = [];
    const holdings = this.account.holdings || [];
    const hasRiskProfile = !!this.$store.getters['risk/riskPreference'];

    // 1. No holdings
    if (holdings.length === 0) {
      recs.push({ priority: 1, title: 'Add Holdings', ... });
      return recs; // No point checking further
    }

    // 2. Concentration
    const totalValue = holdings.reduce((sum, h) => sum + (h.current_value || 0), 0);
    if (totalValue > 0) {
      const topHolding = holdings.reduce((max, h) =>
        (h.current_value || 0) > (max.current_value || 0) ? h : max, holdings[0]);
      const topPercent = (topHolding.current_value / totalValue) * 100;
      if (topPercent > 40) {
        recs.push({ priority: 2, title: 'High Concentration', ... });
      }
    }

    // 3. Limited asset classes
    const assetClasses = new Set(holdings.map(h => h.asset_type));
    if (assetClasses.size <= 1) {
      recs.push({ priority: 3, title: 'Limited Diversification', ... });
    }

    // 4. High account fees (excluding advisory)
    const platformFee = parseFloat(this.account.platform_fee_percent) || 0;
    const advisorFee = parseFloat(this.account.advisor_fee_percent) || 0;
    const weightedOCF = totalValue > 0
      ? holdings.reduce((sum, h) => sum + ((h.current_value || 0) * (parseFloat(h.ocf_percent) || 0)), 0) / totalValue
      : 0;
    const totalFees = platformFee + weightedOCF;
    if (totalFees > 0.8) {
      recs.push({ priority: 2, title: 'High Fees', ... });
    }

    // 5. High-fee individual holdings
    const highFeeHoldings = holdings.filter(h => (parseFloat(h.ocf_percent) || 0) > 0.8);
    if (highFeeHoldings.length > 0) {
      recs.push({ priority: 3, title: 'High-Fee Holdings', ... });
    }

    // 7. ISA allowance
    if (this.account.account_type === 'isa') {
      const remaining = 20000 - (this.account.isa_subscription_current_year || 0);
      if (remaining > 5000) {
        recs.push({ priority: 3, title: 'ISA Allowance Available', ... });
      }
    }

    // 8. No contributions
    if ((this.account.contributions_ytd || 0) === 0
        && this.account.current_value < 100000
        && this.account.account_type !== 'nsi') {
      recs.push({ priority: 4, title: 'No Contributions', ... });
    }

    // 10. No risk profile
    if (!hasRiskProfile) {
      recs.push({ priority: 1, title: 'Set Risk Profile', ... });
    }

    // Sort by priority and return top 3
    return recs.sort((a, b) => a.priority - b.priority).slice(0, 3);
  }
}
```

---

## 5. Implementation Plan

### Option A: Inline in AccountPerformancePanel (Recommended)

Add the strategy card directly to `AccountPerformancePanel.vue` as a new sidebar card. All data is already available via the `account` prop.

**Files to change:**
```
resources/js/views/Investment/AccountPerformancePanel.vue
```

**Advantages:**
- No new API endpoint needed
- No new component file needed
- Data already available
- Consistent with existing sidebar card pattern

### Option B: Separate Component

Create `AccountStrategyCard.vue` as a standalone component used in the performance tab.

**Files to create/change:**
```
resources/js/components/Investment/AccountStrategyCard.vue  (NEW)
resources/js/views/Investment/AccountPerformancePanel.vue   (import + use)
```

**Advantages:**
- Cleaner separation of concerns
- Easier to test independently
- Can be reused in other contexts

### Recommendation

**Option B** - Creating a separate `AccountStrategyCard.vue` component is cleaner for maintainability and follows the existing pattern where each sidebar card (DiversificationInsights, RebalancingStatus, TotalFees) handles its own logic.

### Frontend Rebuild Required: YES

No backend changes needed for account-level card. All data already available.

---

## 6. Dashboard Strategy Changes Implemented (23 January 2026)

The following changes were made to the dashboard-level strategy system in `app/Agents/InvestmentAgent.php`.

### New Analysis Response: `tax_wrappers`

The `analyze()` method now returns a `tax_wrappers` section alongside the existing analysis data:

```php
'tax_wrappers' => [
    'has_isa' => bool,           // User has at least one ISA account
    'isa_allowance' => int,      // Annual ISA allowance from TaxConfigService (currently £20,000)
    'isa_used_this_year' => float, // Total ISA subscriptions this tax year
    'isa_remaining' => float,    // Remaining allowance (allowance - used)
    'has_gia' => bool,           // User has at least one GIA account
    'gia_value' => float,        // Total value across all GIA accounts
    'has_onshore_bond' => bool,  // User has onshore bond account
    'has_offshore_bond' => bool, // User has offshore bond account
]
```

### Tax Efficiency Hierarchy (replaces score-based approach)

The old approach:
```php
// OLD - generic score with no actionable guidance
if ($analysis['tax_efficiency']['efficiency_score'] < 80) {
    'title' => 'Improve Tax Efficiency',
    'description' => 'Your tax efficiency score is X/100',
}
```

The new approach uses a practical hierarchy - only one fires (highest priority match):

```php
// NEW - actionable, specific guidance based on account types
if ($hasGia && !$hasIsa) {
    // 1st: No ISA at all - most tax inefficient
    'title' => 'Open a Stocks & Shares ISA',
    'description' => 'Your investments are in a GIA where gains and dividends are taxable.
                      An ISA shelters up to £20,000/year from income tax and CGT.',

} elseif ($hasIsa && $isaRemaining > 0 && $hasGia) {
    // 2nd: Has ISA with remaining allowance + GIA holdings to shelter
    'title' => 'Use Your ISA Allowance',
    'description' => 'You have £X ISA allowance remaining. Consider moving GIA
                      holdings (£Y) into your ISA before 5 April.',

} elseif ($hasGia && $giaValue > 50000 && !$hasOnshoreBond && !$hasOffshoreBond) {
    // 3rd: ISA used, significant GIA, no bonds - suggest wrappers
    'title' => 'Consider Tax-Efficient Bonds',
    'description' => 'With £X in your GIA, consider onshore bonds (tax-deferred growth,
                      5% annual tax-free withdrawal) or offshore bonds (gross roll-up,
                      no annual UK tax on gains).',
}
```

### Tax Wrapper Hierarchy Logic

| Priority | Condition | Rationale |
|----------|-----------|-----------|
| 1st | Has GIA, no ISA | ISA is the most accessible tax shelter; not having one is the biggest gap |
| 2nd | Has ISA + allowance remaining + GIA | User can immediately shelter GIA funds; time-sensitive (tax year end) |
| 3rd | GIA > £50k, ISA used, no bonds | For larger portfolios beyond ISA limits, bonds offer tax deferral |

### Other Fixes Applied

| Change | Before | After |
|--------|--------|-------|
| Rebalancing guard | No holdings check | `$holdingsCount > 0` required |
| Platform fee recommendation | Not checked | Triggers when `percent_of_portfolio > 0.8%` |
| TaxConfigService injection | Not used | ISA allowance sourced from `getISAAllowances()` |

### Files Changed

```
app/Agents/InvestmentAgent.php
```

### Post-Deployment

```bash
php artisan cache:clear
```
