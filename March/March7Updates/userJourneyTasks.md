# Onboarding Redesign — Implementation Tasks

**Date:** 7 March 2026 | **Source:** `March/March7Updates/userJourneyPlan.md`
**Scope:** Focus-area-driven onboarding with 8 user journeys
**Estimate:** 3-4 weeks across 8 workstreams

---

## Workstream 1: Backend — Journey State Management

**Priority:** 1 (Foundation — everything depends on this)
**Agent:** `feature-dev:code-architect` (design), `Explore` (understand existing onboarding)
**Skills:** `/feature-dev`, `/test-driven-development`

### 1.1 Migration: Journey State Fields

- [ ] **1.1.1** Create migration `add_journey_fields_to_users_table`
  - Add `journey_states` JSON column (nullable, default null)
  - Add `journey_selections` JSON column (nullable, default null)
  - Add `dismissed_prompts` JSON column (nullable, default null)
  - `php artisan make:migration add_journey_fields_to_users_table`
- [ ] **1.1.2** Update `User` model — add columns to `$fillable` and `$casts`
  - `'journey_states' => 'array'`, `'journey_selections' => 'array'`, `'dismissed_prompts' => 'array'`
  - File: `app/Models/User.php`
- [ ] **1.1.3** Run migration
  - `php artisan migrate`
- [ ] **1.1.4** Reseed database
  - `php artisan db:seed`

### 1.2 JourneyStateService

- [ ] **1.2.1** Create `app/Services/Onboarding/JourneyStateService.php`
  - `declare(strict_types=1);`
  - Constructor: `private readonly` dependencies
  - Constants: `JOURNEYS = ['budgeting', 'protection', 'investment', 'retirement', 'estate', 'family', 'business', 'goals']`
  - Constants: `STATES = ['not_started', 'in_progress', 'completed']`
  - File: `app/Services/Onboarding/JourneyStateService.php`
- [ ] **1.2.2** Method: `getJourneyStates(User $user): array`
  - Returns all 8 journey states for the user
  - Default: all `not_started` if `journey_states` is null
- [ ] **1.2.3** Method: `startJourney(User $user, string $journey): void`
  - Sets journey state to `in_progress`
  - Records timestamp
- [ ] **1.2.4** Method: `completeJourney(User $user, string $journey): void`
  - Sets journey state to `completed`
  - Records completion timestamp
- [ ] **1.2.5** Method: `getSelectedJourneys(User $user): array`
  - Returns which journeys the user selected during onboarding
- [ ] **1.2.6** Method: `setSelectedJourneys(User $user, array $journeys): void`
  - Saves the user's focus area selections
  - Initialises journey_states for selected journeys
- [ ] **1.2.7** Method: `getJourneyProgress(User $user, string $journey): array`
  - Returns `['current_step' => int, 'total_steps' => int, 'percentage' => int]`
- [ ] **1.2.8** Test: `tests/Unit/Services/Onboarding/JourneyStateServiceTest.php`
  - `describe('JourneyStateService')` with Pest syntax
  - Test: default states are all not_started
  - Test: startJourney sets in_progress
  - Test: completeJourney sets completed
  - Test: getSelectedJourneys returns only selected
  - Test: invalid journey name throws exception
  - `./vendor/bin/pest tests/Unit/Services/Onboarding/JourneyStateServiceTest.php`

### 1.3 JourneyFieldResolver

- [ ] **1.3.1** Create `app/Services/Onboarding/JourneyFieldResolver.php`
  - `declare(strict_types=1);`
  - Inject `ModuleDataRequirementsService`
  - File: `app/Services/Onboarding/JourneyFieldResolver.php`
- [ ] **1.3.2** Define field requirements per journey
  - Constant: `JOURNEY_FIELDS` mapping each of 8 journeys to required fields
  - Personal fields (from `ModuleDataRequirementsService`) and relationship fields
  - Follow the deduplication matrix from `userJourneyPlan.md` Section 5
