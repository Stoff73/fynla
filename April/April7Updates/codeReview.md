# Full Codebase Review — Fynla v0.9.4

**Date:** 7 April 2026
**Branch:** `estateDash` (15 commits ahead of main)
**Audited by:** 6 parallel agents scanning all layers
**Codebase:** 656 Vue components, 230 PHP services, 92 controllers, 90 models, 33 Vuex stores

---

## Executive Summary

| Severity | Count |
|----------|-------|
| Critical | 21 |
| Warning | 38 |
| Suggestion | 9 |
| **Total** | **68** |

| Category | Count |
|----------|-------|
| Hardcoded Tax Values | 18 |
| Dead Code | 12 |
| Convention Violations | 14 |
| Design System | 10 |
| Security | 5 |
| Complexity (God Files) | 6 |
| Inconsistency | 3 |

### Overall Health Assessment

The codebase is architecturally sound — consistent service layer pattern, proper Sanctum auth on all routes, TaxConfigService broadly adopted, `declare(strict_types=1)` on every PHP file. The two systemic weaknesses are: (1) **hardcoded tax values** scattered across ~18 locations in both PHP and JS despite the project's explicit rule against them, and (2) **~150 services with zero test coverage**, concentrated in Estate, Goals, Investment analytics, and AI modules. The dead code surface is significant (~35 dead methods in investmentService.js alone, a dead Vuex module, and a dead PHP service). Design system violations (banned color tokens, duplicate CSS) are present in ~10 components but are all trivial fixes.

---

## Quick Wins (trivial effort, high impact)

| # | File | Issue | Fix |
|---|------|-------|-----|
| 1 | `app/Models/Investment/InvestmentAccount.php:8` | Wrong namespace: imports `App\Models\Trust` instead of `App\Models\Estate\Trust` | Change to `use App\Models\Estate\Trust;` |
| 2 | `app/Models/Investment/InvestmentAccount.php:349` | `scopeIsa()` queries non-existent `is_isa` column — will SQL error | Change to `whereNotNull('isa_type')` |
| 3 | `app/Models/Investment/InvestmentAccount.php:365` | `scopeActive()` queries non-existent `status` column — will SQL error | Remove or rewrite against real column |
| 4 | `app/Services/Estate/EstateActionDefinitionService.php:164` | Hardcoded `* 0.4` IHT rate bypasses TaxConfigService | Use `$ihtConfig['standard_rate']` |
| 5 | `resources/js/components/Retirement/FundDepletionChart.vue:204` | `PCLS` acronym in user-facing chart label | Spell out "Pension Commencement Lump Sum" |
| 6 | `resources/js/components/UserProfile/FamilyMembers.vue:100` | Uses undefined CSS class `btn-secondary-sm` — button is unstyled | Change to `btn-secondary btn-sm` or add class to app.css |
| 7 | `resources/js/mobile/views/MoreMenu.vue:20` | Version string `v0.9.2` — should be `v0.9.4` | Update the literal |
| 8 | `resources/js/services/portfolioOptimizationService.js:219-235` | Dead local `formatCurrency()`/`formatPercentage()` helpers (never called) | Delete both methods |

---

## Critical Findings

### A. Model & Database Bugs

**A1. InvestmentAccount — 3 broken definitions** `app/Models/Investment/InvestmentAccount.php`
- **Line 8:** Imports `App\Models\Trust` — class doesn't exist at that namespace. Trust is at `App\Models\Estate\Trust`. The `trust()` relationship will fail at runtime.
- **Line 349:** `scopeIsa()` queries `WHERE is_isa = 1` but `investment_accounts` has no `is_isa` column (only `cash_accounts` and `savings_accounts` do). Will throw SQL error.
- **Line 365:** `scopeActive()` queries `WHERE status = 'active'` but no `status` column exists on the table. Will throw SQL error.

**A2. Float casts on 67 financial columns across 9 models** — MEDIUM effort
Binary `float` introduces rounding errors on currency values. Convention requires `decimal:2` for money, `decimal:4` for rates.

