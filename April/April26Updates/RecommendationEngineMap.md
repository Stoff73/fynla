# Fynla Recommendation Engine — Architectural Map

**Date:** 2026-04-26
**Branch:** feature/fyn-persona-split

## Architecture at a glance

```
Vue Component → Vuex store → API → Controller → Agent → Aggregator → Module Services → Personaliser → Cache → DB
```

**Two systems in one:**
- **Live aggregation** — computed on-demand, no DB lookup
- **Tracking table** (`recommendation_tracking`) — only stores user *actions* (mark done, dismiss, notes), not the recommendations themselves

---

## Layer 1 — Database

**Table:** `recommendation_tracking`
**Model:** `app/Models/RecommendationTracking.php`

Stores user action state, not the recommendations.

**Columns:**
- `id` (PK)
- `user_id` (FK → users, cascade delete)
- `recommendation_id` (string, unique per user)
- `module` (protection, savings, investment, retirement, estate, property)
- `recommendation_text` (text)
- `priority_score` (decimal 15,2)
- `recommended_amount` (decimal 15,2) — added 2026-03-02
- `timeline` (immediate, short_term, medium_term, long_term)
- `status` (pending, in_progress, completed, dismissed)
- `completed_at`, `notes`, timestamps

**Methods (`:90-124`):** `markAsCompleted()`, `dismiss()`, `markAsInProgress()`
**Scopes:** `pending()`, `completed()`, `inProgress()`, `active()`, `byModule($module)`, `byTimeline($timeline)`

**Migration:** `database/migrations/2026_03_02_072041_add_recommended_amount_to_recommendation_tracking.php`

---

## Layer 2 — Coordination layer (the heart)

### RecommendationsAggregatorService
`app/Services/Coordination/RecommendationsAggregatorService.php`

- **Entry:** `aggregateRecommendations(int $userId): array` `:29-133`
- **Flow:**
  1. ProtectionAgent→analyze() `:36`
  2. SavingsAgent→analyze() `:56`
  3. RetirementAgent→analyze() `:84`
  4. ComprehensiveEstatePlanService→generateComprehensiveEstatePlan() `:103`
  5. Merges, extracts coverage gaps, emergency fund recs, ISA allowance remaining, income shortfalls
  6. Hands to RecommendationPersonaliser `:125`
  7. Sorts by `priority_score` desc `:128-130`

- **Helpers:**
  - `formatRecommendations()` `:138-159` — normalises structure across modules
  - `determineTimeline()` `:164-175` — priority_score → timeline
  - `determineCategory()` `:180-196`
  - `determineImpact()` `:201-210` — priority → high/medium/low
  - `getTopRecommendations()` `:251-256`
  - `getSummary()` `:261-315` — counts + sums
  - `getRecommendationsByModule/Priority/Timeline()` `:215-246`

### RecommendationPersonaliser
`app/Services/Coordination/RecommendationPersonaliser.php`

- **Entry:** `personaliseRecommendations(array $recs, User $user): array` `:61-67`
- **Purpose:** adds `personalised_context[]` per rec
- **Module builders:**
  - `personaliseProtection()` `:72-118` — family composition, death-in-service, employment status
  - `personaliseEstate()` `:123-219` — IHT NRB context, spouse inheritance allowance, trust timing for minors, gifting window
  - `personaliseInvestment()` `:224-284` — property exposure, employer concentration risk, rebalancing drift, ISA allowance
- **Detectors:** `isLifeCoverRecommendation()`, `isIncomeProtectionRecommendation()`, `isIHTRecommendation()`, `isTrustRecommendation()`, `isGiftingRecommendation()`, `isAssetAllocationRecommendation()`, `isRebalancingRecommendation()`, `isISARecommendation()` `:443-502`

### CoordinatingAgent
`app/Agents/CoordinatingAgent.php`

- **The central hub.** Constructor injects all module agents + coordination services `:61-78`
- `analyze(int $userId): array` `:83-86` → `orchestrateAnalysis()`
- `generateHolisticPlan()` — holistic planning with conflict resolution
- `chat()` via `HasAiChat` trait `:89-100+` — AI chat entry point with tool calling
- Uses `HasAiChat` `:58` + `HasAiGuardrails` `:59` — Claude API integration

---

## Layer 3 — Module recommendation services

### Protection
**`app/Services/Protection/RecommendationEngine.php`**
- `generateRecommendations(array $gaps, ProtectionProfile $profile): array` `:24-136`
- 6–8 deterministic gap-based recommendations:
  1. Life insurance gap (for dependants)
  2. Debt protection (decreasing term)
  3. Critical illness cover
  4. Income protection
  5. Education funding
  6. Trust placement
  7. Policy optimisation (premiums > 5% income)
