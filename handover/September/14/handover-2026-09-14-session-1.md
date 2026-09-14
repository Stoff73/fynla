---
type: handover
mode: session-end
date: 2026-09-14
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-09-14, Session 1

## Where things stand

CSJ started a new programme today: map the whole application section by section, evidence only, in documents a non-technical outsider can follow. This session built the `app-map` skill, ran it three times (the whole-app overview, section 17 emails and notifications, section 01 auth and registration), produced a route-by-route dossier for the orphan API routes, and raised 22 mapping bugs without fixing any. All of it is committed on `dev` at `e50bff154`. Nothing was deployed. The next section per the index is 02, onboarding and the Save Tax campaign.

## Priorities for the next session

1. **BLOCKED ON CSJ — decisions on the mapping bugs.** The register at the top of `September/September14Updates/mappingBugs2026-09-14.md` lists which entries need a call. In priority order: MB-14 and MB-15 (the only Broken backend findings: five daily alert types never delivered, in-app notifications never read), MB-21 (wrong MFA code bounces the user; 422 versus client allowlist), MB-08 (household routes and persona list), MB-06 (seven lifecycle emails: planned or abandoned), MB-01 plus MB-09 (dead-code sweep versus allowlist, extending W-0540), MB-10 (mock-up routes), MB-11 (artisan command pruning). Ask at the start; do not start fixing unasked.
2. **Map section 02, onboarding and the Save Tax campaign** with `/app-map onboarding`. Follow the skill exactly: perimeter from the routes outward, read every file, reverse sweep, tests scoped to the section, Playwright with screenshots, Excalidraw for every flow diagram, MB entries for anything wrong, INDEX row. Load the `fyn-architecture` skill first because onboarding is Fyn's write state. The `onboarding` and `journeys` route groups are at `routes/api.php:276-301`; the AI onboarding start is `routes/api.php:1519-1521`.
3. **Then sections 03 onwards in index order** (`docs/app-map/INDEX.md`). Each is one skill run. Do not batch sections; each report is a deliverable.
4. **Fix the mapping bugs CSJ approves**, one MB per branch and PR to dev, verified on web and `/m`. The five auth ones (MB-18 to MB-22) need no decision and are small; MB-18 is the most user-facing (two-factor users cannot sign in on `/m`).
5. **Carried, unchanged:** the iOS items and everything else in `CSJTODO.md` (build 10 on-screen checks, the six `deferred-ios` board items).

## Context to load

- `.claude/skills/app-map/SKILL.md` and `report-template.md` — the process and the report shape every section must follow; the next run is the fourth use, so tighten the skill where the template did not fit.
- `docs/app-map/INDEX.md` — the section manifest with status per row; update the row you complete.
- `docs/app-map/00-overview.md` — the baseline every section links back to; § 2 has the route counts, layers and surface table; Appendices A and E list the dead files.
- `September/September14Updates/mappingBugs2026-09-14.md` — MB-01 to MB-22 with the decisions register; append, never renumber.
- `docs/app-map/01-auth-registration-sessions.md` — the most recent full section map; copy its depth and its evidence style.
- `docs/app-map/reports/mb-08-orphan-routes.md` — the dossier format for a "full report on one bug", if CSJ asks for another.

## Completed this session

- `app-map` skill written (`.claude/skills/app-map/`), registered in the CLAUDE.md skills table, memory note `project_app_map_programme.md` added.
- `docs/app-map/00-overview.md`: 805 routes, 147 controllers, 542 services, 150 models, 170 tables, 67 commands (36 scheduled), three clients, tests and surfaces; three Excalidraw diagrams; seven screenshots; five appendices.
- `docs/app-map/17-emails-notifications.md`: 38 mailables, 9 notifications, lifecycle engine, alert commands, push, preference screens on web and `/m` (driven, writes confirmed); three diagrams.
- `docs/app-map/01-auth-registration-sessions.md`: registration, login (email code, MFA, recovery code), password reset, sessions, roles, GDPR export and deletion, restoration, native sessions; four diagrams; fifteen screenshots; the whole lifecycle driven on a fresh account.
- `docs/app-map/reports/mb-08-orphan-routes.md`: 25 route rows, 22 delete, 1 live, 2 for decision (written by a research agent, checked and reconciled).
- 22 mapping bugs raised, none fixed. Two corrections to my own findings recorded (`POST api/net-worth/refresh` is live; the 56 Vue public pages are guard-blocked, so PHP is canonical).
- Ten Excalidraw diagrams in `docs/diagrams/map-*.excalidraw`, mirrored to `fynlaBrain/Diagrams/` and indexed.
- Commit `e50bff154` on dev.

