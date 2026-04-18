# CSJTODO — Fynla

*Last updated: 18 April 2026 — session 63*
*Previous session: 18 April 2026 — session 62*

---

## Session 63 (18 April) — Full codebase tech debt audit + remediation

### Completed This Session

- [x] **Full codebase tech debt audit** — 6 parallel `Explore` subagents scanned 489 Vue components + 138 views + 239 services + 98 controllers + 97 models + 35 Vuex stores + 45 frontend services + 211 tests + 173 migrations + 64 factories. 101 findings logged at `docs/tech-debt-report-full.md` (7 critical, 58 warning, 36 suggestion). Critical count down 12 → 7 vs the 9 April audit.
- [x] **70 float → decimal:2 casts** across 12 models — `ExpenditureProfile` 8, `ProtectionProfile` 7, `Estate/IHTCalculation` 23, `Estate/{Asset,Gift,IHTProfile,Liability}` 9, `Investment/{Holding,RebalancingAction,InvestmentGoal,RiskProfile}` 24, `RecommendationTracking` 2. DB columns were already `DECIMAL(15,2)`.
- [x] **New `tests/Architecture/MonetaryCastsArchitectureTest.php`** — scans model `$casts` arrays and fails on future `'float'` declarations against columns whose names imply currency/percentage values. Regression-proof.
- [x] **Downstream `(float)` fixes** at 3 production call sites that silently relied on float returns: `NetWorthAnalyzer.php:177,187`, `FeeAnalyzer.php:196-198`, `TaxEfficiencyCalculator.php:30-41`. Plus `InlineHoldingsTest.php` updated to cast before identity comparison.
- [x] **17 dead API service methods removed** — verified zero callers per-service: estateService (8), goalsService (6), investmentService (2), protectionService (1).
- [x] **54 orphaned Vuex actions removed** via acorn AST-based removal: estate (20), investment (10), retirement (12), goals (12). Store total reduced 3,210 → 2,206 lines (−31%).
- [x] **2 dead Vue components deleted** — `UserProfile/Settings.vue`, `Investment/Goals.vue` (zero callers).
- [x] **5 single-word Vue components renamed** + all imports updated across `AppLayout`, `SavingsDashboard`, `HoldingsDetail`, `InvestmentList`: Navbar→AppNavbar, Footer→AppFooter, Savings/Recommendations→SavingsRecommendations, Investment/Holdings→InvestmentHoldings, Investment/Performance→InvestmentPerformance.
- [x] **`strict_types=1` added to 38 files** — 19 migrations + 19 factories (Python-scripted, all verified).
- [x] **6 generic `\Exception` throws → `FinancialCalculationException` factories** — IntestacyCalculator, ScenarioService, ComprehensiveProtectionPlanService (2), OnboardingService (3).
- [x] **Mockery::close + Carbon freeze** — `afterEach(Mockery::close())` added to BaseAgentTest + ProfileCompletenessTest; `Carbon::setTestNow(2025-06-15)` added to TrustsTest + EstateApiTest.
- [x] **npm audit fix (non-breaking)** — vulnerabilities 16 → 8. Remaining 8 need `--force` (vite 8 + @capacitor/cli 8 major versions) — deferred pending iOS regression window.
- [x] **Verification** — `./deploy/fynla-org/build.sh` PASS (7.7 MB assets), 1,483 unit tests passing (1 pre-existing failure: `AutoRiskCalculatorTest` enum truncation, unrelated), 122 architecture tests including the new one.
- [x] **Session 63 handover written** — `April/April18Updates/handover-tech-debt.md` with 3-commit split plan and 8-flow browser-testing checklist.
- [x] **3 commits made on feature branch** — `feature/csj/tech-debt-session-63` pushed to origin, 84 files changed, +729/−2,160 net. Main is untouched pending PR review.
- [x] **Vault sync** — Apr18.md (14 → 16 commits), Apr2026 Commits.md (402 → 404), Home.md (2,603 → 2,605 total), April Index session 63 entry added. Tech debt report mirrored to `fynlaBrain/Reports/tech-debt-18April2026.md` + linked from Reports Index.

### NOT Done — Outstanding

- [ ] **Browser-test the tech debt branch before merging** — 8 critical flows listed in `handover-tech-debt.md` §4a. Must be completed before opening PR to dev or main. The decimal:2 cast change affects Estate/IHT, Investment holdings, Protection profile, Expenditure form, Net worth dashboard, Savings dashboard (renamed component), Investment detail page (renamed components).
- [ ] **PR `feature/csj/tech-debt-session-63` → `dev`** — follow the feature → dev → main workflow per CLAUDE.md §Deployment. Do NOT PR straight to main. CODEOWNERS will require @Stoff73 review.
- [ ] **After dev green, PR `dev` → `main` + deploy** — standard two-environment flow. Monitor storage/logs/laravel.log for 10-15 min post-deploy.
- [ ] **NPM `--force` fix** — schedule a 2-4h window for vite 8 + @capacitor/cli 8 major upgrades with full PWA + iOS + web regression. 6 high-severity vulnerabilities remain until this is done.
- [ ] **Test Fyn chat fixes on dev (csjones.co/fynla)** — deployed in session 58 but not browser-tested. Carried from session 58.
- [ ] **Re-enable branch protection on `dev`** — carried from session 57.
- [ ] **Add `Current State/Insights.md` to the vault** — carried from session 62.
- [ ] **`AutoRiskCalculatorTest` pre-existing failure** — `risk_level` enum truncation. Pre-existing since 16 April. Not caused by this session's work but surfaces in every full-suite run.

