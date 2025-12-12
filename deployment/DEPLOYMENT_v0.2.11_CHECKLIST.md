# Fynla v0.2.11 Deployment Checklist

**Version**: v0.2.11 (22 November 2025 Patch)
**Type**: Mixed (1 Migration + Code Updates)
**Estimated Time**: 20-25 minutes
**Critical**: Database migration included - backup MANDATORY

---

## Pre-Deployment (5 minutes)

- [ ] **Create database backup** via admin panel (admin@fps.com / admin123)
- [ ] **Download backup** to local machine (verify file size > 1MB)
- [ ] **Verify git status** - all changes committed on main branch
- [ ] **Test local build** - `npm run build` completes without errors
- [ ] **Review migration file** - understand what it does
- [ ] **Confirm SSH access** - can connect to production server
- [ ] **Check production .env** - APP_ENV=production, APP_DEBUG=false

**Backup Location**: `~/Desktop/fps-backups/backup-YYYY-MM-DD-HHMMSS.sql`

---

## Step 1: Create Backups (5 minutes)

### Database Backup
- [ ] Login to admin panel → Database Backup
- [ ] Click "Create Backup"
- [ ] Download backup file
- [ ] Verify backup file downloaded successfully

### Code Backup
```bash
cd ~/tengo-app
backup_name="tengo-v0.2.10-backup-$(date +%Y%m%d-%H%M%S)"
mkdir -p ~/backups/$backup_name
cp -r app/Models/ ~/backups/$backup_name/
cp -r app/Services/ ~/backups/$backup_name/
cp -r resources/js/components/Estate/ ~/backups/$backup_name/components-estate/
cp -r resources/js/components/Investment/ ~/backups/$backup_name/components-investment/
echo "Backup created at: ~/backups/$backup_name"
```

- [ ] Code backup created
- [ ] Backup path noted for rollback

---

## Step 2: Upload Migration File (2 minutes)

**File**: `database/migrations/2025_11_22_092125_add_joint_ownership_to_liabilities_table.php`

**What it does**: Adds trust ownership support to liabilities (ownership_type, joint_owner_id, trust_id)

**Upload to**: `~/tengo-app/database/migrations/`

**Verify**:
```bash
ls -la ~/tengo-app/database/migrations/ | grep "2025_11_22"
```

- [ ] Migration file uploaded
- [ ] Verified file exists on server

---

## Step 3: Upload Backend Files (3 minutes)

Upload these 3 files to `~/tengo-app/`:

1. **app/Models/Estate/Liability.php**
   - [ ] Uploaded and verified

2. **app/Services/Onboarding/OnboardingService.php**
   - [ ] Uploaded and verified

3. **app/Services/UserProfile/UserProfileService.php**
   - [ ] Uploaded and verified

**Verify**:
```bash
ls -la ~/tengo-app/app/Models/Estate/Liability.php
ls -la ~/tengo-app/app/Services/Onboarding/OnboardingService.php
ls -la ~/tengo-app/app/Services/UserProfile/UserProfileService.php
```

- [ ] All backend files show today's date

---

## Step 4: Upload Frontend Files (3 minutes)

**IMPORTANT**: Must create new directory first!

### Create Constants Directory
```bash
mkdir -p ~/tengo-app/resources/js/constants
```

- [ ] Constants directory created

### Upload Files

Upload these 4 files:

1. **resources/js/constants/taxConfig.js** (NEW FILE)
   - [ ] Uploaded to new directory

2. **resources/js/components/Estate/IHTMitigationStrategies.vue**
   - [ ] Uploaded (overwrite existing)

3. **resources/js/components/Estate/IHTPlanning.vue**
   - [ ] Uploaded (overwrite existing)

4. **resources/js/components/Investment/PortfolioOverview.vue**
   - [ ] Uploaded (overwrite existing)

**Verify**:
```bash
ls -la ~/tengo-app/resources/js/constants/taxConfig.js
ls -la ~/tengo-app/resources/js/components/Estate/IHTMitigationStrategies.vue
ls -la ~/tengo-app/resources/js/components/Estate/IHTPlanning.vue
ls -la ~/tengo-app/resources/js/components/Investment/PortfolioOverview.vue
```

- [ ] All frontend files verified

---

## Step 5: Run Database Migration (3 minutes)

**⚠️ CRITICAL**: Verify backup exists before proceeding!

```bash
cd ~/tengo-app

# Verify backup exists
ls -lh ~/backups/backup-*.sql

# Check current migration status
php artisan migrate:status | grep "2025_11_22"

# Run migration
php artisan migrate --force
```

**Expected Output**:
```
Migrating: 2025_11_22_092125_add_joint_ownership_to_liabilities_table
Migrated:  2025_11_22_092125_add_joint_ownership_to_liabilities_table (0.15 seconds)
```

- [ ] Migration ran successfully
- [ ] No error messages displayed

**Verify Migration**:
```bash
# Check migration status
php artisan migrate:status | grep "2025_11_22"
# Expected: Ran | 2025_11_22_092125...

# Verify columns added
php artisan tinker
>>> Schema::hasColumn('liabilities', 'ownership_type');
>>> Schema::hasColumn('liabilities', 'joint_owner_id');
>>> Schema::hasColumn('liabilities', 'trust_id');
>>> exit
```

- [ ] Migration shows "Ran" status
- [ ] All three columns exist

---

## Step 6: Rebuild Frontend (4 minutes)

```bash
cd ~/tengo-app

# Clean old build
rm -rf public/build/*

# Run production build
NODE_ENV=production npm run build
```

