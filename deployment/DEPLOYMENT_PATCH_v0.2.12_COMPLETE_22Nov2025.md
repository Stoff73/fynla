# Fynla v0.2.12 (22 November 2025 Complete Patch) - Production Deployment Instructions

**Date**: November 22, 2025
**Branch**: main (style branch merged)
**Version**: v0.2.12
**Type**: COMPREHENSIVE Mixed Deployment (1 Database Migration + 1 Production SQL + Code Updates)
**Production URL**: https://csjones.co/tengo
**Application Root**: ~/tengo-app/

---

## Deployment Overview

This is a **COMPREHENSIVE deployment** covering ALL 34 sections from friFixes21Nov.md:

- **Security Fixes**: 10 sections (token logging, password validation, CORS, etc.)
- **UI/Feature Fixes**: 22 sections (slippery mode, pensions, investments, properties, etc.)
- **Database Changes**:
  - 1 Migration: Add joint ownership to liabilities
  - 1 Production SQL: Make dc_pensions.provider nullable
- **Code Quality**: 7 improvements (trust ownership, null safety, refactoring)

**Total Files Modified**: ~40 files
- Backend: ~15 files (controllers, models, services, migrations, config)
- Frontend: ~25 files (Vue components, store modules, composables, constants)

**Deployment Method**: File upload + database migration + production SQL + frontend rebuild + cache clear
**Estimated Time**: 35-45 minutes
**Downtime**: Minimal (< 2 minutes during database changes)

---

## Complete Changes Summary (All 34 Sections)

### SECURITY FIXES (Sections 1-10)

#### Section 1: Token Logging Removed (HIGH SEVERITY)
- **File**: `app/Http/Controllers/Api/AuthController.php`
- **Change**: Removed token preview and email from registration logs
- **Impact**: Eliminates security risk of token exposure in logs

#### Section 2: Password Regex Updated (MEDIUM SEVERITY)
- **File**: `app/Http/Controllers/Api/AuthController.php`
- **Change**: Accept all non-alphanumeric special characters (not just @$!%*?&)
- **Impact**: Users can now use characters like #, ^, (, ) in passwords

#### Section 3: CORS Hardcoded Origins Removed (CONFIGURATION RISK)
- **File**: `config/cors.php`
- **Change**: CORS origins now read from environment variables only
- **Impact**: Prevents local dev URLs from accessing production API
- **Environment Variables Added**: ALLOWED_ORIGINS, FRONTEND_URL

#### Section 4: Trust Route Controller Mismatch (DOMAIN LEAKAGE)
- **Files**: `routes/api.php`, `app/Http/Controllers/Api/Estate/TrustController.php`
- **Change**: Moved upcoming tax returns endpoint from WillController to TrustController
- **Impact**: Correct separation of concerns

#### Section 5: N+1 Query Risk Fixed
- **File**: `app/Models/User.php`
- **Change**: Use existing relationship instead of User::find() in hasAcceptedSpousePermission()
- **Impact**: Prevents N+1 query when iterating over users

#### Section 6: Float Casts for Financial Data (DOCUMENTED)
- **File**: `app/Models/User.php`
- **Status**: Known limitation documented, no changes
- **Note**: Future consideration for integer (pence) storage

#### Section 7: User Model Switched to Guarded
- **File**: `app/Models/User.php`
- **Change**: Switched from massive $fillable array to $guarded approach
- **Impact**: Easier maintenance, prevents silent failures

#### Section 8: Route Style Consistency (ALREADY COMPLIANT)
- **File**: `routes/api.php`
- **Status**: Already using imported classes, no changes needed

#### Section 9: Middleware Consolidation (DEFERRED)
- **File**: `routes/api.php`
- **Status**: Deferred - current structure is functional

#### Section 10: Vue Prop Type Warning Fix
- **File**: `resources/js/components/UserProfile/ExpenditureOverview.vue`
- **Change**: Added !! to coerce spouse_id to boolean
- **Impact**: Eliminates Vue prop type warnings

---

### UI/FEATURE FIXES (Sections 11-32)

#### Section 11: Slippery Mode Default Fix
- **File**: `resources/js/composables/useDesignMode.js`
- **Change**: Removed localStorage persistence, always default to 'normal' mode
- **Impact**: Slippery mode no longer persists across sessions

#### Section 12: DC Pension Provider Field Made Optional (FRONTEND)
- **File**: `resources/js/components/Retirement/DCPensionForm.vue`
- **Change**: Removed validation check for provider field
- **Impact**: Users can create DC pensions without provider name

#### Section 13: DC Pension Provider Database Column Made Nullable ⚠️ PRODUCTION SQL REQUIRED
- **Database Change**: ALTER TABLE dc_pensions MODIFY COLUMN provider varchar(255) NULL;
- **Impact**: CRITICAL - Without this, form submissions will fail with integrity constraint violation
- **Verification**: DESCRIBE dc_pensions; (provider column should show YES in Null column)

#### Section 14: Property Form - Removed 'Part and Part' Mortgage Type
- **File**: `resources/js/components/NetWorth/Property/PropertyForm.vue`
- **Change**: Removed 'Part and Part' option from mortgage type dropdown
- **Impact**: Cleaner mortgage type options

#### Section 15: Retirement Age Minimum Reduced to 30
- **File**: `resources/js/components/Onboarding/steps/IncomeStep.vue`
- **Change**: Min retirement age changed from 55 to 30
- **Impact**: Accommodates early-retirement professions (athletes, etc.)

#### Section 16: Protection Dashboard Card - Policy Display Fix
- **Files**: `resources/js/store/modules/protection.js`, `resources/js/views/Dashboard.vue`
- **Change**: Added getters for individual policy types, passed to ProtectionOverviewCard
- **Impact**: Dashboard now displays individual policies under each category

