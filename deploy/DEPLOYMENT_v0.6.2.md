# Fynla Deployment Guide - v0.6.2
## Release Date: 19 January 2026

---

## Release Overview

**Version:** 0.6.2
**Target:** https://fynla.org (SiteGround Shared Hosting)
**Previous Version:** 0.5.1

### What's New in v0.6.2

| Category | Feature |
|----------|---------|
| **Security** | TOTP MFA authentication, session management, login lockout |
| **Security** | GDPR compliance (data export, erasure requests, consent tracking) |
| **Security** | Role-Based Access Control (RBAC) |
| **Goals** | Goals-based financial planning module with progress tracking |
| **Investment** | Automated 7-factor risk calculator questionnaire |
| **Investment** | Dashboard redesign with "strategy" terminology |
| **Statements** | Balance Sheet tab (User/Spouse/Combined columns) |
| **Statements** | Income Statement + Cash Flow combined tab |
| **Preview** | 4 new personas with comprehensive test data |
| **Registration** | Beta registration flow improvements |
| **Bug Fixes** | Division by zero audit, mobile fixes, numerous improvements |

---

## Pre-Deployment Checklist

- [x] Local tests passing (`./vendor/bin/pest`)
- [x] Code formatted (`./vendor/bin/pint --test`)
- [x] All changes committed and pushed to main
- [ ] **Database backup taken on server** (CRITICAL)
- [ ] Maintenance mode plan ready

---

## CRITICAL WARNINGS

### 1. .htaccess File

> **DO NOT upload `public/.htaccess` from your local folder!**
>
> **Always use:** `deploy/fynla-org/.htaccess` → upload to `public_html/public/.htaccess`

The wrong .htaccess causes:
- 500 Internal Server Error (`<DirectoryMatch>` not allowed)
- Wrong `RewriteBase /tengo/` instead of `/`
- CSS/JS MIME type issues

### 2. Build Locally

> **The server cannot run `npm install` or `npm run build`** (memory limits)
>
> You MUST build frontend assets locally and include `public/build/` in the deployment package.

### 3. public/hot File

> **NEVER deploy `public/hot`** - causes blank page with 127.0.0.1:5173 errors.
>
> If this file exists locally, delete it or the build script will exclude it automatically.

---

## Database Migrations (20 total since v0.5.1)

These migrations will run with `php artisan migrate --force`:

```
# Previous Updates (Jan 10-17)
2026_01_10_131616_add_payday_day_of_month_to_users_table
2026_01_12_115104_add_dashboard_widget_order_to_users
2026_01_15_105903_add_other_trust_type_and_country_to_trusts_table
2026_01_15_111814_add_platform_fee_type_and_frequency_to_investment_accounts_table
2026_01_16_151113_add_factor_breakdown_to_risk_profiles
2026_01_17_092200_add_joint_owner_name_to_chattels_table
2026_01_17_100145_add_tenants_in_common_to_mortgages_ownership_type

# Goals Module (Jan 18)
2026_01_18_000001_create_goals_table
2026_01_18_000002_create_goal_contributions_table
2026_01_18_000003_migrate_existing_goals_data

# Security Compliance (Jan 19)
2026_01_19_134658_create_login_attempts_table
2026_01_19_134659_add_mfa_fields_to_users_table
2026_01_19_134700_add_lockout_fields_to_users_table
2026_01_19_134700_create_user_sessions_table
2026_01_19_135404_create_audit_logs_table
2026_01_19_140001_create_erasure_requests_table
2026_01_19_140002_create_user_consents_table
2026_01_19_140003_create_data_exports_table
2026_01_19_140501_create_roles_permissions_tables
2026_01_19_142149_alter_mfa_secret_column_to_text
```

---

## New Composer Dependencies

These packages are required for MFA (TOTP) functionality:

```bash
composer require pragmarx/google2fa-laravel bacon/bacon-qr-code
```

**Note:** The deployment package should include `vendor/` with these already installed. If deploying selectively, run this command on the server.

---

## Required Seeders (Order Matters!)

**Run these in order after migrations:**

| Order | Seeder | Purpose |
|-------|--------|---------|
| 1 | `TaxConfigurationSeeder` | UK tax values (required for calculations) |
| 2 | `TaxProductReferenceSeeder` | Tax Status tab data |
| 3 | `ActuarialLifeTablesSeeder` | Life expectancy tables |
| 4 | `RolesPermissionsSeeder` | **NEW:** RBAC roles and permissions |
| 5 | `AdminUserSeeder` | Admin user account |
| 6 | `PreviewUserSeeder` | Preview personas (4 updated) |

---

## Server Details

