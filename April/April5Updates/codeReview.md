# Full Codebase Tech Debt Review

**Date:** 2026-04-05
**Codebase:** Fynla v0.9.4 (main branch — fynNew architecture work pending merge)
**Scope:** 654 Vue components, 230 PHP services, 92 controllers, 90 models, 32 Vuex stores, 9 agents, 940+ Pest tests
**Total issues:** 90 findings across 6 audit dimensions

---

## Executive Summary

| Severity | Count |
|----------|-------|
| Critical | 15 |
| Warning | 45 |
| Suggestion | 30 |

| Category | Count |
|----------|-------|
| Complexity (god files/methods) | 26 |
| Convention drift | 22 |
| Duplicate code | 12 |
| Dead code | 11 |
| Inconsistency (casts, patterns) | 10 |
| Test coverage gaps | 9 |

### Top 5 Most Impactful Issues

1. **Float-to-decimal cast mismatch on 70+ financial columns** across 12 models (Estate, Investment, Protection). PHP IEEE 754 rounding can silently corrupt IHT/CGT/retirement calculations where penny precision is legally significant.
2. **`RetirementIncomeService.php` — 2,292 lines with 4 methods each 200–382 lines.** Single untested change = cascading retirement calculation errors for all users.
3. **`IHTCalculationService.php` — 1,641 lines**, hardcodes `0.40` IHT rate as default parameter (line 250) despite injecting TaxConfigService.
4. **51 of 84 methods in `investmentService.js` are never called** (1,146 lines total). Also contains a duplicate `analyzeAssetLocation` method definition — latent defect.
5. **Score displays in Investment plan sections violate CLAUDE.md Rule 13** (`{{ data.current_risk_score }}/10`, "Optimisation score" labels). Currently in 7 orphaned components that would ship violations the moment they're wired.

### Top 5 Quick Wins (trivial effort, high impact)

1. **Fix hardcoded `0.40` IHT rate** in `IHTCalculationService.php:250` — pull from TaxConfigService (1 line).
2. **Fix `'£175,000'` hardcoded RNRB** in `PersonalizedGiftingStrategyService.php:335` — value is already in scope (1 line).
3. **Fix `'£60,000 annual allowance'` hardcoded** in `IHTStrategyGeneratorService.php:309` (1 line).
4. **Replace `amber-50/amber-700` banned tokens** in `PensionIhtChanges2027Page.vue:19` with `violet-*` (1 line).
5. **Delete duplicate `analyzeAssetLocation`** at `investmentService.js:1029` — JavaScript silently uses this second definition (1-line deletion).

### Overall Health Assessment

The codebase has **high structural debt concentrated in a small number of god files**, but is **conventionally healthy** at the margins: all 92 controllers have `declare(strict_types=1)`, all `app/Services/` files are strict-types compliant, no `primary-*`/`secondary-*`/`gray-*` tokens remain in Vue components, no `v-if`+`v-for` collisions exist, and mobile code is clean.

The biggest risk vectors are: (1) the RetirementIncomeService/IHTCalculationService god classes where single-file changes have cascading blast radius, (2) the float/decimal cast mismatch that could silently corrupt financial output at penny precision, (3) hardcoded tax values in services that already inject `TaxConfigService` — 10+ independent points of failure when HMRC changes allowances, and (4) 53 of 92 controllers use inline `Validator::make`/`$request->validate` instead of FormRequests, scattering validation logic across method bodies.

---

## Detailed Findings by Module

### 1. Backend Services (app/Services/, 230 files)

#### CRITICAL

**B-1** — `RetirementIncomeService.php:231-542, 889-1241, 1434-1815, 1851-2080` — **God class 2,292 lines with 4 methods each 200–382 lines.** `getAvailableAccounts` (312 lines) handles account retrieval + PCLS splitting + state pension + spouse merging + projection in one pass. `projectFundDepletion` (353 lines) embeds multi-decade simulation inline. **Extract `ProjectionEngine`, `PCLSCalculatorService`, `AllocationStrategyService`.** (effort: large)

