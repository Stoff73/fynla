# Estate Planning Decision Engine: Implementation Plan

> Gap analysis between `research-estate-planning.md` and the current codebase, with phased implementation.
>
> **Date:** 2026-03-14 | **Based on:** 2 codebase audits (backend + frontend/TaxConfig)

---

## Executive Summary

The estate planning module is **the most mature module in the codebase** — all 22 services exist, 32+ Vue components are built, TaxConfigService has 18+ estate-specific methods, and the IHT calculation engine is comprehensive. The gaps are primarily about:

1. **Data discipline** — removing EstateDefaults assumptions, using actual user data, fetching surplus income from Income module
2. **Missing rules** — 2027 pension IHT amendment, 14-year rule enforcement in main calc, RNRB direct descendant clarification, trust NRB avoidance projection
3. **Centralisation** — fixing the one true hardcoded growth rate (in `LifePolicyStrategyService`) to use `AssumptionsService`, adding growth_by_risk mapping to seeder, and moving insurance premium estimates to TaxConfigService
4. **Data readiness** — replacing the auto-create IHT profile approach with granular user data checks
5. **Life insurance enhancement** — checking joint life status, trust status, premium type

Unlike the Savings module (which needed a complete engine rebuild), Estate Planning needs **targeted enhancements** to existing services.

---

## What Exists vs What's Needed

| Area | Current State | Target State | Gap Size |
|------|--------------|-------------|----------|
| **IHT Calculation** | Full NRB/RNRB/taper/spouse calc | Add PET deduction from NRB, 14-year CLT cumulation | Small enhancement |
| **Data Readiness Gate** | Auto-creates default IHTProfile | 12 granular checks, no assumptions | Moderate rewrite |
| **2027 Pension Amendment** | Not implemented — pensions excluded from estate | Dual-scenario projection (current vs post-2027) | New feature |
| **EstateDefaults** | 10 hardcoded defaults (£300k property, etc.) | All from user data; flag missing data instead | Remove & replace |
| **Growth rates** | 4.7% hardcoded in `LifePolicyStrategyService` only (others already use `AssumptionsService`) | From user's risk profile via `AssumptionsService` | Targeted fix |
| **Surplus income for gifting** | Manually summed in GiftingStrategyOptimizer | Use `ResolvesIncome` / `ResolvesExpenditure` traits | Small refactor |
| **RNRB eligibility** | Checks "direct descendants" | Explicitly exclude nieces/nephews/cousins/siblings | Message enhancement |
| **14-year rule** | Exists in TaxConfigService config | Must be enforced in NRB cumulation calculation | Logic enhancement |
| **Trust NRB avoidance** | 10-year charge calculated but no forward projection | Forward value calc using user's risk growth rate | New feature |
| **Life insurance checks** | `in_trust` field exists, basic check | Check joint life, whole of life, premium type | Enhancement |
| **Insurance premium estimates** | Hardcoded table in LifePolicyStrategyService | Move to TaxConfigService | Config migration |
| **Liquidity classification** | Cash+investments=liquid, pensions=semi-liquid | Cash/savings=liquid, investments=semi-liquid, pensions=illiquid | Reclassification |
| **Notifications** | None estate-specific | Gift 7-year reminders, trust anniversary, IHT recalc | New feature |
| **DB-driven recommendations** | Inline in EstateAgent | Follow InvestmentActionDefinition pattern | Future phase |

---

## Implementation Phases

### Phase 0: TaxConfigService Centralisation (Pre-requisite)
**Priority: CRITICAL — blocks other phases**
**Files modified: 5 | Files created: 0**

#### 0.1 Add Growth-by-Risk Mapping & Onboarding Estimates to Seeder

**File:** `database/seeders/TaxConfigurationSeeder.php`

Add to `assumptions` section:
```php
'growth_by_risk' => [
    'very_low' => 0.02,
    'low' => 0.03,
    'low_medium' => 0.04,
    'medium' => 0.05,
    'medium_high' => 0.06,
    'high' => 0.07,
],
```

> **Note:** The `growth_by_risk` mapping is accessed via `AssumptionsService`, NOT `TaxConfigService`. `AssumptionsService` already provides risk-based growth rate resolution — adding a `getGrowthRateForRisk()` method to `TaxConfigService` would duplicate it. The seeder data is still useful (e.g., for the trust NRB avoidance projection in Phase 3.2), but always access it through `AssumptionsService`.

