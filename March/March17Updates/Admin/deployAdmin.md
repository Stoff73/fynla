# Admin Panel Enhancement — Deployment Guide

**Date:** 18 March 2026
**PR:** #135 (merged to main)
**Commit range:** d306e9f..e72ae31

---

## Rebuild Required: YES

Frontend Vue components were added/modified. You must rebuild before uploading.

```bash
./deploy/fynla-org/build.sh
```

---

## Database Migration Required: YES

One new migration must run on the server:

```bash
php artisan migrate --force
```

This creates the `estate_action_definitions` table. No existing tables are modified.

---

## Database Seed Required: YES

```bash
php artisan db:seed --class=EstateActionDefinitionSeeder --force
```

Seeds 8 estate planning action definitions (no_will, iht_exceeds_nrb, no_lpa, etc.).

---

## Files to Upload

### PHP Files (upload to `~/www/fynla.org/public_html/`)

**New files:**

| File | Path on server |
|------|---------------|
| `app/Http/Controllers/Api/ActionDefinitionController.php` | `app/Http/Controllers/Api/` |
| `app/Http/Requests/StoreActionDefinitionRequest.php` | `app/Http/Requests/` |
| `app/Models/EstateActionDefinition.php` | `app/Models/` |
| `app/Services/Admin/UserModuleTrackingService.php` | `app/Services/Admin/` (create directory) |
| `app/Services/Estate/EstateActionDefinitionService.php` | `app/Services/Estate/` |
| `database/migrations/2026_03_17_100001_create_estate_action_definitions_table.php` | `database/migrations/` |
| `database/seeders/EstateActionDefinitionSeeder.php` | `database/seeders/` |

**Modified files:**

| File | What changed |
|------|-------------|
| `app/Http/Controllers/Api/AdminController.php` | Added `moduleStatus()` method |
| `database/seeders/DatabaseSeeder.php` | Registered EstateActionDefinitionSeeder |
| `routes/api.php` | Added generic action-definition routes, decision-matrix route, module-status route, split backup rate limiting |

### Frontend Build (upload after rebuild)

Upload the entire `public/build/` directory:

```
public/build/ → ~/www/fynla.org/public_html/public/build/
```

This includes all compiled Vue components:
- `DecisionMatrix.vue`, `DecisionTree.vue`, `DecisionNode.vue`
- `ActionDefinitionDrawer.vue`, `TriggerConfigEditor.vue`
- `UserModuleStatus.vue`, `UserOnboardingProgress.vue`
- Modified: `AdminPanel.vue`, `UserManagement.vue`, `DatabaseBackup.vue`
- New: `moduleConfigs.js`, `actionDefinitionService.js`
- Modified: `adminService.js`

### Test Files (DO NOT upload to production)

- `tests/Feature/Api/ActionDefinitionControllerTest.php`
- `tests/Feature/Api/AdminBackupTest.php`
- `tests/Unit/Services/Admin/UserModuleTrackingServiceTest.php`
- `March/March17Updates/Admin/implementation-audit.md`

---

## Post-Upload SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migration
php artisan migrate --force

# Seed estate definitions
php artisan db:seed --class=EstateActionDefinitionSeeder --force

# Clear all caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Verification After Deploy

1. Login as admin
2. Navigate to Admin Panel
3. Verify 5 tabs: Dashboard, User Management, Decision Matrix, Tax Settings, Database
4. Click Decision Matrix — verify 6 module sub-tabs load with action counts
5. Click a node — verify drawer opens with all fields
6. Click Estate Planning sub-tab — verify 8 definitions
7. Click User Management — verify Modules column with P S I R E dots
8. Click a user row — verify expanded detail with sub-areas
9. Click Database — verify clean load (no rate limit error)

---

## Rollback

If issues occur, the changes are isolated:
- **Frontend:** Re-deploy previous `public/build/` from backup
- **Backend:** Remove new files, restore previous `routes/api.php`, `AdminController.php`, `DatabaseSeeder.php`
- **Database:** `DROP TABLE estate_action_definitions;` (new table only, no existing tables modified)
