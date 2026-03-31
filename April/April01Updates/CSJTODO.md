# CSJTODO — Fynla

*Last updated: 31 March 2026 — sessions 21-23*
*Previous session: 31 March 2026 session 20 (subscription upgrade proration)*

---

## Sessions 21-23 (31 March) — Production Review + Feature Gating + Journey Links

### Completed This Session
- [x] Production review: all PHP files, frontend build, migrations verified matching via MD5
- [x] **Feature gating (PR #175):** CheckFeatureAccess middleware, greyed sidebar items with tooltip, 10 Pest tests, backend route enforcement
- [x] **Journey link (PR #176):** Stage page "Start your journey" CTAs → registration → journey map
- [x] **Security fix:** Register/login now reset ALL Vuex stores + always clear stored auth token (prevented data leakage between users)
- [x] Fixed tooltip clipping (Teleport to body), stage ID mapping (mid_career, peak), CTA text
- [x] All deployed and browser tested on production
- [x] Vault synced, git history updated, session-end skill rewritten

### Feature Gating — NOW DONE
- [x] Feature access gating per tier (was listed as "not yet done" in previous CSJTODO)
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

### All Sessions Through 23 — DEPLOYED & VERIFIED
- Feature gating: PR #175 merged, deployed, production tested (student tier greyed items, tooltip, pro full access)
- Journey links: PR #176 merged, deployed, production e2e tested (stage CTA → register → journey map)
- Security fix (Vuex state + token leak): included in PR #176

## Context for Next Session

All work is deployed and production tested. Feature gating is live — sidebar items grey out based on subscription tier with upgrade tooltips. Stage page CTAs now flow through registration straight to the journey map. Security fix prevents data leakage between users on register/login. Next priorities: recurring billing, tech debt (god classes), or whatever the user wants to work on.
