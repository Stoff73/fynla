# Fynla Data-to-Value Mapping
## Comprehensive Documentation: How Collected Data Transforms into Actionable Recommendations

**Date:** March 2026
**Version:** 1.0
**Scope:** All 9 financial planning modules

---

## Executive Summary

Fynla transforms raw financial data into personalized, actionable recommendations through a sophisticated **data collection → analysis → calculation → recommendation → action** pipeline. Each module orchestrates this flow via dedicated Agents, Services, and database-driven Action Definitions.

The system employs:
- **8 Agents** (module orchestrators) that analyze user data
- **174 Services** (calculation engines) that process specific financial domains
- **Database-driven triggers** (InvestmentActionDefinition, RetirementActionDefinition) that generate configurable recommendations
- **Cross-module coordination** (CoordinatingAgent) to resolve conflicts and optimize cash flow allocation
- **Scenario builders** for what-if planning and projections

---

## 1. PROTECTION MODULE

### A. Data Collection Points
| Data Point | Source | User Input Fields |
|------------|--------|-------------------|
| **Annual Income** | User Profile | `annual_employment_income`, `annual_self_employment_income`, `annual_rental_income`, `annual_dividend_income`, `annual_other_income` |
| **Monthly Expenditure** | Protection Profile / User Profile | `protection_profile.monthly_expenditure` (fallback: calculated from user expenses) |
| **Dependents** | Family Profile | `protection_profile.number_of_dependents` |
| **Mortgage Balance** | Property / Liabilities | `protection_profile.mortgage_balance` or sum of outstanding balances |
| **Other Debts** | Liabilities table | `liabilities.current_balance` (credit cards, loans, etc.) |
| **Retirement Age** | Protection Profile | `protection_profile.retirement_age` |
| **Date of Birth** | User Profile | `user.date_of_birth` (calculates current age) |
| **Existing Policies** | Life Insurance Policies | Sum of `sum_assured` across policy types (life, critical illness, income protection, disability) |

### B. Services & Calculation Logic

**ProtectionAgent** orchestrates via:
1. **CoverageGapAnalyzer**
   - `calculateProtectionNeeds()` - Computes needs based on income, debts, dependents, retirement age
   - `calculateTotalCoverage()` - Sums existing policy sum_assured
   - `calculateCoverageGap()` - Gap = Needs - Coverage

2. **AdequacyScorer**
   - `calculateAdequacyScore()` - Produces score (0-100) based on gap ratio
   - `generateScoreInsights()` - Descriptive text about adequacy level

3. **RecommendationEngine**
   - `generateRecommendations()` - Creates prioritized action items based on gaps
   - Evaluates: life insurance gaps, debt protection, critical illness, income protection, education funding, trust placement

4. **ScenarioBuilder**
   - `modelDeathScenario()` - Projects financial impact if insured dies
   - `modelCriticalIllnessScenario()` - Income replacement during illness
   - `modelDisabilityScenario()` - Long-term disability impact

5. **ProfileCompletenessChecker**
   - Verifies all required fields present before analysis

### C. Recommendations Generated

**Category: Coverage Gaps**
- **Life Insurance Gap**: When `human_capital_gap > £10k` AND user has dependents
  - Data Input: Annual income + months to retirement + number of dependents
  - Calculation: `(years_to_retirement × annual_income) - existing_coverage`
  - Recommendation Output: "Increase life insurance coverage by £{gap_amount}"
  - Action: Obtain term life quote for gap amount

- **Debt Protection Gap**: When `debt_protection_gap > 0`
  - Data Input: Mortgage balance + other debts
  - Calculation: Total debts not covered by decreasing term insurance
  - Recommendation: "Add decreasing term cover for debts of £{amount}"
  - Action: Review mortgage protection insurance

- **Critical Illness Gap**: When user lacks critical illness cover
  - Data Input: Annual income (used to calculate multiplier, typically 3×)
  - Recommendation: "Consider critical illness cover of £{3 × annual_income}"
  - Action: Get CI quotes

- **Income Protection Gap**: When income unprotected
  - Data Input: Monthly expenditure
  - Calculation: Monthly gap if unable to work
  - Recommendation: "Add income protection for £{monthly_expenditure}/month"
  - Action: Review deferred periods and benefit terms

- **Trust Placement**: When policies NOT in trust
  - Data Input: Policies with `in_trust = false`
  - Calculation: Potential IHT exposure if policies included in estate
  - Recommendation: "Place {count} policies in trust to avoid IHT"
  - Action: Contact insurers to execute trust documentation

**Category: Scenarios**
- **Death Scenario**: Projects impact on dependents if primary earner dies
  - Uses: Coverage gaps + remaining income sources + years to retirement

- **Critical Illness Scenario**: Income replacement during recovery period
  - Uses: Benefit amounts + sick pay/employment protections

- **Disability Scenario**: Long-term income protection needs
  - Uses: Benefit amounts + deferred periods

### D. Data → Recommendation Mapping

```
INPUT: Date of birth, Annual income, Monthly expenditure, Dependents, Debts
         ↓
ANALYSIS: Calculate human capital (PV of future income)
         Calculate needs (income replacement + education + debt)
         Calculate coverage (sum of existing policies)
         ↓
OUTPUT: Coverage gap analysis
        Gap recommendations by type
        What-if scenarios
        ↓
VALUE TO USER:
  - Knows exact protection shortfall
  - Receives prioritized protection actions
  - Understands financial impact of death/illness
  - Can plan policy purchases strategically
```

### E. Tax Calculations
- **Not heavily involved** in Protection (no IHT calculation here)
- **Trust placement** recommendation leverages: `TaxConfigService.getInheritanceTax()`

### F. Monte Carlo / Projections
- **Not used in Protection module**
- Simple deterministic gap analysis

---

## 2. ESTATE PLANNING MODULE

### A. Data Collection Points
| Data Point | Source | Model/Field |
|------------|--------|-------------|
| **All Assets** | Multiple tables | Properties, Savings, Investments, Pensions, Business Interests, Chattels, Life Insurance |
| **All Liabilities** | Liabilities table | Mortgages, personal loans, credit cards |
| **Marital Status** | User Profile | `user.marital_status`, `user.spouse_id` |
| **Spouse Assets** | Spouse User record | If `data_sharing = true`, includes spouse assets |
| **Date of Birth** | User Profile | Calculates current age |
| **Will Preferences** | Will table | `bequests`, `wishes`, `charitable_giving` |
| **Life Insurance Policies** | LifeInsurancePolicy | `sum_assured`, `in_trust` (critical for IHT planning) |
| **Family Members** | FamilyMember table | Children, dependents (inform discretionary trust recommendations) |
| **Trusts Existing** | Trust table | Current trust arrangements |
| **Previous Gifts** | Gift table | Historic gifts to inform 7-year availability |

### B. Services & Calculation Logic

**EstateAgent** orchestrates via:

1. **IHTCalculationService**
   - `calculate($user, $spouse, $dataSharingEnabled)`
   - Computes: Gross estate → Deductions → Taxable estate → IHT liability
   - Uses: `TaxConfigService.getInheritanceTax()` for NRB (£325k), RNRB (£175k), rates (40%)
   - Formula: `IHT = max(0, (taxable_estate - available_nrb) × rate)`
   - If married with data sharing: Unused spouse allowances transferred

