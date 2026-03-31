# CSJTODO — Fynla

*Last updated: 31 March 2026 — session 20*
*Previous session: 31 March 2026 session 19 (subscription UI, deploy)*

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

### NOT Done — Needs Deployment
- [ ] **Build for production:** `./deploy/fynla-org/build.sh` (session 20 changes not yet built)
- [ ] **Deploy session 20 changes:** New migration, updated PHP files, new frontend build
- [ ] **Deploy guide:** needs generating from `git diff`

### NOT Done — Production Issues (carried from session 19)
- [ ] **Production rate limiting (429)** — may still need `php artisan cache:clear` if not resolved
- [ ] **chris@fynla.org subscription status** — has `plan: pro, status: trialing`. May need tinker fix: `$u->subscription->update(['status' => 'active'])`
- [ ] **Full production browser test** of subscription + upgrade flow

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

### Session 20 — NOT YET DEPLOYED
- Code complete, tested on localhost
- Needs: `./deploy/fynla-org/build.sh` → upload build + PHP files → run migration → clear caches
- Changed files: PaymentController.php, Payment.php, Subscription.php, routes/api.php, Navbar.vue, PlanSelectionModal.vue, AppLayout.vue, SubscriptionManagement.vue, CheckoutPage.vue, new migration, new test file

### Session 19 — DEPLOYED
- Frontend build + PHP files uploaded
- All SSH commands run, migrations run, caches cleared, seeders run
- Deploy guides: March/March31Updates/deployUpgrade.md, deployUI.md, deployFull.md

### Previous (sessions 17-18 — deployed)
- Admin user metrics, subscription tiers, Family plan — all deployed

## Context for Next Session

Session 20 implemented the upgrade proration system. A user on Standard clicking "Upgrade Now" sees Family + Pro plans. Selecting one routes to checkout with `&upgrade=true`, which calls `POST /api/payment/upgrade` to calculate the prorated amount (e.g. Standard→Pro, 9 months remaining = £74.97). On payment confirmation, the plan upgrades but period dates stay unchanged. All 4 subscription states browser tested. 9 Pest tests pass. Needs building and deploying.

## Files to Review
- March/March31Updates/subscriptionPlan.md — Full plan with proration formula and architecture
- March/March31Updates/subscriptionTaskList.md — Task list with all checkboxes marked
