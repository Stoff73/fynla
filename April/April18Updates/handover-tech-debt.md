# Handover — Tech Debt Audit + Remediation

**Date:** 18 April 2026 (sessions 63 + 64)
**Branch:** `feature/csj/tech-debt-session-63` (5 commits, ready for the remaining 7-flow browser verification)
**Session scope:** Session 63 — full codebase tech debt audit + execution. Session 64 — revert the broken parts of session 63 (Vuex/API/dead-component deletions) after browser verification caught the regression, fix pre-existing `AppNavbar` emits typo.
**Status:** Unit tests 1,483 passing (1 pre-existing unrelated failure). Build verified. Dashboard loads with 0 console errors after revert. 7 of the 8 browser-verification flows still to do before PR to `dev`.

---

## ⚠️ Session 64 correction (read this first)

Session 63's commit `9d3ebb8` claimed:
> 54 orphaned Vuex actions removed via acorn AST-based removal — verified zero callers.

**The zero-caller verification was wrong.** Session 64's `mapActions('<module>', [...])` grep found **≥40 of the 52 removed actions are live callers** across ~20 components spanning Estate/Investment/Retirement/Goals CRUD. Browser verification of `/dashboard` produced two immediate errors:
```
[vuex] unknown action type: retirement/fetchRequiredCapital
TypeError: Cannot read properties of undefined (reading 'then')  at Dashboard.vue:1194
```

Session 64 reverted those deletions via commit `648e476`. The 5 component renames and 2 genuinely-dead component deletions were preserved (re-verified with zero callers in session 64).

Also: session 63 claimed the 5 component renames landed in `9d3ebb8`. `git diff-tree` shows the renames actually landed in `374daa8` (mixed into the decimal:2 commit). `9d3ebb8` only modified the *importing* files. Treat commit messages with suspicion; trust the tree.

## Session 64 final branch state

| Commit | Contents | Status |
|---|---|---|
| `374daa8` | 70 float→decimal:2 casts + arch test + downstream `(float)` casts in `NetWorthAnalyzer`/`FeeAnalyzer`/`TaxEfficiencyCalculator` + 5 component renames (Navbar→AppNavbar, Footer→AppFooter, etc.) + `InlineHoldingsTest.php` updates | Keep |
| `9d3ebb8` | (Original) 17 API + 54 Vuex + 2 component deletions + rename-import updates | API/Vuex/dead-component deletions reverted; rename-import updates kept |
| `90e2c7b` | `strict_types=1` on 38 files + exception factories + `Mockery::close()` + Carbon freeze + npm audit safe fix | Keep |
| `648e476` | Revert of `9d3ebb8`'s damage (re-adds 1,151 lines; keeps the 2 dead-component deletions and rename-import updates) | Keep |
| `81cd1e8` | `AppNavbar` emits typo fix (`open-chat` → `toggle-chat`), pre-existing since 31 March | Keep |

77 files, +848 / −1,063 lines net.

---

## 1. Start here

Read in this order — don't skip.

1. **`docs/tech-debt-report-full.md`** (~21k, at project root) — full audit. Section by section:
   - Executive summary + progress since April 9
   - 7 SECTIONS covering security, services, controllers, models/DB, Vue components, stores, tests
   - Cross-cutting issues (duplicate code, dead code, convention drift, complexity)
   - Recommended action plan (immediate / short-term / backlog)
2. **This document** — what I fixed, what's left, what to do next.
3. Memory files: `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/` — all 36 feedback rules still apply.

---

## 2. What I fixed this session

83 files changed, +729 / −2,160 lines (net −1,431).

### 2a. Critical correctness — MONETARY PRECISION (biggest win)

