# Onboarding Module - Current State Documentation

## 1. System Overview

The Onboarding module provides a guided wizard experience that collects user data during initial setup. It is the first touchpoint after registration and populates data across all seven application modules (Protection, Savings, Investment, Retirement, Estate Planning, Goals & Life Events, and Coordination).

**Architecture:**
- No dedicated Agent -- the controller communicates directly with `OnboardingService`
- `OnboardingService` (1334 lines) orchestrates all data processing and delegates to `EstateOnboardingFlow` (320 lines) for step definitions
- A dual data persistence strategy exists: some step components save directly via module-specific frontend services, while others route through the onboarding `saveStepData` pipeline

**Current limitations:**
- Only the `estate` focus area is implemented; the `setFocusArea` API accepts `estate`, `protection`, `retirement`, `investment`, and `tax_optimisation`, but only `estate` has a defined flow
- The wizard always resets to the welcome/focus-area-selection screen on mount (no resume from current step)

**Key files:**

| Layer | File | Lines |
|-------|------|-------|
| Controller | `app/Http/Controllers/Api/OnboardingController.php` | 314 |
| Service | `app/Services/Onboarding/OnboardingService.php` | 1334 |
| Flow | `app/Services/Onboarding/EstateOnboardingFlow.php` | 320 |
| Model | `app/Models/OnboardingProgress.php` | 76 |
| Vuex Store | `resources/js/store/modules/onboarding.js` | 437 |
| API Service | `resources/js/services/onboardingService.js` | 92 |
| Wizard | `resources/js/components/Onboarding/OnboardingWizard.vue` | 340 |
| View | `resources/js/views/Onboarding/OnboardingView.vue` | 15 |

---

## 2. Database Schema

### 2.1 `onboarding_progress` Table

Stores per-step progress records keyed on (user_id, focus_area, step_name).

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `user_id` | bigint (FK) | References `users.id` |
| `focus_area` | string | e.g. `estate` |
| `step_name` | string | e.g. `personal_info`, `assets` |
| `step_data` | JSON (nullable) | Full form payload snapshot |
| `completed` | boolean | Whether step was completed |
| `skipped` | boolean | Whether step was skipped |
| `skip_reason_shown` | boolean | Whether skip reason was displayed |
| `completed_at` | datetime (nullable) | Timestamp of completion |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Composite uniqueness:** `(user_id, focus_area, step_name)` -- enforced by `updateOrCreate` calls in the service.

### 2.2 User Model Onboarding Fields

These fields on the `users` table track high-level onboarding state:

| Column | Type | Description |
|--------|------|-------------|
| `onboarding_completed` | boolean | Master completion flag |
| `onboarding_focus_area` | string (nullable) | Selected focus area (`estate`) |
| `onboarding_current_step` | string (nullable) | Last active step name |
| `onboarding_skipped_steps` | JSON (nullable) | Array of skipped step names |
| `onboarding_started_at` | datetime (nullable) | When onboarding began |
| `onboarding_completed_at` | datetime (nullable) | When onboarding finished |

---

## 3. Models

### 3.1 OnboardingProgress

**File:** `app/Models/OnboardingProgress.php` (76 lines)

```php
class OnboardingProgress extends Model
{
    protected $table = 'onboarding_progress';

    protected $fillable = [
        'user_id', 'focus_area', 'step_name', 'step_data',
        'completed', 'skipped', 'skip_reason_shown', 'completed_at',
    ];

    protected $casts = [
        'step_data'          => 'array',
        'completed'          => 'boolean',
        'skipped'            => 'boolean',
        'skip_reason_shown'  => 'boolean',
        'completed_at'       => 'datetime',
    ];
}
```

**Relationship:**
- `user()` -- BelongsTo `User`

**Query Scopes:**
| Scope | Usage |
|-------|-------|
| `completed()` | `->where('completed', true)` |
| `skipped()` | `->where('skipped', true)` |
| `forFocusArea($area)` | `->where('focus_area', $area)` |
| `forStep($name)` | `->where('step_name', $name)` |

---

## 4. Controllers

### 4.1 OnboardingController

**File:** `app/Http/Controllers/Api/OnboardingController.php` (314 lines)

Uses `SanitizedErrorResponse` trait for consistent error handling. Injects `OnboardingService` via constructor.

| Method | HTTP | Endpoint | Description |
|--------|------|----------|-------------|
| `getOnboardingStatus` | GET | `/onboarding/status` | Returns full onboarding state: completion, focus area, progress %, skipped steps, timestamps |
| `setFocusArea` | POST | `/onboarding/focus-area` | Sets the focus area; validates against `estate,protection,retirement,investment,tax_optimisation` |
| `getSteps` | GET | `/onboarding/steps` | Returns filtered step list for user's focus area (requires focus area set) |
| `getStepData` | GET | `/onboarding/step/{step}` | Returns saved step_data JSON or falls back to user table fields |
| `saveStepProgress` | POST | `/onboarding/step` | Saves step data, processes it to domain tables, returns progress % and next step |
| `skipStep` | POST | `/onboarding/skip-step` | Marks a step as skipped, adds to skipped_steps array, advances to next step |
| `getSkipReason` | GET | `/onboarding/skip-reason/{step}` | Returns the human-readable skip reason text for a step |
| `skipToDashboard` | POST | `/onboarding/skip-to-dashboard` | Marks all remaining incomplete steps as skipped, completes onboarding |
| `completeOnboarding` | POST | `/onboarding/complete` | Sets `onboarding_completed = true` with timestamp |
| `restartOnboarding` | POST | `/onboarding/restart` | Deletes all progress records, resets all user onboarding fields to null |

