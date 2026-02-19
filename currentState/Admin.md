# Admin Module Documentation

## 1. System Overview

The Admin module provides system administration capabilities for the Fynla application, encompassing user management, database backup/restore operations, tax configuration management, and subscription monitoring. It is a cross-cutting module that does not correspond to any single financial planning domain but instead underpins the operational infrastructure of the platform.

**Key Characteristics:**

- Protected by the `IsAdmin` middleware checking the `User.is_admin` boolean flag
- No dedicated Vuex store or Agent; state is managed locally within Vue components
- The frontend is a single-page tabbed interface at `/admin` with four tabs: Dashboard, User Management, Database Backups, and Tax Settings
- Backend is served by `AdminController` (user/dashboard/backup operations) and `TaxSettingsController` (tax configuration CRUD)
- A parallel RBAC system (roles, permissions, role_permission pivot) exists in the database but is not yet wired into admin route protection -- all admin routes currently use the simpler `IsAdmin` middleware

**File Inventory:**

| Layer | File | Lines |
|-------|------|-------|
| Controller | `app/Http/Controllers/Api/AdminController.php` | 538 |
| Controller | `app/Http/Controllers/Api/TaxSettingsController.php` | ~300 |
| Service | `app/Services/Admin/DatabaseMetricsService.php` | 109 |
| Service | `app/Services/Auth/PermissionService.php` | 198 |
| Middleware | `app/Http/Middleware/IsAdmin.php` | 29 |
| Middleware | `app/Http/Middleware/HasRole.php` | 48 |
| Middleware | `app/Http/Middleware/HasPermission.php` | 48 |
| Model | `app/Models/Role.php` | 137 |
| Model | `app/Models/Permission.php` | 91 |
| Migration | `database/migrations/2026_01_19_140501_create_roles_permissions_tables.php` | 63 |
| Seeder | `database/seeders/RolesPermissionsSeeder.php` | 25 |
| Validation | `app/Http/Requests/StoreTaxConfigurationRequest.php` | 116 |
| Vue View | `resources/js/views/Admin/AdminPanel.vue` | 158 |
| Vue Component | `resources/js/components/Admin/AdminDashboard.vue` | 298 |
| Vue Component | `resources/js/components/Admin/UserManagement.vue` | 504 |
| Vue Component | `resources/js/components/Admin/UserFormModal.vue` | 360 |
| Vue Component | `resources/js/components/Admin/DatabaseBackup.vue` | 402 |
| Vue Component | `resources/js/components/Admin/TaxSettings.vue` | 1701 |
| API Service | `resources/js/services/adminService.js` | 47 |
| API Service | `resources/js/services/taxSettingsService.js` | 36 |

---

## 2. Database Schema

### 2.1 roles Table

Created by migration `2026_01_19_140501_create_roles_permissions_tables.php`.

```sql
CREATE TABLE roles (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(50) UNIQUE NOT NULL,    -- 'user', 'support', 'admin'
    display_name    VARCHAR(100) NOT NULL,
    description     VARCHAR(255) NULLABLE,
    level           INT DEFAULT 0,                  -- Higher = more permissions
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP
);
```

**Seeded Roles:**

| name | display_name | level |
|------|-------------|-------|
| `user` | User | 0 |
| `support` | Support | 50 |
| `admin` | Administrator | 100 |

### 2.2 permissions Table

```sql
CREATE TABLE permissions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) UNIQUE NOT NULL,   -- e.g., 'users.view'
    display_name    VARCHAR(100) NOT NULL,
    description     VARCHAR(255) NULLABLE,
    category        VARCHAR(50) NULLABLE,           -- For grouping in UI
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP
);
```

**Seeded Permissions:**

| name | display_name | category |
|------|-------------|----------|
| `users.view` | View Users | users |
| `users.edit` | Edit Users | users |
| `users.delete` | Delete Users | users |
| `users.impersonate` | Impersonate Users | users |
| `admin.access` | Access Admin Panel | admin |
| `admin.audit.view` | View Audit Logs | admin |
| `admin.tax_config` | Manage Tax Configuration | admin |
| `admin.erasure_process` | Process Erasure Requests | admin |
| `settings.view` | View Settings | settings |
| `settings.edit` | Edit Settings | settings |

**Note:** The `Permission` model also defines constants for a `content` category (`CONTENT_VIEW`, `CONTENT_CREATE`, `CONTENT_EDIT`, `CONTENT_DELETE`) but these are NOT seeded by `syncDefaultRolesAndPermissions`. They exist as placeholders for future use.

### 2.3 role_permission Pivot Table

```sql
CREATE TABLE role_permission (
    role_id         BIGINT UNSIGNED,    -- FK -> roles.id (CASCADE DELETE)
    permission_id   BIGINT UNSIGNED,    -- FK -> permissions.id (CASCADE DELETE)
    PRIMARY KEY (role_id, permission_id)
);
```

**Seeded Assignments:**

- **support** role: `users.view`, `admin.access`, `admin.audit.view` (3 permissions)
- **admin** role: ALL permissions (all 10)
- **user** role: No permissions assigned

### 2.4 users Table (role_id Column)

The same migration adds a `role_id` foreign key to the `users` table:

```sql
ALTER TABLE users ADD role_id BIGINT UNSIGNED NULLABLE AFTER id
    CONSTRAINT FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL;
```

This means each user belongs to zero or one role. The `User` model has:
- `role(): BelongsTo` relationship to `Role`
- `is_admin: boolean` column (pre-existing, still the primary access gate)

---

## 3. Models

### 3.1 Role Model

**File:** `app/Models/Role.php` (137 lines)

**Constants:**

```php
// Role names
public const ROLE_USER    = 'user';
public const ROLE_SUPPORT = 'support';
public const ROLE_ADMIN   = 'admin';

// Role levels (higher = more permissions)
public const LEVEL_USER    = 0;
public const LEVEL_SUPPORT = 50;
public const LEVEL_ADMIN   = 100;
```