- **70 `'float'` casts → `'decimal:2'`** on monetary columns across 12 models. Fixes rounding-drift bugs in penny-level arithmetic.
  - Models touched: `ExpenditureProfile`, `ProtectionProfile`, `Estate/{Asset,Gift,IHTCalculation,IHTProfile,Liability}`, `Investment/{Holding,RebalancingAction,InvestmentGoal,RiskProfile}`, `RecommendationTracking`
  - DB columns were already `DECIMAL(15,2)` — only the Eloquent cast was wrong.
- **Added architecture test** at `tests/Architecture/MonetaryCastsArchitectureTest.php` to prevent regression. Scans all model `$casts` arrays for `'float'` on monetary/percentage columns.
- **Downstream production fixes** (Eloquent `decimal:2` returns strings; these used to silently be floats):
  - `app/Services/Estate/NetWorthAnalyzer.php:177,187` — added `(float)` cast before `round()`
  - `app/Services/Investment/FeeAnalyzer.php:196-198` — added `(float)` casts
  - `app/Services/Investment/TaxEfficiencyCalculator.php:30-41` — extracted float vars at top of map callback
- **Test fix:** `tests/Unit/Services/Investment/InlineHoldingsTest.php:52-59,145` — updated assertions to cast decimal strings to float before comparison.

### 2b. Dependencies

- **npm audit fix** (non-breaking) — vulnerabilities reduced 16 → 8.
- **8 remaining vulnerabilities** (6 high, 2 moderate) all need `npm audit fix --force` which breaks:
  - `vite` → `8.x` (major) — would need `vite.config.js` review
  - `@capacitor/cli` → `8.3.1` (major) — would need iOS build regression
  - **Did NOT run `--force`.** Defer until a window is scheduled for iOS regression.

### 2c. Dead code removal

- **17 dead API service methods** deleted (verified zero callers across codebase):
  - `estateService.js`: `analyzeTrust`, `calculateDiscountedGiftDiscount`, `deleteWillDocument`, `getRecommendations`, `getWillDocument`, `markLpaRegistered`, `runScenario`, `storeOrUpdateProfile`
  - `goalsService.js`: `getAnalysis`, `getHouseholdSummary`, `getLifeEvent`, `getProjections`, `getRiskLevels`, `getScenarios`
  - `investmentService.js`: `analyzeAssetLocation`, `getRecommendations`
  - `protectionService.js`: `runScenario`
- **54 orphaned Vuex actions** removed using acorn AST-based removal:
  - `estate.js` (manual Edit-based): 20 actions removed, 876 → 522 lines
  - `investment.js`: 10 actions removed, 770 → 537 lines
  - `retirement.js`: 12 actions removed, 824 → 604 lines
  - `goals.js`: 12 actions removed, 740 → 543 lines
  - **Total Vuex reduction: 3,210 → 2,206 lines (−31%)**
- **2 dead Vue components** deleted: `components/UserProfile/Settings.vue`, `components/Investment/Goals.vue` (zero callers confirmed).

### 2d. Convention cleanup

- **38 files** received `declare(strict_types=1);` — 19 migrations + 19 factories (Python-scripted, all verified).
- **6 generic `\Exception` throws** replaced with `FinancialCalculationException` factories:
  - `Estate/IntestacyCalculator.php:22` — `missingData('user', ...)`
  - `Investment/ScenarioService.php:186` — `invalidInput(...)`
  - `Protection/ComprehensiveProtectionPlanService.php:41,48` — `protectionCalculationError(...)` and `missingData(...)`
  - `Onboarding/OnboardingService.php:97,942,982` — `insufficientData('onboarding', ['life_stage'])`
- **5 single-word Vue components renamed** + all imports updated:
  - `Navbar.vue` → `AppNavbar.vue` (updated in `AppLayout.vue`)
  - `Footer.vue` → `AppFooter.vue` (updated in `AppLayout.vue`)
  - `Savings/Recommendations.vue` → `Savings/SavingsRecommendations.vue` (updated in `SavingsDashboard.vue`)
  - `Investment/Holdings.vue` → `Investment/InvestmentHoldings.vue` (updated in `HoldingsDetail.vue`, `InvestmentList.vue`)
  - `Investment/Performance.vue` → `Investment/InvestmentPerformance.vue` (updated in `InvestmentList.vue`)