**B-2** — `UserProfileService.php:661-1081` — **Single method `getFinancialCommitments` at 421 lines** (longest method in codebase). Assembles retirement, property, insurance, loan, savings, investment data in one monolithic pass. **Extract per-category builders.** (effort: large)

**B-3** — `CrossModuleStrategyService.php:156` — **Hardcoded `?? 60000` fallback for Annual Allowance** despite injecting TaxConfigService and calling `getPensionAllowances()` on the line above (L155). **Remove the fallback entirely or use `TaxDefaults::PENSION_ANNUAL_ALLOWANCE`.** (effort: trivial)

**B-4** — `IHTCalculationService.php:250, 1600` — **`float $ihtRate = 0.40` hardcoded default parameter.** L1600 uses `?? 0.40` fallback. IHT rate is HMRC-controlled; a government change would silently use the wrong rate. **Require callers to pass rate from `$ihtConfig['standard_rate']`.** (effort: small)

**B-5** — `IHTStrategyGeneratorService.php:309` — **`'£60,000 annual allowance'` hardcoded** in user-facing advisory text. **Interpolate from `getPensionAllowances()`.** (effort: trivial)

**B-6** — `PersonalizedGiftingStrategyService.php:335` — **`'£175,000'` (RNRB) hardcoded** in user-facing strategy advice. `$ihtConfig` already available in same method. **Replace with `'£'.number_format((int)$ihtConfig['residence_nil_rate_band'], 0)`.** (effort: trivial)

#### WARNING

**B-7** — `IHTCalculationService.php` — **1,641 lines**, contains IHT computation + RNRB + charitable relief + projections + what-if analysis with no sub-delegation. **Extract `CharitableRateCalculator`, `RNRBCalculator`, `ProjectedEstateService`.** (effort: large)

**B-8** — `RetirementStrategyService.php:40-262` — `getStrategies()` at 223 lines, constructor injects 7 services. **Decompose into `buildIncomeStrategies()`, `buildWrapperStrategies()`, `buildDecumulationStrategies()`.** (effort: medium)

**B-9** — `SafetyCheckService.php:38-253` — Single `check()` method at 216 lines does debt ratio + emergency fund + pension + tax band assessment inline. **Extract per-check methods.** (effort: medium)

**B-10** — **Pattern `?? 12570` (personal allowance) duplicated 10+ times across 7 services** (`TaxActionDefinitionService`, `PSACalculator`, `DividendTaxCalculator`, `AssetLocationOptimizer`, `HouseholdPlanningService`, `TaxOptimisationService`). **Add `getPersonalAllowance(): float` helper to TaxConfigService.** (effort: small)

**B-11** — **Pattern `?? 60000` (Annual Allowance) duplicated 5+ times** across Retirement/Tax services. Same fix as B-10. (effort: small)

**B-12** — `ComprehensiveEstatePlanService.php:587-864` — `buildBalanceSheet()` at 278 lines builds 12+ asset categories. **Extract per-category builders.** (effort: medium)

**B-13** — `IntestacyCalculator.php:17-292` — `calculateDistribution()` at 276 lines reaches nesting depth 8. **Guard clauses + per-tier extraction.** (effort: medium)

**B-14** — `DividendTaxCalculator.php` — **Zero references outside Services directory.** Injected by `TaxEfficiencyCalculator` which may itself be dead. **Verify call chain then remove or test.** (effort: small)

**B-15** — `ContributionEstimatorService.php:18-34` — `ISA_ALLOWANCE_FALLBACK = 20000` used as silent fallback when TaxConfigService throws. **Add `Log::error()` before returning fallback.** (effort: trivial)

#### SUGGESTION

**B-16** — `app/Services/Plans/*PlanService.php` (6 files) — `getRecommendations()` null-guard pattern duplicated across concrete classes. **Move to `BasePlanService::resolveComputedData()`.** (effort: small)

