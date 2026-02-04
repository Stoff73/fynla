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


# Retirement Income Planner - Debugging and Scenario Analysis

## Current Bugs Identified

### Bug 1: Incorrect Withdrawal Order

**Problem:** The current logic doesn't prioritise bond 5% withdrawals first, and depletes tax-free accounts too quickly.

**Correct Order:**

1. **Bond 5% allowance FIRST (MANDATORY)** - Tax-deferred, ALWAYS use annual 5% of original investment if bonds exist
2. **Tax-free accounts (PCLS, ISA)** - Spread over retirement years using PMT to deplete at age 100
3. **Taxable income** - Only when tax-free sources can't meet target
4. **Fill gaps** - Tax-free → Personal Allowance → Basic Rate → Higher Rate

**CRITICAL RULE:** Bond 5% withdrawals are MANDATORY and must ALWAYS be included in the allocation if the user has investment bonds. This is because:
- The 5% allowance is "use it or lose it" - unused allowance rolls forward but is still capped
- Bond withdrawals within 5% are completely tax-deferred (no immediate tax liability)
- NOT using the 5% means leaving tax-efficient income on the table

### Bug 2: PCLS Not Spread Evenly

**Problem:** PCLS capped at 25% of target income instead of being spread over retirement years.

**Fix:** `PCLS Annual = PCLS Total ÷ Years in Retirement`

### Bug 3: Income Reduced Prematurely

**Problem:** PMT formula applied even when funds would last to age 100+.

**Fix:** Only reduce income if funds actually deplete BEFORE age 100.

### Bug 4: Tax-Free Accounts Not Fully Depleted (NEW)

**Problem:** Tax-free accounts (Bonds, ISA, PCLS) left with balances at age 100 while taxable pension drawdown is used.

**Fix:** Calculate withdrawal rates so that Bonds, ISA, and PCLS reach £0 at age 100. Reduce taxable pension drawdown accordingly.

---

## Correct Withdrawal Logic Algorithm

```text
Step 1: Calculate Tax-Free Depletion Rates (to reach £0 at age 100)

    For each tax-free account, calculate the annual withdrawal that depletes it to £0:

    PMT Formula: Annual Withdrawal = Balance × (r × (1+r)^n) / ((1+r)^n - 1)

    Where:
      r = growth rate (4% for investments, 0% for cash)
      n = years to age 100

    Bond Annual = PMT(bond_balance, 0.04, years_to_100)
    ISA Annual = PMT(isa_balance, 0.04, years_to_100)
    PCLS Annual = pcls_balance / years_to_100  (no growth on PCLS cash)

Step 2: Calculate Personal Allowance Usage

    Personal Allowance (PA) = £12,570

    Guaranteed income (State Pension + DB Pension) uses PA FIRST:
      guaranteed_taxable = state_pension + db_pension
      remaining_pa = max(0, PA - guaranteed_taxable)

    Example:
      State Pension: £11,502
      DB Pension: £5,000
      Total guaranteed: £16,502
      PA used: £12,570 (full PA consumed)
      Taxable at Basic Rate: £16,502 - £12,570 = £3,932

Step 3: Allocate Income Sources (Tax-Free First)

    remaining_target = target_income - guaranteed_income

    a) Bond 5% MANDATORY (ALWAYS include if bonds exist)
       - Use PMT formula to deplete bond at age 100
       - This ALWAYS gets allocated regardless of remaining_target
       bond_withdrawal = PMT(bond_balance, 0.04, years_to_100)
       remaining_target -= bond_withdrawal
       (remaining_target may go negative - that's OK)

    b) PCLS MANDATORY (spread to deplete at 100)
       - ALWAYS allocate full PMT to ensure depletion
       pcls_withdrawal = pcls_balance / years_to_100
       remaining_target -= pcls_withdrawal

    c) ISA MANDATORY (spread to deplete at 100)
       - ALWAYS allocate full PMT to ensure depletion
       isa_withdrawal = PMT(isa_balance, 0.04, years_to_100)
       remaining_target -= isa_withdrawal

    d) ONLY if remaining_target > 0, use taxable pension drawdown
       pension_drawdown = remaining_target

       Tax on pension drawdown:
         - If remaining_pa > 0: first £remaining_pa is tax-free
         - Amount over remaining_pa taxed at Basic Rate (20%)

       (If remaining_target <= 0, pension_drawdown = 0 - NO TAX on drawdown!)

    e) If pension insufficient, use GIA, then Savings

Step 4: Apply Growth and Verify Depletion

Step 5: Apply Growth and Verify Depletion

    For each year:
      - Withdraw calculated amounts
      - Apply growth to remaining balances
      - Verify all tax-free accounts reach £0 at age 100
```

---

## PMT Formula Reference

The PMT (Payment) formula calculates the annual withdrawal that depletes a fund to £0 over n years with growth rate r:

```text
PMT = PV × (r × (1+r)^n) / ((1+r)^n - 1)

Where:
  PV = Present Value (current balance)
  r = annual growth rate (e.g., 0.04 for 4%)
  n = number of years

Example:
  Balance: £200,000
  Growth: 4%
  Years: 35

  PMT = 200,000 × (0.04 × 1.04^35) / (1.04^35 - 1)
  PMT = 200,000 × (0.04 × 3.9461) / (3.9461 - 1)
  PMT = 200,000 × 0.1578 / 2.9461
  PMT = 200,000 × 0.0536
  PMT = £10,720/year
```

---

## Tax Band Reference (2025/26)

| Band | Range | Rate |
|------|-------|------|
| Personal Allowance | £0 - £12,570 | 0% |
| Basic Rate | £12,571 - £50,270 | 20% |
| Higher Rate | £50,271 - £125,140 | 40% |
| Additional Rate | £125,140+ | 45% |

---

## SCENARIO 1: Simple ISA + Pension

**Profile:**

- Age: 65, Retirement Age: 65, Life Expectancy: 100 (35 years)
- Target Income: £30,000/year

**Assets:**

| Asset | Balance | Tax Treatment |
|-------|---------|---------------|
| DC Pension | £400,000 | PCLS: Tax-free, Drawdown: Taxable |
| S&S ISA | £200,000 | Tax-free |

**Calculations:**

- PCLS Total: £400,000 × 25% = £100,000
- PCLS Annual (deplete at 100): £100,000 ÷ 35 = £2,857/year
- Pension Drawdown Pool: £400,000 × 75% = £300,000
- ISA PMT (4% growth, 35 years): £200,000 × 0.0536 = £10,720/year

