# Cascading Financial Recommendations with Per-Action Charts

## Context

The retirement plan currently shows all actions as a flat list with one aggregate chart. The user wants each action to have its own what-if chart with a toggle, and actions must **cascade**: action 1's result becomes action 2's baseline. Toggling off any action reactively updates all subsequent charts.

This transforms the "Recommended Actions" section from a list + one chart into a series of action cards, each paired with its own before/after projection chart.

---

## Backend — `RetirementPlanService.php`

### 1. Enrich actions with cascade parameters

Add a new method `enrichActionsWithCascadeParams()` called after `prepareActions()` in `generatePlan()`.

For each action, compute `cascade_params.additional_monthly` — the monthly amount this action adds to the pension pot:

- **contribution** type: Allocate from `DistributionAccount` (same as existing `buildWhatIfData` logic — 30% of monthly disposable, capped by remaining budget)
- **tax_optimisation** type: `projected_income * 0.03 / 12` (tax relief reclaimed as monthly equivalent)
- **consolidation** type: `projected_income * 0.02 / 12` (fee savings as monthly equivalent)
- **default** type: `projected_income * 0.01 / 12` (small improvement as monthly equivalent)

Uses same rates from `PlanConfigService` (`getTaxOptimisationGain()`, `getConsolidationEfficiencyGain()`, `getDefaultActionGain()`).

### 2. Add `current_annual_contribution` to `frontend_calc_params`

Sum all DC pension annual contributions (employee + employer) so the frontend can compute realistic base trajectories including existing contributions.

```php
'frontend_calc_params' => [
    'current_dc_value' => $currentDcValue,
    'current_annual_contribution' => $totalAnnualContribution, // NEW
    'growth_rate' => $this->planConfig->getDefaultGrowthRate(),
    'years' => $yearsToRetirement,
    'annuity_rate' => $this->planConfig->getWithdrawalRate(),
],
```

### 3. Action data shape (after enrichment)

Each action in the `actions` array gets:
```php
'cascade_params' => [
    'additional_monthly' => 150.00,  // monthly pot contribution from this action
]
```

---

## Frontend

### New file: `CascadingActionChart.vue`

Small per-action chart showing before vs after for a single action.

**Props:** `beforeSeries` (array), `afterSeries` (array), `years` (int), `differenceAmount` (float)

- ApexCharts line chart, 180px height
- 2 series: "Before" (slate CHART_COLORS[1]), "After this action" (green CHART_COLORS[2])
- If difference > 0, show green badge: "+£X at retirement"
- Same chart styling as `PensionGrowthProjectionChart` (toolbar off, smooth curves, compact y-axis labels)
- Located at `resources/js/components/Plans/Retirement/CascadingActionChart.vue`

### Modified: `RetirementGroupedActions.vue`

Replace the current "list of cards then one chart" layout with cascading action cards, each with its own chart.

**Key computed property — `cascadedActions`:**

```javascript
cascadedActions() {
    const params = this.whatIf?.frontend_calc_params || {};
    const baseValue = params.current_dc_value || 0;
    const growthRate = params.growth_rate || 0.05;
    const years = params.years || 10;
    const baseAnnualContrib = params.current_annual_contribution || 0;
    const sorted = this.sortByPriority(this.allActions);

    let cumulativeAdditionalMonthly = 0;

    return sorted.map((action) => {
        // "before" = base + cumulative effect of all prior ENABLED actions
        const beforeMonthly = cumulativeAdditionalMonthly;
        const beforeSeries = this.projectSeries(baseValue, baseAnnualContrib, beforeMonthly, growthRate, years);

        // "after" = before + this action's effect (if enabled)
        const actionMonthly = action.cascade_params?.additional_monthly || 0;
        const afterMonthly = beforeMonthly + actionMonthly;
        const afterSeries = this.projectSeries(baseValue, baseAnnualContrib, afterMonthly, growthRate, years);

        // Accumulate for next action (only if this action is enabled)
        if (action.enabled) {
            cumulativeAdditionalMonthly += actionMonthly;
        }

        return { action, beforeSeries, afterSeries };
    });
}
```

**Helper method — `projectSeries`:**

```javascript
projectSeries(startValue, baseAnnualContrib, additionalMonthly, growthRate, years) {
    const totalAnnual = baseAnnualContrib + (additionalMonthly * 12);
    const series = [];
    let value = startValue;
    for (let y = 0; y <= years; y++) {
        series.push(Math.round(value));
        value = (value + totalAnnual) * (1 + growthRate);
    }
    return series;
}
```

**Template structure (single pension path):**

```html
<div v-for="item in cascadedActions" :key="item.action.id" class="mb-4">
    <PlanActionCard :action="item.action" @toggle="$emit('toggle', $event)" />
    <CascadingActionChart
        :before-series="item.beforeSeries"
        :after-series="item.afterSeries"
        :years="portfolioYears"
        :difference-amount="item.afterSeries[item.afterSeries.length-1] - item.beforeSeries[item.beforeSeries.length-1]"
    />
</div>
```

**What-if summary metrics remain at the bottom** showing the cumulative effect of all enabled actions (existing RetirementWhatIfControls, unchanged).

### No changes to: `PlanActionCard.vue`, `RetirementPlanContent.vue`, `PensionGrowthProjectionChart.vue`

The multi-pension path (pensionGroups) keeps per-pension charts via `PensionGrowthProjectionChart`. Cascading applies to the single-pension and portfolio-level actions.

---

## Files to Create (1)

| File | Purpose |
|------|---------|
| `resources/js/components/Plans/Retirement/CascadingActionChart.vue` | Per-action before/after chart |

## Files to Modify (2)

| File | Change |
|------|--------|
| `app/Services/Plans/RetirementPlanService.php` | Add `enrichActionsWithCascadeParams()`, add `current_annual_contribution` to frontend_calc_params |
| `resources/js/components/Plans/Retirement/RetirementGroupedActions.vue` | Replace flat list + one chart with cascaded action cards, each with own chart |

---

## Verification

1. `php artisan db:seed` — reseed before testing
2. Login as peak_earners → Retirement Plan → Recommended Actions section
3. Each action has its own chart below the toggle card
4. Toggle off the first action → all subsequent charts shift down (their "before" lines change)
5. Toggle all actions off → charts collapse (before = after = current trajectory)
6. Toggle all actions on → last action's "after" matches the what-if projected DC value at bottom
7. What-if metrics at the bottom still show cumulative current vs projected
8. `./vendor/bin/pest tests/Unit/Services/Plans/` — existing tests pass
9. No amber/orange colours (Rule 9), currency via currencyMixin (Rule 6)