**Fillable:** `name`, `display_name`, `description`, `level`

**Casts:** `level` -> `integer`

**Relationships:**
- `permissions(): BelongsToMany` via `role_permission` pivot
- `users(): HasMany` -> `User`

**Key Methods:**

| Method | Signature | Description |
|--------|-----------|-------------|
| `hasPermission` | `(string $permissionName): bool` | Checks if this role has a specific permission |
| `hasAnyPermission` | `(array $permissions): bool` | Checks if this role has any of the given permissions |
| `hasAllPermissions` | `(array $permissions): bool` | Checks if this role has all of the given permissions |
| `givePermission` | `(Permission $permission): void` | Attaches a permission (idempotent via `syncWithoutDetaching`) |
| `removePermission` | `(Permission $permission): void` | Detaches a permission |
| `syncPermissions` | `(array $permissionIds): void` | Replaces all permissions |
| `isAtLeast` | `(int $level): bool` | Checks if the role's level >= given level |
| `findByName` | `static (string $name): ?self` | Looks up role by `name` column |
| `getUserRole` | `static (): ?self` | Returns the 'user' role |
| `getSupportRole` | `static (): ?self` | Returns the 'support' role |
| `getAdminRole` | `static (): ?self` | Returns the 'admin' role |

### 3.2 Permission Model

**File:** `app/Models/Permission.php` (91 lines)

**Category Constants:**

```php
public const CATEGORY_USERS    = 'users';
public const CATEGORY_CONTENT  = 'content';
public const CATEGORY_SETTINGS = 'settings';
public const CATEGORY_ADMIN    = 'admin';
```

**Permission Constants:**

```php
// Users
public const USERS_VIEW        = 'users.view';
public const USERS_EDIT        = 'users.edit';
public const USERS_DELETE      = 'users.delete';
public const USERS_IMPERSONATE = 'users.impersonate';

// Content (defined but NOT seeded)
public const CONTENT_VIEW   = 'content.view';
public const CONTENT_CREATE = 'content.create';
public const CONTENT_EDIT   = 'content.edit';
public const CONTENT_DELETE = 'content.delete';

// Settings
public const SETTINGS_VIEW = 'settings.view';
public const SETTINGS_EDIT = 'settings.edit';

// Admin
public const ADMIN_ACCESS         = 'admin.access';
public const ADMIN_AUDIT_VIEW     = 'admin.audit.view';
public const ADMIN_TAX_CONFIG     = 'admin.tax_config';
public const ADMIN_ERASURE_PROCESS = 'admin.erasure_process';
```

**Fillable:** `name`, `display_name`, `description`, `category`

**Relationships:**
- `roles(): BelongsToMany` via `role_permission` pivot

**Key Methods:**

| Method | Signature | Description |
|--------|-----------|-------------|
| `findOrCreateByName` | `static (string $name, string $displayName, ?string $category): self` | First-or-create lookup |
| `findByName` | `static (string $name): ?self` | Looks up permission by `name` |
| `inCategory` | `static (string $category): Collection` | Returns all permissions in a category |

---

## 4. Controllers

### 4.1 AdminController

**File:** `app/Http/Controllers/Api/AdminController.php` (538 lines)

**Uses Trait:** `SanitizedErrorResponse` (all catch blocks call `$this->safeErrorResponse()`)

**Constructor Injection:** `DatabaseMetricsService $databaseMetrics`

#### 4.1.1 dashboard() -> JsonResponse

Returns aggregated system statistics. All user queries filter `WHERE is_preview_user = false` to exclude seeded personas.

**Response payload:**
```json
{
  "success": true,
  "data": {
    "total_users": 42,
    "admin_users": 2,
    "linked_spouses": 15,
    "recent_users": [/* last 5 users: id, first_name, surname, email, created_at */],
    "database_size": "12.5 MB",
    "last_backup": "2026-02-18 14:30:00"
  }
}
```

**Implementation details:**
- `linked_spouses` divides the count by 2 because each spouse pair has two records with non-null `spouse_id`
- `database_size` delegates to `DatabaseMetricsService::getDatabaseSize()`
- `last_backup` calls the private `getLastBackupTime()` helper which scans `storage/app/backups/*.sql` for the most recent `filemtime`

#### 4.1.2 getSubscriptionStats() -> JsonResponse

Returns subscription and revenue aggregates.

**Response payload:**
```json
{
  "success": true,
  "data": {
    "trialing": 5,
    "active": 20,
    "expired": 3,
    "cancelled": 2,
    "total_revenue": 150000
  }
}
```

**Important:** `total_revenue` is the sum of `Payment.amount` where `status = 'completed'`. Amounts are stored in **pence** (integer). The frontend divides by 100 for display: `(subStats.total_revenue / 100).toFixed(2)`.

#### 4.1.3 getUsers(Request $request) -> JsonResponse

Paginated, searchable user listing. Excludes preview users.

**Query Parameters:**
- `per_page` (optional, integer, 1-100, default 15)
- `search` (optional, string, max 100 chars)

**Security measures:**
- Search input is truncated to 100 characters as an extra safety measure
- LIKE wildcards (`%` and `_`) are escaped with `str_replace` to prevent unintended pattern matching
- Searches across `first_name`, `surname`, and `email` using OR conditions

**Eager Loads:** `spouse:id,first_name,surname,email`, `subscription`, `subscription.payments`

**Response:** Laravel paginator JSON wrapping `users` with `total`, `last_page`, `current_page`, etc.

#### 4.1.4 createUser(Request $request) -> JsonResponse

Creates a new user account.

**Validation:**
- `name`: required, string, max 255
- `email`: required, email, unique in users table
- `password`: required, min 8, regex: `^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$`
  - Must contain at least one uppercase, one lowercase, one digit, and one special character
