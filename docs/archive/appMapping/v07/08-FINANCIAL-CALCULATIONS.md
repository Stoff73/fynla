# 08 - Financial Calculations

This chapter documents every formula used across Fynla's calculation services, grouped by module. All tax rates and thresholds are sourced from `TaxConfigService` using the active tax year (2025/26) unless stated otherwise. Currency values are in GBP.

---

## 1. Base Agent Math Utilities

`app/Agents/BaseAgent.php`

All module agents inherit from `BaseAgent`, which provides two core financial functions.

**Compound Growth (Future Value)**

Projects a present value forward at a fixed annual rate.

```
FV = principal * (1 + rate)^years
```

- `rate` is expressed as a decimal (0.05 for 5%).
- Used by every agent for quick asset projection.

**Present Value (Discounting)**

Discounts a future value back to today's money.

```
PV = futureValue / (1 + discountRate)^years
```

**Percentage Change**

```
change = ((newValue - oldValue) / oldValue) * 100
```

Returns zero when `oldValue` is zero to avoid division errors.

**Tax Year Determination**

The UK tax year runs 6 April to 5 April. The system determines the current tax year by checking whether today is on or after 6 April; if so the year is `YYYY/(YY+1)`, otherwise `(YYYY-1)/YY`.

---

## 2. Inheritance Tax (IHT)

`app/Services/Estate/IHTCalculationService.php` (1290 lines)

### 2.1 Tax Configuration (2025/26)

All values come from `TaxConfigService::getInheritanceTax()`:

| Parameter | Value |
|-----------|-------|
| Nil Rate Band (NRB) | 325,000 |
| Residence Nil Rate Band (RNRB) | 175,000 |
| RNRB taper threshold | 2,000,000 |
| RNRB taper rate | 0.50 (1 lost per 2 over threshold) |
| Standard rate | 40% |
| Reduced rate (charitable) | 36% |

### 2.2 Current Estate IHT Calculation

The calculation follows these steps in order:

**Step 1 -- Asset Gathering**

`EstateAssetAggregatorService` collects all user assets: properties, investment accounts, savings accounts, chattels, and business interests. Assets flagged `is_iht_exempt` (DC pensions, certain insurance policies) are excluded from the taxable estate.

**Step 2 -- Liability Gathering**

Outstanding mortgages and other liabilities are summed. For married couples with data sharing enabled, both sets of liabilities are combined.

**Step 3 -- Net Estate**

```
user_net_estate     = user_gross_assets - user_liabilities
spouse_net_estate   = spouse_gross_assets - spouse_liabilities
total_net_estate    = total_gross_assets - total_liabilities
```

**Step 4 -- Nil Rate Band**

```
Married:                NRB = 325,000 * 2 = 650,000
Widowed (transferred):  NRB = 325,000 + nrb_transferred_from_spouse
Single:                 NRB = 325,000
```

The `nrb_transferred_from_spouse` value is stored on the user's IHT profile and represents any unused NRB inherited from a deceased spouse.

**Step 5 -- Residence Nil Rate Band**

Eligibility requires owning a main residence (property_type = `main_residence`).

```
full_rnrb (married)  = 175,000 * 2 = 350,000
full_rnrb (widowed)  = 175,000 + rnrb_transferred_from_spouse
full_rnrb (single)   = 175,000
```

Taper applies when `total_net_estate > 2,000,000`:

```
excess    = total_net_estate - 2,000,000
reduction = excess * 0.5
rnrb      = max(0, full_rnrb - reduction)
```

If the estate is below the taper threshold, the full RNRB is available. If the taper reduces RNRB to zero, the status is reported as "fully tapered away".

**Step 6 -- Rate Determination**

The reduced rate of 36% applies when the user's charitable giving meets the 10% threshold:

```
baseline           = max(0, net_estate - nrb_available)
threshold          = baseline * 0.10
charitable_amount  = net_estate * (charitable_giving_percent / 100)

IF charitable_amount >= threshold AND baseline > 0:
    rate = 0.36  (reduced)
ELSE:
    rate = 0.40  (standard)
```

Note that RNRB is deliberately excluded from the baseline calculation. The baseline is net estate minus NRB only.

**Step 7 -- IHT Liability**

```
total_allowances = nrb + rnrb
taxable_estate   = max(0, total_net_estate - total_allowances)
iht_liability    = taxable_estate * rate
effective_rate   = (iht_liability / total_net_estate) * 100
```

### 2.3 Projected Estate at Death

The service projects the estate forward to the expected date of death using asset-specific methods.

**Life Expectancy**

Uses ONS 2020-2022 actuarial life tables from the `actuarial_life_tables` database table. Looks up the user's current age and gender, returning years of remaining life expectancy. Falls back to `max(1, 85 - current_age)` when no data is found. When no date of birth or gender is recorded, defaults to 25 years.

For married couples, the service calculates life expectancy for both partners and uses the longer of the two (second death scenario), since IHT is assessed on the second death.

