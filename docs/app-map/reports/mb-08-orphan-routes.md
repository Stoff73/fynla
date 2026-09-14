# MB-08 — orphan API routes: route-by-route dossier

- Repository: `/Users/CSJ/Desktop/fynla`
- Branch and commit: `dev` at `28194e804`
- Date: 2026-09-14
- Scope: the routes listed in mapping bug MB-08 (`September/September14Updates/mappingBugs2026-09-14.md:63-78`; `docs/app-map/00-overview.md` § 3 and Appendix C)

## Plain-English summary

Fynla's backend exposes a list of web addresses (API routes) that the three apps (the desktop website, the `/m` mobile website and the iPhone app) call to read and write data. An "orphan route" is one of those addresses that none of the three apps, nor the public marketing pages, ever calls any more. The code behind it is still maintained and still has tests, so it costs time and can give false confidence that a feature works when in fact no user can reach it. This dossier looked at each of the routes flagged by the application map, read the code behind them, traced in the version history when each was added and when its last caller was removed, searched every client for a caller, and listed what would have to go with it. The outcome across the 25 route rows examined: 22 are recommended for deletion, 1 turned out to be live and must stay (`POST api/net-worth/refresh`), 2 need a decision from CSJ before anything is done (the household planning routes, which relate to a feature sold on the pricing page, and the public persona list, which the workforce constitution names as canonical), and none is worth keeping for a planned client or wiring up as it stands. Three claims in the original mapping brief were found to be wrong and are marked in the table.

## Method and caveats

Read-only. Nothing was edited beyond this file, nothing was created or committed, no migration or test suite was run. The only commands executed were `php artisan route:list`, `git log`, `git show` and text searches. No Pest file was run, so nothing below is "verified" by test execution; "covered by" means the named test file contains an HTTP call to the route.

- Callers: whole-repository fixed-string search for each path fragment, excluding `node_modules`, `vendor`, `storage`, `public/build`, `public/m-build` and `.git`. Swift paths were matched on `path: "api/..."` literals; `/m` paths on `apiGet/apiPost('/api/...')` calls; web paths on `api.get/post(...)` calls and the service files that wrap them.
- Git history: `git log -S` over `routes/`, `app/Http/Controllers`, `resources/js`, `resources/mobile`, `ios-native`, `public/pages` and `public/js`. Four literals (`dashboard/alerts`, `household/net-worth`, `net-worth/breakdown`, `net-worth/joint-assets`) return no commits because the routes are declared inside prefix groups (`'/alerts'`, `'/breakdown'`) and the clients built the path from a base constant; for those the controller method name and the client service file were traced instead.
- Three findings contradict the brief and are marked in the table: `POST api/net-worth/refresh` is live; `api/advisor/reports` has no API caller (the `AdvisorLayout.vue` hit is a Vue router link, not an HTTP call); `DashboardAggregator::aggregateAlerts()` is live via the `/m` dashboard.

## Summary table