**Tax-Free Total: £2,857 + £10,720 = £13,577/year**

**Remaining from taxable: £30,000 - £13,577 = £16,423/year**

**Annual Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| PCLS | £2,857 | Tax-free | £0 |
| ISA | £10,720 | Tax-free | £0 |
| Pension Drawdown | £16,423 | Taxable | £770.60 |
| **Total** | **£30,000** | | **£770.60** |

**Tax Calculation:**

- Tax-free income: £2,857 + £10,720 = £13,577
- Taxable income: £16,423
- Personal Allowance used: £12,570 (£0 tax)
- Basic Rate: £16,423 - £12,570 = £3,853 × 20% = £770.60

**Year-by-Year Projection (Years 1-10, then key years):**

| Year | Age | PCLS W/D | ISA W/D | Pension W/D | Total W/D | PCLS Bal | ISA Bal | Pension Bal | Total Funds |
|------|-----|----------|---------|-------------|-----------|----------|---------|-------------|-------------|
| 0 | 65 | - | - | - | - | £100,000 | £200,000 | £300,000 | £600,000 |
| 1 | 66 | £2,857 | £10,720 | £16,423 | £30,000 | £97,143 | £196,851 | £294,920 | £588,914 |
| 2 | 67 | £2,857 | £10,720 | £16,423 | £30,000 | £94,286 | £193,605 | £289,713 | £577,604 |
| 3 | 68 | £2,857 | £10,720 | £16,423 | £30,000 | £91,429 | £190,259 | £284,378 | £566,066 |
| 4 | 69 | £2,857 | £10,720 | £16,423 | £30,000 | £88,572 | £186,810 | £278,910 | £554,292 |
| 5 | 70 | £2,857 | £10,720 | £16,423 | £30,000 | £85,715 | £183,252 | £273,303 | £542,270 |
| 10 | 75 | £2,857 | £10,720 | £16,423 | £30,000 | £71,430 | £164,031 | £244,031 | £479,492 |
| 15 | 80 | £2,857 | £10,720 | £16,423 | £30,000 | £57,145 | £141,631 | £211,254 | £410,030 |
| 20 | 85 | £2,857 | £10,720 | £16,423 | £30,000 | £42,860 | £115,515 | £174,481 | £332,856 |
| 25 | 90 | £2,857 | £10,720 | £16,423 | £30,000 | £28,575 | £85,013 | £133,139 | £246,727 |
| 30 | 95 | £2,857 | £10,720 | £16,423 | £30,000 | £14,290 | £49,383 | £86,554 | £150,227 |
| 35 | 100 | £2,857 | £10,720 | £16,423 | £30,000 | £0 | £0 | £33,906 | £33,906 |

**Math Check (Year 1):**

- ISA: (£200,000 - £10,720) × 1.04 = £196,851 ✓
- Pension: (£300,000 - £16,423) × 1.04 = £294,920 ✓
- PCLS: £100,000 - £2,857 = £97,143 (no growth) ✓

**Result:** PCLS and ISA deplete to £0 at age 100. Pension has £33,906 remaining (buffer). Tax reduced from £1,035 to £770.60 by using more ISA.

---

## SCENARIO 2: Bond-Heavy Portfolio

**Profile:**

- Age: 60, Retirement Age: 60, Life Expectancy: 100 (40 years)
- Target Income: £40,000/year

**Assets:**

| Asset | Balance | Original Investment | Tax Treatment |
|-------|---------|---------------------|---------------|
| Onshore Bond | £300,000 | £200,000 | 5% tax-deferred |
| Offshore Bond | £250,000 | £180,000 | 5% tax-deferred |
| DC Pension | £350,000 | - | PCLS/Drawdown |
| Cash ISA | £50,000 | - | Tax-free |

**Calculations:**

- Onshore Bond PMT (4%, 40 yrs): £300,000 × 0.0505 = £15,150/year
- Offshore Bond PMT (4%, 40 yrs): £250,000 × 0.0505 = £12,625/year
- PCLS Total: £350,000 × 25% = £87,500
- PCLS Annual: £87,500 ÷ 40 = £2,188/year
- Pension Drawdown Pool: £350,000 × 75% = £262,500
- Cash ISA (no growth): £50,000 ÷ 40 = £1,250/year

**Tax-Free/Deferred Total: £15,150 + £12,625 + £2,188 + £1,250 = £31,213/year**

**Note:** Bond 5% limits are:

- Onshore: £200,000 × 5% = £10,000 (withdrawing £15,150 exceeds this)
- Offshore: £180,000 × 5% = £9,000 (withdrawing £12,625 exceeds this)

The excess over 5% triggers a "chargeable event" but is still more tax-efficient than pension drawdown for basic rate taxpayers.

**Remaining from taxable: £40,000 - £31,213 = £8,787/year**

**Annual Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| Onshore Bond | £15,150 | Tax-deferred* | £0 |
| Offshore Bond | £12,625 | Tax-deferred* | £0 |
| PCLS | £2,188 | Tax-free | £0 |
| Cash ISA | £1,250 | Tax-free | £0 |
| Pension Drawdown | £8,787 | Taxable | £0 |
| **Total** | **£40,000** | | **£0** |

*Bond withdrawals within policy, tax deferred until full encashment

**Tax Calculation:**

- Taxable: £8,787
- Personal Allowance: £12,570 covers all
- **Total Tax: £0**

**Year-by-Year Projection:**

| Year | Age | Onshore W/D | Offshore W/D | PCLS W/D | ISA W/D | Pension W/D | Total | Onshore Bal | Offshore Bal | PCLS Bal | ISA Bal | Pension Bal | Total |
|------|-----|-------------|--------------|----------|---------|-------------|-------|-------------|--------------|----------|---------|-------------|-------|
| 0 | 60 | - | - | - | - | - | - | £300,000 | £250,000 | £87,500 | £50,000 | £262,500 | £950,000 |
| 1 | 61 | £15,150 | £12,625 | £2,188 | £1,250 | £8,787 | £40,000 | £296,244 | £246,870 | £85,312 | £48,750 | £263,862 | £941,038 |
| 5 | 65 | £15,150 | £12,625 | £2,188 | £1,250 | £8,787 | £40,000 | £280,907 | £234,089 | £76,560 | £43,750 | £268,579 | £903,885 |
| 10 | 70 | £15,150 | £12,625 | £2,188 | £1,250 | £8,787 | £40,000 | £257,618 | £214,682 | £65,620 | £37,500 | £276,171 | £851,591 |
| 20 | 80 | £15,150 | £12,625 | £2,188 | £1,250 | £8,787 | £40,000 | £195,031 | £162,526 | £43,740 | £25,000 | £299,632 | £725,929 |
| 30 | 90 | £15,150 | £12,625 | £2,188 | £1,250 | £8,787 | £40,000 | £108,512 | £90,427 | £21,860 | £12,500 | £335,416 | £568,715 |
| 40 | 100 | £15,150 | £12,625 | £2,188 | £1,250 | £8,787 | £40,000 | £0 | £0 | £0 | £0 | £388,125 | £388,125 |

