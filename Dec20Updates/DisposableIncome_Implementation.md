# Disposable Income & Expenditure Implementation

## Date: December 20, 2024

## Overview

Added disposable income calculation to the Income & Occupation tab and ensured consistency across all modules that use expenditure data.

---

## Changes Made

### 1. Income & Occupation Tab - Disposable Income Card

**File:** `resources/js/components/UserProfile/IncomeOccupation.vue`

Added a new "Disposable Income" section showing:
- Net Income (after tax)
- Annual Expenditure
- Disposable Income (green if positive, red if negative)

```vue
<!-- Disposable Income Section -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
  <div>Net Income (after tax): {{ formatCurrency(incomeOccupation.net_income) }}</div>
  <div>Annual Expenditure: {{ formatCurrency(totalAnnualExpenditure) }}</div>
  <div>Disposable Income: {{ formatCurrency(disposableIncome) }}</div>
</div>
```

---

### 2. Backend Expenditure Calculation

**File:** `app/Services/UserProfile/UserProfileService.php`

Updated `getExpenditureBreakdown()` to correctly calculate expenditure based on entry mode:

#### Category Mode (expenditure_entry_mode = 'category')
Sums all individual category fields:
- food_groceries
- transport_fuel
- healthcare_medical
- insurance
- mobile_phones
- internet_tv
- subscriptions
- clothing_personal_care
- entertainment_dining
- holidays_travel
- pets
- childcare
- school_fees
- school_lunches
- school_extras
- university_fees
- children_activities
- gifts_charity
- regular_savings
- other_expenditure

#### Simple Mode
Uses the `monthly_expenditure` field directly.

#### Financial Commitments Added
Both modes add financial commitments from:
- Retirement (pension contributions)
- Properties (mortgages at ownership %)
- Protection (insurance premiums)
- Liabilities (loan payments)

```php
private function getExpenditureBreakdown(User $user): array
{
    if ($user->expenditure_entry_mode === 'category') {
        $monthlyManual = /* sum of all categories */;
    } else {
        $monthlyManual = (float) ($user->monthly_expenditure ?? 0);
    }

    $commitments = $this->getFinancialCommitments($user);
    $monthlyCommitments = (float) ($commitments['totals']['total'] ?? 0);
    $monthlyTotal = $monthlyManual + $monthlyCommitments;

    return [
        'monthly_manual' => round($monthlyManual, 2),
        'monthly_commitments' => round($monthlyCommitments, 2),
        'monthly' => round($monthlyTotal, 2),
        'annual' => round($monthlyTotal * 12, 2),
    ];
}
```

---

### 3. Mortgage Ownership Percentage Fix

**File:** `app/Services/UserProfile/UserProfileService.php`

Fixed `getFinancialCommitments()` to apply ownership percentage to mortgage payments:

```php
// Before: Full mortgage payment
$totalMonthlyExpense += $mortgage->monthly_payment;

// After: User's share based on ownership
$ownershipPercent = ($property->ownership_percentage ?? 100) / 100;
$userShare = $mortgage->monthly_payment * $ownershipPercent;
$totalMonthlyExpense += $userShare;
```

---

### 4. Retirement Strategies Tab Integration

**File:** `app/Services/Retirement/RetirementStrategyService.php`

Updated `calculateAffordability()` to use the same disposable income from `UserProfileService`:

```php
private function calculateAffordability(User $user): array
{
    $profile = $this->userProfileService->getCompleteProfile($user);
    $incomeData = $profile['income_occupation'] ?? [];

    // Use values from profile (includes categories + financial commitments)
    $annualExpenditure = (float) ($incomeData['annual_expenditure'] ?? 0);
    $disposableIncome = (float) ($incomeData['disposable_income'] ?? 0);
    $monthlyDisposable = (float) ($incomeData['monthly_disposable'] ?? 0);

    return [
        'gross_income' => round($grossIncome, 2),
        'net_income' => round($netIncome, 2),
        'annual_expenditure' => round($annualExpenditure, 2),
        'disposable_income' => round($disposableIncome, 2),
        'monthly_disposable' => round($monthlyDisposable, 2),
    ];
}
```

---

## Data Flow

```
User Profile DB
    │
    ├── expenditure_entry_mode ('category' or 'simple')
    ├── Category fields (food_groceries, transport_fuel, etc.)
    └── monthly_expenditure (simple mode)
           │
           ▼
UserProfileService.getExpenditureBreakdown()
    │
    ├── Calculates monthly_manual (based on entry mode)
    ├── Fetches financial commitments
    └── Returns: monthly_manual + monthly_commitments = monthly total
           │
           ▼
    ┌──────┴──────┐
    │             │
    ▼             ▼
Income &      Retirement
Occupation    Strategies
Tab           Tab
(User         (Monthly
Profile)      Disposable
              Card)
```

---

## Example: James Carter

| Field | Value |
|-------|-------|
| Entry Mode | category |
| Categories Sum | £2,315/month |
| Financial Commitments | £1,124.50/month |
| **Total Monthly Expenditure** | **£3,439.50** |
| **Total Annual Expenditure** | **£41,274** |
| Net Income | £47,261.40 |
| **Disposable Income** | **£5,987.40/year** |
| **Monthly Disposable** | **£498.95** |

---

## Commits

1. `a58654b` - Apply ownership percentage to mortgage payments in Expenditure tab
2. `9e171c2` - Include financial commitments in disposable income calculation
3. `fa93b38` - Save expenditure total with financial commitments included
4. `af4237e` - Use saved expenditure values directly in Income & Occupation tab
5. `227d81e` - Backend calculates expenditure with financial commitments included
6. `b8936e5` - Use category sum when expenditure_entry_mode is 'category'
7. `925f676` - Use Income & Occupation disposable income in Retirement Strategies

---

## Testing

### API Endpoints

```bash
# Get user profile with expenditure breakdown
curl -H "Authorization: Bearer $TOKEN" \
  "http://localhost:8000/api/user/profile"

# Get financial commitments
curl -H "Authorization: Bearer $TOKEN" \
  "http://localhost:8000/api/user/financial-commitments"

# Get retirement strategies (includes affordability)
curl -H "Authorization: Bearer $TOKEN" \
  "http://localhost:8000/api/retirement/strategies"
```

### Verify Values Match

1. Go to User Profile > Income & Occupation tab
2. Check the Disposable Income card shows the correct values
3. Go to Retirement module > Strategies tab
4. Verify the "Monthly Disposable Income" card shows the same value

---

## Files Modified

| File | Changes |
|------|---------|
| `app/Services/UserProfile/UserProfileService.php` | Expenditure calculation, financial commitments ownership % |
| `app/Services/Retirement/RetirementStrategyService.php` | Use profile's disposable income |
| `resources/js/components/UserProfile/IncomeOccupation.vue` | Added Disposable Income section |
| `resources/js/components/UserProfile/ExpenditureForm.vue` | Save total with commitments |
