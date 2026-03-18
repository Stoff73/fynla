# Combined Deployment Guide — All March 17-18 Changes

**Date:** 18 March 2026
**Source of truth:** `git diff d306e9f..HEAD` (every file changed since before March 17 work began)
**Covers:** Life Stage Journey, Admin Panel Enhancement, Admin Fixes, Journey Bug Fixes, Advisor Dashboard + Fixes
**Rebuild Required:** YES

---

## Step 1: Build Frontend Locally

```bash
./deploy/fynla-org/build.sh
```

Mandatory — the server lacks memory for npm builds. ONE build covers all 84 frontend file changes.

---

## Step 2: Upload Files to Server

Upload via SiteGround File Manager to `~/www/fynla.org/public_html/`.

### 2a. Upload `public/build/` Directory

Upload the **entire** `public/build/` directory. This contains all 84 compiled Vue/JS changes listed in Section 4 below.

### 2b. Upload New PHP Files (26 files — create on server)

| # | File | Feature |
|---|------|---------|
| 1 | `app/Http/Controllers/Api/ActionDefinitionController.php` | Admin Panel |
| 2 | `app/Http/Controllers/Api/AdvisorController.php` | Advisor |
| 3 | `app/Http/Controllers/Api/LifeStageController.php` | Life Stage |
| 4 | `app/Http/Middleware/AdvisorImpersonationMiddleware.php` | Advisor |
| 5 | `app/Http/Middleware/AdvisorMiddleware.php` | Advisor |
| 6 | `app/Http/Requests/StoreActionDefinitionRequest.php` | Admin Panel |
| 7 | `app/Http/Requests/StoreClientActivityRequest.php` | Advisor |
| 8 | `app/Models/AdvisorClient.php` | Advisor |
| 9 | `app/Models/ClientActivity.php` | Advisor |
| 10 | `app/Models/EstateActionDefinition.php` | Admin Panel |
| 11 | `app/Services/Admin/UserModuleTrackingService.php` | Admin + Advisor |
| 12 | `app/Services/Advisor/AdvisorDashboardService.php` | Advisor |
| 13 | `app/Services/Advisor/AdvisorImpersonationService.php` | Advisor |
| 14 | `app/Services/Advisor/ClientActivityService.php` | Advisor |
| 15 | `app/Services/Estate/EstateActionDefinitionService.php` | Admin Panel |
| 16 | `app/Services/LifeStage/LifeStageService.php` | Life Stage |
| 17 | `database/factories/AdvisorClientFactory.php` | Advisor |
| 18 | `database/factories/ClientActivityFactory.php` | Advisor |
| 19 | `database/migrations/2026_03_17_100001_add_life_stage_fields_to_users_table.php` | Life Stage |
| 20 | `database/migrations/2026_03_17_100001_create_estate_action_definitions_table.php` | Admin Panel |
| 21 | `database/migrations/2026_03_17_200001_add_is_advisor_to_users_table.php` | Advisor |
| 22 | `database/migrations/2026_03_17_200002_create_advisor_clients_table.php` | Advisor |
| 23 | `database/migrations/2026_03_17_200003_create_client_activities_table.php` | Advisor |
| 24 | `database/migrations/2026_03_18_100001_add_student_fields_to_users_table.php` | Journey Bug |
| 25 | `database/seeders/AdvisorClientSeeder.php` | Advisor |
| 26 | `database/seeders/EstateActionDefinitionSeeder.php` | Admin Panel |

### 2c. Upload Modified PHP Files (17 files — replace existing)

