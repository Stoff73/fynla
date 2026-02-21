# Retirement Module Fixes

**Date:** 21 February 2026
**Branch:** `retirement-fixes`
**Scope:** All known issues from Retirement.md Section 17 (17.1 through 17.7)

---

## Issues Addressed

### From Section 17 (Known Issues & Vulnerabilities)

| # | Priority | Issue | Fix |
|---|----------|-------|-----|
| 17.1 | HIGH | `ContributionOptimizer` has hardcoded tax bands (£50,270, £125,140, 20/40/45%) | Replace with `TaxConfigService` calls; service already injected |
| 17.2 | MEDIUM | `AnnualAllowanceChecker` carry forward always returns 1 year's full allowance | Implement proper 3-year lookback with user-entered prior-year data |
| 17.3 | MEDIUM | `AnnualAllowanceChecker.checkMPAA()` always returns "not triggered" | Add `has_flexibly_accessed` and `flexible_access_date` fields to `dc_pensions`; implement real check |
| 17.4 | MEDIUM | `PensionProjector.projectDBPension()` ignores revaluation and inflation protection | Apply compound revaluation based on `inflation_protection` type over years to retirement |
| 17.5 | MEDIUM | Default retirement age inconsistent across services (67 vs 68) | Centralise to a single shared constant; standardise on 67 (current UK state pension age) |
| 17.6 | HIGH | State pension hardcoded at £11,502 (2024/25); should be £11,973 (2025/26) | Source from `TaxConfigService::getPensionAllowances()` which already has the correct value |
| 17.7 | LOW | `RetirementProfile.risk_tolerance` deprecated but still in schema and `$fillable` | Remove from `$fillable`; add migration to drop column |

---

## Changes By File

### New Files

| File | Purpose |
|------|---------|
| `database/migrations/xxxx_add_mpaa_fields_to_dc_pensions.php` | Adds `has_flexibly_accessed` and `flexible_access_date` to `dc_pensions` |
| `database/migrations/xxxx_add_carry_forward_fields_to_retirement_profiles.php` | Adds `prior_year_unused_allowance` JSON field for manual carry forward entry |
| `database/migrations/xxxx_remove_risk_tolerance_from_retirement_profiles.php` | Drops deprecated `risk_tolerance` column |

### Modified Files

| File | Change |
|------|--------|
| `app/Services/Retirement/ContributionOptimizer.php` | Replace 5 hardcoded tax values with `TaxConfigService` calls |
| `app/Services/Retirement/AnnualAllowanceChecker.php` | Implement 3-year carry forward from user-entered data; implement real MPAA check |
| `app/Services/Retirement/PensionProjector.php` | Apply revaluation to DB pensions; source state pension from `TaxConfigService`; inject `TaxConfigService`; update default retirement age to 67 |
| `app/Services/Retirement/RetirementProjectionService.php` | Update default retirement age constant from 68 to 67 |
| `app/Services/Retirement/RetirementIncomeService.php` | Update default retirement age constant from 68 to 67 |
| `app/Services/Retirement/RequiredCapitalCalculator.php` | Update default retirement age constant from 68 to 67 |
| `app/Models/DCPension.php` | Add `has_flexibly_accessed` and `flexible_access_date` to `$fillable` and `$casts` |
| `app/Models/RetirementProfile.php` | Remove `risk_tolerance` from `$fillable`; add `prior_year_unused_allowance` to `$fillable` and `$casts` |
| `app/Http/Requests/Retirement/StoreDCPensionRequest.php` | Add validation for `has_flexibly_accessed` and `flexible_access_date` |

---

## Implementation Details

### 17.1 Hardcoded Tax Bands in ContributionOptimizer

**Problem:** `ContributionOptimizer` has 5 hardcoded tax values across two methods. `calculateTaxRelief()` (lines 171-191) hardcodes `$basicRateThreshold = 50270`, `$higherRateThreshold = 125140`, and rates 20%/40%/45%. `analyzeTaxRelief()` (lines 196-227) repeats `$higherRateThreshold = 50270`. All other retirement services correctly use `TaxConfigService`.

