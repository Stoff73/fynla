# `app/Agents/` — application-layer agent map

Nine classes, 11,969 lines total (`app/Agents/`, verified by `wc -l`). One abstract base, seven module agents, one coordinator.

---

## 1. `BaseAgent` (155 lines)

**Purpose.** Abstract contract plus a cache toolkit. It declares three abstract methods every agent must implement, wraps `Cache::remember` with a versioned per-user key convention, standardises a response envelope, and provides penny rounding. It carries no domain logic at all. `app/Agents/BaseAgent.php:11-13`

**Public / protected surface**

| Member | Line | Description |
|---|---|---|
| `analyze(int $userId): array` | `BaseAgent.php:26` | Abstract. Analyse user data and return insights. |
| `generateRecommendations(array $analysisData): array` | `BaseAgent.php:31` | Abstract. Turn an analysis array into recommendations. |
| `buildScenarios(int $userId, array $parameters): array` | `BaseAgent.php:36` | Abstract. What-if scenarios. |
| `remember(string $key, callable $cb, ?int $ttl = null)` | `BaseAgent.php:45` | Protected. `Cache::remember` with the agent's default time-to-live. |
| `getUserCacheKey(int $userId, string $suffix)` | `BaseAgent.php:58` | Protected. Builds `v1_{lowercased class basename}_{userId}_{suffix}`. |
| `clearUserCache(int $userId, array $suffixes)` | `BaseAgent.php:71` | Public. Forgets analysis, recommendations, scenarios. |
| `invalidateUserCache(int $userId, array $additionalKeys)` | `BaseAgent.php:86` | Public. The standardised invalidation: five default suffixes plus `v1_{agent}_analysis_{userId}` plus any extra keys. |
| `invalidateCacheForUsers(array $userIds, ...)` | `BaseAgent.php:111` | Public. Loops the above, for joint accounts. |
| `rememberForUser(int $userId, string $suffix, callable $cb, ?int $ttl)` | `BaseAgent.php:128` | Protected. `remember` over `getUserCacheKey`. |
| `response(bool $success, string $message, array $data)` | `BaseAgent.php:138` | Protected. Envelope `['success','message','data','timestamp']`. |
| `roundToPenny(float $value)` | `BaseAgent.php:151` | Protected. `round($value, 2)`. |

**Shared state.** `use FormatsCurrency` at `BaseAgent.php:13`, so every agent has currency formatting. Default time-to-live is `TaxDefaults::CACHE_TTL_STANDARD` (`BaseAgent.php:21`). Cache version constant `v1` (`BaseAgent.php:15`).

---

## 2. `ProtectionAgent` (457 lines)

**Purpose.** Assesses whether a household's life, critical illness and income protection cover is adequate. It opens with a data-readiness gate that returns a fully-keyed null response when the user lacks the facts to analyse (`ProtectionAgent.php:46-64`), then caches an analysis built from a coverage-gap analyser, an adequacy scorer and a profile-completeness checker. It also computes a `missing_for_quality_advice` list so Fyn can ask for what is absent rather than guess.

**Public methods**

| Method | Line | Description |
|---|---|---|
| `analyze(int $userId)` | `41` | Readiness gate, then cached gap/adequacy/coverage analysis wrapped in `response()`. |
| `generateRecommendations(array $analysisData)` | `348` | Reads `data.recommendations` off a prior analysis; returns `success:false` if absent. Emits `EngineCalled('protection_recommendation')` at `368`. |
| `buildScenarios(int $userId, array $parameters)` | `386` | Delegates to `ScenarioBuilder` with an eager-loaded policy set. |
| `invalidateCache(int $userId)` | `453` | `invalidateUserCache` plus the legacy `protection_analysis_{id}` key. |

**Services injected** (`ProtectionAgent.php:26-38`): `CoverageGapAnalyzer`, `AdequacyScorer`, `RecommendationEngine`, `ScenarioBuilder`, `ProfileCompletenessChecker`, `RecommendationPersonaliser`, `ProtectionDataReadinessService`, `CrossModuleAssetAggregator`, `LifeCoverReach`. No `app()` resolutions.

**Callers.** `CoordinatingAgent.php:148`; `ModuleSummaryController.php:38`; `ProtectionController.php:52`; `RecommendationsAggregatorService.php:33` (as `$protectionEngine`); `ProtectionPlanService.php:15`; `WhatIfScenarioService.php:48`; `DashboardAggregator.php:18`; `ComprehensiveProtectionPlanService.php:31`; `MobileDashboardAggregator.php:55`. Cache invalidation is triggered from `app/Traits/PolicyCRUDTrait.php:47,93,135`.

