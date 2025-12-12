# Fynla Onboarding System Documentation

Complete technical reference for the Fynla onboarding flow.

---

## 📋 Table of Contents

- [Overview](#overview)
- [File Structure](#file-structure)
- [How It Works](#how-it-works)
- [Step Definitions](#step-definitions)
- [State Management](#state-management)
- [API Endpoints](#api-endpoints)
- [Database Schema](#database-schema)
- [Adding New Steps](#adding-new-steps)
- [Troubleshooting](#troubleshooting)

---

## 🎯 Overview

The Fynla onboarding system is a multi-step wizard that collects comprehensive financial information from users during their initial setup. The system is designed to be:

- **Modular**: Each step is a self-contained Vue component
- **Flexible**: Steps can be skipped with user confirmation
- **Persistent**: Data is saved to database after each step
- **Progressive**: Real-time progress tracking with visual progress bar
- **Reusable**: Uses unified form components across onboarding and module dashboards

### Current Configuration

- **Focus Area**: Estate planning (hardcoded to 'estate')
- **Total Steps**: 11 steps
- **Completion Time**: ~10-15 minutes
- **Data Persistence**: Saves to actual database tables (not temporary storage)

---

## 📁 File Structure

### Frontend Files

#### 1. Main Views & Controllers

| File | Location | Purpose |
|------|----------|---------|
| **OnboardingView.vue** | `resources/js/views/Onboarding/` | Root view component - Simple wrapper that renders OnboardingWizard |
| **OnboardingWizard.vue** | `resources/js/components/Onboarding/` | Main orchestrator component - Manages step flow, progress bar, transitions, and skip modals |
| **FocusAreaSelection.vue** | `resources/js/components/Onboarding/` | Welcome screen - Shows "Welcome to Fynla" with feature list and "Continue to Onboarding" button |

#### 2. Helper Components

| File | Location | Purpose |
|------|----------|---------|
| **OnboardingStep.vue** | `resources/js/components/Onboarding/` | Base component wrapper for individual steps (provides consistent layout) |
| **SkipConfirmationModal.vue** | `resources/js/components/Onboarding/` | Modal dialog for confirming when user wants to skip a step |

#### 3. Onboarding Steps (11 Steps)

| File | Step Name | Purpose | Skippable |
|------|-----------|---------|-----------|
| **PersonalInfoStep.vue** | `personal_info` | Collect name, DOB, address | No |
| **IncomeStep.vue** | `income` | Collect employment income, self-employment, other income | Yes |
| **ExpenditureStep.vue** | `expenditure` | Collect monthly/annual expenses | Yes |
| **DomicileInformationStep.vue** | `domicile_info` | Collect UK domicile status, deemed domicile, non-dom status | Yes |
| **ProtectionPoliciesStep.vue** | `protection_policies` | Collect life insurance, critical illness, income protection policies | Yes |
| **AssetsStep.vue** | `assets` | Collect properties, investments, savings accounts, pensions | Yes |
| **LiabilitiesStep.vue** | `liabilities` | Collect mortgages, loans, credit cards | Yes |
| **FamilyInfoStep.vue** | `family_info` | Collect spouse and children information | Yes |
| **WillInfoStep.vue** | `will_info` | Collect will status, executor, last review date | Yes |
| **TrustInfoStep.vue** | `trust_info` | Collect trust information | Yes |
| **CompletionStep.vue** | `completion` | Final step - Shows summary and "Complete Onboarding" button | No |

All step files are located in: `resources/js/components/Onboarding/steps/`

#### 4. State Management & Services

| File | Location | Purpose |
|------|----------|---------|
| **onboarding.js** | `resources/js/store/modules/` | Vuex store - Manages onboarding state, progress, current step, focus area |
| **onboardingService.js** | `resources/js/services/` | API service - HTTP calls to backend for status, save progress, complete onboarding |

### Backend Files

#### 5. Controllers

| File | Location | Purpose |
|------|----------|---------|
| **OnboardingController.php** | `app/Http/Controllers/Api/` | RESTful API controller - Handles GET status, POST focus area, POST save progress, POST complete |

#### 6. Services

| File | Location | Purpose |
|------|----------|---------|
| **OnboardingService.php** | `app/Services/Onboarding/` | Main orchestrator - Manages status, focus area, progress calculation, step completion |
| **EstateOnboardingFlow.php** | `app/Services/Onboarding/` | Estate flow definition - Defines 11 steps for estate planning onboarding |

#### 7. Models

| File | Location | Purpose |
|------|----------|---------|
| **OnboardingProgress.php** | `app/Models/` | Database model - Tracks completion status for each step (user_id, step_name, completed_at) |

---

## 🔄 How It Works

### 1. Initial Load

```
User visits /onboarding
   ↓
OnboardingView.vue renders
   ↓
OnboardingWizard.vue loads
   ↓
Fetches onboarding status from API
   ↓
Shows FocusAreaSelection.vue ("Welcome to Fynla")
```

**Components Involved:**
- `OnboardingView.vue` (line 2): Renders `<OnboardingWizard />`
- `OnboardingWizard.vue` (lines 23-26): Conditionally shows `FocusAreaSelection` if no focus area set
- `FocusAreaSelection.vue` (lines 4-9): Displays welcome message and system overview

### 2. Focus Area Selection

```
User clicks "Continue to Onboarding"
   ↓
FocusAreaSelection.vue emits 'selected' event
   ↓
Dispatches onboarding/setFocusArea('estate')
   ↓
API: POST /api/onboarding/focus-area
   ↓
Backend: OnboardingService.setFocusArea()
   ↓
Backend saves focus_area to users table
   ↓
Frontend: Dispatches onboarding/fetchSteps
   ↓
API: GET /api/onboarding/steps
   ↓
Backend: EstateOnboardingFlow.getSteps()
   ↓
Frontend: Renders first step (PersonalInfoStep)
```

**Key Files:**
- `FocusAreaSelection.vue` (lines 85-96): `selectFocusArea()` method
- `onboarding.js` (lines 74-97): `setFocusArea` action
- `OnboardingController.php`: `setFocusArea()` endpoint
- `OnboardingService.php` (lines 50-61): `setFocusArea()` method
- `EstateOnboardingFlow.php`: `getSteps()` returns step definitions

### 3. Step Navigation (Next)

```
User fills out form and clicks "Next"
   ↓
Step component emits @next event
   ↓
OnboardingWizard.vue catches event
   ↓
Calls handleNext() (line 128)
   ↓
Dispatches onboarding/goToNextStep
   ↓
API: POST /api/onboarding/save-progress
   ↓
Backend: OnboardingService.saveStepProgress()
   ↓
Backend: processStepData() saves to actual tables
   ↓
Backend: Updates onboarding_progress table
   ↓
Backend: Updates current_step in users table
   ↓
Frontend: Increments currentStepIndex
   ↓
Frontend: Renders next step component
   ↓
Progress bar updates automatically
```

**Key Files:**
- `OnboardingWizard.vue` (lines 128-130): `handleNext()` method
- `onboarding.js`: `goToNextStep` action
- `OnboardingController.php`: `saveProgress()` endpoint
- `OnboardingService.php` (lines 82-120): `saveStepProgress()` method

### 4. Step Navigation (Back)

```
User clicks "Back"
   ↓
Step component emits @back event
   ↓
OnboardingWizard.vue catches event
   ↓
Calls handleBack() (line 132)
   ↓
Dispatches onboarding/goToPreviousStep
   ↓
Decrements currentStepIndex
   ↓
Renders previous step component
   ↓
Progress bar updates automatically
```

**Key Files:**
- `OnboardingWizard.vue` (lines 132-134): `handleBack()` method
- `onboarding.js`: `goToPreviousStep` action

### 5. Skipping Steps

```
User clicks "Skip" button
   ↓
Step component emits @skip event
   ↓
OnboardingWizard.vue catches event
   ↓
Calls handleSkipRequest() (line 136)
   ↓
Fetches skip reason from store
   ↓
Shows SkipConfirmationModal
   ↓
User confirms skip
   ↓
Calls confirmSkip() (line 150)
   ↓
Dispatches onboarding/skipStep
   ↓
API: POST /api/onboarding/skip-step
   ↓
Backend: OnboardingService.skipStep()
   ↓
Backend: Adds step to skipped_steps array
   ↓
Frontend: Moves to next step
```

**Key Files:**
- `OnboardingWizard.vue` (lines 136-148): Skip handling methods
- `SkipConfirmationModal.vue`: Confirmation dialog
- `onboarding.js`: `skipStep` action
- `OnboardingService.php`: `skipStep()` method

### 6. Completion

```
User completes all steps
   ↓
Reaches CompletionStep.vue
   ↓
Shows summary of entered data
   ↓
User clicks "Complete Onboarding"
   ↓
CompletionStep emits @next event
   ↓
Dispatches onboarding/completeOnboarding
   ↓
API: POST /api/onboarding/complete
   ↓
Backend: OnboardingService.completeOnboarding()
   ↓
Backend: Sets onboarding_completed = true
   ↓
Backend: Sets onboarding_completed_at timestamp
   ↓
Frontend: Redirects to /dashboard
```

**Key Files:**
- `CompletionStep.vue`: Final summary and completion button
- `onboarding.js`: `completeOnboarding` action
- `OnboardingController.php`: `complete()` endpoint
- `OnboardingService.php`: `completeOnboarding()` method

---

## 📝 Step Definitions

Each step in the onboarding flow has a specific purpose and collects specific data.

### Step 1: Personal Information

**Component**: `PersonalInfoStep.vue`
**Step Name**: `personal_info`
**Skippable**: No
**Required**: Yes

**Data Collected**:
- First name
- Last name
- Date of birth
- Address (street, city, postcode)

**Saved To**: `users` table

**Form Components Used**:
- Standard text inputs
- Date input with `formatDateForInput()` helper

---

### Step 2: Income

**Component**: `IncomeStep.vue`
**Step Name**: `income`
**Skippable**: Yes
**Required**: No

**Data Collected**:
- Employment income (annual)
- Self-employment income (annual)
- Rental income
- Dividend income
- Other income sources

**Saved To**: `users` table (income fields)

**Form Components Used**:
- Currency inputs
- Multiple income source fields

---

### Step 3: Expenditure

**Component**: `ExpenditureStep.vue`
**Step Name**: `expenditure`
**Skippable**: Yes
**Required**: No

**Data Collected**:
- Housing costs (mortgage/rent)
- Utilities
- Food & groceries
- Transportation
- Insurance
- Entertainment
- Other expenses

**Saved To**: `users` table (expenditure fields)

**Form Components Used**:
- Currency inputs
- Monthly/annual toggle

---

### Step 4: Domicile Information

**Component**: `DomicileInformationStep.vue`
**Step Name**: `domicile_info`
**Skippable**: Yes
**Required**: No

**Data Collected**:
- UK domicile status
- Deemed domicile status
- Non-domiciled status
- Country of domicile
- Years in UK

**Saved To**: `users` table

**Form Components Used**:
- Radio buttons
- Select dropdown
- Number input

**UK Tax Impact**:
- Domicile status affects IHT calculations
- Deemed domicile rules apply after 15/20 years in UK
- Non-doms have different IHT treatment

---

### Step 5: Protection Policies

**Component**: `ProtectionPoliciesStep.vue`
**Step Name**: `protection_policies`
**Skippable**: Yes
**Required**: No

**Data Collected**:
- Life insurance policies (with policy type selection)
- Critical illness policies
- Income protection policies

**Saved To**:
- `life_insurance_policies` table
- `critical_illness_policies` table
- `income_protection_policies` table

**Form Components Used**:
- `PolicyFormModal.vue` (unified form for all policy types)
- Policy list with add/edit/delete
- Conditional field display based on policy type

**Life Insurance Policy Types** (Canonical):
- `decreasing_term` - Decreasing Life Policy
- `level_term` - Level Term Life Policy
- `whole_of_life` - Whole of Life Policy

**Life Insurance Fields**:

**Common Fields (All Types)**:
- Provider (required)
- Policy number (optional)
- Sum assured / Coverage amount (required)
- Premium amount (required)
- Premium frequency (monthly/annual)
- In Trust checkbox
- Beneficiaries (with percentage allocation)

**Conditional Fields**:

**Decreasing Term Policies**:
- Life Policy Type selection (required)
- Start value (required) - Initial coverage amount
- Decreasing rate (required) - Annual percentage decrease (entered as 0-100%, converted to 0-1 decimal)
- Policy start date (required)
- Policy term years (required, 1-50)
- Calculated end date (auto-calculated from start date + term)

**Level Term Policies**:
- Life Policy Type selection (required)
- Policy start date (required)
- Policy term years (required, 1-50)
- Calculated end date (auto-calculated)

**Whole of Life Policies**:
- Life Policy Type selection (required)
- No start date field shown (auto-populated with today's date)
- No term field shown (auto-populated with 50 years representing lifetime coverage)

**Beneficiary Management** (Following Property Ownership Pattern):
- **Beneficiary Selection Dropdown**:
  - "Select beneficiary..." (default)
  - Linked spouse account (if exists) - "Spouse Name (Spouse - Linked Account)"
  - "Add Beneficiary" (free text entry)
- **Beneficiary Name Field**: Shows when "Add Beneficiary" selected, for manual entry
- **Beneficiary Percentage Input**: Required field (1-100%) for primary beneficiary's share
- **Percentage Split Display**: Visual display showing primary beneficiary % and remaining %
- **Additional Beneficiaries Field**: Only shown when percentage < 100%
  - Free text area for specifying additional beneficiaries and their shares
  - Example: "Children: 15% split, Charity: 5%"
- **Beneficiaries Storage Format**: `"Name: XX%, Additional text"`
- **Linked Account Benefits**: Beneficiaries with linked accounts are notified and benefits appear in their accounts

**Unified Form Pattern**:
```vue
<PolicyFormModal
  :policy="editingPolicy"
  :is-editing="!!editingPolicy"
  @save="handlePolicySaved"
  @close="closeForm"
/>
```

**Database Schema** (`life_insurance_policies`):
```sql
id BIGINT PRIMARY KEY AUTO_INCREMENT
user_id BIGINT NOT NULL
policy_type ENUM('term', 'whole_of_life', 'decreasing_term', 'family_income_benefit', 'level_term')
provider VARCHAR(255) NOT NULL
policy_number VARCHAR(255) NULLABLE
sum_assured DECIMAL(15,2) NOT NULL
start_value DECIMAL(15,2) NULLABLE -- For decreasing policies
decreasing_rate DECIMAL(5,4) NULLABLE -- For decreasing policies
premium_amount DECIMAL(10,2) NOT NULL
premium_frequency ENUM('monthly', 'quarterly', 'annually')
policy_start_date DATE NOT NULL
policy_term_years INT NOT NULL
indexation_rate DECIMAL(5,4) NULLABLE
in_trust BOOLEAN DEFAULT false
beneficiaries TEXT NULLABLE
created_at TIMESTAMP
updated_at TIMESTAMP
```

**Backend Processing**:
- `OnboardingService::createLifeInsurancePolicy()` handles all policy types
- Conditional field validation in `StoreLifePolicyRequest` based on `policy_type`:
  - Decreasing term: Requires `start_value`, `decreasing_rate` (0-1 decimal), `policy_start_date`, `policy_term_years` (1-50)
  - Level term: Requires `policy_start_date`, `policy_term_years` (1-50)
  - Whole of life: All fields nullable, auto-populated with defaults (today's date, 50 years)
- Decreasing rate conversion: Frontend converts percentage (0-100) to decimal (0-1) before submission
- Beneficiaries format: `"Name: percentage%, Additional beneficiaries text"`
- Whole of life policies: Auto-assigned 50 years term (max validation limit) representing lifetime coverage

---

### Step 6: Assets

**Component**: `AssetsStep.vue`
**Step Name**: `assets`
**Skippable**: Yes
**Required**: No

**Data Collected**:
- Properties (main residence, secondary residence, buy-to-let)
- Investment accounts (ISA, GIA, bonds, etc.)
- Savings accounts (cash ISA, instant access, fixed term)
- Pensions (DC, DB, State)

**Saved To**:
- `properties` table
- `investment_accounts` table
- `savings_accounts` table
- `dc_pensions` table
- `db_pensions` table
- `state_pensions` table

**Form Components Used**:
- `PropertyForm.vue`
- `AccountForm.vue` (Investment)
- `SaveAccountModal.vue` (Savings)
- `DCPensionForm.vue`
- `DBPensionForm.vue`
- `StatePensionForm.vue`

**Ownership Types**:
- Individual (sole ownership)
- Joint (shared with spouse)
- Trust (held in trust)

**Critical Rules**:
- ISAs must be individual ownership only (UK tax rule)
- Joint ownership creates reciprocal records automatically

#### DC Pension Details (SIPP and Personal Pensions)

**Scheme Types**:
- `workplace` - Workplace pension scheme
- `sipp` - Self-Invested Personal Pension
- `personal` - Personal pension

**Fields Collected**:

**Common Fields (All Types)**:
- Scheme name (required)
- Scheme type (required: workplace, SIPP, personal)
- Provider (required)
- Member number (optional)
- Current fund value (required)
- Investment strategy (optional)
- Platform fee percentage (optional, 0-10%)
- Retirement age (optional, 55-75)

**Workplace Pension Specific**:
- Annual salary (required if using percentage contributions)
- Employee contribution percentage (0-100%)
- Employer contribution percentage (0-100%)
- Salary sacrifice option (yes/no)

**SIPP and Personal Pension Specific**:
- Monthly contribution amount (£)
- **Lump sum contribution** (£, optional) - NEW FIELD
  - Purpose: Allows users to record one-off contributions to utilize carry forward allowances
  - Validation: Nullable, numeric, minimum 0
  - Help text: "One-off lump sum payment to take advantage of carry forward allowances"

**Database Schema** (`dc_pensions`):
```sql
id BIGINT PRIMARY KEY AUTO_INCREMENT
user_id BIGINT NOT NULL
scheme_name VARCHAR(255) NOT NULL
scheme_type ENUM('workplace', 'sipp', 'personal') NOT NULL
provider VARCHAR(255) NOT NULL
member_number VARCHAR(255) NULLABLE
current_fund_value DECIMAL(15,2) NOT NULL DEFAULT 0.00
annual_salary DECIMAL(10,2) NULLABLE
employee_contribution_percent DECIMAL(5,2) NULLABLE
employer_contribution_percent DECIMAL(5,2) NULLABLE
monthly_contribution_amount DECIMAL(10,2) NULLABLE
lump_sum_contribution DECIMAL(15,2) NULLABLE -- NEW
investment_strategy VARCHAR(255) NULLABLE
platform_fee_percent DECIMAL(5,4) NULLABLE
retirement_age INT NULLABLE
projected_value_at_retirement DECIMAL(15,2) NULLABLE
created_at TIMESTAMP
updated_at TIMESTAMP
```

**Backend Processing**:
- `DCPensionController::store()` and `::update()` handle pension creation/updates
- `StoreDCPensionRequest` validates all fields with conditional logic:
  - Workplace pensions: Requires `annual_salary` if percentage contributions provided
  - SIPP/Personal: Accepts `monthly_contribution_amount` and `lump_sum_contribution`
- All monetary values cast to `decimal:2` in model

---

### Step 7: Liabilities

**Component**: `LiabilitiesStep.vue`
**Step Name**: `liabilities`
**Skippable**: Yes
**Required**: No

**Data Collected**:
- Mortgages (linked to properties)
- Personal loans
- Credit cards
- Other debts

**Saved To**: `liabilities` table

**Form Components Used**:
- `LiabilityForm.vue`

**Liability Types** (Canonical):
- `mortgage`
- `loan`
- `credit_card`
- `other`

---

### Step 8: Family Information

**Component**: `FamilyInfoStep.vue`
**Step Name**: `family_info`
**Skippable**: Yes
**Required**: No

**Data Collected**:
- Spouse information (name, email, DOB, income)
- Children information (name, DOB, education status)
- Charitable bequest intention (yes/no)

**Saved To**:
- `family_members` table (family members)
- `users` table (`charitable_bequest` field)

**Spouse Account Management**:
- **New Email**: Creates spouse user account with random password, sends welcome email
- **Existing Email**: Links accounts bidirectionally, sets `marital_status = 'married'`
- **Data Sharing**: Creates `spouse_permissions` records for granular access control

**Charitable Bequest**:
- Boolean field asking: "Do you wish to leave anything to charity?"
- Displayed with helpful text: "Leaving 10% or more to charity can reduce your IHT rate from 40% to 36%"
- Used in Estate Planning module for IHT mitigation strategies
- Displayed in User Profile → Family Members section

**Form Components Used**:
- `FamilyMemberFormModal.vue`

---

### Step 9: Will Information

**Component**: `WillInfoStep.vue`
**Step Name**: `will_info`
**Skippable**: Yes
**Required**: No

**Data Collected**:
- Will existence (yes/no)
- Executor name
- Executor contact
- Will last updated date
- Will storage location

**Saved To**: `users` table

**Form Components Used**:
- Standard form inputs
- Date input with `formatDateForInput()` helper

---

### Step 10: Trust Information

**Component**: `TrustInfoStep.vue`
**Step Name**: `trust_info`
**Skippable**: Yes
**Required**: No

**Data Collected**:
- Trust name
- Trust type (discretionary, bare, interest in possession)
- Trustees
- Beneficiaries
- Assets held in trust
- Trust creation date

**Saved To**: `trusts` table

**Form Components Used**:
- `TrustForm.vue`

---

### Step 11: Completion

**Component**: `CompletionStep.vue`
**Step Name**: `completion`
**Skippable**: No
**Required**: Yes

**Purpose**:
- Shows summary of all entered data
- Displays completion message
- Provides "Complete Onboarding" button
- Redirects to dashboard after completion

**No Data Collected** - This is a confirmation step only

---

## 🗄️ State Management

### Vuex Store State

**Location**: `resources/js/store/modules/onboarding.js`

```javascript
const state = {
  status: null,                 // null | 'in_progress' | 'completed'
  focusArea: null,              // 'estate' (currently hardcoded)
  currentStepIndex: 0,          // 0-10 (array index)
  currentStepName: null,        // 'personal_info', 'income', etc.
  totalSteps: 0,                // 11
  steps: [],                    // Array of step definitions
  stepData: {},                 // Cached step data
  progressPercentage: 0,        // 0-100%
  loading: false,               // API call in progress
  error: null,                  // Error message
  showSkipModal: false,         // Skip confirmation modal visibility
  currentSkipReason: '',        // Why this step can be skipped
  skipStepName: '',             // Which step is being skipped
};
```

### Vuex Getters

```javascript
// Check if onboarding is complete
isOnboardingComplete: (state) => state.status === 'completed'

// Check if onboarding is in progress
isOnboardingInProgress: (state) => state.status === 'in_progress'

// Get current step object
currentStep: (state) => state.steps[state.currentStepIndex]

// Get data for current step
currentStepData: (state) => state.stepData[state.currentStepName]

// Get progress percentage
progressPercentage: (state) => state.progressPercentage

// Check if user can go to next step
canGoNext: (state) => state.currentStepIndex < state.steps.length - 1

// Check if user can go back
canGoBack: (state) => state.currentStepIndex > 0
```

### Vuex Actions

```javascript
// Fetch onboarding status from API
fetchOnboardingStatus({ commit, state })

// Set focus area (currently 'estate')
setFocusArea({ commit, dispatch }, focusArea)

// Fetch steps for current focus area
fetchSteps({ commit, state })

// Go to next step
goToNextStep({ commit, dispatch, state })

// Go to previous step
goToPreviousStep({ commit, state })

// Skip current step
skipStep({ commit, dispatch, state })

// Complete onboarding
completeOnboarding({ commit, dispatch, state })

// Show skip confirmation modal
showSkipConfirmation({ commit, state }, stepName)

// Hide skip confirmation modal
hideSkipConfirmation({ commit })
```

---

## 🔌 API Endpoints

**Base URL**: `/api/onboarding`

### GET `/api/onboarding/status`

**Purpose**: Get user's onboarding status

**Response**:
```json
{
  "onboarding_completed": false,
  "focus_area": "estate",
  "current_step": "personal_info",
  "skipped_steps": [],
  "started_at": "2025-11-12T00:00:00.000000Z",
  "completed_at": null,
  "progress_percentage": 0,
  "total_steps": 11,
  "completed_steps": 0
}
```

### POST `/api/onboarding/focus-area`

**Purpose**: Set user's focus area

**Request Body**:
```json
{
  "focus_area": "estate"
}
```

**Response**:
```json
{
  "current_step": "personal_info",
  "message": "Focus area set successfully"
}
```

### GET `/api/onboarding/steps`

**Purpose**: Get steps for current focus area

**Response**:
```json
{
  "steps": [
    {
      "name": "personal_info",
      "title": "Personal Information",
      "description": "Tell us about yourself",
      "required": true,
      "skippable": false
    },
    {
      "name": "income",
      "title": "Income",
      "description": "Your income sources",
      "required": false,
      "skippable": true
    },
    // ... more steps
  ]
}
```

### POST `/api/onboarding/save-progress`

**Purpose**: Save progress for current step

**Request Body**:
```json
{
  "step_name": "personal_info",
  "data": {
    "first_name": "John",
    "last_name": "Doe",
    "date_of_birth": "1980-01-01"
  }
}
```

**Response**:
```json
{
  "message": "Progress saved successfully",
  "next_step": "income"
}
```

### POST `/api/onboarding/skip-step`

**Purpose**: Skip current step

**Request Body**:
```json
{
  "step_name": "income"
}
```

**Response**:
```json
{
  "message": "Step skipped successfully",
  "next_step": "expenditure"
}
```

### POST `/api/onboarding/complete`

**Purpose**: Mark onboarding as complete

**Request Body**:
```json
{
  "confirm": true
}
```

**Response**:
```json
{
  "message": "Onboarding completed successfully",
  "redirect": "/dashboard"
}
```

---

## 💾 Database Schema

### `users` Table (Onboarding Fields)

```sql
onboarding_completed BOOLEAN DEFAULT false
onboarding_focus_area VARCHAR(50) NULLABLE
onboarding_current_step VARCHAR(50) NULLABLE
onboarding_skipped_steps JSON NULLABLE
onboarding_started_at TIMESTAMP NULLABLE
onboarding_completed_at TIMESTAMP NULLABLE
charitable_bequest BOOLEAN NULLABLE
```

**Note**: The `charitable_bequest` field is populated during the Family Information step and indicates whether the user intends to leave charitable bequests in their will (used for IHT rate reduction calculations).

### `onboarding_progress` Table

```sql
id BIGINT PRIMARY KEY AUTO_INCREMENT
user_id BIGINT NOT NULL
focus_area VARCHAR(50) NOT NULL
step_name VARCHAR(50) NOT NULL
step_data JSON NULLABLE
completed BOOLEAN DEFAULT false
completed_at TIMESTAMP NULLABLE
created_at TIMESTAMP
updated_at TIMESTAMP

UNIQUE KEY (user_id, focus_area, step_name)
FOREIGN KEY (user_id) REFERENCES users(id)
```

### Data Tables Populated During Onboarding

- `users` - Personal info, income, expenditure, domicile, will info, charitable bequest
- `life_insurance_policies` - Life insurance policies with conditional fields:
  - All types: provider, policy_number, sum_assured, premium_amount, premium_frequency, in_trust, beneficiaries
  - Decreasing term: start_value, decreasing_rate, policy_start_date, policy_term_years
  - Term/Level term: policy_start_date, policy_term_years
  - Whole of life: no dates or term required
- `critical_illness_policies` - Critical illness policies
- `income_protection_policies` - Income protection policies
- `properties` - Properties (main residence, secondary, buy-to-let)
- `investment_accounts` - Investment accounts (ISA, GIA, bonds, etc.)
- `savings_accounts` - Savings accounts (cash ISA, instant access, fixed)
- `dc_pensions` - Defined contribution pensions
- `db_pensions` - Defined benefit pensions
- `state_pensions` - State pensions
- `liabilities` - Mortgages, loans, credit cards
- `family_members` - Spouse and children
- `trusts` - Trust information

---

## ➕ Adding New Steps

### 1. Create Step Component

**Location**: `resources/js/components/Onboarding/steps/NewStep.vue`

```vue
<template>
  <OnboardingStep
    title="New Step Title"
    description="Step description"
  >
    <form @submit.prevent="handleSubmit">
      <!-- Your form fields here -->

      <div class="flex justify-between mt-6">
        <button
          type="button"
          @click="handleBack"
          class="btn-secondary"
        >
          Back
        </button>

        <div class="space-x-3">
          <button
            v-if="isSkippable"
            type="button"
            @click="handleSkip"
            class="btn-secondary"
          >
            Skip
          </button>

          <button
            type="submit"
            class="btn-primary"
          >
            Next
          </button>
        </div>
      </div>
    </form>
  </OnboardingStep>
</template>

<script>
import { ref } from 'vue';
import OnboardingStep from '../OnboardingStep.vue';

export default {
  name: 'NewStep',

  components: {
    OnboardingStep,
  },

  emits: ['next', 'back', 'skip'],

  setup(props, { emit }) {
    const formData = ref({
      // Your form fields
    });

    const handleSubmit = async () => {
      // Validate form data
      // Save via API
      emit('next');
    };

    const handleBack = () => {
      emit('back');
    };

    const handleSkip = () => {
      emit('skip');
    };

    return {
      formData,
      handleSubmit,
      handleBack,
      handleSkip,
      isSkippable: true,
    };
  },
};
</script>
```

### 2. Register Component in OnboardingWizard

**File**: `resources/js/components/Onboarding/OnboardingWizard.vue`

```javascript
// Import component (line 57+)
import NewStep from './steps/NewStep.vue';

// Register component (line 72+)
components: {
  // ... existing components
  NewStep,
},

// Add to component map (line 105+)
const componentMap = {
  // ... existing mappings
  new_step: 'NewStep',
};
```

### 3. Add Step to EstateOnboardingFlow

**File**: `app/Services/Onboarding/EstateOnboardingFlow.php`

```php
public function getSteps(): array
{
    return [
        // ... existing steps
        'new_step' => [
            'name' => 'new_step',
            'title' => 'New Step Title',
            'description' => 'Step description',
            'required' => false,
            'skippable' => true,
            'skip_reason' => 'You can skip this step if...',
        ],
        // ... more steps
    ];
}
```

### 4. Add Data Processing Logic

**File**: `app/Services/Onboarding/OnboardingService.php`

```php
private function processStepData(int $userId, string $stepName, array $data): void
{
    // ... existing step handlers

    if ($stepName === 'new_step') {
        // Process and save new step data
        // Example: Update users table or create records in other tables
        User::where('id', $userId)->update([
            'new_field' => $data['new_field'],
        ]);
    }
}
```

### 5. Test the New Step

1. Clear cache: `php artisan cache:clear`
2. Restart servers: `./dev.sh`
3. Visit `/onboarding`
4. Navigate to new step
5. Test form submission
6. Test skip functionality
7. Test back/next navigation
8. Verify data is saved to database

---

## 🐛 Troubleshooting

### Issue: Step not showing up

**Symptoms**: New step doesn't appear in onboarding flow

**Solutions**:
1. Check component is imported in `OnboardingWizard.vue`
2. Check component is registered in `components` object
3. Check component map has correct mapping
4. Check step is defined in `EstateOnboardingFlow.php`
5. Clear cache: `php artisan cache:clear`

### Issue: Data not saving

**Symptoms**: Form submits but data doesn't persist

**Solutions**:
1. Check API endpoint is being called (browser network tab)
2. Check `processStepData()` has handler for this step
3. Check database table columns exist
4. Check validation rules in FormRequest
5. Check Laravel logs: `storage/logs/laravel.log`

### Issue: Form validation errors

**Symptoms**: Form won't submit, shows validation errors

**Solutions**:
1. Check FormRequest validation rules
2. Check frontend validation logic
3. Check required fields are populated
4. Check field names match between frontend and backend
5. Check API error response in browser network tab

### Issue: Progress not updating

**Symptoms**: Progress bar doesn't move after completing step

**Solutions**:
1. Check `onboarding_progress` table is being updated
2. Check `calculateProgress()` method in `OnboardingService.php`
3. Check Vuex store is updating `progressPercentage`
4. Check step is marked as `completed` in database

### Issue: Skip modal not showing

**Symptoms**: Skip button doesn't show confirmation modal

**Solutions**:
1. Check step is marked as `skippable: true`
2. Check `skip_reason` is defined in step definition
3. Check `SkipConfirmationModal` is imported
4. Check `@skip` event is emitted from step component
5. Check `handleSkipRequest()` is being called

### Issue: Cannot go back to previous step

**Symptoms**: Back button doesn't work

**Solutions**:
1. Check `@back` event is emitted from step component
2. Check `handleBack()` is calling correct Vuex action
3. Check `canGoBack` getter is returning true
4. Check `currentStepIndex` is greater than 0

### Issue: Onboarding redirects to dashboard immediately

**Symptoms**: User is redirected before completing onboarding

**Solutions**:
1. Check `onboarding_completed` is false in database
2. Check route guard in `router/index.js`
3. Check `OnboardingWizard.vue` mounted lifecycle
4. Check `fetchOnboardingStatus()` response

---

## 📊 Statistics

- **Total Files**: 18 files (11 frontend, 7 backend)
- **Total Steps**: 11 steps
- **Average Completion Time**: 10-15 minutes
- **Data Tables Updated**: 13 tables
- **Skippable Steps**: 9 steps
- **Required Steps**: 2 steps (Personal Info, Completion)

---

## 🆕 Recent Updates (November 12, 2025)

### Protection Policies Enhancement

**Life Insurance Policy Types**:
- Added conditional Life Policy Type dropdown with 3 options:
  - Decreasing Term Life Policy
  - Level Term Life Policy
  - Whole of Life Policy
- Each type shows/hides relevant fields dynamically
- Removed duplicate "Term Life Policy" option

**Beneficiary Management (Following Property Ownership Pattern)**:
- Complete redesign of beneficiary system
- Dropdown shows linked spouse accounts or "Add Beneficiary" option
- Percentage allocation system (1-100%)
- Visual split display showing primary beneficiary % and remaining %
- Additional beneficiaries field only appears when percentage < 100%
- Removed "Estate" and "Trust" options from dropdown (Trust checkbox exists separately)
- Beneficiaries stored as: `"Name: percentage%, Additional beneficiaries text"`

**Conditional Fields by Policy Type**:
- **Decreasing Term**: Start value, decreasing rate (auto-converts % to decimal), start date, term years
- **Level Term**: Start date, term years
- **Whole of Life**: No date/term fields shown (auto-populated with today's date and 50 years)

**Bug Fixes**:
- Fixed decreasing rate validation (percentage → decimal conversion)
- Fixed whole of life policies (50-year term limit)
- Fixed family members array loading
- Fixed beneficiary dropdown safety checks

**UI Enhancements**:
- Added green Life Policy Type tags in policy list cards
- Tags show specific policy type next to provider name
- Only displays for life insurance policies

### DC Pension Lump Sum Contribution

**New Field Added**:
- `lump_sum_contribution` field added to SIPP and Personal Pensions
- Purpose: Record one-off contributions to utilize carry forward allowances
- Optional field, accepts decimal values (£)
- Only shows for SIPP and Personal pension types (not workplace)

**Database Changes**:
- Added `lump_sum_contribution` column to `dc_pensions` table (DECIMAL 15,2, nullable)
- Updated `DCPension` model with new field
- Updated `StoreDCPensionRequest` validation

**Frontend Changes**:
- Added input field in `DCPensionForm.vue` after monthly contribution
- Field includes helpful text explaining carry forward usage
- Conditional display using `v-if="isPersonalPension"`

### Documentation Updates

**ONBOARDING.md** (This Document):
- Added comprehensive DC Pension Details section with:
  - Scheme type definitions
  - Field descriptions for all pension types
  - Complete database schema
  - Backend processing notes
- Updated Protection Policies section with complete beneficiary management details
- Added Life Insurance Policy Types with conditional field logic

**Files Modified**: 15 files
- 1 database migration
- 3 model updates
- 2 validation requests
- 5 Vue components
- 1 documentation file

---

## 🔗 Related Documentation

- **CLAUDE.md**: Development guidelines and critical rules
- **README.md**: Project overview and installation
- **COMPREHENSIVE_FEATURES_AND_ARCHITECTURE.md**: Complete technical reference

---

**Last Updated**: November 12, 2025
**Version**: v0.2.7 - Onboarding Complete

**Status**: ✅ **ONBOARDING SYSTEM COMPLETE** - All 11 steps fully functional with comprehensive data collection

---

🤖 **Built with [Claude Code](https://claude.com/claude-code)**
