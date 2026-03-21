# Goals, Life Events & What-If Scenario System — Design Spec

**Date:** 21 March 2026
**Status:** Approved
**Scope:** Three sub-projects delivering full goals/life events integration across all modules, plus a new AI-driven What-If Scenario dashboard

---

## Overview

The Goals and Life Events systems are well-integrated into Investment, Coordination, and Projections, but have gaps in Savings, Protection, Estate, and Retirement modules. Additionally, users have no way to explore "what if" scenarios persistently through Fyn.

This spec covers three sub-projects:

1. **What-If Scenario System** — Fyn builds persistent, living what-if comparisons across all affected modules
2. **Goals Integration Into Remaining Modules** — Wire goals into Savings, Protection, Estate, and Retirement recommendation pipelines
3. **Life Events Recommendation Expansion** — Extend life event-driven recommendations beyond Investment to all modules

---

## Sub-Project 1: What-If Scenario System

### Data Model

New `what_if_scenarios` table:

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | |
| `user_id` | FK → users (cascadeOnDelete) | Owner |
| `name` | string | Display name (e.g. "Retire at 55") — auto-generated or user-edited |
| `scenario_type` | enum | retirement, property, family, income, custom |
| `parameters` | JSON | The what-if inputs: `{ "retirement_age": 55, "sell_property_id": 12 }` |
| `affected_modules` | JSON | Which modules were impacted: `["retirement", "investment", "estate"]` |
| `created_via` | enum | ai_chat, manual |
| `ai_narrative` | text (nullable) | Fyn's summary of the scenario for redisplay |
| `timestamps` | | created_at, updated_at |
| `softDeletes` | | deleted_at |

**Indexes:** `$table->index(['user_id', 'created_at'])`

**Joint ownership:** Scenarios belong to the user who created them (`user_id`). When calculating the comparison, `WhatIfScenarioService` queries assets/liabilities using the standard `forUserOrJoint()` scope — so the scenario reflects the household position regardless of which partner created it. No `joint_owner_id` column needed on the scenario itself.

**Model traits:** `SoftDeletes`, `Auditable`

No snapshot data stored. The comparison is always recalculated live from `parameters` against current user data (living comparison).

### Backend

**`WhatIfScenarioService`** — New service that:

1. Takes scenario parameters and identifies affected modules based on parameter types
2. Calls each affected module agent's `buildScenarios(userId, parameters)` method with **current inputs** (the "Now" column)
3. Calls the same method with **modified inputs** (the "What If" column) using parameter overrides
4. Calculates deltas between the two states
5. Returns structured comparison:

```json
{
  "scenario": { "id": 1, "name": "Retire at 55", "type": "retirement" },
  "affected_modules": ["retirement", "investment", "estate"],
  "current": {
    "retirement": { "projected_income": 39738, "capital_at_retirement": 845479 },
    "estate": { "iht_liability": 291512 }
  },
  "what_if": {
    "retirement": { "projected_income": 31500, "capital_at_retirement": 620000 },
    "estate": { "iht_liability": 246000 }
  },
  "deltas": {
    "retirement": { "projected_income": -8238, "capital_at_retirement": -225479 },
    "estate": { "iht_liability": -45512 }
  },
  "ai_narrative": "Retiring 5 years earlier reduces your projected retirement income by £8,238 per year..."
}
```

**How overrides work:** Agents always use the user's stored data — no override contract needed. The `WhatIfScenarioService` creates a transient copy of the user model (and relevant related models) with the scenario parameters applied (e.g. `retirement_age = 55`), then runs the standard `analyze()` calls against this copy. The "Now" column runs `analyze()` against the real user. The "What If" column runs `analyze()` against the modified copy. The agents don't know they're being used for a what-if — they just see a user with different data. This keeps the agents simple and avoids any new contracts or abstract methods.

**Affected module detection:** Based on scenario parameter types:

- `retirement_age`, `pension_contribution` → retirement, investment, estate
- `sell_property`, `buy_property` → property, estate, savings, retirement
- `divorce`, `marriage`, `new_child` → protection, estate, retirement, savings
- `income_change`, `job_loss` → income, protection, savings, retirement
- `inheritance` → estate, savings, investment

These map to the `life_events.event_type` enum which is extended in Sub-Project 3 to include `divorce`, `marriage`, `new_child`, `job_loss`, `income_change`.

### AI Tool

The existing `run_what_if_scenario` tool in `AiToolDefinitions.php` is **replaced** by `create_what_if_scenario`. The old tool runs single-module ephemeral calculations via `CoordinatingAgent::handleWhatIfScenario()`. The new tool creates persistent, multi-module scenarios via `WhatIfScenarioService`. The `handleWhatIfScenario()` method in `CoordinatingAgent` is updated to route to `WhatIfScenarioService` instead of calling agent `buildScenarios()` directly.

