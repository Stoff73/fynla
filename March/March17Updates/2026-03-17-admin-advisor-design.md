# Design Spec: Admin Panel Enhancement & Advisor Overview Dashboard

**Date:** 17 March 2026
**Version:** 1.1
**Status:** Approved (post-review fixes applied)
**Worktrees:** `feature/admin-enhancement`, `feature/advisor-dashboard`

---

## Executive Summary

Two parallel features for Fynla:

1. **Admin Panel Enhancement** — Visual decision tree matrix for all 5 modules, enhanced user management with granular module/step tracking, database backup verification
2. **Advisor Overview Dashboard** — New advisor role with client management, tiered access (read-only overview + impersonation), activity/communication tracking, suitability report tracking

Both features are developed in separate git worktrees to avoid conflicts, then merged sequentially (admin first, advisor second).

---

## Feature 1: Admin Panel Enhancement

### 1.1 Decision Tree Visualiser

**Purpose:** Give admins a visual representation of how each module's decision engine builds recommendations — from user data inputs through trigger conditions, decision logic, and outcomes.

**Location:** New "Decision Matrix" tab in the admin panel (`AdminPanel.vue`)

**Data source:** Existing `*ActionDefinition` models (Retirement, Investment, Protection, Savings, Tax) and their `trigger_config` JSON fields.

#### Module-to-Model Mapping

The Decision Matrix covers 6 modules. 5 action definition models already exist; Estate Planning requires a new one:

| UI Tab | Model | Status |
|--------|-------|--------|
| Protection | `ProtectionActionDefinition` | Exists (model, service, controller, routes) |
| Cash & Savings | `SavingsActionDefinition` | Exists (model, service) — no controller/routes yet |
| Investments | `InvestmentActionDefinition` | Exists (model, service, controller, routes) |
| Retirement | `RetirementActionDefinition` | Exists (model, service, controller, routes) |
| Estate Planning | `EstateActionDefinition` | **NEW — must create model, migration, service, seeder** |
| Tax | `TaxActionDefinition` | Exists (model, service) — no controller/routes yet |

**Pre-requisites:**

1. **Create `EstateActionDefinition`** — New model, migration, and service following the identical pattern of the other 5 action definition models (same columns: `key`, `source`, `title_template`, `description_template`, `action_template`, `category`, `priority`, `scope`, `what_if_impact_type`, `trigger_config`, `is_enabled`, `sort_order`, `notes`). Seed with estate-specific action definitions (e.g. "No will in place", "Policy not in trust", "Inheritance tax liability exceeds nil-rate band", "No Lasting Power of Attorney").

2. **Single generic controller** — Rather than duplicating the existing per-module controller pattern (3 near-identical controllers already exist), introduce:
   - `ActionDefinitionController.php` — Generic CRUD that accepts `{module}` parameter and resolves to the correct model via the `ALLOWED_MODULES` whitelist.
   - `StoreActionDefinitionRequest.php` — Single form request with base rules. Module-specific enum values (source, what-if impact type) loaded dynamically based on the `{module}` route parameter using module config objects.
   - Routes: `admin/action-definitions/{module}` covers all 6 modules.

**Migration path for existing routes:** The 3 existing route groups (`admin/retirement-actions`, `admin/investment-actions`, `admin/protection-actions`) and their controllers continue to work. The new generic routes are added alongside them. The existing per-module controllers and modals can be deprecated once the Decision Matrix is live.

#### Module Parameter Whitelist

The `GET /api/admin/decision-matrix/{module}` endpoint accepts **only** these values:

```php
private const ALLOWED_MODULES = [
    'protection' => ProtectionActionDefinition::class,
    'savings' => SavingsActionDefinition::class,
    'investment' => InvestmentActionDefinition::class,
    'retirement' => RetirementActionDefinition::class,
    'estate' => EstateActionDefinition::class,
    'tax' => TaxActionDefinition::class,
];
```

The controller uses a static array lookup — **never string interpolation** to resolve the model class. Any unrecognised module value returns 422.

#### UI Structure

