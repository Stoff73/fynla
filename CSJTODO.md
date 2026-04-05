# CSJTODO — Fynla

*Last updated: 5 April 2026 — session 36*
*Previous session: 4 April 2026 session 35*

---

## Session 36 (5 April) — Full Code Review + 38 Tech Debt Quick Wins

### Completed This Session
- [x] **Full codebase tech debt review** — 6 parallel audit agents, 90 findings (15 critical, 45 warning, 30 suggestion). Report at `April/April5Updates/codeReview.md`
- [x] **38 quick-win fixes applied** — PR #190 merged to main as commit `9b02fd0` (51 files, +154/-319)
- [x] **Removed hardcoded tax values** from 6 services (IHT rate, Annual Allowance, RNRB) — all now read from TaxConfigService
- [x] **Added Auditable trait to 17 models** — 12 Estate models + Document, CashAccount, PersonalAccount, Subscription, UserConsent
- [x] **Added HasJointOwnership to JointAccountLog** — convention compliance
- [x] **Expanded IHT acronym to Inheritance Tax** in 3 public pages (Rule 10)
- [x] **Replaced numeric profile score with qualitative label** in ProfileCompletenessAlert (Rule 13)
- [x] **Replaced banned amber-* tokens** with violet-* in PensionIhtChanges2027Page (Rule 9)
- [x] **Deleted duplicate analyzeAssetLocation** in investmentService.js (latent defect)
- [x] **Removed dead store code** — fetchRecommendations in 3 modules, selectedMortgage, riskLevels, hasEdits/editCount
- [x] **CSS hex→@apply sweep** in GuideNav, LearnHubPage, PersonaSelectionModal, NetWorthOverviewCard skeleton
- [x] **Generated deploy guide** at `April/April5Updates/deployQuickwins.md`

### NOT Done — Outstanding
- [ ] **Deploy PR #190 to production** — 25 PHP files + `public/build/` (deploy guide written, not yet run)
- [ ] **Browser-test affected components** after deploy — Estate IHT calcs, PersonaSelectionModal, ProfileCompletenessAlert, bug-report submission in preview mode, audit log entries on Estate edits
- [ ] **Address medium-priority PR #189 issues** (score 75, filtered from review but confirmed real):
  - `lg:mr-10` collapsed-chat margin regression in AppLayout.vue (reverts commit 4df53fc)
  - `fyn-chat-interaction` event fires on chat collapse → clears journey blur prematurely
  - Hardcoded tax thresholds in new insight articles — should use taxConfig.js pattern
- [ ] **Add `.claude/settings.json` to .gitignore** — tax-hook path keeps reverting between Mac/Windows dev machines

### Context for Next Session
PR #190 (38 tech debt quick wins) is merged on main but NOT deployed. Deploy guide ready at `April/April5Updates/deployQuickwins.md` — rebuild frontend + upload 25 PHP files + clear caches. Post-deploy verification checklist included.

Full code review report (`April/April5Updates/codeReview.md`) catalogues 52 deferred findings including god class refactors (RetirementIncomeService 2292L, IHTCalculationService 1641L), god component splits (TaxSettings 2972L, ExpenditureForm 2574L), float-to-decimal cast sweep across 12 models, FormRequest migration across 53 controllers, and 5 service test coverage gaps. Each is a dedicated session.

**Tax year deadline: April 6 (tomorrow).** 2025/26 is the active tax year in the database.

---

## Outstanding — Tech Debt (Deferred from Code Review)

### God Class Refactors (multi-session each)
- [ ] RetirementIncomeService.php (2,292L) → extract ProjectionEngine, PCLSCalculatorService, AllocationStrategyService
- [ ] IHTCalculationService.php (1,641L) → extract CharitableRateCalculator, RNRBCalculator, ProjectedEstateService
- [ ] UserProfileService::getFinancialCommitments (421-line method)
- [ ] User.php (716L, 59 methods) → extract HasSubscription trait, DomicileService
- [ ] InvestmentAccount.php (492L, ~160 fillable) → polymorphic sub-type tables

### God Component Splits
- [ ] TaxSettings.vue (2,972L)
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

### Dead Code Cleanup
- [ ] Delete 51 dead methods in investmentService.js (1,146→~350 lines)
- [ ] Delete 7 orphaned Investment/PlanSections components (3 contain Rule 13 score violations that would ship when wired)
- [ ] Verify and delete DividendTaxCalculator + TaxEfficiencyCalculator
- [ ] LifeEventAllocationController — wire routes or delete

### Test Coverage Gaps
- [ ] ChattelCGTService (223L, zero tests)
- [ ] WhatIfScenarioService (274L, zero tests)
- [ ] InvestmentAgent (no agent-level test)
- [ ] TaxOptimisationAgent (no agent-level test)
- [ ] RevolutService (payment-critical, feature test only)

## Outstanding from Previous Sessions
- [ ] Delete mockup HTML files from public/
- [ ] Submit updated sitemap.xml to Google Search Console
- [ ] Test contact form email delivery on production
- [ ] Recurring billing / auto-renewal (Revolut)
- [ ] Test Excel import with real platform exports
- [ ] Test Fyn timeout fix on production (10+ message conversation)
- [ ] Deploy Excel holdings import to production

## Known Issues
- [ ] Retirement "Other Assets" cards overflow at 1118px
- [ ] DB pension field mapping mismatch
- [ ] Expenditure form fill doesn't animate
- [ ] property_sale life event creates property record (double navigation)

## Deploy Status
- **PR #189:** Deployed to fynla.org (session 35 Mac)
- **PR #190:** Merged to main, NOT deployed — deploy guide at `April/April5Updates/deployQuickwins.md`
- **fynNew branch:** 25 Fyn Response Architecture commits still unmerged (parallel track)