**B-17** — `SystemPromptBuilder.php:194-450` — `buildFinancialContext()` at 257 lines concatenates 6+ module contexts. **Extract per-module builders.** (effort: medium)

**B-18** — `ScenarioService.php:98-101` — Scenario template hardcodes `withdrawal_amount => 20000` (coincides with ISA allowance). **Rename to `DEFAULT_SCENARIO_WITHDRAWAL` constant with comment.** (effort: trivial)

**B-19** — `IncomeDefinitionsService.php:163-180` — `calculateAdjustedAllowances()` uses 7 inline `?? numeric_fallback` expressions. **Validate config shape on boot or centralise in helpers.** (effort: small)

---

### 2. Controllers & HTTP Layer (app/Http/, 92 controllers)

#### HIGH

**H-1** — `InvestmentController.php` — **1,067 lines, 20 public methods, contains `DB::transaction` blocks.** Handles CRUD + holdings + performance + allocation + projections. **Split into `InvestmentAccountController`, `HoldingsController`, `PerformanceController`.** (effort: high)

**H-2** — `RebalancingCalculationController.php` — **698 lines, 6× `Validator::make` inline.** **Extract to `RebalanceRequest`, `CGTComparisonRequest` FormRequests.** (effort: medium)

**H-3** — `AdminController.php` — **666 lines, 5× `Validator::make` + 1× `$request->validate` inline.** No FormRequests exist for admin. **Create `app/Http/Requests/Admin/` directory, one FormRequest per write method.** (effort: medium)

**H-4** — `GoalsController.php` — **792 lines, 21 methods, 4× inline validate.** `app/Http/Requests/Goals/` already exists. **Fill coverage gaps + split goal CRUD from life-event tracking.** (effort: medium)

**H-5** — `RetirementController.php` — **789 lines, 23 methods, 2× `DB::transaction` inline.** **Move transactions into `RetirementService`, split DB pension CRUD from calculations.** (effort: high)

#### MEDIUM

**H-6** — `LifeEventAllocationController.php` — **Fully implemented 118-line controller with zero routes registered.** Dead code or missing wiring. **Register routes under `/api/life-events/{id}/allocations` or delete.** (effort: low)

**H-7** — **Naming collision:** `Investment/TaxOptimizationController` (American, 463 lines) vs `Tax/TaxOptimisationController` (British, ~150 lines) serve different concerns. **Rename Investment one to `InvestmentTaxController`.** (effort: low)

**H-8** — **Estate controllers (`TrustController`, `GiftingController`, `LpaController`, `IHTController`) all use inline validation** despite `app/Http/Requests/Estate/` existing. **Create `StoreTrustRequest`, `UpdateTrustRequest` etc.** (effort: low)

**H-9** — **53 of 92 controllers (57%) use inline `Validator::make`/`$request->validate`.** Only 81 FormRequests exist. **Systematic migration, starting with highest-traffic endpoints.** (effort: high)

**H-10** — **139+ raw `response()->json([...])` arrays across 5 largest controllers** (Investment 29, Retirement 25, Goals 33, Savings 22, Auth 30). **Introduce `InvestmentAccountResource`, `RetirementPensionResource`, `GoalResource`.** (effort: high)

**H-11** — **`DB::transaction` blocks in controllers** (`RetirementController:321,389`, `InvestmentController:374`, `FamilyMembersController:271,395`). **Move into service layer.** (effort: medium)

**H-12** — `PreviewWriteInterceptor.php` — **`EXCLUDED_ROUTES` pattern match gap.** `DELETE /api/ai-chat/conversations/{id}` is not excluded — preview users silently get fake success. **Use regex pattern or explicit route.** (effort: low)

**H-13** — `GDPRController.php` — **612 lines, compliance-critical, mixed concerns** (consent + export + erasure). **Split into `ConsentController`, `DataExportController`, `ErasureController`.** (effort: medium)

#### LOW

**H-14** — **All 92 controllers have `declare(strict_types=1)`** ✓ (positive finding, maintain via Architecture test suite)