#### Section 17: Net Worth Wealth Summary - Spouse Name Display
- **File**: `resources/js/components/NetWorth/NetWorthOverview.vue`
- **Change**: Use user?.spouse?.name instead of user?.spouse_name
- **Impact**: Displays actual spouse name instead of "Spouse Wealth"

#### Section 18: State Pension Save Not Working
- **Files**: `resources/js/components/Retirement/UnifiedPensionForm.vue`, `resources/js/views/Retirement/RetirementReadiness.vue`
- **Change**: Pass pension type with save event, dispatch correct store action
- **Impact**: State pensions now save to database when added from Retirement module

#### Section 19: State Pension Card - Wrong Field Mappings
- **File**: `resources/js/views/Retirement/RetirementReadiness.vue`
- **Change**: Updated to use correct database field names (state_pension_forecast_annual, ni_years_completed, state_pension_age)
- **Impact**: State pension cards display correct values

#### Section 20: DC Pension Card - Missing Fields
- **File**: `resources/js/views/Retirement/RetirementReadiness.vue`
- **Change**: Added retirement age display, fixed field name (monthly_contribution_amount)
- **Impact**: DC pension cards show complete information

#### Section 21: Gifting Strategy - Taper Relief Timeline Colors
- **File**: `resources/js/components/Estate/GiftingStrategy.vue`
- **Change**: Green for years passed, red for years remaining
- **Impact**: Readable taper relief timeline (was white on grey)

#### Section 22: Investment Portfolio Cards - Enhanced Information
- **File**: `resources/js/components/Investment/PortfolioOverview.vue`
- **Change**: ISA accounts show current year contributions and remaining allowance
- **Impact**: Users can track ISA utilization better

#### Section 23: Joint Investment Accounts - Spouse Visibility Fix
- **Files**: `app/Http/Controllers/Api/InvestmentController.php`, `app/Models/Investment/InvestmentAccount.php`
- **Change**: Added joint_owner_id to fillable, updated index query
- **Impact**: Spouses can see joint investment accounts (initially, later reverted in Section 25)

#### Section 24: Tenants in Common - Full Support Added
- **Files**: `resources/js/components/NetWorth/PropertyCard.vue`, `app/Http/Controllers/Api/PropertyController.php`, `app/Http/Controllers/Api/MortgageController.php`
- **Change**: All joint ownership logic updated to include 'tenants_in_common'
- **Impact**: Tenants in common properties display correctly with badges and share breakdown

#### Section 25: Joint Investment Accounts - Fixed Duplicate Display
- **File**: `app/Http/Controllers/Api/InvestmentController.php`
- **Change**: Reverted to where('user_id', $user->id) only
- **Impact**: With reciprocal records, no duplicates show

#### Section 26: Onboarding Pre-population - Personal Info & Income
- **Files**: `resources/js/components/Onboarding/steps/PersonalInfoStep.vue`, `resources/js/components/Onboarding/steps/IncomeStep.vue`
- **Change**: Pre-populate forms from user table data (DOB, gender, address, income, etc.)
- **Impact**: Spouse onboarding shows existing data that was entered when account was created

#### Section 27: Spouse Account Creation - Address Copying
- **File**: `app/Http/Controllers/Api/FamilyMembersController.php`
- **Change**: Copy address from current user when creating/linking spouse account
- **Impact**: Couples automatically share main residence address

#### Section 28: Family Members - Reciprocal Records for Spouse
- **File**: `app/Http/Controllers/Api/FamilyMembersController.php`
- **Change**: Create reciprocal family member record when spouse account created
- **Impact**: Spouse sees partner in Family & Dependents section

#### Section 29: Expenditure Step - Pre-population from User Fields
- **File**: `app/Services/Onboarding/OnboardingService.php`
- **Change**: Added getStepDataFromUser() fallback method for expenditure data
- **Impact**: Spouse onboarding shows expenditure data entered by partner

#### Section 30: Financial Commitments - Ownership Filtering
- **Files**: `app/Http/Controllers/Api/UserProfileController.php`, `app/Services/UserProfile/UserProfileService.php`
- **Change**: Added ownership_filter query parameter (all, joint_only, individual_only)
- **Impact**: Backend supports filtering commitments by ownership type

#### Section 31: Joint Ownership Support - Liabilities (DATABASE MIGRATION)
- **Files**: Migration `2025_11_22_092125_add_joint_ownership_to_liabilities_table.php`, `app/Models/Estate/Liability.php`
- **Change**: Added ownership_type, joint_owner_id, trust_id fields to liabilities
- **Impact**: Liabilities now support joint ownership like other assets

#### Section 32: Joint Investment Display - Value Calculation Fix
- **File**: `resources/js/components/Investment/PortfolioOverview.vue`
- **Change**: Display full value (share × 2) and user's share correctly
- **Impact**: Joint investment accounts show correct values (was dividing by 2 incorrectly)

---

### UI ENHANCEMENTS (Section 33)

#### Section 33.1: Will Strategy Implementation Notice
- **File**: `resources/js/components/Estate/IHTMitigationStrategies.vue`
- **Change**: Added amber warning notice that full will functionality not implemented
- **Impact**: Users informed about feature status

#### Section 33.2: Projected Values Methodology Note
- **File**: `resources/js/components/Estate/IHTPlanning.vue`
- **Change**: Added explanation that projected values use 4.7% growth rate
- **Impact**: Users understand calculation methodology

---

### CODE QUALITY IMPROVEMENTS (Section 34)