- [ ] **1.3.3** Method: `getFieldsForJourneys(array $journeys): array`
  - Accepts array of journey names e.g. `['protection', 'retirement']`
  - Returns deduplicated, merged field list
  - Groups into `personal_fields` and `financial_fields`
  - Each field includes: `key`, `label`, `why` (combined from all relevant journeys), `required`
- [ ] **1.3.4** Method: `getStepsForJourney(string $journey): array`
  - Returns ordered step list for a single journey
  - Each step: `['name' => string, 'component' => string, 'fields' => array]`
- [ ] **1.3.5** Method: `getStepsForJourneys(array $journeys): array`
  - Returns combined, deduplicated step list for multiple journeys
  - Personal info steps merged, module-specific steps appended
  - No field asked twice
- [ ] **1.3.6** Method: `getPreviewForJourneys(array $journeys): array`
  - Returns preview data for the dynamic preview on welcome page
  - `['personal_count' => int, 'financial_count' => int, 'personal_fields' => [...], 'financial_fields' => [...], 'estimated_minutes' => int]`
- [ ] **1.3.7** Test: `tests/Unit/Services/Onboarding/JourneyFieldResolverTest.php`
  - Test: single journey returns correct fields
  - Test: two journeys with overlap deduplicates (Protection + Retirement share date_of_birth)
  - Test: all 8 journeys returns 22 unique items
  - Test: empty array returns empty
  - Test: invalid journey name throws exception
  - Test: field ordering (personal before financial)
  - Test: combined "why" text when field spans multiple journeys
  - `./vendor/bin/pest tests/Unit/Services/Onboarding/JourneyFieldResolverTest.php`

### 1.4 DashboardPromptService

- [ ] **1.4.1** Create `app/Services/Onboarding/DashboardPromptService.php`
  - `declare(strict_types=1);`
  - Inject `JourneyStateService`
- [ ] **1.4.2** Method: `getPostJourneyPrompt(string $journey): array`
  - Returns prompt text and CTA for a completed journey
  - Uses prompts from `userJourneyPlan.md` Section 7.3
- [ ] **1.4.3** Method: `getDashboardPrompts(User $user): array`
  - Returns all prompts for the user's current state
  - Includes: post-journey prompts (undismissed), journey cards, empty state
  - Filters out dismissed prompts (from `users.dismissed_prompts`)
- [ ] **1.4.4** Method: `dismissPrompt(User $user, string $promptId): void`
  - Adds prompt to dismissed list
- [ ] **1.4.5** Test: `tests/Unit/Services/Onboarding/DashboardPromptServiceTest.php`
  - Test: completed journey returns correct prompt
  - Test: dismissed prompts are excluded
  - Test: empty dashboard (no journeys) returns start/goal CTAs
  - Test: in-progress journey returns continue prompt
  - `./vendor/bin/pest tests/Unit/Services/Onboarding/DashboardPromptServiceTest.php`

---

## Workstream 2: Backend — API Endpoints

**Priority:** 1 (Needed for frontend)
**Agent:** `Explore` (understand existing controller patterns)
**Skills:** `/feature-dev`

### 2.1 Journey Controller

- [ ] **2.1.1** Create `app/Http/Controllers/Api/JourneyController.php`
  - Inject `JourneyStateService`, `JourneyFieldResolver`, `DashboardPromptService`
  - Use `SanitizedErrorResponse` trait
  - File: `app/Http/Controllers/Api/JourneyController.php`
- [ ] **2.1.2** Endpoint: `GET /api/journeys/selections`
  - Returns user's selected journeys and their states
- [ ] **2.1.3** Endpoint: `POST /api/journeys/selections`
  - Saves user's focus area selections (array of journey names)
  - Validates: at least 1 selection, all valid journey names
  - Request: `app/Http/Requests/Onboarding/StoreJourneySelectionsRequest.php`
- [ ] **2.1.4** Endpoint: `GET /api/journeys/{journey}/steps`
  - Returns step list for a specific journey
  - Handles deduplication if user has multiple selections
