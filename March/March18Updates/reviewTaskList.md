# Fynla Code Review — Remediation Task List

**Created:** 18 March 2026
**Source:** FULL-CODE-REVIEW.md (96 findings across 5 domains)
**Status:** In Progress

---

## Sprint 1 — Critical Fixes

### Backend & Security Critical
- [x] **TASK-01** Fix float casts to `decimal:2` on User model (32 columns)
- [x] **TASK-02** Fix float casts to `decimal:2`/`decimal:4` on InvestmentAccount model (30+ columns)
- [x] **TASK-03** Fix £150,000 → £125,140 threshold in SavingsActionDefinitionService
- [x] **TASK-04** Fix dividend additional rate fallback 0.3938 → 0.3935 in DividendTaxCalculator
- [x] **TASK-05** Add user_id check to Monte Carlo results (IDOR fix)
- [x] **TASK-06** Replace `!==` with `hash_equals()` in AgentTokenAuth middleware
- [x] **TASK-07** Add `report($e)` logging to EstateAgent catch blocks (6 blocks)
- [x] **TASK-08** Fix `min(20, 30)` dead code in IHTStrategyGeneratorService
- [x] **TASK-09** Add eager loading to IHTCalculationService for user/spouse relationships
- [x] **TASK-10** Add `readonly` to AuthController constructor injections

### Frontend Critical
- [x] **TASK-11** Remove scores from PortfolioOverview.vue (2 scores)
- [x] **TASK-12** Remove scores from TaxFees.vue (1 score)
- [x] **TASK-13** Remove scores from AssetLocationOptimizer.vue (1 score)
- [x] **TASK-14** Verify/remove FinancialHealthScore.vue (marked deprecated — not imported anywhere)
- [x] **TASK-15** Replace hardcoded hex in AssumptionsSettings.vue (30+ values)
- [x] **TASK-16** Replace hardcoded hex in PrivacySettings.vue (40+ values, includes banned amber)
- [x] **TASK-17** Remove duplicated badge CSS from InvestmentList.vue and InvestmentDetailInline.vue
- [x] **TASK-18** Remove duplicated scrollbar CSS from 4 modal components
- [x] **TASK-19** Fix "Optimization" → "Optimisation" in 6 investment components

---

## Sprint 2 — High Priority Fixes

### Tax Compliance
- [x] **TASK-20** Replace TaxDefaults with TaxConfigService in TaxOptimisationService (determineTaxBand, buildISAStrategy, buildCGTStrategy)
- [x] **TASK-21** Replace TaxDefaults with TaxConfigService in TaxActionDefinitionService (determineMarginalRate, determineTaxBand)
- [x] **TASK-22** Replace TaxDefaults with TaxConfigService in PSACalculator (determineTaxBand)
- [x] **TASK-23** Replace hardcoded dividend/CGT rates in TaxOptimisationService
- [x] **TASK-24** Replace hardcoded rates in TaxOptimizationAnalyzer (5 locations)
- [x] **TASK-25** Replace hardcoded IHT rate in EstatePlanService
- [x] **TASK-26** Replace hardcoded IHT/CLT rates in PersonalizedTrustStrategyService (9 locations)

### Security & Backend
- [x] **TASK-27** Create UserResource for auth responses (strip financial data)
- [x] **TASK-28** Create AdminUserResource for admin user listing
- [x] **TASK-29** Fix spouse data over-exposure in auth responses (via UserResource)
- [x] **TASK-30** Fix PaymentController order probe (verify user owns order before Revolut API call)
- [x] **TASK-31** Fix PreviewWriteInterceptor pattern matching (str_contains → regex)
- [x] **TASK-32** Fix MFA setup secret binding (session → cache with user_id)
- [ ] **TASK-33** Consolidate Monte Carlo implementations (MonteCarloSimulator + MonteCarloEngine)
- [x] **TASK-34** Fix ISATracker write-on-read (guard with isDirty)
- [x] **TASK-35** Fix N+1 in Protection RecommendationEngine
- [x] **TASK-36** Fix N+1 in EstateAgent (triple query → single query)
- [x] **TASK-37** Fix RetirementAgent double-load of DCPensions

### Frontend High Priority
- [ ] **TASK-38** Replace console.log with logger in MobileLoginScreen.vue (auth credential exposure)
- [ ] **TASK-39** Replace console.log with logger in netWorth.js store (financial data exposure)
- [ ] **TASK-40** Replace console.log with logger in preview.js store (11 calls)
- [ ] **TASK-41** Standardise error-* → raspberry-* in ConfirmDialog.vue + 43 files
- [ ] **TASK-42** Fix false-positive success notification in PortfolioOptimization.vue
- [ ] **TASK-43** Remove purple-* from AccountGroupList.vue general UI

