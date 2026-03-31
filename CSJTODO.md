# CSJTODO — Fynla

*Last updated: 31 March 2026 — production review*
*Previous session: 31 March 2026 session 20 (subscription upgrade proration)*

---

## Session 20 (31 March) — Subscription Upgrade Proration + Bug Fixes

### Completed This Session
- [x] Removed stale worktree `.claude/worktrees/agent-a9856e54` (admin analytics already on main)
- [x] Fixed inverted progress bar in Navbar (`100 - progress` → `progress`)
- [x] Added `upgrade_from_plan` column to payments table (migration)
- [x] Added `status` + `revolut_order_id` to Subscription model fillable
- [x] Built `POST /api/payment/upgrade` endpoint with proration calculation
- [x] Proration formula: yearly = `(price_diff / 12) * months_remaining`, monthly = full price diff
- [x] Modified `confirmPayment` to keep period dates for upgrades (don't reset to now)
- [x] PlanSelectionModal emits `isUpgrade` flag when `currentPlan` set
- [x] AppLayout + SubscriptionManagement route to checkout with `&upgrade=true`
- [x] CheckoutPage: calls `/payment/upgrade` in upgrade mode, shows "Upgrade Summary" with prorated amount
- [x] Added "Upgrade" button to subscription tab (active non-pro subscribers only)
- [x] SubscriptionManagement passes `currentPlan` to modal (filters to higher tiers)
- [x] 9/9 Pest tests pass (proration math, validation, period date handling)
- [x] Browser tested all 4 states: trial, active standard, active pro, expired
- [x] Database seeded

### Session 19 Completed (earlier today)
- [x] Full subscription/upgrade UI flow (see previous section below)
- [x] Deployed to production (all files, SSH, migrations, caches, seeders)

### Deployment — VERIFIED 31 March 2026
- [x] **Build deployed:** Frontend build manifest matches local (md5: cd6d813d)
- [x] **All PHP files match:** 19/19 files verified via MD5 checksums
- [x] **All migrations ran:** Including session 20 `add_upgrade_from_plan_to_payments_table`
- [x] **All 9 payment routes registered:** Including `POST api/payment/upgrade`
- [x] **Subscription.php synced:** Local updated to match production patch (fillable ordering)
- [x] **Production env:** `production`, Laravel 10.49.1

### Not Yet Done — Feature Gaps
- [ ] Feature access gating per tier (CheckSubscription middleware only checks active status, not plan-specific features)
- [ ] Recurring billing / auto-renewal (currently one-time Revolut orders only)

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

### All Sessions Through 20 — DEPLOYED & VERIFIED
- Production verified via SSH MD5 comparison on 31 March 2026
- All PHP files, frontend build, migrations, routes confirmed matching local
- Deploy guides: March/March31Updates/deployUpgrade.md, deployUI.md, deployFull.md

## Context for Next Session

All sessions through 20 are deployed and verified on production. The subscription upgrade proration system is live. Local codebase matches production exactly (Subscription.php synced to match server patch).

## Files to Review
- March/March31Updates/subscriptionPlan.md — Full plan with proration formula and architecture
- March/March31Updates/subscriptionTaskList.md — Task list with all checkboxes marked
