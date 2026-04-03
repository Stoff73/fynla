# CSJTODO — Fynla

*Last updated: 3 April 2026 — session 34*
*Previous session: 3 April 2026 session 33*

---

## Session 34 (3 April) — Fyn Chat, Plan Selection, Sign-Out Fixes

### Completed This Session (adhoc-changes-1 branch — PR #188)
- [x] **Fyn chat panel wider** — Docked 285→356px, floating 420→525px (25% increase)
- [x] **Fyn chat content no longer shifts** — Removed dynamic right margin, chat overlays content
- [x] **Fyn chat shadow refined** — Left/top/bottom shadows balanced for consistent appearance across resolutions
- [x] **Fyn chat docked duplicate onOpen fix** — Removed duplicate `onOpen()` call in mounted (watcher already handles it)
- [x] **Journey stage options in chat** — `?openFyn=journey` now shows clickable journey stage buttons in chat (light-pink bg), fixed race condition where `startNewConversation` wiped the message
- [x] **Settings — Account Status** — Shows current plan with trial days remaining, "Choose a Plan" button (hidden for Pro users)
- [x] **Plan Selection Modal redesign** — Backdrop blur, "Limited Time Offer" banner, billing toggle matching /pricing page, per-card "Choose Plan" buttons (raspberry), current plan highlighted with blue badge and grey disabled button, aligned card buttons, discounted prices and save % on separate lines
- [x] **Sign-out behaviour** — Public pages stay on current page, authenticated pages redirect to /login (Navbar + SideMenu)
- [x] **Production build** — `public/build/` ready

### NOT Done — Outstanding
- [ ] **Browser test full Fyn registration flow** — Quick start with Fyn → register → dashboard with Fyn chat open
- [ ] **Browser test onboarding flow** — All stages, data persistence, journey switching
- [ ] **Browser test plan selection modal** — Open from Settings, verify current plan highlighted, choose plan flow

### Deploy Status
- **adhoc-changes-1 (PR #188):** Frontend only (10 Vue/JS files). Build done. Upload `public/build/` to production. No PHP changes.
- **Previous session deploys:** Check if cache fix (PR #184) and onboarding (PR #185) were uploaded to production.

### Context for Next Session
PR #188 open on `adhoc-changes-1` — needs merge to main then deploy `public/build/`. All changes are frontend only. 2 pending migrations on main (`create_ai_advice_log_table`, `add_system_prompt_to_ai_messages_table`) — not from this branch.

**Tax year deadline: April 6 (3 days away).** 2025/26 is the active tax year in the database.

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
