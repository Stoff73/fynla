# TODO — Fynla

*Last updated: 20 March 2026 (session 1 end)*

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

## Completed 20 March — Tech Debt Sweep

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

## Outstanding

### Must Verify on Production
- [ ] Risk profile page loads for logged-in real users (not just preview personas)
- [ ] AI chat `get_tax_information` tool works with all 18 topics
- [ ] Investment knowledge nudge appears for users with investments but no knowledge_level
- [ ] Admin tax settings all 10 tabs render correctly on production

### Tech Debt (remaining)
- [ ] Journey progress calculation should use data completeness (Task 4 from dataReadiness plan — deferred, needs product decision)
- [x] ExpenditureForm.vue: retired/widowed budget overrides now persist to DB via JSON columns and restore on load
- [x] ExpenditureForm.vue: joint mode now saves expenditure for both user and spouse

### Features (Backlog)
- [ ] PortfolioOptimization.vue:197 — rebalancing plan (coming soon toast)
- [ ] Scottish Income Tax support (placeholder tab added, needs rate bands)
- [ ] Benefits warnings in onboarding + family dashboard
- [ ] Payment/webhook feature tests (L5 from code review)
- [ ] AI chat feature tests (L6 from code review)

### Production Deployment
- [x] Database migrations run on production (confirmed by user)
- [ ] Deploy today's changes: `March/March20Updates/deployFix.md` (rebuild needed, 11 PHP files + build dir + 1 migration)
- [ ] Previous v0.9.3 deploy: `March/March19Updates/allDeploy.md` (if not yet uploaded)

## Context for Next Session

All tech debt from the 19 March code review is resolved, plus the two ExpenditureForm bugs discovered during review are now fixed. Test suite is fully green: 2029 passed, 0 failures. One new migration adds `retired_budget_overrides` and `widowed_budget_overrides` JSON columns to the users table.

Browser testing was NOT completed this session — Playwright MCP server disconnected. All changes need browser verification before deploy:
- ExpenditureForm colour tokens, section header totals, budget override persistence, joint mode spouse save
- Estate narrative dynamic rate text
- Animation replacements (fade-in, slide-in) on 8 components

Codebase needs rebuild (`./deploy/fynla-org/build.sh`) and deploy per `deployFix.md`. Also `dev.sh` was fixed to use port 5173 (was incorrectly set to 5174).