Also add to seeder under `estate` section:
```php
'onboarding_estimates' => [
    'estimated_property_value' => 300000,
    'estimated_investment_value' => 50000,
    'estimated_savings_value' => 25000,
    'estimated_business_value' => 100000,
],
```

> These are legitimate approximations used only in `EstateOnboardingFlow::calculateEstimatedEstateValue()` for the onboarding flow — NOT used in actual IHT calculations.

#### 0.2 Add Insurance Premium Estimates to TaxConfigService

Add to seeder under `estate` or `insurance` section:
```php
'insurance_premium_estimates' => [
    'per_thousand_monthly' => [
        30 => ['male' => 0.95, 'female' => 0.80],
        35 => ['male' => 1.10, 'female' => 0.95],
        // ... through to 80
    ],
    'joint_life_second_death_factor' => 0.75,
],
```

#### 0.3 Add 2027 Pension IHT Inclusion Date

Add to `inheritance_tax` section:
```php
'pension_iht_inclusion' => [
    'effective_date' => '2027-04-06',
    'announced' => 'Autumn Budget 2024',
    'impact' => 'Unused DC pension pots included in taxable estate',
],
```

#### 0.4 Remove Hardcoded Growth Rates from Services

| File | Current State | Fix |
|------|--------------|-----|
| `app/Services/Estate/LifePolicyStrategyService.php` | **TRUE hardcode** — `INVESTMENT_RETURN_RATE = 0.047` constant, no constructor injection of `AssumptionsService`. | Add `AssumptionsService` constructor injection. Replace constant usage with `$this->assumptionsService->getEstateAssumptions($userId)['return_rate']`. Also move hardcoded premium table to `$this->taxConfig->get('insurance_premium_estimates')`. |
| `app/Services/Estate/LifeCoverCalculator.php` | Already defers to `AssumptionsService` with 4.7% as last-resort fallback. | Acceptable as-is. Optionally replace fallback with `AssumptionsService::getDefaults()['return_rate']` for consistency. |
| `app/Services/Estate/IHTCalculationService.php` | Already uses `AssumptionsService` via `getFallbackGrowthRate()` — 4.7% is only the last-resort fallback. Has NO constant named `DEFAULT_INVESTMENT_GROWTH_RATE`. | Acceptable as-is. No change needed. |
| `app/Services/Estate/FutureValueCalculator.php` | Property growth 3% | Verify property growth key exists in seeder assumptions section. Use `$this->taxConfig->getAssumptions()['property_growth']` (already seeded as 0.03). |

> **Important:** All code accessing the user's risk level must use `app(RiskPreferenceService::class)->getMainRiskLevel($user->id)` — NOT `$user->riskProfile->risk_level` (the `User` model has no `riskProfile` relationship).

#### 0.5 Remove EstateDefaults Estimated Values

**File:** `app/Constants/EstateDefaults.php`

Remove these constants (replace with user data checks in Phase 1):
- ~~`ESTIMATED_PROPERTY_VALUE`~~ — use actual property data
- ~~`ESTIMATED_INVESTMENT_VALUE`~~ — use actual investment data
- ~~`ESTIMATED_SAVINGS_VALUE`~~ — use actual savings data
- ~~`ESTIMATED_BUSINESS_VALUE`~~ — use actual business data
- ~~`DEFAULT_LIFE_EXPECTANCY`~~ — use actuarial tables with user's age/gender
- ~~`DEFAULT_CURRENT_AGE`~~ — use user.date_of_birth (block if missing)

Retain these (derived from TaxConfigService):
- `RNRB_TAPER_THRESHOLD` → change to read from `TaxConfigService::getInheritanceTax()['rnrb_taper_threshold']`
- `TRUST_SUGGESTION_THRESHOLD` → keep but source from TaxConfigService
- `COMBINED_NRB_THRESHOLD` → derive as `NRB * 2`
- `COMBINED_RNRB_THRESHOLD` → derive as `RNRB * 2`

Search for all usages of removed constants across services and replace with actual data lookups or readiness gate flags.

**File:** `app/Services/Onboarding/EstateOnboardingFlow.php`

