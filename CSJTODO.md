# CSJTODO — Fynla

*Last updated: 30 March 2026 — session 18*
*Previous session: 30 March 2026 session 17 (content merge, code review, tech debt)*

---

## Session 18 (30 March) — Admin User Metrics Dashboard + Subscription Tiers

### Completed
- [x] Added "Family" subscription tier (between Standard and Pro)
- [x] Updated pricing: full prices + launch discount prices for all 4 tiers
- [x] Created 2 migrations (family enum, launch price columns)
- [x] Updated SubscriptionPlanSeeder with 4 tiers and launch pricing
- [x] Updated public PricingPage.vue with 4-column layout, strikethrough pricing, launch discount badge
- [x] Built UserMetricsService with 5 analytics query methods
- [x] Built UserMetricsController with 5 API endpoints
- [x] Created 6 Vue components (SnapshotCards, TrialBreakdown, PlanBreakdown, ActivityCharts, ActivityTable, UserMetrics)
- [x] Added "User Metrics" tab to AdminPanel.vue
- [x] 34 Pest tests (20 unit + 14 feature) — all passing
- [x] Browser tested: admin metrics tab (all sections + period selector), pricing page (monthly/yearly toggle)
- [x] Completeness report written to March/March30Updates/
- [x] Deploy guide written to March/March31Updates/deployAdminUser.md
- [x] Branch pushed to origin/adminUserView
- [x] Vault synced (git history, March index, monthly commits, Home.md)
- [x] CLAUDE.md and README.md metrics updated (Vue 646, Services 215, Controllers 90)

### Not Yet Done
- [ ] Merge adminUserView branch into main
- [ ] Deploy to production (see March/March31Updates/deployAdminUser.md)
- [ ] Feature access gating per tier (CheckSubscription middleware only checks active status, not plan-specific features)

---

## Outstanding — Tech Debt Deferred (from techDebtDeferred.md)

### God Class Decomposition (CRITICAL — Large effort, ~40-60 hours)
- [ ] SavingsActionDefinitionService: 3,675 lines
- [ ] RetirementActionDefinitionService: 2,701 lines
- [ ] ProtectionActionDefinitionService: 2,349 lines
- [ ] InvestmentController: 1,067 lines
- [ ] Dashboard.vue: 2,124 lines
- [ ] CalculatorsPage.vue: 2,432 lines

### Float-to-Decimal Migration (HIGH — Blocked)
- [ ] 60+ financial fields across 12 models use 'float' cast
- [ ] Blocked: decimal:2 returns strings, breaks 50+ test assertions
- [ ] Estimated: 1 full sprint

### Test Coverage (HIGH — 20+ hours)
- [ ] Currently 19% (41/214 services tested)
- [ ] Target: 40% coverage

### NPM Vulnerabilities (CRITICAL — 8 hours)
- [ ] 9 high-severity CVEs
- [ ] Needs careful testing of PWA and iOS mobile after update

### Other Deferred
- [ ] Off-palette Tailwind in Risk module (30+ files)
- [ ] Hardcoded hex in SVG/styles (40+ instances)
- [ ] DB facade in 8 controllers
- [ ] Vuex state bloat in investment.js and netWorth.js

## Carried Forward

### Grok AI Migration
- [ ] Get xAI API key
- [ ] Complete AI form fill testing (Steps 4-10)
- [ ] Phase 5: remove Anthropic SDK, delete Python scripts, update legal text

### Known Issues
- [ ] DB pension field mapping mismatch (employer_name vs scheme_name)
- [ ] Expenditure form fill doesn't animate through form
- [ ] property_sale life event: Grok creates property record (double navigation)

## Deploy Status

### Branch: adminUserView (NOT merged to main)
- Deploy guide: March/March31Updates/deployAdminUser.md
- Requires: merge to main, build, PHP upload, 2 migrations, seeder, cache clear

### Previous (session 17 — also not yet deployed)
- Deploy guides: March/March30Updates/reviewDeploy.md + techDeploy.md
- Requires: 48 PHP files, migration (Payment SoftDeletes), reseed, rebuild, cache clear

## Context for Next Session

Session 18 built the admin user metrics dashboard and updated subscription tiers (branch: adminUserView). Both session 17 and session 18 changes need to be deployed. The recommended sequence is: (1) merge adminUserView into main, (2) resolve any conflicts with session 17 changes, (3) do a single production deploy covering both sessions. The deploy guide at March/March31Updates/deployAdminUser.md covers the admin metrics work specifically. Feature-level plan gating (restricting features per tier) is not yet implemented in CheckSubscription middleware — it only checks active subscription status.

## Files to Review
- March/March31Updates/deployAdminUser.md — Admin user metrics deploy guide
- March/March30Updates/admin-user-metrics-completeness-report.md — Full implementation report
- docs/superpowers/specs/2026-03-30-admin-user-metrics-design.md — Design spec
- docs/superpowers/plans/2026-03-30-admin-user-metrics.md — Implementation plan