- [ ] **2.1.5** Endpoint: `POST /api/journeys/{journey}/start`
  - Marks journey as in_progress
- [ ] **2.1.6** Endpoint: `POST /api/journeys/{journey}/complete`
  - Marks journey as completed
- [ ] **2.1.7** Endpoint: `GET /api/journeys/preview`
  - Accepts `?journeys[]=protection&journeys[]=retirement`
  - Returns preview data (field counts, field list, estimated time)
- [ ] **2.1.8** Endpoint: `GET /api/journeys/dashboard-prompts`
  - Returns all prompts for the current user
- [ ] **2.1.9** Endpoint: `POST /api/journeys/dismiss-prompt`
  - Dismisses a post-journey prompt
- [ ] **2.1.10** Add routes to `routes/api.php`
  - Wrap in `auth:sanctum` middleware
  - Prefix: `/journeys`
  - Add calculation endpoints to PreviewWriteInterceptor excluded patterns

### 2.2 API Feature Tests

- [ ] **2.2.1** Test: `tests/Feature/Onboarding/JourneyApiTest.php`
  - Test: save selections returns 201
  - Test: get selections returns saved data
  - Test: get preview returns deduplicated fields
  - Test: start journey sets in_progress
  - Test: complete journey sets completed
  - Test: dismiss prompt works
  - Test: data isolation (user A can't see user B's journeys)
  - Test: validation rejects invalid journey names
  - Test: preview persona compatibility
  - `./vendor/bin/pest tests/Feature/Onboarding/JourneyApiTest.php`

---

## Workstream 3: Frontend — Welcome Page Redesign

**Priority:** 1 (Entry point for all users)
**Agent:** `premium-ui-designer` (polish), `ux-writing-expert` (copy)
**Skills:** `/feature-dev`, `/frontend-design`

### 3.1 Redesign FocusAreaSelection.vue

- [ ] **3.1.1** Remove the "Your Free Trial" card (violet section with 7-day trial info)
  - File: `resources/js/components/Onboarding/FocusAreaSelection.vue`
  - Remove lines 71-88 (the trial info card)
- [ ] **3.1.2** Remove the "Quick Setup - 3 Steps" explanation card
  - Remove lines 19-29 (raspberry-50 card)
- [ ] **3.1.3** Remove the 3-step overview grid
  - Remove lines 31-57
- [ ] **3.1.4** Remove the "What You Get" card
  - Remove lines 61-68
- [ ] **3.1.5** Add "What would you like to focus on?" section
  - Heading: `text-h3 font-semibold text-horizon-500`
  - Subtext: "Choose one or more areas — we'll only ask for the information you need"
- [ ] **3.1.6** Add 4x2 focus area grid component
  - Import new `FocusAreaGrid.vue` component
  - Pass selected state and toggle handler

### 3.2 Create FocusAreaGrid.vue

- [ ] **3.2.1** Create `resources/js/components/Onboarding/FocusAreaGrid.vue`
  - 8 cards: Budgeting, Protection, Investment, Retirement, Estate Planning, Family, Business, Goal Tracking
  - Grid: `grid-cols-2 md:grid-cols-3 lg:grid-cols-4`
  - Each card: icon (40x40 rounded circle) + heading text
  - Unselected: `bg-white border border-light-gray`
  - Selected: `bg-savannah-100 border-2 border-raspberry-500` with checkmark
  - Hover: `border-horizon-300 shadow-sm`
  - Click toggles selection (multi-select)
  - Emits `@update:selections` with array of selected journey names
  - Use `v-preview-disabled` on cards
- [ ] **3.2.2** Define card data (icons, colours) as constants
  - Follow `fynlaDesignGuide.md` v1.2.0 colour tokens
  - SVG icons for each focus area
- [ ] **3.2.3** Add selection animation
  - Subtle `scale(1.02)` + border colour transition on select
  - Checkmark fade-in on selected state

### 3.3 Create JourneyPreview.vue

