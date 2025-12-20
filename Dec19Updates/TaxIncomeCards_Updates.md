# Tax Income Cards & Pension Contributions Update

**Date:** 19 December 2025
**Branch:** incomeTax (merged to main)

## Summary

Redesigned the UK Tax & NI Calculations section in User Profile to consolidate income types that share the same tax bands, and added pension contribution deductions from employment income.

---

## Changes Made

### 1. Consolidated Tax Income Cards

**Before:** Separate cards for each income type (employment, self-employment, rental, pension)

**After:** Single "Earned Income" card for income types using the same tax bands (20%/40%/45%)

#### Card Structure:
- **Earned Income Card** - Employment, self-employment, rental, pension income (combined)
- **Interest Income Card** - Separate due to Personal Savings Allowance
- **Dividend Income Card** - Separate due to special rates (8.75%/33.75%/39.35%)
- **Trust Income Card** - Separate due to trust-specific taxation

### 2. Pension Contribution Deduction

Employee pension contributions to occupational/workplace pensions are now deducted from employment income before tax calculation (tax relief at source).

**Display:**
```
Earned Income: £166,600
├── Employment Income: £145,000
├── Pension Contributions: -£11,600
├── Rental Income: £21,600
└── Taxable Income: £155,000
```

**Tax Calculation:**
- Tax calculated on reduced amount (after pension contributions)
- NI still calculated on gross employment income (pension contributions don't reduce NI)

### 3. Pension Card Contributions Fix

Fixed pension contribution display in Net Worth pension cards:
- Occupational pensions: Calculate from `employee_contribution_percent` + `employer_contribution_percent` × `annual_salary`
- SIPPs/Personal: Use fixed `monthly_contribution_amount`

### 4. Removed Legacy Components

Deleted 6 unused pension panel components (-3,700 lines):
- `PensionDetailView.vue`
- `PensionContributionsPanel.vue`
- `PensionSummaryPanel.vue`
- `PensionDetailsPanel.vue`
- `PensionProjectionsPanel.vue`
- `PensionAnalysisPanel.vue`

---

## Files Modified

### Backend
| File | Changes |
|------|---------|
| `app/Services/UKTaxCalculator.php` | Combined earned income calculation, added pension contribution parameter |
| `app/Services/UserProfile/UserProfileService.php` | Added `calculateAnnualPensionContributions()`, pass to tax calculator |

### Frontend
| File | Changes |
|------|---------|
| `resources/js/components/UserProfile/TaxIncomeCard.vue` | Handle combined earned income display with components breakdown |
| `resources/js/components/UserProfile/IncomeOccupation.vue` | Fixed total income currency formatting |
| `resources/js/components/NetWorth/PensionList.vue` | Added `calculateMonthlyContribution()` method |
| `resources/js/components/NetWorth/PensionDetailInline.vue` | Calculate contributions from percentages |

---

## Data Structure

### Earned Income Breakdown (API Response)
```json
{
  "income_type": "earned",
  "income_type_label": "Earned Income",
  "gross_amount": 166600,
  "income_components": [
    { "label": "Employment Income", "amount": 145000 },
    { "label": "Pension Contributions", "amount": -11600, "is_deduction": true },
    { "label": "Rental Income", "amount": 21600 }
  ],
  "taxable_income": 155000,
  "tax_breakdown": {
    "personal_allowance_used": 12570,
    "basic_rate": { "taxable": 37700, "tax": 7540, "rate": 0.2 },
    "higher_rate": { "taxable": 87440, "tax": 34976, "rate": 0.4 },
    "additional_rate": { "taxable": 17290, "tax": 7780.5, "rate": 0.45 },
    "total_income_tax": 50296.5
  },
  "ni_breakdown": {
    "class_1": { ... },
    "class_4": null,
    "total_ni": 4910.6
  }
}
```

---

## Pension Contribution Calculation

Only **occupational/workplace pensions** qualify for pre-tax deduction:

```php
// UserProfileService.php
private function calculateAnnualPensionContributions(User $user): float
{
    foreach ($user->dcPensions as $pension) {
        if (in_array($pension->scheme_type, ['workplace', 'occupational', 'auto_enrolment'])) {
            if ($pension->employee_contribution_percent && $pension->annual_salary) {
                $contribution = ($pension->annual_salary * $pension->employee_contribution_percent / 100);
                $total += $contribution;
            }
        }
    }
    return $total;
}
```

**Note:** SIPP contributions are paid from net income (different tax relief mechanism) so are not deducted here.

---

## Commits

```
114416d fix: Use consistent currency formatting for total annual income
f368afc fix: Calculate pension contributions from percentages in pension cards
7c26e66 feat: Consolidate tax income cards and add pension contribution deduction
```

---

## Testing

Verified with personas:
- **James Carter (young_family):** Employment £62,000, pension contribution £1,860 (3%)
- **David Mitchell (peak_earners):** Employment £145,000 + Rental £21,600, pension contribution £11,600 (8%)
- **Margaret Thompson (widow):** Pension income only (DB + State), no pension contributions
