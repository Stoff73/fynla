# Fynla Deployment Guide - v0.6.2
## Release Date: 20 January 2026

---

## Deployment Philosophy

**DO NOT create deployment packages.** Instead:
1. Upload only changed files via SiteGround File Manager
2. Run SSH commands for migrations, seeders, and cache clears
3. If frontend changes exist, rebuild locally and upload `public/build/`

---

## Server Connection

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
```

---

## Release Overview

**Version:** 0.6.2
**Target:** https://fynla.org
**Previous Version:** 0.5.1

### What's New in v0.6.2

| Category | Feature |
|----------|---------|
| **Security** | TOTP MFA, session management, login lockout |
| **Security** | GDPR compliance (data export, erasure, consent) |
| **Security** | Role-Based Access Control (RBAC) |
| **Goals** | Goals-based financial planning module |
| **Investment** | Automated 7-factor risk calculator |
| **Investment** | Dashboard redesign + Strategy card fix |
| **Statements** | Balance Sheet tab, Income Statement tab |
| **Preview** | 4 personas with comprehensive test data |
| **Bug Fixes** | Strategy card thresholds, UI polish |

---

## Pre-Deployment Checklist

- [x] Local tests passing
- [x] Code formatted (`./vendor/bin/pint`)
- [x] All changes committed to main
- [ ] **Database backup taken on server**

---

## Step 1: Backup Database (CRITICAL)

```bash
# SSH to server first
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Create backup (get credentials from .env)
mysqldump -u DB_USER -p DB_NAME > ~/backup_v0.5.1_$(date +%Y%m%d).sql
```

Or use SiteGround Site Tools > Security > Backups

---

## Step 2: Upload Changed Files

### Backend Files to Upload

Upload via SiteGround File Manager to `www/fynla.org/public_html/`

**Agents (2 files):**
```
app/Agents/GoalsAgent.php
app/Agents/InvestmentAgent.php  # Modified - Strategy card thresholds + high_fee_holdings
```

**Controllers (7 files):**
```
app/Http/Controllers/Api/GoalsController.php
app/Http/Controllers/Api/MFAController.php
app/Http/Controllers/Api/SessionController.php
app/Http/Controllers/Api/GDPRController.php
app/Http/Controllers/Api/PreviewController.php  # Modified - young_saver/retired_couple personas
app/Http/Controllers/Api/AuthController.php  # Modified
app/Http/Controllers/Api/InvestmentController.php  # Modified
```

**Middleware (3 files):**
```
app/Http/Middleware/EnsureMFAVerified.php
app/Http/Middleware/HasPermission.php
app/Http/Middleware/HasRole.php
```

**Models (10 files):**
```
app/Models/Goal.php
app/Models/GoalContribution.php
app/Models/LoginAttempt.php
app/Models/UserSession.php
app/Models/AuditLog.php
app/Models/ErasureRequest.php
app/Models/UserConsent.php
app/Models/DataExport.php
app/Models/Role.php
app/Models/Permission.php
app/Models/User.php  # Modified
```

**Services - Auth (4 files):**
```
app/Services/Auth/MFAService.php
app/Services/Auth/LoginLockoutService.php
app/Services/Auth/SessionService.php
app/Services/Auth/PermissionService.php
```

**Services - Audit (1 file):**
```
app/Services/Audit/AuditService.php
```

**Services - GDPR (3 files):**
```
app/Services/GDPR/DataExportService.php
app/Services/GDPR/DataErasureService.php
app/Services/GDPR/ConsentService.php
```

**Services - Goals (4 files):**
```
app/Services/Goals/GoalAssignmentService.php
app/Services/Goals/GoalAffordabilityService.php
app/Services/Goals/GoalRiskService.php
app/Services/Goals/GoalProgressService.php
```

**Services - Investment (1 file - Strategy card fix):**
```
app/Services/Investment/FeeAnalyzer.php  # Modified - high-fee threshold
```

**Traits (1 file):**
```
app/Traits/Auditable.php
```

**Migrations (16 files):**
```
database/migrations/2026_01_16_151113_add_factor_breakdown_to_risk_profiles.php
database/migrations/2026_01_17_092200_add_joint_owner_name_to_chattels_table.php
database/migrations/2026_01_17_100145_add_tenants_in_common_to_mortgages_ownership_type.php
database/migrations/2026_01_18_000001_create_goals_table.php
database/migrations/2026_01_18_000002_create_goal_contributions_table.php
database/migrations/2026_01_18_000003_migrate_existing_goals_data.php
database/migrations/2026_01_19_134658_create_login_attempts_table.php
database/migrations/2026_01_19_134659_add_mfa_fields_to_users_table.php
database/migrations/2026_01_19_134700_add_lockout_fields_to_users_table.php
database/migrations/2026_01_19_134700_create_user_sessions_table.php
database/migrations/2026_01_19_135404_create_audit_logs_table.php
database/migrations/2026_01_19_140001_create_erasure_requests_table.php
database/migrations/2026_01_19_140002_create_user_consents_table.php
database/migrations/2026_01_19_140003_create_data_exports_table.php
database/migrations/2026_01_19_140501_create_roles_permissions_tables.php
database/migrations/2026_01_19_142149_alter_mfa_secret_column_to_text.php
```

**Seeders (1 file):**
```
database/seeders/RolesPermissionsSeeder.php
database/seeders/PreviewUserSeeder.php  # Modified
```

**Routes (1 file):**
```
routes/api.php
```

---

## Step 3: Frontend Build (if frontend changed)

Frontend files changed - rebuild required:

```bash
# Local machine
cd /Users/Chris/Desktop/fpsApp/fynla

