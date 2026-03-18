# Life-Stage-Journey: Bug Fixes & Information Completeness Architecture

**Branch:** `feature/life-stage-journey` (merged to main)
**Date:** 2026-03-18
**Source:** Code review of all files flagged in `poorWork.md` + architectural analysis

---

## Architectural Principle: Information Completeness as Single Source of Truth

The platform's reliability depends on a **two-tier information completeness model** driven by the existing `DataReadinessService` (per module) and `PrerequisiteGateService` (centralised). These services must be the **single source of truth** for:

1. **Display level** (partial data) — "Has the user entered *any* data for this module?" Enough to show a dashboard card. Does not require all fields.
2. **Advice level** (sufficient data) — "Has the user entered enough for the Agent to give regulated financial advice?" Driven by BLOCKING checks in `DataReadinessService`. All blocking fields must be present.

### Why This Matters

- **Regulated** — Agents cannot give financial advice without sufficient KYC data. The BLOCKING gates enforce this.
- **Evaluation-driven** — Each module knows exactly what's missing and at what severity (BLOCKING / WARNING / INFO).
- **Decision-centric** — The AI chat already refuses advice on blocked modules via `PrerequisiteGateService.buildCompletenessContext()`.
- **Dynamic** — Data added outside onboarding (e.g. via profile pages) automatically updates completeness.

### Current Architecture (backend — works correctly)

| Layer | Service | Purpose |
|-------|---------|---------|
| Module gates | `DataReadinessService` (per module) | 3 levels: BLOCKING / WARNING / INFO |
| Centralised gate | `PrerequisiteGateService` | Mirrors all module gates, used by AI chat |
| Agent analysis | `BaseAgent.analyze()` | Calls readiness service BEFORE calculations |
| AI chat | `HasAiChat.buildPrerequisiteStateContext()` | Tells assistant which modules are ready vs blocked |
| Profile score | `ProfileCompletenessChecker` | Soft 0-100% for UI guidance (not blocking) |

### What's Broken (frontend — not using the above)

The onboarding progress indicator uses `life_stage_completed_steps` (step-click tracking) instead of actual data presence. The dashboard uses ad-hoc computed properties instead of readiness checks. This creates a fragile system where:

- A user can click through all steps without entering data and show 100% progress
- The dashboard may show empty cards because it checks data independently of readiness gates
- The AI chat correctly refuses advice (via PrerequisiteGateService) but the dashboard gives no indication of what's missing

### Target State

| System | Currently Driven By | Should Be Driven By |
|--------|-------------------|---------------------|
| Progress indicator | `life_stage_completed_steps` (click tracking) | `DataReadinessService` checks (actual data presence) |
| Dashboard card visibility | Ad-hoc computed properties per card | Display-level completeness (any data exists for module) |
| Dashboard "what's missing" | `ProfileCompletenessChecker` (soft score) | `PrerequisiteGateService` (authoritative per module) |
| Agent advice | `DataReadinessService` BLOCKING checks | No change — already correct |
| AI chat advice | `PrerequisiteGateService` | No change — already correct |

---

## Phase 0 — Wire Up Information Completeness (architectural fix)

### 0A. Create frontend completeness API endpoint

Expose `PrerequisiteGateService.buildCompletenessContext()` to the frontend so dashboard and progress components can query authoritative module readiness.

- [x] Create `GET /api/completeness` endpoint returning per-module status (display level + advice level + missing fields)
- [x] Return structure: `{ modules: { protection: { has_data: bool, can_advise: bool, missing: [], level: 'blocking|warning|info' }, ... } }`
- [x] Include both display-level checks (any data exists) and advice-level checks (BLOCKING gates pass)
- [x] Write Pest test: endpoint returns correct structure for user with no data
- [x] Write Pest test: endpoint returns correct structure for user with partial data (some modules)
- [x] Write Pest test: endpoint returns correct structure for user with complete data

### 0B. Create Vuex completeness module

