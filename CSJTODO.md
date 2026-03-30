# CSJTODO — Fynla

*Last updated: 28 March 2026 — session 16*
*Previous session: 27 March 2026 (10 production bug fixes)*

---

## Session 16 (28 March) — PR Merge, Build, Full Code Review

### Completed
- [x] Committed InvestmentProjections.vue cleanup + ProtectionDashboard.vue fix
- [x] Added CSJTODO.md to .gitignore
- [x] Merged PR #169 (brett-v1 — full platform buildout, 68 files, +12,045/-2,506)
- [x] Resolved 12 merge conflicts (kept brett-v1 Vue, added main's PHP fixes)
- [x] Cleaned up stale branches (brett-v1, investmentUI)
- [x] Generated deploy guide (brettMergeDeploy.md)
- [x] Built production frontend (./deploy/fynla-org/build.sh)
- [x] Full codebase code review — 4 parallel agents (backend, frontend, DB/security, architecture)
- [x] Code review report: 43 issues (12 critical, 12 high, 11 medium, 8 low)

---

## Outstanding — Code Review Fixes (from codeReview-28March2026.md)

### CRITICAL — Security (fix before next deploy)
- [ ] SEC-01: Remove national_insurance_number from UserProfileService API response
- [ ] SEC-03: Apply mfa.verified middleware to financial write routes
- [ ] SEC-04: Guard spouse_id, plan, role_id, household_id on User model
- [ ] SEC-02: Fix admin seeder (remove is_preview_user, use env password)

### CRITICAL — Tax Compliance
- [ ] TAX-01: Replace hardcoded 125140/50270 in RetirementActionDefinitionService with TaxConfigService
- [ ] TAX-02: Replace hardcoded max:20000 in AssetLocationController with TaxConfigService
- [ ] TAX-03: Remove hardcoded 60000 Annual Allowance from 4 frontend files

### CRITICAL — Design System
- [ ] DS-01: Replace local Intl.NumberFormat with currencyMixin (9 files)
- [ ] DS-02: Replace bg-[#EEEEEE] with bg-light-gray (14 files)
- [ ] DS-03: Fix off-palette border-blue-600/purple-600 tab colors (9 files)

### HIGH — Security
- [ ] SEC-05: Move Subscription/Payment status fields to $guarded
- [ ] SEC-06: Fix EstateController raw exception message leakage (6 catch blocks)
- [ ] SEC-07: Fix PolicyCRUDTrait raw exception messages (3 catch blocks)
- [ ] SEC-08: Remove dead user_id parameter from verifyCode validation
- [ ] SEC-10: Remove full filesystem paths from AdminController backup endpoints

### HIGH — Architecture
- [ ] ARCH-04: Remove console.log from app.js (8), AccountForm.vue (13), whatIf.js (1)
- [ ] CONV-01: Add readonly to constructor properties in 10+ controllers

### Deferred — Next Sprint
- [ ] ARCH-01/02: Refactor god classes (SavingsActionDef 3105 lines, CoordinatingAgent 2201)
- [ ] ARCH-03: EstateController should delegate to EstateAgent
- [ ] DB-01: Delete duplicate goals table migrations
- [ ] DB-02: Add SoftDeletes to Payment model
- [ ] ARCH-07: Add tests for 5 untested critical services
- [ ] DS-05: Replace banned #EF9F27 amber color in PublicLayout
- [ ] DS-08: Remove score gauge from ContributionPlanner.vue

## Carried Forward (from previous sessions)

### Grok AI Migration
- [ ] Get xAI API key from https://console.x.ai
- [ ] Complete AI form fill testing — follow aiProcess.md Steps 4-10
- [ ] Test with xAI locally — chat, streaming, tool calling, navigation
- [ ] Test document extraction with xAI
- [ ] Phase 5 remaining: remove Anthropic SDK, delete Python scripts, update legal text
- [ ] Merge grokAI branch to main

### Known Issues
- [ ] DB pension field mapping mismatch (employer_name vs scheme_name)
- [ ] Expenditure form fill doesn't animate through form
- [ ] property_sale life event: Grok creates property record (double navigation)
- [ ] WARN-002: Security sessions API returns 500 on /api/auth/sessions
- [ ] WARN-003: Vue error on holistic-plan page

### Tech Debt (Carried Forward)
- [ ] OnboardingWizard.vue: Vue warn about failed component resolution
- [ ] LiabilitiesStep.vue: DEPRECATED comment
- [ ] IncomeStatementTab.vue is orphaned (never imported)

## Deploy Status

### Built (not yet uploaded)
- public/build/ — 6.7MB, 283 precache entries (brett-v1 merge)
- Deploy guide: March/March28Updates/brettMergeDeploy.md
- Frontend-only deploy — no PHP, migration, or seeder changes

## Context for Next Session

PR #169 merged, build complete, not yet uploaded to production. Full code review completed with 43 issues documented in March/March28Updates/codeReview-28March2026.md. The most urgent fixes are the 4 critical security issues (NI number exposure, MFA middleware, mass assignment, admin seeder). The build output is ready to upload via SiteGround File Manager. Next session should either deploy the build first, or start fixing code review issues (in which case, rebuild after fixes).

## Files to Review
- March/March28Updates/codeReview-28March2026.md — Full 43-issue code review report
- March/March28Updates/brettMergeDeploy.md — Deploy guide for brett-v1 merge

---

## Session 17 (29 March) — Content branch merge (PR #170)

### Completed (content branch)
- [x] Full redesign of all 5 personal journey pages (Starting Out, Building Foundations, Protecting and Growing, Planning Your Future, Enjoying Your Wealth)
- [x] Consistent template: horizon-to-raspberry gradient hero, journey name as h1, old title as bold subtitle
- [x] Green spring CTA buttons with "View demo" link (direct persona launch, no modal)
- [x] Dark horizon gradient "What Fynla shows you" section with coloured icon cards
- [x] Light pink features section with 3px hover borders
- [x] Horizon blue moments/small steps cards on eggshell background
- [x] "See it in action" section: large persona name left, CTA right-aligned
- [x] Renamed "Your life stage" to "Your personal journey" in nav mega menu and landing page
- [x] Renamed "Calculators" to "Free calculators" in resources mega menu
- [x] New features comparison page with competitor harvey ball table
- [x] Calculators page redesign: two-column sidebar layout
- [x] Preview banner: white exit button, raspberry sign up
- [x] Login/Register pages: light-blue rounded box with homepage and wishlist links
- [x] Pricing page: auth-aware CTAs
- [x] Mega menu: eggshell background on items

### Outstanding (from content branch)
- [ ] Browser test all 5 journey pages across breakpoints
- [ ] Browser test "View demo" link loads correct persona on each page
- [ ] Verify mockup HTML file `public/mockup-starting-out.html` is removed before deploy
- [ ] Test demo entry/exit — verify return to referrer page
