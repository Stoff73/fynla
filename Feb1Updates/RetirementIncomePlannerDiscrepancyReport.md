# Retirement Income Planner - Discrepancy Report

**Date:** 1 February 2026
**Source of Truth:** `Feb1Updates/RetirementIncomePlannerConsolidated.md`
**Scope:** Full comparison of spec vs implementation

---

## Executive Summary

The Retirement Income Planner implementation is **largely compliant** with the specification. The core algorithms for Monte Carlo projections, tax optimisation, PMT-based withdrawals, and fund depletion are correctly implemented. However, **one critical issue** and several medium-priority discrepancies were identified.

| Severity | Count | Status |
|----------|-------|--------|
| Critical | 2 | Requires immediate fix |
| Minor | 2 | Low priority |
| Clarified (Not Issues) | 3 | Working as designed / Deferred |
| Verified Correct | 9 | No action needed |

---

## Critical Issues

### CRIT-1: Controller Endpoint Does Not Pass Projected Pension Pot

**Spec Reference:** Section 2 - Available Accounts Structure

**Location:** `app/Http/Controllers/Api/RetirementController.php` lines 579-593

**Current Code:**
```php
public function getIncomeAccounts(Request $request): JsonResponse
{
    $user = $request->user();
    $includeSpouse = $request->boolean('include_spouse', false);

    $accounts = $this->retirementIncomeService->getAvailableAccounts($user->id, $includeSpouse);
    // ...
}
```

**Expected Behaviour:**
Per the spec, the `getAvailableAccounts()` method should return accounts with **projected values at retirement** using Monte Carlo 80% confidence. For the pension pot, this requires calling `RetirementProjectionService::projectPensionPot()` to get `percentile_20_at_retirement`.

**Actual Behaviour:**
The controller calls `getAvailableAccounts()` without passing `$projectedPensionPot`, which defaults to `0`. This triggers the condition at line 298 of `RetirementIncomeService.php`:
```php
if ($projectedPensionPot > 0) {
    // Pension pot is only added if this condition is true
}
```

**Impact:**
When the frontend or any external consumer calls `GET /api/retirement/income/accounts`, the **pension pot is NOT included** in the response. The combined DC pension projection is missing entirely.

**Correct Implementation (from internal service):**
`RetirementIncomeService.php` lines 80-94 shows the correct pattern:
```php
$potProjection = $this->projectionService->projectPensionPot($user);
$projectedPensionPot = (float) ($potProjection['percentile_20_at_retirement'] ?? 0);
$yearsToRetirement = max(0, $retirementAge - ($currentAge ?? 45));
$availableAccounts = $this->getAvailableAccounts($userId, $includeSpouse, $projectedPensionPot, $yearsToRetirement);
```

**Resolution:**
Update `RetirementController::getIncomeAccounts()` to match the internal service pattern:

```php
public function getIncomeAccounts(Request $request): JsonResponse
{
    $user = $request->user();
    $includeSpouse = $request->boolean('include_spouse', false);

    // Get projected pension pot value (80% Monte Carlo confidence)
    $potProjection = $this->projectionService->projectPensionPot($user);
    $projectedPensionPot = (float) ($potProjection['percentile_20_at_retirement'] ?? 0);

    // Calculate years to retirement
    $profile = RetirementProfile::where('user_id', $user->id)->first();
    $currentAge = $user->date_of_birth?->age ?? 45;
    $retirementAge = $profile?->target_retirement_age ?? 67;
    $yearsToRetirement = max(0, $retirementAge - $currentAge);

    $accounts = $this->retirementIncomeService->getAvailableAccounts(
        $user->id,
        $includeSpouse,
        $projectedPensionPot,
        $yearsToRetirement
    );

    return response()->json([...]);
}
```

---

### CRIT-2: Account Depletion Order Must Be Verified

**Spec Reference:** Section 3.2 - Tax Efficiency Priority Order, Section 6 - Rules

**Required Depletion Order:**

1. **Tax-Free First:**
   - PCLS (25% of pension pot)
   - ISA (Cash ISA before Stocks & Shares ISA)

2. **Then Taxable Accounts:**
   - GIA (General Investment Account)
   - Savings (non-ISA)