**Cash Projection (Integrated Drawdown Model)**

Cash and investment accounts are projected together year-by-year from current age to death age. This prevents unrealistic negative cash accumulation by drawing deficits from investments.

For each year:

```
1. Calculate cash surplus:
   Pre-retirement:  surplus = annual_income - annual_expenses
   Post-retirement: surplus = retirement_income - retirement_expenses

2. Update cash balance:
   cash_balance += surplus

3. If cash_balance < 0:
   deficit = abs(cash_balance)
   cash_balance = 0
   Distribute deficit equally across all investment accounts
   (each account reduced by deficit / account_count)

4. Apply investment growth AFTER drawdown:
   FOR each investment account:
       account.balance *= (1 + growth_rate)
```

Pre-retirement income sums all income sources (employment, self-employment, rental, dividend, interest, other, trust). Expenses come from the expenditure profile; if no profile exists, expenses default to 70% of total income.

Post-retirement income includes the target retirement income from the retirement profile plus state pension (added only when the user reaches state pension age, default 67). Retirement expenses use `essential_expenditure + lifestyle_expenditure` from the retirement profile; if not set, they default to the target retirement income; if that is also unset, they default to 50% of pre-retirement income.

**Investment Growth Rate**

Two methods are supported, selected by the user's estate planning assumptions:

1. **Monte Carlo method**: Derives an annualised rate from the p20 (80% confidence) percentile of a 10-year Monte Carlo projection:

```
implied_rate = (projected_value / current_value)^(1/10) - 1
```

Clamped to the range -10% to +30%. Falls back to 4.7% when no investments exist.

2. **Custom rate**: Uses `custom_investment_rate` from the user's assumptions, divided by 100.

**Property Projection**

```
projected_value = current_value * (1 + growth_rate)^years
```

Default property growth rate is 3% per annum (configurable via assumptions).

**Liability Projection (Linear Amortisation)**

For each mortgage and liability:

```
IF years_to_death >= years_until_end:
    projected_balance = 0        (liability paid off before death)
ELSE:
    remaining_term = years_until_end - years_to_death
    projected_balance = current_balance * (remaining_term / years_until_end)
```

If no end date is set, the liability is assumed to clear at retirement age.

**Chattels and Business Interests**

These are carried forward at current value (no appreciation applied).

**Projected IHT**

```
projected_gross_assets   = projected_cash + projected_investments
                         + projected_properties + projected_chattels
                         + projected_business
projected_net_estate     = projected_gross_assets - projected_liabilities
projected_taxable_estate = max(0, projected_net_estate - total_allowances)
projected_iht_liability  = projected_taxable_estate * iht_rate
```

### 2.4 Estate Health Score

`app/Agents/EstateAgent.php` -- `calculateEstateHealthScore()`

Starts at 100 and applies deductions:

| Condition | Deduction |
|-----------|-----------|
| No IHT profile | -20 |
| IHT ratio > 30% of gross estate | -25 |
| IHT ratio > 20% of gross estate | -15 |
| IHT ratio > 10% of gross estate | -10 |
| Gross estate > 650,000 with no trusts | -10 |
| Married but no linked spouse | -5 |
| Liquid assets < 50% of IHT liability | -15 |

Final score is clamped to the range 0-100.

### 2.5 Seven-Step IHT Mitigation Decision Tree

The `EstateAgent::generateRecommendations()` method evaluates strategies in priority order (cheapest first, trusts as last resort):

1. **Charitable bequest check** -- Can the IHT rate drop from 40% to 36%?
2. **Liquidity and affordability assessment** -- Are there enough liquid assets to pay IHT?
3. **Check existing life cover** -- Does current cover offset the liability?
4. **Annual gifting** (first resort) -- Use the 3,000/year exemption.
5. **Life cover strategy** (second resort) -- Available only when user is age 50 or under.
6. **PET gifting** (third resort) -- Gifts that become exempt after 7 years.
7. **CLT into trust** (last resort) -- 20% immediate charge on value over NRB.

### 2.6 Gifting Exemptions

Values from `TaxConfigService::getGiftingExemptions()`:

| Exemption | Amount |
|-----------|--------|
| Annual exemption | 3,000/year (carry forward 1 year) |
| Small gifts | 250/recipient/year |
| Wedding -- parents | 5,000 |
| Wedding -- grandparents | 2,500 |
| Wedding -- others | 1,000 |
| Normal expenditure from income | Unlimited (if regular and affordable) |

**PET Taper Relief** (gifts between 3 and 7 years before death):

| Years before death | IHT rate applied |
|-------------------|-----------------|
| 3-4 | 32% (80% of 40%) |
| 4-5 | 24% (60% of 40%) |
| 5-6 | 16% (40% of 40%) |
| 6-7 | 8% (20% of 40%) |
| 7+ | 0% (fully exempt) |

---

## 3. Trust Taxation

`app/Services/Trust/IHTPeriodicChargeCalculator.php`

### 3.1 Entry Charge (Chargeable Lifetime Transfer)

