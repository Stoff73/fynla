---
type: tech-debt-audit
scope: full-codebase
date: 2026-05-23
previous: 2026-04-18
generator: tech-debt-full skill
---

# Full Codebase Tech Debt Report

**Date:** 23 May 2026
**Codebase:** Fynla v1.0 (dev branch at `99400ce`, csjones in sync)
**Files scanned:** 671 Vue components, 327 PHP services, 115 controllers, 114 models, 224 migrations, 52 factories, 27 seeders, 37 Vuex stores, 53 frontend services, 492 tests
**Total issues:** 178
**Previous report:** 18 April 2026 (101 issues, 7 critical) — re-baselined here after 5 weeks of SP1 Pass 2 / SP2 / SP3 work

## Executive Summary

| Severity | Count | Apr 18 |
|----------|-------|--------|
| **Critical** | 14 | 7 |
| **High** | 7 | — (new tier this run) |
| **Warning** | 76 | 58 |
| **Suggestion / Info** | 81 | 36 |
| **Total** | **178** | **101** |

| Category | Count |
|----------|-------|
| God files / complexity | 56 (34 Vue + 22 PHP) |
| Dead code | 33 surfaces (1 service + 2 controller methods + 30 model scopes + 2 Vuex modules + 73 actions + 12 mutations + 83 service methods) |
| Convention / inconsistency | 37 |
| Duplicate code | 11 |
| Security / vulnerability | 16 (7 composer CVEs + 8 npm + 1 frontend bug) |
| Schema / data | 14 (factories, scopes, soft-delete drift, schema dump staleness) |
| Score / icon (Rule #13 / #14 / #16) | 9 |

### Trend since 18 April

**Net debt grew from 101 → 178.** The growth is driven by:
1. A deeper Vuex / frontend-services scan that surfaced 73 orphan actions + 12 mutations + 83 dead service methods (none had been catalogued before — largely invisible to the April scope).
2. A new tier of **Critical Rule #13 / Rule #14 violations** in the Investment / NetWorth surfaces (9 score badges + 1 chrome-less routed view) that didn't exist or weren't flagged in April.
3. **7 fresh Composer CVEs** disclosed by Symfony on 2026-05-20 (3 days ago) — `symfony/mailer`, `symfony/mime` (×2), `symfony/routing`, `symfony/yaml`, etc. All fixable by `composer update symfony/*` to the patched versions.
4. Two carry-overs from prior reports that were never fixed: `IHTStrategyGeneratorService.php` (489 lines, flagged twice, still in repo) and `TaxSettingsController::getCalculations()` hardcoded display strings (W1 from May 22 session report).

**Improvements since April:**
- `declare(strict_types=1)` now universal (was ~90%) — 0 misses in 895 PHP files.
- Canonical enums clean (`'sole'` literal absent from `app/` and `database/`).
- No component imports `axios` directly.
- `currencyMixin` discipline holds — 212 components use it, zero define a local `formatCurrency()`.
- All 25 ApexCharts components consistently consume `designSystem.js` (no inline palette drift in chart components, only in views).
- Backend frontend tax-constant parity: `app/Constants/TaxDefaults.php` ↔ `resources/js/constants/taxConfig.js` match exactly.
- Reference-data store discipline established (SP1 Pass 2): `TaxConfigStore`, `ActuarialLifeTableStore`, `CurrencyRateStore`, `SavingsMarketRateStore` all behind locked allowlists. Only `TaxProductReferenceSeeder` remains on the legacy `DB::table` pattern.

### Quick wins (trivial effort, high impact)

1. **Delete `app/Services/Estate/IHTStrategyGeneratorService.php`** — 489 lines, zero callers, flagged twice in prior reviews. 30 seconds.
2. **Fix `LetterEstateWarnings.vue:125`** — change `'/api/estate/letter-validation'` → `'/estate/letter-validation'`. Silent 404 in production. 30 seconds.
3. **Replace SavingsApiTest:313 joint-ISA-success test** with a `'rejects is_isa=true + ownership_type=joint with 422'` test, then add validation in the savings controller / FormRequest. Removes a regression that endorses an illegal product shape. 15 minutes.
4. **`composer update symfony/*` to patched versions** — 5 minutes of dependency work covers all 7 CVEs.
5. **Delete the `dashboard` and `household` Vuex modules** + remove from `store/index.js` — both entirely unreferenced, ~110 lines each. 5 minutes.
6. **Delete `AssetLocationController::clearUserAssetLocationCache` and `InvestmentController::calculateAccountAnnualisedReturn`** — both marked deprecated/internal, zero callers. 2 minutes each.
7. **Add `user_id` to PropertyFactory / MortgageFactory / ChattelFactory** definitions — un-breaks `Factory::create()` cold calls. 5 minutes.
8. **Run `php artisan schema:dump`** — refreshes a 3-months-stale schema dump. 1 minute, but unblocks every future migration audit.

### High priority (all Criticals + Highs)

| # | Severity | Issue | File | Effort |
|---|----------|-------|------|--------|
| C1 | Critical | Joint-ISA test endorses illegal product creation | `tests/Feature/Savings/SavingsApiTest.php:313-333` | small |
| C2 | Critical | Routed view ships chrome-less (Rule #14) | `resources/js/views/NetWorth/CashOverview.vue` | small |
| C3–C10 | Critical | 8× Rule #13 score-badge violations in Investment surfaces | `Investment/PlanSections/{RiskAnalysis,FeeAnalysis,TaxStrategy}Section.vue`, `Investment/AssetLocationOptimizer.vue`, `Investment/DiversificationTab.vue`, `Investment/TaxOptimizationOverview.vue`, `views/Investment/PortfolioStrategyPanel.vue`, `views/Investment/AccountRebalancingPanel.vue` | medium |
| C11 | Critical | Dead service flagged twice, never removed | `app/Services/Estate/IHTStrategyGeneratorService.php` | trivial |
| C12 | Critical | Duplicate net-worth calc that CrossModuleAssetAggregator was meant to retire | `app/Services/Estate/NetWorthAnalyzer.php` ↔ `app/Services/NetWorth/NetWorthService.php` | medium |
| C13 | Critical | 20 Vue god components > 1200 lines (top: TaxSettings 3068, ExpenditureForm 2574, CalculatorsPage 2490, Dashboard 2247) | (see § Vue) | large |
| C14 | Critical | 14 backend god services > 800 lines (top: SavingsActionDefinition 3690, RetirementActionDefinition 2719, OnboardingChatDirector 2669) | (see § Backend) | large |
| H1 | High | Silent 404 in letter-validation API call (`api.get('/api/estate/...')` double-prefixes) | `resources/js/components/Estate/LetterEstateWarnings.vue:125` | trivial |
| H2 | High | 7 Symfony CVEs disclosed 2026-05-20 (mailer / mime ×2 / routing / yaml / etc.) | `composer.lock` | trivial |
| H3 | High | 8 npm vulnerabilities (4 high, 4 moderate) | `package-lock.json` | small |
| H4 | High | `database/schema/mysql-schema.sql` is stale by 3 months (last recorded migration 2026-02-23, actual through 2026-05-22) — invalidates any tool that trusts it for column presence | `database/schema/mysql-schema.sql` | trivial |
| H5 | High | 11 financial models have `deleted_at` columns but don't `use SoftDeletes` — every `delete()` is still a hard delete despite the migration | Mortgage, Property, Goal, SavingsAccount, CashAccount, Investment/Holding, Subscription, Estate/Will, LifeInsurancePolicy, DBPension, DCPension | medium |
| H6 | High | 3 factories missing `user_id` despite NOT NULL FK — `Factory::create()` cold throws SQL integrity error | `PropertyFactory`, `MortgageFactory`, `ChattelFactory` (+ `BusinessInterestFactory` likely) | low |
| H7 | High | 5 god controllers > 800 lines mixing HTTP / business / transactional logic | `PaymentController` 1159, `InvestmentController` 1074, `AdminController` 893, `AuthController` 871, `GoalsController` 792 | large |

---

## Detailed Findings by Module

### Backend Services (332 files scanned)

**Clean:** strict_types universal, no `App\Http\` imports, no `'sole'` enum drift, DB facade use bounded to legitimate cases.

**Critical**

- **`app/Services/Estate/IHTStrategyGeneratorService.php`** (489 lines) — Zero references in app/, tests/, routes/, config/, resources/. Previously flagged in April/April5Updates/codeReview.md (B-5) and May/May12Updates/review-conventions.md. Delete.
- **`app/Services/Estate/NetWorthAnalyzer.php`** ↔ **`app/Services/NetWorth/NetWorthService.php`** — Two net-worth calculators co-exist; `Shared/CrossModuleAssetAggregator` docblock says it was created to "eliminate duplication" but the two services were never collapsed. Fold Estate-specific manual Asset/Liability merging into NetWorthService, retire NetWorthAnalyzer, repoint EstateController + ComprehensiveEstatePlanService + NetWorthAnalyzerTest.

**God services >800 lines (14)**

| File | Lines | Methods | Notes |
|------|-------|---------|-------|
| `Savings/SavingsActionDefinitionService.php` | 3690 | 61 | Largest service in codebase. Split per evaluator family. |
| `Retirement/RetirementActionDefinitionService.php` | 2719 | 32 | Same evaluator-per-trigger split as Savings. |
| `Onboarding/OnboardingChatDirector.php` | 2669 | 51 | The canonical onboarding writer. Extract capture handlers into a `CaptureDispatcher`. |
| `Protection/ProtectionActionDefinitionService.php` | 2350 | 42 | Same per-trigger split. |
| `Retirement/RetirementIncomeService.php` | 2303 | 23 | Split per income source projector. |
| `Retirement/RetirementStrategyService.php` | 2154 | 33 | Strategy generators into `app/Services/Retirement/Strategies/`. |
| `Estate/IHTCalculationService.php` | 1657 | 34 | Extract per-asset projectors. |
| `AI/AiToolDefinitions.php` | 1640 | 21 | + `XaiToolDefinitions` (1159) is a vendor-format duplicate. Collapse via shared per-tool registry. |
| `Investment/InvestmentActionDefinitionService.php` | 1486 | 30 | Same evaluator pattern. |
| `Onboarding/OnboardingService.php` | 1409 | 21 | Split progress / spouse / step transitions / completion. |
| `AI/AdvicePromptBuilder.php` | 1345 | 23 | On the deletion path per unified-prompt contract — confirm vs canonical 00-canonical.md before refactor. |
| `Estate/ComprehensiveEstatePlanService.php` | 1313 | 20 | Compose, don't duplicate, the per-section builders. |
| `Onboarding/AssetCaptureEntityExtractor.php` | 1258 | — | Split per asset-type extractor. |
| `AI/XaiToolDefinitions.php` | 1159 | — | Collapse with AiToolDefinitions. |

Plus ~22 more services in the 500–1100 range — see prior cross-cutting "largest files" output. None individually critical.

**Convention / inconsistency**

- **`AI/AdvicePromptBuilder.php:1253-1275`** — Hand-rolls income/expenditure resolution; bypasses canonical `ResolvesIncome` / `ResolvesExpenditure` traits used by 20+ services. When user has only `annual_expenditure`, hand-rolled version returns 0 → wrong tax-band estimation in Fyn advice.
- **`Goals/GoalCalculationService.php`** ↔ **`Goals/GoalProgressService.php`** — Both compute progress %, days/months remaining, on-track status against the same Goal model. GoalProgressService inlines the calc rather than delegating.
- **`Coordination/PriorityRanker.php` / `HolisticPlanner.php` / `Dashboard/DashboardAggregator.php`** — Hardcoded £100k / £200k significance thresholds duplicated 12+ times across 3 services. Promote to a `SignificanceThresholds` constants class.
- **`Investment/MonteCarloSimulator.php:143-204`** — Uses `DB::table('monte_carlo_cache')` directly. Should be an Eloquent model with `expires_at` cast.
- **`Estate/PersonalizedTrustStrategyService.php:167,468`** + **`GiftingStrategyOptimizer.php:292`** — Same `$cltLifetimeRate = (float) ($ihtConfig['chargeable_lifetime_transfers']['lifetime_rate'] ?? 0.20);` lookup repeated 3 times. Wrap in `TaxConfigService::getCLTLifetimeRate(): float`.
- **`LifeStage/LifeStageService.php:105`** — Hardcoded £200,000 pension-value threshold. Promote to private const.

---

### Controllers & HTTP (280 PHP files: 118 controllers, 96 FormRequests, 33 Resources, 29 middleware)

**Clean:** strict_types universal, route URL naming consistently kebab-case, PreviewWriteInterceptor EXCLUDED_ROUTES up to date with 2026-04→05 auth additions (restore endpoints correctly listed).

**Warning — God controllers**

| File | Lines | Notes |
|------|-------|-------|
| `Api/PaymentController.php` | 1159 | Subscription activation, plan changes, billing cycle, upgrade/cancel/reactivate, Revolut verification, `DB::transaction` + `lockForUpdate` at lines 473, 883. Split into SubscriptionController + PaymentVerificationController + SubscriptionLifecycleService. |
| `Api/InvestmentController.php` | 1074 | Account CRUD, holdings CRUD, EIS/SEIS/CSOP/SAYE auto-date calculation, holdings cash auto-creation inside DB::transaction (378-417). |
| `Api/AdminController.php` | 893 | 9 logical surfaces, 8 inline `$request->validate` schemas (no FormRequests). Split per surface; extract all validation. |
| `Api/AuthController.php` | 871 | Imports 8 model classes directly, owns register / verify-code / resend-code / login / logout / password / restorability. Extract RegistrationService + LoginFlowService. |
| `Api/GoalsController.php` | 792 | 6 inline validate calls. Split off Goals/ProjectionController + Goals/ForecastController. |
| `Api/RetirementController.php` | 790 | `DB::transaction` at lines 322, 390 wrapping DC pension create + holdings — duplicates the InvestmentController pattern. |

**Warning — Convention**

- **Inter-controller dependency injection** — `LifePolicyController` and `GiftingController` both inject `IHTController` and call `$this->ihtController->calculateIHT($request)` 5× across the two files, then call `getData(true)` to decode the response. Calculation logic in a controller, consumed by other controllers via HTTP-shaping round trip. Extract to `IHTPlanningPayloadBuilder` service.
- **DB facade in HTTP layer** (7 controllers): `InvestmentController:378`, `Retirement/DCPensionHoldingsController:204,219,229`, `WebhookController:77`, `FamilyMembersController:208,332`, `PaymentController:473,883`, `PreviewController:283`, `RetirementController:322,390`. All wrap business logic in `DB::transaction` — push transactions into services.
- **Static helper method on controller**: `AssetLocationController::clearUserAssetLocationCache(int $userId)` — defined on a controller, intended to be called by other controllers. Zero callers. Dead.
- **Hardcoded tax fallbacks**: `Investment/AssetLocationController.php:247-297` substitutes 2026/27 values (`12570`, `50270`, `125140`, `20000`, `0.20`, `0.04`) as `?? defaults` when TaxConfigService returns null. Defeats the whole point of centralised tax config. **Fail loud instead.**
- **Inline `$request->validate` density** — AdminController (8 schemas), Investment cluster (33+ across RebalancingCalculation / TaxOptimization / ModelPortfolio / AssetLocation / AccountRebalancing / GoalProgress / FeeImpact / EfficientFrontier / InvestmentScenario controllers). Password validation regex duplicated verbatim in AdminController:223 + :278. Extract to FormRequest classes + a `StrongPassword` Rule object.
- **Response shape inconsistency** — 14 controllers return raw payloads via `response()->json($variable)` without the documented `{success, message, data}` envelope: BusinessInterestController, PostcodeLookupController, Estate/IHTController, Investment/ContributionOptimizerController, ProtectionController, NetWorthController, PasswordResetController, Public/TaxAllowancesController, RetirementController. Investment sub-controllers also pass raw service-result error dicts straight through (varying shape per service).
- **Raw Eloquent model in response data** — `RetirementController:369-373` returns `'data' => $pension` after `$pension->load('holdings')`. No DCPensionResource exists. Create one (mirror InvestmentAccountResource).
- **`PreviewController.php:283`** — `DB::transaction` orchestrates 13 sequential seed* private methods across 13 modules. Move into `PreviewPersonaSeeder` service.
- **`PreviewController.php:320`** — Error path interpolates `$e->getMessage()` directly into the response, bypassing the `SanitizedErrorResponse` trait already used elsewhere in the same controller. Possible internal-detail leak.

**Warning — Duplication**

- **Holdings-with-cash-remainder logic** is implemented twice — `InvestmentController:378-417` and `RetirementController:322-417` — same ~35-line algorithm modulo `holdable_type` polymorphism. Extract `HoldingsAllocationService::syncHoldings($holdable, $holdings, $totalValue)`.

**Suggestion — Dead code**

- `InvestmentController::calculateAccountAnnualisedReturn` lines 1066-1073 — marked `@deprecated`, zero callers. Delete.
- `AssetLocationController::clearUserAssetLocationCache` lines 304-314 — static, zero callers. Delete.

**Known re-flag**

- `TaxSettingsController::getCalculations()` lines 232-355 — hardcoded UK tax band display strings (~125 lines). Already W1 in May 22 session-level report.

---

### Models & Database (114 models, 224 migrations, 52 factories, 27 seeders)

**Clean:** strict_types universal across all 91 model files. Canonical enum compliance (no `'sole'` in app/database, only deliberate negative tests). No joint-ISA fixtures in factories. Auditable coverage on 34 financial entities largely complete.

**High — Schema drift**

- **`database/schema/mysql-schema.sql`** is stale by ~3 months / 80+ migrations. Last recorded migration: `2026_02_23_120002_add_linked_investment_account_to_goals` (batch 10, id 244). Actual through `2026_05_22_120000_create_currency_rates_table`. Tools / fresh-install pipelines / audit tooling that trust the dump will be wrong. Run `php artisan schema:dump` against a freshly migrated DB and commit. Add a CI guard.

**High — Soft-delete drift**

11 financial models have `deleted_at` columns (from `2026_02_21_200002_add_soft_deletes_to_financial_models`) but DO NOT `use SoftDeletes`. Column is unreachable; `Model::delete()` still hard-deletes. Models affected: Mortgage, Property, Goal, SavingsAccount, CashAccount, Investment/Holding, Subscription, Estate/Will, LifeInsurancePolicy, DBPension, DCPension. Decision: add SoftDeletes everywhere OR drop the `deleted_at` columns. **Needs CSJ call** on whether history-preserved deletes is a real requirement.

**High — Factory FK bugs**

- `PropertyFactory.php`, `MortgageFactory.php`, `ChattelFactory.php` — definition() omits `user_id` despite NOT NULL FK. Calling `Factory::create()` cold raises SQL integrity exception. Tests work around with `['user_id' => $user->id]` manually.
- `BusinessInterestFactory.php` likely same issue (only 1 method total, no user_id).

Add `'user_id' => User::factory()` to each definition. Add `joint()` / `trust()` state methods while you're there.

**Medium — Dead model scopes (~30)**

Concentrations: `LifeEvent` (5: forHousehold, income, expense, inDateRange, byCertainty), `AuditLog` (4: byEventType, byUser, byModel, recent), `RecommendationTracking` (3: inProgress, byModule, byTimeline), all six `*ActionDefinition` models (scopeEnabled + scopeBySource), `OnboardingProgress` (3), `DocumentExtractionLog` (3), plus singletons across Document, Estate/Liability, GoalContribution, Investment/InvestmentAccount, Investment/InvestmentScenario, Invoice, Mortgage, Property, UserConsent, AdvisorClient, AiAdviceLog, DeviceToken, ErasureRequest, ClientActivity, LoginAttempt.

Delete the dead scopes. If any are intended for upcoming work, document the planned consumer.

**Medium — Missing Auditable**

User (806 lines — the biggest target for audit coverage), Household, NotificationPreference, SavingsGoal, SubscriptionPlan. Add `use Auditable;` — for User, pair with a focused `$auditableAttributes` whitelist if the trait supports it.

**Medium — God model**

- **`User.php`** (806 lines, 63 public methods) — mixes auth (MFA / lockout / password), billing (onTrial / hasActivePlan / planIs / isInGracePeriod / isEligibleForStudentPlan), profile (gender / marital status / domicile via calculateYearsUKResident / isDeemedDomiciled), 40+ module relations, account lifecycle (isScheduledForDeletion / canBeRestored). Extract into `HasBillingState`, `HasDomicileInfo`, `HasAccountLifecycle` traits.

**Medium — Missing index**

- `plan_action_funding_selections` (`2026_03_04_000001_create_plan_action_funding_selections_table.php:16-25`) — `target_account_id` + `funding_source_id` as plain `unsignedBigInteger` with no per-column indexes. Composite unique on `(user_id, plan_type, action_category, target_account_id)` only serves user_id-leftmost queries. Add `->index('funding_source_id')` (and consider `['funding_source_type', 'funding_source_id']` if polymorphic lookups happen).

**Medium — Seeder direct insert**

- `AdvisorClientSeeder.php:33-36` — `DB::table('users')->update(['is_advisor' => true])` to bypass User's `$guarded`. Add an `is_advisor` mutator / `markAsAdvisor()` method on User.
- `TaxProductReferenceSeeder.php:19-29` — `DB::table('tax_product_reference')->truncate() + insert()`. Last reference-data seeder still on the legacy pattern. Introduce `TaxProductReferenceStore` (~640 lines, mechanical) per SP1 Pass 2 convention.

**Low — Factory state gaps**

Six joint-capable factories missing `joint()` state: MortgageFactory, PropertyFactory, CashAccountFactory, SavingsAccountFactory, BusinessInterestFactory, ChattelFactory. Pattern already exists on Estate/AssetFactory, Estate/LiabilityFactory, Investment/InvestmentAccountFactory, LifeEventFactory, GoalFactory. UserFactory also missing `preview()` state despite preview-personas being a core architectural concept.

**Low — Possibly orphan column**

- `users.ai_chat_enabled` (added `2026_02_27_200003`) — per memory `feedback_ai_chat_consent_no_toggle.md` consent is granted via privacy policy at registration; there's no UI toggle and the column should not exist. Application code has zero references, but `ChrisUserSeeder.php:87` still writes `'ai_chat_enabled' => true`. **Stale schema dump prevents firm verification**; check live dev DB then either drop the column + remove the seeder line, OR remove the seeder line if the column is already gone (latent crash).

---

### Vue Components (671 files: 514 components + 153 views + 4 mobile)

**Clean:** no banned amber/orange tokens, no `primary-N` / `secondary-N` / `gray-N` Tailwind tokens, no `v-if`+`v-for` collisions, no genuine single-word component names, all sampled `v-for` usages have `:key`. Rule #16 grandfathering applied — pre-existing emoji / Unicode-as-icons not flagged.

**Critical — Rule #14 violation**

- **`resources/js/views/NetWorth/CashOverview.vue`** — Routed view (component is mapped twice in `router/index.js`, lines 757 and 1328 as `CashOverview`). Template root is `<div class="cash-overview module-gradient">` with no AppLayout wrapper. Ships chrome-less — exactly the defect Rule #14 was created for. **Verified.**

**Critical — Rule #13 violations (8 score badges)**

| File | Line | Score |
|------|------|-------|
| `Investment/PlanSections/RiskAnalysisSection.vue` | 13-29 | "Current Risk Score" / "Target Risk Score" — `{{ data.current_risk_score \|\| 0 }}/10` + `getRiskScoreLabel` |
| `Investment/PlanSections/FeeAnalysisSection.vue` | 24-28 | "Fee Efficiency Score" — `{{ formatPercentage(data.efficiency_score \|\| 0) }}%` |
| `Investment/PlanSections/TaxStrategySection.vue` | 14-18, 88 | "Tax Efficiency Score" + "Optimisation score" caption |
| `Investment/AssetLocationOptimizer.vue` | 24-38 | "Asset Location Score" — `optimization_score.score` as 2xl bold + grade letter |
| `views/Investment/AccountRebalancingPanel.vue` | 94 | "Drift Score" panel |
| `Investment/DiversificationTab.vue` | 69, 339 | `getScoreBadge(data.diversification_label)` pill |
| `views/Investment/PortfolioStrategyPanel.vue` | 69 | `tax_efficiency_score` as bold via `getTaxEfficiencyClass()` |
| `Investment/TaxOptimizationOverview.vue` | 114, 126, 138, 150 | 4× `getComponentScore(...)` percentages (isa / cgt / dividend / location) |

Rule #13: scores oversimplify and mislead. Replace each with descriptive text and specific metrics (£ values, %, time periods).

**Critical — God components >1200 lines (20)**

| File | Lines |
|------|-------|
| `components/Admin/TaxSettings.vue` | 3068 |
| `components/UserProfile/ExpenditureForm.vue` | 2574 |
| `views/Public/CalculatorsPage.vue` | 2490 |
| `views/Dashboard.vue` | 2247 |
| `components/Retirement/RetirementIncomeTab.vue` | 2107 |
| `components/NetWorth/PensionList.vue` | 1935 |
| `components/NetWorth/Property/PropertyForm.vue` | 1889 |
| `views/Version.vue` | 1841 |
| `components/UserProfile/LetterToSpouse.vue` | 1786 |
| `components/Estate/IHTPlanning.vue` | 1712 |
| `components/Onboarding/OnboardingWizard.vue` | 1620 |
| `components/NetWorth/InvestmentProjections.vue` | 1515 |
| `components/Onboarding/steps/AssetsStep.vue` | 1474 |
| `components/NetWorth/InvestmentList.vue` | 1346 |
| `views/Settings/PrivacySettings.vue` | 1340 |
| `components/Investment/AccountForm.vue` | 1339 |
| `components/Retirement/CapitalAdequacyTab.vue` | 1335 |
| `components/Retirement/DCPensionForm.vue` | 1287 |
| `components/Retirement/RequiredCapitalDetail.vue` | 1254 |
| `components/Shared/AiChatPanel.vue` | 1237 (touch with care — Rule #16 Fyn-chat ban applies) |

Plus 15 more in the 800–1200 range (warning tier).

**Warning — Duplicate component filenames**

- `Savings/CurrentSituation.vue` (632 lines) + `Protection/CurrentSituation.vue` (824 lines) — both `name: 'CurrentSituation'`. Global-registry collision risk.
- `Goals/GoalCard.vue` (293) + `Investment/GoalCard.vue` (313) — both `name: 'GoalCard'`.

Rename to module-prefixed (`SavingsCurrentSituation`, `ProtectionCurrentSituation`, etc.).

**Warning — Hardcoded hex in style blocks (Rule #12)**

- `Estate/LpaDetailView.vue:197,203,252,271` — uses `#ddd`, `#1F2A44`, `#000`. Header comment even self-documents `#1F2A44 = horizon-500` — migration intent already exists.
- `Estate/WillBuilder/steps/WillBuilderReviewStep.vue:266,272,290,320` — same hex pattern (copy-paste from LpaDetailView). Both should `@apply` or extract to a shared `.legal-document` global class.
- `Shared/ModuleStatusBar.vue:239` — `border-bottom: 1px solid #FECDD3;` → `@apply border-raspberry-100`.
- `views/Login.vue:471`, `views/Register.vue:469`, `views/Plans/PlansDashboard.vue:161` — raw gradient hex (`#FFFFFF`, `#F3F3F3`, `#1F2A44`, `#2D3A5C`).
- `Advisor/{AdvisorClientDetail,AdvisorClientList,AdvisorDashboard}.vue:288/337/454` — triplicated palette literal `['#5854E6', '#E83E6D', '#20B486', '#E6C9A8', '#6C83BC', '#1F2A44']` instead of importing `CHART_COLORS` from `@/constants/designSystem`.
- `views/Dashboard.vue:1156-1222,1308-1318,1843-1853` — mortgages/loans/credit_cards palette + conditional gradient hex hardcoded.
- `views/Public/learn/LearnHubPage.vue:114-124+` — uses `#E8326E` which is actual palette drift from canonical `#E83E6D` raspberry.

**Warning — Custom `@keyframes` duplicating global ones**

- `Journey/JourneyMap.vue:508` — `@keyframes nodeGlow`
- `Dashboard/NetWorthOverviewCard.vue:285` — `@keyframes loading`
- `Onboarding/OnboardingWizard.vue:1608` — `@keyframes nodePulse`

Move to `app.css`.

---

### Stores & Frontend Services (37 stores, 53 services, 12 constants, 17 utils, 2 mixins, 1 directive)

**Clean:** All 36 Vuex modules namespaced. `taxConfig.js` ↔ `TaxDefaults.php` parity holds. No component imports axios directly. All ApexCharts components use `designSystem.js`. `currencyMixin` discipline holds (212 consumers, 0 local `formatCurrency()` methods).

**High — Real bug**

- **`components/Estate/LetterEstateWarnings.vue:125`** — `api.get('/api/estate/letter-validation')` produces `/api/api/estate/letter-validation` (404) because `api.js` baseURL is already `${apiBaseURL}/api`. The catch silently sets a generic message. Backend route confirmed `GET /api/estate/letter-validation`. **Fix:** `api.get('/estate/letter-validation')`. Ideally move into `letterService.js`. **Verified.**

**Medium — Dead Vuex modules**

- **`store/modules/dashboard.js`** (~110 lines) — registered in `store/index.js:6,73` but no `mapState('dashboard')`, no `mapActions('dashboard')`, no `dispatch('dashboard/...')`, no `store.state.dashboard.*` anywhere. Dashboard logic lives in `Dashboard.vue` + `dashboardService`. **Delete the file + registration.**
- **`store/modules/household.js`** — registered but no reads from any component. The `household` string elsewhere refers to a view-mode flag, not this module. **Delete.**

**Medium — Orphan Vuex actions (73 across 28 modules)**

High-impact clusters:
- `userProfile/{addFamilyMember, updateFamilyMember, deleteFamilyMember, addLineItem, updateLineItem, deleteLineItem}` (6) — components use `familyMembersService` directly
- `auth/{login, register, mobileLogout}` (3) — Login.vue / Register.vue call API directly bypassing store
- `aiFormFill/{acknowledgeFormReady, advanceStep, fillStepFields, getStepFields, cancelAll}` (5)
- `dashboard/*` (4) — whole module dead
- `household/*` (4) — whole module dead
- `infoGuide/*` (4)
- `investment/{runScenario, startMonteCarlo, pollMonteCarloResults, saveRiskProfile, setSelectedProjectionPeriod}` (5)
- `retirement/{runScenario, fetchPortfolioAnalysis, updateIncomeAllocation, resetToMainDashboard}` (4)
- `trusts/{fetchTrustById, fetchTrustAssets, calculateTrustIHTImpact, fetchUpcomingTaxReturns}` (4)
- `protection/{fetchProfile, updateProfile, createPolicy}` (3)
- Singletons across advisor, aiChat, businessInterests, chattels, completeness, estate, goals, journeys, lifeStage, netWorth, onboarding, plans, preview, recommendations, savings, taxConfig, taxStrategy

(Full list at `/tmp/orphan_actions_v3.txt` — agent left for follow-up.)

**Medium — Orphan mutations (12)**

`businessInterests/clearError`, `chattels/clearError`, `dashboard/SET_PREVIEW_MODE`, `estate/setRecommendations`, `estate/addLiability`, `goals/CLEAR_GOAL_DEPENDENCIES`, `investment/setOptimizationResult`, `plans/clearPlan`, `protection/setRecommendations`, `savings/setRecommendations`, `spousePermission/clearError`, `trusts/clearError`, `trusts/SET_PREVIEW_MODE`.

The four `clearError` and three `setRecommendations` patterns suggest mid-rolled-back consolidation.

**Medium — Dead service methods (~83)**

High-confidence dead: `authService.{verifyCode, resendCode, clearAuth, isAuthenticated}`, `tokenStorage.{clear, removeToken, getTokenSync}`, `whatIfService.createScenario`, most of `documentService` (8 methods), most of `rebalancingService` (6), all of `portfolioOptimizationService` (5), `mortgageService.getMortgage`, 6× `estateService` methods, 4× `analyticsService` methods.

Some flagged methods may be reachable via dynamic dispatch in admin tooling — review before deleting.

**Medium — Duplicate service**

- **`familyMembersService.js`** ↔ **`userProfileService.js` (lines 60-115)** — Both wrap `/user/family-members` CRUD. Different consumers use different services. Consolidate on `familyMembersService` (cleaner, more callers); delete the 4 family-member methods from `userProfileService`; repoint OnboardingWizard.vue + userProfile store actions.

**Medium — Dead code with stale hardcoded URLs**

- **`Protection/PolicyDetail.vue:578-587`** — `getApiEndpoint(policyType)` builds a hardcoded `/api/protection/*` URL map. The only caller (handleDelete:564) passes the endpoint into `protection/deletePolicy` which expects `{ policyType, id }` and ignores the endpoint. URL map is dead.

**Medium — Convention**

- Services use raw `console.error` / `console.warn` instead of `utils/logger.js`. Stores partially comply. Sites: dashboardService:20,34,49,77; investmentService:90,148; authService:47; occupationService:26; api.js:119,157. Replace with `logger.warn` / `logger.error`.
- `lifeStageConfig.js:238,244,442,445,627,784` — hardcodes £325,000 NRB, £175,000 RNRB, £12,570 PA, £60,000 AA, £20,000 ISA in didYouKnow / quickStat strings. Should import from `taxConfig.js`. Same drift in AssetsStep.vue, SalarySacrificeDisplay.vue:245, IHTPlanning.vue:537, WrapperOptimizer.vue (4 sites), StandardInvestmentFields.vue:293, planPrintMixin.js:1782, SaveTaxCampaignPage.vue:132.

**Low — State bloat**

- `retirement.js` — 824 lines, 35 state properties. 5 module-specific loading flags plus a generic one. Multiple overlapping concepts (requiredCapital, decumulationAnalysis, retirementIncome, incomeAccounts, incomeAllocations, customTargetIncome, analysis, scenarios, projections, strategies, strategyImpact). Defer split until next major retirement change.
- `aiChat.js` (1059 lines, 21 props), `estate.js` (915, 23), `investment.js` (770, 21) — all reasonable for their domains given current scope.

**Low — Mixed `/estate/will` ownership**

- GET in `letterService.js:28`, POST in `estateService.js:315`. Each service has clear domain owner — style not bug.

**Low — Direct API bypass**

- `NetWorth/PropertyList.vue:263,308` calls `api.get('/properties')` / `api.post('/properties', data)` directly via axios import, bypassing both `propertyService` AND the `netWorth` store actions (which are themselves on the orphan list). Three layers exist, only the bottom used.

**Low — console in stores**

- `savings.js:59`, `plans.js:241` — non-test `console.warn` calls. Replace with `logger.warn`.

---

### Tests (492 files)

**Clean:** Eval canonical contract respected (no `EvalUserSeeder`, no `is_eval_user`, no mirror-user in tests or seeders). Debug residue clean (no `dd()`, no `->only(`, no `fdescribe`, no `fit(` — only `dump()` in EvalReport which is intentional reporting output).

**Critical**

- **T-01: `tests/Feature/Savings/SavingsApiTest.php:313-333`** — Test name `'HTTP POST infers UK country and 50/50 split for joint ISA'` posts `{ account_type: 'cash_isa', is_isa: true, ownership_type: 'joint', joint_owner_id: $spouse->id }` and asserts 201 Created. Per memory `feedback_joint_isa_illegal.md` joint ISAs do not exist in UK law — the test name AND the asserted behaviour are wrong, and they entrench an illegal product shape in regression. **Verified.** Replace with a `'rejects is_isa=true + ownership_type=joint with 422'` test and add a validation rule in the savings FormRequest if one doesn't exist.

**Medium**

- **T-02: 8 legacy PHPUnit-style test files (119 methods awaiting Pest migration)**: `tests/Unit/Services/TaxConfigServiceTest.php` (26), `tests/Feature/Api/MortgageControllerTest.php` (18, 477), `tests/Feature/Api/PropertyControllerTest.php` (18, 446), `tests/Feature/TaxConfigurationTest.php` (18, 608), `tests/Unit/Services/PropertyTaxServiceTest.php` (15), `tests/Unit/Services/MortgageServiceTest.php` (11), `tests/Unit/Services/PropertyServiceTest.php` (9), `tests/Feature/CrossModuleIntegrationTest.php` (4). Property (3 files) + Mortgage (2) are a natural first batch.
- **T-03: 13 service modules with no dedicated unit-test coverage** — WhatIf (entirely untested + zero usage in tests/), LifeStage (same), NetWorth (only legacy root-level test), Property (all coverage in legacy PHPUnit files), Cache, Chattel, Dashboard, Settings/Assumptions, plus top-level singletons UKTaxCalculator (only 2 narrow tests), TaxConfigService, TaxBandTracker, TaxConfigSnapshotService, PrerequisiteGateService. **LifeStage and WhatIf are highest priority** (whole-feature gaps).
- **T-06: 48 Feature tests use bare `$this->actingAs($user)` instead of `Sanctum::actingAs($user)`** for `/api/*` endpoints — masks Sanctum-only issues + CSRF middleware behaviour. 340 occurrences vs 175 Sanctum occurrences. Leave `actingAs($user, 'sanctum')` alone (that form is correct).
- **T-08: `tests/Feature/Security/UserMassAssignmentTest.php`** uses `User::create($userData)` (legitimately — it's the SUT for the guarded check) but is PHPUnit-style and the `$userData` array is missing required fields. Move to `tests/Unit/Models/UserMassAssignmentTest.php`, migrate to Pest, populate required fields explicitly.

**Low**

- **T-04: 2 files use raw `Mockery::mock()` without `Mockery::close()` in `afterEach`** — `tests/Feature/Stores/SavingsReadConsumerParityTest.php:1643,1674` and `tests/Feature/AI/ConsentRuntimeCheckTest.php:233`. Add `afterEach(function () { Mockery::close(); });` or convert to `test()->mock(...)` for Pest-managed teardown.
- **T-05: 2 test files >1000 lines** — `tests/Feature/Stores/SavingsReadConsumerParityTest.php` (2005), `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php` (1331). Split along existing `describe()` boundaries.
- **T-07: Hardcoded UK tax constants in test setup** — Coordination/{HouseholdPlanningServiceTest, CrossModuleStrategyServiceTest, HolisticPlanRefactorTest}, Estate/PersonalizedTrustStrategyServiceTest, Investment/{DividendTaxCalculatorTest, TaxEfficiencyCalculatorTest}, Agents/EstateAgentGoalsTest. Extract to `tests/Helpers/TaxConfigFixture::withCurrentTaxConfig(): array`.
- **T-09: 1 browser scenario outside the BS-NN contract** — `tests/Browser/scenarios/document-articles-end-to-end.php`. Either rename to `BS-XX-document-articles-end-to-end.php` with a docblock contract OR relocate to `tests/Browser/manual/`.
- **T-10: Architecture-test gaps** — Rule #16 icon ban (Vue side may need a node script), AdviceFyn write-tool parity check (could enforce that `AdviceFyn::WRITE_TOOLS` contains every tool starting with `create_` / `update_` / `delete_` / `capture_` / `set_expenditure`). The AdviceFyn check is highest-value addition since it directly enforces a canonical contract.

**Info**

- T-11: 3 intentional `->skip` (Dusk bootstrap marker, eval HTTP driver manual integration, admin backup conditional on mysql binary). Investigate `tests/Architecture/EvalScenarioCountTest.php:18` — unconditional `->skip()` on an arch test means the count invariant is currently unenforced.
- T-12: 13 files use `DB::table()` directly in tests — about half clearly justified (migrations, audit-tamper, schema introspection, account-deletion verification). Add a single-line comment above each justifying the choice.

---

## Cross-Cutting Issues

### A. Security / Dependency Health

**7 Composer CVEs** (Symfony, disclosed 2026-05-20):

| Package | CVE | Title |
|---------|-----|----------|
| `symfony/mailer` | CVE-2026-45068 | Argument Injection in SendmailTransport via Dash-Prefixed Recipient Address |
| `symfony/mime` | CVE-2026-45070 | Email Header Injection via Non-Token Characters in Mime Parameter Names |
| `symfony/mime` | CVE-2026-45067 | Email Header / SMTP Command Injection via CRLF in Address |
| `symfony/routing` | CVE-2026-45065 | UrlGenerator Route-Requirement Bypass via Unanchored Regex Alternation → Off-Site `//host` URL Injection |
| `symfony/yaml` | + 3 more advisories per `composer audit` | varies |

**Fix:** `composer update symfony/mailer symfony/mime symfony/routing symfony/yaml` then re-run `composer audit`.

**8 npm vulnerabilities** — 4 high, 4 moderate, 0 critical. Run `npm audit --json` for details; many will be transitive in dev-only chains (worth checking with `npm audit --omit=dev`).

**PHP version note:** local dev running PHP 8.5.2; `composer.json` requires `^8.2`. Worth a parity check against production.

### B. Configuration drift

**`.env.example` vs `.env`:**

In example but not in `.env` (19 keys — possibly optional integrations):
- `AI_AUDIT_HMAC_KEY`, `ALLOWED_ORIGINS`, `ANALYTICS_ENABLED`, `ANTHROPIC_API_KEY`, `ANTHROPIC_CHAT_MODEL`, `FCM_*` (3), `FRONTEND_URL`, `MAIL_MARKETING_FROM_ADDRESS`, `MAIL_MARKETING_FROM_NAME`, `PLAUSIBLE_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_SECURE_COOKIE`, `VITE_PLAUSIBLE_DOMAIN`, `XAI_*` (3)

In `.env` but not in example (5 — undocumented expected keys):
- `ADMIN_EMAILS`, `AGENT_INTERNAL_TOKEN`, `OPENAI_API_KEY`, `SSH_PASSPHRASE`, `VITE_INSIGHTS_CMS_ENABLED`

Add the 5 missing keys (with placeholder values) to `.env.example` so fresh checkouts know they exist.

### C. Duplicate code (verified across modules)

| Pattern | Where |
|---------|-------|
| Net-worth calculation | `Estate/NetWorthAnalyzer` ↔ `NetWorth/NetWorthService` |
| Holdings-with-cash-remainder | `InvestmentController` ↔ `RetirementController` |
| Income/expenditure resolution | `AI/AdvicePromptBuilder` (hand-rolled) ↔ `ResolvesIncome`/`ResolvesExpenditure` traits (everywhere else) |
| Significance thresholds (£100k/£200k) | `Coordination/PriorityRanker` ↔ `HolisticPlanner` ↔ `Dashboard/DashboardAggregator` |
| CLT lifetime rate lookup | `Estate/PersonalizedTrustStrategyService` (×2) ↔ `GiftingStrategyOptimizer` |
| Tax-rate fallbacks (0.20/0.40/0.45) | `SavingsActionDefinitionService` (×2) ↔ `ContributionWaterfallService` (×2) ↔ `TrustService` (×3) |
| Goal progress calc | `Goals/GoalCalculationService` ↔ `Goals/GoalProgressService` |
| Chart palette literal | `views/Advisor/{ClientDetail,ClientList,Dashboard}.vue` (3 copies) + `views/Dashboard.vue` + `views/Public/learn/LearnHubPage.vue` |
| `formatCurrency` site discipline | partially broken across ~20 components (mixed Composition API + Options API) |
| Service vs component vs store layer for property CRUD | `propertyService` + `netWorth.fetchProperties/createProperty` (orphans) + `PropertyList.vue` direct axios — three layers, only the bottom used |
| `familyMembers` REST surface | `familyMembersService` + `userProfileService.{getFamilyMembers, createFamilyMember, updateFamilyMember, deleteFamilyMember}` — two services, different consumers |
| Vue component filename collision | `CurrentSituation.vue` (Savings + Protection) + `GoalCard.vue` (Goals + Investment) — both pairs declare identical `name:` |

### D. Carry-overs from prior reports

- **`IHTStrategyGeneratorService.php`** — flagged in `April/April5Updates/codeReview.md` (B-5) and `May/May12Updates/review-conventions.md` (dead code list, 363 lines). Still 489 lines, still in repo. Delete.
- **`TaxSettingsController::getCalculations()`** hardcoded display strings — W1 in May 22 session report. Still present. Natural to bundle with R1.5 in SP1 Pass 2.

---

## Recommended Action Plan

### Immediate (this week — Quick Wins + Critical Security)

1. `composer update symfony/*` (7 CVEs) — 5 min
2. `npm audit fix` (review high-severity manually) — 30 min
3. Delete `IHTStrategyGeneratorService.php` — 30 sec
4. Fix `LetterEstateWarnings.vue:125` `/api/api/...` 404 — 30 sec
5. Replace SavingsApiTest joint-ISA test + add validation rule — 15 min
6. Delete `store/modules/dashboard.js` + `household.js` + index.js registrations — 5 min
7. Delete `AssetLocationController::clearUserAssetLocationCache` + `InvestmentController::calculateAccountAnnualisedReturn` — 2 min
8. Add `user_id` (+ `joint()` state) to PropertyFactory / MortgageFactory / ChattelFactory / BusinessInterestFactory — 10 min
9. `php artisan schema:dump` — 1 min, then commit
10. Decide soft-delete fate for the 11 financial models (CSJ call: add `SoftDeletes` everywhere OR drop unused `deleted_at` columns)

**Estimated total: ~1 working day.**

### Short-term (this month — Critical UI / convention)

11. Strip 9 Rule #13 score badges from Investment surfaces — replace with descriptive metrics
12. Wrap `views/NetWorth/CashOverview.vue` in `<AppLayout>` (Rule #14)
13. Rename duplicate Vue component filenames (`CurrentSituation.vue`, `GoalCard.vue`) with module prefixes
14. Extract `IHTPlanningPayloadBuilder` service to break the `LifePolicyController` / `GiftingController` → `IHTController` inter-controller injection
15. Fix `AssetLocationController` hardcoded tax fallbacks — fail loud, don't silently substitute 2026/27 values
16. Migrate the 8 legacy PHPUnit test files to Pest (start with Property + Mortgage cluster)
17. Sweep 48 `$this->actingAs` API tests to `Sanctum::actingAs`
18. Extract `tests/Helpers/TaxConfigFixture::withCurrentTaxConfig()` to consolidate hardcoded tax constants
19. Add the 5 missing keys to `.env.example`
20. Add missing Auditable to User / Household / NotificationPreference / SavingsGoal / SubscriptionPlan
21. Consolidate `familyMembersService` (delete the 4 dupe methods from `userProfileService`, repoint consumers)

### Medium-term (next quarter — God file decomposition)

22. Split the 4 ActionDefinitionService classes (Savings 3690 / Retirement 2719 / Protection 2350 / Investment 1486) into per-evaluator subclasses under `app/Services/<Module>/Actions/`
23. Split the 5 god controllers (PaymentController, InvestmentController, AdminController, AuthController, GoalsController) per surface
24. Split the 20 Vue god components >1200 lines (start with TaxSettings 3068 + ExpenditureForm 2574 — both are entry forms with clean section boundaries)
25. Walk the 73 orphan Vuex actions / 12 orphan mutations / ~83 dead service methods, deleting confirmed-dead
26. Walk the 30 dead model scopes, deleting confirmed-dead
27. Introduce `TaxProductReferenceStore` to migrate the last legacy reference seeder
28. Collapse `AiToolDefinitions` + `XaiToolDefinitions` via a shared per-tool registry
29. Address the AdvicePromptBuilder ResolvesIncome/Expenditure drift (Fyn advice tax-band estimation parity)

### Backlog (suggestions / observations)

- Promote `SignificanceThresholds`, `CLT_LIFETIME_RATE` lookup, `TaxDefaults::BASIC_RATE/HIGHER/ADDITIONAL` to canonical constants
- Replace raw `console.*` in services with `utils/logger.js`
- Switch `MonteCarloSimulator` from `DB::table` to an Eloquent model
- Add `joint()` state to the 6 joint-capable factories (Mortgage/Property/Cash/Savings/BusinessInterest/Chattel)
- Add `preview()` state to UserFactory
- Resolve the `ai_chat_enabled` orphan column once schema dump is current
- Add Architecture test asserting AdviceFyn write-tool parity (catches Rule #3 / canonical-contract drift)
- Split the 22 backend services in the 500–800 line range as time allows

---

*Generated by the `tech-debt-full` skill on 2026-05-23. Re-run quarterly or after any major refactor PR.*
