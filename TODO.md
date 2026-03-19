# TODO — Fynla

*Last updated: 19 March 2026 (session 2 end)*

## Completed Today (6 PRs + direct commits)

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

## Outstanding

### Must Verify on Production
- [ ] Risk profile page loads for logged-in real users (not just preview personas)
- [ ] AI chat `get_tax_information` tool works with all 18 topics
- [ ] Investment knowledge nudge appears for users with investments but no knowledge_level
- [ ] Admin tax settings all 10 tabs render correctly on production

### Tech Debt
- [ ] 4 pre-existing Pest test failures (RecommendationPersonaliser x2, FamilyMembersController, InvestmentModule) — not caused by our changes
- [ ] Journey progress calculation should use data completeness (Task 4 from dataReadiness plan — deferred)
- [ ] `ExpenditureForm.vue` — heavily modified by multiple agents, needs careful review
- [ ] Retired/widowed budget hardcoded 85%/70% — should use TaxConfig
- [ ] 16 Vue components with custom @keyframes that should use global CSS (H3 from code review — deferred)
- [ ] EstateAgent narrative strings contain hardcoded "40%", "36%" text (L3 from code review)

### Features (Backlog)
- [ ] PortfolioOptimization.vue:197 — rebalancing plan (coming soon toast)
- [ ] Scottish Income Tax support (placeholder tab added, needs rate bands)
- [ ] Benefits warnings in onboarding + family dashboard
- [ ] Payment/webhook feature tests (L5 from code review)
- [ ] AI chat feature tests (L6 from code review)

### Production Deployment
- [ ] 4 database migrations pending (3 from March 18 + 1 income definitions)
- [ ] Full deploy guide: `March/March19Updates/allDeploy.md`

## Context for Next Session

v0.9.3 is built and ready for production upload. All code review issues (critical/high/medium) are fixed. The main outstanding work is: (1) verifying production after deploy, (2) journey progress using data completeness, and (3) the 4 pre-existing test failures.

The codebase is clean — no uncommitted changes, no stale branches. `public/build/` is ready for upload. 21 PHP files + `resources/views/app.blade.php` + `public/build/` directory need uploading per `allDeploy.md`.