> **Additional file identified by review.** `EstateOnboardingFlow::calculateEstimatedEstateValue()` uses all four estimated value constants (`ESTIMATED_PROPERTY_VALUE`, `ESTIMATED_INVESTMENT_VALUE`, `ESTIMATED_SAVINGS_VALUE`, `ESTIMATED_BUSINESS_VALUE`). Replace these with configurable values under `TaxConfigService::get('onboarding_estimates')` (seeded in Phase 0.1). These are legitimate approximations for the onboarding flow only — NOT used in actual IHT calculations.

---

### Phase 1: Data Readiness Gate
**Priority: HIGH — ensures no assumptions**
**Files modified: 3 | Files created: 1**

#### 1.1 Create EstateDataReadinessService

**File:** `app/Services/Estate/EstateDataReadinessService.php`

Implements the 12-check gate from the research document:

```php
class EstateDataReadinessService
{
    public function assess(User $user): array
    // Returns: [
    //   'can_proceed' => bool,
    //   'blocking' => [...],  // must have before any calc
    //   'warnings' => [...],  // improves accuracy
    //   'info' => [...],      // optional enhancements
    // ]
}
```

**Blocking checks (can_proceed = false if any fail):**
1. `date_of_birth` — required for life expectancy projection
2. `marital_status` — required for spouse exemption / transferable allowances
3. At least one asset with value > 0

**Warning checks (analysis continues but flagged):**
4. UK residency / domicile status
5. Property data (main residence vs renting → RNRB eligibility)
6. Liabilities (mortgage, debts)
7. Family members / dependents (RNRB direct descendants)
8. Lifetime gifts recorded
9. Will status

**Info checks (nice-to-have):**
10. Income & expenditure (gifting from income analysis)
11. Life insurance policies (trust status check)
12. Powers of Attorney

#### 1.2 Integrate into EstateAgent

**File:** `app/Agents/EstateAgent.php`

Add readiness check at the start of `analyze()`:
```php
$readiness = $this->readinessService->assess($user);
if (!$readiness['can_proceed']) {
    // Include readiness WITHIN the normal response envelope
    return [
        'can_proceed' => false,
        'readiness' => $readiness,
        'summary' => null,
        'iht_calculation' => null,
        'gifting_opportunities' => null,
        'trust_recommendations' => null,
        // ... all normal keys present but null
    ];
}
```

> **Critical:** The response must include all expected keys (set to null) so the Vuex store's `setAnalysis` mutation does not receive an unexpected shape. The `can_proceed` flag is checked by the frontend to determine whether to render analysis tabs or the missing data view.

Remove any auto-creation of default IHTProfile. If data is missing, surface it through the readiness response — never assume values.

Apply the readiness gate to `generateRecommendations()` as well as `analyze()`. Replace `EstateDefaults::DEFAULT_LIFE_EXPECTANCY` fallback at line 238 with an explicit check that returns early if life expectancy cannot be determined from user data.

Replace method parameter defaults in `step4AnnualGiftingStrategy()` and `step6PETGiftingStrategy()` that use `EstateDefaults::DEFAULT_LIFE_EXPECTANCY` with a required parameter (no default).

#### 1.3 Update Frontend

**File:** `resources/js/components/Estate/MissingDataAlert.vue`

**Substantial rewrite required** — the current `MissingDataAlert.vue` handles only 7 data types and routes everything to `/profile`. Must be expanded to:
- Display all 12 readiness checks grouped by severity (blocking, warning, info)
- Each check links to the SPECIFIC input form (not just /profile) — e.g., 'Add property' links to the property form, 'Add life insurance' links to the protection module
- `EstateDashboard.vue` must add a `can_proceed` computed property from `state.analysis.can_proceed` and conditionally render MissingDataAlert instead of the analysis tabs when `can_proceed = false`

**File:** `resources/js/components/Estate/EstateDashboard.vue`

Add `can_proceed` handling:
- When `state.analysis.can_proceed === false`, render `MissingDataAlert` with the full `readiness` object
- When `can_proceed` is true or absent (backward compatibility), render analysis tabs as normal
- All four tab components (`IHTPlanning`, `GiftingStrategy`, `LifePolicyStrategy`, `TrustPlanning`) remain unchanged — they only render when `can_proceed` is true

---

### Phase 2: IHT Calculation Enhancements
**Priority: HIGH — core accuracy improvements**
**Files modified: 4**