Applies when assets are placed into a discretionary trust:

```
chargeable_value = max(0, asset_value - NRB)
entry_charge     = chargeable_value * 0.20
```

The entry charge rate (20%) is half the death rate (40%).

### 3.2 Periodic Charge (10-Year Anniversary)

Applies to relevant property trusts every 10 years:

```
chargeable_value = max(0, trust_value - NRB)
periodic_charge  = chargeable_value * 0.06
```

The 6% rate represents 30% of the lifetime IHT rate (40% * 30% = 12%, applied over 10 years = 6%).

### 3.3 Exit Charge

Applies when assets leave a relevant property trust between periodic charges:

```
effective_rate = 0.06 * (quarters_since_last_charge / 40)
exit_charge    = (asset_value / trust_value) * chargeable_value * effective_rate
```

Capped at 6% of the asset value. Quarters are capped at 40 (10 years).

### 3.4 Trust Income Tax Rates

| Trust type | Standard rate | Dividend rate |
|-----------|--------------|---------------|
| Discretionary / Accumulation & maintenance | 45% | 39.35% |
| Interest in possession | 20% | 8.75% |
| Bare | Beneficiary's marginal rate | Beneficiary's marginal rate |
| Settlor-interested | Settlor's marginal rate | Settlor's marginal rate |

### 3.5 Trust CGT

Flat rate of 24% with a 1,500 annual exemption.

### 3.6 Trust Types

The system supports: `bare`, `interest_in_possession`, `discretionary`, `accumulation_maintenance`, `life_insurance`, `discounted_gift`, `loan`, `mixed`, `settlor_interested`.

---

## 4. Emergency Fund Calculator

`app/Services/Savings/EmergencyFundCalculator.php`

**Runway**

```
runway = total_savings / monthly_expenditure
```

Returns 0 when monthly expenditure is zero or negative.

**Adequacy Score**

```
adequacy_score = min(100, (runway / target_months) * 100)
```

Default target is 6 months.

**Shortfall**

```
shortfall = max(0, target_months - runway)
```

**Monthly Top-Up**

```
monthly_top_up = shortfall_amount / target_months
```

**Adequacy Categories**

| Runway | Category |
|--------|----------|
| >= 6 months | Excellent |
| >= 3 months | Good |
| >= 1 month | Fair |
| < 1 month | Critical |

---

## 5. ISA Tracking

Values from `TaxConfigService::getISAAllowances()`:

| ISA type | Annual limit |
|----------|-------------|
| Combined ISA allowance | 20,000 |
| Lifetime ISA (age 18-39) | 4,000 (included in 20,000) |
| Junior ISA | 9,000 |
| LISA government bonus | 25% (up to 1,000/year) |

**Remaining Allowance**

```
total_used = cash_isa + stocks_shares_isa + lisa_contributions
remaining  = max(0, 20000 - total_used)
```

Tax year runs 6 April to 5 April. All ISA types share the single 20,000 annual limit.

---

## 6. Monte Carlo Simulation

`app/Services/Investment/MonteCarloSimulator.php`

Uses Geometric Brownian Motion to project portfolio values under stochastic returns.

**Algorithm**

```
monthly_return    = expected_return / 12
monthly_volatility = volatility / sqrt(12)

FOR each iteration (default 1000):
    portfolio_value = start_value
    FOR each month (years * 12):
        random_return = NORMAL(mean=monthly_return, std=monthly_volatility)
        portfolio_value = portfolio_value * (1 + random_return) + monthly_contribution
    STORE portfolio_value
```

**Random Number Generation (Box-Muller Transform)**

```
u1 = uniform_random(0, 1)    (clamped to minimum 1e-10)
u2 = uniform_random(0, 1)
z0 = sqrt(-2 * ln(u1)) * cos(2 * pi * u2)
result = mean + (z0 * std_dev)
```

**Percentile Calculation**

Final values are sorted, then percentiles extracted at p10, p25, p50, p75, and p90:

```
index = ceil((percentile / 100) * count) - 1
index = clamp(index, 0, count - 1)
```

| Percentile | Meaning |
|-----------|---------|
| p10 | 90% confidence (pessimistic) |
| p20 | 80% confidence (used for IHT projections) |
| p50 | Median (central estimate) |
| p80 | 20% confidence (optimistic) |
| p90 | 10% confidence (best case) |

**Goal Probability**

```
probability = (count where final_value >= goal_amount) / total_iterations * 100
```

**Caching**

Results are stored in the `monte_carlo_cache` table with a 24-hour TTL. Cache keys follow the pattern `user_{id}_pension_pot_{years}y`.

---

## 7. DC Pension Projection

`app/Services/Retirement/RetirementProjectionService.php`

**Monthly Contribution Calculation**

If `monthly_contribution_amount` is set directly, that value is used. Otherwise:

```
employee_contribution = annual_salary * (employee_percent / 100)
employer_contribution = annual_salary * (employer_percent / 100)
total_annual          = employee_contribution + employer_contribution
monthly_contribution  = total_annual / 12
```