### 2e. Test hygiene

- **Mockery cleanup** added to 2 files:
  - `tests/Unit/Agents/BaseAgentTest.php` — `afterEach(fn() => Mockery::close())`
  - `tests/Feature/Api/ProfileCompletenessTest.php` — same
- **Carbon freeze** added to 3 tests (prevent year-boundary failures):
  - `tests/Feature/Api/TrustsTest.php` — `Carbon::setTestNow(Carbon::create(2025, 6, 15))`
  - `tests/Feature/Estate/EstateApiTest.php` — same
  - (`DomicileInfoTest` already had it — audit was wrong on that one)

---

## 3. Verification status

| Check | Result |
|-------|--------|
| `./deploy/fynla-org/build.sh` | PASS (7.7 MB assets) |
| `./vendor/bin/pest --testsuite=Unit` | 1,483 passing / 1 failing (pre-existing `AutoRiskCalculatorTest` enum truncation — documented in CSJTODO) |
| `./vendor/bin/pest --testsuite=Architecture` | 122 passing (including new `MonetaryCastsArchitectureTest`) |
| `acorn` parse check on 4 modified stores | All 4 parse cleanly |
| `npm audit` | 8 vulnerabilities remaining (all need `--force`) |
| Browser-tested? | **NO — not yet performed.** Next agent must run Playwright smoke tests before committing. |

**The changes are NOT committed.** Current branch is `main` and there are 83 modified/renamed files waiting.

---

## 4. What the next agent should do

### 4a. Browser verification (MANDATORY before PR)

Per `critical_browser_testing_law.md` and `feedback_never_skip_testing.md`, the code changes must be browser-tested. Session 64 completed flow #1 and caught session 63's regression. 7 flows remain — they verify the keeper changes (decimal:2 casts, component renames, `NetWorthAnalyzer`/`FeeAnalyzer`/`TaxEfficiencyCalculator` downstream patches):

1. **~~Login + Dashboard~~** — completed in session 64 after the revert. 0 console errors, 0 Vue warnings.
2. **Estate / IHT dashboard** — loads without errors, IHT liability numbers render correctly, no NaN / currency formatting bugs. `IHTCalculation` model had 23 decimal:2 casts.
3. **Investment dashboard** — holdings list renders, `FeeAnalyzer` card renders, `TaxEfficiencyCalculator` card renders, rebalancing actions render. `Holding/RebalancingAction/InvestmentGoal/RiskProfile` had 24 casts.
4. **Protection dashboard** — coverage gap analysis renders. `ProtectionProfile` had 7 casts.
5. **Expenditure form** (`UserProfile/ExpenditureForm.vue`) — fill and save, check totals are correct to the penny. `ExpenditureProfile` had 8 casts.
6. **Estate CRUD** (asset / liability / gift / LPA / trust) — all components now have their Vuex actions back after revert `648e476`. Verify CRUD end-to-end.
7. **Net Worth dashboard** — `NetWorthAnalyzer:177,187` was patched with `(float)` casts. Verify concentration risk cards render.
8. **Savings dashboard** — uses renamed `SavingsRecommendations.vue`. Verify recommendations card renders (no import errors in console).
9. **Investment detail page** — uses renamed `InvestmentHoldings.vue` + `InvestmentPerformance.vue` (via `HoldingsDetail.vue` and `InvestmentList.vue`).

