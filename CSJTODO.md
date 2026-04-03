# CSJTODO — Fynla

*Last updated: 3 April 2026 — session 32*
*Previous session: 2 April 2026 session 31*

---

## Session 32 (3 April) — Cache Fix + Onboarding Bug Fix + UX Improvements

### Completed This Session
- [x] **FynChat branch merged** — PR #183, Fyn chat panel floats above dashboard
- [x] **Cache fix (PR #184)** — Created centralised `CacheInvalidationService`, removed all `Cache::tags()` calls (silently failing on production file driver), extended all TTLs to 24 hours with immediate invalidation on data change. 34 PHP files changed. Deploy guide: `April/April3Updates/deployCacheFix.md`
- [x] **Onboarding expenditure 500 fix (PR #185)** — `ExpenditureForm.vue` was sending `expenditure_entry_mode: 'detailed'` but DB enum is `('simple','category')`. Fixed to `'category'`.
- [x] **Onboarding UX improvements (PR #185)** — Step reorder (assets/debts before income/spending), Continue cycles through asset tabs, Family "Did you know" updated with IHT spouse transfer info, DC pension: hidden Expected Return during onboarding + renamed Planned Access Age to Retirement Age, info icons on prefilled fields, scroll-to-top on all form opens and tab transitions
- [x] **Browser tested** — Full onboarding flow, all asset forms (pension, property, investment, savings), scroll-to-top verified with Playwright scrollY measurements, 0 console errors
- [x] **Production build** — `public/build/` ready for both cache fix and onboarding changes

### Deploy Status
- **Cache fix (PR #184):** 34 PHP files + no frontend build. Deploy guide: `April/April3Updates/deployCacheFix.md`. Needs `mkdir -p app/Services/Cache` on server.
- **Onboarding fix + UX (PR #185):** 9 frontend files, build required (done). Deploy guide: `April/April3Updates/deployOnboarding.md`. No PHP changes.
- **Both builds done** — `public/build/` contains both sets of changes. Single upload covers everything.

### Context for Next Session
Both PRs are merged to main but NOT deployed to production yet. To deploy:
1. Upload the 34 PHP files from cache fix (see `deployCacheFix.md`)
2. Upload `public/build/` (covers both cache fix and onboarding changes)
3. SSH: `mkdir -p app/Services/Cache` then clear caches

**Tax year deadline: April 6 (3 days away).** The bugs branch (PR #182) was already merged — check if it has been deployed. If not, deploy guide at `April/April2Updates/bugIssueDeploy.md`.

---

## Outstanding from Previous Sessions
- [ ] Delete mockup HTML files from public/
- [ ] Submit updated sitemap.xml to Google Search Console
- [ ] Test contact form email delivery on production
- [ ] Recurring billing / auto-renewal (Revolut)
- [ ] Test Excel import with real platform exports (Hargreaves Lansdown, AJ Bell, Vanguard)
- [ ] Test Fyn timeout fix on production (10+ message conversation)
- [ ] Deploy Excel holdings import to production (12 PHP files + build)

## Outstanding — Tech Debt Deferred
- [ ] God class decomposition (6 files, 40-60 hours)
- [ ] Float-to-decimal migration (blocked)
- [ ] Test coverage 19% → 40%
- [ ] NPM vulnerabilities (9 high CVEs)
- [ ] Off-palette Tailwind in Risk module
- [ ] Vuex state bloat

## Known Issues
- [ ] Bug 1: Retirement "Other Assets" cards overflow at 1118px (needs CSS refinement beyond min-width:0)
- [ ] Estate IHT Age 80 projections show unrealistic numbers (£195M)
- [ ] DB pension field mapping mismatch
- [ ] Expenditure form fill doesn't animate
- [ ] property_sale life event creates property record (double navigation)