**Validation rules:**
- `setFocusArea`: `focus_area` required, in: `estate,protection,retirement,investment,tax_optimisation`
- `saveStepProgress`: `step_name` required string, `data` required array
- `skipStep`: `step_name` required string

---

## 5. Agent

There is no dedicated Agent for onboarding. The `OnboardingController` communicates directly with `OnboardingService`, which handles all business logic. This is unlike other modules (Protection, Estate, Retirement, etc.) that use Agent classes as orchestration layers.

---

## 6. Services

### 6.1 OnboardingService

**File:** `app/Services/Onboarding/OnboardingService.php` (1334 lines)

Injected dependency: `EstateOnboardingFlow`

#### Public Methods

| Method | Description |
|--------|-------------|
| `getOnboardingStatus(int $userId): array` | Builds full status payload including progress calculation |
| `setFocusArea(int $userId, string $focusArea): User` | Updates user, sets started_at if first time, determines first step |
| `saveStepProgress(int $userId, string $stepName, array $data): OnboardingProgress` | Processes step data to domain tables, creates/updates progress record, advances to next step |
| `skipStep(int $userId, string $stepName): OnboardingProgress` | Marks step skipped, adds to user's skipped_steps JSON array |
| `skipToDashboard(int $userId): User` | Bulk-skips all uncompleted/unskipped steps, sets onboarding_completed |
| `completeOnboarding(int $userId): User` | Sets `onboarding_completed = true` with timestamp |
| `restartOnboarding(int $userId): User` | Wraps in DB transaction: deletes all progress, resets all user fields |
| `calculateProgress(int $userId): array` | Returns `{percentage, total, completed}` -- counts both completed and skipped as "done" |
| `getOnboardingSteps(string $focusArea, ?int $userId): array` | Delegates to EstateOnboardingFlow, applies progressive disclosure filtering |
| `getNextStep(int $userId, string $currentStep): ?string` | Returns the next step name considering filtered steps |
| `getPreviousStep(int $userId, string $currentStep): ?string` | Returns the previous step name considering filtered steps |
| `shouldShowStep(int $userId, string $stepName): bool` | Checks progressive disclosure rules |
| `getSkipReasonText(string $focusArea, string $stepName): ?string` | Returns skip reason from flow definition |
| `getStepData(int $userId, string $stepName): ?array` | Returns progress.step_data or falls back to `getStepDataFromUser()` |

#### Step Data Processing (`processStepData`)

The central dispatch method routes to domain-specific processors:

| Step Name | Processor Method | Target Tables |
|-----------|-----------------|---------------|
| `personal_info` | `processPersonalInfo()` | `users` (personal fields, address, health/lifestyle) |
| `family_info` | `processFamilyInfo()` | `family_members`, `users` (spouse_id, marital_status), `spouse_permissions` |
| `domicile_info` | `processDomicileInfo()` | `users` (domicile_status, country_of_birth, uk_arrival_date, years_uk_resident, deemed_domicile_date) |
| `assets` | `processAssets()` | `properties`, `mortgages`, `investment_accounts`, `savings_accounts`, `users` (annual_rental_income) |
| `liabilities` | `processLiabilities()` | `liabilities` (excludes mortgage type) |
| `protection_policies` | `processProtectionPolicies()` | `life_insurance_policies`, `critical_illness_policies`, `income_protection_policies` |
| `income` | `processIncomeInfo()` | `users` (employment/income fields), `retirement_profiles` |
| `expenditure` | `processExpenditureInfo()` | `users` (all expenditure category fields) -- for both user and spouse |
| `will_info` | `processWillInfo()` | `wills` (has_will, will_last_updated, executor_name) |
| `trust_info` | *No processor* | Saved to `onboarding_progress.step_data` only |

#### Key Processing Details

**processAssets:**
- Creates `Property` + linked `Mortgage` records for each property with outstanding balance
- Mortgage defaults: 3.5% rate, repayment type, 20 years remaining
- Creates `InvestmentAccount` with account type mapping: `stocks_shares_isa` -> `isa`, `gia` -> `gia`, `offshore_bond` -> `offshore_bond`, `other` -> `gia`
- Creates `SavingsAccount` with access type mapping: `current_account/cash_isa/easy_access` -> `immediate`, `notice_account` -> `notice`, `fixed_term` -> `fixed`
- **Joint ownership:** Divides `current_value` and `isa_allowance_used` by 2, sets `ownership_percentage` to 50

**processFamilyInfo:**
- Handles spouse linking via `handleSpouseLinking()` when spouse relationship has email
- Bidirectional: sets `spouse_id` on both users, sets both to `married`
- Creates bidirectional `SpousePermission` records with `status = 'accepted'`
- Creates reciprocal `FamilyMember` records for both users
- Clears protection analysis cache for both users
- Non-spouse members: simple create/update by name match

**processExpenditureInfo:**
- Two modes: **separate mode** (has `userData` and `spouseData` keys) and **joint mode**
- Joint mode: divides all amounts by 2, saves identical halved data to both user and spouse
- Separate mode: saves each user's data independently
- Sets `expenditure_sharing_mode` to `joint` in joint mode

**processIncomeInfo:**
- Updates employment and income fields on user
- Upserts `RetirementProfile` when `target_retirement_age` is provided
- Special handling for `retired` status: calculates retirement age from retirement_date and date_of_birth

#### Fallback Data Loading (`getStepDataFromUser`)

When no `onboarding_progress` record exists for a step, the service falls back to reading from the user table. Currently only the `expenditure` step has a fallback implementation that reconstructs expenditure data from the user model fields, including spouse data detection for separate mode.

### 6.2 EstateOnboardingFlow

