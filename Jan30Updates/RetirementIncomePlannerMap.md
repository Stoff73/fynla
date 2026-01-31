# Retirement Income Planner - Technical Map

## CRITICAL: Income Calculation Rule                                                                                      
                                                                                                                            
  Income is CALCULATED from available assets, NOT fixed at target.                                                          
                                                                                                                            
  When ANY asset is added/removed/changed:                                                                                  
  1. Recalculate PMT for ALL included tax-free assets                                                                       
  2. Sum PMT withdrawals = Available Tax-Free Income                                                                        
  3. If gap to target exists, fill with pension drawdown                                                                    
  4. If total PMT > target, reduce pension drawdown to £0 (zero tax)                                                        
  5. Projected Net Income = Sum of all calculated withdrawals                                                               
                                                                                                                            
  THE INCOME CHANGES WHEN ASSETS CHANGE.                 

## Executive Summary

The Retirement Income Planner is a Vue.js + Laravel module that calculates tax-optimised retirement income drawdown strategies. Users can interactively model their retirement income from multiple account types (pensions, ISAs, GIAs, savings) with real-time tax calculations, fund depletion projections, and tax-efficient allocation recommendations.

---

## 1. Data Flow Overview

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                    FRONTEND                                          │
├─────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                      │
│   RetirementIncomeTab.vue                                                           │
│   ├── IncomeSourceSlider.vue (one per allocation)                                   │
│   ├── TaxBreakdownCard.vue                                                          │
│   └── FundDepletionChart.vue                                                        │
│                                                                                      │
│         │                    │                        │                              │
│         ▼                    ▼                        ▼                              │
│   ┌──────────────────────────────────────────────────────────────────────┐          │
│   │                      VUEX STORE (retirement.js)                       │          │
│   │  • retirementIncome          • incomeAllocations                     │          │
│   │  • includeSpouseAssets       • customTargetIncome                    │          │
│   │  • requiredCapital (centralised target income)                       │          │
│   └──────────────────────────────────────────────────────────────────────┘          │
│                                     │                                                │
└─────────────────────────────────────│────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                   API SERVICE                                        │
├─────────────────────────────────────────────────────────────────────────────────────┤
│  retirementService.js                                                                │
│  • getRetirementIncome(includeSpouse)       GET  /api/retirement/income             │
│  • calculateRetirementIncome(allocations)   POST /api/retirement/income/calculate   │
│  • getIncomeAccounts(includeSpouse)         GET  /api/retirement/income/accounts    │
└─────────────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                    BACKEND                                           │
├─────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                      │
│   RetirementController.php                                                           │
│          │                                                                           │
│          ▼                                                                           │
│   RetirementIncomeService.php                                                        │
│   ├── getRetirementIncomeConfig()    → Default allocations + calculations           │
│   ├── calculateIncomeScenario()      → Recalculate with user's allocations          │
│   ├── getAvailableAccounts()         → All eligible accounts for retirement         │
│   ├── calculateTaxBreakdown()        → Tax per source + band usage                  │
│   └── projectFundDepletion()         → Year-by-year projections to age 100          │
│          │                                                                           │
│          ▼                                                                           │
│   TaxBandTracker.php                                                                │
│   └── allocateIncome()               → Allocate to PA/Basic/Higher/Additional       │
│                                                                                      │
└─────────────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                                    MODELS                                            │
├─────────────────────────────────────────────────────────────────────────────────────┤
│  DCPension, DBPension, StatePension, InvestmentAccount, SavingsAccount              │
│  RetirementProfile (target_retirement_age, target_retirement_income)                │
└─────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Account Types Used

The system gathers accounts from multiple sources and treats them differently for tax purposes:

| Account Type | Source Model | Tax Treatment | Tax Rate | Notes |
|--------------|--------------|---------------|----------|-------|
| DC Pension - PCLS | DCPension | Tax-free | 0% | 25% of pot as lump sum |
| DC Pension - Drawdown | DCPension | Taxable | Marginal | Remaining 75%, taxed at marginal rate |
| DB Pension | DBPension | Taxable | Marginal | Fixed annual income, taxed at marginal rate |
| State Pension | StatePension | Taxable | Marginal | Only if retirement age >= 67 |
| Onshore Bond | InvestmentAccount (onshore_bond) | Tax-deferred | 5% tax-free | 5% cumulative withdrawal rule; gains taxed on encashment |
| Offshore Bond | InvestmentAccount (offshore_bond) | Tax-deferred | 5% tax-free | 5% cumulative withdrawal rule; gross roll-up, taxed on encashment |
| Cash ISA | SavingsAccount (is_isa=true) | Tax-free | 0% | No withdrawal restrictions |
| Stocks & Shares ISA | InvestmentAccount (isa type) | Tax-free | 0% | No CGT or income tax |
| GIA | InvestmentAccount (gia type) | Taxable | Marginal | Subject to CGT and dividend tax |
| Savings (non-ISA) | SavingsAccount (is_isa=false) | Taxable | Marginal | Interest taxable, PSA may apply |

