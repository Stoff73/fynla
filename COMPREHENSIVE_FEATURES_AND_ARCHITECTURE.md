# Fynla Financial Planning Application - Comprehensive Feature & Architecture Guide

**Version**: v0.2.6  
**Status**: Beta - Active Development  
**Last Updated**: November 12, 2025

---

## Executive Summary

Fynla is a UK-focused comprehensive financial planning web application built with Laravel 10.x and Vue.js 3. It provides integrated financial analysis across five major modules (Protection, Savings, Investment, Retirement, Estate Planning) with advanced features like Monte Carlo simulations, portfolio optimization, and holistic planning recommendations.

**Key Facts**:
- **45+ Database Models** for comprehensive financial tracking
- **80+ API Endpoints** across all modules
- **150+ Vue Components** with reusable form patterns
- **40+ Specialized Services** for domain-specific calculations
- **6 Intelligent Agents** orchestrating module analysis and recommendations
- **60+ Pest Tests** with 100% passing rate
- **15 Vuex Store Modules** for state management

---

## Complete Feature Set by Module

### 1. Protection Module (Insurance Analysis)

**Purpose**: Analyze life, critical illness, and income protection coverage needs

**Key Features**:
- Life Insurance policy management (term, whole of life, decreasing term, level term, family income benefit)
- Critical Illness policy tracking (standalone, accelerated, additional)
- Income Protection (disability) policy analysis
- Sickness/Illness policy support
- **Coverage Adequacy Scoring**: 0-100 scale with visual gauge
- **Gap Analysis**: Identifies shortfalls in life insurance, critical illness, and income protection
- **Premium Affordability Assessment**: Checks if coverage fits within user's budget
- **Policy Timeline Visualization**: Shows when policies start/end and premium changes
- **Scenario Modeling**: Death, critical illness, and disability impact analysis
- **Comprehensive Protection Plan**: Professional PDF report generation for advisers
- **In-Trust Policies**: Support for policies held in trust
- **Spouse Coverage**: Analyzes both individual and household-level coverage

**Services**:
- `CoverageGapAnalyzer` - Calculates insurance gaps based on earnings and dependents
- `AdequacyScorer` - Generates overall adequacy score with trend analysis
- `RecommendationEngine` - Generates prioritized protection recommendations
- `PremiumAffordabilityAnalyzer` - Compares premiums to available budget

**Database Models**:
- `ProtectionProfile` - User's insurance setup preferences
- `LifeInsurancePolicy` - Life insurance policies
- `CriticalIllnessPolicy` - Critical illness coverage
- `IncomeProtectionPolicy` - Disability/income protection
- `DisabilityPolicy` - Additional disability coverage
- `SicknessIllnessPolicy` - Sickness/illness coverage

**Dashboard Tabs**:
1. **Current Situation** - Data entry for all policies with forms
2. **Analysis** - Gap analysis, adequacy scoring, affordability checks
3. **Strategy** - Prioritized recommendations with cost estimates
4. **Comprehensive Protection Plan** - Professional report generation and export

---

### 2. Savings Module (Emergency Fund & Cash Management)

**Purpose**: Track savings accounts, emergency fund adequacy, and ISA allowances

**Key Features**:
- Savings account management (Cash ISA, General savings, notice accounts, fixed-term)
- Emergency fund analysis with 3-6 month runway calculations
- **ISA Allowance Tracking** (£20,000 annual limit for 2025/26)
  - Aggregates Cash ISAs from Savings module
  - Aggregates S&S ISAs from Investment module
  - Warns when approaching or exceeding limit
- Savings goal tracking with progress monitoring
- **Liquidity Ladder Analysis**: Categorizes accounts by access (immediate, notice, fixed)
- **Rate Comparison**: Compares account interest rates to market
- **Goal Progress Tracking**: Shows progress toward savings targets

**Services**:
- `EmergencyFundCalculator` - Calculates required emergency reserves
- `ISATracker` - Cross-module allowance aggregation and tracking
- `RateComparator` - Compares savings account rates
- `LiquidityAnalyzer` - Analyzes cash accessibility
- `GoalProgressCalculator` - Tracks savings goal progress

**Database Models**:
- `SavingsAccount` - Cash savings accounts (access types: immediate, notice, fixed)
- `SavingsGoal` - Savings targets with progress tracking
- `ISAAllowanceTracking` - Tracks ISA usage per tax year

**Dashboard Tabs**:
1. **Current Situation** - Account entry with access type selection
2. **Analysis** - Emergency fund gauge, goal progress, liquidity analysis
3. **Recommendations** - Rate comparison and rate improvement suggestions

---

### 3. Investment Module (Portfolio Optimization & Risk Analysis)

**Purpose**: Comprehensive portfolio analysis with advanced risk metrics and optimization

**Key Features**:

**Portfolio Analysis**:
- Investment account management (ISA, GIA, NS&I, onshore bond, offshore bond, VCT, EIS)
- Holdings management with individual fund/security tracking
- **Advanced Risk Metrics**:
  - Alpha (excess return vs. benchmark)
  - Beta (market sensitivity)
  - Sharpe Ratio (risk-adjusted return)
  - Volatility (standard deviation)
  - Maximum Drawdown (peak-to-trough decline)
  - Value at Risk (VaR 95%)
  - Correlation matrices and covariance calculations

**Portfolio Optimization**:
- **Efficient Frontier Analysis**: Identifies optimal risk/return combinations
- **Markowitz Optimization**: Modern portfolio theory implementation
- **Asset Allocation Breakdown**: Visual allocation across asset classes
- **Diversification Scoring**: Concentration risk analysis
- **Rebalancing Tools**: Tax-aware rebalancing with drift analysis

**Monte Carlo Simulations**:
- 1,000 iterations of portfolio growth over 1-30 year periods
- Year-by-year percentile projections (10th, 25th, 50th, 75th, 90th)
- Median and best/worst case scenario projections
- Contribution impact modeling

