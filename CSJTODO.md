# CSJTODO — Fynla

*Last updated: 31 March 2026 — session 19*
*Previous session: 30 March 2026 session 18 (admin user metrics, subscription tiers)*

---

## Session 19 (31 March) — Dashboard UI + Subscription & Upgrade Logic

### Completed
- [x] Merged adminUserView branch into main, deleted branch
- [x] Pricing page: raspberry launch prices, "First 500 Users" banner, Family as Most Popular
- [x] Preview banner moved above SubNavBar (stays below top nav on all screens)
- [x] Session timeout fix: await preview store init (personas no longer get 15min timeout)
- [x] Fyn chat icon centred in top nav, opens docked chat panel
- [x] Countdown timer moved to own bar below nav
- [x] Admin button removed from top nav (still in sidebar)
- [x] Sign Up Now / Choose a Plan / Upgrade Now context-aware buttons in nav + sidebar
- [x] PlanSelectionModal: currentPlan filter, dismissable prop, launch pricing from DB
- [x] Trial expiry: non-dismissable modal showing all 4 plans
- [x] Upgrade: modal filtered to plans above current tier (active subscribers only)
- [x] Pro subscribers: no upgrade buttons anywhere
- [x] Launch prices flow through entire checkout (Revolut order, confirm, checkout page display)
- [x] Family plan added to createOrder validation
- [x] CheckSubscription middleware: read-only GET access for expired users
- [x] Subscription data centralised: single fetch in AppLayout, passed as props
- [x] Re-fetches on route change so UI updates after payment
- [x] PlanSelectionModal moved to AppLayout (was rendering inside sidebar DOM)
- [x] Payment confirm: retry up to 5 times with 2s delay for Revolut state settlement
- [x] Backend accepts 'pending' state from Revolut
- [x] verify-code added to API interceptor auth endpoint list (was redirecting to login on wrong code)
- [x] Browser tested on localhost: all 4 subscription states (trial, active standard, active pro, expired)
- [x] Deployed to production, browser tested login flow

### NOT Done — Production Issues Remaining
- [ ] **CRITICAL: Production rate limiting (429)** — subscription tab fails to load on production. Need SSH `php artisan cache:clear` to reset rate limiters. I could not SSH (key not set up for this session).
- [ ] **Production subscription status** — chris@fynla.org has `plan: pro, status: trialing` on production. If payment was made, need to verify and fix via tinker: `$u->subscription->update(['status' => 'active'])`
- [ ] **Full production browser test** — login verified, dashboard verified, but subscription tab and plan selection modal NOT fully tested on production due to 429 rate limits

### Not Yet Done — Feature Gaps
- [ ] Feature access gating per tier (CheckSubscription middleware only checks active status, not plan-specific features)
- [ ] Subscription management page for active subscribers (change plan, cancel, billing history display)

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

### Deployed 31 March (partially)
- Frontend build uploaded
- PHP files uploaded: PaymentController.php, CheckSubscription.php
- **NEEDS: `php artisan cache:clear` on production** (rate limiters blocking subscription tab)
- Deploy guide: March/March31Updates/deployUpgrade.md
- Also need to upload: resources/js/services/api.js change is in the build already

### Previous (sessions 17-18 — deployed)
- Admin user metrics, subscription tiers, Family plan — all deployed

## Context for Next Session

Session 19 built the full subscription/upgrade UI flow. The code is correct and browser tested on localhost for all 4 states (trial, active standard, active pro, expired). Production deployment is partially done — the build and PHP files are uploaded but rate limiters need clearing via SSH (`php artisan cache:clear`). After clearing, test the subscription tab and plan selection modal on production. The production subscription for chris@fynla.org may need status fixed to 'active' via tinker if payment went through.

## Files to Review
- March/March31Updates/deployUpgrade.md — Full deploy guide with all changes
- March/March31Updates/deployUI.md — Earlier UI-only deploy guide
