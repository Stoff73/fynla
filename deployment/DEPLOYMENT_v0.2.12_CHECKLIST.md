# Fynla v0.2.12 Deployment Checklist

**Date**: November 22, 2025
**Version**: v0.2.12 (Complete Patch - ALL 34 Sections)
**Production URL**: https://csjones.co/tengo

---

## Pre-Deployment Checks

- [ ] **Database Backup Created** (admin panel or mysqldump)
- [ ] **Backup Downloaded** to local machine
- [ ] **Git Status Clean** (all changes committed on main branch)
- [ ] **Local Build Test** (`npm run build` succeeds)
- [ ] **SSH Access Confirmed** (can connect to production)
- [ ] **Production .env Reviewed** (APP_ENV=production, APP_DEBUG=false)

---

## Step 1: Backup (5 minutes)

- [ ] Login to https://csjones.co/tengo/admin
- [ ] Create database backup via admin panel
- [ ] Download backup file (backup-YYYY-MM-DD-HHMMSS.sql)
- [ ] Save to ~/Desktop/fps-backups/
- [ ] Verify backup file size >5MB

**Backup File**: `_______________________________`

---

## Step 2: Update .env (3 minutes)

- [ ] SSH to production: `ssh [user]@csjones.co -p18765`
- [ ] Navigate: `cd ~/tengo-app`
- [ ] Backup .env: `cp .env .env.backup.$(date +%Y%m%d-%H%M%S)`
- [ ] Edit .env: `nano .env`
- [ ] Add/verify:
  ```
  ALLOWED_ORIGINS=https://csjones.co
  FRONTEND_URL=https://csjones.co
  APP_ENV=production
  APP_DEBUG=false
  ```
- [ ] Save and exit (Ctrl+X, Y, Enter)
- [ ] Verify: `grep "ALLOWED_ORIGINS" .env`

---

## Step 3: Upload Files (10 minutes)

**Total Files: 41 (15 backend + 26 frontend)**

### Backend Files (15)
- [ ] app/Http/Controllers/Api/AuthController.php
- [ ] app/Http/Controllers/Api/Estate/TrustController.php
- [ ] app/Http/Controllers/Api/FamilyMembersController.php
- [ ] app/Http/Controllers/Api/InvestmentController.php
- [ ] app/Http/Controllers/Api/MortgageController.php
- [ ] app/Http/Controllers/Api/PropertyController.php
- [ ] app/Http/Controllers/Api/UserProfileController.php
- [ ] app/Models/User.php
- [ ] app/Models/Estate/Liability.php
- [ ] app/Models/Investment/InvestmentAccount.php
- [ ] app/Services/Onboarding/OnboardingService.php
- [ ] app/Services/UserProfile/UserProfileService.php
- [ ] config/cors.php
- [ ] routes/api.php
- [ ] database/migrations/2025_11_22_092125_add_joint_ownership_to_liabilities_table.php

### Frontend Files (26)
- [ ] resources/js/components/Estate/GiftingStrategy.vue
- [ ] resources/js/components/Estate/IHTMitigationStrategies.vue
- [ ] resources/js/components/Estate/IHTPlanning.vue
- [ ] resources/js/components/Investment/PortfolioOverview.vue
- [ ] resources/js/components/NetWorth/NetWorthOverview.vue
- [ ] resources/js/components/NetWorth/PropertyCard.vue
- [ ] resources/js/components/NetWorth/Property/PropertyForm.vue
- [ ] resources/js/components/Onboarding/steps/IncomeStep.vue
- [ ] resources/js/components/Onboarding/steps/PersonalInfoStep.vue
- [ ] resources/js/components/Retirement/DCPensionForm.vue
- [ ] resources/js/components/Retirement/UnifiedPensionForm.vue
- [ ] resources/js/components/UserProfile/ExpenditureOverview.vue
- [ ] resources/js/composables/useDesignMode.js
- [ ] resources/js/constants/taxConfig.js **NEW FILE**
- [ ] resources/js/store/modules/protection.js
- [ ] resources/js/views/Dashboard.vue
- [ ] resources/js/views/Retirement/RetirementReadiness.vue