### Bond Account Tax Treatment

**5% Cumulative Withdrawal Rule:**
- Policyholders can withdraw up to 5% of the original investment each year without immediate tax
- Unused allowance rolls forward (e.g., if no withdrawals for 5 years, can withdraw 25% tax-free)
- Withdrawals within the 5% allowance are treated as "return of capital" not income
- Tax is deferred until full encashment or exceeding cumulative allowance

**Onshore vs Offshore:**
| Feature | Onshore Bond | Offshore Bond |
|---------|--------------|---------------|
| Fund growth | Taxed within fund at 20% | Gross roll-up (no internal tax) |
| Basic rate taxpayer | No further tax on encashment | 20% tax on gains at encashment |
| Higher rate taxpayer | 20% additional tax on gains | 40% tax on gains at encashment |
| Top-slicing relief | Available | Available |

**"Marginal Rate"**: The exact tax rate depends on total income. The system uses `TaxBandTracker` to calculate based on how much room remains in each tax band.

---

## 3. Available Accounts Structure

When `getAvailableAccounts()` is called, it returns accounts in this format:

### DC Pension (with sub-accounts)
```php
[
    'id' => 1,
    'type' => 'dc_pension',
    'owner_id' => $userId,
    'name' => 'Workplace Pension',
    'provider' => 'Scottish Widows',
    'value' => 250000,
    'pcls_available' => 62500,  // 25% of value
    'annual_contribution' => 6000,
    'tax_treatment' => 'taxable',
    'sub_accounts' => [
        [
            'source_type' => 'dc_pension_pcls',
            'source_id' => 1,
            'name' => 'DC Pension - Tax-Free Cash (PCLS)',
            'max_amount' => 62500,
            'tax_rate' => 0,
            'tax_treatment' => 'tax_free'
        ],
        [
            'source_type' => 'dc_pension_drawdown',
            'source_id' => 1,
            'name' => 'DC Pension - Drawdown',
            'max_amount' => 187500,
            'tax_rate' => null,  // Marginal
            'tax_treatment' => 'taxable'
        ]
    ]
]
```

### DB Pension
```php
[
    'id' => 1,
    'type' => 'db_pension',
    'name' => 'NHS Pension',
    'provider' => 'NHS',
    'value' => null,  // No pot value
    'annual_income' => 15000,
    'payment_start_age' => 60,
    'lump_sum_entitlement' => 45000,
    'tax_treatment' => 'taxable',
    'source_type' => 'db_pension',
    'source_id' => 1
]
```

### State Pension
```php
[
    'id' => 1,
    'type' => 'state_pension',
    'name' => 'State Pension',
    'value' => null,
    'annual_income' => 11502,  // Full new state pension
    'payment_start_age' => 67,
    'already_receiving' => false,
    'tax_treatment' => 'taxable',
    'source_type' => 'state_pension',
    'source_id' => 1
]
```

### ISA (Investment)
```php
[
    'id' => 1,
    'type' => 'isa_investment',
    'name' => 'Vanguard ISA',
    'platform' => 'Vanguard',
    'value' => 100000,
    'isa_type' => 'stocks_shares',
    'tax_rate' => 0,
    'tax_treatment' => 'tax_free',
    'source_type' => 'isa',
    'source_id' => 1
]
```

### GIA
```php
[
    'id' => 1,
    'type' => 'gia',
    'name' => 'Hargreaves Lansdown GIA',
    'platform' => 'Hargreaves Lansdown',
    'value' => 50000,
    'tax_rate' => null,  // Marginal
    'tax_treatment' => 'taxable',
    'source_type' => 'gia',
    'source_id' => 1
]
```

