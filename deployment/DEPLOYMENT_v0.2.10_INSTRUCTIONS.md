# Fynla v0.2.10 (Boma Build) - Production Deployment Instructions

**Date**: November 20, 2025
**Branch**: Boma
**Version**: v0.2.10
**Type**: Code-Only Patch (No Database Migrations)
**Production URL**: https://csjones.co/tengo
**Application Root**: ~/tengo-app/

---

## Deployment Overview

This is a **code-only deployment** with no database migrations required. All 28 changes are confined to backend controllers/services and frontend Vue components.

**Total Files**: 25 (22 modified, 2 new, 1 deleted)
- Backend: 6 files (5 modified + 1 route file)
- Frontend: 17 files (16 modified + 1 new component)
- Documentation: 1 file (bomaPath.md)
- Deleted: 1 file (white.png)

**Deployment Method**: File upload + frontend rebuild + cache clear
**Estimated Time**: 15-20 minutes
**Downtime**: None (rolling file updates)

---

## Pre-Deployment Checklist

- [x] Git branch: Boma (clean state)
- [x] Local production build successful
- [x] manifest.json at correct location (public/build/manifest.json)
- [x] All changes committed and documented
- [x] No database migrations required

---

## Step 1: Backup Current Production (5 minutes)

**Via SSH**:
```bash
ssh [username]@csjones.co -p18765

# Navigate to application root
cd ~/tengo-app

# Create backup directory with timestamp
mkdir -p ~/backups
backup_name="tengo-v0.2.9-backup-$(date +%Y%m%d-%H%M%S)"
mkdir -p ~/backups/$backup_name

# Backup critical files
cp -r app/ ~/backups/$backup_name/
cp -r resources/js/ ~/backups/$backup_name/
cp routes/api.php ~/backups/$backup_name/
cp public/build/manifest.json ~/backups/$backup_name/manifest.json.backup

# Note: Database backup NOT needed (no schema changes)

echo "Backup created at: ~/backups/$backup_name"
```

**Purpose**: Provides rollback capability if issues occur

---

## Step 2: Upload Changed Backend Files (5 minutes)

Upload the following 6 backend files via FTP/SFTP to `~/tengo-app/`:

### Backend Files (6 files)

1. **app/Http/Controllers/Api/FamilyMembersController.php**
   - Changes: Fixed spouse linking idempotency (allows re-linking already-linked accounts)

2. **app/Http/Controllers/Api/NetWorthController.php**
   - Changes: Added financial commitments aggregation, fixed asset allocation calculations

3. **app/Http/Controllers/Api/UserProfileController.php**
   - Changes: Fixed DC Pension, Property, and Protection policy field names in financial commitments
   - Fixed model namespaces (e.g., `App\Models\Retirement\DCPension`)
   - Fixed field names (e.g., `monthly_contribution_amount`, `premium_amount`)

4. **app/Services/Onboarding/EstateOnboardingFlow.php**
   - Changes: Reordered onboarding steps (Income → step 7, Expenditure → step 8)

5. **app/Services/UserProfile/UserProfileService.php**
   - Changes: Fixed expenditure calculations and property expense field names

6. **routes/api.php**
   - Changes: Added financial commitments endpoint

### Upload Method

**Via FTP/SFTP**:
```
Local Path: /Users/Chris/Desktop/fpsApp/tengo/app/
Remote Path: ~/tengo-app/app/
Action: Overwrite existing files
```

**Via SiteGround File Manager**:
1. Go to Site Tools > File Manager
2. Navigate to `~/tengo-app/app/`
3. Upload each file, replacing existing versions

---

## Step 3: Upload Changed Frontend Files (5 minutes)

Upload the following 17 frontend files via FTP/SFTP to `~/tengo-app/`:

### Vue Components & Services (17 files)

1. **resources/js/components/Estate/EstateOverviewCard.vue**
2. **resources/js/components/Estate/IHTPlanning.vue**
3. **resources/js/components/Footer.vue** (updated to v0.2.10 with "Boma Build" link)
4. **resources/js/components/NetWorth/AssetAllocationDonut.vue**
5. **resources/js/components/NetWorth/NetWorthOverview.vue**
6. **resources/js/components/NetWorth/NetWorthTrendChart.vue**
7. **resources/js/components/NetWorth/WealthSummary.vue** ⭐ **NEW FILE**
8. **resources/js/components/Onboarding/steps/IncomeStep.vue**
9. **resources/js/components/Protection/ProtectionOverviewCard.vue**
10. **resources/js/components/Trusts/TrustsOverviewCard.vue**
11. **resources/js/components/UserProfile/ExpenditureForm.vue**
12. **resources/js/services/userProfileService.js**
13. **resources/js/store/modules/estate.js**
14. **resources/js/store/modules/netWorth.js**
15. **resources/js/store/modules/protection.js**
16. **resources/js/views/Dashboard.vue**
17. **resources/js/views/UserProfile.vue**

