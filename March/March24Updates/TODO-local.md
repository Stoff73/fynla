# TODO — Fynla

*Last updated: 23 March 2026 — dashboard batch 7 session (recommended actions, retirement bars, full-width, onboarding fix)*
*Previous session: 22 March 2026*

## Completed This Session

- [x] Remove max-w constraints from all dashboard views (full-width layout)
- [x] Remove max-width from NetWorthDashboard wrapper
- [x] Hero "Recommendations" → "Recommended Actions" sourced from plans store (protection + investment)
- [x] Hide recommended actions section when no actions exist
- [x] Retirement progress bars: bigger (h-6), bolder, horizon blue gradient with % inside
- [x] Centre-align net worth donut chart in dashboard card
- [x] Remove Risk Profile link from InvestmentList
- [x] Remove back button from RiskProfilePage
- [x] Login logo smaller (100px) + linked to fynla.org
- [x] Resolve OnboardingWizard merge conflict (remove duplicate "Onboarding" header, keep "Your Journey")
- [x] Recommended actions section width set to 1/3 of hero bar

## Not Yet Deployed

### Dashboard branch (not merged to main)
All dashboard work is on the `dashboard` branch. Deploy guide at March/March23Updates/deploy.md.

- [ ] Merge dashboard branch to main (or deploy from branch)
- [ ] Run ./deploy/fynla-org/build.sh locally
- [ ] Upload public/build/ to server
- [ ] Upload public/images/Fyn/Fyn-Icon.png
- [ ] Clear caches on server

### Previous (from March 22 — PRs #148-#158 on main)
- [ ] Upload all changed PHP files (see March/March22Updates/deployAll.md)
- [ ] Run 3 pending migrations
- [ ] Run composer dump-autoload on server
- [ ] Seed: php artisan db:seed
- [ ] Delete UKTaxesController.php from server

## Tech Debt (This Session)

- [ ] JourneyProgressHero.vue:111-115 — unused `suggestedGoals` prop (dead code)
- [ ] JourneyProgressHero.vue:132 — unused `learningMilestone` getter mapped but never used

## Known Issues (Carried Forward)

- [ ] PropertyForm edit 422 — editing a property via the UI form returns 422 validation error
- [ ] Sidebar journey %: intermittently shows 0% on some pages (race condition)
- [ ] AI form fill: remaining entity types untested (see March/March21Updates/fynTest.md)

## Tech Debt (Carried Forward)

- [ ] OnboardingWizard.vue: Vue warn about failed component resolution (non-blocking)
- [ ] LiabilitiesStep.vue: DEPRECATED — will be replaced by unified form
- [ ] IncomeStatementTab.vue is orphaned (never imported) — decide: wire in or delete
- [ ] lifeStageConfig.js: sidebar.primary and sidebar.explore arrays unused but kept

## Future Work

- [ ] OpenAI migration — implement spec at docs/superpowers/specs/2026-03-22-openai-agent-sdk-migration-design.md
- [ ] OpenAI SDK guardrails — investigate for regulatory compliance

## Context for Next Session

Dashboard branch has 7 batches of UI polish committed and pushed. Key changes: SubNav system, side menu restructure, full-width layout, recommended actions in hero (from plans store), bigger retirement progress bars, onboarding header fix. None deployed yet — dashboard branch needs merging to main first, then full frontend rebuild and upload. Previous main branch changes (PRs #148-#158) also still awaiting deployment with PHP files + migrations.

Deploy guide (this session): March/March23Updates/deploy.md
Deploy guide (previous): March/March22Updates/deployAll.md