2. **EstateAssetAggregatorService**
   - `gatherUserAssets()` - Collects all asset types and values
   - `calculateUserLiabilities()` - Sums all debts
   - Returns breakdown: Gross estate, net estate (assets - liabilities), liquidity classification

3. **GiftingStrategyOptimizer**
   - `calculateOptimalGiftingStrategy($netEstate, $ihtLiability, $yearsUntilDeath, $user, $nrb, $rnrb)`
   - Evaluates: Annual exemption capacity, PET capacity, gifting windows
   - Calculates: Years to life expectancy, potential IHT savings from gifting
   - Output: Recommended annual gift amount to minimize estate tax

4. **PersonalizedTrustStrategyService**
   - `generatePersonalizedTrustStrategy($assets, $ihtLiability, $profile, $user)`
   - Recommends: Discretionary trusts, interest-in-possession trusts, relevant property trusts
   - Logic: Match trust type to user's wishes + IHT liability + family circumstances

5. **WillAnalysisService**
   - `detectTrustTriggeringWishes()` - Identifies will bequests that require trust structures
   - `analyzeCharitableBequests($user, $netEstate)` - Evaluates charitable giving impact
   - If charity bequests ≥ 10%: Reduced IHT rate (36% vs 40%), saving = estate × 4%

6. **ComprehensiveEstatePlanService**
   - Orchestrates all services into cohesive plan
   - Generates implementation timeline with priorities and costs

### C. Recommendations Generated

**7-Step IHT Mitigation Decision Tree** (in priority order):

**Step 1: Charitable Bequest Check (Rate Reduction)**
- **Trigger**: `IHT liability > 0` AND charitable giving < 10% of estate
- **Data Input**: Net estate, current charitable bequests
- **Calculation**: If increase bequests to 10% threshold: `potential_saving = (net_estate × 10%) × 0.04` (4% rate reduction)
- **Output**: "Increase charitable bequests by £{shortfall} to save £{potential_saving} via reduced rate"

**Step 2: Liquidity & Affordability Assessment**
- **Trigger**: `liquid_assets < IHT_liability`
- **Data Input**: Asset breakdown (cash, savings, investments vs property)
- **Calculation**: Liquidity ratio = liquid assets / IHT liability
- **Output** (if ratio < 0.5): "Liquidity shortfall of £{gap} identified. Consider life insurance or property downsizing"

**Step 3: Check Existing Life Cover**
- **Trigger**: Policies exist with `in_trust = true`
- **Data Input**: Life insurance sum_assured, status
- **Calculation**: Usable cover = cover_in_trust - liabilities
- **Output**: "You have £{usable_cover} in trust cover available" OR "Place {count} policies in trust"

**Step 4: Annual Gifting Strategy (First Resort)**
- **Trigger**: Remaining IHT liability after steps 1-3
- **Data Input**: Current age, years to life expectancy
- **Calculation**:
  - `annual_exemption = £3,000` (from TaxConfigService)
  - `gifting_capacity = annual_exemption × years_to_life_expectancy`
  - `potential_savings = min(gifting_capacity × 0.40, remaining_liability)`
- **Output**: "Use £{annual_exemption}/year exemption. Over {years} years could save £{potential_savings}"

**Step 5: Life Cover Strategy (Second Resort, only if age ≤ 50)**
- **Trigger**: Age ≤ 50 AND remaining liability after steps 1-4
- **Data Input**: Remaining liability, current age
- **Calculation**: `estimated_premium ≈ remaining_liability × 0.02`
- **Output**: "Whole-of-life cover for £{remaining_liability} at ~£{estimated_premium}/year"

**Step 6: PET Gifting Strategy (Third Resort)**
- **Trigger**: Remaining liability AND years to life expectancy ≥ 7
- **Data Input**: Current age, available 7-year cycles
- **Calculation**:
  - `seven_year_cycles = floor(years_to_life_expectancy / 7)`
  - `pet_capacity = seven_year_cycles × NRB`
  - `potential_savings = min(pet_capacity × 0.40, remaining_liability)`
- **Output**: "{cycles} seven-year gifting cycles could shelter £{pet_capacity}"

**Step 7: CLT into Trust (Last Resort Only)**
- **Trigger**: Remaining liability after steps 1-6
- **Data Input**: Remaining liability, NRB
- **Calculation**: `immediate_charge = max(0, remaining_liability - NRB) × 0.20`
- **Output**: "CLT into trust would incur immediate £{immediate_charge} charge (20% on excess)"

### D. Data → Recommendation Mapping

```
INPUT: Properties, Savings, Investments, Pensions, Debts, Age, Marital status,
       Will preferences, Charitable giving, Existing life cover (in_trust status)
       ↓
ANALYSIS:
  Gather all assets & liabilities
  Calculate gross estate, net estate
  Calculate IHT liability (using TaxConfigService)
  Classify asset liquidity
  Evaluate gifting windows
  ↓
RECOMMENDATIONS:
  Step 1-7 IHT mitigation strategies
  Trust structure recommendations
  Will trust trigger analysis
  ↓
SCENARIOS:
  Current estate position
  Optimized plan (all strategies applied)
  Gifting scenarios (various annual amounts)
  Property downsizing impact
  Trust creation impact
  ↓
VALUE TO USER:
  - Knows exact IHT liability
  - Clear prioritized action sequence (most efficient first)
  - Financial impact of each strategy
  - Can plan estate minimization systematically
  - Understands spouse data sharing benefits
```

### E. Tax Calculations

**Central to Estate Planning:**

- **IHT Nil Rate Band (NRB)**: £325,000 (from TaxConfigService)
- **Residence Nil Rate Band (RNRB)**: £175,000 (applicable if conditions met)
- **IHT Rate**: 40% above NRB, 36% if charitable giving ≥ 10%
- **Annual Exemption**: £3,000 (used in gifting strategy)
- **PET 7-year taper**: Calculated for gifts made within 7 years of death
- **CLT rate**: 20% immediate, potentially 40% if death within 7 years

**Key Service:** `TaxConfigService::getInheritanceTax()`
- `nil_rate_band`: £325,000
- `rnrb`: £175,000
- `rate`: 40% (or 36% with charity)
- `clt_rate`: 20%
- `annual_exemption`: £3,000

### F. Monte Carlo / Projections
- **Not used** in Estate (deterministic calculation)
- Uses life expectancy estimates (`EstateDefaults::DEFAULT_LIFE_EXPECTANCY = 85`) for gifting window calculations

---

## 3. INVESTMENT MODULE

### A. Data Collection Points
| Data Point | Source | Model/Field |
|------------|--------|-------------|
| **Holdings** | InvestmentHolding | Quantity, cost, current value, unrealized gain/loss |
| **Accounts** | InvestmentAccount | Account type (ISA, GIA, bonds), current value, platform fee |
| **Risk Profile** | RiskProfile | Risk tolerance score (1-10), risk capacity, loss tolerance |
| **Investment Goals** | InvestmentGoal | Target amount, target date, return expectations |
| **Asset Classes** | Holding metadata | Fund/ETF asset class, sector, geography |
| **Fees** | InvestmentAccount, Holding | OCF (ongoing charges figure), platform fees, transaction costs |
| **Performance** | Holding | YTD return, 3Y return, 5Y return (vs benchmark) |
| **Tax Wrappers** | InvestmentAccount | Account type (ISA/GIA), ISA subscription amounts, SIPP status |
| **Annual Income** | User Profile | For tax-efficient wrapper recommendations |

