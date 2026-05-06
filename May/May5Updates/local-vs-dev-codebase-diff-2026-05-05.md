# Local vs Dev Codebase Comparison — 5 May 2026

**Generated:** 5 May 2026, session 3
**Local branch:** `onboardingFyn` (HEAD `cb94b7a`)
**Local effective code state:** functionally identical to `origin/dev` (`dc335b3`) — `git diff origin/dev..onboardingFyn` shows only `CSJTODO.md` modified + `handover-2026-05-05-session-2-clear.md` added; **no source code differs**.
**Remote compared:** csjones.co dev server, `~/www/csjones.co/fynla-app/`
**Method:** `rsync --dry-run --checksum --itemize-changes --delete` from local → remote, with the standard exclude list (`vendor/`, `node_modules/`, `storage/`, `public/build/`, `.env`, `.git/`, etc.)

## TL;DR

**Local and dev DO NOT mirror each other.** There are three categories of drift:

| Category | Count | Severity |
|---|---|---|
| Files differing in content (exist both sides, diff bytes) | **138** | High — includes core Agents, Controllers, Models, Vue layouts, deploy scripts |
| Files only on remote (server-side WIP, no `git`) | **125** | High — full Eval + Tax Strategy + AI Audit frameworks, 27 migrations |
| Files only on local (deployed-pending) | **360** | Medium — mostly Insights blocks, frontend tests, asset images |

The remote is **not** running what `origin/dev` says it is. The csjones.co server has substantial uncommitted server-side WIP work (Eval framework, Tax Strategy framework, AI Audit/Idempotency, additional Onboarding extractors) that has never been pushed back to `dev`. Local has the squashed PR #214 onboardingFyn work that has not been deployed to csjones since the PR landed (`dc335b3`, today).

This matches the warning in CSJTODO session 75 ("**csjones.co server has 61+ uncommitted server-side WIP files in app/**"), but the drift is now **larger** — closer to 90 files in `app/` alone.

---

## Section 1 — 138 files differ in content

These files exist in both local and remote but have diverged. Likely root causes:

- **Server-side hand-edits**: server WIP work touches the same shared files (Agents, Models, Controllers, traits) that the deployed branch tracks.
- **Local ahead of last deploy**: PR #214 (onboardingFyn → dev) landed on `dev` today as `dc335b3` and has not been deployed to csjones, so anything onboarding/Fyn-related on local is ahead.
- **Renames done locally**: e.g. `resources/js/components/Navbar.vue` was renamed to `AppNavbar.vue` (see Section 3).

Spot-checked size deltas (line counts, remote vs local):

| File | Remote | Local | Delta |
|---|---|---|---|
| `app/Agents/CoordinatingAgent.php` | 4346 | 3004 | **remote +1342** |
| `app/Models/User.php` | 769 | 759 | remote +10 |
| `app/Http/Controllers/Api/AuthController.php` | 799 | 777 | remote +22 |
| `config/services.php` | 69 | 77 | local +8 |
| `routes/api.php` | 1287 | 1290 | local +3 |

`CoordinatingAgent.php` alone has ~1,300 extra lines on the server — almost certainly the orchestration glue that wires the Eval / Tax Strategy / Advice frameworks together.

### Full list (138 files)

#### Top-level / config (8)
- `.gitignore`
- `CLAUDE.md`
- `package.json`
- `package-lock.json`
- `phpunit.xml`
- `tailwind.config.js`
- `config/app.php`
- `config/lifecycle.php`
- `config/onboarding.php`
- `config/services.php`

#### Agents (7)
- `app/Agents/CoordinatingAgent.php`
- `app/Agents/EstateAgent.php`
- `app/Agents/GoalsAgent.php`
- `app/Agents/InvestmentAgent.php`
- `app/Agents/ProtectionAgent.php`
- `app/Agents/RetirementAgent.php`
- `app/Agents/SavingsAgent.php`

#### Console / Constants / Providers (6)
- `app/Console/Kernel.php`
- `app/Console/Commands/ResetPreviewData.php`
- `app/Constants/QuerySchemas.php`
- `app/Constants/TaxDefaults.php`
- `app/Providers/AppServiceProvider.php`
- `app/Providers/EventServiceProvider.php`