| Model | Float Columns |
|-------|---------------|
| `Estate/IHTCalculation.php` | 23 |
| `Investment/RebalancingAction.php` | 11 |
| `Investment/Holding.php` | 8 |
| `ExpenditureProfile.php` | 8 |
| `ProtectionProfile.php` | 7 |
| `Estate/IHTProfile.php` | 4 |
| `Estate/Liability.php` | 3 |
| `RecommendationTracking.php` | 2 |
| `Estate/Gift.php`, `Estate/Asset.php`, `Investment/InvestmentGoal.php`, `Investment/RiskProfile.php` | 1 each |

**A3. Duplicate migration timestamp** `database/migrations/`
Two migrations share `2026_02_21_130000` — execution order undefined. Rename one to `130001`.

---

### B. Hardcoded Tax Values (18 locations)

**CLAUDE.md Rule 3: "No Hardcoded Tax Values — use TaxConfigService for all UK tax values."**

#### Backend (PHP) — 13 locations

| File | Line(s) | Value | Should Use |
|------|---------|-------|------------|
| `Services/Estate/EstateActionDefinitionService.php` | 164 | `* 0.4` (IHT rate) | `$ihtConfig['standard_rate']` |
| `Services/Retirement/RetirementActionDefinitionService.php` | 524, 663 | `?? 12570` (PA) | `TaxDefaults::PERSONAL_ALLOWANCE` |
| `Services/Retirement/RetirementActionDefinitionService.php` | 550, 720, 833 | `?? 60000` (pension AA) | `TaxDefaults::PENSION_ANNUAL_ALLOWANCE` |
| `Services/Coordination/HouseholdPlanningService.php` | 174 | `?? 12570` (PA) | `TaxDefaults::PERSONAL_ALLOWANCE` |
| `Services/Coordination/HouseholdPlanningService.php` | 181 | `?? 20000` (ISA) | `TaxDefaults::ISA_ALLOWANCE` |
| `Services/Coordination/CrossModuleStrategyService.php` | 148 | `?? 50270` (higher rate) | `TaxDefaults::HIGHER_RATE_THRESHOLD` |
| `Services/Investment/PortfolioStrategyService.php` | 548 | `?? 50270` | `TaxDefaults::HIGHER_RATE_THRESHOLD` |
| `Services/Goals/LifeEventAllocationService.php` | 606 | `?? 50270` | `TaxDefaults::HIGHER_RATE_THRESHOLD` |
| `Services/Tax/TaxProductInfoService.php` | 145 | `?? 20000` (ISA) | `TaxDefaults::ISA_ALLOWANCE` |
| `Services/Savings/SavingsActionDefinitionService.php` | 2188 | `?? 12570` (PA) | `TaxDefaults::PERSONAL_ALLOWANCE` |
| `Services/Retirement/SalarySacrificeAnalyzer.php` | 398 | `12570` (labelled as NI threshold) | `TaxDefaults::NI_PRIMARY_THRESHOLD` (add constant) |
| `Services/Investment/ContributionEstimatorService.php` | 19 | `ISA_ALLOWANCE_FALLBACK = 20000` | `TaxDefaults::ISA_ALLOWANCE` |

#### Frontend (JS) — 5 locations

| File | Line(s) | Value | Should Use |
|------|---------|-------|------------|
| `views/Dashboard.vue` | 740 | `formatCurrency(20000)` (ISA) | `this.isaAllowance?.total_allowance` from store |
| `components/Savings/ISAAllowanceTracker.vue` | 137, 142 | `20000` / `16000` | Store `savings/isaAllowance` |
| `components/Investment/AccountStrategyCard.vue` | 181 | `20000 - ...` | Prop or store getter |
| `components/Investment/TaxOptimization.vue` | 31 | `/ 20000 * 100` | Import from `taxConfig.js` |
| `components/Estate/NRBRNRBTracker.vue` | 175-177 | `325000`, `175000`, `2000000` | Import from `taxConfig.js` |

#### Hardcoded Tax Years — 2 locations

| File | Line(s) | Value | Fix |
|------|---------|-------|-----|
| `components/Retirement/AnnualAllowanceTracker.vue` | 266-267 | `['2024/25', '2023/24', '2022/23']` | Derive dynamically from `getCurrentTaxYear()` |
| `mobile/learn/learnTopics.js` | 56 | `"£221.20 per week (2024/25)"` | Update or pull from `taxConfig.js` |

#### Hardcoded Financial Rates — 4 locations

