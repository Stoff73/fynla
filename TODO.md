# TODO — Fynla

*Last updated: 18 March 2026 by session 05c97dda*

## Outstanding from This Session

### Implementation Incomplete
- [ ] `error-*` → `raspberry-*` token standardisation across 43 Vue files (deferred from code review — risk of UI breakage, needs careful visual testing)
- [ ] PortfolioOptimization.vue:197 — rebalancing plan creation TODO (button shows "coming soon" toast but no backend implementation)

### Tech Debt
- [ ] `FinancialHealthScore.vue` — marked DEPRECATED, not imported anywhere. Can be deleted when confirmed unused.
- [ ] Monte Carlo consolidation (TASK-33) — `MonteCarloSimulator` and `MonteCarloEngine` are still two separate implementations. Consolidate into one.
- [ ] `HouseholdPlanningService` hardcoded rates at lines 253, 903 — IHT rate already uses config with fallback, but worth verifying all paths.

### Known Issues
- [ ] 3 new database migrations need running on production (`2026_03_18_100000`, `100001`, `100002` — SoftDeletes, unique constraints, indexes)
- [ ] `UserResource` in auth responses may break frontend if it expects fields that are no longer returned — needs browser testing after deploy
- [ ] Sanctum token expiration reduced from 8hr to 4hr — may cause unexpected logouts for long sessions

### Deferred Items
- [ ] Scottish Income Tax support — no Scottish rate bands implemented. If any users are Scottish taxpayers, rUK rates are applied incorrectly.
- [ ] `console.log` cleanup — 292 raw calls across 146 Vue files were identified. Only the critical ones (MobileLoginScreen, netWorth.js, preview.js) were fixed. The remaining ~280 need gradual cleanup.

## Context for Next Session

This session completed a full codebase code review (96 findings) and fixed all 94 actionable items across 100 files. New skills were created (plan-and-build, vault-sync) and existing skills updated (session-start, session-end). The fynlaBrain vault is fully synced. Metrics in CLAUDE.md and README.md are current.

The codebase is in good shape. The main deployment risk is the `UserResource` change in auth responses — the frontend may expect fields that are now stripped. Test the login flow thoroughly after deploying.

Deploy guide is at `March/March18Updates/deployReview.md`. Run the 3 pending migrations and check for duplicate records before the unique constraints migration.

## Files to Review

- `app/Http/Resources/UserResource.php` — verify frontend compatibility
- `app/Http/Resources/AdminUserResource.php` — verify admin panel still works
- `config/sanctum.php` — token expiration changed from 480 to 240 minutes
- `app/Services/Dashboard/DashboardAggregator.php` — scores replaced with qualitative labels, verify frontend handles the new response shape