**H-15** — `POST /api/bug-report` — Public write route relies on `throttle:bug-reports` limiter being defined in `AppServiceProvider`. **Add test asserting limiter exists + add controller to `EXCLUDED_ROUTES`.** (effort: low)

---

### 3. Models & Database (app/Models/, database/)

#### CRITICAL

**M-1** — **23 float casts on `IHTCalculation.php:59-81`** where DB columns are `decimal(15,2)`. Plus `IHTProfile`, `Liability`, `Gift`, `Asset` all in Estate module. **Replace all `'float'` with `'decimal:2'`.** (effort: small)

**M-2** — **30+ float casts on Investment/Protection models** (`Holding`, `RebalancingAction`, `InvestmentGoal`, `RiskProfile`, `ProtectionProfile`, `ExpenditureProfile`). DB is `decimal(15,2)`/`decimal(15,4)`. **Same fix as M-1.** (effort: small)

**M-3** — `User.php` — **716 lines, 59 methods** (subscription logic + domicile + 30+ relationships + cross-cutting business logic). Most-modified file = merge conflict source. **Extract `HasSubscription` trait + `DomicileService`.** (effort: medium)

#### WARNING

**M-4** — **9 Estate models + `CashAccount`, `Document`, `PersonalAccount`, `Subscription` all missing `Auditable` trait.** Hold sensitive user financial data with no audit trail. **Add `use App\Traits\Auditable;`.** (effort: trivial)

**M-5** — `InvestmentAccount.php` — **492 lines, ~160 fillable fields** covering 6 different investment sub-types (ESS, BADR, SAYE, crowdfunding, standard, bonds). **Extract sub-type fields into polymorphic detail tables.** (effort: large)

**M-6** — `JointAccountLog.php` — **Has `joint_owner_id` column but doesn't use `HasJointOwnership` trait.** **Add trait.** (effort: trivial)

**M-7** — `PersonalAccount.php` — **`amount` column (decimal(15,2)) has no cast at all** in `$casts`. Returns as string, risking silent arithmetic errors. **Add `'amount' => 'decimal:2'`.** (effort: trivial)

**M-8** — **Missing factories for `PlanConfiguration`, `AiConversation`, `AiMessage`, `SubscriptionPlan`, `ISAAllowanceTracking`.** `PlanConfiguration` already used via raw `::create()` in tests. **Create factories.** (effort: small)

**M-9** — Same as M-1 — called out separately because `IHTCalculation` is used in every estate calculation path. Fix may reveal previously-masked rounding bugs. (effort: trivial→medium)

#### SUGGESTION

**M-10** — **6 infrastructure/logging models lack factories** (`AuditLog`, `TaxConfigurationAudit`, `LoginAttempt`, `UserSession`, `PasswordResetSession`, `EmailVerificationCode`). Low priority — skip `AuditLog`. (effort: trivial)

**M-11** — **Convention drift:** older domain models use `Auditable`, newer ones (Estate sub-models, Document, Subscription) don't. **Add to `Subscription`, `Document`, `UserConsent` minimum.** (effort: trivial)

**M-12** — `InvestmentAccount.php` lacks `Auditable` trait despite high-value financial data. **Add trait with `$auditExcludeFields` for high-churn fields.** (effort: trivial)

---

### 4. Vue Components (resources/js/components/, 654 .vue files)

#### CRITICAL

**V-1** — **Score displays (Rule 13 violations)** in `RiskAnalysisSection.vue:14-29` (`{{ current_risk_score }}/10`), `TaxStrategySection.vue:88` ("Optimisation score"), `AssetLocationOptimizer.vue:35-38` (radial bar driven by `optimization_score.score`), `FeeAnalysisSection.vue:28`. **Remove numeric displays, keep descriptive labels.** (effort: medium)

**V-2** — **20 components exceed 800 lines; 10 exceed 1,500.** Top 5: `TaxSettings.vue` (2,972), `ExpenditureForm.vue` (2,574), `CalculatorsPage.vue` (2,489), `Dashboard.vue` (2,256), `RetirementIncomeTab.vue` (2,124). **Prioritise `ExpenditureForm` + `RetirementIncomeTab` — natural section boundaries.** (effort: high)

