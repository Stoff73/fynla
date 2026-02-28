# Deploy 20 February 2026

**Status:** DEPLOYED 20 February 2026

---

## Changes

### 1. SpousePermission cleanup on spouse unlink
- When a spouse family member is deleted (unlinking accounts), bidirectional `SpousePermission` records are now deleted
- Protection analysis cache is cleared for both users on unlink

### 2. SpousePermission creation for new-account spouse branch
- The "create new spouse account" flow was missing `SpousePermission` creation (only the "link existing account" branch had it)
- Both bidirectional permissions are now created when a new spouse account is created

### 3. Race condition protection with pessimistic locking
- Existing-account and new-account spouse linking branches in `FamilyMembersController` wrapped in `DB::transaction()`
- Inside the existing-account transaction, the spouse user row is re-fetched with `lockForUpdate()` and `spouse_id` is re-checked before proceeding — prevents two users concurrently linking to the same spouse
- If the locked check fails (spouse already linked by another user), the transaction returns null and the controller returns a 422 response
- Existing-account branch in `OnboardingService::handleSpouseLinking()` wrapped in `DB::transaction()` with the same `lockForUpdate()` pattern
- Email sends kept outside transactions to avoid holding locks during SMTP

### 4. CashAccount joint_owner_id column
- New migration adds `joint_owner_id` (nullable, indexed) to `cash_accounts` table
- `CashAccount` model gains `HasJointOwnership` trait, `joint_owner_id` in fillable, and `jointOwner()` relationship
- Matches pattern used by Property, InvestmentAccount, and other asset tables

### 5. FamilyMember linked_user_id column
- New migration adds `linked_user_id` (nullable, FK to users, indexed) to `family_members` table
- `FamilyMember` model gains `linked_user_id` in fillable and `linkedUser()` relationship
- All 5 `FamilyMember::create()` calls in `FamilyMembersController` now set `linked_user_id`
- Both `FamilyMember::updateOrCreate()` calls in `OnboardingService` now set `linked_user_id`
- `PreviewUserSeeder` creates bidirectional spouse `FamilyMember` records with `linked_user_id` and `SpousePermission` records

### 6. RBAC Activation — Single Authority System
- **Removed dual-authority:** The legacy `is_admin` boolean flag is no longer the authority. All access checks now go through the RBAC system (roles + permissions + pivot table)
- **Removed all `is_admin` bypasses** from `PermissionService`, `HasPermission` middleware, and `HasRole` middleware
- **Removed `admin` middleware alias** from Kernel — all admin routes now use `permission:` middleware
- **Permission-based route protection:** Admin routes use granular permissions (`admin.access`, `users.edit`, `users.delete`, `admin.backup`, `admin.tax_config`)
- **New migration** assigns `role_id` to all existing users based on their `is_admin` flag
- **New `admin.backup` permission** added; removed dead `users.impersonate` and content permission constants
- **User model `booted()` sync:** When `role_id` changes, `is_admin` is automatically synced as a safety net
- **AdminController:** User CRUD uses `first_name`/`surname` (not `name`), `role_id` (not `is_admin`); new `getRoles()` endpoint; `dashboard()` returns `table_statistics`
- **AuthController:** `user()` endpoint now returns `role` and `permissions` alongside user data; registration assigns `role_id`
- **StoreTaxConfigurationRequest:** Authorization uses PermissionService instead of `is_admin`
- **Frontend store:** Auth store tracks `role` and `permissions`, `isAdmin` getter uses `state.role === 'admin'`
- **UserFormModal:** Uses `first_name`/`surname` fields and role dropdown (not admin checkbox)
- **UserManagement:** Role badges (Admin/Support/User), loads available roles from API
- **AdminPanel:** Removed redundant `mounted()` admin check (router guard handles it)
- **TaxSettings:** "Create New Tax Year" button now opens duplicate modal pre-filled from active config (removed stub)