#### HTTP layer (8)
- `app/Http/Kernel.php`
- `app/Http/Controllers/Api/AdminController.php`
- `app/Http/Controllers/Api/AiAuditController.php`
- `app/Http/Controllers/Api/AiChatController.php`
- `app/Http/Controllers/Api/AuthController.php`
- `app/Http/Middleware/PreviewWriteInterceptor.php`
- `app/Http/Requests/RegisterRequest.php`
- `app/Http/Resources/UserResource.php`

#### Models (12)
- `app/Models/AiConversation.php`
- `app/Models/AiMessage.php`
- `app/Models/DCPension.php`
- `app/Models/FamilyMember.php`
- `app/Models/PendingRegistration.php`
- `app/Models/User.php`
- `app/Models/UserConsent.php`
- `app/Models/Estate/Asset.php`
- `app/Models/Estate/Gift.php`
- `app/Models/Estate/IHTProfile.php`
- `app/Models/Estate/Liability.php`
- `app/Models/Estate/Will.php`
- `app/Models/Investment/Holding.php`

#### Services (16)
- `app/Services/PrerequisiteGateService.php`
- `app/Services/AI/AdviceReviewService.php`
- `app/Services/AI/AiToolDefinitions.php`
- `app/Services/AI/KycGateChecker.php`
- `app/Services/AI/QueryClassifier.php`
- `app/Services/AI/XaiToolDefinitions.php`
- `app/Services/AI/Prompts/CoreIdentity.php`
- `app/Services/AI/Prompts/FcaProcessInstructions.php`
- `app/Services/Estate/EstateDataReadinessService.php`
- `app/Services/Investment/Recommendation/DataReadinessService.php`
- `app/Services/Investment/Recommendation/RecommendationOutputFormatter.php`
- `app/Services/Lifecycle/LifecycleEngine.php`
- `app/Services/Onboarding/JourneyFieldResolver.php`
- `app/Services/Onboarding/OnboardingChatDirector.php`
- `app/Services/Onboarding/OnboardingPromptBuilder.php`
- `app/Services/Onboarding/OnboardingStateMachine.php`
- `app/Services/Onboarding/OnboardingValueInterpreter.php`
- `app/Services/Onboarding/SpouseLinkingService.php`
- `app/Services/Protection/ProtectionDataReadinessService.php`
- `app/Services/Retirement/RetirementDataReadinessService.php`
- `app/Services/Savings/SavingsDataReadinessService.php`

#### Traits (2)
- `app/Traits/HasAiChat.php`
- `app/Traits/HasAiGuardrails.php`

#### Database (6)
- `database/CLAUDE.md`
- `database/factories/Investment/RiskProfileFactory.php`
- `database/seeders/AdminUserSeeder.php`
- `database/seeders/ChrisUserSeeder.php`
- `database/seeders/PreviewUserSeeder.php`
- `database/seeders/TaxConfigurationSeeder.php`
- `database/seeders/TestUsersSeeder.php`

#### Deploy + public (5)
- `deploy/csjones-fynla/.htaccess`
- `deploy/csjones-fynla/build.sh`
- `deploy/fynla-org/.htaccess`
- `deploy/fynla-org/build.sh`
- `public/.htaccess`
- `public/mockup-insights.html`

#### Documentation (3)
- `docs/tech-debt-report-full.md`
- `app/Services/CLAUDE.md`
- `resources/js/CLAUDE.md`

