# Combined Deployment Guide — All March 17-18 Changes

**Date:** 18 March 2026
**Covers:** Life Stage Journey, Admin Panel Enhancement, Admin Fixes, Journey Bug Fixes, Advisor Dashboard + Fixes
**Rebuild Required:** YES

---

## Step 1: Build Frontend Locally

```bash
./deploy/fynla-org/build.sh
```

This is mandatory — the server lacks memory for npm builds. ONE build covers all frontend changes.

---

## Step 2: Upload Files to Server

Upload via SiteGround File Manager to `~/www/fynla.org/public_html/`.

### Upload `public/build/` Directory

Upload the entire `public/build/` directory. This contains all compiled Vue/JS changes from every feature.

### New PHP Files (create on server)

| # | File | From |
|---|------|------|
| 1 | `app/Services/LifeStage/LifeStageService.php` | Life Stage Journey |
| 2 | `app/Http/Controllers/Api/LifeStageController.php` | Life Stage Journey |
| 3 | `app/Http/Controllers/Api/ActionDefinitionController.php` | Admin Panel |
| 4 | `app/Http/Requests/StoreActionDefinitionRequest.php` | Admin Panel |
| 5 | `app/Http/Requests/StoreClientActivityRequest.php` | Advisor Dashboard |
| 6 | `app/Models/EstateActionDefinition.php` | Admin Panel |
| 7 | `app/Models/AdvisorClient.php` | Advisor Dashboard |
| 8 | `app/Models/ClientActivity.php` | Advisor Dashboard |
| 9 | `app/Services/Admin/UserModuleTrackingService.php` | Admin Panel + Advisor |
| 10 | `app/Services/Estate/EstateActionDefinitionService.php` | Admin Panel |
| 11 | `app/Services/Advisor/AdvisorDashboardService.php` | Advisor Dashboard |
| 12 | `app/Services/Advisor/ClientActivityService.php` | Advisor Dashboard |
| 13 | `app/Services/Advisor/AdvisorImpersonationService.php` | Advisor Dashboard |
| 14 | `app/Http/Controllers/Api/AdvisorController.php` | Advisor Dashboard |
| 15 | `app/Http/Middleware/AdvisorMiddleware.php` | Advisor Dashboard |
| 16 | `app/Http/Middleware/AdvisorImpersonationMiddleware.php` | Advisor Dashboard |

### New Migration Files (upload to `database/migrations/`)

| # | File | Purpose |
|---|------|---------|
| 1 | `2026_03_17_100001_add_life_stage_fields_to_users_table.php` | life_stage + life_stage_completed_steps columns |
| 2 | `2026_03_17_100001_create_estate_action_definitions_table.php` | Estate action definitions table |
| 3 | `2026_03_17_200001_add_is_advisor_to_users_table.php` | is_advisor boolean column |
| 4 | `2026_03_17_200002_create_advisor_clients_table.php` | Advisor-client relationships |
| 5 | `2026_03_17_200003_create_client_activities_table.php` | Client activity tracking |
| 6 | `2026_03_18_100001_add_student_fields_to_users_table.php` | university + student_number columns |

### New Seeder/Factory Files

| # | File | Purpose |
|---|------|---------|
| 1 | `database/seeders/EstateActionDefinitionSeeder.php` | 8 estate action definitions |
| 2 | `database/seeders/AdvisorClientSeeder.php` | Demo advisor-client data |
| 3 | `database/factories/AdvisorClientFactory.php` | Test factory |
| 4 | `database/factories/ClientActivityFactory.php` | Test factory |

### Modified PHP Files (upload updated versions)

| # | File | Changes From |
|---|------|-------------|
| 1 | `app/Models/User.php` | Life Stage (life_stage casts) + Advisor (is_advisor, advisorClients/advisors relationships) |
| 2 | `app/Models/Role.php` | Advisor (ROLE_ADVISOR, LEVEL_ADVISOR, getAdvisorRole) |
| 3 | `app/Models/Permission.php` | Advisor (ADVISOR_ACCESS constant) |
| 4 | `app/Http/Kernel.php` | Advisor middleware aliases + AdvisorImpersonationMiddleware in api group |
| 5 | `app/Http/Controllers/Api/AuthController.php` | Life Stage (data_completed_steps) + Admin Fix (auto-promote ADMIN_EMAILS, role fallback) |
| 6 | `app/Http/Controllers/Api/AdminController.php` | Admin Panel (moduleStatus) + Admin Fix (--single-transaction, raw bytes) |
| 7 | `app/Http/Controllers/Api/PreviewController.php` | Life Stage (removed widow from VALID_PERSONAS) |
| 8 | `app/Http/Controllers/Api/LifeStageController.php` | Journey Bug (+completeness endpoint) |
| 9 | `app/Http/Requests/UpdatePersonalInfoRequest.php` | Journey Bug (+monthly_expenditure, +university, +student_number) |
| 10 | `app/Http/Middleware/PreviewWriteInterceptor.php` | Advisor (excluded impersonation routes) |
| 11 | `app/Http/Middleware/CheckSubscription.php` | Advisor (excluded api/advisor/) |
| 12 | `app/Http/Middleware/HasPermission.php` | Admin Fix (admin bypass) |
| 13 | `app/Services/Auth/PermissionService.php` | Advisor (advisor role + permissions, isAdvisor method) |
| 14 | `app/Console/Commands/ResetPreviewData.php` | Life Stage (removed widow, added missing personas) |
| 15 | `database/seeders/PreviewUserSeeder.php` | Life Stage (removed widow, sets life_stage) |
| 16 | `database/seeders/DatabaseSeeder.php` | Admin Panel (EstateActionDefinitionSeeder) + Advisor (AdvisorClientSeeder) |
| 17 | `routes/api.php` | Life Stage routes + Admin routes + Advisor routes + completeness route |

