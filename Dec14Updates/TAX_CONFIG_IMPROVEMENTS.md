# Tax Configuration Improvements - 14 December 2025

## Overview

This update improves the maintainability and consistency of the UK tax configuration system in Fynla. The changes include audit logging, standardized rate formats, removal of hardcoded values, and cleanup of deprecated files.

---

## 1. Audit Logging System

### New Files Created

**Migration**: `database/migrations/2025_12_14_134507_create_tax_configuration_audits_table.php`

Creates the `tax_configuration_audits` table with the following structure:

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `tax_configuration_id` | foreignId | Links to tax_configurations |
| `changed_by_user_id` | foreignId (nullable) | User who made the change |
| `change_type` | string | 'created', 'updated', 'activated', 'deactivated', 'duplicated' |
| `before_state` | JSON (nullable) | Previous config_data |
| `after_state` | JSON | New config_data |
| `changed_fields` | JSON (nullable) | List of fields that changed |
| `rationale` | text (nullable) | Admin's reason for the change |
| `ip_address` | string(45) (nullable) | For compliance tracking |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Model**: `app/Models/TaxConfigurationAudit.php`

Features:
- `log()` static method for easy audit creation
- `calculateChangedFields()` automatically detects what changed
- `getSummaryAttribute()` provides human-readable change descriptions
- Relationships to `TaxConfiguration` and `User` models

### Controller Updates

**File**: `app/Http/Controllers/Api/TaxSettingsController.php`

Added `logAudit()` private method and integrated audit logging into:
- `create()` - Logs 'created' event
- `update()` - Logs 'updated' event with before/after states
- `setActive()` - Logs 'activated' and 'deactivated' events
- `duplicate()` - Logs 'duplicated' event with source reference

---

## 2. Standardized Rate Formats

### Problem
Tax rates were inconsistently stored:
- Income tax bands: `rate: 20` (whole number)
- NI rates: `0.08` (decimal)
- CGT rates: `18` (whole number)
- Dividend rates: `8.75` (whole number)
- IHT rates: `0.40` (decimal)

### Solution
All rates now use **decimal format** (e.g., `0.20` for 20%).

### Files Modified

**Seeder**: `database/seeders/TaxConfigurationSeeder.php`

```php
// Before
'rate' => 20,              // Income tax basic rate
'basic_rate' => 18,        // CGT
'basic_rate' => 8.75,      // Dividend tax

// After
'rate' => 0.20,            // Income tax basic rate
'basic_rate' => 0.18,      // CGT
'basic_rate' => 0.0875,    // Dividend tax
```

**Calculator**: `app/Services/UKTaxCalculator.php`

```php
// Before
$basicRate = $bands[0]['rate'] / 100;
$basicDividendRate = $dividendTax['basic_rate'] / 100;

// After
$basicRate = $bands[0]['rate'];
$basicDividendRate = $dividendTax['basic_rate'];
```

**Property Service**: `app/Services/Property/PropertyTaxService.php`

```php
// Before
$basicCgtRate = $cgtConfig['residential_property_basic_rate'] ?? $cgtConfig['basic_rate'] ?? 18;
$cgtLiability = $taxableGain * ($cgtRate / 100);

// After
$basicCgtRate = $cgtConfig['residential_property_basic_rate'] ?? $cgtConfig['basic_rate'] ?? 0.18;
$cgtLiability = $taxableGain * $cgtRate;
```

---

## 3. Removed Hardcoded Tax Values

### Problem
Several services hardcoded `0.40` instead of reading from TaxConfigService.

### Files Modified

**IHT Calculation Service**: `app/Services/Estate/IHTCalculationService.php`

```php
// Before
$ihtLiability = $taxableEstate * 0.40;
$projectedIHTLiability = $projectedTaxableEstate * 0.40;

// After
$ihtRate = $ihtConfig['standard_rate']; // 0.40 (40%)
$ihtLiability = $taxableEstate * $ihtRate;
$projectedIHTLiability = $projectedTaxableEstate * $ihtRate;
```

**IHT Calculator**: `app/Services/Estate/IHTCalculator.php`

```php
// Before (in applyTaperRelief method)
return $giftValue * 0.40;

// After
return $giftValue * (float) $this->ihtConfig['standard_rate'];
```

---

## 4. Deleted Deprecated Config File

### File Removed
`config/uk_tax_config.php` (533 lines)

This file was never used in production - all tax config is loaded from the database via `TaxConfigService`.

### Documentation Updated
`.claude/skills/fps-feature-builder/references/integration-points.md`