| File | Line(s) | Value | Fix |
|------|---------|-------|-----|
| `components/Protection/ProtectionOverviewCard.vue` | 324 | SSP `118.75` | Add `SSP_WEEKLY_RATE` to `taxConfig.js` |
| `components/Protection/GapAnalysis.vue` | 584 | SSP `118.75` | Same constant |
| `components/Protection/CurrentSituation.vue` | 692 | SSP `118.75` | Same constant |
| `components/UserProfile/IncomeOccupation.vue` | 166 | HICBC `60000` | Add `HICBC_THRESHOLD` to `taxConfig.js` |
| `components/UserProfile/IncomeDefinitionsPanel.vue` | 62, 79 | Pension taper `200000`/`260000` | Add to `taxConfig.js` |
| `views/Public/CalculatorsPage.vue` | 1471 | `50270` in template | Use computed from `taxConfig.js` |

---

### C. Security Issues

**C1. SQL injection pattern** — `app/Console/Commands/SendProtectionAlerts.php:85,177` and `SendPolicyRenewalReminders.php:61`
PHP string interpolation inside `DB::raw("COALESCE(provider, '{$defaultName}')")`. Currently `$defaultName` is from a hardcoded array (not user input), but the pattern is dangerous if copied. Fix: Use `selectRaw('COALESCE(provider, ?) as policy_name', [$defaultName])`.

**C2. XSS via v-html** — `resources/js/layouts/PublicLayout.vue:73`
`v-html="stage.menuName || stage.name"` without sanitisation. The `sanitizeHtml` utility exists but isn't imported here. Fix: Use `{{ }}` interpolation (no HTML needed for display names) or import `sanitizeHtml`.

**C3. Production password in VCS** — `database/seeders/ChrisUserSeeder.php:47`
`Hash::make('Password1!')` — the plaintext production credential is committed. Fix: Use `env('CHRIS_SEED_PASSWORD', 'local-only-fallback')`.

**C4. File upload MIME bypass** — `app/Services/Estate/LpaDocumentService.php:51`
Uses `getClientOriginalExtension()` (client-supplied) instead of `getMimeType()` (server-detected). The HTTP layer validates MIME types, but if the service is called from a non-HTTP context there's no safety net.

**C5. Raw Eloquent models in API responses** (data exposure risk)
- `Estate/TrustController.php:83,127` — returns raw `$trust` instead of `TrustResource`
- `MortgageController.php:61,152,187,262` — uses `->toArray()` instead of `MortgageResource`

---

### D. Dead Code

**D1. ~35 dead methods in `resources/js/services/investmentService.js`** (lines 247-1134)
Groups: goal analysis (8 methods), fee impact (8), tax optimisation (3), asset location (4), performance attribution (5), model portfolio (7), rebalancing strategy (6), efficient frontier (8). None called from any component or store. Confirmed by grep.

**D2. Dead Vuex module `taxOptimisation`** — `resources/js/store/modules/taxOptimisation.js`
Registered in store but never dispatched from any component. Two actions (`fetchOptimisationAnalysis`, `fetchStrategies`) that will never fire.

**D3. 7 dead Vuex actions in `investment.js`** (lines 605-768)
`optimiseContributions`, `calculateAffordability`, `analyseLumpSumVsDCA`, `analyseAssetLocation`, `analysePerformanceAttribution`, `compareBenchmarks`, `analyseFees` — none dispatched by any component. `ContributionPlanner.vue` calls the API directly, bypassing the store.

**D4. Dead PHP service** — `app/Services/Estate/GiftingTimelineService.php`
67 lines, zero external references. Functionality overlaps with `GiftingStrategy.php` and `GiftingStrategyOptimizer.php`.

**D5. Dead controller method** — `app/Http/Controllers/Api/SavingsController.php:~612`
`updateGoalProgress()` marked `@deprecated Since v0.7.0`, no route points to it. Delete.

**D6. Dead `formatCurrency`/`formatPercentage`** — `resources/js/services/portfolioOptimizationService.js:219-235`
Local helpers never called from anywhere.

---

### E. Design System Violations

**E1. Banned color tokens** (`blue-*`, `green-*`, `red-*` outside exempt badge contexts)

