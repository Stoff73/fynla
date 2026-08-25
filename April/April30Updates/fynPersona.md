# Deploy guide — `feature/fyn-persona-split` → dev (csjones.co/fynla)

**Branch:** `feature/fyn-persona-split`
**Tip commit:** `6ce6510` — `fix(tests): exclude Eval dir from Feature suite` (over `5dfe1a3` merge of `origin/dev`, over `fad6e88` `/tax-strategy` redesign)
**Range:** 255 commits ahead of `origin/dev`, 0 behind (post-merge — fully caught up)
**Target environment:** dev / staging — `https://csjones.co/fynla`
**Target server:** SiteGround `~/www/csjones.co/fynla-app/` (sibling-dir layout — see memory `reference_csjones_sibling_dir.md`)
**SSH alias:** `u163-ptanegf9edny@ssh.csjones.co:18765`
**Generated:** 30 April 2026 — session 123 (post-redesign commit), regenerated session 124 after merge of `origin/dev` (PR #238 news/RSS/lifecycle)

> **This is a large deploy.** 255 commits. **132 app PHP files** + 4 config + 1 route file. **30 Vue files**. 24 new migrations. 5 seeders + 2 factories. 1 renamed PHP class (`SystemPromptBuilder` → `AdvicePromptBuilder`). 2 deleted controllers/middleware + 8 deleted Python scripts. 3 new composer dependencies. **Do not skip the pre-flight checklist.**

---

## ⚠ Post-merge note (session 124, 2026-04-30 late evening)

Before this deploy was attempted, `feature/fyn-persona-split` had diverged 33 commits behind `origin/dev` because PR #238 (`feature/phailanx/news-rss-lifecycle-emails`) merged to dev on 2026-04-28. That PR added the news hub, RSS feeds, public newsletter subscribe + confirm/unsubscribe flow, lifecycle email system, NewsSubscriber model + admin endpoints. Deploying the feature branch as-is would have regressed all of that on csjones.co.

`origin/dev` was merged into `feature/fyn-persona-split` (commit `5dfe1a3`) before this guide was regenerated. The merge surfaced 4 conflict files (`CLAUDE.md`, `CSJTODO.md`, `routes/api.php`, `tech-debt-report.md`), all additive and resolved with both sides' content preserved (or HEAD where both were point-in-time docs). PHP lint clean, all 614 routes load, local HTTP smoke passed for `/api/news`, `/api/public/tax-allowances`, `/feed/news.xml`, `/savetax`, `/news`. Backup at `backup/fyn-persona-split-pre-merge`.

A pre-existing pest test-infrastructure clash (Eval testsuite was a subdirectory of Feature, causing every eval test to double-bind `Tests\TestCase`) was diagnosed during merge verification and fixed in commit `6ce6510` — one-line `<exclude>` in `phpunit.xml`. Pest now runs cleanly on default (44 + 6 + 22 sample tests pass).

**Implication for this deploy:** the news/RSS/lifecycle code already exists on the dev server (assuming dev was deployed at or after `c9b0a80`). The file list below reflects the **post-merge** `git diff origin/dev...HEAD --name-status` — anything that existed on both sides falls out automatically, so news/lifecycle files are NOT duplicated in the upload list. Verify with `git log --oneline ~/www/csjones.co/fynla-app -3` during pre-flight.

---

## What's in this branch (one-line summary)

The fyn-persona-split branch lands the full Two-Fyn architecture (Onboarding Fyn / Advice Fyn split per `April/April24Updates/spec/00-canonical.md`), the SaveTax campaign (state-machine flow + 17 deterministic tax-strategy classes + `/tax-strategy` dashboard), the AI audit hash chain, conversation summariser, eval recording infrastructure, idempotency middleware, household tax-strategy schema, civil-partnership marital-status, and the redesigned `/tax-strategy` page.

---

## Pre-flight checklist — DO BEFORE BUILDING

| Step | Why | How |
|---|---|---|
| 1. **Verify dev `.env` has `AI_AUDIT_HMAC_KEY`** | New env var (S0.12 audit hash chain). Falls back to `APP_KEY` if unset, but explicit is safer. | SSH in, `grep AI_AUDIT_HMAC_KEY .env`. If absent, generate `openssl rand -base64 32` and add. |
| 2. **Remove dead `AGENT_INTERNAL_TOKEN`** | The internal-agent endpoint is deleted on this branch. Leaving the var is harmless but tidy. | `sed -i '/^AGENT_INTERNAL_TOKEN=/d' .env` after the deploy verifies. Optional. |
| 3. **DB backup** | 24 migrations modify users / dc_pensions / ai_conversations / ai_messages and add 7 new tables. Rollback needs schema state. | SiteGround `phpMyAdmin → Export → Custom → SQL → Save`. Keep until smoke-tested. |
| 4. **Confirm `dev` branch tip on `csjones.co`** | Post-merge file lists assume the server is at `c9b0a80` (PR #238 already deployed). If it's older, the news/RSS/lifecycle files need to be uploaded manually too — pull them from the merge commit `5dfe1a3` parent set. CLAUDE.md memory `feedback_dev_server_is_separate.md` applies. | SSH in, `cd ~/www/csjones.co/fynla-app && git log --oneline -3`. Should match `git log --oneline origin/dev -3` locally. If older than `c9b0a80`, add the news/lifecycle file batch to the upload (see "Pre-merge dev catch-up" appendix at end of guide). |
| 5. **Check disk space** | Composer install + Vite build artefacts + log growth. | SSH `df -h ~`. Need at least 200MB free. |
| 6. **Stop scheduled jobs while deploying** | Avoid race with `ConversationSummariserJob` / `AiAuditRetentionJob` / `AiIdempotencyCleanupJob` writing during migrate. | Edit `.cron` in cPanel temporarily, OR rely on `php artisan down`. |
| 7. **Run the test slice locally before deploy** | Confirm the working tree builds cleanly. | `./vendor/bin/pest tests/Unit/Services/Tax tests/Feature/AI tests/Feature/Onboarding --testsuite=Architecture` — 940+ pass expected. |

---

## Phase 1 — Build locally

```bash
# from /Users/CSJ/Desktop/fynla on the feature/fyn-persona-split branch
git status                  # must be clean
git log --oneline -3        # confirm tip is fad6e88

./deploy/csjones-fynla/build.sh
```

This runs Vite with the dev-target environment vars baked in:
- `VITE_BASE_PATH=/fynla/build/`
- `VITE_ROUTER_BASE=/fynla/`
- `VITE_API_BASE_URL=https://csjones.co/fynla`
- `VITE_REVOLUT_SANDBOX=true`

**Output:** fully populated `public/build/` directory with new manifest + JS chunks for the Two-Fyn split, SaveTax campaign, the redesigned `/tax-strategy` page, eval recording admin panel, conversation summariser store changes, and 40+ other Vue components.

**Verify build:** `ls public/build/assets/ | wc -l` should be > 200 files.

---

## Phase 2 — Files to upload

### 2a. Vue build output (mandatory)

Upload **the entire `public/build/`** directory to `~/www/csjones.co/public_html/fynla/public/build/`, replacing the server copy. (Build artefacts go under `public_html/fynla/public/`, NOT `fynla-app/public/` — the document root serves directly from `public_html/`.)

> ⚠️ **Memory law `feedback_warn_before_spa_rebuild.md`:** before overwriting on the server, rename the existing `public/build/` to `public/build.old/` first; if anything goes wrong with the new bundle, rename back. After the upload looks healthy, `cp -rn build.old/. build/` to merge any cached files the new build doesn't replace, then delete `build.old`.

### 2b. PHP source (132 app files + 4 config + 1 route file = 137 PHP)

> **Destination:** all paths below upload to `~/www/csjones.co/fynla-app/<same-relative-path>` (the Laravel app dir). Per memory `reference_csjones_sibling_dir.md`. Do NOT upload to `public_html/fynla/`.

> The list below is from `git diff origin/dev...HEAD --name-status` after the session-124 merge. Anything in `app/Http/Controllers/Api/Public/News*`, `app/Models/News/`, `app/Mail/Lifecycle/`, `app/Mail/Newsletter*`, `app/Http/Controllers/{FeedController,NewsletterActionController}.php`, and the news/newsletter blades is **not** in this list — it's already on dev via PR #238 and does not need uploading (assuming pre-flight Step 4 passed).

```
app/Agents/CoordinatingAgent.php             (Two-Fyn dispatch in sendMessage)
app/Agents/EstateAgent.php
app/Agents/GoalsAgent.php
app/Agents/InvestmentAgent.php
app/Agents/ProtectionAgent.php
app/Agents/RetirementAgent.php
app/Agents/SavingsAgent.php

app/Console/Commands/AiAuditVerifyChainCommand.php          [NEW]
app/Console/Commands/BackfillAiDailyUsage.php               [NEW]
app/Console/Commands/EvalPurgeCommand.php                   [NEW]
app/Console/Commands/EvalRecordCommand.php                  [NEW]
app/Console/Commands/EvalShowCommand.php                    [NEW]
app/Console/Commands/SummariseStaleConversationsCommand.php [NEW]
app/Console/Commands/ResetPreviewData.php
app/Console/Kernel.php                                      (schedules new jobs)

app/Constants/QuerySchemas.php
app/Constants/UpdateRecordAllowlist.php                     [NEW]

app/DataTransferObjects/StrategyRecommendation.php          [NEW]
app/DataTransferObjects/TaxStrategyOutputDTO.php            [NEW]
app/DataTransferObjects/TaxStrategyOverridesDTO.php         [NEW]

app/Enums/StrategyCategory.php                              [NEW]
app/Enums/StrategyPriority.php                              [NEW]

app/Events/Eval/AgentDecision.php                           [NEW]
app/Events/Eval/EngineCalled.php                            [NEW]
app/Events/Eval/GateChecked.php                             [NEW]

app/Exceptions/SpouseCollisionException.php                 [NEW]

app/Http/Controllers/Api/Admin/EvalRecordingController.php  [NEW]
app/Http/Controllers/Api/AdminController.php
app/Http/Controllers/Api/AiAuditController.php
app/Http/Controllers/Api/AiChatController.php
app/Http/Controllers/Api/AuthController.php
app/Http/Controllers/Api/EvalAuthController.php             [NEW]
app/Http/Controllers/Api/Public/TaxAllowancesController.php [NEW]
app/Http/Controllers/Api/TaxStrategyController.php          [NEW]

app/Http/Kernel.php                                         (registers IdempotencyKeyMiddleware)
app/Http/Middleware/IdempotencyKeyMiddleware.php            [NEW]
app/Http/Middleware/PreviewWriteInterceptor.php

app/Http/Requests/RegisterRequest.php
app/Http/Requests/TaxStrategyCalculateRequest.php           [NEW]
app/Http/Requests/UpdateIncomeOccupationRequest.php
app/Http/Requests/UpdatePersonalInfoRequest.php
app/Http/Resources/UserResource.php

app/Jobs/AiAuditRetentionJob.php                            [NEW]
app/Jobs/AiIdempotencyCleanupJob.php                        [NEW]
app/Jobs/ConversationSummariserJob.php                      [NEW]

app/Listeners/Eval/EvalTraceListener.php                    [NEW]

app/Models/AiAbortEvent.php                                 [NEW]
app/Models/AiAuditEvent.php                                 [NEW]
app/Models/AiConversation.php
app/Models/AiDailyUsage.php                                 [NEW]
app/Models/AiMessage.php
app/Models/AiRequestIdempotency.php                         [NEW]
app/Models/DCPension.php
app/Models/Estate/Will.php
app/Models/EvalProviderRun.php                              [NEW]
app/Models/EvalRecordingSession.php                         [NEW]
app/Models/FamilyMember.php
app/Models/PendingRegistration.php
app/Models/PensionInputHistory.php                          [NEW]
app/Models/TaxStrategyHouseholdInput.php                    [NEW]
app/Models/User.php                                         (5 new fillable + casts)
app/Models/UserConsent.php

app/Observers/GoalCacheObserver.php                         [NEW]
app/Observers/TrustObserver.php                             [NEW]

app/Providers/AppServiceProvider.php
app/Providers/EvalServiceProvider.php                       [NEW]
app/Providers/EventServiceProvider.php                      (registers EvalTraceListener)

app/Services/AI/AdviceFyn.php                               [NEW]
app/Services/AI/AdvicePromptBuilder.php                     [RENAMED — was SystemPromptBuilder.php]
app/Services/AI/AdvicePromptCacheInvalidator.php            [NEW]
app/Services/AI/AdviceReviewService.php
app/Services/AI/AiToolDefinitions.php
app/Services/AI/AuditChainService.php                       [NEW]
app/Services/AI/ConversationSummariser.php                  [NEW]
app/Services/AI/DuplicateAcknowledgement.php                [NEW]
app/Services/AI/HandoffContract.php                         [NEW]
app/Services/AI/HandoffPayloadValidator.php                 [NEW]
app/Services/AI/KycGateChecker.php
app/Services/AI/MemoryRetrieverService.php                  [NEW]
app/Services/AI/Prompts/ComplianceRules.php
app/Services/AI/Prompts/CoreIdentity.php
app/Services/AI/Prompts/FcaProcessInstructions.php
app/Services/AI/Prompts/UserContentSanitiser.php            [NEW]
app/Services/AI/QueryClassifier.php
app/Services/AI/RecordDuplicateChecker.php                  [NEW]
app/Services/AI/StructuredResponseValidator.php
app/Services/AI/ToolResultContract.php                      [NEW]
app/Services/AI/ToolResultContractException.php             [NEW]
app/Services/AI/WriteIntentClassifier.php                   [NEW]
app/Services/AI/XaiToolDefinitions.php

app/Services/Estate/EstateDataReadinessService.php

app/Services/Eval/EvalBypassGate.php                        [NEW]
app/Services/Eval/EvalDeltaBuilder.php                      [NEW]
app/Services/Eval/EvalHttpDriver.php                        [NEW]
app/Services/Eval/EvalSseConsumer.php                       [NEW]
app/Services/Eval/EvalTraceCollector.php                    [NEW]

app/Services/Investment/Recommendation/DataReadinessService.php

app/Services/Onboarding/AssetCaptureEntityExtractor.php     [NEW]
app/Services/Onboarding/HouseholdProvisioner.php            [NEW]
app/Services/Onboarding/JourneyFieldResolver.php
app/Services/Onboarding/JourneyStateService.php
app/Services/Onboarding/OnboardingChatDirector.php          [NEW]
app/Services/Onboarding/OnboardingFactExtractor.php         [NEW]
app/Services/Onboarding/OnboardingPromptBuilder.php         [NEW]
app/Services/Onboarding/OnboardingStateMachine.php          [NEW]
app/Services/Onboarding/OnboardingValueInterpreter.php      [NEW]
app/Services/Onboarding/SpouseLinkingService.php            [NEW]

app/Services/PrerequisiteGateService.php
app/Services/Protection/ProtectionDataReadinessService.php
app/Services/Retirement/RetirementDataReadinessService.php
app/Services/Savings/SavingsDataReadinessService.php

app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php       [NEW]
app/Services/Tax/Strategies/BedAndIsaStrategy.php                 [NEW]
app/Services/Tax/Strategies/Contract/TaxStrategy.php              [NEW]
app/Services/Tax/Strategies/CrossSpouseBundleStrategy.php         [NEW]
app/Services/Tax/Strategies/DividendAllowanceHarvestStrategy.php  [NEW]
app/Services/Tax/Strategies/GiftAidHigherRateReliefStrategy.php   [NEW]
app/Services/Tax/Strategies/IncomeBandStrategy.php                [NEW]
app/Services/Tax/Strategies/IsaTopUpStrategy.php                  [NEW]
app/Services/Tax/Strategies/JointSavingsStrategy.php              [NEW]
app/Services/Tax/Strategies/LifecycleStrategy.php                 [NEW]
app/Services/Tax/Strategies/NonEarnerSpousePensionStrategy.php    [NEW]
app/Services/Tax/Strategies/PensionAACarryForwardStrategy.php     [NEW]
app/Services/Tax/Strategies/SalarySacrificeNiStrategy.php         [NEW]
app/Services/Tax/Strategies/TaperedAnnualAllowanceStrategy.php    [NEW]
app/Services/Tax/Strategies/TaxStrategyContext.php                [NEW]
app/Services/Tax/TaxStrategyCalculator.php                        [NEW]
app/Services/Tax/TaxStrategyMath.php                              [NEW]
app/Services/Tax/TaxStrategyService.php                           [NEW]

app/Support/AssistantContentSanitiser.php                         [NEW]

app/Traits/HasAiChat.php
app/Traits/HasAiGuardrails.php

app/ValueObjects/CaptureContext.php                               [NEW]
```

> **Get the authoritative list at upload time** with:
> ```bash
> git diff origin/dev...HEAD --name-status -- "app/"
> ```

### 2c. Config (4 files)

```
config/app.php           (modified)
config/services.php      (modified — adds eval recording config)
config/fyn_eval.php      [NEW]
config/onboarding.php    [NEW]
```

### 2d. Routes (1 file)

```
routes/api.php
```

New endpoints registered:
- `GET  /api/public/tax-allowances` (unauthenticated)
- `GET  /api/tax-strategy` (auth:sanctum)
- `POST /api/tax-strategy/calculate` (auth:sanctum)
- `GET  /api/admin/ai-audit/chain`
- `GET  /api/admin/ai-audit/chain/verify`
- `GET|PUT /api/admin/eval-recordings/...` (admin only)
- `POST /api/eval/login/{personaId}` (rate-limited eval auth)
- `POST /api/eval/reset/{personaId}` (auth:sanctum eval auth)
- `GET  /api/onboarding/status`
- `POST /api/onboarding/start`

### 2e. Composer (2 files + vendor regen)

```
composer.json
composer.lock
```

New dependencies on this branch:
- `symfony/yaml ^7.4` (eval recording fixtures)
- `justinrainbow/json-schema ^6.8` (dev — eval schema validation)
- `marc-mabe/php-enum` (transitive)

You **must** run `composer install --no-dev --optimize-autoloader` on the server post-upload.

### 2f. Database (24 migrations + 5 seeders + 2 factories)

```
database/migrations/
  2026_04_15_090000_add_onboarding_fyn_state_to_users.php
  2026_04_15_091500_add_civil_partnership_to_users_marital_status.php
  2026_04_22_000001_add_persona_to_ai_messages.php
  2026_04_22_000002_add_persona_state_to_ai_conversations.php
  2026_04_22_000003_add_onboarding_parked_facts_to_ai_conversations.php
  2026_04_22_000004_add_will_columns.php
  2026_04_25_000001_clear_stale_persona_state.php           (data migration — clears bad state)
  2026_04_25_000010_create_ai_daily_usage_table.php          [TABLE]
  2026_04_25_000011_create_ai_request_idempotency_table.php  [TABLE]
  2026_04_25_000012_create_ai_abort_events_table.php         [TABLE]
  2026_04_25_000013_create_ai_audit_events_table.php         [TABLE]
  2026_04_27_000001_create_eval_recording_tables.php         [2 TABLES — sessions + provider_runs]
  2026_04_27_000002_add_remedial_report_to_eval_recording_sessions.php
  2026_04_27_100001_add_persona_columns_to_eval_recording_sessions.php
  2026_04_27_100002_add_engine_trace_to_eval_provider_runs.php
  2026_04_29_000001_add_signup_source_to_users_and_pending_registrations.php
  2026_05_02_000001_add_conversation_index_columns.php
  2026_05_03_000001_add_tax_strategy_columns_to_users.php          (household_calculation_mode, marriage_allowance_eligible, onboarding_fyn_path, etc.)
  2026_05_03_000002_add_salary_sacrifice_to_dc_pensions.php
  2026_05_03_000003_create_tax_strategy_household_inputs_table.php [TABLE]
  2026_05_04_000001_add_employer_ni_rebate_pct_to_dc_pensions.php
  2026_05_04_000002_add_spouse_existing_pension_balance_to_tax_strategy_household_inputs.php
  2026_05_05_000001_create_pension_input_history_table.php         [TABLE]
  2026_05_05_000002_add_charitable_donations_to_users.php

database/seeders/
  AdminUserSeeder.php
  ChrisUserSeeder.php
  PreviewUserSeeder.php
  TaxConfigurationSeeder.php
  TestUsersSeeder.php

database/factories/
  AiConversationFactory.php   [NEW]
  AiMessageFactory.php        [NEW]
```

### 2g. Frontend Vue source (42 files — only matters if you also re-run a server-side build, which we don't on csjones — Vite ran locally. Listed for traceability.)

```
resources/js/app.js
resources/js/layouts/AppLayout.vue
resources/js/router/index.js
resources/js/store/index.js
resources/js/store/modules/aiChat.js
resources/js/store/modules/aiFormFill.js
resources/js/store/modules/taxStrategy.js                       [NEW]

resources/js/services/aiAuditService.js
resources/js/services/aiChatService.js
resources/js/services/evalRecordingService.js                   [NEW]
resources/js/services/taxStrategyService.js                     [NEW]

resources/js/utils/sourceCapture.js                             [NEW]
resources/js/utils/stripTags.js                                 [NEW]

resources/js/components/Admin/AiAudit.vue
resources/js/components/Admin/EvalRecordings.vue                [NEW]
resources/js/components/Admin/eval/ChecklistItem.vue            [NEW]
resources/js/components/Admin/eval/EvalDataModal.vue            [NEW]
resources/js/components/Admin/eval/ProviderCell.vue             [NEW]
resources/js/components/Admin/eval/RunPanel.vue                 [NEW]

resources/js/components/Fyn/FynOnboardingChat.vue               [NEW]
resources/js/components/Fyn/FynQuickReplies.vue                 [NEW]

resources/js/components/Investment/AccountForm.vue
resources/js/components/NetWorth/PensionList.vue
resources/js/components/Onboarding/ProfileReviewPanel.vue       [NEW]
resources/js/components/Protection/PolicyFormModal.vue
resources/js/components/Public/StaticFynChat.vue
resources/js/components/Shared/AiChatPanel.vue
resources/js/components/Shared/AiChatPanelShell.vue             [NEW]
resources/js/components/Shared/AiMessageContent.vue

resources/js/components/TaxStrategy/AllowanceCard.vue           [NEW]
resources/js/components/TaxStrategy/AllowanceGrid.vue           [NEW]
resources/js/components/TaxStrategy/AssetShiftingPanel.vue      [NEW]
resources/js/components/TaxStrategy/HouseholdView.vue           [NEW]
resources/js/components/TaxStrategy/StrategyRecommendationList.vue [NEW]
resources/js/components/TaxStrategy/TaxYearHeader.vue           [NEW]

resources/js/views/Admin/AdminPanel.vue
resources/js/views/Actions/ActionsDashboard.vue
resources/js/views/Dashboard.vue
resources/js/views/Protection/ProtectionDashboard.vue
resources/js/views/Public/SaveTaxCampaignPage.vue               [NEW]
resources/js/views/Register.vue
resources/js/views/TaxStrategy/TaxStrategyDashboard.vue         [NEW]
```

### 2h. `.env.example` reference (informational, do NOT upload to server)

`.env.example` adds `AI_AUDIT_HMAC_KEY=` and removes `AGENT_INTERNAL_TOKEN=`. Apply the equivalent change to the server's real `.env` (per pre-flight step 1 / 2).

### 2i. `.htaccess` — only if changed

```bash
diff <(curl -s https://csjones.co/fynla/.htaccess) deploy/csjones-fynla/.htaccess
```

If different, upload `deploy/csjones-fynla/.htaccess` to `~/www/csjones.co/public_html/fynla/public/.htaccess`. The dev `.htaccess` MUST have `RewriteBase /fynla/`. Memory law `feedback_htaccess_vs_middleware_headers.md` applies — never set CSP/HSTS in both `.htaccess` and `SecurityHeaders` middleware.

---

## Phase 3 — Files to DELETE from the server

> ⚠️ Paths are under `~/www/csjones.co/fynla-app/` (the Laravel app dir), NOT `public_html/fynla/`. Per memory `reference_csjones_sibling_dir.md`.

```
~/www/csjones.co/fynla-app/app/Http/Controllers/Api/AgentInternalController.php
~/www/csjones.co/fynla-app/app/Http/Middleware/AgentTokenAuth.php
~/www/csjones.co/fynla-app/app/Services/AI/SystemPromptBuilder.php   (renamed to AdvicePromptBuilder.php)
~/www/csjones.co/fynla-app/scripts/fynla_agent/                       (entire directory — 6 .py files)
~/www/csjones.co/fynla-app/scripts/run_agent.py
~/www/csjones.co/fynla-app/scripts/requirements.txt
```

> ⚠️ **The renamed file is the dangerous one.** If `SystemPromptBuilder.php` AND `AdvicePromptBuilder.php` both exist, Composer's autoloader resolves the legacy class first and AdviceFyn picks up the wrong prompt builder. **Delete `SystemPromptBuilder.php` BEFORE running `composer install`.**

---

## Phase 4 — SSH and finalise

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app
```

> Memory law `reference_csjones_sibling_dir.md`: csjones.co's Laravel app lives at `~/www/csjones.co/fynla-app/`, NOT inside `public_html/fynla/`. Run all artisan commands from `fynla-app`. The `public_html/fynla/` is just the document root — it serves the assets in `~/www/csjones.co/public_html/fynla/public/build/`.

```bash
# Maintenance window on (optional but recommended for a 24-migration deploy)
php artisan down --secret="fynpersona-deploy-2026-04-30" --render="errors::503"

# 1. Composer
composer install --no-dev --optimize-autoloader 2>&1 | tail -20

# 2. Migrate (24 new migrations)
php artisan migrate --force

# 3. Reseed (CRITICAL — TaxConfigurationSeeder updates tax bands; TestUsersSeeder
#    and PreviewUserSeeder add the SaveTax campaign personas; civil-partnership
#    enum needs the new value seeded). Do NOT use migrate:fresh.
php artisan db:seed --force

# 4. Cache clears (mandatory after config + route changes)
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# 5. Re-cache for prod
php artisan optimize

# 6. Maintenance off
php artisan up
```

**If `composer install` fails with "class already exists" for `SystemPromptBuilder`** — you skipped Phase 3. Delete the old file and re-run `composer dump-autoload`.

**If `php artisan migrate` fails on `add_civil_partnership_to_users_marital_status`** — the marital_status column is a string-or-enum on the existing schema. Check current column type with `SHOW CREATE TABLE users \G` and adjust the migration's `change()` call before re-running. (Likely fine on dev; previewed on local.)

---

## Phase 5 — Smoke test

Open `https://csjones.co/fynla` in incognito. Run through:

| Path | What to verify | Pass criteria |
|---|---|---|
| `/savetax` | Public campaign page renders, `Register now to ask Fyn` CTA visible 7×, chat panel responds with placeholder + register CTA | All 7 CTAs route to `/register?from=savetax`, no console errors |
| `/register?from=savetax` | Registration form, source captured | After register, user lands in onboarding chat |
| Onboarding (chris@fynla.org or any test user) | Two-Fyn chat: bubbles → asset capture → terminal → `/tax-strategy` | No `persona_state_change` SSE event in network tab; UI input never disables |
| `/tax-strategy` (single user) | New dashboard renders inside AppLayout (sidebar, top nav, "Chat with Fyn", footer all present); hero says "{firstName}, save up to £X this year"; allowances split into "Headroom available" + "Well-utilised"; recommendations in 2-column grid sorted by £ saved; every card has "Next step" CTA | No 404 on `/api/tax-strategy`; recommendation £ matches local browser test |
| `/tax-strategy` (dual-earner persona, e.g. bs27) | HouseholdView renders twin grids labelled "{firstName}'s allowances" and "Spouse's allowances" | No "Your spouse" anywhere |
| `/dashboard` | Existing module cards still render (regression check — AppLayout changed) | All 7 module tiles present, no console errors |
| `/admin/eval-recordings` (admin user) | New eval recordings panel loads | Lists existing eval sessions if any |
| Network tab on `/api/tax-strategy` | Returns 200 with `recommendations[]` populated | If 500, check `storage/logs/laravel.log` for `TaxStrategyContext` instantiation errors |
| `php artisan ai:audit-verify-chain` (SSH) | Hash chain for any existing AI audit events still valid | Output: "Chain valid for N events" |

---

## Phase 6 — Post-deploy

| Action | When |
|---|---|
| Monitor `storage/logs/laravel.log` for 15 min | Watch for `FinancialCalculationException`, autoload errors, missing tables |
| Tail the queue worker for `ConversationSummariserJob` failures | First job runs at next scheduled tick — see `app/Console/Kernel.php` |
| Confirm `AI_AUDIT_HMAC_KEY` is in effect | `php artisan tinker --execute="echo config('ai.audit_hmac_key') ? 'set' : 'fallback to APP_KEY';"` |
| Delete the DB backup taken in pre-flight step 3 | Only after a clean smoke test + 24 hours of uneventful logs |
| Update `April/April30Updates/CSJTODO.md` with the deploy commit + timestamp | Record the canonical deploy commit per session |

---

## Rollback plan

If smoke fails and rollback is needed:

```bash
# 1. Maintenance on
php artisan down

# 2. Rollback migrations (24 steps)
php artisan migrate:rollback --step=24 --force

# 3. Restore the old SystemPromptBuilder.php from git or backup
# 4. Restore the deleted AgentInternalController.php + AgentTokenAuth.php
# 5. Restore old composer dependencies:
git checkout origin/dev -- composer.json composer.lock
composer install --no-dev --optimize-autoloader

# 6. Restore old build:
mv public/build public/build.failed
mv public/build.old public/build

# 7. Cache clear + optimize
php artisan cache:clear && php artisan config:clear && php artisan optimize
php artisan up
```

If migration rollback fails, restore from the SiteGround SQL dump taken in pre-flight step 3.

---

## What is NOT in this deploy

| Item | Status | Why |
|---|---|---|
| Production (`fynla.org`) | NOT deployed | Memory law `feedback_main_via_dev_only.md` — nothing reaches `main` without dev verification first |
| Mobile / iOS Capacitor build | NOT touched | No `ios/` changes; `/tax-strategy` is web-first per the original SaveTax spec OS4 |
| BS-26/27/28 Playwright scenarios | NOT auto-run on deploy | These are interactive runs CSJ drives manually post-deploy if needed |
| Slider / `/api/tax-strategy/calculate` endpoint | Endpoint still exists; UI removed | Frontend `StrategySliderPanel.vue` deleted in commit `fad6e88`. Backend tests still cover the calculate endpoint. Cleanup deferred. |

---

## Branch summary at a glance

| Metric | Value |
|---|---|
| Commits since `origin/dev` | 252 |
| Tip commit | `fad6e88` |
| Diverged at | `58aeb47` |
| App PHP files added/modified/deleted | 83 / 49 / 2 |
| Vue files added/modified | 22 / 20 |
| New migrations | 24 |
| Modified seeders | 5 |
| New tables | 7 (ai_daily_usage, ai_request_idempotency, ai_abort_events, ai_audit_events, eval_recording_sessions, eval_provider_runs, tax_strategy_household_inputs, pension_input_history) |
| Modified tables | 4 (users, ai_conversations, ai_messages, dc_pensions, pending_registrations, family_members) |
| New composer deps | 3 |
| Deleted server files | 8 (2 PHP + 6 Python agent scripts) |
| Renamed PHP class | 1 (SystemPromptBuilder → AdvicePromptBuilder) |
| New API endpoints | 11 |
| New env var | 1 (`AI_AUDIT_HMAC_KEY`) |

---

*Generated 30 April 2026 from `git diff origin/dev...HEAD` at commit `fad6e88`. Per memory law `feedback_deploy_guide_completeness.md`, this is the authoritative file list — do not deploy from memory.*
