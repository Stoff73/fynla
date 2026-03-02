# Shared Infrastructure -- Current State Documentation

This document covers all cross-cutting, shared, and utility code that underpins every module in the Fynla application. Unlike other currentState documents that describe a single vertical module, this one describes the horizontal layer: traits, middleware, resources, constants, mixins, utilities, the router, the base API service, and the design system that every other module depends on.

---

## 1. System Overview

The Shared Infrastructure layer provides the foundational code reused across all seven Fynla modules (Protection, Savings, Investment, Retirement, Estate Planning, Goals & Life Events, and Coordination). It is not a module with its own dashboard -- it is a set of backend traits, middleware, API resources, services, frontend constants, mixins, utilities, and shared Vue components that enforce consistency and reduce duplication.

**Scope of this document:**

| Category                  | Count | Location                                      |
|---------------------------|-------|-----------------------------------------------|
| PHP Traits                | 7     | `app/Traits/` + `app/Http/Traits/`            |
| Middleware                | 7     | `app/Http/Middleware/`                         |
| API Resources             | 10    | `app/Http/Resources/`                         |
| Backend Services          | 1     | `app/Services/Audit/AuditService.php`         |
| Backend Models            | 1     | `app/Models/AuditLog.php`                     |
| Backend Constants         | 3     | `app/Constants/`                              |
| Custom Casts              | 2     | `app/Casts/`                                  |
| Custom Exceptions         | 1     | `app/Exceptions/FinancialCalculationException.php` |
| Frontend Constants        | 2     | `resources/js/constants/`                     |
| Frontend Mixins           | 2     | `resources/js/mixins/`                        |
| Frontend Utilities        | 7     | `resources/js/utils/`                         |
| Shared Vue Components     | 16    | `resources/js/components/Shared/`             |
| Guidance Components       | 2     | `resources/js/components/Guidance/`           |
| Frontend Services         | 35    | `resources/js/services/`                      |
| Vuex Store Helpers        | 1     | `resources/js/store/helpers/storeHelpers.js`  |
| Cross-cutting Vuex Stores | 2     | `guidance.js`, `trusts.js`                    |
| Router                    | 1     | `resources/js/router/index.js` (835 lines)    |
| Base API Service          | 1     | `resources/js/services/api.js` (194 lines)    |
| Seeders                   | 6     | `database/seeders/`                           |
| Test Factories            | 22    | `database/factories/`                         |
| Schema Dump               | 1     | `database/schema/mysql-schema.sql`            |

**Key design principles enforced by shared infrastructure:**

- Single-record joint ownership (one DB row, percentage split)
- No amber/orange colours anywhere in the UI (blue for warnings)
- All currency formatting through centralised formatters (never local `formatCurrency`)
- Preview user isolation at every layer (trait, middleware, mixin, API interceptor)
- Audit trail via model hooks, not manual logging calls
- Design system as single source of truth for all visual tokens

---

## 2. Database Schema (audit_logs table)

**Migration:** `database/migrations/2026_01_19_135404_create_audit_logs_table.php`

```
audit_logs
├── id                  BIGINT UNSIGNED  PK AUTO_INCREMENT
├── user_id             BIGINT UNSIGNED  FK(users.id) NULLABLE, ON DELETE SET NULL
├── event_type          VARCHAR(50)      -- auth | data_access | data_change | admin | gdpr
├── action              VARCHAR(100)     -- e.g. login_success, created, updated, export_requested
├── model_type          VARCHAR(100)     NULLABLE -- Fully qualified class name (App\Models\Property)
├── model_id            BIGINT UNSIGNED  NULLABLE -- PK of the affected model record
├── old_values          JSON             NULLABLE -- Previous field values (on update/delete)
├── new_values          JSON             NULLABLE -- New field values (on create/update)
├── metadata            JSON             NULLABLE -- Arbitrary context (IP for auth, GDPR request ID, etc.)
├── ip_address          VARCHAR(45)      NULLABLE -- Supports IPv6
├── user_agent          TEXT             NULLABLE
└── created_at          TIMESTAMP        DEFAULT CURRENT_TIMESTAMP
```

**Indexes:**

| Index                          | Columns                     | Purpose                           |
|--------------------------------|-----------------------------|-----------------------------------|
| Composite user + time          | `(user_id, created_at)`     | User activity timeline queries    |
| Composite event + action       | `(event_type, action)`      | Filter by category + specific act |
| Composite model                | `(model_type, model_id)`    | Per-record audit history          |
| Time-based                     | `(created_at)`              | Retention/purge queries           |

**Note:** The model uses `$timestamps = false` and manages only `created_at` (no `updated_at`). Audit log records are immutable -- they are never updated after creation.

---

## 3. Models (AuditLog)

**File:** `app/Models/AuditLog.php` (249 lines)

### 3.1 Event Type Constants

| Constant              | Value            | Usage                                     |
|-----------------------|------------------|-------------------------------------------|
| `EVENT_AUTH`          | `auth`           | Login, logout, MFA, password events       |
| `EVENT_DATA_ACCESS`   | `data_access`    | Read-only access logging (programmatic)   |
| `EVENT_DATA_CHANGE`   | `data_change`    | Create, update, delete via Auditable trait|
| `EVENT_ADMIN`         | `admin`          | Administrative actions                    |
| `EVENT_GDPR`          | `gdpr`           | Data export and erasure events            |

### 3.2 Action Constants (18 total)

**Auth actions (11):**

| Constant                          | Value                        |
|-----------------------------------|------------------------------|
| `ACTION_LOGIN_ATTEMPT`            | `login_attempt`              |
| `ACTION_LOGIN_SUCCESS`            | `login_success`              |
| `ACTION_LOGIN_FAILED`             | `login_failed`               |
| `ACTION_LOGOUT`                   | `logout`                     |
| `ACTION_MFA_ENABLED`              | `mfa_enabled`                |
| `ACTION_MFA_DISABLED`             | `mfa_disabled`               |
| `ACTION_MFA_VERIFIED`             | `mfa_verified`               |
| `ACTION_PASSWORD_CHANGED`         | `password_changed`           |
| `ACTION_PASSWORD_RESET_REQUESTED` | `password_reset_requested`   |
| `ACTION_PASSWORD_RESET_COMPLETED` | `password_reset_completed`   |
| `ACTION_SESSION_REVOKED`          | `session_revoked`            |

**Data actions (3):**

| Constant           | Value      |
|--------------------|------------|
| `ACTION_CREATED`   | `created`  |
| `ACTION_UPDATED`   | `updated`  |
| `ACTION_DELETED`   | `deleted`  |

**GDPR actions (4):**

| Constant                    | Value                |
|-----------------------------|----------------------|
| `ACTION_EXPORT_REQUESTED`   | `export_requested`   |
| `ACTION_EXPORT_COMPLETED`   | `export_completed`   |
| `ACTION_ERASURE_REQUESTED`  | `erasure_requested`  |
| `ACTION_ERASURE_COMPLETED`  | `erasure_completed`  |

### 3.3 Static Factory Methods

```php
// General-purpose (all parameters)
AuditLog::log($eventType, $action, $userId?, $modelType?, $modelId?, $oldValues?, $newValues?, $metadata?)

// Convenience wrappers
AuditLog::logAuth($action, $userId?, $metadata?)
AuditLog::logDataChange($action, Model $model, $oldValues?, $newValues?)
AuditLog::logGDPR($action, $userId, $metadata?)
AuditLog::logAdmin($action, $metadata?)
```

All factory methods automatically capture `ip_address` and `user_agent` from the current request. The `user_id` defaults to `auth()->id()` when not explicitly provided.

### 3.4 Query Scopes

| Scope           | Parameters                      | Description                                 |
|-----------------|---------------------------------|---------------------------------------------|
| `byEventType`   | `string $eventType`            | Filter by event type constant               |
| `byUser`         | `int $userId`                  | Filter by user                              |
| `byModel`        | `string $modelType, ?int $id` | Filter by model class, optionally by ID     |
| `recent`         | `int $days = 30`               | Records from last N days                    |

### 3.5 Relationships and Accessors

- `user()` -- BelongsTo User (nullable, set null on delete)
- `auditable()` -- Dynamic morph lookup (`model_type::find(model_id)`)
- `getActionLabelAttribute()` -- Human-readable label accessor via `match` expression

### 3.6 Cast Configuration

```php
'old_values' => 'array',
'new_values' => 'array',
'metadata'   => 'array',
'created_at' => 'datetime',
```

---

## 4. Controllers

N/A -- The shared infrastructure layer has no dedicated controllers. The AuditLog model and AuditService are consumed by controllers in other modules (primarily Auth and Admin). API Resources are used by module-specific controllers.

---

## 5. Agent

N/A -- There is no shared infrastructure agent. The seven module agents (ProtectionAgent, SavingsAgent, InvestmentAgent, RetirementAgent, EstateAgent, GoalsAgent, CoordinatingAgent) each consume shared traits and services directly.

---

## 6. Services (AuditService)

**File:** `app/Services/Audit/AuditService.php` (116 lines)

The AuditService provides a thin programmatic interface over the AuditLog model, primarily used by controllers for events that do not happen through Eloquent model hooks (authentication events, data access logging, admin actions, GDPR operations).

### 6.1 Write Methods

| Method             | Parameters                                           | Delegates To             |
|--------------------|------------------------------------------------------|--------------------------|
| `logAuth`          | `string $action, ?User $user, array $metadata`       | `AuditLog::logAuth`      |
| `logDataAccess`    | `string $action, ?Model $model, array $metadata`     | `AuditLog::log`          |
| `logDataChange`    | `string $action, Model $model, array $old, array $new` | `AuditLog::logDataChange` |
| `logAdmin`         | `string $action, array $metadata`                    | `AuditLog::logAdmin`     |
| `logGDPR`          | `string $action, int $userId, array $metadata`       | `AuditLog::logGDPR`      |

### 6.2 Query Methods

| Method                   | Parameters                                  | Returns                        |
|--------------------------|---------------------------------------------|--------------------------------|
| `getUserLogs`            | `User $user, int $limit = 100`              | Collection of AuditLog records |
| `getRecentAuthLogs`      | `int $limit = 100`                          | Collection (auth events only)  |
| `getModelLogs`           | `string $modelType, ?int $modelId, int $limit = 100` | Collection (model-filtered) |
| `getFailedLoginAttempts` | `int $hours = 24, int $limit = 100`         | Collection (failed logins)     |

All query methods order by `created_at DESC` and apply the specified limit.

---

## 7. Validation Requests

N/A -- The shared infrastructure does not define its own FormRequest classes. Each module defines its own validation requests. The SanitizeInput middleware provides input sanitisation at a global level.

---

## 8. Vuex Store