**Cache.** Key `protection_analysis_{userId}` (`ProtectionAgent.php:67`), default base time-to-live. Invalidated by `invalidateCache` and by `CacheInvalidationService` (module loop at `CacheInvalidationService.php:95-97`).

---

## 3. `SavingsAgent` (607 lines)

**Purpose.** Values the household's cash, measures emergency-fund runway against resolved expenditure, tracks ISA allowance use, assesses liquidity and Financial Services Compensation Scheme exposure, and compares rates. Its readiness-gate branch deliberately still returns a cash total and account count, because the dashboard card reads those beside net worth and a newly-registered spouse was seeing zero (`SavingsAgent.php:69-82`). Joint accounts are reached through `SavingsStore` then fractioned, not read off a `user_id` hasMany (`SavingsAgent.php:92-95`).

**Public methods**

| Method | Line | Description |
|---|---|---|
| `analyze(int $userId)` | `61` | Readiness gate (with a cash-total escape hatch), then cached savings analysis. |
| `generateRecommendations(array $analysisData)` | `296` | Delegates to `SavingsActionDefinitionService::evaluateAgentActions`, sorts Goal-category recommendations first, falls back to inline logic when the service is absent. |
| `buildScenarios(int $userId, array $parameters)` | `426` | Increased-savings and related future-value scenarios. |

**Services injected** (`SavingsAgent.php:38-52`): `EmergencyFundCalculator`, `ISATracker`, `GoalProgressCalculator`, `LiquidityAnalyzer`, `RateComparator`, `SavingsDataReadinessService`, `SavingsStore`, `CrossModuleAssetAggregator`, and optionally `SavingsActionDefinitionService`, `PSACalculator`, `FSCSAssessor`, `PlanConfigService`, `GoalProgressService`. Runtime `app()` resolutions: `SavingsStore` (`307`), `TaxConfigService` (`547`).

**Callers.** `CoordinatingAgent.php:150`; `ModuleSummaryController.php:39`; `SavingsController.php:54`; `RecommendationsAggregatorService.php:34`; `SavingsStrategySource.php:22`; `SavingsPlanService.php:17`; `InvestmentPlanService.php:50`; `WhatIfScenarioService.php:47`; `DashboardAggregator.php:19`; `MobileDashboardAggregator.php:56`.

**Cache.** Key `savings_analysis_{userId}` (`SavingsAgent.php:91`). Time-to-live 1800 seconds, overridden by `PlanConfigService::getSavingsCacheTTL()` when available (`SavingsAgent.php:35,54-56`). No `clearCache`/`invalidateCache` override on this agent; invalidation comes from `CacheInvalidationService`.

**Traits.** `CalculatesOwnershipShare` and `ResolvesExpenditure` (`SavingsAgent.php:33-34`).

---

## 4. `InvestmentAgent` (433 lines)

**Purpose.** Analyses the portfolio: value, returns, asset allocation, diversification, risk metrics, fees against a low-cost comparison, tax efficiency and wrapper use, plus deviation from target allocation. Its distinguishing logic is `holdingsAtUserShare`, which scales each holding's cost and value by the owner's fraction, because a holding carries no ownership columns and a joint portfolio would otherwise reach every derived figure whole (`InvestmentAgent.php:44-63`).

**Public methods**

| Method | Line | Description |
|---|---|---|
| `analyze(int $userId)` | `82` | Readiness gate, then cached portfolio analysis at the user's ownership share. |
| `generateRecommendations(array $analysis)` | `301` | Calls `InvestmentActionDefinitionService::evaluateAgentActions` with empty savings/accounts/fees; the full evaluation lives in `InvestmentPlanService::getRecommendations` (`InvestmentAgent.php:296-299`). |
| `buildScenarios(int $userId, array $parameters)` | `332` | Investment what-if scenarios. |
| `clearCache(int $userId)` | `408` | `invalidateUserCache` plus `investment_analysis_{id}`. |
| `getPortfolioProjections(int $userId, array $periods, ?array $overrides, ?int $selected)` | `418` | Thin pass-through to `InvestmentProjectionService` resolved via `app()` at `426`. |

**Services injected** (`InvestmentAgent.php:35-46`): `PortfolioAnalyzer`, `DiversificationAnalyzer`, `MonteCarloSimulator`, `SimpleAssetAllocationOptimizer`, `FeeAnalyzer`, `TaxEfficiencyCalculator`, `TaxConfigService`, `InvestmentActionDefinitionService`, `DataReadinessService`, `CrossModuleAssetAggregator`. Runtime `app()`: `SavingsStore` (`164`), `InvestmentProjectionService` (`426`).