| Setting | Value |
|---------|-------|
| Host | `ssh.fynla.org` |
| Port | `18765` |
| Path | `~/www/fynla.org/public_html` |
| SSH Key | `~/.ssh/production` |
| SSH Username | `u2783-hrf1k8bpfg02` |

**SSH Connection:**
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
```

---

## Step-by-Step Deployment

### Step 1: Build Locally

```bash
cd /Users/Chris/Desktop/fpsApp/fynla
./deploy/fynla-org/build.sh
```

This script:
1. Sets `VITE_BASE_PATH=/build/` for root deployment
2. Runs `npm run build`
3. Creates deployment package (excludes `public/hot`, `.git`, `node_modules`)
4. Includes correct `.htaccess` from `deploy/fynla-org/`

**Verify build output:**
```bash
ls -la public/build/
cat public/build/manifest.json
```

### Step 2: Take Database Backup on Server

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org

# Create backup
cd ~/www/fynla.org/public_html
mysqldump -u YOUR_DB_USER -p YOUR_DATABASE > ~/backup_v0.5.1_$(date +%Y%m%d_%H%M%S).sql
```

Or use SiteGround Site Tools > Security > Backups

### Step 3: Enable Maintenance Mode (Optional)

```bash
cd ~/www/fynla.org/public_html
php artisan down --message="Upgrading to v0.6.2. Back in a few minutes."
```

### Step 4: Upload Deployment Package

**Option A: Full Package Upload**

1. Upload `fynla-deploy.tar.gz` via SiteGround File Manager
2. Navigate to `~/www/fynla.org/public_html/`
3. Upload the file

**Option B: Selective Upload**

Upload only changed files (see list below) if you prefer a smaller upload.

### Step 5: Extract Files (SSH)

```bash
cd ~/www/fynla.org/public_html

# Extract tarball
tar -xzf fynla-deploy.tar.gz

# Move files (if extracted to subfolder)
mv fynla-deploy/* .
mv fynla-deploy/.* . 2>/dev/null

# Clean up
rmdir fynla-deploy 2>/dev/null
rm fynla-deploy.tar.gz
```

### Step 6: Install New Composer Dependencies

If using selective upload (not full package):
```bash
composer require pragmarx/google2fa-laravel bacon/bacon-qr-code --no-dev
```

If using full package, vendor/ is already included.

### Step 7: Run Migrations

```bash
php artisan migrate --force
```

Expected: 20 migrations (see list above)

### Step 8: Run Seeders (In Order!)

```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=TaxProductReferenceSeeder --force
php artisan db:seed --class=ActuarialLifeTablesSeeder --force
php artisan db:seed --class=RolesPermissionsSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
```

### Step 9: Clear and Rebuild Caches

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

### Step 10: Disable Maintenance Mode

```bash
php artisan up
```

---

## All-in-One SSH Command Block

**Copy and paste this entire block after uploading files:**

```bash
cd ~/www/fynla.org/public_html && \
echo "=== Starting v0.6.2 Deployment ===" && \
tar -xzf fynla-deploy.tar.gz && \
mv fynla-deploy/* . 2>/dev/null && \
mv fynla-deploy/.* . 2>/dev/null && \
rmdir fynla-deploy 2>/dev/null && \
rm fynla-deploy.tar.gz && \
echo "Files extracted" && \
php artisan migrate --force && \
echo "Migrations complete" && \
php artisan db:seed --class=TaxConfigurationSeeder --force && \
php artisan db:seed --class=TaxProductReferenceSeeder --force && \
php artisan db:seed --class=ActuarialLifeTablesSeeder --force && \
php artisan db:seed --class=RolesPermissionsSeeder --force && \
php artisan db:seed --class=AdminUserSeeder --force && \
php artisan db:seed --class=PreviewUserSeeder --force && \
echo "Seeders complete" && \
php artisan config:clear && \
php artisan cache:clear && \
php artisan route:clear && \
php artisan view:clear && \
php artisan config:cache && \
php artisan route:cache && \
php artisan view:cache && \
php artisan optimize && \
echo "=== v0.6.2 Deployment Complete! ===" && \
php artisan --version
```

---

## Post-Deployment Verification

### Quick Tests

| Test | URL/Action | Expected |
|------|------------|----------|
| Homepage | https://fynla.org | Landing page loads |
| Version | Footer shows `v0.6.2` | Correct version |
| Login | Admin login | Works |
| MFA | Settings > Security | MFA setup available |
| Goals | Goals page | Goals module accessible |
| Risk | Investment > Risk | Questionnaire works |
| Statements | Valuable Info | Balance Sheet, Income Statement tabs |
| Personas | Landing > Try Demo | 4 personas work |

### Detailed Verification Checklist