**Pension Pot Projection**

Uses Monte Carlo simulation (see Section 6) with risk parameters derived from the user's risk preference level. The service calls `MonteCarloSimulator::simulate()` with:

- `start_value` = sum of all DC pension `current_fund_value`
- `monthly_contribution` = sum of all DC pension monthly contributions
- `expected_return` and `volatility` from risk preference parameters
- `years` = retirement age - current age
- 1000 iterations

The p20 value at retirement is used for conservative projections.

**Sustainable Withdrawal Rate**

```
DEFAULT_WITHDRAWAL_RATE = 4.7%
TARGET_INCOME_PERCENT   = 75% of net income
```

---

## 8. Pension Annual Allowance

`app/Services/Retirement/AnnualAllowanceChecker.php`

**Standard Annual Allowance**: 60,000/year

**Money Purchase Annual Allowance (MPAA)**: 10,000 (triggered by flexible pension access)

**Total Annual Contributions**

Both employee and employer contributions count towards the annual allowance:

```
IF monthly_contribution_amount > 0:
    total = monthly_contribution_amount * 12
ELSE:
    employee_contrib = annual_salary * (employee_percent / 100)
    employer_contrib = annual_salary * (employer_percent / 100)
    total = employee_contrib + employer_contrib
```

**Tapered Annual Allowance**

Applies when threshold income exceeds the threshold and adjusted income exceeds the adjusted income threshold:

```
threshold_income  = total_income
adjusted_income   = total_income + pension_contributions

IF threshold_income > threshold AND adjusted_income > adjusted_threshold:
    excess    = adjusted_income - adjusted_threshold
    reduction = excess / 2
    tapered   = max(minimum_allowance, standard_allowance - reduction)
```

The minimum tapered allowance is 10,000.

**Carry Forward**

Up to 3 prior years of unused allowance can be carried forward. The current implementation returns a conservative estimate of one year's standard allowance.

**Excess Check**

```
excess_contributions = max(0, total_contributions - (available_allowance + carry_forward))
```

When `excess_contributions > 0`, the excess is taxed at the user's marginal income tax rate.

---

## 9. Required Capital Calculator

`app/Services/Retirement/RequiredCapitalCalculator.php`

**Required Income**

Sourced from the retirement profile's `target_retirement_income`. If not set, falls back to:

```
required_income = (gross_income - pension_contributions) * 0.75
```

**Required Capital at Retirement**

```
required_capital_at_retirement = required_income / withdrawal_rate
```

Default withdrawal rate is 4.7%.

**Future Value with Compound Interest**

```
FV = PV * (1 + r/m)^(m*n)
```

Where `r` = annual rate, `m` = compounding periods per year (default 4, quarterly), `n` = years.

**Future Value with Regular Contributions (Annuity)**

```
FV = PV * (1 + r/m)^(m*n) + PMT * [((1 + r/m)^(m*n) - 1) / (r/m)]
```

Where `PMT` is the contribution per compounding period. When `r = 0`, contributions are simply summed: `PMT * m * n`.

**Present Value (Required Capital Today)**

```
PV = FV / (1 + inflation_rate)^years
```

Default inflation rate is 2.5%.

**Year-by-Year Projection Table**

For each year from 0 to years_to_retirement, the service calculates:
- `accumulated_value`: Future value of current pot plus contributions
- `present_value_today`: Accumulated value discounted back to today's money
- `target_in_today_money`: The retirement target discounted to the current point

---

## 10. Savings Goal Progress

`app/Services/Savings/GoalProgressCalculator.php`

**Progress**

```
shortfall               = max(0, target_amount - current_saved)
required_monthly_savings = shortfall / months_remaining
progress_percent         = (current_saved / target_amount) * 100
```

A goal is on track when `auto_transfer_amount >= required_monthly_savings`.

**Goal Projection with Compound Interest**

```
monthly_rate = interest_rate / 12

IF monthly_rate > 0:
    compound_factor  = (1 + monthly_rate)^months
    projected_amount = current_saved * compound_factor
                     + monthly_contribution * ((compound_factor - 1) / monthly_rate)
ELSE:
    projected_amount = current_saved + (monthly_contribution * months)
```

---

## 11. Income Tax (2025/26)

`app/Services/UKTaxCalculator.php`

### 11.1 Tax Band Tracker

`app/Services/TaxBandTracker.php` tracks personal allowance and band consumption as income types are stacked in order: earned income first, then interest, then dividends.

**Personal Allowance**: 12,570

**Tax Bands**

| Band | Taxable range | Rate |
|------|--------------|------|
| Basic | 12,571 -- 50,270 | 20% |
| Higher | 50,271 -- 125,140 | 40% |
| Additional | 125,141+ | 45% |

### 11.2 Income Tax Calculation Order