| # | Route | Status | Overlap with | Recommendation |
|---|---|---|---|---|
| 1 | GET api/dashboard | dead-only-caller (`dashboardService.js`) | GET api/v1/mobile/dashboard (web, /m, iOS) | DELETE |
| 2 | GET api/dashboard/alerts | dead-only-caller | alerts inside api/v1/mobile/dashboard via `MobileDashboardAggregator::getAlerts()` | DELETE route; keep `DashboardAggregator::aggregateAlerts()` |
| 3 | POST api/dashboard/alerts/{id}/dismiss | dead-only-caller | none; it only forgets a cache key | DELETE |
| 4 | POST api/dashboard/invalidate-cache | dead-only-caller | server-side invalidation already fires from observers and stores | DELETE |
| 5 | POST api/holistic/analyze | dead-only-caller (`CrossModuleInsights.vue`) | GET api/holistic/composite-plan (web, /m, iOS) | DELETE |
| 6 | POST api/holistic/plan | orphan | composite-plan; api/plans/* | DELETE |
| 7 | GET api/holistic/cash-flow-analysis | orphan | composite-plan (`CompositePlanService` uses `CashFlowCoordinator`) | DELETE |
| 8 | api/holistic/recommendations (six routes) | orphan | api/recommendations/* on the same table (web, /m, iOS) | DELETE |
| 9 | api/household/net-worth, death-scenario, optimisations | orphan, but the capability is sold on the pricing page | net-worth/overview spouse branch (partial) | UNSURE (WIRE-UP or DELETE plus capability retirement) |
| 10a | GET api/net-worth/breakdown | orphan | net-worth/overview returns the same breakdown | DELETE |
| 10b | GET api/net-worth/joint-assets | dead-only-caller (store action never dispatched) | none exact | DELETE |
| 10c | POST api/net-worth/refresh | **has live caller** (contradicts brief) | n/a | NOT ORPHAN, keep |
| 11a | GET api/tax/strategies | orphan | api/tax-strategy (web, /m, iOS); mobile/modules/tax | DELETE |
| 11b | GET api/tax/optimisation-analysis | orphan | same | DELETE |
| 11c | GET api/tax-info/summary | orphan | tax-info/investment/{type}, tax-info/savings/{type} (live) | DELETE method only |
| 12 | PUT api/user/dashboard-widget-order | orphan | none; feature removed 2026-01-13 | DELETE |
| 13 | GET/POST api/user/guidance-status, POST api/user/seed-persona-data | orphan | preview/login flow and `PreviewUserSeeder` | DELETE (seed endpoint also bypasses the spouse-invitation model) |
| 14 | GET erasure/status, POST erasure/{id}/cancel, POST erasure/{id}/confirm | orphan (never had a client) | erasure/initiate, verify, execute, resend-code, cancel-scheduled (web + iOS) | DELETE |
| 15 | GET api/payment/trial-status | orphan tombstone (`abort(404)`) | payment/subscription-status | DELETE, with one caveat |
| 16 | GET api/preview/personas | orphan (public, unauthenticated) | preview store bundles the JSON locally; landing page uses fixed ids | UNSURE |
| 17 | GET api/postcode-lookup/{postcode} | orphan (`PostcodeLookup.vue` does not call it either) | none | DELETE |
| 18 | GET api/v1/mobile/modules/{module} | orphan since 2026-09-09 | api/{module} endpoints the /m views now call | DELETE |
| 19 | GET api/v1/mobile/insights/daily | orphan | `fyn_insight` in api/v1/mobile/dashboard via shared `DailyInsightService` (W-0478) | DELETE route and controller; keep service |
| 20 | GET api/v1/native/storekit/status | orphan (never had a client) | GET api/v1/native/entitlement (iOS calls) | DELETE |
| 21 | GET api/advisor/reports | orphan (contradicts brief) | GET api/advisor/activities?activity_type=suitability_report | DELETE |

Totals: DELETE 22, NOT ORPHAN (keep) 1, UNSURE 2, WIRE-UP 0, KEEP-FOR-PLANNED-CLIENT 0.

---

## 1–4. `api/dashboard` group — `DashboardController`

Routes: `routes/api.php:469-474`.

### What it does
- `index` (`app/Http/Controllers/Api/DashboardController.php:27-44`) caches `DashboardAggregator::aggregateOverviewData()` for 24 hours under `dashboard_{userId}`. The aggregator (`app/Services/Dashboard/DashboardAggregator.php:28-42`) calls `analyze()` on the Protection, Savings, Investment, Retirement and Estate agents (`:17-22`, `:121-243`) and returns one summary per module.
- `alerts` (`:49-66`) caches `aggregateAlerts()` (`DashboardAggregator.php:74-95`), which merges each module's alerts and sorts by severity.
- `dismissAlert` (`:71-85`) only forgets the `alerts_{userId}` cache key. It does not record a dismissal anywhere, so the alert returns on the next build.
- `invalidateCache` (`:90-104`) calls `CacheInvalidationService::invalidateForUser()` (`app/Services/Cache/CacheInvalidationService.php:87`).

### Who was meant to call it
- Added in the initial commit `6446f4632 2025-10-14 Initial commit: Financial Planning System (FPS) v2`.
- Last client: `resources/js/store/modules/dashboard.js` imported `dashboardService`; the import and the four calls were removed in `7392ec0e8 2026-05-23 chore(audit): Quick Wins from tech-debt review (Q1, Q3–Q9 + B34)` (diff lines removing `dashboardService.getDashboardData()`, `getAlerts()`, `dismissAlert()`, `fetchAllDashboardData()`).
- The web dashboard moved to the mobile payload: `resources/js/views/Dashboard.vue:110` mounts `GamifiedDashboard`, which calls `api.get('/v1/mobile/dashboard')` at `resources/js/views/GamifiedDashboard.vue:451`.

### Callers found
- Only `resources/js/services/dashboardService.js:4,18,32,47` (dead per the brief; no importer found in `resources/js` or `resources/mobile`). Note the file sets `API_BASE_URL = '/api/dashboard'` on top of an axios `baseURL` that already ends in `/api` (`resources/js/services/api.js:64`), so even when it was imported it requested `/api/api/dashboard`.
- Nothing in `resources/mobile`, `ios-native/Fynla`, `public/pages`.
- Docs: `docs/app-map/00-overview.md:190,337`; `September/September14Updates/mappingBugs2026-09-14.md:67`. No plan intends a client to use it.

### Tests
- `tests/Feature/Dashboard/DashboardApiTest.php` (14 blocks: 8 on `/api/dashboard`, 7 on `/alerts`, 2 on `/alerts/1/dismiss`, 2 on `/invalidate-cache`).
- `tests/Integration/DashboardIntegrationTest.php:139-163`.
- `tests/Feature/AdminRBACTest.php:122,131` uses `/api/dashboard` as a generic "authenticated user gets 200" probe.

### Data written
None persisted. Cache keys `dashboard_{userId}`, `alerts_{userId}` only. `invalidate-cache` forgets the keys listed at `CacheInvalidationService.php:89-160`.

### Overlap
`GET api/v1/mobile/dashboard` (`routes/api_v1.php:129`) is called by web (`GamifiedDashboard.vue:451`), /m (`resources/mobile/views/Dashboard.vue:654`) and iOS (`ios-native/Fynla/Features/Dashboard/DashboardClient.swift:18`). Its aggregator reuses the alerts half of the old aggregator: `app/Services/Mobile/MobileDashboardAggregator.php:61` injects `DashboardAggregator` and `:562` calls `aggregateAlerts()`.

### Recommendation: DELETE
Routes, `DashboardController`, `DashboardAggregator::aggregateOverviewData()`, `safeSummary()` and the five `get*Summary()` helpers (`DashboardAggregator.php:28-72, 251-410`), `resources/js/services/dashboardService.js`, `DashboardApiTest.php`, `DashboardIntegrationTest.php`, and re-point `AdminRBACTest.php:122,131` at a live route. **Keep** `DashboardAggregator::aggregateAlerts()` and the `get*Alerts()` helpers, `get*Analysis()` helpers and the class itself: the /m dashboard depends on them (`MobileDashboardAggregator.php:562`). `CacheInvalidationService` stays (used by observers and stores).

---

## 5–8. `api/holistic` group (excluding `composite-plan`) — `HolisticPlanningController`

Routes: `routes/api.php:1095-1111`, behind `auth:sanctum` and `holistic.full` (`app/Http/Kernel.php:170` → `EnsureFullHolisticAccess`, which returns a structured 403 unless `TeaserGate::isFull($user, 'holistic_plan')`, `app/Http/Middleware/EnsureFullHolisticAccess.php:28-34`). `CheckSubscription.php:51` also maps `api/holistic` to the `holistic_plan` capability.

### What it does
- `analyze` (`HolisticPlanningController.php:37-53`): caches `CoordinatingAgent::orchestrateAnalysis()` (`app/Agents/CoordinatingAgent.php:363`) for one hour under `holistic_analysis_{userId}`.
- `plan` (`:60-84`): caches `CoordinatingAgent::generateHolisticPlan()` (`CoordinatingAgent.php:449-468`, which runs `orchestrateAnalysis`, `HolisticPlanner::createHolisticPlan` and `PriorityRanker::createActionPlan`) for 24 hours, and when freshly generated calls `storeRecommendations()` (`:296-316`), which **deletes every `pending` row** in `recommendation_tracking` for the user and re-inserts the plan's ranked recommendations with fresh UUIDs.
- `recommendations` (`:91-105`): reads active `RecommendationTracking` rows ordered by `priority_score`.
- `cashFlowAnalysis` (`:112-152`): `CashFlowCoordinator::calculateAvailableSurplus`, `optimizeContributionAllocation`, `identifyCashFlowShortfalls`, `createCashFlowChartData`, `getMonthlyFinancials`, `calculateSustainableContributions` (`app/Services/Coordination/CashFlowCoordinator.php:35,56,148,331,187,378`), with demands derived from tracking rows (`:323-348`).
- `markRecommendationDone`, `markRecommendationInProgress`, `dismissRecommendation`, `completedRecommendations`, `updateRecommendationNotes` (`:178-291`): status and notes writes on `RecommendationTracking` by numeric `id`, with `invalidateForUser` on done/dismiss.
- `compositePlan` (`:163-171`) is live and outside this brief.

### Who was meant to call it
- Added `2ece512a3 2025-10-15 feat: Implement Task 13 - Coordinating Agent and Holistic Planning System`.
- The full client (`holisticService.js` with analyze, plan, recommendations, cash-flow, mark-done, in-progress, dismiss, completed, notes) was deleted in `e2230efc5 2026-03-04 feat: rewrite holistic plan to aggregate individual module plans` (nine `-` lines in the diff), when the Holistic plan page was rebuilt on module plans.
- `analyze` got a second caller in `a9d5b2f1c 2026-03-07` (`CrossModuleInsights.vue:133`); that component was unmounted from the dashboard in `33edb16a0 2026-03-17 chore: remove Cross-Module Insights section from dashboard` (`resources/js/views/Dashboard.vue` still carries the comment `// CrossModuleInsights removed from dashboard`, confirmed in `ce74507e6 2026-07-13`).
- The `holistic.full` gate was added `6e4067b01 2026-06-27` to the whole group, after the callers were already gone.

### Callers found
- `resources/js/components/Dashboard/CrossModuleInsights.vue:87` posts `/holistic/analyze` (dead; no importer).
- Live clients call only `composite-plan`: `resources/js/services/holisticService.js:16`, `resources/mobile/views/HolisticPlan.vue:171`, `ios-native/Fynla/Features/HolisticPlan/HolisticPlanClient.swift:27`.
- Docs: `mappingBugs2026-09-14.md:68`; `docs/superpowers/plans/2026-06-15-cross-module-plan-composer.md:998` (composite-plan only). No plan intends a client for the eleven routes.

### Tests
- `tests/Feature/Tiers/PremiumCapabilityEnforcementTest.php:29,53` uses `POST /api/holistic/analyze` as the "Holistic Plan" capability probe (free denied, premium allowed).
- `tests/Feature/Middleware/CheckSubscriptionTest.php:68` uses `POST /api/holistic/analyze` as the stale-capability probe.
- `tests/Feature/CrossModuleIntegrationTest.php:202,259` hits `cash-flow-analysis` and `plan`.
- No test hits the six `holistic/recommendations*` routes.

### Data written
`recommendation_tracking` (`app/Models/RecommendationTracking.php`): `plan` deletes pending rows and inserts; the five tracking routes update `status`, `completed_at`, `notes`. Caches `holistic_analysis_`, `holistic_plan_`.

### Overlap
- `GET api/holistic/composite-plan` is the live successor for analyse/plan/cash-flow (`CompositePlanService` injects `CashFlowCoordinator`, `app/Services/Coordination/CompositePlanService.php:32`).
- The recommendation tracking routes duplicate `api/recommendations/*` (`routes/api.php:1114-1128`, `RecommendationsController.php:160-320`), which operate on the same table and are called by web (`resources/js/views/Actions/ActionsDashboard.vue:183,204`; `GamifiedDashboard.vue:428`), /m (`/api/recommendations/`) and iOS (`DashboardClient.swift:37`, `TaxStrategyClient.swift:36`). Difference: the holistic routes address rows by numeric `id`; the live ones by string `recommendationId` and create a row if missing (`RecommendationsController.php:165-181`).
- `orchestrateAnalysis()` stays live regardless: Fyn reads it (`app/Services/AI/AdvicePromptBuilder.php:88,172`, `app/Services/AI/Fyn/FynContextAssembler.php:64,220`, `app/Traits/HasAiChat.php:1569`).

### Recommendation: DELETE
The eleven routes, the ten controller methods and the three private helpers (`storeRecommendations`, `extractDemandsFromTracking`, `mapModuleToCategory`), `CrossModuleInsights.vue`, and the constructor's `CoordinatingAgent`, `CashFlowCoordinator`, `CacheInvalidationService` injections (`HolisticPlanningController.php:26-28`) once only `compositePlan` remains. Retarget `PremiumCapabilityEnforcementTest.php:29,53` and `CheckSubscriptionTest.php:68` at `GET /api/holistic/composite-plan` (same gate) and delete the two `CrossModuleIntegrationTest` cases. Blast radius otherwise nil: `CoordinatingAgent`, `CashFlowCoordinator`, `RecommendationTracking`, `EnsureFullHolisticAccess` all keep live users. A side effect worth knowing: `plan` is the only code path that bulk-deletes `pending` rows; removing it removes that hazard.

---

## 9. `api/household/*` — `HouseholdController`

Routes: `routes/api.php:1088-1092`, `auth:sanctum`; `CheckSubscription.php:52` maps `api/household` to the `joint_household_view` capability.

### What it does
- `getNetWorth` (`HouseholdController.php:27-49`) → `HouseholdPlanningService::calculateHouseholdNetWorth()` (`app/Services/Coordination/HouseholdPlanningService.php:138`): combines the user's and the consenting spouse's assets and liabilities, de-duplicating joint assets.
- `getOptimisations` (`:57-81`) → `generateSpousalOptimisations()` (`:244`): compares both spouses' income tax positions and recommends transfers, ISA splitting and pension balancing; returns `[]` without a consenting spouse (`:251-253`).
- `getDeathScenario` (`:90-121`) → `modelDeathOfSpouseScenario()` (`:328`): NRB/RNRB transfer, joint assets, pension death benefits and life cover for `?spouse=primary|partner`, using `TaxConfigService` (`:342-345`); single users get `singlePersonScenario()`.

### Who was meant to call it
- Added `160a6ee26 2026-03-07 feat: complete Tax Optimisation Agent and Household Coordination (P2 Workstreams 4 & 6)` alongside `2eb52ec0f` the same day. No commit in the searched paths ever added a `household/` client call: `git log -S'household/'` over `resources/js`, `resources/mobile`, `ios-native`, `public` returns nothing.

### Callers found
None in any client. `goalsService.js:191` calls `/goals/household-summary`, a different controller. Docs: `mappingBugs2026-09-14.md:69`; `workforce/ops/handoffs/quality-lead/pest-full-run-2026-08-23.txt:8650-8660` (test output only). No plan names a client.

### Tests
- `tests/Feature/Household/HouseholdApiTest.php` (11 blocks across the three routes).
- `tests/Feature/Tiers/PremiumCapabilityEnforcementTest.php:34,58` uses `GET /api/household/net-worth` as the "joint household view" capability probe.
- `tests/Unit/Services/Coordination/HouseholdPlanningServiceTest.php` and `SecondDeathRnrbTaperTest.php:8` test the service, not the route.

### Data written
None.

### Overlap
- `GET api/net-worth/overview` (live on web, /m, iOS) already returns the consenting spouse's totals and breakdown (`NetWorthController.php:95-104`), which covers the "combined household" number but not the optimisations or death scenario.
- The public pricing page sells this: `public/pages/pricing.php:189` renders "Combined household view — Not included / Included", driven by `matrix.joint_household_view` at `public/pages/js/pricing.js:158`; the seeded matrix sets it `none` on Free and `full` on Premium (`database/seeders/TierConfigurationSeeder.php:43,86`). `HouseholdPlanningService` has no other user (`grep` finds only the service and two `.md` files).

### Recommendation: UNSURE
What decides it: whether "Combined household view" on the pricing page means the net-worth overview spouse panel (already live) or the three-endpoint household analysis (never surfaced). If the former, DELETE: routes, `HouseholdController`, `HouseholdPlanningService` (only user), `HouseholdApiTest.php`, the two unit tests, the `api/household` entry at `CheckSubscription.php:52`, and retarget `PremiumCapabilityEnforcementTest.php:34,58`. If the latter, WIRE-UP: a Coordination or Net Worth screen on web and /m (Rule 19) should call `household/net-worth` and `household/optimisations`; the death scenario overlaps Estate and would need its own product decision. This is a CSJ product call because a sold capability is involved.

---

## 10. `api/net-worth/breakdown`, `joint-assets`, `refresh` — `NetWorthController`

Routes: `routes/api.php:387-397`.

### What it does
- `getBreakdown` (`NetWorthController.php:125-146`) → `NetWorthService::getAssetBreakdown()` (`app/Services/NetWorth/NetWorthService.php:247-270`): runs `calculateNetWorth()` and returns each asset type with value and percentage of total assets.
- `getJointAssets` (`:177-198`) → `NetWorthService::getJointAssets()` (`:580`): lists joint properties (and other joint holdings) with `ownership_percentage` and co-owner name.
- `refresh` (`:230-256`): `NetWorthService::invalidateCache()` then `calculateNetWorth()` and returns the fresh figure.

### Who was meant to call it
- All three added in `1f7b64130 2025-10-18 feat: Release v0.1.0 (Alpha)` with `netWorthService.getBreakdown()` and `getJointAssets()` and a store consumer.
- `getBreakdown()` was removed from `resources/js/services/netWorthService.js` in `4f959be52 2026-04-09 fix: tech debt audit — 45 issues fixed across 58 files`.
- `refresh` gained its `OnboardingWizard` caller in `d6b731bda 2026-03-17 fix: refresh net worth cache after onboarding completion`; `edf7e70f2 2026-03-18` moved that raw `api.post` into `netWorthService.refresh()`.

### Callers found
- **`POST api/net-worth/refresh` is live.** `resources/js/services/netWorthService.js:42-43` (`refresh()`), called from `resources/js/components/Onboarding/OnboardingWizard.vue:847` (routed via `OnboardingFullView.vue` and `OnboardingModuleView.vue`, `router/index.js:483-503`) and from the store action `netWorth/refreshNetWorth` (`resources/js/store/modules/netWorth.js:268-274`), which is dispatched after writes in `savings.js:291,323,343`, `investment.js:444,465,486`, `retirement.js:451-600`, `estate.js:441-479`, `chattels.js:86-124`, `businessInterests.js:79-117` and `aiChat.js:777`. `resources/js/CLAUDE.md:27` documents it as the cross-module convention. The brief's whole-repo grep missed it because the path is built as `${API_BASE}/refresh`.
- `GET api/net-worth/joint-assets`: `netWorthService.js:34-35` and the store action `fetchJointAssets` (`netWorth.js:226-231`) exist, but nothing dispatches or maps `fetchJointAssets` (grep over `resources/js` excluding the store: no hits). Dead-only-caller chain.
- `GET api/net-worth/breakdown`: no caller anywhere.
- /m and iOS call `overview`, `assets-summary-detailed`, `forecast` only (`resources/mobile` apiGet list; `ios-native/Fynla/Features/NetWorth/NetWorthClient.swift:34,41`).

### Tests
`tests/Feature/Api/NetWorthControllerTest.php:69` (breakdown), `:280` (joint-assets), `:300` (refresh).

### Data written
`refresh` writes only the net-worth cache. The others write nothing.

### Overlap
`GET api/net-worth/overview` returns `data.breakdown` (the service's `calculateNetWorth()` output, `NetWorthService.php:249-250`; controller comment `NetWorthController.php:82`), so `breakdown` adds only percentages. No live route lists joint assets by co-owner.

### Recommendation
- `refresh`: NOT ORPHAN. Keep; remove it from the MB-08 list.
- `breakdown`: DELETE route, `getBreakdown()`, `NetWorthService::getAssetBreakdown()` (its only other mention, `CrossModuleAssetAggregator.php:427`, is a different method on a different class), and `NetWorthControllerTest.php:69` block.
- `joint-assets`: DELETE route, controller method, `netWorthService.getJointAssets()`, store action `fetchJointAssets` and its mutation, `NetWorthService::getJointAssets()` (only user), and the test block at `:280`. If a joint-assets panel is wanted later, `assets-summary-detailed` is the live shape to extend.

---

## 11. `api/tax/strategies`, `api/tax/optimisation-analysis`, `api/tax-info/summary`

### What it does
- `TaxOptimisationController::getAnalysis` (`app/Http/Controllers/Api/Tax/TaxOptimisationController.php:31-49`) and `getStrategies` (`:56-80`) both call `TaxOptimisationAgent::analyze()` (`app/Agents/TaxOptimisationAgent.php:28`); `getStrategies` returns only `strategies`, `total_estimated_saving`, `strategy_count`.
- `TaxProductInfoController::getTaxSummary` (`app/Http/Controllers/Api/TaxProductInfoController.php:68-79`) → `TaxProductInfoService::getTaxSummary()` (`app/Services/Tax/TaxProductInfoService.php:91-102`): status counts and a primary tax status for a product type, from `TaxProductReference`.

### Who was meant to call it
- Tax routes added `160a6ee26 2026-03-07` with `taxOptimisationService.js` (`api.get('/tax/optimisation-analysis')`, `/tax/strategies`); those two service methods were removed in `4f959be52 2026-04-09` (tech debt audit).
- `tax-info/summary` added `f9527460c 2025-12-16 feat: Add Tax Status tab to Investment and Savings detail views` with `taxInfoService.getTaxSummary()`; that service file was deleted in `2a99d28b1 2026-02-26 refactor: remove dead code, unused components, and duplicate utilities`.

### Callers found
None for the three. The sibling `tax-info/investment/{type}` and `tax-info/savings/{type}` are live (`resources/js/components/Common/TaxStatusPanel.vue:156,158`, `NetWorth/InvestmentProjections.vue:1019`, `Investment/AccountPerformancePanel.vue:844`). Docs: `mappingBugs2026-09-14.md:71`; pest output in `workforce/ops/handoffs/quality-lead/pest-full-run-2026-08-23.txt:10532-10537`. No plan names a client.

### Tests
`tests/Feature/Tax/TaxOptimisationApiTest.php` (4 blocks on optimisation-analysis, 2 on strategies). None for `tax-info/summary`.

### Data written
None.

### Overlap
The SaveTax Tax Strategy dashboard `GET api/tax-strategy` (`routes/api.php:381-383`, `TaxStrategyController` with `ComposedTaxPlanService`) is called by web, /m (`/api/tax-strategy`) and iOS (`TaxStrategyClient.swift`). `TaxOptimisationAgent` itself stays live through `CoordinatingAgent.php:152` and `ModuleSummaryController.php:44`.

### Recommendation: DELETE
Both `tax` routes, the whole `TaxOptimisationController` (no other routes use it), `TaxOptimisationApiTest.php`; and the `tax-info/summary` route plus `getTaxSummary()` on controller and service (`TaxProductInfoService::determinePrimaryStatus` becomes unused with it). Keep `TaxOptimisationAgent`, `TaxProductInfoController` and the two live tax-info routes.

---

## 12. `PUT api/user/dashboard-widget-order` — `UserProfileController@updateDashboardWidgetOrder`

Route: `routes/api.php:321`.

### What it does
`UserProfileController.php:394-409` validates `widget_order[]` against a fixed list of nine widget ids and writes `users.dashboard_widget_order` (array cast, `app/Models/User.php:198`; column added by `database/migrations/2026_01_12_115104_add_dashboard_widget_order_to_users.php`).

### Who was meant to call it
Added with the draggable dashboard in `e71566f42 2026-01-12 feat: Add draggable dashboard widgets and fix IHT message styling` (`Dashboard.vue` `api.put('/user/dashboard-widget-order')`). Removed the next day in `b65280a14 2026-01-13 feat: Dashboard redesign with combined cards and actions panel`, which deleted the read (`user.dashboard_widget_order`) and the write.

### Callers found
None. Docs: `mappingBugs2026-09-14.md:72`. No plan.

### Tests
None (fixed-string grep for `dashboard_widget_order` and `dashboard-widget-order` in `tests/` returns nothing).

### Data written
`users.dashboard_widget_order`.

### Overlap
None; the dashboard has had no user-ordered widgets since 2026-01-13.

### Recommendation: DELETE
Route, method, the `dashboard_widget_order` cast and fillable entry on `User`, and a migration dropping the column (also in `database/schema/mysql-schema.sql:4092,4386`). Nothing else references the column.

---

## 13. `api/user/guidance-status` (GET, POST) and `POST api/user/seed-persona-data` — `PreviewController`

Routes: `routes/api.php:353-355`.

### What it does
- `getGuidanceStatus` (`PreviewController.php:336-348`) returns the user's `guidance_*` columns; `updateGuidanceStatus` (`:355-386`) writes `guidance_active`, `guidance_current_step`, `guidance_completed_steps`, `guidance_skipped_steps`, `guidance_version` and derives `guidance_completed` from a hard-coded eight steps (`:368`).
- `seedPersonaData` (`:272-329`) loads a persona JSON (`storage personas/{id}.json` or `resources/js/data/personas/{id}.json`, `:391-410`) and, in one transaction, seeds profile, properties, mortgages, savings, investments (with holdings), DC/DB/state pensions, life, critical-illness and income-protection policies, liabilities and family members onto the **authenticated real user** via the Stores with `IngestSource::SEEDER` (`:453-461`), then sets `preview_persona_kept` and resets the guidance columns (`:307-314`). With `create_spouse_account`, `createSpouseAccount()` (`:668-711`) creates a second `User` with a random password, `registration_source = 'preview_spouse'`, and **links by writing `spouse_id` on both rows directly** (`:691-692`) before seeding the spouse's pensions, savings, investments and life cover.

### Who was meant to call it
Added `ae20716af 2025-12-12 feat: Preview mode fixes and rebrand to Fynla` with a `guidance` Vuex store (`api.get/post('/user/guidance-status')`) and a "keep persona data" step in `Register.vue` (`api.post('/user/seed-persona-data')`). The register caller was removed in `8ea27e345 2026-01-19 fix(register): Remove KeepDataOrFreshModal - all users go directly to onboarding`. The guidance store, `GuidanceTooltip.vue` and `GuidanceWelcomeModal.vue` were deleted in `4f959be52 2026-04-09`.

### Callers found
None. Docs: `mappingBugs2026-09-14.md:72`. No plan.

### Tests
None (fixed-string grep for `guidance-status`, `guidance_active`, `seed-persona` in `tests/` returns nothing).

### Data written
`seed-persona-data`: `users` (many profile columns, `preview_persona_kept`, `guidance_*`, `spouse_id`), `properties`, `mortgages`, `savings_accounts`, `investment_accounts`, `holdings`, `dc_pensions`, `db_pensions`, `state_pensions`, `life_insurance_policies`, `critical_illness_policies`, `income_protection_policies`, `liabilities`, `family_members`, and a new `users` row for the spouse. `guidance-status`: the six `guidance_*` columns on `users`.

### Overlap
Preview personas are served by `POST api/preview/login/{personaId}` against seeded preview users (`PreviewUserSeeder`); guidance has no successor.

### Recommendation: DELETE
Routes, `seedPersonaData`, `getGuidanceStatus`, `updateGuidanceStatus`, `loadPersonaJson` and the fifteen `seed*`/`createSpouseAccount` private methods (`PreviewController.php:272-711`), the Store and Normaliser imports they alone use in that file (`:18-28`), the guidance casts on `User.php:193-195`, and a migration for the `guidance_*` and `preview_persona_kept` columns (`mysql-schema.sql:4089-4098`; their creating migration is not in `database/migrations/`, only in the schema dump). Adjacent hazard to report: the seed endpoint is authenticated and not preview-gated, so any logged-in user could inflate their own account with persona records and create a directly-linked spouse account, bypassing the invitation model recorded in memory as the fix of 2026-08-23. Deleting it closes that.

---

## 14. Legacy GDPR erasure — `GET erasure/status`, `POST erasure/{id}/cancel`, `POST erasure/{id}/confirm`

Routes: `routes/api.php:223-227` under the comment "Legacy erasure endpoints (deprecated, kept for backwards compatibility)". `POST api/auth/gdpr/erasure` (`requestErasure`, `:224`) is in the same block and is equally orphaned though not in the brief's list.

### What it does
- `getErasureStatus` (`GDPRController.php:246-272`) returns the latest `erasure_requests` row for the user.
- `confirmErasure` (`:277-306`) marks a pending row confirmed and completed with `processedBy = 'legacy_endpoint'`, then calls `AccountDeletionService::deleteAccount($user, 'user_requested', 'gdpr_legacy')` (`:300`): an **immediate, irreversible account deletion** with no email code or confirmation phrase, unlike the live flow.
- `cancelErasure` (`:311-337`) cancels a non-terminal row.
- `requestErasure` (`:197-241`) creates a pending row and audit-logs it.

### Who was meant to call it
Added `b39833c6e 2026-01-19 feat: Comprehensive security compliance implementation` (routes only; the diff adds no JavaScript caller for `erasure/status`, `{id}/confirm` or `{id}/cancel`). The self-service flow that clients use arrived two days later in `1467927ea 2026-01-21 feat: Add self-service account/data deletion`. `45a0a2d5e 2026-05-07 refactor(gdpr): repoint executeErasure to AccountDeletionService; add cancel-scheduled` rewired `confirmErasure` to the deletion service and labelled it legacy. `git log -S'erasure/status'` and `-S'getErasureStatus'` over the client paths return nothing: no client ever called these three.

### Callers found
None. Live clients call `initiate`, `verify`, `resend-code`, `execute`, `cancel-scheduled`: `resources/js/services/privacyService.js:63-113`; `ios-native/Fynla/Features/Privacy/AccountDeletionClient.swift:27-69`. `PreviewWriteInterceptor.php:72` excludes only `cancel-scheduled`. Docs: `mappingBugs2026-09-14.md:73`.

### Tests
`tests/Feature/Auth/GDPRApiTest.php:172-181` (status), `:130-190` (`POST /erasure` request). None for confirm or cancel.

### Data written
`erasure_requests` (`app/Models/ErasureRequest.php`, created by `2026_01_19_140001_create_erasure_requests_table.php`); `confirm` deletes the account. `ErasureRequest` is referenced only from `GDPRController.php:215-313` and `User.php:629`; the live `initiate/verify/execute` path (`:368-600`) does not use the table.

### Overlap
The five live endpoints above. `cancel-scheduled` is the live cancel.

### Recommendation: DELETE
The four legacy routes (including `POST /erasure`), the four methods, `ErasureRequest` model, `User::erasureRequests()`, the `ErasureRequest` constants used only there, a migration dropping `erasure_requests`, and the two `GDPRApiTest` blocks. Reason beyond tidiness: `confirmErasure` is an authenticated one-call path to permanent deletion without the verification the live flow enforces; an idle destructive endpoint is a liability. Check whether any admin report reads `erasure_requests` before dropping (grep of `app/` finds no reader).

---

## 15. `GET api/payment/trial-status` — closure

Route: `routes/api.php:1160`, `Route::get('/payment/trial-status', fn () => abort(404));`, public.

### What it does
Returns 404 unconditionally.

### Who was meant to call it
Added `c870ae632 2026-02-12` (Revolut trial flow) and heavily used by `AppLayout`, the auth store and the tier guard through spring 2026 (`7bcbcb6e9 2026-03-31`, `2690f945c 2026-05-29`). The banner caller went in `c69e03db7 2026-05-29`; every remaining JS caller and the controller method were removed in `5a35bd5a9 2026-07-15 feat: establish freemium economic API foundation` (six `-` lines), which pointed the route at `subscriptionStatus`. `c4834a9b2 2026-07-16 refactor(subscription): prepare retired trial schema removal` replaced that with `abort(404)`.

### Callers found
None. Docs: `docs/superpowers/plans/2026-05-29-pure-freemium-signup.md:319-425` (repurpose, since superseded); `mappingBugs2026-09-14.md:74`.

### Tests
`tests/Feature/Payment/SubscriptionStatusTest.php:94-101` asserts 404 for guests and authenticated users.

### Data written
None.

### Overlap
`GET api/payment/subscription-status` (live on web and /m).

### Recommendation: DELETE, with one caveat
The tombstone exists for a reason the code does not state: `routes/web.php:786-788` is a `/{any}` catch-all with no `api` exclusion, so an unregistered `GET /api/payment/trial-status` would return the SPA HTML with 200 to a stale bundle instead of a JSON 404. Every client caller has been gone since 2026-07-15. Delete the closure and the two assertions together, accepting that a cached pre-July bundle would now receive HTML; if CSJ wants the JSON 404 preserved, keep both as they are. Blast radius: nothing else.

---

## 16. `GET api/preview/personas` — `PreviewController@getPersonas`

Route: `routes/api.php:265`, public, unthrottled.

### What it does
`PreviewController.php:260-265` returns `PERSONA_METADATA` (`:60-116`): nine entries, six personas plus three spouse views, with name, tagline, description.

### Who was meant to call it
Added `ed4c03d1d 2025-12-15 feat: Major codebase cleanup and Fynla rebrand`. The same commit's JavaScript loads personas from bundled JSON (`import.meta.glob('../../data/personas/*.json')`), not from the API, and `resources/js/store/modules/preview.js:20-40` still imports the six JSON files directly. `684e909bf 2026-03-30` added `getPersonasForStage()` in a Vue page, unrelated to the route. No commit ever added a client fetch.

### Callers found
None. The landing page posts to `preview/login/{personaId}` with fixed ids (`public/pages/index.php:475-476`). Documents that rely on it: `workforce/core/constitution/01-mission.md:72-74` declares `PreviewController::VALID_PERSONAS / PERSONA_METADATA` canonical "because it is what `GET /api/preview/personas` returns"; `workforce/ops/board/W-0001.md:71` used the route as a verification probe.

### Tests
`tests/Feature/Middleware/TouchSessionActivityTest.php:34-39` uses it as "a public API route with no user" for the middleware no-op check.

### Data written
None.

### Overlap
The preview store's bundled JSON and the landing page's hard-coded cards carry the same six personas; the three spouse-view entries exist nowhere else.

### Recommendation: UNSURE
Deciding factor: whether the constitution's "canonical source" clause needs an HTTP surface or only the PHP constant. If only the constant, DELETE the route and `getPersonas()` (keep `PERSONA_METADATA`, which `login`/`switch` validate against), amend `01-mission.md:73` and retarget `TouchSessionActivityTest.php:35` at another public route such as `GET /api/pricing-config`. If the workforce wants a live probe for persona drift, KEEP and say so in the route comment. Cost of keeping is one unauthenticated, unthrottled GET.

---

## 17. `GET api/postcode-lookup/{postcode}` — `PostcodeLookupController@lookup`

Route: `routes/api.php:1483-1484`, `auth:sanctum` and `throttle:30,1`.

### What it does
`PostcodeLookupController.php:40-153` normalises and validates a UK postcode, reads `services.getaddress.api_key` (`config/services.php:56-58`, env `GETADDRESS_API_KEY`, absent from `.env.example`), proxies `https://api.getaddress.io/autocomplete/{postcode}` with a 10-second timeout (`:76-77`), caches results for an hour (`:33,92`) and returns parsed address lines.

### Who was meant to call it
Added `299f96baa 2026-01-22 feat: Add UK postcode lookup for address forms` with `postcodeService.js` and `PostcodeLookup.vue` used in four address forms. The four form imports were removed in `2f0794823 2026-02-06 feat: Feb 6 deployment` (diff: twelve `-` lines removing `<PostcodeLookup`, its import and registration). `postcodeService.js` (the only HTTP caller, `api.get(\`/postcode-lookup/...\`)`) was deleted in `2a99d28b1 2026-02-26`.

### Callers found
None. The brief says the dead `PostcodeLookup.vue` calls it; it does not: the surviving component is a plain input that emits `update:modelValue` and has no HTTP call (`resources/js/components/Shared/PostcodeLookup.vue:59-93`; grep for `api.`, `fetch`, `/postcode` in the file returns nothing). `AssetForm.vue:266` keeps a `postcodeValue` data field with a comment but registers no component (`:234` `components: {}`). Docs: `workforce/ops/reports/2026-08-21-consistency-sweep.md:710` (route drift note); `mappingBugs2026-09-14.md:74`.

### Tests
None (grep for `PostcodeLookup` and `postcode-lookup` in `tests/` returns nothing).

### Data written
None; cache only.

### Overlap
None. Address entry is manual on every surface.

### Recommendation: DELETE
Route, `PostcodeLookupController`, `PostcodeLookup.vue`, the `postcodeValue` field and comment in `AssetForm.vue:266`, and the `getaddress` block in `config/services.php:56-58`. No other class references any of them.

---

## 18. `GET api/v1/mobile/modules/{module}` — `ModuleSummaryController@show`

Route: `routes/api_v1.php:153-155`, `auth:sanctum`, `etag`, `throttle:mobile-dashboard`.

### What it does
`ModuleSummaryController.php:52-88` validates the module against seven names (`:27-35`), caches the matching agent's `analyze()` output for 24 hours under `mobile_module_{module}_{userId}` (`:63-70`, invalidated at `CacheInvalidationService.php:151-156`), strips score keys with `removeScores()` (`:112-138`) and returns `{module, summary, cached_at}`.

### Who was meant to call it
Added `7e799755b 2026-03-10 feat: add ModuleSummaryController and InsightsController for mobile API (P1-09b)`. Wired to the /m module drill-down in `738715c2e 2026-05-25 feat(mobile): real placeholder dashboard + wired module drill-downs` (`apiGet(\`/api/v1/mobile/modules/${this.slug}\`)`); `docs/mobile/designer-brief.md:373` records it live on prod on 2026-05-27. The caller was removed five days ago in `0a2c14f9d 2026-09-09 fix(m): retire the module-detail scaffold — every /module/{slug} link lands on the real view`; `resources/mobile/router.js:62` now redirects `/module/:slug` to the real module views, which call `api/savings`, `api/investment`, `api/retirement`, `api/protection`, `api/estate`, `api/goals`, `api/tax-strategy` directly.

### Callers found
None in `resources/mobile` or `ios-native/Fynla` (no Swift `path: "api/v1/mobile/modules`). Docs still describing it as current: `docs/mobile/designer-brief.md:203,260,295,373`; `docs/plans/phase1-deploy-notes.md:16,108-109`; `docs/plans/mobileAppTask.md:246`; board items `W-0341`, `W-0342`, `F-0027` used it as a diagnostic probe. No plan intends a new client.

### Tests
`tests/Feature/Mobile/ModuleSummaryTest.php` (7 blocks, agents mocked); `tests/Feature/Contracts/ClientCompatibilityContractTest.php:184-205` ("freezes every shared mobile module envelope", one case per module); `tests/Feature/Mobile/MobileCacheCoherenceTest.php:28-61` and `tests/Feature/Cache/DerivedFiguresInvalidateOnDataChangeTest.php` assert the `mobile_module_*` cache keys are forgotten.

### Data written
None; cache only.

### Overlap
Each module's own API, now called by the /m views; `api/v1/mobile/dashboard` for summaries.

### Recommendation: DELETE
Route, `ModuleSummaryController`, `ModuleSummaryTest.php`, the module-envelope case in `ClientCompatibilityContractTest.php:184-205`, the `mobile_module_*` forget loop at `CacheInvalidationService.php:151-156` and its assertions in `MobileCacheCoherenceTest.php` and `DerivedFiguresInvalidateOnDataChangeTest.php`, and the four doc references. Two things need CSJ's hand: `CLAUDE.md` Rule 12 names `ModuleSummaryController::removeScores()` as the score-stripping mechanism, so the rule text must be updated by its owner; and the contract test's purpose is to freeze payloads native clients depend on, so its author should confirm iOS never adopted this envelope (grep says it did not).

---

## 19. `GET api/v1/mobile/insights/daily` — `InsightsController@daily`

Route: `routes/api_v1.php:158-160`.

### What it does
`InsightsController.php:31-41` returns `DailyInsightService::daily($userId)` (`app/Services/Mobile/DailyInsightService.php:57-73`): caches `CoordinatingAgent::analyze()` for 24 hours under `mobile_insight_daily_{userId}`, composes the candidate insights and selects one, or a fallback on failure. The controller docblock (`:26-29`) records W-0478.

### Who was meant to call it
Added `7e799755b 2026-03-10`. `git log -S'insights/daily'` over `resources/js`, `resources/mobile`, `ios-native`, `public` returns nothing: no client ever called it. `W-0478` (`workforce/ops/board/W-0478-...md:32-37`) documented that in August: "No caller in `resources/`, `resources/mobile/`, or `ios-native/`". CSJ's decision on 2026-08-24 (`:67-75`) was "wire the clients to the endpoint, the richer mechanism wins", implemented instead as one shared `DailyInsightService` read by both the endpoint and the dashboard payload's `fyn_insight` (`MobileDashboardAggregator.php:66,96-97`), because /m and native already read `fyn_insight` from the dashboard. Merged as #714, commit `cd8d5c4aa`.

### Callers found
None. Docs: `docs/plans/phase1-deploy-notes.md:17,110`; `docs/mobile/designer-brief.md:261`; `W-0473:119`; `W-0478`.

### Tests
`tests/Feature/Mobile/InsightsTest.php` (11 blocks, 12 HTTP calls, `CoordinatingAgent` mocked). W-0478 acceptance 4 says this file "follows whichever mechanism survives".

### Data written
None; cache `mobile_insight_daily_{userId}` (not in the `CacheInvalidationService` forget list; grep finds no invalidation of that key).

### Overlap
`fyn_insight` inside `GET api/v1/mobile/dashboard`, consumed by /m (`resources/mobile/views/Dashboard.vue:559` per W-0478) and iOS (`DashboardView.swift:122` per W-0478).

### Recommendation: DELETE route and controller; keep the service
W-0478 acceptance 1 required "either a client is wired to the endpoint or the endpoint is deleted"; the resolution consolidated the composer but left the endpoint standing with no client. Delete the route, `InsightsController`, and `DailyInsightService::daily()` plus its uninvalidated cache key; move the behavioural assertions in `InsightsTest.php` onto `DailyInsightService::compose()`/`select()` (or `MobileDashboardAggregator`), which is where the sentences live. Blast radius: `DailyInsightService::compose()`/`select()` stay live for the dashboard; `CoordinatingAgent::analyze()` unaffected. Because W-0478 was CSJ's own decision, cite it in the PR rather than treating this as a fresh call.

---

## 20. `GET api/v1/native/storekit/status` — `AppleReconciliationController@status`

Route: `routes/api_v1.php:107-115`, `native.client`, `native.version`, `auth:sanctum`, `native.session`, `throttle:sensitive`.

### What it does
`AppleReconciliationController.php:60-75` refuses preview users and users without an `apple_app_account_token`, then **invalidates** the cached entitlement (`$this->entitlements->invalidate($user)`, `:72`) and returns `PremiumEntitlementResolver::resolve()` in the same `statusResponse()` shape the live `reconcile` endpoint uses (`:77-91`).

### Who was meant to call it
Added `8cb25fadc 2026-07-18 feat: process app store server notifications` (routes only). `git log -S'storekit/status' -- ios-native` returns nothing; the iOS client has never called it. The mapping doc's "may be intended for iOS work not yet written" is unsupported by any plan document: no hit in `docs/` or `workforce/` other than `mappingBugs2026-09-14.md:75`.

### Callers found
None. iOS `SubscriptionAPI.swift` calls `purchase-authorization` (`:34`), `account-token` (`:44`), `transactions` (`:60`), `reconcile` (`:76`), and reads entitlement via `entitlement()` (`:10`) against `GET api/v1/native/entitlement` (`routes/api_v1.php:83-85`, `NativeEntitlementController`).

### Tests
`tests/Feature/Native/Billing/AppleReconciliationApiTest.php:28-39` (route registration and middleware boundary for both reconcile and status) and `:80-85` (status returns `provider apple`, `status active` after a reconcile).

### Data written
None persisted; it clears the entitlement cache.

### Overlap
`GET api/v1/native/entitlement` returns the same resolver output without the forced invalidation (`NativeEntitlementController.php:22-37`). The only extra behaviour is cache-busting, which `reconcile` already performs through `AppleReconciliationService`.

### Recommendation: DELETE
Route, `status()`, the `status` half of the registration assertion (`AppleReconciliationApiTest.php:32-39`) and the `:80-85` block. Keep `statusResponse()` and `forbiddenResponse()` (used by `reconcile`). If iOS ever needs "re-resolve now", the reconcile call already does it.

---

## 21. `GET api/advisor/reports` — `AdvisorController@reports`

Route: `routes/api.php:1543`, `auth:sanctum` and `advisor`.

### What it does
`AdvisorController.php:194-205` calls `ClientActivityService::listForAdvisor($advisor, ['activity_type' => 'suitability_report'])` (`app/Services/Advisor/ClientActivityService.php:65`) and returns the list. It is the `activities` endpoint (`:150-159`, same service call with the request's filters) with one filter fixed.

### Who was meant to call it
Added `edeab8b1f 2026-03-17 feat: advisor Vuex store, API service, and router integration` with `advisorService.getReports()` (`api.get('/advisor/reports')`), per the spec `docs/superpowers/specs/2026-03-17-admin-advisor-design.md:483` ("Suitability reports list, filtered from activities where activity_type = 'suitability_report'"). `advisorService.getReports()` was removed in `4f959be52 2026-04-09` (diff line `-        const response = await api.get('/advisor/reports');`).

### Callers found
None for the API. The brief's `AdvisorLayout.vue` hit is a Vue `<router-link to="/advisor/reports">` (`resources/js/layouts/AdvisorLayout.vue:96-99`) to the SPA route `AdvisorReports` (`router/index.js:1449`). That page fetches through `advisorService.getActivities({ activity_type: 'suitability_report' })` (`resources/js/views/Advisor/AdvisorReports.vue:99`), which calls `GET /advisor/activities` (`advisorService.js:62`). `advisorService.js` has no `reports` call (`:13-125`). /m and iOS have no advisor surface.

### Tests
`tests/Feature/Api/AdvisorControllerTest.php:37` (non-advisor gets 403) and `:184-189` (advisor gets `success: true`).

### Data written
None.

### Overlap
`GET api/advisor/activities?activity_type=suitability_report`, live, identical result.

### Recommendation: DELETE
Route, `reports()`, and the two assertions in `AdvisorControllerTest.php`. `ClientActivityService::listForAdvisor()` stays (used by `activities`, `:153`). Mark the brief's "confirm and if so mark NOT orphan" as resolved the other way: orphan.

---

## Cross-cutting notes for the deletion PR

- **Not orphan, remove from the list:** `POST api/net-worth/refresh` (section 10c).
- **Live code the deletions must not touch:** `DashboardAggregator::aggregateAlerts()` and the alert/analysis helpers (/m dashboard); `CoordinatingAgent`, `CashFlowCoordinator`, `TaxOptimisationAgent`, `RecommendationTracking`, `DailyInsightService::compose()/select()`, `PremiumEntitlementResolver`, `ClientActivityService`, `TaxProductInfoController`'s two per-type routes, `EnsureFullHolisticAccess` and the `api/holistic` entry in `CheckSubscription` (composite-plan is gated by them).
- **Tests that use an orphan route as a probe for something else** and need retargeting rather than deletion: `AdminRBACTest.php:122,131`, `PremiumCapabilityEnforcementTest.php:29,34,53,58`, `CheckSubscriptionTest.php:68`, `TouchSessionActivityTest.php:35`, `ClientCompatibilityContractTest.php:184-205`.
- **Documents that describe deleted routes as current:** `docs/mobile/designer-brief.md`, `docs/plans/phase1-deploy-notes.md`, `docs/plans/mobileAppTask.md`, `CLAUDE.md` Rule 12 (`removeScores`), `workforce/core/constitution/01-mission.md:73`. `resources/js/CLAUDE.md:27` is correct as it stands (refresh is live).
- **Two adjacent findings outside the brief:** `POST api/auth/gdpr/erasure` (`requestErasure`) belongs to the same legacy block and is equally uncalled; `POST api/user/seed-persona-data` writes `spouse_id` directly on two accounts, which the invitation model was introduced to prevent.