### B. Services & Calculation Logic

**InvestmentAgent** orchestrates via:

1. **PortfolioAnalyzer**
   - `calculateTotalValue()` - Sum all holdings × current price
   - `calculateReturns()` - YTD, 3Y, 5Y weighted returns
   - `calculateAssetAllocation()` - % by asset class (equities, fixed income, alternatives)
   - `calculateDiversificationScore()` - 0-100 score based on concentration
   - `calculatePortfolioRisk()` - Volatility, downside risk vs risk profile

2. **FeeAnalyzer**
   - `calculateTotalFees()` - Platform + OCF + transaction costs
   - `compareToLowCostAlternatives()` - Identifies overly expensive holdings
   - `identifyHighFeeHoldings()` - Holdings > threshold fee level

3. **TaxEfficiencyCalculator**
   - `calculateUnrealizedGains()` - Current gain/loss vs cost base
   - `calculateTaxEfficiencyScore()` - 0-100 based on tax-wrapper usage, gains distribution
   - `identifyHarvestingOpportunities()` - Losses available for tax-loss harvesting (can offset gains)

4. **AssetAllocationOptimizer**
   - `getTargetAllocation()` - Based on risk profile (e.g., moderate = 60% equity / 40% fixed)
   - `calculateDeviation()` - Actual vs target allocation difference

5. **InvestmentActionDefinitionService** (Database-driven)
   - Evaluates triggers from `investment_action_definitions` table:
     - `risk_profile_not_set` → Recommend setting risk profile
     - `diversification_score_below {threshold}` → Diversify portfolio
     - `total_fee_percent_above {threshold}` → Reduce fees
     - `weighted_ocf_above {threshold}` → Switch to cheaper funds
     - `allocation_needs_rebalancing` → Rebalance to target
     - `has_harvesting_opportunities` → Tax-loss harvest
     - `has_gia_no_isa` → Open ISA for tax-free growth
     - `has_isa_remaining_and_gia` → Use remaining ISA allowance

6. **MonteCarloSimulator**
   - `simulate()` - Runs 10,000 iterations of portfolio growth
   - Input: Current value, monthly contribution, expected return, volatility, time horizon
   - Output: Projection range (10th percentile, median, 90th percentile)

### C. Recommendations Generated

**Triggered by InvestmentActionDefinition:**

1. **Risk Profile**
   - **Trigger**: No risk profile set
   - **Data Input**: None (profile missing)
   - **Output**: "Complete your risk profile to get personalized asset allocation"

2. **Diversification**
   - **Trigger**: `diversification_score < {threshold}` (e.g., 40)
   - **Data Input**: Holdings by asset class, concentration in single holdings
   - **Calculation**: Score drops if >30% in one holding/sector
   - **Output**: "Your portfolio is concentrated in {sector}. Consider adding {suggested_class}"

3. **Fees**
   - **Trigger 1**: `total_fee_percent > {threshold}` (e.g., 0.8%)
   - **Trigger 2**: `weighted_ocf > {threshold}` (e.g., 0.6%)
   - **Data Input**: Account fees, fund OCFs
   - **Calculation**: `total_fees = (platform_fee% + weighted_ocf%) × AUM`
   - **Output**: "Your fees of {fee_amount}/year exceed best practice. Switch to {low_cost_alternative}"
   - **Action**: Shows fee savings opportunity (e.g., "Save £{amount}/year by switching")

4. **Rebalancing**
   - **Trigger**: `allocation_deviation > {threshold}` (e.g., actual 65/35 vs target 60/40)
   - **Data Input**: Current allocation vs target from risk profile
   - **Calculation**: Drift = abs(actual - target)
   - **Output**: "Rebalance to {target}. Sell {amount} of {overweight}, buy {amount} of {underweight}"

5. **Tax-Loss Harvesting**
   - **Trigger**: `has_harvesting_opportunities` AND `unrealized_losses > {threshold}`
   - **Data Input**: Unrealized losses by holding
   - **Calculation**: Can offset gains in portfolio or carry forward
   - **Output**: "You have £{loss_amount} in tax losses available. Use to offset gains"

6. **Tax Wrappers (ISA)**
   - **Trigger 1**: `has_gia_no_isa` (Has General Investment Account but no ISA)
   - **Data Input**: GIA value, no ISA
   - **Output**: "You're paying tax on GIA gains. Open an ISA (£20k/year tax-free)"

   - **Trigger 2**: `has_isa_remaining_and_gia` (ISA allowance unused, but contributing to GIA)
   - **Data Input**: ISA allowance remaining, GIA contributions
   - **Calculation**: `remaining = £20,000 - year_to_date_subscriptions`
   - **Output**: "You have £{remaining} ISA allowance left. Maximize it before year-end"

7. **Bonds for High-Net-Worth**
   - **Trigger**: `gia_value_above {threshold}` (e.g., £500k) AND no bonds
   - **Data Input**: GIA value, bond holdings
   - **Output**: "Consider onshore/offshore bonds for inheritance tax efficiency"

### D. Data → Recommendation Mapping

```
INPUT: Holdings, Account types, Risk profile, Fees, Asset allocation, Unrealized gains,
       Tax wrapper usage, Income (for ISA context)
       ↓
ANALYSIS:
  Calculate portfolio value & returns
  Analyze diversification across assets/sectors
  Calculate total fees (platform + OCF)
  Compare to risk profile targets
  Identify tax-efficient opportunities
  Calculate unrealized gains/losses
  ↓
RECOMMENDATIONS:
  Risk profile → Asset allocation guidance
  Diversification score → Concentration recommendations
  Fees → Cost reduction opportunities
  Allocation drift → Rebalancing actions
  Tax losses → Harvesting opportunities
  Tax wrappers → ISA/GIA optimization
  ↓
SCENARIOS (Monte Carlo):
  5, 10, 20, 30-year projections
  Conservative/balanced/aggressive returns
  Impact of increased contributions
  ↓
VALUE TO USER:
  - Knows if portfolio matches risk tolerance
  - Identifies fee drag & cost-cutting opportunities
  - Receives rebalancing guidance
  - Understands tax-efficient positioning
  - Projects future portfolio value under various scenarios
  - Maximizes tax-wrapper benefits
```

### E. Tax Calculations

**TaxConfigService usage:**
- `getISAAllowances()` → Annual limit (£20,000)
- `getPensionAllowances()` → Annual Allowance (£60,000)

**TaxEfficiencyCalculator focus:**
- **Capital Gains Tax**: Identifies realized gains for 20% CGT (or exempt if in ISA)
- **Income Tax**: Dividen tax (dividend allowance, higher rates), interest tax
- **Tax wrappers**: ISA (tax-free), SIPP (tax relief on contributions), bonds (deferral)