- [x] Create `store/modules/completeness.js` — fetches from `/api/completeness`, stores per-module status
- [x] Expose getters: `hasModuleData(module)` (display level), `canAdviseModule(module)` (advice level), `moduleMissing(module)` (missing fields list)
- [x] Fetch on dashboard load and after onboarding completion
- [x] Refresh after any data-saving action (profile update, account creation, etc.)

### 0C. Rewire progress indicator to use completeness data

- [ ] Update `JourneyProgressHero.vue` to use completeness store instead of `life_stage_completed_steps`
- [ ] Progress = percentage of stage-relevant modules at display level (any data entered)
- [ ] Show per-module status: green (can advise), amber (has data, missing fields for advice), grey (no data)
- [ ] Keep `life_stage_completed_steps` as secondary tracking for step resumption only (not progress display)
- [ ] Browser test: enter data for 3 of 6 modules, verify progress shows ~50%
- [ ] Browser test: add data via profile pages (not onboarding), verify progress updates

### 0D. Rewire dashboard card visibility to use completeness data

- [ ] Dashboard cards use `hasModuleData(module)` to determine visibility instead of ad-hoc checks
- [ ] Cards that show when module has data but can't advise: display data + show "Complete your profile" guidance with specific missing fields from `moduleMissing(module)`
- [ ] Remove ad-hoc `isStudentPersona`, `hasProtectionData`, etc. — replace with completeness getters
- [ ] Browser test: verify cards appear/disappear based on actual data presence across all stages

---

## Phase 1 — Critical Data Bugs (P0)

### 1. Student loan data never loads on dashboard

- **File:** `resources/js/views/Dashboard.vue` (~line 1749)
- **Problem:** `estate/fetchEstateData` missing from student persona `moduleLoaders`. The `studentLiability` computed checks `estate?.liabilities` but estate data is never fetched. Student Debt card always shows "No student loan data available."
- **Fix:** Add estate fetch to student loaders. Longer-term, this is solved by Phase 0D (completeness-driven card visibility).

- [ ] Add `{ name: 'estate', action: 'estate/fetchEstateData' }` to student persona module loaders in Dashboard.vue
- [ ] Verify student loan entered during onboarding appears on dashboard
- [ ] Write Pest test: student persona dashboard loads estate/liability data
- [ ] Browser test: complete university journey with student loan, verify dashboard shows it

### 2. Monthly expenditure persistence — silently swallowed errors

- **File:** `resources/js/components/Onboarding/steps/SimpleExpenditureStep.vue` (~line 128)
- **Problem:** `.catch(() => {})` silently hides failure from `userProfile/updatePersonalInfo`. If backend rejects `monthly_expenditure`, data is lost. This field is a BLOCKING requirement for Savings and Investment `DataReadinessService` — silent loss means the Agent will refuse advice with no explanation visible to the user.
- **Fix:** Verify backend accepts the field. Remove silent catch.

- [x] Check backend `updatePersonalInfo` endpoint accepts `monthly_expenditure` field
- [x] If not accepted, add `monthly_expenditure` to the allowed fields in the form request
- [x] Replace `.catch(() => {})` with proper error handling (at minimum `console.error`)
- [ ] Write Pest test: updating personal info with `monthly_expenditure` persists to DB
- [ ] Browser test: enter expenditure in onboarding, verify it persists after page reload
- [ ] Verify: after onboarding with expenditure, Savings Agent `can_proceed` returns `true`

### 3. Student-specific fields never forwarded during onboarding

- **File:** `resources/js/components/Onboarding/OnboardingWizard.vue` (~line 652)
- **Problem:** `handleLifeStageStepSave` splits personal info into `personalData` and `occupationData` but does NOT include `university`, `student_number`, or `education_level`. Fields render but data is silently dropped.
- **Fix:** Add student fields to the save handler.

- [ ] Add `university`, `student_number`, `education_level` to `handleLifeStageStepSave` personal info handler
- [ ] Verify backend accepts and persists these fields
- [ ] Write Pest test: personal info update with student fields persists correctly
- [ ] Browser test: complete university journey, verify student fields saved to user profile

---

## Phase 2 — Functional Bugs (P1)

### 4. JourneyProgressHero dynamic Tailwind classes won't compile

