# 1. Side Navigation Menu

Added a collapsible left-side navigation menu that persists across all authenticated pages, replacing the previous top-navbar-only navigation.

### Features
- Expanded mode (224px): icon + label for each section
- Collapsed mode (64px): icon only with hover tooltips
- Collapse/expand state persisted in localStorage
- Mobile: hidden by default, hamburger toggle opens as full-height overlay
- Active state highlighting based on current route
- Sections: Main, Planning, Advanced, Plans & Actions, Account, Support, Admin (admin-only)

## 2. Student Preview Persona (Janice Taylor)

Added a 7th preview persona — Janice Taylor, a 21-year-old university Economics student — to showcase Fynla for younger users with minimal financial complexity.

### Persona Details
- **Name:** Janice Taylor, 21, single, female
- **Situation:** Second-year Economics student, part-time work
- **Income:** Maintenance loan + part-time job (approximately 9,000/year)
- **Savings:** Cash ISA (1,200 with Monzo)
- **Investments:** Lifetime ISA (400 with Moneybox)
- **Liabilities:** Student Loan Plan 5 (35,000 with SLC)
- **Goals:** Start LISA for first home, build emergency fund, graduate with a financial plan
- **Net Worth:** Approximately -33,000

### Student Dashboard Layout
- Recent Activity card with 12 mock transactions (wages, rent, ISA transfers, maintenance loan)
- Student Debt card (Plan 5 loan balance, interest rate, repayment threshold)
- Allowances card (LISA + ISA)
- Goals & Life Events card with chart
- Net Worth card hidden (not relevant for student)
- Optimised loading — skips protection, estate, retirement, trusts, and investment analysis modules

## 3. AI Chat Assistant (Fynla Assistant)

Added an AI-powered chat assistant ("Fynla Assistant") accessible from a floating button on all authenticated pages. The AI integrates with the existing 7-agent system to provide personalised financial guidance based on actual user data.

### Architecture
```
User types message
  -> AiChatPanel.vue (SSE stream via fetch POST)
  -> AiChatController
  -> AiChatService orchestrates:
     1. Save user message to DB
     2. Build system prompt (AiContextBuilder)
     3. Call OpenAI Chat Completions API with streaming
     4. Stream text chunks back via SSE
     5. Tool calls -> AiToolExecutor -> results back to model
     6. Save assistant message to DB
```

### 17 AI Tools

| Category | Tools |
|----------|-------|
| Navigation | `navigate_to_page` (auto-navigates user) |
| Analysis (read-only) | `get_module_analysis`, `run_what_if_scenario`, `get_recommendations`, `get_tax_information`, `generate_financial_plan` |
| Data creation (real users only) | `create_goal`, `create_life_event`, `create_savings_account`, `create_investment_account`, `create_pension`, `create_property`, `create_mortgage`, `create_protection_policy`, `create_estate_asset`, `create_estate_liability`, `create_estate_gift` |

### Simulated AI for Preview Personas
Preview users get a realistic AI-like experience without API calls. The simulated service uses pattern-based intent matching, calls real agents for actual financial data, and formats responses using templates with real numbers. Same SSE streaming format, same navigation, same conversation persistence — zero API cost for demo users.

### AI Model
- OpenAI GPT-5-mini via Chat Completions API
- Configurable per subscription tier (Pro gets higher-capability model)
- Rate limiting: Pro 20/min, Standard 10/min, Student 5/min

## 4. Financial Plans System (Complete Rebuild)

The largest workstream of this release — a comprehensive rebuild of the entire financial planning system across 6 phases with 263 implementation tasks, culminating in a full code audit that identified and fixed 25 issues.

### 4.1 Plans Foundation (28 Feb)

**Initial deployment** of the unified plan framework with 4 plan types: Investment and Savings, Protection, Retirement, and Goal Plans.

#### Standardised Plan Architecture
```
Vue View -> Vuex Store -> plansService (API) -> PlanController -> PlanService -> Agent -> Domain Services -> Models -> DB
```