- `is_admin`: optional boolean

**Implementation:** Hashes password with `Hash::make()`, returns 201 on success.

#### 4.1.5 updateUser(Request $request, int $id) -> JsonResponse

Partial update of user details. All fields are `sometimes` validated (only validated if present in request).

**Updatable fields:** `name`, `email`, `password`, `is_admin`

Each field is individually checked with `$request->has()` before assignment, allowing true partial updates.

#### 4.1.6 deleteUser(int $id) -> JsonResponse

Deletes a user account with last-admin protection.

**Guard:** If the target user `is_admin` and there is only one admin in the system (`User::where('is_admin', true)->count() === 1`), the deletion returns 422 with message "Cannot delete the last admin user".

#### 4.1.7 createBackup() -> JsonResponse

Creates a MySQL database backup using `mysqldump`.

**Security implementation:**
1. Creates `storage/app/backups` directory with `0750` permissions (owner + group only)
2. Writes a temporary `.my.cnf` file containing database credentials at `storage/app/backups/.my.cnf.<uniqid>`
3. Sets file permissions to `0600` (owner read/write only) so credentials are not visible to other users
4. Runs `mysqldump --defaults-extra-file=<configFile> <database> > <backupFile>` -- credentials never appear in the process list
5. Immediately deletes the temporary config file in a `finally`-equivalent flow
6. Returns backup metadata including filename, path, human-readable size, and creation timestamp

**Filename pattern:** `backup_YYYY-MM-DD_HH-II-SS.sql`

#### 4.1.8 listBackups() -> JsonResponse

Lists all `.sql` files in `storage/app/backups`, sorted newest first.

Returns array of objects: `{ filename, size, created_at, path }`.

#### 4.1.9 restoreBackup(Request $request) -> JsonResponse

Restores a database from a backup file.

**Security (3-layer):**
1. **Regex validation:** Filename must match `^backup_[\d\-_]+\.sql$`
2. **basename():** Strips any directory traversal from the filename
3. **realpath() boundary check:** Verifies the resolved real path starts with the backups directory path, preventing symlink attacks

**Post-restore:** Clears `cache` and `config` caches via Artisan commands.

Uses the same temporary `.my.cnf` approach as `createBackup()` for credential security.

#### 4.1.10 deleteBackup(Request $request) -> JsonResponse

Deletes a backup file with the same 3-layer security as `restoreBackup()`:
- Regex filename validation
- `basename()` sanitisation
- `realpath()` boundary check

#### 4.1.11 getLastBackupTime() -> ?string (private)

Scans `storage/app/backups/*.sql` for the file with the highest `filemtime` value. Returns formatted datetime string or null.

### 4.2 TaxSettingsController

**File:** `app/Http/Controllers/Api/TaxSettingsController.php`

**Uses Trait:** `SanitizedErrorResponse`

Manages CRUD operations on `TaxConfiguration` records with audit logging via `TaxConfigurationAudit`.

**Key methods:**
- `getCurrent()` -- Returns the active (`is_active = true`) tax configuration with `config_data` flattened into the response
- `getAll()` -- Returns all tax configurations for version management
- `getCalculations()` -- Returns calculated tax values
- `create()` -- Creates a new tax configuration (uses `StoreTaxConfigurationRequest`)
- `update($id)` -- Updates an existing configuration
- `setActive($id)` -- Activates a configuration (deactivates all others in a transaction)
- `duplicate($id)` -- Duplicates a configuration with a new tax year
- `delete($id)` -- Deletes a non-active configuration

All mutation operations log an audit record via `TaxConfigurationAudit::log()`.

---

## 5. Agent

**No dedicated Agent.** The Admin module does not use the Agent pattern employed by the financial planning modules (ProtectionAgent, SavingsAgent, etc.). Controller methods directly interact with models and services.

---

## 6. Services

### 6.1 DatabaseMetricsService

**File:** `app/Services/Admin/DatabaseMetricsService.php` (109 lines)

Provides database size and statistics information via MySQL `information_schema`.

**Methods:**

| Method | Signature | Description |
|--------|-----------|-------------|
| `getDatabaseSizeInBytes` | `(): int` | Sums `data_length + index_length` from `information_schema.TABLES` for the current database |
| `getDatabaseSize` | `(): string` | Returns human-readable size (e.g., "12.5 MB") or "Unknown" if zero |
| `getTableStatistics` | `(): array` | Returns per-table breakdown: name, rows, data_size, index_size, total_size. **Defined but never called** from any controller or route. |
| `getConnectionInfo` | `(): array` | Returns driver, host, port, database, and connection name from config |
| `formatBytes` | `(int $bytes, int $precision = 2): string` | Converts bytes to human-readable string (B/KB/MB/GB/TB) |

**Usage:** Injected into `AdminController` via constructor. Only `getDatabaseSize()` and `formatBytes()` are actively called.

### 6.2 PermissionService

**File:** `app/Services/Auth/PermissionService.php` (198 lines)

Provides the permission-checking logic consumed by `HasRole` and `HasPermission` middleware.

**Methods:**

| Method | Signature | Description |
|--------|-----------|-------------|
| `hasRole` | `(User $user, string $roleName): bool` | Checks `$user->role->name === $roleName` |
| `hasAnyRole` | `(User $user, array $roleNames): bool` | Checks if user's role name is in the array |
| `hasPermission` | `(User $user, string $permissionName): bool` | Admins auto-pass; otherwise delegates to `Role::hasPermission()` |
| `hasAnyPermission` | `(User $user, array $permissions): bool` | Admins auto-pass; otherwise delegates to `Role::hasAnyPermission()` |
| `hasAllPermissions` | `(User $user, array $permissions): bool` | Admins auto-pass; otherwise delegates to `Role::hasAllPermissions()` |
| `isAtLeastLevel` | `(User $user, int $level): bool` | Admins auto-pass; checks role level >= given level |
| `isAdmin` | `(User $user): bool` | Returns true if `is_admin` flag OR has 'admin' role |
| `isSupport` | `(User $user): bool` | Returns true if admin OR has 'support' role |
| `assignRole` | `(User $user, string $roleName): void` | Looks up `Role` by name, sets `user.role_id` |
| `removeRole` | `(User $user): void` | Sets `user.role_id = null` |
| `getUserPermissions` | `(User $user): array` | Admins get ALL permissions; others get their role's permissions |
| `syncDefaultRolesAndPermissions` | `(): void` | Creates/syncs the 3 default roles and 10 default permissions |