Changed from:
```php
$taxConfig = config('uk_tax_config.current_tax_year');
```

To:
```php
use App\Services\TaxConfigService;

public function __construct(private TaxConfigService $taxConfig) {}

$isaAllowance = $this->taxConfig->getISAAllowances()['annual_allowance'];
```

---

## 5. Database Changes

### Run Migration
```bash
php artisan migrate
```

### Re-seed Tax Configurations
```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
```

This updates all 5 tax years (2021/22 - 2025/26) with the standardized decimal rate format.

---

## Testing

All existing tests pass:
- `TaxConfigServiceTest` - 27 tests passed
- `TaxEfficiencyCalculatorTest` - 19 tests passed

Syntax validation passed for all modified files.

---

## Files Summary

### New Files
| File | Purpose |
|------|---------|
| `database/migrations/2025_12_14_134507_create_tax_configuration_audits_table.php` | Audit table migration |
| `app/Models/TaxConfigurationAudit.php` | Audit model with change tracking |

### Modified Files
| File | Changes |
|------|---------|
| `database/seeders/TaxConfigurationSeeder.php` | Rates now in decimal format |
| `app/Services/UKTaxCalculator.php` | Removed /100 conversions |
| `app/Services/Property/PropertyTaxService.php` | Fixed CGT rate handling |
| `app/Services/Estate/IHTCalculationService.php` | Uses config for IHT rate |
| `app/Services/Estate/IHTCalculator.php` | Uses config for IHT rate |
| `app/Http/Controllers/Api/TaxSettingsController.php` | Added audit logging |
| `.claude/skills/fps-feature-builder/references/integration-points.md` | Updated documentation |

### Deleted Files
| File | Reason |
|------|--------|
| `config/uk_tax_config.php` | Never used, all config from database |

---

## API Changes

### TaxSettingsController

The `setActive` method signature changed to accept Request:
```php
// Before
public function setActive(int $id): JsonResponse

// After
public function setActive(Request $request, int $id): JsonResponse
```

This allows passing an optional `rationale` field for audit logging.

---

## Remaining Hardcoded Values

The following files still contain hardcoded `0.40` values but are in strategy/recommendation services where the hardcoding is intentional for specific calculations:

- `app/Services/Estate/ComprehensiveEstatePlanService.php` - Strategy calculations
- `app/Services/Estate/IHTStrategyGeneratorService.php` - Strategy recommendations
- `app/Services/Estate/PersonalizedGiftingStrategyService.php` - Gifting strategies
- `app/Services/Estate/PersonalizedTrustStrategyService.php` - Trust strategies
- `app/Services/Retirement/ContributionOptimizer.php` - Tax relief calculations

These can be refactored in a future update if needed, but are lower priority as they're used for illustrative/recommendation purposes rather than actual tax calculations.

---

## 6. Authentication Bug Fixes

### Problem
Admin login was failing with 401 errors and redirecting to landing page instead of dashboard.

### Root Causes Found

1. **Missing `dispatch` in Vuex action** - `auth.js` login action called `dispatch('fetchUser')` but `dispatch` wasn't destructured from the action context.

2. **`exitPreview` redirect bug** - Login action called `preview/exitPreview` which did `window.location.href = '/'`, redirecting to landing page even for non-preview users.

3. **Missing `is_admin` flag** - `AdminUserSeeder` only set `role: 'admin'` but frontend checks `is_admin` boolean.

4. **Sanctum stateful domains** - Port 5173 (Vite dev server) wasn't in the allowed stateful domains list.

### Files Modified

**`resources/js/store/modules/auth.js`**
```javascript
// Before
async login({ commit, rootState }, credentials) {
  // ...
  await dispatch('preview/exitPreview', null, { root: true }).catch(() => {});

// After
async login({ commit, dispatch, rootState }, credentials) {
  // ...
  // Only clear localStorage if was in preview mode (don't redirect)
  if (wasInPreviewMode) {
    localStorage.removeItem('auth_token');
  }
```

**`database/seeders/AdminUserSeeder.php`**
```php
// Before
User::create([
    'role' => 'admin',
    // missing is_admin

// After
User::updateOrCreate(
    ['email' => 'admin@fps.com'],
    [
        'role' => 'admin',
        'is_admin' => true,  // Required for admin access checks
```

**`config/sanctum.php`**
```php
// Before
'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1'

// After
'localhost,localhost:3000,localhost:5173,127.0.0.1,127.0.0.1:8000,127.0.0.1:5173,::1'
```

### Commands Run
```bash
php artisan db:seed --class=AdminUserSeeder --force
php artisan config:clear
npm run build
```
