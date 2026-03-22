# Deployment Guide — 21 March 2026

**STATUS: DEPLOYED (income fix) | NOT YET DEPLOYED (goals/what-if) | NOT YET DEPLOYED (AI form fill — on `aiFormFill` branch)**

## Rebuild Required?

**Yes** — Multiple Vue components changed. Run:

```bash
./deploy/fynla-org/build.sh
```

Then upload `public/build/` directory.

## Database Migrations (2 pending)

```bash
php artisan migrate
```

1. `2026_03_21_000001_add_new_life_event_types.php` — Adds divorce, marriage, new_child, job_loss, income_change to life_events enum
2. `2026_03_21_000002_create_what_if_scenarios_table.php` — Creates what_if_scenarios table for persistent scenarios

## Database Seeders

```bash
php artisan db:seed
```

Reseed all data after migrations to ensure preview personas and tax config are current.

## PHP Files to Upload

### New Files (12)

```
app/Models/WhatIfScenario.php
app/Services/WhatIf/WhatIfScenarioService.php
app/Http/Controllers/Api/WhatIfScenarioController.php
app/Http/Requests/StoreWhatIfScenarioRequest.php
app/Http/Resources/WhatIfScenarioResource.php
database/factories/WhatIfScenarioFactory.php
database/migrations/2026_03_21_000001_add_new_life_event_types.php
database/migrations/2026_03_21_000002_create_what_if_scenarios_table.php
tests/Unit/Agents/SavingsAgentGoalsTest.php
tests/Unit/Agents/ProtectionAgentGoalsTest.php
tests/Unit/Agents/EstateAgentGoalsTest.php
tests/Unit/Agents/RetirementAgentGoalsTest.php
```

### Modified Files (20)

```
app/Agents/SavingsAgent.php
app/Agents/ProtectionAgent.php
app/Agents/EstateAgent.php
app/Agents/RetirementAgent.php
app/Agents/CoordinatingAgent.php
app/Services/AI/AiToolDefinitions.php
app/Services/PrerequisiteGateService.php
app/Services/UserProfile/PersonalAccountsService.php
app/Services/UserProfile/UserProfileService.php
app/Services/Goals/LifeEventIntegrationService.php
app/Models/LifeEvent.php
app/Observers/LifeEventMonteCarloObserver.php
app/Traits/HasAiChat.php
routes/api.php
tests/Pest.php
tests/Architecture/Phase02ArchitectureTest.php
tests/Unit/Services/PersonalAccountsServiceTest.php
```

## Frontend (via build)

All compiled into `public/build/` — upload the build directory:

```
resources/js/components/UserProfile/IncomeOccupation.vue
resources/js/components/UserProfile/IncomeStatementTab.vue
resources/js/components/Goals/GoalsOverview.vue
resources/js/components/Goals/GoalCard.vue
resources/js/components/Dashboard/GoalsOverviewCard.vue
resources/js/components/WhatIf/ScenarioCard.vue
resources/js/components/WhatIf/ScenarioDetail.vue
resources/js/components/WhatIf/ModuleComparison.vue
resources/js/components/Shared/AiChatPanel.vue
resources/js/views/Planning/WhatIfDashboard.vue
resources/js/views/Planning/WhatIfScenarioDetailView.vue
resources/js/store/modules/whatIf.js
resources/js/store/index.js
resources/js/services/whatIfService.js
resources/js/utils/chatNavigationRouter.js
resources/js/router/index.js
resources/js/layouts/AppLayout.vue
```

## Upload Order

1. Upload all PHP files to matching paths on server
2. Upload 2 migration files
3. Run `composer dump-autoload` on server (new model + service classes)
4. Run `./deploy/fynla-org/build.sh` locally
5. Upload `public/build/` directory
6. SSH and run migrations + seed + clear caches:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate && php artisan db:seed && php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## What Changed

### Income Fix (PR #147)
- Other Income added to IncomeOccupation.vue (was completely missing)
- `annual_other_income` added to UserProfileService API response + tax calculation
- Interest, Pension, Trust income added to PersonalAccountsService P&L
- Hardcoded frontend tax calculator replaced with backend UKTaxCalculator
- All income lines hidden when zero in view mode

### Goals Module Integration (PR #148)
- SavingsAgent: goal shortfall + emergency fund + life event cash buffer recommendations
- ProtectionAgent: goal commitments in coverage analysis
- EstateAgent: goal liquidity risk flagging
- RetirementAgent: post-retirement goal detection
- GoalCard: inline monthly contribution input
- Goals banner: specific goal names + remaining amounts
- AI create_goal tool: monthly_contribution with affordability assessment
- Chat icon hidden for preview users

### Life Events Expansion (PR #149)
- 5 new event types: divorce, marriage, new_child, job_loss, income_change
- Module cache invalidation on life event changes
- AI context enriched with per-module life event impact summaries

