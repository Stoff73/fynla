# Advisor Dashboard — Deployment Guide

**Date:** 18 March 2026
**PR:** #136 (merged to main)
**Branch:** `worktree-advisor-dashboard`

---

## Rebuild Required: YES

Frontend files changed — **must run build script** before uploading.

```bash
./deploy/fynla-org/build.sh
```

---

## Post-Merge Commands (run on server via SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# 1. Run migrations (3 new tables)
php artisan migrate

# 2. Seed advisor data
php artisan db:seed --class=RolesPermissionsSeeder --force
php artisan db:seed --class=AdvisorClientSeeder --force

# 3. Clear all caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Files to Upload

### New PHP Files (upload all)

| File | Purpose |
|------|---------|
| `app/Models/AdvisorClient.php` | Advisor-client relationship model |
| `app/Models/ClientActivity.php` | Activity tracking model |
| `app/Services/Advisor/AdvisorDashboardService.php` | Dashboard stats, client list, activity feed |
| `app/Services/Advisor/ClientActivityService.php` | CRUD for client activities |
| `app/Services/Advisor/AdvisorImpersonationService.php` | Enter/exit client profiles via cache |
| `app/Services/Admin/UserModuleTrackingService.php` | P S I R E module status per user |
| `app/Http/Controllers/Api/AdvisorController.php` | 11 API endpoints |
| `app/Http/Requests/StoreClientActivityRequest.php` | Activity form validation |
| `app/Http/Middleware/AdvisorMiddleware.php` | Checks `is_advisor` flag |
| `app/Http/Middleware/AdvisorImpersonationMiddleware.php` | Resolves impersonated user |
| `database/migrations/2026_03_17_200001_add_is_advisor_to_users_table.php` | Add `is_advisor` column |
| `database/migrations/2026_03_17_200002_create_advisor_clients_table.php` | Create advisor_clients table |
| `database/migrations/2026_03_17_200003_create_client_activities_table.php` | Create client_activities table |
| `database/seeders/AdvisorClientSeeder.php` | Seed demo advisor data |
| `database/factories/AdvisorClientFactory.php` | Test factory |
| `database/factories/ClientActivityFactory.php` | Test factory |

### Modified PHP Files (upload updated versions)

| File | What Changed |
|------|-------------|
| `app/Models/User.php` | Added `is_advisor` to `$guarded`/`$casts`, added `advisorClients()` and `advisors()` relationships |
| `app/Models/Role.php` | Added `ROLE_ADVISOR`, `LEVEL_ADVISOR` constants, `getAdvisorRole()` static |
| `app/Models/Permission.php` | Added `ADVISOR_ACCESS` constant |
| `app/Services/Auth/PermissionService.php` | Added advisor role creation + permissions in `syncDefaultRolesAndPermissions()`, added `isAdvisor()` |
| `app/Http/Kernel.php` | Added `advisor` and `advisor.impersonate` middleware aliases |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | Added advisor impersonation routes to `EXCLUDED_ROUTES` |
| `app/Http/Middleware/CheckSubscription.php` | Added `api/advisor/` to `ALWAYS_EXCLUDED_PATHS` |
| `routes/api.php` | Added 11 advisor API routes |
| `database/seeders/DatabaseSeeder.php` | Added `AdvisorClientSeeder` to Phase 1 |

### Frontend Files (built via build script — upload `public/build/`)

These are all compiled into `public/build/` by the build script. Upload the entire `public/build/` directory.

| File | Purpose |
|------|---------|
| `resources/js/layouts/AdvisorLayout.vue` | Advisor-specific layout (top bar + sidebar + content) |
| `resources/js/layouts/AppLayout.vue` | Added `AdvisorBanner` for impersonation |
| `resources/js/views/Advisor/AdvisorDashboard.vue` | Main dashboard page |
| `resources/js/views/Advisor/AdvisorClientList.vue` | Full client list with search/filter |
| `resources/js/views/Advisor/AdvisorClientDetail.vue` | Read-only client overview |
| `resources/js/views/Advisor/AdvisorActivityLog.vue` | Activity feed with filters |
| `resources/js/views/Advisor/AdvisorReviewsDue.vue` | Review management cards |
| `resources/js/views/Advisor/AdvisorReports.vue` | Suitability reports table |
| `resources/js/components/Advisor/ClientModuleDots.vue` | P S I R E status dots |
| `resources/js/components/Advisor/ClientActivityForm.vue` | Activity logging modal |
| `resources/js/components/Advisor/AdvisorBanner.vue` | Impersonation banner |
| `resources/js/store/modules/advisor.js` | Vuex advisor store module |
| `resources/js/services/advisorService.js` | API service wrapper |
| `resources/js/store/index.js` | Registered advisor module |
| `resources/js/store/modules/auth.js` | Added `isAdvisor` getter |
| `resources/js/router/index.js` | Added advisor routes + guard |

### Test Files (do NOT upload to production)

- `tests/Feature/Api/AdvisorControllerTest.php`
- `tests/Feature/Middleware/AdvisorMiddlewareTest.php`
- `tests/Unit/Services/Admin/UserModuleTrackingServiceTest.php`
- `tests/Unit/Services/Advisor/AdvisorDashboardServiceTest.php`
- `tests/Unit/Services/Advisor/AdvisorImpersonationServiceTest.php`

---

## Upload Checklist

1. Build locally: `./deploy/fynla-org/build.sh`
2. Upload `public/build/` directory to server
3. Upload all **New PHP Files** listed above
4. Upload all **Modified PHP Files** listed above
5. SSH to server and run migration + seed + cache clear commands
6. Test: login as chris@fynla.org, navigate to `/advisor`