### 7. Admin Link Fix — Drop Legacy `role` Column
- **Root cause:** The `users` table had a legacy `role` VARCHAR column that shadowed the `role()` BelongsTo relationship. Eloquent attributes take precedence over relationships, so `$user->role` returned the string `'user'` instead of the Role model. This caused `$user->role?->name` to return null, breaking the entire RBAC chain (AuthController, PermissionService, HasPermission middleware, admin link visibility)
- **New migration** drops the `users.role` column — `$user->role` now correctly resolves to the Role BelongsTo model via `role_id`
- **Seeded roles and assigned role_id** to all existing users (RolesPermissionsSeeder must run before the role-assignment migration)
- **Added `ADMIN_EMAILS=chris@fynla.org,brett@fynla.org`** to `.env` — used by AuthController on registration to auto-assign admin role
- **Updated TestUsersSeeder:** `'role' => 'user'` replaced with `'role_id' => Role::findByName(Role::ROLE_USER)?->id`
- **Updated AdminRBACTest:** Uses `role_id` instead of `role`, seeds RolesPermissionsSeeder, assertions check `data.role` not `data.user.role`
- **Updated Settings.vue:** Account type display uses Vuex `auth/role` getter instead of legacy `currentUser?.role` attribute

---

## Section 17 Issues Resolved

| Issue | Resolution |
|-------|-----------|
| 17.1 — `users.impersonate` placeholder | Removed from Permission model constants and seed |
| 17.2 — "Create from Scratch" stub | Button redirects to duplicate active config |
| 17.3 — RBAC not wired to routes | Full activation via permission middleware on all admin routes |
| 17.4 — `getTableStatistics()` dead code | Wired into dashboard response |
| 17.5 — Revenue in pence | No fix needed (intentional) |
| 17.6 — Content permissions not seeded | Removed constants from Permission model |
| 17.7 — Redundant mounted() check | Removed from AdminPanel.vue |
| 17.8 — createUser uses `name` | Changed to first_name/surname in controller + modal |

---

## Files Changed

| File | Change |
|------|--------|
| `database/migrations/2026_02_19_120000_add_joint_owner_id_to_cash_accounts_table.php` | New migration: add `joint_owner_id` column to `cash_accounts` |
| `database/migrations/2026_02_19_120001_add_linked_user_id_to_family_members_table.php` | New migration: add `linked_user_id` column with FK to `family_members` |
| `database/migrations/2026_02_20_120000_assign_roles_to_existing_users.php` | New migration: assign roles to existing users based on is_admin |
| `database/migrations/2026_02_20_130000_drop_legacy_role_column_from_users.php` | New migration: drop legacy `role` VARCHAR column that shadowed role() relationship |
| `app/Models/CashAccount.php` | Add `HasJointOwnership` trait, `joint_owner_id` to fillable, `jointOwner()` relationship |
| `app/Models/FamilyMember.php` | Add `linked_user_id` to fillable, `linkedUser()` relationship |
| `app/Models/Permission.php` | Add ADMIN_BACKUP constant, remove dead USERS_IMPERSONATE + content constants |
| `app/Models/User.php` | Add `booted()` saving listener to sync is_admin when role_id changes |
| `app/Http/Controllers/Api/FamilyMembersController.php` | SpousePermission cleanup, DB::transaction, linked_user_id |
| `app/Http/Controllers/Api/AdminController.php` | RBAC-based user CRUD, getRoles endpoint, table_statistics, first_name/surname |
| `app/Http/Controllers/Api/AuthController.php` | Return role/permissions in user endpoint, assign role_id on registration |
| `app/Http/Requests/StoreTaxConfigurationRequest.php` | Use PermissionService instead of is_admin |
| `app/Http/Kernel.php` | Remove `admin` middleware alias |
| `app/Http/Middleware/HasPermission.php` | Remove is_admin bypass |
| `app/Http/Middleware/HasRole.php` | Remove is_admin fallback |
| `app/Services/Auth/PermissionService.php` | Remove all is_admin bypasses, add admin.backup to seed |
| `app/Services/Onboarding/OnboardingService.php` | DB::transaction with lockForUpdate in handleSpouseLinking |
| `config/auth.php` | Contains `admin_emails` config reading ADMIN_EMAILS env var |
| `database/seeders/RolesPermissionsSeeder.php` | Seeds roles, permissions, and role-permission assignments |
| `database/seeders/AdminUserSeeder.php` | Fix role assignment (role_id instead of role) |
| `database/seeders/DatabaseSeeder.php` | Add RolesPermissionsSeeder before AdminUserSeeder |
| `database/seeders/PreviewUserSeeder.php` | Create SpousePermissions and spouse FamilyMember records |
| `database/seeders/TestUsersSeeder.php` | Use `role_id` instead of legacy `role` column |
| `routes/api.php` | Replace admin middleware with granular permission middleware |
| `resources/js/services/authService.js` | Return full data object from getUser() |
| `resources/js/services/adminService.js` | Add getRoles() method |
| `resources/js/store/modules/auth.js` | Add role/permissions state, update isAdmin getter |
| `resources/js/views/Admin/AdminPanel.vue` | Remove redundant mounted() admin check |
| `resources/js/components/Admin/UserManagement.vue` | Role badges, load roles, role-based admin count |
| `resources/js/components/Admin/UserFormModal.vue` | Role dropdown, first_name/surname fields |
| `resources/js/components/Admin/TaxSettings.vue` | Remove create stub, redirect to duplicate |
| `resources/js/components/UserProfile/Settings.vue` | Account type from Vuex role getter instead of legacy column |
| `tests/Feature/AdminRBACTest.php` | Use role_id, seed roles, fix assertions for dropped column |
| `tests/Feature/TaxConfigurationTest.php` | Seed roles, assign role_id to admin/user in setUp |
| `tests/Unit/Services/Auth/PermissionServiceTest.php` | Use role_id instead of is_admin, assign permissions to admin role |