Income is stacked in this order:
1. **Earned income** (employment, self-employment, rental, pension) -- uses personal allowance first, then fills basic/higher/additional bands
2. **Interest income** -- taxed at standard rates but with Personal Savings Allowance
3. **Dividend income** -- taxed at dividend-specific rates with dividend allowance
4. **Trust income** -- taxed according to trust type (see Section 3.4)

**Employment income** is reduced by pension contributions before tax:

```
taxable_employment_income = max(0, employment_income - pension_contributions)
```

### 11.3 Personal Savings Allowance (PSA)

| Taxpayer status | PSA |
|----------------|-----|
| Basic rate | 1,000 |
| Higher rate | 500 |
| Additional rate | 0 |

```
taxable_interest = max(0, interest_income - PSA)
```

The PSA is determined by the band position after earned income is allocated.

### 11.4 Section 24 Mortgage Interest Credit

For buy-to-let properties, mortgage interest is no longer deductible from rental profits. Instead, a tax credit at the basic rate (20%) is applied:

```
mortgage_interest_credit = annual_mortgage_interest * 0.20
tax_after_credit         = max(0, total_tax - mortgage_interest_credit)
```

### 11.5 Effective Tax Rate

```
effective_rate = (total_deductions / gross_income) * 100
```

Where `total_deductions = income_tax + national_insurance`.

---

## 12. National Insurance (2025/26)

`app/Services/UKTaxCalculator.php`

### 12.1 Class 1 (Employees)

| Threshold | Rate |
|-----------|------|
| Below primary threshold (12,570) | 0% |
| 12,570 -- 50,270 (main rate) | 8% |
| Above 50,270 (additional rate) | 2% |

```
main_rate_earnings       = min(income - 12570, 50270 - 12570)
main_ni                  = main_rate_earnings * 0.08
additional_rate_earnings = max(0, income - 50270)
additional_ni            = additional_rate_earnings * 0.02
total_ni                 = main_ni + additional_ni
```

### 12.2 Class 4 (Self-Employed)

| Threshold | Rate |
|-----------|------|
| Below lower profits limit (12,570) | 0% |
| 12,570 -- 50,270 | 8% |
| Above 50,270 | 2% |

Same band structure as Class 1, using `lower_profits_limit` and `upper_profits_limit`.

### 12.3 Employer NI

Not calculated in the employee-facing UKTaxCalculator. The rate (13.8% above 9,100) is stored in `TaxConfigService` for reference.

---

## 13. Dividend Tax (2025/26)

`app/Services/UKTaxCalculator.php`

**Dividend Allowance**: 500

```
taxable_dividends = max(0, dividend_income - 500)
```

Dividends are allocated to remaining tax bands after earned and interest income:

| Band | Rate |
|------|------|
| Basic | 8.75% |
| Higher | 33.75% |
| Additional | 39.35% |

Dividends can span multiple bands. The calculator determines how much basic band capacity remains after earned and interest income, then fills higher and additional bands in order.

---

## 14. Capital Gains Tax

`app/Services/Property/PropertyTaxService.php`

**Annual Exempt Amount**: 3,000 (2025/26)

**Property CGT Calculation**

```
acquisition_cost = purchase_price + sdlt_paid
gain             = disposal_price - acquisition_cost - disposal_costs
taxable_gain     = max(0, gain - annual_exempt_amount)
```

**CGT Rates (Residential Property)**

| Taxpayer status | Rate |
|----------------|------|
| Basic rate | 18% |
| Higher/additional rate | 24% |

```
IF total_income > basic_rate_threshold:
    cgt_rate = 0.24
ELSE:
    cgt_rate = 0.18

cgt_liability  = taxable_gain * cgt_rate
effective_rate = (cgt_liability / gain) * 100
```

**Non-Residential CGT Rates** (stored in config):

| Taxpayer status | Rate |
|----------------|------|
| Basic rate | 10% |
| Higher/additional rate | 20% |

**Business Asset Disposal Relief (BADR)**: 10% rate, lifetime limit of 1,000,000.

**Chattel Exemption**: 6,000.

---

## 15. Stamp Duty Land Tax (SDLT)

`app/Services/Property/PropertyTaxService.php`

SDLT is calculated using banded thresholds from `TaxConfigService::getStampDuty()`. The `calculateBandedTax()` method iterates through bands, calculating tax on the portion of the price that falls within each band:

```
FOR each band:
    band_value = min(amount - threshold, next_threshold - threshold)
    tax       += band_value * rate
```

**Standard Residential Bands** (from TaxConfigService):

| Band | Rate |
|------|------|
| 0 -- 125,000 | 0% |
| 125,001 -- 250,000 | 2% |
| 250,001 -- 925,000 | 5% |
| 925,001 -- 1,500,000 | 10% |
| 1,500,001+ | 12% |

**Additional Properties**: +5% surcharge on all bands.

**First-Time Buyers**: 0% up to 300,000, 5% on 300,001 to 500,000. Relief unavailable for properties over 500,000.

**Non-Resident Surcharge**: 2% (stored in config).

**Effective Rate**:

```
effective_rate = (total_sdlt / purchase_price) * 100
```