- **Output fields:** priority (1-5), category, action, rationale, impact, estimated_cost
- **Premium costs:** TaxConfigService factors (base_rate, smoker_loading, ci_ratio, ip_rate) `:177-250`

### Investment (the most elaborate)

**`app/Services/Investment/Recommendation/TransferRecommendationService.php`**
- `scan(array $context): array` `:33-125`
- 13 independent transfer/optimisation scans:
  1. Bed & ISA (GIA → ISA)
  2. Excess cash above emergency target
  3. Tax loss harvesting
  4. PSA (Personal Savings Allowance) breach
  5. Dividend allowance breach
  6. Cash ISA → Stocks & Shares ISA
  7. Pension consolidation
  8. ISA consolidation
  9. Platform consolidation
  10–13 (in full file)
- Uses BedAndISACalculator + CGTHarvestingCalculator
- **Output:** `{recommendations: [], scans_triggered: int, scans_total: 13}`

**`app/Services/Investment/Recommendation/ContributionWaterfallService.php`**
- `allocate(array $context, float $surplus, array $lifeEventModifiers, array $goalModifiers, array $safetyResult): array` `:54-100+`
- 11-step sequential surplus-allocation waterfall:
  1. Lifetime ISA (25% bonus, age <40, FTB)
  2. Stocks & Shares ISA
  3. Pension (Annual Allowance)
  4a. Premium Bonds (max £50k)
  4b. NS&I (10% remainder)
  5. Offshore Bond (min £10k, higher/additional rate)
  6. Onshore Bond (min £5k, higher rate)
  7. Pension Carry Forward (3-year window)
  8. VCT/EIS/SEIS (max 10%, experienced)
  9. GIA (remaining surplus)
- **Output:** `{recommendations, total_allocated, remaining_surplus, steps_executed, steps_skipped, decision_path}`

**Other Investment services:**
- `ConflictResolutionService` — resolves conflicting recs
- `DataReadinessService` — validates data completeness
- `GoalAssessmentService` — goal-related recs
- `LifeEventAssessmentService` — life event modifiers
- `RecommendationOutputFormatter` — output formatting
- `SafetyCheckService` — safety thresholds
- `SpouseOptimisationService` — joint planning
- `UserContextBuilder` — user context aggregation

### Retirement / Savings / Estate / Goals
Recommendations come directly from each module's Agent (RetirementAgent, SavingsAgent, EstateAgent, GoalsAgent) via standard `analyze()` / `generateRecommendations()` pattern.

---

## Layer 4 — HTTP API

### RecommendationsController
`app/Http/Controllers/Api/RecommendationsController.php`

| Endpoint | Method | Action | Returns |
|---|---|---|---|
| `/api/recommendations` | GET | `index()` `:27-76` | Filtered, paginated list (module, priority, timeline, status, limit) |
| `/api/recommendations/summary` | GET | `summary()` `:83-97` | Counts by priority/module/timeline + total benefits/costs |
| `/api/recommendations/top` | GET | `top()` `:104-124` | Top N by priority (default 5) |
| `/api/recommendations/completed` | GET | `completed()` `:281-299` | Completed from tracking table |
| `/api/recommendations/{id}/mark-done` | POST | `markDone()` `:131-164` | Creates/updates RecommendationTracking |
| `/api/recommendations/{id}/in-progress` | POST | `markInProgress()` `:171-203` | Status → in_progress |
| `/api/recommendations/{id}/dismiss` | POST | `dismiss()` `:210-242` | Status → dismissed |
| `/api/recommendations/{id}/notes` | PATCH | `updateNotes()` `:249-274` | Updates notes |

### HolisticPlanningController
`app/Http/Controllers/Api/HolisticPlanningController.php`

| Endpoint | Method | Action | Purpose |
|---|---|---|---|
| `/api/holistic/analyze` | POST | `analyze()` `:34-50` | Cross-module analysis via CoordinatingAgent |
| `/api/holistic/plan` | POST | `plan()` `:57-81` | Holistic plan + auto-stores to tracking `:74` |
| `/api/holistic/recommendations` | GET | `recommendations()` `:88-100+` | Active recs from RecommendationTracking |

### Module-specific endpoints (`routes/api.php`)
- `ProtectionController@recommendations` `:368`
- `SavingsController@recommendations` `:416`
- `InvestmentController@recommendations` `:490`
- `RetirementController@recommendations` `:606`
- `TaxOptimizationController@getRecommendations` `:621`
- `AssetLocationController@getRecommendations` `:882`
- `TrustController@getTrustRecommendations` `:958`
- Route group `/api/recommendations` `:970-981` — middleware `auth:sanctum`