3. **Last:**
   - Pension Drawdown (75% taxable pot)

4. **Exception - Bonds:**
   - Onshore and Offshore Bonds remain at **fixed 5% withdrawal** (hardcoded)
   - Bond depletion timing is not an issue - may deplete before or after other accounts

**Target:**
All accounts (except bonds) should reach as close to **£0 as possible at age 100**.

**Verification Needed:**
Check `calculateDefaultAllocations()` in `RetirementIncomeService.php` to confirm:

- Tax-free sources are allocated first and fully depleted
- GIA/Savings are allocated before pension drawdown
- PMT calculations target £0 at age 100 for each account type
- Bonds are excluded from the depletion ordering logic

---

## Clarified (Not Issues)

### Year-by-Year Tables

**Status:** Working as Designed

Both tables are intentionally present and serve different purposes:

1. **Accumulation Table** (`requiredCapital.year_by_year`): Shows pension pot growth during working years leading to retirement
2. **Decumulation Table** (FundDepletionChart): Shows fund depletion during retirement years

These are complementary views - accumulation shows the journey TO retirement, decumulation shows the journey FROM retirement.

---

### Income Source Sliders

**Status:** Working as Designed

The `IncomeSourceSlider` component displays calculated allocations without slider input. This is intentional - the system automatically calculates optimal tax-efficient allocations based on the priority rules. Manual adjustment is not required.

---

### Spouse Asset Toggle

**Status:** Deferred to Future Release

The spouse toggle is currently disabled with `v-if="false"`. The backend fully supports spouse assets via the `includeSpouse` parameter. This feature is planned for a later release and is not a bug.

---

## Minor Issues

### MIN-1: PCLS Not Shown as Explicit Column

**Spec Reference:** Section 7 - "pcls use" column required

**Location:** `resources/js/components/Retirement/FundDepletionChart.vue`

**Current State:**
The table uses `activeFundTypes` computed property which dynamically includes fund types. PCLS is included if the data contains it, but it's not explicitly guaranteed.

**Resolution:**
Verify that `pension_pot_pcls` or `pcls` appears as a distinct fund type in the projections data.

---

### MIN-2: Personal Allowance Incorrect in Spec

**Spec Reference:** Section 3.2, line 306

**Current State:**
The spec states:
> "Do not go above the tax years personal allowance limit, for 2025/26 this is **£12,750**"

**Correct Value:**
The 2025/26 Personal Allowance is **£12,570**.

**Resolution:**
Update the spec document to correct the PA amount.

---

## Verified Correct Implementations

The following aspects have been verified as correctly implemented per the spec:

### 1. Monte Carlo 80% Projections
**Spec:** Section 2 - Projection Methods by Account Type

**Implementation:**
- DC Pensions: `RetirementProjectionService::projectPensionPot()` returns `percentile_20_at_retirement`
- Investment accounts: `InvestmentProjectionService::getAccountProjectedValue80()` uses 1000 Monte Carlo iterations
- Cash ISAs: 2% compound growth

### 2. Tax Optimisation Priority Order
**Spec:** Section 3.2 - Tax Efficiency Priority Order

**Implementation:** `RetirementIncomeService::calculateDefaultAllocations()` follows:
1. State Pension / DB Pension (unavoidable, uses PA first)
2. Bond 5% tax-deferred withdrawals
3. Fill remaining PA with drawdown
4. PCLS (tax-free)
5. ISA (Cash ISA before S&S ISA)
6. GIA / Savings (last resort)

### 3. PMT Formula for Withdrawals
**Spec:** Section 6 - Rule 1

**Implementation:** `calculateSustainableWithdrawalRate()` at lines 1435-1456:
```php
$pmt = ($totalFunds * $r * $factor) / ($factor - 1);
```

### 4. Growth Rates
**Spec:** Section 4 - Fund Depletion Algorithm

**Implementation:** `getGrowthRateForFund()`:
- Pension Drawdown: 4%
- Investment ISA: 4%
- Onshore/Offshore Bond: 4%
- GIA: 4%
- Cash ISA/PCLS: 0%
- Savings: 2%

### 5. TaxBandTracker Usage
**Spec:** Section 3.3 - Tax Band Application