---

## 16. Rental Income Tax

`app/Services/Property/PropertyTaxService.php`

**Taxable Profit**

```
actual_income     = monthly_rental_income * 12
allowable_expenses = service_charge + ground_rent + insurance
                   + maintenance + other_costs
taxable_profit     = max(0, actual_income - allowable_expenses)
```

**Mortgage Interest Credit**

Mortgage interest cannot be deducted directly from rental profits. Instead:

```
annual_interest          = outstanding_balance * (interest_rate / 100)
mortgage_interest_credit = annual_interest * basic_rate (0.20)
tax_before_credit        = taxable_profit * marginal_tax_rate
tax_liability            = max(0, tax_before_credit - mortgage_interest_credit)
```

**Net Rental Profit**

```
net_rental_profit = actual_income - allowable_expenses - tax_liability
```

---

## 17. Child Benefit

`app/Services/Benefits/ChildBenefitService.php`

**Weekly/Annual Rates**

| Child | Weekly | Annual |
|-------|--------|--------|
| Eldest/only | 26.05 | 1,354.60 |
| Each additional | 17.25 | 897.00 |

Children are sorted by date of birth (oldest first) to identify the eldest. Only family members with `receives_child_benefit = true` and relationship `child` or `step_child` are eligible.

**High Income Child Benefit Charge (HICBC)**

Applies to the higher-earning parent:

```
IF adjusted_net_income <= 60,000:
    charge = 0

ELSE:
    income_over_threshold = adjusted_net_income - 60,000
    clawback_percentage   = min(100, income_over_threshold / 200)
    charge                = child_benefit_amount * (clawback_percentage / 100)
    net_benefit           = child_benefit_amount - charge
```

At 80,000 income (20,000 over threshold), the clawback reaches 100% and the entire benefit is repaid through tax.

**Adjusted Net Income** (simplified):

```
adjusted_net_income = employment + self_employment + rental
                    + dividend + interest + trust
```

The full calculation would also deduct gross pension contributions and Gift Aid donations.

---

## 18. Protection Needs Analysis

`app/Services/Protection/CoverageGapAnalyzer.php`

### 18.1 Human Capital

Represents the present value of future earned income that would be lost on death. Uses NET income (after tax and NI) because that is what the family actually receives. Rental and dividend income are excluded because they continue after death.

```
years_to_retirement = max(0, retirement_age - current_age)
effective_years     = min(years_to_retirement, 10)
human_capital       = net_income_difference * 10 * effective_years
```

Where `net_income_difference = income_that_stops - income_that_continues`. Income that continues includes user's rental/dividend income plus spouse's total income (when data sharing is enabled).

If spouse income exceeds user income, `net_income_difference` is zero and no human capital protection is needed.

### 18.2 Debt Protection

```
debt_need = mortgage_balance + other_debts
```

Uses values from the protection profile. Falls back to querying actual mortgage and liability records.

### 18.3 Education Funding

```
FOR each child:
    years_remaining = max(0, 21 - child_age)
    funding        += 9,000 * years_remaining
```

Assumes 9,000 per year until age 21 for each child.

### 18.4 Final Expenses

Fixed at 7,500 for funeral and final costs.

### 18.5 Total Protection Need

```
total_need = human_capital + debt_protection + education_funding + final_expenses
```

### 18.6 Income Protection Need

```
income_protection_need = gross_income * 0.60
```

Standard recommendation of 60% of gross income.

### 18.7 Coverage Gap Allocation

Life insurance cover is allocated in priority order:
1. Debts first
2. Excess after debts reduces human capital need
3. Remaining excess covers final expenses
4. Any further excess covers education funding

```
total_gap = max(0, total_need - total_coverage)
```

Where `total_coverage = life_coverage + critical_illness_coverage`.

### 18.8 Protection Adequacy Score

`app/Services/Protection/AdequacyScorer.php`

```
coverage_ratio = (total_need - total_gap) / total_need
score          = round(coverage_ratio * 100)
```

Clamped to 0-100. Returns 100 when `total_need` is zero.

| Score | Rating |
|-------|--------|
| >= 80 | Excellent |
| >= 60 | Good |
| >= 40 | Fair |
| < 40 | Critical |

---

## 19. Investment Growth Assumptions

Default values from `TaxConfigurationSeeder` via `TaxConfigService::getAssumptions()`:

| Asset class | Annual growth |
|------------|---------------|
| Cash | 1% |
| Bonds | 2% |
| UK Equities | 5% |
| Global Equities | 5.5% |
| Property | 3% |
| Balanced | 4% |
| Inflation | 2.5% |
| Salary growth | 3% |

The `FutureValueCalculator` provides additional defaults:

| Asset type | Growth rate |
|-----------|------------|
| Property | 3% |
| Investment / Pension | 5% |
| Cash / Savings | 4% |
| Business | 4% |
| Other / Default | 3% |

**Real Future Value (Inflation-Adjusted)**

