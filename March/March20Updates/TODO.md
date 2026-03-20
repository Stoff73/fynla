# TODO — Fynla

*Last updated: 20 March 2026 (session 2 end)*

## Completed 19 March (6 PRs + direct commits)

- [x] PR #140: Simple expenditure bug fix (browser tested), LetterToSpouse fix, education_level validation
- [x] PR #141: Admin tax settings — 568/568 config coverage, NaN fixes, 10 tabs, agent hardcoded values removed, AI tax tool 18 topics
- [x] PR #142: AI CRUD tools (create/update/delete/profile for 18 entity types), zero-token navigation
- [x] PR #143: Info guide button to navbar, chat renamed "Fyn", session lifecycle fixed
- [x] PR #144: PrerequisiteGateService delegates to DataReadiness services, completeness endpoint enriched, knowledge nudge
- [x] PR #145: Code review remediation — 3 critical, 5 high, 5 medium issues
- [x] Risk profile PreviewWriteInterceptor fix
- [x] TrustsDashboard build fix (bg-light-pink-50)
- [x] Version bumped to v0.9.3
- [x] Homepage branch merged
- [x] Browser tab title: "Fyn, your financial companion"
- [x] All stale branches cleaned up

## Completed 20 March — Session 1: Tech Debt Sweep

### Test Fixes (8 failures → 0, 2029 passing)
- [x] RecommendationPersonaliser (x2) — strict type `=== 0.0` fixed with `(float)` cast
- [x] AdvisorController — `ModelNotFoundException` now caught → 403 (was 500)
- [x] FamilyMembersController — `assertDatabaseMissing` → `assertSoftDeleted` (model uses SoftDeletes)
- [x] CompletenessEndpoint — updated expected string to match DataReadiness service label
- [x] WillBuilderApiTest — set `middle_name => null` on spouse factory
- [x] InvestmentModuleTest — `RiskProfile::create` → `firstOrCreate` (observer already creates one)
- [x] AdminBackupTest (x2) — order-dependent, resolved by InvestmentModule fix

### ExpenditureForm.vue Review (7 fixes)
- [x] Financial Commitments section header only summed `protection` — now includes `investments` too
- [x] `isSectionExpanded` fallback `?? true` contradicted `false` init — changed to `?? false`
- [x] Removed dead code: `retiredCommitments` computed + `getRetiredHouseholdValue`
- [x] Replaced all off-palette color tokens: `blue-*` → `violet-*`, `green-*` → `spring-*`/`raspberry-*`, `success-*` → `spring-*`

### Estate Hardcoded Rates (4 files, 30 instances)
- [x] EstateAgent.php — 12 narrative strings now use `$ihtConfig['standard_rate']` / `['reduced_rate_charity']`
- [x] GiftingStrategy.php — narrative + `* 0.04` calculation now use config values
- [x] IHTStrategyGeneratorService.php — narrative uses config
- [x] WillAnalysisService.php — 3 match expressions use config

### Budget Constants (3 files)
- [x] DecumulationPlanner.php — `0.85` → `JOINT_ANNUITY_RATE_REDUCTION`
- [x] IHTFormattingService.php — `0.70` → `EXPENDITURE_FALLBACK_RATIO`
- [x] IHTCalculationService.php — `0.70` → `EXPENDITURE_FALLBACK_RATIO`, `0.50` → `RETIREMENT_EXPENDITURE_FALLBACK_RATIO`

### @keyframes Cleanup (8 files + app.css)
- [x] 4 components: fadeIn/calcFadeIn/contentFadeIn/sectionFadeIn → `.animate-fade-in` / `.animate-fade-in-slide`
- [x] 4 components: slideIn → new global `.animate-slide-in-right` (added to app.css)
- [x] 8 remaining @keyframes are domain-specific (typing-bounce, voice-pulse, confetti-fall, etc.) — correctly kept as local

## Completed 20 March — Session 2: Onboarding Refactor + Field Tracking