#### Section 34.1: Trust Ownership Support for Liabilities (HIGH)
- **Files**: Migration `2025_11_22_092125_add_joint_ownership_to_liabilities_table.php`, `app/Models/Estate/Liability.php`
- **Change**: Added 'trust' to ownership_type enum, added trust_id field
- **Impact**: Liabilities match FPS standards (individual, joint, trust)

#### Section 34.2: Null Safety in OnboardingService (HIGH)
- **File**: `app/Services/Onboarding/OnboardingService.php`
- **Change**: Added defensive null checks for spouse data access
- **Impact**: Prevents exceptions if spouse account deleted

#### Section 34.3: Eloquent Relationships for Liabilities (MEDIUM)
- **File**: `app/Models/Estate/Liability.php`
- **Change**: Added jointOwner() and trust() relationship methods
- **Impact**: Enables efficient eager loading

#### Section 34.4: Ownership Filter Logic Extraction (MEDIUM)
- **File**: `app/Services/UserProfile/UserProfileService.php`
- **Change**: Created shouldIncludeByOwnership() helper method
- **Impact**: Reduced code duplication (DRY principle)

#### Section 34.5: Improved Inline Comments (LOW)
- **File**: `resources/js/components/Investment/PortfolioOverview.vue`
- **Change**: Enhanced comments explaining joint investment storage pattern
- **Impact**: Future developers understand the pattern

#### Section 34.6: Centralized ISA Allowance Constant (LOW)
- **Files**: `resources/js/constants/taxConfig.js` (NEW), `resources/js/components/Investment/PortfolioOverview.vue`
- **Change**: Created tax config constants file, imported ISA_ANNUAL_ALLOWANCE
- **Impact**: Single source of truth for tax constants

#### Section 34.7: Method Naming Consistency (LOW)
- **File**: `resources/js/components/Investment/PortfolioOverview.vue`
- **Change**: Renamed getReturnColourClass to getReturnColorClass
- **Impact**: Consistent American spelling in code

---

## Complete Files Modified List

### Backend Files (15 files)

1. `app/Http/Controllers/Api/AuthController.php` (Sections 1, 2)
2. `config/cors.php` (Section 3)
3. `routes/api.php` (Section 4)
4. `app/Http/Controllers/Api/Estate/TrustController.php` (Section 4)
5. `app/Models/User.php` (Sections 5, 7)
6. `app/Http/Controllers/Api/InvestmentController.php` (Sections 23, 25)
7. `app/Models/Investment/InvestmentAccount.php` (Section 23)
8. `app/Http/Controllers/Api/PropertyController.php` (Section 24)
9. `app/Http/Controllers/Api/MortgageController.php` (Section 24)
10. `app/Http/Controllers/Api/FamilyMembersController.php` (Sections 27, 28)
11. `app/Services/Onboarding/OnboardingService.php` (Sections 29, 34.2)
12. `app/Http/Controllers/Api/UserProfileController.php` (Section 30)
13. `app/Services/UserProfile/UserProfileService.php` (Sections 30, 34.4)
14. `app/Models/Estate/Liability.php` (Sections 31, 34.1, 34.3)
15. `database/migrations/2025_11_22_092125_add_joint_ownership_to_liabilities_table.php` (Sections 31, 34.1)

### Frontend Files (26 files)

1. `resources/js/components/UserProfile/ExpenditureOverview.vue` (Section 10)
2. `resources/js/composables/useDesignMode.js` (Section 11)
3. `resources/js/components/Retirement/DCPensionForm.vue` (Section 12)
4. `resources/js/components/NetWorth/Property/PropertyForm.vue` (Section 14)
5. `resources/js/components/Onboarding/steps/IncomeStep.vue` (Sections 15, 26)
6. `resources/js/store/modules/protection.js` (Section 16)
7. `resources/js/views/Dashboard.vue` (Section 16)
8. `resources/js/components/NetWorth/NetWorthOverview.vue` (Section 17)
9. `resources/js/components/Retirement/UnifiedPensionForm.vue` (Section 18)
10. `resources/js/views/Retirement/RetirementReadiness.vue` (Sections 18, 19, 20)
11. `resources/js/components/Estate/GiftingStrategy.vue` (Section 21)
12. `resources/js/components/Investment/PortfolioOverview.vue` (Sections 22, 32, 34.5, 34.6, 34.7)
13. `resources/js/components/NetWorth/PropertyCard.vue` (Section 24)
14. `resources/js/components/Onboarding/steps/PersonalInfoStep.vue` (Section 26)
15. `resources/js/components/Estate/IHTMitigationStrategies.vue` (Section 33.1)
16. `resources/js/components/Estate/IHTPlanning.vue` (Section 33.2)
17. `resources/js/constants/taxConfig.js` (Section 34.6) **NEW FILE**

**Note**: Several Vue components appear multiple times in different sections (e.g., IncomeStep.vue in Sections 15 and 26, PortfolioOverview.vue in Sections 22, 32, and multiple 34.x sections). The list above shows unique files.

---

## Pre-Deployment Checklist

**CRITICAL - Complete BEFORE starting deployment**:

