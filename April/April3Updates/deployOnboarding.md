# Deploy Guide — Onboarding Expenditure Bug Fix

**Date:** 3 April 2026
**Branch:** onboardingBug
**Generated from:** `git diff --name-only`

---

## What Changed

- **500 error fix:** `ExpenditureForm.vue` was sending `expenditure_entry_mode: 'detailed'` but the DB column is enum `('simple','category')`. Changed to `'category'`.
- **Tab advancement:** Added `advanceToNextTab()` to ExpenditureForm so Continue switches from user to spouse tab in separate mode before saving.
- **Scroll to top:** Added smooth scroll to top when switching to spouse expenditure tab during onboarding.

## Files to Upload

### Frontend Build Required

```bash
./deploy/fynla-org/build.sh
```

Upload `public/build/` directory.

### No PHP Files Changed

Backend code was already correct — the bug was frontend-only.

## Post-Upload SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```
