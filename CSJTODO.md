# CSJTODO — Fynla

*Last updated: 14 April 2026 — session 52 (afternoon)*
*Previous session: 14 April 2026 session 51 (morning) — Trial Reminder Investigation*

---

## Session 52 (14 April, afternoon) — Dev Environment + PRs #210, #211, #212

### ✅ DEPLOYED TO DEV — in testing

All items below live on **`https://csjones.co/fynla`** (dev branch), going through testing before being promoted to `main` / production.

- [x] **Two-environment workflow stood up** — `dev` branch created off `main`, CODEOWNERS + branch protection plan + BOOTSTRAP guide + modernised `.env.production` templates committed. See `deploy/csjones-fynla/BOOTSTRAP.md` for the full one-time provisioning procedure and `CLAUDE.md § Deployment` for the ongoing workflow.
- [x] **Dev environment provisioned at `csjones.co/fynla`** from scratch:
  - Sibling-dir + symlink pattern matching existing `tengo-app`: `~/www/csjones.co/fynla-app/` (Laravel code) + `public_html/fynla → ../fynla-app/public` (web exposure)
  - MySQL DB `dbqyhotaixmoo8` populated via `migrate --force` (167 migrations) and `db:seed --force` (14 users, 9 preview personas, tax config, subscription plans)
  - `.env` written to server with `APP_ENV=staging`, `APP_DEBUG=true`, `REVOLUT_SANDBOX=true`, `LIFECYCLE_TEST_RECIPIENT=chris@fynla.org`
  - Full login flow end-to-end verified via Playwright (credentials → verification code from dev DB → dashboard)
- [x] **Pre-existing cross-environment bugs caught and fixed during dev bootstrap:**
  - `deploy/csjones-fynla/.htaccess` used `<DirectoryMatch "\.git">` which is **not permitted in shared-hosting .htaccess** — would 500 the entire site on upload. Fixed to `RewriteRule ^\.git - [F,L]`.
  - `resources/views/app.blade.php` hardcoded `/images/logos/favicon.{ico,png}` — 404'd on subdirectory deployments. Fixed with `{{ asset(...) }}` helper (works on both envs).
  - `resources/js/services/api.js` built `baseURL` as `${window.location.origin}/api`, which on subdirectory deployments resolved to `csjones.co/api` (404) instead of `csjones.co/fynla/api`. Fixed to thread `VITE_ROUTER_BASE` into the base URL.
  - 23 remaining Vue files still hardcode `/images/...` — patched on dev via `public_html/images → ../fynla-app/public/images` symlink (pragmatic workaround, not a proper fix — see "Known follow-ups" below).
- [x] **PR #210 (Icecube-acc, brett-v2)** — two new insight articles (Stocks & Shares ISA, How Much to Retire) + router wiring + sitemap. Retargeted from main → dev, merged, deployed. Smoke-tested: `/fynla/insights` shows the new articles at the top, individual article page loads.
- [x] **PR #211 (Phailanx, email-designs)** — 10 email template redesigns + review carousel on homepage + Meta Pixel tracking events + persona modal mobile fix + new `email:test` artisan command. Retargeted from main → dev, merged, deployed. Smoke-tested: "What our customers say" carousel renders with 6 unique reviews, `email:test` command registered, all 10 templates uploaded with today's timestamp.
- [x] **Dev branch pushed to origin** — `origin/dev` is 7 commits ahead of `origin/main` and deployed to csjones.co/fynla for testing.

### 🟡 IN TESTING on csjones.co/fynla (not yet promoted to main)