**Admin Bypass Pattern:** Every permission check method first tests `$user->is_admin` and returns `true` immediately, ensuring backwards compatibility with the boolean flag approach.

---

## 7. Validation Requests

### 7.1 StoreTaxConfigurationRequest

**File:** `app/Http/Requests/StoreTaxConfigurationRequest.php` (116 lines)

Used by `TaxSettingsController::create()` for creating new tax configurations.

**Authorisation:** `$this->user() && $this->user()->is_admin` (must be admin)

**Key Validation Rules:**

| Field | Rules |
|-------|-------|
| `tax_year` | Required, string, regex `^\d{4}\/\d{2}$`, unique per row |
| `effective_from` | Required, date |
| `effective_to` | Required, date, must be after `effective_from` |
| `config_data` | Required, array |
| `config_data.income_tax.personal_allowance` | Required, numeric, min 0 |
| `config_data.income_tax.bands` | Required, array, min 3 items |
| `config_data.income_tax.bands.*.rate` | Required, numeric, 0-1 |
| `config_data.national_insurance.class_1_employee.*` | Various thresholds and rates |
| `config_data.isa.annual_allowance` | Required, numeric, min 0 |
| `config_data.pension.annual_allowance` | Required, numeric, min 0 |
| `config_data.pension.mpaa` | Required, numeric, min 0 |
| `config_data.inheritance_tax.nil_rate_band` | Required, numeric, min 0 |
| `config_data.inheritance_tax.standard_rate` | Required, numeric, 0-1 |
| `config_data.capital_gains_tax.annual_exempt_amount` | Required, numeric, min 0 |
| `config_data.dividend_tax.allowance` | Required, numeric, min 0 |

**Custom Messages:** Provides human-readable error messages for regex, rate ranges, and minimum values.

### 7.2 AdminController Inline Validation

The `AdminController` does not use dedicated FormRequest classes. Instead, it uses `Validator::make()` inline within each method:

- **createUser:** Validates `name`, `email`, `password` (with regex), `is_admin`
- **updateUser:** Same fields with `sometimes` rule for partial updates
- **getUsers:** Validates `per_page` and `search` query parameters
- **restoreBackup / deleteBackup:** Validates `filename` with regex pattern

---

## 8. Vuex Store

**No dedicated admin Vuex store module.** All admin state is managed locally within individual Vue components using `data()` properties.

The only Vuex integration is the `auth` store getter used by the router guard and `AdminPanel.vue`:

```javascript
// resources/js/store/modules/auth.js
isAdmin: (state) => state.user?.is_admin === true || state.user?.is_admin === 1,
```

This getter handles both boolean and integer representations of the `is_admin` flag.

---

## 9. API Services

### 9.1 adminService.js

**File:** `resources/js/services/adminService.js` (47 lines)

Wraps all `AdminController` endpoints.

```javascript
export default {
  // Dashboard
  getDashboard()                     // GET  /api/admin/dashboard

  // User Management
  getUsers(params = {})              // GET  /api/admin/users?page=&per_page=&search=
  createUser(userData)               // POST /api/admin/users
  updateUser(userId, userData)       // PUT  /api/admin/users/:id
  deleteUser(userId)                 // DELETE /api/admin/users/:id

  // Subscription Stats
  getSubscriptionStats()             // GET  /api/admin/subscriptions/stats

  // Database Backup
  createBackup()                     // POST /api/admin/backup/create
  listBackups()                      // GET  /api/admin/backup/list
  restoreBackup(filename)            // POST /api/admin/backup/restore
  deleteBackup(filename)             // DELETE /api/admin/backup/delete
};
```

**Note:** `deleteBackup()` passes the filename via `{ data: { filename } }` as Axios requires the `data` key for DELETE request bodies.

### 9.2 taxSettingsService.js

**File:** `resources/js/services/taxSettingsService.js` (36 lines)

Wraps all `TaxSettingsController` endpoints.

```javascript
export default {
  getCurrent()                       // GET  /api/tax-settings/current
  getAll()                           // GET  /api/tax-settings/all
  getCalculations()                  // GET  /api/tax-settings/calculations
  create(configData)                 // POST /api/tax-settings/create
  update(configId, configData)       // PUT  /api/tax-settings/:id
  setActive(configId)                // POST /api/tax-settings/:id/activate
  duplicate(configId, data)          // POST /api/tax-settings/:id/duplicate
  delete(configId)                   // DELETE /api/tax-settings/:id
};
```

---

## 10. Frontend Components

### 10.1 AdminPanel.vue (View)

**File:** `resources/js/views/Admin/AdminPanel.vue` (158 lines)

The top-level view that renders the Admin Panel page. Acts as a tab container.

**Tabs:**

| Tab ID | Label | Component |
|--------|-------|-----------|
| `dashboard` | Dashboard | `AdminDashboard` |
| `users` | User Management | `UserManagement` |
| `backups` | Database Backups | `DatabaseBackup` |
| `tax-settings` | Tax Settings | `TaxSettings` |