#### Frontend — Vue components (38)
- `resources/js/components/Admin/AdminDashboard.vue`
- `resources/js/components/Admin/UserManagement.vue`
- `resources/js/components/Goals/GoalsOverview.vue`
- `resources/js/components/Investment/AccountForm.vue`
- `resources/js/components/Investment/PortfolioOverview.vue`
- `resources/js/components/Investment/StandardInvestmentFields.vue`
- `resources/js/components/Journey/JourneyProgressHero.vue`
- `resources/js/components/NetWorth/AssetAllocationDonut.vue`
- `resources/js/components/NetWorth/AssetBreakdownBar.vue`
- `resources/js/components/NetWorth/BusinessInterestsList.vue`
- `resources/js/components/NetWorth/ChattelsList.vue`
- `resources/js/components/NetWorth/HoldingsDetail.vue`
- `resources/js/components/NetWorth/InvestmentList.vue`
- `resources/js/components/NetWorth/LiabilitiesList.vue`
- `resources/js/components/NetWorth/NetWorthWealthSummary.vue`
- `resources/js/components/NetWorth/PensionList.vue`
- `resources/js/components/NetWorth/PropertyList.vue`
- `resources/js/components/Payment/DataRetentionOverlay.vue`
- `resources/js/components/Payment/PlanSelectionModal.vue`
- `resources/js/components/Public/ReviewCarousel.vue`
- `resources/js/components/Retirement/DCPensionForm.vue`
- `resources/js/components/Retirement/UnifiedPensionForm.vue`
- `resources/js/components/Shared/AiChatPanel.vue`
- `resources/js/components/Shared/AiMessageContent.vue`
- `resources/js/components/UserProfile/LetterToSpouse.vue`
- `resources/js/components/UserProfile/SubscriptionManagement.vue`
- `resources/js/layouts/AppLayout.vue`
- `resources/js/layouts/PublicLayout.vue`
- `resources/js/mobile/views/NotificationSettings.vue`
- `resources/js/views/Dashboard.vue`
- `resources/js/views/Login.vue`
- `resources/js/views/UserProfile.vue`
- `resources/js/views/Admin/AdminPanel.vue`
- `resources/js/views/Auth/CheckoutPage.vue`
- `resources/js/views/Goals/GoalsDashboard.vue`
- `resources/js/views/Public/AboutPage.vue`
- `resources/js/views/Public/FeaturesPage.vue`
- `resources/js/views/Public/HowItWorksPage.vue`
- `resources/js/views/Public/LandingPage.vue`
- `resources/js/views/Public/PricingPage.vue`
- `resources/js/views/Public/PrivacyPolicyPage.vue`
- `resources/js/views/Public/insights/InsightsHubPage.vue`
- `resources/js/views/Public/why-fynla/OnePlatformPage.vue`
- `resources/js/views/Savings/SavingsDashboard.vue`
- `resources/js/views/Trusts/TrustsDashboard.vue`

#### Frontend — JS / Vuex / utils / router / services (12)
- `resources/js/constants/subNavConfig.js`
- `resources/js/router/index.js`
- `resources/js/services/aiChatService.js`
- `resources/js/services/investmentService.js`
- `resources/js/store/index.js`
- `resources/js/store/modules/aiChat.js`
- `resources/js/store/modules/aiFormFill.js`
- `resources/js/store/modules/userProfile.js`
- `resources/js/utils/cookieConsent.js`

#### Routes (1)
- `routes/api.php`

---

## Section 2 — Files only on remote (server-side WIP, 125 files)

These files are present on the csjones server but **do not exist anywhere in `git`** (not in `dev`, not in `main`, not in `onboardingFyn`). This is uncommitted dev work performed directly on the server.

### 2a. `app/` — 90 files

#### Eval framework (15)
- `app/Listeners/Eval/EvalTraceListener.php`
- `app/Events/Eval/AgentDecision.php`
- `app/Events/Eval/EngineCalled.php`
- `app/Events/Eval/GateChecked.php`
- `app/Services/Eval/EvalBypassGate.php`
- `app/Services/Eval/EvalDeltaBuilder.php`
- `app/Services/Eval/EvalHttpDriver.php`
- `app/Services/Eval/EvalSseConsumer.php`
- `app/Services/Eval/EvalTraceCollector.php`
- `app/Providers/EvalServiceProvider.php`
- `app/Http/Controllers/Api/EvalAuthController.php`
- `app/Http/Controllers/Api/Admin/EvalRecordingController.php`
- `app/Console/Commands/EvalPurgeCommand.php`
- `app/Console/Commands/EvalRecordCommand.php`
- `app/Console/Commands/EvalShowCommand.php`
- `app/Models/EvalProviderRun.php`
- `app/Models/EvalRecordingSession.php`

