# CSJTODO — Fynla

*Last updated: 9 April 2026 — session 48*
*Previous session: 8 April 2026 session 47*

---

## Session 48 (9 April) — Tech Debt Audit, Security Audit, Fyn Bugs, v1.0

### Completed This Session
- [x] **Code reviewed PR #203** (GA4 events, Meta Pixel, dashboard video) — flagged settings.json path + CSP issue
- [x] **Merged PR #203** + restored Mac path for tax hook
- [x] **Full tech debt audit** — 1,246 files scanned, 68 issues found, 45 fixed (PR #204 included some)
- [x] **$toast global registered** — 23 silent notification failures in Settings + MFA now working
- [x] **PSACalculator bug fixed** — wrong PSA tier for below-PA earners
- [x] **21 hardcoded tax values replaced** — 10 backend services + 10 Vue components + 1 store
- [x] **1,204 lines dead code removed** — guidance module, dead store actions/getters, unused scopes, PythonAgentBridge
- [x] **6 test files Mockery cleanup** + TaxYearController extracted + 2 factories created
- [x] **UI cleanup** — removed feedback nav, wishlist links, beta warning (PR #204)
- [x] **Hidden Fyn Quick Start CTA** — new user flow broken, documented for later fix
- [x] **Fixed aiChat state leak** — Login.vue + Register.vue bypassed auth store, aiChat/reset never fired
- [x] **Invoice PDF** — added company registration (Fynla Limited, 16903721, 124 City Road London) (PR #205)
- [x] **6 Fyn chat bugs fixed** — fill queue for multiple entries, scroll-to-top, expenditure routing, mortgage defaults (PR #206)
- [x] **Full security audit** — 0 critical, 0 high, 3 medium, 5 low — all 8 remediated (PR #207)
- [x] **Terms/Privacy pages** — updated address to London, privacy@ → support@fynla.org
- [x] **Version bumped to v1.0** in footer
- [x] **CLAUDE.md metrics updated** — Vue 660, Services 233, Controllers 94, Stores 32
- [x] **Vault sync complete** — April Index, Git History, Home.md all updated
- [x] **All 6 deploy guides written and marked deployed**

### NOT Done — Outstanding
- [ ] **Fyn Quick Start flow** — hidden CTA, but root cause unfixed (see `fynQuickStartBugs.md`): production missing `dc_pensions.current_value` column, `users.employment_status` enum missing `full_time`, AI analyses empty data for new users
- [ ] **Run pending migrations on production** — `dc_pensions.current_value`, `users.plan` enum with `family`
- [ ] **Generate missing invoice for payment #17** (user 542, chris@fynla.org) — from session 47
- [ ] **PR #197 cleanup** — 9 markdown files in repo root should be moved to Articles/
- [ ] **Meta Pixel CSP** — `connect.facebook.net` and `www.facebook.com` not in SecurityHeaders.php CSP whitelist
- [ ] **`fynNew` branch** (25 Fyn Response Architecture commits) still unmerged
- [ ] **Add `.claude/settings.json` to .gitignore** — tax-hook path keeps reverting

### Context for Next Session
**v1.0 deployed.** Major cleanup session — tech debt, security, Fyn bugs all addressed. The Fyn Quick Start CTA is hidden until the new-user flow is fixed (3 production DB issues + AI needs a "no data" prompt path). The `fynNew` branch with the Fyn Response Architecture (25 commits) is still a separate parallel track. Terms/Privacy pages now show London address. All PRs #203-#207 merged and deployed.

---

## Outstanding — Tech Debt (Deferred from Code Review)

### Duplicate Code Consolidation (from 9 April audit)
- [ ] DUP-01: Consolidate `determineTaxBand` — 7 implementations across services → UKTaxCalculator
- [ ] DUP-02: Consolidate DC pension annual contribution — 5 duplicates
- [ ] DUP-03: Consolidate pension tax relief — 3 duplicates
- [ ] DUP-04: Consolidate `calculateFutureValue` — 5 duplicates (shared service exists but not injected)
- [ ] CONV-03: Migrate 22 Vue components from direct currency import to currencyMixin

### God Class Refactors (multi-session each)
- [ ] RetirementIncomeService.php (2,292L) → extract ProjectionEngine, PCLSCalculatorService, AllocationStrategyService
- [ ] IHTCalculationService.php (1,641L) → extract CharitableRateCalculator, RNRBCalculator, ProjectedEstateService
- [ ] UserProfileService::getFinancialCommitments (421-line method)
- [ ] User.php (713L, 59 methods) → extract HasSubscription trait, DomicileService
- [ ] InvestmentAccount.php (492L, ~164 fillable) → polymorphic sub-type tables
- [ ] TaxSettings.vue (3,068L), ExpenditureForm.vue (2,574L), CalculatorsPage.vue (2,471L), Dashboard.vue (2,215L), RetirementIncomeTab.vue (2,107L)

### Architectural Debt
- [ ] Float-to-decimal cast sweep across 9 models (65 columns)
- [ ] FormRequest migration across 26 controllers (~78 new classes)
- [ ] API Resource extraction for 92 controllers returning raw JSON
- [ ] Split InvestmentController (1,070L) + AdminController (794L) + GoalsController (792L)
- [ ] Nonce-based CSP to replace `unsafe-inline` (MEDIUM-01 from security audit)
- [ ] npm audit fix (14 vulnerabilities, 11 high — breaking changes need testing)

### Test Coverage Gaps
- [ ] ~85 services with zero tests (IHTCalculationService most critical)
- [ ] Investment Analytics/Rebalancing/Performance/Tax subdirectories entirely untested (~35 services)
- [ ] AutoRiskCalculatorTest — `risk_level` column enum doesn't accept `medium_low` (2 pre-existing failures)

## Known Issues
- [ ] Retirement "Other Assets" cards overflow at 1118px
- [ ] DB pension field mapping mismatch
- [ ] Expenditure form fill doesn't animate
- [ ] property_sale life event creates property record (double navigation)

## Deploy Status
- **PR #203 (ga-updates):** DEPLOYED 9 April 2026
- **Tech debt audit (45 fixes):** DEPLOYED 9 April 2026
- **PR #204 (fynStart):** DEPLOYED 9 April 2026 — UI cleanup + aiChat state leak fix
- **PR #205 (invoice):** DEPLOYED 9 April 2026 — company registration in PDF
- **PR #206 (fynBugs):** DEPLOYED 9 April 2026 — fill queue, scroll, mortgage, expenditure
- **PR #207 (security):** DEPLOYED 9 April 2026 — NI $fillable, MFA log, safety comments
- **Terms/Privacy + v1.0:** DEPLOYED 9 April 2026 (committed directly to main)