### Onshore Bond
```php
[
    'id' => 1,
    'type' => 'onshore_bond',
    'name' => 'Prudential Onshore Bond',
    'provider' => 'Prudential',
    'value' => 100000,
    'original_investment' => 80000,  // For 5% calculation
    'cumulative_withdrawals' => 12000,  // Already withdrawn
    'cumulative_allowance' => 20000,  // 5% × years held × original
    'available_tax_free' => 8000,  // Remaining allowance
    'tax_rate' => 0,  // Within 5% allowance
    'tax_treatment' => 'tax_deferred',
    'source_type' => 'onshore_bond',
    'source_id' => 1
]
```

### Offshore Bond
```php
[
    'id' => 1,
    'type' => 'offshore_bond',
    'name' => 'RL360 Offshore Bond',
    'provider' => 'RL360',
    'value' => 150000,
    'original_investment' => 100000,
    'cumulative_withdrawals' => 15000,
    'cumulative_allowance' => 25000,  // 5 years × 5%
    'available_tax_free' => 10000,
    'tax_rate' => 0,  // Within 5% allowance
    'tax_treatment' => 'tax_deferred',
    'source_type' => 'offshore_bond',
    'source_id' => 1
]
```

---

## 4. Tax Optimisation Strategy

### 4.1 Default Allocation Algorithm

When the user first loads the planner, `calculateDefaultAllocations()` creates an optimal starting point:

```text
Step 1: Include Guaranteed Income (can't avoid)
├── State Pension (if retirement_age >= 67)
└── DB Pension (if retirement_age >= normal_retirement_age)
    These use Personal Allowance first, reducing overall tax

Step 2: Tax-Free/Tax-Deferred Sources (PCLS, Bonds, ISA)
├── PCLS: Up to 25% of target or available PCLS (100% tax-free)
├── Bonds: 5% of original investment (tax-deferred, uses cumulative allowance)
└── ISA: 4.7% sustainable withdrawal from ISA balance (100% tax-free)
    No immediate tax impact

Step 3: Fill Remaining Personal Allowance
└── DC Drawdown: Use remaining PA on taxable pension income
    £12,570 (2025/26) at 0% effective rate

Step 4: Taxable Flexible Income
├── DC Drawdown (additional)
├── GIA withdrawals
└── Savings withdrawals
    Fills remaining target at basic/higher/additional rates
```

### 4.2 Tax Efficiency Priority Order

```text
1. PCLS (25% tax-free lump sum)
   └── Always use first if available

2. State Pension / DB Pension
   └── Guaranteed, unavoidable - uses PA first

3. Bond Withdrawals (5% tax-deferred)
   └── Uses cumulative 5% allowance - no immediate tax
   └── Preserves tax-deferred growth in remaining bond value

4. ISA Withdrawals (4.7% sustainable)
   └── 100% tax-free, preserves tax-advantaged growth
   └── After bonds as ISA has no cumulative limit

5. DC Pension Drawdown
   └── Use remaining PA, then basic rate

6. GIA / Savings
   └── Last resort - highest marginal rates
```

### 4.3 Tax Band Application

The `TaxBandTracker` service applies income to tax bands in order:

```
┌─────────────────────────────────────────────────────────────────┐
│ Personal Allowance: £12,570 (2025/26)                          │
│ └── First £12,570 of taxable income = 0% tax                   │
├─────────────────────────────────────────────────────────────────┤
│ Basic Rate: £12,571 - £50,270                                  │
│ └── Next £37,700 = 20% tax                                     │
├─────────────────────────────────────────────────────────────────┤
│ Higher Rate: £50,271 - £125,140                                │
│ └── Next £74,870 = 40% tax                                     │
├─────────────────────────────────────────────────────────────────┤
│ Additional Rate: Over £125,140                                 │
│ └── Everything above = 45% tax                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Note:** Personal Allowance tapers for income over £100,000 (reduced by £1 for every £2 over £100k).

---

## 5. Tax Breakdown Calculation

When `calculateTaxBreakdown()` is called with user allocations:

### Input
```php
$incomeAllocations = [
    ['source_type' => 'state_pension', 'annual_amount' => 11502, 'tax_treatment' => 'taxable'],
    ['source_type' => 'db_pension', 'annual_amount' => 15000, 'tax_treatment' => 'taxable'],
    ['source_type' => 'dc_pension_pcls', 'annual_amount' => 10000, 'tax_treatment' => 'tax_free'],
    ['source_type' => 'dc_pension_drawdown', 'annual_amount' => 8498, 'tax_treatment' => 'taxable'],
    ['source_type' => 'isa', 'annual_amount' => 5000, 'tax_treatment' => 'tax_free'],
]
```

### Processing
```
1. Separate tax-free and taxable sources
   Tax-free: PCLS (£10k) + ISA (£5k) = £15,000
   Taxable: SP (£11,502) + DB (£15k) + DC Drawdown (£8,498) = £35,000