**Result:** ZERO TAX! All tax-free accounts deplete at 100. Pension grows to £388k as reserve. Excellent tax efficiency.

---

## SCENARIO 3: High Income Requirement

**Profile:**

- Age: 55, Retirement Age: 55, Life Expectancy: 100 (45 years)
- Target Income: £80,000/year

**Assets:**

| Asset | Balance | Tax Treatment |
|-------|---------|---------------|
| DC Pension | £800,000 | PCLS/Drawdown |
| S&S ISA | £300,000 | Tax-free |
| GIA | £150,000 | Taxable (CGT) |

**Step 1: Calculate Tax-Free Withdrawals (to deplete at 100)**

- PCLS Total: £800,000 × 25% = £200,000
- PCLS Annual: £200,000 ÷ 45 = £4,444/year
- ISA PMT (4%, 45 yrs): £300,000 × 0.0483 = £14,490/year

**Tax-Free Total: £4,444 + £14,490 = £18,934/year**

**Step 2: Calculate Taxable Withdrawal Needed**

Remaining from taxable: £80,000 - £18,934 = £61,066/year

**Step 3: Check Sustainability of Taxable Sources**

- Pension Drawdown Pool: £600,000
- GIA: £150,000
- Total Taxable Pool: £750,000

Sustainable PMT from £750,000 at 4% over 45 years:
PMT = £750,000 × 0.0483 = £36,225/year

**Problem:** We need £61,066/year but can only sustain £36,225/year!

This means taxable accounts WILL deplete before age 100.

**Step 4: Calculate When Taxable Accounts Deplete**

Combined taxable (£750,000) at 4% growth, withdrawing £61,066/year:

Using annuity formula: n ≈ 17 years

**Taxable accounts deplete around age 72.**

**Step 5: Split Taxable Withdrawal Between Pension and GIA**

To deplete BOTH taxable sources together at age 72 (17 years):

- Pension PMT (4%, 17 yrs): £600,000 × 0.0822 = £49,320/year
- GIA PMT (4%, 17 yrs): £150,000 × 0.0822 = £12,330/year
- Total: £61,650/year ≈ £61,066 needed ✓

**Corrected Annual Allocation (Ages 55-71):**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| PCLS | £4,444 | Tax-free | £0 |
| ISA | £14,490 | Tax-free | £0 |
| Pension Drawdown | £48,853 | Taxable | Higher Rate |
| GIA | £12,213 | Taxable | Higher Rate |
| **Total** | **£80,000** | | **£11,858** |

**Tax Calculation:**

- Tax-free: £4,444 + £14,490 = £18,934
- Taxable: £48,853 + £12,213 = £61,066
- Personal Allowance: £12,570 @ 0%
- Basic Rate: £37,700 @ 20% = £7,540
- Higher Rate: £61,066 - £50,270 = £10,796 @ 40% = £4,318.40
- **Total Tax: £11,858.40**

**Year-by-Year Projection (Phase 1: Ages 55-71):**

| Year | Age | PCLS | ISA | Pension | GIA | Total | PCLS Bal | ISA Bal | Pension Bal | GIA Bal | Total Funds |
|------|-----|------|-----|---------|-----|-------|----------|---------|-------------|---------|-------------|
| 0 | 55 | - | - | - | - | - | £200,000 | £300,000 | £600,000 | £150,000 | £1,250,000 |
| 1 | 56 | £4,444 | £14,490 | £48,853 | £12,213 | £80,000 | £195,556 | £296,930 | £573,193 | £143,298 | £1,208,977 |
| 5 | 60 | £4,444 | £14,490 | £48,853 | £12,213 | £80,000 | £177,780 | £282,787 | £487,656 | £121,914 | £1,070,137 |
| 10 | 65 | £4,444 | £14,490 | £48,853 | £12,213 | £80,000 | £155,560 | £262,994 | £366,510 | £91,628 | £876,692 |
| 15 | 70 | £4,444 | £14,490 | £48,853 | £12,213 | £80,000 | £133,340 | £239,362 | £219,036 | £54,759 | £646,497 |
| 17 | 72 | £4,444 | £14,490 | £48,853 | £12,213 | £80,000 | £124,452 | £227,001 | £130,892 | £32,723 | £515,068 |

**Phase 2: After Taxable Sources Deplete (Ages 72-100)**

Around age 72-73, pension and GIA both reach £0. Only tax-free sources remain:

- PCLS remaining: ~£115,000
- ISA remaining: ~£215,000

**New Annual Income (Tax-Free Only):**

| Source | Amount | Tax |
|--------|--------|-----|
| PCLS | £4,444 | £0 |
| ISA | £14,490 | £0 |
| **Total** | **£18,934** | **£0** |

**Income Drops from £80,000 to £18,934!**

**Full Projection (Ages 72-100):**

| Year | Age | PCLS | ISA | Total | PCLS Bal | ISA Bal | Total Funds |
|------|-----|------|-----|-------|----------|---------|-------------|
| 18 | 73 | £4,444 | £14,490 | £18,934 | £120,008 | £221,091 | £341,099 |
| 25 | 80 | £4,444 | £14,490 | £18,934 | £88,900 | £186,238 | £275,138 |
| 35 | 90 | £4,444 | £14,490 | £18,934 | £44,460 | £127,350 | £171,810 |
| 45 | 100 | £4,444 | £14,490 | £18,934 | £0 | £0 | £0 |

**Summary:**

| Phase | Ages | Annual Income | Annual Tax | Notes |
|-------|------|---------------|------------|-------|
| Phase 1 | 55-72 | £80,000 | £11,858 | All sources used |
| Phase 2 | 73-100 | £18,934 | £0 | Tax-free only |

**Key Insight:** £80,000/year is NOT sustainable to age 100 with these assets. The user has two choices:

1. **Accept income drop:** £80k for 17 years, then £19k for 28 years
2. **Reduce target:** Sustainable income to age 100 = ~£55,000/year

**Sustainable Income Calculation:**

If target was reduced to deplete ALL accounts at 100:

- PCLS: £4,444/year
- ISA PMT: £14,490/year
- Pension PMT: £600,000 × 0.0483 = £28,980/year
- GIA PMT: £150,000 × 0.0483 = £7,245/year
- **Total Sustainable: £55,159/year**

At £55,159/year:
- Tax-free: £18,934
- Taxable: £36,225
- Tax: £36,225 - £12,570 = £23,655 × 20% = £4,731/year (all basic rate!)

**Result:** High income (£80k) cannot be sustained. Either accept income cliff at 72, or reduce to sustainable £55k.

---

## SCENARIO 4: Zero Tax Achievable

**Profile:**

- Age: 60, Retirement Age: 60, Life Expectancy: 100 (40 years)
- Target Income: £25,000/year

**Assets:**

| Asset | Balance | Original Investment | Tax Treatment |
|-------|---------|---------------------|---------------|
| DC Pension | £300,000 | - | PCLS/Drawdown |
| S&S ISA | £400,000 | - | Tax-free |
| Onshore Bond | £200,000 | £150,000 | 5% tax-deferred |

**Calculations:**

- Bond PMT (4%, 40 yrs): £200,000 × 0.0505 = £10,100/year
- PCLS Total: £300,000 × 25% = £75,000
- PCLS Annual: £75,000 ÷ 40 = £1,875/year
- ISA PMT (4%, 40 yrs): £400,000 × 0.0505 = £20,200/year
- Pension Drawdown Pool: £225,000

**Tax-Free Total: £10,100 + £1,875 + £20,200 = £32,175/year**

Since £32,175 > £25,000, we can achieve **ZERO TAX** and not touch pension!

**Adjusted Allocation (to hit exactly £25,000):**

Reduce ISA withdrawal: £25,000 - £10,100 - £1,875 = £13,025/year from ISA

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| Bond | £10,100 | Tax-deferred | £0 |
| PCLS | £1,875 | Tax-free | £0 |
| ISA | £13,025 | Tax-free | £0 |
| Pension Drawdown | £0 | - | £0 |
| **Total** | **£25,000** | | **£0** |

**Year-by-Year Projection:**

| Year | Age | Bond W/D | PCLS W/D | ISA W/D | Pension W/D | Total | Bond Bal | PCLS Bal | ISA Bal | Pension Bal | Total |
|------|-----|----------|----------|---------|-------------|-------|----------|----------|---------|-------------|-------|
| 0 | 60 | - | - | - | - | - | £200,000 | £75,000 | £400,000 | £225,000 | £900,000 |
| 1 | 61 | £10,100 | £1,875 | £13,025 | £0 | £25,000 | £197,496 | £73,125 | £402,454 | £234,000 | £907,075 |
| 5 | 65 | £10,100 | £1,875 | £13,025 | £0 | £25,000 | £186,881 | £65,625 | £410,960 | £273,914 | £937,380 |
| 10 | 70 | £10,100 | £1,875 | £13,025 | £0 | £25,000 | £170,594 | £56,250 | £422,406 | £333,171 | £982,421 |
| 20 | 80 | £10,100 | £1,875 | £13,025 | £0 | £25,000 | £129,050 | £37,500 | £451,123 | £493,095 | £1,110,768 |
| 30 | 90 | £10,100 | £1,875 | £13,025 | £0 | £25,000 | £71,877 | £18,750 | £491,247 | £730,145 | £1,312,019 |
| 40 | 100 | £10,100 | £1,875 | £13,025 | £0 | £25,000 | £0 | £0 | £546,621 | £1,080,985 | £1,627,606 |

**Wait - ISA is growing, not depleting!**

The ISA withdrawal (£13,025) is less than growth (£400,000 × 4% = £16,000), so ISA grows.

**Recalculate to deplete ISA at 100:**

Since we need to deplete tax-free accounts, increase ISA withdrawal and keep pension at £0.

New ISA withdrawal needed to reach £0 at 100: PMT = £400,000 × 0.0505 = £20,200/year

But total would be: £10,100 + £1,875 + £20,200 = £32,175 > £25,000 target

**Solution:** Reduce bond withdrawal to balance:

- Target: £25,000
- PCLS: £1,875 (fixed to deplete)
- ISA: £20,200 (PMT to deplete)
- Bond: £25,000 - £1,875 - £20,200 = £2,925/year

**Corrected Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| Bond | £2,925 | Tax-deferred | £0 |
| PCLS | £1,875 | Tax-free | £0 |
| ISA | £20,200 | Tax-free | £0 |
| Pension Drawdown | £0 | - | £0 |
| **Total** | **£25,000** | | **£0** |

**Corrected Year-by-Year:**

| Year | Age | Bond W/D | PCLS W/D | ISA W/D | Pension W/D | Total | Bond Bal | PCLS Bal | ISA Bal | Pension Bal | Total |
|------|-----|----------|----------|---------|-------------|-------|----------|----------|---------|-------------|-------|
| 0 | 60 | - | - | - | - | - | £200,000 | £75,000 | £400,000 | £225,000 | £900,000 |
| 1 | 61 | £2,925 | £1,875 | £20,200 | £0 | £25,000 | £204,958 | £73,125 | £395,392 | £234,000 | £907,475 |
| 10 | 70 | £2,925 | £1,875 | £20,200 | £0 | £25,000 | £232,866 | £56,250 | £343,809 | £333,171 | £966,096 |
| 20 | 80 | £2,925 | £1,875 | £20,200 | £0 | £25,000 | £290,049 | £37,500 | £261,296 | £493,095 | £1,081,940 |
| 30 | 90 | £2,925 | £1,875 | £20,200 | £0 | £25,000 | £385,422 | £18,750 | £147,856 | £730,145 | £1,282,173 |
| 40 | 100 | £2,925 | £1,875 | £20,200 | £0 | £25,000 | £546,098 | £0 | £0 | £1,080,985 | £1,627,083 |

**Problem:** Bond is GROWING because we're only taking £2,925 when growth is £8,000+/year.

**Final Solution:** We must accept that with £25k target and these assets, we cannot deplete all accounts. The optimal strategy is:

1. Deplete PCLS first (no growth): £1,875/year, depletes at 100 ✓
2. Deplete ISA via PMT: £20,200/year, depletes at 100 ✓
3. Remaining £2,925 from bond OR pension

**Choose bond** (tax-deferred) over pension (taxable). Bond will not deplete but that's acceptable.

**Result:** Zero tax achieved. PCLS and ISA deplete at 100. Bond and Pension are reserves. Funds grow to £1.6M+.