- [ ] Two new insight articles (PR #210) — needs content review
- [ ] Email template redesigns (PR #211) — needs visual review via `php artisan email:test <email>` run against an inbox
- [ ] Review carousel (PR #211) — needs cosmetic check on mobile breakpoint
- [ ] Meta Pixel tracking (PR #211) — needs verification that events fire correctly (CompleteRegistration, StartTrial, Subscribe)

### 🔴 BLOCKING items — must be resolved before promoting dev → main

1. **Production cron verification** (carried from session 51 morning) — the system cron added on production at end of session 51 must be verified firing. See "FIRST THING TOMORROW" section below.
2. **csjones.co system cron** — dev also needs a system cron for scheduled commands to fire there. SiteGround Site Tools → Devs → Cron Jobs → `cd /home/u163-ptanegf9edny/www/csjones.co/fynla-app && /usr/local/php82/bin/php-cli artisan schedule:run >> /dev/null 2>&1` with all five `*` fields.
3. **PR #212 (lifecycle engine)** — still targeted at `main`. Needs to be retargeted to `dev`, merged into dev, deployed to csjones.co, tested, THEN promoted to main. Known merge conflict in `resources/views/emails/trial-expiration-reminder.blade.php` (PR #211 redesigned it, PR #212 palette-fixed it) — resolution is to keep PR #211's new layout and reapply PR #212's Fynla-palette colour tokens on top.

### Pending on CSJ (web UI only — can't be done via SSH)

- [ ] **GitHub branch protection** on `main` and `dev` — Settings → Branches → Add rule → require PR + 1 code-owner approval + no admin bypass, for BOTH branches. Contributors `icecube-acc` and `Phailanx` at **Write** role (can push to feature branches, cannot merge to protected branches).
- [ ] **csjones.co system cron** (see "BLOCKING items" #2 above)
- [ ] **Revolut sandbox webhook registration** at `https://sandbox-merchant.revolut.com` pointing to `https://csjones.co/fynla/api/payment/webhook` — only needed if testing payment flows on dev.

### Known follow-ups (non-blocking, backlog)

- [ ] **Fix 23 hardcoded `/images/...` paths in Vue files** — currently patched on dev via the `public_html/images` symlink, but the proper fix is a base-aware helper (`import.meta.env.VITE_ROUTER_BASE`) so both root and subdirectory deploys work without filesystem tricks. Low urgency — dev works, the symlink is stable.
- [ ] **CSP whitelist for Google Fonts + Meta Pixel** — pre-existing on production too (tracked in session 51 handover as "Meta Pixel CSP"). Both show as CSP errors in the browser console on every page load.

---

## Session 51 (14 April morning) — Trial Reminder System Investigation + Production Fixes

### Completed This Session

- [x] **Investigated trial reminder email system** — root cause found: no system cron entry on production triggering `php artisan schedule:run`. ALL 15 scheduled commands (not just trial reminders) had never run. Full report at `April/April14Updates/trialReminderInvestigation.md` (also in vault)
- [x] **Cron job added on production** (CSJ via SiteGround Site Tools → Devs → Cron Jobs)
- [x] **11 ghost trialing subscriptions expired** via `php artisan trials:expire` on production. 11 subs moved `trialing → expired`, 11 users moved to `plan='free'`, 30-day data retention countdown started for each. **Of those 11, only 1 is a real user (Jessica Cracknell, user 301, `jessicacracknell18@gmail.com`)** — she had 34 days of bonus access she shouldn't have had and never received a single reminder email. Worth flagging if she gets in touch about losing access.
- [x] **`notifications` table created on production** — fixed a latent crash bug. Original report claim that `notifications:daily-insight` would crash was **wrong** (that command bypasses Laravel's notification system and uses FCM directly). The actual issue affected 5 OTHER scheduled commands that call `$user->notify(...)` with `via(['database'])` notification classes: `notifications:policy-renewals`, `protection:send-alerts`, `notifications:mortgage-rate-alerts`, `savings:send-alerts`, `estate:send-alerts`. All 5 would have crashed daily once cron started running them.
- [x] **New migration** `database/migrations/2026_04_14_094042_create_notifications_table.php` — generated locally via `php artisan notifications:table`, edited to match Fynla conventions (`declare(strict_types=1)`, `Schema::hasTable()` safety check), uploaded to production via SSH, applied via `migrate --force`. Verified with insert/readback/delete.
- [x] **`schedule:run` proven healthy** on production manually — Laravel chain works end-to-end, no errors, exit 0.
- [x] **Disclosure:** I ran `php artisan schedule:test --name="trials:send-reminders"` on production thinking it was a dry run — it isn't. As a side effect, **10 real reminder emails were sent** at 08:42 UTC to users 581–588 (3-day reminders) and 551/552/580 (2-day reminders). All were legitimate reminders the system was supposed to send anyway. The `trial_reminder_log` was correctly populated so they will not receive duplicates.
- [x] **Repo cleanup** — removed 487 archived vault note files from `Articles/`, `Feb/`, `March/` directories (these were already in vault and `April/` is `.gitignore`d).

### NOT Done — Outstanding from Session 51

- [ ] **🔴 Verify cron is firing on production** — to be done first thing tomorrow. Full verification checklist in `April/April15Updates/CSJTODO.md` (also in vault). The 09:00 UTC fire on 15 April is the moment of truth.
- [ ] **Update `fynlaBrain/Architecture/v083/11-CONFIGURATION-DEPLOYMENT.md`** to add the cron setup as a documented deploy step (so this can never recur on a future server migration).
- [ ] **Confirm Jessica Cracknell** (user 301) doesn't need a goodwill gesture or trial reset — she's the only real user affected by the ghost trial cleanup, and she never received a reminder email through no fault of her own.

### Context for Next Session

The cron entry was added to SiteGround at the very end of this session. We did **not** have time to verify it actually fires before close — `crontab` command is not available on this user (SiteGround panel cron, not visible from SSH), and `schedule:run` is a no-op when nothing is due. Tomorrow's first task is to verify by checking `trial_reminder_log` for new rows after 09:00 UTC, `pending_registrations` for the January rows being cleared (hourly), and `laravel.log` for any errors. **If cron is NOT firing, the diagnostic next steps are documented in tomorrow's CSJTODO.**

The 5-bug correction in the notifications table fix is a reminder to **read command code** before claiming what depends on what — I made a wrong claim in the original report based on a failed tinker query rather than reading `SendDailyInsightNotifications.php`. The corrected report is now in both repo and vault.

---

## Carry-Over From Session 50 — Still Outstanding

- [ ] **Fyn Quick Start flow** — hidden CTA, root cause unfixed (`fynQuickStartBugs.md`): production missing `dc_pensions.current_value` column, AI analyses empty data for new users
- [ ] **Run pending migration on production** — `dc_pensions.current_value`
- [ ] **Generate missing invoice for payment #17** (user 542, chris@fynla.org) — from session 47
- [ ] **PR #197 cleanup** — 9 markdown files in repo root should be moved to `Articles/`
- [ ] **Meta Pixel CSP** — `connect.facebook.net` and `www.facebook.com` not in `SecurityHeaders.php` whitelist
- [ ] **`fynNew` branch** (25 Fyn Response Architecture commits) still unmerged
- [ ] **Add `.claude/settings.json` to `.gitignore`** — tax-hook path keeps reverting

---

## Outstanding — Tech Debt (Deferred from Code Review)

### Duplicate Code Consolidation (from 9 April audit)
- [ ] DUP-01: Consolidate `determineTaxBand` — 7 implementations across services → UKTaxCalculator
- [ ] DUP-02: Consolidate DC pension annual contribution — 5 duplicates
- [ ] DUP-03: Consolidate pension tax relief — 3 duplicates
- [ ] DUP-04: Consolidate `calculateFutureValue` — 5 duplicates (shared service exists but not injected)
- [ ] CONV-03: Migrate 22 Vue components from direct currency import to currencyMixin

### God Class Refactors (multi-session each)
- [ ] RetirementIncomeService.php (2,292L) → extract ProjectionEngine, PCLSCalculatorService, AllocationStrategyService
- [ ] IHTCalculationService.php (1,641L) → extract CharitableRateCalculator, RNRBCalculator, ProjectedEstateService
- [ ] UserProfileService::getFinancialCommitments (421-line method)
- [ ] User.php (713L, 59 methods) → extract HasSubscription trait, DomicileService
- [ ] InvestmentAccount.php (492L, ~164 fillable) → polymorphic sub-type tables
- [ ] TaxSettings.vue (3,068L), ExpenditureForm.vue (2,574L), CalculatorsPage.vue (2,471L), Dashboard.vue (2,215L), RetirementIncomeTab.vue (2,107L)

### Architectural Debt
- [ ] Float-to-decimal cast sweep across 9 models (65 columns)
- [ ] FormRequest migration across 26 controllers (~78 new classes)
- [ ] API Resource extraction for 92 controllers returning raw JSON
- [ ] Split InvestmentController (1,070L) + AdminController (794L) + GoalsController (792L)
- [ ] Nonce-based CSP to replace `unsafe-inline` (MEDIUM-01 from security audit)
- [ ] npm audit fix (14 vulnerabilities, 11 high — breaking changes need testing)

### Test Coverage Gaps
- [ ] ~85 services with zero tests (IHTCalculationService most critical)
- [ ] Investment Analytics/Rebalancing/Performance/Tax subdirectories entirely untested (~35 services)
- [ ] AutoRiskCalculatorTest — `risk_level` column enum doesn't accept `medium_low` (2 pre-existing failures)

## Known Issues
- [ ] Retirement "Other Assets" cards overflow at 1118px
- [ ] DB pension field mapping mismatch
- [ ] Expenditure form fill doesn't animate
- [ ] property_sale life event creates property record (double navigation)

## Deploy Status
- **PR #208 (claudeReview):** DEPLOYED 9 April 2026 — spouse toggle, dashboard refresh, markdown, enum, tier gating
- **Trial reminder migration (notifications table):** DEPLOYED 14 April 2026 (uploaded via SSH, applied via `migrate --force`)
- **Production cron entry:** ADDED 14 April 2026 via SiteGround Site Tools — verification pending session 52