**Expected Output**:
```
✓ built in 18-25s
public/build/manifest.json created
```

**Verify Build**:
```bash
# Check manifest.json exists at correct location
ls -la public/build/manifest.json

# Count assets
ls public/build/assets/ | wc -l
# Should show: 100+
```

- [ ] Build completed successfully
- [ ] manifest.json exists
- [ ] Assets compiled (100+ files)

---

## Step 7: Clear Caches (2 minutes)

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

# Optimize
php artisan optimize

# Fix permissions
chmod -R 775 storage bootstrap/cache
```

- [ ] All caches cleared
- [ ] Production caches rebuilt
- [ ] Permissions set

---

## Step 8: Post-Deployment Verification (8 minutes)

### Database Verification

```bash
# Check migration applied
php artisan migrate:status | grep "2025_11_22"

# Verify all existing liabilities defaulted to 'individual'
mysql -u [DB_USER] -p[DB_PASS] [DB_NAME] -e "
  SELECT COUNT(*) as total,
         COUNT(CASE WHEN ownership_type = 'individual' THEN 1 END) as individual
  FROM liabilities;
"
```

- [ ] Migration shows "Ran"
- [ ] All liabilities have ownership_type = 'individual'

### Application Tests

**Test 1: Homepage**
- [ ] Visit https://csjones.co/tengo
- [ ] Page loads without errors (HTTP 200)
- [ ] No console errors in browser DevTools

**Test 2: User Login**
- [ ] Login with demo@fps.com / password
- [ ] Successful login and redirect to dashboard
- [ ] Dashboard displays all cards

**Test 3: IHT Planning (Section 33 Changes)**
- [ ] Navigate to Estate Planning → IHT Planning
- [ ] "Projected" column shows methodology note: "This is a static future value calculation using 4.7%"
- [ ] Expand Will strategy card (if visible)
- [ ] Shows amber warning: "⚠️ Note: Full will functionality has not been implemented"

**Test 4: Investment Portfolio (Section 34 Changes)**
- [ ] Navigate to Investment → Portfolio Overview
- [ ] ISA accounts show "ISA Contributions This Year" and "Remaining Allowance"
- [ ] No browser console errors about TAX_CONFIG
- [ ] Year-to-date returns display with color coding

**Test 5: Error Logs**
```bash
# Check for errors
tail -50 storage/logs/laravel.log | grep ERROR
```
- [ ] No new ERROR level messages

---

## Step 9: Monitoring (30 minutes)

**0-5 minutes** (Active):
- [ ] Homepage loads correctly
- [ ] User login works
- [ ] Dashboard renders
- [ ] No 500 errors in logs

**5-15 minutes** (Passive):
- [ ] Check logs every 2-3 minutes
- [ ] Test complex user flow
- [ ] No performance issues

**15-30 minutes** (Periodic):
- [ ] Check logs every 5 minutes
- [ ] Monitor for error patterns
- [ ] Test from different browsers

**Monitor Command**:
```bash
tail -f ~/tengo-app/storage/logs/laravel.log
```

---

## Rollback Procedure (if needed)

**⚠️ ONLY IF CRITICAL ISSUES**

### Rollback Migration
```bash
cd ~/tengo-app
php artisan migrate:rollback --step=1
php artisan migrate:status | grep "2025_11_22"
# Should show: Pending
```

### Restore Code
```bash
backup_name="[your backup from Step 1]"
cp -r ~/backups/$backup_name/Models/* app/Models/
cp -r ~/backups/$backup_name/Services/* app/Services/
cp -r ~/backups/$backup_name/components-estate/* resources/js/components/Estate/
cp -r ~/backups/$backup_name/components-investment/* resources/js/components/Investment/
rm -rf resources/js/constants/
```

### Rebuild
```bash
rm -rf public/build/*
NODE_ENV=production npm run build
php artisan cache:clear
php artisan config:clear
php artisan config:cache
php artisan route:cache
```

- [ ] Migration rolled back
- [ ] Code restored
- [ ] Frontend rebuilt
- [ ] Application functional

---

## Success Criteria

**Deployment is SUCCESSFUL when ALL checked**:

- [ ] Migration shows "Ran" status
- [ ] Database has ownership_type, joint_owner_id, trust_id columns
- [ ] Frontend build created manifest.json at correct location
- [ ] Assets directory has 100+ files
- [ ] Homepage loads (HTTP 200)
- [ ] User can login
- [ ] Dashboard renders correctly
- [ ] IHT Planning shows methodology note
- [ ] Investment Portfolio shows ISA allowances
- [ ] No ERROR level messages in Laravel logs
- [ ] Page load times < 3 seconds

---

## Quick Reference

**Files Changed**: 9 total
- 1 migration (NEW)
- 3 backend files (modified)
- 3 frontend components (modified)
- 1 constants file (NEW)

**Documentation**:
- Full guide: `/DEPLOYMENT/DEPLOYMENT_PATCH_v0.2.11_22Nov2025.md`
- SQL verification: `/DEPLOYMENT/verify_v0.2.11_migration.sql`
- Changes log: `friFixes21Nov.md` sections 33-34

**Test Accounts**:
- Admin: admin@fps.com / admin123
- Demo: demo@fps.com / password

**Production URL**: https://csjones.co/tengo

---

**Status**: □ Not Started | □ In Progress | □ Complete | □ Rolled Back

**Deployment Time**: _____ to _____ (total: _____ minutes)

**Notes**:
```
[Space for deployment notes, issues encountered, etc.]
```

---

Built with [Claude Code](https://claude.com/claude-code) by Anthropic
