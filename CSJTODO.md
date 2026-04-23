# CSJTODO — Fynla

*Last updated: 23 April 2026 — session 67 (UI fixes bundle: logout redirect, progress hero for all users, form field collapse, joint net-worth layout, spouse name regression)*
*Previous session: 23 April 2026 — session 66 (pension projection fix + unified add pension form + nav refresh)*

---

## Session 67 (23 April night) — UI fixes bundle

PR [#226](https://github.com/Stoff73/fynla/pull/226) merged to `dev` as merge commit `416e770`, deployed + browser-tested on `csjones.co/fynla` (per CSJ).

### Completed

#### Six independent UI fixes, one branch (`genUIFixes`)

- [x] **Logout redirects straight to `/login`** — the success modal used to hold the user on the dashboard until they dismissed it. `AppNavbar.vue` now mirrors what `SideMenu.vue` already did: dispatch `auth/logout`, then `router.push('/login')`. Orphan `LogoutSuccessModal.vue` deleted. Commit `acc6086`.
- [x] **Dashboard progress hero now renders for every user**, not only journey users. Skip-to-dashboard and Fyn-onboarded users previously saw a blank top of page. The Scenario Completeness column is hidden when there's no active journey; its column width is split evenly into narrow left + right margins so Profile Completeness and Recommended Actions keep their original `w-1/3` positions. Ring restored to full 140px; labels like "Cash Management" fit on one line without overflowing into the percentage column. Collapsed bar shows overall profile % + "Profile complete" when no journey. Mobile carousel skips the Scenario slide and re-counts pagination dots. Commit `d3756ae`.
- [x] **Pension + Investment Add/Edit forms** — advanced fields now collapse behind a single "Additional information" toggle per form. Auto-expands in edit mode when any hidden field has a user-provided value. Collapsed-on-save nulls the hidden fields in the outgoing payload. Commit `c515aa3`.
  - Pension form (DCPensionForm for Money Purchase types): Lump Sum Contribution, Expected Return %, Platform Fee, Advisor Fee, Beneficiary section, Holdings editor. DB / State branches unchanged.
  - Investment form (AccountForm + StandardInvestmentFields for ISA / GIA / Bonds / VCT / NS&I / Other): Country, Platform/Product Name, Planned Lump Sum (amount + date, both non-ISA and ISA variants), Platform Fee, Holdings editor. Private Investment and Employee Share Scheme sub-forms explicitly left untouched.
  - `expected_return_percent` default changed from `5.0` to `null` so users who never expand the section don't persist a synthetic return assumption.
- [x] **Joint Net Worth Wealth Summary redesigned** — married users previously saw three donuts stacked in the left column (user, spouse, combined) and a right-hand bar chart showing only the current user's figures. Joint users now see two per-person donuts inline, then a full-width Assets-vs-Liabilities bar chart underneath. Hovering a bar opens a custom tooltip: "Category: £TOTAL" with the per-person split below it ("David Mitchell: £755,500 / Sarah Mitchell: £637,500"). Single users keep the original layout untouched. Commit `eaf4552`.
- [x] **Root-cause fix for the recurring "Partner" / "Spouse" regression** — the `userProfile/spouse` getter returned inconsistent shapes across its code paths. `spouseInFamily` paths returned FamilyMember records (which carry a `name` column from the DB), but the `currentUser.spouse` fallback paths built synthetic objects with only `first_name` / `last_name`. Every consumer reading `spouse.name` through those fallback paths silently rendered empty and was masked by `|| 'Partner'` / `|| 'Spouse'` fallbacks in callers. Getter now normalises every return path through a `withName` helper so `name` is always resolved. `NetWorthWealthSummary.spouseUserName`, `PortfolioOverview.getSpouseName`, and `LetterToSpouse.spouseNameForLetter` all updated to read from `userProfile/spouse` first, falling back to the auth inline spouse object, and only then to the string literal. Admin / Estate IHT / Protection analysis / Preview persona spouse-name reads are fed by different data sources (admin users list API, IHT calc response, preview persona JSON) and intentionally not touched. Commits `2a0d7b2` + `7e1739d`.
- [x] **csjones build script output updated** — the post-build echoed instructions pointed at the legacy `public_html/fynla/` layout and omitted the sibling-dir reality (Laravel app at `~/www/csjones.co/fynla-app/`, `public_html/fynla` is a symlink). Script now echoes the correct upload target, the preserve-old-chunks `mv`+`cp -rn` pattern, the full SSH command, and the full cache-clear sequence. No logic change — only the trailing echo. Commit `677f146`.

#### Deploy + docs

- [x] **PR #226 opened, 7 commits, admin-merged to `dev`** as merge commit `416e770`.
- [x] **`April/April23Updates/deployUIFix.md`** — full deploy guide with sibling-dir upload path, preserve-old-chunks pattern, smoke-test steps per fix, rollback, and the promote-to-main handoff. Mirrored to vault.
- [x] **Deployed to csjones.co/fynla dev + browser-tested.** Per CSJ: all six fixes working on the live dev site.
- [x] **Local browser-tested during the session:** pension Add form (collapse/expand, SIPP variant), investment Add form (collapse/expand, GIA + ISA variants), joint net-worth layout (David & Sarah Mitchell preview persona — tooltip split, spouse name on donut + wealth summary + bar chart props), logout redirect.

### Outstanding from session 67

- [ ] **Cut `dev → main` PR when ready.** This deploy passes dev smoke tests. When the next production cut happens, #226 rides along. Production build uses `./deploy/fynla-org/build.sh` (NOT the csjones script — base paths differ).
- [ ] **Exercise the edit-mode auto-expand** on an existing pension or investment account that already has hidden-field values populated. Logic is reviewed in diff only; not browser-tested end-to-end.
- [ ] **Exercise collapsed-form submit → DB verification** for both forms — confirm the null-on-save code path actually writes nulls on a real save.
- [ ] **Exercise the onboarding path** for both forms. Both accept `isOnboarding` prop but only the standalone modal path was browser-tested this session.

### Outstanding from session 66 (carried forward)

- [ ] **Cut `dev → main` PR when ready.** Pension projection fix + nav refresh (PR #225) still pending production cut.
- [ ] **Optional SQL purge on production after dev→main cut** to age out legacy MC cache keys immediately (otherwise 24h wait):
  ```sql
  DELETE FROM monte_carlo_cache WHERE cache_key LIKE '%pension_pot_%' AND cache_key NOT LIKE '%_i%';
  ```
- [ ] **Before the next `dev → main` PR**, verify `deploy/fynla-org/build.sh` and production `.env` have the LIVE Revolut pk (not sandbox) baked in / present, so a future production rebuild from a developer's laptop doesn't accidentally ship a sandbox-pk build to prod.

---

## Session 66 (23 April evening) — pension projection + unified add pension + nav refresh

PR [#225](https://github.com/Stoff73/fynla/pull/225) merged to `dev` as commit `6b7306d`, deployed + browser-tested on `csjones.co/fynla`, old builds cleaned up.

### Completed

#### The long-standing pension projection regression, fixed at the root
- [x] **Reproduced the "pension added but projection shows £0" bug** live on `sarah@example.com` — the pension's fund value rendered correctly on the dashboard but `pension_pot_projection.percentile_20_at_retirement` and the year-by-year Monte Carlo array were all zeros. No console errors. The API returned structurally-valid data that happened to all be zero.
- [x] **Traced the root cause to the Monte Carlo DB cache.** Cache key for `projectPensionPot` was `user_{id}_pension_pot_{years}y_e{eventHash}` — user, years-to-retirement, and life-event hash, but **not** the actual simulation inputs (start value, monthly contribution, return, volatility). When a brand-new user loaded the dashboard with zero pensions, `simulate(0, 0, …)` produced all zeros and cached them under that key. When the user added a pension, `simulate(50000, 500, …)` hit the same key and got the stale zeros back.
- [x] **Fix: content-addressed cache key.** Hashed the four numeric inputs into the key (`md5("{startValue}:{monthly}:{return}:{vol}")`). Input changes → new key → fresh simulation. No observer wiring, no write-path coupling — which is why the previous attempts to fix this at the write side (observers, central `CacheInvalidationService`) kept regressing. Commit `a6cfa5a`. Same fix applied to `projectIndividualDCPension`.

#### Unified Add Pension form (no more three-tile picker)
- [x] **Replaced the tile picker** that had Money Purchase / Final Salary / State Pension with a single "Add Pension" form. Pension type dropdown now carries Occupational, SIPP, Personal, Stakeholder, **Final Salary (Defined Benefit)**, **State Pension** — all six in one place.
- [x] **Conditional field groups** inside `DCPensionForm`: picking Final Salary swaps body to DB fields (scheme status, annual income, service years, accrual rate, revaluation rate, PCLS). Picking State Pension swaps to State fields (forecast weekly, qualifying years, NI gaps). Backend payload shapes mirror the legacy `DBPensionForm` / `StatePensionForm` outputs exactly — verified `db_pensions` and `state_pensions` records are identical whether captured via this unified form or edited via the legacy forms. Commit `5a7ecec`.
- [x] **Onboarding scoped** — when `isOnboarding=true`, the two new dropdown options are hidden via `v-if="!isOnboarding"` so the onboarding DC pension step keeps its original 4-option dropdown and its `dc_pension` AI-fill wiring.
- [x] **Edit flows untouched** — existing DB and State pension edits still render the legacy `DBPensionForm` / `StatePensionForm` via `initialPensionType` routing.

#### SubNavBar hidden globally, CTAs moved inline
- [x] **SubNavBar suppressed** (`v-if="false"` in `AppLayout.vue`). Component + `subNavConfig.js` kept intact — one-char revert to re-enable. Commit `88af49a`.
- [x] **Retirement CTAs inline** under the pension list, right-aligned next to the projection chart (same raspberry / bordered styling as the old SubNavBar). Commit `618e0ba`.
- [x] **Investments CTAs inline** at the bottom of the accounts column (same convention as retirement).
- [x] **Property-type pages CTAs** top-right of the list on Property, Liabilities, Personal Valuables, Business, Trusts, Goals.
- [x] **Duplicate CTAs resolved** — Cash and Protection already had inline buttons (hiding the SubNavBar removes the duplicates). `GoalsOverview` had its own quick-add row that would have doubled with the new tab-header Add Goal — removed.
- [x] **Life Events** uses `EventsTab`'s own internal Add button — not duplicated in the tab header.

#### Sticky top nav
- [x] **AppNavbar wrapper** is now `sticky top-0 z-30 bg-eggshell-500` in `AppLayout.vue`. Dashboards scroll under it; nav always visible. Offsets to `top-[44px]` when the AdvisorBanner is active during advisor impersonation. Docked-chat `headerOffset` calculation continues to work — as a bonus, the chat no longer jumps upward as the user scrolls since the header bottom edge stops moving. Commit `2901b30`.

#### Investment account detail projection fix (same session, different shape)
- [x] **Found and fixed a matching-but-different projection bug** — clicking into an investment account card showed "Failed to load projection data" with `TypeError: investmentService.getAccountProjections is not a function` in console. Not a cache bug — the frontend service method itself was missing (likely removed by commit `d635d36`'s dead-code sweep and never restored by the `b0ad5ad` revert). Backend route + controller were fine. Added the method back with optional `risk_level` param for the what-if feature the backend already supports. Commit `f2ba360`.

#### Small UX polish
- [x] **Browser tab always reads "Fynla"** — `Login.vue` was setting `document.title = 'Sign In — Fynla'` on mount and nothing reset it post-login, so the tab label stuck as "Sign In — Fynla" across the whole authenticated session. Login.vue now sets `'Fynla'`, and a `router.afterEach` hook keeps the tab title as `'Fynla'` on every SPA navigation. Blade template's long marketing title untouched for SEO crawlers. Commit `e653180`.

#### Deploy + docs
- [x] **PR #225 opened, pushed through 8 commits, admin-merged to `dev`** as merge commit `6b7306d`.
- [x] **`April/April23Updates/deployPensionFix.md`** — upload checklist, SSH command sequence, 7-part smoke-test plan, rollback, optional SQL purge for legacy MC cache rows. Mirrored to vault.
- [x] **`April/April23Updates/patchPensionInvest.md`** — end-user patch notes (plain English, no tech jargon). Mirrored to vault.
- [x] **Dev server deployed + browser-tested by CSJ.** All 7 smoke-test sections passed. Old `public/build.old` and `public/build.old2` directories removed from `~/www/csjones.co/fynla-app/public/` — freed ~23MB.

### Outstanding from session 66

- [ ] **Cut `dev → main` PR when ready.** This deploy passes all smoke tests on dev. Production cut-over guidance is in `deployPensionFix.md` §Production cut-over. Must include PR #224 (intervention/image v3 downgrade) carried through — verified by running `composer show intervention/image` on dev reporting `3.11.7`.
- [ ] **Optional SQL purge on production after the dev→main cut** to age out legacy MC cache keys immediately (otherwise 24h wait):
  ```sql
  DELETE FROM monte_carlo_cache WHERE cache_key LIKE '%pension_pot_%' AND cache_key NOT LIKE '%_i%';
  ```

### Outstanding from 65b (carried forward)

- [x] **Complete the in-flight checkout test** — ticked at session 66 start after CSJ confirmed it was done.
- [x] **Clean up `public/build.old/` and `public/build.old2/`** on the dev server — done at end of session 66.
- [ ] **Before the next `dev → main` PR**, verify `deploy/fynla-org/build.sh` and production `.env` have the LIVE Revolut pk (not sandbox) baked in / present, so a future production rebuild from a developer's laptop doesn't accidentally ship a sandbox-pk build to prod.

---

## Session 65b (23 April late-afternoon) — CSP / Revolut / .env cascade

### Completed

- [x] **Removed HSTS + CSP + Permissions-Policy `Header set` from both `.htaccess` templates** (`deploy/csjones-fynla/.htaccess`, `deploy/fynla-org/.htaccess`). Apache's `Header set` was overwriting `SecurityHeaders` middleware's richer CSP and blocking Revolut widget on dev. Commit `f0770bb`.
- [x] **Uploaded new csjones `.htaccess` to dev server**, cleared Laravel caches.
- [x] **Fixed dotenv syntax on server `.env` line 62** — `ADMIN_EMAILS` now quoted (was unquoted comma-separated value with whitespace, invalid dotenv syntax that was hidden by config cache until `config:clear` exposed it). Backup at `.env.backup-2026-04-23-csp-fix`.
- [x] **Pinned `VITE_REVOLUT_SANDBOX=true` + `VITE_REVOLUT_PUBLIC_KEY=pk_D2JdE2srRipv0jdHerivLw1hMoWSrjqDa4lEozJxTwchuG04`** into `deploy/csjones-fynla/build.sh`. Builds now reproducible regardless of builder's local `.env`. Commits `921bb3d` + follow-up.
- [x] **Rebuilt + uploaded** new `public/build/`. New `CheckoutPage-CAePoYgl.js` has correct sandbox SDK URL + correct merchant pk, Revolut widget 403s are gone.
- [x] **Preserved old build chunks** alongside new ones (`cp -rn public/build.old/. public/build/`) so CSJ's in-flight incognito session survived the rebuild without a forced refresh — every route except `/checkout` continued to work mid-session.
- [x] **Incident log written** at `April/April23Updates/revolutCSPIncident.md` + mirrored to vault. Documents timeline, root causes, fixes, and 5 rules for next session (chief rule: warn CSJ before rebuilding during active browser testing).

### Outstanding from 65b

- [x] **Complete the in-flight checkout test** — CSJ's original session has the pre-fix `CheckoutPage-Dq2ZEZzV.js` in memory with the wrong pk. Needs a fresh incognito window to exercise the correct `CheckoutPage-CAePoYgl.js` chunk and confirm the full sandbox checkout flow works end-to-end.
- [x] **Clean up `public/build.old/` and `public/build.old2/`** on the dev server once ~24h have passed and no one is on a pre-rebuild session. `rm -rf` both. *Done end of session 66 — freed ~23MB.*
- [ ] **Before the next `dev → main` PR**, verify `deploy/fynla-org/build.sh` and production `.env` have the LIVE Revolut pk (not sandbox) baked in / present, so a future production rebuild from a developer's laptop doesn't accidentally ship a sandbox-pk build to prod.

---

## Session 65 (23 April afternoon) — PR triage + dev deploy + intervention/image v3 downgrade

### Completed This Session

#### Repository + branch protection
- [x] **Re-enabled branch protection on `dev`** — 1 required PR review, code-owner review required (CODEOWNERS pins `@Stoff73`), dismiss stale reviews, required conversation resolution, no force pushes, no deletions. `enforce_admins: false` retained so CSJ can admin-bypass when needed.
- [x] **Re-enabled branch protection on `main`** — identical settings to dev. Previously unprotected, which contradicted CLAUDE.md's documented workflow.
- [x] **Saved new durable rule** in memory (`feedback_main_via_dev_only.md`): nothing merges to main without first being committed to dev, deployed to csjones.co/fynla, and browser-tested. Only CSJ overrides with explicit words in the current turn. MEMORY.md index updated.

#### PR triage (5 PRs processed)
- [x] **PR #213 closed** — stale session 52 CSJTODO doc, superseded by later handovers.
- [x] **PR #212 re-targeted** from `main` → `dev` (violated the new rule by targeting main directly).
- [x] **PR #221 rebased** onto the refreshed `dev` — CSJTODO conflict resolved by taking dev's newer version; force-pushed; admin-merged via `gh pr merge 221 --merge --admin`. Campaign pages + ReviewCarousel + StaticFynChat + 404 page now on dev.
- [x] **PR #223 opened + admin-merged** (`main → dev` back-merge) — brought session 64's subscription hotfix + session 63/64 handover docs onto dev. Dev was missing 3 commits (`ad73bd0`, `5cd5d62`, `bd9042e`) that had been admin-merged directly to main. Clean merge — only `AppLayout.vue` overlapped and auto-merged.
- [x] **PR #212 rebased** onto new `dev` through 40+ commits, 6 conflict points resolved manually (CSJTODO, CLAUDE.md, trial-expiration-reminder.blade.php, routes/web.php twice, AppLayout.vue three times, router/index.js, Settings.vue deletion). Force-pushed and admin-merged. Full lifecycle email engine (5 campaigns + engine + E2E test commands + magic-link routes + NotificationPreferences page + 14 toggles) now on dev.
- [x] **PR #224 opened + admin-merged** — downgraded `intervention/image ^4.0 → ^3.0` to keep PHP 8.2 compatibility, ported `InsightImageService` to the 3.11 API (`ImageManager::gd()`, `->read()`, `->toWebp(quality:)`). 9/9 existing tests still pass.

#### Dev server redeploy (csjones.co/fynla) — 167 files uploaded, 7 deleted, 12 migrations ran
- [x] **Server state probed via SSH** — confirmed server was at approximately `origin/onboardingFyn` state (last migration `2026_04_15_153100`), not main. Real delta was 173 files not the 153 my original guide assumed.
- [x] **`filesUploaded.md` comprehensive checklist** generated and mirrored to repo + vault. 215 line items across §A upload / §B delete / §C exclusions / §D server commands / §E smoke tests / §F rollback.
- [x] **167 files uploaded** via tar-pipe in 0.3s; hash-verified byte-for-byte match against `origin/dev`.
- [x] **7 superseded files deleted** on server (OnboardingChatDirector, OnboardingPromptBuilder, OnboardingStateMachine, OnboardingValueInterpreter, SpouseLinkingService, EmptyDataGuard, config/onboarding.php). 2 items in delete list were already absent.
- [x] **composer install** — resolved to `intervention/image 3.11.7` + `intervention/gif 4.2.4`, both PHP 8.2 compatible. Platform-check re-enabled and passing.
- [x] **Appended `.env` vars**: `LIFECYCLE_ENGINE_ENABLED=true` + `LIFECYCLE_TEST_RECIPIENT=chris@fynla.org`. Deduped after a session confusion created doubles. `.env.backup-2026-04-23-post-lifecycle` preserved.
- [x] **12 pending migrations ran** — 7 lifecycle + 5 insights, all `DONE`.
- [x] **Cache clears + optimize** — config + routes cached.
- [x] **Insights seeder** — 8 bespoke articles seeded.
- [x] **Full `php artisan db:seed --force`** — 22 seeders all green, including **OccupationCode (406 codes)**, Preview users (6 personas), ChrisUser, AdvisorClient, etc.
- [x] **Lifecycle engine smoke test** — `php artisan lifecycle:run-daily` ran all 5 campaigns cleanly (0 eligible users, as expected).
- [x] **Endpoint smoke tests** — `/fynla/`, `/fynla/pricing`, `/fynla/quickstart`, `/fynla/insights`, `/fynla/how-it-works`, `/fynla/features`, bad-URL SPA fallthrough → all HTTP 200.

#### Landing page CTA
- [x] **Unhid "Quick start with Fyn" CTA** on the landing page hero — commit `97edb5d` admin-pushed to dev. The HTML comment markers were removed; the `<router-link to="/register?from=fyn">` now renders live on both localhost:8000 and csjones.co/fynla. Known caveat: new-user Fyn flow has bugs (per `April/April9Updates/fynQuickStartBugs.md`) — CTA-to-flow fixes deferred to a future session.

#### Supporting docs (all mirrored to repo + vault)
- [x] `April/April23Updates/devUpdateDeploy.md` — initial deploy guide (subsequently superseded by filesUploaded.md when server state turned out to be further behind than main).
- [x] `April/April23Updates/filesUploaded.md` — authoritative 215-item upload + server-command checklist; all §A/§B/§D items (except optional §B4 renames + cron verification) ticked.
- [x] MEMORY.md index updated with new project memory for PR #214 coupling with `feature/fyn-persona-split`, and new feedback rule for main-via-dev-only workflow.

### NOT Done — Outstanding from Session 65

- [ ] **Browser smoke-test PR #221 features** end-to-end on csjones.co/fynla dev — 14 items listed in `filesUploaded.md` §E. This is the next-session opening task. Tech stack to exercise: `/quickstart`, QuickStart CTA (newly unhidden), ReviewCarousel on pricing/features/how-it-works, NotFoundPage fall-through, `/profile/notifications` toggles, lifecycle magic-link → discount prefill, admin insights image upload (tests intervention/image 3.11.7 port).
- [ ] **Fix Fyn quickstart bugs** — see `April/April9Updates/fynQuickStartBugs.md`. CTA is now live on dev but clicks route to `/register?from=fyn` which hits the known-buggy new-user Fyn flow. User explicitly deferred this to a later session.
- [ ] **Verify SG Site Tools crontab** — `crontab -l` via SSH returns empty, yet existing daily jobs (`trials:send-reminders`, `trials:expire`, etc.) clearly run on dev. SiteGround manages cron via their Site Tools web UI. Check that `* * * * * php artisan schedule:run` is configured for csjones.co; if not, the 08:30 UTC daily lifecycle job will silently never fire.
- [ ] **Test lifecycle engine end-to-end** with real emails — `php artisan lifecycle:e2e-test` seeds 5 test users and runs all campaigns against them, sending to `chris@fynla.org` (the LIFECYCLE_TEST_RECIPIENT override). Then `php artisan lifecycle:e2e-cleanup` removes them. Verifies magic-link routes, WebP hero rendering, discount code generation, restart-trial handler, feedback capture.
- [ ] **Optional §B4 cleanup** on server — delete the 7 stale Vue source files on the server (`Navbar.vue`, `Footer.vue`, `Holdings.vue`, `Performance.vue`, `Recommendations.vue`, dead `Goals.vue`, dead `UserProfile/Settings.vue`). Purely cosmetic — build output doesn't reference them.

### Context for Next Session

Dev branch is fully in sync with csjones.co/fynla server. Working tree is clean. Local dev server was running at end of session on Laravel :8000 + Vite :5173 — may still be up or may have been shut down. The big next-session task is browser-testing all the deployed PR #221/#212 features on the dev server, specifically the ones newly visible via the unhidden QuickStart CTA. After dev is stable and browser-tested, the next PR pipeline is `dev → main` for production rollout — but that must include #224's intervention/image downgrade or production will 500 on first composer install.

---

## Outstanding — Tech Debt Deferred (from earlier sessions)

- [ ] **Session 63 tech-debt branch** — already merged to dev (via PR #220) but still needs browser-test matrix before `dev → main`. 8 flows in `April/April18Updates/handover-tech-debt.md §4a`: Estate/IHT dashboard, Investment (holdings/fees/tax/rebalance), Protection, Expenditure form penny-level totals, Estate CRUD, Net worth, Savings, Investment detail.
- [ ] **28 Vue god components** (>800 lines) — prioritise `Admin/TaxSettings.vue` (3,068 lines) and `UserProfile/ExpenditureForm.vue` (2,574 lines). Multi-week effort.
- [ ] **13 backend god files** — `SavingsActionDefinitionService.php` (3,686 lines), `RetirementActionDefinitionService.php` (2,701), `ProtectionActionDefinitionService.php` (2,349), `RetirementIncomeService.php` (2,292), `IHTCalculationService.php` (1,641).
- [ ] **54 controllers using inline `$request->validate()`** — convert to Form Request classes (~60-80h total).
- [ ] **npm `--force` fix** — schedule a 2-4h window for vite 8 + `@capacitor/cli` 8 major upgrades with full PWA + iOS + web regression. 6 high-severity vulnerabilities remain until done. Carried from session 63.
- [ ] **Test Fyn chat fixes on dev (csjones.co/fynla)** — deployed in session 58 but not browser-tested. Carried from session 58.
- [ ] **Add `Current State/Insights.md`** to the vault — carried from session 62.
- [ ] **`AutoRiskCalculatorTest` pre-existing failure** — `risk_level` enum truncation. Pre-existing since 16 April.

## Known Issues

- **CLAUDE.md stale tax-year claim** — says `active: 2025/26` but the seeded `TaxConfiguration` table correctly has `2026/27` active (which is right — 2026/27 started 6 April 2026). `TaxConfigService` reads from DB so behaviour is correct; the line in CLAUDE.md just wants a one-character update.
- **Build script deploy-path echo** is outdated — `./deploy/csjones-fynla/build.sh` prints `~/www/csjones.co/public_html/fynla/public/build/` but the actual sibling-dir path is `~/www/csjones.co/fynla-app/public/build/`. Cosmetic.
- **Dev server user crontab empty** — see "Outstanding — verify SG Site Tools crontab" above.

## Deploy Status

- **fynla.org (production)** — unchanged from session 64. `ad73bd0` subscription hotfix live. Test user `bugrepro_expired_2026_04_23@fynla.org` still in grace-period state.
- **csjones.co/fynla (dev)** — fully in sync with dev branch tip `97edb5d`. All four merged PRs (#212, #220, #221, #223) plus session 65's CTA unhide deployed. composer, .env, migrations, seeds, caches all current.
- **Pending production deploy** — `dev → main` PR not opened. Must include PR #224 (intervention/image v3) or production will 500 on first composer install due to PHP 8.3 requirement. Don't open the `dev → main` PR until session 65's browser testing is complete and any uncovered issues are fixed.
- **Open PRs remaining:** #214 (`onboardingFyn` → `dev`) — still CONFLICTING, coupled with `feature/fyn-persona-split` per memory. Do NOT rebase/merge in isolation.

## Active Work Not Carried by PR

- **Local dev server:** running at `http://localhost:8000/` + Vite `:5173` as of end of session. Check with `lsof -i :8000` before relying on it next session.
- **SSH key:** `~/.ssh/fynlaDev` was loaded into the agent this session (`ssh-add`). It'll remain loaded until the agent cache expires or the machine is rebooted.