### What-If Scenario System (PR #150, #151)
- New what_if_scenarios table with persistent scenarios
- WhatIfScenarioService: living Now vs What-If comparisons
- create_what_if_scenario AI tool (replaces run_what_if_scenario)
- Card grid list → dedicated detail page with back button
- AI auto-navigation: Fyn creates scenario and navigates user to detail view

### Fyn AI Navigation (PR #152)
- Query string parsing fixed for `/valuable-info?section=income` style URLs
- Client-side router: all 37 sidebar pages mapped to zero-token keyword matches
- Added missing keywords: savings, retirement, sipp, life events, individual plans, help
- AI tool route list: comprehensive with categories, legacy redirect warnings
- System prompt: always navigate first, offer help on empty modules, module dependency guidance

### Fyn AI Goals & Life Events Context (PR #153)
- All active goals included in system prompt with IDs, progress, status, contributions
- All upcoming life events included in system prompt with IDs, amounts, timing
- New `list_goals` tool: lightweight goal listing without full agent analysis
- New `list_life_events` tool: lightweight event listing without full agent analysis
- Fyn can reference goals/events by ID for updates and deletes without a prior tool call

### Chat History Fix (PR #154)
- History drawer was missing from the docked chat panel (only existed in floating panel)
- Real users clicking the history icon saw nothing — state toggled but no UI rendered
- Added full history drawer to docked panel: conversation list with titles, times, delete buttons

### Chat Scroll Anchor (PR #155)
- Chat was scrolling to the bottom of Fyn's response, forcing users to scroll up to read
- Now anchors to the top of the response so users read downward naturally
- User messages still scroll to bottom; streaming doesn't auto-scroll

### Tool Error Handling
- When a tool call fails, Fyn was showing raw error text ("let me try that again...")
- Tool results with errors now get `is_error: true` flag for the Anthropic API
- System prompt instructs Fyn to answer from knowledge with a caveat instead of showing errors
- Never retries the same failing tool, never mentions technical issues to the user

## AI Form Fill — MERGED (PR #156)

See `currentFormFillState.md` for full details. Remaining entity type testing in `fynTest.md`.

## Onboarding Updates (branch: `onboardingUpdates`)

**27 commits.** Browser verified: onboarding journey resumption, clickable steps, will planning, multiple executors, goals skip modal, asset tab form closing, NS&I field hiding, useful resources sidebar card, required field indicators, pension access age, mobile scroll anchoring, leasehold expiry date.

### What Changed

