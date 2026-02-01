# Retirement Income Planner - Technical Map

## Executive Summary

The Retirement Income Planner is a Vue.js + Laravel module that calculates tax-optimised retirement income drawdown strategies. Users can interactively model their retirement income from multiple account types (pensions, ISAs, GIAs, savings) with real-time tax calculations, fund depletion projections, and tax-efficient allocation recommendations.

---

## 1. Account Types Used

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

### GIA Account Tax Treatment

- GIA accounts will generally have a CGT tax liability
- We will not deduct any tax from a GIA account, however we will note it as taxable
- As the CGT is calculated elsewhere, and fluctuates, this account is lower on the priority of use list

---

## 2. Available Accounts Structure

When `getAvailableAccounts()` is called, it returns accounts with **projected values at retirement** using Monte Carlo 80% confidence (20th percentile). All drawable assets are projected forward to the user's target retirement age.

### Projection Methods by Account Type

| Account Type | Projection Method | Source |
|--------------|-------------------|--------|
| Pension Pot (combined DC) | Monte Carlo 80% | `RetirementProjectionService::projectPensionPot()` → `percentile_20_at_retirement` |
| Stocks & Shares ISA | Monte Carlo 80% | `InvestmentProjectionService::getAccountProjectedValue80()` |
| GIA | Monte Carlo 80% | `InvestmentProjectionService::getAccountProjectedValue80()` |
| Onshore Bond | Monte Carlo 80% | `InvestmentProjectionService::getAccountProjectedValue80()` |
| Offshore Bond | Monte Carlo 80% | `InvestmentProjectionService::getAccountProjectedValue80()` |
| Cash ISA | Compound growth | 2% annual growth rate |
| DB Pension | N/A | Income-based (no pot value) |
| State Pension | N/A | Income-based (no pot value) |

### Pension Pot (Combined DC Pensions with sub-accounts)

All DC pensions are combined into a single "Pension Pot" using the Monte Carlo 80% projected value. This represents the combined value of ALL DC pensions at retirement age.

```php
[
    'id' => 'pension_pot',
    'type' => 'pension_pot',
    'owner_id' => $userId,
    'name' => 'Pension Pot',
    'value' => 485000.00,              // Monte Carlo 80% projected value at retirement
    'pcls_available' => 121250.00,     // 25% of projected value (tax-free)
    'tax_treatment' => 'taxable',
    'is_projected' => true,
    'sub_accounts' => [
        [
            'source_type' => 'pension_pot_pcls',
            'source_id' => 'pension_pot',
            'name' => 'Pension Pot - Tax-Free Cash (PCLS)',
            'max_amount' => 121250.00,  // 25% of projected pot
            'tax_rate' => 0,
            'tax_treatment' => 'tax_free'
        ],
        [
            'source_type' => 'pension_pot_drawdown',
            'source_id' => 'pension_pot',
            'name' => 'Pension Pot - Drawdown',
            'max_amount' => 363750.00,  // 75% of projected pot
            'tax_rate' => null,         // Marginal rate
            'tax_treatment' => 'taxable'
        ]
    ]
]
```

### DB Pension

DB pensions are income-based (no pot value to project).

```php
[
    'id' => 1,
    'type' => 'db_pension',
    'owner_id' => $userId,
    'name' => 'NHS Pension',
    'provider' => 'NHS',
    'value' => null,                    // No pot value
    'annual_income' => 15000.00,        // Fixed annual income
    'payment_start_age' => 60,
    'lump_sum_entitlement' => 45000.00,
    'tax_treatment' => 'taxable',
    'source_type' => 'db_pension',
    'source_id' => 1
]
```

### State Pension

State pensions are income-based (no pot value to project).

```php
[
    'id' => 1,
    'type' => 'state_pension',
    'owner_id' => $userId,
    'name' => 'State Pension',
    'value' => null,
    'annual_income' => 11502.00,        // Full new state pension
    'payment_start_age' => 67,
    'already_receiving' => false,
    'tax_treatment' => 'taxable',
    'source_type' => 'state_pension',
    'source_id' => 1
]
```

### ISA - Stocks & Shares (Monte Carlo 80%)