### F. Monte Carlo / Projections

**InvestmentProjectionService:**
- Projects portfolio value using `MonteCarloSimulator`
- Inputs: Current value, contribution rate, expected return (by risk level), volatility
- Output: Confidence levels (10%, 50%, 90%) at various time horizons
- Used for: "What if I increase contributions?" "Will I reach my goal?"

---

## 4. TAX OPTIMISATION MODULE

### A. Data Collection Points
| Data Point | Source |
|------------|--------|
| **Income (all sources)** | User Profile + Income models |
| **Pension contributions** | DCPension, DBPension records |
| **Investment account types** | InvestmentAccount (ISA, GIA, bonds) |
| **Chargeable gains** | Realized gains from selling holdings |
| **Dividend income** | Dividend income field, investment statements |
| **Personal savings allowance** | Interest income (varies by tax band) |
| **Marital status** | User Profile (spouse data sharing) |
| **Age** | User Profile (for age-related allowances) |

### B. Services & Calculation Logic

**Key Services:**

1. **UKTaxCalculator**
   - Main tax computation engine
   - Calculates: Income tax, CGT, dividend tax, inheritance tax (if applicable)
   - Uses TaxConfigService for current rates/bands

2. **TaxConfigService**
   - Centralised tax configuration lookup
   - Returns: Personal allowance, basic/higher rates, CGT allowances, ISA/pension limits

3. **TaxBandTracker**
   - Tracks usage of personal allowance + basic rate band
   - Used when allocating income across multiple sources

### C. Recommendations Generated

**Tax Optimisation:**

1. **Pension Contribution Optimization**
   - **Data Input**: Income, existing contributions, Annual Allowance (£60k)
   - **Calculation**: `remaining_allowance = £60,000 - contributions_this_year`
   - **Output**: "You have £{remaining} available to contribute tax-free this year"

2. **ISA Allowance Usage**
   - **Data Input**: Income, ISA subscriptions YTD
   - **Calculation**: `remaining = £20,000 - subscriptions_ytd`
   - **Output**: "Maximize your remaining £{remaining} ISA allowance before tax year-end"

3. **Dividend Allowance**
   - **Data Input**: Dividend income received
   - **Calculation**: `excess_dividend = dividend_income - £500`
   - **Output**: "First £500 dividend tax-free, excess taxed at 8.75%/39.35%"

4. **CGT Allowance**
   - **Data Input**: Realized capital gains (if selling holdings)
   - **Calculation**: `excess_cgt = gains - annual_exemption` (£3,000)
   - **Output**: "Use CGT allowance: First £3k tax-free, gains above at 20%"

### D. Data → Recommendation Mapping

```
INPUT: Income sources, Pension contributions, Investment types, Gains/dividends,
       Age, Marital status
       ↓
ANALYSIS:
  Calculate personal allowance usage
  Calculate tax band utilization
  Identify available tax-free allowances
  ↓
RECOMMENDATIONS:
  Pension contribution room
  ISA allowance remaining
  CGT harvesting opportunities
  Spouse allowance transfer (if married)
  ↓
VALUE TO USER:
  - Knows tax position for the year
  - Understands remaining contribution room
  - Identifies tax-free opportunities
  - Can plan year-end tax strategies
```

### E. Tax Calculations
- **Primary service**: `UKTaxCalculator` (35K LOC of tax logic)
- **Rates updated annually** via TaxConfigService (2025/26 active year)

---

## 5. RETIREMENT MODULE

### A. Data Collection Points
| Data Point | Source | Model/Field |
|------------|--------|-------------|
| **Target Retirement Age** | RetirementProfile | `target_retirement_age` |
| **Current Age** | User Profile | `user.date_of_birth` (calculated) |
| **Target Income** | RetirementProfile | `target_retirement_income` |
| **DC Pension Value** | DCPension | `current_fund_value`, contributions, returns |
| **DB Pension** | DBPension | `annual_income_at_retirement`, accrual rate |
| **State Pension** | StatePension | `state_pension_age`, estimated `annual_income` |
| **Pension Contributions** | DCPension | Employee + employer contributions |
| **Investment Returns** | DCPension assumptions | Expected growth rate (varies by risk profile) |
| **Life Expectancy** | System default | Age 85 (used for projection horizon) |

### B. Services & Calculation Logic

**RetirementAgent** orchestrates via:

1. **PensionProjector**
   - `projectTotalRetirementIncome()` - Estimates annual retirement income from all sources
   - Calculation:
     - `dc_annual_income = dc_fund_value × withdrawal_rate` (e.g., 4% rule)
     - `db_annual_income = accrued_pension_amount`
     - `state_pension_income = state_pension_value` (if reaching SPA)
     - `total = dc_income + db_income + state_pension_income`

2. **PensionPortfolioAnalyzer**
   - Analyzes pension fund composition
   - Calculates: Asset allocation, fees, performance vs benchmarks
   - (Mirrors InvestmentAgent for pension holdings)

3. **AnnualAllowanceChecker**
   - `checkAnnualAllowance()` - Evaluates AA compliance
   - Calculation: `excess = total_contributions - £60,000`
   - If excess: Tapered allowance applies (if income > £260k)
   - Output: AA usage, excess (if any), tax charge (if exceeded)

4. **ContributionOptimizer**
   - `optimizeContributions()` - Recommends contribution levels
   - Uses: Target income, gap analysis, remaining AA capacity
   - Output: "Increase pension contribution to £{amount}/year to close retirement gap"

5. **DecumulationPlanner**
   - `buildDecumulationStrategy()` - Withdrawal sequence in retirement
   - Considers: Tax efficiency, longevity risk, estate planning
   - Output: Safe withdrawal rate, sequencing recommendations

6. **RetirementActionDefinitionService** (Database-driven)
   - Evaluates triggers from `retirement_action_definitions` table:
     - `income_gap_exists` → Increase pension contributions
     - `insufficient_dc_value` → Accelerate DC contributions
     - `state_pension_not_optimized` → Defer state pension (increases by 5.8%/year)
     - `income_protection_gap` → Ensure income protection to retirement
     - `annual_allowance_exceeded` → Monitor/reduce contributions
     - `decumulation_strategy_missing` → Build retirement income plan

### C. Recommendations Generated

**Triggered by RetirementActionDefinition:**

1. **Income Gap**
   - **Trigger**: `projected_income < target_income`
   - **Data Input**: Target retirement age, target income, projected income, years to retirement
   - **Calculation**:
     - `gap = max(0, target_income - projected_income)`
     - `additional_contribution_needed = gap ÷ years_to_retirement ÷ assumption_factor`
   - **Output**: "Increase pension contributions by £{amount}/year to close £{gap}/year gap"

2. **DC Pension Value Too Low**
   - **Trigger**: `dc_fund_value < retirement_income_target ÷ withdrawal_rate`
   - **Data Input**: Current DC value, target income, expected return
   - **Calculation**: `shortfall = target_fund_value - current_dc_value`
   - **Output**: "Build DC pension to £{target}. Contribute additional £{amount}/year"

