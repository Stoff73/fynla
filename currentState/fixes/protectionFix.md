# Protection Module Fixes

**Date:** 21 February 2026
**Branch:** `protection-fixes`
**Scope:** All known issues from Protection.md Section 17 except #5 (No Spouse Policies)

---

## Scope Note

**Not addressed in this round:**

| # | Priority | Issue | Reason |
|---|----------|-------|--------|
| 5 | INFO | No Spouse Policies -- policies belong to individual users via `user_id` | Future enhancement requiring new data model (joint policy concept), cross-user policy aggregation, and frontend changes |

---

## Issues Addressed

### From Section 17 (Known Issues and Limitations)

| # | Priority | Issue | Fix |
|---|----------|-------|-----|
| 1 | MEDIUM | `critical_illness_score` and `income_protection_score` return 0 in `AdequacyScorer` | Implement real gap calculations in `CoverageGapAnalyzer`, then compute CI and IP scores from actual needs vs coverage |
| 2 | LOW | `EstateAgent::step3ExistingLifeCover()` described as using `$existingCover = 0` | Already resolved in current code -- EstateAgent queries `LifeInsurancePolicy` in trust. Minor improvement: also surface total non-trust cover for recommendations |
| 3 | LOW | Controller returns raw Eloquent models, no API Resources | Create API Resource classes for each policy type and `ProtectionProfile` |
| 4 | LOW | Disability and Sickness/Illness policies not seeded in `PreviewUserSeeder` | Add seeder methods for both policy types with persona data |
| 6 | LOW | Only `LifeInsurancePolicy` has `Auditable` trait | Add `Auditable` trait to `DisabilityPolicy` and `SicknessIllnessPolicy` |
| 7 | LOW | `StoreLifePolicyRequest` does not extend `BasePolicyRequest` | Refactor all Store/Update request classes to extend `BasePolicyRequest` |

### Additional Issues Discovered During Investigation

| # | Priority | Issue | Fix |
|---|----------|-------|-----|
| A1 | LOW | `getScoreColor()` returns `'orange'` for scores 40-59, violating design system Rule #9 | Replace `'orange'` with `'blue'` |
| A2 | LOW | `RecommendationEngine` CI recommendation uses `empty($collection)` which is always false for Eloquent collections | Replace with `->isEmpty()` check |
| A3 | LOW | `RecommendationEngine` IP recommendation depends on `income_protection_gap > 0` which is always 0 | Resolved by Issue #1 fix (real gap calculations) |

---

## Changes By File

### New Files

| File | Purpose |
|------|---------|
| `app/Http/Resources/Protection/LifeInsurancePolicyResource.php` | API Resource for life insurance policies |
| `app/Http/Resources/Protection/CriticalIllnessPolicyResource.php` | API Resource for critical illness policies |
| `app/Http/Resources/Protection/IncomeProtectionPolicyResource.php` | API Resource for income protection policies |
| `app/Http/Resources/Protection/DisabilityPolicyResource.php` | API Resource for disability policies |
| `app/Http/Resources/Protection/SicknessIllnessPolicyResource.php` | API Resource for sickness/illness policies |
| `app/Http/Resources/Protection/ProtectionProfileResource.php` | API Resource for protection profile |

### Modified Files

