# TODO — Fynla

*Last updated: 19 March 2026*

## Completed This Session

- [x] `error-*` → `raspberry-*` token standardisation across 43 Vue files + removed `error`/`warning`/`info` legacy color definitions from `tailwind.config.js` and `app.css`
- [x] `FinancialHealthScore.vue` — deleted component, store, service, controller, route, and all tests (14 files modified/deleted). Rule 13 compliance.
- [x] `HouseholdPlanningService` hardcoded rates — verified, already uses `TaxConfigService` with sensible fallback defaults. No change needed.
- [x] `console.log` cleanup — already complete. Only 20 occurrences remain in infrastructure files (logger.js, consoleCapture.js, api.js, app.js, bootstrap.js, router/index.js) — all legitimate.
- [x] Branch cleanup — deleted 11 local branches (8 agent worktrees, journeyBug, feature/life-stage-journey, worktree-journeyBug), 3 remote branches (feature/life-stage-journey, worktree-journeyBug, logo-update), removed 1 worktree (journeyBug). All were merged into main.

## Outstanding

### Implementation Incomplete
- [ ] PortfolioOptimization.vue:197 — rebalancing plan creation TODO (button shows "coming soon" toast but no backend implementation)

### Tech Debt
- [ ] Monte Carlo consolidation (TASK-33) — `MonteCarloSimulator` (Investment, has caching + scheduled injections) and `MonteCarloEngine` (Shared, simpler primitive) are still two separate implementations. Consolidate into one. Touches 6+ consumers.
- [ ] `DashboardApiTest.php` — 10 tests failing with 500 errors due to missing `TaxConfigurationSeeder` in `beforeEach()`. Pre-existing issue, not caused by FHS removal.

### Known Issues (Production Deployment)
- [ ] 3 new database migrations need running on production (`2026_03_18_100000`, `100001`, `100002` — SoftDeletes, unique constraints, indexes)
- [ ] `UserResource` in auth responses may break frontend if it expects fields that are no longer returned — needs browser testing after deploy
- [ ] Sanctum token expiration reduced from 8hr to 4hr — may cause unexpected logouts for long sessions

### Deferred Items
- [ ] Scottish Income Tax support — no Scottish rate bands implemented. If any users are Scottish taxpayers, rUK rates are applied incorrectly.

## Context

This session addressed all actionable TODO items from the previous session. The `error-*` → `raspberry-*` migration is complete and the legacy semantic color aliases (`error`, `warning`, `info`) have been removed from the Tailwind config. The FinancialHealthScore feature has been fully removed (component, store, service, controller, route, tests).

Deploy guide is at `March/March18Updates/deployReview.md` and `March/March18Updates/allDeploy.md`. Run the 3 pending migrations and check for duplicate records before the unique constraints migration.

## Files to Review

- `app/Http/Resources/UserResource.php` — verify frontend compatibility
- `app/Http/Resources/AdminUserResource.php` — verify admin panel still works
- `config/sanctum.php` — token expiration changed from 480 to 240 minutes
