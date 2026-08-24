# RBAC Activation & Admin Link Fix

**Status:** IMPLEMENTED + CRITICAL BUG FIXED

## Bug Report

**Symptom:** Admin link not showing in navbar for chris@fynla.org despite `is_admin=true` in the database.

**Root Cause:** Three compounding issues:

### Issue 1: Legacy `role` column shadows RBAC relationship
The `users` table had both a legacy `role` VARCHAR column (values like `'user'`, `'admin'`) AND a `role_id` FK column (added by the RBAC migration). Eloquent attributes take precedence over relationships when names collide, so `$user->role` returned the string `'user'` instead of the `Role` BelongsTo model. This broke:
- `AuthController::user()` — `$user->role?->name` returned null (calling `->name` on a string)
- `PermissionService` — all `$user->role?->hasPermission()` calls failed
- `HasPermission` middleware — permission checks always failed
- `AdminController::getUsers()` — role data never loaded despite `with(['role'])`

### Issue 2: RolesPermissionsSeeder never ran
The migration `2026_02_20_120000_assign_roles_to_existing_users.php` ran but found no roles in the `roles` table (guard clause: `if (!$adminRoleId || !$userRoleId) return;`). The `RolesPermissionsSeeder` that creates the roles hadn't been executed.

### Issue 3: ADMIN_EMAILS not configured
The `.env` file had no `ADMIN_EMAILS` variable, so new registrations for chris@fynla.org or brett@fynla.org would not receive admin role automatically.

---

## Fix Applied

### 1. Drop legacy `role` column
**New file:** `database/migrations/2026_02_20_130000_drop_legacy_role_column_from_users.php`
- Drops the `users.role` VARCHAR column that was shadowing the `role()` BelongsTo relationship
- With the column gone, `$user->role` correctly resolves to the Role model via `role_id`

### 2. Seed roles + assign to existing users
```bash
php artisan db:seed --class=RolesPermissionsSeeder --force
```
Then manually ran the logic from the migration (which had already executed before roles existed):
- `users.is_admin=true` + `role_id IS NULL` → assigned admin role
- All other `role_id IS NULL` → assigned user role

### 3. Add ADMIN_EMAILS to .env
```
ADMIN_EMAILS=chris@fynla.org,brett@fynla.org
```
Referenced by `config/auth.php` → `auth.admin_emails`. Used in `AuthController::verifyCode()` during registration to auto-assign admin role.

### 4. Fix code references to legacy column

| File | Change |
|------|--------|
| `database/seeders/TestUsersSeeder.php` | `'role' => 'user'` → `'role_id' => Role::findByName(Role::ROLE_USER)?->id` |
| `tests/Feature/AdminRBACTest.php` | Use `role_id` instead of `role`, seed RolesPermissionsSeeder, fix assertions to check `data.role` |
| `resources/js/components/UserProfile/Settings.vue` | Replace `currentUser?.role` with computed `accountType` from Vuex `auth/role` getter |

---

## How Admin Access Now Works (End-to-End)

```
1. Login → AuthController::login()
2. Fetch user → AuthController::user()
   → $user->load('role.permissions')
   → returns { user, role: 'admin', permissions: [...] }
3. Vuex auth store
   → state.role = 'admin'
   → getter isAdmin = state.role === 'admin' → true
4. Navbar.vue
   → v-if="isAdmin" → shows Admin link
5. Router guard
   → to.meta.requiresAdmin && !isAdmin → allows access
6. API routes
   → middleware permission:admin.access checks Role→Permissions pivot
```

**Admin access is restricted to users with the `admin` role. Only chris@fynla.org and brett@fynla.org are configured as admin emails.**

---

## Original RBAC Context

The admin module had a dual-authority problem: a legacy `is_admin` boolean flag and a fully-built but unused RBAC system (roles, permissions, role_permission pivot, `HasRole`/`HasPermission` middleware). The RBAC activation (Phases 1-3 below) was implemented correctly but the legacy `role` column was never dropped, silently breaking the entire system.

---

## Phase 1: Database & Seed Foundation

### 1a. Migration: assign roles to existing users
**File:** `database/migrations/2026_02_20_120000_assign_roles_to_existing_users.php`
- `UPDATE users SET role_id = <admin_id> WHERE is_admin = true AND role_id IS NULL`
- `UPDATE users SET role_id = <user_id> WHERE role_id IS NULL`
- **Note:** Must run RolesPermissionsSeeder BEFORE this migration or it exits early

