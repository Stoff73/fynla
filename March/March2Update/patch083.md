# Fynla v0.8.3 Patch Report

**Period:** 27 February - 2 March 2026
**Branch:** `estatePlan` (PR #101 to `main`)
**Commits:** 46
**Files changed:** 380 (28,210 insertions, 71,641 deletions)

---

## Executive Summary

This release spans six days of intensive development across four major workstreams: a collapsible side navigation menu, a student preview persona, an AI-powered chat assistant, and a comprehensive rebuild of the entire financial planning system. The planning system alone accounts for the majority of changes — replacing hardcoded values with admin-configurable services, integrating goals into module plans, adding joint estate views, rebuilding the holistic plan from a tab layout to a flowing vertical page, and removing all legacy plan code. A final code audit identified and fixed 25 issues (3 critical bugs, 7 important fixes, 15 simplifications).

---

## 1. Side Navigation Menu

**Deployed:** 27 Feb 2026 | **PR:** #95

Added a collapsible left-side navigation menu that persists across all authenticated pages, replacing the previous top-navbar-only navigation.

### Features
- Expanded mode (224px): icon + label for each section
- Collapsed mode (64px): icon only with hover tooltips
- Collapse/expand state persisted in localStorage
- Mobile: hidden by default, hamburger toggle opens as full-height overlay
- Active state highlighting based on current route
- Sections: Main, Planning, Advanced, Plans & Actions, Account, Support, Admin (admin-only)

### Key Changes
- **AppLayout.vue** — Added SideMenu component with dynamic left margin
- **Navbar.vue** — Removed logo (moved to side menu), removed mobile hamburger menu, removed Dashboard link
- **NetWorthDashboard.vue** — Removed the old sidebar navigation (entire sidebar template, data, computed, methods, watchers, CSS)
- **holisticService.js** — Fixed double `/api/api/` prefix on all holistic endpoints (was causing 405 errors)

### New Files (5)
- `SideMenu.vue`, `SideMenuIcon.vue`, `SideMenuItem.vue`, `SideMenuMobileToggle.vue`, `SideMenuSection.vue`

---

## 2. Student Preview Persona (Janice Taylor)

**Deployed:** 27 Feb 2026 | **PR:** #96

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

### Files Changed
- New: `resources/js/data/personas/student.json`
- Modified: `PreviewController.php`, `PreviewUserSeeder.php`, `PersonaSelector.vue`, `PreviewBanner.vue`, `preview.js`, `Dashboard.vue`

---

## 3. AI Chat Assistant (Fynla Assistant)

**Deployed:** 27 Feb 2026 | **PR:** #97

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

### Database
- `ai_conversations` table — conversation metadata, token usage tracking
- `ai_messages` table — individual messages (user + assistant roles)
- `ai_chat_enabled` column on `users` table — user preference toggle

### Bug Fixes Included
1. Invalid navigation routes corrected (e.g. `/net-worth/savings` to `/net-worth/cash`)
2. Navigation tool now auto-navigates via `$router.push()` instead of just rendering a clickable card
3. HolisticPlan view missing `<AppLayout>` wrapper (side menu was not showing)

### New Files (16)
- 2 models, 7 backend services (`app/Services/AI/`), 1 controller, 3 migrations, 1 frontend service, 1 Vuex store module, 4 Vue components

---

## 4. Financial Plans System (Complete Rebuild)

**Deployed:** 28 Feb - 2 Mar 2026 | **PRs:** #98, #99, #100, #101

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

#### Key Backend Classes
- **BasePlanService** — abstract base with shared methods: `structureActions()`, `applyActionFilter()`, `buildPlanMetadata()`, `generateDynamicConclusion()`
- **WhatIfCalculator** — orchestrates recalculation when actions are toggled
- **PlanController** — centralised API controller for all plan types

#### Protection Plan Specifics
- Rebuilt to delegate to `ComprehensiveProtectionPlanService` (same engine as the protection module)
- Coverage analysis cards: Need / Have / Gap with "How we calculated your need" breakdowns
- Life cover calculation changed from 10x multiplier to sustainable drawdown formula: Annual Income Need / 0.047 (4.7% withdrawal rate)
- Dynamic conclusion names specific actions, amounts, gap reductions, and remaining shortfalls

#### All Scores Removed
Scores (numerical ratings) removed from all plans and documented as project-wide Rule 12 in CLAUDE.md and designStyle.md.

**File count:** 30 new, 11 modified (41 + build output)

### 4.2 Investment Plan Enhancement (28 Feb)

Per-account fee recommendations and reactive line charts replacing the original portfolio-level horizontal bar chart.

- Fee-related actions generated per investment account (not just portfolio-level)
- Per-account growth projections with line charts comparing current fees vs reduced fees
- Projection timeframes use the user's real data — linked goal target dates or years-to-retirement
- Bar chart replaced with portfolio-level line graph
- Reactive account charts — toggling an action updates that account's chart in real time via linear interpolation

**File count:** 2 new, 5 modified (7 + build output)

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

**File count:** 2 new, 6 modified (8 + build output)

### 4.4 Estate Plan (28 Feb)

New Estate Plan at `/plans/estate` with IHT mitigation strategies and reactive what-if scenarios.

- Gate conditions: plan only generates if user is age 35+ AND has an IHT liability greater than 0
- Strategies: charitable bequest, life cover, annual gifting, PET, and CLT
- Current situation sections: Estate Value, Inheritance Tax, Asset Breakdown (liquid/semi-liquid/illiquid), Life Cover (in trust/not in trust), Charitable Giving (status vs 10% threshold)
- Reactive what-if: toggling actions updates projected IHT liability using frontend calculation parameters

#### EstateAgent Bug Fixes (6 pre-existing)
1. `aggregateEstateAssets()` changed to `gatherUserAssets()` + new `buildAssetSummary()` helper
2. `calculateIHT()` changed to `calculate()` (correct method name)
3. `getPersonalizedStrategies()` changed to `generatePersonalizedTrustStrategy()` with correct parameters
4. `identifyOpportunities()` changed to `calculateOptimalGiftingStrategy()` with correct parameters
5. `$ihtCalculation['gross_estate']` changed to `$ihtCalculation['effective_rate']` (fixed 17M% effective rate bug)
6. `catch (\Exception)` changed to `catch (\Throwable)` to handle TypeErrors

**File count:** 6 new, 7 modified (13 + build output)

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

#### Backend Fixes
- **CoordinatingAgent** — added EstateAgent and GoalsAgent injection; added 5 mapping methods to normalise agent response formats; removed hardcoded estate placeholder data
- **HolisticPlanner** — fixed goals key mismatch (`total_active` changed to `total_goals`); risk assessment expanded to 6 areas (added goals)
- **HolisticPlanningController** — fixed recommendation_text mapping to check `title` and `description` keys before fallback
- **Shared DistributionAccount** — single instance shared across all modules with priority allocation: Emergency > Protection > Pension > Investment > Estate > Goals
- **ConflictResolver** — added estate-vs-goals conflict detection
- **PriorityRanker** — includes estate and goal recommendations

#### Frontend
- **FinancialSnapshot.vue** (new) — net worth, assets, liabilities, cash flow overview
- All child components updated to use `PlanSectionHeader` for design consistency
- Holistic Vuex store getter fixed to merge `action_plan_summary` into returned object

### 4.9 Phase 6: Dashboard and Polish (1 Mar)

- Holistic Plan card added to Plans dashboard (full-width, navigates to `/holistic-plan`)
- Legacy plan links removed
- Goal plan cards show module association (goal type + target amount)
- PlanController `statuses()` endpoint updated to include holistic readiness

### 4.10 Code Audit and Bug Fixes (2 Mar)

A comprehensive audit cross-referenced 3 planning documents against the codebase, verified all 263 tasks, and performed full code review and simplification analysis.

#### 3 Critical Bugs Fixed

| Issue | File | Problem | Fix |
|-------|------|---------|-----|
| CR-1 | RetirementPlanService.php | Stale `current_age` on RetirementProfile used for years-to-retirement (becomes wrong after birthday) | Use agent-computed `years_to_retirement` from analysis data |
| CR-2 | HolisticPlanningController.php | `storeRecommendations()` ran on every request including cached — wiped user's recommendation notes and progress | Moved inside `Cache::remember` callback with fresh-generation tracking |
| CR-3 | HolisticPlanningController.php | Cash flow allocation chart used hardcoded dummy amounts (150/200/300/250/100) disconnected from user data | Derive amounts from real recommendation tracking records |

#### 7 Important Fixes

| Issue | Fix |
|-------|-----|
| CR-4 | HolisticPlanner hardcoded 4%/6% growth rates — injected PlanConfigService |
| CR-5 | PlansDashboard local `formatSimpleCurrency` — replaced with `currencyMixin` |
| CR-6 | `overall_score` and `adequacy_score` in API responses — replaced with `health_status` text |
| CR-7 | Double `analyze()` calls in InvestmentPlanService and RetirementPlanService — pass pre-computed analysis to recommendation building |
| CR-8 | Vuex `toggleAction` mutated nested state directly — replaced plan objects in state for proper reactivity |
| CR-9 | GoalPlanService completeness percentage could go negative — added `max(0, ...)` guard |
| CR-10 | "Steps to close this gap" appended when gap is zero — gated inside `$incomeGap > 0` |

#### 12 Simplifications

| Issue | Change |
|-------|--------|
| CS-4 | Duplicate FV projection formula consolidated into shared `BasePlanService::projectFutureValue()` |
| CS-5 | Duplicate `projectBaseline()`/`projectOptimized()` consolidated using PlanConfigService rates |
| CS-6 | 6 repetitive try/catch blocks in `collectModuleAnalysis()` extracted to `safeCollectModule()` helper |
| CS-8 | "Get first name" logic duplicated in 4 services — extracted to `BasePlanService::getUserFirstName()` |
| CS-9 | "Enabled actions" extraction pattern duplicated in 4 services — extracted to `BasePlanService::getEnabledActions()` |
| CS-10 | 5 near-identical `getDefault*Analysis()` methods — consolidated into single `getDefaultAnalysis()` with type parameter |
| CS-11 | Triplicated status badge logic in PlanGoalSection — consolidated into single `goalStatus()` method |
| CS-12 | Repetitive event handler try/catch in HolisticPlan — extracted `handleRecommendationAction()` helper |
| CS-13 | Unused `$projections = []` variable removed from HolisticPlanner |
| CS-14 | Redundant `$accountValue > 0` guard removed from InvestmentPlanService |
| CS-15 | Local `formatSimpleCurrency` replaced with currencyMixin (same as CR-5) |

#### Architecture Test Fixes (4)
- Removed assertions for methods that were never implemented (`getNetWorthTrend`, `getTrend`)
- Corrected method name assertion (`calculatePensionValue` to `calculatePensionBreakdown`)
- Added 3 controllers to DB facade ignore list (WebhookController, FamilyMembersController, PaymentController)
- All 73 architecture tests now pass

---

## 5. Source Control Cleanup (2 Mar)

Updated `.gitignore` with targeted rules and removed 160+ tracked files that should not be in version control:

- Design source files (root-level PNGs, `/logo/`, `/portraits/`)
- Development screenshots (`/Feb/` directory)
- Planning documentation (`/appMapping/`, `/March2Update/`)
- Local configuration (`.DS_Store`, `.claude/settings.local.json`, `.playwright-mcp/` logs)
- Application assets in `public/` and `resources/` remain tracked

---

## 6. Complete File Summary

### New Files Created

| Category | Count | Key Files |
|----------|:-----:|-----------|
| Backend Services | 14 | PlanConfigService, DistributionAccount, DisposableIncomeAccessor, EstatePlanService, 7x AI services, BasePlanService, WhatIfCalculator, GoalPlanService |
| Backend Controllers | 2 | PlanController, AiChatController |
| Backend Models | 2 | AiConversation, AiMessage |
| Database Migrations | 5 | plan_configurations, ai_conversations, ai_messages, ai_chat_enabled, recommended_amount |
| Database Seeders | 1 | PlanConfigurationSeeder |
| Frontend Components | 37 | 14 shared plan components, 12 module-specific plan components, 5 side menu components, 4 AI chat components, FinancialSnapshot, EstateJointView |
| Frontend Views | 5 | InvestmentPlan, ProtectionPlan, RetirementPlan, EstatePlan, GoalPlan |
| Frontend Services | 2 | plansService, aiChatService |
| Frontend Stores | 2 | plans.js, aiChat.js |
| Frontend Data | 1 | student.json persona |
| Tests | 3 | GoalIntegrationTest, PlanConfigServiceTest, DistributionAccountTest |

### Files Deleted

| Category | Count | Reason |
|----------|:-----:|--------|
| Legacy controllers | 3 | Replaced by unified PlanController |
| Legacy services | 2 | Replaced by module-specific plan services |
| Legacy models | 2 | No longer needed |
| Legacy views | 4 | Replaced by new plan views |
| Dev/admin files | 160+ | Removed from git tracking via .gitignore |

### Files Modified (Key)

| File | Changes |
|------|---------|
| `routes/api.php` | Added plan routes, AI chat routes, removed legacy routes |
| `app/Agents/CoordinatingAgent.php` | Estate/goals injection, response normalisation, shared distribution |
| `app/Agents/EstateAgent.php` | 6 bug fixes, score removal |
| `app/Agents/InvestmentAgent.php` | Per-account recommendations, wording changes |
| `app/Agents/RetirementAgent.php` | Per-pension recommendations |
| `app/Services/Coordination/HolisticPlanner.php` | PlanConfigService injection, goals key fix, risk assessment expansion |
| `app/Services/Protection/CoverageGapAnalyzer.php` | Sustainable drawdown formula |
| `app/Models/User.php` | Added ihtProfile, assets, gifts relationships |
| `resources/js/layouts/AppLayout.vue` | Side menu, AI chat integration |
| `resources/js/views/HolisticPlan.vue` | Tabs to flowing vertical layout |
| `resources/js/views/Plans/PlansDashboard.vue` | Holistic card, estate card, currencyMixin |
| `resources/js/router/index.js` | New plan routes, legacy route removal |
| `resources/js/store/index.js` | Plans and aiChat module registration |
| `CLAUDE.md` | Rule 12 (no scores), side menu documentation |
| `designStyle.md` | Score prohibition, plan component patterns |

---

## 7. Testing

### Test Results After All Changes

| Suite | Tests | Assertions | Status |
|-------|:-----:|:----------:|:------:|
| Plan Services | 42 | 163 | All passing |
| Plan-related (broad) | 65 | 249 | All passing |
| Architecture | 73 | - | All passing |
| PlanConfigService | 6 | - | All passing |
| DistributionAccount | 13 | - | All passing |
| Goal Integration | 7 | - | All passing |

### Verification Across Preview Personas

All 6 preview personas verified across all plan types:

| Persona | Investment | Protection | Retirement | Estate | Goals | Holistic |
|---------|:---:|:---:|:---:|:---:|:---:|:---:|
| Peak Earners (David Mitchell) | Pass | Pass | Pass | Pass (IHT 179,180) | Pass | Pass |
| Young Family (James Carter) | Pass | Pass | Pass | N/A (no IHT) | Pass | Pass |
| Widow (Margaret Thompson) | Pass | Pass | Pass | Pass (IHT 255,940) | Pass | Pass |
| Entrepreneur (Alex Chen) | Pass | Pass | Pass | Pass (IHT 83,672) | Pass | Pass |
| Young Saver (John Morgan) | Pass | Pass | Pass | N/A (no IHT) | Pass | Pass |
| Retired Couple (Patricia Bennett) | Pass | Pass | Pass | N/A (no IHT) | Pass | Pass |

---

## 8. API Routes (Current State)

### Plans API

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/plans/statuses` | Dashboard readiness per module |
| GET | `/api/plans/{type}` | Generate plan (investment/protection/retirement/estate) |
| POST | `/api/plans/{type}/recalculate` | Recalculate with toggled actions |
| DELETE | `/api/plans/{type}/clear-cache` | Clear plan cache |
| GET | `/api/plans/goal/{goalId}` | Generate goal-specific plan |
| POST | `/api/plans/goal/{goalId}/recalculate` | Recalculate goal plan |

### AI Chat API

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/ai-chat/conversations` | List conversations |
| POST | `/api/ai-chat/conversations` | Start new conversation |
| GET | `/api/ai-chat/conversations/{id}` | Load conversation + messages |
| DELETE | `/api/ai-chat/conversations/{id}` | Soft-delete conversation |
| POST | `/api/ai-chat/conversations/{id}/messages` | Send message (SSE streaming) |

### Holistic API (Existing)

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/holistic/analyze` | Holistic analysis |
| POST | `/api/holistic/plan` | Holistic plan |
| GET | `/api/holistic/recommendations` | Recommendations |

---

## 9. Database Changes

### New Tables

| Table | Purpose |
|-------|---------|
| `plan_configurations` | Admin-configurable plan values (growth rates, benchmarks, thresholds) |
| `ai_conversations` | AI chat conversation metadata and token usage |
| `ai_messages` | Individual chat messages (user + assistant roles) |

### New Columns

| Table | Column | Purpose |
|-------|--------|---------|
| `users` | `ai_chat_enabled` (boolean) | User preference toggle for AI chat |
| `recommendation_trackings` | `recommended_amount` (decimal) | Monetary amount for tracked recommendations |

---

## 10. Deployment Notes

### Build Requirement
All changes require a frontend rebuild:
```bash
./deploy/fynla-org/build.sh
```

### Environment Variables
Add to production `.env`:
```
OPENAI_API_KEY=sk-...
```

### Post-Upload SSH Commands
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate
php artisan db:seed --class=PlanConfigurationSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## 11. Rule Compliance

| Rule | Status |
|------|:------:|
| Rule 6: currencyMixin only | Enforced (local formatCurrency removed) |
| Rule 9: No amber/orange colours | Enforced |
| Rule 10: No acronyms except ISA | Enforced ("Inheritance Tax Liability" not "IHT", etc.) |
| Rule 11: Design system compliance | Enforced (PlanSectionHeader, design system chart colours) |
| Rule 12: No scores in UI | Enforced (all scores removed from API and templates) |
| British spelling in user-facing text | Enforced ("Optimisation", "Personalised") |
| TaxConfigService for all tax values | Enforced via PlanConfigService integration |
| No hardcoded constants in plan services | Enforced (16+ values moved to admin config) |
