# Current Flow: Minimal Data Entry for Investments and Pensions

## INVESTMENT ACCOUNT (Minimal Data)

### Required Fields
- `account_type` (isa, gia, nsi, etc.)
- `provider`
- `current_value`
- `tax_year`

### Key Behavior
1. **NO auto-created holdings** - When an investment account is created with no holdings, no default holding is created
2. **Returns card shows "Enter Holdings"** - When no holdings exist, the YTD Return field displays a clickable "Enter Holdings" link instead of a percentage
3. **User must add holdings** - To see returns and diversification analysis, user must manually add holdings to the account

### Flow
```
AccountForm.vue → investmentService.createAccount() → POST /api/investment/accounts
→ InvestmentController.storeAccount() → InvestmentAccount::create()
→ No auto-created holdings (user must add manually)
```

### Frontend Form - AccountForm.vue

**Location:** `resources/js/components/Investment/AccountForm.vue`

**Required Fields (Validation):**
- `account_type` - Required (must select from: isa, gia, nsi, onshore_bond, offshore_bond, vct, eis, other)
- `provider` - Required (free text, max 255 chars)
- `current_value` - Required (numeric, min 0)
- `tax_year` - Required (defaults to 2025/26)
- `account_type_other` - Required IF account_type is 'other'

**Optional Fields:**
- `platform` - Optional platform/product name
- `country` - Defaults to 'United Kingdom'
- `platform_fee_percent` / `platform_fee_amount` / `platform_fee_frequency` - All optional
- `isa_subscription_current_year` - Optional (ISA accounts only)
- `isa_type` - Defaults to 'stocks_and_shares' if not specified
- `ownership_type` - Defaults to 'individual'
- `joint_owner_id`, `trust_id` - Optional for ownership
- `risk_preference` - Optional (loaded from risk profile if available)

### Backend Controller - InvestmentController.php

**Location:** `app/Http/Controllers/Api/InvestmentController.php` (lines 267-349)

**Validation Rules:**
```php
$validated = $request->validate([
    'account_type' => ['required', Rule::in([...valid types...])],
    'account_type_other' => 'required_if:account_type,other|nullable|string|max:255',
    'provider' => 'required|string|max:255',
    'account_number' => 'nullable|string|max:255',
    'platform' => 'nullable|string|max:255',
    'current_value' => 'required|numeric|min:0',
    'contributions_ytd' => 'nullable|numeric|min:0',
    'tax_year' => 'required|string|max:10',
    'platform_fee_percent' => 'nullable|numeric|min:0|max:100',
    'isa_type' => ['nullable', Rule::in([...])],
    'isa_subscription_current_year' => 'nullable|numeric|min:0|max:20000',
    'ownership_type' => ['nullable', Rule::in(['individual', 'joint', 'trust'])],
    'ownership_percentage' => 'nullable|numeric|min:0|max:100',
    'joint_owner_id' => 'nullable|exists:users,id',
    'trust_id' => 'nullable|exists:trusts,id',
]);
```

### No Auto-Created Holdings

Investment accounts created with minimal data have **no holdings** until the user adds them manually.

**Frontend Display - AccountSummaryPanel.vue:**
```vue
<div class="detail-item">
  <span class="detail-label">YTD Return</span>
  <span v-if="hasHoldings && account.ytd_return !== null" class="detail-value">
    {{ formatReturn(account.ytd_return) }}
  </span>
  <span v-else class="detail-value text-blue-600 cursor-pointer hover:underline" @click="$emit('add-holding')">
    Enter Holdings
  </span>
</div>
```

**With No Holdings:**
- YTD Return shows "Enter Holdings" link (clickable)
- Asset allocation shows "No holdings in this account yet"
- Diversification analysis cannot be performed
- User is prompted to add holdings via the link

---

## DC PENSION (Minimal Data)

### Required Fields (Frontend)
- `pension_type` (occupational, sipp, personal, stakeholder)
- `scheme_name`
- `current_fund_value`
- `annual_salary` (workplace pensions only)

