# TODO — Fynla

*Last updated: 19 March 2026*

## Completed This Session

### Tech Debt & Code Quality
- [x] `error-*` → `raspberry-*` token standardisation across 43 Vue files + removed `error`/`warning`/`info` legacy color definitions from `tailwind.config.js` and `app.css`
- [x] `FinancialHealthScore.vue` — deleted component, store, service, controller, route, and all tests (14 files modified/deleted). Rule 13 compliance.
- [x] Monte Carlo consolidation — `MonteCarloSimulator` now extends `MonteCarloEngine`. Eliminated ~100 lines of duplicated code. All 38 tests pass.
- [x] `HouseholdPlanningService` hardcoded rates — verified, already uses `TaxConfigService` correctly. No change needed.
- [x] `console.log` cleanup — already complete. Only 20 in infrastructure files (legitimate).

### Bug Fixes
- [x] DashboardApiTest 500 errors — `AdvisorImpersonationMiddleware` crashed on `TransientToken::$id`. Fixed by checking for `PersonalAccessToken`. All 27 dashboard tests pass.
- [x] PreviewUserSeeder risk_profiles unique constraint violation — `create()` → `updateOrCreate()`. Database seeding now works.

### Cleanup
- [x] Branch cleanup — deleted 11 local branches, 3 remote branches, removed 10 worktrees. All merged into main.

### UI Changes
- [x] Removed age ranges from all journey/life stage cards — landing page, onboarding focus area selection, dashboard sidebar, persona selector dropdown, persona selection modal. Removed `ageRange` property from `lifeStageConfig.js`. Cards now show just name + tagline.
- [x] Removed "Why this matters" helper text from all 14 onboarding form steps (17 occurrences). Redundant alongside field-level helper text; will be replaced with different contextual content later.

## Outstanding

### Features (Backlog)
- [ ] PortfolioOptimization.vue:197 — rebalancing plan creation (button shows "coming soon" toast, needs backend model/service/controller)
- [ ] Scottish Income Tax support — no Scottish rate bands implemented. Needs `TaxConfigService` extension + income tax calculator updates.

### Production Deployment
- [ ] 3 new database migrations need running (`2026_03_18_100000`, `100001`, `100002` — SoftDeletes, unique constraints, indexes)
- [ ] `UserResource` in auth responses may break frontend if it expects fields that are no longer returned — needs browser testing after deploy
- [ ] Sanctum token expiration reduced from 8hr to 4hr — may cause unexpected logouts for long sessions
- [ ] Deploy guide: `March/March18Updates/deployReview.md` and `March/March18Updates/allDeploy.md`

## Files Changed This Session

### Commits on `main` (before `onboardFix` branch)
- `6c96214` — error→raspberry tokens + FinancialHealthScore removal (57 files)
- `fcba5d7` — AdvisorImpersonationMiddleware TransientToken fix
- `628db8b` — PreviewUserSeeder updateOrCreate fix
- `merge` — Monte Carlo consolidation (4 files)
- `9ccf20a` — TODO.md update

### On `onboardFix` branch (uncommitted)
- `resources/js/constants/lifeStageConfig.js` — removed `ageRange` from all 5 stages
- `resources/js/views/Public/LandingPage.vue` — removed age range display
- `resources/js/components/Onboarding/FocusAreaSelection.vue` — removed age range display
- `resources/js/components/SideMenu.vue` — removed age range from sidebar badge
- `resources/js/components/Preview/PersonaSelector.vue` — removed age range from dropdown
- `resources/js/components/Preview/PersonaSelectionModal.vue` — removed age range from modal