| Component | Tokens Used | Replace With |
|-----------|-------------|--------------|
| `UserProfile/SpouseDataSharing.vue` | `bg-blue-50`, `text-blue-600/700/800`, `bg-green-50`, `text-green-600/700/800`, `bg-red-50` | `light-blue-*`, `spring-*`, `raspberry-*` |
| `UserProfile/FamilyMembers.vue:74-86` | `bg-blue-100 text-blue-800`, `text-green-600` | Design system badge tokens, `spring-600` |
| `UserProfile/TaxIncomeCard.vue` | `text-green-600/700`, `text-red-600`, `bg-blue-*` | `spring-*`, `raspberry-*`, `light-blue-*` |
| `UserProfile/IncomeOccupation.vue` | `text-blue-500/700/800`, `bg-success-50` | `horizon-*`, `spring-*` |
| `NetWorth/Property/PropertyForm.vue` | `focus:ring-green-500` (6 inputs) | `focus:ring-violet-500` |
| `NetWorth/BusinessInterestForm.vue:496` | `focus:ring-green-500` | `focus:ring-violet-500` |

**E2. Duplicate global CSS classes in scoped styles**
- `Retirement/RetirementIncomeTab.vue:1533-1548` — redefines `.btn-secondary` (exists in `app.css:46`)
- `Estate/AssetForm.vue:697-704` — redefines `.btn-secondary` with different hover colors

**E3. Hardcoded hex in style block**
- `Dashboard/GoalsProjectionChartDashboard.vue:479` — `#DDE2EF` in `<style scoped>`. Matches `horizon-100`.

**E4. Hardcoded hex in JS template literal**
- `UserProfile/LetterToSpouse.vue:1375-1430` — print document CSS uses old-system hex codes (`#dbeafe`, `#1e40af`, `#dcfce7`, `#166534`)

**E5. Acronyms in user-facing text**
- `Retirement/FundDepletionChart.vue:204` — `PCLS (Tax-Free)` in chart legend
- `NetWorth/BusinessInterestForm.vue:505` — `BPR can reduce...` without expansion
- `Admin/TaxSettings.vue:1102` — `MPAA` in label (admin screen, lower priority)

---

### F. Convention Violations

**F1. 13 controllers missing `readonly` on constructor injection**
`PasswordResetController`, `TrustController`, `GiftingController`, `RiskPreferenceController`, `LifePolicyController`, `InfoGuideController`, `LetterToSpouseController`, `AssumptionsController`, `OnboardingController`, `BusinessInterestController`, `PersonalAccountsController`, `UserProfileController`, `InvestmentProjectionController`.

**F2. Response envelope violations** — Controllers returning non-standard JSON shapes
- `Investment/PortfolioStrategyController.php:26-46` — raw service array, no `{success, data}` envelope
- `PaymentController.php:139-142` — `{token, order_id}` without envelope (intentional for Revolut SDK but undocumented)
- `PaymentController.php:526` — `{payments: [...]}` without `success` key

**F3. 13 Vue components with duplicate `formatNumber` methods** instead of `currencyMixin`
5 in `Investment/PlanSections/` + 8 more in `Investment/` module components.

**F4. 8 single-word Vue component names**
`Goals.vue`, `Holdings.vue`, `Performance.vue`, `Recommendations.vue`, `Dashboard.vue`, `Login.vue`, `Register.vue`, `Help.vue`.

**F5. `Validator::make()` instead of `$request->validate()` in 16 controllers** (62 occurrences)
Redundant boilerplate. Heavy users: `EfficientFrontierController` (7), `RebalancingCalculationController` (6), `RebalancingStrategiesController` (5), `AdminController` (5).

**F6. 4 models missing `Auditable` trait** on financial-change tracking models
`RecommendationTracking`, `RebalancingAction`, `Payment`, `LifeEventAllocation`.

**F7. 14 models missing factory files** — entire Estate module has zero factories
`Asset`, `Bequest`, `Gift`, `IHTCalculation`, `IHTProfile`, `LastingPowerOfAttorney`, `Liability`, `Trust`, `Will`, `InvestmentAccount`, `InvestmentScenario`, `RiskProfile`, `SavingsGoal`, `ISAAllowanceTracking`.

**F8. User model uses `$guarded` instead of `$fillable`**
`app/Models/User.php:28-42` — opt-out mass assignment on the most sensitive model. 60+ columns including financial data.