- [ ] Homepage loads with no console errors
- [ ] Footer shows v0.6.2
- [ ] Admin login works (`admin@fps.com` / `admin123`)
- [ ] Preview personas selectable from landing page
- [ ] James Carter (young_family) data loads correctly
- [ ] David Mitchell (peak_earners) data loads correctly
- [ ] Goals module: Can view/add goals
- [ ] Investment Risk Calculator: 7-question flow works
- [ ] Investment Dashboard: Shows "strategy" terminology
- [ ] Valuable Info: Balance Sheet tab displays
- [ ] Valuable Info: Income Statement/Cash Flow tab displays
- [ ] Settings: Security section visible (MFA setup available)
- [ ] Mobile responsive: Test on phone/tablet

### Log Check

```bash
# Check for errors
tail -100 ~/www/fynla.org/public_html/storage/logs/laravel.log
```

---

## Rollback Procedure

If something goes wrong:

### 1. Restore Database

```bash
mysql -u YOUR_DB_USER -p YOUR_DATABASE < ~/backup_v0.5.1_TIMESTAMP.sql
```

### 2. Restore Files

Use SiteGround Site Tools > Security > Backups to restore previous file state.

### 3. Rollback Migrations (Alternative)

```bash
# Rollback all Jan 19 migrations
php artisan migrate:rollback --step=10 --force

# Clear caches
php artisan config:clear
php artisan cache:clear
```

---

## Troubleshooting

### Common Issues

| Issue | Symptom | Solution |
|-------|---------|----------|
| Blank page | 127.0.0.1:5173 in console | `rm public/hot` on server |
| 500 Error | "DirectoryMatch not allowed" | Upload `deploy/fynla-org/.htaccess` to `public/.htaccess` |
| MIME errors | JS/CSS not loading | Wrong VITE_BASE_PATH; rebuild locally |
| MFA not working | Setup fails | Check `google2fa-laravel` installed |
| Goals missing | 404 on goals | Check migrations ran |
| Personas broken | "User not found" | Run PreviewUserSeeder |
| Tax errors | "No active tax config" | Run TaxConfigurationSeeder |

### Debug Commands

```bash
# Check environment
php artisan env

# Check migrations
php artisan migrate:status | tail -25

# Test database
php artisan tinker --execute="DB::connection()->getPdo();"

# Check routes
php artisan route:list | grep -E "(goals|mfa|risk)"

# Check logs
tail -100 storage/logs/laravel.log

# Clear everything
php artisan optimize:clear
```

---

## Files Changed Since v0.5.1

### New Backend Files

```
app/Http/Controllers/Api/GoalsController.php
app/Http/Controllers/Api/MFAController.php
app/Http/Controllers/Api/SessionController.php
app/Http/Controllers/Api/GDPRController.php
app/Http/Middleware/EnsureMFAVerified.php
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
app/Services/Auth/MFAService.php
app/Services/Auth/LoginLockoutService.php
app/Services/Auth/SessionService.php
app/Services/Audit/AuditService.php
app/Services/GDPR/DataExportService.php
app/Services/GDPR/DataErasureService.php
app/Services/GDPR/ConsentService.php
app/Services/Goals/GoalService.php
app/Services/Investment/RiskCalculatorService.php
database/seeders/RolesPermissionsSeeder.php
```

### New Frontend Files

```
resources/js/views/Goals/GoalsDashboard.vue
resources/js/components/Goals/GoalCard.vue
resources/js/components/Goals/GoalFormModal.vue
resources/js/components/Goals/GoalProgressBar.vue
resources/js/components/Auth/MFASetupModal.vue
resources/js/components/Auth/MFAVerifyModal.vue
resources/js/components/Settings/ActiveSessions.vue
resources/js/components/Investment/RiskQuestionnaire.vue
resources/js/components/UserProfile/BalanceSheetTab.vue
resources/js/components/UserProfile/IncomeStatementTab.vue
resources/js/store/modules/goals.js
```

### Modified Files

```
app/Http/Controllers/Api/AuthController.php
app/Http/Controllers/Api/InvestmentController.php
app/Models/User.php
resources/js/views/Investment/InvestmentDashboard.vue
resources/js/views/ValuableInfo.vue
resources/js/components/Footer.vue
resources/js/views/Version.vue
routes/api.php
database/seeders/PreviewUserSeeder.php
```

---

## Security Checklist

- [ ] `APP_DEBUG=false` in .env
- [ ] `APP_ENV=production` in .env
- [ ] `.env` file is NOT in public directory
- [ ] `.htaccess` blocks access to .env and .git
- [ ] HTTPS is enforced
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] MFA available for users
- [ ] Login lockout active (5 failed attempts = 15 min lockout)
- [ ] RBAC roles configured

---

## Support

If deployment fails:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Check SiteGround error logs: Site Tools > Statistics > Error Log
3. Restore from backup if needed