# Set environment and build
export VITE_BASE_PATH=/build/
export VITE_ROUTER_BASE=/
export VITE_API_BASE_URL=https://fynla.org
npm run build

# Verify build
ls -la public/build/
```

**Upload entire `public/build/` folder** to server via File Manager.

Also upload `deploy/fynla-org/.htaccess` to `public/.htaccess` (if not already correct).

---

## Step 4: Install Composer Dependencies

```bash
# SSH to server
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Install MFA packages
composer require pragmarx/google2fa-laravel bacon/bacon-qr-code

# Fix security vulnerability
composer update symfony/http-foundation
composer audit
```

---

## Step 5: Run Migrations

```bash
php artisan migrate --force
```

Expected: 16 migrations

---

## Step 6: Run Seeders

```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=TaxProductReferenceSeeder --force
php artisan db:seed --class=ActuarialLifeTablesSeeder --force
php artisan db:seed --class=RolesPermissionsSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
```

---

## Step 7: Clear Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

---

## All-in-One SSH Commands (After File Upload)

```bash
cd ~/www/fynla.org/public_html && \
composer require pragmarx/google2fa-laravel bacon/bacon-qr-code && \
composer update symfony/http-foundation && \
php artisan migrate --force && \
php artisan db:seed --class=TaxConfigurationSeeder --force && \
php artisan db:seed --class=TaxProductReferenceSeeder --force && \
php artisan db:seed --class=ActuarialLifeTablesSeeder --force || true && \
php artisan db:seed --class=RolesPermissionsSeeder --force && \
php artisan db:seed --class=AdminUserSeeder --force && \
php artisan db:seed --class=PreviewUserSeeder --force && \
php artisan config:clear && \
php artisan cache:clear && \
php artisan route:clear && \
php artisan view:clear && \
php artisan config:cache && \
php artisan route:cache && \
php artisan view:cache && \
php artisan optimize && \
echo "=== Deployment Complete ===" && \
php artisan --version
```

---

## Post-Deployment Verification

| Test | Expected |
|------|----------|
| https://fynla.org | Landing page loads |
| Footer version | v0.6.2 |
| Preview personas | 4 personas work |
| Investment Dashboard | Strategy card shows recommendations |
| Goals module | Accessible |
| Risk calculator | 7-question flow |

---

## Bug Fixes in v0.6.2

### BUG-001: Strategy Card Not Showing

**Files changed:**
- `app/Agents/InvestmentAgent.php` - Adjusted recommendation thresholds
- `app/Services/Investment/FeeAnalyzer.php` - Lowered high-fee threshold

**Fix:** Thresholds were too stringent. Changed:
- Diversification: < 60 → < 70
- Fee savings: > £100 → > £50
- Tax efficiency: < 70 → < 80
- High-fee: 0.75% → 0.5%
- Added new "High-Fee Holdings" recommendation

### UI-001: Strategy Count Badge Removed

**File changed:**
- `resources/js/components/NetWorth/InvestmentList.vue`

**Fix:** Removed redundant "4 strategies" badge from card header.

---

## Rollback

```bash
# Restore database
mysql -u DB_USER -p DB_NAME < ~/backup_v0.5.1_YYYYMMDD.sql

# Rollback migrations
php artisan migrate:rollback --step=16 --force

# Clear caches
php artisan optimize:clear
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Blank page | `rm public/hot` on server |
| 500 error | Upload `deploy/fynla-org/.htaccess` to `public/.htaccess` |
| MIME errors | Rebuild with correct VITE_BASE_PATH |
| Strategy card missing | Clear cache: `php artisan cache:clear` |
| Personas broken | Run PreviewUserSeeder |