**Fix:**

The constructor already injects `TaxConfigService` (line 21), so this is a pure value replacement:

```php
// In calculateTaxRelief() - replace lines 173-175
$incomeTax = $this->taxConfig->getIncomeTax();
$pensionConfig = $this->taxConfig->getPensionAllowances();

$basicRateThreshold = $incomeTax['higher_rate_threshold'];
$higherRateThreshold = $incomeTax['additional_rate_threshold'];

$basicRate = $pensionConfig['tax_relief']['basic_rate'];
$higherRate = $pensionConfig['tax_relief']['higher_rate'];
$additionalRate = $pensionConfig['tax_relief']['additional_rate'];
```

Apply the same pattern in `analyzeTaxRelief()`:

```php
// In analyzeTaxRelief() - replace line 202
$incomeTax = $this->taxConfig->getIncomeTax();
$higherRateThreshold = $incomeTax['higher_rate_threshold'];
```

Also update the stale comment on line 173 from "UK tax bands 2024/25" to a reference that this is now dynamically sourced.

---

### 17.2 Proper 3-Year Carry Forward

**Problem:** `AnnualAllowanceChecker::getCarryForward()` (lines 160-166) always returns one full year's standard allowance (£60,000) regardless of the user's actual contribution history. This is maximally permissive -- every user appears to have £60,000 of carry forward available, which could mask a real annual allowance breach.

The proper HMRC rule is: unused annual allowance from the **previous 3 tax years** can be carried forward, oldest year first, but only if the user was a member of a registered pension scheme in that year.

**Data gap:** There is no `pension_contribution_history` table and no historical allowance tracking. Building a full automated system would require retroactive contribution data that doesn't exist.

**Fix - User-entered carry forward:**

Most financial planning tools handle this pragmatically: the user enters their unused allowance from prior years. Add a `prior_year_unused_allowance` JSON field to `retirement_profiles`:

**Migration:**

```php
Schema::table('retirement_profiles', function (Blueprint $table) {
    $table->json('prior_year_unused_allowance')->nullable()->after('life_expectancy');
});
```

**Data structure:**

```json
{
    "2022/23": 25000,
    "2023/24": 40000,
    "2024/25": 60000
}
```

**Updated `getCarryForward()`:**

```php
public function getCarryForward(int $userId, string $taxYear): float
{
    $profile = RetirementProfile::where('user_id', $userId)->first();

    if (!$profile || !$profile->prior_year_unused_allowance) {
        // No data entered -- return 0 (conservative, not permissive)
        return 0.0;
    }

    $priorYears = $profile->prior_year_unused_allowance;
    $carryForward = 0.0;

    // Sum unused from previous 3 years (oldest first per HMRC rules)
    $previousYears = $this->getPrevious3TaxYears($taxYear);

    foreach ($previousYears as $year) {
        $carryForward += (float) ($priorYears[$year] ?? 0);
    }

    return $carryForward;
}

private function getPrevious3TaxYears(string $currentTaxYear): array
{
    // Parse "2025/26" -> 2025
    $startYear = (int) substr($currentTaxYear, 0, 4);

    return [
        ($startYear - 3) . '/' . substr((string) ($startYear - 2), -2),
        ($startYear - 2) . '/' . substr((string) ($startYear - 1), -2),
        ($startYear - 1) . '/' . substr((string) $startYear, -2),
    ];
}
```

**Key behaviour change:** When no carry forward data is entered, the method returns `0` instead of `60,000`. This is the safe default -- it cannot cause a user to unknowingly exceed their allowance. The frontend should prompt users to enter their prior-year data for accurate carry forward calculations.

---

### 17.3 MPAA Implementation

