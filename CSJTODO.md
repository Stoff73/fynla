# CSJTODO — Fynla

*Last updated: 1 April 2026 — session 26*
*Previous session: 1 April 2026 session 25*

---

## Session 26 (1 April) — Fyn AI Field Fixes + Card Overflow Fix

### Completed This Session
- [x] **19 wrong field names fixed in Fyn AI context** — mortgages, DC pensions, business interests, chattels, life insurance all reading £0/blank
- [x] Root cause: SystemPromptBuilder, HasAiChat, CoordinatingAgent used non-existent model attributes (e.g. `current_balance` instead of `outstanding_balance` on Mortgage)
- [x] Field alias mapping added to `handleUpdateRecord()` so Fyn updates don't silently drop fields
- [x] Full audit of every model field reference across all AI files — verified correct against DB columns
- [x] **Deployed to production** (3 PHP files via SSH sed) and **production tested**:
  - Mortgage: Fyn now shows £200,000 mortgage and £150,000 equity (was "no mortgage recorded")
  - Rental income: Fyn shows £900/mo with 50% joint share correctly
  - Joint vs individual: Fyn distinguishes ownership shares per property
  - Pension contributions: Fyn identifies employment income as relevant UK earnings
  - Acronyms: All spelled out (Annual Exempt Amount, not AEA)
  - IDs: No record IDs leaked in any response
- [x] **Card text overflow fix** (UI branch) — global `overflow-hidden` + `break-word` on all card variants, DecisionTreeDiagram and DecisionTraceTimeline fixed

### Known Issues (from fynTest.pdf — ALL RESOLVED)
- [x] Fyn showing record IDs — FIXED (session 25, StructuredResponseValidator)
- [x] Fyn using acronyms (AEA) — FIXED (session 25, banned acronym list)
- [x] Fyn leaking [Context:] blocks — FIXED (session 25, sanitiser)
- [x] Fyn not picking up rental income — FIXED (session 26, `monthly_rental_income` was working, data didn't exist on chris account — confirmed working after adding BTL via Fyn)
- [x] Fyn not distinguishing joint vs individual property ownership — FIXED (session 26, ownership percentage now in context)
- [x] Fyn giving incorrect pension contribution recs — FIXED (session 26, employment income correctly identified as relevant UK earnings)
- [x] Fyn thinks user doesn't have mortgages — FIXED (session 26, `outstanding_balance` field fix)

### Deploy Status
- **main branch**: Fyn field fixes deployed to production (3 PHP files via sed)
- **UI branch**: Card overflow fix — built locally, needs `public/build/` uploaded to production
- Deploy notes: `April/April1Updates/fynFieldFixes.md`

### Context for Next Session
All fynTest.pdf issues resolved. UI branch has card overflow fix ready to deploy (build done, upload `public/build/`). The UI branch should be merged to main after deploying. Version is still v0.9.4 — version bump to v0.9.5 not yet done in Footer/Version.vue.

---

## Session 25 (1 April) — Fyn AI Phase 2 + AI Audit Dashboard + Deploy

### Completed This Session
- [x] **Fyn AI Phase 2 implemented** — 6 phases, 16 new files, 71 tests (145 assertions)
- [x] Phase 1: System prompt refactored into 10-layer `SystemPromptBuilder`
- [x] Phase 2: `QueryClassifier` (22 query types) + `KycGateChecker` (mandatory navigation routes)
- [x] Phase 3: Knowledge RAG — ~3,109 tokens saved on data entry queries
- [x] Phase 4: Mandatory tool sequences per query type
- [x] Phase 5: Decision tree binding — recommendations with £ amounts + triggers
- [x] Phase 6: Review system — advice logging, data change detection, annual reviews
- [x] **StructuredResponseValidator** — strips IDs, context blocks, HTML; flags acronyms, jargon, missing £ amounts
- [x] **AI Audit Dashboard** — admin 3-panel view (users → conversations → messages with expandable system prompt)
- [x] Admin tab grouping — Users and AI dropdown menus
- [x] **Merged to main**, pushed, built, deployed to production
- [x] Production verified: routes registered, migrations run, caches cleared
- [x] Production tested: Fyn responded with no leaked IDs, no acronyms, no [Context:] blocks
- [x] UserMetricsServiceTest date edge case fixed

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

### main branch — Fyn field fixes DEPLOYED (v0.9.4)
- 3 PHP files deployed via SSH sed to production
- All fynTest.pdf issues verified fixed on production

### UI branch — Card overflow fix PENDING DEPLOY
- Build done locally, `public/build/` needs uploading
- 3 frontend files changed (app.css, DecisionTreeDiagram.vue, DecisionTraceTimeline.vue)

## Context for Next Session

All fynTest.pdf issues resolved and production tested. UI branch card overflow fix built and ready to upload. Merge UI → main after deploying. Version bump to v0.9.5 still pending.
