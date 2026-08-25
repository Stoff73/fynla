# Risk System Fixes

**Date:** 21 February 2026
**Branch:** `risk-fixes`
**Scope:** Medium priority vulnerabilities (#4, #5, #6) and low priority items #7, #8, #9 from risk.md Section 17

---

## Scope Note

**Not addressed in this round:**

| # | Priority | Issue | Reason |
|---|----------|-------|--------|
| 1 | HIGH | Factor Weighting -- All Factors Equal | Deferred -- requires broader design decision on weighted scoring model |
| 2 | HIGH | No Factor Reaches `low` Easily | Deferred -- requires broader design decision on factor output ranges |
| 3 | HIGH | Mode Tie-Breaking Towards Lower Risk | Deferred -- arguably a feature (safety-first bias); needs product decision |
| 10 | LOW | No Spouse Risk Profile | Deferred -- future enhancement requiring new data model |

---

## Issues Addressed

### From Section 17 (Vulnerabilities)

| # | Priority | Issue | Fix |
|---|----------|-------|-----|
| 4 | MEDIUM | Goals Use Separate Numeric Risk System | Add explicit string-to-integer mapping in `GoalRiskService` |
| 5 | MEDIUM | Education Factor Oversimplification | Replace binary degree check with `knowledge_level` (novice/intermediate/experienced) from risk profile |
| 6 | MEDIUM | PropertyRiskObserver Doesn't Extend Base | Refactor to extend `RiskRecalculationObserver`, remove inline debounce |
| 7 | LOW | Surplus Cash Threshold of 500 is Hardcoded | Use percentage of monthly income instead of fixed amount |
| 8 | LOW | No Rate Limiting on Recalculate Endpoint | Add `throttle:6,1` middleware to the recalculate route |
| 9 | LOW | Stale Cache After Self-Select | Update `factor_breakdown` in DB when user self-selects risk level |

---

## Changes By File

### Modified Files

| File | Change |
|------|--------|
| `app/Services/Goals/GoalRiskService.php` | Add `RISK_LEVEL_STRING_MAP` constant mapping 5-level strings to integers 1-5; use it when `use_global_risk_profile` is true |
| `app/Services/Risk/AutoRiskCalculator.php` | Rewrite `calculateEducationFactor()` to use `knowledge_level` with 3 tiers; rewrite surplus cash thresholds to use percentage of income |
| `app/Observers/PropertyRiskObserver.php` | Extend `RiskRecalculationObserver`, remove private `dispatchRecalculation()` method |
| `app/Services/Risk/RiskPreferenceService.php` | Update `setMainRiskLevel()` to recalculate and store `factor_breakdown` alongside self-selected level |
| `routes/api.php` | Add `throttle:6,1` to the `POST /recalculate` route |

---

## Implementation Details

### 4. GoalRiskService String-to-Integer Mapping

**Problem:** When `use_global_risk_profile = true`, `GoalRiskService` assigns `$globalRiskProfile->risk_level` (a string like `'medium'`) to `$riskLevel`, which is then used as an array key into the integer-keyed `RISK_LEVELS` constant. PHP's loose comparison in `max(1, min(5, $riskLevel))` casts the string to `0`, which then clamps to `1` -- every global profile user silently gets Conservative (level 1) instead of their actual risk level.

**Fix:** Add an explicit mapping constant and use it:

```php
private const RISK_LEVEL_STRING_MAP = [
    'low' => 1,
    'lower_medium' => 2,
    'medium' => 3,
    'upper_medium' => 4,
    'high' => 5,
];
```

In `getRiskParameters()`, when `use_global_risk_profile` is true:

```php
if ($goal->use_global_risk_profile && $globalRiskProfile) {
    $globalLevel = $globalRiskProfile->risk_level;
    $riskLevel = self::RISK_LEVEL_STRING_MAP[$globalLevel] ?? 3;
}
```

This ensures the string `'medium'` correctly maps to integer `3` (Balanced), `'upper_medium'` to `4` (Growth), etc.

### 5. Education Factor -> Knowledge Level Factor

**Problem:** Factor 3 only checks whether the user has a degree or not (binary). This is a poor proxy for investment risk tolerance -- a plumber with 20 years of investment experience gets `lower_medium`, while a fresh graduate with zero financial knowledge gets `medium`.

**Fix:** Rewrite `calculateEducationFactor()` to use `knowledge_level` (already stored on the `risk_profiles` table) with three tiers. Rename the factor for clarity:

| Knowledge Level | Risk Level | Reasoning |
|-----------------|------------|-----------|
| `novice` (or null/empty) | `lower_medium` | Limited investment knowledge suggests a cautious approach |
| `intermediate` | `medium` | Some investment knowledge allows for moderate risk-taking |
| `experienced` | `upper_medium` | Strong investment knowledge supports higher risk tolerance |

The method will be renamed from `calculateEducationFactor` to `calculateKnowledgeFactor` internally, but the factor key will change to `knowledge_level` and the display name to "Investment Knowledge".

**Data access:** The user's `knowledge_level` is on the `risk_profiles` table, not the `users` table. The calculator will need to load the risk profile to access this. If no risk profile exists yet (first calculation), default to `novice`.

Updated components array:

```php
'components' => [
    'knowledge_level' => $knowledgeLevel,
]
```

**Frontend impact:** `RiskFactorDetailPage.vue` has custom layouts per factor type. The `education` factor type is handled in the data table layout. This will need updating to `knowledge_level` to match the new factor key. The icon remains `academic-cap`.

### 6. PropertyRiskObserver Standardisation

**Problem:** `PropertyRiskObserver` implements its own inline debounce logic rather than extending `RiskRecalculationObserver` like the other 5 observers. Functionally identical, but if the base class debounce logic ever changes, this observer won't inherit the update.

**Fix:** Change `PropertyRiskObserver` to extend `RiskRecalculationObserver`:

```php
class PropertyRiskObserver extends RiskRecalculationObserver
{
    private const RISK_RELEVANT_FIELDS = [
        'current_value',
        'purchase_price',
        'ownership_percentage',
    ];

    public function created(Property $property): void
    {
        $this->dispatchRecalculation($property->user_id, 'property_created');
    }

    public function updated(Property $property): void
    {
        if ($this->hasRiskRelevantChanges($property)) {
            $this->dispatchRecalculation($property->user_id, 'property_updated');
        }
    }

    public function deleted(Property $property): void
    {
        $this->dispatchRecalculation($property->user_id, 'property_deleted');
    }

    private function hasRiskRelevantChanges(Property $property): bool
    {
        foreach (self::RISK_RELEVANT_FIELDS as $field) {
            if ($property->isDirty($field)) {
                return true;
            }
        }
        return false;
    }
}
```

Removes: the private `dispatchRecalculation()` method and its `Cache` import (inherited from base now).

### 7. Surplus Cash -- Percentage-Based Thresholds

**Problem:** The `£500/month` threshold is hardcoded and doesn't scale with income. For a user earning £120k/year (£10k/month), £500 surplus is only 5% -- quite tight. For someone earning £20k/year (£1,667/month), £500 surplus is 30% -- very healthy.

**Fix:** Replace the fixed £500 threshold with percentage of monthly income:

| Condition | Level | Reasoning |
|-----------|-------|-----------|
| Surplus <= 0 (deficit) | `lower_medium` | No capacity to top up investments |
| Surplus > 0 but <= 10% of monthly income | `medium` | Modest capacity relative to earnings |
| Surplus > 10% of monthly income | `upper_medium` | Strong surplus relative to earnings |

**Edge case:** If monthly income is 0, treat any positive surplus as `medium` (likely receiving non-tracked income).

The description text will also be updated to reflect the percentage context:

```php
if ($surplus <= 0) {
    $level = 'lower_medium';
    $description = 'No monthly surplus means limited ability to top up investments if needed.';
} elseif ($monthlyIncome > 0 && ($surplus / $monthlyIncome) > 0.10) {
    $level = 'upper_medium';
    $description = sprintf(
        'Monthly surplus of %s (%.0f%% of income) allows regular investing and risk tolerance.',
        '£' . number_format($surplus, 0),
        ($surplus / $monthlyIncome) * 100
    );
} else {
    $level = 'medium';
    $description = 'Modest monthly surplus provides some capacity for investment contributions.';
}
```

The `components` array will additionally include `surplus_percent`:

```php
'components' => [
    'annual_income' => round($annualIncome, 2),
    'monthly_income' => round($monthlyIncome, 2),
    'monthly_expenditure' => round((float) $monthlyExpenditure, 2),
    'surplus' => round($surplus, 2),
    'surplus_percent' => $monthlyIncome > 0 ? round(($surplus / $monthlyIncome) * 100, 1) : null,
],
```

### 8. Rate Limiting on Recalculate Endpoint

**Problem:** `POST /api/investment/risk/recalculate` has no per-route throttle. The frontend calls this on every visit to `/risk-profile`, and each call runs 7 database queries plus a net worth calculation.

**Fix:** Add throttle middleware to the specific route in `routes/api.php`:

```php
Route::post('/recalculate', [RiskPreferenceController::class, 'recalculate'])
    ->middleware('throttle:6,1');
```

This limits to 6 recalculations per minute per user -- more than enough for normal usage (page visit + manual recalculate button) while preventing abuse. The existing `auth:sanctum` middleware already identifies the user for per-user throttling.

### 9. Update factor_breakdown on Self-Select

**Problem:** When a user self-selects their risk level via `setMainRiskLevel()`, only `risk_level`, `is_self_assessed`, and `risk_assessed_at` are updated. The `factor_breakdown` JSON in the database is left stale from the last auto-calculation. While the API always recalculates factors live (no functional impact), the stored JSON is misleading for direct database queries or audit.

**Fix:** Update `setMainRiskLevel()` in `RiskPreferenceService` to also recalculate and store the current factor breakdown:

```php
public function setMainRiskLevel(int $userId, string $riskLevel): RiskProfile
{
    if (! isset($this->riskLevelOrder[$riskLevel])) {
        throw new \InvalidArgumentException("Invalid risk level: {$riskLevel}");
    }

    // Recalculate factor breakdown for audit accuracy
    $calculator = app(AutoRiskCalculator::class);
    $user = User::findOrFail($userId);
    $calculated = $calculator->calculateRiskProfile($user);

    $riskProfile = RiskProfile::updateOrCreate(
        ['user_id' => $userId],
        [
            'risk_level' => $riskLevel,
            'factor_breakdown' => $calculated['factor_breakdown'],
            'risk_assessed_at' => now(),
            'is_self_assessed' => true,
        ]
    );

    $this->clearUserCache($userId);

    return $riskProfile;
}
```

This ensures the stored JSON always reflects the factors at the time of the decision, regardless of whether it was auto-calculated or self-selected.

---

## Frontend Changes

### RiskFactorDetailPage.vue

The factor key change from `education` to `knowledge_level` (fix #5) requires updating `RiskFactorDetailPage.vue`:

- Update the data table layout condition to check for `knowledge_level` instead of `education`
- Update the threshold levels display to show 3 tiers (novice/intermediate/experienced) instead of 2 (degree/no degree)
- Update the "what would need to change" text to reference investment knowledge rather than education level

### RiskProfilePage.vue / RiskProfileSummary.vue

No structural changes needed -- these components render factor cards dynamically from the API response. The new factor key, display name, and description will flow through automatically.

### RiskFactorDetailPage.vue -- Surplus Cash

The surplus cash factor detail page may reference the £500 threshold in its threshold display. Update to show percentage-based thresholds instead.

---

## Testing

| Test | What to verify |
|------|----------------|
| GoalRiskService mapping | Global risk profile `'medium'` maps to integer 3 (Balanced), `'high'` maps to 5 (Aggressive), etc. |
| GoalRiskService fallback | Missing or null `risk_level` defaults to 3 |
| Knowledge factor -- novice | User with `knowledge_level = 'novice'` or null gets `lower_medium` |
| Knowledge factor -- intermediate | User with `knowledge_level = 'intermediate'` gets `medium` |
| Knowledge factor -- experienced | User with `knowledge_level = 'experienced'` gets `upper_medium` |
| PropertyRiskObserver | Creating/updating/deleting properties still triggers recalculation (no regression) |
| Surplus percentage -- low earner | £1,667/month income, £100 surplus (6%) -> `medium` |
| Surplus percentage -- high earner | £10,000/month income, £2,000 surplus (20%) -> `upper_medium` |
| Surplus percentage -- deficit | Negative surplus -> `lower_medium` |
| Surplus percentage -- zero income | £0 income, any positive surplus -> `medium` |
| Throttle -- recalculate | 7th call within 1 minute returns 429 |
| Self-select factor storage | After self-selecting, `factor_breakdown` JSON in DB is current (not stale) |

---

## Deployment Notes

**Files to upload:**

1. `app/Services/Goals/GoalRiskService.php`
2. `app/Services/Risk/AutoRiskCalculator.php`
3. `app/Observers/PropertyRiskObserver.php`
4. `app/Services/Risk/RiskPreferenceService.php`
5. `routes/api.php`
6. `resources/js/views/Risk/RiskFactorDetailPage.vue` (if changed)
7. `public/build/` (rebuild via `./deploy/fynla-org/build.sh`)

**Post-upload:**

```bash
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```

No database migrations required.
