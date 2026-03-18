# Admin Panel — Browser & Pest Test Report

**Tester:** Claude Opus 4.6 (1M context)
**Date:** 18 March 2026
**Branch:** `main`
**Environment:** localhost:8000 (Laravel dev server) + Vite :5173
**Login:** `admin@fps.com` / `admin123` (preview admin user, role_id=67 "admin")

---

## Test Summary

| Area | Status | Notes |
|------|--------|-------|
| Admin Dashboard tab | PASS | All 8 stat cards render, Recent Users table shows 3 test users, Database Backup Status shows last backup date |
| User Management tab | PASS | Search, status filter dropdown, Create User button, P S I R E module dots, Edit/Delete actions all present |
| User Expandable Row | PARTIAL | Expands correctly showing Module Status (P/S/I/R/E) and Onboarding Progress sections. API calls to `/api/admin/users/{id}/module-status` return 401 when admin is a preview user |
| Decision Matrix tab | PASS | All 6 module sub-tabs with correct counts (Protection 28, Cash & Savings 41, Investments 21, Retirement 18, Estate Planning 8, Tax 5) |
| Decision Matrix — Stats Bar | PASS | 5 stat cards: Total (28), Enabled (25), Disabled (3), Critical/High (17), Medium (9) |
| Decision Matrix — Tree Layout | PASS | 4-column layout with column headers: User Data, Trigger, Logic, Outcome. SVG arrows between nodes |
| Decision Matrix — Legend Bar | PASS | 5 node types (User Data Input, Trigger Condition, Decision Logic, Outcome/Action, Disabled) + 4 priority badges (CRIT, HIGH, MED, LOW) |
| Decision Matrix — Search/Filter | PASS | Both Search and Filter buttons present in tree header, toggle input/dropdown on click |
| Decision Matrix — Collapse All | PASS | Button present |
| Decision Matrix — + Add Action | PASS | Button present |
| Decision Matrix — Node Click → Drawer | PASS | 420px slide-in drawer opens with all fields: Key (monospace), Source (select), Title Template, Description Template (monospace + variable tags), Action Template, Category, Priority (select), Scope (select), What-If Impact Type (select), Trigger Configuration (IF condition + AND/OR combinator), Sort Order, Notes, Enabled toggle |
| Decision Matrix — Drawer Save | PASS | Save Changes button submits, drawer closes, tree re-renders. (Preview user gets fake success via PreviewWriteInterceptor) |
| Decision Matrix — Disabled Nodes | PASS | Show at reduced opacity with "OFF" badge, dashed arrows |
| Decision Matrix — Priority Badges | PASS | CRIT (raspberry-700), HIGH (raspberry-500), MED (violet-500), LOW (spring-500), OFF (neutral-500) |
| Tax Settings tab | PASS | Active Tax Configuration (2025/26), 6 sub-tabs (Income Tax & NI, Savings & Investments, Pensions, IHT, Property/SDLT, Version Management), tax bands and NI rates all rendered correctly |
| Database tab | PARTIAL | Create New Backup, Refresh, backup table with Restore/Delete actions. **BUG: Size column shows "NaN undefined"** |
| Estate Planning sub-tab | PASS | Shows 8 definitions (verified via count badge) |
| Tab Navigation | PASS | All 5 tabs (Dashboard, User Management, Decision Matrix, Tax Settings, Database) switch correctly with active state styling |

---

## Pest Test Results

### ActionDefinitionControllerTest — 8/8 PASS

| # | Test | Time | Status |
|---|------|------|--------|
| 1 | it lists action definitions for a module | 10.30s | PASS |
| 2 | it returns 422 for invalid module | 0.16s | PASS |
| 3 | it creates an action definition | 0.16s | PASS |
| 4 | it updates an action definition | 0.15s | PASS |
| 5 | it toggles enabled state | 0.15s | PASS |
| 6 | it deletes an action definition | 0.15s | PASS |
| 7 | it returns decision matrix data | 0.18s | PASS |
| 8 | it requires admin permission | 0.15s | PASS |

### UserModuleTrackingServiceTest — 5/6 PASS, 1 FAIL