- [ ] **3.3.1** Create `resources/js/components/Onboarding/JourneyPreview.vue`
  - Props: `selections` (array of journey names)
  - Watches `selections` and calls `/api/journeys/preview` to get field data
  - Two-column layout: "Personal Details" | "Financial Details"
  - Each item: label + info icon `(i)`
  - Summary line: "We'll ask about X personal details and Y financial areas"
  - "Show details" toggle (collapsed by default to prevent scare factor)
  - Reassuring footer: "You can skip any question and come back later"
  - Estimated time: "About X minutes"
- [ ] **3.3.2** Smooth slide-down animation
  - Use `max-height` transition with `overflow-hidden`
  - Reserve container space to prevent layout shift
- [ ] **3.3.3** Info icons use `InfoTooltip.vue` component (see WS5)

### 3.4 Update Action Buttons

- [ ] **3.4.1** Update primary CTA
  - Text: "Start Your Journey" (plural if multiple selected: "Start Your Journeys")
  - Disabled state when 0 selections
  - `bg-raspberry-500 hover:bg-raspberry-600 rounded-button`
- [ ] **3.4.2** Update secondary action
  - "Skip to Dashboard" text link (`text-neutral-500 hover:text-raspberry-500`)
  - Remove "Complete Full Setup Instead" link (no longer needed)

### 3.5 Testing

- [ ] **3.5.1** Manual test: welcome page renders correctly
- [ ] **3.5.2** Manual test: grid selections toggle correctly
- [ ] **3.5.3** Manual test: preview updates dynamically
- [ ] **3.5.4** Manual test: responsive layout (desktop, tablet, mobile)
- [ ] **3.5.5** Manual test: preview persona flow (register → welcome → select → proceed)
  - `php artisan db:seed`

---

## Workstream 4: Frontend — Journey Step Flow

**Priority:** 2 (Depends on WS1 + WS3)
**Agent:** `feature-dev:code-architect` (design), `Explore` (understand step components)
**Skills:** `/feature-dev`

### 4.1 Update OnboardingWizard.vue

- [ ] **4.1.1** Support journey-specific step sequences
  - Read `journey_selections` from user to determine which steps to show
  - Call `JourneyFieldResolver` via API to get merged step list
  - File: `resources/js/components/Onboarding/OnboardingWizard.vue`
- [ ] **4.1.2** Add journey context header
  - Show which journey(s) the user is completing
  - "Setting up: Protection, Retirement" with icons
- [ ] **4.1.3** Update progress bar
  - Show journey-aware progress (not generic step count)
  - If multi-journey: show overall progress across all selected journeys
- [ ] **4.1.4** Add per-step info tooltips
  - Each form field shows `(i)` with "why" text from `ModuleDataRequirementsService`
  - Import `InfoTooltip.vue` component

### 4.2 Create Journey-Specific Step Components

- [ ] **4.2.1** Create `resources/js/components/Onboarding/steps/BudgetingSteps.vue`
  - Income, monthly spending, savings accounts
  - 3 steps
- [ ] **4.2.2** Create `resources/js/components/Onboarding/steps/GoalSetupStep.vue`
  - Goal type selector, target amount, target date
  - Optional: link savings/investment account
- [ ] **4.2.3** Update existing step components for reuse
  - `PersonalInfoStep.vue` — accept prop for which fields to show (only needed ones)
  - `IncomeStep.vue` — accept prop for context (which journey triggered it)
  - `FamilyInfoStep.vue` — accept prop for context
  - Ensure all steps emit `@next` and `@back` consistently
- [ ] **4.2.4** Create `resources/js/components/Onboarding/steps/JourneyCompletionStep.vue`
  - Dynamic completion message per journey
  - "Your [module] is ready" with CTA to view module
  - Celebration animation (confetti/checkmark — subtle)

### 4.3 Update Vuex Store

- [ ] **4.3.1** Create `resources/js/store/modules/journeys.js`
  - State: `selections`, `journeyStates`, `currentJourney`, `dashboardPrompts`, `dismissedPrompts`
  - Actions: `fetchSelections`, `saveSelections`, `startJourney`, `completeJourney`, `fetchDashboardPrompts`, `dismissPrompt`
  - Getters: `selectedJourneys`, `completedJourneys`, `inProgressJourneys`, `notStartedJourneys`