### Key Behavior
1. **NO auto-created holdings** - DC pensions do NOT create a default holding
2. **Expected Return shows "Enter Holdings"** - When no holdings exist, the Expected Return field displays a clickable "Enter Holdings" link
3. **Projections use current value only** - With no monthly contributions, projection = `current_value * (1 + growth_rate)^years`
4. **Diversification returns "no holdings"** - GET `/api/retirement/pensions/dc/{id}/diversification` returns `has_holdings: false`

### Flow
```
DCPensionForm.vue → retirementService.createDCPension() → POST /api/retirement/pensions/dc
→ RetirementController.storeDCPension() → DCPension::create()
→ No auto-created holdings
```

### Frontend Form - DCPensionForm.vue

**Location:** `resources/js/components/Retirement/DCPensionForm.vue`

**Required Fields (Validation - lines 520-561):**
- `pension_type` - Required (occupational, sipp, personal, stakeholder)
- `scheme_name` - Required (free text)
- `current_fund_value` - Required (numeric, min 0)
- `annual_salary` - Required for workplace pensions only

**Optional Fields:**
- `provider` - Optional (free text)
- `policy_number` - Optional (free text)
- `expected_return_percent` - Defaults to 5.0 if not provided
- `platform_fee_percent` - Optional
- `lump_sum_contribution` - Optional (SIPP/personal only)
- `salary_sacrifice` - Checkbox (workplace only), defaults to false
- `retirement_age` - Optional (55-75)
- `notes` - Optional (textarea)
- `risk_preference` - Optional

### Backend Validation - StoreDCPensionRequest.php

**Location:** `app/Http/Requests/Retirement/StoreDCPensionRequest.php`

**All Fields Are Nullable:**
```php
public function rules(): array
{
    return [
        'scheme_name' => ['nullable', 'string', 'max:255'],
        'scheme_type' => ['nullable', 'in:workplace,sipp,personal'],
        'pension_type' => ['nullable', 'in:occupational,sipp,personal,stakeholder'],
        'provider' => ['nullable', 'string', 'max:255'],
        'member_number' => ['nullable', 'string', 'max:255'],
        'current_fund_value' => ['nullable', 'numeric', 'min:0'],
        'annual_salary' => ['nullable', 'numeric', 'min:0'],
        'employee_contribution_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        'employer_contribution_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        'monthly_contribution_amount' => ['nullable', 'numeric', 'min:0'],
        'lump_sum_contribution' => ['nullable', 'numeric', 'min:0'],
        'investment_strategy' => ['nullable', 'string', 'max:255'],
        'platform_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:10'],
        'retirement_age' => ['nullable', 'integer', 'min:55', 'max:75'],
        'projected_value_at_retirement' => ['nullable', 'numeric', 'min:0'],
    ];
}
```

### Database Storage - DCPension Model

**Location:** `app/Models/DCPension.php`

**Fields Stored (with minimal data):**
```php
[
    'user_id' => 1,
    'scheme_name' => 'My Personal Pension',
    'scheme_type' => 'personal',
    'pension_type' => 'personal',
    'provider' => 'Aviva',
    'member_number' => null,
    'current_fund_value' => 75000,
    'annual_salary' => null,
    'employee_contribution_percent' => null,
    'employer_contribution_percent' => null,
    'monthly_contribution_amount' => null,
    'lump_sum_contribution' => null,
    'investment_strategy' => null,
    'platform_fee_percent' => null,
    'retirement_age' => null,
    'expected_return_percent' => 5,
    'projected_value_at_retirement' => null,
    'has_custom_risk' => false,
    'risk_preference' => null,
]
```

### Pension Projections with Minimal Data

**PensionProjector.projectDCPension() (lines 36-58):**

```php
public function projectDCPension(DCPension $pension, int $yearsToRetirement, float $growthRate): float
{
    $currentValue = 75000;  // Provided
    $monthlyContribution = null ?? 0.0;  // Defaults to 0
    $annualContribution = 0;

    $netGrowthRate = 0.05 - (0 / 100);  // 5% - 0% fees = 5%

    // Future value of current fund only (no contributions)
    $futureValueOfCurrentFund = 75000 * pow(1.05, $yearsToRetirement);

    return $futureValueOfCurrentFund;
}
```

