# CSJTODO — Fynla

*Last updated: 14 April 2026 — session 51*
*Previous session: 9 April 2026 — session 50*

---

## Session 51 (14 April) — Trial Reminder System Investigation + Production Fixes

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