| # | Test | Time | Status |
|---|------|------|--------|
| 1 | it returns complete status for user with all protection data | 0.12s | PASS |
| 2 | it returns partial status for user with some data | 0.07s | PASS |
| 3 | it returns empty status for user with no data | 0.06s | PASS |
| 4 | it returns correct sub-area counts and values | 0.06s | **FAIL** |
| 5 | it returns onboarding data | 0.06s | PASS |
| 6 | it handles user with no relationships loaded | 0.06s | PASS |

**Test 4 failure:** `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'cover_amount' in 'field list'`
- The `LifeInsurancePolicyFactory` references a `cover_amount` column that doesn't exist on the `life_insurance_policies` table
- Root cause: Factory/migration mismatch — factory was written expecting a column that was never migrated or has been renamed

### AdminBackupTest — HUNG (not completable)

The `createBackup` test calls `mysqldump` which acquires LOCK TABLES on the entire database. On the local dev MySQL, this blocks ALL other queries (including the seeder and dev server) indefinitely. The test cannot complete in the local environment without a dedicated test database.

**Test cases that exist in the file:**
1. lists existing backups with correct metadata
2. deletes a backup file
3. rejects path traversal in backup filename
4. rejects invalid filename format
5. requires admin authentication
6. returns empty array when no backups exist
7. cleans up temporary credential files after backup operations

**Missing test cases (per spec):**
- `createBackup` (verify mysqldump executes, .sql file created)
- `restoreBackup` (verify restore works on test DB)
- Rate limiting (send 4 rapid requests, assert 429 on 4th)

---

## Bugs Found

### BUG-1: Database Backup Size Shows "NaN undefined"

**Severity:** Low (cosmetic)
**Location:** `resources/js/components/Admin/DatabaseBackup.vue:165` / `app/Http/Controllers/Api/AdminController.php:417`
**Description:** Backend `listBackups()` returns `size` as a pre-formatted string (e.g., "5.41 MB") via `$this->databaseMetrics->formatBytes()`. Frontend `formatFileSize()` expects a raw byte count (number). Calling `Math.log("5.41 MB")` returns NaN.
**Fix:** Either return raw bytes from backend (`filesize($fullPath)`) or display `backup.size` directly without `formatFileSize()`.

### BUG-2: UserModuleTrackingServiceTest Factory Mismatch

**Severity:** Medium (test failure)
**Location:** `tests/Unit/Services/Admin/UserModuleTrackingServiceTest.php:47`
**Description:** `LifeInsurancePolicyFactory` uses `cover_amount` column that doesn't exist on `life_insurance_policies` table. The column may have been renamed to `sum_assured` or was never added.
**Fix:** Update factory to use the correct column name matching the migration.

### BUG-3: AdminBackupTest Causes Database Lock

**Severity:** High (blocks dev environment)
**Location:** `tests/Feature/Api/AdminBackupTest.php`
**Description:** The backup tests use real `mysqldump` which acquires LOCK TABLES on the dev database. This blocks ALL concurrent MySQL queries — the dev server, seeders, and other tests all hang. If the test process is killed, the lock persists until the MySQL connection times out.
**Fix:** Mock `mysqldump` execution in tests, or use `--single-transaction` flag, or run against a dedicated test database with `RefreshDatabase`.

### BUG-4: Admin Route Guard — Direct URL Navigation

**Severity:** Low
**Location:** Vue Router guard
**Description:** Navigating directly to `http://localhost:8000/admin` via URL bar redirects to `/dashboard`. Navigation via sidebar link works fine. This appears to be a race condition — the auth state isn't loaded when the router guard runs on initial page load.
**Fix:** The router guard should wait for auth state to be initialised before checking `is_admin`.

---

## Screenshots Taken

| Screenshot | Description |
|------------|-------------|
| admin-dashboard.png | Dashboard tab with stats cards and Recent Users |
| admin-user-management.png | User Management with P S I R E dots |
| admin-user-expanded-row.png | Expanded user row with Module Status and Onboarding Progress |
| admin-decision-matrix-error.png | Decision Matrix before permissions fix (401 errors) |
| admin-decision-matrix-working.png | Full Decision Matrix with 28 Protection definitions |
| admin-drawer-open.png | Action Definition drawer with all fields |
| admin-database-tab.png | Database Backups tab showing "NaN undefined" bug |
