# Dev Update Deploy — 23 April 2026

**Target:** `csjones.co/fynla` (dev / staging)
**Branch:** `dev` (tip: `36ea5a0`)

> ⚠️ **USE [filesUploaded.md](./filesUploaded.md) AS THE UPLOAD CHECKLIST.**
> After SSH probing the server on 23 Apr at session 65, the actual server state was confirmed at `origin/onboardingFyn` (last migration `2026_04_15_153100`), NOT main. The real delta is **173 files to upload + 9 to delete + 12 pending migrations + 1 new composer dep (intervention/image)**, not the 153 files shown below. The sections below remain useful for PR-level context but the upload list below UNDERSTATES what's needed.

**Delta from main:** 153 files, 4 merged PRs since the last dev deploy

## What's being deployed

Four PRs merged into `dev` today (session 65), all now in the single build:

| PR | Summary | Migrations | New env vars |
|---|---|---|---|
| **#220** (session 63 tech debt) | 70 float→decimal:2 casts · 17 dead API methods removed · 54 orphaned Vuex actions removed · 2 dead components deleted · 5 single-word components renamed (Navbar→AppNavbar etc.) · strict_types added to 38 files · 6 generic exceptions → `FinancialCalculationException` factories · arch test added | none | none |
| **#221** (campaign pages) | 5 campaign landing pages · `/quickstart` · testimonials + `ReviewCarousel` reused on 3 pages · `StaticFynChat` on 404 + campaign pages · pricing banner trim · Phailanx work | none | none |
| **#223** (subscription hotfix back-merge) | R1–R5 from session 64 — already live on fynla.org, now also on dev | none | none |
| **#212** (lifecycle email engine) | 5 campaigns (Empty Trialer, Engaged Trialer, Cancelled Trialer, Churned Subscriber, Lapsed Subscriber) · `LifecycleEngine` with dedup + failure isolation · signed magic-link routes + `LifecycleActionController` · scheduled daily at 08:30 UTC · `NotificationPreferences` web page (14 toggles) · mobile settings sync · 7 migrations · E2E test commands | **7 new** | **2 new** |

---

## Pre-deploy — before you upload anything

1. **Confirm which branch is currently deployed on csjones.co/fynla.** Per `feedback_dev_server_is_separate.md`, the dev server may be on a different branch than `dev` (last known state was `onboardingFyn`). If the server is NOT on the dev branch state, you are deploying MORE than this guide covers — upload the full `public/build/` directory and every PHP file, not just the delta.

2. **Assumes server is on `dev` at a state just before PR #220** (i.e. the last dev deploy was session 62 or earlier). If in doubt, treat as a full deploy: upload everything in `public/build/`, all PHP files on this list, run all migrations.

---

## 1. Build locally (already run by Claude — see below)

```bash
git checkout dev && git pull
./deploy/csjones-fynla/build.sh
```

This sets `VITE_BASE_PATH=/fynla/build/`, `VITE_ROUTER_BASE=/fynla/`, `VITE_REVOLUT_SANDBOX=true`, and writes to `public/build/`. Upload that whole directory to the server.

**Do NOT** run `npx vite build` or `npm run build` — `feedback_never_raw_vite_build.md`.

---

## 2. Upload `public/build/` (frontend assets)

Path on server: `~/www/csjones.co/fynla-app/public/build/`