The shared infrastructure is a utility layer and does not have its own dedicated Vuex store module. However, it provides store helpers used by all 21 Vuex modules. Two cross-cutting Vuex stores (`guidance.js` and `trusts.js`) are documented in Sections 16.7 and 16.8 respectively.

**File:** `resources/js/store/helpers/storeHelpers.js` (229 lines)

| Export                   | Purpose                                                         |
|--------------------------|-----------------------------------------------------------------|
| `createBaseState`        | Adds `loading: false, error: null` to any module state          |
| `createBaseGetters`      | Standard `loading` and `error` getters                          |
| `createBaseMutations`    | Standard `setLoading` and `setError` mutations                  |
| `createCrudMutations`    | Generates `set{Collection}`, `add{Item}`, `update{Item}`, `remove{Item}` |
| `withLoading`            | Wraps async operations with loading/error state management      |
| `createAsyncAction`      | Factory for Vuex actions with automatic loading/error handling  |
| `extractResponseData`    | Handles both wrapped (`response.data`) and unwrapped API responses |
| `formatCurrency`         | Intl.NumberFormat GBP formatting (store-level convenience)      |
| `calculateTotal`         | Sum a field across an array of items with optional transform    |

**Additional async utility:** `resources/js/utils/asyncAction.js` (158 lines)

| Export                          | Purpose                                                       |
|---------------------------------|---------------------------------------------------------------|
| `createAsyncAction`             | More configurable factory with `extractData`, `onSuccess`, `onError`, `skipLoading`, `rethrowError` |
| `createAsyncActionWithRefresh`  | Same as above but dispatches refresh actions after success     |
| `createCrudAction`              | Specialised for CRUD with `add`/`update`/`remove` mutation routing |

---

## 9. API Service (api.js)

**File:** `resources/js/services/api.js` (194 lines)

This is the base Axios instance imported by all 35 frontend service files.

### 9.1 Instance Configuration

```javascript
const api = axios.create({
  baseURL: `${apiBaseURL}/api`,
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
  withCredentials: true,
});
```

**Base URL resolution:**
- Local development (`localhost` or `127.0.0.1`): `http://{hostname}:8000`
- Production: `window.location.origin` (avoids CORS mismatch between `www.fynla.org` and `fynla.org`)
- Fallback chain: `window.location.origin` -> `VITE_API_BASE_URL` env var -> `http://localhost:8000`

### 9.2 Request Interceptor

Attaches Bearer token from `sessionStorage` (primary) with `localStorage` fallback (legacy). Every outgoing request gets the `Authorization: Bearer {token}` header if a token exists.

### 9.3 Response Interceptor (Error Handling)

| Status | Behaviour                                                                        |
|--------|----------------------------------------------------------------------------------|
| 200    | Checks for `preview_mode: true` flag, adds `_preview_notice` if present          |
| 401    | Clears token, redirects to `/login` (unless on auth endpoint or in preview mode) |
| 422    | Logs as info (not error), rejects with structured `{message, errors, status}`    |
| Other  | Rejects with `{message, errors, status, response}`                               |
| Network| Rejects with `'Network error. Please check your connection.'`                    |

### 9.4 Retry Interceptor

A second response interceptor implements automatic retry with exponential backoff:

| Setting         | Value                                            |
|-----------------|--------------------------------------------------|
| Max retries     | 3                                                |
| Base delay      | 1,000ms (2,000ms for 429 responses)              |
| Max delay       | 10,000ms                                         |
| Jitter          | Random 0-30% of exponential delay                |
| Retry condition | 5xx, network errors, 429 (rate limited)          |
| Idempotent only | GET, HEAD, OPTIONS, PUT, DELETE (NOT POST)        |

**Backoff formula:** `min(base * 2^retryCount + random_jitter, 10000)`

### 9.5 All Frontend Services (35 total)

Every service file imports the base `api` instance and adds module-specific endpoints:

| Service                        | Module         | Description                          |
|--------------------------------|----------------|--------------------------------------|
| `adminService.js`              | Admin          | User management, system config       |
| `api.js`                       | Shared         | Base Axios instance (this file)      |
| `assumptionsService.js`        | Settings       | Planning assumptions CRUD            |
| `authService.js`               | Auth           | Login, register, MFA, password       |
| `bugReportService.js`          | Shared         | Error reporting to backend           |
| `businessInterestService.js`   | Net Worth      | Business interests CRUD              |
| `chattelService.js`            | Net Worth      | Chattels/valuables CRUD              |
| `consoleCapture.js`            | Shared         | Browser console capture for bug reports |
| `dashboardService.js`          | Dashboard      | Dashboard summary data               |
| `dcPensionHoldingsService.js`  | Retirement     | DC pension holdings management       |
| `diversificationService.js`    | Investment     | Portfolio diversification analysis   |
| `documentService.js`           | Shared         | Document upload & AI extraction      |
| `estateService.js`             | Estate         | Estate planning calculations         |
| `familyMembersService.js`      | Profile        | Family member management             |
| `goalsService.js`              | Goals          | Goals CRUD and tracking              |
| `holisticService.js`           | Coordination   | Cross-module holistic plan           |
| `investmentService.js`         | Investment     | Investment accounts, Monte Carlo     |
| `mortgageService.js`           | Property       | Mortgage CRUD                        |
| `netWorthService.js`           | Net Worth      | Aggregated net worth calculations    |
| `occupationService.js`         | Profile        | Occupation autocomplete              |
| `onboardingService.js`         | Onboarding     | Step-by-step onboarding              |
| `plansService.js`              | Plans          | Financial plans generation           |
| `portfolioOptimizationService.js` | Investment  | Portfolio rebalancing optimisation   |
| `postcodeService.js`           | Shared         | UK postcode lookup (placeholder)     |
| `propertyService.js`           | Property       | Property CRUD                        |
| `protectionService.js`         | Protection     | Insurance policies CRUD              |
| `rebalancingService.js`        | Investment     | Portfolio rebalancing execution      |
| `retirementService.js`         | Retirement     | Pension and retirement calculations  |
| `riskService.js`               | Risk           | Risk profiling and assessment        |
| `savingsService.js`            | Savings        | Savings accounts CRUD                |
| `sessionLifecycleService.js`   | Auth           | Session management, heartbeat        |
| `spousePermissionService.js`   | Profile        | Spouse account linking               |
| `taxInfoService.js`            | Tax            | Tax information display              |
| `taxSettingsService.js`        | Admin          | Tax configuration management         |
| `userProfileService.js`        | Profile        | User profile CRUD                    |

---

## 10. Frontend Components (Shared/ directory)

**Path:** `resources/js/components/Shared/`

All 16 shared components, listed with their purpose and key implementation details:

### 10.1 CurrencyInputField.vue
- v-model number input with `GBP` prefix
- Accepts numeric value, emits parsed number on change
- Handles formatting on blur, raw input on focus

### 10.2 RiskBadge.vue
- Coloured pill badge displaying risk level
- Imports `RISK_TAILWIND_CLASSES`, `RISK_DISPLAY_NAMES`, `RISK_LEGACY_MAP` from `designSystem.js`
- Normalises legacy values (`cautious`/`balanced`/`adventurous`) to the five-level system
- Falls back to gray styling for unknown levels

### 10.3 ConfidenceBadge.vue
- Three-colour badge for AI extraction confidence levels
- Green = high confidence, Blue = medium, Red = low
- Used in document upload/extraction workflows

### 10.4 RiskLevelSelector.vue
- Full allocation visualisation with expected returns for each risk level
- Shows the five risk levels (Low, Lower-Medium, Medium, Upper-Medium, High)
- Displays typical asset allocation breakdown per level
- Emits selected risk level to parent

### 10.5 ViewToggle.vue
- Button group component for switching between display modes
- Accepts array of options, emits selected value
- Used across dashboards for toggling between chart/table/card views

### 10.6 PostcodeLookup.vue
- Simple text input for UK postcodes
- API integration not active yet (placeholder for future postcode lookup service)
- Emits entered postcode string

### 10.7 ProcessingState.vue
- Animated spinner with step progression display
- Shows current processing step with label text
- Used during document upload, AI extraction, and calculation workflows
- Configurable step labels and animation timing

### 10.8 ISAAllowanceSummary.vue
- Combined ISA allowance tracker across all ISA types
- Hardcodes `ISA_ALLOWANCE: 20000` (known issue -- should import from `taxConfig.js`)
- Shows used/remaining allowance with progress bar

### 10.9 InfoGuideButton.vue
- Floating action button that opens the contextual info guide panel
- Teleported to body to avoid z-index issues
- Visibility controlled by route-level module detection

### 10.10 InfoGuidePanel.vue
- Slide-out panel showing contextual help and requirements
- Content driven by the `infoGuide` Vuex store module
- Fetches module-specific requirements on route change

### 10.11 ProfileCompletenessAlert.vue
- Three severity levels (low/medium/high) based on profile completion percentage
- Includes progress bar showing completion percentage
- Links to relevant sections that need attention
- Dismissible per session

### 10.12 DocumentUploadModal.vue
- Three-step upload workflow: (1) file upload, (2) AI processing, (3) review extracted data
- Uses `ProcessingState` component during step 2
- Integrates with `documentService.js` for upload and extraction
- Emits extracted data for parent component to consume

### 10.13 SpouseSuccessModal.vue
- Confirmation modal shown after spouse account creation or linking
- Displays success message with spouse name
- Provides next-step guidance

### 10.14 OccupationAutocomplete.vue
- Typeahead input for occupation selection
- Integrates with `occupationService.js` for search
- Emits selected occupation object

### 10.15 CountrySelector.vue
- Dropdown for country selection
- Pre-populated with common countries, UK as default
- Used in property and profile forms

### 10.16 UploadDropZone.vue
- Drag-and-drop file upload area
- Accepts configurable file types and size limits
- Visual feedback on drag hover
- Emits selected file(s) to parent

---

## 11. Frontend Routing (router/index.js)

**File:** `resources/js/router/index.js` (835 lines)

### 11.1 Router Configuration

```javascript
createWebHistory(import.meta.env.VITE_ROUTER_BASE || '/')
```

- Development: base path `/`
- Production fynla.org: base path `/`
- Production csjones.co/fynla: base path `/fynla/`

Scroll behaviour: restores saved position on back, smooth-scrolls to hash targets, otherwise scrolls to top.

### 11.2 Route Meta Flags

| Flag            | Type    | Purpose                                                  |
|-----------------|---------|----------------------------------------------------------|
| `public`        | boolean | Accessible without authentication                        |
| `requiresAuth`  | boolean | Redirects to Login if not authenticated                  |
| `requiresGuest` | boolean | Redirects to Dashboard if already authenticated          |
| `requiresAdmin` | boolean | Redirects to Dashboard if not admin                      |
| `hideNavbar`    | boolean | Hides the main navigation bar (used for onboarding)     |
| `previewMode`   | boolean | Marks route as preview-accessible without real auth      |
| `devOnly`       | boolean | Blocked in production via `beforeEnter` guard            |
| `breadcrumb`    | array   | Array of `{label, path}` objects for breadcrumb display  |