---

## SCENARIO 5: Multiple Bonds Portfolio

**Profile:**

- Age: 58, Retirement Age: 58, Life Expectancy: 100 (42 years)
- Target Income: £50,000/year

**Assets:**

| Asset | Balance | Original Investment | Tax Treatment |
|-------|---------|---------------------|---------------|
| Prudential Onshore | £180,000 | £120,000 | Tax-deferred |
| Aviva Onshore | £150,000 | £100,000 | Tax-deferred |
| RL360 Offshore | £220,000 | £160,000 | Tax-deferred |
| DC Pension | £450,000 | - | PCLS/Drawdown |
| S&S ISA | £100,000 | - | Tax-free |

**Calculations (PMT to deplete at 100):**

- Prudential PMT (4%, 42 yrs): £180,000 × 0.0495 = £8,910/year
- Aviva PMT (4%, 42 yrs): £150,000 × 0.0495 = £7,425/year
- RL360 PMT (4%, 42 yrs): £220,000 × 0.0495 = £10,890/year
- PCLS Total: £450,000 × 25% = £112,500
- PCLS Annual: £112,500 ÷ 42 = £2,679/year
- ISA PMT (4%, 42 yrs): £100,000 × 0.0495 = £4,950/year
- Pension Drawdown Pool: £337,500

**Tax-Free/Deferred Total: £8,910 + £7,425 + £10,890 + £2,679 + £4,950 = £34,854/year**

**Remaining from taxable: £50,000 - £34,854 = £15,146/year**

**Annual Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| Prudential Bond | £8,910 | Tax-deferred | £0 |
| Aviva Bond | £7,425 | Tax-deferred | £0 |
| RL360 Bond | £10,890 | Tax-deferred | £0 |
| PCLS | £2,679 | Tax-free | £0 |
| ISA | £4,950 | Tax-free | £0 |
| Pension Drawdown | £15,146 | Taxable | £515.20 |
| **Total** | **£50,000** | | **£515.20** |

**Tax Calculation:**

- Taxable: £15,146
- Personal Allowance: £12,570 @ 0%
- Basic Rate: £15,146 - £12,570 = £2,576 × 20% = £515.20

**Year-by-Year Projection:**

| Year | Age | Bonds Total | PCLS | ISA | Pension | Total | Prudential | Aviva | RL360 | PCLS Bal | ISA Bal | Pension Bal | Total |
|------|-----|-------------|------|-----|---------|-------|------------|-------|-------|----------|---------|-------------|-------|
| 0 | 58 | - | - | - | - | - | £180,000 | £150,000 | £220,000 | £112,500 | £100,000 | £337,500 | £1,100,000 |
| 1 | 59 | £27,225 | £2,679 | £4,950 | £15,146 | £50,000 | £177,934 | £148,278 | £217,474 | £109,821 | £98,892 | £335,248 | £1,087,647 |
| 10 | 68 | £27,225 | £2,679 | £4,950 | £15,146 | £50,000 | £159,654 | £133,045 | £195,133 | £85,710 | £87,040 | £319,988 | £980,570 |
| 20 | 78 | £27,225 | £2,679 | £4,950 | £15,146 | £50,000 | £125,855 | £104,879 | £153,822 | £59,010 | £68,512 | £294,759 | £806,837 |
| 30 | 88 | £27,225 | £2,679 | £4,950 | £15,146 | £50,000 | £75,041 | £62,534 | £91,717 | £32,310 | £42,103 | £257,780 | £561,485 |
| 42 | 100 | £27,225 | £2,679 | £4,950 | £15,146 | £50,000 | £0 | £0 | £0 | £0 | £0 | £203,821 | £203,821 |

**Result:** Only £515.20 tax on £50k income (1.03% effective rate). All tax-free accounts deplete at 100. Pension remains as buffer.

---

## SCENARIO 6: Large ISA Portfolio

**Profile:**

- Age: 62, Retirement Age: 62, Life Expectancy: 100 (38 years)
- Target Income: £35,000/year

**Assets:**

| Asset | Balance | Tax Treatment |
|-------|---------|---------------|
| S&S ISA (Vanguard) | £500,000 | Tax-free |
| Cash ISA | £80,000 | Tax-free |
| DC Pension | £200,000 | PCLS/Drawdown |

**Calculations (PMT to deplete at 100):**

- PCLS Total: £200,000 × 25% = £50,000
- PCLS Annual: £50,000 ÷ 38 = £1,316/year
- S&S ISA PMT (4%, 38 yrs): £500,000 × 0.0508 = £25,400/year
- Cash ISA (no growth): £80,000 ÷ 38 = £2,105/year
- Pension Drawdown Pool: £150,000

**Tax-Free Total: £1,316 + £25,400 + £2,105 = £28,821/year**

**Remaining from taxable: £35,000 - £28,821 = £6,179/year**

**Annual Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| PCLS | £1,316 | Tax-free | £0 |
| S&S ISA | £25,400 | Tax-free | £0 |
| Cash ISA | £2,105 | Tax-free | £0 |
| Pension Drawdown | £6,179 | Taxable | £0 |
| **Total** | **£35,000** | | **£0** |

**Tax Calculation:**

- Taxable: £6,179
- Personal Allowance: £12,570 covers all
- **Total Tax: £0**

**Year-by-Year Projection:**

| Year | Age | PCLS | S&S ISA | Cash ISA | Pension | Total | PCLS Bal | S&S ISA Bal | Cash ISA Bal | Pension Bal | Total |
|------|-----|------|---------|----------|---------|-------|----------|-------------|--------------|-------------|-------|
| 0 | 62 | - | - | - | - | - | £50,000 | £500,000 | £80,000 | £150,000 | £780,000 |
| 1 | 63 | £1,316 | £25,400 | £2,105 | £6,179 | £35,000 | £48,684 | £493,584 | £77,895 | £149,574 | £769,737 |
| 10 | 72 | £1,316 | £25,400 | £2,105 | £6,179 | £35,000 | £36,840 | £430,115 | £58,950 | £145,034 | £670,939 |
| 20 | 82 | £1,316 | £25,400 | £2,105 | £6,179 | £35,000 | £23,680 | £336,790 | £37,900 | £136,975 | £535,345 |
| 30 | 92 | £1,316 | £25,400 | £2,105 | £6,179 | £35,000 | £10,520 | £205,088 | £16,850 | £124,313 | £356,771 |
| 38 | 100 | £1,316 | £25,400 | £2,105 | £6,179 | £35,000 | £0 | £0 | £0 | £107,185 | £107,185 |