25 Vue/JS/CSS source files changed — all compiled into the single `public/build/` output. Upload the whole directory (the hashed asset filenames will be different from what's there now, so older files can stay; Vite only references the new hashed names in the manifest).

New Vue components in this build:
- `resources/js/components/AppFooter.vue` (renamed from `Footer.vue`)
- `resources/js/components/AppNavbar.vue` (renamed from `Navbar.vue`)
- `resources/js/components/Investment/InvestmentHoldings.vue` (renamed from `Holdings.vue`)
- `resources/js/components/Investment/InvestmentPerformance.vue` (renamed from `Performance.vue`)
- `resources/js/components/Savings/SavingsRecommendations.vue` (renamed from `Recommendations.vue`)
- `resources/js/components/Public/ReviewCarousel.vue`
- `resources/js/components/Public/StaticFynChat.vue`
- `resources/js/components/UserProfile/NotificationPreferences.vue`
- `resources/js/views/Public/CampaignPage.vue`
- `resources/js/views/Public/NotFoundPage.vue`
- `resources/js/views/Public/QuickStartPage.vue`

Deleted source components (no action needed — they're only in `public/build/` if they were ever compiled; the build simply won't reference them anymore):
- `resources/js/components/Investment/Goals.vue`
- `resources/js/components/UserProfile/Settings.vue`
- Old single-word names (`Footer.vue`, `Navbar.vue`, `Holdings.vue`, `Performance.vue`, `Recommendations.vue`)

---

## 3. Upload backend PHP files

Path on server: `~/www/csjones.co/fynla-app/`

### New lifecycle engine (PR #212) — 30 files

```
app/Console/Commands/RunLifecycleEngine.php                       [NEW]
app/Console/Commands/RunLifecycleEngineE2ECleanup.php             [NEW]
app/Console/Commands/RunLifecycleEngineE2ETest.php                [NEW]
app/Console/Kernel.php                                            [MODIFIED — schedules lifecycle:run-daily at 08:30 UTC]
app/Http/Controllers/Api/NotificationPreferenceController.php     [NEW — web users]
app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php  [MODIFIED — adds lifecycle + estate_alerts]
app/Http/Controllers/Lifecycle/LifecycleActionController.php      [NEW — magic-link handlers]
app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php     [MODIFIED — adds 5 lifecycle + estate_alerts fields]
app/Mail/Lifecycle/CancelledTrialerMail.php                       [NEW]
app/Mail/Lifecycle/ChurnedSubscriberMail.php                      [NEW]
app/Mail/Lifecycle/EmptyTrialerMail.php                           [NEW]
app/Mail/Lifecycle/EngagedTrialerMail.php                         [NEW]
app/Mail/Lifecycle/LapsedSubscriberMail.php                       [NEW]
app/Models/DiscountCode.php                                       [MODIFIED — user_id/metadata/lifecycle_welcome]
app/Models/FeedbackResponse.php                                   [NEW]
app/Models/LifecycleEmailLog.php                                  [NEW]
app/Models/NotificationPreference.php                             [MODIFIED — 5 lifecycle fields]
app/Models/User.php                                               [MODIFIED — is_lifecycle_test_user cast + relations]
app/Providers/AppServiceProvider.php                              [MODIFIED — binds LifecycleEngine singleton]
app/Services/Lifecycle/Campaigns/CancelledTrialerCampaign.php     [NEW]
app/Services/Lifecycle/Campaigns/ChurnedSubscriberCampaign.php    [NEW]
app/Services/Lifecycle/Campaigns/EmptyTrialerCampaign.php         [NEW]
app/Services/Lifecycle/Campaigns/EngagedTrialerCampaign.php       [NEW]
app/Services/Lifecycle/Campaigns/LapsedSubscriberCampaign.php     [NEW]
app/Services/Lifecycle/Contracts/LifecycleCampaign.php            [NEW]
app/Services/Lifecycle/LifecycleDiscountCodeGenerator.php         [NEW]
app/Services/Lifecycle/LifecycleEngine.php                        [NEW]
app/Services/Lifecycle/LifecycleSnapshotService.php               [NEW]
app/Services/Payment/DiscountCodeService.php                      [MODIFIED — per-user lock + lifecycle_welcome type]
app/Services/Payment/TrialService.php                             [MODIFIED — restartTrial() for Campaign 1]
```

### Tech-debt cleanup (PR #220) — 16 files

Decimal:2 casts on monetary columns and float removals. All modifications (no new files, no deletions at the PHP level — it's all schema/cast hardening).

```
app/Models/Estate/Asset.php
app/Models/Estate/Gift.php
app/Models/Estate/IHTCalculation.php
app/Models/Estate/IHTProfile.php
app/Models/Estate/Liability.php
app/Models/ExpenditureProfile.php
app/Models/Investment/Holding.php
app/Models/Investment/InvestmentGoal.php
app/Models/Investment/RebalancingAction.php
app/Models/Investment/RiskProfile.php
app/Models/ProtectionProfile.php
app/Models/RecommendationTracking.php
app/Services/Estate/IntestacyCalculator.php
app/Services/Estate/NetWorthAnalyzer.php
app/Services/Investment/FeeAnalyzer.php
app/Services/Investment/ScenarioService.php
app/Services/Investment/TaxEfficiencyCalculator.php
app/Services/Onboarding/OnboardingService.php
app/Services/Protection/ComprehensiveProtectionPlanService.php
```

### Routes + config

```
config/lifecycle.php    [NEW]
routes/api.php          [MODIFIED — adds GET /api/notifications/preferences and PUT /api/notifications/preferences, both behind auth:sanctum]
routes/web.php          [MODIFIED — lifecycle magic-link routes + insights SEO route]
```

### Database seeder (NEW — required for `lifecycle:e2e-test`)

```
database/seeders/LifecycleTestSeeder.php    [NEW — `lifecycle:e2e-test` instantiates this; skip upload = command dies with "Class not found"]
```

### Database factories (modified for decimal:2 casts — PR #220)

Upload all 19 so model casts and factory output stay in sync:

```
database/factories/BusinessInterestFactory.php
database/factories/CashAccountFactory.php
database/factories/ChattelFactory.php
database/factories/CriticalIllnessPolicyFactory.php
database/factories/DBPensionFactory.php
database/factories/DCPensionFactory.php
database/factories/DisabilityPolicyFactory.php
database/factories/FamilyMemberFactory.php
database/factories/HouseholdFactory.php
database/factories/IncomeProtectionPolicyFactory.php
database/factories/LifeInsurancePolicyFactory.php
database/factories/MortgageFactory.php
database/factories/PersonalAccountFactory.php
database/factories/PropertyFactory.php
database/factories/RetirementProfileFactory.php
database/factories/SavingsAccountFactory.php
database/factories/SicknessIllnessPolicyFactory.php
database/factories/StatePensionFactory.php
database/factories/UserFactory.php
```

---

## 4. Upload Blade templates + email views

Path on server: `~/www/csjones.co/fynla-app/resources/views/`

```
resources/views/emails/lifecycle/_button.blade.php               [NEW]
resources/views/emails/lifecycle/_layout.blade.php               [NEW]
resources/views/emails/lifecycle/_quick-picks.blade.php          [NEW]
resources/views/emails/lifecycle/cancelled-trialer.blade.php     [NEW]
resources/views/emails/lifecycle/churned-subscriber.blade.php    [NEW]
resources/views/emails/lifecycle/empty-trialer.blade.php         [NEW]
resources/views/emails/lifecycle/engaged-trialer.blade.php       [NEW]
resources/views/emails/lifecycle/lapsed-subscriber.blade.php     [NEW]
resources/views/lifecycle/feedback-text-thanks.blade.php         [NEW]
resources/views/lifecycle/feedback-thanks.blade.php              [NEW]
```

---

## 5. Database migrations (7 new)

Path on server: `~/www/csjones.co/fynla-app/database/migrations/`

Upload ALL 7, then run `php artisan migrate --force` (see §7).

```
database/migrations/2026_04_14_122231_create_lifecycle_email_log_table.php
database/migrations/2026_04_14_122345_create_feedback_responses_table.php
database/migrations/2026_04_14_122424_add_user_id_and_metadata_to_discount_codes.php
database/migrations/2026_04_14_122508_add_is_lifecycle_test_user_to_users.php
database/migrations/2026_04_14_122545_add_lifecycle_columns_to_notification_preferences.php
database/migrations/2026_04_14_122656_add_subscriptions_indexes.php
database/migrations/2026_04_14_123409_add_lifecycle_welcome_to_discount_codes_type_enum.php
```

**Heads-up:** `add_subscriptions_indexes.php` adds composite indexes to `subscriptions`. If the table is large, expect a brief lock — still safe on dev, worth flagging for the prod deploy later.

---

## 6. Add env vars to `.env` on the server

Append these two lines to `~/www/csjones.co/fynla-app/.env`:

```env
# Lifecycle email engine (Phase 9)
LIFECYCLE_ENGINE_ENABLED=true
LIFECYCLE_TEST_RECIPIENT=chris@fynla.org
```

- `LIFECYCLE_ENGINE_ENABLED` — master kill switch. Daily scheduled run no-ops when false.
- `LIFECYCLE_TEST_RECIPIENT` — on the staging/dev env, all lifecycle emails are redirected to this address regardless of the target user, so you can safely exercise the engine without emailing real users. On prod this must be UNSET or null.

Production `.env` (fynla.org) should NOT have `LIFECYCLE_TEST_RECIPIENT` when this rolls to main later — deploy/fynla-org/.env.production is the template.

---

## 7. Files to DELETE on the server

Two Vue components were deleted as dead code. They're source-only so this is cosmetic, but worth cleaning up to match:

```
resources/js/components/Investment/Goals.vue
resources/js/components/UserProfile/Settings.vue
```

Five single-word components were renamed — delete the old filenames after confirming the new ones are in place:

```
resources/js/components/Footer.vue                    → AppFooter.vue
resources/js/components/Navbar.vue                    → AppNavbar.vue
resources/js/components/Investment/Holdings.vue       → InvestmentHoldings.vue
resources/js/components/Investment/Performance.vue    → InvestmentPerformance.vue
resources/js/components/Savings/Recommendations.vue   → SavingsRecommendations.vue
```

(These are only in `resources/js/` on the server, not in `public/build/`. The build output already references only the new names.)

---

## 7b. Infrastructure prerequisites (verify, don't skip)

**Queue worker:** NOT required. The lifecycle Mail classes do NOT implement `ShouldQueue`; `LifecycleEngine::run()` sends synchronously via `Mail::to()->send()`. Daily wall-clock time scales with (eligible users × 5 campaigns). Fine on dev.

**Cron:** `lifecycle:run-daily` depends on Laravel's `schedule:run` being invoked every minute from the system crontab. If existing daily jobs (`trials:send-reminders` at 09:00, `trials:expire` at 00:05, etc.) fire reliably on csjones.co/fynla, this one will too. If in doubt, verify on the server:

```bash
crontab -l | grep -i "schedule:run"
# Expected line: * * * * * cd ~/www/csjones.co/fynla-app && php artisan schedule:run >> /dev/null 2>&1
```

If the line is missing, the 08:30 daily job will silently never run. Add via `crontab -e` before smoke-testing.

**Mail config:** The server's existing `.env` `MAIL_*` values must already be valid (they are, since existing trial reminder emails work). No new mail changes needed.

---

## 8. SSH in and finalise

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app

# Apply the 7 new migrations
php artisan migrate --force

# Clear everything
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan optimize

# Verify scheduler has the new daily job
php artisan schedule:list | grep lifecycle
# Expected: lifecycle:run-daily  08:30 UTC
```

If you want to smoke-test the lifecycle engine immediately without waiting for the daily schedule:

```bash
# Dry run — lists which users would receive which campaign, no emails sent
php artisan lifecycle:run-daily --dry-run

# Full E2E with seeded test users
php artisan lifecycle:e2e-test
# …after testing, tear them down
php artisan lifecycle:e2e-cleanup
```

---

## 9. Smoke-test checklist on csjones.co/fynla

Per `critical_browser_testing_law.md` — click, fill, submit, verify. Do NOT mark these [x] without Playwright (or manual browser) interaction and result verification.

- [ ] `/quickstart` page loads, Review carousel renders, Fyn chat dock shows
- [ ] `/404` / any bad URL → NotFoundPage with docked Fyn chat
- [ ] `/features`, `/how-it-works`, `/pricing` — all render, no layout breaks
- [ ] Login → `/dashboard` → subscription status intact (session 64 hotfix)
- [ ] Navigate `/checkout` while expired-trial → `PlanSelectionModal` DOES NOT stack on top of `DataRetentionOverlay` (R1 from hotfix)
- [ ] Register with `.ac.uk` email → Student plan visible on plan modal (R2 from hotfix)
- [ ] Register with non-`.ac.uk` email → Student plan hidden; Standard card shows Student bullets (R3)
- [ ] `/profile/notifications` (new page) loads, all 14 toggles visible, toggling one persists on refresh
- [ ] Mobile `NotificationSettings` (if testing via iOS) — same 14 toggles, including the 5 new lifecycle ones
- [ ] Dry-run lifecycle engine: `php artisan lifecycle:run-daily --dry-run` — lists candidate users per campaign, no exceptions
- [ ] Trigger a test send manually (via `lifecycle:e2e-test`) and confirm the email lands in `chris@fynla.org` (the test recipient override)
- [ ] Magic link in the test email → lands on dashboard with `?lifecycle_discount=CODE` → PlanSelectionModal opens with the discount pre-populated (if user has an open trial-expired state)
- [ ] No regressions: Estate IHT dashboard, Net Worth, Investment holdings, Protection (tech-debt touched all these models)

Log errors to watch for in `storage/logs/laravel.log` during smoke test:
- Any `SQLSTATE` error (likely a migration that didn't run)
- `Class not found` (missing PHP file upload)
- `View [emails.lifecycle.*] not found` (missing Blade template upload)
- `Route [lifecycle.*] not defined` (routes/web.php not uploaded)
- `InvalidArgumentException: The file does not exist at path …/build/manifest.json` (public/build not uploaded)

---

## 10. Rollback

If the deploy breaks:

1. Restore the previous `public/build/` directory (keep the pre-deploy snapshot before uploading)
2. `php artisan migrate:rollback --step=7` — rolls back all 7 lifecycle migrations in reverse order
3. Remove the two new env vars from `.env`
4. `php artisan cache:clear && php artisan config:clear && php artisan optimize`

The 5 renamed components and 2 deletions don't need rolling back — they're only referenced by the new build output.

---

## References

- Session 63 tech-debt PR #220: `April/April18Updates/handover-tech-debt.md`
- Session 64 subscription hotfix: `April/April23Updates/production/deploy-fix-2026-04-23.md`
- Session 65 (today) PR triage: this session, PRs #212/#213/#214/#220/#221/#223
- Memory: `project_pr214_with_persona_split.md` — PR #214 still open, coupled with `feature/fyn-persona-split`, do NOT merge in isolation
