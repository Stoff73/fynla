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

### To Deploy
- [ ] Run ./deploy/fynla-org/build.sh locally (done — built twice today)
- [ ] Upload public/build/ to server
- [ ] Upload all changed PHP files (see deployAll.md for full list)
- [ ] Run 3 pending migrations
- [ ] Run composer dump-autoload on server
- [ ] Seed: php artisan db:seed
- [ ] Clear caches on server
- [ ] Delete UKTaxesController.php from server

## Known Issues (Carried Forward)
- [ ] PropertyForm edit 422 — editing a property via the UI form returns 422 validation error
- [ ] Sidebar journey %: intermittently shows 0% on some pages (race condition)
- [ ] AI form fill: remaining entity types untested (DB pension, property, mortgage, estate assets/gifts, trusts, business interests, chattels, goals, life events, family members, edit flow) — see fynTest.md

## Tech Debt
- [ ] OnboardingWizard.vue: Vue warn about failed component resolution (non-blocking, cosmetic)
- [ ] LiabilitiesStep.vue: DEPRECATED comment — will be replaced by unified form with context="onboarding"
- [ ] IncomeStatementTab.vue is orphaned (never imported) — decide: wire into a view or delete
- [ ] lifeStageConfig.js: sidebar.primary and sidebar.explore arrays still present but unused (kept intentionally in case filtering is re-enabled)

## Future Work
- [ ] OpenAI migration — implement the spec at docs/superpowers/specs/2026-03-22-openai-agent-sdk-migration-design.md (6 files, curl-based, no new dependencies)
- [ ] OpenAI SDK guardrails — investigate input/output guardrails for regulatory compliance

## Context for Next Session

PR #158 merged with sidebar, info guide, suggested goals, and UK Taxes cleanup. All on main, not deployed. Build is ready (ran twice today). The OpenAI migration spec is drafted but the user clarified the approach: just swap the API call in HasAiChat.php using PHP curl, no Python subprocess needed. 6 files to change. Model: gpt-5-nano-2025-08-07.

Deploy guide: March/March22Updates/deployAll.md
AI form fill state: March/March21Updates/currentFormFillState.md
AI form fill testing: March/March21Updates/fynTest.md
