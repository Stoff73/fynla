# Enhance Goals Section with Detail View

## Context

The retirement plan goals section currently shows compact cards (name, type, progress bar, months remaining, contribution). The user wants each goal to show a detail view: what the goal is, why it's there, and what's needed to achieve it.

## Backend — `BasePlanService.php` (`formatGoalForPlan`)

Add two fields already on the Goal model but not currently included:

```php
'description' => $goal->description,
'is_essential' => (bool) $goal->is_essential,
```

## Frontend — `PlanGoalSection.vue`

Expand each linked goal card below the existing progress bar. Keep all existing content (header, status badge, progress bar, meta row) unchanged.

**Add below the progress bar / above the meta row:**

1. **Description** — `goal.description` shown in gray italic text (hidden if empty)
2. **Detail grid** — 2x2 on mobile, 4-col on md+, using small metric blocks (same `bg-gray-50 rounded-lg p-3` pattern as state pension metrics):
   - **Priority** — capitalised, with "Essential" suffix badge if `is_essential`
   - **Target Date** — formatted "DD Month YYYY", or "—"
   - **Amount Remaining** — `target_amount - current_amount`, currency formatted
   - **Required Monthly** — `required_monthly_contribution` formatted; text colour green if current `monthly_contribution >= required`, red if less

## Files to Modify (2)

| File | Change |
|------|--------|
| `app/Services/Plans/BasePlanService.php` | Add `description`, `is_essential` to `formatGoalForPlan()` |
| `resources/js/components/Plans/Shared/PlanGoalSection.vue` | Add description text + detail metric grid inside linked goal cards |

## Verification

1. Login as peak_earners (David Mitchell) — goals show description, priority, target date, amount remaining, required monthly
2. Login as young_saver (John Morgan) — goals with no description show no description row
3. `./vendor/bin/pest tests/Unit/Services/Plans/` — all pass