**Fee Analysis**:
- Platform fee impact analysis
- Fund Ongoing Charge Figure (OCF) breakdown
- Total cost of ownership calculations
- Low-cost alternative recommendations
- Tax drag calculations

**Tax Efficiency**:
- ISA vs. GIA tax efficiency analysis
- Capital Gains Tax (CGT) harvesting optimization
- Bed and ISA strategies
- Tax-location recommendations

**Goal-Based Planning**:
- Investment goal progress probability analysis
- Shortfall and surplus modeling
- Scenario analysis for goal achievement
- Required return calculations

**Performance Attribution**:
- Asset allocation effect on returns
- Security selection impact
- Benchmark comparison analysis

**Model Portfolios**:
- Pre-built diversified portfolios by risk profile
- Risk questionnaire-based recommendations
- Fund selector with performance data
- Asset allocation templates

**Asset Location Optimization**:
- Account type recommendations (ISA, GIA, pension)
- Tax-drag calculator
- Optimal placement suggestions for funds

**Contribution Optimization**:
- Required monthly contribution calculations
- Lump sum impact analysis
- Goal probability by contribution level

**Investment & Savings Plans** (NEW - November 2025):
- Consolidated view combining Investment and Savings goals
- Risk dashboard with comprehensive metrics
- Progress tracking across all investments
- Plan generation with recommendations

**Services**:
- `MonteCarloSimulator` - Runs 1,000 iteration portfolio projections
- `PortfolioAnalyzer` - Comprehensive portfolio risk analysis
- `EfficientFrontierCalculator` - Calculates optimal portfolios
- `FeeAnalyzer` - Analyzes total cost of ownership
- `TaxEfficiencyCalculator` - Tax optimization calculations
- `AssetLocationOptimizer` - Recommends account placement
- `ContributionOptimizer` - Calculates required contributions
- `RebalancingCalculator` - Tax-aware rebalancing analysis
- `InvestmentPlanGenerator` - Creates comprehensive investment plans

**Database Models**:
- `InvestmentAccount` - Brokerage/investment accounts by type
- `Holding` - Individual holdings (polymorphic - belongs to Account or DC Pension)
- `InvestmentGoal` - Goals like "£500k in 10 years"
- `InvestmentPlan` - Generated comprehensive plans
- `InvestmentRecommendation` - Specific recommendations with actions
- `InvestmentScenario` - What-if scenario projections
- `RiskProfile` - User's risk tolerance assessment
- `RebalancingAction` - Suggested rebalancing transactions

**Dashboard Tabs**:
1. **Current Situation** - Account and holding entry
2. **Analysis** - Risk metrics, allocation breakdown, diversification
3. **Portfolio Optimisation** - Efficient frontier, recommendations
4. **Monte Carlo** - 1,000 simulation results with projections
5. **Fee Analysis** - Platform and fund cost breakdown
6. **Tax Efficiency** - Tax optimization opportunities
7. **Goal Progress** - Goal achievement probability analysis
8. **Performance Attribution** - Attribution analysis vs. benchmarks
9. **Investment Plan** - Comprehensive plan generation and export

---

### 4. Retirement Module (Pension Planning & Decumulation)

**Purpose**: Track pensions, analyze retirement readiness, and model decumulation strategies

**Key Features**:

**Pension Inventory**:
- Defined Contribution (DC) pensions with holdings management
- Defined Benefit (DB) pensions with details
- State Pension planning
- **DC Pension Portfolio Optimization** (NEW - November 2025):
  - Full integration of Investment module tools
  - Holdings management for DC pensions
  - Polymorphic holdings system (shared with investment accounts)
  - Advanced risk metrics (Alpha, Beta, Sharpe, Volatility, VaR)
  - Portfolio analysis and diversification scoring
  - Fee analysis with platform and fund breakdowns
  - Monte Carlo simulations for pension growth
  - Efficient frontier analysis

**Contribution Analysis**:
- **Annual Allowance Tracking** (£60,000 + 3-year carry forward)
- Tapered annual allowance for high earners (£180k+ income)
- Contribution optimization with tax relief calculations
- Enhanced Annual Allowance (EAA) for defined benefit schemes

**Retirement Readiness**:
- Multi-factor readiness scoring
- Years to retirement countdown
- Income projection with stacked area charts
- Multiple scenario modeling

**Decumulation Planning**:
- **Annuity vs. Drawdown Comparison**: Shows pros/cons of each
- Sustainability modeling with longevity risk assessment
- Safe withdrawal rate calculations
- Phased retirement income scenarios

**Investment Services** (Shared with Investment Module):
- Monte Carlo simulations for pension growth
- Asset allocation optimization
- Fee analysis
- Tax efficiency recommendations
- Risk profiling

**Services**:
- `PensionProjector` - Models retirement income from all sources
- `RetirementReadinessScorer` - Calculates readiness score
- `AnnualAllowanceChecker` - Tracks pension annual allowance
- `ContributionOptimizer` - Optimizes contribution strategies
- `DecumulationPlanner` - Models drawdown strategies
- Plus all Investment module services for DC pensions

**Database Models**:
- `DCPension` - Defined contribution pensions with holdings
- `DBPension` - Defined benefit pensions
- `StatePension` - State pension details and projections
- `RetirementProfile` - User's retirement preferences
- `Holding` - Holdings in DC pensions (polymorphic)

**Dashboard Tabs**:
1. **Current Situation** - Pension and contribution entry
2. **Analysis** - Readiness scoring, years to retirement, income projection
3. **Portfolio Optimisation** - DC pension portfolio optimization (shared with Investment)
4. **Contribution Optimiser** - Shows contribution impact
5. **Retirement Scenarios** - Models different retirement ages and strategies
6. **Decumulation Planning** - Annuity vs. drawdown analysis

---

### 5. Estate Planning Module (IHT & Net Worth)

**Purpose**: Comprehensive estate planning with IHT calculations and mitigation strategies

**Key Features**:

