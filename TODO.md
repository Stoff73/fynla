# TODO — Fynla

*Last updated: 22 March 2026 — session 3 (sidebar revert, info guide fixes, UK taxes removal, OpenAI spec)*

## Completed This Session

### Sidebar & UI Cleanup (PR #158 — merged)
- [x] Sidebar revert — all menu items always visible under headings, no journey filtering
- [x] Info guide link fixes — 43 navigation links corrected across all modules
- [x] "Suggested for You" goals card removed from dashboard
- [x] ~215 lines dead code cleanup (SideMenu.vue, lifeStage.js, lifeStageConfig.js, Dashboard.vue)
- [x] UK Taxes page deleted (hardcoded values, wrong NI/CGT rates) — 2,362 lines removed
- [x] InfoGuidePanel business interests route fixed
- [x] Code review — 6 issues found and all fixed
- [x] Full deployment guide (deployAll.md) covering all pending changes
- [x] Non-technical update notes for March 20-22

### OpenAI Migration Spec (drafted, not implemented)
- [x] Design spec for swapping Fyn assistant from Anthropic to OpenAI
- [x] Approach: swap the curl call in HasAiChat.php, adapt request/response format, 6 files to change

## Not Yet Deployed

All changes from PRs #148-#158 are on main but NOT deployed to production. Full deploy guide at March/March22Updates/deployAll.md.

### To Deploy — COMPLETED 23 March 2026
- [x] Run ./deploy/fynla-org/build.sh locally (done — built twice today)
- [x] Upload public/build/ to server
- [x] Upload all changed PHP files (see deployAll.md for full list)
- [x] Run 3 pending migrations
- [x] Run composer dump-autoload on server
- [x] Seed: php artisan db:seed
- [x] Clear caches on server
- [x] Delete UKTaxesController.php from server

## Known Issues (Carried Forward)
- [x] PropertyForm edit 422 — FIXED & deployed 23 March. Root cause: `lease_remaining_years` sent as `{}` (Laravel MissingValue from `$this->when()`) failed integer validation. Fix: clean non-scalar values in PropertyForm.vue handleSubmit()
- [x] Sidebar journey %: intermittently shows 0% — FIXED & deployed 23 March. Root cause: sidebar rendered before life-stage API responded. Fix: hide progress section until data loaded (SideMenu.vue showProgress computed). Also fixed isStudentPersona hiding dashboard cards for real users with university life stage.
- [ ] AI form fill: remaining entity types untested (DB pension, property, mortgage, estate assets/gifts, trusts, business interests, chattels, goals, life events, family members, edit flow) — see fynTest.md

## Tech Debt
- [ ] OnboardingWizard.vue: Vue warn about failed component resolution (non-blocking, cosmetic)
- [ ] LiabilitiesStep.vue: DEPRECATED comment — will be replaced by unified form with context="onboarding"
- [ ] IncomeStatementTab.vue is orphaned (never imported) — decide: wire into a view or delete
- [ ] lifeStageConfig.js: sidebar.primary and sidebar.explore arrays still present but unused (kept intentionally in case filtering is re-enabled)

## Grok AI Migration (branch: grokAI)

**Status:** Phase 0-1-3 complete. Phases 2, 4, 5, 6 remaining.
**Plan:** March/March23Updates/grokMigrationPlan-v2.md
**Status doc:** March/March23Updates/grokMigrationStatus.md

### Completed
- [x] Phase 0: Security hardening (deployed to production)
- [x] Phase 1: Foundation — openai-php/client installed, XaiClient, config, feature flag
- [x] Phase 3: Streaming chat — dual-provider HasAiChat (Anthropic + xAI), browser tested

### Next Session Tasks
- [ ] Phase 2: Document extraction — update AIExtractionService (URL, auth, format)
- [ ] Phase 4: Python sidecar replacement — create XaiDeepAnalysisService (PHP-native tool loop)
- [ ] Phase 5: Cleanup — remove Anthropic SDK, delete Python scripts, update legal text
- [ ] Phase 6: Full testing with XAI_API_KEY — streaming, tools, documents, all personas
- [ ] Get xAI API key from https://console.x.ai
- [ ] Test with AI_PROVIDER=xai locally before deploying

## Known Issues (Carried Forward)
- [ ] AI form fill: remaining entity types untested (DB pension, property, mortgage, estate assets/gifts, trusts, business interests, chattels, goals, life events, family members, edit flow) — see fynTest.md

## Tech Debt
- [ ] OnboardingWizard.vue: Vue warn about failed component resolution (non-blocking, cosmetic)
- [ ] LiabilitiesStep.vue: DEPRECATED comment — will be replaced by unified form with context="onboarding"
- [ ] IncomeStatementTab.vue is orphaned (never imported) — decide: wire into a view or delete
- [ ] lifeStageConfig.js: sidebar.primary and sidebar.explore arrays still present but unused (kept intentionally in case filtering is re-enabled)
- [ ] WARN-002: Security sessions API returns 500 on /api/auth/sessions
- [ ] WARN-003: Vue error on holistic-plan page: "Cannot read properties of undefined"

## Context for Next Session

Session 23 March: Fixed 4 bugs (property edit 422, investment edit 422, dashboard card filtering, sidebar 0% flash). Full system test on production (28 pages, 14 forms, 30 Fyn nav routes, new user registration). Started Grok AI migration — Phase 0 security hardening deployed, Phases 1 and 3 implemented on grokAI branch.

Key files:
- Migration plan: March/March23Updates/grokMigrationPlan-v2.md
- Migration status: March/March23Updates/grokMigrationStatus.md
- Security hardening deploy: March/March23Updates/deploySecHard.md
- Full test results: March/March23Updates/testResults.md
- Form filling test: March/March23Updates/formFilling.md
- Test protocol: March/March23Updates/testingProtocol.md