Every plan follows a consistent 6-section structure:
1. **Executive Summary** — personalised letter format ("Dear David, ...")
2. **Current Situation** — module-specific data cards with detailed breakdowns
3. **Toggleable Actions** — priority-ordered recommendations with on/off toggles
4. **What-If Scenarios** — horizontal bar charts comparing current vs projected outcomes
5. **Dynamic Conclusion** — action-specific text that updates when toggles change
6. **PDF Export** — print-ready A4 format via browser print dialog

#### Protection Plan Specifics
- Rebuilt to delegate to `ComprehensiveProtectionPlanService` (same engine as the protection module)
- Coverage analysis cards: Need / Have / Gap with "How we calculated your need" breakdowns
- Life cover calculation changed from 10x multiplier to sustainable drawdown formula: Annual Income Need / 0.047 (4.7% withdrawal rate)
- Dynamic conclusion names specific actions, amounts, gap reductions, and remaining shortfalls

### 4.2 Investment Plan Enhancement (28 Feb)

Per-account fee recommendations and reactive line charts replacing the original portfolio-level horizontal bar chart.

- Fee-related actions generated per investment account (not just portfolio-level)
- Per-account growth projections with line charts comparing current fees vs reduced fees
- Projection timeframes use the user's real data — linked goal target dates or years-to-retirement
- Bar chart replaced with portfolio-level line graph
- Reactive account charts — toggling an action updates that account's chart in real time via linear interpolation

### 4.3 Retirement Plan Enhancement (28 Feb)

Per-pension grouped actions with reactive line charts and corrected income gap calculations for early retirement.

- Per-pension contribution recommendations with `scope: 'account'`
- Per-pension growth projection charts comparing current trajectory vs with-actions
- **Income gap fix for early retirement** — when target retirement age is before State Pension Age, income gap now correctly excludes State Pension. Two-phase gap reported:
  - Gap at retirement (pensions only, no state pension)
  - Gap after State Pension Age (when state pension kicks in)
- ContributionOptimizer fix — no longer subtracts state pension from target income when retiring before State Pension Age
- What-if projection fix — additional contributions added on top of already-projected value (not re-projected from scratch)
- Terminology changed from "Defined Contribution" to "Pension" in all user-facing text

### 4.4 Estate Plan (28 Feb)

New Estate Plan at `/plans/estate` with IHT mitigation strategies and reactive what-if scenarios.

- Gate conditions: plan only generates if user is age 35+ AND has an IHT liability greater than 0
- Strategies: charitable bequest, life cover, annual gifting, PET, and CLT
- Current situation sections: Estate Value, Inheritance Tax, Asset Breakdown (liquid/semi-liquid/illiquid), Life Cover (in trust/not in trust), Charitable Giving (status vs 10% threshold)
- Reactive what-if: toggling actions updates projected IHT liability using frontend calculation parameters

### 4.5 Phase 1-2: Foundation and Core Refactors (1 Mar)

Eliminated all hardcoded constants and integrated real user financial data into plan recommendations.

#### PlanConfigService (New)
Admin-configurable service replacing 16+ hardcoded values across plan services:

| Configuration | Previous (Hardcoded) | New (Admin-Configurable) |
|---------------|---------------------|--------------------------|
| Default growth rate | 0.05 | `getDefaultGrowthRate()` |
| Withdrawal rate | 0.04 | `getWithdrawalRate()` |
| Platform fee benchmark | 0.25% | `getPlatformFeeBenchmark()` |
| OCF benchmark | 0.15% | `getOCFBenchmark()` |
| Estate age gate | 35 | `getEstateAgeGate()` |
| Plan cache TTL | 1800s | `getPlanCacheTTL()` |
| Consolidation efficiency gain | 2% | `getConsolidationEfficiencyGain()` |
| Tax optimisation gain | 3% | `getTaxOptimisationGain()` |
| Default action gain | 1% | `getDefaultActionGain()` |
| Optimised growth rate | 0.06 | `getOptimisedGrowthRate()` |

Backed by `plan_configurations` database table with `PlanConfigurationSeeder` for defaults.

