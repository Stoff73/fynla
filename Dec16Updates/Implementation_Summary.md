# Dec 16 Implementation Summary

## Overview
Implemented optional fields across the application, refactored user registration to use separate name fields, and added a comprehensive 5-level risk preference system.

---

## Phase 1: Registration Refactor (COMPLETED)

### Database Migration
**File**: `database/migrations/2025_12_16_103303_refactor_users_name_fields.php`
- Split single `name` column into `first_name`, `middle_name`, `surname`
- Intelligent parsing of existing names (handles 1, 2, 3+ word names)
- Dropped legacy `name` column after data migration

### User Model Update
**File**: `app/Models/User.php`
- Added backwards-compatible `getNameAttribute()` accessor
- Accessor checks for new fields first, falls back to legacy `name` column
- Returns combined name: "First Middle Surname"

### Registration Validation
**File**: `app/Http/Requests/RegisterRequest.php`
- `first_name`: required, string, max:255
- `middle_name`: nullable, string, max:255
- `surname`: required, string, max:255

### Auth Controller
**File**: `app/Http/Controllers/Api/AuthController.php`
- Updated register method to use new name fields

### Frontend Registration
**File**: `resources/js/views/Register.vue`
- Three separate input fields: First Name, Middle Name, Surname
- First Name and Surname required, Middle Name optional

---

## Phase 2: Backend Form Request Updates (COMPLETED)

Changed `required` to `nullable` for all non-registration fields in:

| File | Module |
|------|--------|
| StoreLifePolicyRequest.php | Protection |
| UpdateLifePolicyRequest.php | Protection |
| StoreDisabilityPolicyRequest.php | Protection |
| UpdateDisabilityPolicyRequest.php | Protection |
| StoreSicknessIllnessPolicyRequest.php | Protection |
| UpdateSicknessIllnessPolicyRequest.php | Protection |
| StoreSavingsAccountRequest.php | Savings |
| UpdateSavingsAccountRequest.php | Savings |
| StoreSavingsGoalRequest.php | Savings |
| UpdateSavingsGoalRequest.php | Savings |
| StoreDCPensionRequest.php | Retirement |
| UpdateDCPensionRequest.php | Retirement |
| StoreDBPensionRequest.php | Retirement |
| UpdateDBPensionRequest.php | Retirement |
| StorePropertyRequest.php | Net Worth |
| UpdatePropertyRequest.php | Net Worth |
| StoreMortgageRequest.php | Net Worth |
| UpdateMortgageRequest.php | Net Worth |
| StoreFamilyMemberRequest.php | Family |
| UpdateFamilyMemberRequest.php | Family |
| StoreInvestmentAccountRequest.php | Investment |
| UpdateInvestmentAccountRequest.php | Investment |

---

## Phase 3: Frontend Required Attribute Removal (COMPLETED)

Removed HTML5 `required` attributes from all form inputs in:

| File | Component |
|------|-----------|
| PolicyFormModal.vue | Protection policies |
| SaveAccountModal.vue | Savings accounts |
| SavingsGoalForm.vue | Savings goals |
| DCPensionForm.vue | DC pensions |
| DBPensionForm.vue | DB pensions |
| StatePensionForm.vue | State pension |
| AccountForm.vue | Investment accounts |
| HoldingForm.vue | Investment holdings |
| PropertyForm.vue | Properties |

---

## Phase 4: Division/Math Operation Fixes (COMPLETED)

Added safe division checks to prevent division by zero errors:

| File | Fix |
|------|-----|
| EmergencyFundCalculator.php | `$targetMonths > 0` check |
| OnboardingService.php | `$years <= 0` early return, `$denominator == 0` check |
| InvestmentSavingsPlanService.php | `$totalValue > 0` ternary |
| CashFlowProjector.php | `$totalYears > 0` check |
| AssetLocationOptimizer.php | `$totalValue > 0` check |
| AssetAllocationOptimizer.php | `$total > 0` and `$totalPortfolioValue > 0` checks |
| PerformanceAttributionAnalyzer.php | Multiple `$totalValue > 0` checks |
| EfficientFrontierCalculator.php | `$n > 0` check |
| AlphaBetaCalculator.php | `count($values) > 0` check |

