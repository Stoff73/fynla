# December 31, 2025 Updates

## Summary

This folder documents changes made on December 31, 2025.

## Changes

### 1. Information Guide Feature
**File:** `InfoGuide_Feature.md`

Added a floating help button that shows users what data is needed for each module, with context-aware requirements and plain-language explanations.

### 2. Seeder Requirements Update
**File:** `Seeder_Requirements_Update.md`

Updated seeder classification to make `AdminUserSeeder` and `PreviewUserSeeder` required seeders (Phase 1) instead of optional development-only seeders.

### 3. Documentation Cleanup

Removed outdated documentation from Dec14-Dec30 folders:
- 50+ markdown files consolidated/archived
- Reference PDFs moved elsewhere
- Word documents removed

### 4. Investment Portfolio Return Fix
**File:** `Investment_Portfolio_Return_Fix.md`

Fixed the Investment Portfolio Summary card showing YTD Return as 0%. Now displays:
- **Gross Return** - Annualised return before fees
- **Net of Fees Return** - Annualised return after platform, advisor, and OCF fees

Calculation uses value-weighted average of individual account returns.

### 5. Diversification Tab for Investment Accounts & DC Pensions
**Files:** `Diversification_Tab_Plan.md`, `divTasks.md`

Added a new "Diversification" tab to investment account and DC pension detail views:
- **HHI Score** - Herfindahl-Hirschman Index for concentration measurement
- **Concentration Warnings** - Alerts for over-concentrated positions
- **Asset Class Breakdown** - Visual breakdown vs target allocation
- **Recommendations** - Actionable suggestions based on analysis

Backend service: `DiversificationAnalyzer.php` with 46 unit tests.

### 6. Portfolio-Wide Diversification Score
**File:** `Portfolio_Diversification_Score_Plan.md`

Fixed the Investment Portfolio Summary card showing Diversification Score as 0/100. Now calculates:
- **Value-weighted average** of per-account diversification scores
- **Score labels**: Excellent (80+), Good (60-79), Fair (40-59), Poor (<40)
- Uses HHI, concentration metrics, and asset class diversity

Example: peak_earners persona shows 40/100 (Fair).

### 7. Version Bump to v0.4.4

Updated version across all files:
- `CLAUDE.md`, `README.md`
- `Footer.vue`, `PublicLayout.vue`
- `Version.vue` with full changelog

### 8. Code Review Fixes

Addressed issues from code quality audit (82/100 score):
- Critical: Fixed currency formatting consistency
- High: Removed code duplication in Vue components
- Medium: Added missing type hints, removed unused imports
- Low: Fixed PHPDoc comments, removed debug code

### 9. Deployment Environment Separation
**Files:** `deploy/`, `DEPLOYMENT_FYNLA_ORG.md`

Created structured deployment system with clear environment separation:

```
deploy/
├── README.md                    # Documentation
├── fynla-org/                   # ROOT deployment (https://fynla.org)
│   ├── .env.production          # Environment template
│   ├── .htaccess                # RewriteBase /
│   └── build.sh                 # Sets VITE_BASE_PATH=/build/
└── csjones-fynla/               # SUBDIRECTORY (https://csjones.co/fynla)
    ├── .env.production          # Environment template
    ├── .htaccess                # RewriteBase /fynla/
    └── build.sh                 # Sets VITE_BASE_PATH=/fynla/build/
```

**Changes:**
- Updated `vite.config.js` to use `VITE_BASE_PATH` environment variable
- Created comprehensive deployment guide: `DEPLOYMENT_FYNLA_ORG.md`
- Created build scripts for each target
- Cleaned up scattered .env and .htaccess files

### 10. Deployment Package Created

Built and packaged application for fynla.org deployment:
- **File:** `/Users/Chris/Desktop/fpsApp/fynla-fynla-org-deploy.zip` (118 MB)
- Built with `VITE_BASE_PATH=/build/` for root deployment
- Includes correct `.htaccess` with `RewriteBase /`
- Excludes: `.git`, `node_modules`, `tests`, `.env`, `*.md`

## Required Seeders (Updated)

After these changes, the following 6 seeders are required for the app to function:

```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=TaxProductReferenceSeeder --force
php artisan db:seed --class=UKLifeExpectancySeeder --force
php artisan db:seed --class=ActuarialLifeTablesSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
```

## Commits

| Hash | Description |
|------|-------------|
| `4a75bd3` | feat: Add deployment environment separation and fynla.org guide |
| `b67ef93` | chore: Bump version to v0.4.4 |
| `673deb9` | fix: Address code review issues across all priority levels |
| `aa61489` | fix: Remove skipped tests and fix test failures |
| `1a1956f` | fix: Remove holdings display from investment account cards |
| `164b3f8` | feat: Add portfolio-wide diversification score to Investment Summary |
| `2f5ff78` | feat: Add Diversification Tab for investment accounts and DC pensions |
| `3696c74` | feat: Add portfolio-wide annualised return to Investment Summary |
| `b7f419a` | docs: Add Dec31Updates documentation |
| `f81489d` | docs: Update seeder requirements and clean up old documentation |
| `786e2d0` | feat: Add Information Guide feature for data requirements |

---

## NEXT SESSION: Deploy to fynla.org

### Deployment Package Ready

**Location:** `/Users/Chris/Desktop/fpsApp/fynla-fynla-org-deploy.zip` (118 MB)

### Deployment Steps

Follow `DEPLOYMENT_FYNLA_ORG.md` for full details. Quick reference:

#### 1. Upload to SiteGround
1. Log in to SiteGround > Websites > fynla.org > Site Tools
2. Go to Site > File Manager
3. Navigate to `public_html/`
4. Upload `fynla-fynla-org-deploy.zip`
5. Extract the ZIP
6. Move contents from `fynla-deploy/` to `public_html/` root
7. Delete the empty folder and ZIP

#### 2. Configure Document Root
- Site Tools > Site > Site Settings
- Change Document Root to: `public_html/public`

#### 3. Create .env File
1. In File Manager, create `.env` in `public_html/`
2. Copy content from `deploy/fynla-org/.env.production`
3. Update placeholder values:
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
   - `MAIL_PASSWORD`
   - `ANTHROPIC_API_KEY`

#### 4. SSH Commands (in order)
```bash
ssh your_username@fynla.org
cd ~/public_html

# Permissions
chmod -R 775 storage bootstrap/cache
chmod 644 .env

# Storage directories
mkdir -p storage/app/public
mkdir -p storage/framework/{cache/data,sessions,views}
mkdir -p storage/logs

# Generate key
php artisan key:generate --force

# Database
php artisan migrate --force

# Seeders (CRITICAL)
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=TaxProductReferenceSeeder --force
php artisan db:seed --class=UKLifeExpectancySeeder --force
php artisan db:seed --class=ActuarialLifeTablesSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force

# Storage link
php artisan storage:link

# Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

#### 5. Verify
- Visit https://fynla.org - should see landing page
- Test login: admin@fps.com / admin123
- Test preview personas from landing page

### If Rebuild Needed

```bash
# From project root
./deploy/fynla-org/build.sh

# Then recreate package (excludes .git, node_modules, tests, .env, *.md)
```