**IHT Calculations**:
- **Single Person IHT**: Applies Nil Rate Band (£325,000)
- **Married Couple IHT**: 
  - Combined nil rate band (£650,000)
  - Combined Residence Nil Rate Band - RNRB (£350,000)
  - No spouse exemption - uses combined NRB
  - Second death analysis with actuarial projections
- Estate growth projections with inflation/investment returns
- Actuarial life expectancy tables (ONS 2020-2022 data)
- Future value calculations for surviving spouse scenarios

**Asset & Liability Management**:
- Property tracking (main residence, secondary residence, buy to let)
- Investment accounts and holdings
- Savings accounts
- Business interests
- Chattels (valuables, jewelry, art)
- Cash accounts
- **Liability Management**:
  - Mortgages (on any property)
  - Loans (personal, business)
  - Credit cards
  - Other liabilities

**Gifting Strategy**:
- **Potentially Exempt Transfers (PETs)**: 7-year gifting rules
- **Charitable Lifetime Transfers (CLTs)**: Reduced rate gifting (25% IHT)
- Annual exemptions (£3,000)
- Small gifts exemption
- Gifting timeline visualization
- Dual spouse gifting timeline for married couples
- Optimized gifting sequences

**Trust Management**:
- Trust creation and tracking
- Trust-held assets with IHT implications
- Discretionary vs. fixed interest trusts
- Trust tax planning strategies
- Periodic charge calculations

**Will & Intestacy**:
- Will information capture (executor details, date)
- Intestacy rules application (varies by country)
- Bequest management and flexibility
- Digital asset location tracking

**Joint & Spouse Ownership**:
- Joint ownership tracking (creates reciprocal records)
- Spouse data sharing and permissions
- Coordinated IHT planning for couples

**Net Worth Analysis**:
- Complete asset/liability statement
- Net worth projections
- Asset categorization (liquid, semi-liquid, illiquid)
- Ownership analysis (individual, joint, trust)

**Life Cover Strategy**:
- Whole of Life vs. self-insurance comparison
- Premium calculations
- Policy strategy optimization
- Second-death analysis for couple planning

**Services**:
- `IHTCalculationService` - Complete IHT liability calculations
- `NetWorthAnalyzer` - Aggregates all assets/liabilities
- `GiftingTimelineService` - Models gifting strategies
- `PersonalizedGiftingStrategyService` - Recommends optimal gifting
- `LifePolicyStrategyService` - Models life cover options
- `TrustService` - Trust planning and calculations
- `FutureValueCalculator` - Projects asset growth
- `ActuarialLifeTableService` - Life expectancy analysis
- `PersonalizedTrustStrategyService` - Trust recommendations

**Database Models**:
- `IHTCalculation` - Stored IHT calculations with projections
- `IHTProfile` - User's IHT planning preferences
- `Asset` - Estate assets (property, investment, pension, business, other)
- `Liability` - Estate liabilities
- `Gift` - Gifting history and projections
- `Trust` - Trust structures
- `Will` - Will information and bequests
- `Bequest` - Specific bequests in will
- `NetWorthStatement` - Snapshot of net worth at a point in time
- `ActuarialLifeTable` - Life expectancy data

**Dashboard Tabs**:
1. **Current Situation** - Asset/liability entry, ownership type selection
2. **Analysis** - IHT calculations, net worth breakdown, liability summary
3. **IHT Planning** - IHT liability breakdown by scenario
4. **Gifting Strategy** - Optimized gifting timeline and PET management
5. **Trust Planning** - Trust strategy and tax implications
6. **Will Planning** - Will information and intestacy rules
7. **Life Policy Strategy** - Life cover comparison and recommendations

---

### 6. Holistic Planning (Coordinating Agent)

**Purpose**: Cross-module analysis, conflict resolution, and integrated planning

**Key Features**:
- **Module Integration**: Analyzes all five modules together
- **Conflict Resolution**: 
  - Cashflow conflicts (spending vs. savings recommendations)
  - ISA allowance conflicts (savings vs. investment allocation)
  - Protection/insurance vs. financial goals
- **Priority Ranking**: 
  - Urgency × Impact × Ease framework
  - Cross-module recommendation sequencing
- **20-Year Net Worth Projections**: Models future financial position
- **Executive Summary**: Overall financial health score (0-100)
- **Integrated Analysis**: Shows how modules interact
- **Recommendation Tracking**: Mark recommendations as pending/in-progress/completed

**Services**:
- `CoordinatingAgent` - Orchestrates cross-module analysis
- `HolisticPlanner` - Creates integrated financial plans
- `ConflictResolver` - Identifies and resolves module conflicts
- `PriorityRanker` - Prioritizes recommendations by impact
- `CashFlowCoordinator` - Analyzes household cash flow

**Dashboard**:
- **Executive Summary**: Overall financial health score
- **Module Summaries**: Quick stats from each module
- **Holistic Recommendations**: Prioritized cross-module advice
- **Cash Flow Analysis**: Household cash flow allocation
- **20-Year Projection**: Net worth growth over time
- **Financial Health Score**: 0-100 rating with improvement tracking

---

## Authentication & User Management

### Account Types & Roles

**User Types**:
- **Individual Users**: Primary account holder
- **Admin Users**: Full system access (database backups, tax settings, user management)
- **Spouse Accounts**: Linked spouse with optional data sharing permissions

### Authentication System

- **Laravel Sanctum**: Token-based API authentication
- **Auto-Generated Spouse Accounts**: 
  - When primary user adds spouse via Family Members
  - Auto-sends welcome email with login link
  - Spouse must change password on first login
  - `must_change_password` flag enforces security
- **Email-Based Account Linking**:
  - If spouse email already exists, links accounts instead of creating new
  - Sets `marital_status = 'married'` for both users
  - Sends linking notification email
- **Password Security**:
  - First-time login password change requirement
  - Secure password reset via email

### Data Sharing & Permissions

**Spouse Permissions System**:
- Granular view/edit permissions per module
- `SpousePermission` model tracks:
  - `protection_view`, `protection_edit`
  - `savings_view`, `savings_edit`
  - `investment_view`, `investment_edit`
  - `retirement_view`, `retirement_edit`
  - `estate_view`, `estate_edit`
  - `net_worth_view`, `net_worth_edit`
  - `data_sharing_enabled` (master switch)