2. Apply taxable income to bands via TaxBandTracker:
   PA used: £12,570 (all of it)
   Basic rate: £35,000 - £12,570 = £22,430 @ 20% = £4,486 tax

3. Calculate per-source breakdown:
   State Pension: Uses £11,502 of PA → £0 tax
   DB Pension: Uses £1,068 of PA + £13,932 basic → £2,786 tax
   DC Drawdown: £8,498 all in basic → £1,700 tax
```

### Output
```php
[
    'sources' => [...with individual tax calculations...],
    'gross_income' => 50000,
    'tax_free_total' => 15000,
    'taxable_total' => 35000,
    'personal_allowance_used' => 12570,
    'basic_rate_used' => 22430,
    'higher_rate_used' => 0,
    'additional_rate_used' => 0,
    'total_tax' => 4486,
    'net_income' => 45514,
    'effective_rate' => 0.0897,  // 8.97%
    'band_usage' => [
        'personal_allowance' => ['limit' => 12570, 'used' => 12570, 'remaining' => 0],
        'basic_rate' => ['limit' => 37700, 'rate' => 0.2, 'used' => 22430, 'remaining' => 15270],
        'higher_rate' => ['limit' => 74870, 'rate' => 0.4, 'used' => 0, 'remaining' => 74870],
        'additional_rate' => ['rate' => 0.45, 'used' => 0, 'remaining' => null]
    ]
]
```

---

## 6. Fund Depletion Projection

### Algorithm

`projectFundDepletion()` calculates year-by-year fund balances from retirement to age 100:

```
For each year from retirement_age to 100:
    1. Deduct annual withdrawals from each fund
       - DC Pension: Deduct PCLS + Drawdown amount
       - ISA: Deduct ISA withdrawal
       - GIA: Deduct GIA withdrawal
       - Savings: Deduct savings withdrawal

    2. Check for depletion
       - If balance <= 0, record depletion age
       - Cap withdrawal to remaining balance

    3. Apply growth (if balance > 0)
       - Pension: 4% growth
       - ISA: 4% growth
       - GIA: 4% growth
       - Cash/Savings: 0% growth

    4. Aggregate by fund type for chart
       - dc_pension: Sum all DC pension balances
       - isa: Sum all ISA balances
       - gia: Sum all GIA balances
       - savings: Sum all savings balances
```

### Output
```php
[
    'projections' => [
        ['age' => 68, 'dc_pension' => 240000, 'isa' => 95000, 'gia' => 48000, 'savings' => 20000, 'total_funds' => 403000],
        ['age' => 69, 'dc_pension' => 232800, 'isa' => 93800, 'gia' => 47840, 'savings' => 15000, 'total_funds' => 389440],
        // ... to age 100
    ],
    'depletion_ages' => [
        'dc_pension' => 85,
        'isa' => 92,
        'gia' => 95,
        'savings' => 78
    ]
]
```

---

## 7. Target Income Sources

The system determines target retirement income from multiple sources in priority order:

```
Priority 1: customTargetIncome (user override from modal)
    └── User explicitly set via "Set Target Income" modal

Priority 2: requiredCapital.required_income (centralised calculation)
    └── From RequiredCapitalCalculator: 75% of (gross income - pension contributions)

Priority 3: retirementIncome.target_income (API default)
    └── From RetirementProfile.target_retirement_income or calculated default

Fallback: 0
```

This priority ensures:
1. User's explicit choice takes precedence
2. RequiredCapitalDetail's calculated value is used for consistency
3. Profile setting used if nothing else available

---

## 8. Investment Account Retirement Inclusion

Before an investment account appears in the Retirement Income Planner, it must be explicitly marked for inclusion. This prevents all investment accounts from automatically appearing in retirement calculations.

### Account Filtering Logic

```text
getAvailableAccounts():
    1. Query InvestmentAccounts WHERE user_id = $userId
    2. Filter: WHERE include_in_retirement = true
    3. Only accounts with this flag enabled are:
       - Shown in the Retirement Income Planner
       - Included in fund depletion projections
       - Available for income allocation via sliders