**Problem:** `AnnualAllowanceChecker::checkMPAA()` (lines 173-187) hardcodes `$isTriggered = false`. The Money Purchase Annual Allowance (£10,000) should apply when a user has flexibly accessed any DC pension (e.g., flexi-access drawdown, UFPLS, or cashing in a small pot). No field exists on any model to track this.

**Fix:**

**Migration - add fields to `dc_pensions`:**

```php
Schema::table('dc_pensions', function (Blueprint $table) {
    $table->boolean('has_flexibly_accessed')->default(false)->after('beneficiary_name');
    $table->date('flexible_access_date')->nullable()->after('has_flexibly_accessed');
});
```

**Update DCPension model:**

```php
// Add to $fillable
'has_flexibly_accessed',
'flexible_access_date',

// Add to $casts
'has_flexibly_accessed' => 'boolean',
'flexible_access_date' => 'date',
```

**Update validation in `StoreDCPensionRequest`:**

```php
'has_flexibly_accessed' => 'nullable|boolean',
'flexible_access_date' => 'nullable|date|before_or_equal:today',
```

**Update `checkMPAA()`:**

```php
public function checkMPAA(int $userId): array
{
    $isTriggered = DCPension::where('user_id', $userId)
        ->where('has_flexibly_accessed', true)
        ->exists();

    $mpaaAmount = $this->getMPAA();

    $triggerDate = null;
    if ($isTriggered) {
        $triggerDate = DCPension::where('user_id', $userId)
            ->where('has_flexibly_accessed', true)
            ->min('flexible_access_date');
    }

    return [
        'is_triggered' => $isTriggered,
        'mpaa_amount' => $mpaaAmount,
        'trigger_date' => $triggerDate,
        'message' => $isTriggered
            ? 'Money Purchase Annual Allowance triggered - your annual allowance for money purchase contributions is reduced to £' . number_format($mpaaAmount) . ' per year.'
            : 'Money Purchase Annual Allowance not triggered - standard annual allowance applies.',
    ];
}
```

**Integration with `checkAnnualAllowance()`:** When MPAA is triggered, the annual allowance for DC pension contributions is reduced to £10,000 (while DB pension accrual retains the full standard/tapered allowance as an "alternative annual allowance"). The existing `checkAnnualAllowance()` method needs to apply this reduced limit to DC contributions specifically when `is_triggered` is true.

---

### 17.4 DB Pension Revaluation

**Problem:** `PensionProjector::projectDBPension()` (lines 66-72) returns `accrued_annual_pension` unchanged. A deferred DB pension accrued at £15,000 today will be worth more at retirement due to statutory revaluation, but the projection ignores this entirely. The `inflation_protection` field (`cpi`, `rpi`, `fixed`, `none`) is captured in the form but never used.

**Fix:**

```php
public function projectDBPension(DBPension $pension, ?int $currentAge = null): float
{
    $accruedPension = (float) $pension->accrued_annual_pension;

    if (!$currentAge) {
        $user = $pension->user;
        $currentAge = $user?->age ?? $user?->date_of_birth?->age;
    }

    $retirementAge = $pension->normal_retirement_age ?? self::DEFAULT_RETIREMENT_AGE;
    $yearsToRetirement = max(0, $retirementAge - ($currentAge ?? 40));

    if ($yearsToRetirement <= 0) {
        return $accruedPension;
    }

    $revaluationRate = $this->getRevaluationRate($pension);

    if ($revaluationRate <= 0) {
        return $accruedPension;
    }

    return round($accruedPension * pow(1 + $revaluationRate, $yearsToRetirement), 2);
}

private function getRevaluationRate(DBPension $pension): float
{
    return match ($pension->inflation_protection) {
        'cpi' => 0.025,    // Statutory CPI cap for preserved benefits
        'rpi' => 0.03,     // RPI-linked (older schemes)
        'fixed' => $this->parseFixedRate($pension->revaluation_method),
        'none' => 0.0,
        default => 0.02,   // Conservative default
    };
}

private function parseFixedRate(?string $revaluationMethod): float
{
    if (!$revaluationMethod) {
        return 0.025; // Default to CPI-equivalent if not specified
    }

    // Extract percentage from strings like "Fixed 3%", "3% per annum"
    if (preg_match('/(\d+(?:\.\d+)?)%/', $revaluationMethod, $matches)) {
        return (float) $matches[1] / 100;
    }

    return 0.025;
}
```

