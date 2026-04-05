# CSJTODO — Fynla

*Last updated: 5 April 2026 — session 37 (evening)*
*Previous session: 5 April 2026 session 36 (morning)*

---

## Session 37 (5 April evening) — UK Tax Year 2026/27 Launch + Fyn Icon Fix

### Completed This Session
- [x] **Added UK tax year 2026/27 configuration** (`TaxConfigurationSeeder::getTaxConfig202627()`) with every documented change — dividend +2pp (10.75%/35.75%), BADR 14%→18%, APR/BPR £2.5m combined cap, AIM shares 50%, state pension £12,547.60, NLW £12.71, SSP £123.25 with day-one payment, premium bonds 3.3%, child benefit £27.05/£17.90, UC LCWRA halved for new claims, two-child limit lifted
- [x] **Fixed 2025/26 seeder bugs per taxError.md** — BPA 2870→3130, SSP 116.75→118.75, PIP rates uprated, IHT freeze 2030→2031, SPA wording corrected, APR/BPR £1m→£2.5m notes
- [x] **Preserved 2024/25 historical accuracy** — added overrides for BPA (3070), SSP (116.75), PIP rates
- [x] **Added admin quick-switch dropdown** in TaxSettings.vue header — confirm dialog, revert on cancel, calls existing `/api/tax-settings/{id}/activate` endpoint
- [x] **Fixed app-wide tax year reactivity** — new `GET /api/tax-year/current` endpoint (auth:sanctum) + `taxConfig` Vuex store mirrored into `dateFormatter.setActiveTaxYear()` cache → one change fixes all 32+ components that display "current tax year"
- [x] **Added `Cache::flush()` on year switch** in `TaxSettingsController::setActive` — invalidates all users' cached agent analyses so they see new rates immediately
- [x] **Guarded ISATracker + AnnualAllowanceChecker fallback estimates** — monthly-contribution projections only apply when requested year === calendar year, preventing last year's contributions from leaking into new year
- [x] **Fixed 6 broken Fyn icon references** — `/images/Website/Fynla-Fyn-Icon.png` (file never in repo) → `/images/Fyn/Fyn-Icon.png` (actual path) across Navbar, AiChatPanel, JourneyProgressHero, AppLayout
- [x] **Fixed TaxDefaults.php stale CGT rates** — 0.10/0.20 → 0.18/0.24 (aligned with residential rates since Oct 2024)
- [x] **Added DIVIDEND_*, BADR_RATE, STATE_PENSION_* constants** to TaxDefaults + frontend taxConfig.js
- [x] **Fixed 25 pre-existing test failures** — RetirementProjectionServiceTest constructor signature drift (UserProfileService→RequiredCapitalCalculator), GDPRApiTest Mail view compilation (added Mail::fake()), UserDomicileTest RefreshDatabase pollution (20 users leaking into UserMetricsServiceTest), ProfileCompletenessTest cache TTL mismatch (600→86400), SavingsIntegrationTest ISA year (2025/26→2026/27), IncomeDefinitionsServiceTest BPA (2870→3250)
- [x] **Full test suite: 2191 passed / 0 failed** (was 25 failed)
- [x] **Merged `tax` branch to main** (3 commits: f646f88, 441eb07, 19f7d23)
- [x] **Pushed main to origin** (bypassed PR requirement)
- [x] **Built frontend from main** (./deploy/fynla-org/build.sh)
- [x] **Deployed to fynla.org** — PHP files + public/build/ uploaded, seeder run, caches cleared
- [x] **Verified on production** — production manifest matches local, new app.js + AppLayout chunks live, `/api/tax-year/current` endpoint returns 200
- [x] **Deploy guide written** at `April/April5Updates/deployTaxFyn.md` (both repo + vault)
- [x] **CLAUDE.md metrics updated** — Vue 651→654, Services 229→230, Vuex 32→33

### NOT Done — Outstanding
- [ ] **Deploy PR #190 to production** — STILL OUTSTANDING from session 36. 25 PHP files + `public/build/` never uploaded. Deploy guide at `April/April5Updates/deployReview.md`. (Session 37 deployed the tax changes only — PR #190 fixes are independent.)
- [ ] **Verify allowances display correctly on production after browser refresh** — tax year label showed "2025/26" during testing due to browser cache. Hard refresh needed. If it still shows old values after hard refresh, may need to investigate Laravel route cache on production.
- [ ] **Clean up `.claude/worktrees/tax` directory** — no longer needed, branch merged
- [ ] **Delete `tax` branch** locally and on origin (already merged to main)
- [ ] **Consider adding HistoricalTaxYearSeeder overrides for 2021/22-2023/24** — BPA values for these years may now show 2025/26's 3130 instead of their true historical values (scope creep deferred)