- Default: All permissions enabled once spouse account linked
- Users can revoke individual permissions

---

## Asset Ownership Features

### Ownership Types (All Modules)

**Three Ownership Models**:
1. **Individual** (sole owner) - Default for most assets
2. **Joint Owner** - Two people own together
3. **Trust Owned** - Asset held in trust

### Joint Ownership Implementation

- **Reciprocal Records**: Automatically creates mirror record for joint owner
- **Joint Owner Selection**: 
  - If spouse in system: Link to user account
  - If external: Free-text entry with optional email
- **Ownership Type Variations** (UK legal structures):
  - Tenants in Common (separate interests, can gift shares)
  - Joint Tenants (automatic survivorship)
- **Affected Assets**:
  - Properties (mortgages auto-linked)
  - Investment accounts
  - Savings accounts
  - Business interests
  - Chattels
  - Cash accounts

### Trust Ownership

- **Trust Selection**: Link to Trust from Estate module
- **Trust-Held Assets**: 
  - Properties held in trust
  - Investment accounts in trust
  - Chattels in trust
- **IHT Implications**: Different treatment in estate calculations

### Special Rules

- **ISAs**: Individual ownership ONLY (UK tax rule - cannot be joint or trust)
- **Pensions**: Individual ownership ONLY
- **Properties**: Support all three ownership types

---

## User Profile & Account Features

### Personal Information
- Name, email, date of birth
- Smoking status (yes, no, yes previously)
- Health status (healthy, poor, yes, yes previously)
- Education level (primary, secondary, tertiary)
- Domicile information (UK resident, domiciled in UK, etc.)

### Income & Expenditure
- **Income**:
  - Annual employment income
  - Self-employment income
  - Investment income
  - Rental income
  - Pension income
  - Other income sources
- **Expenditure**:
  - Detailed monthly/annual breakdown
  - Rent/mortgage
  - Utilities
  - Childcare
  - Education
  - Food
  - Transport
  - Insurance
  - Subscriptions
  - Healthcare
  - Other

### Family & Household
- Family members management
- Spouse/partner information
- Children and dependents
- Household composition
- Household ID for group asset tracking

### Letter to Spouse Feature

**Four-Part Comprehensive Letter**:

**Part 1: What to Do Immediately**
- Executor name and contact
- Power of attorney name and contact
- Financial advisor name and contact
- Accountant name and contact
- Immediate fund access location
- Employer HR and benefits information

**Part 2: Accessing & Managing Accounts**
- Password manager information
- Phone/digital access details
- Bank accounts (auto-populated from Savings module)
- Investment accounts (auto-populated from Investment module)
- Insurance policies (auto-populated from Protection module)
- Real estate details (auto-populated from Estate module)
- Vehicle information
- Valuable items inventory
- Cryptocurrency and digital assets
- Liabilities and debt overview (auto-populated)
- Recurring bills and subscriptions (auto-populated)

**Part 3: Long-Term Plans**
- Estate documents location
- Beneficiary information (auto-populated from Will)
- Children's education plans
- Financial guidance for surviving family
- Social Security/State Pension information

**Part 4: Funeral & Final Wishes**
- Burial vs. cremation preference
- Service details and location
- Obituary wishes
- Memorial instructions
- Additional personal wishes

**Auto-Population**: System automatically aggregates data from all modules
**Dual View**: 
- Users can edit their own letter
- Can view spouse's letter (read-only)
**Data Persistence**: Stored in `letters_to_spouse` table

---

## Onboarding System

### Focus Areas
- **Protection-First**: Start with insurance analysis
- **Savings-First**: Start with emergency fund
- **Investment-First**: Start with portfolio
- **Retirement-First**: Start with pensions
- **Estate-First**: Start with will and assets
- **Holistic**: Complete balanced approach

### Progressive Steps by Focus Area

Each flow adapts based on user's focus:

**Typical Step Flow**:
1. Personal Information (all flows)
2. Income & Occupation (all flows)
3. Domicile Information (all flows)
4. Health Information (for insurance planning)
5. Module-Specific Data Entry (properties, policies, etc.)
6. Family Members (for joint/spouse planning)
7. Completion & Review

### Onboarding Features
- Save progress between sessions
- Skip steps with reason tracking
- Auto-advance to next step
- Restart onboarding from beginning
- Track completion percentage

### Progressive Data Binding
- Forms pre-populate with existing data
- Multi-step forms collect related data
- Validation on submit with error handling
- Automatic syncing to user profile

---

## Admin Panel Capabilities

### Dashboard Tab
- User statistics
- System health metrics
- Recent activities
- Database size information
- Backup status

### User Management Tab
- View all users with registration date
- User details inspection
- Admin role assignment
- User account suspension/activation
- Login history (if available)

### Database Backups Tab
- **Backup Creation**: Create on-demand database backup
- **Backup History**: View all previous backups
- **Restore Functionality**: Restore database from backup
- **Auto-Backups**: Schedule automatic daily backups (configurable)
- **Backup Storage**: Stored in `storage/app/backups/`
- **Retention Policy**: Keeps last 30 days of backups
- **Download Backups**: Download backup files to local machine

### Tax Settings Tab
- **Tax Year Selection**: Choose active tax year (2021/22 - 2025/26)
- **Tax Configuration Editor**: View/edit all tax values:
  - Income tax bands and rates
  - National Insurance thresholds
  - ISA allowances (£20,000)
  - Pension annual allowance (£60,000)
  - IHT rates, NRB (£325k), RNRB (£175k)
  - Capital Gains Tax rates and exemptions
  - Dividend tax allowances
  - Stamp Duty Land Tax bands
  - PET/CLT gifting rules
- **Activation**: Switch active tax year (auto-deactivates previous)
- **Effective Dates**: Set when tax rules apply
- **Notes Field**: Document changes and rationale