#### 2.1 PET/CLT Deduction from NRB

**File:** `app/Services/Estate/IHTCalculationService.php`

Deduct the **primary user's own** PETs and CLTs from the **user's own** NRB only. Do NOT apply this to the spouse's NRB block — `SpouseNRBTrackerService` already handles the deceased spouse's NRB usage calculation.

```php
// Primary user's own NRB deduction only:
$petsIn7Years = $user->gifts()
    ->where('gift_type', 'pet')
    ->where('gift_date', '>', today()->subYears(7))
    ->sum('gift_value');
$cltsIn7Years = $user->gifts()
    ->where('gift_type', 'clt')
    ->where('gift_date', '>', today()->subYears(7))
    ->sum('gift_value');
$nrbUsed = min($nrb, $petsIn7Years + $cltsIn7Years);
$availableNrb = $nrb - $nrbUsed;
```

**File:** `app/Services/Estate/SpouseNRBTrackerService.php`

Separately, extend the existing PET filter to also include CLTs. Currently `SpouseNRBTrackerService` only filters `gift_type = 'pet'` — CLTs are excluded. Change to `whereIn('gift_type', ['pet', 'clt'])` in the gift query.

#### 2.2 14-Year Rule Enforcement

**File:** `app/Services/Estate/IHTCalculationService.php`

> **Direction clarification:** The 14-year rule operates in BOTH directions:
> - **Direction A** (seeder config): Failed PETs reduce the NRB available for CLTs
> - **Direction B** (this implementation): Historical CLTs (7-14 years before death) reduce the NRB available for PETs in the final 7 years
>
> This phase implements Direction B. Direction A is already partially described in `TaxConfigService::getFourteenYearRule()` seeder data but is a separate implementation concern for a future phase.

When calculating NRB available for PETs in the final 7 years, also cumulate CLTs from years 7-14 before death:

```php
// CLTs made 7-14 years before death don't incur IHT themselves
// but they DO reduce the NRB available for PETs in the final 7 years
$clts7to14Years = $user->gifts()
    ->where('gift_type', 'clt')
    ->where('gift_date', '>', today()->subYears(14))
    ->where('gift_date', '<=', today()->subYears(7))
    ->sum('gift_value');
$nrbForPets = max(0, $nrb - $clts7to14Years - $cltsIn7Years);
```

**File:** `app/Services/Estate/SpouseNRBTrackerService.php`

Apply same 14-year CLT cumulation when calculating how much of the deceased spouse's NRB was used.

#### 2.3 RNRB Direct Descendant Clarification

**File:** `app/Services/Estate/IHTCalculationService.php`

In RNRB eligibility check, add explicit exclusion message:

```php
// When checking beneficiaries for RNRB
// Direct descendants: children, grandchildren, step-children
// NOT direct descendants: nieces, nephews, cousins, siblings
```

Update the message template:
```
"No Residence Nil Rate Band available — main residence must pass to direct
descendants (children, grandchildren, step-children). Nieces, nephews,
cousins, siblings, and other relatives are not direct descendants and
do not qualify for RNRB."
```

#### 2.4 Liquidity Reclassification

**File:** `app/Services/Estate/AssetLiquidityAnalyzer.php`

Change classification:
- **Liquid:** Cash accounts, savings accounts (easy access, notice), Cash ISAs, Premium Bonds, NS&I
- **Semi-Liquid:** Investment accounts (ISAs, GIAs, bonds) — can be sold but takes days/weeks, may incur CGT
- **Illiquid:** Properties, business interests, pensions (cannot be used to pay IHT)

Currently investments are classified as liquid — move to semi-liquid. Pensions currently semi-liquid — move to illiquid with note that they pass to beneficiaries but cannot fund IHT payment.

---

### Phase 3: Gifting & Trust Enhancements
**Priority: MEDIUM — accuracy and new projections**
**Files modified: 3 | Files created: 0**

#### 3.1 Standardise Surplus Income via Traits

**Files:** `app/Services/Estate/GiftingStrategyOptimizer.php`, `app/Services/Estate/PersonalizedGiftingStrategyService.php`

Add `ResolvesIncome` and `ResolvesExpenditure` traits to both `GiftingStrategyOptimizer` and `PersonalizedGiftingStrategyService`. Replace manual income field summation with the trait methods:

```php
use App\Traits\ResolvesIncome;
use App\Traits\ResolvesExpenditure;

class GiftingStrategyOptimizer
{
    use ResolvesIncome, ResolvesExpenditure;

    // BEFORE (manually summing User model fields):
    // $totalIncome = $employment + $selfEmployment + $rental + ...
    // $surplus = $totalIncome - ($monthlyExpenditure * 12)

    // AFTER (using standardised traits):
    $grossIncome = $this->resolveGrossAnnualIncome($user);
    $annualExpenditure = $this->resolveMonthlyExpenditure($user) * 12;
    $surplus = $grossIncome - $annualExpenditure;
}
```

The `ResolvesIncome` and `ResolvesExpenditure` traits provide standardised income/expenditure resolution across the codebase. Both gifting services must use these traits instead of manually summing User model income fields.

> **Note:** There is no `IncomeService` class or `income_profile` relationship on the User model. The traits are the correct mechanism.

#### 3.2 Trust NRB Avoidance Forward Projection

**File:** `app/Services/Estate/PersonalizedTrustStrategyService.php`

Add `AssumptionsService` and `RiskPreferenceService` constructor injection. Add method to calculate maximum initial settlement that stays below NRB at 10-year anniversary:

```php
public function calculateNRBAvoidanceProjection(User $user, float $plannedAmount): array
{
    $riskLevel = app(RiskPreferenceService::class)->getMainRiskLevel($user->id);
    $assumptions = $this->assumptionsService->getEstateAssumptions($user->id);
    $growthRate = $assumptions['return_rate'];
    $nrb = $this->taxConfig->getInheritanceTax()['nil_rate_band'];
    $maxInitial = $nrb / pow(1 + $growthRate, 10);

    $projectedValue = $plannedAmount * pow(1 + $growthRate, 10);
    $willExceedNrb = $projectedValue > $nrb;

    // Year-by-year trajectory
    $trajectory = [];
    for ($year = 0; $year <= 11; $year++) {
        $value = $plannedAmount * pow(1 + $growthRate, $year);
        $trajectory[] = [
            'year' => $year,
            'projected_value' => $value,
            'exceeds_nrb' => $value > $nrb,
        ];
    }

    return [
        'max_initial_settlement' => $maxInitial,
        'planned_amount' => $plannedAmount,
        'projected_at_10_years' => $projectedValue,
        'will_exceed_nrb' => $willExceedNrb,
        'estimated_periodic_charge' => $willExceedNrb
            ? $this->calculatePeriodicCharge($projectedValue, $nrb)
            : 0,
        'growth_rate_used' => $growthRate,
        'risk_level' => $riskLevel,
        'trajectory' => $trajectory,
    ];
}
```

Growth rate comes from `AssumptionsService` (which internally uses `RiskPreferenceService`) — never from a hardcoded default or `TaxConfigService`.

---

### Phase 4: Life Insurance & Pension Enhancements
**Priority: MEDIUM — regulatory compliance**
**Files modified: 4 | Files created: 1**

#### 4.0 Database Migration

Create migration adding `joint_life` boolean (default false) to `life_insurance_policies` table. Add to `LifeInsurancePolicy::$fillable` and `$casts`. Update the life insurance form (SavePolicyModal or equivalent) to include a joint life checkbox.

**File:** `database/migrations/xxxx_xx_xx_add_joint_life_to_life_insurance_policies.php`

```php
Schema::table('life_insurance_policies', function (Blueprint $table) {
    $table->boolean('joint_life')->default(false)->after('in_trust');
});
```

> **Note:** Without this migration, `$policy->joint_life` returns `null` silently in Laravel, meaning `!$policy->joint_life` is always `true` — every policy would be incorrectly flagged as non-joint, generating spurious warnings for married users.

#### 4.1 Enhanced Life Insurance Checks

**File:** `app/Services/Estate/LifeCoverCalculator.php`

Before recommending new cover, check existing policies:

```php
private function assessExistingPolicies(Collection $policies): array
{
    $warnings = [];
    foreach ($policies as $policy) {
        if (!$policy->in_trust) {
            $warnings[] = [
                'type' => 'not_in_trust',
                'policy_id' => $policy->id,
                'message' => "Policy {$policy->name} ({$policy->sum_assured}) is not in trust."
            ];
        }
        if ($policy->policy_type !== 'whole_of_life') {
            $warnings[] = [
                'type' => 'not_whole_of_life',
                'message' => "Policy is term cover expiring {$policy->end_date}. IHT cover requires whole of life."
            ];
        }
        if (!$policy->joint_life && $user->marital_status === 'married') {
            $warnings[] = [
                'type' => 'not_joint',
                'message' => "Single life policy. Joint life second death is more cost-effective for IHT cover."
            ];
        }
    }
    return $warnings;
}
```

#### 4.2 2027 Pension IHT Amendment

**File:** `app/Services/Estate/IHTCalculationService.php`

Add dual-scenario projection:

```php
public function calculateWithPensionAmendment(User $user, array $baseCalc): array
{
    $pensionInclusionDate = $this->taxConfig->get(
        'inheritance_tax.pension_iht_inclusion.effective_date'
    );

    // If projected death is after April 2027
    if ($user->projected_death_date > Carbon::parse($pensionInclusionDate)) {
        $pensionValue = $this->getPensionValuesForUser($user);
        $postAmendmentEstate = $baseCalc['net_estate'] + $pensionValue;
        $postAmendmentIHT = $this->calculateIHTOnEstate($postAmendmentEstate);

        return [
            'current_rules' => $baseCalc,
            'post_2027_rules' => [
                'net_estate' => $postAmendmentEstate,
                'pension_value_included' => $pensionValue,
                'iht_liability' => $postAmendmentIHT,
                'difference' => $postAmendmentIHT - $baseCalc['iht_liability'],
            ],
            'amendment_warning' => true,
        ];
    }

    return ['current_rules' => $baseCalc, 'amendment_warning' => false];
}
```

**File:** `app/Agents/EstateAgent.php`

Include pension amendment in analysis response when applicable.

**File:** Frontend — add a notification banner when `amendment_warning = true`.

---

### Phase 5: Notification System
**Priority: LOW — alerting improvements**
**Files created: 3 | Files modified: 2**

#### 5.1 Gift 7-Year Exemption Reminders

**File:** `app/Notifications/GiftExemptionNotification.php`

Notify when a gift approaches 7-year exemption:
- 6 months before: "Your gift of {amount} to {recipient} on {date} will become fully exempt from IHT in 6 months."
- On exemption: "Your gift of {amount} made on {date} is now fully exempt from IHT."

#### 5.2 Trust 10-Year Anniversary Alert

**File:** `app/Notifications/TrustAnniversaryNotification.php`

Notify 90 days before a relevant property trust's 10-year anniversary:
- "Your {trust_name} trust approaches its 10-year anniversary on {date}. A periodic charge may apply."

#### 5.3 Scheduled Command

**File:** `app/Console/Commands/SendEstateAlerts.php`

Runs daily. Checks:
- Gifts approaching 7-year exemption (6 months before)
- Trust 10-year anniversaries (90 days before)
- Annual IHT recalculation prompt (once per tax year)

Register in Kernel schedule.

> **Implementation notes:**
> - Use `today()->subYears(7)` not `now()->subYears(7)` when comparing against `gift_date` (date column, not datetime).
> - Add `User::chunk(100, ...)` to the scheduled command for scale.
> - Specify `return ['database']` in new notification `via()` methods to match the established project pattern.

---

## Dependency Graph

```
Phase 0 ──────────────────────────────────────────┐
(TaxConfigService + growth rates + EstateDefaults) |
    |                                               |
    v                                               |
Phase 1 ──────────────────────────────────────────┤
(Data Readiness Gate)                              |
    |                                               |
    v                                               |
Phase 2 ────────── Phase 3 ────────────────────────┤
(IHT calc fixes)   (Gifting/Trust enhancements)    |
    |                   |                           |
    v                   v                           |
Phase 4 ──────────────────────────────────────────┤
(Life insurance + 2027 pension amendment)          |
    |                                               |
    v                                               |
Phase 5 ──────────────────────────────────────────┘
(Notifications)
```

Phases 2 and 3 can run in parallel after Phase 1.

---

## Files Created (New)