- [ ] **4.3.2** Register in `resources/js/store/index.js`
- [ ] **4.3.3** Create `resources/js/services/journeyService.js`
  - API wrapper for all `/api/journeys/*` endpoints

### 4.4 Update Router

- [ ] **4.4.1** Add journey-specific routes
  - `/onboarding/journey/:journey` — starts a specific journey
  - `/onboarding/welcome` — the redesigned welcome page
  - Update route guards for journey flow
  - File: `resources/js/router/index.js`

### 4.5 Testing

- [ ] **4.5.1** Manual test: select Protection → complete journey → verify dashboard
- [ ] **4.5.2** Manual test: select Protection + Retirement → verify deduplicated steps
- [ ] **4.5.3** Manual test: select all 8 → verify combined flow
- [ ] **4.5.4** Manual test: pause mid-journey → return → resume where left off
- [ ] **4.5.5** Test with all 6 preview personas
  - `php artisan db:seed`

---

## Workstream 5: Frontend — Info Tooltips

**Priority:** 2 (Used across WS3 and WS4)
**Agent:** `ux-writing-expert` (copy), `premium-ui-designer` (polish)
**Skills:** `/frontend-design`

### 5.1 Create InfoTooltip Component

- [ ] **5.1.1** Create `resources/js/components/Shared/InfoTooltip.vue`
  - Props: `why` (string), `howUsed` (string, optional), `position` (top/bottom/left/right)
  - Desktop: hover shows tooltip (CSS tooltip, not JS popup)
  - Mobile: tap opens popover below element with "Got it" dismiss button
  - Accessibility: `aria-describedby`, focusable, keyboard accessible
  - Icon: small `(i)` circle in `neutral-500`, hover → `violet-500`
  - Tooltip bg: `bg-horizon-500 text-white` rounded with arrow