**Callers.** `CoordinatingAgent.php:149`; `InvestmentController.php:71`; `ModuleSummaryController.php:40`; `InvestmentProjectionController.php:18`; `RecommendationsAggregatorService.php:35`; `InvestmentStrategySource.php:26`; `SavingsPlanService.php:18`; `InvestmentPlanService.php:49`; `WhatIfScenarioService.php:50`; `DashboardAggregator.php:20`; `MobileDashboardAggregator.php:57`. `clearCache` is called around a dozen times from `InvestmentController` on write paths, including for the joint owner (`InvestmentController.php:467,471,608,612,617,…`).

**Cache.** Key `investment_analysis_{userId}` (`InvestmentAgent.php:109`). `CacheInvalidationService.php:92-97` notes this key was previously missed by every invalidation path (W-0239) and is now derived from the module list.

**Trait.** `CalculatesOwnershipShare` (`InvestmentAgent.php:33`).

---

## 5. `RetirementAgent` (838 lines)

**Purpose.** Projects pension income to and through retirement. It gates on readiness but still returns data-completeness actions and a provision summary when the gate is closed, so a module card can say what the household holds even with no target set (`RetirementAgent.php:87-99`). It projects defined contribution and defined benefit pots plus State Pension, checks the Annual Allowance, optimises contributions, and plans decumulation. It borrows the Investment module's portfolio services to analyse the pension portfolio itself.

**Public methods**

| Method | Line | Description |
|---|---|---|
| `analyze(int $userId)` | `75` | Readiness gate returning completeness actions and provision summary, then cached projection, breakdown, allowance, profile, decumulation and post-retirement goals. |
| `generateRecommendations(array $analysisData)` | `524` | Database-driven via `RetirementActionDefinitionService::evaluateAgentActions`; emits `EngineCalled('retirement_recommendation')`. |
| `buildScenarios(int $userId, array $parameters)` | `543` | Current, increased-contribution, later-retirement and lower-target scenarios, then a comparison (`594`, `613`, `652`, `689`, `709`). |
| `analyzeDCPensionPortfolio(int $userId, ?int $dcPensionId)` | `827` | Cached pass-through to `PensionPortfolioAnalyzer`. Called from `RetirementController.php:741`. |

**Services injected** (`RetirementAgent.php:45-69`): `PensionProjector`, `AnnualAllowanceChecker`, `PensionContributionOptimizer`, `DecumulationPlanner`, `PensionPortfolioAnalyzer`, `TaxConfigService`, `RetirementActionDefinitionService`, `RiskPreferenceService`, `RetirementDataReadinessService`, `StatePensionAgeResolver`, `PortfolioAnalyzer`, `MonteCarloSimulator`, `SimpleAssetAllocationOptimizer`, `FeeAnalyzer`, `TaxEfficiencyCalculator`, `FutureValueCalculator`, optional `PlanConfigService`. Runtime `app()`: `PensionStore` (`753`).

**Callers.** `CoordinatingAgent.php:151`; `RetirementController.php:60`; `ModuleSummaryController.php:41`; `RecommendationsAggregatorService.php:36`; `RetirementStrategySource.php:22`; `RetirementPlanService.php:23`; `WhatIfScenarioService.php:46`; `DashboardAggregator.php:21`; `MobileDashboardAggregator.php:58`, whose docblock at `263` states every figure on the /m retirement card comes from this agent and nowhere else.

**Cache.** `retirement_analysis_{userId}` (`RetirementAgent.php:107`), `dc_pensions_portfolio_{userId}` or `dc_pension_{id}_portfolio` (`829-831`). Time-to-live 3600, overridden by `PlanConfigService::getRetirementCacheTTL()` (`RetirementAgent.php:40,67-71`). Memoises `$dcPensions` per instance (`RetirementAgent.php:42`).

---

## 6. `EstateAgent` (1,856 lines)

**Purpose.** The largest module agent. It builds an inheritance-tax picture from an eagerly loaded estate (assets, properties, liabilities, mortgages, spouse, family, trusts, gifts), then produces a strictly ordered seven-step mitigation strategy that treats chargeable lifetime transfers into trust as the last resort. The order is documented at `EstateAgent.php:480-487`: charitable bequest rate reduction, liquidity assessment, existing life cover, annual gifting, life cover strategy, potentially exempt transfer gifting, then trust.

**Public methods**

