# Fynla v0.2.11 (22 November 2025 Patch) - Production Deployment Instructions

**Date**: November 22, 2025
**Branch**: main (style branch merged)
**Version**: v0.2.11
**Type**: Mixed Deployment (1 Database Migration + Code Updates)
**Production URL**: https://csjones.co/tengo
**Application Root**: ~/tengo-app/

---

## Deployment Overview

This deployment includes:
- **Database Migration**: 1 new migration (adds trust ownership support to liabilities)
- **Backend Changes**: 4 files modified
- **Frontend Changes**: 4 files modified (3 existing + 1 new)
- **Changes Documentation**: Section 33 (IHT Planning UI) + Section 34 (Code Quality)

**Total Files**: 9 files (8 modified + 1 new constant file)
- Backend: 5 files (4 models/services + 1 migration)
- Frontend: 4 files (3 Vue components + 1 new constants file)

**Deployment Method**: File upload + database migration + frontend rebuild + cache clear
**Estimated Time**: 20-25 minutes
**Downtime**: Minimal (< 30 seconds during migration)

---

## Changes Summary (friFixes21Nov.md Sections 33-34)

### Section 33: IHT Planning UI Enhancements
1. **Will Strategy Implementation Notice**: Added warning that full will functionality not yet implemented
2. **Projection Methodology Note**: Added 4.7% growth rate explanation to projected values

### Section 34: Code Quality Improvements (7 Fixes)
1. **Trust Ownership for Liabilities** (HIGH): Added 'trust' to ownership_type enum + trust_id field
2. **Null Safety in OnboardingService** (HIGH): Added defensive null checks for spouse data
3. **Eloquent Relationships** (MEDIUM): Added jointOwner() and trust() methods to Liability model
4. **Ownership Filter Extraction** (MEDIUM): Created shouldIncludeByOwnership() helper method
5. **Improved Comments** (LOW): Enhanced joint investment display logic comments
6. **Centralized ISA Allowance** (LOW): Created taxConfig.js constants file
7. **Method Naming** (LOW): Renamed getReturnColourClass → getReturnColorClass

---

## Pre-Deployment Checklist

**CRITICAL - Complete BEFORE starting deployment**:

- [ ] **Database Backup Created**: Via admin panel (https://csjones.co/tengo/admin) OR manual mysqldump
- [ ] **Backup Downloaded**: Stored safely locally with timestamp
- [ ] **Git Status Clean**: All changes committed on main branch
- [ ] **Local Build Test**: `npm run build` completes successfully
- [ ] **Migration Reviewed**: Understand what `2025_11_22_092125_add_joint_ownership_to_liabilities_table.php` does
- [ ] **SSH Access Confirmed**: Can connect to production server
- [ ] **Production .env Verified**: APP_ENV=production, APP_DEBUG=false

---

## Step 1: Create Production Database Backup (CRITICAL - 5 minutes)

**⚠️ WARNING**: This deployment includes a database migration. A backup is MANDATORY.

### Method 1: Admin Panel Backup (RECOMMENDED)

**Via Browser**:
```
1. Login to https://csjones.co/tengo/admin
   Credentials: admin@fps.com / admin123

2. Navigate to Admin Panel → Database Backup

3. Click "Create Backup" button

4. Wait for confirmation message "Backup created successfully"

5. Click "Download Backup" to save locally

6. Verify file exists in your Downloads folder:
   Format: backup-YYYY-MM-DD-HHMMSS.sql
   Example: backup-2025-11-22-143022.sql

7. Move to safe location:
   ~/Desktop/fps-backups/backup-2025-11-22-143022.sql
```

**Backup Location on Server**: `~/tengo-app/storage/app/backups/`

### Method 2: Manual Database Backup (ALTERNATIVE)

**Via SSH** (if admin panel unavailable):
```bash
ssh [username]@csjones.co -p18765

cd ~/tengo-app

# Get database credentials from .env
grep "DB_" .env

# Create backup using mysqldump
mysqldump -u [DB_USERNAME] -p[DB_PASSWORD] [DB_DATABASE] > backup-$(date +%Y-%m-%d-%H%M%S).sql

# Verify backup created
ls -lh backup-*.sql
# Should show file size > 1MB

# Download backup to local machine (in new terminal)
scp -P 18765 [username]@csjones.co:~/tengo-app/backup-*.sql ~/Desktop/fps-backups/
```

**Verification**:
```bash
# Check backup file is valid SQL
head -20 backup-*.sql
# Should show: -- MySQL dump 10.13  Distrib...
```

**✅ DO NOT PROCEED** until backup is created and downloaded successfully.

---

## Step 2: Backup Current Production Code (5 minutes)

Create rollback point for code changes.

**Via SSH**:
```bash
ssh [username]@csjones.co -p18765

cd ~/tengo-app

# Create backup directory with timestamp
mkdir -p ~/backups
backup_name="tengo-v0.2.10-backup-$(date +%Y%m%d-%H%M%S)"
mkdir -p ~/backups/$backup_name

# Backup files that will change
cp -r app/Models/ ~/backups/$backup_name/
cp -r app/Services/ ~/backups/$backup_name/
cp -r resources/js/components/Estate/ ~/backups/$backup_name/components-estate/
cp -r resources/js/components/Investment/ ~/backups/$backup_name/components-investment/
cp public/build/manifest.json ~/backups/$backup_name/manifest.json.backup

# Create marker file with deployment info
echo "Backup created before v0.2.11 deployment on $(date)" > ~/backups/$backup_name/README.txt
echo "Database backup: backup-$(date +%Y-%m-%d)-*.sql" >> ~/backups/$backup_name/README.txt

# Verify backup created
ls -la ~/backups/$backup_name/
# Should show: Models/, Services/, components-estate/, components-investment/, manifest.json.backup, README.txt

echo "Code backup created at: ~/backups/$backup_name"
```

**Purpose**: Provides file-level rollback if migration succeeds but code causes issues.

---

## Step 3: Upload New Database Migration (2 minutes)

**⚠️ CRITICAL**: Upload migration file BEFORE running `php artisan migrate`.

### Migration Details

**File**: `database/migrations/2025_11_22_092125_add_joint_ownership_to_liabilities_table.php`

**What it does**:
1. Adds `ownership_type` ENUM column: `['individual', 'joint', 'trust']` (default: 'individual')
2. Adds `joint_owner_id` foreign key to users table (nullable)
3. Adds `trust_id` foreign key to trusts table (nullable)
4. Creates indexes on `joint_owner_id` and `trust_id` for performance

**Why it's needed**:
- Liabilities previously only supported individual ownership
- Code in UserProfileService.php references `$liability->ownership_type` but field didn't exist
- This brings liabilities into consistency with Properties, Investments, and Savings (all support individual/joint/trust)

**Impact on existing data**:
- NO DATA LOSS - purely additive migration
- All existing liability records will default to `ownership_type = 'individual'`
- No records are modified, updated, or deleted

### Upload Migration File

**Via SFTP/FTP**:
```
Local Path:  /Users/Chris/Desktop/fpsApp/tengo/database/migrations/2025_11_22_092125_add_joint_ownership_to_liabilities_table.php
Remote Path: ~/tengo-app/database/migrations/2025_11_22_092125_add_joint_ownership_to_liabilities_table.php
Action:      Upload new file
```

**Via SiteGround File Manager**:
1. Go to Site Tools → File Manager
2. Navigate to `~/tengo-app/database/migrations/`
3. Click "Upload" button
4. Select `2025_11_22_092125_add_joint_ownership_to_liabilities_table.php`
5. Verify file appears in file list

**Verify Upload**:
```bash
# Via SSH
ls -la ~/tengo-app/database/migrations/ | grep "2025_11_22"
# Should show: 2025_11_22_092125_add_joint_ownership_to_liabilities_table.php
```

---

## Step 4: Upload Backend Files (3 minutes)

Upload the following 4 backend files via FTP/SFTP to `~/tengo-app/`:

### Backend Files (4 files)

#### 1. app/Models/Estate/Liability.php
**Changes**:
- Added `ownership_type`, `joint_owner_id`, `trust_id` to $fillable array (line 23-26)
- Added `jointOwner()` relationship method (lines 75-81)
- Added `trust()` relationship method (lines 83-89)

**Why**: Model must support new database fields from migration

**Upload Path**: `~/tengo-app/app/Models/Estate/Liability.php`

---

#### 2. app/Services/Onboarding/OnboardingService.php
**Changes**:
- Added null safety checks in `getStepDataFromUser()` method (lines 1199-1214)
- Changed `if ($spouse)` to `if ($spouse !== null)`
- Added null coalescing operators: `$spouse->monthly_expenditure ?? 0`

**Why**: Prevent exceptions when spouse account deleted but spouse_id still exists

**Critical Lines**:
```php
// Line 1199: Defensive null check
if ($spouse !== null) {
    $hasSpouseExpenditureData = ($spouse->monthly_expenditure ?? 0) > 0 ||
                               ($spouse->annual_expenditure ?? 0) > 0 ||
                               // ... all fields now use ?? 0
```

**Upload Path**: `~/tengo-app/app/Services/Onboarding/OnboardingService.php`

---

#### 3. app/Services/UserProfile/UserProfileService.php
**Changes**:
- Added `shouldIncludeByOwnership()` helper method (lines 625-636)
- Replaced 4 instances of duplicated ownership filter logic with helper calls
- Fixed DC Pension ownership logic (set all to 'individual')

**Why**: DRY principle - eliminate 8 lines of duplicated code

**New Method** (lines 625-636):
```php
private function shouldIncludeByOwnership(bool $isJoint, string $filter): bool
{
    return match($filter) {
        'joint_only' => $isJoint,
        'individual_only' => !$isJoint,
        'all' => true,
        default => true,
    };
}
```

**Upload Path**: `~/tengo-app/app/Services/UserProfile/UserProfileService.php`

---

### Upload Method

**Via SFTP/FTP Client** (Recommended):
```
Local Base:  /Users/Chris/Desktop/fpsApp/tengo/
Remote Base: ~/tengo-app/

Files to upload (overwrite existing):
1. app/Models/Estate/Liability.php
2. app/Services/Onboarding/OnboardingService.php
3. app/Services/UserProfile/UserProfileService.php
```

**Via SiteGround File Manager**:
1. Navigate to `~/tengo-app/app/Models/Estate/`
2. Upload `Liability.php` (replace existing)
3. Navigate to `~/tengo-app/app/Services/Onboarding/`
4. Upload `OnboardingService.php` (replace existing)
5. Navigate to `~/tengo-app/app/Services/UserProfile/`
6. Upload `UserProfileService.php` (replace existing)

**Verify Backend Upload**:
```bash
# Via SSH - check file modification times
ls -la ~/tengo-app/app/Models/Estate/Liability.php
ls -la ~/tengo-app/app/Services/Onboarding/OnboardingService.php
ls -la ~/tengo-app/app/Services/UserProfile/UserProfileService.php

# All should show today's date
```

---

## Step 5: Upload Frontend Files (3 minutes)

Upload the following 4 frontend files (3 existing + 1 new):

### Frontend Vue Components (3 files)

#### 1. resources/js/components/Estate/IHTMitigationStrategies.vue
**Changes** (Section 33.1):
- Added implementation status notice to Will strategy card (line 1092-1096)
- Shows amber warning: "⚠️ Note: Full will functionality has not been implemented"

**Why**: Inform users that will feature is not yet complete

**Upload Path**: `~/tengo-app/resources/js/components/Estate/IHTMitigationStrategies.vue`

---

#### 2. resources/js/components/Estate/IHTPlanning.vue
**Changes** (Section 33.2):
- Added projection methodology note to "Projected" column header (line 585-587)
- Shows: "This is a static future value calculation using 4.7%"

**Why**: Transparency - users understand the growth assumption

**Upload Path**: `~/tengo-app/resources/js/components/Estate/IHTPlanning.vue`

---

#### 3. resources/js/components/Investment/PortfolioOverview.vue
**Changes** (Section 34.5, 34.6, 34.7):
- **Improved comments**: Joint investment display logic (lines 315-320)
- **ISA allowance**: Now imports from TAX_CONFIG constant instead of hardcoding 20000
- **Method rename**: `getReturnColourClass` → `getReturnColorClass` (American spelling)

**Critical Changes**:
```javascript
// Line 10: Import TAX_CONFIG
import { TAX_CONFIG } from '@/constants/taxConfig';

// Line 127: Use constant instead of hardcoded value
getIsaRemaining(account) {
  const contributions = this.getIsaContributions(account);
  return Math.max(0, TAX_CONFIG.ISA_ANNUAL_ALLOWANCE - contributions);
}

// Line 213: Method renamed
getReturnColorClass(value) { ... }
```

**Upload Path**: `~/tengo-app/resources/js/components/Investment/PortfolioOverview.vue`

---

### NEW Frontend Constants File (1 file)

#### 4. resources/js/constants/taxConfig.js ⭐ NEW FILE
**Purpose** (Section 34.6):
- Centralized UK tax configuration constants
- Single source of truth for frequently used tax values
- Easier to update for new tax years

**File Contents**:
```javascript
export const TAX_CONFIG = {
  ISA_ANNUAL_ALLOWANCE: 20000,
  LIFETIME_ISA_ALLOWANCE: 4000,
  JUNIOR_ISA_ALLOWANCE: 9000,
  PERSONAL_ALLOWANCE: 12570,
  PENSION_ANNUAL_ALLOWANCE: 60000,
  CGT_ALLOWANCE: 3000,
};
```

**Upload Path**: `~/tengo-app/resources/js/constants/taxConfig.js`

**⚠️ IMPORTANT**: This is a NEW directory. You must create `~/tengo-app/resources/js/constants/` first.

---

### Upload Method

**Via SFTP/FTP Client**:
```
Local Base:  /Users/Chris/Desktop/fpsApp/tengo/
Remote Base: ~/tengo-app/

Files to upload:
1. resources/js/components/Estate/IHTMitigationStrategies.vue (overwrite)
2. resources/js/components/Estate/IHTPlanning.vue (overwrite)
3. resources/js/components/Investment/PortfolioOverview.vue (overwrite)
4. resources/js/constants/taxConfig.js (NEW - create directory first)
```

**Via SiteGround File Manager**:
1. Navigate to `~/tengo-app/resources/js/`
2. **Create new folder**: Click "Create Folder" → Name: `constants`
3. Navigate to `~/tengo-app/resources/js/constants/`
4. Upload `taxConfig.js`
5. Navigate to `~/tengo-app/resources/js/components/Estate/`
6. Upload `IHTMitigationStrategies.vue` (replace existing)
7. Upload `IHTPlanning.vue` (replace existing)
8. Navigate to `~/tengo-app/resources/js/components/Investment/`
9. Upload `PortfolioOverview.vue` (replace existing)

**Verify Frontend Upload**:
```bash
# Via SSH - verify all files present
ls -la ~/tengo-app/resources/js/components/Estate/IHTMitigationStrategies.vue
ls -la ~/tengo-app/resources/js/components/Estate/IHTPlanning.vue
ls -la ~/tengo-app/resources/js/components/Investment/PortfolioOverview.vue
ls -la ~/tengo-app/resources/js/constants/taxConfig.js

# Verify constants directory created
ls -la ~/tengo-app/resources/js/constants/
# Should show: taxConfig.js

# All files should show today's date
```

---

## Step 6: Run Database Migration (3 minutes)

**⚠️ CRITICAL STEP**: This alters the production database. Backup must exist before proceeding.

### Pre-Migration Checks

**Via SSH**:
```bash
cd ~/tengo-app

# 1. Verify backup exists
ls -lh ~/backups/backup-*.sql
# Should show recent backup file with size > 1MB

# 2. Check current migration status
php artisan migrate:status
# Should show all existing migrations with "Ran" status

# 3. Verify environment
php artisan env
# Should show: Current application environment: production

# 4. Check database connection
php artisan tinker
>>> DB::connection()->getPdo();
>>> exit
# Should connect without errors
```

### Understanding the Migration

**Migration File**: `2025_11_22_092125_add_joint_ownership_to_liabilities_table.php`

**SQL That Will Execute**:
```sql
-- Add ownership_type column (default: individual)
ALTER TABLE liabilities
ADD COLUMN ownership_type ENUM('individual', 'joint', 'trust')
DEFAULT 'individual'
AFTER user_id;

-- Add joint_owner_id column (nullable)
ALTER TABLE liabilities
ADD COLUMN joint_owner_id BIGINT UNSIGNED NULL
AFTER ownership_type;

-- Add trust_id column (nullable)
ALTER TABLE liabilities
ADD COLUMN trust_id BIGINT UNSIGNED NULL
AFTER joint_owner_id;

-- Add foreign key constraints
ALTER TABLE liabilities
ADD CONSTRAINT liabilities_joint_owner_id_foreign
FOREIGN KEY (joint_owner_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE liabilities
ADD CONSTRAINT liabilities_trust_id_foreign
FOREIGN KEY (trust_id) REFERENCES trusts(id) ON DELETE SET NULL;

-- Add indexes for performance
CREATE INDEX liabilities_joint_owner_id_index ON liabilities(joint_owner_id);
CREATE INDEX liabilities_trust_id_index ON liabilities(trust_id);
```

**Expected Changes**:
- 3 new columns added to `liabilities` table
- 2 foreign key constraints created
- 2 indexes created
- All existing records get `ownership_type = 'individual'` (no data change)

**Estimated Time**: 2-5 seconds (production has < 50 liability records)

### Run Migration

**Via SSH**:
```bash
cd ~/tengo-app

# Run migration with --force flag (required in production)
php artisan migrate --force

# Expected output:
# Migrating: 2025_11_22_092125_add_joint_ownership_to_liabilities_table
# Migrated:  2025_11_22_092125_add_joint_ownership_to_liabilities_table (0.15 seconds)
```

**✅ Success Indicators**:
- Output shows: "Migrated: 2025_11_22_092125_add_joint_ownership_to_liabilities_table"
- No error messages
- Time shown (e.g., 0.15 seconds)

**❌ Failure Indicators**:
- SQLSTATE errors
- Foreign key constraint failures
- "Nothing to migrate" (migration file not uploaded)

### Verify Migration Success

**Via SSH**:
```bash
cd ~/tengo-app

# 1. Check migration status (should show "Ran")
php artisan migrate:status | grep "2025_11_22"
# Expected: Ran | 2025_11_22_092125_add_joint_ownership_to_liabilities_table

# 2. Verify database schema changes
php artisan tinker
>>> Schema::hasColumn('liabilities', 'ownership_type');
// Should return: true
>>> Schema::hasColumn('liabilities', 'joint_owner_id');
// Should return: true
>>> Schema::hasColumn('liabilities', 'trust_id');
// Should return: true
>>> exit

# 3. Check existing data (all should be 'individual')
mysql -u [DB_USERNAME] -p[DB_PASSWORD] [DB_DATABASE] -e "SELECT id, ownership_type FROM liabilities LIMIT 5;"
# Expected: All rows show ownership_type = 'individual'

# 4. Describe table structure
mysql -u [DB_USERNAME] -p[DB_PASSWORD] [DB_DATABASE] -e "DESCRIBE liabilities;"
# Should show new columns:
# - ownership_type | enum('individual','joint','trust') | NO | | individual
# - joint_owner_id | bigint unsigned | YES | MUL | NULL
# - trust_id       | bigint unsigned | YES | MUL | NULL
```

**If Migration Fails**: See "Migration Rollback Procedure" in Step 11.

---

## Step 7: Rebuild Frontend Production Assets (4 minutes)

**Critical**: Frontend components have changed, so we MUST rebuild production assets.

**Via SSH**:
```bash
cd ~/tengo-app

# 1. Clean old build artifacts
rm -rf public/build/*

# 2. Verify Node.js and NPM versions
node -v
# Should show: v14.x or higher
npm -v
# Should show: v6.x or higher

# 3. Check disk space (need at least 500MB free)
df -h .
# Avail column should show > 500M

# 4. Run production build
NODE_ENV=production npm run build

# Expected output:
# vite v4.x.x building for production...
# ✓ XX modules transformed.
# ✓ built in 18-25s
# public/build/manifest.json created
```

**Build Process Notes**:
- Build time: 18-25 seconds typical
- Progress shown: modules transforming, then building
- Final output shows file count and timing

### Verify Build Success

**Via SSH**:
```bash
cd ~/tengo-app

# 1. CRITICAL: Verify manifest.json location
ls -la public/build/manifest.json
# Must show: public/build/manifest.json (NOT in .vite/ subdirectory)
# Example: -rw-r--r-- 1 user user 8472 Nov 22 14:30 public/build/manifest.json

# 2. Check manifest.json content
head -10 public/build/manifest.json
# Should show JSON with asset mappings

# 3. Verify assets compiled
ls public/build/assets/ | wc -l
# Should show: 100+ files

# 4. Check asset file sizes
du -sh public/build/assets/
# Should show: 2-4MB total

# 5. Verify specific component files exist (sample check)
ls public/build/assets/ | grep -E "(app|vendor|PortfolioOverview|IHTPlanning)"
# Should show compiled JS chunks with hash names
```

**❌ Build Failures - Troubleshooting**:

**Issue**: `npm run build` fails with "Cannot find module"
**Solution**:
```bash
# Reinstall dependencies
rm -rf node_modules package-lock.json
npm ci
NODE_ENV=production npm run build
```

**Issue**: Manifest.json in wrong location (public/build/.vite/manifest.json)
**Solution**:
```bash
# Check vite.config.js settings
grep "manifest" vite.config.js
# Should show: manifest: true (not a custom path)

# Clean and rebuild
rm -rf public/build
NODE_ENV=production npm run build
```

**Issue**: Out of disk space
**Solution**:
```bash
# Check disk usage
df -h .
# If < 500MB free, clean old backups/logs
rm -rf ~/backups/old-backup-*
rm -rf storage/logs/laravel-*.log
```

**Issue**: Permission denied errors
**Solution**:
```bash
chmod -R 775 public/build
NODE_ENV=production npm run build
```

---

## Step 8: Clear Laravel Caches (2 minutes)

**Via SSH**:
```bash
cd ~/tengo-app

# 1. Clear all caches (fresh start)
php artisan cache:clear
php artisan view:clear
php artisan config:clear
php artisan route:clear

# Expected output (for each):
# Application cache cleared successfully.
# Compiled views cleared successfully.
# Configuration cache cleared successfully.
# Route cache cleared successfully.

# 2. Rebuild production caches (performance optimization)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Expected output (for each):
# Configuration cached successfully.
# Routes cached successfully.
# Blade templates cached successfully.

# 3. Run general optimization
php artisan optimize

# Expected output:
# Configuration cache cleared successfully.
# Configuration cached successfully.
# Route cache cleared successfully.
# Routes cached successfully.
# Files cached successfully.

# 4. Verify storage permissions
chmod -R 775 storage bootstrap/cache
chown -R [your-user]:www-data storage bootstrap/cache

# 5. List cache files to verify
ls -la storage/framework/cache/
ls -la bootstrap/cache/
# Should show recently modified files
```

**Why This Step is Critical**:
- Config cache: Ensures Laravel uses production .env values
- Route cache: Improves routing performance
- View cache: Compiles Blade templates for faster rendering
- Permissions: Ensures Laravel can write to cache/log directories

---

## Step 9: Post-Deployment Verification (8 minutes)

**CRITICAL**: Do not skip these tests. They verify the deployment succeeded.

### 9.1 Database Migration Verification

**Via SSH**:
```bash
cd ~/tengo-app

# Test 1: Check migration ran
php artisan migrate:status | grep "2025_11_22"
# Expected: Ran | 2025_11_22_092125_add_joint_ownership_to_liabilities_table

# Test 2: Query liabilities table
mysql -u [DB_USERNAME] -p[DB_PASSWORD] [DB_DATABASE] -e "
  SELECT COUNT(*) as total_liabilities,
         COUNT(CASE WHEN ownership_type = 'individual' THEN 1 END) as individual_count,
         COUNT(CASE WHEN ownership_type = 'joint' THEN 1 END) as joint_count,
         COUNT(CASE WHEN ownership_type = 'trust' THEN 1 END) as trust_count
  FROM liabilities;
"
# Expected: All liabilities show ownership_type = 'individual' (existing data unchanged)

# Test 3: Verify foreign keys exist
mysql -u [DB_USERNAME] -p[DB_PASSWORD] [DB_DATABASE] -e "
  SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_NAME = 'liabilities' AND TABLE_SCHEMA = '[DB_DATABASE]';
"
# Expected: Shows liabilities_joint_owner_id_foreign → users
#           Shows liabilities_trust_id_foreign → trusts
```

**✅ Pass Criteria**:
- Migration shows "Ran" status
- All existing liabilities have `ownership_type = 'individual'`
- Foreign keys created successfully

---

### 9.2 Frontend Build Verification

**Via Browser**:
```
1. Hard refresh homepage (Ctrl+Shift+R or Cmd+Shift+R)
   URL: https://csjones.co/tengo
   Expected: Page loads without 404 errors in browser console

2. Open browser DevTools (F12) → Console tab
   Expected: No 404 errors for JS/CSS files
   Expected: No "Failed to load resource" errors

3. Check Network tab → Filter: JS
   Expected: All app-*.js and vendor-*.js files return 200 status
   Expected: File names include hash (e.g., app-abc123.js)
```

**Via SSH**:
```bash
# Test manifest.json is valid
cat ~/tengo-app/public/build/manifest.json | python3 -m json.tool > /dev/null
echo $?
# Expected: 0 (valid JSON)

# Test assets are accessible
curl -I https://csjones.co/tengo/build/manifest.json
# Expected: HTTP/2 200
```

**✅ Pass Criteria**:
- Homepage loads without console errors
- All JS/CSS assets return 200 status
- manifest.json is valid JSON and accessible

---

### 9.3 Application Smoke Tests

**Test 1: User Login**
```
URL: https://csjones.co/tengo/login
Credentials: demo@fps.com / password
Expected: Successful login and redirect to dashboard
```

**Test 2: Dashboard Load**
```
Action: View dashboard after login
Expected:
- Dashboard displays without errors
- All cards render (Net Worth, Protection, Savings, Investment, Retirement, Estate)
- No blank cards or loading spinners stuck
```

**Test 3: IHT Planning Tab (Section 33 Changes)**
```
Action: Navigate to Estate Planning module → IHT Planning tab
Tests:
1. View "Projected" column header
   Expected: Shows "(Age XX)" and methodology note "This is a static future value calculation using 4.7%"

2. Navigate to Mitigation Strategies section
   Expected: Cards display recommendations

3. Expand Will strategy card (if visible)
   Expected: Shows amber warning box "⚠️ Note: Full will functionality has not been implemented"
```

**Test 4: Investment Portfolio (Section 34 Changes)**
```
Action: Navigate to Investment module → Portfolio Overview
Tests:
1. View ISA accounts
   Expected: Shows "ISA Contributions This Year: £X,XXX"
   Expected: Shows "Remaining Allowance: £X,XXX" in green/amber/red

2. Check browser console
   Expected: No errors about TAX_CONFIG not found
   Expected: No errors about getReturnColourClass undefined

3. Hover over year-to-date returns
   Expected: Color coding works (green positive, red negative)
```

**Test 5: User Profile Financial Commitments**
```
Action: Navigate to User Profile → Household Expenditure
Tests:
1. View financial commitments section
   Expected: Shows DC pensions, properties, liabilities, protection policies

2. Check ownership filtering (if married with spouse)
   Expected: Spouse tab shows only joint commitments
   Expected: User tab shows individual + joint commitments
```

**Test 6: Liability Management (New Feature)**
```
Action: Navigate to Estate Planning → Assets & Liabilities → Liabilities tab
Tests:
1. View existing liabilities
   Expected: All show ownership type (should default to "Individual")

2. Add new liability (if testing)
   Expected: Ownership type field available (Individual/Joint/Trust)
   Note: UI for joint/trust may not be fully implemented yet
```

---

### 9.4 Error Log Check

**Via SSH**:
```bash
cd ~/tengo-app

# Check Laravel logs for errors (last 100 lines)
tail -100 storage/logs/laravel.log

# Check for ERROR level messages only
grep "ERROR" storage/logs/laravel.log | tail -20

# Check for today's errors specifically
grep "$(date +%Y-%m-%d)" storage/logs/laravel.log | grep ERROR

# Check PHP-FPM error log (if accessible)
sudo tail -50 /var/log/php8.2-fpm.log
```

**Expected**:
- No new ERROR level messages after deployment time
- Warnings (WARN) are acceptable for deprecations
- INFO messages about cache clearing are normal

**Via SiteGround**:
```
Site Tools → Statistics → Error Log
Time Range: Last 1 hour
Expected: No 500 Internal Server Error entries
Expected: No Fatal Error entries
```

---

### 9.5 Performance Verification

**Via Browser DevTools**:
```
1. Open DevTools → Network tab
2. Hard refresh dashboard (Ctrl+Shift+R)
3. Check metrics:
   - Total requests: < 50 typical
   - Total transfer: 2-4MB typical
   - Load time: < 3 seconds (on good connection)
   - All resources return 200 status
```

**Via SSH** (Response Time):
```bash
# Test homepage response time
time curl -I https://csjones.co/tengo
# Expected: real time < 1.0s

# Test API endpoint response time
time curl -I https://csjones.co/tengo/api/user/financial-commitments \
  -H "Authorization: Bearer [valid-token]"
# Expected: real time < 2.0s
```

**✅ Pass Criteria**:
- Homepage loads in < 3 seconds
- No 404 or 500 errors
- All critical user flows functional

---

## Step 10: Monitoring (30 minutes post-deployment)

**CRITICAL**: Actively monitor for first 30 minutes after deployment.

### Real-Time Log Monitoring

**Terminal 1 - Laravel Logs**:
```bash
ssh [username]@csjones.co -p18765
cd ~/tengo-app
tail -f storage/logs/laravel.log
```

**Watch for**:
- ❌ ERROR level messages (investigate immediately)
- ❌ SQLSTATE errors (database issues)
- ❌ Class not found errors (autoload issues)
- ✅ INFO messages (normal)

**Terminal 2 - Web Server Logs** (if accessible):
```bash
sudo tail -f /var/log/nginx/error.log
# or
sudo tail -f /var/log/apache2/error.log
```

**Watch for**:
- ❌ 500 Internal Server Error
- ❌ PHP Fatal Error
- ❌ Segmentation fault

### Monitoring Checklist

**0-5 minutes**: Active verification
- [ ] Homepage loads without errors
- [ ] User login works
- [ ] Dashboard displays correctly
- [ ] No 500 errors in logs

**5-15 minutes**: Passive monitoring
- [ ] Check logs every 2-3 minutes
- [ ] Test one complex user flow (e.g., create liability, view IHT planning)
- [ ] Verify no performance degradation

**15-30 minutes**: Periodic checks
- [ ] Check logs every 5 minutes
- [ ] Monitor error log for patterns
- [ ] Test from different browsers/devices if possible

### Common Issues to Watch For

**Issue 1: Eloquent Relationship Errors**
```
Log: "Call to undefined relationship [jointOwner]"
Cause: Liability model file not uploaded correctly
Fix: Re-upload app/Models/Estate/Liability.php
```

**Issue 2: TAX_CONFIG Not Found**
```
Log: "Cannot read property 'ISA_ANNUAL_ALLOWANCE' of undefined"
Cause: taxConfig.js not uploaded or wrong path
Fix: Verify resources/js/constants/taxConfig.js exists and is correct
```

**Issue 3: Migration Not Applied**
```
Log: "SQLSTATE[42S22]: Column not found: 'ownership_type'"
Cause: Migration didn't run successfully
Fix: Check migration status, re-run if needed
```

**Issue 4: Frontend Assets 404**
```
Log: Browser console shows 404 for app-*.js files
Cause: Build didn't complete or manifest.json wrong location
Fix: Re-run npm run build, verify manifest.json
```

---

## Step 11: Rollback Procedures (if needed)

**⚠️ ONLY USE IF CRITICAL ISSUES OCCUR**

### Scenario 1: Migration Failed (Database Unchanged)

**If migration threw error and did NOT complete**:
```bash
cd ~/tengo-app

# Check migration status
php artisan migrate:status | grep "2025_11_22"
# If shows "Pending", migration didn't run

# Simply fix the issue and re-run migration
php artisan migrate --force
```

**No rollback needed** - database unchanged.

---

### Scenario 2: Migration Succeeded But Application Broken

**If migration completed but application has critical errors**:

#### Step 2.1: Rollback Migration First

**Via SSH**:
```bash
cd ~/tengo-app

# Rollback the last migration batch
php artisan migrate:rollback --step=1

# Expected output:
# Rolling back: 2025_11_22_092125_add_joint_ownership_to_liabilities_table
# Rolled back:  2025_11_22_092125_add_joint_ownership_to_liabilities_table (0.05 seconds)

# Verify rollback
php artisan migrate:status | grep "2025_11_22"
# Expected: Pending | 2025_11_22_092125_add_joint_ownership_to_liabilities_table
```

**What the rollback does** (from migration down() method):
1. Drops foreign keys: `liabilities_joint_owner_id_foreign`, `liabilities_trust_id_foreign`
2. Drops indexes: `liabilities_joint_owner_id_index`, `liabilities_trust_id_index`
3. Drops columns: `ownership_type`, `joint_owner_id`, `trust_id`

**Result**: `liabilities` table returns to pre-deployment state.

#### Step 2.2: Restore Code Files

**Via SSH**:
```bash
cd ~/tengo-app

# Use backup created in Step 2
backup_name="[your backup directory from Step 2]"

# Restore backend files
cp -r ~/backups/$backup_name/Models/* app/Models/
cp -r ~/backups/$backup_name/Services/* app/Services/

# Restore frontend components (if needed)
cp -r ~/backups/$backup_name/components-estate/* resources/js/components/Estate/
cp -r ~/backups/$backup_name/components-investment/* resources/js/components/Investment/

# Remove new constants directory
rm -rf resources/js/constants/

# Rebuild frontend
rm -rf public/build/*
NODE_ENV=production npm run build

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan config:cache
php artisan route:cache

# Verify application loads
curl -I https://csjones.co/tengo
# Expected: HTTP/2 200
```

---

### Scenario 3: Data Corruption (Database Restore Needed)

**⚠️ EXTREME SCENARIO - ONLY IF DATABASE CORRUPTED**

**If migration corrupted data** (very unlikely with this additive migration):

#### Step 3.1: Stop Application

**Via SSH**:
```bash
cd ~/tengo-app

# Create maintenance mode
php artisan down --message="Emergency maintenance - restoring backup"
```

#### Step 3.2: Restore Database Backup

**Via SSH** (using backup from Step 1):
```bash
cd ~/tengo-app

# Get database credentials
DB_NAME=$(grep "DB_DATABASE" .env | cut -d '=' -f2)
DB_USER=$(grep "DB_USERNAME" .env | cut -d '=' -f2)
DB_PASS=$(grep "DB_PASSWORD" .env | cut -d '=' -f2)

# Restore from backup
mysql -u $DB_USER -p$DB_PASS $DB_NAME < backup-2025-11-22-*.sql

# Verify restoration
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "DESCRIBE liabilities;"
# Should show old schema (no ownership_type, joint_owner_id, trust_id)
```

#### Step 3.3: Restore Code and Restart

**Via SSH**:
```bash
cd ~/tengo-app

# Restore code files (as in Scenario 2)
backup_name="[your backup directory]"
cp -r ~/backups/$backup_name/Models/* app/Models/
cp -r ~/backups/$backup_name/Services/* app/Services/
# ... restore all files

# Rebuild frontend
rm -rf public/build/*
NODE_ENV=production npm run build

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan config:cache
php artisan route:cache

# Bring application back up
php artisan up

# Verify
curl -I https://csjones.co/tengo
```

---

## Step 12: Success Confirmation

**Deployment is successful when ALL of the following are true**:

- [x] **Database Migration**: `php artisan migrate:status` shows migration "Ran"
- [x] **Database Schema**: `liabilities` table has `ownership_type`, `joint_owner_id`, `trust_id` columns
- [x] **Frontend Build**: `public/build/manifest.json` exists at correct location
- [x] **Frontend Assets**: `public/build/assets/` contains 100+ files
- [x] **Application Loads**: Homepage loads without errors (HTTP 200)
- [x] **User Login**: Can login with demo credentials
- [x] **Dashboard**: All cards render correctly
- [x] **IHT Planning**: Shows projection methodology note
- [x] **Investment Portfolio**: ISA allowance displays correctly, no TAX_CONFIG errors
- [x] **Error Logs**: No new ERROR level messages in Laravel logs
- [x] **Performance**: Page load times < 3 seconds

**Final Verification Command**:
```bash
cd ~/tengo-app

# Run comprehensive check
echo "=== Migration Status ==="
php artisan migrate:status | grep "2025_11_22"

echo "=== Database Schema ==="
mysql -u [DB_USER] -p[DB_PASS] [DB_NAME] -e "SHOW COLUMNS FROM liabilities LIKE '%ownership%';"

echo "=== Frontend Build ==="
ls -lh public/build/manifest.json

echo "=== Asset Count ==="
ls public/build/assets/ | wc -l

echo "=== Application Response ==="
curl -I https://csjones.co/tengo

echo "=== Recent Errors ==="
grep ERROR storage/logs/laravel.log | tail -5

echo "=== Deployment Complete ==="
```

**All checks pass** → Deployment successful ✅

---

## Files Changed Summary

### Database (1 file)
- **database/migrations/2025_11_22_092125_add_joint_ownership_to_liabilities_table.php** (NEW)
  - Adds trust ownership support to liabilities
  - Adds `ownership_type` enum, `joint_owner_id`, `trust_id` fields
  - Creates foreign keys and indexes

### Backend (4 files)
- **app/Models/Estate/Liability.php**
  - Added `ownership_type`, `joint_owner_id`, `trust_id` to $fillable
  - Added `jointOwner()` and `trust()` relationship methods

- **app/Services/Onboarding/OnboardingService.php**
  - Added null safety checks for spouse data access
  - Changed to defensive null coalescing operators

- **app/Services/UserProfile/UserProfileService.php**
  - Created `shouldIncludeByOwnership()` helper method
  - Replaced duplicated ownership filter logic

### Frontend (4 files)
- **resources/js/components/Estate/IHTMitigationStrategies.vue**
  - Added Will strategy implementation status notice

- **resources/js/components/Estate/IHTPlanning.vue**
  - Added projection methodology note to column header

- **resources/js/components/Investment/PortfolioOverview.vue**
  - Improved joint investment display comments
  - Imported TAX_CONFIG constant for ISA allowance
  - Renamed method to American spelling

- **resources/js/constants/taxConfig.js** (NEW)
  - Centralized UK tax configuration constants
  - ISA, pension, CGT allowances

**Total Files**: 9 (8 modified + 1 new migration + 1 new constants file)

---

## What Changed in v0.2.11

### Section 33: IHT Planning UI Enhancements (2 changes)

1. **Will Strategy Notice**: Informs users that full will functionality not yet implemented
2. **Projection Methodology**: Explains that projected values use 4.7% growth assumption

### Section 34: Code Quality Improvements (7 changes)

1. **Trust Ownership for Liabilities** (HIGH): Database migration + model updates for trust support
2. **Null Safety** (HIGH): Defensive coding in OnboardingService for spouse data
3. **Eloquent Relationships** (MEDIUM): Added jointOwner() and trust() methods
4. **DRY Principle** (MEDIUM): Extracted ownership filter logic to helper method
5. **Improved Comments** (LOW): Enhanced joint investment display documentation
6. **Centralized Constants** (LOW): Created taxConfig.js for tax values
7. **Method Naming** (LOW): Consistent American spelling in code

---

## Expected Outcome

After successful deployment:

- **Application Version**: v0.2.11
- **Database Schema**: Liabilities support individual/joint/trust ownership
- **Code Quality**: Improved null safety, DRY principle, centralized constants
- **User Experience**: Better transparency in IHT Planning projections
- **Maintainability**: Easier to update tax values (single source of truth)
- **Performance**: No degradation expected (migration is lightweight)
- **Downtime**: Minimal (< 30 seconds during migration)

---

## Support & Troubleshooting

### Common Issues

**Issue**: Migration error "Table 'liabilities' doesn't exist"
**Solution**: Check database connection, verify .env DB_* credentials

**Issue**: Frontend shows "Cannot read property 'ISA_ANNUAL_ALLOWANCE' of undefined"
**Solution**: Verify `resources/js/constants/taxConfig.js` uploaded correctly

**Issue**: Eloquent error "Call to undefined method [jointOwner]"
**Solution**: Re-upload `app/Models/Estate/Liability.php`, clear config cache

**Issue**: 500 error after deployment
**Solution**: Check Laravel logs, verify all files uploaded, clear all caches

**Issue**: Assets 404 errors
**Solution**: Re-run `npm run build`, verify manifest.json location

### Emergency Contacts

**SiteGround Support**:
- 24/7 Chat: https://my.siteground.com
- Site Tools: Error logs, PHP settings, SSH access

**Test Accounts**:
- Admin: admin@fps.com / admin123 (for database backups)
- Demo: demo@fps.com / password (for smoke testing)

### Documentation References

- **Migration Details**: See `friFixes21Nov.md` Section 34.1 (lines 1143-1186)
- **Null Safety**: See `friFixes21Nov.md` Section 34.2 (lines 1188-1215)
- **UI Enhancements**: See `friFixes21Nov.md` Section 33 (lines 1061-1133)
- **Full Change Log**: See `friFixes21Nov.md`

---

## Deployment Checklist (Quick Reference)

**Pre-Deployment**:
- [ ] Create database backup (admin panel or mysqldump)
- [ ] Download backup to local machine
- [ ] Create code backup on server

**Upload Files**:
- [ ] Upload migration file to `database/migrations/`
- [ ] Upload 4 backend files (Models/Services)
- [ ] Create `resources/js/constants/` directory
- [ ] Upload 4 frontend files (components + constants)

**Database & Build**:
- [ ] Run `php artisan migrate --force`
- [ ] Verify migration with `php artisan migrate:status`
- [ ] Run `npm run build` (production)
- [ ] Verify `public/build/manifest.json` exists

**Caching**:
- [ ] Clear all caches (cache/view/config/route)
- [ ] Rebuild production caches
- [ ] Verify permissions (storage, bootstrap/cache)

**Verification**:
- [ ] Test homepage loads (HTTP 200)
- [ ] Test user login (demo@fps.com)
- [ ] Test IHT Planning projection note visible
- [ ] Test Investment ISA allowance displays
- [ ] Check error logs (no new ERRORs)

**Monitoring**:
- [ ] Monitor logs for 5 minutes (active)
- [ ] Monitor logs for 15 minutes (passive)
- [ ] Final check at 30 minutes

---

**Deployment Version**: v0.2.11 (22 November 2025 Patch)
**Deployment Type**: Mixed (1 migration + code updates)
**Target Environment**: Production (https://csjones.co/tengo)
**Critical Changes**: Trust ownership for liabilities, UI transparency improvements

Built with [Claude Code](https://claude.com/claude-code) by Anthropic

---

**Document Version**: 1.0
**Created**: 22 November 2025
**Author**: Claude Code (Anthropic)
**Status**: Ready for Production Deployment