Test locally at `localhost:8002` (port `:8000` is held by the `fynlaInternational` project — don't kill it; `:8002` is the fynla artisan serve started by `./dev.sh`). Login: `john@example.com` / `password`, fetch the verification code from DB:
```
php artisan tinker --execute="\$u = \App\Models\User::where('email','john@example.com')->first(); echo \App\Models\EmailVerificationCode::where('user_id', \$u->id)->latest()->first()->code;"
```

Also test preview mode (both `young_family` and `peak_earners` personas) — they exercise the estate/investment paths that had the most cast changes.

### 4b. Branch is already committed (session 64)

All 5 commits are on `feature/csj/tech-debt-session-63` pushed to origin. No further commits needed unless a browser-verification flow fails.

### 4c. Remaining audit work (out of scope for this session, ordered by ROI)

#### Immediate next sprint (pick 1–2 per day)

1. **NPM `--force` fix** — schedule 2–4h. Run `npm audit fix --force`, then full PWA + iOS + web regression. Vite 8 will likely need `vite.config.js` tweaks; `@capacitor/cli` 8 may need iOS rebuild. The 8 remaining vulnerabilities are all supply-chain.
2. **Decompose top 3 `ActionDefinitionService` classes** — highest single-file readability win.
   - `app/Services/Savings/SavingsActionDefinitionService.php` (3,686 lines) — target: split by recommendation type (ISA / PSA / FSCS / etc.)
   - `app/Services/Retirement/RetirementActionDefinitionService.php` (2,701 lines) — split by pension action type
   - `app/Services/Protection/ProtectionActionDefinitionService.php` (2,349 lines) — split by protection type
   - Pattern: create `{Type}RecommendationEvaluator` classes, thin main service to orchestrator.
3. **Unit tests for highest-risk untested services** (top 10, ordered by risk):
   1. `IHTCalculationService` (1,641 lines, no test)
   2. `MarkowitzOptimizer`
   3. `RequiredCapitalCalculator`
   4. `SalarySacrificeAnalyzer`
   5. `TrustValuationService`
   6. `RebalancingCalculator`
   7. `TaxAwareRebalancer`
   8. `AssetLocationOptimizer`
   9. `PensionContributionOptimizer`
   10. `WillAnalysisService`
   - 134 services total have zero unit tests. Only top 10 are in immediate scope.
4. **Agent tests for 5 missing agents** (3 days total):
   - `CoordinatingAgent`, `EstateAgent`, `InvestmentAgent`, `RetirementAgent`, `TaxOptimisationAgent`
   - Pattern: follow `tests/Unit/Agents/ProtectionAgentTest.php`.

#### Medium-term (month-long backlog)

5. **Fat controller refactor** — extract service layer out of the 6 worst:
   - `InvestmentController` (1,070 lines)
   - `PaymentController` (956)
   - `AdminController` (873, plus 8 inline validates)
   - `GoalsController` (792)
   - `RetirementController` (789, plus 2 `DB::` facade calls)
   - `AuthController` (777)
6. **Form Request conversion** — 54 controllers use inline `$request->validate()`. Convert the top 10 controllers with highest call volume first (Admin/Payment/Retirement/Auth/UserProfile).
7. **DB facade removal from 8 controllers** — move to service layer:
   - `TaxSettingsController.php` (4x)
   - `Retirement/DCPensionHoldingsController.php` (3x)
   - `RetirementController.php` (2x), `PaymentController.php` (2x), `FamilyMembersController.php` (2x)
   - `WebhookController`, `PreviewController`, `InvestmentController` (1x each)

#### Backlog (defer unless triggered by adjacent work)

8. **Duplicate code consolidation:**
   - Income tax band allocation (3 services) — build `TaxBandAllocator`
   - CGT liability calc (3 services) — build `CGTCalculator`
   - Income resolution (3 services already have the trait, just not used consistently)
   - AI tool definitions (`AiToolDefinitions` + `XaiToolDefinitions` 974+888 lines each) — build `ToolRegistry` interface
9. **Dead local model scopes** — 56 defined, spot check found dead ones (`AdvisorClient::scopeActive()`, `AiConversation::scopeActive()`, `Goal::scopeActive()`). Full sweep needed.
10. **Vue god components** — 28 components over 800 lines. Prioritise `TaxSettings.vue` (3,068) and `ExpenditureForm.vue` (2,574).
11. **Mixed state naming in 12 Vuex modules** — decide convention project-wide.
12. **Architecture test coverage gaps** (extend `tests/Architecture/`):
    - Vue component naming (multi-word PascalCase)
    - Tax hardcoding in helpers / middleware / jobs
    - Vuex state naming convention

#### Known pre-existing issues (not created by this audit)

- **`AutoRiskCalculatorTest` enum truncation** — `risk_profiles.risk_level` enum doesn't include `medium_low`. Pre-existing since at least April 16. Test failure unrelated to tech debt fix.
- **`onboardingFyn` branch state** — per session-62 notes, dev server may be running a different branch. Ask user before deploying to dev.

### 4d. What NOT to do

- **Don't run `npm audit fix --force` without user confirmation** — breaks vite config and iOS build.
- **Don't commit without browser testing** — per `critical_browser_testing_law.md`, this is non-negotiable.
- **Don't touch the CSJTODO.md or vault** until commits land. Update them together.
- **Don't run `migrate:fresh` or `migrate:refresh`** — destroys data.
- **Don't skip the architecture test** — `MonetaryCastsArchitectureTest.php` prevents future regressions; if you find yourself wanting to remove it, something is wrong.

---

## 5. Pointers for future audits

### The audit pipeline that worked

1. Dispatch 6 parallel `Explore` subagents, one per area (backend services, controllers, models/DB, Vue components, stores/services, tests).
2. Each subagent returns structured JSON findings (severity/category/file/lines/suggestion/effort).
3. Main agent runs cross-cutting checks (npm audit, composer audit, banned color scan, god-file scan).
4. Aggregate into `docs/tech-debt-report-full.md` using the exact schema in the `tech-debt-full` skill.
5. Compare against the previous report's date — note resolved / regressed items explicitly.

### Files worth re-reading before the next audit

- `docs/tech-debt-report-full.md` — this IS the baseline now.
- `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/MEMORY.md` — still current.
- `tests/Architecture/HardcodedValuesArchitectureTest.php` — enforces no hardcoded tax values in services.
- `tests/Architecture/MonetaryCastsArchitectureTest.php` (new) — enforces no float casts on currency columns.

### Key deltas from April 9 → April 18

Resolved:
- `$toast` registration (app.js:63-74)
- `PSACalculator::determineTaxBand` returns `'non_taxpayer'` correctly
- All 12+ hardcoded tax values in backend services
- Banned color tokens (0 uses)
- Dead `PythonAgentBridge.php` + `taxOptimisationService.js`

Regressed:
- Float casts: 65 → **70** (now fixed to 0 via this session)
- NPM vulnerabilities: 14 → 16 → now 8 after safe fix (6 high remain)

New baseline after this session:
- 0 float casts on monetary columns (enforced by architecture test)
- 54 orphaned Vuex actions removed (was 60+)
- 17 dead API service methods removed
- 7 single-word Vue components handled (5 renamed, 2 deleted)
- 1,483 unit tests passing (was 1,483 before — no test regressions)

---

## 6. Quick reference: files to review

If the next agent wants to understand the scope quickly, read these files in this order:

1. `docs/tech-debt-report-full.md` — the report
2. `tests/Architecture/MonetaryCastsArchitectureTest.php` — the regression prevention
3. `app/Services/Estate/NetWorthAnalyzer.php` lines 170-195 — example of downstream `(float)` cast pattern
4. `resources/js/store/modules/estate.js` — clean state (876 → 522 lines)
5. `resources/js/components/AppNavbar.vue` + `resources/js/layouts/AppLayout.vue` — rename pattern

---

*Generated by tech-debt-full remediation session, 18 April 2026.*