---

## Layer 5 — Frontend (Vue 3 + Vuex)

### Vuex
`resources/js/store/modules/recommendations.js`
- **State:** `recommendations`, `topRecommendations`, `loading`, `error` `:4-9`
- **Actions:**
  - `fetchRecommendations({ commit }, params)` `:30-42`
  - `fetchTopRecommendations({ commit }, limit)` `:44-53`
- **Getters:**
  - `highPriorityRecommendations` `:58-60` — filter impact='high'
  - `recommendationsByModule(module)` `:62-64`
  - `pendingRecommendations` `:66-68`
  - `inProgressRecommendations` `:70-72`

### Components

**Protection (canonical card UI)**
- `resources/js/components/Protection/RecommendationCard.vue` `:1-202`
  - Props: `recommendation` (priority, action, category, rationale, impact, estimated_cost, personalised_context)
  - Priority badge, collapsed/expandable rationale, expected impact, est. cost (currencyMixin), personalised context bullets `:88-100`
  - Emits: `mark-done`

**Retirement**
- `resources/js/views/Retirement/Recommendations.vue` `:1-173` — currently has **"Coming Soon"** watermark; backend ready but UI hidden
- Priority filter (All/High/Medium/Low) + list with badges
- Reads from `retirement/recommendations` Vuex `:128`

**Investment**
- `InvestmentRecommendationsTracker.vue` — tracks user actions
- `TaxOptimizationRecommendations.vue` — tax-specific
- `StrategyRecommendationCard.vue` — strategy/asset allocation
- `RecommendationsSection.vue` (PlanSections) — within plan view

**Estate**
- `LifeCoverRecommendations.vue` — life cover gap

**Shared / Generic**
- `RecommendationFilters.vue` — module/priority/timeline/status filters
- `ActionsOverviewCard.vue` (Dashboard) — top recs widget
- `SpousalOptimisations.vue` (Dashboard) — joint planning

All components use `currencyMixin`.

### Frontend services
**No dedicated `recommendationService.js`** — uses generic `api.get('/recommendations')` via Vuex dispatch.

---

## Layer 6 — Cache invalidation

`app/Observers/RecommendationCacheObserver.php`

Triggers on: model created/updated/deleted.
Logic: invalidates agent caches when financial data changes (prevents stale recs).

**Model → agents map:**
- SavingsAccount → [Savings, TaxOptimisation]
- InvestmentAccount, Holding → [Investment, TaxOptimisation]
- DCPension, DBPension, RetirementProfile → [Retirement, TaxOptimisation]
- LifeInsurancePolicy, CriticalIllnessPolicy, ProtectionProfile → [Protection]
- Gift, Trust, Liability, Chattel, BusinessInterest → [Estate, TaxOptimisation]
- Property, Mortgage → [Estate, Protection, TaxOptimisation]
- FamilyMember → [Protection, Estate, TaxOptimisation]
- Goal, LifeEvent → [Savings, Investment]
- **Always** invalidates CoordinatingAgent

**Joint owner handling:** invalidates both `user_id` AND `joint_owner_id` `:59-65`
**Cache key pattern:** `v1_{agent}_{userId}_{suffix}` (BaseAgent)

---

## Layer 7 — Triggering & generation flow

When are recommendations generated?

1. **On user view (live aggregation)**
   - Frontend dispatches `recommendations/fetchRecommendations()` → GET /api/recommendations
   - RecommendationsAggregatorService aggregates from all agents (no DB lookup)
   - Stored to `recommendation_tracking` only when user marks done/in-progress/dismissed

2. **On holistic plan generation**
   - POST /api/holistic/plan
   - HolisticPlanningController → CoordinatingAgent→generateHolisticPlan()
   - Recs auto-stored to tracking via `storeRecommendations()` `:74`

3. **On data change**
   - Create/update/delete financial record
   - RecommendationCacheObserver invalidates relevant agent caches
   - Next request forces fresh calculation

**No scheduled batch generation. No queue jobs for rec generation.** (AiIdempotencyCleanupJob, AiAuditRetentionJob exist but are AI-specific.)

---

## Layer 8 — AI / Fyn integration

**Recommendations are deterministic, not AI-generated.**

- **Protection:** rule-based gap analysis + priority calculation
- **Investment:** tax rules (ISA, pension allowances) + waterfall + transfer scans
- **Retirement / Savings / Estate:** projection shortfalls, adequacy scores