**File:** `app/Services/Onboarding/EstateOnboardingFlow.php` (320 lines)

Defines the 11 steps of the estate onboarding flow with their configuration.

#### Step Definitions (sorted by order)

| Order | Step Name | Title | Required | Conditional | Skippable |
|-------|-----------|-------|----------|-------------|-----------|
| 1 | `personal_info` | Personal Information | Yes | No | No |
| 2 | `family_info` | Family & Beneficiaries | No | No | Yes |
| 3 | `domicile_info` | Domicile Information | Yes | No | No |
| 4 | `assets` | Assets & Wealth | Yes | No | Yes (in UI) |
| 5 | `liabilities` | Liabilities & Debts | No | No | Yes |
| 6 | `protection_policies` | Protection Policies | No | No | Yes |
| 7 | `income` | Employment & Income | Yes | No | No |
| 8 | `expenditure` | Household Expenditure | Yes | No | No |
| 9 | `will_info` | Will Information | No | No | Yes |
| 10 | `trust_info` | Trust Information | No | Yes | Yes |
| 11 | `completion` | Setup Complete | Yes | No | No |

#### Progressive Disclosure

The `shouldShowStep()` method controls conditional step visibility:

- **trust_info**: Only shown if `has_trusts === true` OR estimated estate value exceeds the RNRB taper threshold (2,000,000)
- All other steps are always shown (non-conditional)

Estate value estimation uses constants from `EstateDefaults`:
- Property ownership: +300,000
- Investments: +100,000
- Savings: +50,000
- Business interests: +200,000

#### Key Methods

| Method | Description |
|--------|-------------|
| `getSteps(): array` | Returns all 11 steps sorted by order |
| `getFilteredSteps(array $userData): array` | Returns steps after applying progressive disclosure |
| `shouldShowStep(string $stepName, array $userData): bool` | Evaluates progressive disclosure rules |
| `getSkipReason(string $stepName): ?string` | Returns skip reason text from step config |
| `getNextStep(string $currentStep, array $userData): ?string` | Returns next step key in filtered list |
| `getPreviousStep(string $currentStep, array $userData): ?string` | Returns previous step key in filtered list |

---

## 7. Validation Requests

The onboarding module does **not** use dedicated Form Request classes. Validation is handled in two places:

1. **Controller-level inline validation** using `Validator::make()` in `setFocusArea`, `saveStepProgress`, and `skipStep`
2. **Frontend client-side validation** within individual step components (e.g., `PersonalInfoStep` validates required fields before dispatching to the store)

No `app/Http/Requests/Onboarding*` files exist.

---

## 8. Vuex Store

**File:** `resources/js/store/modules/onboarding.js` (437 lines)

Namespaced module: `onboarding/`

### State

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `status` | string/null | `null` | `null`, `'in_progress'`, or `'completed'` |
| `focusArea` | string/null | `null` | Selected focus area |
| `currentStepIndex` | number | `0` | Index in the steps array |
| `currentStepName` | string/null | `null` | Name of current step |
| `totalSteps` | number | `0` | Total step count |
| `steps` | array | `[]` | Step definitions from API |
| `stepData` | object | `{}` | Cache of step data keyed by step name |
| `progressPercentage` | number | `0` | 0-100 progress |
| `loading` | boolean | `false` | Loading state |
| `error` | string/null | `null` | Error message |
| `showSkipModal` | boolean | `false` | Skip confirmation modal visibility |
| `currentSkipReason` | string | `''` | Reason text for current skip |
| `skipStepName` | string | `''` | Step being considered for skip |
| `skippedSteps` | array | `[]` | Array of skipped step names |
| `hasSkippedSteps` | boolean | `false` | Whether any steps are skipped |
| `fullyCompleted` | boolean | `false` | Completed with no skipped steps |

### Getters

| Getter | Description |
|--------|-------------|
| `isOnboardingComplete` | `status === 'completed'` |
| `isOnboardingInProgress` | `status === 'in_progress'` |
| `isFullyCompleted` | `fullyCompleted` flag |
| `hasSkippedSteps` | Boolean |
| `skippedSteps` | Array of skipped step names |
| `currentStep` | Current step object from `steps[currentStepIndex]` |
| `currentStepData` | Cached data for current step |
| `progressPercentage` | Progress value |
| `canGoNext` | `currentStepIndex < steps.length - 1` |
| `canGoBack` | `currentStepIndex > 0` |
| `isLoading` | Loading state |
| `hasError` | Boolean |
| `errorMessage` | Error string |

### Actions

| Action | Description |
|--------|-------------|
| `fetchOnboardingStatus` | Calls `getStatus()`, sets status, fetches steps if focus area exists |
| `setFocusArea(focusArea)` | Calls API, updates state, fetches steps |
| `fetchSteps` | Calls `getSteps()`, updates steps array, syncs current step index |
| `fetchStepData(stepName)` | Calls `getStepData()`, caches result in `stepData` |
| `saveStepData({stepName, data})` | Calls `saveStepProgress()`, caches data, updates progress, removes from skipped |
| `skipStep(stepName)` | Calls API, updates progress, adds to skipped, hides modal |
| `showSkipConfirmation(stepName)` | Fetches skip reason from API, shows modal |
| `hideSkipConfirmation` | Hides skip modal |
| `goToNextStep` | Increments index, fetches step data for next step |
| `goToPreviousStep` | Decrements index, fetches step data for previous step |
| `goToStep(stepIndex)` | Jumps to arbitrary step index, fetches step data |
| `skipToDashboard` | Calls API, sets completed status, refreshes user via `auth/fetchUser` |
| `completeOnboarding` | Calls API, sets completed, refreshes user via `auth/fetchUser` |
| `restartOnboarding` | Calls API, commits `RESET_STATE` |

