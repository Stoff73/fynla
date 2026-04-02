# CSJTODO — Fynla

*Last updated: 2 April 2026 — session 30*
*Previous session: 2 April 2026 session 29*

---

## Session 30 (2 April) — Bug Report Audit + Dynamic Tax Year Overhaul

### Completed This Session
- [x] **Full bug audit of 3 PDF reports** — BugReportApril1.pdf (5 bugs), bugsApril2One.pdf (6 items), bugsApril2Two.pdf (8 bugs). 9 bugs investigated, 7 fixed, 2 already fixed.
- [x] **Bug 1: Retirement responsive layout** — "Other Assets" box cut off at 1118px. Fixed with min-width:0 on grid children + 1024px breakpoint.
- [x] **Bug 2: Estate planning internal error** — AssetLiquidityAnalyzer TypeError on production. Fixed type hint from Asset to object, added null coalescing.
- [x] **Bug 3: Projected Net Income not clickable** — Added visible "View income breakdown" CTA link on planner card.
- [x] **Bug 4: VCT form too complicated + save error** — Hid contributions section for VCT/EIS, show field-level validation errors.
- [x] **Bug 5: Journey selection not persisted** — Stage now saved immediately on card selection, not just on "Start My Journey".
- [x] **Bug 6: Pension retirement age defaults to 67** — DC pension form always re-fetches profile for latest retirement age.
- [x] **Bug 8: Property wizard exits on Tenants in Common "Other"** — Guard in handleSubmit prevents accidental form submit on intermediate steps.
- [x] **Dynamic tax year overhaul** — Eliminated ALL hardcoded "2025/26" and tax values across 39 frontend files + 2 backend files. Created getCurrentTaxYear() utility. Replaced hardcoded ISA/pension/CGT values with taxConfig.js imports. Also fixed stale CGT allowance (£12,300 → £3,000 in RebalancingCalculator).
- [x] **Stop hook: tax-hardcode-check.sh** — Runs after every session, greps for hardcoded tax values in changed files.
- [x] **Memory rule: feedback_never_hardcode_tax_values.md** — Permanent enforcement rule.
- [x] **Design guide v1.3.0** — Dynamic Financial Values, Number Input Scroll Prevention, Clickable Card CTA, Grid Overflow Prevention, Sidebar-Aware Breakpoints.
- [x] **Session-start skill updated** — Expert Laravel/PHP/Vue developer identity.
- [x] **Browser tested** — All 8 previously-fixed bugs verified in Playwright + new fixes verified.
- [x] **Production build** — `./deploy/fynla-org/build.sh` completed (6.9M).

### Deploy Status
- **Bugs branch (NOT merged to main):** 9 bug fixes + dynamic tax year overhaul (42 files) + design guide v1.3.0
- **Deploy guide:** `April/April2Updates/bugIssueDeploy.md`
- **Files to upload:** `public/build/` + 3 PHP files (EstateController, AssetLiquidityAnalyzer, OnboardingService)
- **From earlier today (already deployed):** Fyn timeout fix, onboarding redesign, SEO, 8 bug fixes (part 2)

### Context for Next Session
The `bugs` branch has all session 29 + 30 work. Needs PR + merge to main, then deploy using `bugIssueDeploy.md`. The dynamic tax year change is critical — the UK tax year changes to 2026/27 on April 6 (4 days away). Without this deploy, all tax year references on the site will show stale "2025/26" after April 5. Also: the Excel holdings import (PR #181 on main) still hasn't been deployed to production.

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
- [ ] Estate IHT Age 80 projections show unrealistic numbers (£195M)
- [ ] DB pension field mapping mismatch
- [ ] Expenditure form fill doesn't animate
- [ ] property_sale life event creates property record (double navigation)