---

## Phase 5: Database Nullable Columns (COMPLETED)

**File**: `database/migrations/2025_12_16_103444_make_all_data_columns_nullable.php`

Made columns nullable in tables:
- life_insurance_policies
- critical_illness_policies
- income_protection_policies
- disability_policies
- sickness_illness_policies
- savings_accounts
- savings_goals
- dc_pensions
- db_pensions
- investment_accounts
- investment_holdings
- properties
- mortgages
- liabilities
- family_members

---

## Seeder Updates (COMPLETED)

Updated to use new name fields (`first_name`, `middle_name`, `surname`):

| File | Changes |
|------|---------|
| PreviewUserSeeder.php | Lines 133-136, 201-204 |
| TestUsersSeeder.php | Lines 17-19, 41-43, 69-71 |
| AdminUserSeeder.php | Lines 20-21 |
| DemoUserSeeder.php | Lines 18-19 |

---

## Required Seeders

After migration, run the main seeder (now includes all reference data):

```bash
php artisan db:seed
```

Or run individually:

```bash
# Reference data (REQUIRED for app to function)
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=TaxProductReferenceSeeder --force
php artisan db:seed --class=UKLifeExpectancySeeder --force
php artisan db:seed --class=ActuarialLifeTablesSeeder --force

# User data (development only)
php artisan db:seed --class=PreviewUserSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
```

**Full seeding documentation**: See `/seedMigration.md` in the project root.

### DatabaseSeeder Updates

**File**: `database/seeders/DatabaseSeeder.php`

Now orchestrates all seeders in correct order:
- Phase 1: Reference data (TaxConfiguration, TaxProductReference, UKLifeExpectancy, ActuarialLifeTables)
- Phase 2: User data (Household, TestUsers, Admin, PreviewUsers) - only in dev/staging

### Common Seeding Issues

| Issue | Fix |
|-------|-----|
| Tax Status tab empty | `php artisan db:seed --class=TaxProductReferenceSeeder --force` |
| Tax calculations failing | `php artisan db:seed --class=TaxConfigurationSeeder --force` |
| Preview personas broken | `php artisan db:seed --class=PreviewUserSeeder --force` |

---

## Bug Fixes (COMPLETED)

### Router Guard Fix for Preview Mode
**File**: `resources/js/router/index.js`

**Issue**: Clicking "Register to Save Your Data" button in preview mode banner did nothing.

**Cause**: Router guard was blocking authenticated users from accessing guest routes. Preview mode sets `isAuthenticated=true`, so preview users were being redirected away from the register page.

**Fix**: Added `&& !isPreviewMode` condition to allow preview users to access the register page:
```javascript
} else if (to.meta.requiresGuest && isAuthenticated && !isPreviewMode) {
  // Redirect to dashboard if already authenticated (but allow preview users to register)
  next({ name: 'Dashboard' });
}
```

### Registration Page Logo
**File**: `resources/js/views/Register.vue`

- Replaced "Fynla" text heading with logo image
- Logo imported from `@/assets/images/logo.png`
- Styled at h-48 (192px height) with reduced spacing

### Footer Updates
**File**: `resources/js/components/Footer.vue`

- Updated version from v0.2.18 to v0.2.20
- Changed link from csjones.co to csjones.co/fynla
- Removed "Boma Build" branding, replaced with "csjones.co/fynla"

### Landing Page Updates
**File**: `resources/js/views/Public/LandingPage.vue`

- Changed heading from "What's in the Boma Build" to "What's in the Demo"

---

## Phase 6: Risk Module Integration (COMPLETED)