### Upload Method

**Via FTP/SFTP**:
```
Local Path: /Users/Chris/Desktop/fpsApp/tengo/resources/
Remote Path: ~/tengo-app/resources/
Action: Overwrite existing files + upload new files
```

---

## Step 4: Delete Deprecated Assets (1 minute)

### Delete Deprecated Assets

**Via SSH**:
```bash
cd ~/tengo-app
rm -f white.png
```

**Or via File Manager**: Delete `white.png` from root directory

---

## Step 5: Rebuild Frontend Production Assets (3 minutes)

**Critical**: Frontend components have changed, so we MUST rebuild the production assets.

**Via SSH**:
```bash
cd ~/tengo-app

# Clean old build artifacts
rm -rf public/build/*

# Run production build
NODE_ENV=production npm run build

# Verify manifest.json location (CRITICAL)
ls -la public/build/manifest.json
# Must show: public/build/manifest.json (NOT in .vite/ subdirectory)

# Verify assets compiled
ls public/build/assets/ | wc -l
# Should show: 100+ files
```

**Expected Output**:
```
✓ built in 18-20s
public/build/manifest.json created
public/build/assets/ contains 100+ files
```

**Important**: If build fails, check:
1. Node.js version: `node -v` (should be v14+)
2. NPM dependencies: `npm ci` (reinstall if needed)
3. Disk space: `df -h` (ensure sufficient space)

---

## Step 6: Clear Laravel Caches (2 minutes)

**Via SSH**:
```bash
cd ~/tengo-app

# Clear all caches
php artisan cache:clear
php artisan view:clear
php artisan config:clear
php artisan route:clear

# Rebuild production caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run optimization
php artisan optimize

# Verify permissions
chmod -R 775 storage bootstrap/cache
```

**Expected Output**:
```
Application cache cleared successfully.
Compiled views cleared successfully.
Configuration cache cleared successfully.
Route cache cleared successfully.
Configuration cached successfully.
Routes cached successfully.
Blade templates cached successfully.
Files cached successfully.
```

---

## Step 7: Post-Deployment Verification (5 minutes)

### Smoke Tests

**1. Homepage Load Test**:
```
URL: https://csjones.co/tengo
Expected: Homepage loads without errors
```

**2. User Login Test**:
```
URL: https://csjones.co/tengo/login
Credentials: demo@fps.com / password
Expected: Successful login and redirect to dashboard
```

**3. Dashboard Cards Test**:
```
Action: Login and view dashboard
Expected:
- Net Worth card shows clickable navigation
- Estate Planning card displays correctly
- Protection card shows coverage indicators
- Trusts card shows "Coming Soon" placeholder
```

**4. User Profile Test**:
```
Action: Navigate to User Profile > Household Expenditure
Expected:
- Three tabs visible: My Expenditure, Spouse's Expenditure, Household Total
- Financial commitments auto-populated from assets/liabilities
- No duplicate expenditure forms
- Totals calculate correctly including commitments
```

**5. Onboarding Flow Test**:
```
Action: Create new test account and start onboarding
Expected:
- Steps reordered correctly (Income at step 7, Expenditure at step 8)
- Rental income displays after adding property
- All forms load without errors
```

**6. Footer Version Test**:
```
Action: Scroll to footer on any page
Expected: Footer shows "v0.2.10" and "Boma Build" link
```

### Error Log Check

**Via SSH**:
```bash
cd ~/tengo-app

# Check Laravel logs for errors (last 50 lines)
tail -50 storage/logs/laravel.log

# Check for PHP errors
tail -50 storage/logs/laravel-$(date +%Y-%m-%d).log
```

**Expected**: No new errors, warnings acceptable

**Via SiteGround**:
```
Site Tools > Statistics > Error Log
Expected: No 500 errors, no fatal PHP errors
```

---

## Step 8: Monitoring (15-30 minutes after deployment)

**Monitor for**:
1. Error rate spikes in Laravel logs
2. User-reported issues with expenditure forms
3. Dashboard card rendering issues
4. Financial commitments calculation errors

**Commands**:
```bash
# Follow Laravel log in real-time
tail -f ~/tengo-app/storage/logs/laravel.log

# Check last 100 log entries
tail -100 ~/tengo-app/storage/logs/laravel.log | grep ERROR
```

---

## Rollback Procedure (if needed)

If critical issues occur, rollback to v0.2.9:

**Via SSH**:
```bash
cd ~/tengo-app

# Stop any running processes
pkill -f "artisan"

# Restore backend files
backup_name="[your backup directory name from Step 1]"
cp -r ~/backups/$backup_name/app/ ./
cp ~/backups/$backup_name/api.php routes/

# Restore frontend files
cp -r ~/backups/$backup_name/resources/js/ resources/

# Rebuild frontend
NODE_ENV=production npm run build

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Verify
curl -I https://csjones.co/tengo
```