#### HIGH

**V-3** — `CashFlowProjectionChart.vue:316-344` — **Local `formatCurrencyShort` duplicates currencyMixin.** Component already uses mixin. **Delete local method (15 lines).** (effort: low)

**V-4** — `NetWorthWaterfallChart.vue:20` — Imports `formatCurrency` directly from `@/utils/currency` instead of via currencyMixin. **Add mixin, remove direct import.** (effort: low)

**V-5** — `TrustPlanningStrategy.vue` — Uses `formatCurrency` in template without mixin declaration. **Silently fails if not inherited from parent.** Add currencyMixin. (effort: low)

**V-6** — `LetterToSpouse.vue:1181-1426` — **28+ hardcoded hex values** in print window HTML (`#1f2937`, `#64748b`, `#dc2626`). Uses old Tailwind slate values instead of design system palette. **Extract to `LetterPrintService` with `designSystem.js` constants.** (effort: medium)

**V-7** — `GuideNav.vue:170`, `LearnHubPage.vue:203,209,214` — `border-bottom-color: #E8326E`, `color: #6B7280` hex instead of `@apply` directives. **Use `@apply border-raspberry-500`, `@apply text-neutral-500`.** (effort: low)

**V-8** — `PersonaSelectionModal.vue:476-480` — 5 persona card classes use raw hex for backgrounds. **Use `@apply bg-spring-100 text-spring-900` etc.** (effort: low)

**V-9** — `PensionIhtChanges2027Page.vue:19` — **Banned `amber-50/amber-700` tokens** (Rule 9 violation, public-facing page). **Replace with `violet-*`.** (effort: low)

#### MEDIUM

**V-10** — `ProfileCompletenessAlert.vue:34` — Renders `{{ completeness_score }}%` to users. **Rule 13 violation.** Replace with qualitative status. (effort: low)

**V-11** — `NRBRNRBTracker.vue` — Component name includes banned acronyms; likely surfaces in parent UI headings. **Audit `IHTPlanning.vue` and expand "NRB"/"RNRB" to "Nil Rate Band"/"Residence Nil Rate Band".** (effort: low)

**V-12** — **IHT acronym in user-facing strings** in `InheritanceTaxExplainedPage.vue:171`, `IhtPlanningFeature.vue:21-22`, `FynlaVsSpreadsheetsPage.vue:147`. Meta descriptions appear in Google search results. **Replace "IHT liability" → "Inheritance Tax liability".** (effort: low)

**V-13** — `AdminPanel.vue:161` — `shortLabel: 'DB'` for Database tab (could mean "Defined Benefit" elsewhere). **Change to `'Data'`.** (effort: trivial)

**V-14** — **Missing `:key` on `v-for`** in `ClientActivityForm.vue:42`, `LpaComplianceChecklist.vue:24`, `IHTMitigationStrategies.vue:18,113,137`. **Add stable IDs.** (effort: low)

**V-15** — `CalculatorsPage.vue` — **2,489-line public view** co-locates mortgage/savings/retirement/ISA calculators. **Extract each calculator + lazy-load.** (effort: high)

**V-16** — `GuideNav.vue:57-70` — `color: '#E8326E'`, `dotColor: '#E8326E'` hardcoded in JS data array. **Import from `designSystem.js`.** (effort: low)

**Clean findings (positive):**
- No `primary-*`, `secondary-*`, `gray-*`, `orange-*` tokens in components
- No `v-if` + `v-for` on same element
- Mobile directory (5 components) all under 800 lines, no banned tokens

---

### 5. Stores & Frontend Services (resources/js/store/, services/)

#### CRITICAL

**S-1** — `investmentService.js` — **51 of 84 methods never called** (1,146 lines). Phase 2 analysis suite dead: `analyzeGoalProgress`, `generateWhatIfScenarios`, `calculateGoalProbability`, `analyzeDrift`, `compareBenchmarks`, 46 others. **Delete confirmed dead methods (~800 lines).** (effort: medium)