**Features:**
- Administrator badge in the header (red shield icon)
- Tab navigation with SVG icons per tab, responsive short labels on mobile
- Client-side admin check on `mounted()`: redirects to `/dashboard` if `currentUser.is_admin` is falsy
- Uses `mapGetters('auth', ['currentUser'])` from the auth Vuex store

### 10.2 AdminDashboard.vue

**File:** `resources/js/components/Admin/AdminDashboard.vue` (298 lines)

Displays 8 statistics cards in two rows plus a recent users table.

**Row 1 -- User Statistics (4 cards):**
- Total Users (blue icon)
- Administrators (red icon)
- Linked Spouses (green icon)
- Database Size (purple icon)

**Row 2 -- Subscription Statistics (4 cards):**
- Trialing (blue clock icon)
- Active (green check icon)
- Expired (red X icon)
- Total Revenue (emerald currency icon) -- displays `(total_revenue / 100).toFixed(2)` for pence-to-pounds conversion

**Recent Users Table:**
- Shows last 5 users (ID, Name, Email, Created At)
- Dates formatted with `en-GB` locale

**Backup Status Card:**
- Blue info card showing last backup time or "No backups created yet"
- Refresh Dashboard button that reloads both `loadDashboard()` and `loadSubscriptionStats()`

**Data loading:** Subscription stats load in parallel with dashboard stats. Subscription stats fail silently (supplementary data).

### 10.3 UserManagement.vue

**File:** `resources/js/components/Admin/UserManagement.vue` (504 lines)

Full CRUD interface for managing user accounts.

**Features:**
- **Search:** Text input with 500ms debounce, searches name and email
- **Status Filter:** Dropdown for subscription status (trialing, active, expired, cancelled)
- **Create User button:** Opens `UserFormModal` in create mode

**Table Columns (11 columns):**

| Column | Description |
|--------|-------------|
| ID | User ID |
| Name | Full name |
| Email | Email address |
| Role | Badge: red "Admin" or grey "User" |
| Spouse | Green linked icon + spouse name, or dash |
| Plan | Badge with plan name (free/student/standard/pro) |
| Status | Subscription status badge (trialing=blue, active=green, expired=red, cancelled=grey) |
| Trial | Day counter "Day X/7" for trialing users, "Ended" for expired |
| Payment | Last completed payment date and amount (pence / 100) |
| Created | Account creation date |
| Actions | Edit (pencil icon) and Delete (trash icon) buttons |

**Pagination:**
- Server-side pagination with `per_page=15`
- Previous/Next navigation with mobile and desktop layouts
- Shows "Showing X to Y of Z users"

**Delete Protection:**
- Client-side guard: delete button is disabled if user is admin and `totalAdmins === 1`
- Confirmation dialog using `ConfirmDialog` component before deletion
- Success messages auto-clear after 3 seconds

**Timeout Cleanup:** Both `searchTimeout` and `messageTimeout` are cleared in `beforeUnmount()`.

### 10.4 UserFormModal.vue

**File:** `resources/js/components/Admin/UserFormModal.vue` (360 lines)

Modal form for creating and editing users.

**Props:**
- `show: Boolean` (required) -- controls modal visibility
- `user: Object` (default null) -- when non-null with an `id`, the modal operates in edit mode

**Form Fields:**

| Field | Create Mode | Edit Mode |
|-------|-------------|-----------|
| Name | Required | Editable |
| Email | Required | Editable |
| Password | Required (min 8 chars) | Hidden |
| Confirm Password | Required (must match) | Hidden |
| Administrator checkbox | Available | Available |
| Reset Password checkbox | Hidden | Available |

**Validation (client-side):**
- Name: required, non-empty
- Email: required, valid format (`/^[^\s@]+@[^\s@]+\.[^\s@]+$/`)
- Password (create only): required, min 8 chars
- Password confirmation: required, must match password

**Form Pattern:** Uses `@submit.prevent="submitForm"` on the internal `<form>` element. The `submitForm()` method validates, builds the payload, and emits `save` event to the parent. This follows the project's standard two-part form modal pattern (form prevent + emit `save`).

**Loading State:** Submit button shows a spinner and "Saving..." text while `submitting` is true.

### 10.5 TaxSettings.vue

**File:** `resources/js/components/Admin/TaxSettings.vue` (1701 lines)

The largest component in the Admin module. Provides a comprehensive tax configuration editor.

**Internal Tabs (6 tabs):**

| Tab ID | Label | Content |
|--------|-------|---------|
| `income-ni` | Income Tax & National Insurance | Personal allowance, income tax bands, NI Class 1/2/4 rates and thresholds |
| `savings-investments` | Savings & Investments | ISA allowances, Capital Gains Tax rates and exempt amounts, Dividend tax rates |
| `pensions` | Pensions | Annual allowance, Money Purchase Annual Allowance, tapered allowance thresholds, state pension, lifetime allowance |
| `inheritance-tax` | Inheritance Tax | Nil rate band, Residence Nil Rate Band, standard/reduced rates, gifting exemptions, taper relief |
| `property` | Property/Stamp Duty Land Tax | Stamp Duty Land Tax bands for standard/additional properties, first-time buyer relief thresholds |
| `versions` | Version Management | Table of all configurations with Activate/Duplicate/Delete actions |

**Edit Mode:**
- "Edit Configuration" button toggles `isEditing` state
- All fields switch between display-only (`<p>`) and editable (`<input>`) based on `isEditing`
- Deep-clones `currentConfig` into `editableConfig` on edit start (`JSON.parse(JSON.stringify(...))`)
- "Save Changes" button disabled until `isFormValid` computed property returns true
- "Cancel" reverts to display mode without saving

**Validation (comprehensive `validateConfig()`):**
- Income tax: personal allowance > 0, at least 3 bands, rates 0-100
- National Insurance: all rates must be 0-1 (decimal format)
- Inheritance Tax: standard_rate and reduced_rate 0-1
- Positive amount checks on ISA allowance, pension allowance, nil rate band, RNRB, CGT exempt amount