**Example: 10 years to retirement**
- Current: £75,000
- Monthly Contribution: £0
- Growth Rate: 5% p.a.
- Projected at Retirement: £75,000 * 1.05^10 = £122,390

### Diversification Analysis with No Holdings

**RetirementController.getDCPensionDiversification() (lines 570-622):**

```php
if ($holdings->isEmpty()) {
    return response()->json([
        'success' => true,
        'data' => [
            'message' => 'No holdings recorded for this pension',
            'has_holdings' => false,
            'pension_id' => $id,
            'pension_name' => 'My Personal Pension',
        ],
    ]);
}
```

---

## Key Differences Summary

| Aspect | Investment Account | DC Pension |
|--------|-------------------|-----------|
| Auto-Created Holding | NO | NO |
| Required Fields | account_type, provider, current_value, tax_year | scheme_name, pension_type, current_fund_value |
| Fees Default | 0% (nullable) | 0% or null (nullable) |
| Holdings Required | No (shows "Enter Holdings") | No (shows "Enter Holdings") |
| Projections Without Data | No returns shown until holdings added | Growth of current_value only (no contributions) |
| Risk Profile | Can have account-level override | Can have pension-level override |
| Joint Ownership | Supported (single-record pattern) | Not applicable (pensions are personal) |
| Diversification Analysis | Not available until holdings added | Returns "no holdings" message if empty |

---

## Display & User Feedback

### For Investment Accounts (Minimal Data):
- Dashboard shows account value: £50,000
- YTD Return shows: "Enter Holdings" (clickable link)
- Diversification Insights card shows: "Enter Holdings" (clickable link)
- Asset allocation shows: "No holdings in this account yet"
- No returns or diversification analysis until holdings added
- "Improve Portfolio Diversification" recommendation NOT triggered (requires holdings)
- User prompted to add holdings via clickable links

### For DC Pensions (Minimal Data):
- Dashboard shows current fund: £75,000
- Expected Return shows: "Enter Holdings" (clickable link)
- Projected at retirement (10 years, 5% p.a.): £122,390
- Monthly contributions: £0
- No diversification analysis (no holdings)
- Recommendation may trigger: "Increase Pension Contributions" (if income gap exists)

---

# Strategy Flow & Decision Trees

## Investment Account Strategies

### Strategy Architecture

**Core Components:**
- **InvestmentAgent** (`app/Agents/InvestmentAgent.php`) - Orchestrates portfolio analysis and generates basic recommendations
- **PortfolioStrategyService** (`app/Services/Investment/PortfolioStrategyService.php`) - Aggregates all investment strategies with priority ordering
- **InvestmentRecommendation** model - Stores strategy data for tracking

**Request Flow:**
```
Vue Component (Investment Dashboard)
  ↓
PortfolioStrategyController API
  ↓
PortfolioStrategyService (aggregates all strategies)
  ↓
TaxOptimizationAnalyzer, FeeAnalyzer, DriftAnalyzer
  ↓
InvestmentAgent (fallback simple analysis)
  ↓
Frontend Display (RecommendationsSection.vue, StrategyRecommendationCard.vue)
```

### Strategy Categories & Decision Triggers

The system generates recommendations in a **4-level priority hierarchy**:

#### PRIORITY 1: TAX STRATEGIES (Highest Priority)

**Decision Logic:** `PortfolioStrategyService` (line 85-92)
- Calls `TaxOptimizationAnalyzer::analyzeCompleteTaxPosition()`

**Strategies:**
- **ISA Underutilization** - When ISA allowance unused (£20,000 annual)
- **Bed & ISA** - Selling positions at loss then rebuy in ISA
- **Tax Loss Harvesting** - When unrealized losses exist to offset gains

**Thresholds:**
- Tax efficiency score < 80/100 triggers general optimization recommendation
- Harvesting opportunities count > 0 with potential savings calculated

**Potential Savings:**
- ISA: up to 20% tax savings on gains for basic rate, 40% for higher rate
- Bed & ISA: full tax deferral on reinvested amount

#### PRIORITY 2: WRAPPER STRATEGIES (Bond Wrappers)

**Decision Logic:** `PortfolioStrategyService` (line 94-98, 187-257)
- Only shows for GIA/General Investment Accounts (not ISA)
- Only for **higher and additional rate taxpayers**