```

### Database Field

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `include_in_retirement` | boolean | false | Whether account is included in retirement planning |

### User Control

Users can toggle this setting per investment account via:
1. Investment account detail view
2. Account edit form
3. Retirement planner settings (future)

### Affected Account Types

This filter applies to:
- Stocks & Shares ISA
- Cash ISA (via SavingsAccount)
- GIA (General Investment Account)
- Onshore Bonds
- Offshore Bonds
- LISA (Lifetime ISA)

### Rationale

Not all investment accounts are suitable for retirement funding:
- Short-term savings goals (e.g., house deposit in ISA)
- Emergency funds in accessible accounts
- Accounts earmarked for other purposes (education, gifts)
- Illiquid investments with lock-in periods

By requiring explicit opt-in, users maintain control over which assets form part of their retirement income strategy.

---

## 9. Spouse Asset Inclusion

> **UI STATUS: HIDDEN**
> The "Include spouse's assets" toggle is currently hidden in the UI. This functionality exists in the backend but is not exposed to users at this time. The toggle may be enabled in a future release.

When `includeSpouseAssets = true` (backend only):

```text
1. Get spouse from linked_user_id relationship
2. Query all account types with:
   WHERE user_id IN ($userId, $spouseId)
3. Each account tagged with owner_id
4. Allocations can draw from either person's accounts
5. Tax calculated across combined household income
6. Fund projections include both people's funds
```

**Important:** Toggling spouse resets allocations to recalculated defaults.

---

## 10. User Interaction Flow

### Initial Load
```
1. Mount RetirementIncomeTab
2. Parallel fetch:
   - fetchRetirementIncome() → Default allocations + calculations
   - fetchRequiredCapital() → Centralised target income
3. Store updates → Components render
4. User sees:
   - Summary cards (Target, Net, Tax)
   - Sliders for each income source
   - Tax breakdown with band usage
   - Fund depletion chart
```

### Slider Adjustment
```
1. User moves slider
2. 150ms debounce in IncomeSourceSlider
3. Emit 'update' to parent
4. Parent updates Vuex allocation
5. 300ms debounce in RetirementIncomeTab
6. POST to /api/retirement/income/calculate
7. Backend recalculates:
   - New tax breakdown
   - New fund depletion projections
8. Store updates → UI reflects changes
9. Total latency: ~600ms for visual feedback
```

### Custom Target Income
```
1. User clicks edit button on Target Income card
2. Modal opens with current value
3. User enters custom amount or clicks "Use Profile Default"
4. setCustomTargetIncome(amount) updates store
5. Triggers recalculation with new target
6. Allocations unchanged, but progress indicators update
```

---

## 11. UI Components

### Summary Cards
| Card | Source | Styling | Purpose |
|------|--------|---------|---------|
| Target Annual Income | `displayTargetIncome` | Blue gradient | Shows goal |
| Projected Net Income | `taxBreakdown.net_income` | Green/Orange/Red based on vs target | After-tax income |
| Annual Tax | `taxBreakdown.total_tax` | Purple gradient | Total tax liability |

### IncomeSourceSlider
- One per allocation source
- Shows name, current amount, tax treatment badge
- Tax badge colours:
  - Green: Tax-free (0%)
  - Indigo: Variable/Marginal
  - Orange: Basic rate (20%)
  - Darker Orange: Higher rate (40%)
  - Red: Additional rate (45%)

### TaxBreakdownCard
- Source-by-source breakdown
- Band usage progress bars
- Effective tax rate
- Optimisation tips (unused PA warning)

### FundDepletionChart
- Stacked area chart (ApexCharts)
- Colour-coded by fund type
- X-axis: Age (retirement to 100)
- Y-axis: Fund balance
- Depletion ages grid below

---

## 12. Key Files Reference

### Frontend
| File | Purpose |
|------|---------|
| `resources/js/components/Retirement/RetirementIncomeTab.vue` | Main orchestrator |
| `resources/js/components/Retirement/IncomeSourceSlider.vue` | Allocation slider |
| `resources/js/components/Retirement/TaxBreakdownCard.vue` | Tax visualisation |
| `resources/js/components/Retirement/FundDepletionChart.vue` | Depletion projections |
| `resources/js/store/modules/retirement.js` | Vuex state management |
| `resources/js/services/retirementService.js` | API calls |

### Backend
| File | Purpose |
|------|---------|
| `app/Http/Controllers/Api/RetirementController.php` | API endpoints |
| `app/Services/Retirement/RetirementIncomeService.php` | Core calculations |
| `app/Services/TaxBandTracker.php` | Tax band allocation |
| `app/Models/RetirementProfile.php` | User retirement settings |
| `app/Models/DCPension.php` | DC pension data |
| `app/Models/DBPension.php` | DB pension data |
| `app/Models/StatePension.php` | State pension data |

---

## 13. Optimisation Strategy Summary

The system optimises for the user by:

1. **Maximising tax-free/tax-deferred income first** - PCLS, then bonds (5% allowance), then ISA before taxable sources
2. **Using Personal Allowance efficiently** - Guaranteed income (SP/DB) uses PA before flexible income
3. **Avoiding higher rate tax** - Defaults stay within basic rate where possible
4. **Exploiting bond 5% rule** - Uses cumulative tax-deferred allowance before touching ISA
5. **Preserving tax-advantaged growth** - ISA withdrawals limited to sustainable 4.7%
6. **Real-time tax feedback** - Sliders show immediate tax impact
7. **Depletion warnings** - Alerts if funds deplete before age 90
8. **Account-level control** - Only accounts marked "include in retirement" appear in planner

The user can override any allocation, but the defaults provide a tax-optimised starting point that minimises overall tax liability while meeting their target retirement income.

---

## 14. Fund Depletion Calculation - Correct Process

### Step 1: Get Target Income

Get the **Target Retirement Income** value from the Pension Pot Forecast Detail View. This is the starting income figure for all calculations.

### Step 2: Calculate Total Funds

Sum ALL drawable funds at retirement:

```
Total Funds = Projected Pension Pot (80% Monte Carlo)
            + All Included Investment Accounts (projected to retirement)
            + All Included Savings Accounts (projected to retirement)