**S-2** — `investmentService.js:298 AND :1029` — **`analyzeAssetLocation` defined TWICE in same object.** JavaScript silently uses the second definition (takes no params vs parameterised first). **Latent defect — delete L1029.** (effort: low)

#### HIGH

**S-3** — `investment.js` — **4 Phase 2 store actions have zero dispatchers** (`analyseAssetLocation`, `analysePerformanceAttribution`, `compareBenchmarks`, `analyseFees`) + corresponding state fields never read. **Delete (~140 lines).** (effort: medium)

**S-4** — **7 orphaned components** in `Investment/PlanSections/` never imported: `FeeAnalysisSection`, `RiskAnalysisSection`, `TaxStrategySection`, `GoalProgressSection`, `ActionPlanSection`, `CurrentSituationSection`, `RecommendationsSection`. **3 contain Rule 13 score displays — will ship violations when wired.** Delete. (effort: low)

**S-5** — **4 advisor views call `advisorService.*` directly with bare `catch {}` blocks** — no user-visible error state. **Add `error` reactive field OR move behind `advisor` Vuex module.** (effort: medium)

**S-6** — **`fetchRecommendations` duplicated across 5 module stores** (`investment.js`, `protection.js`, `savings.js`, `estate.js`, `retirement.js`) + dedicated `recommendations.js` store. **Estate/protection/savings versions are dead.** Delete. (effort: low)

#### MEDIUM

**S-7** — `investmentService.js` — **1,146 lines, 84 methods** = 8× median service size. After dead code removal still largest file. **Split into `investmentAnalysisService`, `investmentTaxService`, `investmentPerformanceService`.** (effort: high)

**S-8** — `preview.js:115-120` — **`hasEdits: () => false`, `editCount: () => 0` unconditionally return false/0.** `PersonaSelector.vue` has entire "unsaved changes" warning (lines 104-135, 190-294) that can never display. **Delete or complete.** (effort: low)

**S-9** — `willDocumentRenderer.js:24`, `lpaDocumentRenderer.js:16` — **Private `formatCurrency`/`formatDate` duplicating `utils/currency.js`/`utils/dateFormatter.js`.** Will version returns `''` for null instead of `'£0'`. **Import from canonical utils.** (effort: low)

**S-10** — **American spelling drift** — `investmentService.js` has 18 `analyze*` methods, `estateService.js` has 2. Vuex actions correctly use British `analyse*`. **Rename service methods.** (effort: medium)

**S-11** — `netWorth.js` — **`selectedMortgage` state + mutation defined but never read externally.** Local to store only. **Remove (767→~740 lines).** (effort: low)

**S-12** — `goals.js:6-7,329-345,150` — **`riskLevels` state + `fetchRiskLevels` action never dispatched.** Components use local data instead. **Remove.** (effort: low)

---

### 6. Tests & Cross-Cutting (tests/, CSS)

#### HIGH — Test Coverage Gaps

**T-1** — `ChattelCGTService.php` (223 lines) — **UK CGT calculation service with ZERO tests.** **Create `ChattelCGTServiceTest.php`** covering standard/higher rates, £6,000 chattel exemption, part-disposal. (effort: medium)

**T-2** — `WhatIfScenarioService.php` (274 lines) — **Cross-module scenario engine with zero tests.** **Create `WhatIfScenarioServiceTest.php`** for income/expense/lump-sum scenarios. (effort: medium)

**T-3** — `InvestmentAgent.php` — **No agent-level test.** Only 4 of 8 agents have tests. **Create `InvestmentAgentTest.php`** mirroring `ProtectionAgentTest.php` pattern. (effort: medium)

**T-4** — `TaxOptimisationAgent.php` — **No agent test.** Service tested but agent orchestration layer untested. **Create `TaxOptimisationAgentTest.php`.** (effort: medium)

