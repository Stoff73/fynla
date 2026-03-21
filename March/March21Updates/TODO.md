# TODO — Fynla

*Last updated: 21 March 2026 — session 4 (income fix + goals/what-if + Fyn AI navigation + Fyn goals context)*

## Completed This Session

### Income Statement Fix (PR #147)
- [x] Added Other Income to IncomeOccupation.vue (view, edit, form data, total, submit)
- [x] Added `annual_other_income` to UserProfileService API response + tax calculation
- [x] Added Interest, Pension, Trust income to PersonalAccountsService P&L and cashflow
- [x] Replaced hardcoded frontend tax calculator with backend `UKTaxCalculator` using `TaxConfigService`
- [x] All income line items hidden when zero in view mode
- [x] All 20 PersonalAccountsService tests passing, 33/33 browser checks on production

### Goals Module Integration (PR #148)
- [x] SavingsAgent: goal shortfall, emergency fund goal, life event cash buffer recommendations
- [x] ProtectionAgent: goal commitments in coverage analysis with coverage note
- [x] EstateAgent: goal liquidity risk flagged in analysis
- [x] RetirementAgent: post-retirement goal detection
- [x] AI `create_goal` tool: accepts `monthly_contribution` with affordability assessment
- [x] GoalCard: inline contribution input with edit/save
- [x] Goals banner: replaced "X goals need attention" with specific goal names + remaining amounts
- [x] Chat icon hidden for preview/persona users
- [x] 80 agent tests passing (12 new goal integration tests)

### Life Events Expansion (PR #149)
- [x] 5 new life event types: divorce, marriage, new_child, job_loss, income_change
- [x] LifeEventIntegrationService: EVENT_MODULE_MAP + MODULE_CONTEXT for new types
- [x] LifeEventMonteCarloObserver: extended cache invalidation for affected modules
- [x] AI context enriched with per-module life event impact summaries

### What-If Scenario System (PR #150, #151)
- [x] Database: `what_if_scenarios` table (migration, model with SoftDeletes + Auditable, factory)
- [x] WhatIfScenarioService: core comparison engine (transient user copies, Now vs What-If, deltas)
- [x] API: 6 endpoints (CRUD + live comparison + count)
- [x] AI tool: `create_what_if_scenario` replaces `run_what_if_scenario` (excluded for preview users)
- [x] Frontend: WhatIfDashboard (card grid), WhatIfScenarioDetailView (dedicated detail page with back button)
- [x] Vuex store + API service
- [x] Router: `/planning/what-if` list, `/planning/what-if/:id` detail, death-of-spouse child route preserved
- [x] AI auto-navigation: Fyn creates scenario → navigates to detail page
- [x] Browser tested end-to-end: "What if I retire at 55?" → scenario created → detail page with AI narrative + module comparisons

### Fyn AI Navigation Upgrade (PR #152)
- [x] Query string parsing fixed: `handleNavigation()` properly parses `?section=income` for Vue Router
- [x] Client router: added savings, retirement, sipp, life events, individual plans, help, security settings, planning assumptions
- [x] AI tool definition: comprehensive categorised route list with legacy redirect warnings
- [x] System prompt: always navigate first (never refuse), offer to help on empty modules
- [x] Module dependency guidance: Fyn explains what estate/protection/retirement need from other modules
- [x] All 37 sidebar-accessible pages mapped to zero-token client-side keyword matches
- [x] Fixed "plan" keyword ambiguity (specific plans match before generic)

### Fyn AI Goals & Life Events Context (PR #153)
- [x] Goals summary in financial context: all active goals with ID, name, progress, status, contribution, target date in prompt
- [x] Life events in financial context: all upcoming events with ID, name, amount, months until, certainty in prompt
- [x] New `list_goals` tool: lightweight goal listing with IDs (no full agent analysis needed)
- [x] New `list_life_events` tool: lightweight event listing with IDs (no full agent analysis needed)
- [x] PrerequisiteGateService: both new tools pass through without blocking
- [x] Fyn can now reference goals/events by ID for updates and deletes without a prior tool call

### Uploads from Previous Session (confirmed by user)
- [x] `app/Observers/NetWorthCacheObserver.php`
- [x] `app/Providers/EventServiceProvider.php`
- [x] `app/Http/Controllers/Api/MortgageController.php`
- [x] `app/Http/Controllers/Api/PropertyController.php`
- [x] `composer dump-autoload` on server

## Needs Deployment

### Income Fix (build + PHP upload)
- [ ] `app/Services/UserProfile/PersonalAccountsService.php`
- [ ] `app/Services/UserProfile/UserProfileService.php`
- [ ] Rebuild frontend and upload `public/build/`

### Goals + Life Events + What-If + Fyn AI (build + PHP upload + migration)
- [ ] Run migrations on server (`what_if_scenarios` table + life event enum extension)
- [ ] Run `composer dump-autoload` on server (new model + service classes)
- [ ] Rebuild frontend and upload `public/build/`
- [ ] Upload PHP files (see deploy guide)
- [ ] Seed: `php artisan db:seed` (to refresh preview data)
- [ ] Clear caches on server

## Known Issues
- [ ] PropertyForm edit 422 — editing a property via the UI form returns 422 validation error
- [ ] Goals page: Goals from onboarding not visible on dedicated Goals page (j1 testing)
- [ ] Sidebar journey %: intermittently shows 0% on some pages (race condition)
- [ ] What-If: delta colours need polish (IHT reduction should show spring not raspberry)
- [ ] What-If: sidebar count badge not yet implemented
- [ ] What-If: preview persona seeder for example scenarios not yet created

## Tech Debt
- [ ] OnboardingWizard.vue: dynamic and static imports warning for 8 step components
- [ ] PropertyForm sends empty strings for nullable fields — clean up before submission
- [ ] `IncomeStatementTab.vue` is orphaned (never imported) — decide: wire into a view or delete

## Context for Next Session

Major upgrade completed across 8 PRs. The Goals & Life Events systems are now fully integrated into all module agents and Fyn's AI context. A full What-If Scenario System was built with AI-driven creation, persistent storage, living comparisons, and a clean card-grid → detail page UX pattern. Fyn AI navigation completely overhauled — all 37 sidebar pages reachable with zero tokens, query string parsing fixed, empty module behaviour improved. Fyn now has full goals and life events awareness at prompt time with IDs for direct updates/deletes.

**Design spec:** `docs/superpowers/specs/2026-03-21-goals-whatif-integration-design.md`
**Plans:** `docs/superpowers/plans/2026-03-21-goals-module-integration.md`, `2026-03-21-life-events-expansion.md`, `2026-03-21-whatif-scenario-system.md`
**Deploy guide:** `March/March21Updates/deployFix21.md`

## PRs Merged This Session
| PR | Branch | What |
|----|--------|------|
| #147 | incomeFix | Income: Other Income, hardcoded tax, zero-value cleanup |
| #148 | goalsUpgrade | Goals integration: 4 agents, AI tool, GoalCard, chat icon |
| #149 | lifeEventsExpansion | 5 new life event types, module cache, AI context |
| #150 | whatIfScenarios | What-If: DB, service, API, AI tool, dashboard |
| #151 | whatIfFixes | What-If: detail page, URL fixes, data flow |
| #152 | fynAI | Fyn AI navigation: routes, query parsing, empty module behaviour |
| #153 | fynGoals | Fyn goals/events: context in prompt, list tools, IDs |