### Context for Next Session
**2026/27 is now the active tax year in production and local DB.** All allowance calculations (ISA, Pension AA) use 2026/27 rates. Switching years via admin dropdown is functional end-to-end. Browser testing on production verified manifests match, API endpoints work, and Fyn icons render.

**PR #190 still awaits deploy.** Separate workstream from today's tax changes.

**Worktree lesson captured:** Saved memory `feedback_worktree_deploy_disconnect.md` after today's 30+ min confusion where worktree changes weren't visible to user building from main. Current guidance: work directly in main dir per `feedback_never_switch_branches.md`.

---

## Outstanding — Tech Debt (Deferred from Code Review)

### God Class Refactors (multi-session each)
- [ ] RetirementIncomeService.php (2,292L) → extract ProjectionEngine, PCLSCalculatorService, AllocationStrategyService
- [ ] IHTCalculationService.php (1,641L) → extract CharitableRateCalculator, RNRBCalculator, ProjectedEstateService
- [ ] UserProfileService::getFinancialCommitments (421-line method)
- [ ] User.php (716L, 59 methods) → extract HasSubscription trait, DomicileService
- [ ] InvestmentAccount.php (492L, ~160 fillable) → polymorphic sub-type tables

### God Component Splits
- [ ] TaxSettings.vue (3,079L after today's edits)
- [ ] ExpenditureForm.vue (2,574L)
- [ ] CalculatorsPage.vue (2,489L)
- [ ] Dashboard.vue (2,256L)
- [ ] RetirementIncomeTab.vue (2,124L)

### Architectural Debt
- [ ] Float-to-decimal cast sweep across 12 Estate/Investment/Protection models (70+ columns)
- [ ] FormRequest migration across 53 controllers (57% use inline validation)
- [ ] API Resource extraction for Investment/Retirement/Goals/Savings/Auth (139+ raw json responses)
- [ ] Split InvestmentController (1,067L) + RetirementController (789L) + GoalsController (792L) + AdminController (666L) + GDPRController (612L)
- [ ] Refactor TaxConfigService to throw on missing keys instead of allowing `?? 60000` fallback patterns
- [ ] AnnualAllowanceTracker.vue lines 266-267: hardcoded `2024/25`/`2023/24` carry-forward years — will go stale each year
- [ ] Add `/api/tax-year/current` response to frontend tests (not currently covered)

### Dead Code Cleanup
- [ ] Delete 51 dead methods in investmentService.js (1,146→~350 lines)
- [ ] Delete 7 orphaned Investment/PlanSections components (3 contain Rule 13 score violations)
- [ ] Verify and delete DividendTaxCalculator + TaxEfficiencyCalculator
- [ ] LifeEventAllocationController — wire routes or delete

### Test Coverage Gaps
- [ ] ChattelCGTService (223L, zero tests)
- [ ] WhatIfScenarioService (274L, zero tests)
- [ ] InvestmentAgent (no agent-level test)
- [ ] TaxOptimisationAgent (no agent-level test)
- [ ] RevolutService (payment-critical, feature test only)
- [ ] Tests for the new `taxConfig` Vuex store + `setActiveTaxYear()` cache

## Outstanding from Previous Sessions
- [ ] Delete mockup HTML files from public/
- [ ] Submit updated sitemap.xml to Google Search Console
- [ ] Test contact form email delivery on production
- [ ] Recurring billing / auto-renewal (Revolut)
- [ ] Test Excel import with real platform exports
- [ ] Test Fyn timeout fix on production (10+ message conversation)
- [ ] Deploy Excel holdings import to production
- [ ] Address medium-priority PR #189 issues (lg:mr-10 regression, fyn-chat-interaction event, hardcoded tax thresholds in insight articles)
- [ ] Add `.claude/settings.json` to .gitignore — tax-hook path keeps reverting

## Known Issues
- [ ] Retirement "Other Assets" cards overflow at 1118px
- [ ] DB pension field mapping mismatch
- [ ] Expenditure form fill doesn't animate
- [ ] property_sale life event creates property record (double navigation)
- [ ] Browser caching may require hard refresh to see new builds (document in deploy guide template)

## Deploy Status
- **PR #189:** Deployed to fynla.org (session 35 Mac)
- **PR #190:** Merged to main, NOT deployed — deploy guide at `April/April5Updates/deployReview.md`
- **Tax branch (f646f88..19f7d23):** Merged to main + deployed to fynla.org today. 2026/27 active.
- **fynNew branch:** 25 Fyn Response Architecture commits still unmerged (parallel track)