**T-5** — `RevolutService.php` (182 lines) — **Payment-critical service with single feature test only.** No unit coverage for order creation, confirmation, cancellation. **Add `RevolutServiceTest.php` with `Http::fake()`.** (effort: medium)

#### MEDIUM

**T-6** — `MortgageCalculationService.php` — **Test file name mismatch** (`MortgageServiceTest.php` exists, covers `MortgageService`). Verify actual coverage. (effort: low)

**T-7** — `EstatePlanRefactorTest.php` — **686 lines** (largest test file). Covers IHT + trusts + gifting + wills. **Split using `describe()` blocks.** (effort: low)

**T-8** — `HolisticPlanRefactorTest.php` — **685 lines.** **Split by plan section.** (effort: low)

**T-9** — `NetWorthOverviewCard.vue:268-295` — **Custom `@keyframes loading` skeleton** with hardcoded hex (`#f3f4f6`, `#e5e7eb` = banned gray tokens). **Use `theme('colors.savannah.100')` + `theme('colors.eggshell.500')`.** (effort: low)

**T-10** — `MonteCarloResults.vue:179` — **`const TARGET_COLOR = '#8b5cf6'` hardcodes violet-500.** **Import from `designSystem.js`.** (effort: low)

**T-11** — `LpaDetailView.vue`, `WillBuilderReviewStep.vue` — **8 hardcoded hex values** in print document styles (`#1F2A44`, `#ddd`, `#000`). **Use `@apply` with palette tokens.** (effort: low)

#### LOW

**T-12** — `JourneyMap.vue:506`, `OnboardingWizard.vue:1595` — **Nearly identical `@keyframes nodePulse/nodeGlow` animations** in two files. **Consolidate into `.node-pulse` global class in `app.css`.** (effort: low)

**T-13** — `GuidanceTooltip.vue:378-390` — **`@keyframes guidance-pulse` with raw RGBA** `(88, 84, 230, ...)` hardcoded. **Use CSS custom properties.** (effort: low)

**T-14** — `XaiClient.php`, `SystemPromptBuilder.php` — **No unit tests** for foundational AI chat services. **Add with `Http::fake()`.** (effort: medium)

**T-15** — `LifeStageService.php` — **Only Feature-level test exists** (full HTTP stack). No unit test for classification rules. **Create `LifeStageServiceTest.php`.** (effort: low)

---

## Cross-Cutting Patterns

### Hardcoded Tax Values

Despite `TaxConfigService` being the canonical source, the codebase has **~20+ independent hardcoded fallbacks** across:
- 7 services with `?? 12570` (personal allowance)
- 5+ services with `?? 60000` (Annual Allowance)
- 3 services with `'£325,000'` / `'£175,000'` as literal strings in user-facing text
- `IHTCalculationService` with `0.40` IHT rate as default param

**Impact:** When HMRC changes any threshold, 20+ points must be found and fixed. Tests currently don't catch drift because the fallback values happen to match current HMRC rates.

**Root-cause fix:** Add `getPersonalAllowance(): float`, `getPensionAnnualAllowance(): float`, `getIHTStandardRate(): float` helpers to `TaxConfigService` that throw if config missing (no silent fallback).

### God Files (>800 lines)

**Backend:** 2 services over 1,600 lines, 5 controllers 600-1,067 lines, `User.php` at 716 lines.

**Frontend:** 20 Vue components over 800 lines, 10 over 1,500. Biggest: `TaxSettings.vue` (2,972), `investmentService.js` (1,146), `netWorth.js` (767).

**Pattern:** New features pile onto existing files rather than extract. Testing becomes harder at every addition.

### Dead Code

- 51/84 methods in `investmentService.js`
- 4 unused store actions + state fields in `investment.js`
- 7 orphaned PlanSections components
- `LifeEventAllocationController` (no routes)
- Multiple dead `fetchRecommendations` in module stores
- `DividendTaxCalculator` + possibly `TaxEfficiencyCalculator`
- `selectedMortgage`/`riskLevels` store state never read

---

## Recommended Action Plan