**Eligibility Thresholds:**
```
BOND_WRAPPER_MIN_BALANCE = £50,000
OFFSHORE_BOND_MIN_BALANCE = £100,000 (additional rate taxpayers only)
```

**Trigger Conditions:**
1. User is higher/additional rate taxpayer
2. GIA account balance >= £50,000
3. For offshore: balance >= £100,000 AND additional rate taxpayer

**Calculation of Benefit:**
```
Estimated Return: 6% annual
Annual Growth: Account Value × 6%
Tax Deferral Benefit:
  - Additional rate: 25% of growth (45% income tax vs ~20% effective bond rate)
  - Higher rate: 20% of growth (40% income tax vs ~20% effective bond rate)
```

**Recommendation Selection:**
- Balance >= £100,000 AND additional rate → Offshore Bond (recommended)
- Balance >= £50,000 AND higher/additional rate → Onshore Bond

#### PRIORITY 3: FEE STRATEGIES

**Decision Logic:** `PortfolioStrategyService` (line 100-107)
- Uses FeeAnalyzer to assess portfolio costs

**Trigger #1: Portfolio-Level High Fees**
- Current fee percentage > industry average
- Levels: "high" (5-7% annually) or "very_high" (>7%)
- Shows when: Assessment level = 'high' or 'very_high'

**Trigger #2: Individual High-Fee Holdings**
- Individual fund OCF > 0.5% annually
- Minimum threshold: Potential savings > £50/year

**Calculation:**
```
Current Annual Cost: Holding Value × Current OCF
Low-Cost Alternative: Holding Value × 0.15% (assumed)
Potential Saving: max(0, Current Cost - Low-Cost Cost)
```

#### PRIORITY 4: REBALANCING STRATEGIES

**Decision Logic:** `PortfolioStrategyService` (line 109-113)
- Uses DriftAnalyzer to evaluate allocation deviation

**Trigger Conditions:**
```php
if (urgency.action_required === true) {
    // Show rebalancing recommendation
}
```

**Drift Analysis:**
- Compares current allocation vs. target allocation from risk profile
- Calculates drift percentage per asset class
- Default threshold: 10% drift from target

**Urgency Levels:**
- **High**: Drift > 15% from target
- **Medium**: Drift 10-15% from target
- **Low**: Drift < 10% from target

### Investment Decision Tree

```
User has investment accounts?
  ├─ NO → Return empty
  └─ YES
     ├─ PRIORITY 1 (Tax): Tax efficiency score < 80?
     │   ├─ YES → Tax strategy recommendations
     │   └─ Harvesting opportunities? → Tax loss harvest rec
     │
     ├─ PRIORITY 2 (Wrapper): Higher/additional rate taxpayer?
     │   ├─ YES + GIA balance >= £50,000
     │   │   └─ Recommend onshore/offshore bond wrapper
     │   └─ NO → Skip
     │
     ├─ PRIORITY 3 (Fees): Portfolio fees > industry average?
     │   ├─ YES → High fee portfolio recommendation
     │   └─ Any holdings with OCF > 0.5%?
     │       ├─ YES + savings > £50 → High fee holding recs
     │       └─ NO → Skip
     │
     └─ PRIORITY 4 (Rebalancing): Drift > threshold?
         ├─ YES → Rebalancing recommendation
         └─ NO → Skip
```

---

## DC Pension Strategies (Retirement Planning)

### Strategy Architecture

**Core Components:**
- **RetirementStrategyService** (`app/Services/Retirement/RetirementStrategyService.php`) - Main strategy engine
- **ContributionOptimizer** (`app/Services/Retirement/ContributionOptimizer.php`) - Contribution analysis
- **RetirementAgent** (`app/Agents/RetirementAgent.php`) - High-level orchestration
- **StrategyCard.vue** - Frontend display with interactive sliders

**Request Flow:**
```
Vue Component (Retirement Dashboard)
  ↓
RetirementStrategyController API
  ↓
RetirementStrategyService::getStrategies()
  ↓
RetirementProjectionService (gets base projections)
  ↓
Four Priority Checks (in sequence)
  ↓
StrategyCard.vue with Interactive Sliders
```