#### Tax Strategy framework (19)
- `app/DataTransferObjects/StrategyRecommendation.php`
- `app/DataTransferObjects/TaxStrategyOutputDTO.php`
- `app/DataTransferObjects/TaxStrategyOverridesDTO.php`
- `app/Enums/StrategyCategory.php`
- `app/Enums/StrategyPriority.php`
- `app/Constants/UpdateRecordAllowlist.php`
- `app/Http/Controllers/Api/TaxStrategyController.php`
- `app/Http/Controllers/Api/Public/TaxAllowancesController.php`
- `app/Http/Requests/TaxStrategyCalculateRequest.php`
- `app/Models/TaxStrategyHouseholdInput.php`
- `app/Services/Tax/TaxStrategyCalculator.php`
- `app/Services/Tax/TaxStrategyMath.php`
- `app/Services/Tax/TaxStrategyService.php`
- `app/Services/Tax/Strategies/Contract/TaxStrategy.php`
- `app/Services/Tax/Strategies/TaxStrategyContext.php`
- `app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php`
- `app/Services/Tax/Strategies/BedAndIsaStrategy.php`
- `app/Services/Tax/Strategies/CrossSpouseBundleStrategy.php`
- `app/Services/Tax/Strategies/DividendAllowanceHarvestStrategy.php`
- `app/Services/Tax/Strategies/GiftAidHigherRateReliefStrategy.php`
- `app/Services/Tax/Strategies/IncomeBandStrategy.php`
- `app/Services/Tax/Strategies/IsaTopUpStrategy.php`
- `app/Services/Tax/Strategies/JointSavingsStrategy.php`
- `app/Services/Tax/Strategies/LifecycleStrategy.php`
- `app/Services/Tax/Strategies/NonEarnerSpousePensionStrategy.php`
- `app/Services/Tax/Strategies/PensionAACarryForwardStrategy.php`
- `app/Services/Tax/Strategies/SalarySacrificeNiStrategy.php`
- `app/Services/Tax/Strategies/TaperedAnnualAllowanceStrategy.php`

#### AI Audit + Idempotency + Handoff + Advice (24)
- `app/Models/AiAbortEvent.php`
- `app/Models/AiAuditEvent.php`
- `app/Models/AiDailyUsage.php`
- `app/Models/AiRequestIdempotency.php`
- `app/Http/Middleware/IdempotencyKeyMiddleware.php`
- `app/Http/Requests/AI/SendAiChatMessageRequest.php`
- `app/Jobs/AiAuditRetentionJob.php`
- `app/Jobs/AiIdempotencyCleanupJob.php`
- `app/Jobs/ConversationSummariserJob.php`
- `app/Console/Commands/AiAuditVerifyChainCommand.php`
- `app/Console/Commands/BackfillAiDailyUsage.php`
- `app/Console/Commands/SummariseStaleConversationsCommand.php`
- `app/Services/AI/AdviceFyn.php`
- `app/Services/AI/AdvicePromptBuilder.php`
- `app/Services/AI/AdvicePromptCacheInvalidator.php`
- `app/Services/AI/AuditChainService.php`
- `app/Services/AI/ConversationSummariser.php`
- `app/Services/AI/DuplicateAcknowledgement.php`
- `app/Services/AI/HandoffContract.php`
- `app/Services/AI/HandoffPayloadValidator.php`
- `app/Services/AI/MemoryRetrieverService.php`
- `app/Services/AI/RecordDuplicateChecker.php`
- `app/Services/AI/ToolResultContract.php`
- `app/Services/AI/ToolResultContractException.php`
- `app/Services/AI/WriteIntentClassifier.php`
- `app/Services/AI/Prompts/UserContentSanitiser.php`

#### Onboarding extras (3)
- `app/Services/Onboarding/AssetCaptureEntityExtractor.php`
- `app/Services/Onboarding/HouseholdProvisioner.php`
- `app/Services/Onboarding/OnboardingFactExtractor.php`

#### Misc (5)
- `app/ValueObjects/CaptureContext.php`
- `app/Support/XaiFunctionCallLeakStripper.php`
- `app/Models/PensionInputHistory.php`
- `app/Observers/GoalCacheObserver.php`

### 2b. `database/` — 27 files

#### Factories (2)
- `database/factories/AiConversationFactory.php`
- `database/factories/AiMessageFactory.php`