**Revaluation rates:**

| `inflation_protection` | Rate | Basis |
|---|---|---|
| `cpi` | 2.5% | Statutory cap on CPI revaluation for preserved benefits under Pension Schemes Act |
| `rpi` | 3.0% | RPI-linked (Guaranteed Minimum Pension and older contracted-out schemes) |
| `fixed` | Parsed from `revaluation_method` string | Scheme-specific fixed rate |
| `none` | 0% | No revaluation (pre-1985 early leavers) |

**Future enhancement:** Source CPI/RPI assumptions from `AssumptionsService` rather than hardcoding, allowing users to adjust inflation expectations.

**Impact:** This changes projected retirement income for any user with DB pensions. The `projectTotalRetirementIncome()` method which calls `projectDBPension()` will now return higher projected income, potentially closing the income gap shown on the dashboard. This is a more accurate picture -- the current zero-growth projection systematically understates DB pension value.

---

### 17.5 Consistent Default Retirement Age

**Problem:** Four services define independent default retirement age constants:

| Service | Current Value |
|---------|--------------|
| `PensionProjector` | 67 |
| `RetirementProjectionService` | 68 |
| `RetirementIncomeService` | 68 |
| `RequiredCapitalCalculator` | 68 |

The UK state pension age is currently 67 (rising to 68 between 2044-2046). Using 67 as the default is appropriate for current planning.

**Fix:**

Standardise all four constants to **67**:

```php
// RetirementProjectionService.php - line 21
private const DEFAULT_RETIREMENT_AGE = 67; // was 68

// RetirementIncomeService.php - line 33
private const DEFAULT_RETIREMENT_AGE = 67; // was 68

// RequiredCapitalCalculator.php - line 34
private const DEFAULT_RETIREMENT_AGE = 67; // was 68

// PensionProjector.php - line 22 (already 67, no change)
private const DEFAULT_RETIREMENT_AGE = 67;
```

Also fix the inline fallbacks in `RetirementProjectionService` that reference 67 as the state pension age but use 68 as the retirement age default -- these should now both be 67 consistently.

**Note:** This only affects users who have **not** set a target retirement age. The resolution chain (user profile -> retirement profile -> first DC pension -> default) means real users with data are unaffected.

---

### 17.6 State Pension Amount from TaxConfigService

**Problem:** `PensionProjector::projectStatePension()` (line 87) hardcodes `$fullStatePension = 11502.00` (2024/25 figure). The correct 2025/26 amount is £11,973.00, which is already stored in `TaxConfigService::getPensionAllowances()['state_pension']['full_new_state_pension']`.

**Fix:**

**Step 1 - Add `TaxConfigService` to constructor:**

`PensionProjector` currently only injects `RiskPreferenceService`. Add `TaxConfigService`:

```php
public function __construct(
    private RiskPreferenceService $riskPreferenceService,
    private TaxConfigService $taxConfig
) {}
```

**Step 2 - Replace hardcoded value:**

```php
// Before (line 87)
$fullStatePension = 11502.00;

// After
$pensionConfig = $this->taxConfig->getPensionAllowances();
$fullStatePension = $pensionConfig['state_pension']['full_new_state_pension'] ?? 11973.00;
```

The fallback `11973.00` ensures the method still works if the config is missing, using the current 2025/26 figure.

**Step 3 - Also source qualifying years from config:**

While we're in this method, the NI qualifying years for full state pension (currently 35) should also come from config:

```php
// Before
$niYearsRequired = $statePension->ni_years_required ?? 35;

// After
$niYearsRequired = $statePension->ni_years_required
    ?? ($pensionConfig['state_pension']['qualifying_years'] ?? 35);
```