3. **State Pension Deferral**
   - **Trigger**: User retiring before state pension age (SPA)
   - **Data Input**: State pension age, retirement age, state pension annual amount
   - **Calculation**: `income_increase = state_pension × 5.8% × years_deferred`
   - **Output**: "Defer state pension to {recommended_age}. Gain £{income_increase}/year"

4. **Annual Allowance Exceeded**
   - **Trigger**: `total_contributions > £60,000` (or tapered if income > £260k)
   - **Data Input**: Contributions (employee + employer), income
   - **Calculation**: `excess = contributions - allowance`; `tax_charge = excess × marginal_rate`
   - **Output**: "Annual Allowance breached by £{excess}. Tax charge: £{charge}"

5. **Decumulation Strategy**
   - **Trigger**: User reaches retirement age
   - **Data Input**: Pension fund value, target withdrawal rate, expected returns, longevity
   - **Calculation**: Safe withdrawal rate (e.g., 4% rule) considering inflation, sequencing
   - **Output**: "Safe annual withdrawal: £{amount}. Sequence: Draw tax-free lump sum first, then income-drawdown"

### D. Data → Recommendation Mapping

```
INPUT: Target retirement age, Current age, Target income, DC pension value & contributions,
       DB pension details, State pension estimates, Expected returns
       ↓
ANALYSIS:
  Project DC fund growth to retirement
  Estimate DB pension value at retirement
  Project state pension income (if reaching SPA)
  Calculate total projected retirement income
  Calculate income gap (target - projected)
  ↓
RECOMMENDATIONS:
  Contribution increase (if gap exists)
  State pension deferral (if retiring before SPA)
  Annual Allowance monitoring
  Decumulation strategy
  ↓
PROJECTIONS (Monte Carlo):
  10, 20, 30-year pension growth
  Scenarios: Conservative/balanced/aggressive returns
  Retirement income sufficiency
  ↓
SCENARIOS:
  Increase contributions by 10% / 20%
  Defer retirement by 1 / 2 years
  Increase state pension deferral
  ↓
VALUE TO USER:
  - Knows if on track for retirement target
  - Receives specific contribution increase guidance
  - Understands state pension optimization
  - Projected income at retirement age
  - Can model "what-if" scenarios
  - Avoids Annual Allowance penalties
```

### E. Tax Calculations

**TaxConfigService usage:**
- `getPensionAllowances()` → Annual Allowance (£60k), Lifetime Allowance (abolished 2023)
- Personal allowance varies with income (higher earners lose allowance)

**Tax implications:**
- Contributions (employee): Get basic rate tax relief automatically
- Contributions (self-employed): Claim relief via tax return
- Drawdown: Taxed as income (first 25% tax-free lump sum, rest as income)
- If exceed AA: Charge at marginal rate (20%-60%)

### F. Monte Carlo / Projections

**Used extensively:**
- Projects DC fund growth over 10-30 years
- Accounts for: Market volatility, contribution changes, inflation
- Outputs: Confidence levels for retirement income sufficiency
- Used in: "Can I retire at 60?" "What if I contribute more?"

---

## 6. RETIREMENT DECUMULATION MODULE

### A. Data Collection Points
| Data Point | Source |
|------------|--------|
| **Pension fund value at retirement** | Retirement projections |
| **Target withdrawal amount** | RetirementProfile or user input |
| **Inflation assumption** | System default (typically 2.5%) |
| **Longevity estimate** | User age + life expectancy table (85) |
| **Drawdown vs annuity preference** | User choice |
| **Spouse income needs** | Family profile |
| **Estate planning goals** | Estate profile |

### B. Services & Calculation Logic

**DecumulationPlanner** service:
- `buildDecumulationStrategy()` - Generates withdrawal sequence
- Considers: Tax efficiency, capital preservation, longevity risk
- Output: Safe withdrawal rate, sequencing (which accounts to draw from first)

**Decumulation strategies:**
1. **4% Rule**: Withdraw 4% of starting balance, adjusted for inflation
2. **Flexible Drawdown**: Adjust withdrawals based on market conditions
3. **Annuity**: Convert fund to guaranteed lifetime income
4. **Income Drawdown**: Draw from investment returns + sustainable capital reduction

### C. Recommendations Generated

1. **Withdrawal Rate**
   - **Data Input**: Pension fund value, target annual withdrawal, life expectancy
   - **Calculation**: `sustainable_withdrawal = fund_value × withdrawal_rate` (e.g., 4%)
   - **Output**: "You can safely withdraw £{amount}/year from £{fund_value} fund"

2. **Drawdown Sequencing**
   - **Data Input**: Multiple account types (tax-free lump sum allowance, income-drawdown, annuity)
   - **Output**: "Sequence: Take tax-free lump sum (£{amount}), then draw from income, then capital"

3. **Longevity Insurance**
   - **Data Input**: User age, life expectancy, fund value
   - **Calculation**: Probability of fund depletion before longevity age
   - **Output**: "At 4% withdrawal, {%} probability of outliving funds. Consider annuity for guarantee"

### D. Data → Recommendation Mapping

```
INPUT: Pension fund value, Target withdrawal, Life expectancy, Tax position,
       Spouse income needs, Estate goals
       ↓
ANALYSIS:
  Calculate safe withdrawal rate
  Model fund depletion timeline
  Evaluate tax-efficient sequencing
  ↓
RECOMMENDATIONS:
  Annual withdrawal amount (with inflation adjustment)
  Sequencing (which accounts to draw from)
  Longevity risk mitigation (annuity vs drawdown)
  ↓
VALUE TO USER:
  - Knows sustainable withdrawal amount
  - Understands longevity risk
  - Has clear drawdown plan
  - Can optimize tax position in retirement
```

---

## 7. RISK PROFILE MODULE

### A. Data Collection Points
| Data Point | Source |
|------------|--------|
| **Risk Tolerance** | RiskProfile questionnaire (1-10 scale) |
| **Risk Capacity** | Income, assets, time horizon |
| **Loss Tolerance** | Investor reaction to portfolio swings |
| **Time Horizon** | Years to financial goal |
| **Life Stage** | Age (young/accumulation, mid-career, pre-retirement, retirement) |
| **Financial Circumstances** | Income stability, job security, emergency fund |

### B. Services & Calculation Logic

**RiskProfileService** (implied from agents):
- Combines questionnaire responses + financial circumstances
- Produces: Risk score (1-10), investment risk category (conservative/moderate/aggressive)
- Used by: InvestmentAgent, RetirementAgent for asset allocation targeting

### C. Recommendations Generated

1. **Risk Profile Setting**
   - **Trigger**: No risk profile exists
   - **Output**: "Complete risk questionnaire to receive personalized asset allocation"

2. **Risk Mismatch**
   - **Data Input**: Current portfolio allocation vs target from risk profile
   - **Output**: "Your portfolio is more aggressive than your risk tolerance. Rebalance to {target}"

### D. Value

```
INPUT: Risk tolerance answers, Financial circumstances
       ↓
ANALYSIS: Calculate risk score + category
       ↓
OUTPUT: Target asset allocation (e.g., 60% equities / 40% bonds)
       ↓
VALUE: Portfolio alignment with tolerance, Prevents over/under-risk taking
```

---

## 8. GOALS & LIFE EVENTS MODULE

