# CSJTODO — Fynla

*Last updated: 2 April 2026 — session 31*
*Previous session: 2 April 2026 session 30*

---

## Session 31 (2 April) — Production Bug Testing (Incomplete)

### Completed This Session
- [x] **ChrisUserSeeder fix** — Added missing `annual_income` (55000) and `monthly_expenditure` (2500) to ProtectionProfile in ChrisUserSeeder. `db:seed` was failing.
- [x] **Database seeded** — All seeders running clean.

### NOT Done — Attempted but Incomplete
- [ ] **Production testing of bug fix report** — Started testing `bugFixReport.md` bugs on fynla.org. Tested Bug 1 (retirement layout at 1118px) — confirmed the bug is STILL PRESENT on production because the `bugs` branch has NOT been merged/deployed. The 1024px breakpoint fix is deployed but the `min-width: 0` fix is not fully effective at 1118px. Session ended before remaining bugs could be tested.

### Context for Next Session
**CRITICAL — DEPLOY THE BUGS BRANCH BEFORE TESTING:**
The `bugs` branch has 10 commits ahead of main with 9 bug fixes + dynamic tax year overhaul. These fixes are NOT on production yet. Before testing on production, the branch must be:
1. PR + merge to main
2. Build with `./deploy/fynla-org/build.sh`
3. Deploy using `April/April2Updates/bugIssueDeploy.md`
4. THEN test on production

**Bug 1 (retirement layout at 1118px)** — The "Other Assets" cards still overflow at 1118px viewport even with the current fix. The `min-width: 0` helps but the "Joint General Investment Account" card text is cut off at the right edge. May need additional CSS work (e.g., `overflow: hidden` or `text-overflow: ellipsis` on card text, or widening the 1024px breakpoint to 1200px).

**Tax year deadline: April 6 (4 days away).** The dynamic tax year overhaul on the bugs branch must be deployed before then or all tax year references will show stale "2025/26".

### Deploy Status
- **Bugs branch (NOT merged to main):** 10 commits ahead — 9 bug fixes + dynamic tax year overhaul + ChrisUserSeeder fix
- **Deploy guide:** `April/April2Updates/bugIssueDeploy.md`
- **Files to upload:** `public/build/` + 3 PHP files + `database/seeders/ChrisUserSeeder.php`

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