- [ ] **5.1.2** First-encounter pulse animation
  - On first render (per session), icon briefly pulses with `violet-500` glow
  - Uses `animate-pulse` once, then stops
  - Controlled by sessionStorage flag (don't pulse every page load)
- [ ] **5.1.3** Test: tooltip renders on hover (desktop)
- [ ] **5.1.4** Test: popover opens on tap (mobile)
- [ ] **5.1.5** Test: keyboard focus shows content

### 5.2 Update ModuleDataRequirementsService

- [ ] **5.2.1** Add Budgeting module requirements
  - Fields: `monthly_expenditure`, `annual_employment_income`
  - Relationships: `savings_accounts`
  - With "why" text for each
  - File: `app/Services/UserProfile/ModuleDataRequirementsService.php`
- [ ] **5.2.2** Add Family module requirements
  - Fields: `marital_status`
  - Relationships: `family_members`, `spouse`
- [ ] **5.2.3** Add Business module requirements
  - Fields: `occupation`, `employment_status`
  - Relationships: `business_interests`
- [ ] **5.2.4** Add Goals module requirements
  - Fields: `date_of_birth`, `annual_employment_income`, `monthly_expenditure`
  - Relationships: `goals`
- [ ] **5.2.5** Add `how_used` field to all module requirement definitions
  - Second line of tooltip content: what calculation/insight it powers
  - Follow tone guidelines from `userJourneyPlan.md` Section 8
- [ ] **5.2.6** Test: updated service still passes existing tests
  - `./vendor/bin/pest tests/Unit/Services/UserProfile/`

---

## Workstream 6: Frontend — Dashboard Integration

**Priority:** 2 (Depends on WS1 + WS4)
**Agent:** `premium-ui-designer` (polish), `ux-writing-expert` (copy)
**Skills:** `/frontend-design`

### 6.1 Journey Cards on Dashboard

- [ ] **6.1.1** Create `resources/js/components/Dashboard/JourneyCard.vue`
  - Props: `journey` (object with name, state, progress)
  - 3 visual states:
    - Not started: white card, "Start your [name] journey", muted CTA
    - In progress: savannah card, progress bar, "Continue" CTA
    - Completed: spring-50 card, checkmark, "View [module]" links
  - Dismissible (not-started cards can be hidden)
  - `v-preview-disabled` on CTAs
  - File: `resources/js/components/Dashboard/JourneyCard.vue`
- [ ] **6.1.2** Create journey cards section in Dashboard.vue
  - Add section between alert banners and main content
  - Grid: `grid-cols-1 md:grid-cols-2` for journey cards
  - Only show for users with `journey_selections` set
  - Maximum 3 visible at once with "Show more" toggle
  - File: `resources/js/views/Dashboard.vue`
- [ ] **6.1.3** Integrate with `journeys` Vuex store
  - Fetch journey states on dashboard mount
  - Update when user completes a journey

### 6.2 Post-Journey Prompts

- [ ] **6.2.1** Create `resources/js/components/Dashboard/PostJourneyPrompt.vue`
  - Dismissible banner at top of dashboard
  - Shows journey-specific prompt text and CTA
  - One prompt at a time (queue if multiple)
  - Spring-50 background with spring border
  - Dismiss button stores in `dismissed_prompts`
- [ ] **6.2.2** Integrate with Dashboard.vue
  - Fetch prompts from `DashboardPromptService` API
  - Show first undismissed prompt
  - Refresh after dismiss

### 6.3 Empty Dashboard State

- [ ] **6.3.1** Create `resources/js/components/Dashboard/EmptyDashboard.vue`
  - Shows when user has no journey_selections and no financial data
  - Two CTAs: "Set a Financial Goal" and "Start a Planning Journey"
  - Warm, inviting copy (not "your dashboard is empty")
  - "Or explore on your own" text with nav hint
  - File: `resources/js/components/Dashboard/EmptyDashboard.vue`
- [ ] **6.3.2** Integrate with Dashboard.vue
  - Conditional rendering: show empty state when applicable
  - "Start a Planning Journey" routes to `/onboarding/welcome`
  - "Set a Financial Goal" routes to `/goals`

### 6.4 Update ProfileCompletionCards

- [ ] **6.4.1** Update `ProfileCompletionCards.vue` for new journey system
  - Show cards based on `journey_selections` (not just `onboarding_asset_flags`)
  - Support new journey-based routing
  - Keep existing functionality for users on old onboarding
  - File: `resources/js/components/Dashboard/ProfileCompletionCards.vue`

### 6.5 Testing

- [ ] **6.5.1** Manual test: not-started journey cards appear correctly
- [ ] **6.5.2** Manual test: in-progress journey card shows progress
- [ ] **6.5.3** Manual test: completed journey card shows module links
- [ ] **6.5.4** Manual test: post-journey prompt appears and dismisses
- [ ] **6.5.5** Manual test: empty dashboard shows for user with no data
- [ ] **6.5.6** Manual test: all 6 preview personas
  - `php artisan db:seed`

---

## Workstream 7: Backend — Preview Persona Updates

**Priority:** 3 (After core flow works)
**Agent:** `Explore` (understand persona seeder)
**Skills:** `/systematic-debugging`

### 7.1 Update PreviewUserSeeder

- [ ] **7.1.1** Add `journey_states` to each persona
  - `young_family`: Protection completed, Budgeting completed, Goals in_progress
  - `peak_earners`: All 8 completed (full data)
  - `widow`: Estate completed, Protection completed
  - `entrepreneur`: Business completed, Investment completed, Retirement in_progress
  - `young_saver`: Budgeting completed, Goals completed
  - `retired_couple`: Retirement completed, Estate completed, Protection completed
  - File: `database/seeders/PreviewUserSeeder.php`
- [ ] **7.1.2** Add `journey_selections` to each persona
  - Match the journey_states above
- [ ] **7.1.3** Reseed and verify
  - `php artisan db:seed`
  - Test all 6 personas via landing page
- [ ] **7.1.4** Test: personas load correctly with journey data
  - `./vendor/bin/pest tests/Feature/Preview/`

---

## Workstream 8: Testing & Polish

**Priority:** 3 (Final validation)
**Agent:** `feature-dev:code-reviewer` (review), `premium-ui-designer` (polish)
**Skills:** `/code-review`, `/systematic-debugging`

### 8.1 Unit Tests

- [ ] **8.1.1** `tests/Unit/Services/Onboarding/JourneyStateServiceTest.php`
  - 8+ tests covering all state transitions
  - `./vendor/bin/pest tests/Unit/Services/Onboarding/JourneyStateServiceTest.php`
- [ ] **8.1.2** `tests/Unit/Services/Onboarding/JourneyFieldResolverTest.php`
  - 10+ tests covering deduplication matrix
  - `./vendor/bin/pest tests/Unit/Services/Onboarding/JourneyFieldResolverTest.php`
- [ ] **8.1.3** `tests/Unit/Services/Onboarding/DashboardPromptServiceTest.php`
  - 6+ tests covering prompt generation and dismissal
  - `./vendor/bin/pest tests/Unit/Services/Onboarding/DashboardPromptServiceTest.php`

### 8.2 Feature Tests

- [ ] **8.2.1** `tests/Feature/Onboarding/JourneyApiTest.php`
  - 12+ tests covering all API endpoints
  - Data isolation, validation, auth
  - `./vendor/bin/pest tests/Feature/Onboarding/JourneyApiTest.php`
- [ ] **8.2.2** `tests/Feature/Onboarding/JourneyFlowTest.php`
  - End-to-end journey completion
  - Multi-journey deduplication
  - Resume after pause
  - `./vendor/bin/pest tests/Feature/Onboarding/JourneyFlowTest.php`

### 8.3 Regression Tests

- [ ] **8.3.1** Verify existing onboarding tests still pass
  - `./vendor/bin/pest tests/Feature/Onboarding/`
- [ ] **8.3.2** Verify all preview personas still work
  - `php artisan db:seed`
  - Manual test through landing page
- [ ] **8.3.3** Verify ModuleDataRequirementsService backwards compatible
  - Existing callers still get correct data
  - New modules (budgeting, family, business, goals) added without breaking existing

### 8.4 Accessibility Tests

- [ ] **8.4.1** Keyboard navigation through focus area grid
  - Tab between cards, Enter/Space to toggle
- [ ] **8.4.2** Screen reader announces card selection state
  - `role="checkbox"`, `aria-checked`, `aria-label`
- [ ] **8.4.3** Info tooltips accessible via keyboard
  - Focus on `(i)` icon shows content via `aria-describedby`
- [ ] **8.4.4** Touch device popover works (no hover dependency)
  - Test on iOS Safari, Android Chrome

### 8.5 Final Validation

- [ ] **8.5.1** Run full test suite
  - `./vendor/bin/pest`
- [ ] **8.5.2** Run architecture tests
  - `./vendor/bin/pest --testsuite=Architecture`
- [ ] **8.5.3** Format code
  - `./vendor/bin/pint`
- [ ] **8.5.4** Reseed database
  - `php artisan db:seed`
- [ ] **8.5.5** Code review
  - `/code-review`
- [ ] **8.5.6** UI polish pass
  - Agent: `premium-ui-designer`
  - Focus: animations, transitions, responsive layout, visual consistency with `fynlaDesignGuide.md`

---

## Summary

| # | Workstream | Priority | Tasks | Dependencies |
|---|-----------|----------|-------|-------------|
| 1 | Journey State Management | 1 | 16 | None |
| 2 | API Endpoints | 1 | 12 | WS1 |
| 3 | Welcome Page Redesign | 1 | 16 | WS2 |
| 4 | Journey Step Flow | 2 | 15 | WS1, WS3 |
| 5 | Info Tooltips | 2 | 11 | None (can parallel with WS3) |
| 6 | Dashboard Integration | 2 | 13 | WS1, WS4 |
| 7 | Preview Persona Updates | 3 | 4 | WS1 |
| 8 | Testing & Polish | 3 | 16 | All |
| **Total** | | | **103** | |

### Execution Order

```
Phase 1 (Foundation):  WS1 + WS5 in parallel
Phase 2 (API + UI):    WS2 + WS3 in parallel (after WS1)
Phase 3 (Flow):        WS4 (after WS2 + WS3)
Phase 4 (Dashboard):   WS6 (after WS4)
Phase 5 (Polish):      WS7 + WS8 (after WS6)
```

### New Files To Create

| Type | File | Purpose |
|------|------|---------|
| Migration | `database/migrations/*_add_journey_fields_to_users_table.php` | journey_states, journey_selections, dismissed_prompts |
| Service | `app/Services/Onboarding/JourneyStateService.php` | Journey state management |
| Service | `app/Services/Onboarding/JourneyFieldResolver.php` | Field deduplication |
| Service | `app/Services/Onboarding/DashboardPromptService.php` | Dashboard prompt generation |
| Controller | `app/Http/Controllers/Api/JourneyController.php` | Journey API endpoints |
| Request | `app/Http/Requests/Onboarding/StoreJourneySelectionsRequest.php` | Validation |
| Vue | `resources/js/components/Onboarding/FocusAreaGrid.vue` | 4x2 focus area grid |
| Vue | `resources/js/components/Onboarding/JourneyPreview.vue` | Dynamic field preview |
| Vue | `resources/js/components/Onboarding/steps/BudgetingSteps.vue` | Budgeting journey steps |
| Vue | `resources/js/components/Onboarding/steps/GoalSetupStep.vue` | Goal tracking journey |
| Vue | `resources/js/components/Onboarding/steps/JourneyCompletionStep.vue` | Journey completion screen |
| Vue | `resources/js/components/Dashboard/JourneyCard.vue` | Dashboard journey state card |
| Vue | `resources/js/components/Dashboard/PostJourneyPrompt.vue` | Post-journey prompt banner |
| Vue | `resources/js/components/Dashboard/EmptyDashboard.vue` | Empty dashboard state |
| Vue | `resources/js/components/Shared/InfoTooltip.vue` | Accessible info tooltip |
| Store | `resources/js/store/modules/journeys.js` | Journey Vuex module |
| Service | `resources/js/services/journeyService.js` | Journey API wrapper |
| Test | `tests/Unit/Services/Onboarding/JourneyStateServiceTest.php` | Unit tests |
| Test | `tests/Unit/Services/Onboarding/JourneyFieldResolverTest.php` | Unit tests |
| Test | `tests/Unit/Services/Onboarding/DashboardPromptServiceTest.php` | Unit tests |
| Test | `tests/Feature/Onboarding/JourneyApiTest.php` | Feature tests |
| Test | `tests/Feature/Onboarding/JourneyFlowTest.php` | E2E flow tests |

### Files To Modify

| File | Changes |
|------|---------|
| `app/Models/User.php` | Add journey columns to $fillable, $casts |
| `app/Services/UserProfile/ModuleDataRequirementsService.php` | Add budgeting, family, business, goals modules + how_used field |
| `resources/js/components/Onboarding/FocusAreaSelection.vue` | Complete redesign |
| `resources/js/components/Onboarding/OnboardingWizard.vue` | Journey-aware step flow |
| `resources/js/components/Onboarding/steps/PersonalInfoStep.vue` | Accept field filter props |
| `resources/js/components/Onboarding/steps/IncomeStep.vue` | Accept context props |
| `resources/js/components/Dashboard/ProfileCompletionCards.vue` | Support journey system |
| `resources/js/views/Dashboard.vue` | Journey cards, prompts, empty state |
| `resources/js/store/index.js` | Register journeys module |
| `resources/js/router/index.js` | Journey routes |
| `routes/api.php` | Journey API routes |
| `database/seeders/PreviewUserSeeder.php` | Add journey_states to personas |