CoordinatingAgent serves as **single AI chat entry point**:
- `chat()` via `HasAiChat` trait `:89-100+`
- Override system prompt + tool allowlist
- Anthropic Claude API
- Can delegate to recommendation-aware tools

**Fyn discusses recs but doesn't generate them.** CoordinatingAgent orchestrates the analysis.

---

## Layer 9 — Configuration

### TaxConfigService
`app/Services/TaxConfigService.php` — singleton per request, loads active `TaxConfiguration`. Used by all rec services for:
- ISA: `$taxConfig->getISAAllowances()['annual_allowance']`
- Pension: `$taxConfig->getPensionAllowances()`
- IHT: `$taxConfig->getInheritanceTax()['nil_rate_band']`, `['residence_nil_rate_band']`
- Protection premium factors: `$taxConfig->get('protection.premium_factors.*')`
- Investment waterfall: `$taxConfig->get('investment.waterfall')`

### TaxDefaults
Fallback constants when TaxConfigService unavailable (NRB 325k, ISA 20k, PA 12570, etc.).

**No feature flags for recommendations** — all modules generate them if data is available.

---

## Layer 10 — Tests

**Unit:**
- `tests/Unit/Services/Coordination/RecommendationsAggregatorServiceTest.php` — aggregation, filtering, summary
- `tests/Unit/Services/Coordination/RecommendationPersonaliserTest.php` — personalisation context
- `tests/Unit/Services/Protection/RecommendationEngineTest.php` — protection gap recs

**Feature:**
- `tests/Feature/Api/RecommendationsControllerTest.php` `:52-80` — index, filter, summary, top

**Frontend:**
- `tests/frontend/components/Protection/RecommendationCard.test.js` — RecommendationCard component

---

## Complete flow diagram

```
┌─────────────────────────────────────────────────────────────┐
│ FRONTEND (Vue 3 + Vuex)                                     │
│                                                             │
│  RecommendationCard.vue  →  Vuex: recommendations module    │
│        ↓                          ↓                         │
│  User clicks "Get Recs"  Dispatch: fetchRecs                │
└──────────────┬──────────────────────┬───────────────────────┘
               │                      │
               ▼                      ▼
        POST /api/holistic/plan   GET /api/recommendations
               │                      │
      ┌────────┴──────────────────────┴───────────────────┐
      ▼                                                   ▼
   HolisticPlanningController              RecommendationsController
   └─ plan() / analyze()                    └─ index() / top() / summary()
      ↓                                        ↓
   CoordinatingAgent                     RecommendationsAggregatorService
   └─ generateHolisticPlan()             ├─ protectionAgent.analyze()
     └─ orchestrateAnalysis()            ├─ savingsAgent.analyze()
                                         ├─ investmentAgent.analyze()
                                         ├─ retirementAgent.analyze()
                                         └─ estatePlanService.generate()
                                            │
                                            ├─ ContributionWaterfallService.allocate()
                                            ├─ TransferRecommendationService.scan()
                                            ├─ ConflictResolutionService.resolve()
                                            └─ ProtectionRecommendationEngine.generate()

       ↓
   RecommendationPersonaliser.personaliseRecommendations()
       │
       ├─ personaliseProtection()
       ├─ personaliseEstate()
       └─ personaliseInvestment()

       ↓
   JSON response + (holistic flow only) store to RecommendationTracking

   User actions:
   └─ POST /api/recommendations/{id}/mark-done
   └─ POST /api/recommendations/{id}/in-progress
   └─ POST /api/recommendations/{id}/dismiss
   └─ PATCH /api/recommendations/{id}/notes
```

---

## Key findings

1. **Dual recommendation system** — live aggregation (no DB lookup) + tracking table (only for user actions)
2. **Investment is by far the most complex** — 13 transfer scans + 11-step waterfall + conflict/safety/spouse/life-event/goal services
3. **No scheduled generation** — all on-demand or triggered by data changes
4. **Personalisation is rich** — module-specific context (family, employment, spouse, gifting windows, property, etc.)
5. **Retirement UI is watermarked "Coming Soon"** — backend ready, frontend hidden
6. **CoordinatingAgent is dual-purpose** — orchestrates module agents AND is the AI chat entry point
7. **Cache invalidation is sophisticated** — observer-driven, model-aware, joint-ownership-aware
8. **Recommendations are deterministic, not AI-generated** — Fyn discusses them but doesn't author them
9. **Estate recs** extracted from implementation timeline (action items with timeframes, costs, IHT savings)
10. **All recs flow through normalisation** before reaching the frontend (consistent structure)