### Probability-Based Decision System

**"On Track" Threshold: 95% probability**

Current position starts with a calculated probability based on:
- Current retirement income projection
- Target retirement income
- Years to retirement
- Current pension pot value
- Guaranteed income (DB pensions, state pension)

**Probability Formula:**
```php
probability = 10 + (income_ratio × 85)
where income_ratio = projected_income / target_income

// Capped at 10% (very poor) to 95% (on track)
```

### Strategy Priorities (Sequential Evaluation)

#### PRIORITY 1: EMPLOYER MATCH OPTIMIZATION

**Trigger Conditions:** (`RetirementStrategyService` line 424-500)

1. **Scheme Type Check:**
   - Only applies to workplace pensions
   - Skips personal pensions (SIPP), stakeholder, etc.

2. **Match Detection:**
   - Uses robust inference algorithm (line 514-542)
   - Checks explicit `employer_matching_limit` field (if valid 1-50%)
   - Falls back to inferring from contribution patterns:
     - If employer_contribution > employee_contribution → infer match limit at employer level
     - If employee ≥ employer → no matching opportunity
     - If employer ≤ 0 → no matching scheme

3. **Gap Detection:**
   - Current employee contribution < inferred match limit
   - AND gap ≥ 0.5% (meaningful difference)

**Calculation:**
```php
Additional Percentage Needed = Match Limit - Current Employee %
Additional Monthly = (Annual Salary × Additional %) / 12
Employer Bonus = Employee Additional (1:1 match)
Total Additional Monthly = Employee + Employer
```

**Impact on Probability:**
- Each £100/month additional contribution ≈ +2% probability
- Shows with cumulative tracking from prior strategies

#### PRIORITY 2: CONTRIBUTION INCREASE STRATEGY

**Trigger Conditions:** (`RetirementStrategyService` line 546-631)

1. **Affordability Check:**
   - Disposable income > 0 (from income & expenditure profile)
   - AND remaining annual allowance > 0

2. **Constraint Analysis:**
   - Max additional = min(disposable_income, remaining_allowance)
   - Recommended = 50% of max additional (conservative suggestion)
   - Respects annual allowance carry-forward (if 3-year history exists)

3. **Binding Constraint Determination:**
   - If annual_allowance < disposable_income → allowance is limiting
   - Otherwise → affordability is limiting

**Slider Configuration:**
```php
'slider_config' => [
    'min' => current_monthly_total,
    'max' => current_monthly + max_additional,
    'step' => £50,
    'unit' => '/month',
    'format' => 'currency',
]
```

**Impact Calculation:**
```
Additional Monthly (slider input - current)
↓
Convert to annual: × 12
↓
Project to retirement: FV of annuity formula with 5% growth
↓
Convert to annual income: pot × 0.047 (sustainable withdrawal)
↓
Recalculate probability with cumulative prior strategies
```

**Only Shows If:**
- Still not on track after employer match strategy
- Probability < 95%

#### PRIORITY 3: RETIREMENT AGE ADJUSTMENT

**Trigger Conditions:** (`RetirementStrategyService` line 636-752)

1. **Age Constraint Check:**
   - Current retirement age < 68 (max recommended)
   - Slider max = 75

2. **Income Target Requirement:**
   - Target income > 0
   - (Cannot calculate without target)

3. **Gap Analysis:**
   - Income gap > £5,000 → suggests retirement age adjustment

**Optimal Age Calculation:**
```php
// Test each year of delay
for ($yearsDelay = 1 to $maxRecommended) {
    pot_at_delayed_age = calculate_with_monte_carlo()
    sustainable_income = pot × 0.047
    total_income = sustainable + guaranteed
    coverage = total / target

    if (coverage >= 95%) {
        use this age (minimum delay to reach 95%)
        break;
    }
}

// If still can't reach 95%, use max recommended age (68)
```

**Cumulative Projection:**
```
"Without Strategy" = Monte Carlo baseline + prior strategies' pot
"With Strategy" = Pot continues growing for additional years
Shows: additional pot growth, additional contributions, total at delayed retirement
```