### Overview
Integrated a comprehensive 5-level risk preference system allowing users to:
- Set a main risk level via self-selection (Low → High)
- Override risk per product within ±1 level of their main preference
- See risk indicators on dashboard cards and forms
- View a dedicated Risk Profile page with educational content

### Risk Levels

| Level | DB Value | Display Name | Equity/Bond/Cash | Expected Return |
|-------|----------|--------------|------------------|-----------------|
| 1 | `low` | Low | 10/70/20 | 1-3% |
| 2 | `lower_medium` | Lower-Medium | 30/55/10 | 2-4.5% |
| 3 | `medium` | Medium | 50/40/5 | 3.5-6.5% |
| 4 | `upper_medium` | Upper-Medium | 75/20/0 | 5-8.5% |
| 5 | `high` | High | 90/5/0 | 6-12% |

### Database Migrations

| File | Purpose |
|------|---------|
| `2025_12_16_152549_add_risk_level_to_risk_profiles_table.php` | Added `risk_level`, `risk_assessed_at`, `is_self_assessed` to risk_profiles |
| `2025_12_16_152550_add_risk_preference_to_investment_accounts_table.php` | Added `risk_preference`, `has_custom_risk` to investment_accounts |
| `2025_12_16_152552_add_risk_preference_to_dc_pensions_table.php` | Added `risk_preference`, `has_custom_risk` to dc_pensions |

### Backend Service

**File**: `app/Services/Risk/RiskPreferenceService.php`

Key methods:
- `getAvailableRiskLevels()` - Returns all 5 risk levels with configurations
- `setMainRiskLevel(userId, riskLevel)` - Sets user's main risk preference
- `getMainRiskLevel(userId)` - Gets current risk level
- `getAllowedProductRiskLevels(userId)` - Returns levels within ±1 of main
- `validateProductRiskLevel(userId, riskLevel)` - Validates product-level override
- `getRiskLevelConfig(riskLevel)` - Returns asset allocation and return expectations

### API Controller

**File**: `app/Http/Controllers/Api/RiskPreferenceController.php`