```

Only accounts marked `include_in_retirement = true` are included.

### Step 3: Check Sustainability

Check if the **Target Income** will deplete **Total Funds** before age 100:

```
For each year from retirement_age to 100:
    1. Withdraw target income amount
    2. Apply growth to remaining balance
    3. Check if total_funds <= 0
```

### Step 4: Adjust If Necessary

**If funds DO NOT deplete before age 100:**
→ Use Target Income as-is, show the plan

**If funds DO deplete before age 100:**
→ Calculate reduced income that depletes funds to £0 AT age 100

Use PMT formula to find sustainable withdrawal:
```
Sustainable Withdrawal = Total Funds × (r × (1+r)^n) / ((1+r)^n - 1)

Where:
  r = weighted average growth rate (typically 4%)
  n = years in retirement (100 - retirement_age)
```

**Tolerance:** ±5 years is acceptable. If funds deplete at age 95-105, this is fine.

### Step 5: Tax-Efficient Withdrawal Order

When withdrawing funds each year, follow this order:

```
1. PCLS (25% of pension pot) - Tax-free
2. Bonds (5% of original investment) - Tax-deferred
3. ISA - Tax-free
4. Pension Pot Drawdown (75% of pot) - Taxable
5. GIA - Taxable
6. Savings - Taxable
```

### Step 6: Year-by-Year Projection

For each year from retirement to age 100:

```
START OF YEAR:
  - Record balances for each fund type

WITHDRAWALS:
  - Withdraw in tax-efficient order (Step 5)
  - Withdraw up to annual income target
  - Cap withdrawal at available balance per fund
  - Record withdrawal per fund type

END OF YEAR:
  - Apply growth to remaining balances:
    - Pension/ISA/Bond/GIA: 4% growth
    - Cash/Savings: 2% growth
  - Record new balances
  - Record total_funds (sum of all balances)
```

### Step 7: Output

```
Year-by-Year Table:
| Age | Withdrawal | Pension | ISA | Bond | GIA | Savings | Growth | Total Balance |