---

## Technical Architecture

### Three-Tier Architecture

```
┌─────────────────────────────────────────────────┐
│ Presentation Layer                              │
│ Vue.js 3 + Vuex + ApexCharts + Tailwind CSS    │
│ 150+ Components, 15 Store Modules               │
└─────────────────────────────────────────────────┘
              ↕ REST API (JSON)
┌─────────────────────────────────────────────────┐
│ Application Layer                               │
│ Laravel 10.x Sanctum Auth                       │
│ Controllers + Services + Agents                 │
│ 80+ API Endpoints                               │
└─────────────────────────────────────────────────┘
              ↕ Eloquent ORM
┌─────────────────────────────────────────────────┐
│ Data Layer                                      │
│ MySQL 8.0+ (45+ Tables)                         │
│ Memcached (Calculation Caching)                 │
└─────────────────────────────────────────────────┘
```

### Agent-Based Design Pattern

**Base Agent Architecture**:
- `BaseAgent` - Abstract class with common utilities
- Three core methods: `analyze()`, `generateRecommendations()`, `buildScenarios()`
- Caching with configurable TTLs (default 1 hour)
- Currency and percentage formatting utilities

**Module Agents** (Extend BaseAgent):
1. `ProtectionAgent` - Life/CI/IP analysis and recommendations
2. `SavingsAgent` - Emergency fund and ISA tracking analysis
3. `InvestmentAgent` - Portfolio analysis with Monte Carlo
4. `RetirementAgent` - Pension projections and readiness scoring
5. `EstateAgent` (DEPRECATED - replaced by services)
6. `CoordinatingAgent` - Holistic cross-module planning

### Service Layer Architecture

**Layered Services**:
```
Controllers (API Layer)
    ↓
Agents (Orchestration)
    ↓
Services (Domain Logic)
    ↓
Models (Data Access)
```

**Service Categories**:

**Specialized Calculators**:
- `MonteCarloSimulator` (1,000 iterations)
- `EfficientFrontierCalculator` (Markowitz optimization)
- `IHTCalculationService` (Complete IHT modeling)
- `PensionProjector` (Retirement income projections)

**Analysis Services**:
- `PortfolioAnalyzer` (Risk metrics: alpha, beta, sharpe, VaR)
- `CoverageGapAnalyzer` (Insurance needs assessment)
- `NetWorthAnalyzer` (Asset/liability aggregation)
- `EmergencyFundCalculator` (Cash adequacy)

**Optimization Services**:
- `AssetAllocationOptimizer` (Portfolio allocation)
- `AssetLocationOptimizer` (Account type placement)
- `TaxEfficiencyCalculator` (Tax optimization)
- `RebalancingCalculator` (Drift-based rebalancing)

**Strategy Services**:
- `PersonalizedGiftingStrategyService` (Gifting optimization)
- `PersonalizedTrustStrategyService` (Trust planning)
- `GiftingTimelineService` (7-year gifting rules)
- `LifePolicyStrategyService` (Insurance strategy)

**Coordination Services**:
- `HolisticPlanner` (Integrated planning)
- `ConflictResolver` (Module conflicts)
- `PriorityRanker` (Recommendation prioritization)

**Utility Services**:
- `TaxConfigService` (Centralized tax config access)
- `ISATracker` (Cross-module ISA tracking)
- `PropertyService` (Property CRUD and calculations)
- `PropertyTaxService` (SDLT, CGT, rental income tax)

---

## Database Structure

### Core Tables (45+ Total)

**User Management** (7 tables):
- `users` - User accounts with profile data
- `personal_accounts` - P&L, cash flow, balance sheet
- `family_members` - Dependents and spouse information
- `households` - Grouping for joint ownership
- `spouse_permissions` - Granular data sharing controls
- `onboarding_progress` - Step completion tracking
- `recommendation_tracking` - User's recommendation status

**Protection Module** (5 tables):
- `protection_profiles` - Protection setup preferences
- `life_insurance_policies` - Term, whole of life, etc.
- `critical_illness_policies` - CI coverage details
- `income_protection_policies` - Disability insurance
- `sickness_illness_policies` - Sickness/illness coverage

**Savings Module** (3 tables):
- `savings_accounts` - Cash savings by access type
- `savings_goals` - Savings targets and progress
- `isa_allowance_tracking` - Annual ISA limit tracking

**Investment Module** (9 tables):
- `investment_accounts` - Brokerage accounts by type
- `holdings` - Individual holdings (polymorphic)
- `investment_goals` - Investment targets
- `investment_plans` - Generated plans with recommendations
- `investment_recommendations` - Specific suggestions
- `investment_scenarios` - What-if scenario projections
- `risk_profiles` - Risk tolerance assessment
- `rebalancing_actions` - Suggested rebalancing trades
- Plus: factor exposures, risk metrics, efficient frontier calculations

**Retirement Module** (4 tables):
- `dc_pensions` - Defined contribution pensions
- `db_pensions` - Defined benefit pensions
- `state_pensions` - State pension details
- `retirement_profiles` - Retirement preferences
- (Uses `holdings` table polymorphically for DC pension holdings)

**Estate Planning Module** (13 tables):
- `assets` - Estate assets (property, pension, investment, business, other)
- `liabilities` - Estate liabilities (mortgages, loans, credit cards)
- `properties` - Real estate with full details
- `mortgages` - Mortgages on properties
- `gifts` - Gifting history and projections
- `trusts` - Trust structures and details
- `wills` - Will information
- `bequests` - Specific bequests in will
- `iht_profiles` - IHT planning preferences
- `iht_calculations` - Stored IHT liability calculations
- `net_worth_statements` - Net worth snapshots
- `actuarial_life_tables` - Life expectancy data
- Plus: business interests, chattels, cash accounts

**Other Assets** (5 tables):
- `business_interests` - Business ownership stakes
- `chattels` - Valuables, jewelry, art
- `cash_accounts` - Cash holdings
- `letters_to_spouse` - Emergency instructions
- `tax_configurations` - UK tax rules by year