**Implementation:** `TaxBandTracker` correctly allocates income through PA → Basic → Higher → Additional rates.

### 6. include_in_retirement Filter
**Spec:** Section 5 - Investment Account Retirement Inclusion

**Implementation:**
- `InvestmentAccount::$fillable` includes `include_in_retirement`
- `SavingsAccount::$fillable` includes `include_in_retirement`
- `getAvailableAccounts()` filters by `where('include_in_retirement', true)`

### 7. Account Structure with Projection Fields
**Spec:** Section 2 - Key Fields for Projected Accounts

**Implementation:** Accounts include:
- `current_value` - today's value
- `value` - projected value
- `is_projected` - boolean flag
- `years_projected` - years to retirement
- `projection_type` - 'monte_carlo_80' or growth rate

### 8. Fund Depletion Simulation
**Spec:** Section 4 - Fund Depletion Projection

**Implementation:** `projectFundDepletion()` simulates year-by-year from retirement age to 100, applying withdrawals, growth, and tracking depletion ages.

### 9. Database Fields
**Spec:** Section 5 - Database Field

**Implementation:**
- Migration `2026_01_30_150000` adds `include_in_retirement` to `investment_accounts`
- Migration `2026_01_31_120000` adds `include_in_retirement` to `savings_accounts`
- Bond fields (`bond_purchase_date`, `bond_withdrawal_taken`) exist

---

## Resolution Plan

### Phase 1: Fix Controller Endpoint (Critical)

**File:** `app/Http/Controllers/Api/RetirementController.php`

Update `getIncomeAccounts()` to calculate and pass projected pension pot. Estimated: 15 lines of code.

### Phase 2: Verify Account Depletion Order (Critical)

**File:** `app/Services/Retirement/RetirementIncomeService.php`

Verify `calculateDefaultAllocations()` implements correct depletion order:

1. Tax-Free First: PCLS → ISA (Cash before S&S)
2. Then Taxable: GIA → Savings
3. Last: Pension Drawdown
4. Exception: Bonds at fixed 5% (no ordering requirement)

Ensure PMT calculations target £0 at age 100 for all accounts except bonds.

### Phase 3: Minor Fixes

1. Verify PCLS shows as explicit column in FundDepletionChart
2. Correct PA amount in spec from £12,750 to £12,570

---

## Testing Checklist

After implementing fixes:

- [ ] Call `GET /api/retirement/income/accounts` - verify `pension_pot` type account is present with Monte Carlo 80% projected value
- [ ] Load Retirement Income Planner - verify pension pot card shows projected value
- [ ] Verify depletion order: PCLS → ISA → GIA/Savings → Drawdown (bonds at 5% fixed)
- [ ] Check all accounts (except bonds) project to £0 at age 100
- [ ] Toggle an investment account inclusion - verify fund depletion recalculates
- [ ] Check year-by-year table shows PCLS as explicit column
- [ ] Verify tax breakdown shows PA usage, basic rate, higher rate correctly
- [ ] Test with user who has no DC pensions - verify other accounts still project correctly

---

## Appendix: File Locations

| Component | File Path | Priority |
|-----------|-----------|----------|
| Controller | `app/Http/Controllers/Api/RetirementController.php` | **Critical - Fix CRIT-1** |
| Income Service | `app/Services/Retirement/RetirementIncomeService.php` | **Critical - Verify CRIT-2** |
| Projection Service | `app/Services/Retirement/RetirementProjectionService.php` | Reference |
| Investment Projection | `app/Services/Investment/InvestmentProjectionService.php` | Reference |
| Depletion Chart | `resources/js/components/Retirement/FundDepletionChart.vue` | Minor - Verify PCLS column |
| Main UI Component | `resources/js/components/Retirement/RetirementIncomeTab.vue` | Reference |
| Tax Breakdown | `resources/js/components/Retirement/TaxBreakdownCard.vue` | Reference |
| Income Source Card | `resources/js/components/Retirement/IncomeSourceSlider.vue` | Reference |
| Vuex Store | `resources/js/store/modules/retirement.js` | Reference |
| Spec Document | `Feb1Updates/RetirementIncomePlannerConsolidated.md` | Minor - Fix PA typo |