#### PRIORITY 4: INCOME TARGET REDUCTION STRATEGY

**Trigger Conditions:** (`RetirementStrategyService` line 759-817)

1. **Alternative to Contributions:**
   - Only if still not on track after prior 3 strategies
   - Probability < 95%

2. **Realistic Check:**
   - Total achievable income >= 95% of current target
   - (No point if user is way off target)

3. **Calculation:**
   - Recommended target = total achievable income
   - (Shows: "accept what you'll actually get, you'll be on track")

**Income Components Visible:**
```php
Guaranteed Income (DB pensions + state pension)
+ Sustainable from DC pot (pot × 0.047)
= Total Achievable Income
```

**Only Shows If:**
- current_achievable >= 95% of target (realistic path to on-track)
- Probability improves materially by accepting lower target

### Cumulative Strategy Tracking

**Problem Being Solved:**
- Each strategy shows impact considering ALL previous strategies
- Prevents double-counting of contributions
- Shows true cumulative probability improvement

**Implementation:**
- Each strategy receives:
  - `prior_cumulative_monthly` - total additional monthly from all prior strategies
  - `prior_cumulative_income` - total additional annual income from all prior
  - `prior_probability` - probability AFTER prior strategies (becomes new baseline)

- Strategies store:
  - `prior_probability` - baseline before this strategy
  - `new_probability` - after this strategy
  - `probability_improvement` - delta (only for this strategy)

**Example Flow:**
```
Initial Probability: 65%
  ↓
+ Employer Match (adds £200/month) → 75% (+10% improvement)
  ↓
+ Contribution Increase (adds £300/month, shown on £200 baseline) → 82% (+7% improvement)
  ↓
+ Retirement Age Delay → 95% (+13% improvement) → ON TRACK!
```

### Pension Decision Tree

```
User has retirement profile?
  ├─ NO → Return empty
  └─ YES
     ├─ Calculate current probability from projections
     ├─ Is probability >= 95%?
     │   ├─ YES → "On track" - no strategies shown
     │   └─ NO
     │      ├─ PRIORITY 1: Workplace pension with unmatched employer?
     │      │   ├─ YES + gap >= 0.5% → Show employer match strategy
     │      │   └─ Update cumulative, recheck probability
     │      │
     │      ├─ PRIORITY 2: Disposable income > 0?
     │      │   ├─ YES + allowance remaining → Show contribution increase
     │      │   └─ Update cumulative, recheck probability
     │      │
     │      ├─ PRIORITY 3: Can delay retirement to age 75?
     │      │   ├─ YES + income gap > £5,000 → Show retirement age
     │      │   └─ Update cumulative, recheck probability
     │      │
     │      └─ PRIORITY 4: Already highly achievable income?
     │          ├─ YES + >= 95% of target achievable → Show income target reduction
     │          └─ NO → No more strategies
     │
     └─ Return ALL strategies with on_track_at_strategy = index
```

---

## Thresholds & Configuration

### Investment Thresholds

| Threshold | Value | Logic |
|-----------|-------|-------|
| Tax Efficiency Score | < 80/100 | Room for tax optimization |
| OCF (High Fee) | > 0.5% | Actively managed vs. index |
| Fee Minimum Saving | > £50/year | Trivial savings ignored |
| Bond Wrapper Min | £50,000 | Transaction cost justified |
| Offshore Bond Min | £100,000 | Costs for additional features justified |
| Rebalancing Drift | 10% (default) | Customizable per account |

### Pension Thresholds

| Threshold | Value | Logic |
|-----------|-------|-------|
| On Track Probability | >= 95% | High confidence retirement ready |
| Employer Match Gap | >= 0.5% | Meaningful difference |
| Income Gap Trigger | > £5,000 | Material shortfall |
| Growth Rate (projection) | 5% | Conservative assumption |
| Withdrawal Rate | 4.7% | Sustainable drawdown |
| Employer Match Limit | 1-50% | Reasonable salary % range |
| Annual Allowance | £60,000 | 2025/26 UK limit |

---

## Frontend Components

