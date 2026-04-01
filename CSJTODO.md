# CSJTODO — Fynla

*Last updated: 1 April 2026 — session 25*
*Previous session: 1 April 2026 session 24*

---

## Session 25 (1 April) — Fyn AI Phase 2 Implementation

### Completed This Session
- [x] **Fyn AI Phase 2 implemented** — 6 phases, 5 commits, 16 new files, 49 tests (112 assertions)
- [x] Phase 1: System prompt refactored into 10-layer `SystemPromptBuilder` (extracted from 670-line monolith in HasAiChat)
- [x] Phase 2: `QueryClassifier` (22 query types, multi-label, keyword + route fallback) + `KycGateChecker` (data completeness with mandatory navigation routes)
- [x] Phase 3: Query-aware knowledge RAG — pension query saves ~1,328 tokens, data entry saves ~3,109 tokens
- [x] Phase 4: Mandatory tool sequences injected per query type (`<required_tools>` block)
- [x] Phase 5: Decision tree binding — recommendations include description, £ amounts, action steps, triggers
- [x] Phase 6: Review system — `ai_advice_logs` table, `AdviceReviewService` (data change detection, annual review prompts), KYC mandatory navigation fix
- [x] Full regression: 2,139 passed, 9 pre-existing failures (unrelated to this work)
- [x] Browser tested: KYC blocking, data entry bypass, navigation, pension advice, IHT query, mandatory navigation to expenditure page

### Key Files Created
- `app/Services/AI/SystemPromptBuilder.php` — 10-layer prompt assembly
- `app/Services/AI/QueryClassifier.php` — multi-label query classification
- `app/Services/AI/KycGateChecker.php` — KYC data completeness with mandatory routes
- `app/Services/AI/Prompts/` — CoreIdentity, ComplianceRules, FcaProcessInstructions, QueryKnowledge
- `app/Services/AI/AdviceReviewService.php` — data change + annual review detection
- `app/Constants/QuerySchemas.php` — all query types, tools, triggers, knowledge domains
- `app/Models/AiAdviceLog.php` + migration

### Plan & Task Docs
- Plan: `April/April1Updates/fynUpgrade2.md` (marked IMPLEMENTED)
- Tasks: `April/April1Updates/fyn2Tasks.md` (all checkboxes marked)

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

All work deployed and production tested at v0.9.4. Next priorities: recurring billing (Revolut auto-renewal), tech debt (god classes), contact form mail config on production, sitemap submission to Google Search Console, or whatever the user wants to work on.