| Method | Line | Description |
|---|---|---|
| `analyze(int $userId)` | `63` | Loads the estate, gates on readiness, then caches summary, asset breakdown, IHT calculation, trust and gifting recommendations, charitable analysis, will review, life cover, pension amendment and goal liquidity. |
| `generateRecommendations(array $analysisData)` | `489` | Runs the seven ordered steps against the remaining liability, each in its own private method (`726`, `902`, `979`, `1091`, `1174`, `1234`, `1310`). |
| `buildScenarios(int $userId, array $parameters)` | `1624` | Current, optimised, gifting, property-downsizing and trust-creation scenarios (`1723`, `1751`, `1775`, `1799`, `1819`). |
| `invalidateCache(int $userId)` | `1846` | `invalidateUserCache` plus the legacy `estate_analysis_{id}` string, kept only to drain pre-W-0381 entries. |

**Services injected** (`EstateAgent.php:44-58`): `IHTCalculationService`, `EstateAssetAggregatorService`, `ComprehensiveEstatePlanService`, `GiftingStrategyOptimizer`, `PersonalizedTrustStrategyService`, `WillAnalysisService`, `TaxConfigService`, `RecommendationPersonaliser`, `EstateDataReadinessService`, `LifeCoverCalculator`, `LifeCoverReach`, `FutureValueCalculator`.

**Callers.** `CoordinatingAgent.php:152`; `ModuleSummaryController.php:42`; `EstatePlanService.php:21`; `WhatIfScenarioService.php:49`; `DashboardAggregator.php:22`; `MobileDashboardAggregator.php:59`. Note it is absent from `RecommendationsAggregatorService`, unlike the other five module agents.

**Cache.** Key derived from `getUserCacheKey($userId, 'analysis')`, giving `v1_estateagent_{id}_analysis` (`EstateAgent.php:112`). The comment at `105-111` records why: the agent previously wrote `estate_analysis_{id}` while invalidation forgot the derived key, so every invalidation cleared a key nothing had written and stale analysis survived the full time-to-live (W-0381).

---

## 7. `GoalsAgent` (542 lines)

**Purpose.** The only module agent with no data-readiness service. It reads goals for the user or their joint owner, splits active from completed, groups by assigned module (savings, investment, property, retirement), computes progress and affordability, and assesses risk. `PrerequisiteGateService.php:166` records the deliberate absence: "No DataReadinessService exists for goals — GoalsAgent checks has_goals directly."

**Public methods**

| Method | Line | Description |
|---|---|---|
| `analyze(int $userId)` | `32` | Cached. Returns `has_goals:false` with an empty summary when there are none, else per-module analysis, summary, affordability and risk. |
| `generateRecommendations(array $analysisData)` | `151` | Hand-written rules: a getting-started recommendation when there are no goals, then behind-schedule, affordability and emergency-fund checks. Not database-driven. |
| `buildScenarios(int $userId, array $parameters)` | `240` | Requires a `goal_id`; builds increased-contribution and lump-sum scenarios. |
| `getDashboardOverview(int $userId)` | `340` | Dashboard shape with top goals by priority. |
| `clearCache(int $userId)` | `538` | Calls `clearUserCache`. |

**Services injected** (`GoalsAgent.php:22-27`): `GoalAssignmentService`, `GoalAffordabilityService`, `GoalProgressService`, `GoalRiskService`. No `app()` resolutions, no `TaxConfigService`.

**Callers.** `CoordinatingAgent.php:153`; `ModuleSummaryController.php:43`; `GoalsController.php:40` (`getDashboardOverview` at `GoalsController.php:123`, `generateRecommendations` at `104`, `buildScenarios` at `472`); `RecommendationsAggregatorService.php:38`; `GoalPlanService.php:19`; `MobileDashboardAggregator.php:60`. Not in `DashboardAggregator` or `WhatIfScenarioService`.

**Cache.** `rememberForUser($userId, 'analysis', …)` at `GoalsAgent.php:35`, so `v1_goalsagent_{id}_analysis`.

---

## 8. `TaxOptimisationAgent` (130 lines)

**Purpose.** The thinnest agent. It asks `TaxOptimisationService` for allowance usage and strategies, and republishes them with the active tax year from `TaxConfigService`. Every figure comes from the service; the agent adds the cache, the envelope and a recommendation mapping. `app/Agents/TaxOptimisationAgent.php:11-17`

**Public methods**

| Method | Line | Description |
|---|---|---|
| `analyze(int $userId)` | `28` | Cached. Tax year, allowance usage, strategies, total estimated saving, strategy count. |
| `generateRecommendations(array $analysisData)` | `53` | Maps each strategy to a recommendation with `module`, `type`, `priority`, `title`, `description`, `action`, `estimated_saving`. |
| `buildScenarios(int $userId, array $parameters)` | `80` | Three scenarios: maximise ISA, maximise pension, staged capital gains realisation. |