### Context for Next Session

On `main` branch, clean working tree. All session-63 work is on `feature/csj/tech-debt-session-63` (pushed to origin, ready for PR to dev). The feature branch has 3 commits that together close all 7 critical items from the 18 April tech debt audit:

1. `374daa8` — 70 float→decimal:2 casts + architecture test + downstream (float) patches
2. `9d3ebb8` — 17 dead API methods + 54 orphaned Vuex actions + 2 dead components + 5 renames
3. `90e2c7b` — strict_types + exception factories + test hygiene + npm audit + CLAUDE.md count refresh

**Next session start point: browser-test the feature branch end-to-end before opening PR to dev.** Per `handover-tech-debt.md` §4a, 8 flows must be verified: Estate/IHT dashboard, Investment dashboard (holdings/fees/tax/rebalance), Protection dashboard, Expenditure form (penny-level totals), Estate CRUD (asset/liability/gift/LPA/trust — Vuex actions removed; components call service directly), Net worth dashboard (NetWorthAnalyzer was patched), Savings dashboard (renamed component), Investment detail (renamed components).

Login flow for local testing: `john@example.com` / `password`, fetch verification code from DB per CLAUDE.md auth guide. Use preview personas `young_family` (active Estate/Investment/Protection) + `peak_earners` (heavy Investment + Estate).

If any test fails: fix on the feature branch, commit, re-test from step 1 (never skip to the fix point — feedback rule).

---

## Outstanding — Tech Debt Deferred

- [ ] **28 Vue god components** (>800 lines) — prioritise `Admin/TaxSettings.vue` (3,068 lines) and `UserProfile/ExpenditureForm.vue` (2,574 lines). Multi-week.
- [ ] **13 backend god files** — decompose `SavingsActionDefinitionService.php` (3,686 lines), `RetirementActionDefinitionService.php` (2,701), `ProtectionActionDefinitionService.php` (2,349), `RetirementIncomeService.php` (2,292), `IHTCalculationService.php` (1,641).
- [ ] **54 controllers using inline `$request->validate()`** — convert to Form Request classes (~60-80h total). Top 10 first: Admin, Payment, Retirement, Auth, UserProfile, Investment, Property, TaxSettings, Onboarding, Recommendations.
- [ ] **8 controllers using `DB::` facade** — move to service layer.
- [ ] **134 services without unit tests** — 44% coverage. Prioritise top 10 per handover §4c.
- [ ] **5 agents without test files** — CoordinatingAgent, EstateAgent, InvestmentAgent, RetirementAgent, TaxOptimisationAgent. Pattern: follow `ProtectionAgentTest`.
- [ ] **47 controllers without feature tests** — see handover §4c.
- [ ] **56 local model scopes** — spot check showed dead ones (`AdvisorClient::scopeActive()` etc.) — full sweep needed.
- [ ] **Duplicate code consolidation** — income tax band allocation (3 services), CGT liability calc (3 services), AI tool definitions (`AiToolDefinitions` + `XaiToolDefinitions` → `ToolRegistry`).
- [ ] **Mixed state naming in 12 Vuex modules** — decide convention project-wide.
- [ ] **Architecture test coverage gaps** — Vue component naming, helper hardcoding, middleware/job hardcoding, Vuex state naming.
- [ ] `AutoRiskCalculatorTest` enum truncation (pre-existing, not insights/tech-debt related).

## Known Issues

- **Tech debt branch not browser-tested** — 83 files changed including financial calculation casts; must not deploy until §4a checklist is complete.
- **`AutoRiskCalculatorTest`** still failing due to `risk_profiles.risk_level` enum not including `medium_low`. Documented since 16 April, not addressed.

## Deploy Status

**Production (fynla.org):** Running commit `72e5dba` (session 62 end). No production deploy pending for session 63 — feature branch must go through dev verification and PR review first.

**Dev (csjones.co/fynla):** Unchanged from session 58 (`onboardingFyn` branch deploy). When deploying session 63 work to dev, ASK WHICH BRANCH THE DEV SERVER IS RUNNING before building (per `feedback_dev_server_is_separate`).

**Pending deploy:** `feature/csj/tech-debt-session-63` → needs browser verification → PR to `dev` → smoke test → PR to `main`.
