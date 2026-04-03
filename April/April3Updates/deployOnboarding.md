# Deploy Guide — Onboarding Bug Fix + UX Improvements

**Date:** 3 April 2026
**Branch:** onboardingBug
**Generated from:** `git diff main..onboardingBug --name-only`

---

## What Changed

### Bug Fix
- **500 error fix:** `ExpenditureForm.vue` was sending `expenditure_entry_mode: 'detailed'` but the DB column is enum `('simple','category')`. Changed to `'category'`.

### UX Improvements
- **Step reorder:** Assets & Debts now come before Income & Spending across all 5 life stages
- **Tab advancement:** Expenditure Continue switches from user to spouse tab in separate mode before saving
- **Assets Continue cycles tabs:** Main Continue button cycles through Retirement → Properties → Investments → Cash before advancing. Removed internal Next/Previous tab buttons.
- **Family "Did you know":** Added that spouse transfers are inheritance tax free and the £1 million combined nil-rate band is to beneficiaries
- **Income/Expenditure "Why we ask":** Added affordability assessment and risk profile mentions
- **DC Pension form:** Hidden "Expected Return" during onboarding. Renamed "Planned Access Age" to "Retirement Age" with info tooltip showing data source
- **Info icons on prefilled fields:** Visible info icon with hover tooltip on First Name, Surname, Email (showing "From your registration")
- **Scroll to top:** All form opens (pension, property, investment, savings, liability, protection) and all section/tab transitions now scroll to top. Fixed PropertyForm which was scrolling modal container instead of window in onboarding context.

## Files Changed (9 frontend files)

```
resources/js/components/NetWorth/Property/PropertyForm.vue
resources/js/components/Onboarding/steps/AssetsStep.vue
resources/js/components/Onboarding/steps/ExpenditureStep.vue
resources/js/components/Onboarding/steps/LiabilitiesStep.vue
resources/js/components/Onboarding/steps/PersonalInfoStep.vue
resources/js/components/Onboarding/steps/ProtectionPoliciesStep.vue
resources/js/components/Retirement/DCPensionForm.vue
resources/js/components/UserProfile/ExpenditureForm.vue
resources/js/constants/lifeStageConfig.js
```

### Frontend Build Required

```bash
./deploy/fynla-org/build.sh
```

Upload `public/build/` directory.

### No PHP Files Changed

Backend code was already correct — all changes are frontend-only.

## Post-Upload SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```