| File | Purpose |
|------|---------|
| `app/Services/Estate/EstateDataReadinessService.php` | 12-check data readiness gate |
| `app/Notifications/GiftExemptionNotification.php` | 7-year gift exemption reminders |
| `app/Notifications/TrustAnniversaryNotification.php` | Trust 10-year anniversary alerts |
| `app/Console/Commands/SendEstateAlerts.php` | Daily estate alert scheduler |
| `database/migrations/xxxx_xx_xx_add_joint_life_to_life_insurance_policies.php` | Add `joint_life` boolean to life insurance policies table |

## Files Modified (Existing)

| File | Change |
|------|--------|
| `database/seeders/TaxConfigurationSeeder.php` | Add growth_by_risk, onboarding_estimates, insurance_premium_estimates, pension_iht_inclusion |
| `app/Constants/EstateDefaults.php` | Remove estimated value defaults, source thresholds from TaxConfigService |
| `app/Services/Estate/IHTCalculationService.php` | PET/CLT NRB deduction (primary user only), 14-year rule (Direction B), RNRB message, 2027 pension |
| `app/Services/Estate/SpouseNRBTrackerService.php` | 14-year CLT cumulation in NRB transfer calc, add CLTs to existing PET filter |
| `app/Services/Estate/AssetLiquidityAnalyzer.php` | Reclassify: investments→semi-liquid, pensions→illiquid |
| `app/Services/Estate/GiftingStrategyOptimizer.php` | Add `ResolvesIncome` / `ResolvesExpenditure` traits for surplus income |
| `app/Services/Estate/PersonalizedGiftingStrategyService.php` | Add `ResolvesIncome` / `ResolvesExpenditure` traits for surplus income |
| `app/Services/Estate/PersonalizedTrustStrategyService.php` | Add NRB avoidance forward projection using `AssumptionsService` + `RiskPreferenceService` |
| `app/Services/Estate/LifeCoverCalculator.php` | Add existing policy assessment (trust/joint/whole of life) |
| `app/Services/Estate/LifePolicyStrategyService.php` | Add `AssumptionsService` constructor injection, replace hardcoded `INVESTMENT_RETURN_RATE`, move premium table to TaxConfigService |
| `app/Services/Estate/FutureValueCalculator.php` | Use TaxConfigService for property growth rate |
| `app/Services/Onboarding/EstateOnboardingFlow.php` | Replace four estimated value constants with `TaxConfigService::get('onboarding_estimates')` |
| `app/Agents/EstateAgent.php` | Add readiness gate to `analyze()` and `generateRecommendations()`, remove `DEFAULT_LIFE_EXPECTANCY` fallback, pension amendment, enhanced analysis response with full envelope |
| `app/Models/LifeInsurancePolicy.php` | Add `joint_life` to `$fillable` and `$casts` |
| `resources/js/components/Estate/MissingDataAlert.vue` | Substantial rewrite: all 12 readiness checks, grouped by severity, specific form links |
| `resources/js/components/Estate/EstateDashboard.vue` | Add `can_proceed` computed property, conditionally render MissingDataAlert vs analysis tabs |
| `app/Models/NotificationPreference.php` | Add estate alert preferences |

---

## What This Plan Does NOT Include

1. **DB-driven action definitions** — The Estate module does not currently use the InvestmentActionDefinition pattern. Migrating to that pattern is a larger architectural decision that should be made across ALL modules simultaneously, not piecemeal. For now, recommendations remain inline in EstateAgent.
2. **Domicile/cross-border** — Already has basic support via TaxConfigService. Full multi-jurisdiction support is out of scope.
3. **PDF export of estate plan** — Useful but not part of the decision engine upgrade.
4. **IHT400 form generation** — Compliance reporting is a separate feature.

---

## Testing Strategy

| Phase | Tests |
|-------|-------|
| Phase 0 | Run full test suite. Verify no hardcoded 4.7%/3% remain via architecture test. |
| Phase 1 | Unit tests for each of 12 readiness checks. |
| Phase 2 | Unit tests for PET NRB deduction, 14-year rule, RNRB exclusion message. Use existing estate test fixtures. |
| Phase 3 | Unit test for trust NRB avoidance projection. Integration test for surplus income fetch. |
| Phase 4 | Unit tests for policy assessment warnings. Feature test for 2027 pension amendment dual-scenario. |
| Phase 5 | Unit tests for notification classes. Feature test for scheduled command. |