**Result:** ZERO TAX achieved. All tax-free accounts deplete at 100. Pension remains as emergency buffer.

---

## SCENARIO 7: Pension-Dominant Portfolio (DB + State Pension)

**Profile:**

- Age: 67, Retirement Age: 67, Life Expectancy: 100 (33 years)
- Target Income: £55,000/year
- State Pension: £11,502/year (immediate)
- DB Pension: £18,000/year (NHS pension, immediate)

**Assets:**

| Asset | Balance/Income | Tax Treatment |
|-------|----------------|---------------|
| DC Pension (SIPP) | £600,000 | PCLS/Drawdown |
| State Pension | £11,502/year | Taxable (guaranteed) |
| DB Pension (NHS) | £18,000/year | Taxable (guaranteed) |
| S&S ISA | £50,000 | Tax-free |

**Guaranteed Income:** £11,502 + £18,000 = £29,502/year (taxable, unavoidable)

**Calculations:**

- PCLS Total: £600,000 × 25% = £150,000
- PCLS Annual: £150,000 ÷ 33 = £4,545/year
- ISA PMT (4%, 33 yrs): £50,000 × 0.0540 = £2,700/year
- Pension Drawdown Pool: £450,000

**Tax-Free Available: £4,545 + £2,700 = £7,245/year**

**Remaining needed: £55,000 - £29,502 - £7,245 = £18,253/year (from DC Drawdown)**

**Annual Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| State Pension | £11,502 | Taxable | Uses PA |
| DB Pension (NHS) | £18,000 | Taxable | Basic Rate |
| PCLS | £4,545 | Tax-free | £0 |
| ISA | £2,700 | Tax-free | £0 |
| DC Pension Drawdown | £18,253 | Taxable | Basic Rate |
| **Total** | **£55,000** | | **£6,937** |

**Tax Calculation:**

- Tax-free: £4,545 + £2,700 = £7,245
- Taxable: £11,502 + £18,000 + £18,253 = £47,755
- Personal Allowance: £12,570 @ 0%
- Basic Rate: £47,755 - £12,570 = £35,185 × 20% = £7,037

**Year-by-Year Projection:**

| Year | Age | State P | DB Pension | PCLS | ISA | DC Drawdown | Total | PCLS Bal | ISA Bal | DC Bal | Total Funds |
|------|-----|---------|------------|------|-----|-------------|-------|----------|---------|--------|-------------|
| 0 | 67 | - | - | - | - | - | - | £150,000 | £50,000 | £450,000 | £650,000 |
| 1 | 68 | £11,502 | £18,000 | £4,545 | £2,700 | £18,253 | £55,000 | £145,455 | £49,192 | £448,617 | £643,264 |
| 10 | 77 | £11,502 | £18,000 | £4,545 | £2,700 | £18,253 | £55,000 | £104,550 | £40,717 | £433,880 | £579,147 |
| 20 | 87 | £11,502 | £18,000 | £4,545 | £2,700 | £18,253 | £55,000 | £59,100 | £28,277 | £408,648 | £496,025 |
| 33 | 100 | £11,502 | £18,000 | £4,545 | £2,700 | £18,253 | £55,000 | £0 | £0 | £366,104 | £366,104 |

**Result:** Tax unavoidable due to guaranteed DB/State income using Personal Allowance. PCLS and ISA deplete at 100. DC remains as buffer.

---

## SCENARIO 8: Early Retirement with State Pension Gap

**Profile:**

- Age: 55, Retirement Age: 55, State Pension Age: 67 (12-year gap)
- Life Expectancy: 100 (45 years)
- Target Income: £45,000/year

**Assets:**

| Asset | Balance | Tax Treatment |
|-------|---------|---------------|
| DC Pension (SIPP) | £500,000 | PCLS/Drawdown |
| S&S ISA | £250,000 | Tax-free |
| GIA | £100,000 | Taxable |
| State Pension (from 67) | £11,502/year | Taxable |

**Phase 1 (Ages 55-66): No State Pension - 12 years**

- PCLS Total: £500,000 × 25% = £125,000
- PCLS Annual: £125,000 ÷ 45 = £2,778/year
- ISA PMT (4%, 45 yrs): £250,000 × 0.0483 = £12,075/year
- Pension Drawdown Pool: £375,000

**Tax-Free: £2,778 + £12,075 = £14,853/year**

**Remaining: £45,000 - £14,853 = £30,147/year (from pension drawdown)**

**Phase 1 Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| PCLS | £2,778 | Tax-free | £0 |
| ISA | £12,075 | Tax-free | £0 |
| Pension Drawdown | £30,147 | Taxable | £3,515.40 |
| **Total** | **£45,000** | | **£3,515.40** |

**Tax (Phase 1):**

- Taxable: £30,147
- PA: £12,570 @ 0%
- Basic: £30,147 - £12,570 = £17,577 × 20% = £3,515.40

**Phase 2 (Ages 67-100): State Pension Active - 33 years**

State Pension: £11,502/year

**Phase 2 Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| State Pension | £11,502 | Taxable | Uses PA |
| PCLS | £2,778 | Tax-free | £0 |
| ISA | £12,075 | Tax-free | £0 |
| Pension Drawdown | £18,645 | Taxable | £3,515.40 |
| **Total** | **£45,000** | | **£3,515.40** |

**Tax (Phase 2):**

- Taxable: £11,502 + £18,645 = £30,147
- PA: £12,570 @ 0%
- Basic: £17,577 × 20% = £3,515.40

**Year-by-Year Projection:**

| Year | Age | State P | PCLS | ISA | Pension | Total | PCLS Bal | ISA Bal | Pension Bal | GIA Bal | Total |
|------|-----|---------|------|-----|---------|-------|----------|---------|-------------|---------|-------|
| 0 | 55 | £0 | - | - | - | - | £125,000 | £250,000 | £375,000 | £100,000 | £850,000 |
| 1 | 56 | £0 | £2,778 | £12,075 | £30,147 | £45,000 | £122,222 | £247,282 | £358,647 | £104,000 | £832,151 |
| 5 | 60 | £0 | £2,778 | £12,075 | £30,147 | £45,000 | £111,110 | £235,795 | £299,580 | £121,665 | £768,150 |
| 12 | 67 | £11,502 | £2,778 | £12,075 | £18,645 | £45,000 | £91,664 | £211,396 | £207,243 | £163,194 | £673,497 |
| 20 | 75 | £11,502 | £2,778 | £12,075 | £18,645 | £45,000 | £69,440 | £180,195 | £177,982 | £218,470 | £646,087 |
| 30 | 85 | £11,502 | £2,778 | £12,075 | £18,645 | £45,000 | £41,660 | £134,377 | £137,668 | £323,252 | £636,957 |
| 45 | 100 | £11,502 | £2,778 | £12,075 | £18,645 | £45,000 | £0 | £0 | £60,234 | £619,788 | £680,022 |

