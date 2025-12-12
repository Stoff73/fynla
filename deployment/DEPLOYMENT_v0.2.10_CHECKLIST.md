# Fynla v0.2.10 - Deployment Checklist

**Version**: v0.2.10 (Boma Build)
**Date**: November 20, 2025
**Type**: Code-Only Patch
**Estimated Time**: 15-20 minutes

---

## Pre-Deployment

- [ ] Verify git branch: Boma
- [ ] Verify git status: clean
- [ ] Local production build successful
- [ ] All files committed
- [ ] SSH access to csjones.co available
- [ ] FTP/SFTP client ready

---

## Deployment Steps

### 1. Backup (5 minutes)

- [ ] SSH into production server
- [ ] Create backup directory: `~/backups/tengo-v0.2.9-backup-[timestamp]`
- [ ] Backup app/ directory
- [ ] Backup resources/js/ directory
- [ ] Backup routes/api.php
- [ ] Backup public/build/manifest.json
- [ ] Note backup directory name for rollback

### 2. Upload Backend Files (5 minutes)

Upload to `~/tengo-app/`:

- [ ] app/Http/Controllers/Api/FamilyMembersController.php
- [ ] app/Http/Controllers/Api/NetWorthController.php
- [ ] app/Http/Controllers/Api/UserProfileController.php
- [ ] app/Services/Onboarding/EstateOnboardingFlow.php
- [ ] app/Services/UserProfile/UserProfileService.php

### 3. Upload Frontend Files (5 minutes)

Upload to `~/tengo-app/`:

- [ ] resources/js/components/Estate/EstateOverviewCard.vue
- [ ] resources/js/components/Estate/IHTPlanning.vue
- [ ] resources/js/components/Footer.vue
- [ ] resources/js/components/NetWorth/AssetAllocationDonut.vue
- [ ] resources/js/components/NetWorth/NetWorthOverview.vue
- [ ] resources/js/components/NetWorth/NetWorthTrendChart.vue
- [ ] resources/js/components/NetWorth/WealthSummary.vue (NEW)
- [ ] resources/js/components/Onboarding/steps/IncomeStep.vue
- [ ] resources/js/components/Protection/ProtectionOverviewCard.vue
- [ ] resources/js/components/Trusts/TrustsOverviewCard.vue
- [ ] resources/js/components/UserProfile/ExpenditureForm.vue
- [ ] resources/js/services/userProfileService.js
- [ ] resources/js/store/modules/estate.js
- [ ] resources/js/store/modules/netWorth.js
- [ ] resources/js/store/modules/protection.js
- [ ] resources/js/utils/currency.js (NEW - create directory if needed)
- [ ] resources/js/views/Dashboard.vue
- [ ] resources/js/views/UserProfile.vue
- [ ] routes/api.php

### 4. Upload Assets (2 minutes)

- [ ] Upload estate.png to `~/tengo-app/`
- [ ] Delete white.png from `~/tengo-app/`

### 5. Rebuild Frontend (3 minutes)

Via SSH:
```bash
cd ~/tengo-app
rm -rf public/build/*
NODE_ENV=production npm run build
ls -la public/build/manifest.json
ls public/build/assets/ | wc -l
```

- [ ] Build completed successfully (~18-20 seconds)
- [ ] manifest.json exists at public/build/manifest.json
- [ ] 100+ assets in public/build/assets/

### 6. Clear Caches (2 minutes)

Via SSH:
```bash
cd ~/tengo-app
php artisan cache:clear
php artisan view:clear
php artisan config:clear
php artisan route:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
chmod -R 775 storage bootstrap/cache
```

- [ ] All caches cleared
- [ ] Production caches rebuilt
- [ ] Permissions set correctly

---

## Post-Deployment Verification

### Smoke Tests (5 minutes)

- [ ] Homepage loads: https://csjones.co/tengo
- [ ] Login works: demo@fps.com / password
- [ ] Dashboard displays correctly
- [ ] Net Worth card clickable
- [ ] Estate Planning card shows IHT data
- [ ] Protection card shows coverage
- [ ] Trusts card shows "Coming Soon"
- [ ] User Profile > Expenditure form loads
- [ ] Three tabs visible (My, Spouse, Household)
- [ ] Financial commitments displayed
- [ ] No duplicate forms
- [ ] Footer shows "v0.2.10" and "Boma Build"

### Error Checks

Via SSH:
```bash
tail -50 ~/tengo-app/storage/logs/laravel.log
```

- [ ] No critical errors in Laravel log
- [ ] No PHP fatal errors

Via SiteGround:
- [ ] Site Tools > Error Log shows no 500 errors

### Browser Tests

- [ ] Clear browser cache (Ctrl+F5)
- [ ] Test in incognito/private window
- [ ] Verify styles load correctly
- [ ] Check console for JavaScript errors (should be none)

---

## Monitoring (15-30 minutes)

- [ ] Monitor Laravel logs for errors
- [ ] Watch for user-reported issues
- [ ] Check error rate in SiteGround
- [ ] Test critical user flows

---

## Rollback (if needed)

If critical issues occur:

```bash
cd ~/tengo-app
backup_name="[your backup name from step 1]"
cp -r ~/backups/$backup_name/app/ ./
cp ~/backups/$backup_name/api.php routes/
cp -r ~/backups/$backup_name/resources/js/ resources/
NODE_ENV=production npm run build
php artisan cache:clear && php artisan config:clear
php artisan config:cache && php artisan route:cache
```

- [ ] Backup restored
- [ ] Frontend rebuilt
- [ ] Caches cleared
- [ ] Site verified working

---

## Completion

- [ ] All smoke tests passed
- [ ] No critical errors in logs
- [ ] User experience verified
- [ ] Version confirmed: v0.2.10
- [ ] Monitoring in place for next 30 minutes

---

## Notes

**What NOT to do**:
- ❌ DO NOT run `php artisan migrate` (no migrations in v0.2.10)
- ❌ DO NOT modify database
- ❌ DO NOT run composer install (dependencies unchanged)

**Key Changes**:
- 28 bug fixes and enhancements
- No database migrations
- Code-only deployment
- Focus on expenditure forms and dashboard cards

**Expected Outcome**:
- Zero downtime
- Improved user experience
- Better code quality
- Enhanced dashboard navigation

---

**Deployment Completed**: _____________ (date/time)
**Deployed By**: _____________
**Issues Encountered**: _____________
**Rollback Required**: Yes / No

---

Built with Claude Code by Anthropic