| # | File | What Changed |
|---|------|-------------|
| 1 | `app/Console/Commands/ResetPreviewData.php` | Removed widow, added missing personas |
| 2 | `app/Http/Controllers/Api/AdminController.php` | moduleStatus method, --single-transaction on backup, raw bytes for size |
| 3 | `app/Http/Controllers/Api/AuthController.php` | data_completed_steps in response, auto-promote ADMIN_EMAILS, role fallback |
| 4 | `app/Http/Controllers/Api/PreviewController.php` | Removed widow from VALID_PERSONAS |
| 5 | `app/Http/Kernel.php` | Advisor middleware aliases + AdvisorImpersonationMiddleware in api group |
| 6 | `app/Http/Middleware/CheckSubscription.php` | Excluded api/advisor/ from subscription check |
| 7 | `app/Http/Middleware/HasPermission.php` | Admin users bypass permission checks |
| 8 | `app/Http/Middleware/PreviewWriteInterceptor.php` | Excluded advisor impersonation routes |
| 9 | `app/Http/Requests/UpdatePersonalInfoRequest.php` | +monthly_expenditure, +university, +student_number |
| 10 | `app/Models/Permission.php` | ADVISOR_ACCESS constant |
| 11 | `app/Models/Role.php` | ROLE_ADVISOR, LEVEL_ADVISOR, getAdvisorRole() |
| 12 | `app/Models/User.php` | is_advisor cast, life_stage casts, advisorClients/advisors relationships |
| 13 | `app/Services/Auth/PermissionService.php` | Advisor role/permissions, isAdvisor() method |
| 14 | `app/Services/PrerequisiteGateService.php` | Correct RiskProfile import namespace |
| 15 | `database/seeders/DatabaseSeeder.php` | Registered EstateActionDefinitionSeeder + AdvisorClientSeeder |
| 16 | `database/seeders/PreviewUserSeeder.php` | Removed widow, sets life_stage on creation |
| 17 | `routes/api.php` | Life stage routes + admin routes + advisor routes + completeness route |

### 2d. Upload Persona JSON Files (6 files — replace existing)

Upload to `resources/js/data/personas/` on server:

| # | File | Change |
|---|------|--------|
| 1 | `resources/js/data/personas/student.json` | Added life_stage: "university" |
| 2 | `resources/js/data/personas/young_saver.json` | Aged to 28, expanded data, life_stage: "early_career" |
| 3 | `resources/js/data/personas/young_family.json` | Added life_stage: "mid_career" |
| 4 | `resources/js/data/personas/entrepreneur.json` | Added life_stage: "mid_career" |
| 5 | `resources/js/data/personas/peak_earners.json` | Added life_stage: "peak" |
| 6 | `resources/js/data/personas/retired_couple.json` | Added life_stage: "retirement" |

### 2e. Delete from Server

| File | Reason |
|------|--------|
| `resources/js/data/personas/widow.json` | Persona removed from system |

---

## Step 3: SSH Commands (after all files uploaded)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# 1. Run all migrations (6 new migration files)
php artisan migrate --force

# 2. Seed all data (estate definitions, advisor clients, preview personas)
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

## Section 4: Complete Frontend File Reference

These are ALL compiled into `public/build/` by the build script. You do NOT upload these individually — uploading `public/build/` covers them all. Listed here for reference only.

### New Frontend Files (32 files)

| # | File | Feature |
|---|------|---------|
| 1 | `resources/js/components/Admin/ActionDefinitionDrawer.vue` | Admin Panel |
| 2 | `resources/js/components/Admin/DecisionMatrix.vue` | Admin Panel |
| 3 | `resources/js/components/Admin/DecisionNode.vue` | Admin Panel |
| 4 | `resources/js/components/Admin/DecisionTree.vue` | Admin Panel |
| 5 | `resources/js/components/Admin/TriggerConfigEditor.vue` | Admin Panel |
| 6 | `resources/js/components/Admin/UserModuleStatus.vue` | Admin Panel |
| 7 | `resources/js/components/Admin/UserOnboardingProgress.vue` | Admin Panel |
| 8 | `resources/js/components/Advisor/AdvisorBanner.vue` | Advisor |
| 9 | `resources/js/components/Advisor/ClientActivityForm.vue` | Advisor |
| 10 | `resources/js/components/Advisor/ClientModuleDots.vue` | Advisor |
| 11 | `resources/js/components/Dashboard/GoalsCard.vue` | Life Stage |
| 12 | `resources/js/components/Dashboard/LifeTimelineCard.vue` | Life Stage |
| 13 | `resources/js/components/Journey/JourneyMap.vue` | Life Stage |
| 14 | `resources/js/components/Journey/JourneyProgressHero.vue` | Life Stage |
| 15 | `resources/js/components/Onboarding/LearningMilestoneSidebar.vue` | Life Stage |
| 16 | `resources/js/components/Onboarding/steps/StudentLoanStep.vue` | Journey Bug |
| 17 | `resources/js/composables/useLifeStageFields.js` | Life Stage |
| 18 | `resources/js/constants/lifeStageConfig.js` | Life Stage |
| 19 | `resources/js/constants/moduleConfigs.js` | Admin Panel |
| 20 | `resources/js/layouts/AdvisorLayout.vue` | Advisor |
| 21 | `resources/js/services/actionDefinitionService.js` | Admin Panel |
| 22 | `resources/js/services/advisorService.js` | Advisor |
| 23 | `resources/js/services/lifeStageService.js` | Life Stage |
| 24 | `resources/js/store/modules/advisor.js` | Advisor |
| 25 | `resources/js/store/modules/completeness.js` | Journey Bug |
| 26 | `resources/js/store/modules/lifeStage.js` | Life Stage |
| 27 | `resources/js/views/Advisor/AdvisorActivityLog.vue` | Advisor |
| 28 | `resources/js/views/Advisor/AdvisorClientDetail.vue` | Advisor |
| 29 | `resources/js/views/Advisor/AdvisorClientList.vue` | Advisor |
| 30 | `resources/js/views/Advisor/AdvisorDashboard.vue` | Advisor |
| 31 | `resources/js/views/Advisor/AdvisorReports.vue` | Advisor |
| 32 | `resources/js/views/Advisor/AdvisorReviewsDue.vue` | Advisor |