### Production Bug Fixes (12 issues from production testing)
- [x] Phone validation — `prepareForValidation()` strips spaces/dashes
- [x] Goals retirement age fallback `45` → `DEFAULT_RETIREMENT_AGE` (68)
- [x] Dashboard student loan — dynamic plan type + thresholds
- [x] Back navigation — `savedStepData` cache preserves form data
- [x] Will step — removed stale "Coming Soon" banner
- [x] Estate API — `$allPolicies` → `$allLifePolicies` undefined variable
- [x] Net Worth liabilities — include mortgages in response
- [x] Joint owner — handles unlinked spouse via `joint_owner_name`
- [x] Investment/Retirement plan CTAs — corrected routes to `/net-worth/*`

### Onboarding System Refactor
- [x] Deleted 9 duplicate components (Era 2/3/4)
- [x] Unified STEP_COMPONENTS (25+ → 13 entries)
- [x] Single `assets` step per journey with tab filtering via `visibleTabs`
- [x] Inline forms (`context="onboarding"`) — 6 form components render in content area, not modals
- [x] Contextual sidebar — 10 contexts with "Did you know?", "Why we ask", Quick Stat
- [x] `LearningMilestoneSidebar` accepts `override` prop for step-level sidebar content
- [x] Family step added to journeys 4 (Peak) and 5 (Retirement) with tailored sidebar milestones
- [x] PersonalInfoStep field visibility per journey (marital, address, health hidden for J1/J2)
- [x] Name + email pre-populated from auth store, disabled
- [x] All frontend validation removed (21 Vue files)
- [x] All PHP Form Requests `required` → `sometimes` (31 files)
- [x] 15 enum columns made nullable via migration
- [x] Returning user mode simplified — always resumes life stage journey
- [x] `LifeStageService::getDataCompleteness()` — single `assets` check instead of split IDs

### Field-Level Completeness Tracking
- [x] `LifeStageService::getStepCompleteness()` — per-step field checks, journey-aware
- [x] `LifeStageService::getFullFieldCompleteness()` — all fields for agents/AI with `form_link`
- [x] `LifeStageController::progress()` returns `step_completeness` in response
- [x] `lifeStage.js` store — `stepCompleteness` state, `refreshCompleteness` action, field-based `progressPercentage`
- [x] Progress bar — three states: green tick (complete), raspberry with % (partial), raspberry dash (skipped)
- [x] `handleLifeStageSkip` no longer stamps step as complete
- [x] `handleLifeStageNext` refreshes completeness from backend
- [x] `JourneyProgressHero` counts only steps with `status === 'complete'`
- [x] Browser tested: J3 with mixed states — 56% partial, skipped, complete all showing correctly

### Knowledge Nudge Fix
- [x] `updateKnowledgeLevel` was spreading entire risk profile (422 error) — now sends only `{ knowledge_level }`
- [x] Browser tested: click Experienced → saves to DB → nudge disappears → risk profile shows "Experienced / Upper-Med"

### Occupation Lookup Fix
- [x] `OccupationCodeSeeder` added to `DatabaseSeeder.php` (was missing)
- [x] 406 ONS SOC 2020 codes seeded

### Tax Config Corrections (5 values)
- [x] Employer NI Rate: 13.8% → 15.0% (April 2025)
- [x] Employer NI Secondary Threshold: £9,100 → £5,000 (Autumn Budget 2024)
- [x] CGT non-property rates: 10%/20% → 18%/24% (30 Oct 2024)
- [x] BADR rate: 10% → 14% (2025/26, rises to 18% in 2026/27)
- [x] Class 4 NI Main Rate: 9% → 6% (April 2024)

### Browser Testing (Journeys 4 + 5 end-to-end)
- [x] Journey 4 (Peak): David Mitchell — 6 steps, all fields filled, dashboard 100%, SIPP £380k, property £1.85M
- [x] Journey 5 (Retirement): Robert Williams — 6 steps, all fields filled, Net Worth £694,500, IHT £77,800