### 11.3 Route Groups

**Public Routes (7):**

| Path               | Name            | Component         |
|--------------------|-----------------|--------------------|
| `/`                | Home            | LandingPage        |
| `/calculators`     | Calculators     | CalculatorsPage    |
| `/learning-centre` | LearningCentre  | LearningCentre     |
| `/security`        | Security        | SecurityPage       |
| `/about`           | About           | AboutPage          |
| `/pricing`         | Pricing         | PricingPage        |
| `/sitemap`         | Sitemap         | SitemapPage        |

**Auth Routes (3):**

| Path          | Name        | Meta             |
|---------------|-------------|------------------|
| `/login`      | Login       | requiresGuest    |
| `/register`   | Register    | requiresGuest    |
| `/onboarding` | Onboarding  | requiresAuth, hideNavbar |

**Authenticated App Routes (~30):**

| Path                              | Name                        | Notes                        |
|-----------------------------------|-----------------------------|------------------------------|
| `/checkout`                       | Checkout                    | Payment page                 |
| `/dashboard`                      | Dashboard                   | Main hub                     |
| `/settings`                       | Settings                    | Account settings             |
| `/settings/security`              | SecuritySettings            | MFA, password, sessions      |
| `/settings/privacy`               | PrivacySettings             | GDPR, data export            |
| `/settings/assumptions`           | AssumptionsSettings         | Planning assumptions         |
| `/valuable-info`                  | ValuableInfo                | Important documents          |
| `/profile`                        | UserProfile                 | Personal details             |
| `/net-worth`                      | NetWorthDashboard           | Parent with child routes     |
| `/net-worth/wealth-summary`       | NetWorthWealthSummary       | Default child                |
| `/net-worth/retirement`           | NetWorthRetirement          | Pension list                 |
| `/net-worth/property`             | NetWorthProperty            | Property list                |
| `/net-worth/investments`          | NetWorthInvestments         | Investment list              |
| `/net-worth/investment-detail`    | InvestmentDetail            | Projections                  |
| `/net-worth/tax-efficiency`       | TaxEfficiencyDetail         | Tax wrapper analysis         |
| `/net-worth/holdings-detail`      | HoldingsDetail              | Fund holdings                |
| `/net-worth/fees-detail`          | FeesDetail                  | Fee analysis                 |
| `/net-worth/strategy-detail`      | StrategyDetail              | Investment strategy          |
| `/net-worth/cash`                 | NetWorthCash                | Cash overview                |
| `/net-worth/business`             | NetWorthBusiness            | Business interests           |
| `/net-worth/chattels`             | NetWorthChattels            | Chattels list                |
| `/net-worth/joint-history`        | JointAccountHistory         | Joint ownership history      |
| `/pension/:type/:id`              | PensionDetail               | Individual pension view      |
| `/protection`                     | Protection                  | Protection dashboard         |
| `/protection/policy/:policyType/:id` | PolicyDetail             | Individual policy view       |
| `/protection-plan`                | ComprehensiveProtectionPlan | Full protection analysis     |
| `/savings`                        | Savings                     | Savings dashboard            |
| `/savings/account/:id`            | SavingsAccountDetail        | Individual account view      |
| `/goals`                          | Goals                       | Goals dashboard              |
| `/investment`                     | (redirect)                  | Redirects to `/net-worth/investments` |
| `/risk-profile`                   | RiskProfile                 | Risk assessment              |
| `/risk-profile/levels`            | RiskLevelsExplained         | Risk level education         |
| `/risk-profile/factor/:factor`    | RiskFactorDetail            | Individual risk factor       |
| `/estate`                         | Estate                      | Estate dashboard             |
| `/estate-plan`                    | ComprehensiveEstatePlan     | Full estate analysis         |
| `/trusts`                         | Trusts                      | Trusts dashboard             |
| `/trusts/:id`                     | TrustDetail                 | Individual trust view        |
| `/actions`                        | Actions                     | Recommendations dashboard    |
| `/holistic-plan`                  | HolisticPlan                | Cross-module plan            |
| `/plans`                          | Plans                       | Plans dashboard              |
| `/plans/investment-savings`       | InvestmentSavingsPlan       | Investment & savings plan    |
| `/uk-taxes`                       | UKTaxes                     | requiresAdmin                |
| `/admin`                          | AdminPanel                  | requiresAdmin                |
| `/version`                        | Version                     | No auth required             |
| `/help`                           | Help                        | No auth required             |
| `/debug-env`                      | DebugEnv                    | requiresAdmin + devOnly      |

**Preview Mirror Routes (8):**

| Path                              | Mirrors                     |
|-----------------------------------|-----------------------------|
| `/preview`                        | Dashboard                   |
| `/preview/net-worth`              | NetWorthDashboard (+ children)|
| `/preview/protection`             | ProtectionDashboard         |
| `/preview/savings`                | SavingsDashboard            |
| `/preview/goals`                  | GoalsDashboard              |
| `/preview/investment`             | (redirect to investments)   |
| `/preview/retirement`             | (redirect to retirement)    |
| `/preview/estate`                 | EstateDashboard             |
| `/preview/profile`                | UserProfile                 |

All preview routes use `meta: { public: true, previewMode: true }`.

### 11.4 Navigation Guards

**beforeEach guard logic (in order):**

1. **Preview route handling:** If route is a preview route and user is authenticated, redirect to the authenticated equivalent. If persona query param present, dispatch `preview/enterPreviewMode`.
2. **Auth check:** If `requiresAuth` and not authenticated and not in preview mode, redirect to Login.
3. **Guest check:** If `requiresGuest` and authenticated and not preview, redirect to Dashboard.
4. **Admin check:** If `requiresAdmin` and not admin, redirect to Dashboard.

**afterEach hook:**

Maps the current route path to a module identifier using a prefix-match table, then dispatches `infoGuide/fetchRequirements` with the detected module. Skips public/auth pages. Module mapping:

| Path prefix              | Module identifier |
|--------------------------|-------------------|
| `/protection`            | protection        |
| `/savings`               | savings           |
| `/goals`                 | goals             |
| `/investment`, `/net-worth/investments` | investment |
| `/net-worth/retirement`, `/retirement`, `/pension` | retirement |
| `/estate`, `/trusts`     | estate            |
| `/net-worth`             | net_worth         |
| `/dashboard`, `/preview`, `/profile` | dashboard |

---

## 12. Cross-Module Integration

### 12.1 PHP Traits

#### Auditable (189 lines)

**File:** `app/Traits/Auditable.php`

Automatically logs model lifecycle events (created, updated, deleted) to the `audit_logs` table via Eloquent model hooks.

**Boot hooks:** `bootAuditable()` registers `created`, `updated`, and `deleted` event listeners.

**Skip conditions:**
- `shouldAudit()` returns false if running unit tests (unless `auditInTests()` overridden)
- `shouldAudit()` returns false if authenticated user is a preview user (`is_preview_user = true`)

**Field filtering:**
- If model defines `$auditableFields` array: only those fields are logged
- If model defines `$auditExcludeFields` array: those are excluded (merged with defaults)
- Default exclusions: `created_at`, `updated_at`, `remember_token`, `password`
- If neither defined: all fields except defaults are audited

**Models using Auditable (16):**
SavingsAccount, LifeEvent, Goal, Holding, InvestmentAccount, FamilyMember, DCPension, Mortgage, BusinessInterest, Chattel, Property, LifeInsurancePolicy, DBPension, CriticalIllnessPolicy, StatePension, IncomeProtectionPolicy

**Public method:**
- `auditLogs()` -- returns query builder for this model instance's audit history, ordered by most recent

---

#### CalculatesOwnershipShare (169 lines)

**File:** `app/Traits/CalculatesOwnershipShare.php`

Calculates a user's proportional share of a jointly-owned asset based on the single-record ownership model.

**Value field resolution order:** `current_value` -> `current_balance` -> `current_valuation` -> `outstanding_balance` -> 0

**Methods:**

| Method                     | Purpose                                                |
|----------------------------|--------------------------------------------------------|
| `calculateUserShare`       | Returns user's monetary share based on ownership type  |
| `calculateUserMortgageShare` | Same logic but for mortgage liabilities              |
| `userOwnsAsset`            | Boolean: user is primary or joint owner                |
| `isPrimaryOwner`           | Boolean: user is the `user_id` owner                   |
| `isSharedOwnership`        | Boolean: ownership type is `joint` or `tenants_in_common` |
| `getFullValue`             | Returns the full asset value regardless of ownership   |

**Business interest exception:** For business interests (detected by presence of `current_valuation` AND `business_name` fields), `ownership_percentage` applies even for `individual` ownership because it represents shareholding percentage, not co-ownership.

**Standard logic (non-business assets):**
- `individual` or `trust`: 100% to `user_id` owner, 0% to anyone else
- `joint` or `tenants_in_common`: `ownership_percentage` to `user_id`, `(100 - ownership_percentage)` to `joint_owner_id`
- If `ownership_percentage` is 100 (indicating no split was specified), defaults to 50/50

**Used by 12 services/controllers:**
SavingsController, InvestmentController, BusinessInterestController, PropertyController, MortgageController, ComprehensiveEstatePlanService, NetWorthService, IHTFormattingService, PropertyService, EstateAssetAggregatorService, PersonalAccountsService, CrossModuleAssetAggregator

---

#### StructuredLogging (165 lines)

**File:** `app/Traits/StructuredLogging.php`

Provides consistent structured logging with automatic context enrichment.

**Log methods:**

| Method               | Level   | Extra Context                                |
|----------------------|---------|----------------------------------------------|
| `logInfo`            | info    | Standard context                             |
| `logWarning`         | warning | Standard context                             |
| `logError`           | error   | Standard context + optional exception details|
| `logDebug`           | debug   | Standard context                             |
| `logAuth`            | info    | action, user_id, IP, user agent              |
| `logApiRequest`      | info    | HTTP method, endpoint                        |
| `logModelOperation`  | info    | operation, model_type, model_id              |
| `logCalculation`     | debug   | calculation name, inputs, result             |

**Standard context (added to every log entry):**
- `class`: the calling class name (`static::class`)
- `timestamp`: ISO 8601
- `user_id`: from `auth()->id()` (if authenticated and not already in context)
- `request_id`: from `X-Request-ID` header (if present)

**Exception logging:** `logError` with a `$exception` parameter captures: class, message, code, file, line.