**F9. `console.log` in production code** (2 locations)
`resources/js/services/api.js:60` and `resources/js/bootstrap.js:31` — fire on every Capacitor app startup. Wrap in `import.meta.env.DEV`.

---

### G. Complexity — God Files

#### Backend (>800 lines)

| File | Lines | Notes |
|------|-------|-------|
| `Services/Savings/SavingsActionDefinitionService.php` | 3,675 | 61 methods |
| `Services/Retirement/RetirementActionDefinitionService.php` | 2,701 | 32 methods |
| `Services/Protection/ProtectionActionDefinitionService.php` | 2,349 | |
| `Services/Retirement/RetirementIncomeService.php` | 2,292 | Known |
| `Services/Retirement/RetirementStrategyService.php` | 2,141 | |
| `Services/Estate/ComprehensiveEstatePlanService.php` | 1,308 | |
| `Services/Investment/InvestmentActionDefinitionService.php` | 1,486 | |
| `Services/Investment/Recommendation/ContributionWaterfallService.php` | 1,024 | |
| `Services/UserProfile/UserProfileService.php` | 1,099 | |
| `Services/AI/AIExtractionService.php` | 965 | |
| `Services/AI/SystemPromptBuilder.php` | 963 | |
| `Services/AI/AiToolDefinitions.php` | 974 | |
| `Http/Controllers/Api/InvestmentController.php` | 1,067 | Known |

#### Frontend (>1000 lines)

| File | Lines | Notes |
|------|-------|-------|
| `Admin/TaxSettings.vue` | 2,932 | Known |
| `views/CalculatorsPage.vue` | 2,327 | Known |
| `UserProfile/ExpenditureForm.vue` | 2,355 | Known |
| `views/Dashboard.vue` | 2,034 | Known |
| `Retirement/RetirementIncomeTab.vue` | 1,833 | |
| `views/Version.vue` | 1,731 | |
| `NetWorth/Property/PropertyForm.vue` | 1,724 | |
| `NetWorth/PensionList.vue` | 1,651 | |
| `UserProfile/LetterToSpouse.vue` | 1,644 | |
| `Estate/IHTPlanning.vue` | 1,539 | |
| `Onboarding/OnboardingWizard.vue` | 1,449 | |
| `NetWorth/InvestmentProjections.vue` | 1,357 | |
| `Onboarding/steps/AssetsStep.vue` | 1,317 | |
| `Investment/AccountForm.vue` | 1,172 | |
| `views/PrivacySettings.vue` | 1,125 | |
| `NetWorth/InvestmentList.vue` | 1,122 | |
| `Retirement/CapitalAdequacyTab.vue` | 1,138 | |
| `Retirement/RequiredCapitalDetail.vue` | 1,089 | |

---

### H. Test Coverage

**~150 services have zero test coverage.** The most critical gaps:

| Area | Untested Services | Risk |
|------|-------------------|------|
| **Payment** | RevolutService, TrialService, DataPurgeService | Real money |
| **Estate** | 14 services including IHTCalculationService (core IHT engine) | Core module |
| **Goals** | 11 services | Full module |
| **Investment Analytics** | ~30 services across Performance, Rebalancing, AssetLocation, Tax, ModelPortfolio, Analytics, Efficient Frontier | Entire advanced analytics layer |
| **Retirement** | 7 services including RetirementIncomeService (2,292 lines) | Core calculations |
| **AI/Agent** | XaiClient, SystemPromptBuilder, AiToolDefinitions | External API integration |

### I. Dependencies

- `composer.json` requires `php: ^8.1` but codebase targets 8.2 — tighten to `^8.2`
- `openai-php/client: ^0.19.1` — pre-1.0, no longer maintained for security. Evaluate `^1.0`
- `axios: ^1.6.4` — has known patched vulnerabilities in 1.6.x. Consider `^1.7`
- `vuex-persistedstate: ^4.1.0` — unmaintained since 2022

---

## What's Clean

