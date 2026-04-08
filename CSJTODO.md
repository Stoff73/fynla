# CSJTODO — Fynla

*Last updated: 8 April 2026 — session 47*
*Previous session: 8 April 2026 session 46*

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

### Session 38 (7 April) — Code Review Fixes + Deploy

- [x] **Full codebase review** — 68 issues found, 48 fixed in PR #193
- [x] **Hardcoded tax values** — 14 of 24 locations fixed (TaxDefaults constants backend, taxConfig.js frontend)
- [x] **Security fixes** — SQL injection (2 artisan commands), XSS (PublicLayout), MIME validation (LpaDocumentService), plaintext password (ChrisUserSeeder)
- [x] **Dead code removed** — GiftingTimelineService, taxOptimisation Vuex module, 8 investmentService stubs, updateGoalProgress
- [x] **Design system fixes** — banned color tokens in 6 components, duplicate .btn-secondary, hardcoded hex
- [x] **Convention fixes** — readonly on 13 controllers, TrustResource/MortgageResource, Auditable on 4 models, 14 factories created
- [x] **PR #193 merged + deployed to production** — 221 files, includes estateDash + code review fixes
- [x] **PR #190 deployed** — was included in estateDash branch

### Session 47 (8 April evening) — PRs, Revolut Live, Bug Fixes, Token Upgrade

- [x] **PRs reviewed + merged** — #197 (ISA guide + retirement articles from Brett), #198 (Fyn icon Vite bundling from Phailanx)
- [x] **Settings.json fix** — reverted Windows stop hook path to Mac path (PR #198 broke it)
- [x] **Revolut sandbox → live** — live API keys, webhook created via API, card payments enabled, .env updated
- [x] **aiChat session leakage fix** — Fyn store never reset on login/logout/register (PR #200)
- [x] **Invoice webhook race condition fix** — webhook beats confirmPayment, invoice skipped; now checks invoice_id===null (PR #200, Pest tested)
- [x] **Dashboard UI polish** — progress bar labels moved above bars, journey complete state hides step count, "Start a new journey" link, portfolio projection double border removed (PR #201)
- [x] **Token limits doubled** — student 300k, standard 1M, family 1.5M, pro 2M (PR #202)
- [x] **Token limit UI** — violet info box with live countdown to midnight reset, input disabled (PR #202)
- [x] **All deployed to fynla.org** — PRs #200, #201, #202 + invoice PDF template + Revolut live config

### NOT Done — Outstanding
- [ ] **Clean up `.claude/worktrees/tax` directory** — no longer needed, branch merged
- [ ] **Delete `tax`, `estateDash`, `revolutLive`, `uiFixes`, `fynUpgrade` branches** locally and on origin (all merged)
- [ ] **Invoice PDF template was stale on production** — "UK Financial Planning" instead of "Your financial companion for life". Uploaded correct version this session but verify it's rendering correctly on next payment.
- [ ] **Generate missing invoice for payment #17** (user 542, chris@fynla.org) — payment completed but no invoice generated due to the race condition. Need to manually generate and email.
- [ ] **PR #197 cleanup** — 9 markdown files dumped in repo root (faq.md, how-it-works.md, etc.) should be moved to Articles/ or removed
- [ ] **Consider adding HistoricalTaxYearSeeder overrides for 2021/22-2023/24** — BPA values for these years may now show 2025/26's 3130 instead of their true historical values (scope creep deferred)

### Context for Next Session
**Revolut is LIVE on production.** Real card payments processing. Invoice webhook race condition fixed. Token limits doubled with countdown UI. All PRs merged and deployed. Production `dc_pensions` table missing `current_value` column (repeated LifeStageService errors in logs). The `fynNew` branch (25 Fyn Response Architecture commits) is still unmerged — parallel track.

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
- [x] ~~AnnualAllowanceTracker.vue lines 266-267: hardcoded carry-forward years~~ — fixed, now dynamic from getCurrentTaxYear()
- [ ] Add `/api/tax-year/current` response to frontend tests (not currently covered)

### Dead Code Cleanup
- [x] ~~Delete dead methods in investmentService.js~~ — 8 stubs removed (PR #193). ~8 remaining Phase 2 stubs still present.
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
- **PR #190:** Deployed to fynla.org (included in PR #193 estateDash branch)
- **Tax branch (f646f88..19f7d23):** Deployed to fynla.org (session 37). 2026/27 active.
- **PR #193 (estateDash):** Deployed to fynla.org 7 April 2026. Estate redesign + code review fixes + cookie consent + spouse lifecycle. Deploy guide at `April/April7Updates/estateBranchDeploy.md`.
- **fynNew branch:** 25 Fyn Response Architecture commits still unmerged (parallel track)
- **PR #197 (brett-v1):** Merged 8 April 2026. ISA guide + retirement planning articles.
- **PR #198 (adhoc-changes-3):** Merged 8 April 2026. Fyn icon bundled via Vite + mega menu fix. Settings.json path fixed post-merge.
- **PR #200 (revolutLive):** Deployed to fynla.org 8 April 2026. Invoice webhook race condition fix + aiChat session leakage fix.
- **PR #201 (uiFixes):** Deployed to fynla.org 8 April 2026. Dashboard progress bar labels, journey complete state, portfolio projection double border.
- **Revolut Live:** Switched from sandbox to production 8 April 2026. Live webhook created, card payments enabled.
- **PR #202 (fynUpgrade):** Deployed to fynla.org 8 April 2026. Token limits doubled, token limit UI with countdown, new token-usage endpoint.
