# CSJTODO — Fynla

*Last updated: 3 April 2026 — session 32*
*Previous session: 2 April 2026 session 31*

---

## Session 32 (3 April) — Cache Fix + Onboarding Bug Fix + UX Improvements + Adhoc Homepage Changes

### Completed This Session (main branch — PRs merged)
- [x] **FynChat branch merged** — PR #183, Fyn chat panel floats above dashboard
- [x] **Cache fix (PR #184)** — Created centralised `CacheInvalidationService`, removed all `Cache::tags()` calls (silently failing on production file driver), extended all TTLs to 24 hours with immediate invalidation on data change. 34 PHP files changed. Deploy guide: `April/April3Updates/deployCacheFix.md`
- [x] **Onboarding expenditure 500 fix (PR #185)** — `ExpenditureForm.vue` was sending `expenditure_entry_mode: 'detailed'` but DB enum is `('simple','category')`. Fixed to `'category'`.
- [x] **Onboarding UX improvements (PR #185)** — Step reorder (assets/debts before income/spending), Continue cycles through asset tabs, Family "Did you know" updated with IHT spouse transfer info, DC pension: hidden Expected Return during onboarding + renamed Planned Access Age to Retirement Age, info icons on prefilled fields, scroll-to-top on all form opens and tab transitions
- [x] **Browser tested** — Full onboarding flow, all asset forms (pension, property, investment, savings), scroll-to-top verified with Playwright scrollY measurements, 0 console errors
- [x] **Production build** — `public/build/` ready for both cache fix and onboarding changes

### Completed This Session (adhoc-changes-1 branch)
- [x] **Meet Fyn section redesign** — Updated copy, added expandable "What can Fyn help you with?" section with bullet points, replaced Ask Fyn input with "Quick start with Fyn" CTA
- [x] **Fyn registration flow** — "Quick start with Fyn" links to /register?from=fyn, after registration redirects to dashboard with Fyn chat open asking journey stage question
- [x] **Homepage scroll fix** — Smooth scroll to Meet Fyn and dashboard sections now offsets by navbar height
- [x] **Tax hook path fix** — Updated .claude/settings.json hook path for Windows

### NOT Done — Outstanding
- [ ] **Browser test full Fyn registration flow** — Quick start with Fyn → register → dashboard with Fyn chat open
- [ ] **Browser test onboarding flow** — All stages, data persistence, journey switching

### Deploy Status
- **Cache fix (PR #184):** 34 PHP files + no frontend build. Deploy guide: `April/April3Updates/deployCacheFix.md`. Needs `mkdir -p app/Services/Cache` on server.
- **Onboarding fix + UX (PR #185):** 9 frontend files, build required (done). Deploy guide: `April/April3Updates/deployOnboarding.md`. No PHP changes.
- **adhoc-changes-1:** 3 commits — NOT merged, NOT deployed. Needs PR + merge to main.

### Context for Next Session
Cache fix + onboarding PRs merged to main but NOT deployed to production yet. adhoc-changes-1 branch needs PR + merge.
**Tax year deadline: April 6 (3 days away).**

---

## Outstanding from Previous Sessions
- [ ] Delete mockup HTML files from public/
- [ ] Submit updated sitemap.xml to Google Search Console
- [ ] Test contact form email delivery on production
- [ ] Recurring billing / auto-renewal (Revolut)
- [ ] Test Excel import with real platform exports (Hargreaves Lansdown, AJ Bell, Vanguard)
- [ ] Test Fyn timeout fix on production (10+ message conversation)
- [ ] Deploy Excel holdings import to production (12 PHP files + build)
- [ ] Bug 1: retirement layout at 1118px still needs work

## Outstanding — Tech Debt Deferred
- [ ] God class decomposition (6 files, 40-60 hours)
- [ ] Float-to-decimal migration (blocked)
- [ ] Test coverage 19% → 40%
- [ ] NPM vulnerabilities (9 high CVEs)
- [ ] Off-palette Tailwind in Risk module

## Known Issues
- [ ] Tax year deadline April 6 — dynamic tax year overhaul deployed via bugs branch merge
- [ ] DB pension field mapping mismatch (employer_name vs scheme_name)