### Modified Frontend Files (52 files)

| # | File | Feature |
|---|------|---------|
| 1 | `resources/js/App.vue` | Life Stage (dispatches lifeStage/fetchStage) |
| 2 | `resources/js/components/Admin/DatabaseBackup.vue` | Admin Fix |
| 3 | `resources/js/components/Admin/UserManagement.vue` | Admin Panel |
| 4 | `resources/js/components/Estate/LiabilityForm.vue` | Journey Bug (CSS refactor) |
| 5 | `resources/js/components/Investment/AccountForm.vue` | Journey Bug |
| 6 | `resources/js/components/Navbar.vue` | Advisor (violet Advisor button) |
| 7 | `resources/js/components/NetWorth/Property/PropertyForm.vue` | Life Stage (context prop) |
| 8 | `resources/js/components/Onboarding/FocusAreaSelection.vue` | Life Stage (welcome screen) |
| 9 | `resources/js/components/Onboarding/OnboardingWizard.vue` | Life Stage + Journey Bug |
| 10 | `resources/js/components/Onboarding/steps/AssetsStep.vue` | Life Stage |
| 11 | `resources/js/components/Onboarding/steps/ExpenditureStep.vue` | Life Stage |
| 12 | `resources/js/components/Onboarding/steps/FamilyInfoStep.vue` | Life Stage |
| 13 | `resources/js/components/Onboarding/steps/GoalSetupStep.vue` | Journey Bug (custom goal type) |
| 14 | `resources/js/components/Onboarding/steps/IncomeStep.vue` | Life Stage |
| 15 | `resources/js/components/Onboarding/steps/LiabilitiesStep.vue` | Life Stage |
| 16 | `resources/js/components/Onboarding/steps/PersonalInfoStep.vue` | Life Stage |
| 17 | `resources/js/components/Onboarding/steps/QuickAssetsStep.vue` | Life Stage |
| 18 | `resources/js/components/Onboarding/steps/SimpleExpenditureStep.vue` | Journey Bug |
| 19 | `resources/js/components/Onboarding/steps/SimplePersonalInfoStep.vue` | Life Stage |
| 20 | `resources/js/components/Onboarding/steps/SimplePropertyMortgageStep.vue` | Life Stage |
| 21 | `resources/js/components/Onboarding/steps/SimpleSavingsAccountStep.vue` | Life Stage |
| 22 | `resources/js/components/Onboarding/steps/WillInfoStep.vue` | Journey Bug (new tab) |
| 23 | `resources/js/components/Preview/PersonaSelectionModal.vue` | Life Stage (stage grouping) |
| 24 | `resources/js/components/Preview/PersonaSelector.vue` | Life Stage (button colours) |
| 25 | `resources/js/components/Preview/PreviewBanner.vue` | Life Stage (colour fixes) |
| 26 | `resources/js/components/Protection/PolicyFormModal.vue` | Journey Bug (backdrop fix) |
| 27 | `resources/js/components/Retirement/DCPensionForm.vue` | Journey Bug (inline validation) |
| 28 | `resources/js/components/Retirement/StatePensionForm.vue` | Journey Bug (inline validation) |
| 29 | `resources/js/components/Savings/SaveAccountModal.vue` | Journey Bug (backdrop fix) |
| 30 | `resources/js/components/SideMenu.vue` | Life Stage + Advisor (stage badge, explore, advisor link) |
| 31 | `resources/js/components/SideMenuItem.vue` | Life Stage (activeColour, muted props) |
| 32 | `resources/js/components/UserProfile/ExpenditureForm.vue` | Life Stage (context prop) |
| 33 | `resources/js/components/UserProfile/FamilyMemberFormModal.vue` | Life Stage |
| 34 | `resources/js/components/UserProfile/PersonalInformation.vue` | Life Stage + Journey Bug (student fields) |
| 35 | `resources/js/layouts/AppLayout.vue` | Advisor (AdvisorBanner) |
| 36 | `resources/js/mobile/views/MobileDashboard.vue` | Life Stage (journey progress) |
| 37 | `resources/js/router/index.js` | Life Stage + Advisor routes |
| 38 | `resources/js/services/adminService.js` | Admin Panel |
| 39 | `resources/js/store/index.js` | Life Stage + Advisor + Completeness modules |
| 40 | `resources/js/store/modules/auth.js` | Life Stage + Admin Fix + Advisor (isAdvisor getter) |
| 41 | `resources/js/store/modules/preview.js` | Life Stage (removed widow, stage dispatch) |
| 42 | `resources/js/views/Admin/AdminPanel.vue` | Admin Panel (5 tabs) |
| 43 | `resources/js/views/Dashboard.vue` | Life Stage + Journey Bug (hero, curated cards, completeness) |
| 44 | `resources/js/views/Public/LandingPage.vue` | Life Stage (Find Your Stage) |
| 45 | `resources/js/views/Version.vue` | March 18 patch notes |
| 46 | `tailwind.config.js` | Life Stage (safelisted stroke classes) |
| 47 | `resources/js/data/personas/entrepreneur.json` | Life Stage |
| 48 | `resources/js/data/personas/peak_earners.json` | Life Stage |
| 49 | `resources/js/data/personas/retired_couple.json` | Life Stage |
| 50 | `resources/js/data/personas/student.json` | Life Stage |
| 51 | `resources/js/data/personas/young_family.json` | Life Stage |
| 52 | `resources/js/data/personas/young_saver.json` | Life Stage (aged to 28) |