**Tax & Config** (1 table):
- `tax_configurations` - Complete UK tax config by year

---

## API Structure

### Authentication Routes
```
POST   /api/auth/register              - User registration
POST   /api/auth/login                 - User login
POST   /api/auth/logout                - User logout
GET    /api/auth/user                  - Current user
POST   /api/auth/change-password       - Change password
```

### Onboarding Routes (14 endpoints)
```
GET    /api/onboarding/status          - Get onboarding status
POST   /api/onboarding/focus-area      - Set focus area
GET    /api/onboarding/steps           - Get available steps
GET    /api/onboarding/step/{step}     - Get step data
POST   /api/onboarding/step            - Save step progress
POST   /api/onboarding/complete        - Mark onboarding complete
```

### User Profile Routes (18+ endpoints)
```
GET    /api/user/profile               - Get user profile
PUT    /api/user/profile/personal      - Update personal info
PUT    /api/user/profile/income-occupation - Update income
PUT    /api/user/profile/expenditure   - Update spending
PUT    /api/user/profile/domicile      - Update domicile
GET    /api/user/letter-to-spouse      - Get user's letter
PUT    /api/user/letter-to-spouse      - Update letter
GET    /api/user/letter-to-spouse/spouse - Get spouse's letter
GET    /api/user/family-members        - List family members
POST   /api/user/family-members        - Add family member
```

### Protection Module Routes (8+ endpoints)
```
GET    /api/protection/profile         - Get protection profile
POST   /api/protection/profile         - Create profile
PUT    /api/protection/profile/:id     - Update profile
GET    /api/protection/policies/life-insurance - List policies
POST   /api/protection/policies/life-insurance - Add policy
POST   /api/protection/analyze         - Run analysis
GET    /api/protection/recommendations - Get recommendations
```

### Savings Module Routes (10+ endpoints)
```
GET    /api/savings/accounts           - List accounts
POST   /api/savings/accounts           - Add account
GET    /api/savings/goals              - List goals
POST   /api/savings/goals              - Add goal
GET    /api/savings/isa-tracker        - ISA allowance status
POST   /api/savings/analyze            - Run analysis
```

### Investment Module Routes (25+ endpoints)
```
GET    /api/investment/accounts        - List investment accounts
POST   /api/investment/accounts        - Add account
GET    /api/investment/holdings        - List holdings
POST   /api/investment/holdings        - Add holding
POST   /api/investment/analyze         - Run analysis
POST   /api/investment/monte-carlo     - Run Monte Carlo
POST   /api/investment/optimize        - Run optimization
GET    /api/investment/plans           - Get investment plan
POST   /api/investment/fee-impact      - Fee analysis
POST   /api/investment/tax-efficiency  - Tax analysis
GET    /api/investment/rebalancing     - Rebalancing suggestions
```

### Retirement Module Routes (20+ endpoints)
```
GET    /api/retirement/pensions/dc     - List DC pensions
POST   /api/retirement/pensions/dc     - Add DC pension
GET    /api/retirement/pensions/db     - List DB pensions
POST   /api/retirement/pensions/db     - Add DB pension
POST   /api/retirement/analyze         - Run analysis
POST   /api/retirement/scenarios       - Model scenarios
GET    /api/retirement/holdings        - DC pension holdings
POST   /api/retirement/holdings        - Add DC pension holding
```

### Estate Planning Routes (20+ endpoints)
```
GET    /api/estate/assets              - List assets
POST   /api/estate/assets              - Add asset
GET    /api/estate/liabilities         - List liabilities
POST   /api/estate/liabilities         - Add liability
GET    /api/estate/calculate-iht       - Calculate IHT
POST   /api/estate/gifts               - Add gift
GET    /api/estate/trusts              - List trusts
POST   /api/estate/trusts              - Add trust
GET    /api/estate/will                - Get will info
PUT    /api/estate/will                - Update will
```

### Holistic Planning Routes (8+ endpoints)
```
POST   /api/holistic/analyze           - Cross-module analysis
POST   /api/holistic/plan              - Generate plan
GET    /api/holistic/recommendations   - Get recommendations
POST   /api/holistic/recommendations/:id/mark-done - Mark done
POST   /api/holistic/recommendations/:id/in-progress - Mark in progress
```

### Net Worth Routes (5+ endpoints)
```
GET    /api/net-worth/overview         - Net worth overview
GET    /api/net-worth/breakdown        - Assets/liabilities breakdown
GET    /api/net-worth/trend            - Historical trend
GET    /api/net-worth/assets-summary   - Asset summary
POST   /api/net-worth/refresh          - Refresh calculations
```

### Total: 80+ API Endpoints

---

## Frontend Architecture

### Vue.js 3 Structure

**Component Organization**:
```
resources/js/
├── views/                    # Page-level components
│   ├── Dashboard.vue
│   ├── Protection/
│   ├── Savings/
│   ├── Investment/
│   ├── Retirement/
│   ├── Estate/
│   ├── NetWorth/
│   ├── UserProfile.vue
│   ├── Help.vue
│   ├── Version.vue
│   └── ... (20+ view files)
│
├── components/               # Reusable components by module
│   ├── Protection/           # 15+ components
│   ├── Savings/              # 10+ components
│   ├── Investment/           # 20+ components
│   ├── Retirement/           # 15+ components
│   ├── Estate/               # 20+ components
│   ├── NetWorth/             # 10+ components
│   ├── Holistic/             # 5+ components
│   ├── Dashboard/            # 5+ components
│   ├── Admin/                # 5+ components
│   └── ... (150+ total components)
│
├── layouts/                  # Layout components
│   ├── AppLayout.vue
│   └── AuthLayout.vue
│
├── services/                 # API wrapper services
│   ├── protectionService.js
│   ├── savingsService.js
│   ├── investmentService.js
│   ├── retirementService.js
│   ├── estateService.js
│   ├── adminService.js
│   └── ... (15+ service files)
│
├── store/modules/            # Vuex state management
│   ├── auth.js
│   ├── protection.js
│   ├── savings.js
│   ├── investment.js
│   ├── retirement.js
│   ├── estate.js
│   ├── netWorth.js
│   ├── holistic.js
│   ├── dashboard.js
│   ├── user.js
│   ├── userProfile.js
│   ├── onboarding.js
│   ├── trusts.js
│   ├── spousePermission.js
│   ├── recommendations.js
│   └── (15 total modules)
│
├── router/                   # Vue Router
│   └── index.js              # 30+ routes
│
├── App.vue                   # Root component
├── bootstrap.js              # Axios setup
└── main.js                   # Entry point
```

