# CSJTODO — Fynla

*Last updated: 3 April 2026 — session 33*
*Previous session: 3 April 2026 session 32*

---

## Session 33 (3 April) — Logic Guard PR Check + Estate/Retirement Bug Fixes

### Completed This Session
- [x] **Logic Guard PR check (PR #186)** — GitHub Actions workflow that blocks PRs from Phailanx when they modify logic in protected dashboard/onboarding files. Posts detailed violation report. Override with `logic-change-approved` label. Deployed to main.
- [x] **Estate IHT projection fix (PR #187)** — Fixed unrealistic future value numbers (entrepreneur £250M → £7.2M, chris £36M → £4.3M). Root cause: `getMonteCarloAnnualRate()` reverse-engineered a contribution-inflated growth rate. Now uses Monte Carlo p20 directly + inflation-adjusted income/expenses for cash. Life events injected at specific ages. Cash can go negative.
- [x] **Retirement income consistency (PR #187)** — All three retirement views now show consistent gross income (£40,415) and target (£31,688) from single source (`RequiredCapitalCalculator`). Removed duplicate `getTargetRetirementIncome()` from `RetirementProjectionService`. Dashboard uses backend data instead of hardcoded 4% SWR.
- [x] **Retirement UI overhaul (PR #187)** — Income Planner and Capital Planner tabs: header card pattern matching pension detail view with summary metrics inside. Removed blue "Agentic AI" dev banner. Income sources moved behind chart toggle. Fund depletion changed to stacked bar chart. Cleaned up labels and removed redundant elements.
- [x] **Browser tested** — All views verified in Playwright (dashboard, retirement page, income tab, capital tab). 0 console errors from our changes.
- [x] **Production build** — `public/build/` ready. Both PRs merged and deployed.

### Deploy Status
- **Logic Guard (PR #186):** Deployed — `.github/workflows/logic-guard.yml` (no server upload needed, GitHub Actions only)
- **Estate + Retirement fixes (PR #187):** Deployed — 2 PHP files + 5 Vue files + build. Deploy guide: `April/April3Updates/deployPensionFix.md`
- **Previous session deploys (PRs #183-185):** Check if cache fix and onboarding changes were uploaded to production. Deploy guides at `deployCacheFix.md` and `deployOnboarding.md`.

### Context for Next Session
All PRs merged to main. Branch protection ruleset on main requires `logic-guard` status check. The user needs to ensure the ruleset targets `main` branch (Settings > Rules > Rulesets > target branches > include `main`).

**Tax year deadline: April 6 (3 days away).** 2025/26 is the active tax year in the database. The 2026/27 tax year begins April 6.

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
- [ ] Clean up dead methods in IHTCalculationService (projectCashAndInvestmentsIntegrated, getMonteCarloAnnualRate, getInvestmentAccountsArray — no longer called)

## Known Issues
- [ ] Bug 1: Retirement "Other Assets" cards overflow at 1118px (needs CSS refinement beyond min-width:0)
- [x] ~~Estate IHT Age 80 projections show unrealistic numbers (£195M)~~ — Fixed session 33
- [ ] DB pension field mapping mismatch
- [ ] Expenditure form fill doesn't animate
- [ ] property_sale life event creates property record (double navigation)
- [ ] Untracked files in repo root: `HasAiChatArchive.php`, `April/April3Updates/*.md` docs, `docs/superpowers/` specs/plans
