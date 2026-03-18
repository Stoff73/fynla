# Journey Bug Implementation Report

**Date:** 2026-03-18
**Branch:** `worktree-journeyBug` (from main)
**Commits:** 3 (`0a96c9e`, `c8f0361`, `88a2e0a`)
**Files changed:** 21 (18 modified, 3 new)

---

## Summary

Implemented all phases from `journeyBug.md` including the deferred items. All Pest tests pass (11/11). Browser tested 3 persona dashboards and the profile page.

---

## Phase 0 — Information Completeness Architecture

### 0A: Completeness API Endpoint

**Created:** `GET /api/life-stage/completeness`

Returns per-module two-tier status:
- `has_data` — display level: any data exists (enough to show a dashboard card)
- `can_advise` — advice level: all BLOCKING prerequisites met (enough for Agent analysis)
- `missing` — specific fields needed for advice level
- `guidance` — user-friendly explanation
- `required_actions` — links to fix pages

7 modules: protection, savings, retirement, investment, estate, goals, tax_optimisation.

**Files:** `LifeStageController.php`, `routes/api.php`

### 0B: Vuex Completeness Store

**Created:** `resources/js/store/modules/completeness.js`

Getters: `hasModuleData(module)`, `canAdviseModule(module)`, `moduleMissing(module)`, `moduleGuidance(module)`, `overallAdviceReadiness`. Actions: `fetchCompleteness` (5s debounce), `refreshCompleteness`.

**Files:** `store/index.js`, `services/lifeStageService.js`

### 0C/0D: Dashboard Integration

- Completeness fetched on every dashboard load
- Progress indicator uses backend data completeness via `LifeStageService::getDataCompleteness()`
- Dynamic Tailwind classes fixed with lookup maps in JourneyProgressHero

### Tests — 7 new Pest tests (all passing)

---

## Phase 1 — Critical Data Bugs

| Bug | Root Cause | Fix |
|-----|-----------|-----|
| Student loan not on dashboard | `estate/fetchEstateData` missing from student moduleLoaders | Added to loader array |
| Monthly expenditure lost | `monthly_expenditure` not in form request + silent `.catch(() => {})` | Added to `UpdatePersonalInfoRequest`, replaced catch with `console.error` |
| Student fields dropped | `university`, `student_number`, `education_level` not in save handler | Added to personalData extraction. Created migration for university/student_number columns. |

---

## Phase 2 — Functional Bugs

| Bug | Fix |
|-----|-----|
| Dynamic Tailwind classes | `stageTextClass`/`stageBgClass` lookup maps in JourneyProgressHero |
| `alert()` validation | Inline `validationError` display in DCPensionForm and StatePensionForm |
| `bg-eggshell-5000` typo | Fixed to `bg-black/50` in SaveAccountModal and PolicyFormModal |
| Skip doesn't track progress | Added `completeStep` dispatch to `handleLifeStageSkip` |
| WillInfoStep exits onboarding | Changed to `window.open('...', '_blank')` |
| `isStudentPersona` too narrow | Added `life_stage === 'university'` check |
| No student employment status | Added `Student` option to dropdown and display labels |
| PropertyForm wizard-in-wizard | Added `property-mortgage` to `stepsWithOwnNav` |

---

## Phase 3 — Code Quality

| Issue | Fix |
|-------|-----|
| Hardcoded hex in scrollbar | Removed custom CSS, applied `scrollbar-thin` class |
| Duplicated `@keyframes fadeIn` | Removed, applied `animate-fade-in` class |
| Dead code in SideMenu | Removed 5 unused items (primaryItems, exploreItems, etc.) |
| Non-palette blue-* colours | Replaced with `violet-*` tokens in PersonalInformation |
| LiabilityForm CSS | **Refactored 260 lines of custom CSS to Tailwind utility classes** |

---

## Deferred Items — Now Complete

### 1. Student DB Columns (Migration)

Created `2026_03_18_100001_add_student_fields_to_users_table.php`:
- `university` (string, 255, nullable)
- `student_number` (string, 50, nullable)

Added both fields to `UpdatePersonalInfoRequest` validation and OnboardingWizard save handler.

### 2. Dedicated StudentLoanStep.vue

Created `resources/js/components/Onboarding/steps/StudentLoanStep.vue`:
- Repayment plan selector (Plan 1, 2, 4, 5, Postgraduate)
- Auto-populates repayment threshold and default interest rate per plan
- Outstanding balance, interest rate, monthly payment fields
- Summary card showing loan details
- Emits `save` with `liability_type: 'student_loan'` — wired into OnboardingWizard save handler
- Replaced `LiabilityForm` in `STEP_COMPONENTS['student-loan']`

### 3. LiabilityForm CSS Refactor

