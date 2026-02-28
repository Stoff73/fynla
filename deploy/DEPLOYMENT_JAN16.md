# Fynla Deployment Instructions - January 16, 2026

## Pre-Deployment Checklist
- [x] Local build completed (`./deploy/fynla-org/build.sh`)
- [ ] Upload files via FileManager
- [ ] **Upload correct .htaccess** (see Critical Note below)
- [ ] Run SSH commands

---

## CRITICAL: .htaccess File

**WARNING:** The local `public/.htaccess` is configured for csjones.co/tengo (subdirectory deployment) and is **WRONG** for fynla.org.

**Use this file instead:** `deploy/fynla-org/.htaccess`

The wrong .htaccess causes:
- 500 Internal Server Error (`<DirectoryMatch>` not allowed in .htaccess)
- Wrong `RewriteBase /tengo/` instead of `/`
- Wrong storage path `^/tengo/storage/`

---

## Option A: Full Deployment (Recommended)

Upload the complete deployment package:
```
Location: /Users/Chris/Desktop/fpsApp/fynla-org-deploy.zip
Size: 178M
```

### Upload via FileManager:
1. Navigate to `~/www/fynla.org/public_html/`
2. Upload `fynla-org-deploy.zip`
3. Extract in place (will overwrite existing files)
4. Delete the ZIP file after extraction

---

## Option B: Selective File Upload

If you prefer to upload only changed files, here are all the files modified since the last deployment:

### 1. Frontend Build Assets (CRITICAL - Upload entire folder)
```
public/build/          -> ~/www/fynla.org/public_html/public/build/
```
**Delete the existing `public/build/` folder on server first, then upload the new one.**

### 2. Public Files
```
public/sitemap.xml              -> ~/www/fynla.org/public_html/public/sitemap.xml
public/robots.txt               -> ~/www/fynla.org/public_html/public/robots.txt
deploy/fynla-org/.htaccess      -> ~/www/fynla.org/public_html/public/.htaccess  (CRITICAL!)
```
**NOTE:** Upload `deploy/fynla-org/.htaccess` NOT `public/.htaccess`

### 3. Backend - Controllers
```
app/Http/Controllers/Api/InvestmentController.php
app/Http/Controllers/Api/UserProfileController.php
```

### 4. Backend - Request Validators
```
app/Http/Requests/BusinessInterest/StoreBusinessInterestRequest.php
app/Http/Requests/Chattel/StoreChattelRequest.php
app/Http/Requests/Investment/OptimizePortfolioRequest.php
app/Http/Requests/Protection/StoreCriticalIllnessPolicyRequest.php
app/Http/Requests/Protection/StoreProtectionProfileRequest.php
app/Http/Requests/Retirement/UpdateStatePensionRequest.php
app/Http/Requests/Savings/StoreSavingsAccountRequest.php
app/Http/Requests/StoreFamilyMemberRequest.php
app/Http/Requests/StorePersonalAccountLineItemRequest.php
app/Http/Requests/UpdateDomicileInfoRequest.php
```

### 5. Backend - Models
```
app/Models/EmailVerificationCode.php
app/Models/Estate/Trust.php
app/Models/Investment/InvestmentAccount.php
app/Models/User.php
```

### 6. Backend - Services
```
app/Services/Estate/FutureValueCalculator.php
app/Services/UserProfile/ModuleDataRequirementsService.php
app/Services/UserProfile/UserProfileService.php
```

### 7. Database - Migrations (NEW)
```
database/migrations/2026_01_08_091458_make_form_fields_optional.php
database/migrations/2026_01_10_131616_add_payday_day_of_month_to_users_table.php
database/migrations/2026_01_12_115104_add_dashboard_widget_order_to_users.php
database/migrations/2026_01_15_105903_add_other_trust_type_and_country_to_trusts_table.php
database/migrations/2026_01_15_111814_add_platform_fee_type_and_frequency_to_investment_accounts_table.php
```

### 8. Database - Seeders
```
database/seeders/DatabaseSeeder.php
database/seeders/PreviewUserSeeder.php
```

### 9. Routes
```
routes/api.php
```

### 10. Files to DELETE on Server
These files were removed in the codebase (life expectancy consolidation) and should be deleted from production:
```
app/Services/Estate/ActuarialLifeTableService.php
database/seeders/UKLifeExpectancySeeder.php
config/uk_life_expectancy.php
```

---

## SSH Commands (Run After Upload)

Connect to server:
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
```

Navigate to app directory:
```bash
cd ~/www/fynla.org/public_html
```

### Step 1: Run Database Migrations
```bash
php artisan migrate --force
```

Expected migrations:
- `2026_01_08_091458_make_form_fields_optional`
- `2026_01_10_131616_add_payday_day_of_month_to_users_table`
- `2026_01_12_115104_add_dashboard_widget_order_to_users`
- `2026_01_15_105903_add_other_trust_type_and_country_to_trusts_table`
- `2026_01_15_111814_add_platform_fee_type_and_frequency_to_investment_accounts_table`

### Step 2: Reseed Preview Users (Optional but Recommended)
```bash
php artisan db:seed --class=PreviewUserSeeder --force
```

### Step 3: Clear All Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### Step 4: Optimize for Production
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 5: Verify Deployment
```bash
# Check Laravel version and environment
php artisan --version
php artisan env

# Check migration status
php artisan migrate:status | tail -10

# Test a simple route
curl -s https://fynla.org/api/health | head -5
```

---

## All-in-One SSH Command Block

Copy and paste this entire block after uploading files:

```bash
cd ~/www/fynla.org/public_html && \
rm -f app/Services/Estate/ActuarialLifeTableService.php && \
rm -f database/seeders/UKLifeExpectancySeeder.php && \
rm -f config/uk_life_expectancy.php && \
echo "Deleted obsolete files" && \
php artisan migrate --force && \
php artisan db:seed --class=PreviewUserSeeder --force && \
php artisan config:clear && \
php artisan cache:clear && \
php artisan route:clear && \
php artisan view:clear && \
php artisan config:cache && \
php artisan route:cache && \
php artisan view:cache && \
echo "Deployment complete!"
```

---

## Post-Deployment Verification

1. **Test Landing Page**: https://fynla.org/
2. **Test Demo Flow**: Click "Try the Demo" and select a persona
3. **Test Dashboard**: Verify all cards load correctly
4. **Check Console**: Open browser DevTools, verify no CSS/JS errors

---

## Rollback (If Needed)

If something goes wrong:

```bash
# Rollback last migration batch
php artisan migrate:rollback --step=1

# Clear caches
php artisan config:clear
php artisan cache:clear
```

---

## Changes Summary

### New Features (Jan 15-16)
- Trust module: "Other" trust type with country field
- Investment module: Flexible platform fee options (%, fixed, frequency)
- Security page at /security
- Sitemap page at /sitemap
- XML sitemap for search engines
- Hero section improvements (clickable cards, layout optimization)
- Calculators page restyle
- Learning Centre restyle

### Bug Fixes
- Landing page text spacing fix
- Security page contact email fix
- Router scroll behaviour

### Version
- Updated to v0.5.1
