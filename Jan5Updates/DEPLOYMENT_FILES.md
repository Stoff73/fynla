# Complete Deployment Files - Jan 2-6, 2026

This document lists ALL files that need to be uploaded to production from the Jan2, Jan3, Jan5, and Jan6 updates.

## PHP Backend Files (30 files)

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

### Jan5Updates - Preview/Registration Fixes

| # | Local Path | Server Path |
|---|------------|-------------|
| 10 | `app/Http/Middleware/PreviewWriteInterceptor.php` | `app/Http/Middleware/PreviewWriteInterceptor.php` |
| 11 | `app/Http/Controllers/Api/PreviewController.php` | `app/Http/Controllers/Api/PreviewController.php` |

### Jan5Updates - Code Quality Audit Fixes (NEW)

| # | Local Path | Server Path |
|---|------------|-------------|
| 12 | `app/Traits/FormatsCurrency.php` | `app/Traits/FormatsCurrency.php` |
| 13 | `app/Http/Controllers/Api/EstateController.php` | `app/Http/Controllers/Api/EstateController.php` |
| 14 | `app/Http/Controllers/Api/InvestmentController.php` | `app/Http/Controllers/Api/InvestmentController.php` |
| 15 | `app/Http/Controllers/Api/ProtectionController.php` | `app/Http/Controllers/Api/ProtectionController.php` |
| 16 | `app/Http/Controllers/Api/SavingsController.php` | `app/Http/Controllers/Api/SavingsController.php` |
| 17 | `app/Http/Controllers/Api/Investment/PortfolioStrategyController.php` | `app/Http/Controllers/Api/Investment/PortfolioStrategyController.php` |
| 18 | `app/Services/Investment/PortfolioStrategyService.php` | `app/Services/Investment/PortfolioStrategyService.php` |
| 19 | `app/Services/Protection/ComprehensiveProtectionPlanService.php` | `app/Services/Protection/ComprehensiveProtectionPlanService.php` |
| 20 | `app/Services/Retirement/RetirementStrategyService.php` | `app/Services/Retirement/RetirementStrategyService.php` |
| 21 | `app/Services/UserProfile/UserProfileService.php` | `app/Services/UserProfile/UserProfileService.php` |
| 22 | `app/Services/Documents/ImageResizeService.php` | `app/Services/Documents/ImageResizeService.php` |
| 23 | `app/Services/UserProfile/ProfileCompletenessChecker.php` | `app/Services/UserProfile/ProfileCompletenessChecker.php` |
| 24 | `app/Constants/ValidationLimits.php` | `app/Constants/ValidationLimits.php` |
| 25 | `routes/api.php` | `routes/api.php` |

### Jan6Updates - Email Branding Fix

| # | Local Path | Server Path |
|---|------------|-------------|
| 26 | `app/Mail/SpouseAccountCreated.php` | `app/Mail/SpouseAccountCreated.php` |
| 27 | `app/Mail/SpouseAccountLinked.php` | `app/Mail/SpouseAccountLinked.php` |
| 28 | `resources/views/emails/spouse-account-created.blade.php` | `resources/views/emails/spouse-account-created.blade.php` |
| 29 | `resources/views/emails/spouse-account-linked.blade.php` | `resources/views/emails/spouse-account-linked.blade.php` |

### Jan6Updates - Rate Limit Fix

| # | Local Path | Server Path |
|---|------------|-------------|
| 30 | `app/Providers/RouteServiceProvider.php` | `app/Providers/RouteServiceProvider.php` |

**Note:** The following Vue/JS files are included in the frontend build (no separate upload needed):
- `resources/js/store/modules/preview.js` - Added effectivePersonaData getter

---

## Frontend Build Assets

The following Vue files were modified and are included in the rebuilt assets:

### Jan2-3 Fixes
- `resources/js/views/Register.vue` (Jan5 - registration from preview mode fix)
- `resources/js/components/Preview/KeepDataOrFreshModal.vue` (Jan5 - removed invalid Vuex getter)
- `resources/js/components/Onboarding/steps/FamilyInfoStep.vue` (Jan2 - improved error messages)

### Jan6 - Email Branding Fix (4 files)

| # | File | Changes |
|---|------|---------|
| 29 | `resources/views/emails/spouse-account-created.blade.php` | FPS → Fynla branding |
| 30 | `resources/views/emails/spouse-account-linked.blade.php` | FPS → Fynla branding |
| 31 | `app/Mail/SpouseAccountCreated.php` | Subject: FPS → Fynla |
| 32 | `app/Mail/SpouseAccountLinked.php` | Subject: FPS → Fynla |

### Jan5 - Mobile Responsive Fixes (28 Vue files)

| # | File | Changes |
|---|------|---------|
| 1 | `resources/js/views/Investment/AccountDetailView.vue` | Header layout, title typography |
| 2 | `resources/js/components/NetWorth/Property/PropertyDetailInline.vue` | Header, typography, all definition lists |
| 3 | `resources/js/components/Protection/PolicyDetail.vue` | Header layout, typography, metrics grids |
| 4 | `resources/js/views/Retirement/PensionDetail.vue` | Header, typography, grids, definition lists |
| 5 | `resources/js/components/NetWorth/BusinessInterestDetailInline.vue` | Header layout, title typography |
| 6 | `resources/js/components/NetWorth/InvestmentDetailInline.vue` | Header overflow, button layout, metrics grid |
| 7 | `resources/js/views/NetWorth/NetWorthDashboard.vue` | **CRITICAL**: CSS Grid overflow fix (min-width: 0) |
| 8 | `resources/js/components/NetWorth/InvestmentList.vue` | Container overflow handling |
| 9 | `resources/js/views/Investment/AccountSummaryPanel.vue` | Grid breakpoints |
| 10 | `resources/js/components/Protection/CurrentSituation.vue` | Coverage grid (5-col to responsive) |
| 11 | `resources/js/views/Retirement/RetirementReadiness.vue` | Pension cards grid, sm: breakpoints |
| 12 | `resources/js/components/Retirement/AnnualAllowanceTracker.vue` | Progress sections, summary rows |
| 13 | `resources/js/components/Protection/PolicyFormModal.vue` | Modal edge padding |
| 14 | `resources/js/components/Protection/PolicyCard.vue` | Card grid responsive |
| 15 | `resources/js/views/Savings/SavingsAccountDetailInline.vue` | Header layout, title typography |
| 16 | `resources/js/views/Savings/SavingsAccountDetail.vue` | Header layout, title typography |
| 17 | `resources/js/components/NetWorth/ChattelDetailInline.vue` | Header layout, title typography |
| 18 | `resources/js/components/NetWorth/Property/PropertyDetail.vue` | Header layout, title typography |
| 19 | `resources/js/components/NetWorth/PensionDetailInline.vue` | Header layout, title typography |
| 20 | `resources/js/views/Protection/ProtectionDashboard.vue` | Header typography (text-xl sm:text-2xl lg:text-3xl) |
| 21 | `resources/js/components/UserProfile/ExpenditureForm.vue` | All grids md: → sm: (14 grids fixed) |
| 22 | `resources/js/components/UserProfile/FamilyMemberFormModal.vue` | Name fields, DOB/Gender, NI/Income grids |
| 23 | `resources/js/components/UserProfile/HealthInformation.vue` | Display and edit mode grids |
| 24 | `resources/js/components/UserProfile/DomicileInformation.vue` | Header layout, button responsive |
| 25 | `resources/js/components/Estate/GiftingStrategy.vue` | Header, grids, definition lists |
| 26 | `resources/js/components/Estate/TrustPlanning.vue` | Header, grids, metrics cards |
| 27 | `resources/js/components/Estate/LifePolicyStrategy.vue` | Header, grids, policy cards |
| 28 | `resources/js/components/UserProfile/PersonalInformation.vue` | Grid breakpoints (sm:) |