### A. Data Collection Points
| Data Point | Source | Model/Field |
|------------|--------|-------------|
| **Goal Name** | Goal model | `goal_name` |
| **Target Amount** | Goal | `target_amount` |
| **Target Date** | Goal | `target_date` |
| **Current Progress** | Linked Savings/Investment Account | Account balances linked to goal |
| **Priority** | Goal | `priority` (1-5) |
| **Module Assignment** | Goal | `assigned_module` (savings, investment, property, retirement) |
| **Contribution Streak** | Goal | `contribution_streak`, `longest_streak` |
| **Time Horizon** | Calculated | `target_date - today` |

### B. Services & Calculation Logic

**GoalsAgent** orchestrates via:

1. **GoalAssignmentService**
   - `assignGoalToModule()` - Decides which module best serves goal
   - Factors: Time horizon, amount, user circumstances

2. **GoalAffordabilityService**
   - `analyzeAllGoals()` - Calculates affordability of all goals
   - Output: Can afford, at risk, unaffordable, with rationale

3. **GoalProgressService**
   - `calculateProgress()` - % of target reached
   - `prioritizeGoals()` - Rank by deadline + urgency

4. **GoalRiskService**
   - `calculateGoalRiskLevel()` - Likelihood of achieving goal
   - Based on: Time horizon, required returns, contributions

### C. Recommendations Generated

1. **Create First Goal**
   - **Trigger**: No goals exist
   - **Output**: "Set your first financial goal to start tracking progress and receive planning recommendations"

2. **Goal On Track**
   - **Data Input**: Progress %, deadline, required monthly contribution
   - **Output**: "You're on track to reach {goal} by {date} with £{monthly}/month contribution"

3. **Goal At Risk**
   - **Data Input**: Current progress %, required contribution vs actual
   - **Output**: "You're {shortfall} behind on {goal}. Increase contribution to £{amount}/month"

4. **Goal Unaffordable**
   - **Data Input**: Target amount, time horizon, available cash flow
   - **Calculation**: `required_monthly = (target - current) ÷ months × growth_factor`
   - **Output**: "Goal requires £{required} monthly but you have £{available}. Extend timeline or reduce target"

5. **Top Goals**
   - **Data Input**: All goals sorted by priority + deadline
   - **Output**: "Focus on: 1) {goal1} (due {date1}), 2) {goal2} (due {date2})"

### D. Data → Recommendation Mapping

```
INPUT: Goal target, deadline, current progress, time horizon, contributions,
       Assigned module (savings/investment/property/retirement)
       ↓
ANALYSIS:
  Calculate affordability (required vs available contribution)
  Calculate progress % to date
  Calculate required monthly contribution to reach goal
  Rank goals by deadline
  ↓
RECOMMENDATIONS:
  Increase contribution (if at risk)
  Celebrate progress (if on track)
  Extend timeline or reduce target (if unaffordable)
  Focus on top-priority goals
  ↓
VALUE TO USER:
  - Knows which goals are achievable
  - Receives specific contribution targets
  - Can prioritize across multiple goals
  - Celebrates milestones & streaks
```

---

## 9. FAMILY, LETTER TO SPOUSE MODULE

### A. Data Collection Points
| Data Point | Source |
|------------|--------|
| **Spouse/Partner Info** | FamilyMember table, User spouse_id |
| **Children/Dependents** | FamilyMember table |
| **Guardianship Wishes** | Will preferences |
| **Financial Dependents** | Family profile, financial circumstances |
| **Inheritance Distribution** | Will bequests |
| **Trustee Appointments** | Will + Trust documents |
| **Letter Preferences** | User input (what to communicate) |

### B. Services & Calculation Logic

**Key Services:**
- **WillAnalysisService** - Analyzes will structure, identifies guardianship gaps
- **FamilyProfileService** - Aggregates family member financial data
- **DocumentStorageService** - Manages letter to spouse, will, trust documents

### C. Recommendations Generated

1. **Missing Guardianship Appointment**
   - **Trigger**: Has dependent children but no appointed guardians in will
   - **Output**: "Appoint guardians for your {count} dependent(s) in your will"

2. **Missing Will**
   - **Trigger**: Has dependents or significant estate but no will
   - **Output**: "Create or update your will to protect your family and ensure wishes are followed"

3. **Letter to Spouse**
   - **Data Input**: User's location preferences, digital asset details, financial accounts, contacts
   - **Output**: Letter template populated with user's key information
   - **Usage**: Spouse has guidance if primary earner dies

4. **POA Documents**
   - **Trigger**: Has dependents or significant assets but no Power of Attorney
   - **Output**: "Create a Lasting Power of Attorney for financial/health decisions"

### D. Data → Recommendation Mapping

```
INPUT: Dependents, Family structure, Will existence, Guardianship appointments,
       Digital assets, Financial account list
       ↓
ANALYSIS:
  Check guardianship coverage
  Verify will appointment
  Assess letter to spouse completeness
  ↓
RECOMMENDATIONS:
  Appoint guardians (if children exist)
  Update will (if changed circumstances)
  Write/update letter to spouse
  Create POA documents
  ↓
VALUE TO USER:
  - Family protected if something happens
  - Spouse has clear guidance & instructions
  - Peace of mind that arrangements are in place
  - Dependents' futures secured
```

---

## CROSS-MODULE: COORDINATION & CONFLICT RESOLUTION

### CoordinatingAgent Architecture

The **CoordinatingAgent** brings together all 7 module agents into a unified holistic plan:

```
┌─────────────────────────────────────────────────────────────┐
│            CoordinatingAgent                                 │
│  Orchestrates all module agents + resolves conflicts         │
└─────────────────────────────────────────────────────────────┘
        ↓                           ↓                      ↓
  ┌──────────────┐    ┌──────────────────┐    ┌──────────────┐
  │ Protection   │    │ Estate Planning  │    │ Investment   │
  │ Agent        │    │ Agent            │    │ Agent        │
  └──────────────┘    └──────────────────┘    └──────────────┘
        ↓                           ↓                      ↓
    [Analysis]             [Analysis]              [Analysis]
        ↓                           ↓                      ↓
  ┌──────────────┐    ┌──────────────────┐    ┌──────────────┐
  │ Savings      │    │ Retirement       │    │ Goals        │
  │ Agent        │    │ Agent            │    │ Agent        │
  └──────────────┘    └──────────────────┘    └──────────────┘
        ↓                           ↓                      ↓
    [Analysis]             [Analysis]              [Analysis]
        │                       │                      │
        └───────────────────────┴──────────────────────┘
                                ↓
                    ┌─────────────────────────┐
                    │ CashFlowCoordinator     │
                    │ - Available Surplus     │
                    │ - Demand Extraction     │
                    │ - Allocation Optimizer  │
                    └─────────────────────────┘
                                ↓
                    ┌─────────────────────────┐
                    │ ConflictResolver        │
                    │ - Identify conflicts    │
                    │ - Resolve via rules     │
                    └─────────────────────────┘
                                ↓
                    ┌─────────────────────────┐
                    │ PriorityRanker          │
                    │ - Rank all recs         │
                    │ - User context aware    │
                    │ - Output action plan    │
                    └─────────────────────────┘
                                ↓
                    ┌─────────────────────────┐
                    │ HolisticPlan            │
                    │ - Integrated strategy   │
                    │ - Implementation steps  │
                    │ - Timeline & costs      │
                    └─────────────────────────┘
```