**Version Management Tab:**
- Lists all `TaxConfiguration` records in a table (tax year, effective period, status)
- Active config highlighted with blue background and "Active" badge
- **Activate:** Makes a config the active one (deactivates all others)
- **Duplicate:** Opens modal to specify new tax year and effective dates, copies all config data
- **Delete:** Only available for inactive configs
- **Create New Tax Year:** Button opens create modal -- but this is a **STUB** (creates empty config structure, must use Duplicate for production use)

**Data Loading:** Uses `Promise.all` to load current config and all configs in parallel.

### 10.6 DatabaseBackup.vue

**File:** `resources/js/components/Admin/DatabaseBackup.vue` (402 lines)

Manages the full backup lifecycle: create, list, restore, and delete.

**Features:**
- "Create New Backup" button with loading spinner
- Backup list table: filename, size, created at, actions (Restore/Delete)
- Empty state with database icon when no backups exist
- Refresh button to reload the backup list
- Warning card with important notes about storage location and data overwrite risks

**Confirmation Dialogs:**
- Restore: Warning type, "Restore Backup" confirm text, with loading state
- Delete: Danger type, "Delete Backup" confirm text, with loading state

Both dialogs use the shared `ConfirmDialog` component.

**Format Helpers:**
- `formatFileSize()`: Converts bytes to human-readable (mirrors the backend `formatBytes()`)
- `formatDate()`: en-GB locale with time (hours, minutes, seconds)

---

## 11. Frontend Routing

**File:** `resources/js/router/index.js`

```javascript
{
    path: '/admin',
    name: 'AdminPanel',
    component: AdminPanel,  // Lazy-loaded: () => import('@/views/Admin/AdminPanel.vue')
    meta: {
        requiresAuth: true,
        requiresAdmin: true,
        breadcrumb: [
            { label: 'Home', path: '/dashboard' },
            { label: 'Admin Panel', path: '/admin' },
        ],
    },
}
```

**Navigation Guard (router.beforeEach):**

```javascript
const isAdmin = store.getters['auth/isAdmin'];
// ...
if (to.meta.requiresAdmin && !isAdmin) {
    next({ name: 'Dashboard' });
}
```

Non-admin users are silently redirected to the dashboard. Preview mode users cannot access admin routes (preview mode sets `is_admin` to false).

**Additional admin-protected routes:**
- `/uk-taxes` -- `requiresAdmin: true`
- `/debug-env` -- `requiresAdmin: true` + `devOnly: true` (additional `beforeEnter` guard checks environment)

---

## 12. Cross-Module Integration

### 12.1 Tax Configuration Consumption

The Tax Settings managed through the Admin module power calculations across all financial modules:

- **Protection Module:** Uses inheritance tax rates (NRB, RNRB) for estate shortfall calculations
- **Savings Module:** Uses ISA allowance for contribution limit checks
- **Investment Module:** Uses CGT rates and annual exempt amounts for tax-efficient investment analysis
- **Retirement Module:** Uses pension annual allowance, MPAA, tapered allowance thresholds, and state pension age
- **Estate Planning Module:** Uses inheritance tax rates, gifting exemptions, and taper relief percentages
- **Coordination Module:** Aggregates tax data across all modules for holistic planning

All modules access tax values through `TaxConfigService`, which reads from the active `TaxConfiguration` record.

### 12.2 User Management Dependencies

- **Spouse Linking:** User management displays spouse relationships. The `spouse` eager load in `getUsers()` shows linked pairs.
- **Subscription/Payment System:** User management surfaces subscription status and payment history. Amounts stored in pence.
- **Preview User Isolation:** All admin user queries filter `WHERE is_preview_user = false`, ensuring seeded persona data never appears in admin views.

### 12.3 Middleware Integration

The `IsAdmin` middleware is registered as the `admin` alias in `Kernel.php` and applied to three route groups:

```
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')         -> AdminController
Route::middleware(['auth:sanctum', 'admin'])->prefix('tax-settings')  -> TaxSettingsController
Route::middleware(['auth:sanctum', 'admin'])->prefix('uk-taxes')      -> UKTaxesController
```

---

## 13. Profile Completeness

**N/A.** The Admin module does not participate in the profile completeness system. Profile completeness is a user-facing feature that tracks onboarding progress across financial modules.

---

## 14. Seeder Data

### 14.1 RolesPermissionsSeeder

**File:** `database/seeders/RolesPermissionsSeeder.php` (25 lines)

```php
class RolesPermissionsSeeder extends Seeder
{
    public function __construct(private PermissionService $permissionService) {}

    public function run(): void
    {
        $this->permissionService->syncDefaultRolesAndPermissions();
        $this->command->info('Default roles and permissions seeded successfully.');
    }
}
```

Delegates entirely to `PermissionService::syncDefaultRolesAndPermissions()`. This method is idempotent, using `firstOrCreate` for roles and permissions, and `syncPermissions` for the pivot.

**Reseed command:**
```bash
php artisan db:seed --class=RolesPermissionsSeeder --force
```

**Data created:**

| Entity | Count |
|--------|-------|
| Roles | 3 (user, support, admin) |
| Permissions | 10 (across users, admin, settings categories) |
| Role-Permission assignments | 3 for support, 10 for admin, 0 for user |

---

## 15. API Routing

**File:** `routes/api.php`

All admin routes require `auth:sanctum` + `admin` middleware.

### 15.1 Admin Panel Routes (prefix: `/api/admin`)