### Vuex Store Modules

**Auth Module**:
- User login/logout/registration
- Token management
- User role checking

**Dashboard Module**:
- Module summaries
- Quick stats
- Card data loading

**Protection Module**:
- Policy data (life, CI, IP)
- Analysis results
- Gap analysis calculations
- Recommendations

**Savings Module**:
- Account list
- Emergency fund calculations
- ISA allowance tracking
- Goal progress

**Investment Module** (Largest - 46KB):
- Investment accounts and holdings
- Portfolio analysis data
- Monte Carlo results
- Risk metrics and allocation
- Plans and recommendations
- Scenarios

**Retirement Module** (22KB):
- Pension list (DC, DB, State)
- Retirement readiness data
- Income projections
- Annual allowance tracking
- DC pension holdings (polymorphic)

**Estate Module** (21KB):
- Assets and liabilities
- IHT calculations
- Gifting strategies
- Trust data
- Will information
- Life policy strategy

**Net Worth Module** (22KB):
- Overall net worth
- Asset breakdown by type
- Ownership analysis
- Trend data
- Historical snapshots

**Holistic Module** (8KB):
- Executive summary
- Recommendations
- Cross-module insights
- Priority ranking

**User Profile Module** (15KB):
- Personal information
- Income and expenditure
- Family members
- Domicile information
- Spouse permissions
- Letter to spouse data

---

## State Management (Vuex)

### Store Pattern

**Per-Module Structure**:
```javascript
{
  state: {
    items: [],
    loading: false,
    error: null,
    selectedId: null
  },
  
  mutations: {
    setLoading(state, loading) { ... },
    setError(state, error) { ... },
    setItems(state, items) { ... }
  },
  
  actions: {
    async fetchItems({ commit }) { ... },
    async createItem({ commit }, data) { ... },
    async updateItem({ commit }, data) { ... }
  },
  
  getters: {
    items: state => state.items,
    totalValue: state => state.items.reduce(...),
    isLoading: state => state.loading
  }
}
```

### Form Modal Pattern

**Unified Form Components** (Critical Rule):
- Single form component used across onboarding, dashboard, and editing
- Accepts `item` and `isEditing` props
- Emits `@save` and `@close` events (NOT `@submit` - causes double submission)
- Parent component handles create vs. update logic

**Example Usage**:
```vue
<PolicyForm
  v-if="showForm"
  :policy="editingPolicy"
  :is-editing="!!editingPolicy"
  @save="handleSave"
  @close="showForm = false"
/>
```

---

## Testing & Quality

### Test Suite

**Pest PHP Framework** (60+ tests):
- Unit tests for services and calculations
- Feature tests for API endpoints
- Architecture tests for code standards

**Test Categories**:
- **Unit Tests** (36+): Individual service calculations
  - MonteCarloSimulatorTest
  - AdequacyScorerTest
  - CoverageGapAnalyzerTest
  - IHTCalculatorTest
  - TaxConfigServiceTest
  - etc.

- **Feature Tests**: API endpoint integration
  - Authentication flow
  - CRUD operations
  - Cross-module workflows

- **Architecture Tests** (24): Code quality enforcement
  - All agents extend BaseAgent
  - All controllers extend Controller
  - All form requests extend FormRequest
  - Models use HasFactory trait
  - Services use strict typing
  - No raw DB queries in controllers

### Code Quality

- **Laravel Pint**: PSR-12 compliance checking
- **Type Safety**: Strict types declared in all files
- **Testing**: 100% passing rate
- **Architecture**: Automated architecture rule enforcement

---

## Tax Configuration System

### Database-Driven Configuration

**Active Tax Year**: 2025/26 (configurable)

**Stored Tax Values**:
- Income tax bands and rates
- National Insurance thresholds and rates
- ISA allowances (£20,000 per annum)
- Pension annual allowance (£60,000 + taper)
- Dividend tax rates and allowances
- Capital Gains Tax rates and exemptions
- IHT rates, NRB (£325k), RNRB (£175k)
- Stamp Duty Land Tax bands
- PET/CLT gifting rules and exemptions

**Access Pattern**:
```php
use App\Services\TaxConfigService;

class MyService {
    public function __construct(private TaxConfigService $taxConfig) {}
    
    public function calculate() {
        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowance = $incomeTax['personal_allowance'];
    }
}
```

**Admin Updates**:
1. Select tax year in admin panel
2. Edit all tax values
3. Activate new year (auto-deactivates previous)
4. Effective date tracking for period changes

---

## Caching Strategy

### Cache Layers

**Cache TTLs**:
- **Tax Config**: 1 hour (changes infrequently)
- **Monte Carlo Results**: 24 hours (expensive calculation)
- **Dashboard Data**: 30 minutes (frequently accessed)
- **Agent Analysis**: 1 hour (module-level insights)
- **Holistic Plan**: 24 hours (expensive cross-module)

### Cache Invalidation

**Automatic Invalidation** on:
- Data creation (new policy, account, etc.)
- Data updates (modify holdings, income, etc.)
- Data deletion (remove account, policy, etc.)

**Manual Cache Clearing**:
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## Development Stack

### Backend
- **Framework**: Laravel 10.x
- **PHP**: 8.2+
- **Database**: MySQL 8.0+
- **Cache**: Memcached 1.6+
- **Queue**: Database-backed
- **Auth**: Sanctum (token-based)
- **Testing**: Pest 2.36+
- **Formatting**: Pint (PSR-12)