- **Admin tab:** "Decision Matrix" added to existing admin tab bar (Dashboard, User Management, **Decision Matrix**, Tax Settings, Database)
- **Module sub-tabs:** Protection, Cash & Savings, Investments, Retirement, Estate Planning, Tax — each with action count badge
- **Stats bar:** Total actions, enabled count, disabled count, critical/high count, medium count
- **Tree canvas:** Horizontal left-to-right flow with 4 columns:
  - **User Data** (light-blue-100/light-blue-500 border) — what data the trigger reads
  - **Trigger** (violet-50/violet-200 border) — the condition that fires
  - **Logic** (spring-50/spring-200 border) — the calculation or assessment
  - **Outcome** (raspberry-50/raspberry-200 border) — the recommendation generated
- **Priority badges:** CRIT (raspberry-700), HIGH (raspberry-500), MED (violet-500), LOW (spring-500), OFF (neutral-500)
- **Disabled rows:** 0.45 opacity, dashed connection lines
- **Legend bar:** Colour-coded node types + priority badge reference

#### Interaction: Click Node to Edit (Side Drawer)

Clicking any node opens a **slide-in drawer** (right side, 420px width, shadow-lg) with:

- **Header:** Action title + machine key (monospace)
- **Status toggle:** Enabled/disabled (spring-500 toggle)
- **Priority:** Select dropdown (Critical/High/Medium/Low)
- **Category:** Text input
- **Title template:** Text input with {variable} placeholders
- **Description template:** Textarea (monospace)
- **Action template:** Textarea (monospace)
- **Trigger configuration:** Visual condition builder
  - Field selector + operator + value/comparison
  - AND/OR combinators
  - Each condition on its own row
- **Source:** Agent (automatic) or Goal (user-linked)
- **Sort order:** Numeric input
- **Template variables:** Tag display of available {variables}
- **Footer:** Cancel (secondary btn) + Save Changes (primary btn)

**Future (marked for B upgrade):** Full drag-and-drop visual editor where admins can rewire connections, add/remove nodes, and rearrange the tree graphically.

#### New Components

| Component | Path | Purpose |
|-----------|------|---------|
| `DecisionMatrix.vue` | `resources/js/components/Admin/DecisionMatrix.vue` | Container with module sub-tabs |
| `DecisionTree.vue` | `resources/js/components/Admin/DecisionTree.vue` | Tree visualisation for one module |
| `DecisionNode.vue` | `resources/js/components/Admin/DecisionNode.vue` | Individual node component |
| `ActionDefinitionDrawer.vue` | `resources/js/components/Admin/ActionDefinitionDrawer.vue` | **Single** side drawer edit panel for ALL modules |
| `TriggerConfigEditor.vue` | `resources/js/components/Admin/TriggerConfigEditor.vue` | Condition builder UI |

**Important — Single Form Pattern:** The existing codebase has 3 near-identical action definition modals (`ProtectionActionModal.vue`, `RetirementActionModal.vue`, `InvestmentActionModal.vue`). The `ActionDefinitionDrawer.vue` replaces all of these with a **single component** that receives a `moduleConfig` prop containing module-specific enum values:

```javascript
// Module config objects — one per module
const MODULE_CONFIGS = {
  protection: {
    sourceOptions: ['agent', 'gap'],
    whatIfImpactOptions: ['coverage_increase', 'gap_reduction', 'default'],
    conditionOptions: ['gap_exists', 'strategy_recommendation', 'policies_exist_with_gaps', ...],
    triggerFields: { coverage_type: {...}, category_match: {...}, threshold: {...} }
  },
  savings: {
    sourceOptions: ['agent', 'goal'],
    whatIfImpactOptions: [...],
    conditionOptions: [...],
    triggerFields: {...}
  },
  estate: {
    sourceOptions: ['agent', 'goal'],
    whatIfImpactOptions: ['iht_reduction', 'estate_protection', 'default'],
    conditionOptions: ['no_will', 'policy_not_in_trust', 'iht_exceeds_nrb', 'no_lpa', ...],
    triggerFields: {...}
  },
  // ... investment, retirement, tax
};
```