#### Migrations (25, dated 2026-04-22 → 2026-05-06)
- `database/migrations/2026_04_22_000001_add_persona_to_ai_messages.php`
- `database/migrations/2026_04_22_000002_add_persona_state_to_ai_conversations.php`
- `database/migrations/2026_04_22_000003_add_onboarding_parked_facts_to_ai_conversations.php`
- `database/migrations/2026_04_22_000004_add_will_columns.php`
- `database/migrations/2026_04_25_000001_clear_stale_persona_state.php`
- `database/migrations/2026_04_25_000010_create_ai_daily_usage_table.php`
- `database/migrations/2026_04_25_000011_create_ai_request_idempotency_table.php`
- `database/migrations/2026_04_25_000012_create_ai_abort_events_table.php`
- `database/migrations/2026_04_25_000013_create_ai_audit_events_table.php`
- `database/migrations/2026_04_27_000001_create_eval_recording_tables.php`
- `database/migrations/2026_04_27_000002_add_remedial_report_to_eval_recording_sessions.php`
- `database/migrations/2026_04_27_100001_add_persona_columns_to_eval_recording_sessions.php`
- `database/migrations/2026_04_27_100002_add_engine_trace_to_eval_provider_runs.php`
- `database/migrations/2026_04_29_000001_add_signup_source_to_users_and_pending_registrations.php`
- `database/migrations/2026_05_02_000001_add_conversation_index_columns.php`
- `database/migrations/2026_05_03_000001_add_tax_strategy_columns_to_users.php`
- `database/migrations/2026_05_03_000002_add_salary_sacrifice_to_dc_pensions.php`
- `database/migrations/2026_05_03_000003_create_tax_strategy_household_inputs_table.php`
- `database/migrations/2026_05_04_000001_add_employer_ni_rebate_pct_to_dc_pensions.php`
- `database/migrations/2026_05_04_000002_add_spouse_existing_pension_balance_to_tax_strategy_household_inputs.php`
- `database/migrations/2026_05_05_000001_create_pension_input_history_table.php`
- `database/migrations/2026_05_05_000002_add_charitable_donations_to_users.php`
- `database/migrations/2026_05_06_000001_drop_is_eval_user_from_users.php`
- `database/migrations/2026_05_06_000002_rename_eval_user_id_to_preview_user_id.php`
- `database/migrations/2026_05_06_000003_add_operation_created_at_index_to_ai_audit_events.php`

### 2c. `resources/` — 8 files (all old/superseded Vue components)

These exist on csjones because they were never `--delete`'d from a previous deploy — they have local replacements (see Section 3).

| Remote-only file | Likely local replacement |
|---|---|
| `resources/js/components/Navbar.vue` | `resources/js/components/AppNavbar.vue` |
| `resources/js/components/Footer.vue` | `resources/js/components/AppFooter.vue` |
| `resources/js/components/Auth/LogoutSuccessModal.vue` | (deleted) |
| `resources/js/components/Investment/Performance.vue` | `resources/js/components/Investment/InvestmentPerformance.vue` |
| `resources/js/components/Investment/Holdings.vue` | `resources/js/components/Investment/InvestmentHoldings.vue` |
| `resources/js/components/Investment/Goals.vue` | (deleted / merged into other module) |
| `resources/js/components/Savings/Recommendations.vue` | `resources/js/components/Savings/SavingsRecommendations.vue` |
| `resources/js/components/UserProfile/Settings.vue` | `resources/js/components/UserProfile/NotificationPreferences.vue` (similar role) |

### 2d. `config/` — 1 file
- `config/fyn_eval.php` (Eval framework config — pairs with the Eval WIP)

### 2e. Loose markdown / log files at server root
- `php_errorlog`
- `SITE_ARCHITECTURE.md`
- `when-can-i-retire.md`, `protection-gap.md`, `pension-tracker.md`, `net-worth-dashboard.md`, `monte-carlo.md`, `iht-planning.md`, `ice-letters.md`, `how-it-works.md`, `faq.md` (drafted SEO/insight content notes — not in `git`)
- `before-emily-toggle.png` (one-off screenshot)

---

## Section 3 — Files only on local (deployed-pending, 360 files of substance)

These exist on local but not on the server. Categorised by whether they are real source / config (deployable) or local-only artefacts (not deployable).

### 3a. Real code that should ship on next deploy

#### `app/` (2)
- `app/Services/AI/SystemPromptBuilder.php`
- `app/Services/AI/Prompts/EmptyDataGuard.php`