### Persona JSON Files (upload to `resources/js/data/personas/`)

| # | File | Change |
|---|------|--------|
| 1 | `resources/js/data/personas/student.json` | Added life_stage |
| 2 | `resources/js/data/personas/young_saver.json` | Aged to 28, expanded data, added life_stage |
| 3 | `resources/js/data/personas/young_family.json` | Added life_stage |
| 4 | `resources/js/data/personas/entrepreneur.json` | Added life_stage |
| 5 | `resources/js/data/personas/peak_earners.json` | Added life_stage |
| 6 | `resources/js/data/personas/retired_couple.json` | Added life_stage |

### Delete from Server

| File | Reason |
|------|--------|
| `resources/js/data/personas/widow.json` | Persona removed from system |

---

## Step 3: SSH Commands (after upload)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# 1. Run all migrations (6 new migration files)
php artisan migrate --force

# 2. Seed all data
php artisan db:seed --force

# 3. Clear all caches (CRITICAL — Kernel.php middleware changed)
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Step 4: Environment Check

Verify `ADMIN_EMAILS` is set in production `.env`:

```
ADMIN_EMAILS=chris@fynla.org,brett@fynla.org,azlan@fynla.org
```

If not present, add it and run `php artisan config:clear`.

---

## Step 5: Verification

### Life Stage Journey
- [ ] Landing page shows "Find Your Stage" section with 5 stage cards
- [ ] Click a stage → journey map renders
- [ ] Preview personas show correct stage-adaptive sidebar and dashboard
- [ ] Onboarding flow works with two-column layout

### Admin Panel
- [ ] Login as chris@fynla.org → Admin button appears in top nav
- [ ] Admin Panel → 5 tabs load (Dashboard, User Management, Decision Matrix, Tax Settings, Database)
- [ ] Decision Matrix → 6 module sub-tabs with action counts
- [ ] User Management → P S I R E module dots per user
- [ ] Database → backup sizes show correctly, create backup works

### Journey Bug Fixes
- [ ] Student persona → Student Debt card on dashboard
- [ ] Sidebar has "Explore" section with secondary items
- [ ] Onboarding forms render inline, data persists

### Advisor Dashboard
- [ ] "Advisor" button visible in top navbar
- [ ] "Advisor Dashboard" link visible in sidebar
- [ ] Click Advisor → dashboard loads with 5 clients, stats, reviews, activity
- [ ] Client Detail → full profile with modules, review info, activity timeline
- [ ] Activity Log → 19 activities with type labels and client names
- [ ] Reviews Due → client names, overdue badges, dates
- [ ] Suitability Reports → table with formatted report types
- [ ] Enter Profile → shows client's actual financial data with violet banner
- [ ] Exit → returns to advisor dashboard
- [ ] Log Activity → form opens, fill and save, new entry appears
- [ ] Search clients → filters table correctly
- [ ] Client filter on Activity Log → filters activities by client

---

## File Count Summary

| Category | Count |
|----------|-------|
| New PHP files | 16 |
| New migration files | 6 |
| New seeder/factory files | 4 |
| Modified PHP files | 17 |
| Persona JSON files | 6 (upload) + 1 (delete) |
| Frontend build | 1 directory (`public/build/`) |
| **Total files to upload** | **50 + public/build/** |

---

## Rollback

All migrations are additive (new tables and nullable columns). No existing tables are modified destructively. To rollback:
1. Re-deploy previous `public/build/` from backup
2. Restore previous PHP files
3. New tables can be dropped if needed: `estate_action_definitions`, `advisor_clients`, `client_activities`
4. New columns are nullable and won't affect existing functionality if left in place