---

#### PolicyCRUDTrait (136 lines)

**File:** `app/Traits/PolicyCRUDTrait.php`

Standardised CRUD operations for Protection module policies.

**Methods:**

| Method          | HTTP | Behaviour                                              |
|-----------------|------|--------------------------------------------------------|
| `storePolicy`   | POST | Creates record, invalidates protection cache, returns 201 |
| `updatePolicy`  | PUT  | Finds by user_id + id, updates, invalidates cache, returns 200 |
| `destroyPolicy` | DELETE | Finds by user_id + id, deletes, invalidates cache, returns 200 |

All methods catch `ModelNotFoundException` (404) and general `Exception` (500).

**Used by:** `ProtectionController` only. Expects `$this->protectionAgent` to be available for cache invalidation.

---

#### FormatsCurrency (81 lines)

**File:** `app/Traits/FormatsCurrency.php`

Server-side currency formatting for use in services generating human-readable output.

| Method                   | Output Example  | Description                    |
|--------------------------|-----------------|--------------------------------|
| `formatCurrency`         | `"£1,234"`      | No decimals                    |
| `formatCurrencyWithPence`| `"£1,234.56"`   | 2 decimal places               |
| `formatCurrencyPrecise`  | `"£1,234.567"`  | Custom decimal places          |
| `formatCurrencyCompact`  | `"£1.2M"`       | M for millions, K for thousands|
| `formatPercentage`       | `"5.00%"`       | Optional decimal-to-percent conversion |

**Used by:** RetirementStrategyService, PortfolioStrategyService, ComprehensiveProtectionPlanService

---

#### HasJointOwnership (57 lines)

**File:** `app/Traits/HasJointOwnership.php`

Query scopes and helper methods for models that support joint ownership.

**Scopes:**

| Scope              | Query                                              |
|--------------------|------------------------------------------------------|
| `forUserOrJoint`   | `WHERE user_id = ? OR joint_owner_id = ?`           |
| `forUser`          | `WHERE user_id = ?`                                  |
| `forJointOwner`    | `WHERE joint_owner_id = ?`                           |

**Instance methods:**

| Method          | Returns | Logic                                    |
|-----------------|---------|------------------------------------------|
| `isOwnedBy`    | bool    | user_id === userId OR joint_owner_id === userId |
| `hasJointOwner`| bool    | joint_owner_id !== null                   |

**Models using HasJointOwnership (9):**
Property, SavingsAccount, InvestmentAccount, Chattel, BusinessInterest, Goal, Mortgage, LifeEvent, Estate\Liability

---

### 12.2 Middleware Stack (7 middleware)

#### Authentication & Authorization Middleware

The following custom middleware is registered in `app/Http/Kernel.php`. See **auth.md Section 5** for full documentation of each:

| Middleware | Alias | Purpose |
|-----------|-------|---------|
| `IsAdmin` | `admin` | Requires `is_admin = true` |
| `HasRole` | `role` | Requires specified role |
| `HasPermission` | `permission` | Requires specified permission |
| `EnsureMFAVerified` | `mfa.verified` | Requires completed MFA verification |
| `SanitizeInput` | — | XSS/injection prevention on all input |
| `CheckSubscription` | — | Requires active subscription/trial (see **PaymentSubscription.md Section 13**) |

#### PreviewWriteInterceptor

`app/Http/Middleware/PreviewWriteInterceptor.php` (166 lines) — Intercepts all write operations (POST/PUT/PATCH/DELETE) from preview users and returns fake success responses without touching the database. Resolves user from Bearer token before `auth:sanctum`. See **auth.md Section 14.3** for full documentation of excluded routes, excluded patterns, and the fake response format.

---

### 12.3 API Resources (10 total)

**Path:** `app/Http/Resources/`

All resources follow a consistent pattern:
- `whenLoaded()` for conditional relationship inclusion (prevents N+1 and handles null)
- ISO 8601 timestamps via `->toIso8601String()`
- `links.self` for HATEOAS-style self-references
- Date fields use `->toDateString()` (YYYY-MM-DD format)

| Resource                    | Model                | Key Features                                           |
|-----------------------------|----------------------|--------------------------------------------------------|
| `UserResource`              | User                 | Core user fields, `is_preview_user`, `is_admin`        |
| `PropertyResource`          | Property             | Nested `MortgageResource` collection, joint_owner      |
| `MortgageResource`          | Mortgage             | Outstanding balance, rate, type                        |
| `SavingsAccountResource`    | SavingsAccount       | Balance, interest rate, ISA type                       |
| `InvestmentAccountResource` | InvestmentAccount    | Value, provider, risk level, nested holdings           |
| `HoldingResource`           | Holding              | Fund name, units, value, allocation percentage         |
| `ChattelResource`           | Chattel              | **Inline `calculateUserShare`** (see known issues)     |
| `BusinessInterestResource`  | BusinessInterest     | Valuation, business type, ownership percentage         |
| `GoalResource`              | Goal                 | Target amount, current progress, target date           |
| `GoalContributionResource`  | GoalContribution     | Amount, date, contribution type                        |

**ChattelResource inline ownership calculation:** The `ChattelResource` contains a private `calculateUserShare()` method (lines 91-120) that duplicates the logic in `CalculatesOwnershipShare` trait. This is a known technical debt item -- see Section 17 (Known Issues).

---

## 13. Profile Completeness

N/A -- Profile completeness logic is handled within the `ProfileCompletenessAlert` shared component and the user profile module, not as a standalone shared infrastructure feature.

---

## 14. Seeder Data

The shared infrastructure layer does not have its own seeders. The audit_logs table is populated organically through application usage. Tax configuration data is seeded via `TaxConfigurationSeeder` as part of the Tax module. Full seeder documentation (DatabaseSeeder orchestration, AdminUserSeeder, DemoUserSeeder, HouseholdSeeder, TestUsersSeeder, ComprehensiveDemoDataSeeder) is in Section 16.9. Test factories (22 files) are listed in Section 16.10.

---

## 15. API Routing

N/A -- Shared infrastructure does not define its own API routes. The middleware classes are registered in the HTTP kernel and applied to routes defined by each module. The `PreviewWriteInterceptor` is applied globally to all API routes.

---

## 16. Key Constants

### 16.1 designSystem.js (435 lines)

**File:** `resources/js/constants/designSystem.js`

This file is the single source of truth for all visual design tokens in the application. Every component that needs colours, spacing, or chart configuration must import from here.

#### Colour System

**Primary Brand (Trust Blue):**

| Token | Hex       | Usage                          |
|-------|-----------|--------------------------------|
| 500   | `#3B82F6` | Accent blue, links             |
| 600   | `#1257A0` | Main brand -- buttons, active  |
| 700   | `#0E3A66` | Hover states                   |
| 800   | `#0B2C4F` | Active/pressed states          |

**Semantic Colours:**

| Category | Key Hex   | Critical Rule                                  |
|----------|-----------|------------------------------------------------|
| SUCCESS  | `#15803D` | Green for positive/success                     |
| ERROR    | `#EF4444` | Red for errors/negative                        |
| WARNING  | `#3B82F6` | **BLUE, not amber/orange** (project rule)      |
| INFO     | `#0EA5E9` | Sky blue for informational                     |

**Chart Colour Arrays:**

| Array           | Count | Purpose                                  |
|-----------------|-------|------------------------------------------|
| `CHART_COLORS`  | 8     | Ordered sequence for multi-series charts |
| `ASSET_COLORS`  | 6     | Named map: pensions, property, investments, cash, business, chattels |
| `SPENDING_COLORS`| 16   | Extended palette for expenditure breakdown |

**Risk Level System:**

| Level          | Colour Theme |
|----------------|--------------|
| `low`          | Yellow       |
| `lower_medium` | Pink         |
| `medium`       | Green        |
| `upper_medium` | Teal         |
| `high`         | Blue         |

Exports include `RISK_COLORS`, `RISK_TAILWIND_CLASSES`, `RISK_DISPLAY_NAMES`, `RISK_ABBREVIATED_LABELS`, `RISK_DESCRIPTIONS`, `RISK_LEGACY_MAP`, plus helper functions `getRiskClasses()`, `getRiskDisplayName()`, and `normalizeRiskLevel()`. See **risk.md Section 16** for the complete risk design system including hex values, Tailwind classes, and helper functions.

**Text and Background colours:**