---

## Verification Checklist

### Life Stage Journey
- [ ] Landing page shows "Find Your Stage" section with 5 stage cards
- [ ] Click a stage -> journey map renders
- [ ] Preview personas show correct stage-adaptive sidebar and dashboard
- [ ] Onboarding flow works with two-column layout

### Admin Panel
- [ ] Login as chris@fynla.org -> Admin button appears in top nav
- [ ] Admin Panel -> 5 tabs load (Dashboard, User Management, Decision Matrix, Tax Settings, Database)
- [ ] Decision Matrix -> 6 module sub-tabs with action counts
- [ ] User Management -> P S I R E module dots per user
- [ ] Database -> backup sizes show correctly, create backup works

### Journey Bug Fixes
- [ ] Student persona -> Student Debt card on dashboard
- [ ] Sidebar has "Explore" section with secondary items
- [ ] Onboarding forms render inline, data persists

### Advisor Dashboard
- [ ] "Advisor" button visible in top navbar
- [ ] "Advisor Dashboard" link visible in sidebar
- [ ] Click Advisor -> dashboard loads with 5 clients, stats, reviews, activity
- [ ] Client Detail -> full profile with modules, review info, activity timeline
- [ ] Activity Log -> activities with type labels and client names
- [ ] Reviews Due -> client names, overdue badges, dates
- [ ] Suitability Reports -> table with formatted report types
- [ ] Enter Profile -> shows client's actual financial data with violet banner
- [ ] Exit -> returns to advisor dashboard
- [ ] Log Activity -> form opens, fill and save, new entry appears
- [ ] Search clients -> filters table correctly

---

## File Count Summary

| Category | New | Modified | Deleted | Total |
|----------|-----|----------|---------|-------|
| PHP (upload manually) | 26 | 17 | 0 | 43 |
| Frontend (compiled into public/build/) | 32 | 52 | 1 | 85 |
| **Total unique files** | **58** | **69** | **1** | **128** |

**What to physically upload:**
- 43 PHP files (individually)
- 1 directory: `public/build/` (covers all 85 frontend files)
- Delete 1 file: `resources/js/data/personas/widow.json`

---

## Rollback

All migrations are additive (new tables and nullable columns). No existing tables are modified destructively.

1. Re-deploy previous `public/build/` from backup
2. Restore previous PHP files
3. New tables can be dropped if needed: `estate_action_definitions`, `advisor_clients`, `client_activities`
4. New columns are nullable and won't affect existing functionality if left in place
