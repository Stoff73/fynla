# CSJTODO — Fynla

*Last updated: 4 April 2026 — session 35*
*Previous session: 3 April 2026 session 34*

---

## Session 35 (4 April) — Code Review, Merge & Deploy PR #189

### Completed This Session
- [x] **Code reviewed PR #189** — "feat: dashboard UX, about page, editorial policy, insight articles" (22 files, +967/-97)
  - 5 parallel reviewer agents (CLAUDE.md, bugs, git history, past PRs, code comments)
  - 2 high-confidence issues flagged (score ≥ 80): tax-hook Windows path reversion, acronyms (IHT/NRB/RNRB/PET) in insight page
  - Comment posted: https://github.com/Stoff73/fynla/pull/189#issuecomment-4187675361
- [x] **Created `logic-change-approved` label** on repo
- [x] **Approved & admin-merged PR #189** into main (commit c84dfaf)
- [x] **Built production assets** (`./deploy/fynla-org/build.sh`) — 7.0M, 297 PWA entries
- [x] **Deployed to production** via rsync + SSH cache clear (fynla.org live)
- [x] **Committed April4Updates session notes** (nothingWorks.md)

### NOT Done — Outstanding
- [ ] **Browser test PR #189 changes on production** — Empty dashboard CTA, journey blur overlay, mobile chat button, About page, Editorial Policy page, 2 new insight articles
- [ ] **Add `.claude/settings.json` to .gitignore** — tax-hook path keeps reverting between Mac/Windows dev machines (run `git rm --cached .claude/settings.json` after)
- [ ] **Address medium-priority PR #189 issues** (score 75, filtered out but worth tracking):
  - `lg:mr-10` collapsed-chat margin removed from AppLayout.vue (reverts commit 4df53fc same-day fix)
  - `fyn-chat-interaction` event fires on chat collapse → clears journey blur prematurely
  - Hardcoded tax thresholds (£325k NRB, £175k RNRB, £60k AA, "2026/27" string) in new insight articles — should use taxConfig.js pattern

### Deploy Status
- **PR #189:** Deployed to fynla.org (frontend only, rsync + cache clear complete)
- **Previous deploys:** PR #188 frontend deployed session 34; check PR #184 (cache fix) + PR #185 (onboarding) uploaded to production

### Context for Next Session
PR #189 is merged and live on fynla.org. Next session should browser-test the new dashboard empty state, About page, Editorial Policy, and 2 new insight articles (InheritanceTaxExplained, PensionContributionLimits). Three deferred issues from the code review (margin regression, chat-interaction event, hardcoded tax values) should be addressed in a follow-up PR. The `.claude/settings.json` gitignore fix is a quick win to prevent hook path reverts.

**Tax year deadline: April 6 (2 days away).** 2025/26 is the active tax year in the database.

---

## Outstanding from Previous Sessions
- [ ] Delete mockup HTML files from public/
- [ ] Submit updated sitemap.xml to Google Search Console
- [ ] Test contact form email delivery on production
- [ ] Recurring billing / auto-renewal (Revolut)
- [ ] Test Excel import with real platform exports (Hargreaves Lansdown, AJ Bell, Vanguard)
- [ ] Test Fyn timeout fix on production (10+ message conversation)
- [ ] Deploy Excel holdings import to production (12 PHP files + build)
- [ ] Browser test full Fyn registration flow — Quick start with Fyn → register → dashboard with Fyn chat open
- [ ] Browser test onboarding flow — All stages, data persistence, journey switching
- [ ] Browser test plan selection modal — Open from Settings, verify current plan highlighted, choose plan flow

## Outstanding — Tech Debt Deferred
- [ ] God class decomposition (6 files, 40-60 hours)
- [ ] Float-to-decimal migration (blocked)
- [ ] Test coverage 19% → 40%
- [ ] NPM vulnerabilities (9 high CVEs)
- [ ] Off-palette Tailwind in Risk module
- [ ] Vuex state bloat
- [ ] Clean up dead methods in IHTCalculationService (projectCashAndInvestmentsIntegrated, getMonteCarloAnnualRate, getInvestmentAccountsArray — no longer called)
- [ ] Slate-*/emerald-* tokens in AboutPage.vue (approved for public pages per design guide v1.3.0, but CLAUDE.md Rule 12 references v1.2.0 — reconcile)

## Known Issues
- [ ] Bug 1: Retirement "Other Assets" cards overflow at 1118px (needs CSS refinement beyond min-width:0)
- [ ] DB pension field mapping mismatch
- [ ] Expenditure form fill doesn't animate
- [ ] property_sale life event creates property record (double navigation)