### A. Available Surplus Calculation

```php
CashFlowCoordinator::calculateAvailableSurplus($userId)
  = Gross Income
    - Tax & National Insurance
    - Essential Expenses
    - Existing Commitments (mortgages, loan payments, current policy premiums)
    = Available for allocation to recommendations
```

### B. Conflict Resolution

**Common Conflicts Resolved:**

1. **Pension vs ISA Contributions**
   - **Conflict**: Should I contribute more to pension or max out ISA?
   - **Resolution**: Priority ranked by: Current AA room > remaining ISA room > tax relief efficiency
   - **Output**: "Prioritize: Contribute £{x} to pension (get tax relief), then £{y} to ISA (tax-free growth)"

2. **Debt Repayment vs Savings**
   - **Conflict**: Pay off credit card or build emergency fund?
   - **Resolution**: High-interest debt > Emergency fund (3-6 months) > Additional savings
   - **Output**: "Pay credit card (10% interest) before investing"

3. **Life Insurance vs Other Protections**
   - **Conflict**: Buy life insurance or income protection first?
   - **Resolution**: Life insurance (if dependents) > Income protection (if income-dependent)
   - **Output**: "Prioritize: Life cover for {gap}, then income protection"

4. **Estate Planning vs Tax Optimization**
   - **Conflict**: Estate tax minimization vs liquidity for retirement?
   - **Resolution**: Liquidity first (ensure retirement income), then minimize IHT
   - **Output**: "Step 2 liquidity assessment before Step 4 gifting strategy"

### C. Ranking Algorithm

**PriorityRanker** scores each recommendation on:

1. **Urgency** (deadline, time-sensitive)
2. **Impact** (financial benefit if actioned)
3. **User Context** (age, life stage, family situation)
4. **Module Interaction** (conflicts/synergies with other recs)
5. **Effort** (difficulty/cost to implement)

Output: Ranked list with clear action sequence

### D. Example Holistic Plan Output

```
USER SCENARIO:
  - Age 40, employed, married with 2 kids
  - £50k/year income, £30k/year expenditure
  - £100k pension, £20k emergency fund
  - £0 life insurance, £100k mortgage, £5k credit card debt
  - Estate: £350k (property + savings)

COORDINATED ANALYSIS:
  ✓ Protection: £300k life insurance gap (dependents + mortgage)
  ✓ Estate: £24k IHT liability (single allowance only)
  ✓ Savings: Emergency fund adequate, but credit card at high interest
  ✓ Retirement: On track, but could optimize contributions
  ✓ Investment: No investment account yet, could use ISA
  ✓ Goals: School fees goal (£15k in 5 years) unaffordable with current surplus

HOLISTIC RECOMMENDATIONS (Ranked by Priority):
  1. ⭐ URGENT: Life Insurance
     Data: £300k gap (income × years to retirement - existing cover)
     Action: Buy term life cover £300k (cost ~£30/month)
     Value: Protects dependents from financial hardship if you die

  2. ⭐ URGENT: Eliminate Credit Card Debt
     Data: £5k at 18% interest = £900/year in wasted interest
     Action: Clear £5k within 6 months (£850/month from surplus)
     Value: Saves £900/year, improves cash flow

  3. HIGH: Place Life Policies in Trust
     Data: Any life insurance in estate (not in trust) = IHT exposed
     Action: Contact insurer, execute trust documentation
     Value: £{cover_amount} × 40% IHT rate = savings

  4. HIGH: Maximize Pension Contributions
     Data: £{available_aa_room}/year remaining Annual Allowance
     Action: Contribute £{amount}/year additional (get tax relief)
     Value: Tax savings + retirement income improvement

  5. MEDIUM: Open ISA & Invest for School Fees Goal
     Data: £20k ISA allowance remaining, goal in 5 years = £3k/year needed
     Action: Invest £250/month in stocks & shares ISA
     Value: Tax-free growth to reach £15k goal

  6. MEDIUM: Annual Gifting Strategy (Estate Planning)
     Data: £3k/year annual exemption, £24k IHT liability
     Action: Gift £3k/year to spouse/children (7-year strategy)
     Value: Over 7 years, saves £0.84k IHT (3k × 7 × 40%)

  7. LOW: Estate Planning Documents
     Data: Dependents exist but no will/POA
     Action: Create/update will, appoint guardians, create letter to spouse
     Value: Family protected if something happens

CASH FLOW ALLOCATION (Available £20k/year):
  - Life insurance premium: -£360 (annual)
  - Credit card repayment: -£5,100 (6-month plan)
  - Pension additional: -£2,000 (remaining AA room)
  - ISA investment: -£3,000 (school fees goal)
  - Remaining: £9,540 (buffer / additional payments / other goals)

IMPLEMENTATION TIMELINE:
  Month 1-3: Get life insurance quotes, apply for cover
  Month 1-6: Clear credit card debt
  Month 4+:  Maximize pension contribution
  Month 6+:  Begin ISA school fees investment
  Month 12:  Annual gifting (£3k to spouse)
  Ongoing:   Annual gifting strategy (every year, 7-year plan)
```

---

## ARCHITECTURE: DATA COLLECTION → VALUE DELIVERY

### Complete Flow Diagram

