# Deploy Guide — Code Review Fixes + Protection Bug Fix (30 March 2026)

## Summary

54 code review issues fixed (security, tax compliance, design system, convention) + protection dashboard bug fix. Includes PR #170 (content branch), PR #171/172 (image fixes), PR #174 (code review fixes), and protection card fix.

**Both PHP and frontend changes.** Requires rebuild, PHP upload, migration, cache clear.

## Pre-Deploy: Rebuild

```bash
./deploy/fynla-org/build.sh
```

## Upload

### 1. Frontend (compiled build)

Upload the entire `public/build/` directory:
```
~/www/fynla.org/public_html/public/build/
```

### 2. Images

Upload these new files:
```
~/www/fynla.org/public_html/public/images/Website/Fyn-Brain-Animated-Short.gif
~/www/fynla.org/public_html/public/images/Website/Homepage-Header-Desktopv3.png
```

### 3. PHP Files (48 files)

**Models (14 files):**
```
app/Models/CriticalIllnessPolicy.php
app/Models/DCPension.php
app/Models/DisabilityPolicy.php
app/Models/IncomeProtectionPolicy.php
app/Models/LifeInsurancePolicy.php
app/Models/Payment.php
app/Models/ProtectionProfile.php
app/Models/SicknessIllnessPolicy.php
app/Models/Subscription.php
app/Models/User.php
```

**Controllers (13 files):**
```
app/Http/Controllers/Api/AdminController.php
app/Http/Controllers/Api/AuthController.php
app/Http/Controllers/Api/FamilyMembersController.php
app/Http/Controllers/Api/GDPRController.php
app/Http/Controllers/Api/Investment/AssetLocationController.php
app/Http/Controllers/Api/Investment/EfficientFrontierController.php
app/Http/Controllers/Api/Investment/PerformanceAttributionController.php
app/Http/Controllers/Api/Investment/PortfolioStrategyController.php
app/Http/Controllers/Api/Investment/RebalancingCalculationController.php
app/Http/Controllers/Api/Investment/TaxOptimizationController.php
app/Http/Controllers/Api/MFAController.php
app/Http/Controllers/Api/SessionController.php
```

**Middleware (3 files):**
```
app/Http/Middleware/EnsureMFAVerified.php
app/Http/Middleware/SanitizeInput.php
app/Http/Middleware/SecurityHeaders.php
```

**Form Requests (9 files):**
```
app/Http/Requests/Estate/StoreAssetRequest.php
app/Http/Requests/Estate/StoreLpaRequest.php
app/Http/Requests/LoginRequest.php
app/Http/Requests/RegisterRequest.php
app/Http/Requests/StoreClientActivityRequest.php
app/Http/Requests/StorePersonalAccountLineItemRequest.php
app/Http/Requests/StoreTaxConfigurationRequest.php
app/Http/Requests/UpdateDomicileInfoRequest.php
app/Http/Requests/V1/RegisterDeviceRequest.php
```

**Services (6 files):**
```
app/Services/Auth/PermissionService.php
app/Services/Coordination/ConflictResolver.php
app/Services/Coordination/CrossModuleStrategyService.php
app/Services/Coordination/HouseholdPlanningService.php
app/Services/Coordination/RecommendationPersonaliser.php
app/Services/Retirement/RetirementActionDefinitionService.php
app/Services/UserProfile/UserProfileService.php
```

**Other PHP (3 files):**
```
app/Agents/EstateAgent.php
app/Traits/PolicyCRUDTrait.php
```

**Migration (1 new file):**
```
database/migrations/2026_03_30_000001_add_soft_deletes_to_payments_table.php
```

**Seeders (4 files):**
```
database/seeders/AdminUserSeeder.php
database/seeders/DatabaseSeeder.php
database/seeders/HouseholdSeeder.php
database/seeders/TestUsersSeeder.php
```

## Post-Deploy: SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migration (adds deleted_at to payments table)
php artisan migrate

# Reseed (updates admin password, environment gates)
php artisan db:seed

# Clear all caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## What Changed

### Critical Fixes
- Payment/Subscription amount: integer → decimal:2 (data precision)
- PricingPage FAQ: "Stripe" → "Revolut"
- Login/Register: mobile responsive padding
- Tax thresholds: TaxConfigService lookups instead of hardcoded
- RNRB qualification checks + taper in estate planning
- Protection dashboard: field name fix (cover_amount → sum_assured) + disability policies added

### Security
- NI number masked in API responses
- MFA token abilities enforced
- Exception messages sanitised
- User model $guarded hardened
- Payment/Subscription $fillable hardened
- Admin filesystem paths removed
- SanitizeInput exemptions added
- Security headers (COOP, X-Permitted-Cross-Domain-Policies)
- MFAController uses UserResource
- Readonly constructor properties
- Payment SoftDeletes + migration
- Admin seeder: env-based password, removed is_preview_user

### Tax Compliance
- RetirementActionDefinitionService: TaxConfigService
- HouseholdPlanningService: RNRB qualification + taper
- ISA/NRB/RNRB fallbacks use TaxDefaults constants
- Frontend ANNUAL_ALLOWANCE constant

### Design System
- bg-[#EEEEEE] → bg-light-gray (14 files)
- Spinner/tab colors standardised (11 files)
- Banned amber removed (3 files)
- Score gauge → descriptive text
- Chart colors from designSystem.js
- slate/emerald/red → palette tokens
- Scoped style hex fixed

### Convention
- Acronyms spelled out (DC, DB, SIPP, ICE)
- console.log → logger.debug
- Timer leaks fixed
- sanitizeHtml added to AI chat
- declare(strict_types=1) in seeders
- AdvisorClientSeeder environment-gated

## Checklist

- [ ] Rebuild: `./deploy/fynla-org/build.sh`
- [ ] Upload `public/build/` directory
- [ ] Upload 2 new images to `public/images/Website/`
- [ ] Upload 48 PHP files (maintain directory structure)
- [ ] Upload 1 new migration file
- [ ] SSH: `php artisan migrate`
- [ ] SSH: `php artisan db:seed`
- [ ] SSH: Clear all caches
- [ ] Verify homepage loads (new header image, brain GIF)
- [ ] Verify login works (responsive, raspberry error text)
- [ ] Verify pricing page ("Revolut" in FAQ, "our features" link)
- [ ] Verify features page loads (/features route)
- [ ] Verify David Mitchell dashboard (Protection: Life £500k, CI £200k, Disability £7,250)
- [ ] Verify How It Works page ("Self-Invested Personal Pension")
- [ ] Delete `public/mockup-starting-out.html` if present on server