**Services injected** (`TaxOptimisationAgent.php:20-23`): `TaxOptimisationService`, `TaxConfigService`.

**Callers.** `CoordinatingAgent.php:154,255,667`; `ModuleSummaryController.php:44,102`; `TaxOptimisationController.php:23,36,61`, routed at `routes/api.php:1153-1154`. Deliberately excluded from the dashboard path: `DailyInsightService.php:41` records that adding it there would put a cost on every dashboard load.

**Cache.** `rememberForUser($userId, 'analysis', …)` at `TaxOptimisationAgent.php:30`, so `v1_taxoptimisationagent_{id}_analysis`. `CacheInvalidationService.php:36-39` notes this agent was reachable only through a now-removed observer, so removing it would have dropped these keys silently.

**Dead methods.** `generateRecommendations` and `buildScenarios` have zero production callers. See section 11.

---

## 9. `CoordinatingAgent` (6,951 lines)

**Purpose.** Two jobs in one class, and the docblock says so (`CoordinatingAgent.php:118-124`). First, it is the cross-module orchestrator: it fans out to the seven module agents, resolves conflicts between their recommendations, ranks them into one list, allocates the cash-flow surplus across competing demands, and generates cross-module strategies. Second, through `use HasAiChat` and `use HasAiGuardrails` (`127-128`) it is the single entry point for Fyn, owning the tool-execution dispatch for roughly 50 AI tools — every create, update, delete and capture Fyn can perform.

### 9a. Aggregation of the other agents

`collectModuleAnalysis` (`645-682`) calls all seven agents in fixed order, each wrapped in `safeModuleAnalysis` (`687-696`) which catches any exception, logs it, and substitutes a typed default so one failing module never fails the plan. Defaults are per-module: `getDefaultModuleAnalysis` (`701`) for five, `getDefaultEstateAnalysis` (`902`) for estate, an inline array for goals and tax.

Each raw result passes through `mappedModuleAnalysis` (`534`), described at `526-533` as "the one place a module's raw agent output becomes the shape the prompt builder reads". It switches on module (`537-602`) into `mapProtectionAnalysis` (`793`), `mapSavingsAnalysis` (`817`), `mapInvestmentAnalysis` (`830`), `mapRetirementAnalysis` (`849`), `mapEstateAnalysis` (`873`). The mappers exist because agents disagree on envelope: Protection, Retirement and Estate use `response()`, while Savings, Investment and Goals return raw arrays. `MobileDashboardAggregator.php:156-161` documents the same split on the mobile side.

### 9b. Cross-module logic it owns

`orchestrateAnalysis` (`365-443`) is the spine:

1. Emits an `EngineCalled('orchestrate_analysis')` entry event (`371-377`) and a matching exit event (`430-440`).
2. Collects module analysis (`380`).
3. Asks `CashFlowCoordinator` for available surplus (`383`).
4. Extracts recommendations (`387`).
5. Identifies conflicts via `ConflictResolver` (`390`).
6. Resolves them (`393`).
7. Ranks them (`396`).
8. Extracts monetary demands and optimises contribution allocation, then identifies shortfalls (`399-401`).
9. Generates cross-module strategies via `CrossModuleStrategyService` (`407`).

`resolveConflicts` (`477-514`) handles exactly three conflict types: protection versus savings, cash-flow, and ISA allowance. The ISA branch reads the allowance from `TaxConfigService::getISAAllowances()` with a hardcoded 20000 fallback (`501-503`) — that literal is the one hardcoded tax value in the file.

`extractDemands` (`741-771`) folds recommendations into monthly demands by category, taking the maximum urgency per category. `mapModuleToCategory` (`776-788`) maps savings to `emergency_fund` and retirement to `pension`.

Net worth is not computed here. It is injected as `NetWorthService` (`157`) and invalidated in `invalidateModuleCache` (`4858`). Household reach is delegated to `DependantsReach` (`168`) and `HouseholdProvisioner` (`162`).

### 9c. Ranking — "one ranking: seeded priority wins"

`rankRecommendations` (`521-524`) is a one-line delegation to `PriorityRanker`. The rule lives entirely in `app/Services/Coordination/PriorityRanker.php`, whose docblock states it directly (`PriorityRanker.php:8-18`): the seeded priority wins, and a numeric benefit plus module weight "only break ties inside a band; together they can never lift a rec across one."