| Token Category  | Key Values                                      |
|-----------------|-------------------------------------------------|
| `TEXT_COLORS`   | primary (#111827), secondary (#374151), muted (#6B7280), disabled (#D1D5DB) |
| `BG_COLORS`     | page (#F9FAFB), card (#FFFFFF), subtle (#F3F4F6), overlay (rgba) |
| `BORDER_COLORS` | default (#E5E7EB), hover (#D1D5DB), focus (primary 600), error, success |

**ApexCharts defaults (`CHART_DEFAULTS`):**
- Font: Inter, system-ui, sans-serif
- Toolbar: hidden
- Zoom: disabled
- Grid: dashed lines using `BORDER_COLORS.default`
- Axis labels: muted text colour, 11px

**Utility functions:**
- `getColorByThreshold(value, {success: 80, warning: 60})` -- Returns green/blue/red based on percentage
- `getValueColor(value)` -- Returns green for positive, red for negative, muted for zero

**Spacing and Layout:**

```javascript
SPACING:       { xs: '0.25rem', sm: '0.5rem', md: '1rem', lg: '1.5rem', xl: '2rem', '2xl': '3rem' }
BORDER_RADIUS: { none: '0', sm: '0.25rem', md: '0.375rem', button: '0.5rem', card: '0.75rem', lg: '1rem', xl: '1.5rem', full: '9999px' }
ANIMATION:     { duration: { fast: '150ms', default: '200ms', slow: '300ms', slower: '500ms' },
                 easing: { easeOut: 'cubic-bezier(0.4,0,0.2,1)', easeIn: 'cubic-bezier(0.4,0,1,1)', bounce: 'cubic-bezier(0.34,1.56,0.64,1)' } }
```

---

### 16.2 taxConfig.js (114 lines)

**File:** `resources/js/constants/taxConfig.js`

**IMPORTANT:** These are FALLBACK values only. The authoritative source is `TaxConfigService` on the backend, which loads from the database. Components should prefer API data, then Vuex store values, and only fall back to these constants when API data is unavailable.

**Tax Year:** 2025/26 (6 April 2025 - 5 April 2026)

| Category            | Constant                              | Value       |
|---------------------|---------------------------------------|-------------|
| **ISA**             | `ISA_ANNUAL_ALLOWANCE`                | 20,000      |
|                     | `LIFETIME_ISA_ALLOWANCE`              | 4,000       |
|                     | `JUNIOR_ISA_ALLOWANCE`                | 9,000       |
| **Pension**         | `PENSION_ANNUAL_ALLOWANCE`            | 60,000      |
|                     | `MONEY_PURCHASE_ANNUAL_ALLOWANCE`     | 10,000      |
|                     | `PENSION_LIFETIME_ALLOWANCE`          | null (abolished) |
| **Income Tax**      | `PERSONAL_ALLOWANCE`                  | 12,570      |
|                     | `PERSONAL_ALLOWANCE_TAPER_THRESHOLD`  | 100,000     |
|                     | `HIGHER_RATE_THRESHOLD`               | 50,270      |
|                     | `ADDITIONAL_RATE_THRESHOLD`           | 125,140     |
| **CGT**             | `CGT_ANNUAL_ALLOWANCE`                | 3,000       |
| **IHT**             | `IHT_NIL_RATE_BAND`                   | 325,000     |
|                     | `IHT_RESIDENCE_NIL_RATE_BAND`         | 175,000     |
|                     | `IHT_RNRB_TAPER_THRESHOLD`           | 2,000,000   |
|                     | `IHT_STANDARD_RATE`                   | 0.40        |
|                     | `IHT_REDUCED_RATE`                    | 0.36        |
| **Other**           | `DIVIDEND_ALLOWANCE`                  | 500         |
|                     | `SAVINGS_ALLOWANCE_BASIC`             | 1,000       |
|                     | `SAVINGS_ALLOWANCE_HIGHER`            | 500         |
|                     | `MARRIAGE_ALLOWANCE`                  | 1,260       |
|                     | `ANNUAL_GIFT_EXEMPTION`               | 3,000       |
|                     | `SMALL_GIFT_EXEMPTION`                | 250         |

**Legacy export:** A default `TAX_CONFIG` object re-exports all constants for backward compatibility. New code should use named imports.

---

### 16.3 Backend Constants (app/Constants/)

Three PHP constant classes centralise fallback values and validation limits used across all modules.

#### EstateDefaults (`app/Constants/EstateDefaults.php`)

Estimated values for estate planning calculations when user has not yet provided actual data. All values are conservative UK averages.

| Constant                      | Value       | Purpose                                               |
|-------------------------------|-------------|-------------------------------------------------------|
| `ESTIMATED_PROPERTY_VALUE`    | 300,000     | ONS average when user indicates property but no value  |
| `ESTIMATED_INVESTMENT_VALUE`  | 100,000     | Conservative default for investment portfolios         |
| `ESTIMATED_SAVINGS_VALUE`     | 50,000      | Conservative default for savings                       |
| `ESTIMATED_BUSINESS_VALUE`    | 200,000     | Conservative default for business interests            |
| `RNRB_TAPER_THRESHOLD`       | 2,000,000   | Estate value above which RNRB tapers (loses £1 per £2) |
| `TRUST_SUGGESTION_THRESHOLD`  | 2,000,000   | Estate value above which trust structures suggested    |
| `COMBINED_NRB_THRESHOLD`     | 650,000     | Maximum transferable NRB between spouses (2 x £325k)  |
| `COMBINED_RNRB_THRESHOLD`    | 350,000     | Maximum transferable RNRB between spouses (2 x £175k) |
| `DEFAULT_LIFE_EXPECTANCY`    | 85          | Conservative UK actuarial estimate                     |
| `DEFAULT_CURRENT_AGE`        | 50          | Fallback when user age unknown                         |

Last reviewed: 4 February 2026. Class is `final` (no subclassing).

#### TaxDefaults (`app/Constants/TaxDefaults.php`)

Centralised fallback values for UK tax calculations. Used only when `TaxConfigService` cannot retrieve values from the database. Should be reviewed annually.

**Inheritance Tax (2025/26):**

| Constant                 | Value    | Note                                       |
|--------------------------|----------|--------------------------------------------|
| `NRB`                    | 325,000  | Nil Rate Band (frozen until April 2028)    |
| `RNRB`                   | 175,000  | Residence Nil Rate Band (frozen)           |
| `IHT_RATE`               | 0.40     | Standard IHT rate                          |
| `IHT_CHARITABLE_RATE`    | 0.36     | Reduced rate for 10%+ charitable legacies  |
| `ANNUAL_GIFT_EXEMPTION`  | 3,000    | Per donor annual gift exemption            |
| `SMALL_GIFT_EXEMPTION`   | 250      | Per recipient small gifts                  |
| `CLT_RATE`               | 0.20     | Chargeable Lifetime Transfer rate          |

**ISA Allowances (2025/26):**

| Constant           | Value   |
|--------------------|---------|
| `ISA_ALLOWANCE`    | 20,000  |
| `JISA_ALLOWANCE`   | 9,000   |
| `LISA_ALLOWANCE`   | 4,000   |

**Pension Allowances (2025/26):**

| Constant                      | Value    |
|-------------------------------|----------|
| `PENSION_ANNUAL_ALLOWANCE`    | 60,000   |
| `PENSION_TAPER_THRESHOLD`     | 260,000  |
| `PENSION_MINIMUM_ALLOWANCE`   | 10,000   |
| `MPAA`                        | 10,000   |

**Income Tax (2025/26):**

| Constant                        | Value    |
|---------------------------------|----------|
| `PERSONAL_ALLOWANCE`            | 12,570   |
| `BASIC_RATE_BAND`               | 37,700   |
| `HIGHER_RATE_THRESHOLD`         | 50,270   |
| `ADDITIONAL_RATE_THRESHOLD`     | 125,140  |
| `PERSONAL_ALLOWANCE_TAPER`      | 100,000  |

**Capital Gains Tax (2025/26):**

| Constant                       | Value  |
|--------------------------------|--------|
| `CGT_ANNUAL_EXEMPT`            | 3,000  |
| `CGT_BASIC_RATE_PROPERTY`      | 0.18   |
| `CGT_HIGHER_RATE_PROPERTY`     | 0.24   |
| `CGT_BASIC_RATE`               | 0.10   |
| `CGT_HIGHER_RATE`              | 0.20   |

**Other:**

| Constant                   | Value   | Purpose                               |
|----------------------------|---------|---------------------------------------|
| `HICBC_THRESHOLD`          | 60,000  | High Income Child Benefit Charge      |
| `HICBC_FULL_WITHDRAWAL`   | 80,000  | Full Child Benefit withdrawal         |
| `DEFAULT_GROWTH_RATE`      | 0.05    | Pension projection assumed growth     |
| `SAFE_WITHDRAWAL_RATE`     | 0.04    | Retirement income withdrawal rate     |
| `CACHE_TTL_STANDARD`       | 3,600   | 1-hour cache for analysis data        |
| `CACHE_TTL_SIMULATION`     | 86,400  | 24-hour cache for Monte Carlo results |

Last verified: 4 February 2026 (2025/26 tax year). Class is `final`.

#### ValidationLimits (`app/Constants/ValidationLimits.php`)

Maximum and minimum values used in validation rules across all modules. Not `final` -- includes static helper methods.

**Currency limits:**

| Constant              | Value          |
|-----------------------|----------------|
| `MAX_CURRENCY_VALUE`  | 999,999,999.99 |
| `MIN_CURRENCY_VALUE`  | 0              |
| `MAX_PROPERTY_VALUE`  | 50,000,000     |
| `MIN_PROPERTY_VALUE`  | 0              |
| `MAX_MORTGAGE_VALUE`  | 10,000,000     |
| `MAX_INVESTMENT_VALUE`| 100,000,000    |
| `MAX_HOLDING_VALUE`   | 50,000,000     |
| `MAX_HOLDING_UNITS`   | 999,999,999    |

**Percentage and rate limits:**

| Constant              | Value |
|-----------------------|-------|
| `MAX_PERCENTAGE`      | 100   |
| `MIN_PERCENTAGE`      | 0     |
| `MAX_INTEREST_RATE`   | 50    |
| `MAX_GROWTH_RATE`     | 25    |

**Age and year limits:**

| Constant                  | Value |
|---------------------------|-------|
| `MIN_AGE` / `MAX_AGE`    | 0 / 125 |
| `MIN_RETIREMENT_AGE`     | 50    |
| `MAX_RETIREMENT_AGE`     | 100   |
| `MIN_POLICY_TERM_YEARS`  | 1     |
| `MAX_POLICY_TERM_YEARS`  | 50    |
| `MAX_YEARS_TO_RETIREMENT`| 60    |

**String and count limits:**

| Constant                    | Value |
|-----------------------------|-------|
| `MAX_NAME_LENGTH`           | 255   |
| `MAX_DESCRIPTION_LENGTH`    | 1,000 |
| `MAX_NOTES_LENGTH`          | 5,000 |
| `MAX_PROVIDER_NAME_LENGTH`  | 255   |
| `MAX_DEPENDENTS`            | 20    |
| `MAX_POLICIES_PER_TYPE`     | 50    |
| `MAX_ACCOUNTS`              | 100   |
| `MAX_HOLDINGS_PER_ACCOUNT`  | 500   |

**Static helper methods:**

| Method                      | Purpose                                                          |
|-----------------------------|------------------------------------------------------------------|
| `getISALimit(?TaxConfigService)` | Returns ISA limit from `TaxConfigService`, falls back to 20,000 |
| `getPensionAnnualAllowance(?TaxConfigService)` | Returns pension AA from service, falls back to 60,000 |
| `currencyRules(bool $required)` | Returns validation rule string (e.g. `"nullable|numeric|min:0|max:999999999.99"`) |
| `percentageRules(bool $required)` | Returns validation rule string for 0-100 percentage fields |

---

### 16.4 Custom Casts (app/Casts/)

Two custom Eloquent casts provide transparent encryption at rest for sensitive financial data.

#### EncryptedDecimal (`app/Casts/EncryptedDecimal.php`)

Encrypts decimal/float values before database storage and decrypts on retrieval. Uses Laravel's built-in AES-256-CBC encryption via `Crypt::encryptString()`.

**`get()` behaviour:**
- Returns `null` for null or empty values
- Decrypts string, casts to `float`
- **Backwards compatibility:** If decryption fails and the value is numeric (pre-encryption data), casts the raw value to float
- Logs a warning on decryption failure for non-numeric values

**`set()` behaviour:**
- Returns `null` for null values
- Converts to string with `sprintf('%.10f')`, trims trailing zeros
- Encrypts the cleaned string

#### EncryptedString (`app/Casts/EncryptedString.php`)

Encrypts string values before database storage and decrypts on retrieval. Same AES-256-CBC encryption.

**`get()` behaviour:**
- Returns `null` for null or empty values
- Decrypts string, returns as-is
- **Backwards compatibility:** If decryption fails, returns the original value (assumes pre-encryption data)
- Logs a warning on decryption failure

**`set()` behaviour:**
- Returns `null` for null or empty values
- Encrypts the string value

Both casts are applied to model `$casts` arrays (e.g. `'national_insurance_number' => EncryptedString::class`).

---

### 16.5 Custom Exceptions (app/Exceptions/)

#### FinancialCalculationException (`app/Exceptions/FinancialCalculationException.php`)

A domain-specific exception class for financial calculation failures, extending PHP's base `Exception`. Carries a `calculationType` string and a `context` array for structured error handling.

**Constructor:** `new FinancialCalculationException($message, $calculationType = 'general', $context = [], $code = 0, $previous = null)`

**Accessors:**
- `getCalculationType()` -- Returns the calculation type string
- `getContext()` -- Returns the context array

**Named static factory methods (9 total):**

| Factory Method              | Calculation Type         | Purpose                                    |
|-----------------------------|-------------------------|--------------------------------------------|
| `missingData`               | `missing_data`          | Required data not available for calculation |
| `invalidInput`              | `invalid_input`         | Invalid field value with reason             |
| `taxConfigError`            | `tax_config_error`      | Tax configuration retrieval failure         |
| `projectionError`           | `projection_error`      | Projection/forecast calculation failure     |
| `ihtCalculationError`       | `iht_calculation`       | Inheritance Tax calculation failure         |
| `pensionCalculationError`   | `pension_calculation`   | Pension calculation failure                 |
| `investmentCalculationError`| `investment_calculation` | Investment calculation failure              |
| `protectionCalculationError`| `protection_calculation` | Protection adequacy calculation failure     |
| `insufficientData`          | `insufficient_data`     | Not enough data to perform calculation      |
| `timeout`                   | `timeout`               | Calculation exceeded time limit             |

#### Exception Handler (`app/Exceptions/Handler.php`)

The global exception handler renders all API exceptions (routes matching `api/*` or expecting JSON) as consistent JSON responses via `JsonResponseHelper`:

| Exception Type            | HTTP Status | Response                     |
|---------------------------|-------------|------------------------------|
| `ValidationException`     | 422         | Validation errors array      |
| `ModelNotFoundException`  | 404         | "Resource not found"         |
| `NotFoundHttpException`   | 404         | "Endpoint not found"         |
| `AuthenticationException` | 401         | "Unauthenticated"            |
| Other (debug mode)        | varies      | Actual exception message     |
| Other (production)        | 500         | "Internal server error"      |

Inputs `current_password`, `password`, `password_confirmation` are never flashed to session on validation exceptions.

---

### 16.6 Backend Traits (app/Traits/ and app/Http/Traits/)

Seven traits provide reusable behaviour across models, services, and controllers. Six are in `app/Traits/`, one is in `app/Http/Traits/`.

#### SanitizedErrorResponse (`app/Http/Traits/SanitizedErrorResponse.php`)

Provides standardised error response formatting for API controllers. Ensures sensitive internal details are never leaked in production responses.

**Methods:**

| Method                      | Purpose                                                           |
|-----------------------------|-------------------------------------------------------------------|
| `errorResponse($exception, $context, $statusCode, $additionalLogContext)` | Logs full error server-side; returns sanitised JSON response. In debug mode, includes exception class, file basename, and line number. In production, returns generic message. |
| `safeErrorResponse($context, $exception, $statusCode)` | Backward-compatible alias for `errorResponse()` (swapped parameter order) |
| `notFoundResponse($resourceType)` | Returns 404 JSON with `"{$resourceType} not found or access denied."` |
| `validationErrorResponse($message, $errors)` | Returns 422 JSON with message and errors array |

**Production response format:**
```json
{
  "success": false,
  "message": "{Context} failed. Please try again or contact support if the problem persists.",
  "debug": null
}
```

**Debug response format:**
```json
{
  "success": false,
  "message": "{Context} failed: {exception message}",
  "debug": { "exception": "ClassName", "file": "filename.php", "line": 42 }
}
```

**Note:** The remaining six traits (`Auditable`, `CalculatesOwnershipShare`, `FormatsCurrency`, `HasJointOwnership`, `PolicyCRUDTrait`, `StructuredLogging`) are documented in detail in Section 12.1 above.

---

### 16.7 Guidance System

The Guidance System is a post-registration onboarding feature that walks new users through key sections of the application step by step. It consists of a Vuex store module, two Vue components, and backend API endpoints for persisting guidance state.

#### guidance.js Vuex Store (`resources/js/store/modules/guidance.js`)

**GUIDANCE_STEPS constant (8 steps):**

| Step ID          | Label                    | Target Selector       | Route          | Icon         |
|------------------|--------------------------|-----------------------|----------------|--------------|
| `personal_info`  | Personal Information     | `#profile-section`    | `/profile`     | user         |
| `family`         | Family Members           | `#family-section`     | `/profile`     | users        |
| `properties`     | Properties               | `#property-card`      | `/net-worth`   | home         |
| `savings`        | Savings                  | `#savings-card`       | `/savings`     | piggy-bank   |
| `investments`    | Investments              | `#investment-card`    | `/investments` | trending-up  |
| `pensions`       | Pensions                 | `#retirement-card`    | `/retirement`  | calendar     |
| `protection`     | Protection               | `#protection-card`    | `/protection`  | shield       |
| `income`         | Income & Expenditure     | `#income-section`     | `/profile`     | pound-sign   |

Each step has an `id`, `label`, `description`, `target` (CSS selector for tooltip positioning), `route` (where user should navigate), and `icon` (for tooltip display).

**Version:** `GUIDANCE_VERSION = '1.0.0'`

**State:**

| Key                | Type     | Default         | Purpose                         |
|--------------------|----------|------------------|---------------------------------|
| `isActive`         | boolean  | `false`          | Whether guidance is actively running |
| `currentStepIndex` | number   | `0`              | Index into GUIDANCE_STEPS       |
| `completedSteps`   | array    | `[]`             | Array of completed step IDs     |
| `skippedSteps`     | array    | `[]`             | Array of skipped step IDs       |
| `version`          | string   | `'1.0.0'`        | Guidance version                |
| `showWelcomeModal` | boolean  | `false`          | Whether to show welcome modal   |
| `initialized`      | boolean  | `false`          | Whether status fetched from API |

**Getters:**

| Getter                  | Returns                                                  |
|-------------------------|----------------------------------------------------------|
| `allSteps`              | Full GUIDANCE_STEPS array                                |
| `currentStep`           | Current step object or null                              |
| `currentStepId`         | Current step's ID string or null                         |
| `progress`              | Object: `{current, total, completed, skipped, handled, percentage}` |
| `isComplete`            | Boolean: all steps completed or skipped                  |
| `isActive`              | Boolean: guidance actively running                       |
| `shouldShowWelcomeModal`| Boolean: welcome modal should display                    |
| `isStepCompleted(id)`   | Boolean: specific step completed                         |
| `isStepSkipped(id)`     | Boolean: specific step skipped                           |
| `isStepHandled(id)`     | Boolean: step completed or skipped                       |
| `remainingSteps`        | Array of unhandled steps                                 |
| `nextUnhandledStep`     | First remaining step or null                             |

**Actions:**

| Action              | Behaviour                                                        |
|---------------------|------------------------------------------------------------------|
| `startGuidance`     | Sets active, resets to step 0, hides welcome modal, saves to API |
| `showWelcomeModal`  | Shows welcome modal                                              |
| `hideWelcomeModal`  | Hides welcome modal                                              |
| `completeStep(id)`  | Marks step complete, advances to next unhandled, saves to API    |
| `skipStep(id)`      | Marks step skipped, advances to next unhandled, saves to API     |
| `goToStep(id)`      | Jumps to specific step by ID, saves to API                       |
| `dismissGuidance`   | Deactivates guidance, hides welcome modal, saves to API          |
| `restartGuidance`   | Resets all state, reactivates, shows welcome modal, saves to API |
| `saveStatus`        | POSTs current state to `/api/user/guidance-status`               |
| `fetchStatus`       | GETs state from `/api/user/guidance-status` (once, idempotent)   |
| `initFromUser(user)`| Initialises state from user data object (called after login/register). Shows welcome modal for new users with active but incomplete guidance. |
| `reset`             | Clears all state (used on logout)                                |

**API endpoints:**
- `POST /api/user/guidance-status` -- Save guidance state
- `GET /api/user/guidance-status` -- Fetch guidance state

Guidance state is non-critical: all API failures are caught and logged but do not throw errors.

#### GuidanceTooltip.vue (`resources/js/components/Guidance/GuidanceTooltip.vue`)

A floating tooltip component that highlights the current guidance step's target element and provides navigation controls.

**Rendering:** Teleported to `<body>` with transition animations (fade + scale). Fixed positioning with z-index 50.

**Positioning logic:**
- Locates the target DOM element using the step's `target` CSS selector
- Calculates available space in all four directions (top, bottom, left, right)
- Prefers placement: bottom > top > right > left
- Falls back to screen centre if target element not found
- Re-positions on window resize, scroll (capture phase), and body resize (via `ResizeObserver`)

**Target highlighting:**
- Adds `.guidance-highlight` CSS class to the current target element
- Highlight effect: pulsing box-shadow (primary colour at 30-40% opacity, 2s infinite animation)
- Target is scrolled into view with `scrollIntoView({ behavior: 'smooth', block: 'center' })`
- Previous highlight is removed when step changes

**Tooltip content:**
- Progress indicator: "Step N of M" with dot indicators (filled for handled steps)
- Step icon (from inline SVG icon components: user, users, home, piggy-bank, trending-up, calendar, shield, pound-sign)
- Step label and description
- Three action buttons: "Done" (completes step), "Skip" (skips step), dismiss (X icon, dismisses entire guide)

**Vuex integration:** Maps `isActive`, `currentStep`, `progress` getters and `completeStep`, `skipStep`, `dismissGuidance` actions.

#### GuidanceWelcomeModal.vue (`resources/js/components/Guidance/GuidanceWelcomeModal.vue`)

A full-screen modal shown to new users before guidance begins.

**When shown:** Controlled by `isOpen` prop from parent component, typically triggered when `shouldShowWelcomeModal` getter is true (new users with active but uncompleted guidance).

**Content:**
- Welcome heading: "Welcome to Fynla!"
- Description explaining the guided setup process
- Preview grid showing first 8 guidance steps with labels
- Time estimate: "Takes about 5-10 minutes"
- Two actions: "Let's Start" (calls `startGuidance`, emits `start`) and "I'll explore on my own" (calls `dismissGuidance`, emits `dismiss`)
- Footer note: "You can restart the setup guide anytime from Settings"

**Preview mode integration:** The guidance system works with preview mode -- preview users can experience the guided onboarding flow. Guidance state is persisted via the same API endpoints (PreviewController methods handle guidance status for preview users).

---

### 16.8 trusts.js Vuex Store (`resources/js/store/modules/trusts.js`)

Manages trust data for the Estate Planning module. Namespaced as `trusts`.

**State:**

| Key              | Type    | Default | Purpose                        |
|------------------|---------|---------|--------------------------------|
| `trusts`         | array   | `[]`    | All trust records              |
| `selectedTrust`  | object  | `null`  | Currently selected trust       |
| `trustAssets`    | object  | `null`  | Assets for selected trust      |
| `loading`        | boolean | `false` | Loading state                  |
| `error`          | string  | `null`  | Error message                  |
| `isPreviewMode`  | boolean | `false` | Preview mode flag              |
| `previewData`    | object  | `null`  | Preview mode data              |

**Getters:**

| Getter                    | Returns                                            |
|---------------------------|----------------------------------------------------|
| `trusts`                  | All trusts                                         |
| `activeTrusts`            | Trusts where `is_active` is true                   |
| `inactiveTrusts`          | Trusts where `is_active` is false                  |
| `relevantPropertyTrusts`  | Trusts where `is_relevant_property_trust` is true  |
| `totalTrustValue`         | Sum of `total_asset_value` or `current_value`      |
| `getTrustById(id)`        | Find trust by ID                                   |

**Actions:**

| Action                     | API Endpoint                              | Behaviour                     |
|----------------------------|-------------------------------------------|-------------------------------|
| `fetchTrusts`              | `GET /api/estate/trusts`                  | Fetches all trusts            |
| `fetchTrustById(id)`       | `GET /api/estate/trusts` (filters client-side) | Fetches all, selects one |
| `fetchTrustAssets(trustId)` | `GET /api/estate/trusts/{id}/assets`     | Fetches trust assets          |
| `createTrust(data)`        | `POST /api/estate/trusts`                 | Creates trust, refreshes list |
| `updateTrust({id, data})`  | `PUT /api/estate/trusts/{id}`             | Updates trust, refreshes list |
| `deleteTrust(id)`          | `DELETE /api/estate/trusts/{id}`          | Deletes trust, refreshes list |
| `calculateTrustIHTImpact(trustId)` | `POST /api/estate/trusts/{id}/calculate-iht-impact` | Calculates IHT impact |
| `fetchUpcomingTaxReturns(monthsAhead)` | `GET /api/estate/trusts/upcoming-tax-returns` | Trust tax return dates |

All actions skip API calls when `isPreviewMode` is true (returns immediately).

**Preview mode mutation:** `SET_PREVIEW_MODE({isPreview, data})` sets preview state and optionally populates trusts from `data.trusts`.

---

### 16.9 Additional Seeders

The seeder system is documented in Section 14 above with a note that shared infrastructure has no dedicated seeders. This sub-section provides additional detail on seeders not covered by individual module documentation.

#### DatabaseSeeder (`database/seeders/DatabaseSeeder.php`)

The main orchestrator that runs all seeders in the correct order. Divided into two phases:

**Phase 1 -- Required Data (always runs):**
1. `TaxConfigurationSeeder` -- Tax rates, allowances, thresholds
2. `TaxProductReferenceSeeder` -- ISA/GIA/Bond tax treatment info
3. `ActuarialLifeTablesSeeder` -- Life expectancy data for estate/retirement projections
4. `AdminUserSeeder` -- Admin account
5. `PreviewUserSeeder` -- Preview personas (young_family, peak_earners, widow, entrepreneur, young_saver, retired_couple)

**Phase 2 -- Optional Data (local/development/staging only):**
6. `HouseholdSeeder` -- Test households for multi-user testing
7. `TestUsersSeeder` -- Additional test user accounts

Also provides a `seedRequiredDataOnly()` method for production use.

#### AdminUserSeeder (`database/seeders/AdminUserSeeder.php`)

Creates or updates a single admin user account using `updateOrCreate`:

| Field              | Value                |
|--------------------|----------------------|
| Email              | `admin@fps.com`      |
| Name               | Admin User           |
| Role               | `admin`              |
| `is_admin`         | `true`               |
| `is_preview_user`  | `true` (skips email verification) |

#### DemoUserSeeder (`database/seeders/DemoUserSeeder.php`)

Creates a basic demo user account:

| Field  | Value            |
|--------|------------------|
| Email  | `demo@fps.com`   |
| Name   | Demo User        |

Password is `password`. This user serves as the base for `ComprehensiveDemoDataSeeder`.

#### HouseholdSeeder (`database/seeders/HouseholdSeeder.php`)

Creates two test households using `firstOrCreate`:
- **Smith Family** -- "Test household for development -- married couple with joint assets"
- **Jones Family** -- "Test household for development -- second family"

Used as a prerequisite for `TestUsersSeeder`.

#### TestUsersSeeder (`database/seeders/TestUsersSeeder.php`)

Creates three test user accounts linked to households:

| User              | Email               | Household    | Role    | Notes                       |
|-------------------|---------------------|--------------|---------|-------------------------------|
| John Smith        | `john@example.com`  | Smith Family | user    | Primary account, married, spouse-linked to Jane |
| Jane Smith        | `jane@example.com`  | Smith Family | user    | Secondary account, spouse-linked to John |
| Sarah Jones       | `sarah@example.com` | Jones Family | user    | Primary account, single      |

Spouses are bidirectionally linked via `spouse_id`. All users include full profile data (DOB, NI number, address, employment details).

#### ComprehensiveDemoDataSeeder (`database/seeders/ComprehensiveDemoDataSeeder.php`)

Populates the demo user (`demo@fps.com`) with realistic data across all modules. Requires `DemoUserSeeder` to have run first.

**Data created:**

| Module       | Records                                                                |
|--------------|------------------------------------------------------------------------|
| Profile      | Updated with DOB, gender, marital status, employment, income (£75k)   |
| Family       | Spouse (Sarah Demo) + 2 children (Emily, Jack)                        |
| Expenditure  | Full monthly expenditure profile (£4,500/month total)                 |
| Protection   | 2 life insurance policies (Aviva term life £250k, L&G whole of life £100k) |
| Savings      | 3 accounts (cash ISA £15k, regular saver £8.5k, joint savings £12k) + 2 goals |
| Investment   | Risk profile + 2 accounts (Vanguard ISA £45k, HL GIA £28k) with holdings + 1 goal |
| Retirement   | Retirement profile + 2 DC pensions + 1 DB pension (NHS) + state pension |
| Property     | 2 properties with mortgages (main residence £425k, buy-to-let £185k)  |
| Estate       | IHT profile + 4 assets + 2 liabilities + 3 gifts (PETs) + mirror will |

---

### 16.10 Test Factories

**Path:** `database/factories/`

22 factory files provide Faker-based test data generation for use in Pest/PHPUnit tests:

| Factory                          | Model                    |
|----------------------------------|--------------------------|
| `BusinessInterestFactory.php`    | BusinessInterest         |
| `CashAccountFactory.php`         | CashAccount              |
| `ChattelFactory.php`             | Chattel                  |
| `CriticalIllnessPolicyFactory.php` | CriticalIllnessPolicy  |
| `DBPensionFactory.php`           | DBPension                |
| `DCPensionFactory.php`           | DCPension                |
| `DisabilityPolicyFactory.php`    | DisabilityPolicy         |
| `FamilyMemberFactory.php`        | FamilyMember             |
| `HouseholdFactory.php`           | Household                |
| `IncomeProtectionPolicyFactory.php` | IncomeProtectionPolicy |
| `LifeInsurancePolicyFactory.php` | LifeInsurancePolicy      |
| `MortgageFactory.php`            | Mortgage                 |
| `PersonalAccountFactory.php`     | PersonalAccount          |
| `PropertyFactory.php`            | Property                 |
| `ProtectionProfileFactory.php`   | ProtectionProfile        |
| `RetirementProfileFactory.php`   | RetirementProfile        |
| `SavingsAccountFactory.php`      | SavingsAccount           |
| `SavingsGoalFactory.php`         | SavingsGoal              |
| `SicknessIllnessPolicyFactory.php` | SicknessIllnessPolicy  |
| `StatePensionFactory.php`        | StatePension             |
| `TaxConfigurationFactory.php`    | TaxConfiguration         |
| `UserFactory.php`                | User                     |

Full testing documentation (test structure, coverage, factory usage patterns) is in `currentState/Testing.md`.

---

### 16.11 Database Schema Dump

**File:** `database/schema/mysql-schema.sql`

A full MySQL schema dump containing `CREATE TABLE` statements for all application tables. This file is generated by Laravel's `schema:dump` command and represents the baseline schema state. It is used by Laravel when running `migrate:fresh` -- instead of replaying all historical migrations from scratch, Laravel loads this schema dump first and then only runs migrations created after the dump.

**Contents:** All table definitions including columns, types, constraints, indexes, and foreign keys. Tables are in alphabetical order (starting with `actuarial_life_tables`, `assets`, `bequests`, etc.).

**When to use:**
- Reference for understanding current table structures
- Starting point for fresh database installations
- NOT a substitute for individual migration files (which should still exist for incremental changes)

---

## 17. Known Issues

### 17.1 ChattelResource Inline calculateUserShare

**Severity:** Low (code duplication)
**File:** `app/Http/Resources/ChattelResource.php` (lines 91-120)

The `ChattelResource` contains a private `calculateUserShare()` method that duplicates the logic in the `CalculatesOwnershipShare` trait. Since API Resources are not Eloquent models, they cannot directly `use` the trait. The inline method correctly handles individual, trust, joint, and tenants_in_common ownership, but any future changes to ownership calculation logic must be updated in both locations.

**Potential fix:** Extract a standalone utility class or make `CalculatesOwnershipShare` methods static so they can be called from resource classes.

---

### 17.2 ISAAllowanceSummary Hardcoded Allowance

**Severity:** Low (consistency)
**File:** `resources/js/components/Shared/ISAAllowanceSummary.vue`

The component hardcodes `ISA_ALLOWANCE: 20000` rather than importing `ISA_ANNUAL_ALLOWANCE` from `@/constants/taxConfig.js`. If the ISA allowance changes in a future tax year, this component would not pick up the change from the centralised constant.

---

### 17.3 Duplicate Date Utilities

**Severity:** Low (developer confusion)
**Files:** `resources/js/utils/dates.js` (151 lines) and `resources/js/utils/dateFormatter.js` (224 lines)

Both files provide overlapping functionality:

| Function            | dates.js              | dateFormatter.js         |
|---------------------|-----------------------|--------------------------|
| Date for input      | `formatDateForInput`  | `formatDateForInput`     |
| Date for display    | `formatDateForDisplay`| `formatDate`             |
| Relative time       | `formatRelativeDate`  | `getRelativeTime`        |
| Parse UK date       | `parseUKDate`         | `parseDate`              |

`dateFormatter.js` additionally provides `formatDateLong` (with month name) and `calculateAge`, which `dates.js` does not. `dates.js` provides tax year utilities (`getTaxYearStart`/`getTaxYearEnd`).

**Impact:** Both files are imported across different components, leading to inconsistent imports. Should be consolidated into a single date utility file.

---

### 17.4 Dual Async Action Patterns

**Severity:** Low (developer confusion)
**Files:** `resources/js/utils/asyncAction.js` and `resources/js/store/helpers/storeHelpers.js`

Both files export a `createAsyncAction` function with different signatures:

- **asyncAction.js version:** `createAsyncAction(serviceCall, commitMutation, options)` -- more configurable, supports `extractData`, `onSuccess`, `onError`, `skipLoading`, `rethrowError`
- **storeHelpers.js version:** `createAsyncAction(serviceFn, options)` -- simpler, wraps with `withLoading`, supports `errorMessage` and `onSuccess`

`storeHelpers.js` also provides `withLoading()`, `createBaseState/Getters/Mutations()`, and `createCrudMutations()` which are complementary utilities not duplicated elsewhere.

**Impact:** Developers must check which import they are using. The two factories are not interchangeable.

---

### 17.5 PostcodeLookup Without Active API

**Severity:** Informational
**File:** `resources/js/components/Shared/PostcodeLookup.vue`

The PostcodeLookup component is a simple text input. The UK postcode API integration is not yet active. The `postcodeService.js` exists but is a placeholder.

---

### 17.6 StructuredLogging Trait Not Currently Used

**Severity:** Informational
**File:** `app/Traits/StructuredLogging.php`

The `StructuredLogging` trait is defined and fully functional but no models or services currently `use` it. Logging across the application is done with direct `Log::` facade calls or the `Auditable` trait for data change events. The trait provides richer context (class name, request ID, user ID) that would benefit from wider adoption.

---

## 18. Deep Dive: Design System & Cross-Cutting Patterns

### 18.1 The Ownership Pattern

The single-record joint ownership pattern is the most pervasive cross-cutting concern in the application. It affects every module that deals with assets or liabilities.

**Database convention (all asset/liability tables):**
```
user_id             INT     -- Primary owner (FK to users)
joint_owner_id      INT     -- Secondary owner (nullable FK to users)
ownership_type      ENUM    -- individual | joint | tenants_in_common | trust
ownership_percentage DECIMAL -- Primary owner's percentage (default varies)
```

**Backend enforcement stack:**
1. `HasJointOwnership` trait on models provides query scopes (`forUserOrJoint`, `forUser`, `forJointOwner`)
2. `CalculatesOwnershipShare` trait on services/controllers provides value calculation
3. API Resources include computed `user_share`, `is_primary_owner`, `is_shared` fields

**Frontend enforcement stack:**
1. `ownership.js` utility provides `calculateUserShare`, `calculateTotalUserShare`, `filterByOwner`
2. `currencyMixin` includes `formatOwnershipType` for display labels
3. Individual module stores apply ownership calculations when computing totals

**The business interest exception:**
Business interests are the only asset type where `ownership_percentage` applies even for `individual` ownership. This is because the percentage represents shareholding (e.g., "I individually own 60% of this company") rather than co-ownership split. The `CalculatesOwnershipShare` trait detects business interests by checking for both `current_valuation` and `business_name` fields on the asset object.

---

### 18.2 The Preview Isolation Pattern

Preview mode allows unauthenticated visitors to explore the application with seeded test data. Isolation is enforced at every layer -- model trait, middleware, Vue mixin, API interceptor, router meta, Vuex store, and subscription bypass. See **auth.md Section 14** for the complete preview isolation architecture, including the full layer table, the PreviewWriteInterceptor flow, excluded routes/patterns, and the fake response format.

---

### 18.3 The Currency Formatting Pattern

Currency formatting is standardised to prevent inconsistencies across modules.

**Backend:** `FormatsCurrency` trait (used by 3 services) provides `formatCurrency`, `formatCurrencyWithPence`, `formatCurrencyCompact`, `formatPercentage`. All use PHP's `number_format()`.

**Frontend:** Two-layer approach:
1. `utils/currency.js` -- Pure utility functions using `Intl.NumberFormat('en-GB', {currency: 'GBP'})`. Functions: `formatCurrency`, `formatCurrencyWithPence`, `formatCurrencyCompact`, `parseCurrency`, `formatPercentage`.
2. `mixins/currencyMixin.js` -- Vue mixin that wraps `currency.js` functions as component methods. Adds 5 type formatters: `formatAccountType`, `formatOwnershipType`, `formatSavingsAccountType`, `formatPropertyType`, `formatMortgageType`.

**Rule:** Components must never define local `formatCurrency()` methods. Always use the mixin or import from `currency.js`.

---

### 18.4 The Audit Pattern

Two complementary mechanisms provide comprehensive audit coverage:

**Automatic (model-level):** The `Auditable` trait hooks into Eloquent lifecycle events. Any model that `use`s the trait automatically logs creates, updates, and deletes to `audit_logs`. This covers all 16 auditable models. Old and new values are recorded, with configurable field filtering.

**Programmatic (service-level):** The `AuditService` provides explicit logging for events that do not correspond to model lifecycle events:
- Authentication events (login attempts, MFA verification, password changes)
- Data access events (viewing sensitive data)
- Admin actions (user management, configuration changes)
- GDPR events (data export requests, account erasure)

---

### 18.5 The Colour System Enforcement

The design system enforces several colour rules across the entire application:

**Amber/orange ban:** The `WARNING_COLORS` object uses blue (`#3B82F6`) instead of amber/orange. This is a deliberate design decision. No component should ever use `amber-*` or `orange-*` Tailwind classes.

**Risk level colours:** The five-level risk system uses a specific colour mapping (yellow, pink, green, teal, blue) that does NOT follow a traditional red-to-green gradient. This is intentional -- risk levels are not "bad to good" but represent different investment approaches.

**Chart consistency:** All charts must use `CHART_DEFAULTS` spread into their ApexCharts configuration. Asset breakdown charts must use `ASSET_COLORS` to ensure pensions are always Trust Blue, property is always green, and so on across all views.

**Import rule:** All colour values must be imported from `@/constants/designSystem`. Hardcoded hex values in component styles are not permitted.

---

### 18.6 The Frontend Service Architecture

All 35 frontend services share a common pattern:

```javascript
import api from '@/services/api';

export default {
  getItems() {
    return api.get('/module/items');
  },
  createItem(data) {
    return api.post('/module/items', data);
  },
  // ... etc
};
```

Each service is a plain object (not a class) with methods that return Axios promises. The base `api` instance handles:
- Authentication headers (Bearer token)
- Base URL resolution (dev vs production)
- Error interception (401 redirect, 422 validation, preview mode detection)
- Retry logic (exponential backoff for 5xx and 429 errors)

Services never handle their own error display -- errors bubble up to Vuex actions or component catch blocks.

---

### 18.7 The Vuex Store Helper Pattern

The `storeHelpers.js` file provides factory functions that eliminate boilerplate across all 21 Vuex modules:

**State creation:**
```javascript
state: createBaseState({
  accounts: [],
  selectedAccount: null,
})
// Result: { loading: false, error: null, accounts: [], selectedAccount: null }
```

**Mutation creation:**
```javascript
mutations: {
  ...createBaseMutations(),        // setLoading, setError
  ...createCrudMutations('accounts'), // setAccounts, addAccount, updateAccount, removeAccount
}
```

**Action creation:**
```javascript
actions: {
  fetchAccounts: createAsyncAction(
    (payload) => savingsService.getAccounts(),
    { onSuccess: (commit, response) => commit('setAccounts', response.data.data) }
  ),
}
```

This pattern ensures consistent loading/error state management and reduces per-module boilerplate from ~50 lines to ~5 lines per action.

---

### 18.8 Frontend Utility Summary

| Utility File    | Lines | Key Exports                                                   |
|-----------------|-------|---------------------------------------------------------------|
| `currency.js`   | 140   | `formatCurrency`, `formatCurrencyWithPence`, `formatCurrencyCompact`, `parseCurrency`, `formatPercentage` |
| `ownership.js`  | 110   | `OWNERSHIP_TYPES`, `isSharedOwnership`, `calculateUserShare`, `calculateTotalUserShare`, `filterByOwner`, `getOwnershipLabel` |
| `dates.js`      | 151   | `formatDateForInput`, `formatDateForDisplay`, `formatRelativeDate`, `parseUKDate`, `getTaxYearStart`, `getTaxYearEnd` |
| `dateFormatter.js` | 224 | `formatDate`, `formatDateForInput`, `parseDate`, `formatDateLong`, `calculateAge`, `getRelativeTime` |
| `logger.js`     | 156   | Default export with `info`, `warn`, `error`, `debug`, `table`, `styled`, `group`, `time`, `timeAsync`, `setCategory` |
| `poller.js`     | 131   | `poll`, `pollMonteCarloJob` (exponential backoff polling for async jobs) |
| `asyncAction.js`| 158   | `createAsyncAction`, `createAsyncActionWithRefresh`, `createCrudAction` |

---

### 18.9 Mixin Summary

| Mixin              | Lines | Methods/Computed                                              |
|--------------------|-------|---------------------------------------------------------------|
| `currencyMixin`    | 203   | `formatCurrency`, `formatCurrencyWithPence`, `formatCurrencyCompact`, `parseCurrency`, `formatPercentage`, `formatNumber`, `formatLiability`, `formatAccountType`, `formatOwnershipType`, `formatSavingsAccountType`, `formatPropertyType`, `formatMortgageType` |
| `previewModeMixin` | 106   | **Computed:** `isPreviewMode`, `previewTooltip`. **Methods:** `getPreviewTooltip`, `handlePreviewAction`, `previewGuard`, `getPreviewButtonProps`, `canOpenModal` |

---

### 18.10 Middleware Execution Order

The middleware stack for a typical authenticated API request:

```
1. SanitizeInput          -- Trim + strip_tags on all inputs
2. PreviewWriteInterceptor -- Intercept writes from preview users (before auth)
3. auth:sanctum           -- Authenticate via Bearer token
4. EnsureMFAVerified      -- Check MFA if enabled
5. CheckSubscription      -- Verify active subscription (feature-flagged)
6. [Route-specific: HasPermission | HasRole | IsAdmin]
7. Controller method
```

For admin routes, step 6 adds the `IsAdmin` middleware. For routes with granular permissions, `HasPermission` or `HasRole` is applied instead.

---

*Document generated from codebase analysis. File paths are relative to the project root `/Users/Chris/Desktop/fynla/`.*
