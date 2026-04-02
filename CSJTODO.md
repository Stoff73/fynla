# CSJTODO — Fynla

*Last updated: 2 April 2026 — session 29*
*Previous session: 2 April 2026 session 28*

---

## Session 29 (2 April) — Excel Import, Onboarding Merge, Bug Fixes Part 2

### Completed This Session
- [x] **Fyn AI connection drop fix** — 20s PHP socket timeout causing xAI stream death. XaiClient now has 120s Guzzle timeout. Frontend detects empty responses. IHT Carbon→string fix. PR #179 merged.
- [x] **Excel holdings import** — full feature: ExcelParserService, AIExtractionService.extractSheet(), HoldingsImportService, 4 new field mappers (Property, Protection, Savings, Mortgage), SheetReviewStep + HoldingsReviewTable Vue components. Browser tested with 3-sheet workbook. PR #181 merged.
- [x] **Onboarding redesign merge** — PR #180 merged with conflict resolution (3 conflicts: AssetsStep, IncomeStep, CSJTODO). All features preserved including spouse step.
- [x] **8 bugs fixed (part 2)** — modal Teleport, institution tooltip, Premium Bonds £50k validation, mortgage joint ownership on liabilities, mortgage card clickable, dashboard pie chart clickable, investment-detail redirect. All browser tested.
- [x] **Session-end skill updated** — dynamic month paths (no more hardcoded March)
- [x] **Vault synced** — 6 files to fynlaBrain, git history updated (36 commits)
- [x] **CLAUDE.md metrics updated** — Vue 651, Services 229

### Deploy Status
- **Bugs branch (NOT merged to main):** 8 bug fixes + session-end skill + CLAUDE.md metrics
- **Files to upload when merged:** `public/build/` + `app/Http/Controllers/Api/EstateController.php`
- **From earlier today (already deployed):** Fyn timeout fix (XaiClient, IHT), onboarding redesign, SEO

### Context for Next Session
The `bugs` branch has 4 commits with 8 bug fixes (all browser tested). Needs PR + merge to main, then build + deploy. The Excel holdings import (PR #181) is already on main but hasn't been deployed to production — needs `public/build/` + 12 PHP files + route cleared. The onboarding redesign (PR #180) frontend is deployed but some PHP files from the onboarding branch may not be on production yet.

---

## Outstanding from Previous Sessions
- [ ] Delete mockup HTML files from public/
- [ ] Submit updated sitemap.xml to Google Search Console
- [ ] Test contact form email delivery on production
- [ ] Recurring billing / auto-renewal (Revolut)
- [ ] Test Excel import with real platform exports (Hargreaves Lansdown, AJ Bell, Vanguard)
- [ ] Test Fyn timeout fix on production (10+ message conversation)

## Outstanding — Tech Debt Deferred
- [ ] God class decomposition (6 files, 40-60 hours)
- [ ] Float-to-decimal migration (blocked)
- [ ] Test coverage 19% → 40%
- [ ] NPM vulnerabilities (9 high CVEs)
- [ ] Off-palette Tailwind in Risk module
- [ ] Vuex state bloat

## Known Issues
- [ ] Estate IHT Age 80 projections show unrealistic numbers (£195M)
- [ ] DB pension field mapping mismatch
- [ ] Expenditure form fill doesn't animate
- [ ] property_sale life event creates property record (double navigation)