**No database rollback needed** (no migrations in v0.2.10)

---

## What Changed in v0.2.10 (28 Changes)

### Bug Fixes (18 changes)

1. Fixed expenditure form duplicate rendering
2. Fixed spouse expenditure calculations
3. Fixed financial commitments API model namespaces
4. Fixed financial commitments field names (DC Pension, Property, Protection)
5. Fixed IHT Planning display and mitigation cards
6. Fixed spouse account linking idempotency
7. Fixed rental income display in onboarding (uses fresh API data)
8. Fixed double-counting of joint commitments on spouse tab
9. Fixed household total calculations
10. Removed duplicate computed properties

### Feature Enhancements (10 changes)

11. Added Wealth Summary component with spouse data
12. Enhanced dashboard cards with uniform styling and clickable navigation
13. Reordered onboarding steps for better UX
14. Added rental income auto-display in Income step
15. Added joint-only commitments to spouse expenditure tab
16. Updated footer to v0.2.10 with "Boma Build" link
17. Centralized currency formatting to utility
18. Enhanced protection and estate cards
19. Added comprehensive test suite (30+ tests)
20. Added expenditure module documentation

### Code Quality Improvements

21. Centralized currency formatting (`resources/js/utils/currency.js`)
22. Added error logging to rental income fetch
23. Removed duplicate code in ExpenditureForm.vue
24. Added comprehensive Pest tests for financial commitments
25. Added expensemodule.md documentation
26. Fixed code to PSR-12 compliance
27. Improved component organization
28. Enhanced error handling throughout

---

## Files Modified Summary

### Backend (5 files)
- app/Http/Controllers/Api/FamilyMembersController.php
- app/Http/Controllers/Api/NetWorthController.php
- app/Http/Controllers/Api/UserProfileController.php
- app/Services/Onboarding/EstateOnboardingFlow.php
- app/Services/UserProfile/UserProfileService.php

### Frontend (21 files)
- resources/js/components/Estate/EstateOverviewCard.vue
- resources/js/components/Estate/IHTPlanning.vue
- resources/js/components/Footer.vue
- resources/js/components/NetWorth/AssetAllocationDonut.vue
- resources/js/components/NetWorth/NetWorthOverview.vue
- resources/js/components/NetWorth/NetWorthTrendChart.vue
- resources/js/components/NetWorth/WealthSummary.vue (NEW)
- resources/js/components/Onboarding/steps/IncomeStep.vue
- resources/js/components/Protection/ProtectionOverviewCard.vue
- resources/js/components/Trusts/TrustsOverviewCard.vue
- resources/js/components/UserProfile/ExpenditureForm.vue
- resources/js/services/userProfileService.js
- resources/js/store/modules/estate.js
- resources/js/store/modules/netWorth.js
- resources/js/store/modules/protection.js
- resources/js/utils/currency.js (NEW)
- resources/js/views/Dashboard.vue
- resources/js/views/UserProfile.vue
- routes/api.php

### Assets (2 files)
- estate.png (NEW)
- white.png (DELETED)

### Documentation (3 files)
- bomaPath.md (NEW)
- expensemodule.md (NEW)
- tests/Unit/Services/UserProfile/FinancialCommitmentsTest.php (NEW)

---

## Expected Outcome

After successful deployment:

- Application version: v0.2.10
- All 28 bug fixes and enhancements live
- No database changes
- No downtime experienced
- Improved code quality and maintainability
- Enhanced user experience in expenditure tracking
- Better dashboard card navigation and styling
- Comprehensive test coverage added

---

## Support & Troubleshooting

### Common Issues

**Issue**: 500 Internal Server Error
**Solution**: Check Laravel logs, verify file permissions, clear config cache

**Issue**: Assets not loading (404 errors)
**Solution**: Verify manifest.json location, rebuild frontend, check .env VITE_API_BASE_URL

**Issue**: Expenditure form not displaying correctly
**Solution**: Hard refresh browser (Ctrl+F5), clear browser cache, verify ExpenditureForm.vue uploaded

**Issue**: Financial commitments not showing
**Solution**: Check API endpoint `/api/user-profile/financial-commitments`, verify UserProfileController.php uploaded

### Get Help

**Documentation**:
- Full deployment guide: `DEPLOYMENT_GUIDE_SITEGROUND.md`
- Patch details: `bomaPath.md`
- Expenditure module: `expensemodule.md`

**SiteGround Support**:
- 24/7 Chat: https://my.siteground.com
- Site Tools: Error logs, PHP settings, SSH access

**Test Accounts**:
- Admin: admin@fps.com / admin123
- Demo: demo@fps.com / password

---

**Deployment Version**: v0.2.10 (Boma Build)
**Deployment Date**: November 20, 2025
**Target Environment**: Production (https://csjones.co/tengo)
**Database Migrations**: None (code-only deployment)

Built with Claude Code by Anthropic