| Method | URI | Controller Method | Rate Limited |
|--------|-----|-------------------|--------------|
| GET | `/admin/dashboard` | `AdminController@dashboard` | No |
| GET | `/admin/users` | `AdminController@getUsers` | No |
| POST | `/admin/users` | `AdminController@createUser` | No |
| PUT | `/admin/users/{id}` | `AdminController@updateUser` | No |
| DELETE | `/admin/users/{id}` | `AdminController@deleteUser` | No |
| GET | `/admin/subscriptions/stats` | `AdminController@getSubscriptionStats` | No |
| POST | `/admin/backup/create` | `AdminController@createBackup` | **Yes**: `throttle:3,1` |
| GET | `/admin/backup/list` | `AdminController@listBackups` | **Yes**: `throttle:3,1` |
| POST | `/admin/backup/restore` | `AdminController@restoreBackup` | **Yes**: `throttle:3,1` |
| DELETE | `/admin/backup/delete` | `AdminController@deleteBackup` | **Yes**: `throttle:3,1` |

**Rate Limiting:** Backup operations are wrapped in `throttle:3,1` (3 requests per minute) for security.

### 15.2 Tax Settings Routes (prefix: `/api/tax-settings`)

| Method | URI | Controller Method |
|--------|-----|-------------------|
| GET | `/tax-settings/current` | `TaxSettingsController@getCurrent` |
| GET | `/tax-settings/all` | `TaxSettingsController@getAll` |
| GET | `/tax-settings/calculations` | `TaxSettingsController@getCalculations` |
| POST | `/tax-settings/create` | `TaxSettingsController@create` |
| PUT | `/tax-settings/{id}` | `TaxSettingsController@update` |
| POST | `/tax-settings/{id}/activate` | `TaxSettingsController@setActive` |
| POST | `/tax-settings/{id}/duplicate` | `TaxSettingsController@duplicate` |
| DELETE | `/tax-settings/{id}` | `TaxSettingsController@delete` |

---

## 16. Key Constants

### 16.1 Role Constants (Role Model)

```php
Role::ROLE_USER    = 'user'
Role::ROLE_SUPPORT = 'support'
Role::ROLE_ADMIN   = 'admin'

Role::LEVEL_USER    = 0
Role::LEVEL_SUPPORT = 50
Role::LEVEL_ADMIN   = 100
```

### 16.2 Permission Constants (Permission Model)

```php
// Users category
Permission::USERS_VIEW        = 'users.view'
Permission::USERS_EDIT        = 'users.edit'
Permission::USERS_DELETE      = 'users.delete'
Permission::USERS_IMPERSONATE = 'users.impersonate'

// Content category (defined but NOT seeded)
Permission::CONTENT_VIEW   = 'content.view'
Permission::CONTENT_CREATE = 'content.create'
Permission::CONTENT_EDIT   = 'content.edit'
Permission::CONTENT_DELETE = 'content.delete'

// Settings category
Permission::SETTINGS_VIEW = 'settings.view'
Permission::SETTINGS_EDIT = 'settings.edit'

// Admin category
Permission::ADMIN_ACCESS         = 'admin.access'
Permission::ADMIN_AUDIT_VIEW     = 'admin.audit.view'
Permission::ADMIN_TAX_CONFIG     = 'admin.tax_config'
Permission::ADMIN_ERASURE_PROCESS = 'admin.erasure_process'
```

### 16.3 Password Regex

```
^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$
```

Requires: lowercase, uppercase, digit, and special character. Minimum 8 characters.

### 16.4 Backup Filename Regex

```
^backup_[\d\-_]+\.sql$
```

Only allows filenames matching this pattern for restore and delete operations.

### 16.5 Rate Limits

```
throttle:3,1  -- 3 requests per minute (backup operations)
```

---

## 17. Known Issues

### 17.1 Impersonation Endpoint Missing

`Permission::USERS_IMPERSONATE` is defined and seeded as a permission, but no controller endpoint or route implements user impersonation. The permission exists as a placeholder for a future feature.

### 17.2 "Create from Scratch" Tax Year is a Stub

The "Create New Tax Year" button in `TaxSettings.vue` opens a modal that accepts a tax year and date range, but when creating from scratch (not duplicating), the resulting configuration has an empty/default `config_data` structure. The practical workflow requires using the **Duplicate** action on an existing configuration and then editing the values. Creating from scratch would require manual entry of hundreds of fields.

### 17.3 HasRole / HasPermission Middleware Not Wired to Admin Routes

Despite having fully implemented `HasRole` and `HasPermission` middleware registered in `Kernel.php` as `role` and `permission` aliases, all admin routes still use the simpler `IsAdmin` middleware (the `admin` alias). The RBAC middleware is ready but not yet integrated into the route definitions. This means the `support` role and per-permission checks are currently unused in practice.

### 17.4 getTableStatistics() Never Called

`DatabaseMetricsService::getTableStatistics()` is fully implemented, returning per-table row counts and size breakdowns from `information_schema`. However, no controller method or route exposes this data. It could be surfaced in the admin dashboard for database health monitoring.

### 17.5 Subscription Revenue in Pence

`Payment.amount` is stored as an integer in pence (smallest currency unit). The `getSubscriptionStats()` endpoint returns the raw sum without conversion. The frontend handles the division:

```javascript
£{{ ((subStats.total_revenue || 0) / 100).toFixed(2) }}
```

Similarly, individual payment amounts in the user table:
```javascript
£{{ (lastPayment(user).amount / 100).toFixed(2) }}
```

This is intentional (standard practice for financial data storage) but worth noting for anyone working with the API directly.

### 17.6 Content Permissions Defined but Not Seeded

The `Permission` model defines four constants for a `content` category (`CONTENT_VIEW`, `CONTENT_CREATE`, `CONTENT_EDIT`, `CONTENT_DELETE`), but `syncDefaultRolesAndPermissions()` does not create these permission records. They remain as code-level placeholders.

### 17.7 Client-Side Admin Check Redundancy

`AdminPanel.vue` checks `currentUser.is_admin` on `mounted()` and redirects to `/dashboard`. This duplicates the router navigation guard which already checks `requiresAdmin` meta. The component-level check is a defence-in-depth measure but could be removed to reduce redundancy.