```php
[
    'id' => 1,
    'type' => 'isa_investment',
    'owner_id' => $userId,
    'name' => 'Vanguard ISA',
    'platform' => 'Vanguard',
    'current_value' => 100000.00,       // Current value today
    'value' => 156420.00,               // Monte Carlo 80% projected value
    'is_projected' => true,
    'years_projected' => 15,
    'projection_type' => 'monte_carlo_80',
    'isa_type' => 'stocks_shares',
    'tax_rate' => 0,
    'tax_treatment' => 'tax_free',
    'source_type' => 'isa',
    'source_id' => 1
]
```

### ISA - Cash (Compound Growth)

```php
[
    'id' => 2,
    'type' => 'isa_cash',
    'owner_id' => $userId,
    'name' => 'Marcus Cash ISA',
    'current_value' => 20000.00,        // Current value today
    'value' => 26930.00,                // Projected at 2% growth
    'is_projected' => true,
    'years_projected' => 15,
    'growth_rate' => 0.02,              // 2% for cash
    'isa_type' => 'cash',
    'tax_rate' => 0,
    'tax_treatment' => 'tax_free',
    'source_type' => 'isa',
    'source_id' => 2
]
```

### GIA (Monte Carlo 80%)

```php
[
    'id' => 1,
    'type' => 'gia',
    'owner_id' => $userId,
    'name' => 'Hargreaves Lansdown GIA',
    'platform' => 'Hargreaves Lansdown',
    'current_value' => 50000.00,        // Current value today
    'value' => 78210.00,                // Monte Carlo 80% projected value
    'is_projected' => true,
    'years_projected' => 15,
    'projection_type' => 'monte_carlo_80',
    'tax_rate' => null,                 // Marginal rate (CGT applies)
    'tax_treatment' => 'taxable',
    'source_type' => 'gia',
    'source_id' => 1
]
```

### Onshore Bond (Monte Carlo 80%)

```php
[
    'id' => 1,
    'type' => 'onshore_bond',
    'owner_id' => $userId,
    'name' => 'Prudential Onshore Bond',
    'provider' => 'Prudential',
    'current_value' => 100000.00,       // Current value today
    'value' => 156420.00,               // Monte Carlo 80% projected value
    'is_projected' => true,
    'years_projected' => 15,
    'projection_type' => 'monte_carlo_80',
    'original_investment' => 80000.00,  // For 5% calculation
    'annual_tax_free_allowance' => 4000.00,  // 5% of original investment
    'tax_rate' => 0,                    // Within 5% allowance
    'tax_treatment' => 'tax_deferred',
    'source_type' => 'onshore_bond',
    'source_id' => 1
]
```

### Offshore Bond (Monte Carlo 80%)

```php
[
    'id' => 1,
    'type' => 'offshore_bond',
    'owner_id' => $userId,
    'name' => 'RL360 Offshore Bond',
    'provider' => 'RL360',
    'current_value' => 150000.00,       // Current value today
    'value' => 234630.00,               // Monte Carlo 80% projected value
    'is_projected' => true,
    'years_projected' => 15,
    'projection_type' => 'monte_carlo_80',
    'original_investment' => 100000.00,
    'annual_tax_free_allowance' => 5000.00,  // 5% of original investment
    'tax_rate' => 0,                    // Within 5% allowance
    'tax_treatment' => 'tax_deferred',
    'source_type' => 'offshore_bond',
    'source_id' => 1
]
```

### Key Fields for Projected Accounts

| Field | Description |
|-------|-------------|
| `current_value` | The account's value today |
| `value` | The **projected** value at retirement (used for calculations) |
| `is_projected` | Boolean flag indicating value is projected |
| `years_projected` | Number of years until retirement |
| `projection_type` | Either `monte_carlo_80` or compound growth rate |

---

## 3. Tax Optimisation Strategy

### 3.1 Default Allocation Algorithm

When the user loads the planner, `calculateDefaultAllocations()` runs the following:

```text
Check if user has any Guaranteed Income (can't avoid) from 
├── State Pension (if retirement_age >= 67)
└── DB Pension (if retirement_age >= normal_retirement_age)
    These use Personal Allowance first, reducing overall tax, and work out any potential tax user may have

This will reduce the target income needed (either user entered or default) by the after tax income received from above, giving us a net target income to work with.
We will use this 'net target income' as the starting point for the fund depletion algorithim, which is run at this point.
Once this is run, and we have confirmed either the net target income or a reduced income due to insufficient funds, we can start to draw from the various funds as follows:

Tax-Free/Tax-Deferred Sources (Bonds, PCLS, ISA)
├── Bonds: 5% of original investment (tax-deferred, uses cumulative allowance)
├── Fill remaining personal allowance(if state pension and any db pension payments left any personal allowance) with drawdown
├── PCLS (100% tax-free)
└── ISA accounts, first cash ISA then stocks & shares ISA(100% tax-free)
└── Savings withdrawals (treat as return of capital)
    No immediate tax impact

Taxable Flexible Income
├── DC Drawdown (additional)
├── GIA withdrawals

    Fills remaining target at basic/higher/additional rates
```
If any accounts or items are missing, go to the next step, so if the user has not entered any state pension details, and has no DB pension, run the depletion algorithim, and carry on from that point.

### 3.2 Tax Efficiency Priority Order

We must work out the optimal tax efficient balance for the user between PCLS ans ISA withdrawal to maximise their tax free draw for as long as possible.

```text
1. Always use full personal allowance each year
    If the state pension and/or db payments do not use the full personal allowance, ensure that any remaining personal allowance is used up from drawdown.
    Do not go above the tax years personal allowance limit, for 2025/26 this is £12,570

2. State Pension / DB Pension
   └── Guaranteed, unavoidable - uses PA first

3. Bond Withdrawals (5% tax-deferred)
   └── Uses cumulative 5% allowance - no immediate tax
   └── Preserves tax-deferred growth in remaining bond value

4. ISA Withdrawals (cash isa before stocks & shares)
   └── 100% tax-free, preserves tax-advantaged growth
   └── After bonds as ISA has no cumulative limit

5. DC Pension Drawdown
   └── Use remaining PA, then basic rate

6. GIA / Savings
   └── Last resort - highest marginal rates
```

### 3.3 Tax Band Application

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

## 4. Fund Depletion Projection

### Algorithm

Need to run multiple scenarios on the users income need (taregt or net target income) and the funds they ahve available(pension funds by default). This is done everytime a user includes or excludes an asset from the 'Other Assets' section. This must also run everytime the monte carlo projections changes for any of the included pensions and/or assets.

`projectFundDepletion()` calculates year-by-year fund balances from retirement to age 100:

```
For each year from retirement_age to 100:
    1. Deduct annual withdrawals from each fund for the included assets
       - 5% bond withdrawal
       - DC drawdown up to any remaining personal allowance
       - DC Pension: Deduct PCLS
       - ISA: Deduct Cash ISA then stocks & shares ISA withdrawal
       - GIA: Deduct GIA withdrawal
       - Savings: Deduct savings withdrawal
       - Drawdown: taxable portion over and above the personal allowance

    2. Check for depletion
       - If balance <= 0, record depletion age
       - If the age is < 95 and > 105, run the algorithim again with different values to get within this range

    3. Apply growth (if balance > 0)
       - Pension: 4% growth
       - ISA: 4% growth
       - GIA: 4% growth
       - Cash/Savings: 0% growth

    4. Aggregate by fund type for chart
       - dc_pension: Sum all DC pension balances
       - isa: Sum all Cash ISA balances
       - isa: Sum all Stocks & Shares ISA balances
       - gia: Sum all GIA balances
       - savings: Sum all savings balances
```

## 5. Investment Account Retirement Inclusion

Before an investment account appears in the Retirement Income Planner Sources of Income section, it must be explicitly marked for inclusion. This prevents all investment accounts from automatically appearing in retirement calculations.

### Account Filtering Logic

```text
getAvailableAccounts():
    1. Query InvestmentAccounts WHERE user_id = $userId
    2. Filter: WHERE include_in_retirement = true
    3. Only accounts with this flag enabled are:
       - Shown in the Retirement Income Planner sources of income section
       - Included in fund depletion projections
    4. Accounts with this flag disabled are shown in the 'Other Assets' section.
```

### Database Field

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `include_in_retirement` | boolean | false | Whether account is included in retirement planning |

### User Control

Users can toggle this setting per investment account via a toggle shown on the card in the retirement planner other assets section.

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

## 6. Rules

### Rule 1: Use PMT Formula to Deplete Tax-Free accounts at Age 100

Tax-free accounts should reach £0 at age 100. Use PMT formula for growing accounts(bonds being an exception for now):

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

### Rule 2: PMT Multipliers by Years

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
### Rule 3: Only Reduce Income If Funds Deplete Before 100