**Action:** Upload the entire `public/build/` folder to replace the server's `public/build/` folder.

---

## Deployment Steps

### Step 1: Upload PHP Files

Upload these 30 PHP/Blade files to the server, maintaining the directory structure:

```
# Jan2-3 files
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

# Jan5 Code Quality Audit files (NEW)
app/Traits/FormatsCurrency.php
app/Constants/ValidationLimits.php
app/Http/Controllers/Api/EstateController.php
app/Http/Controllers/Api/InvestmentController.php
app/Http/Controllers/Api/ProtectionController.php
app/Http/Controllers/Api/SavingsController.php
app/Http/Controllers/Api/Investment/PortfolioStrategyController.php
app/Services/Investment/PortfolioStrategyService.php
app/Services/Protection/ComprehensiveProtectionPlanService.php
app/Services/Retirement/RetirementStrategyService.php
app/Services/UserProfile/UserProfileService.php
app/Services/Documents/ImageResizeService.php
app/Services/UserProfile/ProfileCompletenessChecker.php
routes/api.php

# Jan6 Email Branding
app/Mail/SpouseAccountCreated.php
app/Mail/SpouseAccountLinked.php
resources/views/emails/spouse-account-created.blade.php
resources/views/emails/spouse-account-linked.blade.php

# Jan6 Rate Limit Fix
app/Providers/RouteServiceProvider.php
```

### Step 2: Create New Directory (if needed)

The `app/Traits/` directory may not exist on the server. Create it:

```bash
mkdir -p ~/www/fynla.org/public_html/app/Traits
```

### Step 3: Upload Frontend Build

1. Delete the existing `public/build/` folder on the server
2. Upload the local `public/build/` folder to the server

### Step 4: Run Server Commands

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

# Dump autoload for new Trait class
composer dump-autoload
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

### Jan5Updates - Preview/Registration
1. **Registration from Preview** - PreviewWriteInterceptor excludes auth endpoints
2. **Persona Caching** - Register.vue caches persona before auth state changes
3. **Vuex Getter Fix** - KeepDataOrFreshModal uses correct getter
4. **Auth Token Fix** - Removed exitPreview call that cleared token

### Jan5Updates - Code Quality Audit
1. **CRITICAL: Password Security** - Removed password logging and API response exposure in FamilyMembersController
2. **Request Data Logging** - Removed unsafe `$request->all()` logging from EstateController, InvestmentController
3. **Centralized Currency Formatting** - New `FormatsCurrency` trait replaces duplicate implementations
4. **N+1 Query Fix** - Use `$user->spouse` relationship instead of `User::find()` in OnboardingService, UserProfileService
5. **Debug Logging Removed** - Removed verbose debug logs from ImageResizeService
6. **Null Safety Fix** - Fixed operator precedence in ProfileCompletenessChecker
7. **Code Style Fixes** - Pint fixes for 8 files

### Jan6Updates - Email Branding Fix
1. **Email Branding** - Updated spouse account emails from "FPS" to "Fynla" branding (titles, headers, body text, signatures, footers)

### Jan6Updates - Rate Limit Fix
1. **Rate Limit Increased** - Increased API rate limit from 60 to 300 requests/minute in production (dashboard makes ~15 API calls per page load)

### Jan5Updates - Mobile Responsive Fixes
1. **Target**: 375px minimum width (iPhone SE)
2. **CRITICAL: CSS Grid Overflow Fix** - NetWorthDashboard.vue `.main-content` needs `min-width: 0` to prevent grid items overflowing container
3. **Header Layouts** - All detail views now stack title/buttons on mobile
4. **Grid Breakpoints** - Added `sm:` intermediate breakpoints to metric grids
5. **Definition Lists** - Label/value pairs stack vertically on mobile
6. **Modal Edge Padding** - Added `mx-4 sm:mx-0` for safe area on mobile
7. **Typography** - Responsive font sizes (`text-xl sm:text-2xl lg:text-3xl`)
8. **28 Vue components** modified across Investment, Protection, Retirement, Savings, NetWorth, Estate, UserProfile modules