Mechanically: `priorityLabel` (`PriorityRanker.php:94-111`) prefers a string `priority`, then a string `impact`, then an integer `priority` (1-2 high, 3 medium, 4+ low), else medium. That label picks a band from `BANDS` (`PriorityRanker.php:23`): critical 95, high 85, medium 60, low 45. The score is `urgency + impact/20 + weight/100` (`PriorityRanker.php:71`), and `MODULE_WEIGHTS` top out at 80 (`PriorityRanker.php:26-34`), so the tiebreak contribution is capped at 0.8 — below the 15-point gap between adjacent bands. `impactLabel` folds critical into high for the user interface (`PriorityRanker.php:116`).

Two consumers, named in the docblock at `PriorityRanker.php:16-18` and verified: `CoordinatingAgent` (Fyn's financial context, `get_recommendations`, the holistic plan) and `RecommendationsAggregatorService.php:226` (web API, dashboard focus cards, /m next actions).

### 9d. How its output reaches the dashboard and Fyn

**To Fyn — three paths, all through this class.**

- `AiChatController.php:46` injects it; `163` calls `getTokenUsageDetails`, which lives on `HasAiGuardrails.php:235`.
- `FynLoop.php:92` injects it, sets per-turn state on the instance (`133-148`), then streams `chatWithPromptOverride` (`152`) and clears the state in a finally block (`167-176`). `FynLoop.php:37` and `146` both note the agent is container-transient, which is why set and stream must share one instance.
- `OnboardingChatDirector.php:115` injects it and calls `executeTool` at seven sites (`2365`, `2777`, `3581`, `3930`, `6683`, `6684`, `8592`), plus `handleCaptureMonthlyExpenditure` directly (`2539`) and `invalidateUserCache` (`7359`).
- `AdviceFyn.php:206` injects it but never calls a method on it in that file; the analysis reaches the prompt builder as an injected callable — `HasAiChat.php:1622` and `1665` pass `orchestrateAnalysis: fn (int $userId) => $this->analyzeRelevantModules(…)`.

`analyzeRelevantModules` (`220-289`) is the sizing decision. `AdviceFyn::engineCallLevelFor` returns one of three levels (`226`): `holistic` runs the full orchestration; `factual` returns `['module_analysis' => []]`; `module` fans out only to the classified modules (`245-257`) and then ranks (`283-286`). The docblock at `200-219` explains the motive: module-scoped scenarios assert `must_not_contain: orchestrate_analysis`, and the previous behaviour burned holistic compute on every chat send.

**To the dashboards — not through this class.** `DashboardAggregator.php:17-22` and `MobileDashboardAggregator.php:55-60` inject the six module agents directly and call `analyze()` on each, each wrapped in its own try/catch (`DashboardAggregator.php:118-243`). `ModuleSummaryController.php:96-102` does the same for /m module pages, caching under `mobile_module_{module}_{userId}` for 86400 seconds (`63-65`) and stripping financial-quality scores at `73`.

The only HTTP surface that reaches the coordinator's cross-module output is `HolisticPlanningController`, which calls `orchestrateAnalysis` (`46`) and `generateHolisticPlan` (`73`), routed under the `holistic.full` middleware at `routes/api.php:1098-1113`. One further consumer: `DailyInsightService.php:61` calls `analyze($userId)`, which is itself just `orchestrateAnalysis` (`CoordinatingAgent.php:195-198`).

### 9e. Tool execution

`executeTool` (`932-1231`) is the AI write path. It normalises xAI quirks (the string `"null"`, HTML entities, the numeric-zero sentinel for `joint_owner_id` and `trust_id`) at `949-969`, checks the eval bypass gate and preview flag (`980-981`), appends an audit-chain row at dispatch (`986-993`), emits an `AgentDecision` eval event (`996-1007`), then dispatches through a `match` of roughly 50 tool names (`1166-1231`) to private handlers. Read tools (`list_records`, `get_module_analysis`, `get_recommendations`, `get_tax_information`) sit alongside about 30 write handlers.

`handleRecommendations` (`2737-2772`) is where ranking meets Fyn: it runs the full orchestration, composes a tax plan, records fetch provenance, and returns `ranked_recommendations` plus the composed plan.

### 9f. Constructor

Twenty-three injected services at `CoordinatingAgent.php:142-169`: five coordination services (`ConflictResolver`, `PriorityRanker`, `HolisticPlanner`, `CashFlowCoordinator`, `CrossModuleStrategyService`), all seven module agents (`148-154`), then `TaxConfigService`, `AiToolDefinitions`, `NetWorthService`, `PrerequisiteGateService`, `ComposedTaxPlanService`, `TeaserGate`, `CaptureAccuracyGate`, `HouseholdProvisioner`, `SubscriptionStatusService`, `HouseholdExpenditureWriter`, `DependantsReach`. Roughly 100 further classes are imported for runtime use (`CoordinatingAgent.php:7-116`).

### 9g. Cache

`invalidateUserCache` (`186-190`) overrides the base to also call `CacheInvalidationService::invalidateForUser`. The docblock at `171-185` explains why, and explicitly corrects itself: this is no longer the only writer, since `UserDataCacheObserver` invalidates on every financial model write, so this call is "the belt to that observer's braces" (W-0239).

The agent does not cache its own orchestration. Its `Cache` use is short-lived working state: capture-accuracy evidence with a 15-minute time-to-live (`1122`, `1488`, `1513`), tax-information lookups (`2831`, `2839`), and the forget calls in `invalidateModuleCache` (`4856-4878`).

---

## 10. What the agents share

**The base contract.** All eight concrete agents extend `BaseAgent` and implement `analyze`, `generateRecommendations`, `buildScenarios`. Verified at `GoalsAgent.php:18`, `RetirementAgent.php:39`, `EstateAgent.php:32`, `SavingsAgent.php:31`, `ProtectionAgent.php:21`, `TaxOptimisationAgent.php:18`, `InvestmentAgent.php:31`, `CoordinatingAgent.php:125`.

**`FormatsCurrency`.** Via `BaseAgent.php:13`, inherited by all.

**`TaxConfigService`.** Injected by Estate (`57`), Investment (`42`), Retirement (`50`), TaxOptimisation (`22`), Coordinating (`155`); resolved at runtime by Savings (`547`). Protection and Goals do not use it.

**`CalculatesOwnershipShare`.** Investment (`33`) and Savings (`33`) only. Estate reaches joint records through `EstateAssetAggregatorService` instead.

**The response envelope split.** Protection, Retirement, Estate and TaxOptimisation return `BaseAgent::response()`. Savings, Investment and Goals return raw arrays. Both consumer sides carry unwrap logic: `CoordinatingAgent`'s five mappers, `MobileDashboardAggregator.php:161`, `DashboardAggregator.php:126,148,170,197,224`, and `ToolResultContract.php:115`.

**The readiness gate.** Five agents open `analyze()` with a `DataReadinessService::assess()` call and return a fully-keyed null response when `can_proceed` is false: Protection (`46-64`), Savings (`66-89`), Investment (`87-107`), Retirement (`79-104`), Estate (`84-102`). Goals and TaxOptimisation have none, by design (`PrerequisiteGateService.php:166,183`). `EvalDeltaBuilder.php:462` calls this "the agent's secondary profile gate" and names `ProtectionAgent` line 72 and `RetirementAgent` line 101 as the reference sites.

**`missing_for_quality_advice`.** All six module agents (not TaxOptimisation) compute a private `findMissingForQualityAdvice` and publish the result under that key: Estate (`351`, `407`, `434`), Goals (`73`, `91`, `112`), Investment (`186`, `223`, `249`), Protection (`174`, `269`, `298`), Retirement (`277`, `293`, `368`, `466`), Savings (`185`, `224`, `251`).

**Eval telemetry.** Every module agent imports `App\Events\Eval\EngineCalled` and emits it around `analyze` and `generateRecommendations`. Protection and Retirement additionally import `GateChecked` (`ProtectionAgent.php:8`, `RetirementAgent.php:9`). Coordinating emits `EngineCalled` and `AgentDecision`.

**The recommendation array shape.** There is no DTO class. The shape is an array convention: a `priority` or `impact` label, `title`, `description`, `action`, `category` or `type`, and one of the benefit keys. `PriorityRanker` then adds `module`, `priority_score`, `urgency_score`, `impact_score`, `impact_label` and `timeline` (`PriorityRanker.php:66-74`). Benefit keys are read in first-present order: `estimated_impact`, `estimated_saving`, `estimated_annual_tax_saved`, `potential_benefit` (`PriorityRanker.php:40`). Compare `TaxOptimisationAgent.php:60-68` with `GoalsAgent.php:161-168` to see the convention rather than a contract.

**Recommendation generation splits three ways.** Database-driven action definitions for Savings (`296`), Investment (`301`) and Retirement (`524`); pass-through of what `analyze()` already built for Protection (`348`); hand-written rules for Goals (`151`), Estate's seven steps (`489`) and TaxOptimisation's mapping (`53`).

---

## 11. Dead or duplicated

**`TaxOptimisationAgent::generateRecommendations` (`53`) and `::buildScenarios` (`80`) — dead.** Grepping `taxOptimisationAgent->` and `taxAgent->` across `app/` and `tests/` returns five call sites, all `analyze`. `RecommendationsAggregatorService` injects six module agents (`33-38`) but not this one, and `TaxOptimisationController` calls only `analyze` (`36`, `61`). The two methods exist solely to satisfy the `BaseAgent` abstract contract.

**`CoordinatingAgent::buildScenarios` (`349`) — a stub.** It returns `['message' => 'Cross-module scenarios not yet implemented', 'scenarios' => []]` (`353-356`) and has no callers outside the class.

**`CoordinatingAgent::generateRecommendations` (`341`), `::resolveConflicts` (`477`) and `::rankRecommendations` (`521`) — public but internal-only.** `resolveConflicts` and `rankRecommendations` are called from `orchestrateAnalysis` (`393`, `396`); `generateRecommendations` has no caller at all. Greps for `coordinatingAgent->` variants of these three return nothing in `app/` or `tests/`.

**`BaseAgent::invalidateCacheForUsers` (`111`) — test-only.** Only callers are `tests/Unit/Agents/BaseAgentTest.php:151,163`.

**The `$cacheTags` fourth argument is discarded.** `BaseAgent::remember` takes three parameters (`BaseAgent.php:45`). Four call sites build a `$cacheTags` array and pass it as a fourth positional argument: `EstateAgent.php:113,410`, `ProtectionAgent.php:68,272`, `RetirementAgent.php:108,295`, `RetirementAgent.php:832,836`. PHP accepts extra positional arguments to userland methods without error, so the tags are silently dropped and no tag-based invalidation exists. I verified the signature and all four call sites by reading them; I did not execute code to confirm the runtime behaviour.

**`CoordinatingAgent::invalidateModuleCache` (`4856-4878`) forgets keys nothing writes.** It forgets `v1_savings_{id}`, `v1_investment_{id}`, `v1_retirement_{id}`, `v1_property_{id}`, `v1_protection_{id}`, `v1_estate_{id}` and their `_analysis`/`_recommendations` variants (`4861-4873`), plus `v1_coordinating_{id}_analysis` (`4875`). `BaseAgent::getUserCacheKey` (`58-63`) uses the lowercased class basename, producing `v1_savingsagent_{id}_…` and `v1_coordinatingagent_{id}_…`. The agents that use a bare key use `protection_analysis_{id}`, `savings_analysis_{id}`, `investment_analysis_{id}`, `retirement_analysis_{id}` — also no match. Same class of key-name mismatch that `EstateAgent.php:105-111` documents as W-0381, in a different method. The practical effect is masked because `invalidateUserCache` (`186-190`) delegates to `CacheInvalidationService`, which does use the correct names (`CacheInvalidationService.php:95-107`).

**Duplication between `ProtectionAgent` and `ProtectionStrategySource`.** `ProtectionStrategySource.php:44` states it builds "gaps + profile exactly as ProtectionAgent::analyze() does", and `72` records that its own docblock once claimed to be the agent "but the agent was routed" elsewhere. Two mechanisms produce the same protection analysis. I read only the docblock comments, not the full body of `ProtectionStrategySource`, so COULD NOT VERIFY the behavioural equivalence.

**`HasAiChat` and `HasAiGuardrails` are used only by `CoordinatingAgent`** (`127-128`). `HasAiChat` is itself large and carries the chat loop (`chat()` at `HasAiChat.php:385`), the override setters (`139`, `198`, `204`, `228`, `2164`, `2182`) and the tool-call loop that calls back into `executeTool` (`HasAiChat.php:946`). Functionally the chat surface is part of `CoordinatingAgent` even though it lives in `app/Traits/`.

---

## 12. Files to look at

- `/Users/CSJ/Desktop/fynla/app/Agents/BaseAgent.php` — the contract and cache-key convention
- `/Users/CSJ/Desktop/fynla/app/Agents/CoordinatingAgent.php` — orchestration (`192-790`), tool execution (`925-6790`)
- `/Users/CSJ/Desktop/fynla/app/Services/Coordination/PriorityRanker.php` — the one ranking rule
- `/Users/CSJ/Desktop/fynla/app/Services/Cache/CacheInvalidationService.php` — the cache-key contract
- `/Users/CSJ/Desktop/fynla/app/Services/Dashboard/DashboardAggregator.php` and `/Users/CSJ/Desktop/fynla/app/Services/Mobile/MobileDashboardAggregator.php` — the web and /m dashboard paths
- `/Users/CSJ/Desktop/fynla/app/Http/Controllers/Api/V1/Mobile/ModuleSummaryController.php` — the /m module path for all seven agents
- `/Users/CSJ/Desktop/fynla/app/Traits/HasAiChat.php` — the chat loop that drives `executeTool`

Nothing was fixed.