---

## Step 4: Database Changes (5 minutes)

### 4A: Production SQL - dc_pensions.provider nullable

- [ ] SSH to production
- [ ] `cd ~/tengo-app`
- [ ] Get credentials: `grep "DB_" .env`
- [ ] Connect: `mysql -u [DB_USER] -p[DB_PASSWORD] [DB_NAME]`
- [ ] Show current: `DESCRIBE dc_pensions;`
- [ ] Run SQL: `ALTER TABLE dc_pensions MODIFY COLUMN provider varchar(255) NULL;`
- [ ] Verify: `DESCRIBE dc_pensions;` (provider shows YES in Null column)
- [ ] Exit: `EXIT;`

### 4B: Migration - liabilities joint ownership

- [ ] Run: `php artisan migrate --force`
- [ ] Expected output: `2025_11_22_092125_add_joint_ownership_to_liabilities_table ......... DONE`
- [ ] Check status: `php artisan migrate:status`
- [ ] Verify: `mysql -u [DB_USER] -p -e "DESCRIBE liabilities;"` (shows ownership columns)

---

## Step 5: Build Frontend (8 minutes)

- [ ] `cd ~/tengo-app`
- [ ] Install composer: `composer install --no-dev --optimize-autoloader`
- [ ] Dump autoload: `composer dump-autoload --optimize`
- [ ] Install npm: `npm ci`
- [ ] Build assets: `npm run build`
- [ ] Verify build: `ls -lh public/build/manifest.json`
- [ ] Check assets: `ls -lh public/build/assets/ | head -20`

---

## Step 6: Clear Caches (3 minutes)

- [ ] Clear all: `php artisan optimize:clear`
- [ ] Cache config: `php artisan config:cache`
- [ ] Cache routes: `php artisan route:cache`
- [ ] Cache views: `php artisan view:cache`
- [ ] Optimize: `php artisan optimize`
- [ ] Restart PHP-FPM: `sudo systemctl restart php8.2-fpm`
- [ ] Restart Nginx: `sudo systemctl restart nginx`
- [ ] Verify services: `sudo systemctl status php8.2-fpm nginx`

---

## Step 7: Verification (10 minutes)

### Basic Tests
- [ ] Homepage loads: https://csjones.co/tengo
- [ ] Login works: demo@fps.com / password
- [ ] Dashboard displays correctly

### Security Fixes
- [ ] Check CORS: `grep -r "ALLOWED_ORIGINS" ~/tengo-app/bootstrap/cache/config.php`
- [ ] Test password with special chars: Test123!@#

### Feature Tests
- [ ] **DC Pension without provider** (Section 13):
  - Navigate to Retirement → Add Pension → DC
  - Leave provider blank
  - Save successfully

- [ ] **State Pension saves** (Section 18):
  - Navigate to Retirement → Add Pension → State
  - Fill forecast and NI years
  - Saves and displays

- [ ] **Joint investments display** (Section 32):
  - Navigate to Investment
  - Joint accounts show "Full Value" and "Your Share (50%)"

- [ ] **Tenants in common** (Section 24):
  - Navigate to Net Worth
  - TIC properties show green badge and share breakdown

- [ ] **ISA allowance** (Section 22):
  - Navigate to Investment
  - ISA accounts show contributions and remaining allowance

- [ ] **Slippery mode** (Section 11):
  - Logout and login
  - Landing page shows normal mode (not slippery)

- [ ] **IHT UI enhancements** (Section 33):
  - Navigate to Estate → IHT Planning
  - Will strategy shows amber notice
  - Projected column shows 4.7% explanation