- **File:** `resources/js/components/Journey/JourneyProgressHero.vue` (~lines 9, 49)
- **Problem:** Template literal class construction (`:class="'text-' + stageColour + '-500'"`) — Tailwind JIT can't detect these. Won't render in production.
- **Fix:** Use lookup map pattern (like SideMenu.vue).

- [x] Replace dynamic class construction with a lookup map of full class strings
- [ ] Verify all stage colours resolve to valid classes
- [ ] Browser test: check colours render in dev AND after production build (`./deploy/fynla-org/build.sh`)

### 5. `alert()` used for validation in pension forms

- **Files:** `DCPensionForm.vue` (~line 601), `StatePensionForm.vue` (~line 252)
- **Problem:** Browser `alert()` for validation — inconsistent UX, especially during onboarding.
- **Fix:** Replace with inline validation errors.

- [x] Replace `alert()` in DCPensionForm with inline validation errors
- [x] Replace `alert()` in StatePensionForm with inline validation errors
- [ ] Browser test: trigger validation errors in both forms during onboarding, verify inline display

### 6. `bg-eggshell-5000` typo in modal backdrops

- **Files:** `SaveAccountModal.vue` (line 7), `PolicyFormModal.vue` (line 4)
- **Problem:** `bg-eggshell-5000` is not a valid Tailwind class. Backdrop renders transparent.
- **Fix:** Correct to proper class name.

- [x] Fix `bg-eggshell-5000` to correct class in SaveAccountModal.vue
- [x] Fix `bg-eggshell-5000` to correct class in PolicyFormModal.vue
- [ ] Browser test: open both modals, verify backdrop renders correctly

### 7. LiabilityForm not suited for student-loan onboarding step

- **File:** `resources/js/components/Estate/LiabilityForm.vue`
- **Problem:** Generic liability form for student-loan step. No auto-selection of `student_loan` type, no plan type fields. Emits `cancel` not `close`. Custom CSS inconsistent with Tailwind.
- **Fix:** Create dedicated `StudentLoanStep.vue` or add student-loan context to LiabilityForm.

- [ ] Create dedicated `StudentLoanStep.vue` OR add student-loan onboarding context to LiabilityForm
- [ ] Auto-select `student_loan` liability type when in student-loan onboarding step
- [ ] Add student-loan-specific fields (plan type: Plan 1-5, interest rate)
- [ ] Fix event emission: emit `close` not `cancel` when used in onboarding context
- [ ] Browser test: complete student-loan step in university journey, verify correct type and fields

### 8. `handleLifeStageSkip` doesn't track progress

- **File:** `resources/js/components/Onboarding/OnboardingWizard.vue` (~line 634)
- **Problem:** Skip handler advances index but never calls `completeStep`. Progress doesn't update. Re-entering onboarding replays skipped steps.
- **Note:** With Phase 0C (completeness-driven progress), skip tracking becomes secondary. But for step-resumption purposes, skipped steps should still be recorded.

- [x] Add `completeStep` dispatch to `handleLifeStageSkip` with a `skipped: true` flag
- [ ] Store skipped steps separately so they can be distinguished from completed steps
- [ ] Browser test: skip steps, verify re-entering onboarding doesn't replay them

### 9. WillInfoStep navigates away without saving state

- **File:** `resources/js/components/Onboarding/steps/WillInfoStep.vue` (~line 191)
- **Problem:** `router.push('/estate/will-builder')` exits onboarding entirely. No state saved. No return path.
- **Fix:** Open in new tab or save state before navigation.

- [ ] Save onboarding progress before navigating to will builder
- [ ] Add return mechanism (save current step index to store/localStorage)
- [ ] OR open will builder in new tab instead of in-app navigation
- [ ] Browser test: click "Create a Will" during onboarding, verify state preserved or new tab opens

### 10. `isStudentPersona` only matches preview persona

- **File:** `resources/js/views/Dashboard.vue` (~lines 900-901)
- **Problem:** Checks `preview_persona_id === 'student'` only. Real university-stage users don't get student dashboard layout.
- **Note:** Phase 0D (completeness-driven visibility) will replace this entirely, but as an interim fix, check life stage too.