This ensures the state pension calculation stays current when the `TaxConfigurationSeeder` is updated for future tax years.

---

### 17.7 Remove Deprecated risk_tolerance Field

**Problem:** `RetirementProfile.risk_tolerance` is explicitly marked as deprecated in the model docblock. No retirement service reads it. It exists only in `$fillable`, meaning it can be mass-assigned but is never consumed. Keeping it creates confusion and the risk of accidental use.

**Fix:**

**Step 1 - Remove from model:**

```php
// RetirementProfile.php - remove from $fillable array
// Before
protected $fillable = [
    'user_id', 'current_age', 'target_retirement_age', 'current_annual_salary',
    'target_retirement_income', 'essential_expenditure', 'lifestyle_expenditure',
    'life_expectancy', 'spouse_life_expectancy', 'risk_tolerance',
];

// After
protected $fillable = [
    'user_id', 'current_age', 'target_retirement_age', 'current_annual_salary',
    'target_retirement_income', 'essential_expenditure', 'lifestyle_expenditure',
    'life_expectancy', 'spouse_life_expectancy',
];
```

Remove the `@deprecated` docblock since the field will no longer exist.

**Step 2 - Migration to drop column:**

```php
Schema::table('retirement_profiles', function (Blueprint $table) {
    $table->dropColumn('risk_tolerance');
});
```

**Step 3 - Check seeder:**

Verify `PreviewUserSeeder::createRetirementProfiles()` does not set `risk_tolerance`. If it does, remove that field from the seeded data.

**Risk:** Zero. No code reads this field from `RetirementProfile`. The Investment module references `risk_tolerance` on its own `RiskProfile` model, which is a different table and unaffected.

---

## Testing Requirements

| Fix | Test |
|-----|------|
| 17.1 Tax bands | Verify `calculateTaxRelief()` returns correct relief at basic/higher/additional thresholds; verify values match `TaxConfigService` output |
| 17.2 Carry forward | Enter prior-year unused allowance JSON; verify 3-year sum is correct; verify empty data returns 0 (not £60,000) |
| 17.3 MPAA | Set `has_flexibly_accessed = true` on a DC pension; verify `checkMPAA()` returns triggered; verify annual allowance check uses £10,000 limit for DC contributions |
| 17.4 DB revaluation | Create DB pension with `inflation_protection = 'cpi'`, 20 years to retirement; verify projected value is `accrued * 1.025^20`; test each inflation type |
| 17.5 Retirement age | Verify all 4 services use 67 as default; create user with no retirement age set; verify consistent projections across all services |
| 17.6 State pension | Verify `projectStatePension()` returns value based on `TaxConfigService` (£11,973); update seeder to new amount; verify NI years also from config |
| 17.7 risk_tolerance | Run migration; verify column dropped; verify `RetirementProfile::create()` ignores `risk_tolerance`; verify no errors in retirement analysis |

---

## Implementation Order

| Order | Fix | Reason |
|-------|-----|--------|
| 1 | 17.1 Hardcoded tax bands | Highest priority; directly incorrect tax relief calculations; simplest fix (value replacement, no structural change) |
| 2 | 17.6 State pension amount | Highest priority; factually wrong amount (£471/year understatement); simple fix once TaxConfigService injected |
| 3 | 17.5 Inconsistent retirement ages | Medium priority; 1-line change in 3 files; eliminates subtle discrepancies |
| 4 | 17.4 DB pension revaluation | Medium priority; improves projection accuracy significantly for DB pension holders |
| 5 | 17.3 MPAA implementation | Medium priority; requires migration + model change + checker update |
| 6 | 17.2 Carry forward | Medium priority; requires migration + model change + new data entry; depends on understanding 17.3 pattern |
| 7 | 17.7 Deprecated field removal | Lowest priority; cleanup only; no behaviour change; run migration last |