**Onboarding Journey:**
- Replaced legacy `onboarding_focus_area` with `life_stage` as single source of truth
- All step progress saves, skip, dashboard skip, step queries now use `life_stage`
- Removed "Focus area not set" errors for life stage mode users
- Changed `focus_area` DB column from enum to varchar (enum didn't include life stage values)
- Dashboard refreshes journey completeness on every mount (was stale after onboarding/Fyn data entry)
- Clickable step indicators in onboarding progress bar — users can jump to any step

**Will Planning:**
- Multiple executors support — "+ Add executor" button with remove option in both onboarding and estate will views
- Stored as comma-separated string in existing `executor_name` column
- Will sidebar link now shows WillPlanning overview when user has a will, Will Builder when they don't
- Estate data API now returns `will_info` so dashboard recognises existing wills
- "Build Your Will" banner hidden when user already has a will record

**Goals & Forms:**
- Goals step: clicking Continue without a goal shows skip confirmation modal ("Go Back" / "Skip Anyway") instead of validation error
- Assets step: switching tabs closes any open forms (was leaving forms open across tabs)
- Savings form: NS&I products (Premium Bonds, NS&I Savings) hide irrelevant fields (interest rate, access type, checkboxes, account number)
- Useful Resources moved from inside step forms to own sidebar card below Learning Milestones (12 step files updated)
- Useful Resources card styled white with shadow to match sidebar cards
- Family member form: red asterisks on required fields (Relationship, Email, First Name, Last Name)
- DC pension form: Planned Access Age field for SIPP/personal/stakeholder pensions (min 55, defaults from user profile retirement age, stored per-pension for individual accumulation/decumulation calcs)
- Onboarding: scroll to top on step and tab changes (mobile stacked layout fix)
- Property form: leasehold now asks for expiry date, calculates and displays remaining years automatically (removed manual remaining years input)

### Database Migration
```bash
php artisan migrate
```
- `2026_03_21_214000_change_focus_area_to_varchar` — changes `onboarding_progress.focus_area` and `users.onboarding_focus_area` from enum to varchar(50)

### PHP Files Modified
```
app/Services/Onboarding/OnboardingService.php
app/Http/Controllers/Api/OnboardingController.php
app/Services/LifeStage/LifeStageService.php
app/Services/Onboarding/JourneyStateService.php
app/Http/Controllers/Api/EstateController.php (will_info in estate data response)
```

### Frontend Files Modified
```
resources/js/views/Dashboard.vue (refreshCompleteness on mount)
resources/js/components/Onboarding/OnboardingWizard.vue (clickable step indicators)
resources/js/components/Onboarding/steps/WillInfoStep.vue (multiple executors)
resources/js/components/Onboarding/steps/GoalSetupStep.vue (skip confirmation modal instead of error)
resources/js/components/Onboarding/steps/AssetsStep.vue (close forms on tab switch)
resources/js/components/Estate/WillPlanning.vue (multiple executors)
resources/js/views/Estate/WillBuilderView.vue (show WillPlanning when user has will)
resources/js/store/modules/estate.js (read will_info from estate data)
resources/js/components/Savings/SaveAccountModal.vue (hide NS&I irrelevant fields)
resources/js/components/Onboarding/UsefulResources.vue (white bg, moved to sidebar)
resources/js/components/UserProfile/FamilyMemberFormModal.vue (required field asterisks)
resources/js/components/Retirement/DCPensionForm.vue (pension access age field)
resources/js/components/NetWorth/Property/PropertyForm.vue (leasehold expiry date, auto-calc remaining years)
+ 12 onboarding step files (removed inline UsefulResources)
```

---

## AI Form Fill Detail (merged to main via PR #156)

**Status:** Savings, investments, protection, pensions, and liabilities browser-verified working. Cross-page navigation with chat persistence. System prompt updated with explicit tool mapping for all entity types.

**23 commits on branch.** 80 agent tests passing.

### What Changed
- All 16 `handleCreate*` methods in CoordinatingAgent now return `fill_form` instead of saving directly
- New `aiFormFill` Vuex store coordinates field-by-field fill with 250ms highlight animation
- `fill_form` SSE event type in HasAiChat.php + aiChat.js
- 10 page components with `pendingFill` watchers + mounted checks to open modals
- 15 form components with highlight bindings + auto-submit
- `.ai-fill-highlight` CSS class (violet ring)
- Conditional field pre-setting for protection (policyType), pension (pension_type), property (hasMortgage)
- Field mapping fixes: investment account_type, pension scheme_type, route corrections
- DC pension: removed annual salary as required field (salary is on user profile)
- Fallback timeout increased from 3s to 10s
- Chat panel preserves conversation on cross-page navigation (was starting new conversation on every page change)
- Savings institution field now persists (pre-set before highlight sequence)
- Renamed create_estate_liability→create_liability, create_estate_asset→create_asset (AI wasn't using tools with "estate" in name for general debts)
- System prompt: added explicit tool mapping so AI knows which tool to use for every entity type
- Liability form: pre-set fields, expanded type validation, fixed Vue rendering crash after save
- `app/Services/AI/AiToolDefinitions.php` — tool renames + description updates
- `app/Services/PrerequisiteGateService.php` — updated tool names
- `resources/js/store/modules/estate.js` — removed addLiability intermediate commit (fixes Vue crash)

### Additional PHP Files (when merging aiFormFill)

```
app/Agents/CoordinatingAgent.php (modified — all 16 handlers)
app/Traits/HasAiChat.php (modified — fill_form SSE event)
```

### Additional Frontend Files (compiled into build)

```
resources/js/store/modules/aiFormFill.js (NEW)
resources/js/store/modules/aiChat.js (modified — fill_form handler, dispatch fix)
resources/js/store/index.js (modified — register aiFormFill)
resources/css/app.css (modified — .ai-fill-highlight class)
+ 10 page components (watchers + mounted checks)
+ 15 form components (highlight bindings + fill watchers)
```

See `currentFormFillState.md` for full file list and current status.

---

## Post-Deploy Verification

1. **Income**: Log in as preview persona → Income tab → verify zero-value types hidden, Other Income editable
2. **Goals**: Goals page → verify behind-schedule banner shows specific goal names with remaining amounts
3. **Chat**: Preview mode → verify no chat icon. Real user → verify docked Fyn chat
4. **Chat history**: As real user → send a message to Fyn → click history icon (clock) → verify conversation appears in list → click it → verify messages reload
5. **What-If**: As real user, ask Fyn "What if I retire at 55?" → verify scenario created, auto-navigated to detail page with AI narrative + module comparisons
6. **What-If list**: Navigate to /planning/what-if → verify scenario card in grid, click navigates to detail page, back button returns to list
7. **Life Events**: Create a new life event → verify affected module caches cleared
8. **Navigation**: Ask Fyn "show me my income" → verify navigates to `/valuable-info?section=income` (not `/profile`)
9. **Navigation**: Ask Fyn "show me my life events" → verify navigates to `/goals?tab=events`
10. **Navigation**: Ask Fyn "show me my retirement plan" → verify navigates to `/plans/retirement` (not `/holistic-plan`)
11. **Goals context**: Ask Fyn "what are my goals?" → verify Fyn lists goals with names, amounts, and status without needing a tool call
