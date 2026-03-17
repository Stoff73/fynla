# Life Stage Journey System — Deployment Guide

**Date:** 2026-03-17
**Branch:** `feature/life-stage-journey`
**Rebuild Required:** YES — frontend rebuild mandatory (`./deploy/fynla-org/build.sh`)

---

## Pre-Deployment

### 1. Run migration (BEFORE uploading PHP files)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate
```

This adds two columns to the `users` table:
- `life_stage` (VARCHAR 20, nullable)
- `life_stage_completed_steps` (JSON, nullable)

### 2. Reseed preview personas

```bash
php artisan db:seed --class=PreviewUserSeeder --force
php artisan db:seed --class=TaxConfigurationSeeder --force
```

The widow persona has been removed. Young saver (John Morgan) has been aged to 28. All personas now have `life_stage` set.

---

## Files to Upload

### New Files (create on server)

| File | Purpose |
|------|---------|
| `app/Services/LifeStage/LifeStageService.php` | Backend life stage service |
| `app/Http/Controllers/Api/LifeStageController.php` | API endpoints for life stage |
| `database/migrations/2026_03_17_100001_add_life_stage_fields_to_users_table.php` | Migration |
| `resources/js/constants/lifeStageConfig.js` | Central stage configuration (922 lines) |
| `resources/js/store/modules/lifeStage.js` | Vuex module |
| `resources/js/services/lifeStageService.js` | API service |
| `resources/js/composables/useLifeStageFields.js` | Form field visibility composable |
| `resources/js/components/Journey/JourneyMap.vue` | SVG journey map component |
| `resources/js/components/Journey/JourneyProgressHero.vue` | Dashboard progress hero |
| `resources/js/components/Dashboard/GoalsCard.vue` | Goals card with progress bars |
| `resources/js/components/Dashboard/LifeTimelineCard.vue` | Life timeline card |
| `resources/js/components/Onboarding/LearningMilestoneSidebar.vue` | Learning sidebar for onboarding |
| `tests/Feature/LifeStageControllerTest.php` | Backend tests |

### Modified Files (replace on server)

**Backend (PHP):**

| File | Change |
|------|--------|
| `app/Models/User.php` | Added `life_stage_completed_steps` to casts |
| `app/Http/Controllers/Api/AuthController.php` | Returns `data_completed_steps` in auth response |
| `app/Http/Controllers/Api/PreviewController.php` | Removed widow from VALID_PERSONAS |
| `app/Console/Commands/ResetPreviewData.php` | Removed widow, added missing personas |
| `database/seeders/PreviewUserSeeder.php` | Removed widow, sets life_stage on creation |
| `routes/api.php` | Added life-stage API routes |

**Frontend (JS/Vue) — included in rebuild:**

| File | Change |
|------|--------|
| `resources/js/App.vue` | Dispatches lifeStage/fetchStage after auth |
| `resources/js/store/index.js` | Registers lifeStage module |
| `resources/js/store/modules/auth.js` | Sets life stage + data completeness from auth response |
| `resources/js/store/modules/preview.js` | Removed widow import, dispatches setStageFromPersona |
| `resources/js/store/modules/lifeStage.js` | New Vuex module |
| `resources/js/views/Dashboard.vue` | JourneyProgressHero, stage-curated cards, GoalsCard, LifeTimelineCard, removed CrossModuleInsights |
| `resources/js/views/Public/LandingPage.vue` | "Find Your Stage" section |
| `resources/js/components/SideMenu.vue` | Stage badge, progress ring, primary/explore split, inline toggle |
| `resources/js/components/SideMenuItem.vue` | activeColour and muted props |
| `resources/js/components/Onboarding/FocusAreaSelection.vue` | Life stage welcome + inline journey map |
| `resources/js/components/Onboarding/OnboardingWizard.vue` | Two-column layout, life stage mode |
| `resources/js/components/UserProfile/PersonalInformation.vue` | context prop, stage-adaptive fields, "About You" heading |
| `resources/js/components/UserProfile/ExpenditureForm.vue` | context prop |
| `resources/js/components/NetWorth/Property/PropertyForm.vue` | context prop |
| `resources/js/components/Savings/SaveAccountModal.vue` | context prop, stage defaults |
| `resources/js/components/Protection/PolicyFormModal.vue` | context prop, stage defaults |
| `resources/js/components/Preview/PreviewBanner.vue` | Fixed young_family button colour, spouse toggle darker |
| `resources/js/components/Preview/PersonaSelector.vue` | Fixed button colours, stage grouping |
| `resources/js/components/Preview/PersonaSelectionModal.vue` | Stage-grouped personas |
| `resources/js/mobile/views/MobileDashboard.vue` | Journey progress, stage-curated cards |
| `tailwind.config.js` | Safelisted stroke classes for SVG progress ring |

**Persona JSON files:**

| File | Change |
|------|--------|
| `resources/js/data/personas/student.json` | Added `life_stage: "university"` |
| `resources/js/data/personas/young_saver.json` | Aged to 28, expanded data, added `life_stage: "early_career"` |
| `resources/js/data/personas/young_family.json` | Added `life_stage: "mid_career"` |
| `resources/js/data/personas/entrepreneur.json` | Added `life_stage: "mid_career"` |
| `resources/js/data/personas/peak_earners.json` | Added `life_stage: "peak"` |
| `resources/js/data/personas/retired_couple.json` | Added `life_stage: "retirement"` |
| `resources/js/data/personas/widow.json` | **DELETED** |

**Deprecated (marked, not yet deleted):**

12 onboarding step components marked with deprecation comments — still functional, will be removed in future cleanup.

### Deleted Files

| File | Reason |
|------|--------|
| `resources/js/data/personas/widow.json` | Persona removed from system |

---

## Build & Deploy Steps

### 1. Build frontend locally

```bash
./deploy/fynla-org/build.sh
```

This is **mandatory** — the server lacks memory for npm builds.

### 2. Upload to server

Upload via SiteGround File Manager:

1. `public/build/` directory → `~/www/fynla.org/public_html/public/build/`
2. All PHP files listed above → their respective paths
3. Migration file → `database/migrations/`
4. Persona JSON files → `resources/js/data/personas/`
5. Delete `resources/js/data/personas/widow.json` from server

### 3. SSH commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migration
php artisan migrate

# Reseed
php artisan db:seed

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

### 4. Verify

- Landing page shows "Find Your Stage" section
- Click each stage → journey map renders inline
- Click "Start My Journey" → onboarding with two-column layout
- Preview personas show correct stage-adaptive sidebar and dashboard
- Student persona shows 6 items in sidebar, mid-career shows 8
- Progress ring shows correct percentage based on actual data

---

## Rollback

If issues occur, revert to the previous `public/build/` directory and restore the PHP files from the last deployment. The migration is additive (nullable columns) so no rollback needed for the database.