### Database Verification
- [ ] Run verification script: `mysql -u [DB_USER] -p < verify_v0.2.12_database.sql`
- [ ] All checks show PASS

### Log Review
- [ ] Check Laravel: `tail -50 ~/tengo-app/storage/logs/laravel.log`
- [ ] Check Nginx: `sudo tail -50 /var/log/nginx/error.log`
- [ ] No critical errors

---

## Step 8: Monitor (30 minutes)

- [ ] Watch logs: `tail -f ~/tengo-app/storage/logs/laravel.log`
- [ ] Monitor for database errors
- [ ] Monitor for CORS errors
- [ ] Monitor for 500 errors

---

## Post-Deployment

- [ ] Update .env with version: `APP_VERSION=v0.2.12`
- [ ] Tag git commit: `git tag -a v0.2.12 -m "Complete patch - 34 sections"`
- [ ] Push tag: `git push origin v0.2.12`
- [ ] Document any issues encountered
- [ ] Send deployment notification (if applicable)

---

## Rollback Plan (If Needed)

### Quick Rollback (Code Only)
```bash
cd ~/tengo-app
git log --oneline -10
git checkout [PREVIOUS_COMMIT]
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan optimize:clear && php artisan config:cache
sudo systemctl restart php8.2-fpm nginx
```

### Database Rollback
```bash
php artisan migrate:rollback --step=1
mysql -u [DB_USER] -p[DB_PASSWORD] [DB_NAME]
# In MySQL:
UPDATE dc_pensions SET provider = 'Unknown' WHERE provider IS NULL;
ALTER TABLE dc_pensions MODIFY COLUMN provider varchar(255) NOT NULL;
```

### Full Restore
```bash
php artisan down
mysql -u [DB_USER] -p < storage/app/backups/backup-2025-11-22-HHMMSS.sql
php artisan up
sudo systemctl restart php8.2-fpm nginx
```

---

## Success Criteria

**Deployment is successful when ALL are checked**:

- [ ] All 41 files uploaded
- [ ] Production SQL executed (dc_pensions.provider nullable)
- [ ] Migration completed (liabilities ownership columns)
- [ ] Frontend assets built (manifest.json exists)
- [ ] Caches cleared and rebuilt
- [ ] Services restarted
- [ ] Homepage loads
- [ ] Login works
- [ ] Dashboard displays with fixes
- [ ] DC pension without provider works
- [ ] State pension saves
- [ ] Joint investments display correctly
- [ ] No critical errors in logs
- [ ] CORS uses production URLs only
- [ ] Database verification passes

---

## Deployment Record

**Deployed By**: Chris Jones
**Deployment Date**: November 22, 2025
**Deployment Time**: [Completed]
**Backup File Used**: backup-2025-11-22-[timestamp].sql
**Issues Encountered**:
- Memcached cache:clear permission denied (expected on shared hosting)
- MacOS metadata warnings during tar extraction (harmless)
**Resolution**:
- All critical caches cleared successfully (config, route, view)
- Migration completed successfully (batch [6])
- All 33 files extracted and verified
- Production deployment successful

---

## Notes

**Total Changes**:
- Security Fixes: 10 sections
- UI/Feature Fixes: 22 sections
- Code Quality: 7 improvements
- Database Changes: 2 (1 migration + 1 SQL statement)
- Files Modified: 41 files

**Key Sections**:
- Section 3: CORS environment variables REQUIRED
- Section 13: Production SQL REQUIRED (dc_pensions.provider)
- Section 31: Migration REQUIRED (liabilities ownership)

**Documentation**:
- Full deployment guide: DEPLOYMENT_PATCH_v0.2.12_COMPLETE_22Nov2025.md
- Database verification: verify_v0.2.12_database.sql
- Source documentation: friFixes21Nov.md (ALL 34 sections)

---

**Generated**: November 22, 2025
**Version**: v0.2.12
**Status**: Ready for Production Deployment

Built with Claude Code
https://claude.com/claude-code