---

## Rebuild Required

Yes - Vue component changes require frontend rebuild.

```bash
./deploy/fynla-org/build.sh
```

---

## Files to Upload

### PHP files
1. `app/Models/CashAccount.php`
2. `app/Models/FamilyMember.php`
3. `app/Models/Permission.php`
4. `app/Models/User.php`
5. `app/Http/Controllers/Api/FamilyMembersController.php`
6. `app/Http/Controllers/Api/AdminController.php`
7. `app/Http/Controllers/Api/AuthController.php`
8. `app/Http/Requests/StoreTaxConfigurationRequest.php`
9. `app/Http/Kernel.php`
10. `app/Http/Middleware/HasPermission.php`
11. `app/Http/Middleware/HasRole.php`
12. `app/Services/Auth/PermissionService.php`
13. `app/Services/Onboarding/OnboardingService.php`
14. `config/auth.php`
15. `database/migrations/2026_02_19_120000_add_joint_owner_id_to_cash_accounts_table.php`
16. `database/migrations/2026_02_19_120001_add_linked_user_id_to_family_members_table.php`
17. `database/migrations/2026_02_20_120000_assign_roles_to_existing_users.php`
18. `database/migrations/2026_02_20_130000_drop_legacy_role_column_from_users.php`
19. `database/seeders/AdminUserSeeder.php`
20. `database/seeders/DatabaseSeeder.php`
21. `database/seeders/PreviewUserSeeder.php`
22. `database/seeders/TestUsersSeeder.php`
23. `database/seeders/RolesPermissionsSeeder.php`
24. `routes/api.php`
25. `tests/Feature/AdminRBACTest.php`
26. `tests/Feature/TaxConfigurationTest.php`
27. `tests/Unit/Services/Auth/PermissionServiceTest.php`

### Frontend build
28. `public/build/` (entire directory after running build script)

### Production .env update
Add to production `.env`:
```
ADMIN_EMAILS=chris@fynla.org,brett@fynla.org
```

---

## Post-Upload

Run these steps **in order**:

### Step 1: Seed roles and permissions
```bash
php artisan db:seed --class=RolesPermissionsSeeder --force
```

### Step 2: Run migrations
Adds nullable columns (`joint_owner_id`, `linked_user_id`). The legacy `role` column drop and role-assignment migrations already ran on production.
```bash
php artisan migrate --force
```

### Step 3: Assign admin role to admin users
**CRITICAL:** The role-assignment migration already ran on production as a no-op (roles weren't seeded yet, and `is_admin` was false for admin users). All migrations are now marked as complete, so `php artisan migrate` won't re-run them. You must manually assign roles.

Assign admin role by email, and user role to everyone else:
```bash
php artisan tinker --execute="
\$adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
\$userRoleId = DB::table('roles')->where('name', 'user')->value('id');
DB::table('users')->whereIn('email', ['chris@fynla.org', 'brett@fynla.org'])->update(['role_id' => \$adminRoleId, 'is_admin' => true]);
DB::table('users')->whereNull('role_id')->update(['role_id' => \$userRoleId]);
echo 'Admin: ' . DB::table('users')->where('role_id', \$adminRoleId)->count() . ' users. User: ' . DB::table('users')->where('role_id', \$userRoleId)->count() . ' users.';
"
```

Verify:
```bash
php artisan tinker --execute="echo App\Models\User::where('email','chris@fynla.org')->first()?->role?->name;"
```
Expected output: `admin`

### Step 4: Clear caches and reseed preview users
```bash
php artisan cache:clear && php artisan route:clear && php artisan config:clear && php artisan db:seed --class=PreviewUserSeeder --force
```