### Mutations

| Mutation | Description |
|----------|-------------|
| `SET_STATUS` | Updates multiple status fields from payload |
| `SET_FOCUS_AREA` | Sets focus area and status to `'in_progress'` |
| `SET_CURRENT_STEP` | Updates `currentStepName` |
| `SET_CURRENT_STEP_INDEX` | Updates `currentStepIndex` |
| `SET_STEPS` | Sets steps array and totalSteps |
| `UPDATE_STEP_DATA` | Merges step data into cache |
| `SET_PROGRESS_PERCENTAGE` | Updates progress |
| `SET_LOADING` | Sets loading flag |
| `SET_ERROR` | Sets error message |
| `ADD_SKIPPED_STEP` | Adds to skippedSteps if not already present |
| `REMOVE_SKIPPED_STEP` | Removes from skippedSteps (when step is later completed) |
| `SHOW_SKIP_MODAL` | Shows skip modal with step name and reason |
| `HIDE_SKIP_MODAL` | Hides skip modal, clears reason |
| `RESET_STATE` | Resets all state to defaults |

---

## 9. API Service (Frontend)

**File:** `resources/js/services/onboardingService.js` (92 lines)

Wraps all API calls using the shared `api` axios instance. All endpoints are prefixed with `/onboarding/`.

| Method | HTTP | Endpoint | Parameters |
|--------|------|----------|------------|
| `getStatus()` | GET | `/onboarding/status` | -- |
| `setFocusArea(focusArea)` | POST | `/onboarding/focus-area` | `{ focus_area }` |
| `getSteps()` | GET | `/onboarding/steps` | -- |
| `getStepData(stepName)` | GET | `/onboarding/step/{stepName}` | -- |
| `saveStepProgress(stepName, data)` | POST | `/onboarding/step` | `{ step_name, data }` |
| `skipStep(stepName)` | POST | `/onboarding/skip-step` | `{ step_name }` |
| `getSkipReason(stepName)` | GET | `/onboarding/skip-reason/{stepName}` | -- |
| `skipToDashboard()` | POST | `/onboarding/skip-to-dashboard` | -- |
| `completeOnboarding()` | POST | `/onboarding/complete` | -- |
| `restartOnboarding()` | POST | `/onboarding/restart` | -- |

---

## 10. Frontend Components

### 10.1 OnboardingView

**File:** `resources/js/views/Onboarding/OnboardingView.vue` (15 lines)

Thin wrapper that renders `OnboardingWizard`. No logic of its own.

### 10.2 OnboardingWizard

**File:** `resources/js/components/Onboarding/OnboardingWizard.vue` (340 lines)

The main orchestrator component. Uses Composition API with `setup()`.

**Key behaviour:**
- On mount: fetches onboarding status, then **always resets** to welcome screen (`SET_FOCUS_AREA(null)`, `SET_CURRENT_STEP_INDEX(0)`, `SET_CURRENT_STEP(null)`)
- Shows `FocusAreaSelection` when no focus area is set
- Renders dynamic step component via `<component :is="currentStepComponent">`
- Manages progress indicator with step circles (green=completed, blue=skipped, teal=current, gray=pending)
- Handles skip flow: step emits `skip` -> wizard fetches skip reason -> shows `SkipConfirmationModal` -> on confirm, dispatches `skipStep` and `goToNextStep`
- Shows "Skip to Dashboard" link (hidden on completion step) -> opens `SkipToDashboardModal`

**Step component mapping:**

| Step Name | Component |
|-----------|-----------|
| `personal_info` | `PersonalInfoStep` |
| `family_info` | `FamilyInfoStep` |
| `domicile_info` | `DomicileInformationStep` |
| `assets` | `AssetsStep` |
| `liabilities` | `LiabilitiesStep` |
| `protection_policies` | `ProtectionPoliciesStep` |
| `income` | `IncomeStep` |
| `expenditure` | `ExpenditureStep` |
| `will_info` | `WillInfoStep` |
| `trust_info` | `TrustInfoStep` |
| `completion` | `CompletionStep` |

**Short label mapping for progress circles:**
`personal_info` -> "Personal", `family_info` -> "Family", `domicile_info` -> "Domicile", `income` -> "Income", `expenditure` -> "Expenses", `assets` -> "Assets", `liabilities` -> "Debts", `protection_policies` -> "Protection", `will_info` -> "Will", `trust_info` -> "Trusts", `completion` -> "Complete"

### 10.3 OnboardingStep

**File:** `resources/js/components/Onboarding/OnboardingStep.vue` (118 lines)

Reusable shell component providing consistent layout for all step components.

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | String | required | Step heading |
| `description` | String | required | Step subtitle |
| `canGoBack` | Boolean | `true` | Show back button |
| `canSkip` | Boolean | `false` | Show skip link |
| `loading` | Boolean | `false` | Disables buttons, shows "Saving..." |
| `disabled` | Boolean | `false` | Disables continue button |
| `error` | String | `null` | Error message display |
| `nextButtonText` | String | `'Continue'` | Continue button text |

**Emits:** `next`, `back`, `skip`

**Slot:** Default slot for step-specific form content.

### 10.4 FocusAreaSelection

**File:** `resources/js/components/Onboarding/FocusAreaSelection.vue` (232 lines)

Welcome/landing screen shown before onboarding begins. Uses Composition API.

