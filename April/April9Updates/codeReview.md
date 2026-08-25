# Code Review — 9 April 2026

**Session:** Full codebase tech debt audit + fix session
**Codebase:** Fynla v0.9.4
**Files scanned:** 1,246 (537 Vue, 234 Services, 93 Controllers, 94 Models, 35 Stores, 197 Tests)
**Total issues found:** 68
**Issues fixed this session:** 45
**Issues deferred:** 23

---

## What Was Fixed (45 issues)

### Broken Features (2)
- **$toast global registered in app.js** — 23 success/failure notifications in Settings + MFA were silently swallowed because `$toast` was never registered as a Vue global property
- **PSACalculator semantic bug fixed** — `determineTaxBand()` returned `'basic'` for below-personal-allowance earners instead of `'non_taxpayer'`, assigning wrong PSA tier

### Hardcoded Tax Values Replaced (14)
**Backend (10 services):**
- `DecumulationPlanner.php` — hardcoded `12,570` and `50,270` in user-facing tips
- `RetirementActionDefinitionService.php` — hardcoded `50,270` / `125,140` in threshold string
- `SavingsActionDefinitionService.php` — hardcoded `0.20/0.40/0.45` tax rates (2 locations)
- `PersonalizedTrustStrategyService.php` — `?? 0.40` fallback x5 replaced with `TaxDefaults::IHT_RATE`
- `CGTHarvestingCalculator.php` — `?? 0.20` replaced with config CGT rate
- `TrustService.php` — hardcoded `0.45` / `0.3935` trust rates
- `UKTaxCalculator.php` — hardcoded `0.45` default trust rate
- `AssetLocationOptimizer.php` — hardcoded `0.10/0.20` CGT rates
- `ContributionWaterfallService.php` — hardcoded tax relief rate arrays (2 locations)

**Frontend (10 components + 1 store):**
- `GiftForm.vue`, `IHTPlanning.vue`, `GiftingStrategy.vue` — `3,000` annual gift exemption
- `WrapperOptimizer.vue`, `TaxEfficiencyPanel.vue`, `AccountRebalancingPanel.vue`, `PropertyTaxCalculator.vue` — `3,000` CGT allowance
- `TrustsDashboard.vue`, `TrustPlanningStrategy.vue` — `325,000` NRB
- `AnnualAllowanceTracker.vue` — `10,000` MPAA
- `investment.js` — ISA allowance `|| 20000` fallback

### Dead Code Removed (12)
- Deleted `guidance` module (store + 2 components) — never imported by any parent
- Deleted `taxOptimisationService.js` — zero imports
- Deleted `PythonAgentBridge.php` — zero references
- Removed 4 dead DC Pension Holdings actions from `retirement.js`
- Removed 6 dead actions + state from `recommendations.js`
- Removed 7 dead getters from `completeness.js`
- Removed dead methods from 5 API services (advisorService, netWorthService, dcPensionHoldingsService, portfolioOptimizationService, whatIfService)
- Removed 8 unused scopes from 3 models (Goal, Holding, LoginAttempt)
- Removed ghost `status` methods from InvestmentScenario model

### Convention Fixes (8)
- Fixed 5 single-word component names (Footer, Navbar, Version, Projections, Recommendations)
- Removed 3 bare `localStorage.removeItem` calls — now use `tokenStorage.js` abstraction
- Added `Auditable` trait to Invoice model
- Created 2 missing factories (DiscountCodeUsage, Referral)
- Added `Mockery::close()` to 6 test files
- Extracted route closure to `TaxYearController`

### Test Results
- **2,184 passing** / 2 pre-existing failures (risk_level enum constraint)
- DecumulationPlannerTest mock updated for new `getIncomeTax()` call
- Net code change: 58 files, +551 / -1,755 lines (**-1,204 net**)

---

## Deferred Items (23 issues)

### Priority 1 — Next Session (medium effort, high value)

#### DUP-01: Consolidate `determineTaxBand` — 7 duplicate implementations
**Files:**
- `app/Services/Tax/TaxActionDefinitionService.php:363`
- `app/Services/Tax/TaxOptimisationService.php:474`
- `app/Services/Coordination/HouseholdPlanningService.php:487`
- `app/Services/Goals/LifeEventAllocationService.php:598`
- `app/Services/Investment/PortfolioStrategyService.php:536`
- `app/Services/Investment/Recommendation/UserContextBuilder.php:361`
- `app/Services/AI/SystemPromptBuilder.php:920`

**Fix:** Add a public `determineTaxBand(float $grossIncome): string` method to `UKTaxCalculator`. Update all 7 services to inject `UKTaxCalculator` and call it instead of their private method.
**Effort:** 3-4 hours

#### DUP-02: Consolidate DC pension annual contribution calculation — 5 duplicates
**Files:**
- `app/Services/Retirement/PensionProjector.php:324`
- `app/Services/Retirement/PensionContributionOptimizer.php:188`
- `app/Services/Retirement/RetirementActionDefinitionService.php:2683`
- `app/Services/Plans/RetirementPlanService.php:415`
- `app/Services/Retirement/AnnualAllowanceChecker.php:261`

**Fix:** Extract to a shared trait or make public on `PensionProjector`.
**Effort:** 2-3 hours

