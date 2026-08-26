---
id: W-0502
title: IHTCalculationService::calculate() throws on a mortgaged main residence everywhere except production, so the estate calculation 500s on staging for the most ordinary case there is
mission: w-0368-undivided-share-discount
branch: null
owner: null
reviewers: [quality-lead]
status: open
claimed_by: null
severity: high
surfaces: [web, m, ios]
created: 2026-08-26T00:00:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-26
prior_art_found: [W-0501, W-0368]
prior_art_outcome: none
source: found during browser verification of W-0501 against seeded data, 2026-08-26
---

## Intent

`IHTCalculationService::sumMainResidenceNetShare()` (`:2297-2309`) reads
`$property->mortgages` on models returned by
`PropertyStore::forUserByType()` (`:85-90`), which is:

```php
return Property::forUserOrJoint($user->id)
    ->where('property_type', $propertyType)
    ->get();
```

**No `with('mortgages')`.** So the relation is lazy-loaded, and
`AppServiceProvider:217` sets:

```php
Model::preventLazyLoading(! app()->isProduction());
```

| Environment | Behaviour |
|---|---|
| **local, development, csjones staging** | `LazyLoadingViolationException` → the whole estate calculation **500s** |
| **production** | no exception; silently lazy-loads, one extra query per property |

## Reproduced

`chris@fynla.org` (id 101) on a freshly seeded local database:

```
calculate() direct: THROWS Illuminate\Database\LazyLoadingViolationException
  app/Services/Estate/IHTCalculationService.php:2303  __get
  app/Services/Estate/IHTCalculationService.php:2301  sum
```

The distinguishing factor is **a main residence carrying a mortgage**:

| User | Main residences | With a mortgage | `calculate()` |
|---|---|---|---|
| john@example.com | 2 | 0 | OK |
| jane@example.com | 1 | 0 | OK |
| chris@fynla.org | 2 | **1** | **throws** |

That is not an edge case. A mortgaged home is the ordinary case, and
`ChrisUserSeeder` — the persona described as "production-matching" — produces it.

## Pre-existing, and NOT from W-0501

Checked before reporting, because W-0501 landed the same day and newly routes
`EstateActionDefinitionService` through `calculate()`:

- `calculate()` throws when called **directly**, with no involvement from W-0501.
- Re-checked with `app/Services/Estate/` restored to `origin/dev`: **still throws**.

W-0501 does widen the blast radius — the estate action evaluator now reaches this
path, so a surface that previously never called `calculate()` now can — but it did
not create the defect.

## Why this has not been noticed

Production does not throw, so no customer-facing outage points at it. Staging does
throw, but only for a user whose main residence carries a mortgage — and the two
most-used test accounts, `john@` and `jane@`, have none. It was found by verifying
against `chris@`, who does.

Test fixtures share the blind spot: W-0501's own tests build properties with no
mortgages and pass.

## Acceptance

1. `forUserByType()` eager-loads `mortgages`, or `sumMainResidenceNetShare()` stops
   reaching for a relation it did not ask for. One or the other, not a
   `loadMissing()` sprinkled at the call site.
2. A test that builds a main residence **with a mortgage** and calls `calculate()`,
   run with `preventLazyLoading` ON — which is the default outside production, so
   simply having the fixture is enough.
3. Sweep `IHTCalculationService` for the same shape: any relation read on a model
   returned by a store method that does not eager-load it. `getUserMortgages`,
   `gatherUserAssets` and the projection paths are the obvious candidates.
4. Consider whether `preventLazyLoading` differing between production and everywhere
   else is what you want. It means staging can 500 where production merely runs
   slowly — the failure mode is louder off-production, which is defensible, but it
   also means production carries silent N+1s nobody is forced to fix.

## Related

- **W-0501** — routed the estate action evaluator through `calculate()`, which is how
  this surfaced.
- **W-0368** — `sumMainResidenceNetShare()` is the method that work added.