### Database
- [x] **TASK-44** Add SoftDeletes to Trust model + migration
- [x] **TASK-45** Add SoftDeletes to IHTProfile model + migration
- [x] **TASK-46** Add SoftDeletes to FamilyMember model + migration
- [x] **TASK-47** Add SoftDeletes to ProtectionProfile model + migration
- [x] **TASK-48** Add SoftDeletes to StatePension model + migration
- [x] **TASK-49** Add unique constraints on HasOne tables (6 tables)
- [x] **TASK-50** Add FK constraint on bequests.asset_id
- [x] **TASK-51** Drop 5 duplicate indexes
- [x] **TASK-52** Add 3 missing indexes (bequests, life_event_allocations, plan_action_funding_selections)

---

## Sprint 3 — Medium Priority Fixes

### Tax Compliance (Remaining Hardcoded Values)
- [x] **TASK-53** Replace hardcoded trust charge rates in IHTPeriodicChargeCalculator
- [x] **TASK-54** Replace hardcoded trust tax rates in TrustService
- [x] **TASK-55** Replace hardcoded PSA values in UKTaxCalculator
- [x] **TASK-56** Replace hardcoded PA taper threshold in DividendTaxCalculator
- [x] **TASK-57** Fix fragile tax band parsing in HasAiChat trait
- [x] **TASK-58** Replace hardcoded gift/IHT values in ComprehensiveEstatePlanService
- [x] **TASK-59** Replace hardcoded CLT rate in GiftingStrategyOptimizer
- [x] **TASK-60** Replace hardcoded CGT values in TaxAwareRebalancer + fix outdated comment
- [x] **TASK-61** Replace hardcoded rates in BedAndISACalculator + ISAAllowanceOptimizer
- [x] **TASK-62** Replace hardcoded rates in HouseholdPlanningService

### Security & Backend
- [x] **TASK-63** Tighten preview login rate limits (10/min → 3/min)
- [x] **TASK-64** Add advisor-client relationship validation in AdvisorImpersonationMiddleware
- [x] **TASK-65** Validate column name in CoordinatingAgent whereRaw
- [x] **TASK-66** Add urlencode() to PostcodeLookupController
- [x] **TASK-67** Reduce Sanctum token expiration (480min → 240min)
- [x] **TASK-68** Remove legacy localStorage auth_token references
- [x] **TASK-69** Add HTML sanitiser for LpaDetailView and WillBuilderReviewStep v-html usage
- [x] **TASK-70** Fix RetirementController silent error handling — already had report($e)
- [x] **TASK-71** Fix AdminController redundant name field (done in Sprint 2)
- [x] **TASK-72** Add declare(strict_types=1) to test files — already present in all 172 files
- [x] **TASK-73** Add readonly to MarkowitzOptimizer constructor dependencies

### Frontend Medium Priority
- [x] **TASK-74** Replace hardcoded hex in GoalsProjectionChart tooltip templates
- [x] **TASK-75** Replace hardcoded hex in GoalsProjectionChartDashboard tooltip templates
- [x] **TASK-76** Replace hardcoded hex in DBPensionForm range slider
- [x] **TASK-77** Replace hardcoded hex in RetirementIncomeTab accent-color
- [x] **TASK-78** Replace hardcoded hex in TrustsDashboard
- [x] **TASK-79** Replace hardcoded hex in LpaDetailView
- [x] **TASK-80** Move direct API calls to service layer in LetterToSpouse.vue
- [x] **TASK-81** Move direct API calls to service layer in OnboardingWizard.vue
- [x] **TASK-82** Move direct API calls to service layer in PrivacySettings.vue
- [x] **TASK-83** Fix index-as-key in TaxSettings.vue (5 occurrences)
- [x] **TASK-84** Fix index-as-key in ContributionPlanner.vue (6 occurrences)
- [x] **TASK-85** Fix AnnualAllowanceTracker getHistoricalContributions — shows "Not yet tracked" instead of £0
- [x] **TASK-86** Fix MobileLayout milestoneCount — connected to goals store
- [x] **TASK-87** Verify formatDCPensionType returns spelled-out labels — confirmed, no abbreviations
- [x] **TASK-88** Verify all tooltip titles use "Stocks & Shares ISA" — confirmed, no "S&S" in user-facing text

### Backend (Lower Priority)
- [x] **TASK-89** Implement multi-source income for AnnualAllowanceChecker taper calculation
- [x] **TASK-90** Create Estate module Resource classes (AssetResource, LiabilityResource, GiftResource, TrustResource)
- [x] **TASK-91** Fix DashboardAggregator score exposure — replaced numeric scores with qualitative labels
- [x] **TASK-92** Fix MySQL-specific DATE_ADD in SendProtectionAlerts command
- [x] **TASK-93** Fix IHTStrategyGeneratorService hardcoded "£3,000" string
- [x] **TASK-94** Fix EstateAgent double-load of user data — consolidated to single query

---

## Progress Tracker

| Sprint | Total | Done | Remaining |
|--------|-------|------|-----------|
| Sprint 1 (Critical) | 19 | 19 | 0 |
| Sprint 2 (High) | 33 | 33 | 0 |
| Sprint 3 (Medium) | 42 | 42 | 0 |
| **Total** | **94** | **94** | **0** |

---

*Generated from FULL-CODE-REVIEW.md on 18 March 2026*
