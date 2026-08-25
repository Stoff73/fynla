# Files Uploaded — Dev Deploy 23 April 2026

**Target:** `csjones.co/fynla` → `~/www/csjones.co/fynla-app/`
**Source:** `dev` branch at `36ea5a0`
**Server state detected via SSH:** last migration ran at `2026_04_15_153100_add_awin_tracking_to_payments_table` (batch 13). Server is at approximately `origin/onboardingFyn` state — NOT main. Deploy spans **4 PRs** (#212, #220, #221, #223) PLUS the insights CMS work from earlier main commits that was never deployed to dev server.

**Scale:** 167 files to upload (71 new + 96 modified), 9 files to delete, 12 migrations pending, 1 new PHP dependency, 5 new folders to create.

> Note: earlier drafts of this header said "173" — that was a pre-filter count. The actual Section A checklist (and upload) is 167. Items stripped between draft and final: `package.json`, `package-lock.json`, `tailwind.config.js`, and 3 subdirectory `CLAUDE.md` docs (all build-machine or developer docs, not server-relevant).

---

## 🚨 Critical pre-checks before any file uploads

- [x] **composer install will be required** — `composer.json` adds `intervention/image: ^4.0` which does not exist in server's `vendor/intervention/` (verified via SSH). After uploading `composer.json` + `composer.lock`, SSH in and run `composer install --no-dev --optimize-autoloader`.
- [x] **5 new directories need to exist on server** — SiteGround File Manager will create them when uploading the first file in each, but flagging explicitly:
  - `app/Services/Lifecycle/`
  - `app/Services/Lifecycle/Campaigns/`
  - `app/Services/Lifecycle/Contracts/`
  - `app/Http/Controllers/Lifecycle/`
  - `app/Mail/Lifecycle/`
  - `resources/views/emails/lifecycle/`
  - `resources/views/lifecycle/`
- [x] **Back up the existing `public/build/` on the server** before overwriting — in case rollback is needed
- [x] **Back up `.env` on the server** before any changes

---

## Section A: Files to UPLOAD (71 new + 96 modified = 167 total)

### A1. Frontend build (overwrite entire `public/build/`)

- [x] Upload local `public/build/` (8.1 MB, 318 PWA precache entries) → `~/www/csjones.co/fynla-app/public/build/`
  - This replaces all Vue/JS/CSS compiled output.
  - Individual `resources/js/*.vue` / `*.js` files are NOT uploaded directly — they're compiled into `public/build/`.

### A2. New PHP files — 41 files

#### Lifecycle engine (PR #212) — 22 files

- [x] `app/Console/Commands/RunLifecycleEngine.php`
- [x] `app/Console/Commands/RunLifecycleEngineE2ECleanup.php`
- [x] `app/Console/Commands/RunLifecycleEngineE2ETest.php`
- [x] `app/Http/Controllers/Api/NotificationPreferenceController.php`
- [x] `app/Http/Controllers/Lifecycle/LifecycleActionController.php`
- [x] `app/Mail/Lifecycle/CancelledTrialerMail.php`
- [x] `app/Mail/Lifecycle/ChurnedSubscriberMail.php`
- [x] `app/Mail/Lifecycle/EmptyTrialerMail.php`
- [x] `app/Mail/Lifecycle/EngagedTrialerMail.php`
- [x] `app/Mail/Lifecycle/LapsedSubscriberMail.php`
- [x] `app/Models/FeedbackResponse.php`
- [x] `app/Models/LifecycleEmailLog.php`
- [x] `app/Services/Lifecycle/Contracts/LifecycleCampaign.php`
- [x] `app/Services/Lifecycle/Campaigns/CancelledTrialerCampaign.php`
- [x] `app/Services/Lifecycle/Campaigns/ChurnedSubscriberCampaign.php`
- [x] `app/Services/Lifecycle/Campaigns/EmptyTrialerCampaign.php`
- [x] `app/Services/Lifecycle/Campaigns/EngagedTrialerCampaign.php`
- [x] `app/Services/Lifecycle/Campaigns/LapsedSubscriberCampaign.php`
- [x] `app/Services/Lifecycle/LifecycleDiscountCodeGenerator.php`
- [x] `app/Services/Lifecycle/LifecycleEngine.php`
- [x] `app/Services/Lifecycle/LifecycleSnapshotService.php`
- [x] `config/lifecycle.php`

#### Insights CMS (PR #219, backlog from main) — 19 files

- [x] `app/Http/Controllers/Api/Admin/InsightArticleController.php`
- [x] `app/Http/Controllers/Api/Admin/InsightImageController.php`
- [x] `app/Http/Controllers/Api/Admin/InsightTemplateController.php`
- [x] `app/Http/Controllers/Api/Public/InsightController.php`
- [x] `app/Http/Middleware/InsightsSeoMetaInjector.php`
- [x] `app/Http/Requests/Admin/Insights/StoreInsightArticleRequest.php`
- [x] `app/Http/Requests/Admin/Insights/StoreInsightTemplateRequest.php`
- [x] `app/Http/Requests/Admin/Insights/UpdateInsightArticleRequest.php`
- [x] `app/Http/Requests/Admin/Insights/UploadInsightImageRequest.php`
- [x] `app/Http/Resources/Insights/InsightArticleListResource.php`
- [x] `app/Http/Resources/Insights/InsightArticleResource.php`
- [x] `app/Http/Resources/Insights/InsightTemplateResource.php`
- [x] `app/Jobs/PublishScheduledInsightsJob.php`
- [x] `app/Models/Insights/InsightArticle.php`
- [x] `app/Models/Insights/InsightArticleRevision.php`
- [x] `app/Models/Insights/InsightTemplate.php`
- [x] `app/Observers/InsightArticleObserver.php`
- [x] `app/Services/Insights/BlockValidator.php`
- [x] `app/Services/Insights/InsightArticleService.php`
- [x] `app/Services/Insights/InsightImageService.php`
- [x] `app/Services/Insights/InsightSeoService.php`
- [x] `app/Services/Insights/InsightTemplateService.php`

### A3. New database files — 17 files

#### New migrations (to be applied, in order) — 12 files

- [x] `database/migrations/2026_04_14_122231_create_lifecycle_email_log_table.php`
- [x] `database/migrations/2026_04_14_122345_create_feedback_responses_table.php`
- [x] `database/migrations/2026_04_14_122424_add_user_id_and_metadata_to_discount_codes.php`
- [x] `database/migrations/2026_04_14_122508_add_is_lifecycle_test_user_to_users.php`
- [x] `database/migrations/2026_04_14_122545_add_lifecycle_columns_to_notification_preferences.php`
- [x] `database/migrations/2026_04_14_122656_add_subscriptions_indexes.php`
- [x] `database/migrations/2026_04_14_123409_add_lifecycle_welcome_to_discount_codes_type_enum.php`
- [x] `database/migrations/2026_04_17_090001_create_insight_templates_table.php`
- [x] `database/migrations/2026_04_17_090002_create_insight_articles_table.php`
- [x] `database/migrations/2026_04_17_090003_create_insight_article_revisions_table.php`
- [x] `database/migrations/2026_04_18_090000_expand_insight_article_categories.php`
- [x] `database/migrations/2026_04_18_100000_add_authors_to_insight_articles_table.php`

#### New factories — 3 files

- [x] `database/factories/Insights/InsightArticleFactory.php`
- [x] `database/factories/Insights/InsightArticleRevisionFactory.php`
- [x] `database/factories/Insights/InsightTemplateFactory.php`

#### New seeders — 2 files

- [x] `database/seeders/ExistingInsightsMetadataSeeder.php`
- [x] `database/seeders/LifecycleTestSeeder.php`

### A4. New Blade templates — 10 files

#### Lifecycle email templates

- [x] `resources/views/emails/lifecycle/_button.blade.php`
- [x] `resources/views/emails/lifecycle/_layout.blade.php`
- [x] `resources/views/emails/lifecycle/_quick-picks.blade.php`
- [x] `resources/views/emails/lifecycle/cancelled-trialer.blade.php`
- [x] `resources/views/emails/lifecycle/churned-subscriber.blade.php`
- [x] `resources/views/emails/lifecycle/empty-trialer.blade.php`
- [x] `resources/views/emails/lifecycle/engaged-trialer.blade.php`
- [x] `resources/views/emails/lifecycle/lapsed-subscriber.blade.php`

#### Lifecycle feedback views

- [x] `resources/views/lifecycle/feedback-text-thanks.blade.php`
- [x] `resources/views/lifecycle/feedback-thanks.blade.php`

### A5. Modified PHP files — 49 files

#### Root + providers + kernel

- [x] `app/Console/Kernel.php` *(schedules `lifecycle:run-daily` at 08:30 UTC)*
- [x] `app/Providers/AppServiceProvider.php` *(binds `LifecycleEngine` as singleton)*
- [x] `app/Providers/EventServiceProvider.php`
- [x] `app/Http/Kernel.php`

#### Controllers + requests + middleware — 9 files

- [x] `app/Http/Controllers/Api/AdminController.php`
- [x] `app/Http/Controllers/Api/AiChatController.php`
- [x] `app/Http/Controllers/Api/PaymentController.php`
- [x] `app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php`
- [x] `app/Http/Controllers/Api/WebhookController.php`
- [x] `app/Http/Middleware/PreviewWriteInterceptor.php`
- [x] `app/Http/Middleware/SanitizeInput.php`
- [x] `app/Http/Requests/UpdateIncomeOccupationRequest.php`
- [x] `app/Http/Requests/UpdatePersonalInfoRequest.php`
- [x] `app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php`

#### Models — 15 files (mostly PR #220 decimal:2 casts)

- [x] `app/Models/DiscountCode.php`
- [x] `app/Models/Estate/Asset.php`
- [x] `app/Models/Estate/Gift.php`
- [x] `app/Models/Estate/IHTCalculation.php`
- [x] `app/Models/Estate/IHTProfile.php`
- [x] `app/Models/Estate/Liability.php`
- [x] `app/Models/ExpenditureProfile.php`
- [x] `app/Models/Investment/Holding.php`
- [x] `app/Models/Investment/InvestmentGoal.php`
- [x] `app/Models/Investment/RebalancingAction.php`
- [x] `app/Models/Investment/RiskProfile.php`
- [x] `app/Models/NotificationPreference.php`
- [x] `app/Models/ProtectionProfile.php`
- [x] `app/Models/RecommendationTracking.php`
- [x] `app/Models/User.php`

#### Services — 13 files

- [x] `app/Services/AI/AiToolDefinitions.php`
- [x] `app/Services/AI/Prompts/ComplianceRules.php`
- [x] `app/Services/AI/StructuredResponseValidator.php`
- [x] `app/Services/AI/SystemPromptBuilder.php`
- [x] `app/Services/AI/XaiToolDefinitions.php`
- [x] `app/Services/Estate/IntestacyCalculator.php`
- [x] `app/Services/Estate/NetWorthAnalyzer.php`
- [x] `app/Services/Investment/FeeAnalyzer.php`
- [x] `app/Services/Investment/ScenarioService.php`
- [x] `app/Services/Investment/TaxEfficiencyCalculator.php`
- [x] `app/Services/Onboarding/JourneyFieldResolver.php`
- [x] `app/Services/Onboarding/JourneyStateService.php`
- [x] `app/Services/Onboarding/OnboardingService.php`
- [x] `app/Services/Payment/DiscountCodeService.php`
- [x] `app/Services/Payment/TrialService.php`
- [x] `app/Services/Protection/ComprehensiveProtectionPlanService.php`

#### Agents / constants / traits — 3 files

- [x] `app/Agents/CoordinatingAgent.php`
- [x] `app/Constants/QuerySchemas.php`
- [x] `app/Traits/HasAiChat.php`

### A6. Modified database files — 21 files

#### Migrations (existing — re-upload to stay in sync with strict_types from PR #220)

- [x] `database/migrations/2025_12_30_103416_add_advisor_fee_to_investment_accounts.php`
- [x] `database/migrations/2025_12_30_110842_add_rebalance_threshold_to_investment_accounts.php`
- [x] `database/migrations/2025_12_30_160326_add_account_name_to_investment_accounts.php`
- [x] `database/migrations/2026_01_08_091458_make_form_fields_optional.php`
- [x] `database/migrations/2026_01_10_131616_add_payday_day_of_month_to_users_table.php`
- [x] `database/migrations/2026_01_12_115104_add_dashboard_widget_order_to_users.php`
- [x] `database/migrations/2026_01_17_092200_add_joint_owner_name_to_chattels_table.php`
- [x] `database/migrations/2026_01_24_091552_add_monthly_interest_portion_to_mortgages_table.php`
- [x] `database/migrations/2026_01_28_163920_create_monte_carlo_cache_table.php`
- [x] `database/migrations/2026_01_29_082107_add_private_investment_fields_to_investment_accounts_table.php`
- [x] `database/migrations/2026_01_29_140000_add_employee_share_scheme_fields_to_investment_accounts_table.php`
- [x] `database/migrations/2026_01_31_135615_add_bond_fields_to_investment_accounts_table.php`
- [x] `database/migrations/2026_01_31_154201_add_badr_fields_to_investment_accounts_table.php`
- [x] `database/migrations/2026_02_17_120040_add_account_name_to_savings_accounts_table.php`
- [x] `database/migrations/2026_02_21_104352_add_soft_deletes_to_business_interests_and_chattels.php`
- [x] `database/migrations/2026_02_21_104355_add_joint_owner_foreign_keys_to_business_interests_and_chattels.php`
- [x] `database/migrations/2026_03_18_100000_add_soft_deletes_to_key_models.php`
- [x] `database/migrations/2026_03_18_100001_add_unique_constraints_to_has_one_tables.php`
- [x] `database/migrations/2026_03_18_100002_fix_indexes_and_constraints.php`

*(These 19 existing-migration files had `declare(strict_types=1);` added in PR #220 — cosmetic, doesn't re-run since migrations table already has them.)*

#### Factories (PR #220 decimal:2 alignment)

- [x] `database/factories/BusinessInterestFactory.php`
- [x] `database/factories/CashAccountFactory.php`
- [x] `database/factories/ChattelFactory.php`
- [x] `database/factories/CriticalIllnessPolicyFactory.php`
- [x] `database/factories/DBPensionFactory.php`
- [x] `database/factories/DCPensionFactory.php`
- [x] `database/factories/DisabilityPolicyFactory.php`
- [x] `database/factories/FamilyMemberFactory.php`
- [x] `database/factories/HouseholdFactory.php`
- [x] `database/factories/IncomeProtectionPolicyFactory.php`
- [x] `database/factories/LifeInsurancePolicyFactory.php`
- [x] `database/factories/MortgageFactory.php`
- [x] `database/factories/PersonalAccountFactory.php`
- [x] `database/factories/PropertyFactory.php`
- [x] `database/factories/RetirementProfileFactory.php`
- [x] `database/factories/SavingsAccountFactory.php`
- [x] `database/factories/SicknessIllnessPolicyFactory.php`
- [x] `database/factories/StatePensionFactory.php`
- [x] `database/factories/UserFactory.php`

#### Seeders

- [x] `database/seeders/ChrisUserSeeder.php`
- [x] `database/seeders/DatabaseSeeder.php`

### A7. Modified routes + config — 2 files

- [x] `routes/api.php` *(adds `GET /api/notifications/preferences` + `PUT /api/notifications/preferences`, plus insights admin/public routes)*
- [x] `routes/web.php` *(adds lifecycle magic-link routes + `/insights/{slug}` SEO middleware route)*

### A8. Modified Blade templates — 4 files

- [x] `resources/views/app.blade.php`
- [x] `resources/views/emails/invoice.blade.php`
- [x] `resources/views/emails/payment-confirmation.blade.php`
- [x] `resources/views/emails/payment-failed.blade.php`

### A9. Composer (REQUIRES `composer install` on server after upload)

- [x] `composer.json` *(adds `intervention/image: ^4.0`)*
- [x] `composer.lock`

---

## Section B: Files to DELETE on the server (9 files)

These exist on the current server deploy but have been removed on dev. Stale PHP files here can cause autoloader confusion if anything references them by classname.

### B1. Superseded onboarding state machine (replaced by work on `feature/fyn-persona-split`)

- [x] DELETE `app/Services/Onboarding/OnboardingChatDirector.php`
- [x] DELETE `app/Services/Onboarding/OnboardingPromptBuilder.php`
- [x] DELETE `app/Services/Onboarding/OnboardingStateMachine.php`
- [x] DELETE `app/Services/Onboarding/OnboardingValueInterpreter.php`
- [x] DELETE `app/Services/Onboarding/SpouseLinkingService.php`
- [x] DELETE `app/Exceptions/SpouseCollisionException.php` *(already absent on server)*
- [x] DELETE `config/onboarding.php`

### B2. Dead observer + guard

- [x] DELETE `app/Observers/TrustObserver.php` *(already absent on server)*
- [x] DELETE `app/Services/AI/Prompts/EmptyDataGuard.php`

### B3. DO NOT DELETE — migration files that ran on server

The two migrations below were removed from `dev` but have ALREADY RUN on the server (batch 12). Their schema changes (`users.onboarding_fyn_state` column, `civil_partnership` enum value) are still in the DB and dev's code doesn't depend on them. **Leave the files alone on the server** — deleting them would leave orphan records in the `migrations` table and break any future `migrate:rollback`.

- ⚠️ **Do not touch** `database/migrations/2026_04_15_090000_add_onboarding_fyn_state_to_users.php`
- ⚠️ **Do not touch** `database/migrations/2026_04_15_091500_add_civil_partnership_to_users_marital_status.php`

### B4. Optional housekeeping — old Vue source files (harmless)

Renamed to prefixed versions in PR #220. Source files only — not referenced by the new build.

- [ ] (Optional) DELETE `resources/js/components/Footer.vue` *(renamed to `AppFooter.vue`)*
- [ ] (Optional) DELETE `resources/js/components/Navbar.vue` *(renamed to `AppNavbar.vue`)*
- [ ] (Optional) DELETE `resources/js/components/Investment/Holdings.vue` *(renamed to `InvestmentHoldings.vue`)*
- [ ] (Optional) DELETE `resources/js/components/Investment/Performance.vue` *(renamed to `InvestmentPerformance.vue`)*
- [ ] (Optional) DELETE `resources/js/components/Savings/Recommendations.vue` *(renamed to `SavingsRecommendations.vue`)*
- [ ] (Optional) DELETE `resources/js/components/Investment/Goals.vue` *(dead code, removed in PR #220)*
- [ ] (Optional) DELETE `resources/js/components/UserProfile/Settings.vue` *(dead code, removed in PR #220)*

---

## Section C: Files I am DELIBERATELY NOT including in the upload

Documenting for transparency — these are in the dev diff but have no deploy target:

- **Tests** (`tests/**`) — 43 files changed. Not served; Pest runs locally or in CI, never production.
- **Docs** (`docs/**`) — 37 files. Local knowledge base, not deploy-relevant.
- **Project memory** — `.claude/**`, `CLAUDE.md`, `CSJTODO.md`, `April/**`, `tech-debt-report.md`, `docs/tech-debt-report-full.md`. Never deployed.
- **Build config** — `package.json`, `package-lock.json`, `tailwind.config.js`, `vite.config.js`. Used during build, not at runtime on server. *(The npm deps have already been incorporated into the local build output.)*
- **Deploy scripts** — `deploy/**`. Runs locally, not deployed.
- **AWIN test fixtures** (`awin/**`) — 7 local files, never deployed.

---

## Section D: Post-upload server commands

SSH: `ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co`
Path: `cd ~/www/csjones.co/fynla-app`

### D1. Install composer dependencies

- [x] `composer install --no-dev --optimize-autoloader`
  - Installs `intervention/image ^4.0` and any transitive deps.
  - Expected new packages in `vendor/`: `intervention/`, `intervention/gif`, plus GD/Imagick bindings.

### D2. Update `.env` with new lifecycle vars

Append (or update) these two lines:

```env
LIFECYCLE_ENGINE_ENABLED=true
LIFECYCLE_TEST_RECIPIENT=chris@fynla.org
```

- [x] `.env` updated with `LIFECYCLE_ENGINE_ENABLED=true`
- [x] `.env` updated with `LIFECYCLE_TEST_RECIPIENT=chris@fynla.org`

### D3. Run pending migrations (12 total)

- [x] `php artisan migrate --force`
  - Should apply all 12 pending migrations in timestamp order
  - Verify with `php artisan migrate:status | tail -15` — the 12 listed in §A3 should show `Ran`

### D4. Clear all caches

- [x] `php artisan cache:clear`
- [x] `php artisan config:clear`
- [x] `php artisan view:clear`
- [x] `php artisan route:clear`
- [x] `php artisan optimize`

### D5. Seed insights + any refreshed data

- [x] `php artisan db:seed --class=ExistingInsightsMetadataSeeder --force` *(seeds 8 bespoke articles' metadata)*
- [x] (Optional) `php artisan db:seed --force` *(full reseed — only if you want to refresh all data including preview personas, tax config, etc.)*

### D6. Verify scheduler picked up new job

- [x] `php artisan schedule:list | grep lifecycle`
  - Expected: `lifecycle:run-daily` listed at `08:30 UTC`

### D7. Verify crontab runs scheduler (prerequisite for daily job)

- [ ] `crontab -l | grep -c "schedule:run"` → should return `1` or more
  - Line expected: `* * * * * cd ~/www/csjones.co/fynla-app && php artisan schedule:run >> /dev/null 2>&1`
  - If 0, the daily 08:30 lifecycle job will silently never run. Fix with `crontab -e`.

---

## Section E: Smoke-test checklist (after post-upload)

Per `critical_browser_testing_law.md` — click/fill/submit, don't just visit.

- [ ] `https://csjones.co/fynla/` loads, no blank-page/blank-manifest errors in console
- [ ] `https://csjones.co/fynla/quickstart` — QuickStart campaign page renders with Review carousel, docked Fyn chat
- [ ] `https://csjones.co/fynla/404` or any bad URL → NotFoundPage with docked Fyn chat
- [ ] `https://csjones.co/fynla/pricing` — Family card shows Parents/Children-free bullets; Student card visible
- [ ] `https://csjones.co/fynla/insights` — Insights hub renders, articles load
- [ ] `https://csjones.co/fynla/insights/<slug>` — individual insight renders with SEO meta (check `<title>` + `<meta name="description">` in view-source)
- [ ] Login → `/dashboard` loads, subscription data shows
- [ ] Navigate to `/profile/notifications` — all 14 toggles render, toggling persists on refresh
- [ ] Expired-trial flow: PlanSelectionModal + DataRetentionOverlay DO NOT stack on `/checkout` (session 64 hotfix)
- [ ] Admin insights editor (`/admin/insights` or similar) — creating/editing an article succeeds (requires intervention/image for hero image upload)
- [ ] Dry-run lifecycle engine: `php artisan lifecycle:run-daily --dry-run` — lists candidate users per campaign, no exceptions
- [ ] E2E lifecycle test: `php artisan lifecycle:e2e-test` (seeds 5 test users, sends to `chris@fynla.org`), then `php artisan lifecycle:e2e-cleanup`
- [ ] Magic link in E2E test email → redirects to dashboard with `?lifecycle_discount=CODE` → PlanSelectionModal opens with discount pre-populated (for users in expired-trial state)
- [ ] No regressions in Estate IHT, Net Worth, Investment holdings, Protection dashboards (all models touched by PR #220 casts)

### Log lines to watch for during smoke test

Tail `storage/logs/laravel.log` while clicking through:

- `SQLSTATE[42S02]: Base table or view not found` — missing migration run
- `Class 'App\Services\Lifecycle\…' not found` — PHP file not uploaded / autoloader needs `composer install`
- `View [emails.lifecycle.…] not found` — Blade template not uploaded
- `Route [lifecycle.…] not defined` — `routes/web.php` not uploaded
- `Class 'Intervention\Image\ImageManager' not found` — `composer install` not run

---

## Section F: Rollback procedure

If something breaks badly:

1. Restore the previous `public/build/` backup
2. SSH in and run:
   ```bash
   php artisan migrate:rollback --step=12    # rolls back all 12 lifecycle + insights migrations
   composer install --no-dev --optimize-autoloader    # (no-op if you didn't already run install, otherwise rolls back with previous composer.lock)
   php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
   ```
3. Restore the previous `.env` backup (removes the 2 lifecycle env vars)
4. Restore the previous PHP files from your file-manager backup

Or: `git checkout` the previous branch locally, rebuild, and re-upload the old `public/build/` + PHP files.

---

## References

- Deploy guide: [devUpdateDeploy.md](./devUpdateDeploy.md)
- PR #212 (lifecycle engine) — merged 23 Apr via admin-bypass
- PR #220 (tech debt) — merged 23 Apr (earlier today)
- PR #221 (campaign pages) — merged 23 Apr (earlier today)
- PR #223 (main → dev back-merge, subscription hotfix) — merged 23 Apr
- PR #219 (insights CMS) — previously merged to main, now reaching csjones dev for the first time
- Server probed via SSH: confirmed at `origin/onboardingFyn` state, last migration `2026_04_15_153100`
