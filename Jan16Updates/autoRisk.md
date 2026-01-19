# Automated Risk Profile Calculator

## Overview

Replace manual risk profile selection with automatic calculation based on 7 financial factors. Users will no longer be redirected to set a risk profile when adding investments/pensions - it will be calculated automatically from their data.

---

## The 7 Factors & Risk Level Mapping

| Factor | Data Source | Risk Level Assignment |
|--------|-------------|----------------------|
| **1. Capacity for Loss** | (investments + pensions) / net worth | <30% = HIGH, 30-75% = MEDIUM, >75% = LOWER_MEDIUM |
| **2. Time Horizon** | Years to retirement | Retired-3y = LOWER_MEDIUM, 3-15y = MEDIUM, 15-20y = UPPER_MEDIUM, 20+y = HIGH |
| **3. Education** | `user.education_level` | No degree (secondary/a_level) = LOWER_MEDIUM, Degree+ = MEDIUM |
| **4. Dependants** | Count of `is_dependent=true` | 0 = UPPER_MEDIUM, 1 = MEDIUM, 2+ = LOWER_MEDIUM |
| **5. Employment** | `user.employment_status` | employed/self_employed = MEDIUM, retired = LOWER_MEDIUM |
| **6. Emergency Cash** | Emergency fund runway months | 0-3mo = LOWER_MEDIUM, 3-6mo = MEDIUM, 6+mo = UPPER_MEDIUM |
| **7. Surplus Cash** | Monthly income - expenditure | Negative-0 = LOWER_MEDIUM, 0-500 = MEDIUM, 501+ = UPPER_MEDIUM |

**Final Risk Level** = Most recurring level across all 7 factors (mode)

---

## Implementation Architecture

### Backend Service: `AutoRiskCalculator.php`

```php
class AutoRiskCalculator
{
    public function calculateRiskProfile(User $user): array
    {
        $factors = [
            $this->calculateCapacityForLoss($user),
            $this->calculateTimeHorizon($user),
            $this->calculateEducationFactor($user),
            $this->calculateDependantsFactor($user),
            $this->calculateEmploymentFactor($user),
            $this->calculateEmergencyCashFactor($user),
            $this->calculateSurplusCashFactor($user),
        ];

        return [
            'risk_level' => $this->determineFinalLevel($factors),
            'factor_breakdown' => $factors,
        ];
    }
}
```

Each factor method returns:
```php
['factor' => 'capacity_for_loss', 'level' => 'medium', 'value' => '45%', 'description' => '...']
```

### Integration with RiskPreferenceService

New method added:
```php
public function calculateAndSetRiskLevel(int $userId): array
{
    $calculator = app(AutoRiskCalculator::class);
    $user = User::findOrFail($userId);
    $result = $calculator->calculateRiskProfile($user);

    RiskProfile::updateOrCreate(
        ['user_id' => $userId],
        [
            'risk_level' => $result['risk_level'],
            'factor_breakdown' => json_encode($result['factor_breakdown']),
            'is_self_assessed' => false,
            'risk_assessed_at' => now(),
        ]
    );

    return $result;
}
```

---

## Files to Modify

| File | Change |
|------|--------|
| `app/Services/Risk/AutoRiskCalculator.php` | **CREATE** - Core calculation service |
| `app/Services/Risk/RiskPreferenceService.php` | Add `calculateAndSetRiskLevel()` method |
| `app/Http/Controllers/Api/RiskPreferenceController.php` | Add recalculate endpoint, update getProfile |
| `database/migrations/xxxx_add_factor_breakdown_to_risk_profiles.php` | **CREATE** - Add JSON column |
| `resources/js/views/Risk/RiskProfilePage.vue` | Transform to breakdown display |
| `resources/js/components/Risk/FactorBreakdownCard.vue` | **CREATE** - Factor display component |
| `resources/js/components/Investment/AccountForm.vue` | Remove redirect gate (lines 571-577) |
| `resources/js/components/Retirement/DCPensionForm.vue` | Remove redirect gate |

---

## Data Dependencies

All required data is already available in the system:

| Data | Source |
|------|--------|
| Net worth | `NetWorthService::calculateNetWorth()` |
| Investment totals | `CrossModuleAssetAggregator` or sum `investment_accounts.current_value` |
| Pension totals | Sum `dc_pensions.current_fund_value` |
| Emergency fund runway | `EmergencyFundCalculator::calculateRunway()` |
| Education level | `user.education_level` |
| Employment status | `user.employment_status` |
| Retirement age | `user.target_retirement_age` - current age |
| Dependants | `FamilyMember::where('is_dependent', true)->count()` |
| Monthly income | Sum of `user.annual_*_income` fields / 12 |
| Monthly expenditure | `user.monthly_expenditure` |

---

## UI Changes

### Risk Profile Page Transformation

The `/risk-profile` page will change from a selection interface to a breakdown display:

1. **Current Risk Level** - Prominently displayed badge
2. **Factor Breakdown Section** - 7 cards showing each factor with:
   - Factor name and icon
   - Current value (e.g., "45%", "18 years", "6.2 months")
   - Resulting risk level badge
   - Brief description
3. **Mode Explanation** - "Your risk level is determined by the most recurring level across factors"
4. **Product Override Section** - Keep existing +/-1 level override capability
5. **Educational Content** - Keep existing panels

### Form Changes

Remove redirect gates from:
- `AccountForm.vue` - Investment account form
- `DCPensionForm.vue` - Pension form

Users can now add investments/pensions without being forced to set a risk profile first.

---

## Auto-Recalculation Triggers

Risk profile recalculates automatically when:
- User profile changes (income, education, employment, retirement age)
- Family member changes (add/remove dependants)
- Savings account changes (emergency fund balance)
- Investment/pension value changes

---

## Verification Plan

1. **Test auto-calculation:** Login, verify risk profile is calculated automatically
2. **Test factor breakdown:** Check `/risk-profile` page shows all 7 factors
3. **Test mode calculation:** Verify most recurring level is selected
4. **Test form access:** Add investment/pension without redirect
5. **Test product override:** Verify +/-1 level override still works
6. **Test recalculation:** Change user data, verify risk level updates