Summary:
- Total Starting Funds: £X
- Annual Withdrawal: £Y
- Sustainable Withdrawal: £Z
- Income Was Adjusted: true/false
- All Funds Depleted At: Age X
```

### Key Rules

1. **Income = Withdrawal** for tax-free sources (ISA, PCLS). If withdrawing £50k from ISA, income shown is £50k.

2. **Math must balance**: `Previous Balance - Withdrawal + Growth = New Balance`

3. **PCLS is separate** from Pension Drawdown. Track as separate buckets:
   - `pension_pot_pcls` = 25% of projected pot (tax-free)
   - `pension_pot_drawdown` = 75% of projected pot (taxable)

4. **Only allocated accounts** appear in projections. Don't show accounts that aren't in the income plan.

5. **Target is ceiling, not floor**. Never withdraw MORE than target income. Only withdraw LESS if funds are insufficient.

---

## 15. Fix: True Tax Optimisation Logic (UPDATED)

### The Problem (Fixed)

The original implementation had these issues:

1. **Tax-free sources used fixed rates**: ISA used 4.7%, Bonds used 5% - but these don't deplete accounts to £0 at age 100.

2. **GIA not included from start**: GIA was only used as fallback, not proportionally with pension.

3. **Tax-free money left at death**: Using sustainable rates meant tax-free accounts still had balances at age 100.

### The Correct Logic (PMT-Based Depletion)

#### Rule 1: Use PMT Formula to Deplete Tax-Free at Age 100

Tax-free accounts should reach £0 at age 100. Use PMT formula for growing accounts:

```
PMT Formula: Annual Withdrawal = Balance × (r × (1+r)^n) / ((1+r)^n - 1)

Where:
  r = growth rate (4% for investments, 0% for cash)
  n = years to age 100

PCLS Annual = PCLS Total ÷ Years to 100 (no growth - simple division)
Bond Annual = PMT(bond_balance, 0.04, years_to_100)
ISA Annual  = PMT(isa_balance, growth_rate, years_to_100)
  - Investment ISA: 4% growth
  - Cash ISA: 0% growth (simple division)
```

#### Rule 2: PMT Multipliers by Years

Quick reference for 4% growth:

```
Years to 100 | PMT Multiplier
-------------|----------------
    35       |    0.0536
    38       |    0.0508
    40       |    0.0505
    42       |    0.0495
    45       |    0.0483

Example: £200,000 ISA, 35 years to 100
PMT = £200,000 × 0.0536 = £10,720/year
```

#### Rule 3: Include GIA Proportionally from Start

When taxable income is needed, split between Pension Drawdown and GIA proportionally:

```
Pension Balance: £600,000
GIA Balance: £150,000
Total Taxable: £750,000

Pension share: 600,000 / 750,000 = 80%
GIA share: 150,000 / 750,000 = 20%

If £50,000 taxable needed:
  Pension: £40,000
  GIA: £10,000
```

#### Rule 4: Only Reduce Income If Funds Deplete Before 100

```
1. Simulate depletion with FULL target income using PMT allocations
2. Check: Do funds hit £0 BEFORE age 100?

IF funds > £0 at age 100:
  → Use full target income
  → Tax-free accounts deplete to £0
  → Pension/GIA may have remaining balance (acceptable)

IF funds = £0 BEFORE age 100:
  → Income exceeds sustainable level
  → Show warning: "Income will drop at age X"
  → Or reduce target to sustainable level
```

### Correct Allocation Order (PMT-Based)

When allocating target income to sources:

```
Step 1: Calculate PMT withdrawals to deplete tax-free at age 100
  PCLS Annual = PCLS Total ÷ Years to 100
  Bond PMT = PMT(bond_balance, 0.04, years_to_100)
  ISA PMT = PMT(isa_balance, growth_rate, years_to_100)

Step 2: Allocate from tax-free sources FIRST (using PMT rates)
  remaining = target_income

  IF PCLS PMT available AND remaining > 0:
    allocate min(PCLS PMT, remaining) to PCLS
    remaining -= allocated

  IF Bond PMT available AND remaining > 0:
    allocate min(Bond PMT, remaining) to Bond
    remaining -= allocated

  IF ISA PMT available AND remaining > 0:
    allocate min(ISA PMT, remaining) to ISA
    remaining -= allocated

Step 3: If tax-free doesn't cover target, use TAXABLE sources proportionally
  IF remaining > 0:
    Calculate PMT for Pension Drawdown and GIA
    Split remaining proportionally by balance
    Pension + GIA both deplete together

  IF still remaining > 0:
    allocate from Savings
```

### Example: £80k Target (PMT-Based)

```
User has:
  Pension Pot: £1,000,000 (PCLS = £250,000, Drawdown = £750,000)
  ISA: £400,000
  Bonds: £200,000 (current value)