```
1. Simulate depletion with target(or net target) income using PMT allocations
2. Check: Do funds hit £0 BEFORE age 100?

IF funds > £0 at age 100:
  → Use target(or net target) income
  → Tax-free accounts deplete to £0
  → Pension/GIA may have remaining balance (acceptable)

IF funds < £0 BEFORE age 95:
  → Income exceeds sustainable level
  → Show warning: "Income will drop at age X"
  → Reduce target(or net target) income to sustainable level
```
## 7. Year by year table

The table must reflect the following for each year from retirement to 100:
- year, showing the age of the user
- withdrawal amount - adjusted for any tax, so if the user's target income is £10k, and there is £1k tax, the withdrawl will be £11k
- columns for the variouse accounts included, if an account is excluded this column must also dissapear, and the table calculation redone and if included the column will appear and calculations redone
- pcls use
- drawdown use
- tax payable
- total balance of all incldued accounts

---

## 8. Testing Protocol

This section documents the comprehensive testing procedures for verifying the Retirement Income Planner functionality.

### 8.1 Prerequisites

1. **Database Seeded**: Run `php artisan db:seed` to ensure all preview personas have data
2. **Dev Server Running**: Start with `./dev.sh` or `php artisan serve` + `npm run dev`
3. **Test Persona**: Use "David & Sarah Mitchell" (peak_earners) - they have comprehensive pension and investment data

### 8.2 Access Path

1. Navigate to http://localhost:8000
2. Click "Try the Demo"
3. Select "David & Sarah Mitchell" persona
4. From Dashboard, click "Pensions" in the Assets section
5. Click "Retirement" in the left sidebar
6. Click on the "Retirement Income Planner" card

### 8.3 Core Value Verification

Verify the following values are displayed correctly:

| Field | Expected Behaviour |
|-------|-------------------|
| Pension Capital at Retirement | Shows Monte Carlo 80% projected value (e.g., £901,562) |
| PCLS | 25% of Pension Capital (e.g., £225,390) |
| Pension Drawdown | 75% of Pension Capital (e.g., £676,171) |
| Other Assets at Retirement | Sum of included ISA/GIA/Savings projected values |
| Optimised Income | Adjusted if funds deplete before age 100, otherwise equals target |
| Annual Tax | £0 while drawing from tax-free sources |

### 8.4 Account Toggle Testing

The "Other Assets" section contains toggles to include/exclude accounts. Test each combination:

#### 8.4.1 Single Account Tests

| Test | Toggle ON | Expected Changes |
|------|-----------|------------------|
| ISA Only | David's S&S ISA | Other Assets increases, Income Sources shows ISA card, Gap to Target reduces |
| GIA Only | Joint GIA | Other Assets increases, Income Sources shows GIA card, GIA column appears in table |
| Savings Only | HSBC/Nationwide | Other Assets increases, Savings column appears in chart/table |
| Cash ISA Only | Nationwide Cash ISA | Other Assets increases, ISA column shows combined value |

#### 8.4.2 Combination Tests

| Test | Accounts Included | Verify |
|------|-------------------|--------|
| All Tax-Free | ISA + Cash ISA | Tax remains £0, ISA column shows combined, depletion order correct |
| Tax-Free + Taxable | ISA + GIA | Tax appears when taxable sources drawn, correct depletion order |
| All Accounts | ISA + GIA + All Savings | Maximum income achieved, all columns visible |
| Pension Only | None toggled on | Only PCLS + Drawdown shown, Other Assets = £0 |

#### 8.4.3 Toggle Verification Checklist

For EACH toggle change, verify:

- [ ] **Summary Cards Update**: Optimised Income, Other Assets, Gap to Target recalculate
- [ ] **Income Sources Update**: New account card appears/disappears with projected value and annual draw
- [ ] **Chart Updates**: Legend shows/hides account type, stacked areas adjust
- [ ] **Table Updates**: Column appears/disappears for toggled account type
- [ ] **Tax Recalculates**: Tax changes appropriately based on taxable/tax-free mix

### 8.5 Fund Depletion Chart Verification

The stacked area chart should display:

| Element | Verification |
|---------|--------------|
| Legend | Shows all included account types (PCLS, Pension Drawdown, ISA, GIA, Savings) |
| X-axis | Age from retirement (e.g., 60) to 100 |
| Y-axis | Fund Value in £k |
| State Pension Age | Vertical annotation at age 67 |
| Depletion Order | Visual confirmation: PCLS depletes first, then ISA, then GIA/Savings, Drawdown last |
| End Balance | Funds reach near £0 at age 100 (or show remaining balance) |

### 8.6 Year-by-Year Table Verification

The depletion table must show:

| Column | Content |
|--------|---------|
| Age | Sequential from retirement age to 100 |
| Withdrawal | Annual withdrawal amount (red, negative) |
| PCLS | Balance with withdrawal shown below (depletes first) |
| Drawdown | Balance with withdrawal shown below |
| ISA | Balance with withdrawal shown below (if included) |
| GIA | Balance with withdrawal shown below (if included) |
| Savings | Balance with withdrawal shown below (if included) |
| Growth | Annual growth amount (green, positive) |
| Taxable Drawdown | Amount exceeding Personal Allowance |
| Tax Paid | Tax amount (red when > 0, green £0 when tax-free) |
| Total Balance | Sum of all fund balances |

#### 8.6.1 Depletion Order Verification

Verify in the table that accounts deplete in this order:

1. **PCLS depletes first** - Should reach £0 within first few years (e.g., by age 64-65)
2. **ISA depletes second** - After PCLS exhausted, ISA draws begin (e.g., depletes by age 72)
3. **GIA/Savings deplete third** - If included, after ISA exhausted
4. **Pension Drawdown last** - Continues until age 100

#### 8.6.2 Tax Transition Verification

- **Tax-free phase**: While drawing from PCLS + ISA, Tax Paid should be £0
- **Tax transition**: When taxable drawdown exceeds PA, tax appears (e.g., age 72+)
- **Consistent tax**: Once in taxable phase, tax should be consistent each year (e.g., £8,704/year)

### 8.7 Edge Case Tests

| Test Case | Setup | Expected |
|-----------|-------|----------|
| No State Pension | User has no state pension entered | Warning shown, projections exclude state pension |
| Already Retired | Retirement age <= current age | Uses current values, not projected |
| Insufficient Funds | Small pension, high target income | Income adjusted down, warning shown |
| Very High Income | Target £150k+ | Additional rate tax calculated |
| Spouse Included | Toggle "Include Spouse" | Combined assets shown, joint calculations |

### 8.8 Calculation Accuracy Tests

#### 8.8.1 PMT Formula Verification

For a given ISA balance and years to retirement:
```
PMT = Balance × (r × (1+r)^n) / ((1+r)^n - 1)
Where r = 0.04 (4%), n = years to age 100
```

Example verification:
- ISA Balance at retirement: £339,510
- Years to age 100: 40
- Expected PMT ≈ £17,153/year (verify Annual draw shown matches)

#### 8.8.2 Tax Calculation Verification

When taxable drawdown occurs:
```
Taxable amount = Drawdown - remaining Personal Allowance
Tax = Taxable amount × 20% (basic rate)
```

Example:
- Total drawdown: £56,092
- State Pension: £11,502 (uses PA first)
- Remaining PA: £12,570 - £11,502 = £1,068
- Taxable: £56,092 - £1,068 = ~£55,024
- Tax at 20%: ~£11,005

### 8.9 Regression Test Checklist

After any code changes, verify:

- [ ] Pension pot uses 80% Monte Carlo projection
- [ ] PCLS is exactly 25% of pension pot
- [ ] Drawdown is exactly 75% of pension pot
- [ ] ISA toggle adds account to Income Sources
- [ ] GIA toggle adds account to Income Sources
- [ ] Chart legend updates when accounts toggled
- [ ] Table columns appear/disappear correctly
- [ ] Tax is £0 during tax-free withdrawal phase
- [ ] Tax appears when taxable withdrawals begin
- [ ] Funds project to age 100 (not depleting early unless income adjusted)
- [ ] Warning message appears if income was adjusted

### 8.10 Browser Console Checks

Open browser DevTools (F12) and check:

1. **No JavaScript errors** in Console tab
2. **API responses successful** in Network tab:
   - `GET /api/retirement/income` returns 200
   - `PATCH /api/investments/{id}` returns 200 when toggling
3. **Correct data in responses**:
   - `projected_pension_pot` is populated
   - `available_accounts` includes pension_pot with sub_accounts
   - `fund_projections` array has entries from retirement age to 100