- [x] Update `isStudentPersona` to also check `life_stage === 'university'`
- [ ] Verify non-preview university-stage users see student dashboard layout
- [ ] Write Pest test: non-preview user with university life stage gets student dashboard data
- [ ] Browser test: register as new user, select university stage, verify student dashboard displays

### 11. PersonalInformation missing `student` employment status

- **File:** `resources/js/components/UserProfile/PersonalInformation.vue` (~lines 448-460)
- **Problem:** No `student` option. University-stage users can't set their status correctly. `employment_status` is a WARNING-level field in several `DataReadinessService` checks — wrong value affects analysis quality.

- [x] Add `{ value: 'student', label: 'Student' }` to employment status options
- [ ] Verify backend accepts `student` as a valid employment status value
- [ ] Browser test: select university stage, verify student employment status is available

### 12. PropertyForm "wizard within wizard" in onboarding

- **File:** `resources/js/components/NetWorth/Property/PropertyForm.vue`
- **Problem:** Internal multi-step wizard + OnboardingWizard's Back/Skip/Continue both visible.

- [x] Add property step to `stepsWithOwnNav` array in OnboardingWizard.vue
- [ ] Verify only PropertyForm's internal navigation shows during onboarding
- [ ] Browser test: reach property step in onboarding, verify no duplicate navigation buttons

---

## Phase 3 — Code Quality (P2)

### 13. Hardcoded hex colours in DCPensionForm scrollbar

- **File:** `DCPensionForm.vue` (~lines 640-647)
- **Problem:** `#888` and `#555` in scrollbar styles. Violates CLAUDE.md rule 12.

- [x] Remove custom scrollbar CSS from DCPensionForm scoped styles
- [x] Apply global `.scrollbar-thin` class to the scrollable container
- [ ] Browser test: verify scrollbar still renders correctly

### 14. Duplicated CSS animations in pension forms

- **Files:** `DCPensionForm.vue` (~lines 626-647), `StatePensionForm.vue` (~lines 278-282)
- **Problem:** Custom `@keyframes fadeIn` duplicated. Global `.animate-fade-in` exists.

- [x] Remove `@keyframes fadeIn` from DCPensionForm, use `.animate-fade-in` global class
- [x] Remove `@keyframes fadeIn` from StatePensionForm, use `.animate-fade-in` global class
- [ ] Browser test: verify fade animations still work in both forms

### 15. Dead code in SideMenu.vue

- **File:** `resources/js/components/SideMenu.vue`
- **Problem:** Unused: `primaryItems`, `exploreItems`, `activeFlyoutItemClass`, `exploreFlyoutOpen`, `SideMenuIcon` import.

- [x] Remove unused `primaryItems` computed property
- [x] Remove unused `exploreItems` computed property
- [x] Remove unused `activeFlyoutItemClass` computed property
- [x] Remove unused `exploreFlyoutOpen` ref
- [x] Remove unused `SideMenuIcon` import
- [ ] Browser test: verify sidebar still functions correctly after cleanup

### 16. LiabilityForm uses custom CSS instead of Tailwind

- **File:** `LiabilityForm.vue` (~lines 484-743)
- **Problem:** 260 lines of custom CSS. `.btn-primary:hover` same colour as default.

- [ ] Refactor LiabilityForm template to use Tailwind utility classes
- [ ] Remove custom CSS classes (`.form-group`, `.form-control`, `.btn`, etc.)
- [ ] Fix `.btn-primary:hover` to use `bg-raspberry-600`
- [ ] Browser test: verify form renders correctly with Tailwind classes

### 17. Non-palette colour tokens in PersonalInformation

- **File:** `PersonalInformation.vue` (~lines 157-166)
- **Problem:** `bg-blue-50`, `text-blue-700` — not in design palette.

- [x] Replace `bg-blue-50` with palette-compliant colour (e.g., `bg-violet-50` or `bg-light-blue-100`)
- [x] Replace `text-blue-700`/`text-blue-600` with palette-compliant colour
- [ ] Browser test: verify domicile info boxes render with correct colours