**Key features:**
- Displays personalised welcome with user's first name
- Shows 8-step overview of what the onboarding collects
- "Your Free Trial" section (7-day trial, 30-day data retention)
- "Start Onboarding" button -- **hardcoded to `selectFocusArea('estate')`**
- "Skip onboarding and go to dashboard" link -- navigates to Dashboard route
- Emits `selected` event after setting focus area

### 10.5 SkipConfirmationModal

**File:** `resources/js/components/Onboarding/SkipConfirmationModal.vue` (98 lines)

Modal shown when a user attempts to skip a step.

**Props:** `show` (Boolean), `reason` (String -- the backend-provided skip reason text)
**Emits:** `cancel` (Go Back button), `skip` (Skip Anyway button)

### 10.6 SkipToDashboardModal

**File:** `resources/js/components/Onboarding/SkipToDashboardModal.vue` (97 lines)

Modal shown when user clicks "Skip to Dashboard" in the progress bar area.

**Props:** `show` (Boolean)
**Emits:** `continue` (Continue Onboarding button), `skip-to-dashboard` (Go to Dashboard button)

### 10.7 Step Components

#### PersonalInfoStep (508 lines)
- **Persistence:** Onboarding `saveStepData` -> `processPersonalInfo()`
- **Required fields:** `date_of_birth`, `gender`, `marital_status`, `address_line_1`, `city`, `postcode`
- **Optional fields:** `national_insurance_number`, `phone`, `address_line_2`, `county`, `health_status`, `smoking_status`, `education_level`
- **Validation:** Client-side with per-field error messages, cleared on input
- **NI format:** Uppercase, max 9 chars (regex: AB123456C pattern)
- **DOB constraints:** Min 105 years ago, max 18 years ago
- **Pre-population:** Reads from `auth/currentUser` store, then overrides with `onboarding/fetchStepData`
- **canGoBack:** `false` (first step), **canSkip:** `false`

#### FamilyInfoStep (343 lines)
- **Persistence:** Direct via `familyMembersService` (NOT onboarding save pipeline)
- **Uses:** `FamilyMemberFormModal`, `SpouseSuccessModal`
- **Behaviour:** Inline CRUD -- loads existing family members on mount, add/edit/delete via modal
- **Spouse linking:** When a spouse is added with email, checks if account exists:
  - If exists: shows "Linked" success modal
  - If new: shows "Created" success modal with temporary password
  - Refreshes `auth/fetchUser` after linking