- **All 230 PHP files** have `declare(strict_types=1)` — no exceptions
- **All controllers** use constructor injection with proper DI
- **Auth middleware** correctly applied to all non-public routes — no gaps found
- **PreviewWriteInterceptor** EXCLUDED_ROUTES is comprehensive and correct
- **CSRF/rate limiting** properly configured on all sensitive endpoints
- **No `$guarded = []`** anywhere (User model uses populated `$guarded` array, not empty)
- **No hardcoded API keys** in source — all use `env()` correctly
- **No `{!! !!}`** unescaped Blade output
- **TaxConfigService** is broadly adopted — the violations are fallback values, not primary lookups
- **MonteCarloEngine** inheritance is correctly structured
- **Joint ownership pattern** consistently implemented with `CalculatesOwnershipShare` trait
- **Sanctum tokens** properly scoped and rotated on login

---

## Recommended Action Plan

### Immediate (this week) — 8 items, ~2 hours

1. Fix 3 InvestmentAccount bugs (broken scopes + wrong Trust import) — **trivial**
2. Fix hardcoded `* 0.4` IHT rate in EstateActionDefinitionService — **trivial**
3. Fix undefined `btn-secondary-sm` class in FamilyMembers.vue — **trivial**
4. Fix `PCLS` acronym in FundDepletionChart chart label — **trivial**
5. Update MoreMenu.vue version to v0.9.4 — **trivial**
6. Delete dead `GiftingTimelineService.php` — **trivial**
7. Delete dead `updateGoalProgress()` in SavingsController — **trivial**
8. Wrap 2 `console.log` calls in dev guard — **trivial**

### Short-term (this month) — 12 items, ~1-2 days

9. Replace all 12 inline numeric fallbacks in PHP services with `TaxDefaults::*` constants
10. Replace 5 hardcoded tax values in frontend JS with `taxConfig.js` imports
11. Fix 3 SSP rate hardcodes in Protection components
12. Fix banned color tokens in 6 Vue components
13. Remove 2 duplicate `.btn-secondary` scoped style blocks
14. Add `readonly` to 13 controller constructors
15. Delete ~35 dead methods in investmentService.js
16. Delete dead `taxOptimisation` Vuex module + 7 dead investment store actions
17. Delete dead `formatCurrency`/`formatPercentage` in portfolioOptimizationService.js
18. Fix 3 response envelope violations in controllers
19. Use `TrustResource` and `MortgageResource` instead of raw model/toArray
20. Fix SQL injection pattern in 2 artisan commands

### Backlog (large effort)

21. Float-to-decimal cast sweep: 67 columns across 9 models — **medium**
22. Add `Auditable` trait to 4 models — **trivial per model**
23. Create 14 missing factory files (Estate module priority) — **medium**
24. Switch User model from `$guarded` to `$fillable` — **small**
25. Replace `Validator::make()` with `$request->validate()` in 16 controllers — **medium**
26. FormRequest migration for 10+ write-operation controllers — **medium**
27. Rename 8 single-word Vue component names — **small**
28. Replace 13 duplicate `formatNumber` methods with `currencyMixin` — **small**
29. Derive carry-forward years dynamically in AnnualAllowanceTracker — **small**
30. God class refactors (SavingsActionDefinitionService 3,675L etc.) — **large**
31. God component splits (TaxSettings 2,932L, ExpenditureForm 2,355L etc.) — **large**
32. Test coverage: prioritise RevolutService, IHTCalculationService, RetirementIncomeService — **large**
33. Upgrade `openai-php/client` to ^1.0, `axios` to ^1.7 — **small**

---

## Comparison with Previous Review (18 March 2026)

The March review fixed 94/94 items. Since then:

**Improved:**
- Tax values now use `TaxConfigService` broadly (was ~40% adoption, now ~90%)
- `declare(strict_types=1)` is 100% (was ~95%)
- API Resources adopted for most new controllers
- Legacy `HasAiChat` code removed (760 lines)
- Auth flow properly secured with MFA

**Regressed or Unchanged:**
- Float-to-decimal sweep still not done (was flagged in March)
- God class sizes have grown (SavingsActionDefinitionService was ~3000L, now 3675L)
- InvestmentAccount broken scopes existed in March and still unfixed
- Dead investmentService.js methods still present (was 51, now ~35 after partial cleanup)
- Estate module still has zero factory files

---

*Generated by full codebase tech debt audit, 7 April 2026*
*6 parallel agents: backend services, controllers/HTTP, models/database, Vue components, frontend stores/services, tests/security*