```
real_growth_rate = ((1 + nominal_rate) / (1 + inflation_rate)) - 1
real_FV          = PV * (1 + real_growth_rate)^years
```

**Compound Annual Growth Rate (CAGR)**

```
CAGR = (target_value / present_value)^(1/years) - 1
```

---

## 20. OCF Impact Calculator

`app/Services/Investment/Fees/OCFImpactCalculator.php`

### 20.1 Weighted Average OCF

```
FOR each holding:
    weight     = holding_value / total_portfolio_value
    weighted_ocf += weight * holding_ocf
```

When a holding has no explicit OCF, the system estimates based on asset type:

| Asset type | Estimated OCF |
|-----------|--------------|
| Index fund / ETF | 0.10% |
| Active fund | 0.75% |
| Equity / Stock | 0.00% |
| Bond | 0.05% |
| Alternative | 1.50% |
| Default | 0.50% |

### 20.2 Portfolio Projection with OCF Drag

```
net_return          = gross_return - weighted_ocf
value_without_ocf   = initial_value * (1 + gross_return)^years
value_with_ocf      = initial_value * (1 + net_return)^years
total_ocf_drag      = value_without_ocf - value_with_ocf
drag_percent        = (total_ocf_drag / value_without_ocf) * 100
```

### 20.3 High-Cost Holding Threshold

Holdings with OCF above 0.75% are flagged. Severity is "high" when OCF exceeds 1.50%.

### 20.4 Active vs Passive Classification

- **Active**: asset_type is `active_fund` OR OCF > 0.50%
- **Passive**: asset_type is `index_fund` or `etf` OR OCF <= 0.25%

### 20.5 Compound Savings from Fee Reduction

```
fee_percent = annual_savings / portfolio_value
compound_savings = value * ((1 + return_rate)^years - (1 + return_rate - fee_percent)^years)
```

### 20.6 OCF Impact Assessment

| Drag % over period | Assessment |
|-------------------|-----------|
| > 30% | Very high |
| > 20% | High |
| > 10% | Medium |
| <= 10% | Low |

---

## 21. Portfolio Statistics

`app/Services/Investment/Analytics/PortfolioStatisticsCalculator.php`

### 21.1 Expected Return

Weighted average of asset class returns:

```
expected_return = SUM(weight_i * return_i)
```

### 21.2 Portfolio Volatility

Uses the covariance matrix approach:

```
variance = SUM_i SUM_j (w_i * w_j * vol_i * vol_j * correlation_ij)
volatility = sqrt(variance)
```

### 21.3 Sharpe Ratio

```
sharpe = (expected_return - risk_free_rate) / volatility
```

Default risk-free rate is 4%.

### 21.4 Sortino Ratio

```
downside_deviation = volatility * 0.7    (approximation)
sortino = (expected_return - risk_free_rate) / downside_deviation
```

### 21.5 Value at Risk (VaR)

Parametric VaR using Z-scores:

```
VaR = |expected_return - (z_score * volatility)|
```

| Confidence | Z-score |
|-----------|---------|
| 90% | 1.28 |
| 95% | 1.645 |
| 99% | 2.326 |

### 21.6 Conditional VaR (Expected Shortfall)

```
CVaR = VaR * 1.2
```

Approximation representing the expected loss beyond the VaR threshold.

### 21.7 Maximum Drawdown Estimate

```
max_drawdown = volatility * 2.0
```

### 21.8 Diversification Ratio

```
weighted_volatility    = SUM(weight_i * volatility_i)
diversification_ratio  = weighted_volatility / portfolio_volatility
```

A ratio greater than 1.0 indicates diversification benefit (the portfolio volatility is lower than the weighted sum of individual volatilities).

### 21.9 Default Correlation Matrix

| | Equities | Bonds | Cash | Alternatives |
|---|---------|-------|------|-------------|
| Equities | 1.00 | 0.20 | 0.05 | 0.40 |
| Bonds | 0.20 | 1.00 | 0.30 | 0.15 |
| Cash | 0.05 | 0.30 | 1.00 | 0.10 |
| Alternatives | 0.40 | 0.15 | 0.10 | 1.00 |

### 21.10 Default Asset Class Assumptions (UK Market)

| Asset class | Expected return | Volatility |
|------------|----------------|-----------|
| Equities | 8% | 18% |
| Bonds | 4% | 6% |
| Cash | 2.5% | 1% |
| Alternatives | 6% | 12% |

---

## 22. Risk Profile Calculator

`app/Services/Risk/AutoRiskCalculator.php`

Calculates an automated risk profile based on 7 financial factors. Each factor maps to a risk level. The final level is the **mode** (most frequent level). In a tie, the lower risk level wins.

**Risk Level Hierarchy** (lowest to highest): `low`, `lower_medium`, `medium`, `upper_medium`, `high`.

### 22.1 Factor 1: Capacity for Loss

```
ratio = (investments + pensions) / net_worth * 100
```