### Immediate (this week — ~4 hours of quick wins)

1. Fix 6 hardcoded tax values: B-3, B-4, B-5, B-6, V-9 (trivial 1-line fixes)
2. Delete duplicate `analyzeAssetLocation` at `investmentService.js:1029` (S-2)
3. Add `:key` to 5 `v-for` loops (V-14)
4. Fix `PensionIhtChanges2027Page.vue` banned amber tokens (V-9)
5. Wire or delete `LifeEventAllocationController` (H-6)
6. Fix `PreviewWriteInterceptor` DELETE gap (H-12)
7. Add `amount => decimal:2` cast to `PersonalAccount.php` (M-7)
8. Add `HasJointOwnership` trait to `JointAccountLog.php` (M-6)

### Short-term (this month)

1. **Float-to-decimal cast sweep** (M-1, M-2, M-9) — 70+ columns across 12 files, one PR. Highest risk-reduction ROI.
2. **Add `Auditable` trait** to Estate models + Document/Subscription/UserConsent (M-4, M-11)
3. **Delete dead code in `investmentService.js`** (S-1) — ~800 lines removal, no behaviour change
4. **Delete 7 orphaned PlanSections components** (S-4) — prevents Rule 13 violations shipping
5. **Add tests for ChattelCGTService, WhatIfScenarioService, InvestmentAgent, TaxOptimisationAgent, RevolutService** (T-1 through T-5) — financial calculation safety net
6. **Add TaxConfigService helper methods** (`getPersonalAllowance()` etc.) and refactor 20+ hardcoded fallbacks (B-10, B-11, B-19)
7. **Create missing factories** for PlanConfiguration, AiConversation, AiMessage, SubscriptionPlan (M-8)

### Backlog (large effort, plan as dedicated sprints)

1. **Refactor `RetirementIncomeService` (2,292 lines)** → extract `ProjectionEngine`, `PCLSCalculatorService`, `AllocationStrategyService` (B-1)
2. **Refactor `IHTCalculationService` (1,641 lines)** → extract `CharitableRateCalculator`, `RNRBCalculator`, `ProjectedEstateService` (B-7)
3. **Refactor `UserProfileService::getFinancialCommitments` (421-line method)** (B-2)
4. **Refactor `User.php` (716 lines)** → extract `HasSubscription` trait, `DomicileService` (M-3)
5. **Split `InvestmentController` (1,067 lines)** + migrate 53 inline-validation controllers to FormRequests (H-1, H-9)
6. **Extract `Api Resources` for Investment/Retirement/Goals/Savings/Auth** (H-10)
7. **Split `ExpenditureForm.vue` (2,574) + `RetirementIncomeTab.vue` (2,124) + `Dashboard.vue` (2,256) + `CalculatorsPage.vue` (2,489)** (V-2, V-15)
8. **Split `InvestmentAccount.php` (492 lines, 160 fillable fields)** into polymorphic sub-types (M-5)
9. **Split `investmentService.js` (1,146 lines)** after dead code removal (S-7)
10. **Split god test files** `EstatePlanRefactorTest`, `HolisticPlanRefactorTest` (T-7, T-8)

---

## Positive Findings

- **All 92 controllers have `declare(strict_types=1)`** ✓
- **All 230 services in `app/Services/` have strict_types** ✓
- **No `primary-*`, `secondary-*`, `gray-*`, `orange-*` tokens** in Vue components ✓
- **No `v-if` + `v-for` on same element** anywhere ✓
- **Mobile directory is clean** — all components under 800 lines, no banned tokens ✓
- **Vuex action naming uses British spelling consistently** (`analyse*`, `optimise*`) ✓
- **Architecture test suite enforces key conventions** (Pest Architecture tests)
- **940+ tests with 2,561+ assertions** — baseline coverage is solid in covered areas

---

*Generated 2026-04-05 via parallel multi-agent audit (6 Sonnet agents covering services, HTTP, models/DB, Vue, Vuex/services, tests/cross-cutting).*