### 1b. Migration: drop legacy role column
**File:** `database/migrations/2026_02_20_130000_drop_legacy_role_column_from_users.php`
- Drops `users.role` VARCHAR column that shadowed the `role()` BelongsTo relationship

### 1c. Permissions seeded
`admin.backup` permission added. Unused constants removed (`USERS_IMPERSONATE`, content permissions).

### 1d. RolesPermissionsSeeder in DatabaseSeeder
Runs before AdminUserSeeder to ensure roles exist.

---

## Phase 2: Backend — RBAC as Single Authority

- `PermissionService`: All `is_admin` bypasses removed; checks go through role→permissions
- `HasPermission` middleware: No `is_admin` bypass
- `HasRole` middleware: No `is_admin` fallback
- Routes use `permission:admin.access`, `permission:admin.tax_config`, etc.
- `AuthController::user()` returns `{ user, role, permissions }` from RBAC
- `User::booted()` syncs `is_admin` flag when `role_id` changes (safety net)

---

## Phase 3: Frontend — RBAC-Driven UI

- Vuex `auth` store: `state.role`, `state.permissions`, getter `isAdmin: state.role === 'admin'`
- Navbar: `v-if="isAdmin"` for Admin link (desktop + mobile)
- Router: `requiresAdmin` meta checked against `auth/isAdmin` getter
- Settings.vue: Account type from Vuex role getter, not legacy column

---

## All Files Changed

| File | Change |
|------|--------|
| `database/migrations/2026_02_20_120000_assign_roles_to_existing_users.php` | Assign roles to existing users |
| `database/migrations/2026_02_20_130000_drop_legacy_role_column_from_users.php` | **New** — drop legacy `role` column |
| `database/seeders/DatabaseSeeder.php` | RolesPermissionsSeeder before AdminUserSeeder |
| `database/seeders/AdminUserSeeder.php` | Use `role_id` not `role` |
| `database/seeders/TestUsersSeeder.php` | Use `role_id` not `role` |
| `app/Models/Permission.php` | Add ADMIN_BACKUP, remove unused constants |
| `app/Models/User.php` | `booted()` syncs is_admin when role_id changes |
| `app/Services/Auth/PermissionService.php` | Remove is_admin bypasses |
| `app/Http/Middleware/HasPermission.php` | Remove is_admin bypass |
| `app/Http/Middleware/HasRole.php` | Remove is_admin fallback |
| `app/Http/Kernel.php` | Remove legacy `admin` middleware alias |
| `routes/api.php` | Permission-based middleware on all admin routes |
| `app/Http/Controllers/Api/AdminController.php` | RBAC-based CRUD, getRoles endpoint |
| `app/Http/Controllers/Api/AuthController.php` | Return role/permissions, assign role on registration |
| `app/Http/Requests/StoreTaxConfigurationRequest.php` | PermissionService instead of is_admin |
| `resources/js/services/authService.js` | Return `{user, role, permissions}` |
| `resources/js/services/adminService.js` | Add getRoles() |
| `resources/js/store/modules/auth.js` | Role/permissions state, isAdmin getter |
| `resources/js/views/Admin/AdminPanel.vue` | Remove redundant admin check |
| `resources/js/components/Admin/UserManagement.vue` | Role badges, role-based counts |
| `resources/js/components/Admin/UserFormModal.vue` | Role dropdown, first_name/surname |
| `resources/js/components/Admin/TaxSettings.vue` | Remove create stub |
| `resources/js/components/UserProfile/Settings.vue` | Account type from Vuex getter |
| `tests/Feature/AdminRBACTest.php` | Use role_id, seed roles, fix assertions |
| `tests/Feature/TaxConfigurationTest.php` | Seed roles, assign role_id to admin/user in setUp |
| `tests/Unit/Services/Auth/PermissionServiceTest.php` | Use role_id instead of is_admin, assign permissions to admin role |
| `.env` | Add `ADMIN_EMAILS=chris@fynla.org,brett@fynla.org` |

---

## Rebuild Required

Yes — frontend changes (Settings.vue computed property).

---

## Post-Upload Commands

```bash
php artisan migrate && php artisan db:seed --class=RolesPermissionsSeeder --force && php artisan cache:clear && php artisan route:clear && php artisan config:clear
```

**Production .env must also include:**
```
ADMIN_EMAILS=chris@fynla.org,brett@fynla.org
```