### 17.8 User Create Uses `name` Field

`AdminController::createUser()` accepts a `name` field and stores it directly, while the User model elsewhere uses `first_name` and `surname` as separate fields. The admin create path does not split the name into first/last components, which could lead to inconsistency if other parts of the application expect `first_name` and `surname` to be populated.

---

## 18. Deep Dive: Role-Based Access Control Architecture

### 18.1 Overview

Fynla implements a hybrid access control system that combines a legacy boolean flag (`User.is_admin`) with a modern role-based access control (RBAC) system. The RBAC system is fully implemented at the code level but not yet activated on routes, creating a two-tier architecture.

### 18.2 The Dual-Authority Model

**Layer 1 -- Boolean Flag (Active):**

```
User.is_admin = true  -->  IsAdmin middleware  -->  Admin routes
```

This is the currently active access control. The `IsAdmin` middleware simply checks:

```php
if (! $request->user() || ! $request->user()->is_admin) {
    return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
}
```

**Layer 2 -- RBAC (Implemented, Not Activated):**

```
User.role_id  -->  Role  --pivot-->  Permissions
                    |
                    v
              HasRole / HasPermission middleware
                    |
                    v
              PermissionService checks
```

### 18.3 Middleware Stack Detail

Three middleware classes are registered in `app/Http/Kernel.php`:

```php
'admin'      => \App\Http\Middleware\IsAdmin::class,
'role'       => \App\Http\Middleware\HasRole::class,
'permission' => \App\Http\Middleware\HasPermission::class,
```

**IsAdmin Middleware:**
- Checks `$request->user()->is_admin` boolean
- Returns 403 if falsy
- No interaction with the RBAC system

**HasRole Middleware:**
- Accepts variadic role names: `middleware('role:admin,support')`
- Delegates to `PermissionService::hasAnyRole()`
- Has a **legacy fallback**: if `$user->is_admin && in_array('admin', $roles)`, access is granted even if the user has no `role_id` set
- Returns 403 with message listing required roles

**HasPermission Middleware:**
- Accepts variadic permission names: `middleware('permission:users.view,users.edit')`
- **Admin bypass**: if `$user->is_admin`, access is always granted (short-circuit)
- Otherwise delegates to `PermissionService::hasAnyPermission()`
- Returns 403 with message listing required permissions

### 18.4 Role Hierarchy

The level system enables hierarchical checks:

```
admin (100) > support (50) > user (0)
```

`PermissionService::isAtLeastLevel()` allows checking if a user meets a minimum access tier:

```php
// Check if user is at least support level
$permissionService->isAtLeastLevel($user, Role::LEVEL_SUPPORT);
```

Admins auto-pass this check via the `is_admin` bypass.

### 18.5 Permission Assignment Matrix

| Permission | user | support | admin |
|------------|------|---------|-------|
| `users.view` | - | Yes | Yes |
| `users.edit` | - | - | Yes |
| `users.delete` | - | - | Yes |
| `users.impersonate` | - | - | Yes |
| `admin.access` | - | Yes | Yes |
| `admin.audit.view` | - | Yes | Yes |
| `admin.tax_config` | - | - | Yes |
| `admin.erasure_process` | - | - | Yes |
| `settings.view` | - | - | Yes |
| `settings.edit` | - | - | Yes |

The `user` role has no permissions assigned. This is intentional -- regular users access their own data through standard auth, not through admin permissions.

### 18.6 How the RBAC System Would Be Activated

To transition from the boolean flag to full RBAC, the following changes would be needed:

1. **Route definition changes:**
```php
// Current (boolean flag):
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(...)

// Future (RBAC):
Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin')->group(function () {
    Route::get('/users', ...)->middleware('permission:users.view');
    Route::post('/users', ...)->middleware('permission:users.edit');
    Route::delete('/users/{id}', ...)->middleware('permission:users.delete');
    // etc.
});
```

2. **Assign roles to existing admin users:**
```php
$permissionService->assignRole($adminUser, Role::ROLE_ADMIN);
```

3. **Support role would enable:** View-only access to the admin panel with user listing and audit log viewing, but no edit/delete/backup capabilities.

### 18.7 Frontend Access Control

The frontend enforces admin access at two levels:

1. **Router navigation guard** (`router.beforeEach`):
   - Reads `store.getters['auth/isAdmin']`
   - Redirects non-admin users to Dashboard for routes with `meta.requiresAdmin`

2. **Component-level guard** (`AdminPanel.vue mounted()`):
   - Reads `this.currentUser.is_admin`
   - Redirects to `/dashboard` if not admin
   - Defence-in-depth against direct component rendering

The frontend does **not** currently check roles or permissions -- it relies solely on the `is_admin` boolean from the auth store. When RBAC is activated on routes, the frontend would need to be updated to check specific permissions for conditional UI rendering (e.g., hiding the Delete button for support users who lack `users.delete`).

### 18.8 Security Considerations

1. **Admin bypass is consistent:** Both `PermissionService` methods and `HasPermission` middleware grant full access to users with `is_admin = true`, ensuring no regression when transitioning to RBAC.

2. **is_admin is guarded:** The `User` model has `is_admin` in the `$guarded` array, preventing mass assignment. Only explicit `$user->is_admin = true; $user->save()` can set it.

3. **Null-safe role access:** All permission checks use `$user->role?->` (null-safe operator), gracefully handling users without an assigned role.

4. **Role deletion safety:** The `role_id` foreign key has `ON DELETE SET NULL`, so deleting a role does not break user records -- they simply lose their role assignment.

5. **Seeder idempotency:** `syncDefaultRolesAndPermissions()` uses `firstOrCreate` and `syncPermissions`, making it safe to run repeatedly without creating duplicates.