#### `database/` (2)
- `database/factories/NewsArticleFactory.php`
- `database/factories/NewsSubscriberFactory.php`

#### `resources/js/` — 63 files (selected highlights)

**New Insights block-based article renderer** (24 files): `components/Insights/blocks/*Block.vue`, `components/Admin/Insights/blocks/Edit*Block.vue`, `BlockPickerModal`, `RichTextEditor`, `BespokeArticleNotice`, `ArticleBlockRenderer`.

**News module** (5 files): `components/News/NewsSubscribeBanner.vue`, `NewsletterStatusModal.vue`, `services/newsService.js`, `newsSubscriberService.js`, `views/Public/NewsArticlePage.vue`, `NewsHubPage.vue`, `views/Admin/NewsSubscribersPage.vue`.

**Documents CMS** (4 files): `components/Admin/Documents/DropZone.vue`, `CoverImagePicker.vue`, `views/Admin/Documents/DocumentEditor.vue`, `DocumentListPage.vue`, `services/documentArticleService.js`, `store/modules/documentArticles.js`.

**Insights admin** (3 files): `views/Admin/Insights/ArticleEditor.vue`, `ArticleListPage.vue`, `TemplateListPage.vue`, `services/insightsService.js`, `store/modules/insights.js`, `utils/insightsSanitize.js`.

**Renamed components** (5 files — replace remote-only files in 2c):
- `components/AppNavbar.vue`, `components/AppFooter.vue`
- `components/Investment/InvestmentHoldings.vue`, `InvestmentPerformance.vue`
- `components/Savings/SavingsRecommendations.vue`

**New public pages** (5 files): `views/Public/CampaignPage.vue`, `NotFoundPage.vue`, `QuickStartPage.vue`, `views/Public/insights/InsightArticlePage.vue`.

**Insight asset images** (4 files): `resources/js/assets/insights/{how-much-to-retire-uk,isa-guide-uk,retirement-planning-uk,stocks-shares-isa}.jpg`.

**Other**: `components/Fyn/FynQuickReplies.vue`, `components/Public/StaticFynChat.vue`, `components/UserProfile/NotificationPreferences.vue`, `views/InvoiceView.vue`, `utils/awinTracking.js`.

#### `deploy/` (2)
- `deploy/awin/README.md`
- `deploy/notes/2026-04-17-insights-cms.md`

#### `public/` (2)
- `public/images/Homepage-Fynla-ProductVideov2.mp4`
- `public/mockup-insights-v2.html`

#### `scripts/` (8)
- `scripts/run_agent.py`, `scripts/requirements.txt`, `scripts/fynla_agent/{__init__,agent,config,hooks,schemas,tools}.py`

### 3b. Local-only artefacts (not for deployment)

- 293 files under `tests/frontend/components/{Estate,Goals,Investment,Protection,Retirement,Savings,Shared}/` and `tests/frontend/views/` — frontend unit tests (Vitest), not part of any deploy pipeline.
- Top-level research artefacts: `Fynla_Multi_Country_Architecture.docx`, `Fynla_Phase_0_Implementation_Guide.docx`, `Fynla_SA_Research_and_Mapping.docx`, `Fynla_Scaling_Playbook.docx`, plus several `.png` screenshots (`cms-after-save.png`, `cms-editor-with-back.png` etc.).
- `tech-debt-report.md`.

---

## What this means

### The truth about the dev environment

**The csjones.co server is running a hybrid: deployed `dev` branch code + ~125 files of uncommitted server-side work + ~138 modified files.** It is not a faithful mirror of `origin/dev`, and pushing local `dev` to it via the standard rsync deploy would either:

1. **Without `--delete`**: leave the server WIP intact (current behaviour, safe), OR
2. **With `--delete`**: nuke ~125 files of unmerged dev work (unsafe — work loss).

This matches the longstanding warning in CSJTODO from session 75 ("**Future deploys MUST run rsync without `--delete` and ASK before bulk-syncing**"). The drift has now grown — the original "61+" figure from session 75 is closer to **~90 in `app/` plus 27 migrations plus a config plus a handful of factories/Vue files** today.

### Concrete risks