**`create_what_if_scenario`**
- Input: `description` (string — Fyn's parsed understanding), `parameters` (object — structured overrides), `name` (string — short display name)
- Output: Full comparison result + `scenario_id` + `action: 'navigate'` + `route_path: '/planning/what-if?scenario={id}'`
- Frontend auto-navigation handled by existing `pendingNavigation` watcher in `AiChatPanel.vue`

**Preview user handling:** The `create_what_if_scenario` tool is excluded from `getTools()` when `isPreviewMode` is true. Preview users cannot create scenarios via Fyn.

**AI behaviour (hybrid autonomy):**
- **Simple single-factor changes** (e.g. "What if I retire at 55?"): Fyn interprets and generates immediately, narrates results
- **Complex multi-factor scenarios** (e.g. "What if I get divorced and sell the house?"): Fyn confirms key assumptions first ("I'll model: divorce settlement 50/50 asset split, property sale at current value £500k, single income from employment only — sound right?"), then generates

After generation, Fyn narrates the headline impacts in chat, then the frontend auto-navigates to `/planning/what-if?scenario={id}` via the existing `pendingNavigation` mechanism in `AiChatPanel.vue`.

### Frontend

**Route:** `/planning/what-if` — the existing route currently points to `WhatIfScenarios.vue` which renders only a "Death of Spouse" scenario. This existing view is renamed to `DeathOfSpouseScenario.vue` and moved to a child route (`/planning/what-if/death-of-spouse`). The parent route `/planning/what-if` is updated to point to the new `WhatIfDashboard.vue`.

**Scenario List View** (default when no scenario selected):
- Cards in a grid showing each saved scenario
- Each card shows: name, date created, headline metric delta (e.g. "Retirement income -£8,200/year"), number of affected modules
- No icons on cards

**Scenario Detail View** (when viewing a specific scenario):
- Scenario list as a sidebar panel on the left, detail on the right (matching Plans/Journeys pattern — scales beyond 5+ scenarios)
- Active scenario name with edit (rename) and delete options
- Fyn's AI narrative displayed at the top as a summary card
- Below: affected modules as expandable sections, each showing:
  - "Now" column | "What If" column | Delta column
  - Key metrics per module
  - Delta indicators: spring for improvements, raspberry for deteriorations
- Recalculated on each page load (living comparison)

**Empty State:**
- Fyn proactively asks "What scenario can I help you with?" in the docked chat
- For preview/persona users: the empty state CTA is greyed out with "Register to use" on mouseover

**Sidebar:**
- Single link: "What If Scenarios" under the Planning group (near Plans, Journeys)
- Count badge populated via a lightweight `GET /api/what-if-scenarios/count` endpoint (avoids loading all scenarios on app boot)

**Vuex Store:** `resources/js/store/modules/whatIf.js` (namespace: `whatIf`)
- State: `scenarios[]`, `activeScenarioId`, `comparisonData`, `loading`
- Actions: `fetchScenarios`, `fetchScenarioComparison(id)`, `deleteScenario(id)`, `renameScenario(id, name)`, `fetchCount`
- Getters: `activeScenario`, `scenarioCount`

### API Endpoints

| Method | Route | Controller Method | Purpose |
|--------|-------|-------------------|---------|
| GET | `/api/what-if-scenarios` | `index` | List user's scenarios |
| GET | `/api/what-if-scenarios/count` | `count` | Sidebar badge count |
| GET | `/api/what-if-scenarios/{id}` | `show` | Scenario detail + live comparison |
| POST | `/api/what-if-scenarios` | `store` | Create (from AI or manual) |
| PUT | `/api/what-if-scenarios/{id}` | `update` | Rename |
| DELETE | `/api/what-if-scenarios/{id}` | `destroy` | Soft delete |

**Form Request:** `StoreWhatIfScenarioRequest.php` — validates `name` (required string), `scenario_type` (enum), `parameters` (required JSON object), `affected_modules` (array of valid module names).

**API Resource:** `WhatIfScenarioResource.php` — transforms model for API output.

### Preview Persona Seeder

`WhatIfScenarioSeeder` seeds 2-3 example scenarios for preview personas (e.g. "Retire at 55" for peak_earners, "Downsize Property" for retired_couple) so the What-If page is not empty in demo mode. These use `created_via: 'manual'` and have a pre-written `ai_narrative`.

---

## Sub-Project 2: Goals Integration Into Remaining Modules

Lightweight wiring of existing `GoalStrategyService` and goal data into module agents that currently don't reference goals, plus goal contribution input with AI validation.

**Dependency note:** This sub-project should be completed before Sub-Project 1 as the goals-aware recommendations inform the What-If comparison narrative.

### Goal Contribution Input

Users can enter a monthly contribution amount for each goal. Fyn validates the contribution:
- **Too low:** "At £200/month you'll reach only 60% of your £50,000 target by the deadline. Consider increasing to £350/month to stay on track"
- **Too high:** "£800/month towards this goal would use 45% of your disposable income. Consider reducing to £500/month to maintain a comfortable buffer"
- **Just right:** "£350/month puts you on track to reach your £50,000 target 2 months early"

Validation uses `GoalAffordabilityService` (existing) for the disposable income check and `GoalProgressService` (existing) for the on-track calculation. The AI tool `create_goal` is enhanced to accept `monthly_contribution` and return Fyn's assessment. The Goals UI (`GoalCard.vue`) adds a contribution input field that saves via the existing `updateGoal` API.

### Savings Module

**SavingsAgent** enhancements:
- Query active goals linked to savings accounts. If a savings-linked goal is behind schedule, recommend increasing contributions to that specific account with the shortfall amount
- Emergency fund goal detection — if user has no emergency fund goal but `EmergencyFundCalculator` shows insufficient runway, auto-suggest creating one
- Life events approaching within 12 months that need cash reserves trigger "Build a cash buffer for [event name]" recommendations

### Protection Module

**ProtectionAgent** enhancements:
- When calculating coverage needs for users with dependants, factor in active goal commitments. The family needs enough cover to fund both living costs AND outstanding goal targets
- Example: User has £45,000 education goal + £200,000 property goal = £245,000 additional coverage consideration
- Life events like `redundancy_payment`, `wedding`, `new_child` referenced in protection gap analysis narrative text

### Estate Module

**EstateAgent** enhancements:
- Goals with large target amounts flag liquidity risk — "You have £200,000 in active goal commitments. Ensure your estate structure provides sufficient liquidity to meet these if needed"
- Life events (inheritance, property sale) already used for IHT impact — enhance narrative to cross-reference with goal funding ("Incoming inheritance of £200k could fund your Pre-Pension Bridge Fund goal")

### Retirement Income Planning

**RetirementAgent** enhancements:
- Validate retirement income target against active goal funding needs — "Your retirement income target of £35,000/year may need to increase by £3,000/year to continue funding your 2 active goals post-retirement"
- Goals with target dates post-retirement included in drawdown modelling as additional expenditure items
- Surface in recommendations when goal commitments conflict with sustainable drawdown rate

---

## Sub-Project 3: Life Events Recommendation Expansion

Currently `LifeEventAssessmentService` only feeds into Investment module recommendations. This extends it to all modules.

### New Life Event Types Migration

Add 5 new event types to the `life_events.event_type` enum:

| New Type | Category | Primary Module |
|----------|----------|----------------|
| `divorce` | Family change | Estate |
| `marriage` | Family change | Protection |
| `new_child` | Family change | Protection |
| `job_loss` | Income change | Protection |
| `income_change` | Income change | Savings |

Update `LifeEventIntegrationService::EVENT_MODULE_MAP` with the new types.

### Module-Specific Life Event Responses

**Savings:**
- Upcoming expense events within 24 months → "Build a cash reserve of £X for [event name]"
- Income events (`inheritance`, `bonus`, `gift_received`) → "Consider allocating to [goal name] or building emergency reserves"

**Protection:**
- Life-change events (`marriage`, `divorce`, `new_child`, `redundancy_payment`) → "Your circumstances have changed — review your protection cover"
- `job_loss` / `redundancy_payment` event → "Consider income protection — you have no replacement income if unable to work"
- `new_child` → "Review life cover — your family's needs have increased"

**Estate:**
- `inheritance` events → "Incoming inheritance may increase your estate above the nil rate band — review IHT position"
- `property_sale` / `business_sale` → "Potential Capital Gains Tax liability — review estate structure"

**Retirement:**
- `pension_lump_sum` events → "Consider how this affects your drawdown strategy and tax position"
- Early retirement events → "Bridge fund needed — £X shortfall between retirement and state pension age"

Note: Event types above include both existing enum values (`inheritance`, `bonus`, `redundancy_payment`, `pension_lump_sum`, `property_sale`, `business_sale`, `wedding`) and new ones added by this sub-project's migration (`divorce`, `marriage`, `new_child`, `job_loss`, `income_change`).

### Triggering Mechanism

The existing `LifeEventMonteCarloObserver` fires on life event changes and clears projection caches. This is extended to also clear recommendation caches for affected modules (using the existing `LifeEventIntegrationService::EVENT_MODULE_MAP`). This is cache invalidation only — the next time a user loads a module page, the agent's `analyze()` call regenerates recommendations with the updated life event data. No async job dispatch needed.

### AI Context Enhancement

Fyn's financial context (`HasAiChat::buildFinancialContext`) already includes life events. Enhancement:
- Instead of just "user has 3 upcoming events", the context includes per-module impact summaries from `LifeEventIntegrationService::getModuleContext()`
- When Fyn discusses any module, it knows the specific life event impacts — "You have a £45,000 education expense in 18 months which is driving the savings recommendation to build reserves"

---

## Module Metrics for What-If Comparisons

Each module contributes specific metrics to the What-If comparison dashboard:

| Module | Metrics |
|--------|---------|
| Retirement | Projected annual income, capital at retirement, years income lasts, state pension gap |
| Investment | Portfolio value at target date, projected growth, fee impact |
| Estate | Taxable estate value, IHT liability, effective IHT rate, liquidity position |
| Protection | Total coverage, coverage gaps, monthly premiums |
| Savings | Emergency fund runway, total savings, goal progress |
| Income | Total annual income, net income after tax, disposable income |
| Property | Total property value, equity, rental yield |

Only affected modules shown per scenario.

---

## Implementation Order

1. **Sub-Project 2: Goals Module Integration** — Lowest risk, highest immediate value. Wires existing data into existing pipelines. Makes the platform smarter without new infrastructure.

2. **Sub-Project 3: Life Events Recommendation Expansion** — Extends existing patterns (includes new life event type migration). Depends on module agents being goals-aware (Sub-Project 2).

3. **Sub-Project 1: What-If Scenario System** — Largest piece. New table, new service, new AI tool, new frontend page. Uses transient user model copies for what-if calculations — no agent changes needed, agents always use stored user data.

---

## In Scope (v1)

- **Goal contribution input** — Users can enter goal contributions manually. Fyn suggests alternatives if the contribution is too low (won't meet target by deadline) or too high (overcommitted relative to disposable income)
- **New life event types** — Add `divorce`, `marriage`, `new_child`, `job_loss`, `income_change` to the `life_events.event_type` enum via migration. These are common planning scenarios that users need to model

## Out of Scope (v2)

- What-if scenario sharing between household members
- Historical scenario comparison (comparing how a scenario's projections changed over time)
- Mobile app What-If views (web only for v1)

---

## Key Files Affected

**New files:**
- `app/Models/WhatIfScenario.php` (with `SoftDeletes`, `Auditable` traits)
- `app/Services/WhatIf/WhatIfScenarioService.php`
- `app/Http/Controllers/Api/WhatIfScenarioController.php`
- `app/Http/Requests/StoreWhatIfScenarioRequest.php`
- `app/Http/Resources/WhatIfScenarioResource.php`
- `resources/js/views/Planning/WhatIfDashboard.vue`
- `resources/js/components/WhatIf/ScenarioCard.vue`
- `resources/js/components/WhatIf/ScenarioDetail.vue`
- `resources/js/components/WhatIf/ModuleComparison.vue`
- `resources/js/store/modules/whatIf.js`
- `resources/js/services/whatIfService.js`
- `database/migrations/..._create_what_if_scenarios_table.php`
- `database/factories/WhatIfScenarioFactory.php`
- `database/seeders/WhatIfScenarioSeeder.php`

**Modified files:**
- `app/Agents/SavingsAgent.php` — goals + life events in recommendations
- `app/Agents/ProtectionAgent.php` — goals in coverage calc + life event triggers
- `app/Agents/EstateAgent.php` — goals liquidity risk + life event cross-reference
- `app/Agents/RetirementAgent.php` — goals in income validation + drawdown
- `app/Services/Goals/LifeEventIntegrationService.php` — new event types in EVENT_MODULE_MAP
- `database/migrations/..._add_new_life_event_types.php` — enum extension
- `app/Agents/CoordinatingAgent.php` — `handleWhatIfScenario()` routes to `WhatIfScenarioService`
- `app/Services/AI/AiToolDefinitions.php` — replace `run_what_if_scenario` with `create_what_if_scenario`
- `app/Traits/HasAiChat.php` — richer life event context per module
- `app/Observers/LifeEventMonteCarloObserver.php` — extended cache invalidation for affected module recommendations
- `resources/js/views/Planning/WhatIfScenarios.vue` — renamed to `DeathOfSpouseScenario.vue`, route updated
- `resources/js/router/index.js` — what-if route restructured
- `routes/api.php` — what-if API routes