| Ratio | Level |
|-------|-------|
| 0-15% | high |
| 15-50% | medium |
| 50-75% | lower_medium |
| > 75% | low |

Interpretation: A low ratio means less wealth is at market risk, so the user has higher capacity to take investment risk.

### 22.2 Factor 2: Time Horizon

| Years to retirement | Level |
|--------------------|-------|
| Retired or < 3 | lower_medium |
| 3-15 | medium |
| 15-20 | upper_medium |
| 20+ | high |

### 22.3 Factor 3: Education

| Education | Level |
|-----------|-------|
| No degree (secondary, A-level, GCSE, none) | lower_medium |
| Degree or higher | medium |

### 22.4 Factor 4: Dependants

| Dependant count | Level |
|----------------|-------|
| 0 | upper_medium |
| 1 | medium |
| 2+ | lower_medium |

### 22.5 Factor 5: Employment

| Status | Level |
|--------|-------|
| Employed / Self-employed | medium |
| Retired / Semi-retired / Other | lower_medium |

### 22.6 Factor 6: Emergency Cash

```
runway = emergency_fund_total / monthly_expenditure
```

| Runway | Level |
|--------|-------|
| < 3 months | lower_medium |
| 3-6 months | medium |
| 6+ months | upper_medium |

### 22.7 Factor 7: Surplus Cash

```
surplus = (annual_income / 12) - monthly_expenditure
```

| Surplus | Level |
|---------|-------|
| <= 0 | lower_medium |
| 1-500 | medium |
| 501+ | upper_medium |

---

## 23. Life Cover Calculator

`app/Services/Estate/LifeCoverCalculator.php`

Calculates life cover recommendations across three scenarios.

### 23.1 Premium Estimation

Uses industry-average rates for whole-of-life cover:

```
base_rate = 1.50 per 1,000 cover per month

age_loading:
    < 40:  0.8
    40-49: 1.0
    50-59: 1.5
    60-69: 2.5
    70+:   4.0

joint_discount = 0.75 for joint life second death (1.0 for single life)

monthly_premium = (cover_amount / 1000) * base_rate * age_loading * joint_discount
annual_premium  = monthly_premium * 12
```

For joint policies, `age_loading` uses the average of both ages.

### 23.2 Self-Insurance Option

Projects the value of investing premiums instead of buying cover:

```
FV_annuity = payment * ((1 + rate)^periods - 1) / rate
```

Default assumed return: 4.7%. Compares the projected fund value against the IHT liability.

### 23.3 Cost-Benefit Ratio

```
cost_benefit_ratio = cover_amount / total_premiums_paid
```

---

## 24. Mortgage Balance Projection

`app/Services/Estate/FutureValueCalculator.php`

### 24.1 Repayment Mortgage Amortisation

Month-by-month simulation:

```
FOR each month up to projection:
    interest_payment  = remaining_balance * (annual_rate / 100 / 12)
    principal_payment = monthly_payment - interest_payment
    remaining_balance -= principal_payment
    IF remaining_balance <= 0: return 0
```

### 24.2 Interest-Only Mortgage

Balance stays constant (capital not repaid):

```
projected_balance = current_balance
```

### 24.3 Maturity Check

```
IF remaining_term_months <= months_to_project:
    projected_balance = 0    (mortgage paid off)
```

### 24.4 Linear Fallback

When monthly payment data is unavailable:

```
monthly_reduction  = current_balance / remaining_term_months
projected_balance  = current_balance - (monthly_reduction * months_to_project)
```

---

## 25. Summary of Key Constants

| Constant | Value | Source |
|----------|-------|--------|
| Default retirement age | 68 | IHTCalculationService, RequiredCapitalCalculator |
| Default state pension age | 67 | IHTCalculationService, RetirementProjectionService |
| Default property growth | 3% p.a. | IHTCalculationService |
| Sustainable withdrawal rate | 4.7% | RequiredCapitalCalculator, RetirementProjectionService |
| Target income in retirement | 75% of net income | RequiredCapitalCalculator |
| Default compounding periods | 4 (quarterly) | RequiredCapitalCalculator |
| Default inflation | 2.5% | RequiredCapitalCalculator |
| Default fee rate | 1% | RequiredCapitalCalculator |
| Monte Carlo iterations | 1000 | MonteCarloSimulator, RetirementProjectionService |
| Monte Carlo cache TTL | 24 hours | MonteCarloSimulator |
| Expense fallback (pre-retirement) | 70% of income | IHTCalculationService |
| Expense fallback (post-retirement) | 50% of pre-retirement income | IHTCalculationService |
| Final expenses | 7,500 | CoverageGapAnalyzer |
| Education cost per child | 9,000/year to age 21 | CoverageGapAnalyzer |
| Income protection standard | 60% of gross income | CoverageGapAnalyzer |
| Life expectancy fallback | max(1, 85 - age) | IHTCalculationService, FutureValueCalculator |
| Risk-free rate (default) | 4% | PortfolioStatisticsCalculator |
| End age for drawdown | 100 | RetirementProjectionService |