1. **Server WIP cannot be reproduced from `git`** — if the csjones VM dies, ~125 files of work disappear. This is the highest-priority risk.
2. **Migrations not in `git`** — 25 migrations on the server have never been committed. Running `php artisan migrate` on a freshly cloned `dev` checkout would diverge the schema from what's actually live on csjones.
3. **Production deploy will silently NOT include any of this WIP** — when the `dev → main` PR is merged and `./deploy/fynla-org/build.sh` is run from a clean checkout, none of the Eval / Tax Strategy / AI Audit work goes to production. That is probably *intentional* (it's WIP), but worth surfacing.
4. **The 138 content-divergent files include shared core**: `CoordinatingAgent`, `User`, `AuthController`, every `*DataReadinessService`, every `Estate Model`, the `LifecycleEngine`, all 7 module agents, `routes/api.php`, `tailwind.config.js`. A naive deploy would clobber server-side hand-edits in any of these. The `CoordinatingAgent.php` size delta of +1342 lines suggests the server-side work has hooks and orchestration we'd lose.
5. **Local has 2 PHP services** (`SystemPromptBuilder`, `EmptyDataGuard`) and 2 factories (News) that haven't shipped to csjones yet. Plus 63 frontend files (Insights/blocks, News, Documents CMS, renamed components). These should land on csjones in the next deploy.

### Recommended next steps (CSJ to decide)

This report is informational. The skill that generated it does not auto-fix or auto-merge. Possible next steps:

1. **Server WIP rescue** — SSH to csjones, package the 125 uncommitted files into a feature branch (`feature/csj/server-wip-rescue` or similar), open a PR to `dev`. This is the only way to make the dev branch actually reflect the dev environment.
2. **Targeted deploy of Section 3a items** — push the 67 deployable local-only files (2 PHP, 2 factories, 63 frontend) to csjones using rsync without `--delete`. Local-only artefacts in 3b stay local.
3. **Reconcile the 138 modified files** — case-by-case. For files that have only been server-edited (e.g. `CoordinatingAgent.php`), pull the server version into a branch before any clobbering deploy. For files local has changed (e.g. `routes/api.php`, `config/services.php`), confirm the server version is compatible before deploying.
4. **Defer everything until after production deploy** — if the next priority is the `dev → main` release PR, freeze csjones changes, deploy `main` first, then come back to reconcile dev drift.

---

## Reproduction

```bash
# Compare local to csjones (dry-run, content checksum)
rsync -arcn --delete --itemize-changes \
  --exclude='/.git/' --exclude='/.github/' --exclude='/.claude/' \
  --exclude='/vendor/' --exclude='/node_modules/' --exclude='/storage/' \
  --exclude='/bootstrap/cache/' --exclude='/public/build/' \
  --exclude='/public/hot' --exclude='/public/storage' \
  --exclude='.env' --exclude='.env.*' --exclude='*.log' \
  --exclude='/May/' --exclude='/April/' --exclude='/March/' \
  --exclude='/February/' --exclude='/January/' --exclude='/December/' \
  --exclude='/November/' --exclude='/October/' \
  --exclude='/fynlaBrain/' --exclude='/CSJTODO.md' \
  --exclude='/CSJTODO.local.md' --exclude='/ios/' \
  --exclude='/.vscode/' --exclude='/.idea/' \
  --exclude='/.DS_Store' --exclude='.DS_Store' \
  --exclude='/tests/coverage/' --exclude='/coverage/' \
  --exclude='/campaigns/' --exclude='/fyn/' --exclude='/personas/' \
  --exclude='/prompts/' --exclude='/tools/' \
  --exclude='/.phpunit.result.cache' --exclude='/.phpactor.json' \
  --exclude='/dev.sh' --exclude='*.pid' --exclude='/auth.json' \
  -e "ssh -p 18765 -i ~/.ssh/fynlaDev" \
  /Users/CSJ/Desktop/fynla/ \
  u163-ptanegf9edny@ssh.csjones.co:~/www/csjones.co/fynla-app/
```

The dry-run output uses `<f+++++++` (push to remote = local-only), `*deleting` (would delete = remote-only), `<fc.t....` (content differs).

Raw rsync output is preserved at `/tmp/fynla-compare/rsync-output.txt` (18,638 lines) and per-category breakdowns in sibling files in the same dir.