**Result:** Same tax in both phases. PCLS and ISA deplete at 100. Pension and GIA remain as reserves.

---

## SCENARIO 9: ISA-Only Early Years (Preserve Pension)

**Profile:**

- Age: 55, Retirement Age: 55, Life Expectancy: 100 (45 years)
- Target Income: £30,000/year
- **Strategy:** Maximise tax-free ISA withdrawals, let pension compound

**Assets:**

| Asset | Balance | Tax Treatment |
|-------|---------|---------------|
| DC Pension | £400,000 | PCLS/Drawdown |
| S&S ISA | £350,000 | Tax-free |

**Strategy Analysis:**

Option A: Use ISA + Pension together (standard approach)
Option B: Use ISA only first, then pension (preserve pension growth)

**Option B Calculation:**

ISA-only years until ISA depletes:

- Target: £30,000/year from ISA
- ISA with 4% growth, £30,000 withdrawal

PMT formula reversed: How many years until £350,000 depletes at £30,000/year with 4% growth?

Using annuity formula: n = ln(1 - PV × r / PMT) / ln(1 + r) ... approximately 15 years

**ISA depletes around age 70.**

Meanwhile pension grows: £400,000 × 1.04^15 = £720,366

**Year-by-Year (ISA-Only Phase, Years 1-15):**

| Year | Age | ISA W/D | Pension W/D | Total | ISA Bal | Pension Bal | Total Funds |
|------|-----|---------|-------------|-------|---------|-------------|-------------|
| 0 | 55 | - | - | - | £350,000 | £400,000 | £750,000 |
| 1 | 56 | £30,000 | £0 | £30,000 | £332,800 | £416,000 | £748,800 |
| 5 | 60 | £30,000 | £0 | £30,000 | £263,040 | £486,661 | £749,701 |
| 10 | 65 | £30,000 | £0 | £30,000 | £166,579 | £592,098 | £758,677 |
| 14 | 69 | £30,000 | £0 | £30,000 | £66,682 | £688,493 | £755,175 |
| 15 | 70 | £30,000 | £0 | £30,000 | £39,349 | £716,033 | £755,382 |
| 16 | 71 | £30,000 | £0 | £30,000 | £10,923 | £744,674 | £755,597 |
| 17 | 72 | £10,923 | £19,077 | £30,000 | £0 | £754,541 | £754,541 |

**ISA depletes at age 72.**

**Post-ISA Phase (Age 72-100): Pension Only**

Pension at age 72: £754,541

- PCLS: £754,541 × 25% = £188,635
- PCLS Annual: £188,635 ÷ 28 = £6,737/year
- Drawdown Pool: £754,541 × 75% = £565,906

**Post-ISA Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| PCLS | £6,737 | Tax-free | £0 |
| Pension Drawdown | £23,263 | Taxable | £2,138.60 |
| **Total** | **£30,000** | | **£2,138.60** |

**Tax (Post-ISA):**

- Taxable: £23,263
- PA: £12,570 @ 0%
- Basic: £10,693 × 20% = £2,138.60

**Comparison:**

| Strategy | Tax Years 1-17 | Tax Years 18-45 | Total Tax (45 yrs) |
|----------|----------------|-----------------|-------------------|
| ISA-Only First | £0 | £2,138.60/yr × 28 = £59,881 | £59,881 |
| Standard Blend | ~£770/yr × 45 = £34,650 | Same | £34,650 |

**Result:** ISA-only strategy pays MORE total tax! The standard blended approach is more tax-efficient because it spreads taxable income across more years, using Personal Allowance each year.

---

## SCENARIO 10: Complex Multi-Asset Portfolio

**Profile:**

- Age: 60, Retirement Age: 60, State Pension Age: 67
- Life Expectancy: 100 (40 years)
- Target Income: £60,000/year

**Assets:**

| Asset | Balance | Original Inv. | Tax Treatment |
|-------|---------|---------------|---------------|
| DC Pension (Workplace) | £350,000 | - | PCLS/Drawdown |
| DC Pension (SIPP) | £280,000 | - | PCLS/Drawdown |
| S&S ISA | £180,000 | - | Tax-free |
| Cash ISA | £45,000 | - | Tax-free |
| Onshore Bond | £120,000 | £90,000 | Tax-deferred |
| Offshore Bond | £85,000 | £60,000 | Tax-deferred |
| GIA | £75,000 | - | Taxable |
| Savings | £30,000 | - | Taxable |
| State Pension (from 67) | £11,502/year | - | Taxable |

**Calculations (PMT to deplete at 100):**

**Pensions:**

- Total DC Pension: £350,000 + £280,000 = £630,000
- PCLS Total: £630,000 × 25% = £157,500
- PCLS Annual: £157,500 ÷ 40 = £3,938/year
- Pension Drawdown Pool: £472,500

**Bonds:**

- Onshore PMT (4%, 40 yrs): £120,000 × 0.0505 = £6,060/year
- Offshore PMT (4%, 40 yrs): £85,000 × 0.0505 = £4,293/year

**ISAs:**

- S&S ISA PMT (4%, 40 yrs): £180,000 × 0.0505 = £9,090/year
- Cash ISA (no growth): £45,000 ÷ 40 = £1,125/year

**Tax-Free/Deferred Total: £3,938 + £6,060 + £4,293 + £9,090 + £1,125 = £24,506/year**

**Phase 1 (Ages 60-66): No State Pension**

**Remaining from taxable: £60,000 - £24,506 = £35,494/year**

**Phase 1 Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| Onshore Bond | £6,060 | Tax-deferred | £0 |
| Offshore Bond | £4,293 | Tax-deferred | £0 |
| PCLS | £3,938 | Tax-free | £0 |
| S&S ISA | £9,090 | Tax-free | £0 |
| Cash ISA | £1,125 | Tax-free | £0 |
| Pension Drawdown | £35,494 | Taxable | £4,584.80 |
| **Total** | **£60,000** | | **£4,584.80** |

**Tax (Phase 1):**

- Taxable: £35,494
- PA: £12,570 @ 0%
- Basic: £35,494 - £12,570 = £22,924 × 20% = £4,584.80

