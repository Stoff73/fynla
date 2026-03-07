# Goal Completion Action with Lump Sum Calculation

## Context

Each goal card in the retirement plan needs an action block beneath it showing: the lump sum needed to complete the goal before tax year end (5 April), with a clear calculation (`target - current = lump sum`), and a recommended funding account.

## Backend — `BasePlanService.php` (`formatGoalForPlan`)

Add linked account name to the goal data so the frontend can recommend a funding source:

```php
'linked_account_name' => $this->getLinkedAccountName($goal),
```

New private helper method:
```php
private function getLinkedAccountName(Goal $goal): ?string
{
    if ($goal->linked_investment_account_id) {
        $account = $goal->linkedInvestmentAccount;
        return $account?->account_name ?? $account?->provider ?? null;
    }
    if ($goal->linked_savings_account_id) {
        $account = $goal->linkedSavingsAccount;
        return $account?->account_name ?? $account?->provider ?? null;
    }
    return null;
}
```

## Frontend — `PlanGoalSection.vue`

Add an action block below each **incomplete** linked goal card (where `progress_percentage < 100`):

- Light blue-50 rounded box with border
- Header: "Action to Complete Goal"
- Calculation line: `{target} − {current} = {lump sum} lump sum needed`
- Deadline: "Before tax year end — 5 April {year}"
- Funding source: "Recommended source: {linked_account_name}" (or "Link an account to identify a funding source" if none)

Tax year end date derived from current date: if before 6 April → 5 April this year; if after → 5 April next year.

## Files to Modify (2)

| File | Change |
|------|--------|
| `app/Services/Plans/BasePlanService.php` | Add `linked_account_name` to `formatGoalForPlan()`, add helper |
| `resources/js/components/Plans/Shared/PlanGoalSection.vue` | Add action block below each incomplete goal card |

## Verification

1. Login as peak_earners — goal shows lump sum calculation and linked account name
2. Unlinked goals show "Link an account" prompt instead
3. Completed goals (100%) don't show the action block
4. `./vendor/bin/pest tests/Unit/Services/Plans/` — all pass