## Verification state

- `./vendor/bin/pest tests/Architecture`: 153 passed, 1 skipped, 30 deprecated at `28194e804`.
- `npx vitest run`: 143 files, 1329 tests passed at `28194e804`.
- `./vendor/bin/pest tests/Feature/Auth tests/Feature/Native/Auth tests/Feature/Consent tests/Feature/Middleware/RedirectAuthenticatedToDashboardTest.php tests/Feature/Mobile/DeviceRegistrationTest.php`: 219 passed at `28194e804`.
- Playwright, local build: web login, dashboard, nav, Fyn panel, notification preferences, registration, MFA setup and login, recovery code, password reset, security page, privacy page, export, account deletion, restore modal; `/m` login, dashboard, menu, notification preferences, MFA-user login. Every claim in the three maps is tagged Working, Unverified, Broken, Dead or Dead end accordingly.
- Not verified: Unit, Feature (beyond auth), Integration, Browser and E2E suites; iOS anything; production anything; the email templates' rendered appearance; push delivery (no APNs keys locally).

## Decisions and dead ends

- **CSJ decided the skill's shape:** Excalidraw for every overview and flow diagram (no Mermaid); Playwright screenshots only for sections that are driven; tests written and run per section; issues raised in `<Month>/<Month><D>Updates/mappingBugs<date>.md` and never fixed inside a mapping run; reports in `docs/app-map/`; the overview first, then sections in index order.
- **CSJ confirmed the PHP public pages are canonical** (Phailanx is Azlan Raj's GitHub handle; he built `public/pages/` in May). The router guard at `resources/js/router/index.js:1662-1680` already forces a full load for those paths, so the 56 Vue copies never render. MB-09 says delete them.
- **MB-01 is W-0540 extended, not a new finding.** W-0540 (4 September) measured 79 orphan components and parked the allowlist-versus-sweep decision; it is still parked. My 153 count adds transitive orphans, comment-only mentions and non-component files, and MB-09 adds 56 more.
- **Reachability method:** imports plus component-tag mentions from the entry points, with HTML and JS comments stripped before matching. Without the comment strip, eleven files mentioned only inside commented-out template blocks (`InvestmentList.vue:190-218`) were wrongly counted live. Path strings built from a base constant (`${API_BASE}/refresh`) escaped the client-route regex; the MB-08 agent's fixed-string greps were more reliable than my regex for that question.
- **The dossier agent was worth it** for MB-08 (21 controllers, git archaeology per route) but its result came back truncated in the message; the fix was to have it write the file itself. Do that from the start next time.
- **Dead end:** the Playwright tab died once mid-MFA-setup (blank `about:blank`), the known dead-tab issue; recovering meant a fresh navigation and a full re-login. Refs also go stale after any dialog; take a new snapshot before typing.
- **Authenticator codes for testing** are computed from the cached setup secret or the user's encrypted secret: `php artisan tinker --execute='...(new \PragmaRX\Google2FA\Google2FA)->getCurrentOtp(\Crypt::decryptString($u->mfa_secret))'`. Works within the two-window tolerance; compute it right before typing.
- **Not done on purpose:** no fixes, no deletions, no deploy, no vault-sync mid-session, no iOS build.

## Things that will bite you

- The local `.env` sends real email through the production SMTP host (`MAIL_MAILER=smtp`, `mail.fynla.org`). Every test login sends a real code email to the test address. Fetch codes from the database instead: `email_verification_codes`, `pending_registrations.verification_code`, `password_reset_sessions.email_code`.
- Test account left behind: user 85 `map-tester-2026-09-14@example.com`, password `MapTest2!`, two-factor enabled (secret encrypted on the row; recovery codes in the session transcript are not recorded here), restored after deletion, one export under `storage/app/exports/`. Marketing consent on. Delete it if it gets in the way; nothing depends on it.
- `john@example.com` had its `fyn_daily_insight` preference toggled off and back on; it ends the session true.
- Signing out on the web revokes the token the `/m` tab was using in the same browser context; log in on `/m` again after any web sign-out.
- The local dev servers were started with `./dev.sh` and are still running (`artisan serve` on 8000, Vite on 5173).
- The app-map INDEX section list is a starting order; split or merge rows as the overview reveals boundaries (the overview did not change it).

## Tech debt deferred

No application code changed this session; the pass does not apply. Everything found is in the bugs file, not here.

## Branch and deploy state

- Branch: dev
- Unpushed commits: 1 before the handover commit (`e50bff154`); pushed together with the handover.
- Deploy status: nothing deployed this session; prod main and dev are as the 2026-09-12 handover left them.
