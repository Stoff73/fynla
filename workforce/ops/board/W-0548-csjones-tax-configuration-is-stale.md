---
id: W-0548
title: csjones tax configuration is stale — retirement and goals projections 500 on every dashboard load
mission: new-user-run-2026-09-07
branch: null
owner: build-lead
reviewers: [quality-lead]
status: done
severity: medium
surfaces: [web, m]
created: 2026-09-09
source: new-user run, csjones 2026-09-07; production verified healthy 2026-09-09
prior_art_checked: 2026-09-09
prior_art_found: []
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Environment/data, **not application code**, and **csjones only**.

On csjones, `GET /api/plans/retirement` and `GET /api/goals/projection` return
**500** repeatedly on every dashboard load:

    pension.state_pension.age_schedule is missing from tax configuration.
    Reseed with TaxConfigurationSeeder — W-0197 retired current_spa and
    future_spa, and there is deliberately no scalar fallback.
      RuntimeException @ StatePensionAgeResolver.php:113

**Production is healthy** — both endpoints return 200 on fynla.org, so this did
not ship to live.

Confirmed the seeder is not at fault: on a freshly seeded local database
`TaxConfigService->get('pension.state_pension.age_schedule')` returns a 6-entry
array, and the seeder defines it at `TaxConfigurationSeeder.php:309`. csjones is
simply stale.

Until reseeded, any retirement, goals, estate or tax reconciliation performed on
csjones is untrustworthy — worth knowing before a persona run is scheduled there.

## Acceptance

- [ ] `php artisan db:seed --class=TaxConfigurationSeeder --force` run on csjones.
- [ ] Both endpoints return 200 on csjones.
- [ ] Consider what makes a staging database go stale unnoticed — the failure was
      silent to anyone not reading the network log.

## Outcome — done, 2026-09-10

Already resolved before this pass: `db:seed --force` runs in every csjones deploy
(`deploy/DEPLOY.md` step 6) and the 2026-09-08/09 deploys reseeded it. Verified
2026-09-10 on csjones: `pension.state_pension.age_schedule` returns the six-entry array,
`/api/plans/retirement` and `/api/goals/projection` return 200 for john, and the last
"age_schedule is missing" error in `laravel.log` is dated 2026-09-07 (15 that day, none
since). The deploy step is the guard against it going stale again.
