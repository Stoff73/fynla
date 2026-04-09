# CSJTODO — Fynla

*Last updated: 9 April 2026 — session 50*
*Previous session: 9 April 2026 session 49*

---

## Session 49-50 (9 April) — T&C Pricing, Persona Fixes, QA Review Fixes

### Completed This Session
- [x] **T&C pricing updated** — added Family plan, expanded table to show launch + regular prices, added launch special explanation, added Student/Family upload limits
- [x] **About page** — hidden bottom "About Fynla" section
- [x] **Alex Chen dividend income** — diagnosed outdated persona JSONs on production, uploaded all 6, reseeded — dividend now showing
- [x] **Preview spouse toggle fixed** — `switchingSpouse` flag never reset on success, moved to `finally` block
- [x] **Dashboard data refresh** — `loadAllData()` now detects `user.id` change on persona switch and refetches
- [x] **Fyn AI markdown headings** — `##` and `###` now rendered as styled headings in chat
- [x] **Employment status enum** — added `full_time` to DB enum (was causing silent data truncation on production)
- [x] **AI tool definitions** — both `AiToolDefinitions.php` and `XaiToolDefinitions.php` now list all valid `employment_status` values
- [x] **Family tab gating** — User Profile Family tab now requires Family plan or higher
- [x] **Liabilities route gating** — extracted from Pro estate group to Standard; estate read-only endpoints ungated for dashboard
- [x] **Brett's QA report reviewed** — 14 bugs assessed against dev codebase, 0 critical bugs confirmed in code
- [x] **PR #208 (claudeReview)** — merged and deployed
- [x] **Vault sync complete** — April Index, Git History updated

### NOT Done — Outstanding
- [ ] **Fyn Quick Start flow** — hidden CTA, root cause unfixed (see `fynQuickStartBugs.md`): production missing `dc_pensions.current_value` column, AI analyses empty data for new users
- [ ] **Run pending migration on production** — `dc_pensions.current_value`
- [ ] **Generate missing invoice for payment #17** (user 542, chris@fynla.org) — from session 47
- [ ] **PR #197 cleanup** — 9 markdown files in repo root should be moved to Articles/
- [ ] **Meta Pixel CSP** — `connect.facebook.net` and `www.facebook.com` not in SecurityHeaders.php CSP whitelist
- [ ] **`fynNew` branch** (25 Fyn Response Architecture commits) still unmerged
- [ ] **Add `.claude/settings.json` to .gitignore** — tax-hook path keeps reverting

### Context for Next Session
**v1.0 deployed + QA review fixes deployed.** Brett's QA report (claudeReview.md) reviewed — most "bugs" were either already fixed, non-existent routes, or correct behaviour (dividend rate 35.75% is correct for 2026/27). Real fixes: spouse toggle, dashboard data refresh, markdown headings, employment_status enum, tier gating. The `employment_status` enum now includes `full_time` on production. Family plan users now see the Family tab in profile, and Liabilities page works for Standard+ (was incorrectly Pro-gated). The `fynNew` branch with the Fyn Response Architecture (25 commits) is still a separate parallel track.

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
- **PR #204 (fynStart):** DEPLOYED 9 April 2026
- **PR #205 (invoice):** DEPLOYED 9 April 2026
- **PR #206 (fynBugs):** DEPLOYED 9 April 2026
- **PR #207 (security):** DEPLOYED 9 April 2026
- **Terms/Privacy + v1.0:** DEPLOYED 9 April 2026
- **T&C pricing + About page:** DEPLOYED 9 April 2026
- **Persona JSONs (all 6):** DEPLOYED 9 April 2026
- **PR #208 (claudeReview):** DEPLOYED 9 April 2026 — spouse toggle, dashboard refresh, markdown, enum, tier gating