| File | Change |
|------|--------|
| `app/Services/Protection/CoverageGapAnalyzer.php` | Replace hardcoded `income_protection_gap`, `disability_coverage_gap`, `sickness_illness_gap` with real calculations |
| `app/Services/Protection/AdequacyScorer.php` | Implement real CI and IP scores; fix `getScoreColor()` to use `'blue'` instead of `'orange'` |
| `app/Services/Protection/RecommendationEngine.php` | Fix `empty()` on Eloquent collection to use `->isEmpty()` |
| `app/Models/DisabilityPolicy.php` | Add `Auditable` trait |
| `app/Models/SicknessIllnessPolicy.php` | Add `Auditable` trait |
| `app/Http/Requests/Protection/StoreLifePolicyRequest.php` | Extend `BasePolicyRequest`, remove duplicated common rules |
| `app/Http/Requests/Protection/StoreCriticalIllnessPolicyRequest.php` | Extend `BasePolicyRequest`, remove duplicated common rules |
| `app/Http/Requests/Protection/StoreIncomeProtectionPolicyRequest.php` | Extend `BasePolicyRequest`, remove duplicated common rules |
| `app/Http/Requests/Protection/StoreDisabilityPolicyRequest.php` | Extend `BasePolicyRequest`, remove duplicated common rules |
| `app/Http/Requests/Protection/StoreSicknessIllnessPolicyRequest.php` | Extend `BasePolicyRequest`, remove duplicated common rules |
| `app/Http/Requests/Protection/UpdateLifePolicyRequest.php` | Extend `BasePolicyRequest`, remove duplicated common rules |
| `app/Http/Requests/Protection/UpdateCriticalIllnessPolicyRequest.php` | Extend `BasePolicyRequest`, remove duplicated common rules |
| `app/Http/Requests/Protection/UpdateIncomeProtectionPolicyRequest.php` | Extend `BasePolicyRequest`, remove duplicated common rules |
| `app/Http/Requests/Protection/UpdateDisabilityPolicyRequest.php` | Extend `BasePolicyRequest`, remove duplicated common rules |
| `app/Http/Requests/Protection/UpdateSicknessIllnessPolicyRequest.php` | Extend `BasePolicyRequest`, remove duplicated common rules |
| `app/Http/Controllers/Api/ProtectionController.php` | Return API Resources instead of raw models |
| `app/Traits/PolicyCRUDTrait.php` | Accept resource class parameter for response transformation |
| `app/Agents/EstateAgent.php` | Surface total non-trust life cover alongside trust cover for recommendations |
| `database/seeders/PreviewUserSeeder.php` | Add `createDisabilityPolicies()` and `createSicknessIllnessPolicies()` methods; add to `deleteUserData()` |

---

## Implementation Details

### 1. Real Coverage Gap Calculations + Adequacy Scores

**Problem:** Three interconnected placeholders prevent the protection analysis from scoring critical illness and income protection:

1. `CoverageGapAnalyzer::calculateCoverageGap()` hardcodes `income_protection_gap => 0`, `disability_coverage_gap => 0`, `sickness_illness_gap => 0` even though it computes `total_income_coverage` and the `$needs` array has `income_protection_need`
2. `AdequacyScorer::calculateIndividualScores()` hardcodes `$ciScore = 0` and `$ipScore = 0`
3. `RecommendationEngine` income protection recommendation (line 62) checks `income_protection_gap > 0` which is always false

**Fix Part A -- CoverageGapAnalyzer:**

After STEP 5 (line 176), compute real income-based gaps:

```php
// STEP 5: Income-based policies (separate track from life cover allocation)
$totalIncomeCoverage = $coverage['income_protection_coverage']
                     + $coverage['disability_coverage']
                     + $coverage['sickness_illness_coverage'];

// Income protection need (60% of gross) vs total income coverage
$incomeProtectionNeed = $needs['income_protection_need'] ?? 0;
$incomeProtectionGap = max(0, $incomeProtectionNeed - $totalIncomeCoverage);

// Break down by policy type for granular reporting
$ipCoverage = $coverage['income_protection_coverage'] ?? 0;
$disabilityCoverage = $coverage['disability_coverage'] ?? 0;
$sicknessCoverage = $coverage['sickness_illness_coverage'] ?? 0;

// Individual gaps (proportional to need minus specific coverage)
// IP is the primary coverage; disability and sickness are supplementary
$ipSpecificGap = max(0, $incomeProtectionNeed - $ipCoverage);
$disabilityGap = $ipCoverage >= $incomeProtectionNeed ? 0 : max(0, $incomeProtectionGap - $disabilityCoverage);
$sicknessGap = ($ipCoverage + $disabilityCoverage) >= $incomeProtectionNeed ? 0 : max(0, $incomeProtectionGap - $disabilityCoverage - $sicknessCoverage);
```

Update the returned `gaps_by_category` array:

```php
'gaps_by_category' => [
    'human_capital_gap' => $humanCapitalGap,
    'debt_protection_gap' => $debtGap,
    'final_expenses_gap' => $finalExpensesGap,
    'education_funding_gap' => $educationGap,
    'income_protection_gap' => $incomeProtectionGap,
    'disability_coverage_gap' => $disabilityGap,
    'sickness_illness_gap' => $sicknessGap,
],
```

**Fix Part B -- AdequacyScorer:**

Replace the placeholder scores in `calculateIndividualScores()`:

```php
// Critical illness score
// CI need = 3x annual gross income (industry standard lump sum benchmark)
$ciNeed = ($needs['gross_income'] ?? 0) * 3;
$ciCoverage = $gaps['coverage_allocated']['ci_coverage'] ?? ($gaps['total_coverage'] - ($gaps['coverage_allocated']['debt_covered'] ?? 0) - ($gaps['coverage_allocated']['human_capital_covered'] ?? 0) - ($gaps['coverage_allocated']['final_expenses_covered'] ?? 0) - ($gaps['coverage_allocated']['education_covered'] ?? 0));
// Simpler: use the CI coverage directly from the coverage array passed via needs
$ciCoverage = $needs['critical_illness_coverage'] ?? 0;
$ciScore = $ciNeed > 0 ? (int) round(min($ciCoverage, $ciNeed) / $ciNeed * 100) : 100;

// Income protection score
// IP need = 60% of gross income (already in needs as income_protection_need)
$ipNeed = $needs['income_protection_need'] ?? 0;
$ipCoverage = $gaps['income_replacement_coverage'] ?? 0;
$ipScore = $ipNeed > 0 ? (int) round(min($ipCoverage, $ipNeed) / $ipNeed * 100) : 100;
```

To make this work, the `$needs` array (from `calculateProtectionNeeds()`) needs to also pass through the CI coverage, and the `$gaps` array already contains `income_replacement_coverage`. The `ProtectionAgent` calls `calculateIndividualScores()` via `generateScoreInsights()`, passing `$gaps` and `$needs`. We need to augment `$needs` with `critical_illness_coverage` from the `$coverage` array.

In `ProtectionAgent::analyze()`, when calling `generateScoreInsights()`:

```php
// Augment needs with coverage data for individual score calculation
$needs['critical_illness_coverage'] = $coverage['critical_illness_coverage'] ?? 0;

$scoreInsights = $this->adequacyScorer->generateScoreInsights(
    $overallScore, $gaps, $needs, $hasDependants
);
```

**Fix Part C -- getScoreColor:**

```php
// Before
$score >= 40 => 'orange',

// After (Rule #9: no amber/orange)
$score >= 40 => 'blue',
```

---

### 2. EstateAgent Integration (Already Partially Resolved)

**Problem as documented:** Section 17 states `step3ExistingLifeCover()` uses `$existingCover = 0`. However, the current code already queries `LifeInsurancePolicy::where('in_trust', true)` in the `analyze()` method and passes the sum through `$data['life_cover']['total_cover_in_trust']` to `step3ExistingLifeCover()`.

**Current state:** The integration is functional. `step3ExistingLifeCover()` reads `$lifeCover['total_cover_in_trust']` and computes `$usableCover = max(0, $existingCover - $liabilities)`.

**Remaining improvement:** Surface total non-trust life cover so the estate module can recommend putting policies in trust. Currently the EstateAgent only queries `in_trust = true`, missing the opportunity to recommend trust placement for non-trust policies.

**Fix:** In `EstateAgent::analyze()`, also query total life cover (not just in-trust):

```php
// Existing (keep)
$lifePoliciesInTrust = LifeInsurancePolicy::where('user_id', $userId)
    ->where('in_trust', true)
    ->get();

// New: also get non-trust policies for recommendation context
$lifePoliciesNotInTrust = LifeInsurancePolicy::where('user_id', $userId)
    ->where(function ($q) {
        $q->where('in_trust', false)->orWhereNull('in_trust');
    })
    ->get();
```

Add to the `life_cover` data array:

```php
'life_cover' => [
    'user_cover_in_trust' => (float) $lifePoliciesInTrust->sum('sum_assured'),
    'spouse_cover_in_trust' => (float) $spouseLifeCoverInTrust,
    'total_cover_in_trust' => (float) $lifePoliciesInTrust->sum('sum_assured') + $spouseLifeCoverInTrust,
    'total_cover_not_in_trust' => (float) $lifePoliciesNotInTrust->sum('sum_assured'),
    'policy_count' => $lifePoliciesInTrust->count(),
    'policies_not_in_trust_count' => $lifePoliciesNotInTrust->count(),
],
```

In `step3ExistingLifeCover()`, add a recommendation when non-trust policies exist:

```php
if ($lifeCover['policies_not_in_trust_count'] ?? 0 > 0) {
    $recommendations[] = [
        'category' => 'trust_planning',
        'priority' => 'medium',
        'step' => 3,
        'title' => 'Place Life Policies in Trust',
        'description' => sprintf(
            'You have %d life insurance %s totalling %s not written in trust. Policies in trust bypass the estate for IHT purposes.',
            $lifeCover['policies_not_in_trust_count'],
            $lifeCover['policies_not_in_trust_count'] === 1 ? 'policy' : 'policies',
            $this->formatCurrency($lifeCover['total_cover_not_in_trust'])
        ),
        'actions' => ['Contact your insurance provider to place existing policies in trust'],
    ];
}
```

---

### 3. API Resources

**Problem:** `ProtectionController::index()`, `storeProfile()`, `updateHasNoPolicies()`, and `PolicyCRUDTrait::storePolicy()`/`updatePolicy()` all return raw Eloquent models. This leaks internal structure (hidden attributes, appended fields, relationship loading inconsistencies).

**Fix:** Create an API Resource class for each policy type and the protection profile. Each resource selects the specific fields to expose.

Example `LifeInsurancePolicyResource`:

```php
class LifeInsurancePolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'policy_type' => $this->policy_type,
            'provider' => $this->provider,
            'policy_number' => $this->policy_number,
            'sum_assured' => (float) $this->sum_assured,
            'premium_amount' => (float) $this->premium_amount,
            'premium_frequency' => $this->premium_frequency,
            'policy_start_date' => $this->policy_start_date?->format('Y-m-d'),
            'policy_end_date' => $this->policy_end_date?->format('Y-m-d'),
            'policy_term_years' => $this->policy_term_years,
            'in_trust' => (bool) $this->in_trust,
            'is_mortgage_protection' => (bool) $this->is_mortgage_protection,
            'beneficiaries' => $this->beneficiaries,
            'indexation_rate' => $this->indexation_rate ? (float) $this->indexation_rate : null,
            'start_value' => $this->start_value ? (float) $this->start_value : null,
            'decreasing_rate' => $this->decreasing_rate ? (float) $this->decreasing_rate : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
```

Each of the 5 policy resource classes follows this pattern, exposing only the fields relevant to that policy type (e.g., `DisabilityPolicyResource` includes `coverage_type`, `occupation_class`; `SicknessIllnessPolicyResource` includes `conditions_covered`, `exclusions`).

`ProtectionProfileResource` wraps the profile fields.

Update `ProtectionController::index()`:

```php
return response()->json([
    'success' => true,
    'data' => [
        'profile' => new ProtectionProfileResource($profile),
        'policies' => [
            'life_insurance' => LifeInsurancePolicyResource::collection($lifePolicies),
            'critical_illness' => CriticalIllnessPolicyResource::collection($criticalIllnessPolicies),
            'income_protection' => IncomeProtectionPolicyResource::collection($incomeProtectionPolicies),
            'disability' => DisabilityPolicyResource::collection($disabilityPolicies),
            'sickness_illness' => SicknessIllnessPolicyResource::collection($sicknessIllnessPolicies),
        ],
    ],
]);
```

Update `PolicyCRUDTrait` to accept a resource class parameter:

```php
protected function storePolicy(
    string $modelClass,
    array $data,
    int $userId,
    string $policyTypeName,
    string $resourceClass = null
): JsonResponse {
    // ... existing logic ...

    $responseData = $resourceClass ? new $resourceClass($policy) : $policy;

    return response()->json([
        'success' => true,
        'message' => "{$policyTypeName} policy created successfully.",
        'data' => $responseData,
    ], 201);
}
```

The `ProtectionController` method calls then pass the appropriate resource class:

```php
return $this->storePolicy(
    LifeInsurancePolicy::class,
    $request->validated(),
    $userId,
    'Life insurance',
    LifeInsurancePolicyResource::class
);
```

---

### 4. Unseeded Policy Types

**Problem:** `PreviewUserSeeder` only seeds Life Insurance, Critical Illness, and Income Protection policies. Disability and Sickness/Illness are never seeded, meaning preview users never have these policy types, and the analysis pipeline is never tested with them.

**Fix:** Add two new methods to `PreviewUserSeeder` and call them from `seedPersona()`:

```php
$this->createDisabilityPolicies($user, $spouse, $data['disability_policies'] ?? []);
$this->createSicknessIllnessPolicies($user, $spouse, $data['sickness_illness_policies'] ?? []);
```

Add imports:

```php
use App\Models\DisabilityPolicy;
use App\Models\SicknessIllnessPolicy;
```

Example `createDisabilityPolicies()`:

```php
private function createDisabilityPolicies(User $user, ?User $spouse, array $policies): void
{
    foreach ($policies as $policy) {
        $owner = ($policy['owner'] ?? 'user') === 'spouse' && $spouse ? $spouse : $user;

        DisabilityPolicy::create([
            'user_id' => $owner->id,
            'provider' => $policy['provider'],
            'policy_number' => $policy['policy_number'] ?? null,
            'benefit_amount' => $policy['benefit_amount'],
            'benefit_frequency' => $policy['benefit_frequency'] ?? 'monthly',
            'deferred_period_weeks' => $policy['deferred_period_weeks'] ?? 4,
            'benefit_period_months' => $policy['benefit_period_months'] ?? null,
            'premium_amount' => $policy['premium_amount'],
            'premium_frequency' => $policy['premium_frequency'] ?? 'monthly',
            'occupation_class' => $policy['occupation_class'] ?? null,
            'policy_start_date' => $policy['policy_start_date'] ?? null,
            'policy_term_years' => $policy['policy_term_years'] ?? null,
            'coverage_type' => $policy['coverage_type'] ?? 'accident_and_sickness',
        ]);
    }
}
```

`createSicknessIllnessPolicies()` follows the same pattern, including `conditions_covered` and `exclusions` fields.

Update `deleteUserData()` to clean up both types:

```php
DisabilityPolicy::where('user_id', $user->id)->delete();
SicknessIllnessPolicy::where('user_id', $user->id)->delete();
```

Add persona data to at least 2 persona JSON files. Suggested placements:

| Persona | Disability | Sickness/Illness | Rationale |
|---------|-----------|-------------------|-----------|
| `peak_earners` (David & Sarah Mitchell) | David: Accident & sickness cover via employer | Sarah: NHS permanent health insurance | High earners typically have employer group schemes |
| `entrepreneur` (Alex Chen) | Personal accident cover | -- | Self-employed need personal cover |

---

### 5. Auditable Trait Consistency

**Problem:** `LifeInsurancePolicy`, `CriticalIllnessPolicy`, and `IncomeProtectionPolicy` all have the `Auditable` trait. `DisabilityPolicy` and `SicknessIllnessPolicy` do not, creating inconsistent audit trails across policy types.

**Fix:**

`DisabilityPolicy.php`:

```php
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisabilityPolicy extends Model
{
    use Auditable, HasFactory;
    // ...
}
```

`SicknessIllnessPolicy.php`:

```php
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SicknessIllnessPolicy extends Model
{
    use Auditable, HasFactory;
    // ...
}
```

The `Auditable` trait automatically hooks into `created`, `updated`, and `deleted` events. It skips preview users and test environments, so no side effects on seeded data.

---

### 6. StoreLifePolicyRequest Extends BasePolicyRequest

**Problem:** `BasePolicyRequest` exists with `commonRules()` (8 shared fields) and `mergeWithCommonRules()` helper, but **none** of the 10 Store/Update request classes extend it. All extend `FormRequest` directly, duplicating the 8 common field validations (provider, policy_number, sum_assured, premium_amount, premium_frequency, policy_start_date, policy_end_date, policy_term_years).

**Fix:** Refactor all 10 policy request classes to extend `BasePolicyRequest` and use `mergeWithCommonRules()` / `mergeWithCommonMessages()`.

Example refactored `StoreLifePolicyRequest`:

```php
class StoreLifePolicyRequest extends BasePolicyRequest
{
    public function rules(): array
    {
        $specificRules = [
            'policy_type' => ['nullable', Rule::in(['term', 'whole_of_life', 'decreasing_term', 'family_income_benefit', 'level_term'])],
            'in_trust' => ['nullable', 'boolean'],
            'is_mortgage_protection' => ['nullable', 'boolean'],
            'beneficiaries' => ['nullable', 'string', 'max:1000'],
            'indexation_rate' => ['nullable', 'numeric', 'min:0', 'max:0.10'],
        ];

        // Conditional rules based on policy type
        $policyType = $this->input('policy_type');
        if ($policyType === 'decreasing_term') {
            $specificRules['start_value'] = ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'];
            $specificRules['decreasing_rate'] = ['nullable', 'numeric', 'min:0', 'max:1'];
        } else {
            $specificRules['start_value'] = ['nullable'];
            $specificRules['decreasing_rate'] = ['nullable'];
        }

        return $this->mergeWithCommonRules($specificRules);
    }

    public function messages(): array
    {
        return $this->mergeWithCommonMessages([
            'policy_type.in' => 'Invalid policy type selected.',
        ]);
    }
}
```

