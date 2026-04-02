# CSJTODO — Fynla

*Last updated: 2 April 2026 — session 27*
*Previous session: 1 April 2026 session 26*

---

## Session 27 (2 April) — Chris User Seeder + Fyn Pension Prompt Improvement

### Completed This Session
- [x] **ChrisUserSeeder created** — seeds chris@fynla.org locally with all production-matching data
  - User profile, 2 properties + mortgages, savings, investment account + 2 holdings, DC pension, business interest, chattel, 2 goals, life event, family member, risk profile, ISA tracking, subscription
- [x] **Registered in DatabaseSeeder** — runs automatically with `php artisan db:seed`
- [x] **Field-by-field verified** against production (properties city/postcode, goal is_first_time_buyer, subscription trial_ends_at all corrected)
- [x] **Fyn pension prompt improved** — Personal Allowance reclaim now covers incomes above £125,140 (not just £100k-£125k). Includes worked example (£145k income, £45k contribution, £12,570 PA restored, £2,514 additional saving)

### Files Changed
- `database/seeders/ChrisUserSeeder.php` (NEW)
- `database/seeders/DatabaseSeeder.php` (added ChrisUserSeeder)
- `app/Constants/FinancialPlanningKnowledge.php` (pension PA reclaim prompt)

### Deploy Status
- **UI branch**: Commit `2212ba5`. No production deploy needed (seeder is local-only, prompt change needs build + upload)
- **Pending from session 26**: Card overflow fix `public/build/` still needs uploading to production

---

## Session 26 (1 April) — Fyn AI Field Fixes + Card Overflow Fix

### Completed This Session
- [x] **19 wrong field names fixed in Fyn AI context** — mortgages, DC pensions, business interests, chattels, life insurance all reading £0/blank
- [x] Root cause: SystemPromptBuilder, HasAiChat, CoordinatingAgent used non-existent model attributes
- [x] Field alias mapping added to `handleUpdateRecord()`
- [x] **Deployed to production** (3 PHP files via SSH sed) and **production tested**
- [x] **Card text overflow fix** (UI branch) — global `overflow-hidden` + `break-word` on all card variants

---

## Outstanding from Previous Sessions
- [ ] Upload `public/build/` to production (card overflow fix from session 26)
- [ ] Merge UI → main after deploying card overflow fix
- [ ] Delete mockup HTML files from public/ (insights, learn, journey, persona, mobile)
- [ ] Submit updated sitemap.xml to Google Search Console
- [ ] Test contact form email delivery on production (requires mail config)
- [ ] Recurring billing / auto-renewal (currently one-time Revolut orders only)

---

## Outstanding — Tech Debt Deferred

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

## Context for Next Session

ChrisUserSeeder is complete and verified field-by-field against production. Fyn's pension prompt now correctly handles PA reclaim for high earners above £125,140. Card overflow fix from session 26 still needs `public/build/` uploaded to production, then UI branch should merge to main.
