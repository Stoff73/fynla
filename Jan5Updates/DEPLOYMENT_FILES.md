# Complete Deployment Files - Jan 2-5, 2026

This document lists ALL files that need to be uploaded to production from the Jan2, Jan3, and Jan5 updates.

## PHP Backend Files (11 files)

### Jan2Updates

| # | Local Path | Server Path |
|---|------------|-------------|
| 1 | `app/Http/Controllers/Api/FamilyMembersController.php` | `app/Http/Controllers/Api/FamilyMembersController.php` |
| 2 | `app/Services/Onboarding/OnboardingService.php` | `app/Services/Onboarding/OnboardingService.php` |
| 3 | `app/Agents/BaseAgent.php` | `app/Agents/BaseAgent.php` |
| 4 | `app/Agents/ProtectionAgent.php` | `app/Agents/ProtectionAgent.php` |
| 5 | `app/Agents/EstateAgent.php` | `app/Agents/EstateAgent.php` |
| 6 | `app/Services/Estate/AssetLiquidityAnalyzer.php` | `app/Services/Estate/AssetLiquidityAnalyzer.php` |
| 7 | `app/Services/Estate/PersonalizedGiftingStrategyService.php` | `app/Services/Estate/PersonalizedGiftingStrategyService.php` |

### Jan3Updates

| # | Local Path | Server Path |
|---|------------|-------------|
| 8 | `database/migrations/2026_01_03_154132_make_risk_profile_columns_nullable.php` | `database/migrations/2026_01_03_154132_make_risk_profile_columns_nullable.php` |
| 9 | `app/Http/Controllers/Api/Estate/IHTController.php` | `app/Http/Controllers/Api/Estate/IHTController.php` |

### Jan5Updates

| # | Local Path | Server Path |
|---|------------|-------------|
| 10 | `app/Http/Middleware/PreviewWriteInterceptor.php` | `app/Http/Middleware/PreviewWriteInterceptor.php` |
| 11 | `app/Http/Controllers/Api/PreviewController.php` | `app/Http/Controllers/Api/PreviewController.php` |

---

## Frontend Build Assets

The following Vue files were modified and are included in the rebuilt assets:

- `resources/js/views/Register.vue` (Jan5 - registration from preview mode fix)
- `resources/js/components/Preview/KeepDataOrFreshModal.vue` (Jan5 - removed invalid Vuex getter)
- `resources/js/components/Onboarding/steps/FamilyInfoStep.vue` (Jan2 - improved error messages)

**Action:** Upload the entire `public/build/` folder to replace the server's `public/build/` folder.

---

## Deployment Steps

### Step 1: Upload PHP Files

Upload these 11 PHP files to the server, maintaining the directory structure:

```
app/Http/Controllers/Api/FamilyMembersController.php
app/Http/Controllers/Api/PreviewController.php
app/Http/Controllers/Api/Estate/IHTController.php
app/Http/Middleware/PreviewWriteInterceptor.php
app/Services/Onboarding/OnboardingService.php
app/Services/Estate/AssetLiquidityAnalyzer.php
app/Services/Estate/PersonalizedGiftingStrategyService.php
app/Agents/BaseAgent.php
app/Agents/ProtectionAgent.php
app/Agents/EstateAgent.php
database/migrations/2026_01_03_154132_make_risk_profile_columns_nullable.php
```

### Step 2: Upload Frontend Build

1. Delete the existing `public/build/` folder on the server
2. Upload the local `public/build/` folder to the server

### Step 3: Run Server Commands

Connect to the server and run:

```bash
cd ~/www/fynla.org/public_html

# Run the new migration (Jan3 - risk profile columns)
php artisan migrate --force

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

---

## Bug Fixes Included

### Jan2Updates
1. **Spouse Creation Fix** - SpousePermission using correct column names
2. **Cache Tagging Fix** - File cache compatibility for Protection/Estate agents
3. **Asset Liquidity Fix** - Added 'chattel' case to match statement
4. **Gifting Strategy Fix** - Correct array keys (asset_name, current_value)

### Jan3Updates
1. **Risk Profile Fix** - Made optional columns nullable
2. **Will Status Fix** - Added will_info to IHT API response

### Jan5Updates
1. **Registration from Preview** - PreviewWriteInterceptor excludes auth endpoints
2. **Persona Caching** - Register.vue caches persona before auth state changes
3. **Vuex Getter Fix** - KeepDataOrFreshModal uses correct getter
4. **Auth Token Fix** - Removed exitPreview call that cleared token

---

## Testing After Deployment

1. Go to https://fynla.org
2. Click "Try the Demo"
3. Select any persona (e.g., Emily & James Carter)
4. Click "Register to Save Your Data"
5. Complete registration with valid email
6. Verify email with code
7. Select "Keep data" in modal
8. Click Continue - should redirect to dashboard
9. Click on Net Worth card - should NOT redirect to login
10. Verify all persona data is displayed

---

## File Checksums (for verification)

Run locally to generate checksums:
```bash
md5 app/Http/Controllers/Api/FamilyMembersController.php
md5 app/Http/Middleware/PreviewWriteInterceptor.php
md5 app/Agents/BaseAgent.php
```

Compare with server after upload to verify files transferred correctly.