### API Routes (under `/api/investment/risk/`)

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/levels` | Get all available risk levels with descriptions |
| GET | `/profile` | Get user's current risk profile |
| POST | `/profile` | Set or update user's main risk level |
| GET | `/allowed-levels` | Get allowed levels for product override (±1) |
| POST | `/validate-product-level` | Validate a product risk level |
| GET | `/config/{level}` | Get configuration for a specific risk level |

### Model Updates

| Model | Changes |
|-------|---------|
| `RiskProfile.php` | Added `risk_level`, `risk_assessed_at`, `is_self_assessed` to fillable/casts |
| `InvestmentAccount.php` | Added `risk_preference`, `has_custom_risk` to fillable/casts |
| `DCPension.php` | Added `risk_preference`, `has_custom_risk` to fillable/casts |

### Frontend Components Created

| File | Purpose |
|------|---------|
| `components/Shared/RiskLevelSelector.vue` | Interactive 5-level selector with colors, info panels |
| `components/Shared/RiskBadge.vue` | Compact badge for cards (size variants, custom-risk indicator) |
| `components/Risk/RiskFactorsPanel.vue` | 4-quadrant investment risk factors display |
| `components/Risk/CapacityForLossSection.vue` | Interactive spectrum visualization |
| `components/Risk/TimeHorizonSection.vue` | Time horizon selector with risk/horizon matrix |
| `components/Risk/InvestmentTypesAccordion.vue` | Expandable asset class explanations |
| `views/Risk/RiskProfilePage.vue` | Complete risk profile page with educational content |
| `services/riskService.js` | API wrapper for all risk endpoints |

### Frontend Components Modified

| File | Changes |
|------|---------|
| `components/Investment/AccountForm.vue` | Added RiskLevelSelector, redirect to risk-profile if no profile set |
| `components/Retirement/DCPensionForm.vue` | Added RiskLevelSelector, redirect to risk-profile if no profile set |
| `components/NetWorth/InvestmentList.vue` | Added RiskBadge in card header |
| `components/NetWorth/PensionList.vue` | Added RiskBadge for DC pensions (top-right corner) |
| `components/Shared/RiskLevelSelector.vue` | Uses inline styles for colors (fixes Tailwind purging issue) |
| `router/index.js` | Added `/risk-profile` route |
| `store/modules/investment.js` | Added risk profile getters and actions |

### Frontend Components Deleted (Unused)

| File | Reason |
|------|--------|
| `components/Investment/AccountCard.vue` | Not used - InvestmentList renders cards inline |
| `components/Investment/Accounts.vue` | Not used - InvestmentList is the actual component |
| `components/Retirement/PensionCard.vue` | Not used - PensionList renders cards inline |
| `views/Retirement/PensionInventory.vue` | Not used - not in router |

### Risk Level Color Scheme

| Level | Tailwind Classes |
|-------|-----------------|
| Low | `bg-green-100 text-green-800` |
| Lower-Medium | `bg-teal-100 text-teal-800` |
| Medium | `bg-blue-100 text-blue-800` |
| Upper-Medium | `bg-amber-100 text-amber-800` |
| High | `bg-red-100 text-red-800` |

### Educational Content Sections (RiskProfilePage)

1. **Introduction** - Why understanding risk matters
2. **Risk Factors Panel** - 4 key investment risks (value falls, capacity, inflation, liquidity)
3. **Capacity for Loss** - Interactive spectrum with interpretation
4. **Time Horizon** - How investment timeline affects risk tolerance
5. **Risk Level Selection** - Main risk level selector
6. **Investment Types** - Expandable accordion explaining asset classes
7. **Custom Products** - List of products with custom risk settings

### Product-Level Risk Override

- Users can set per-product risk levels within ±1 of their main level
- `has_custom_risk` flag indicates when product differs from main profile
- Custom risk shown with amber ring on badges

---

## Testing Verification

All API endpoints tested and working:
- `/api/preview/login/{persona}` - Authentication
- `/api/dashboard` - Dashboard data
- `/api/net-worth/overview` - Net worth summary
- `/api/net-worth/assets-summary-detailed` - Detailed assets
- `/api/properties` - Property data
- `/api/savings` - Savings accounts
- `/api/retirement` - Pension data (now includes `risk_preference`, `has_custom_risk`)
- `/api/investment` - Investment accounts (now includes `risk_preference`, `has_custom_risk`)
- `/api/tax-info/investment/{type}` - Tax status information
- `/api/investment/risk/levels` - Risk levels configuration
- `/api/investment/risk/profile` - User risk profile
- `/api/investment/risk/allowed-levels` - Allowed product risk levels

---

## Files Modified

### Backend (PHP)
- app/Models/User.php
- app/Models/Investment/RiskProfile.php (added risk_level fields)
- app/Models/Investment/InvestmentAccount.php (added risk_preference fields)
- app/Models/DCPension.php (added risk_preference fields)
- app/Http/Controllers/Api/AuthController.php
- app/Http/Controllers/Api/RiskPreferenceController.php (new)
- app/Http/Requests/RegisterRequest.php
- app/Services/Risk/RiskPreferenceService.php (new)
- app/Services/Investment/AssetAllocationOptimizer.php (updated for 5-level system)
- routes/api.php (added risk routes)
- 22 Form Request files (see Phase 2)
- 9 Service files (see Phase 4)
- database/migrations/2025_12_16_103303_refactor_users_name_fields.php
- database/migrations/2025_12_16_103444_make_all_data_columns_nullable.php
- database/migrations/2025_12_16_152549_add_risk_level_to_risk_profiles_table.php (new)
- database/migrations/2025_12_16_152550_add_risk_preference_to_investment_accounts_table.php (new)
- database/migrations/2025_12_16_152552_add_risk_preference_to_dc_pensions_table.php (new)
- database/seeders/PreviewUserSeeder.php
- database/seeders/TestUsersSeeder.php
- database/seeders/AdminUserSeeder.php
- database/seeders/DemoUserSeeder.php

### Frontend (Vue)
- resources/js/views/Register.vue
- resources/js/views/Risk/RiskProfilePage.vue (new)
- resources/js/router/index.js (preview mode fix + risk-profile route)
- resources/js/components/Footer.vue (version and link update)
- resources/js/views/Public/LandingPage.vue (Boma Build removal)
- resources/js/assets/images/logo.png (new logo asset)
- resources/js/components/Shared/RiskLevelSelector.vue (new - uses inline styles)
- resources/js/components/Shared/RiskBadge.vue (new)
- resources/js/components/Risk/RiskFactorsPanel.vue (new)
- resources/js/components/Risk/CapacityForLossSection.vue (new)
- resources/js/components/Risk/TimeHorizonSection.vue (new)
- resources/js/components/Risk/InvestmentTypesAccordion.vue (new)
- resources/js/components/Investment/AccountForm.vue (added RiskLevelSelector + redirect)
- resources/js/components/Retirement/DCPensionForm.vue (added RiskLevelSelector + redirect)
- resources/js/components/NetWorth/InvestmentList.vue (added RiskBadge)
- resources/js/components/NetWorth/PensionList.vue (added RiskBadge, top-right positioning)
- resources/js/services/riskService.js (new)
- resources/js/store/modules/investment.js (added risk getters/actions)
- tailwind.config.js (added safelist for risk level colors)
- 9 form components (see Phase 3)

### Frontend Files Deleted
- resources/js/components/Investment/AccountCard.vue (unused)
- resources/js/components/Investment/Accounts.vue (unused)
- resources/js/components/Retirement/PensionCard.vue (unused)
- resources/js/views/Retirement/PensionInventory.vue (unused)

### New Documentation
- /seedMigration.md (comprehensive seeding guide)

---

## Deployment Notes

1. Run migrations: `php artisan migrate --force`
   - Includes 3 new risk module migrations
2. Run seeders (see Required Seeders section)
3. Clear caches: `php artisan config:clear && php artisan cache:clear`
4. Rebuild frontend: `npm run build`

---

## New Routes

### Risk Profile Page
- **URL**: `/risk-profile`
- **Component**: `views/Risk/RiskProfilePage.vue`
- **Purpose**: Educational content and main risk level selection

---

## Usage Notes

### Setting Risk Profile
1. Navigate to `/risk-profile` or click "Set Risk Profile" link in investment/pension forms
2. Review educational content about investment risks
3. Select main risk level (Low → High)
4. Risk level is saved to user's risk profile

### Per-Product Risk Override
1. Open investment account or DC pension form
2. If main risk profile is set, RiskLevelSelector appears
3. Select risk level within ±1 of main profile
4. If different from main, `has_custom_risk` flag is set

### Risk Badges on Cards
- Appear on InvestmentList and PensionList cards when risk_preference is set
- Color-coded by risk level (green → teal → blue → amber → red)
- Amber ring indicates custom risk (different from main profile)
- DC Pension badges positioned in top-right corner of card

---

## New Documentation Files

| File | Purpose |
|------|---------|
| `/seedMigration.md` | Comprehensive database seeding guide for all Claude instances |
| `/CLAUDE.md` (updated) | Added quick reference for seeding commands |

---

## Preview Persona Risk Data

All preview personas now include risk profile data:

| Persona | Main Risk | Investment Risks | DC Pension Risks |
|---------|-----------|------------------|------------------|
| young_family | medium | ISA: medium | Workplace: medium |
| peak_earners | David: upper_medium, Sarah: medium | ISA: high/medium, GIA: upper_medium, VCT: high | SIPP: upper_medium |
| widow | lower_medium | ISA: lower_medium, Bond: lower_medium, GIA: medium | N/A |
| entrepreneur | high | ISA: high, GIA: high | SIPP: upper_medium |