This follows the same pattern as `PolicyFormModal.vue` which uses a single form for all 5 protection policy types via conditional rendering. **Do NOT create separate drawer/form components per module.**

#### API Endpoints

New generic controller replaces the need for per-module route groups. Existing 3 module-specific routes remain for backwards compatibility.

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/admin/decision-matrix/{module}` | Returns all action definitions for a module, grouped and ordered for tree rendering |
| GET | `/api/admin/action-definitions/{module}` | List all action definitions for a module |
| POST | `/api/admin/action-definitions/{module}` | Create new action definition |
| GET | `/api/admin/action-definitions/{module}/{id}` | Get single action definition |
| PATCH | `/api/admin/action-definitions/{module}/{id}` | Update action definition |
| DELETE | `/api/admin/action-definitions/{module}/{id}` | Delete action definition |
| PATCH | `/api/admin/action-definitions/{module}/{id}/toggle` | Toggle enabled/disabled |

The `{module}` parameter must be one of: `protection`, `savings`, `investment`, `retirement`, `estate`, `tax`. Validated via the `ALLOWED_MODULES` whitelist. The generic `ActionDefinitionController` resolves the correct model class from this parameter.

**Existing routes retained:** `admin/retirement-actions/*`, `admin/investment-actions/*`, `admin/protection-actions/*` remain functional. The existing per-module modals (`RetirementActionModal.vue`, etc.) continue to use these. The Decision Matrix drawer uses the new generic routes.

#### Data Flow

```
*ActionDefinition DB → GET /api/admin/decision-matrix/{module}
  → DecisionMatrix.vue (module tabs)
  → DecisionTree.vue (renders nodes + connections)
  → Click node → ActionDefinitionDrawer.vue
  → Edit + Save → PATCH /api/admin/{module}-actions/{id}
  → Re-fetch tree data
```

### 1.2 Enhanced User Management

**Purpose:** Show admins which modules each user has entered data in, granular step completion within each module, onboarding progress, and skipped areas.

**Location:** Enhanced `UserManagement.vue` in admin panel

#### Per-User Module Tracking

For each user in the admin user list, show:

- **Module status dots:** P S I R E (Protection, Savings, Investment, Retirement, Estate) with colour coding:
  - Complete (spring-500): User has entered meaningful data in all key areas of the module
  - Partial (violet-500): Some data entered but key areas missing
  - No data (light-gray): No records exist for this module
  - Skipped (eggshell-500 with horizon-300 text, strikethrough): User explicitly skipped this module

- **Expandable detail row:** Click a user row to expand and see granular breakdown per module

#### Granular Step Tracking (Expanded View)

When expanded, each module shows sub-areas and their status:

**Protection:**
- Life insurance policies (count + total cover)
- Critical illness policies (count)
- Income protection policies (count)
- Disability policies (count)
- Sickness & illness policies (count)

**Cash & Savings:**
- Cash accounts (count + total balance)
- Savings accounts (count + total balance)
- ISA accounts (count)
- Emergency fund status

**Investments:**
- Investment accounts (count + total value)
- Holdings (count)
- Risk profile (set/not set)
- Investment goals (count)

**Retirement:**
- Defined Contribution pensions (count + total fund value)
- Defined Benefit pensions (count)
- State pension (set/not set)
- Retirement profile (target date, income need)

**Estate Planning:**
- Will (exists/not exists)
- Lasting Powers of Attorney (count)
- Trusts (count + total value)
- Gifts (count)
- Estate assets (count)

#### Data Source

Query existing models per user. No new database tables needed — this is a read-only aggregation of existing data:

```php
// New service — also reused by Advisor dashboard (section 2.4)
class UserModuleTrackingService
{
    public function getModuleStatus(User $user): array
    {
        // Returns structured array of module → sub-area → status
        // Uses eager loading to prevent N+1 queries:
        // $user->load([
        //     'lifeInsurancePolicies', 'criticalIllnessPolicies',
        //     'incomeProtectionPolicies', 'disabilityPolicies',
        //     'cashAccounts', 'savingsAccounts',
        //     'investmentAccounts.holdings',
        //     'dcPensions', 'dbPensions', 'statePension',
        //     'trusts', 'assets', 'gifts', 'lastingPowersOfAttorney'
        // ]);
    }
}
```

#### Onboarding Progress Display

Leverage existing fields:
- `onboarding_completed` (boolean)
- `onboarding_started_at` / `onboarding_completed_at` (timestamps)
- `life_stage` (current stage)
- `life_stage_completed_steps` (array)
- `journey_states` (JSON — per-journey status)
- `journey_selections` (JSON — selected journeys)
- `OnboardingProgress` model records (step-level tracking)

Display as a progress summary card within the expanded user row.

#### New Components

| Component | Path | Purpose |
|-----------|------|---------|
| `UserModuleStatus.vue` | `resources/js/components/Admin/UserModuleStatus.vue` | Module dots + expandable detail |
| `UserOnboardingProgress.vue` | `resources/js/components/Admin/UserOnboardingProgress.vue` | Onboarding progress card |

#### API Endpoint

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/admin/users/{id}/module-status` | Returns module tracking data for a specific user |

### 1.3 Database Backup Verification

**Purpose:** Ensure the existing backup functionality in `AdminController` actually works correctly.

**Verification tasks:**
1. Test `createBackup()` — verify mysqldump executes, .sql file is created in `storage/app/backups/`, file contains valid SQL
2. Test `listBackups()` — verify backup files are listed correctly with sizes and dates
3. Test `restoreBackup()` — verify restore works on a test database (NOT production), caches are cleared after
4. Test `deleteBackup()` — verify file deletion with path traversal protection
5. Verify credential file security — temp `.my.cnf` is created with 0600 permissions and deleted after use
6. Verify rate limiting — 3 requests per minute on backup endpoints
7. Test error handling — what happens when mysqldump is not available, disk is full, file permissions are wrong

**Fix any issues found.** No new features — just make what exists work reliably.

---

## Feature 2: Advisor Overview Dashboard

### 2.1 Database Schema

#### New Migration: `advisor_clients` table

```sql
CREATE TABLE advisor_clients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    advisor_id BIGINT UNSIGNED NOT NULL,        -- FK to users.id (the advisor)
    client_id BIGINT UNSIGNED NOT NULL,          -- FK to users.id (the client)
    status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    assigned_date DATE NOT NULL,
    last_review_date DATE NULL,
    next_review_due DATE NULL,
    review_frequency_months TINYINT UNSIGNED DEFAULT 12,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (advisor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_advisor_client (advisor_id, client_id),
    INDEX idx_advisor_status (advisor_id, status),
    INDEX idx_next_review (next_review_due)
);
```

#### New Migration: `client_activities` table

```sql
CREATE TABLE client_activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    advisor_client_id BIGINT UNSIGNED NOT NULL,  -- FK to advisor_clients.id
    advisor_id BIGINT UNSIGNED NOT NULL,          -- denormalised for direct queries
    client_id BIGINT UNSIGNED NOT NULL,           -- denormalised for direct queries
    activity_type ENUM('email', 'phone', 'meeting', 'letter', 'suitability_report', 'review', 'note') NOT NULL,
    summary VARCHAR(500) NOT NULL,
    details TEXT NULL,
    activity_date DATETIME NOT NULL,
    follow_up_date DATE NULL,
    follow_up_completed BOOLEAN DEFAULT FALSE,
    -- Suitability report specific fields
    report_type VARCHAR(100) NULL,           -- e.g., 'protection_review', 'annual_review', 'pension_transfer'
    report_sent_date DATE NULL,
    report_acknowledged_date DATE NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (advisor_client_id) REFERENCES advisor_clients(id) ON DELETE CASCADE,
    FOREIGN KEY (advisor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_advisor_client_id (advisor_client_id),
    INDEX idx_advisor_client (advisor_id, client_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_activity_date (activity_date),
    INDEX idx_follow_up (follow_up_date, follow_up_completed)
);
```

#### User Table Modification

```sql
ALTER TABLE users ADD COLUMN is_advisor BOOLEAN DEFAULT FALSE AFTER is_admin;
```

**User model changes required:**
- Add `'is_advisor'` to the `$guarded` array (alongside `is_admin`, `is_preview_user`)
- Add `'is_advisor' => 'boolean'` to `$casts`

### 2.2 Models

| Model | Path | Purpose |
|-------|------|---------|
| `AdvisorClient` | `app/Models/AdvisorClient.php` | Advisor-client relationship with metadata |
| `ClientActivity` | `app/Models/ClientActivity.php` | Communication, report, and review tracking |

**AdvisorClient relationships:**
- `advisor()` → belongsTo User
- `client()` → belongsTo User
- `activities()` → hasMany ClientActivity (via `advisor_client_id`)

**ClientActivity relationships:**
- `advisorClient()` → belongsTo AdvisorClient
- `advisor()` → belongsTo User
- `client()` → belongsTo User

**User model additions:**
- `advisorClients()` → hasMany AdvisorClient (foreign key `advisor_id`)
- `advisors()` → hasMany AdvisorClient (foreign key `client_id`)
- `isAdvisor()` → accessor checking `is_advisor` flag

### 2.3 Seeder

**`AdvisorClientSeeder`** — Assigns all 6 preview personas as clients of chris@fynla.org:

- Sets `is_advisor = true` on chris@fynla.org (via `DB::table('users')->where(...)->update(...)` — not mass-assignment, since `is_advisor` is guarded)
- Creates `advisor_clients` records for all preview persona primary users
- Seeds sample `client_activities` records (emails, phone calls, meetings, suitability reports) with realistic dates
- Sets `last_review_date` and `next_review_due` based on persona type (some overdue for demo purposes)

**Note:** These seeded records are for demo purposes only. In production, `AdvisorDashboardService::getClientList` filters by `users.is_preview_user = false` to exclude preview personas from real advisor views.

### 2.4 Backend Services

| Service | Path | Purpose |
|---------|------|---------|
| `AdvisorDashboardService` | `app/Services/Advisor/AdvisorDashboardService.php` | Aggregates dashboard stats, client list, activity feed |
| `ClientActivityService` | `app/Services/Advisor/ClientActivityService.php` | CRUD for client activities |
| `AdvisorImpersonationService` | `app/Services/Advisor/AdvisorImpersonationService.php` | Handles advisor entering/exiting client profiles |

**AdvisorDashboardService methods:**
- `getDashboardStats(User $advisor)` — client count, reviews due, communications this week, reports this month
- `getClientList(User $advisor, array $filters)` — clients with module status, last review, last communication, last report
  - **Reuses `UserModuleTrackingService`** (from section 1.2) for module status per client
  - **Cached 5 minutes per advisor** (`Cache::remember("advisor:{$advisor->id}:clients", 300, ...)`)
  - **Eager loads** all module relationships in a single query to prevent N+1
  - **Filters `users.is_preview_user = false`** in production (seeded demo data excluded)
- `getReviewsDue(User $advisor)` — overdue and upcoming reviews
- `getRecentActivity(User $advisor, int $limit)` — recent activity feed

**AdvisorImpersonationService methods:**
- `enterClientProfile(User $advisor, User $client)` — validates relationship, guards against privilege escalation, creates audit log, stores impersonation state in cache
- `exitClientProfile(User $advisor)` — removes cache entry, logs exit
- `isImpersonating(User $advisor)` — check cache for active impersonation

#### Impersonation State Storage

Impersonation uses **server-side Laravel cache** (not token replacement):

```php
// Cache key format
"advisor_impersonation:{$advisor->currentAccessToken()->id}"

// Cache value
['client_id' => $client->id, 'started_at' => now()]

// TTL: 8 hours (auto-expires stale sessions)
```

**How it works:**
1. `enterClientProfile()` stores the cache entry and returns `{ impersonating: true, client: $clientData }`
2. `AdvisorImpersonationMiddleware` checks the cache on every request:
   - If cache entry exists: resolves `auth()->user()` to the **client** user for all data-scoping queries
   - Stores the **real advisor** in `request()->attributes->set('advisor', $advisor)` for audit logging
3. `exitClientProfile()` deletes the cache entry
4. The advisor's Sanctum token is never modified — "Exit" simply clears the cache and the next request resolves back to the advisor

#### Impersonation Security Guards

`enterClientProfile()` must enforce:

```php
// 1. Client must be assigned to this advisor
abort_unless(
    AdvisorClient::where('advisor_id', $advisor->id)
        ->where('client_id', $client->id)
        ->where('status', 'active')
        ->exists(),
    403, 'Client is not assigned to you'
);

// 2. Cannot impersonate admins or other advisors
abort_if($client->is_admin, 403, 'Cannot enter an admin account');
abort_if($client->is_advisor, 403, 'Cannot enter another advisor account');

// 3. Cannot nest impersonation
abort_if($this->isImpersonating($advisor), 403, 'Already impersonating a client');
```

### 2.5 Controller

**`AdvisorController`** (`app/Http/Controllers/Api/AdvisorController.php`):

| Method | Route | Purpose |
|--------|-------|---------|
| `dashboard` | GET `/api/advisor/dashboard` | Dashboard stats |
| `clients` | GET `/api/advisor/clients` | Client list with module status |
| `clientDetail` | GET `/api/advisor/clients/{id}` | Single client overview |
| `clientModuleStatus` | GET `/api/advisor/clients/{id}/modules` | Detailed module breakdown |
| `enterClient` | POST `/api/advisor/clients/{id}/enter` | Start impersonation |
| `exitClient` | POST `/api/advisor/exit` | End impersonation |
| `activities` | GET `/api/advisor/activities` | Activity feed (filterable by client_id, activity_type) |
| `storeActivity` | POST `/api/advisor/activities` | Log new activity |
| `updateActivity` | PUT `/api/advisor/activities/{id}` | Update activity |
| `reviewsDue` | GET `/api/advisor/reviews-due` | Overdue + upcoming reviews |
| `reports` | GET `/api/advisor/reports` | Suitability reports list (filtered from activities where `activity_type = 'suitability_report'`) |

#### Form Request Validation

**`StoreClientActivityRequest`** (`app/Http/Requests/StoreClientActivityRequest.php`):

```php
public function rules(): array
{
    return [
        'client_id' => 'required|exists:users,id',
        'activity_type' => 'required|in:email,phone,meeting,letter,suitability_report,review,note',
        'summary' => 'required|string|max:500',
        'details' => 'nullable|string|max:5000',
        'activity_date' => 'required|date',
        'follow_up_date' => 'nullable|date|after_or_equal:activity_date',
        'report_type' => 'nullable|required_if:activity_type,suitability_report|string|max:100',
        'report_sent_date' => 'nullable|date',
        'report_acknowledged_date' => 'nullable|date|after_or_equal:report_sent_date',
    ];
}
```

### 2.6 Middleware & Route Protection

#### Advisor Role — RBAC Integration

Use the existing `Role` model rather than a standalone boolean check:

```php
// Add to Role model constants
public const ROLE_ADVISOR = 'advisor';
public const LEVEL_ADVISOR = 25;  // between user (10) and support (50)
```

**`AdvisorMiddleware`** — applied to all `/api/advisor/*` routes:
- Checks `$user->is_advisor === true` (boolean flag for quick checks)
- Also verifiable via `$user->role->slug === 'advisor'` (RBAC consistency)
- Returns 403 if not an advisor

**Route middleware:** Use `permission:advisor.access` on advisor routes (consistent with `permission:admin.access` pattern).

**`AdvisorImpersonationMiddleware`** — applied during impersonation:
- Reads cache to check if advisor is impersonating a client
- Substitutes `auth()->user()` with the client user
- Stores real advisor in request attributes for audit logging
- Provides advisor banner data to frontend via response headers

#### PreviewWriteInterceptor Exclusions

Add to `EXCLUDED_ROUTES` in `app/Http/Middleware/PreviewWriteInterceptor.php`:

```php
'api/advisor/clients/*/enter',
'api/advisor/exit',
```

This prevents the interceptor from blocking impersonation start/stop for preview user testing.

### 2.7 Frontend

#### Routing

```javascript
// New route group in router/index.js
{
  path: '/advisor',
  component: AdvisorLayout,
  meta: { requiresAuth: true, requiresAdvisor: true },
  children: [
    { path: '', component: AdvisorDashboard },
    { path: 'clients', component: AdvisorClientList },
    { path: 'clients/:id', component: AdvisorClientDetail },
    { path: 'activities', component: AdvisorActivityLog },
    { path: 'reviews', component: AdvisorReviewsDue },
    { path: 'reports', component: AdvisorReports },
  ]
}
```

#### Router Guard Addition

Add `requiresAdvisor` check to `router/index.js` guard, alongside existing `requiresAdmin`:

```javascript
// After the existing requiresAdmin block:
} else if (to.matched.some(r => r.meta.requiresAdvisor) && !store.getters['auth/isAdvisor']) {
  next({ name: 'Dashboard' });
}
```

**Vuex getter:** Add `isAdvisor` to `store/modules/auth.js`:
```javascript
isAdvisor: (state) => state.user?.is_advisor === true
```

#### Components

| Component | Path | Purpose |
|-----------|------|---------|
| `AdvisorLayout.vue` | `resources/js/layouts/AdvisorLayout.vue` | Sidebar + content layout with "ADVISOR VIEW" badge |
| `AdvisorDashboard.vue` | `resources/js/views/Advisor/AdvisorDashboard.vue` | Main dashboard page |
| `AdvisorClientList.vue` | `resources/js/views/Advisor/AdvisorClientList.vue` | Full client table view |
| `AdvisorClientDetail.vue` | `resources/js/views/Advisor/AdvisorClientDetail.vue` | Read-only client overview |
| `AdvisorActivityLog.vue` | `resources/js/views/Advisor/AdvisorActivityLog.vue` | Activity feed + log form |
| `AdvisorReviewsDue.vue` | `resources/js/views/Advisor/AdvisorReviewsDue.vue` | Review management |
| `AdvisorReports.vue` | `resources/js/views/Advisor/AdvisorReports.vue` | Suitability report tracking (queries activities where type = suitability_report) |
| `ClientModuleDots.vue` | `resources/js/components/Advisor/ClientModuleDots.vue` | P S I R E status dots |
| `ClientActivityForm.vue` | `resources/js/components/Advisor/ClientActivityForm.vue` | Modal form to log activity |
| `AdvisorBanner.vue` | `resources/js/components/Advisor/AdvisorBanner.vue` | Top banner during impersonation |

#### Vuex Store

**`store/modules/advisor.js`** — new namespaced store:
- State: clients, activities, dashboardStats, reviewsDue, impersonating, impersonatedClient
- Actions: fetchDashboard, fetchClients, fetchActivities, enterClient, exitClient, logActivity
- Getters: overdueReviews, clientById, activeClients

#### Impersonation Flow

1. Advisor clicks "Enter Profile" on a client row
2. POST `/api/advisor/clients/{id}/enter` — server validates relationship + security guards, stores cache entry, logs to audit
3. Frontend sets `impersonating = true` and `impersonatedClient` in Vuex advisor store
4. Frontend redirects to client's dashboard with `AdvisorBanner.vue` fixed at the top
5. Banner shows: "You are viewing [Client Name]'s profile as their advisor" + "Exit" button
6. Advisor can navigate all client module pages, edit data (all logged with advisor attribution)
7. Clicking "Exit" → POST `/api/advisor/exit` → clears cache + Vuex state → redirect back to advisor dashboard
8. All actions during impersonation are logged to `AuditLog` with `advisor_id` in metadata

### 2.8 Household & Couples Handling

When an advisor views or impersonates a client who is part of a couple (linked via `spouse_id`):

- **Client list:** Each couple appears as a single row (e.g. "James & Emily Carter"). The `advisor_clients` record links to the **primary account holder**. The spouse is implicitly included.
- **Module status dots:** Reflect the **household** view — if either spouse has data in a module, it counts. Joint assets (single record with `joint_owner_id`) are counted once, not double-counted.
- **Impersonation:** Entering a coupled client's profile shows the same household view the client sees (including spouse's data where `hasAcceptedSpousePermission()` is true). The advisor does not need a separate `advisor_clients` record for the spouse.
- **Activity logging:** Activities are logged against the primary client. The advisor can note spouse involvement in the `details` field.

### 2.9 Audit Trail

All advisor actions logged to existing `AuditLog` model using the existing `EVENT_ADMIN` constant:

```php
AuditLog::logAdmin('enter_client', null, [
    'advisor_id' => $advisor->id,
    'client_id' => $client->id,
]);
```

- `event_type: AuditLog::EVENT_ADMIN` (uses existing constant — consistent with admin action logging)
- `action:` one of `'enter_client'`, `'exit_client'`, `'view_client'`, `'edit_client_data'`, `'log_activity'`, `'send_report'`
- `metadata: { advisor_id, client_id, ... }`

The `action` field values distinguish advisor actions from regular admin actions. If in future a dedicated constant is needed, add `public const EVENT_ADVISOR = 'advisor'` to `AuditLog`.

---

## Shared Touchpoints & Conflict Management

### Files Both Features Touch

| File | Admin Changes | Advisor Changes | Strategy |
|------|--------------|-----------------|----------|
| `routes/api.php` | Admin decision matrix + savings/tax action routes | Advisor route group | Merge admin first, advisor rebases |
| `User.php` model | (no changes) | Add `is_advisor` to $guarded/$casts, `advisorClients()`, `advisors()`, `isAdvisor()` | Advisor-only |
| `router/index.js` | (no changes) | Add `/advisor/*` routes + `requiresAdvisor` guard | Advisor-only |
| `AdminPanel.vue` | Add Decision Matrix tab, enhance User Management | (no changes) | Admin-only |
| `PreviewWriteInterceptor.php` | (no changes) | Add advisor routes to `EXCLUDED_ROUTES` | Advisor-only |
| `AuditLog.php` | (no changes) | (no changes — uses existing `EVENT_ADMIN`) | No conflict |
| `Role.php` | (no changes) | Add `ROLE_ADVISOR` constant | Advisor-only |
| `store/modules/auth.js` | (no changes) | Add `isAdvisor` getter | Advisor-only |

### Merge Order

1. Merge `feature/admin-enhancement` into `main`
2. Run `php artisan db:seed`
3. Rebase `feature/advisor-dashboard` onto updated `main`
4. Resolve any conflicts (expected to be minimal — different files)
5. Merge `feature/advisor-dashboard` into `main`
6. Run `php artisan migrate && php artisan db:seed`

---

## Design System Compliance

Both features strictly follow `fynlaDesignGuide.md` v1.2.0:

- **Colours:** raspberry-*, horizon-*, spring-*, violet-*, savannah-*, eggshell-*, neutral-500, light-gray, light-blue-* only
- **No banned colours:** No amber, orange, mustard, neon, pure black
- **No accent borders:** No `border-l-4` side indicators (banned per updated guide)
- **Typography:** Segoe UI, weights 900/700/600/400
- **Cards:** `bg-white p-6 rounded-card shadow-card border border-light-gray`
- **Buttons:** Primary raspberry-500, secondary white/light-gray, rounded-button (8px)
- **Inputs:** Focus ring violet-500, border-light-gray
- **Tables:** Hover `bg-savannah-100`, border-light-gray rows
- **Badges:** rounded-full, semantic colours (spring for success, raspberry for error/danger, violet for warning/info)
- **Spacing:** 4px base, p-6 cards, gap-6 grids

---

## Mockups

- Admin Decision Tree: `March/March17Updates/Admin/decision-tree-mockup.html`
- Advisor Dashboard: `March/March17Updates/Advisor/advisor-dashboard-mockup.html`

---

## Future Enhancements (Marked for Later)

1. **Decision Tree — Full Visual Editor (B upgrade):** Drag-and-drop node editing, rewiring connections, adding/removing nodes directly on the tree canvas
2. **Client Activities — Automated Logging (B upgrade):** Email integration, calendar sync, automated report generation writing to the same `client_activities` schema
3. **Advisor Profile Model:** Separate `Advisor` model with FCA number, firm name, qualifications — when multi-advisor support is needed