### Investment Strategy Components
- **Recommendations.vue** - Main page with load button
- **InvestmentRecommendationsTracker.vue** - Shows all strategies
- **StrategyRecommendationCard.vue** - Individual card display
- **TaxStrategySection.vue** - Tax optimization details
- Modals: ISATransferModal, BondWrapperInfoModal, HarvestLossModal, BedAndISAWizardModal

### Retirement Strategy Components
- **StrategyCard.vue** - Interactive strategy with slider
  - Real-time probability calculation on slider input
  - Projection chart showing pot growth with/without strategy
  - Income comparison: current path vs. strategy path
  - Cumulative impact tracking

---

## API Endpoints

### Investment
- `GET /api/investment` - Portfolio strategy analysis
- `POST /api/portfolio-strategy` - Generate strategies

### Retirement
- `GET /api/retirement` - Full retirement analysis
- `POST /api/retirement/strategies` - Generate strategies
- `POST /api/retirement/calculate-strategy-impact` - Slider calculation

### Aggregation
- `GET /api/recommendations` - All module recommendations (via RecommendationsAggregatorService)

---

# Files Changed (21 January 2026)

## Summary of Changes

Removed auto-created cash holding behavior and implemented "Enter Holdings" display pattern for investments and pensions when no holdings exist.

## Backend Files

### app/Http/Controllers/Api/InvestmentController.php
- **Change**: Removed auto-created 100% Cash holding when investment account is created
- **Lines removed**: 311-326 (Holding::create block)
- **New behavior**: Account created without holdings; user must add manually

### app/Agents/InvestmentAgent.php
- **Change**: Added holdings count check before generating diversification recommendation
- **New logic**: Only trigger "Improve Portfolio Diversification" when `holdings_count > 0`
```php
$holdingsCount = $analysis['portfolio_summary']['holdings_count'] ?? 0;
if ($holdingsCount > 0 && $analysis['diversification_score'] < 70) {
    // Generate recommendation
}
```

## Frontend Files

### resources/js/views/Investment/AccountSummaryPanel.vue
- **Change**: YTD Return field shows "Enter Holdings" when no holdings
- **New template**:
```vue
<span v-if="hasHoldings && account.ytd_return !== null" class="detail-value">
  {{ formatReturn(account.ytd_return) }}
</span>
<span v-else class="detail-value text-blue-600 cursor-pointer hover:underline"
      @click="$emit('add-holding')">Enter Holdings</span>
```

### resources/js/views/Investment/AccountPerformancePanel.vue
- **Change**: Diversification Insights card shows three states:
  1. No holdings → "Enter Holdings" (clickable)
  2. Has holdings + recommendations → Shows recommendation list
  3. Has holdings + no recommendations → "Well Diversified"
- **Added emit**: `add-holding`
- **New template for no holdings**:
```vue
<div v-if="!hasHoldings" class="text-center py-4">
  <p class="text-lg font-semibold text-blue-600 hover:underline">Enter Holdings</p>
  <p class="text-xs text-gray-500 mt-1">Add holdings to see diversification analysis</p>
</div>
```

### resources/js/views/Retirement/PensionDetail.vue
- **Change**: Expected Return field shows "Enter Holdings" when no holdings
- **Added computed**: `hasHoldings` - checks `pension.holdings?.length > 0`
- **Added method**: `addHoldings()` - opens edit modal
- **New template**:
```vue
<p v-if="hasHoldings" class="text-2xl font-bold text-gray-900">
  {{ pension.expected_return_percent || 0 }}%
</p>
<p v-else class="text-lg font-semibold text-blue-600 cursor-pointer hover:underline"
   @click="addHoldings">Enter Holdings</p>
```

## Key Behavior Changes

| Component | Previous | New |
|-----------|----------|-----|
| InvestmentController | Auto-created 100% Cash holding | No auto-created holdings |
| AccountSummaryPanel (YTD Return) | Showed 0% | Shows "Enter Holdings" link |
| AccountPerformancePanel (Diversification) | Hidden when no recommendations | Shows "Enter Holdings" or "Well Diversified" |
| PensionDetail (Expected Return) | Always showed percentage | Shows "Enter Holdings" when no holdings |
| InvestmentAgent | Triggered diversification rec even with no holdings | Only triggers when holdings exist |