### Code Review Fixes (11 issues)
- [x] AssetsStep: document upload captures type before closeUploadModal nulls it
- [x] AssetsStep: handleNext uses allowedTabs not hardcoded full tab order
- [x] OnboardingController: removed PII (data payload) from Log::info
- [x] OnboardingWizard: resume uses allCompletedSteps union + awaits fetchStage
- [x] OnboardingWizard: honours ?step= query param from dashboard Continue Journey
- [x] OnboardingWizard: student loan checks for existing liability before creating
- [x] OnboardingService: joint accounts set joint_owner_id per Rule #7
- [x] OnboardingService: postcode access uses ?? null fallback
- [x] GoalSetupStep: consumes savedData prop for back-navigation restore
- [x] LifeStageService: only sets onboarding_focus_area for estate stage
- [x] OnboardingController: restricts setFocusArea to estate only

### Tech Debt Audit
- [x] Full codebase scan completed — 58 issues found (6 critical, 30 warning, 22 suggestion)
- [x] Report saved to `docs/tech-debt-report-full.md`

## Outstanding

### Must Verify on Production
- [ ] Deploy all changes per `deployFix.md`
- [ ] Run `TaxConfigurationSeeder` on production (currently serving wrong rates)
- [ ] Run `OccupationCodeSeeder` on production (table empty)
- [ ] Risk profile page loads for logged-in real users (not just preview personas)
- [ ] AI chat `get_tax_information` tool works with all 18 topics
- [ ] Admin tax settings all 10 tabs render correctly on production

### Known Minor Issues
- [ ] Step 3 label in journey overview shows "assets" (lowercase) instead of "Assets & Wealth"
- [ ] State Pension sidebar shows DC pension sidebar content instead of `retirement-form-state`
- [ ] Vue warnings: "Failed to resolve component" in console (likely stale JourneyCompletionStep reference)

### Tech Debt Quick Wins (from full audit — `docs/tech-debt-report-full.md`)
- [ ] Payment.php + Subscription.php: amount cast `integer` → `decimal:2` (5 min)
- [ ] IHTCalculation.php: 15 currency fields cast `float` → `decimal:2` (15 min)
- [ ] Liability.php: float casts → `decimal:2` (5 min)
- [ ] Deduplicate `calculateAge` in FamilyMembers.vue + FamilyInfoStep.vue (10 min)
- [ ] Remove orphaned `guidance.js` store module (10 min)
- [ ] Consolidate duplicate `calculateEquity()` in PropertyCalculationService vs PropertyService (1 hr)
- [ ] 15 Vue files with old colour tokens (`blue-*`, `green-*`, `purple-*`, `red-*`) need palette migration (3 hrs)

### Features (Backlog)
- [ ] PortfolioOptimization.vue:197 — rebalancing plan (coming soon toast)
- [ ] Scottish Income Tax support (placeholder tab added, needs rate bands)
- [ ] Benefits warnings in onboarding + family dashboard
- [ ] Payment/webhook feature tests (L5 from code review)
- [ ] AI chat feature tests (L6 from code review)

### Production Deployment
- [ ] Deploy today's changes: `March/March20Updates/deployFix.md`
- [ ] Rebuild: `./deploy/fynla-org/build.sh`
- [ ] Upload: PHP files + seeders + migrations + `public/build/`
- [ ] SSH: migrate + seed (OccupationCode + TaxConfiguration) + cache clear

## Context for Next Session

Session 2 completed: onboarding refactor (Era 1 consolidation, inline forms, contextual sidebar), field-level completeness tracking (skipped/partial/complete), family step for journeys 4+5, tax config corrections, knowledge nudge fix, and 11 code review fixes (PII logging, hidden tab navigation, resume logic, duplicate liabilities, joint ownership). Full tech debt audit completed — report at `docs/tech-debt-report-full.md`. All changes committed. Ready for deploy.

Key commits today (session 2):
- `c719ed8` — remap all journeys to Era 1 components
- `717d24f` — delete 9 duplicate components
- `5b54cc3` — inline forms + contextual sidebar
- `6444522` — field-level completeness tracking
- `a00bd48` — knowledge nudge fix
- `a5e41a0` — tax config corrections
- `487c51f` — 11 code review fixes (PII, hidden tabs, resume, duplicates)
