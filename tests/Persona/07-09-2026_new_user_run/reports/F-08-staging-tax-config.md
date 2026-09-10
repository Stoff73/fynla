# F-08 · Staging tax configuration is stale — retirement & goals return 500

> **VERIFIED 2026-09-09: csjones ONLY. Production is healthy.**
> On fynla.org both `GET /api/plans/retirement` and `GET /api/goals/projection`
> return **200**. This is an environment/data problem on csjones, not a code
> defect, and it did **not** ship to live.

**Env:** csjones staging · **Type:** environment/data, not application code

## Symptom

Repeated `500`s on the dashboard:

- `GET /api/plans/retirement` → 500
- `GET /api/goals/projection` → 500

Console shows these firing many times per dashboard load.

## Cause (from the response body — self-documenting)

```
Generating retirement plan failed: pension.state_pension.age_schedule is missing
from tax configuration. Reseed with TaxConfigurationSeeder — W-0197 retired
current_spa and future_spa, and there is deliberately no scalar fallback to
silently stand in for the schedule.
  RuntimeException @ StatePensionAgeResolver.php:113
```

The csjones database has not been reseeded since W-0197 changed the State
Pension age representation.

## Impact on testing

Anything depending on State Pension age is unreliable on staging right now —
retirement projections and goals projections are outright 500ing. Estate and tax
reconciliation work should be treated as untrustworthy on this environment until
it is reseeded.

## Fix

Per CLAUDE.md's troubleshooting table, on the csjones server:

```
php artisan db:seed --class=TaxConfigurationSeeder --force
```

Needs SSH, which is not yet available on this machine.

## Credit where due

The error message is exemplary — it names the missing key, the fix, the work
item that caused the change, and explicitly states that the absence of a
fallback is deliberate. That is what every failure message should look like.