- [ ] **Database Backup Created**: Via admin panel (https://csjones.co/tengo/admin) OR manual mysqldump
- [ ] **Backup Downloaded**: Stored safely locally with timestamp
- [ ] **Git Status Clean**: All changes committed on main branch
- [ ] **Local Build Test**: `npm run build` completes successfully
- [ ] **Migrations Reviewed**: Understand what migration does (adds joint ownership to liabilities)
- [ ] **Production SQL Reviewed**: Understand ALTER TABLE for dc_pensions.provider
- [ ] **SSH Access Confirmed**: Can connect to production server
- [ ] **Production .env Verified**: APP_ENV=production, APP_DEBUG=false
- [ ] **CORS Environment Variables**: ALLOWED_ORIGINS and FRONTEND_URL set correctly in production .env

---

## Step 1: Create Production Database Backup (CRITICAL - 5 minutes)

**⚠️ WARNING**: This deployment includes:
1. One database migration (liabilities table)
2. One production SQL statement (dc_pensions.provider nullable)

**A backup is MANDATORY.**

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
   Example: backup-2025-11-22-150000.sql

7. Move to safe location:
   ~/Desktop/fps-backups/backup-2025-11-22-150000.sql
```

**Backup Location on Server**: `~/tengo-app/storage/app/backups/`

### Method 2: Manual Database Backup (ALTERNATIVE)

**Via SSH** (if admin panel unavailable):
```bash
ssh [username]@csjones.co -p18765

cd ~/tengo-app

# Get database credentials from .env
grep "DB_" .env

# Create backup
mysqldump -u [DB_USER] -p[DB_PASSWORD] [DB_NAME] > backup-$(date +%Y%m%d-%H%M%S).sql

# Verify backup created
ls -lh backup-*.sql

# Download to local machine (from your local terminal)
scp -P 18765 [username]@csjones.co:~/tengo-app/backup-*.sql ~/Desktop/fps-backups/
```

**✅ CHECKPOINT**: Backup file exists locally and is non-zero size (should be >5MB)

---

## Step 2: Review Database Changes (5 minutes)

### Database Change 1: Migration - Add Joint Ownership to Liabilities

**File**: `database/migrations/2025_11_22_092125_add_joint_ownership_to_liabilities_table.php`

**What it does**:
```sql
ALTER TABLE liabilities
ADD COLUMN ownership_type ENUM('individual', 'joint', 'trust') DEFAULT 'individual' AFTER user_id,
ADD COLUMN joint_owner_id BIGINT UNSIGNED NULL AFTER ownership_type,
ADD COLUMN trust_id BIGINT UNSIGNED NULL AFTER joint_owner_id,
ADD CONSTRAINT liabilities_joint_owner_id_foreign FOREIGN KEY (joint_owner_id) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT liabilities_trust_id_foreign FOREIGN KEY (trust_id) REFERENCES trusts(id) ON DELETE SET NULL;
```

**Impact**:
- Adds 3 new columns to liabilities table
- Existing records default to 'individual' ownership
- Non-destructive (no data loss)
- Adds foreign key constraints for data integrity

**Rollback**:
```sql
ALTER TABLE liabilities
DROP FOREIGN KEY liabilities_trust_id_foreign,
DROP FOREIGN KEY liabilities_joint_owner_id_foreign,
DROP COLUMN trust_id,
DROP COLUMN joint_owner_id,
DROP COLUMN ownership_type;
```

### Database Change 2: Production SQL - Make DC Pensions Provider Nullable

**⚠️ CRITICAL PRODUCTION SQL STATEMENT**

**What to run**:
```sql
ALTER TABLE dc_pensions MODIFY COLUMN provider varchar(255) NULL;
```

**Why needed**:
- Frontend validation made provider optional (Section 12)
- Database column still had NOT NULL constraint
- Without this change, form submissions fail with SQLSTATE[23000]

**Verification**:
```sql
DESCRIBE dc_pensions;
-- Check that 'provider' column shows 'YES' in the 'Null' column
```

**Expected Output**:
```
+--------------------------+---------------------+------+-----+---------+----------------+
| Field                    | Type                | Null | Key | Default | Extra          |
+--------------------------+---------------------+------+-----+---------+----------------+
| provider                 | varchar(255)        | YES  |     | NULL    |                |
+--------------------------+---------------------+------+-----+---------+----------------+
```

**Rollback** (if needed):
```sql
-- Ensure no existing NULL values first
UPDATE dc_pensions SET provider = 'Unknown' WHERE provider IS NULL;
ALTER TABLE dc_pensions MODIFY COLUMN provider varchar(255) NOT NULL;
```

---

## Step 3: Update Production .env File (3 minutes)

**⚠️ CRITICAL**: Section 3 requires CORS environment variables

### SSH into Production Server

```bash
ssh [username]@csjones.co -p18765

cd ~/tengo-app
```

### Backup Current .env

```bash
cp .env .env.backup.$(date +%Y%m%d-%H%M%S)
```

### Edit .env File

```bash
nano .env
```

### Add/Update These Variables

**Find or add these lines**:
```env
# CORS Configuration (Section 3 requirement)
ALLOWED_ORIGINS=https://csjones.co/tengo
FRONTEND_URL=https://csjones.co/tengo

# Verify these are correct for production
APP_ENV=production
APP_DEBUG=false
APP_URL=https://csjones.co/tengo
```

**Save and exit**: Ctrl+X, Y, Enter

### Verify .env Changes

```bash
grep "ALLOWED_ORIGINS" .env
grep "FRONTEND_URL" .env
grep "APP_ENV" .env
grep "APP_DEBUG" .env
```

**Expected Output**:
```
ALLOWED_ORIGINS=https://csjones.co/tengo
FRONTEND_URL=https://csjones.co/tengo
APP_ENV=production
APP_DEBUG=false
```

**✅ CHECKPOINT**: CORS environment variables are set correctly

---

## Step 4: Upload Modified Files to Production (10 minutes)

### Files to Upload

**Total: 33 files (15 backend + 26 frontend)**

Use SFTP client (FileZilla, Cyberduck, Transmit) or command line.

### Backend Files (Upload to ~/tengo-app/)

```
app/Http/Controllers/Api/AuthController.php
app/Http/Controllers/Api/Estate/TrustController.php
app/Http/Controllers/Api/FamilyMembersController.php
app/Http/Controllers/Api/InvestmentController.php
app/Http/Controllers/Api/MortgageController.php
app/Http/Controllers/Api/PropertyController.php
app/Http/Controllers/Api/UserProfileController.php
app/Models/User.php
app/Models/Estate/Liability.php
app/Models/Investment/InvestmentAccount.php
app/Services/Onboarding/OnboardingService.php
app/Services/UserProfile/UserProfileService.php
config/cors.php
routes/api.php
database/migrations/2025_11_22_092125_add_joint_ownership_to_liabilities_table.php
```

### Frontend Files (Upload to ~/tengo-app/)

```
resources/js/components/Estate/GiftingStrategy.vue
resources/js/components/Estate/IHTMitigationStrategies.vue
resources/js/components/Estate/IHTPlanning.vue
resources/js/components/Investment/PortfolioOverview.vue
resources/js/components/NetWorth/NetWorthOverview.vue
resources/js/components/NetWorth/PropertyCard.vue
resources/js/components/NetWorth/Property/PropertyForm.vue
resources/js/components/Onboarding/steps/IncomeStep.vue
resources/js/components/Onboarding/steps/PersonalInfoStep.vue
resources/js/components/Retirement/DCPensionForm.vue
resources/js/components/Retirement/UnifiedPensionForm.vue
resources/js/components/UserProfile/ExpenditureOverview.vue
resources/js/composables/useDesignMode.js
resources/js/constants/taxConfig.js (NEW FILE - CREATE THIS)
resources/js/store/modules/protection.js
resources/js/views/Dashboard.vue
resources/js/views/Retirement/RetirementReadiness.vue
```

### Using Command Line SCP (Alternative)

**From local terminal** (in ~/Desktop/fpsApp/tengo directory):
```bash
# Create tarball of all modified files
tar -czf v0.2.12-patch.tar.gz \
  app/Http/Controllers/Api/AuthController.php \
  app/Http/Controllers/Api/Estate/TrustController.php \
  app/Http/Controllers/Api/FamilyMembersController.php \
  app/Http/Controllers/Api/InvestmentController.php \
  app/Http/Controllers/Api/MortgageController.php \
  app/Http/Controllers/Api/PropertyController.php \
  app/Http/Controllers/Api/UserProfileController.php \
  app/Models/User.php \
  app/Models/Estate/Liability.php \
  app/Models/Investment/InvestmentAccount.php \
  app/Services/Onboarding/OnboardingService.php \
  app/Services/UserProfile/UserProfileService.php \
  config/cors.php \
  routes/api.php \
  database/migrations/2025_11_22_092125_add_joint_ownership_to_liabilities_table.php \
  resources/js/components/Estate/GiftingStrategy.vue \
  resources/js/components/Estate/IHTMitigationStrategies.vue \
  resources/js/components/Estate/IHTPlanning.vue \
  resources/js/components/Investment/PortfolioOverview.vue \
  resources/js/components/NetWorth/NetWorthOverview.vue \
  resources/js/components/NetWorth/PropertyCard.vue \
  resources/js/components/NetWorth/Property/PropertyForm.vue \
  resources/js/components/Onboarding/steps/IncomeStep.vue \
  resources/js/components/Onboarding/steps/PersonalInfoStep.vue \
  resources/js/components/Retirement/DCPensionForm.vue \
  resources/js/components/Retirement/UnifiedPensionForm.vue \
  resources/js/components/UserProfile/ExpenditureOverview.vue \
  resources/js/composables/useDesignMode.js \
  resources/js/constants/taxConfig.js \
  resources/js/store/modules/protection.js \
  resources/js/views/Dashboard.vue \
  resources/js/views/Retirement/RetirementReadiness.vue

# Upload tarball
scp -P 18765 v0.2.12-patch.tar.gz [username]@csjones.co:~/tengo-app/

# SSH to production
ssh [username]@csjones.co -p18765

# Extract files
cd ~/tengo-app
tar -xzf v0.2.12-patch.tar.gz

# Clean up
rm v0.2.12-patch.tar.gz
```

**✅ CHECKPOINT**: All 33 files uploaded successfully

---

## Step 5: Run Database Changes (5 minutes)

**⚠️ CRITICAL**: This step modifies the database. Ensure backup exists.

### SSH into Production Server

```bash
ssh [username]@csjones.co -p18765
cd ~/tengo-app
```

### Step 5A: Run Production SQL (DC Pensions Provider)

```bash
# Get database credentials
grep "DB_" .env

# Connect to MySQL
mysql -u [DB_USER] -p[DB_PASSWORD] [DB_NAME]
```

**In MySQL prompt**:
```sql
-- Show current schema
DESCRIBE dc_pensions;

-- Make provider nullable
ALTER TABLE dc_pensions MODIFY COLUMN provider varchar(255) NULL;

-- Verify change
DESCRIBE dc_pensions;

-- Should show 'YES' in Null column for provider field
-- Expected output:
-- | provider | varchar(255) | YES  |     | NULL    |                |

-- Exit MySQL
EXIT;
```

**✅ CHECKPOINT**: dc_pensions.provider is now nullable

### Step 5B: Run Migration (Liabilities Joint Ownership)

```bash
# Run pending migrations
php artisan migrate --force
```

**Expected Output**:
```
Running migrations.
2025_11_22_092125_add_joint_ownership_to_liabilities_table ................... DONE
```

### Verify Migration Success

```bash
# Check migration status
php artisan migrate:status

# Connect to MySQL and verify table structure
mysql -u [DB_USER] -p[DB_PASSWORD] [DB_NAME] -e "DESCRIBE liabilities;"
```

**Expected Output** (should include new columns):
```
+------------------+-------------------------------------------+------+-----+---------+----------------+
| Field            | Type                                      | Null | Key | Default | Extra          |
+------------------+-------------------------------------------+------+-----+---------+----------------+
| user_id          | bigint unsigned                           | NO   | MUL | NULL    |                |
| ownership_type   | enum('individual','joint','trust')        | NO   |     | individual |             |
| joint_owner_id   | bigint unsigned                           | YES  | MUL | NULL    |                |
| trust_id         | bigint unsigned                           | YES  | MUL | NULL    |                |
+------------------+-------------------------------------------+------+-----+---------+----------------+
```

**✅ CHECKPOINT**: Migration completed, liabilities table has new ownership columns

---

## Step 6: Install Dependencies and Build Frontend (8 minutes)

### Ensure Composer Dependencies Up-to-Date

```bash
cd ~/tengo-app

# Install/update Composer dependencies (production only)
composer install --no-dev --optimize-autoloader

# Verify autoloader
composer dump-autoload --optimize
```

**Expected Output**: No errors, "Generating optimized autoload files"

### Build Frontend Assets

```bash
# Install npm dependencies from lock file
npm ci

# Build production assets
npm run build
```

**Expected Output**:
```
vite v5.x.x building for production...
✓ xxxx modules transformed.
dist/assets/app-xxxxx.js  xxx.xx kB │ gzip: xxx.xx kB
dist/assets/app-xxxxx.css  xxx.xx kB │ gzip: xxx.xx kB
✓ built in xxxs
```

### Verify Build Output

```bash
# Check manifest exists
ls -lh public/build/manifest.json

# Check assets directory
ls -lh public/build/assets/ | head -20
```

**Expected**: manifest.json exists, assets directory contains compiled JS/CSS files

**✅ CHECKPOINT**: Frontend assets built successfully

---

## Step 7: Clear All Caches (3 minutes)

**⚠️ CRITICAL**: Config cache must be rebuilt to pick up CORS changes

```bash
cd ~/tengo-app

# Clear all caches
php artisan optimize:clear

# Rebuild production caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

**Expected Output**:
```
Configuration cache cleared successfully.
Route cache cleared successfully.
View cache cleared successfully.
Application cache cleared successfully.

Configuration cached successfully.
Routes cached successfully.
Compiled views cleared successfully.
Views cached successfully.
```

### Restart PHP-FPM and Web Server

```bash
# Find your PHP version
php -v

# Restart PHP-FPM (adjust version as needed)
sudo systemctl restart php8.2-fpm

# Restart Nginx
sudo systemctl restart nginx

# Verify services running
sudo systemctl status php8.2-fpm
sudo systemctl status nginx
```

**Expected**: Both services show "active (running)" in green

**✅ CHECKPOINT**: All caches cleared, services restarted

---

## Step 8: Post-Deployment Verification (10 minutes)

### 8.1 Basic Smoke Tests

**Test 1: Homepage Loads**
```
Navigate to: https://csjones.co/tengo
Expected: Homepage loads without errors
```

**Test 2: Login Works**
```
Login with: demo@fps.com / password
Expected: Successful login, dashboard loads
```

**Test 3: Dashboard Displays**
```
Check: All dashboard cards visible
Expected: Protection card shows individual policies (Section 16 fix)
Expected: Net Worth shows spouse name (Section 17 fix)
```

### 8.2 Security Fixes Verification

**Test 4: CORS Configuration**
```bash
# Check config cache includes correct CORS origins
grep -r "ALLOWED_ORIGINS" ~/tengo-app/bootstrap/cache/config.php
```
**Expected**: Shows production URL only, no localhost URLs

**Test 5: Password Validation**
```
Test registration with password: Test123!@#
Expected: Accepts special characters beyond @$!%*?&
```

### 8.3 Feature-Specific Tests

**Test 6: DC Pension Without Provider** (Section 12-13)
```
1. Navigate to Retirement → Retirement Readiness
2. Click "Add Pension" → "Defined Contribution (DC)"
3. Fill required fields, LEAVE PROVIDER BLANK
4. Click Save
Expected: Pension saves successfully (no integrity constraint error)
```

**Test 7: State Pension Save** (Section 18)
```
1. Navigate to Retirement → Retirement Readiness
2. Click "Add Pension" → "State Pension"
3. Fill in forecast amount and NI years
4. Click Save
Expected: Pension saves and appears in card
```

**Test 8: Tenants in Common Display** (Section 24)
```
1. Navigate to Net Worth
2. Find a property with "Tenants in Common" ownership
Expected: Green badge displays, shows full value and share breakdown
```

**Test 9: Joint Investment Display** (Section 32)
```
1. Navigate to Investment → Portfolio Overview
2. Find a joint investment account
Expected: Shows "Full Value" (share × 2) and "Your Share (50%)" correctly
```

**Test 10: ISA Allowance Display** (Section 22)
```
1. Navigate to Investment → Portfolio Overview
2. Find an ISA account
Expected: Shows current year contributions and remaining allowance with colour coding
```

**Test 11: Slippery Mode Default** (Section 11)
```
1. Logout and login again
Expected: Landing page shows normal mode (not slippery mode)
```

**Test 12: IHT Planning UI** (Section 33)
```
1. Navigate to Estate Planning → IHT Planning
2. Expand Will strategy card
Expected: Shows amber notice "Full will functionality has not been implemented"
3. Check Projected column header
Expected: Shows "This is a static future value calculation using 4.7%"
```

### 8.4 Database Verification

```bash
# Verify both database changes
mysql -u [DB_USER] -p[DB_PASSWORD] [DB_NAME]
```

**In MySQL**:
```sql
-- Verify dc_pensions.provider is nullable
DESCRIBE dc_pensions;
-- Expected: provider shows YES in Null column

-- Verify liabilities has new ownership columns
DESCRIBE liabilities;
-- Expected: ownership_type, joint_owner_id, trust_id columns exist

-- Check liabilities table data integrity
SELECT ownership_type, COUNT(*) FROM liabilities GROUP BY ownership_type;
-- Expected: All existing records show 'individual'

-- Exit
EXIT;
```

### 8.5 Log Review

```bash
# Check Laravel logs for errors
tail -50 ~/tengo-app/storage/logs/laravel.log

# Check Nginx error log
sudo tail -50 /var/log/nginx/error.log

# Check PHP-FPM log
sudo tail -50 /var/log/php8.2-fpm.log
```

**Expected**: No critical errors related to deployment

**✅ CHECKPOINT**: All tests pass, no errors in logs

---

## Step 9: User Communication and Monitoring (Ongoing)

### Immediate Monitoring (First 30 minutes)

**Watch Laravel logs in real-time**:
```bash
ssh [username]@csjones.co -p18765
tail -f ~/tengo-app/storage/logs/laravel.log
```

**Monitor for**:
- Database errors (especially related to dc_pensions or liabilities)
- CORS errors
- Authentication issues
- 500 errors

### User Communication

**Send deployment notification** (if applicable):
```
Subject: Fynla v0.2.12 Deployed - Security & Feature Updates

Key Updates:
✅ Enhanced security (password validation, CORS, logging)
✅ DC Pension provider field now optional
✅ State pension saving fixed
✅ Joint investment display improved
✅ Tenants in common fully supported
✅ Better ISA allowance tracking
✅ Multiple UI enhancements

All systems operational. Report any issues to [support email].
```

---

## Rollback Procedure (Emergency Only)

**⚠️ USE ONLY IF CRITICAL ISSUES DETECTED**

### Quick Rollback (Code Only - 5 minutes)

```bash
ssh [username]@csjones.co -p18765
cd ~/tengo-app

# Note current commit for investigation
git rev-parse HEAD

# Checkout previous working commit (get from git log)
git log --oneline -10
git checkout [PREVIOUS_WORKING_COMMIT]

# Reinstall dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# Clear caches
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
sudo systemctl restart php8.2-fpm nginx
```

### Full Rollback (Code + Database - 10 minutes)

**If migration must be rolled back**:
```bash
cd ~/tengo-app

# Rollback last migration
php artisan migrate:rollback --step=1

# Verify rollback
php artisan migrate:status

# Revert dc_pensions.provider to NOT NULL (if needed)
mysql -u [DB_USER] -p[DB_PASSWORD] [DB_NAME]
```

**In MySQL**:
```sql
-- First ensure no NULL values exist
UPDATE dc_pensions SET provider = 'Unknown' WHERE provider IS NULL;

-- Make column NOT NULL again
ALTER TABLE dc_pensions MODIFY COLUMN provider varchar(255) NOT NULL;

EXIT;
```

**Then complete code rollback** (see Quick Rollback above)

### Restore from Database Backup (Last Resort - 15 minutes)

```bash
cd ~/tengo-app

# Stop application temporarily
php artisan down --message="Maintenance in progress"

# Restore database
mysql -u [DB_USER] -p[DB_PASSWORD] [DB_NAME] < storage/app/backups/backup-2025-11-22-150000.sql

# Bring application back online
php artisan up

# Restart services
sudo systemctl restart php8.2-fpm nginx
```

---

## Success Criteria

**Deployment is considered successful when**:

✅ All 33 files uploaded without errors
✅ Production SQL executed successfully (dc_pensions.provider nullable)
✅ Migration completed successfully (liabilities ownership columns added)
✅ Frontend assets built successfully (manifest.json exists)
✅ All caches cleared and rebuilt
✅ Services restarted (PHP-FPM, Nginx)
✅ Homepage loads without errors
✅ User can login successfully
✅ Dashboard displays correctly with all fixes
✅ DC pension can be created without provider
✅ State pension saves correctly
✅ Joint investments display correct values
✅ No critical errors in Laravel or Nginx logs
✅ CORS configuration uses production URLs only
✅ No token logging in authentication events

---

## Post-Deployment Tasks

### Update Version Number

**In production .env**:
```bash
cd ~/tengo-app
nano .env
```

**Add/Update**:
```env
APP_VERSION=v0.2.12
DEPLOYMENT_DATE=2025-11-22
```

### Tag Git Commit

**On local machine**:
```bash
cd ~/Desktop/fpsApp/tengo
git tag -a v0.2.12 -m "Complete patch - 34 sections (security, features, code quality)"
git push origin v0.2.12
```

### Update Documentation

**Create deployment record**:
- Save this deployment document
- Update CLAUDE.md with new version number
- Note any issues encountered for future reference

---

## Troubleshooting Guide

### Issue: CORS Errors After Deployment

**Symptom**: API requests fail with CORS policy error

**Diagnosis**:
```bash
cd ~/tengo-app
grep "ALLOWED_ORIGINS" .env
php artisan config:cache
```

**Solution**:
```bash
# Ensure .env has correct values
nano .env
# Set: ALLOWED_ORIGINS=https://csjones.co/tengo

# Rebuild config cache
php artisan config:clear
php artisan config:cache
sudo systemctl restart php8.2-fpm nginx
```

### Issue: DC Pension Form Still Fails

**Symptom**: "Column 'provider' cannot be null" error

**Diagnosis**:
```bash
mysql -u [DB_USER] -p[DB_PASSWORD] [DB_NAME] -e "DESCRIBE dc_pensions;"
```

**Solution**:
```sql
-- Run the ALTER TABLE again
mysql -u [DB_USER] -p[DB_PASSWORD] [DB_NAME]
ALTER TABLE dc_pensions MODIFY COLUMN provider varchar(255) NULL;
EXIT;
```

### Issue: Migration Already Exists

**Symptom**: "Migration already exists" error

**Diagnosis**:
```bash
php artisan migrate:status
```

**Solution**: Migration already ran, no action needed. Verify columns exist:
```bash
mysql -u [DB_USER] -p[DB_PASSWORD] [DB_NAME] -e "DESCRIBE liabilities;"
```

### Issue: Assets Not Loading

**Symptom**: CSS/JS files return 404

**Diagnosis**:
```bash
ls -lh ~/tengo-app/public/build/manifest.json
ls -lh ~/tengo-app/public/build/assets/
```

**Solution**:
```bash
cd ~/tengo-app
npm run build
php artisan optimize:clear
sudo systemctl restart php8.2-fpm nginx
```

### Issue: White Screen / 500 Error

**Symptom**: Homepage shows blank white screen

**Diagnosis**:
```bash
tail -100 ~/tengo-app/storage/logs/laravel.log
sudo tail -100 /var/log/nginx/error.log
```

**Solution**:
```bash
# Clear all caches
cd ~/tengo-app
php artisan optimize:clear
php artisan cache:clear

# Check file permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Restart services
sudo systemctl restart php8.2-fpm nginx
```

---

## File Upload Checklist

**Use this checklist to track file uploads**:

### Backend Files (15 files)
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

### Frontend Files (26 files)
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
- [ ] resources/js/constants/taxConfig.js (NEW FILE)
- [ ] resources/js/store/modules/protection.js
- [ ] resources/js/views/Dashboard.vue
- [ ] resources/js/views/Retirement/RetirementReadiness.vue

---

## Database Changes Verification Script

**Save this script as verify_v0.2.12_database.sql**:

```sql
-- ============================================
-- Fynla v0.2.12 Database Verification Script
-- ============================================

-- 1. Verify dc_pensions.provider is nullable
SELECT
    COLUMN_NAME,
    IS_NULLABLE,
    COLUMN_TYPE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'dc_pensions'
  AND COLUMN_NAME = 'provider';
-- Expected: IS_NULLABLE = 'YES'

-- 2. Verify liabilities has new ownership columns
SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'liabilities'
  AND COLUMN_NAME IN ('ownership_type', 'joint_owner_id', 'trust_id')
ORDER BY ORDINAL_POSITION;
-- Expected: 3 rows returned

-- 3. Check liabilities ownership_type enum values
SELECT COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'liabilities'
  AND COLUMN_NAME = 'ownership_type';
-- Expected: enum('individual','joint','trust')

-- 4. Verify foreign key constraints exist
SELECT
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_NAME = 'liabilities'
  AND REFERENCED_TABLE_NAME IS NOT NULL
  AND CONSTRAINT_NAME IN ('liabilities_joint_owner_id_foreign', 'liabilities_trust_id_foreign');
-- Expected: 2 rows (joint_owner_id → users, trust_id → trusts)

-- 5. Check existing liabilities data integrity
SELECT
    ownership_type,
    COUNT(*) as count,
    COUNT(DISTINCT user_id) as distinct_users
FROM liabilities
GROUP BY ownership_type;
-- Expected: All existing records should be 'individual'

-- 6. Verify no NULL provider values in dc_pensions cause issues
SELECT COUNT(*) as null_provider_count
FROM dc_pensions
WHERE provider IS NULL;
-- Expected: Any number (acceptable now that column is nullable)

-- 7. Check migration table
SELECT migration, batch
FROM migrations
WHERE migration LIKE '%liabilities%'
ORDER BY id DESC
LIMIT 1;
-- Expected: 2025_11_22_092125_add_joint_ownership_to_liabilities_table

-- Summary Report
SELECT
    'Database verification complete' as status,
    NOW() as verified_at;
```

**Run this script after deployment**:
```bash
mysql -u [DB_USER] -p[DB_PASSWORD] [DB_NAME] < verify_v0.2.12_database.sql
```

---

## Deployment Summary

**Version**: v0.2.12
**Total Sections**: 34 (ALL sections from friFixes21Nov.md)
**Total Files**: 33 files modified
**Database Changes**: 2 (1 migration + 1 production SQL)
**Security Fixes**: 10 sections
**Feature Fixes**: 22 sections
**Code Quality**: 7 improvements

**Key Highlights**:
- ✅ Enhanced security (token logging removed, password validation improved, CORS hardening)
- ✅ DC Pension provider now optional (frontend + backend + database)
- ✅ State pension saving fixed
- ✅ Joint investment accounts display correctly
- ✅ Tenants in common fully supported
- ✅ Liabilities support joint ownership and trusts
- ✅ Better ISA allowance tracking
- ✅ Spouse data pre-population in onboarding
- ✅ Slippery mode no longer persists across sessions
- ✅ Multiple UI enhancements and bug fixes

**Deployment Date**: November 22, 2025
**Deployed By**: [Your Name]
**Production URL**: https://csjones.co/tengo

---

## Document Control

**Created**: November 22, 2025
**Last Updated**: November 22, 2025
**Author**: Claude Code (Anthropic)
**Version**: 1.0
**Status**: Ready for Production Deployment

**Related Documents**:
- friFixes21Nov.md (source documentation)
- DEPLOYMENT_PATCH_v0.2.11_22Nov2025.md (previous deployment)
- CLAUDE.md (project guidelines)

---

**End of Deployment Instructions**

Generated with Claude Code
https://claude.com/claude-code