### Frontend
- **Framework**: Vue.js 3 (Composition API)
- **Build Tool**: Vite 5.x
- **State**: Vuex 4.x
- **Charts**: ApexCharts 5.3+
- **HTTP**: Axios 1.6+
- **CSS**: Tailwind CSS 3.4+
- **Router**: Vue Router 4.5+

### Development Tools
- **Version Control**: Git
- **API Testing**: Postman (module collections)
- **Node.js**: 18.x+

---

## Unique Features & Innovations

### 1. Polymorphic Holdings System
- Holdings belong to either `InvestmentAccount` OR `DCPension`
- Enables shared portfolio optimization across modules
- Reduces code duplication
- Flexible asset management

### 2. Unified Form Components
- Single form used across onboarding, dashboard, editing
- Reduces maintenance burden
- Consistent UX across application
- Prevents duplicate form logic

### 3. Cross-Module ISA Tracking
- Aggregates ISAs from both Savings and Investment modules
- Enforces £20,000 annual limit
- Real-time allowance usage calculation
- Warns at 90% and 100% of limit

### 4. Second-Death IHT Calculations
- Complete married couple IHT planning
- Actuarial life expectancy projections
- Combined NRB (£650k) and RNRB (£350k)
- Future value modeling for surviving spouse

### 5. Monte Carlo Simulations
- 1,000 iterations of portfolio growth
- Year-by-year percentile projections
- Box-Muller normal distribution generation
- Contribution impact modeling
- Longevity risk assessment

### 6. Efficient Frontier Analysis
- Markowitz modern portfolio theory
- Optimal risk/return combinations
- Covariance and correlation matrices
- Asset allocation recommendations

### 7. Comprehensive Letter to Spouse
- Auto-population from all modules
- Four-part structure (immediate, accounts, planning, wishes)
- Dual spouse view (edit own, read other's)
- Emergency instructions repository

### 8. Joint Ownership with Reciprocal Records
- Automatically creates mirror records
- Tracks joint owner details
- Supports external (non-user) joint owners
- Links mortgages to properties

### 9. Spouse Account Auto-Creation
- Detects new spouse via Family Members
- Auto-sends welcome email with secure link
- Forces password change on first login
- Links accounts bidirectionally

### 10. Granular Data Sharing Permissions
- Per-module view/edit permissions
- Spouse can have selective access
- Master "data sharing enabled" switch
- Revokable permissions

---

## Performance Optimizations

### Database
- **Eager Loading**: Uses `with()` to prevent N+1 queries
- **Indexing**: Proper indexes on foreign keys
- **Caching**: Memcached for expensive calculations
- **Pagination**: Large result sets paginated

### API
- **Throttling**: Rate limiting per endpoint
- **Validation**: Server-side validation on all inputs
- **Response Compression**: Gzip enabled
- **Lazy Loading**: Vue components loaded on demand

### Frontend
- **Code Splitting**: Vue Router lazy loads pages
- **Component Lazy Loading**: Async components for heavy views
- **Store Modules**: Organized by domain
- **Service Workers**: Future PWA support

### Calculations
- **Queue Jobs**: Monte Carlo runs async
- **Batch Processing**: Large calculations batched
- **Memoization**: Expensive functions cached

---

## Deployment Considerations

### Environment Setup
- Separate `.env` for development vs. production
- Never commit `.env.production` with real values
- Use `.env.production.example` as template

### Database
- **Migrations**: Forward-only (no rollback in production)
- **Backups**: Required before any schema changes
- **Seeding**: Tax configuration must be seeded
- **Admin Account**: `admin@fps.com` / `admin123`

### Server Requirements
- PHP 8.2+ with BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML
- MySQL 8.0+ with InnoDB
- Memcached 1.6+
- Nginx or Apache with mod_rewrite
- SSL certificate (Let's Encrypt recommended)

### Queue Workers
- Supervisor configuration required for queue:work
- At least 2 workers for reliability
- Monitoring/alerting on failure

---

## Key Statistics

- **45+ Database Models** across all modules
- **80+ API Endpoints** for complete REST coverage
- **150+ Vue Components** with 100% reusability
- **6 Intelligent Agents** orchestrating analysis
- **40+ Specialized Services** for domain logic
- **15 Vuex Store Modules** managing state
- **60+ Pest Tests** with 100% pass rate
- **30+ Routes** in Vue Router
- **5 Major Modules** + Holistic Planning + Admin Panel
- **370+ Route Definitions** in api.php

---

## Current Version & Status

**Version**: v0.2.6  
**Status**: Beta - Active Development  
**Core Modules**: 100% Complete  
**Advanced Features**: 95% Complete  
**Testing**: Comprehensive Suite (60+ tests)  
**Production Ready**: Yes with security review

---

## Known Limitations & Future Enhancements

**Current Limitations**:
- Monte Carlo runs async (queue worker required)
- No real-time collaboration features yet
- Limited to UK tax rules (by design)
- No mobile app (web-responsive only)

**Future Enhancements** (Planned):
- Client portal for advisers
- Real-time recommendation engine
- Advanced scenario modeling
- Risk profiling questionnaire
- Goal tracking dashboard
- Integration with financial data aggregators

---

## Summary

Fynla is a comprehensive financial planning application that integrates five major modules through an intelligent agent-based architecture. With advanced features like Monte Carlo simulations, portfolio optimization, and holistic planning, it provides UK individuals and families with deep financial insights and actionable recommendations.

The application emphasizes code quality through:
- Strict PSR-12 compliance (Laravel Pint)
- Comprehensive test coverage (60+ tests)
- Architecture rule enforcement
- Type safety throughout
- Clear separation of concerns

The codebase is well-organized with clear patterns for:
- Form management (unified components)
- State management (Vuex modules)
- API communication (service layer)
- Business logic (agents and services)
- Data access (Eloquent models)

This makes Fynla maintainable, extensible, and ready for production deployment with ongoing enhancements.