#### DUP-03: Consolidate pension tax relief calculation — 3 duplicates
**Files:**
- `app/Services/Retirement/PensionContributionOptimizer.php:251`
- `app/Services/Investment/ContributionOptimizer.php:304`
- `app/Services/Savings/SavingsActionDefinitionService.php:2192` (now uses config, but still duplicated logic)

**Effort:** 1-2 hours

#### DUP-04: Consolidate `calculateFutureValue` — 5 duplicates
**Files:**
- `app/Services/Estate/FutureValueCalculator.php:210` (shared service — exists but not injected)
- `app/Services/Retirement/RequiredCapitalCalculator.php:307`
- `app/Services/Plans/InvestmentPlanService.php:950`
- `app/Services/Estate/LifePolicyStrategyService.php:480`
- `app/Services/Estate/LifeCoverCalculator.php:336`

**Fix:** Inject `FutureValueCalculator` into the 4 services that have private copies.
**Effort:** 1-2 hours

#### CONV-03: Migrate 22 Vue components from direct `@/utils/currency` import to `currencyMixin`
**Files:** All in `components/UserProfile/`, `components/Onboarding/steps/`, `components/Protection/`
**Effort:** 2-3 hours (mechanical)

### Priority 2 — Short-term Backlog (large effort)

#### VULN-01: NPM vulnerabilities — 14 (3 moderate, 11 high)
- `npm audit fix --force` required (breaking changes: Vite, Capacitor CLI, tar)
- Must test: PWA, mobile iOS build, Vite dev server, production build
- **Effort:** 4-6 hours with testing

#### CONV-05: Extract DB facade from 8 controllers to services
**Files:** `DCPensionHoldingsController`, `RetirementController`, `InvestmentController`, `PaymentController`, `WebhookController`, `PreviewController`, `FamilyMembersController`, `TaxSettingsController`
**Effort:** 4-6 hours

#### ARCH-02: Merge `refreshCompleteness` — same API called from both `lifeStage` and `completeness` stores
**Effort:** 2-3 hours

#### ARCH-04: AdequacyScorer still exposes score data to API (Rule #13)
**Effort:** 3-4 hours

#### ARCH-05: Property equity calculation conflicting semantics
`PropertyService.php:27` vs `PropertyCalculationService.php:35` — ownership percentage applied differently
**Effort:** 2-3 hours (needs investigation first)

### Priority 3 — Multi-Sprint Backlog

#### God Class Decomposition (16 files)

**Backend services >2,000 lines:**
| File | Lines |
|------|-------|
| `SavingsActionDefinitionService.php` | 3,675 |
| `RetirementActionDefinitionService.php` | 2,701 |
| `ProtectionActionDefinitionService.php` | 2,349 |
| `RetirementIncomeService.php` | 2,292 |
| `RetirementStrategyService.php` | 2,141 |

**Vue components >2,000 lines:**
| File | Lines |
|------|-------|
| `Admin/TaxSettings.vue` | 3,068 |
| `UserProfile/ExpenditureForm.vue` | 2,574 |
| `Public/CalculatorsPage.vue` | 2,471 |
| `Dashboard.vue` | 2,215 |
| `Retirement/RetirementIncomeTab.vue` | 2,107 |

**Controllers >500 lines:** InvestmentController (1,070), PaymentController (871), AdminController (794), GoalsController (792), RetirementController (789), AuthController (777)

#### DB-01: Float-to-decimal cast migration — 65 columns across 9 models
- `ExpenditureProfile`, `ProtectionProfile`, `IHTCalculation`, `IHTProfile`, `Estate/Asset`, `Estate/Gift`, `Estate/Liability`, `Investment/Holding`, `Investment/RebalancingAction`
- Requires API Resource updates before changing to avoid breaking frontend
- **Effort:** Full sprint

#### CONV-01: Form Request migration — 26 controllers with inline validation
- ~78 new Form Request classes needed
- Investment sub-controllers (RebalancingCalculation, EfficientFrontier, TaxOptimization) have zero Form Requests
- **Effort:** Full sprint

#### CONV-02: API Resource adoption — 92/93 controllers return raw JSON
- 22 Resource classes exist but rarely used
- Start with new endpoints and high-churn resources
- **Effort:** Ongoing, incremental

#### TEST-01: Test coverage — ~85 untested services
- Priority: `IHTCalculationService` (1,641 lines, backbone of Estate), all Investment Analytics (~35 services), 6 Retirement calculation services, `RevolutService`, `InvoiceService`
- Current: ~20% coverage. Target: 40%
- **Effort:** Multi-sprint

#### CONV-07: Vuex mutation naming — SCREAMING_SNAKE vs camelCase across 17 modules
- **Effort:** Large if full migration, small if per-module

#### DB-04: InvestmentAccount 164-field $fillable — wide-table anti-pattern
- Extract employee share scheme + private company fields to child models
- **Effort:** Large

---

## Pre-existing Issues (not from this session)

- **AutoRiskCalculatorTest** — `risk_level` column enum doesn't accept `medium_low`. 2 test failures. Needs migration to add the value or fix the calculator output.
- **`error-*` Tailwind token** still used in 43 files (should be `raspberry-*`). Deferred from March code review.

---

*Generated 9 April 2026 — Full tech debt audit session*