```
┌──────────────────────────────────────────────────────────────────────────┐
│                           USER DATA COLLECTION                           │
│  (Vue.js Forms → API → Database Models)                                  │
│  Profile, Properties, Investments, Pensions, Policies, Goals, etc.      │
└──────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌──────────────────────────────────────────────────────────────────────────┐
│                        MODULE AGENTS ANALYSIS                            │
│  (ProtectionAgent, EstateAgent, InvestmentAgent, RetirementAgent, etc.) │
│  Each calls 3-10 domain services to analyze user data                   │
└──────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌──────────────────────────────────────────────────────────────────────────┐
│                      DOMAIN SERVICES PROCESSING                          │
│  (174 services performing specific calculations)                         │
│  - Coverage gap analysis (Protection)                                    │
│  - IHT calculations (Estate)                                             │
│  - Portfolio analysis & fee calculations (Investment)                    │
│  - Pension projections (Retirement)                                      │
│  - Emergency fund adequacy (Savings)                                     │
│  - Goal affordability (Goals)                                            │
│  - Tax calculations (Tax)                                                │
└──────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌──────────────────────────────────────────────────────────────────────────┐
│                    ACTION DEFINITION EVALUATION                          │
│  (Database-driven trigger evaluation)                                    │
│  - InvestmentActionDefinition: Fee triggers, rebalancing, tax-wrappers  │
│  - RetirementActionDefinition: Contribution gaps, AA breaches            │
│  - Each condition mapped to recommendation template                      │
└──────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌──────────────────────────────────────────────────────────────────────────┐
│                      COORDINATING AGENT SYNTHESIS                        │
│  - Extract recommendations from all modules                              │
│  - Identify conflicts (pension vs ISA, debt vs savings, etc.)           │
│  - Resolve conflicts via ConflictResolver rules                         │
│  - Calculate available surplus via CashFlowCoordinator                  │
│  - Allocate cash flow to demands                                         │
└──────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌──────────────────────────────────────────────────────────────────────────┐
│                       PRIORITY RANKING                                    │
│  - Score each recommendation by: urgency, impact, user context, effort   │
│  - Rank from 1 (do first) to N (do last)                                │
│  - Create action plan with implementation timeline                       │
└──────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌──────────────────────────────────────────────────────────────────────────┐
│                      API DELIVERY TO FRONTEND                            │
│  (RecommendationsController, HolisticPlanningController)                │
│  - Ranked recommendations in priority order                              │
│  - Action plan with timeline                                             │
│  - Cash flow allocation breakdown                                        │
│  - Implementation costs & benefits                                       │
└──────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌──────────────────────────────────────────────────────────────────────────┐
│                    FRONTEND VISUALIZATION                                │
│  (Vue.js Components)                                                     │
│  - Dashboard: Top recommendations                                        │
│  - Action cards with "Take Action" CTAs                                 │
│  - Module pages with full recommendations                               │
│  - Holistic plan view with timeline                                     │
│  - What-if scenario explorer                                            │
└──────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌──────────────────────────────────────────────────────────────────────────┐
│                        USER TAKES ACTION                                  │
│  - Click "Get Quote" → redirected to provider                           │
│  - "Schedule Review" → books advisor session                            │
│  - "View Plan" → detailed implementation guide                          │
│  - "Run Scenario" → adjusts assumption, reruns analysis                 │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## KEY INSIGHTS: DATA → VALUE TRANSFORMATION

### 1. **Multi-Layer Processing**
Each piece of user data flows through:
- **Agent orchestration** (module-level logic)
- **Service processing** (calculation, analysis)
- **Action definition evaluation** (database-driven triggers)
- **Cross-module coordination** (conflict resolution, prioritization)
- **Frontend delivery** (actionable recommendations)

### 2. **Service Reuse Across Modules**
Many services are shared:
- `TaxConfigService`: Used by all modules for tax rates/limits
- `MonteCarloSimulator`: Shared by Investment + Retirement for projections
- `PortfolioAnalyzer`: Used by both Investment + Retirement for pension portfolios

### 3. **Database-Driven Recommendations**
Both Investment and Retirement modules use **ActionDefinition tables**:
- Enables non-code configuration of triggers
- Each trigger condition → recommendation template
- Easily updatable without code changes

### 4. **Cash Flow as Binding Constraint**
CoordinatingAgent's `CashFlowCoordinator` ensures:
- All recommendations respect available surplus
- Cash flow allocated optimally across competing demands
- Shortfalls identified (tells user "can't afford all recommendations")

### 5. **Conflict Resolution Hierarchy**
When recommendations compete for same resources:
1. **Urgency-based**: Time-sensitive recs (AA breaches, deadlines) prioritized
2. **Impact-based**: High-benefit recommendations (IHT savings, death protection) prioritized
3. **Dependency-based**: Prerequisite recs (life insurance before estate planning) sequenced
4. **Affordability-based**: Within available surplus, highest ROI prioritized

---

## SUMMARY TABLE: 9 AREAS DATA → VALUE MAPPING

| Area | Key Data | Primary Calculation | Recommendation Type | Value to User |
|------|----------|-------------------|-------------------|----------------|
| **Protection** | Income, debts, dependents, existing policies | Coverage gap = needs - current | Life/Critical/Income gaps | Know exact protection shortfall, receive prioritized actions |
| **Estate** | Assets, liabilities, age, will preferences | IHT = (gross - NRB) × 40% | 7-step IHT mitigation sequence | Minimize estate tax, protect family, optimize gifting |
| **Investment** | Holdings, risk profile, fees, tax wrappers | Diversification score, fee drag, allocation drift | Risk/diversification/fees/rebalancing/tax-wrapper optimization | Align with risk tolerance, reduce fees, optimize tax efficiency |
| **Tax** | Income sources, contributions, gains, age | Personal allowance usage, tax bands, exemptions | Contribution room, tax-free opportunities | Maximize use of tax allowances and relief |
| **Retirement** | DC/DB pensions, contributions, age, target income | Income gap = target - projected, AA usage | Contribution increases, state pension deferral, decumulation strategy | Retire on target income, avoid AA penalties, understand drawdown sequence |
| **Decumulation** | Pension fund value, life expectancy, withdrawal needs | Safe withdrawal rate (4% rule), sequencing | Annual withdrawal amount, drawdown order, longevity hedging | Sustainable retirement income, avoid fund depletion, tax-efficient sequencing |
| **Risk Profile** | Tolerance questionnaire, financial circumstances, time horizon | Risk score, target asset allocation | Asset allocation targeting, rebalancing to match | Portfolio aligned with tolerance, prevents over/under-risk |
| **Goals** | Target amount, deadline, contributions, time horizon | Affordability = required monthly vs available, progress % | Contribution increases, timeline extensions, goal prioritization | Know if goals achievable, receive specific contribution targets, celebrate progress |
| **Family/Letter** | Dependents, will status, guardianship, digital assets | Guardianship coverage, document completeness | Will creation/update, guardianship appointment, letter to spouse, POA | Family protected, spouse has clear guidance, peace of mind |

---

## APPENDIX: KEY FILES & ENTRY POINTS

### Agents (Module Orchestrators)
- `/app/Agents/ProtectionAgent.php`
- `/app/Agents/EstateAgent.php`
- `/app/Agents/InvestmentAgent.php`
- `/app/Agents/RetirementAgent.php`
- `/app/Agents/SavingsAgent.php`
- `/app/Agents/GoalsAgent.php`
- `/app/Agents/CoordinatingAgent.php`
- `/app/Agents/BaseAgent.php`

### Key Services (Calculation Engines)
- `/app/Services/TaxConfigService.php` (centralised tax lookups)
- `/app/Services/UKTaxCalculator.php` (primary tax engine)
- `/app/Services/Investment/InvestmentActionDefinitionService.php`
- `/app/Services/Retirement/RetirementActionDefinitionService.php`
- `/app/Services/Estate/IHTCalculationService.php`
- `/app/Services/Protection/RecommendationEngine.php`
- `/app/Services/Coordination/RecommendationsAggregatorService.php`
- `/app/Services/Coordination/CoordinatingAgent.php`

### Controllers (API Entry Points)
- `/app/Http/Controllers/Api/RecommendationsController.php`
- `/app/Http/Controllers/Api/HolisticPlanningController.php`
- `/app/Http/Controllers/Api/Plans/PlanController.php`

### Frontend Components (Vue.js)
- `/resources/js/components/*/` (378 components across 27 modules)
- `/resources/js/store/modules/` (21 Vuex stores)
- `/resources/js/services/` (33 API wrapper services)

---

**Document Generated:** March 2026
**Scope:** All Fynla modules (Protection, Estate, Investment, Tax, Retirement, Decumulation, Risk, Goals, Family)
**Data Points Mapped:** 50+
**Services Documented:** 20+
**Agents Covered:** 7 module + 1 coordinating
**Recommendations Evaluated:** 50+