- **Edit restrictions:** Spouse records cannot be edited/deleted from this screen (message says to log into spouse's account)
- **canGoBack:** `true`, **canSkip:** `true`
- **Does NOT call `onboarding/saveStepData`** -- emits `next` directly

#### DomicileInformationStep (257 lines)
- **Persistence:** Onboarding `saveStepData` -> `processDomicileInfo()`
- **Uses:** `CountrySelector` shared component
- **UK countries:** England, Scotland, Wales, Northern Ireland
- **Auto-determination:** If born in UK, sets `domicile_status = 'uk_domiciled'`, hides arrival fields
- **Non-UK born:** Shows UK arrival date, auto-calculates `years_uk_resident` and `deemed_domicile_date`
- **Deemed domicile threshold:** 15 years UK resident
- **canGoBack:** `true`, **canSkip:** `false`

#### AssetsStep (1277 lines)
- **Persistence:** Direct via module services (NOT onboarding save pipeline)
- **4 internal tabs:** Retirement, Properties, Investments, Cash
- **Tab cycling:** "Continue" button advances to next tab before emitting `next` to the wizard. Only emits `next` when on the last tab (Cash).
- **Services used:** `retirementService`, `propertyService`, `investmentService`, `savingsService`, `userProfileService`
- **Retirement tab:** Shows DC pensions, DB pensions, state pension. Add/edit forms: `DCPensionForm`, `DBPensionForm`, `StatePensionForm`. Upload statement button.
- **Properties tab:** Shows `PropertyCard` list. Add/edit via `PropertyForm`. Handles mortgage creation/update alongside property.
- **Investments tab:** Shows account cards. Add/edit via `AccountForm` (with `isOnboarding=true`). Upload statement button.
- **Cash tab:** Shows savings account cards. Add/edit via `SaveAccountModal`. Upload statement button.
- **Document upload:** `DocumentUploadModal` for pension_statement, investment_statement, savings_statement types
- **canGoBack:** `true`, **canSkip:** `true`

#### LiabilitiesStep (214 lines)
- **Persistence:** Direct via `estateService` (NOT onboarding save pipeline)
- **Excludes mortgage type** (mortgages are linked to properties in AssetsStep)
- **Uses:** `LiabilityForm` from Estate module
- **Behaviour:** Inline CRUD -- load, add, edit, delete liabilities
- **canGoBack:** `true`, **canSkip:** `true`

#### ProtectionPoliciesStep (448 lines)
- **Persistence:** Direct via `protectionService` (NOT onboarding save pipeline)
- **5 policy types:** life, criticalIllness, incomeProtection, disability, sicknessIllness
- **"No policies" checkbox:** Sets `has_no_policies` flag via `protectionService.updateHasNoPolicies()`; hides add buttons when checked; automatically unchecked if user adds a policy
- **Uses:** `PolicyFormModal`, `DocumentUploadModal` (insurance_policy type)
- **Delete routing:** Calls type-specific delete methods (`deleteLifePolicy`, `deleteCriticalIllnessPolicy`, etc.)
- **canGoBack:** `true`, **canSkip:** `true`

#### IncomeStep (513 lines)
- **Persistence:** Onboarding `saveStepData` -> `processIncomeInfo()`
- **Employment status options:** employed, part_time, self_employed, unemployed, retired, other
- **Conditional fields:**
  - employed/part_time: occupation, employer, industry, retirement age, employment income
  - self_employed: occupation, employer, industry, retirement age, self-employment income
  - unemployed: benefit income
  - retired: retirement date, early retirement warning (if < 55)
  - All statuses: dividend income, interest income, other income
- **Rental income:** Read-only, fetched from `propertyService.getProperties()` with ownership percentage calculation
- **Total income:** Computed sum of all sources including rental
- **Uses:** `OccupationAutocomplete` shared component
- **canGoBack:** `true`, **canSkip:** `false`

#### ExpenditureStep (253 lines)
- **Persistence:** Onboarding `saveStepData` -> `processExpenditureInfo()`
- **Delegates to:** `ExpenditureForm` from UserProfile module (shared component)
- **Props passed to ExpenditureForm:** `initialData`, `spouseData`, `spouseName`, `isMarried`, `alwaysShowTabs=false`, `showButtons=false`, `startInEditMode=true`, `showBudgetTabs=false`, `isOnboarding=true`
- **Skip modal:** Custom inline modal shown when expenditure totals are zero. Dispatches `onboarding/skipStep` and `onboarding/goToNextStep` on confirm.
- **Spouse data:** Fetches via `auth/fetchUserById` if married
- **canGoBack:** `true`, **canSkip:** `false` (but has internal skip-on-zero mechanism)

#### WillInfoStep (181 lines)
- **Persistence:** Onboarding `saveStepData` -> `processWillInfo()`
- **Fields:** `has_will` (radio yes/no), `will_last_updated` (date, shown if has_will), `executor_name` (text, shown if has_will)
- **No-will warning:** Green info box about intestacy rules
- **Feature notice:** Blue info box noting enhanced will features are coming soon
- **canGoBack:** `true`, **canSkip:** `true`

#### TrustInfoStep (135 lines)
- **Persistence:** Onboarding `saveStepData` -> saved to `onboarding_progress.step_data` ONLY
- **No domain table processing** -- `processStepData` has no case for `trust_info`
- **Fields:** `has_trusts` (radio yes/no), `trust_count` (number, shown if has_trusts)
- **Conditional step:** Only shown via progressive disclosure when estate > 2,000,000 or has_trusts is true
- **canGoBack:** `true`, **canSkip:** `true`

#### CompletionStep (409 lines)
- **Persistence:** No data to save -- triggers `onboarding/completeOnboarding` on button click
- **Summary display:** Loads counts from 6 services in parallel: `propertyService`, `investmentService`, `savingsService`, `protectionService`, `estateService`, `userProfileService`
- **Summary metrics:** property count/value, investment count/value, savings count/value, liability count/value, policy count, family member count
- **Skipped sections detection:** Checks if any summary metric is zero
- **"Go to Dashboard" button:** Dispatches `completeOnboarding`, refreshes user, navigates to Dashboard
- **Step navigation:** Each summary row is clickable, dispatches `goToStep` to navigate back to that step

---

## 11. Frontend Routing

**File:** `resources/js/router/index.js`

```javascript
const Onboarding = () => import('@/views/Onboarding/OnboardingView.vue');

{
  path: '/onboarding',
  name: 'Onboarding',
  component: Onboarding,
  meta: { requiresAuth: true, hideNavbar: true },
  children: [
    {
      path: ':step',
      name: 'OnboardingStep',
      component: Onboarding,  // Re-uses parent component; step rendering is handled by store
    },
  ],
}
```

**Key meta flags:**
- `requiresAuth: true` -- must be logged in
- `hideNavbar: true` -- main navigation is hidden during onboarding

**Note:** The child route `/:step` exists but the wizard does not use URL-based step navigation. Step state is managed entirely through the Vuex store. The URL parameter is effectively unused.

---

## 12. Cross-Module Integration

Onboarding is the primary data ingestion point that populates tables across all modules. Here is the complete map of which onboarding steps write to which module tables:

### Protection Module
- **ProtectionPoliciesStep** -> `life_insurance_policies`, `critical_illness_policies`, `income_protection_policies` (via `protectionService` directly)
- **OnboardingService.processProtectionPolicies()** -> same tables (via backend save pipeline, not used by the step component's primary flow)

### Savings Module
- **AssetsStep (Cash tab)** -> `savings_accounts` (via `savingsService` directly)
- **OnboardingService.processAssets()** -> `savings_accounts` (via backend save pipeline)

### Investment Module
- **AssetsStep (Investments tab)** -> `investment_accounts` (via `investmentService` directly)
- **OnboardingService.processAssets()** -> `investment_accounts` (via backend save pipeline)

### Retirement Module
- **AssetsStep (Retirement tab)** -> `dc_pensions`, `db_pensions`, `state_pensions` (via `retirementService` directly)
- **OnboardingService.processIncomeInfo()** -> `retirement_profiles` (when target_retirement_age provided)

### Estate Planning Module
- **LiabilitiesStep** -> `liabilities` (via `estateService` directly)
- **OnboardingService.processLiabilities()** -> `liabilities`
- **OnboardingService.processWillInfo()** -> `wills`
- **TrustInfoStep** -> `onboarding_progress.step_data` only (no estate table)

### Property (Net Worth)
- **AssetsStep (Properties tab)** -> `properties`, `mortgages` (via `propertyService` directly)
- **OnboardingService.processAssets()** -> `properties`, `mortgages`

### User Profile / Core
- **PersonalInfoStep** -> `users` (personal fields, address, health/lifestyle)
- **DomicileInformationStep** -> `users` (domicile fields)
- **IncomeStep** -> `users` (employment/income fields)
- **ExpenditureStep** -> `users` (expenditure fields for both user and spouse)
- **FamilyInfoStep** -> `family_members`, `users` (spouse_id), `spouse_permissions`

### Account Linking
- **FamilyInfoStep** (spouse with email) -> bidirectional `spouse_id` linking, `spouse_permissions`, reciprocal `family_members`

---

## 13. Profile Completeness

The onboarding module tracks completeness through two mechanisms:

### 1. Progress Percentage (During Onboarding)
Calculated by `OnboardingService.calculateProgress()`:
- Counts steps where `completed = true OR skipped = true` in `onboarding_progress`
- Divides by total step count for the focus area
- Both completed and skipped steps count toward progress

### 2. Onboarding Completion Status (Post-Onboarding)
Tracked on the user model:
- `onboarding_completed`: Master flag set by `completeOnboarding()` or `skipToDashboard()`
- `onboarding_skipped_steps`: JSON array of step names that were skipped
- The API response includes `fully_completed` (`onboarding_completed && empty(skipped_steps)`) and `has_skipped_steps` flags
- The dashboard's "Areas to Complete" card uses `skipped_steps` to show what the user can return to

### 3. Progress Indicator UI
The wizard shows a horizontal progress bar with step circles:
- **Green circle with checkmark:** Completed step
- **Blue circle with X:** Skipped step
- **Teal circle with number:** Current step
- **Gray circle with number:** Pending step
- Green connecting lines between completed steps

---

## 14. Seeder Data

There are **no dedicated onboarding seeders**. The `PreviewUserSeeder` seeds complete user data (including `onboarding_completed = true`) for preview personas, but it does not create `onboarding_progress` records.

Preview users bypass onboarding entirely -- their data is seeded directly into domain tables.

---

## 15. API Routing

**File:** `routes/api.php` (lines 162-173)

All routes are under `auth:sanctum` middleware and `/onboarding` prefix:

```
GET    /api/onboarding/status              -> OnboardingController@getOnboardingStatus
POST   /api/onboarding/focus-area          -> OnboardingController@setFocusArea
GET    /api/onboarding/steps               -> OnboardingController@getSteps
GET    /api/onboarding/step/{step}         -> OnboardingController@getStepData
POST   /api/onboarding/step                -> OnboardingController@saveStepProgress
POST   /api/onboarding/skip-step           -> OnboardingController@skipStep
GET    /api/onboarding/skip-reason/{step}  -> OnboardingController@getSkipReason
POST   /api/onboarding/skip-to-dashboard   -> OnboardingController@skipToDashboard
POST   /api/onboarding/complete            -> OnboardingController@completeOnboarding
POST   /api/onboarding/restart             -> OnboardingController@restartOnboarding
```

**Route count:** 10 (4 GET, 6 POST)

**Note:** These routes are NOT listed in `PreviewWriteInterceptor::EXCLUDED_ROUTES` because preview users have `onboarding_completed = true` and would not normally access the onboarding wizard. If preview users needed to test onboarding, the POST routes would need to be excluded.

---

## 16. Key Constants

### EstateDefaults Constants
Default values used by estate onboarding when user data is unavailable. See **SharedInfrastructure.md Section 16.3** for the complete constants table.

### Mortgage Defaults (processAssets)

When creating mortgages during onboarding asset processing:
- Interest rate: 3.5% (0.0350)
- Rate type: `fixed`
- Mortgage type: `repayment`
- Start date: 5 years ago
- Maturity date: 20 years from now
- Remaining term: 240 months

### Investment Account Type Mapping

| Frontend Type | Database Type |
|---------------|---------------|
| `stocks_shares_isa` | `isa` |
| `gia` | `gia` |
| `offshore_bond` | `offshore_bond` |
| `other` | `gia` |

### Savings Access Type Mapping

| Account Type | Access Type |
|--------------|------------|
| `current_account` | `immediate` |
| `cash_isa` | `immediate` |
| `easy_access` | `immediate` |
| `notice_account` | `notice` |
| `fixed_term` | `fixed` |

### Focus Area Validation Values

```
estate, protection, retirement, investment, tax_optimisation
```

Only `estate` has an implemented flow.

---

## 17. Known Issues

### 17.1 Only 'estate' Focus Area Implemented
The `setFocusArea` endpoint validates against five focus areas, but only `estate` has a defined step flow (`EstateOnboardingFlow`). Selecting any other area results in an empty steps array. The `FocusAreaSelection` component is hardcoded to pass `'estate'` to `selectFocusArea()`.

### 17.2 TrustInfoStep Data Not Persisted to Domain Tables
The `processStepData()` switch statement has no `case 'trust_info'`. Trust data is saved only to `onboarding_progress.step_data` and is not written to any trust-related domain table. This means trust information collected during onboarding is effectively orphaned.

### 17.3 Wizard Always Resets to Welcome Screen on Mount
The `onMounted` hook in `OnboardingWizard` always resets the store:
```javascript
store.commit('onboarding/SET_FOCUS_AREA', null);
store.commit('onboarding/SET_CURRENT_STEP_INDEX', 0);
store.commit('onboarding/SET_CURRENT_STEP', null);
```
This means users returning to `/onboarding` after partial completion always see the welcome screen rather than resuming from their current step. They must click "Start Onboarding" again, which re-fetches steps and starts from step 1 (though previously completed steps retain their data).

### 17.4 URL-Based Step Navigation Not Functional
The router defines a child route `path: ':step'` (`/onboarding/:step`), but the wizard does not read step state from the URL. Step navigation is purely store-driven. Deep linking to a specific step does not work.

### 17.5 Expenditure Step Not Truly Required
Despite being marked as `required: true` in the EstateOnboardingFlow, the ExpenditureStep has an internal skip mechanism (zero-amount modal) that calls `onboarding/skipStep`. The `canSkip` prop passed to `OnboardingStep` is `false`, so there is no visible "Skip this step" link, but the step can still be skipped via the zero-data modal.

### 17.6 AssetsStep Backend Processor Unused by Frontend
The `processAssets()` method in OnboardingService handles property, investment, and savings creation with specific default values. However, the `AssetsStep` frontend component saves directly via module services (`propertyService`, `investmentService`, `savingsService`, `retirementService`). The backend processor would only be invoked if the step data were routed through `saveStepProgress`, which the frontend does not do for this step.

Similarly, `processLiabilities()` and `processProtectionPolicies()` exist in the service but are not used by the frontend components, which save directly through `estateService` and `protectionService` respectively.

### 17.7 FamilyInfoStep Backend Processor Partially Redundant
`processFamilyInfo()` in OnboardingService handles family member creation and spouse linking. However, the `FamilyInfoStep` component uses `familyMembersService` directly for CRUD. The backend processor is available but not invoked from the frontend step component's primary flow.

### 17.8 No Step Data Fallback for Most Steps
The `getStepDataFromUser()` method only implements a fallback for the `expenditure` step. All other steps return `null` when no `onboarding_progress` record exists. This means if a user has data in domain tables but no onboarding progress record, pre-population only works for expenditure.

---

## 18. Deep Dive: Dual Data Persistence Strategy

This is the most architecturally significant aspect of the onboarding module. There are two distinct patterns for how step data reaches the database, and the choice is made independently by each step component.

### Pattern A: Onboarding Pipeline (Backend Processing)

```
Step Component
  -> store.dispatch('onboarding/saveStepData', { stepName, data })
  -> onboardingService.saveStepProgress(stepName, data)
  -> POST /api/onboarding/step
  -> OnboardingController.saveStepProgress()
  -> OnboardingService.saveStepProgress()
  -> OnboardingService.processStepData()     // Writes to domain tables
  -> OnboardingProgress::updateOrCreate()     // Saves raw data to onboarding_progress
```

**Steps using this pattern:**
| Step | Backend Processor | Target Tables |
|------|-------------------|---------------|
| PersonalInfoStep | `processPersonalInfo()` | `users` |
| DomicileInformationStep | `processDomicileInfo()` | `users` |
| IncomeStep | `processIncomeInfo()` | `users`, `retirement_profiles` |
| ExpenditureStep | `processExpenditureInfo()` | `users` (both user and spouse) |
| WillInfoStep | `processWillInfo()` | `wills` |
| TrustInfoStep | *None* | `onboarding_progress` only |

**Characteristics:**
- Data passes through the onboarding API endpoint
- Raw form payload is saved to `onboarding_progress.step_data` for recovery/audit
- Domain-specific processing writes to the appropriate module tables
- Progress tracking (completed status, progress percentage) is automatically updated
- The `next_step` is calculated and returned in the API response

### Pattern B: Direct Module Service (Frontend Bypass)

```
Step Component
  -> moduleService.createRecord(data)         // Direct API call to module endpoint
  -> emit('next')                              // Tells wizard to advance
```

**Steps using this pattern:**
| Step | Frontend Service | Target Tables |
|------|-----------------|---------------|
| FamilyInfoStep | `familyMembersService` | `family_members`, `users`, `spouse_permissions` |
| AssetsStep | `retirementService`, `propertyService`, `investmentService`, `savingsService` | `dc_pensions`, `db_pensions`, `state_pensions`, `properties`, `mortgages`, `investment_accounts`, `savings_accounts` |
| LiabilitiesStep | `estateService` | `liabilities` |
| ProtectionPoliciesStep | `protectionService` | `life_insurance_policies`, `critical_illness_policies`, `income_protection_policies` |

**Characteristics:**
- Data goes directly to the module's own API endpoints
- No `onboarding_progress` record is created for these steps
- No `step_data` JSON snapshot is saved for recovery
- These steps use existing module CRUD forms (PropertyForm, AccountForm, PolicyFormModal, etc.)
- Progress tracking relies on the wizard's step index advancement rather than backend completion records
- The step simply emits `next` to advance the wizard without calling the onboarding save endpoint

### CompletionStep (Pattern C: Read-Only)

The completion step does not save form data. It:
1. Reads summary counts from 6 different module services
2. Dispatches `onboarding/completeOnboarding` on the "Go to Dashboard" button
3. Navigates to the dashboard

### Implications of the Dual Strategy

1. **Progress calculation asymmetry:** Steps using Pattern B never create `onboarding_progress` records, so `calculateProgress()` may undercount completed steps. The wizard relies on step index position rather than backend progress for these steps.

2. **Restart behaviour:** `restartOnboarding()` deletes all `onboarding_progress` records but does NOT delete data created via Pattern B (properties, investments, policies, etc.). A restart resets the onboarding state but the domain data remains.

3. **Data recovery:** Steps using Pattern A can recover their data from `onboarding_progress.step_data`. Steps using Pattern B have no onboarding-specific backup -- their data exists only in domain tables.

4. **Backend processors are partially dead code:** The `processAssets()`, `processLiabilities()`, `processProtectionPolicies()`, and `processFamilyInfo()` methods exist in `OnboardingService` but are not invoked from the frontend's primary flow because those steps use Pattern B. These processors would only run if the frontend were changed to route data through `saveStepProgress`.

5. **Consistency consideration:** The backend `processAssets()` method applies specific defaults (3.5% mortgage rate, repayment type) and joint ownership splitting (divide by 2). The frontend Pattern B flow uses whatever the module forms and services provide, which may have different defaults.
