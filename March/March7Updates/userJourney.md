# Fynla User Journey: Data Collection, Value Mapping & Gap Analysis

**Date:** 7 March 2026 | **Version:** 1.0 | **Codebase:** v0.8.3

This document maps what data Fynla collects from users, why it's collected, and how it transforms into actions, strategies, and recommendations across all 9 planning areas. It synthesises three specialist perspectives: UX Journey, Financial Planning, and Devil's Advocate.

---

## Table of Contents

1. [How Users Enter Data](#1-how-users-enter-data)
2. [Module-by-Module Analysis](#2-module-by-module-analysis)
3. [Cross-Module Dependencies](#3-cross-module-dependencies)
4. [Architecture: How Data Becomes Value](#4-architecture-how-data-becomes-value)
5. [Gap Analysis & Opportunities](#5-gap-analysis--opportunities)
6. [Priority Recommendations](#6-priority-recommendations)

---

## 1. How Users Enter Data

### Onboarding Flow (11 Steps)

```
Focus Area Selection (Estate)
  |
Personal Info (REQUIRED) ---- DOB, Gender, Marital Status
  |
Family Info (skippable) ----- Spouse, Children, Dependents
  |
Domicile Info (skippable) --- UK status, Arrival date
  |
Income (REQUIRED) ----------- Employment status, Salary, Other income
  |
Expenditure (skippable) ----- Monthly costs by category
  |
Assets (skippable) ---------- Properties, Savings, Investments, Pensions, Business, Chattels
  |
Liabilities (skippable) ----- Mortgages, Loans, Credit cards
  |
Protection (skippable) ------ Life, CI, IP, Disability policies
  |
Will Info (skippable) ------- Will exists, Executors, Beneficiaries
  |
Trust Info (skippable) ------ Trust type, Trustees, Beneficiaries
  |
Completion
```

**Only 2 steps are truly required:** Personal Info and Income. Everything else is skippable with contextual warnings explaining the impact of skipping.

### Post-Onboarding Enhancement

After onboarding, each module dashboard allows users to add/refine data progressively. The 6 preview personas (young_family, peak_earners, widow, entrepreneur, young_saver, retired_couple) demonstrate value before commitment.

---

## 2. Module-by-Module Analysis

### 2.1 Protection

#### What We Collect

| Data Point | Required | Source |
|-----------|----------|--------|
| Policy type (Life, CI, IP, Disability) | Yes | Protection form |
| Cover amount (sum assured) | Yes | Protection form |
| Monthly premium | No | Protection form |
| Policy term / maturity date | No | Protection form |
| Ownership type | Yes | Protection form |
| Provider | No | Protection form |
| Annual income (all sources) | Yes | User profile |
| Monthly expenditure | No | Protection profile |
| Number of dependents | No | Family info |
| Mortgage balance | No | Liabilities |
| Other debts | No | Liabilities |
| Retirement age | No | Protection profile |
| Date of birth | Yes | Personal info |

#### How It Maps to Recommendations

```
Data Collected                    Service/Agent                      Recommendation Output
--------------------------        ----------------------------       -----------------------------------
Income + Retirement age      -->  CoverageGapAnalyzer           -->  "Buy X life insurance to cover gap"
Dependents + Debts           -->  RecommendationEngine          -->  "Add decreasing term cover for debts"
No CI cover + Income         -->  RecommendationEngine          -->  "Consider CI cover of 3x income"
Expenditure                  -->  RecommendationEngine          -->  "Add IP cover of X/month"
Policies not in trust        -->  RecommendationEngine          -->  "Place policies in trust for IHT"
All above combined           -->  ScenarioBuilder               -->  Death/CI/Disability what-if scenarios
```

**Key services:** ProtectionAgent, CoverageGapAnalyzer, AdequacyScorer, RecommendationEngine, ScenarioBuilder

**Value to user:**
- Knows exact protection shortfall in pounds
- Receives prioritised action list (which cover to buy first)
- Understands financial impact of death/illness on family
- Can plan policy purchases strategically

#### Devil's Advocate Issues

- **Dead data:** `occupation`, `health_status`, `dependents_ages` collected but never used in calculations
- **Weak personalisation:** Recommendations are template-driven (gap size only); ignores family situation, employer death benefits, partner income
- **Missing cross-module links:** Doesn't coordinate with Savings (emergency fund reduces need), Estate (trust placement), or Retirement (IP phases out at pension age)

---

### 2.2 Estate Planning

#### What We Collect

| Data Point | Required | Source |
|-----------|----------|--------|
| All assets (properties, savings, investments, pensions, business, chattels) | Yes (values) | Multiple forms |
| All liabilities (mortgages, loans, credit cards) | Yes (balances) | Liabilities form |
| Marital status + Spouse data sharing | Yes | Personal info |
| Will details (executors, beneficiaries, provisions) | No | Will form |
| Trust details (type, trustees, beneficiaries, value) | No | Trust form |
| Previous gifts (date, value, recipient) | No | Gift form |
| Bequests (beneficiary, type, value) | No | Bequest form |
| Life insurance policies (sum assured, in_trust status) | No | Protection data |
| Family members (children, dependents) | No | Family info |
| Date of birth | Yes | Personal info |

#### How It Maps to Recommendations

```
Data Collected                    Service/Agent                      Recommendation Output
--------------------------        ----------------------------       -----------------------------------
All assets - liabilities     -->  EstateAssetAggregator         -->  Net estate value
Net estate + NRB/RNRB       -->  IHTCalculationService         -->  "IHT liability: X at 40%"
Charitable bequests < 10%   -->  WillAnalysisService           -->  "Increase charity to 10% for 36% rate"
Liquid assets < IHT bill    -->  ComprehensiveEstatePlan       -->  "Liquidity shortfall - consider life cover"
Life policies not in trust  -->  ComprehensiveEstatePlan       -->  "Place policies in trust"
Age + life expectancy       -->  GiftingStrategyOptimizer      -->  "Gift 3k/year, save X over Y years"
Age <= 50 + remaining IHT   -->  ComprehensiveEstatePlan       -->  "Whole-of-life cover for remaining"
7+ years to LE              -->  GiftingStrategyOptimizer      -->  "PET gifting: X cycles, shelter Y"
Last resort                 -->  PersonalizedTrustStrategy     -->  "CLT into trust (20% immediate)"
Will + family dynamics      -->  WillAnalysisService           -->  "Trusts needed for specific wishes"
```

**7-Step IHT Mitigation Decision Tree** (priority order):
1. Charitable bequest rate reduction (40% to 36%)
2. Liquidity assessment
3. Existing life cover check
4. Annual gifting strategy (first resort)
5. Life cover strategy (if age <= 50)
6. PET gifting strategy (7-year cycles)
7. CLT into trust (last resort)

**Key services:** EstateAgent, IHTCalculationService, EstateAssetAggregatorService, GiftingStrategyOptimizer, PersonalizedTrustStrategyService, WillAnalysisService, ComprehensiveEstatePlanService

**Value to user:**
- Knows exact IHT liability
- Clear prioritised action sequence (most tax-efficient first)
- Financial impact of each strategy quantified
- Can plan estate minimisation systematically
- Understands spouse data sharing benefits (transferred NRB/RNRB)

#### Devil's Advocate Issues

- **Dead data:** `country` on liabilities, `secured_against`, `fixed_until`, bequest narrative text
- **Employee Share Schemes:** 60+ fields collected with zero valuation, zero tax analysis, zero recommendations
- **Weak trust recommendations:** Template-driven ("IHT > 0? Recommend trust") rather than personalised by family dynamics
- **Missing:** Will review date staleness, property location (England vs Scotland succession law), beneficiary resilience profiling

---

### 2.3 Investments

#### What We Collect

| Data Point | Required | Source |
|-----------|----------|--------|
| Account type (ISA, GIA, SIPP, Bonds) | Yes | Account form |
| Current value | Yes | Account form |
| Holdings (name, quantity, price, cost basis, sector) | No | Holdings form |
| Platform fees, OCF | No | Account form |
| Monthly contribution | No | Account form |
| Rebalance threshold % | No | Account form |
| Risk profile (tolerance, capacity, time horizon) | Yes (for recommendations) | Risk questionnaire |
| Investment goals (target, date) | No | Goal form |
| Annual income | Yes | User profile |

#### How It Maps to Recommendations

```
Data Collected                    Service/Agent                      Recommendation Output
--------------------------        ----------------------------       -----------------------------------
No risk profile              -->  ActionDefinitionService        -->  "Complete risk profile"
Holdings by class            -->  PortfolioAnalyzer             -->  Diversification analysis
Concentration > 30%          -->  DiversificationAnalyzer       -->  "Concentrated in X, add Y"
Platform + OCF fees          -->  FeeAnalyzer                   -->  "Fees X/yr, save Y by switching"
Actual vs target allocation  -->  AssetAllocationOptimizer      -->  "Rebalance: sell X, buy Y"
Unrealised losses            -->  TaxEfficiencyCalculator       -->  "Tax-loss harvest X"
GIA but no ISA               -->  ActionDefinitionService        -->  "Open ISA for tax-free growth"
ISA allowance remaining      -->  ActionDefinitionService        -->  "Use remaining X ISA allowance"
Current value + contributions-->  MonteCarloSimulator           -->  5/10/20/30-year projections
```

**Database-driven triggers** (`investment_action_definitions` table):
- `risk_profile_not_set`, `diversification_score_below`, `total_fee_percent_above`, `weighted_ocf_above`, `allocation_needs_rebalancing`, `has_harvesting_opportunities`, `has_gia_no_isa`, `has_isa_remaining_and_gia`

**Key services:** InvestmentAgent, PortfolioAnalyzer, FeeAnalyzer, TaxEfficiencyCalculator, AssetAllocationOptimizer, InvestmentActionDefinitionService, MonteCarloSimulator

**Value to user:**
- Portfolio alignment with risk tolerance
- Fee drag identified with cost-cutting opportunities
- Rebalancing guidance with specific trades
- Tax-efficient positioning (ISA vs GIA)
- Projected future values under multiple scenarios (Monte Carlo)

#### Devil's Advocate Issues

- **Dead data:** `esg_preference`, `attitude_to_volatility`, `voting_rights`, `dividend_rights`, `company_sector`, `platform_fee_percent` (not aggregated in fee analysis)
- **Missing:** Performance vs benchmark, fund-level constituent holdings (overlap detection), execution dates (for tax-loss harvesting timing)
- **Risk profile is binary:** Low/medium/high ignores user values, ESG preferences, knowledge level
- **No cross-module coordination:** Investments don't coordinate with Tax (no ISA sequencing, no CGT planning, no dividend optimisation)

---

### 2.4 Tax Optimisation

#### What We Collect

| Data Point | Required | Source |
|-----------|----------|--------|
| Domicile status | No | Domicile form |
| UK arrival date | If non-UK | Domicile form |
| All income sources | Yes | User profile |
| Pension contributions (employee + employer) | No | Pension records |
| Investment account types (ISA, GIA) | No | Investment accounts |
| Realised capital gains | No | Holdings |
| Dividend income | No | User profile |
| Marital status | Yes | Personal info |
| Age | Yes | Personal info |

#### How It Maps to Recommendations

```
Data Collected                    Service/Agent                      Recommendation Output
--------------------------        ----------------------------       -----------------------------------
Income + contributions       -->  UKTaxCalculator               -->  "Remaining pension AA: X"
ISA subscriptions YTD        -->  UKTaxCalculator               -->  "Remaining ISA allowance: X"
Dividend income              -->  UKTaxCalculator               -->  "First 500 tax-free, excess at X%"
Capital gains                -->  UKTaxCalculator               -->  "Use CGT allowance: first 3k free"
Tax band utilisation         -->  TaxBandTracker                -->  Personal allowance/basic rate usage
```

**Key services:** UKTaxCalculator (35K+ LOC), TaxConfigService (central tax values), TaxBandTracker

**Value to user:**
- Knows tax position for the year
- Understands remaining contribution room (pension AA, ISA)
- Identifies tax-free opportunities
- Can plan year-end tax strategies

#### Devil's Advocate Issues - CRITICAL

- **No TaxOptimisationAgent exists** - this is a ghost module
- **CoordinatingAgent excludes tax** from its 6-module orchestration
- **Zero cross-module tax strategies:** No ISA sequencing, no spousal income splitting, no salary sacrifice, no BADR strategy, no carry forward, no temporal deferral
- **Missing strategies with zero system support:**
  1. Salary sacrifice optimisation
  2. Spousal income splitting
  3. ISA sequencing (which type to max first)
  4. Business asset disposal relief
  5. Carry forward of unused allowances
  6. Temporal tax deferral
- **Users see tax data but get no actionable tax strategy** - read-only reporting, not proactive planning

---

### 2.5 Retirement

#### What We Collect

| Data Point | Required | Source |
|-----------|----------|--------|
| Target retirement age | No | Retirement profile |
| Target retirement income | No | Retirement profile |
| DC pensions (value, contributions, employer match, strategy) | Yes (value) | Pension forms |
| DB pensions (annual income, accrual rate, service years) | Yes (income) | Pension forms |
| State pension (age, estimated income) | No | State pension form |
| NI contributions record | No | State pension form |
| Date of birth / current age | Yes | Personal info |
| Monthly expenditure | No | User profile |
| Life expectancy | System default (85) | Not user-configurable |

#### How It Maps to Recommendations

```
Data Collected                    Service/Agent                      Recommendation Output
--------------------------        ----------------------------       -----------------------------------
Target income - projected    -->  PensionProjector              -->  "Income gap: X/year"
Income gap + years left      -->  ContributionOptimizer         -->  "Increase contributions by X/year"
DC value < required fund     -->  ActionDefinitionService        -->  "Build DC pension to target X"
Retiring before SPA          -->  ActionDefinitionService        -->  "Defer state pension: gain X/yr"
Contributions > 60k          -->  AnnualAllowanceChecker        -->  "AA breached by X, tax charge: Y"
Pension composition          -->  PensionPortfolioAnalyzer      -->  Asset allocation analysis
All pension sources          -->  MonteCarloSimulator           -->  Confidence levels for sufficiency
```

**Database-driven triggers** (`retirement_action_definitions` table):
- `income_gap_exists`, `insufficient_dc_value`, `state_pension_not_optimized`, `income_protection_gap`, `annual_allowance_exceeded`, `decumulation_strategy_missing`

**Key services:** RetirementAgent, PensionProjector, PensionPortfolioAnalyzer, AnnualAllowanceChecker, ContributionOptimizer, RetirementActionDefinitionService

**Value to user:**
- Knows if on track for retirement target
- Specific contribution increase guidance
- State pension optimisation (deferral benefit quantified)
- Projected income at retirement (Monte Carlo)
- Avoids Annual Allowance penalties

#### Devil's Advocate Issues

- **Dead data:** `scheme_member_reference`, `pension_provider_helpline`, `projected_increase_percent`, `scheme_status`
- **Fixed life expectancy (85):** No personalisation by health or family history
- **Missing:** Partner/spouse pension expectations, care cost assumptions, DB commutation comparison
- **No cross-module links:** Goals don't feed into retirement projection; volatile portfolio doesn't trigger sequence-of-returns warnings for near-retirees

---

### 2.6 Retirement Decumulation

#### What We Collect

| Data Point | Required | Source |
|-----------|----------|--------|
| Pension fund value at retirement | Projected | Retirement projections |
| Target withdrawal amount | No | Retirement profile |
| Drawdown vs annuity preference | No | User choice |
| Inflation assumption | System default (2.5%) | Not user-configurable |
| Longevity estimate | System default (85) | Not user-configurable |
| Spouse income needs | No | Family profile |
| Estate planning goals | No | Estate profile |

#### How It Maps to Recommendations

```
Data Collected                    Service/Agent                      Recommendation Output
--------------------------        ----------------------------       -----------------------------------
Fund value + withdrawal rate -->  DecumulationPlanner           -->  "Safe annual withdrawal: X"
Multiple account types       -->  DecumulationPlanner           -->  "Sequence: tax-free lump first, then drawdown"
Age + life expectancy        -->  DecumulationPlanner           -->  "X% probability of outliving funds"
Fund value + longevity       -->  DecumulationPlanner           -->  "Consider annuity for guarantee"
```

**Strategies:** 4% rule, flexible drawdown, annuity conversion, income drawdown

**Value to user:**
- Knows sustainable withdrawal amount
- Clear drawdown sequencing plan
- Understands longevity risk
- Can optimise tax position in retirement

#### Devil's Advocate Issues - CRITICAL

- **DecumulationPlanner exists but is NEVER called** by RetirementAgent or CoordinatingAgent
- **No API endpoint** exposes decumulation analysis
- **Users don't see drawdown strategies** until they actually retire
- **Missing:** Spouse coordination, care cost scenarios, sequence-of-returns risk warnings

---

### 2.7 Risk Profile

#### What We Collect

| Data Point | Required | Source |
|-----------|----------|--------|
| Investment experience | Yes | Risk questionnaire |
| Investment time horizon | Yes | Risk questionnaire |
| Risk tolerance | Yes | Risk questionnaire |
| Comfort with volatility (max acceptable loss %) | Yes | Risk questionnaire |
| Investment goals | No | Risk questionnaire |
| Market experience (stocks, bonds, alternatives) | No | Risk questionnaire |

#### How It Maps to Recommendations

```
Data Collected                    Service/Agent                      Recommendation Output
--------------------------        ----------------------------       -----------------------------------
Questionnaire answers        -->  AutoRiskCalculator            -->  Risk score (1-10), category
Risk category                -->  AssetAllocationOptimizer      -->  Target allocation (e.g. 60/40)
Actual vs target allocation  -->  InvestmentAgent               -->  "Portfolio too aggressive, rebalance"
No profile set               -->  ActionDefinitionService        -->  "Complete risk profile"
```

**AutoRiskCalculator 7 factors:** Capacity for loss, Time horizon, Investment knowledge, Dependants, Employment status, Emergency cash, Surplus cash

**Value to user:**
- Portfolio aligned with tolerance
- Prevents over/under risk-taking
- Target asset allocation guidance

#### Devil's Advocate Issues

- **Dead data:** `esg_preference`, `attitude_to_volatility` collected but completely unused
- **Missing factors:** Age, estate size, income stability, planned inheritances
- **Once set manually, never recalculates** - no trigger on age change, life events, or capacity change
- **No "you said X but your portfolio says Y" warning** when manual preference mismatches auto-calculation

---

### 2.8 Goals and Life Events

#### What We Collect

**Goals:**

| Data Point | Required | Source |
|-----------|----------|--------|
| Goal title | Yes | Goal form |
| Goal type (education, home, car, etc.) | Yes | Goal form |
| Target amount | Yes | Goal form |
| Target date | Yes | Goal form |
| Current savings toward goal | No | Goal form |
| Annual contribution | No | Goal form |
| Priority (high/medium/low) | No | Goal form |
| Linked savings/investment account | No | Goal form |

**Life Events:**

| Data Point | Required | Source |
|-----------|----------|--------|
| Event type (birth, marriage, redundancy, etc.) | Yes | Life event form |
| Event date | Yes | Life event form |
| Expected financial impact | No | Life event form |
| Impact category (income/expenditure/asset change) | No | Life event form |
| Trigger replan | No | Life event form |

#### How It Maps to Recommendations

```
Data Collected                    Service/Agent                      Recommendation Output
--------------------------        ----------------------------       -----------------------------------
Target + date + progress     -->  GoalAffordabilityService      -->  "On track / at risk / unaffordable"
Required vs available surplus-->  GoalAffordabilityService      -->  "Increase contribution to X/month"
Unaffordable goal            -->  GoalAffordabilityService      -->  "Extend timeline or reduce target"
All goals ranked             -->  GoalProgressService           -->  "Focus on: 1) X, 2) Y, 3) Z"
Goal + time horizon          -->  GoalAssignmentService         -->  Assigns to savings/investment module
Goal risk level              -->  GoalRiskService               -->  Likelihood of achievement
Life event trigger           -->  Observers                     -->  Full plan recalculation across modules
```

**Key services:** GoalsAgent, GoalAssignmentService, GoalAffordabilityService, GoalProgressService, GoalRiskService

**Value to user:**
- Knows which goals are achievable
- Specific contribution targets per goal
- Priority ranking across multiple goals
- Life events auto-trigger replanning

#### Devil's Advocate Issues

- **Dead data:** `contribution_streak`, `longest_streak`, `milestones`, `completion_notes` never displayed or used
- **No goal conflict detection:** Two goals requesting 100% of monthly surplus with no ranking or deferral
- **No tax-efficient vehicle mapping:** Goals don't map to ISA/pension/taxable accounts
- **Property goals:** Recommend 20% deposit without verifying mortgage qualification

---

### 2.9 Family, Letter to Spouse & Coordination

#### What We Collect

**Family Members:**

| Data Point | Required | Source |
|-----------|----------|--------|
| Name | Yes | Family form |
| Relationship (spouse, child, dependent) | Yes | Family form |
| Date of birth | No | Family form |
| Email (if spouse) | No | Family form |
| Is dependent | No | Family form |
| Special needs | No | Family form |

**Letter to Spouse (27 fields):**

| Data Point | Required | Source |
|-----------|----------|--------|
| Immediate actions | No | Letter form |
| Executor contacts | No | Letter form |
| Account access information | No | Letter form |
| Insurance policy locations | No | Letter form |
| Cryptocurrency details | No | Letter form |
| Vehicle details | No | Letter form |
| Funeral wishes | No | Letter form |
| Document type | No | Letter form |

#### How It Maps to Recommendations

```
Data Collected                    Service/Agent                      Recommendation Output
--------------------------        ----------------------------       -----------------------------------
Spouse + dependents          -->  ProtectionAgent               -->  Coverage needs multiplier
Children                     -->  EstateAgent                   -->  Beneficiary/trust distribution
Guardianship wishes          -->  WillAnalysisService           -->  "Appoint guardian in will"
Letter content               -->  LetterToSpouseService         -->  Document generation/storage
Household structure          -->  CoordinatingAgent             -->  Cross-module conflict resolution
```

**Value to user:**
- Family structure informs protection needs and estate distribution
- Letter provides peace of mind for executors/spouse
- Joint ownership support across all asset types

#### Devil's Advocate Issues - CRITICAL

- **Letter to Spouse is completely isolated:** 27 fields never validated against Estate data
  - Executor in letter vs executor in will? No cross-check
  - Insurance locations listed but not matched to system holdings
  - Cryptocurrency noted but not tracked as an asset
- **Dead family data:** `education_status`, `receives_child_benefit`, `national_insurance_number` collected with zero downstream usage
- **No household coordination:** Despite serving married couples:
  - No household-level financial plan
  - No spousal asset optimisation recommendations
  - Plans can conflict (Spouse A: buy residence; Spouse B: buy investment property)
  - No "what if one spouse dies?" household scenario

---

## 3. Cross-Module Dependencies

### Data Flow Map

```
Personal Info (DOB, Gender, Marital Status)
  |
Family Structure (Spouse, Dependents) <--- used by Protection & Estate
  |
Income (Employment, Salary Sources) <--- used by ALL modules
  |
Expenditure (Monthly costs) <--- used by Retirement & Goals
  |
Assets (Properties, Savings, Investments, Pensions) <--- core to ALL modules
  |
Liabilities (Mortgages, Loans) <--- Estate value, Retirement planning
  |
Domicile (UK status, Tax residency) <--- Tax & IHT calculations
  |
Protection Policies <--- Estate supplementation
  |
Will/Trusts/Gifts <--- Estate planning
  |
Risk Profile <--- Investment recommendations
  |
Goals/Life Events <--- Retirement & savings planning
```

### Dependency Matrix

| From | To | What Flows | Why |
|------|-----|-----------|-----|
| Income | Protection | Annual salary | Coverage gap calculation |
| Income | Retirement | Salary + sources | Pension contribution capacity |
| Assets | Estate | All asset values | IHT liability |
| Assets | Investments | Holdings, allocation | Portfolio analysis |
| Assets | Retirement | Pension values | Income projection |
| Liabilities | Estate | Total debt | Net estate value |
| Liabilities | Retirement | Mortgage term | Debt-free date vs retirement |
| Family | Protection | Dependents count | Insurance needs multiplier |
| Family | Estate | Beneficiaries | Distribution planning |
| Domicile | Estate | UK/non-UK | IHT applicability |
| Expenditure | Retirement | Monthly costs | Income replacement ratio |
| Expenditure | Goals | Savings capacity | Goal feasibility |
| Protection | Estate | Policy proceeds | Estate liquidity |
| Goals | Investments | Time horizon | Asset allocation |
| Life Events | ALL | Event trigger | Full plan recalculation |

---

## 4. Architecture: How Data Becomes Value

### End-to-End Pipeline

```
User Data Collection
  |
  v
Vue Components (378) --> API Services (33) --> Controllers (70)
  |
  v
Module Agents (8): Protection, Investment, Savings, Retirement, Estate, Goals, Savings, Coordinating
  |
  v
Domain Services (174): Calculations, Projections, Analysis
  |
  v
Database-driven Triggers: investment_action_definitions, retirement_action_definitions
  |
  v
CoordinatingAgent: Conflict resolution, Priority ranking, Cash flow allocation
  |
  v
API Response --> Vue Components --> User sees: Actions, Strategies, Recommendations
```

### Coordination: How Conflicts Are Resolved

The **CoordinatingAgent** resolves competing recommendations using:

1. **Urgency** - AA breaches and deadlines first
2. **Impact** - IHT savings and death protection prioritised
3. **Dependencies** - Prerequisites sequenced (risk profile before allocation)
4. **Affordability** - Within available surplus

### Example: Full Transformation

```
INPUT: Age 40, 50k income, 100k pension, zero life insurance,
       2 kids, 300k house, 5k credit card debt

7 MODULE AGENTS RUN SIMULTANEOUSLY:
  Protection: 300k coverage gap detected
  Estate: 24k IHT liability
  Savings: Credit card wasting 900/year
  Investment: No ISA (tax efficiency gap)
  Retirement: On track but could optimise
  Goals: School fees unaffordable
  Risk: Needs profile completion

COORDINATED PLAN (Ranked):
  1. CRITICAL: Buy 300k life insurance (protect dependents)
  2. CRITICAL: Clear 5k credit card (18% = 900/yr waste)
  3. HIGH: Maximise pension (remaining AA room)
  4. HIGH: Place policies in trust (IHT)
  5. MEDIUM: Open ISA for school fees (5-year horizon)
  6. MEDIUM: Annual gifting strategy (3k/yr, 7-year IHT)
  7. LOW: Estate documents (will, guardianship, POA)

CASH FLOW ALLOCATION:
  Life insurance: 360/year
  Credit card clearance: 5,100 (6-month plan)
  Pension contribution: 2,000/year
  ISA investment: 3,000/year
  Remaining surplus: 9,540 (buffer)
```

---

## 5. Gap Analysis & Opportunities

### Dead Data (Collected but Never Used)

| Module | Dead Fields | % of Fields | Severity |
|--------|------------|-------------|----------|
| Protection | occupation, health_status, dependents_ages | 20% | HIGH |
| Estate | country, secured_against, fixed_until, bequest notes | 15% | MODERATE |
| Investment | esg_preference, attitude_to_volatility, voting_rights, dividend_rights, company_sector | 25% | HIGH |
| Retirement | scheme_member_reference, provider_helpline, scheme_status | 10% | MODERATE |
| Risk | esg_preference, attitude_to_volatility | 30% | MODERATE |
| Goals | contribution_streak, milestones, completion_notes | 20% | MODERATE |
| Family | education_status, receives_child_benefit, NIN | 30% | MODERATE |
| Letter to Spouse | All 27 fields isolated from Estate | 100% | CRITICAL |
| Employee Shares | 40+ fields (tax treatment, vesting, etc.) | 90%+ | CRITICAL |

### Missing Module: Tax Optimisation Agent

Tax Optimisation has no agent, no orchestration, and no cross-module strategies. Missing strategies:

1. Salary sacrifice optimisation
2. Spousal income splitting
3. ISA sequencing (which type to max first)
4. Business asset disposal relief
5. Carry forward of unused allowances
6. Temporal tax deferral

### Stranded Logic: Decumulation

`DecumulationPlanner` exists with sophisticated logic but is never called by RetirementAgent, has no API endpoint, and users never see drawdown strategies.

### Missing Household Coordination

Despite serving married couples, there is no household-level plan, no spousal asset optimisation, and plans can conflict between spouses.

### Cross-Module Blind Spots

| Missing Link | Impact |
|-------------|--------|
| Tax + Investments | No ISA sequencing, no CGT planning |
| Tax + Retirement | No pension relief sequencing, no spousal optimisation |
| Protection + Retirement | No IP phase-out at pension age |
| Investments + Goals | No conflict detection for same surplus |
| Investments + Retirement | No sequence-of-returns warnings for near-retirees |
| Protection + Savings | Emergency fund could reduce cover need |
| Goals + Estate | Major purchases affect IHT planning |

### Onboarding Friction

- 11-step wizard presented upfront (no progressive disclosure)
- Collects dead data fields increasing friction without output
- No "skip if not applicable" for wills/trusts (relevant to ~5% of users)
- Likely high abandonment at steps 5-7

---

## 6. Priority Recommendations

### Priority 1: Immediate (1-2 weeks)

1. **Remove dead data fields from forms** - reduce onboarding friction by 30-40%
   - Protection: remove occupation, health_status from forms
   - Family: remove education_status, receives_child_benefit
   - Goals: remove contribution_streak display (never shown)

2. **Activate DecumulationPlanner** - wire into RetirementAgent, create API endpoint, show in retirement dashboard

3. **Integrate Letter to Spouse with Estate** - cross-validate executors, match insurance to holdings, flag untracked assets

### Priority 2: Near-term (3-4 weeks)

4. **Create Tax Optimisation Agent** - ISA sequencing, spousal transfers, BADR strategy, carry forward; integrate into CoordinatingAgent

5. **Implement progressive onboarding** - minimum 3 steps first (personal, income, assets), show dashboard, then opt-in modules

6. **Build household coordination** - household net worth view, spousal asset optimisation, "death of spouse" scenario

### Priority 3: Medium-term (4-8 weeks)

7. **Enable ESG and knowledge factors** in investment recommendations
8. **Build cross-module strategies** - sequencing recommendations across tax/investment/retirement
9. **Personalise recommendations** - use family composition, career stage, previous decisions instead of templates
10. **Add missing data points** - user-configurable life expectancy, goal dependencies, care cost assumptions

---

## Supporting Documents

- **Full Financial Mapping:** `FYNLA_DATA_VALUE_MAPPING.md` (data-to-recommendation detail for all 9 modules)
- **Full Gap Analysis:** `DEVILS_ADVOCATE_ANALYSIS.md` (dead data, weak recommendations, missing integrations)
- **UX Data Journey:** Stored in project memory (form fields, validation rules, cross-module dependencies)