The `authorize()` method is removed from each concrete class since `BasePolicyRequest` already returns `true`.

Apply the same pattern to all 10 request classes. Each keeps only its type-specific fields and delegates common rules to the base class.

**Note on `StoreLifePolicyRequest` simplification:** The current implementation has separate conditional blocks for `term`, `level_term`, `family_income_benefit`, `whole_of_life`, and `decreasing_term` that all define the same date rules. These can be collapsed since the common rules in `BasePolicyRequest` already handle `policy_start_date`, `policy_end_date`, and `policy_term_years`. Only `decreasing_term`'s extra fields (`start_value`, `decreasing_rate`) need conditional logic.

---

### A2. RecommendationEngine Collection Check Fix

**Problem:** Line 50 of `RecommendationEngine.php`:

```php
if ($gaps['gaps_by_category']['human_capital_gap'] > 0 && empty($profile->user->criticalIllnessPolicies))
```

`empty()` on an Eloquent Collection always returns `false` because even an empty Collection is a truthy object. This means the CI recommendation only fires when `human_capital_gap > 0` -- the `empty()` check is effectively a no-op rather than checking whether the user has no CI policies.

**Fix:**

```php
// Before
empty($profile->user->criticalIllnessPolicies)

// After
$profile->user->criticalIllnessPolicies->isEmpty()
```

This correctly checks whether the collection has zero items, so the CI recommendation fires only when the user has no existing CI policies AND has a human capital gap.

---

## Testing Requirements

| Fix | Test |
|-----|------|
| 1. Gap calculations | Verify `income_protection_gap` is non-zero when IP coverage < 60% of gross income; verify CI score reflects `critical_illness_coverage` vs `3 * gross_income`; verify IP score reflects `total_income_coverage` vs `income_protection_need` |
| 1. Score edge cases | User with no income: all scores should be 100 (no need); user with full coverage: scores should be 100; user with zero coverage and positive income: scores should be 0 |
| 2. Estate life cover | Verify `total_cover_not_in_trust` is populated; verify trust placement recommendation appears when non-trust policies exist |
| 3. API Resources | Verify `index()` response structure matches resource format; verify dates are ISO strings; verify numeric fields are cast to float |
| 4. Seeded policies | Run `PreviewUserSeeder`; verify `peak_earners` persona has disability and sickness/illness policies; verify `deleteUserData()` cleans them up |
| 5. Auditable trait | Create/update/delete a `DisabilityPolicy`; verify `audit_logs` table has entries |
| 6. BasePolicyRequest | Submit life policy without `provider` -- verify common validation message; submit with invalid `policy_type` -- verify type-specific message; verify no regression in existing CRUD flows |
| A1. Score color | Verify score of 45 returns `'blue'` not `'orange'` |
| A2. CI recommendation | User with human_capital_gap > 0 and no CI policies: recommendation appears; user with existing CI policies: recommendation does not appear |

---

## Implementation Order

| Order | Fix | Reason |
|-------|-----|--------|
| 1 | #6 Auditable trait | Two-line change per model, no dependencies, quick win |
| 2 | #1 Gap calculations + scores | Core analytical fix; unlocks IP recommendation in RecommendationEngine (A3) |
| 3 | A2 RecommendationEngine collection fix | Small fix, depends on understanding gap flow from #1 |
| 4 | A1 Score color fix | One-line change |
| 5 | #7 BasePolicyRequest refactor | Structural cleanup, no functional impact |
| 6 | #3 API Resources | New files + controller updates, no dependencies |
| 7 | #4 Unseeded policy types | Depends on models being correct (#6); involves persona JSON changes |
| 8 | #2 EstateAgent non-trust cover | Low priority improvement, partially cross-module with estateFix.md |