Replaced all 260 lines of custom scoped CSS with Tailwind utility classes:
- `.form-group` → `mb-5`
- `.form-control` → `input-field`
- `.is-invalid` → `border-raspberry-500`
- `.error-message` → `text-sm text-raspberry-500 mt-1 block`
- `.btn-primary` → `bg-raspberry-500 hover:bg-raspberry-600 text-white ...`
- `.btn-secondary` → `border border-light-gray text-horizon-500 ...`
- `.input-with-icon` → `relative` with absolute positioning
- Removed entire `<style scoped>` block

---

## Test Results

### Pest Tests

```
PASS  CompletenessEndpointTest (7 tests, 81 assertions)
PASS  LifeStageControllerTest (4 tests, 11 assertions)
Total: 11 passed, 0 failed (92 assertions)
```

### Browser Tests — Verified

| Test | Result | Evidence |
|------|--------|----------|
| Student loan on dashboard | PASS | Student Debt card shows: £35,000 balance, Plan 5, 7.3% rate, £25,000/yr threshold |
| Stage label colours render | PASS | "Starting Out" in violet, "Protecting What Matters" in raspberry |
| Progress bar correct | PASS | 100% for complete personas, gradient renders correctly |
| Sidebar section headings | PASS | Cash Management, Finances, Admin, Planning, Account, Support visible |
| Student employment status | PASS | "Student" option visible in employment status dropdown |
| University/student_number fields | PASS | Fields render in edit mode on profile page |
| Domicile violet colours | PASS | Info boxes use violet-* not blue-* |
| Dashboard cards (young_family) | PASS | All 6 cards: Net Worth, Protection, Cash & Savings, Investments, Retirement, Goals |
| Modal backdrops | PASS | PolicyFormModal and SaveAccountModal use bg-black/50 |

Screenshots saved:
- `student-dashboard-verified.png` — Student persona dashboard with debt card
- `young-family-dashboard.png` — Full young_family dashboard with all cards

### Items Not Browser Tested (require new user registration flow)

These items need a fresh user registration to test end-to-end onboarding:
- StudentLoanStep form submission during onboarding
- PropertyForm shows only its own navigation
- Skip behaviour and re-entry
- WillInfoStep opens new tab
- Inline pension form validation errors
- Expenditure persistence after reload

These require creating a new account which needs email verification — can be tested in the next session with user assistance.

---

## All Files Changed

| File | Type | Changes |
|------|------|---------|
| `app/Http/Controllers/Api/LifeStageController.php` | Modified | +completeness endpoint |
| `app/Http/Requests/UpdatePersonalInfoRequest.php` | Modified | +monthly_expenditure, +university, +student_number |
| `database/migrations/2026_03_18_100001_add_student_fields_to_users_table.php` | **New** | university + student_number columns |
| `resources/js/components/Estate/LiabilityForm.vue` | Modified | CSS refactored to Tailwind |
| `resources/js/components/Journey/JourneyProgressHero.vue` | Modified | Dynamic Tailwind class lookup maps |
| `resources/js/components/Onboarding/OnboardingWizard.vue` | Modified | +student fields, +skip tracking, +property-mortgage nav, +StudentLoanStep |
| `resources/js/components/Onboarding/steps/SimpleExpenditureStep.vue` | Modified | Error logging instead of silent catch |
| `resources/js/components/Onboarding/steps/StudentLoanStep.vue` | **New** | Dedicated student loan onboarding step |
| `resources/js/components/Onboarding/steps/WillInfoStep.vue` | Modified | Opens in new tab |
| `resources/js/components/Protection/PolicyFormModal.vue` | Modified | Fixed backdrop class |
| `resources/js/components/Retirement/DCPensionForm.vue` | Modified | Inline validation, removed CSS |
| `resources/js/components/Retirement/StatePensionForm.vue` | Modified | Inline validation, removed CSS |
| `resources/js/components/Savings/SaveAccountModal.vue` | Modified | Fixed backdrop class |
| `resources/js/components/SideMenu.vue` | Modified | Removed dead code |
| `resources/js/components/UserProfile/PersonalInformation.vue` | Modified | +student status, violet colours |
| `resources/js/services/lifeStageService.js` | Modified | +getCompleteness |
| `resources/js/store/index.js` | Modified | +completeness module |
| `resources/js/store/modules/completeness.js` | **New** | Completeness Vuex store |
| `resources/js/views/Dashboard.vue` | Modified | +estate loader, +isStudentPersona, +completeness |
| `routes/api.php` | Modified | +completeness route |
| `tests/Feature/CompletenessEndpointTest.php` | **New** | 7 tests |

**Total: 21 files changed, 3 new files, ~800 additions, ~420 deletions**
