# Journey Bug Fix — Deployment Guide

**Branch:** `worktree-journeyBug`
**Date:** 2026-03-18
**Commits:** 8
**Rebuild required:** YES — frontend changes require `./deploy/fynla-org/build.sh`

---

## Pre-Deployment

```bash
# 1. Merge the branch
git checkout main
git merge worktree-journeyBug

# 2. Run migration (new columns on users table)
php artisan migrate

# 3. Reseed (always after migration)
php artisan db:seed

# 4. Build frontend
./deploy/fynla-org/build.sh
```

---

## Files to Upload

### PHP Files (upload via SiteGround File Manager)

| File | Path on Server | Change |
|------|---------------|--------|
| `LifeStageController.php` | `app/Http/Controllers/Api/` | +completeness endpoint |
| `UpdatePersonalInfoRequest.php` | `app/Http/Requests/` | +monthly_expenditure, +university, +student_number |
| `api.php` | `routes/` | +completeness route |
| `2026_03_18_100001_add_student_fields_to_users_table.php` | `database/migrations/` | NEW — university + student_number columns |

### Frontend Build (upload entire build directory)

```
public/build/    →    ~/www/fynla.org/public_html/public/build/
```

**Rebuild is required** because the following JS/Vue files changed:

| File | Change |
|------|--------|
| `OnboardingWizard.vue` | +StudentLoanStep, +property save fix, +student fields, +skip tracking, +stepsWithOwnNav |
| `GoalSetupStep.vue` | +custom_goal_type_name for "Other" goal type |
| `StudentLoanStep.vue` | NEW — dedicated student loan onboarding step |
| `SimpleExpenditureStep.vue` | Replace silent catch with console.error |
| `WillInfoStep.vue` | window.open (new tab) instead of router.push |
| `PolicyFormModal.vue` | bg-eggshell-5000 → bg-black/50 |
| `DCPensionForm.vue` | alert() → inline validation, CSS cleanup |
| `StatePensionForm.vue` | alert() → inline validation, CSS cleanup |
| `SaveAccountModal.vue` | bg-eggshell-5000 → bg-black/50 |
| `SideMenu.vue` | Primary/explore separation, explore section, SIDEBAR_ITEMS entries |
| `PersonalInformation.vue` | +student status, violet colours |
| `LiabilityForm.vue` | CSS refactored to Tailwind (260 lines removed) |
| `JourneyProgressHero.vue` | Dynamic Tailwind class lookup maps |
| `Dashboard.vue` | +estate loader, +isStudentPersona, +completeness fetch |
| `lifeStageConfig.js` | +letter, +power-of-attorney, +holistic-plan, +actions to stage configs |
| `lifeStageService.js` | +getCompleteness method |
| `store/index.js` | +completeness module |
| `store/modules/completeness.js` | NEW — completeness Vuex store |

### Test Files (do NOT upload to production)

| File | Note |
|------|------|
| `tests/Feature/CompletenessEndpointTest.php` | NEW — 7 tests, dev only |

---

## Post-Deployment (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migration for new student columns
php artisan migrate

# Clear all caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize

# Reseed to ensure all data is fresh
php artisan db:seed
```

---

## What Changed (Summary)

### New Features
- **Completeness API** (`GET /api/life-stage/completeness`) — per-module has_data/can_advise status
- **StudentLoanStep.vue** — dedicated onboarding step with plan types, thresholds, rates
- **Sidebar primary/explore separation** — core items in main sections, secondary in "Explore"

### Bug Fixes
- Student loan data now loads on dashboard (missing estate fetch)
- Monthly expenditure persists properly (was silently lost)
- Student fields (university, student_number) saved during onboarding
- Dynamic Tailwind classes compile in production (lookup maps)
- alert() replaced with inline validation in pension forms
- Modal backdrops render correctly (bg-eggshell-5000 typo fixed)
- Skip button tracks progress
- Will Builder opens in new tab (preserves onboarding state)
- isStudentPersona checks life_stage too (not just preview persona)
- PropertyForm save extracts nested property/mortgage data
- Custom goal type sends custom_goal_type_name
- Expression of Wishes, Power of Attorney visible in all stage sidebars
- Holistic Plan, Actions visible for mid_career+ stages
- Dead code removed from SideMenu

### Database Migration
- `university` (string, 255, nullable) added to users table
- `student_number` (string, 50, nullable) added to users table

---

## Verification After Deploy

1. Visit landing page → select "Starting Out" → verify student dashboard shows Student Debt card
2. Check sidebar has "Explore" section with secondary items
3. Check "Expression of Wishes" and "Power of Attorney" visible in Explore
4. Try onboarding for any stage → verify forms render inline (no modals)
5. Enter data → verify it appears on dashboard after completion