**Phase 2 (Ages 67-100): State Pension Active**

State Pension: £11,502/year (uses PA first)

**Phase 2 Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| State Pension | £11,502 | Taxable | Uses PA |
| Onshore Bond | £6,060 | Tax-deferred | £0 |
| Offshore Bond | £4,293 | Tax-deferred | £0 |
| PCLS | £3,938 | Tax-free | £0 |
| S&S ISA | £9,090 | Tax-free | £0 |
| Cash ISA | £1,125 | Tax-free | £0 |
| Pension Drawdown | £23,992 | Taxable | £4,584.80 |
| **Total** | **£60,000** | | **£4,584.80** |

**Tax (Phase 2):**

- Taxable: £11,502 + £23,992 = £35,494
- PA: £12,570 @ 0%
- Basic: £22,924 × 20% = £4,584.80

**Year-by-Year Projection:**

| Yr | Age | State | Bonds | PCLS | ISAs | Pension | Total | Onshore | Offshore | PCLS Bal | S&S ISA | Cash ISA | Pension Bal | GIA | Savings | Total |
|----|-----|-------|-------|------|------|---------|-------|---------|----------|----------|---------|----------|-------------|-----|---------|-------|
| 0 | 60 | £0 | - | - | - | - | - | £120,000 | £85,000 | £157,500 | £180,000 | £45,000 | £472,500 | £75,000 | £30,000 | £1,165,000 |
| 1 | 61 | £0 | £10,353 | £3,938 | £10,215 | £35,494 | £60,000 | £118,498 | £83,935 | £153,562 | £177,587 | £43,875 | £454,486 | £78,000 | £30,600 | £1,140,543 |
| 7 | 67 | £11,502 | £10,353 | £3,938 | £10,215 | £23,992 | £60,000 | £109,231 | £77,369 | £130,234 | £161,788 | £37,125 | £431,584 | £98,358 | £34,326 | £1,080,015 |
| 10 | 70 | £11,502 | £10,353 | £3,938 | £10,215 | £23,992 | £60,000 | £103,416 | £73,250 | £118,358 | £152,612 | £33,750 | £424,523 | £110,611 | £36,818 | £1,053,338 |
| 20 | 80 | £11,502 | £10,353 | £3,938 | £10,215 | £23,992 | £60,000 | £78,251 | £55,428 | £79,108 | £115,502 | £22,500 | £392,883 | £163,866 | £44,592 | £952,130 |
| 30 | 90 | £11,502 | £10,353 | £3,938 | £10,215 | £23,992 | £60,000 | £43,562 | £30,856 | £39,858 | £64,310 | £11,250 | £349,115 | £242,647 | £54,200 | £835,798 |
| 40 | 100 | £11,502 | £10,353 | £3,938 | £10,215 | £23,992 | £60,000 | £0 | £0 | £0 | £0 | £0 | £289,612 | £359,148 | £65,836 | £714,596 |

**Result:** All tax-free accounts (Bonds, PCLS, ISAs) deplete to £0 at age 100. Pension, GIA, and Savings remain as reserves. Same tax in both phases due to same total taxable income.

---

## Summary Comparison

| Scenario | Target | Assets | Tax/Year | Effective Rate | Tax-Free Depleted? |
|----------|--------|--------|----------|----------------|-------------------|
| 1. Simple ISA + Pension | £30,000 | £600,000 | £771 | 2.6% | Yes at 100 |
| 2. Bond-Heavy | £40,000 | £950,000 | £0 | 0% | Yes at 100 |
| 3. High Income | £80,000 | £1,250,000 | £11,858→£0 | 14.8%→0% | Taxable at 72, tax-free at 100 |
| 4. Zero Tax | £25,000 | £900,000 | £0 | 0% | PCLS+ISA at 100 |
| 5. Multiple Bonds | £50,000 | £1,100,000 | £515 | 1.0% | Yes at 100 |
| 6. Large ISA | £35,000 | £780,000 | £0 | 0% | Yes at 100 |
| 7. Pension-Dominant | £55,000 | £650,000 + DB/SP | £7,037 | 12.8% | Yes at 100 |
| 8. Early Retirement | £45,000 | £850,000 | £3,515 | 7.8% | Yes at 100 |
| 9. ISA-Only Strategy | £30,000 | £750,000 | £0→£2,139 | 0→7.1% | ISA early, PCLS late |
| 10. Complex Multi-Asset | £60,000 | £1,165,000 | £4,585 | 7.6% | Yes at 100 |

---

## Key Takeaways

### Correct Withdrawal Order (Priority)

**MANDATORY TAX-FREE/DEFERRED (ALWAYS allocate full PMT):**
1. **Bond PMT (MANDATORY)** - ALWAYS use if bonds exist, PMT to deplete at age 100
2. **PCLS (MANDATORY)** - ALWAYS spread evenly: PCLS Total ÷ Years to 100
3. **ISA PMT (MANDATORY)** - ALWAYS use if ISAs exist, PMT to deplete at age 100

**TAXABLE (only to fill gap after tax-free):**
4. **Pension drawdown** - ONLY if remaining_target > 0 after all tax-free allocated
5. **GIA** - Only if pension insufficient
6. **Savings** - Last resort

**GOAL: remaining_target should be £0 or negative after tax-free allocation = NO TAX or minimal tax!**

### Critical Rules

1. **Bond 5% is MANDATORY** - ALWAYS allocate if bonds exist, regardless of target income
2. **PCLS is MANDATORY** - ALWAYS allocate full PMT to deplete at age 100
3. **ISA is MANDATORY** - ALWAYS allocate full PMT to deplete at age 100
4. **Pension drawdown ONLY for gap** - ONLY allocate if remaining_target > 0 AFTER all tax-free
5. **Goal is NO TAX** - If tax-free PMT >= target, pension_drawdown = £0

**The algorithm should result in ZERO pension drawdown when tax-free sources can cover the target!**

### PMT Formula Quick Reference

```text
PMT = PV × (r × (1+r)^n) / ((1+r)^n - 1)

At 4% growth:
  35 years: multiply balance by 0.0536
  38 years: multiply balance by 0.0508
  40 years: multiply balance by 0.0505
  42 years: multiply balance by 0.0495
  45 years: multiply balance by 0.0483
```

### Why This Matters

Using the standard sustainable withdrawal rate (4.7%) often leaves significant tax-free balances at death. By calculating the exact PMT to deplete at age 100:

- More tax-free income each year
- Less taxable pension drawdown needed
- Lower lifetime tax bill
- Pension grows as reserve (still accessible if needed)