Years in Retirement: 32 (age 68 to 100)
PMT Multiplier at 4% for 32 years: 0.0540

Tax-Free PMT (to deplete at 100):
  PCLS Annual: £250,000 ÷ 32 = £7,812
  Bond PMT: £200,000 × 0.0540 = £10,800
  ISA PMT: £400,000 × 0.0540 = £21,600

  TOTAL TAX-FREE PMT: £40,212/year

Target Income: £80,000

Allocation (all tax-free accounts deplete at 100):
  PCLS: £7,812 (tax-free)
  Bond: £10,800 (tax-deferred)
  ISA: £21,600 (tax-free)

  Remaining: £80,000 - £40,212 = £39,788 (taxable)
  Pension Drawdown: £39,788

Tax Calculation:
  Personal Allowance: £12,570 @ 0%
  Basic Rate: £39,788 - £12,570 = £27,218 @ 20% = £5,444

  Total Tax: £5,444
  Net Income: £74,556
```

### Example: £40k Target - ZERO TAX (PMT-Based)

```
Same funds as above, target is £40,000

Tax-Free PMT Available: £40,212/year

Since £40,212 > £40,000, we can achieve ZERO TAX!

Allocation (adjusted to hit target):
  PCLS: £7,812 (tax-free)
  Bond: £10,800 (tax-deferred)
  ISA: £21,388 (reduced slightly to hit target)
  Pension Drawdown: £0

Tax Calculation:
  No taxable income = ZERO TAX

  Total Tax: £0
  Net Income: £40,000

Note: ISA slightly under PMT rate, so ISA won't fully deplete at 100.
This is acceptable - tax-free balance at death is fine.
```

### Fund Depletion Check

```
AFTER allocations are calculated using PMT rates:

1. Simulate year-by-year with PMT-based allocations
2. Track each fund balance
3. Tax-free accounts should reach £0 at age 100

Expected outcomes:
  - PCLS: £0 at age 100 (simple division, no growth)
  - Bonds: £0 at age 100 (PMT with 4% growth)
  - ISA: £0 at age 100 (PMT with 4% growth)
  - Pension: May have balance (taxable, acts as reserve)
  - GIA: May have balance (taxable, acts as reserve)

IF target income > sustainable:
  → Taxable accounts deplete before 100
  → Tax-free accounts still deplete at 100 (correct!)
  → Show warning: "Income may need to reduce at age X"
```

### Tax Breakdown Must Match Year-by-Year Table

The tax breakdown and year-by-year withdrawal table MUST show identical values:

```
Tax Breakdown shows:
  PCLS: £7,812 (depletes at 100)
  Bond: £10,800 (depletes at 100)
  ISA: £21,600 (depletes at 100)
  Pension Drawdown: £39,788

Year-by-Year Table (Age 68) shows:
  PCLS withdrawal: £7,812
  Bond withdrawal: £10,800
  ISA withdrawal: £21,600
  Pension withdrawal: £39,788

These MUST match. If they don't, there's a bug.
```

### Summary of Code Changes (IMPLEMENTED)

1. **`calculateDefaultAllocations()`**:
   - PCLS: `PCLS Total ÷ Years to 100` (simple division, no growth)
   - Bonds: `PMT(balance, 4%, years_to_100)` - depletes at 100
   - ISA: `PMT(balance, growth_rate, years_to_100)` - depletes at 100
   - GIA: Included from start, split proportionally with pension

2. **`calculateDefaultAllocations()`**: Tax-free sources allocated using PMT rates to ensure depletion at age 100

3. **`projectFundDepletion()`**: Withdraws in tax-efficient order (PCLS → Bonds → ISA → Drawdown → GIA → Savings)

4. **Taxable split**: When taxable income needed, Pension and GIA are split proportionally by balance so they deplete together

5. **Sustainability check**: If `totalTaxablePMT < remainingTarget`, income is NOT sustainable - show warning

### PMT Formula Reference

```
PMT = PV × (r × (1+r)^n) / ((1+r)^n - 1)

Where:
  PV = Present Value (account balance)
  r = growth rate (0.04 for investments, 0 for cash)
  n = years to age 100

Quick multipliers at 4%:
  30 years: 0.0578
  32 years: 0.0540
  35 years: 0.0536
  38 years: 0.0508
  40 years: 0.0505
  45 years: 0.0483
```
