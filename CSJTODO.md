# CSJTODO — Fynla

*Last updated: 1 April 2026 — session 25*
*Previous session: 1 April 2026 session 24*

---

## Session 25 (1 April) — Auth & Onboarding UI Redesign

### Completed This Session
- [x] **Register page redesign** — blue-to-pink gradient bg, white-to-grey card, light-blue beta warning, horizon-blue labels/borders, mandatory asterisks, logo clickable, middle name moved to onboarding
- [x] **Login page redesign** — matching gradient bg, card style, light-blue beta warning, increased padding, relative links
- [x] **Onboarding stage selection** — gradient bg, homepage-style stage cards (horizon gradient, raspberry accent, "Start here" links), light-pink intro box
- [x] **Onboarding journey map (welcome)** — horizon-blue hero, centred steps info, full-opacity nodes, collapsible "What you'll need", pink back button, "Start My Journey" chevron
- [x] **Onboarding form pages** — 52px progress circles with horizon-blue active/complete, thick connecting line (blue for completed), sidebar inside form card with light-pink bg, dynamic "Why we ask this" aligned to active field via ? icons, "How this fits your journey" with read more/less, consistent pink Back + "Skip to dashboard" + Continue nav
- [x] **Global onboarding styles** — onb-input, onb-label, q-icon in app.css; OnboardingStep deep overrides for all step components
- [x] **Journey reset fix** — switching stages clears saved data and sidebar override
- [x] **4 HTML mockups created** — auth-redesign, onboarding-redesign, onboarding-welcome-redesign, onboarding-form-redesign

### NOT Done — Needs Further Work
- [ ] "Why we ask this" box Y-alignment fine-tuning — slightly off for some fields
- [ ] Browser testing of full onboarding flow end-to-end (all stages, all steps)
- [ ] Other step components (StudentLoan, Income, Spending, etc.) need individual ? icon WHY_FIELD_DATA maps for field-specific text
- [ ] Mobile testing of onboarding layout (sidebar stacks below, arrow hidden)
- [ ] Remove old mockup HTML files from public/mockups/ before deploy

### Branch
- `onboarding-and-squirrels` — 2 commits, NOT merged to main, NOT deployed
- 15 files changed vs main

---

## Session 24 (1 April) — PR #177 Merge, Deploy, Version Bump

### Completed This Session
- [x] **PR #177 merged** — resource pages redesign (131 files, 17 commits, 6 merge conflicts resolved)
- [x] Old comparison URL redirects added (fynla-vs-moneyhub, fynla-vs-voyant, fynla-vs-projectionlab)
- [x] CSP updated for Google Analytics (googletagmanager.com, *.google-analytics.com)
- [x] Built and deployed to production (291 assets + 5 PHP files + sitemap)
- [x] Production browser tested — 0 console errors across all pages
- [x] Version bumped v0.9.3.2 → v0.9.4 (Footer, Version.vue, CLAUDE.md)
- [x] Version page updated with patch notes for PRs #175-177
- [x] Deploy notes at April/April1Updates/deployNotes.md
- [x] Patch notes at fynlaBrain/April/April01Updates/patchNotes-PR177.md

### Outstanding from Previous Sessions
- [ ] Delete mockup HTML files from public/ (insights, learn, journey, persona, mobile)
- [ ] Submit updated sitemap.xml to Google Search Console
- [ ] Test contact form email delivery on production (requires mail config)
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

### All Through PR #177 — DEPLOYED & VERIFIED (v0.9.4)
- PR #175: Feature gating — deployed, production tested
- PR #176: Journey links + security fix — deployed, production tested
- PR #177: Resource pages redesign — merged 1 April, deployed, production tested (0 console errors)
- Version bumped to v0.9.4, patch notes on /version page
- Deploy notes: April/April1Updates/deployNotes.md

## Context for Next Session

Branch `onboarding-and-squirrels` has auth page and onboarding UI redesign — mockup-first workflow, 2 commits. NOT merged or deployed. Key remaining work: fine-tune "Why we ask this" Y-alignment, add field-specific WHY text to all step components beyond PersonalInfoStep, browser test full onboarding flow, mobile test. Production is at v0.9.4 with all PR #175-177 work deployed.