---

## Testing After Deployment

### Registration Flow Test
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

### Security Fix Verification
1. Create a spouse account through Family Members
2. Check server logs - should NOT contain temporary password
3. Check API response - should NOT contain `temporary_password` field
4. Should contain `email_sent` boolean instead

### Service Tests
1. Navigate to Retirement > Strategies tab - verify it loads
2. Navigate to Investment > Strategy tab - verify recommendations display
3. Navigate to Protection - verify adequacy analysis works

### Mobile Responsive Tests
Test on iPhone SE (375px) or browser DevTools mobile view:
1. **Investment Detail** - Click any investment account, verify header stacks properly
2. **Protection Detail** - Click any policy, verify metrics grid is readable
3. **Protection Dashboard** - Verify header typography scales properly
4. **Pension Detail** - Click any pension, verify definition lists stack on mobile
5. **Property Detail** - Click any property, verify all sections display correctly
6. **PolicyFormModal** - Add new policy, verify modal has edge padding
7. **UserProfile > Expenditure** - Verify all form grids display in 2 columns at 640px+
8. **UserProfile > Family Members** - Add family member, verify form grids stack properly
9. **UserProfile > Health** - Verify health/lifestyle grids are responsive
10. **UserProfile > Domicile** - Verify header stacks title and button on mobile
11. **Estate > Gifting Strategy** - Verify recommendation cards and grids are responsive
12. **Estate > Trust Planning** - Verify trust list and metrics are readable on mobile
13. No horizontal scroll should appear at 375px width

---

## File Checksums (for verification)

Run locally to generate checksums:
```bash
md5 app/Http/Controllers/Api/FamilyMembersController.php
md5 app/Http/Middleware/PreviewWriteInterceptor.php
md5 app/Agents/BaseAgent.php
md5 app/Traits/FormatsCurrency.php
```

Compare with server after upload to verify files transferred correctly.

---

## Quick Copy-Paste File List

For easy SCP/SFTP upload, here are all 30 PHP/Blade files:

```
app/Traits/FormatsCurrency.php
app/Constants/ValidationLimits.php
app/Http/Middleware/PreviewWriteInterceptor.php
app/Http/Controllers/Api/FamilyMembersController.php
app/Http/Controllers/Api/PreviewController.php
app/Http/Controllers/Api/EstateController.php
app/Http/Controllers/Api/InvestmentController.php
app/Http/Controllers/Api/ProtectionController.php
app/Http/Controllers/Api/SavingsController.php
app/Http/Controllers/Api/Estate/IHTController.php
app/Http/Controllers/Api/Investment/PortfolioStrategyController.php
app/Agents/BaseAgent.php
app/Agents/ProtectionAgent.php
app/Agents/EstateAgent.php
app/Services/Onboarding/OnboardingService.php
app/Services/Estate/AssetLiquidityAnalyzer.php
app/Services/Estate/PersonalizedGiftingStrategyService.php
app/Services/Investment/PortfolioStrategyService.php
app/Services/Protection/ComprehensiveProtectionPlanService.php
app/Services/Retirement/RetirementStrategyService.php
app/Services/UserProfile/UserProfileService.php
app/Services/UserProfile/ProfileCompletenessChecker.php
app/Services/Documents/ImageResizeService.php
database/migrations/2026_01_03_154132_make_risk_profile_columns_nullable.php
routes/api.php

# Jan6 Email Branding
app/Mail/SpouseAccountCreated.php
app/Mail/SpouseAccountLinked.php
resources/views/emails/spouse-account-created.blade.php
resources/views/emails/spouse-account-linked.blade.php

# Jan6 Rate Limit Fix
app/Providers/RouteServiceProvider.php
```