#### DisposableIncomeAccessor and DistributionAccount (New)
- `DisposableIncomeAccessor` — fetches the user's actual disposable income from existing income data (does not recalculate)
- `DistributionAccount` — in-memory allocation tracker initialised with real disposable income; agents draw from it to prevent double-counting and exceeding affordability
- Integrated into InvestmentPlanService, RetirementPlanService, and GoalPlanService

#### Emergency Fund Surplus Logic (New)
When emergency fund exceeds 6 months of expenditure AND surplus cash is not allocated to goals, the plan recommends moving excess into tax-efficient wrappers in priority order:
1. ISA (if allowance available)
2. Pension (if annual allowance available)
3. Bond wrapper
4. Gifting

#### Projection Horizon Fix
Removed the `?? 10` year default fallback from all projection calculations. Projections now use:
- Goal target date (if a goal is linked)
- Years to retirement (fallback)
- Missing retirement date triggers a data completeness warning

#### Recommendation Wording Changes
- "Complete Your Risk Profile" changed to ask for the specific missing information (time horizon, capacity for loss, risk tolerance)
- "Add Your Holdings" changed to "Defaulting to risk-based fee-optimised allocations" with explanation

#### Legacy Plans Removal
Deleted 11 files (controllers, services, models, views) and removed all associated API and frontend routes:
- `InvestmentSavingsPlanController`, `InvestmentPlanController`, `InvestmentRecommendationController`
- `InvestmentSavingsPlanService`, `InvestmentPlanGenerator`
- `InvestmentPlan` model, `InvestmentRecommendation` model
- `InvestmentSavingsPlan.vue`, `ComprehensiveProtectionPlan.vue`, `ComprehensiveEstatePlan.vue`, `InvestmentSavingsPlanView.vue`

### 4.6 Phase 3: Goal Integration (1 Mar)

Goals are now integrated into module plans. Each goal linked to a savings, investment, or retirement account appears in the corresponding plan with progress tracking.

#### Backend
- `BasePlanService` — new methods: `getGoalsForPlan()`, `formatGoalForPlan()`, `buildGoalRecommendations()`
- Goal-sourced recommendations appear FIRST in the actions list (highest priority)
- Recommendations generated for: no contribution set, behind schedule, approaching deadline
- On-track goals generate no recommendation

#### Frontend
- **PlanGoalSection.vue** (new shared component) — displays linked goals with progress bars, status badges, months remaining, and monthly contribution info
- Unlinked goals show a prompt directing users to allocate an account at `/goals`
- Integrated into InvestmentPlanContent.vue and RetirementPlanContent.vue

### 4.7 Phase 4: Estate Plan Refactor (1 Mar)

Major refactor to eliminate redundant calculations, add joint views, and enrich guidance.

- **Fetch from module** — uses cached EstateAgent analysis instead of recalculating
- **Joint estate view** — married users with spouse data see a side-by-side estate position (`EstateJointView.vue`)
- **Funding sources** — charitable and gifting recommendations show which accounts the money comes from (`identifyFundingSource()`)
- **Affordability checks** — life cover recommendations checked against 15% of disposable income
- **Step-by-step guidance** — `buildActionGuidance()` provides actionable instructions for each strategy
- **Estate health score removed** — replaced with descriptive text per Rule 12

### 4.8 Phase 5: Holistic Plan Refactor (1 Mar)

Rebuilt the holistic plan from a 7-tab layout to a flowing vertical page, fixed the backend data pipeline, and integrated estate and goals data.

#### Layout Change
Tabs removed entirely. All sections stacked vertically matching the pattern used by all other plans:
1. Executive Summary (health status, strengths, vulnerabilities, priorities)
2. Financial Snapshot (new: net worth, assets, liabilities, monthly cash flow)
3. Module Summaries (6 module cards in a grid)
4. Prioritised Recommendations (all cross-module actions by timeline)
5. Cash Flow Allocation Chart (surplus allocation with chart and table)
6. Net Worth Projection Chart (baseline vs optimised)
7. Risk Assessment (risk level, areas, mitigation)
8. Conflicts Alert (only shown if detected)