### 18. `formatCurrency` imported directly instead of using `currencyMixin`

- **Files:** `GoalSetupStep.vue` (line 116), `SimpleExpenditureStep.vue` (line 76)
- **Problem:** Direct import instead of `currencyMixin` per CLAUDE.md rule 6.
- **Note:** These are Composition API components (`<script setup>`). The mixin pattern is Options API. Direct import from `@/utils/currency` is the correct Composition API equivalent — document this as an accepted pattern.

- [ ] Document in CLAUDE.md that Composition API components may use `import { formatCurrency } from '@/utils/currency'` directly
- [ ] OR create a `useCurrency` composable wrapping the util for consistency
- [ ] Verify currency formatting works correctly in both components

### 19. Insufficient test coverage

- **File:** `tests/Feature/LifeStageControllerTest.php`
- **Problem:** Only 4 tests. No coverage for completeness checks, edge cases, or error handling.

- [ ] Write Pest test: `getDataCompleteness` returns correct completeness for each stage
- [ ] Write Pest test: completing a step updates progress correctly
- [ ] Write Pest test: invalid step IDs are rejected
- [ ] Write Pest test: duplicate step completion is handled gracefully
- [ ] Write Pest test: stage transition suggestions return valid next stages
- [ ] Write Pest test: completeness endpoint returns correct display-level and advice-level status per module
- [ ] Run full Pest test suite — all tests pass

---

## Verification Checklist (after all phases)

### Pest Tests

- [ ] Run `./vendor/bin/pest tests/Feature/LifeStageControllerTest.php` — all tests pass
- [ ] Run `./vendor/bin/pest tests/Unit/Services/LifeStage/` — all tests pass
- [ ] Run `./vendor/bin/pest` — full suite passes, no regressions

### Information Completeness Verification

- [ ] User with NO data: all modules show `has_data: false`, `can_advise: false`
- [ ] User with partial data (e.g., DOB + income but no expenditure): Savings shows `has_data: true`, `can_advise: false`, `missing: ['expenditure']`
- [ ] User with complete data: all relevant modules show `can_advise: true`
- [ ] AI chat correctly refuses advice on blocked modules and explains what's missing
- [ ] Agent `analyze()` returns `can_proceed: false` for incomplete modules
- [ ] Progress indicator reflects actual data presence, NOT step-click history
- [ ] Adding data via profile pages (not onboarding) updates progress indicator

### Browser Tests — All 5 Journeys

- [ ] **University journey:** Register > select university > fill ALL fields (personal info with student fields, student loan, income, expenditure, savings, goal) > complete > dashboard shows ALL data including student loan > AI chat can advise on modules with sufficient data
- [ ] **Starting Out journey:** Register > select starting out > fill ALL fields > complete > dashboard shows all data, sidebar shows correct items > completeness shows correct per-module status
- [ ] **Building Wealth journey:** Register > select building wealth > fill ALL fields (including property, mortgage, investments) > complete > dashboard shows all data, net worth includes property/mortgage > Investment Agent `can_proceed` returns true
- [ ] **Established journey:** Register > select established > fill ALL fields (including pensions, estate) > complete > dashboard shows all data > Retirement Agent and Estate Agent both return `can_proceed: true`
- [ ] **Enjoying Wealth journey:** Register > select enjoying wealth > fill ALL fields > complete > dashboard shows all data
- [ ] **All journeys:** No modal wrappers appear during onboarding — all forms render inline
- [ ] **All journeys:** Progress bar updates based on actual data entered, not steps clicked
- [ ] **All journeys:** Sidebar shows correct section headings and items for each stage
- [ ] **Partial data test:** Complete onboarding with some steps skipped > dashboard shows cards only for modules with data > progress reflects partial completion > AI chat explains what's missing for skipped modules
- [ ] **Post-onboarding data entry:** After onboarding, add data via profile/module pages > verify progress indicator updates > verify Agent readiness changes from blocked to ready
- [ ] **Will Builder:** Click "Create a Will" during onboarding, verify state is preserved or opens in new tab
