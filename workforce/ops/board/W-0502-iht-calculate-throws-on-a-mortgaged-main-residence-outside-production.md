---
id: W-0502
title: IHTCalculationService::calculate() throws on a mortgaged main residence everywhere except production, so the estate calculation 500s on staging for the most ordinary case there is
mission: w-0368-undivided-share-discount
branch: null
owner: build-lead
reviewers: [quality-lead]
status: done
closed: 2026-08-29
claimed_by: null
severity: high
surfaces: [web, m, ios]
created: 2026-08-26T00:00:00Z
claimed: 2026-08-26
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

---

## Correction and fix — 2026-08-26

### The trigger stated above is WRONG

This item said the trigger was "a main residence carrying a mortgage". **It is not.**
`chris@fynla.org` has exactly that — property 69, one mortgage — and
`calculate()` returns normally for him. I inferred the trigger from the one account
that failed instead of bisecting it, and the inference was wrong.

**The reproducible trigger is being the `joint_owner_id` of a property whose
`user_id` is somebody else.** Bisected: Chris alone is fine; add a property owned by
user 9 with Chris as joint owner and `calculate()` throws; delete it and he is fine
again.

`PropertyStore::forUserByType()` reads `forUserOrJoint`, so that property comes back,
and `sumMainResidenceNetShare()` then reads `$property->mortgages` — a relation the
store never loaded.

### Not fully explained, and said so

**Why the viewer's OWN mortgaged main residence does not throw, I could not
establish.** `relationLoaded('mortgages')` reports false for both properties, so both
should lazy-load and both should raise. Only the joint-owned one does. I stopped
digging into Eloquent's violation handling rather than keep spending on it, and the
fix does not depend on the answer.

### Fixed

`forUserByType()` now eager-loads `mortgages`. That is correct on its own merits
whatever the exact trigger: the store hands out models, both consumers of this read
subtract a mortgage from a residence value, and neither can ask for the relation
itself. It removes the throw off-production and an N+1 in production.

Verified against the reproduction: the case that threw now returns a liability of
£498,500. 786 passed across Estate, Estate services, Stores, Property and NetWorth.

### No regression guard — acceptance 2 NOT met

**I could not reproduce this in a test.** Three fixtures were tried — an individually
owned mortgaged home, the same with a direct descendant, and the joint-owner shape
that reproduces in tinker — and all three passed against the *unfixed* code. Whatever
distinguishes the seeded database from a factory-built fixture, I did not find it.

The test that exists (`IhtHandlesAMortgagedMainResidenceTest`) exercises the path and
asserts the share is taken rather than the whole, so it is not worthless — **but it
would not have caught this defect and must not be mistaken for a guard against it.**
Acceptance 2 stays open, and acceptance 3's sweep is untouched.

### Acceptance 3 — the sweep, done

Done properly rather than pattern-matched, having got the trigger wrong once already:
every relation read on a model returned by a store read, checked against whether that
read loads it.

| Consumer | Relation read | Source | State |
|---|---|---|---|
| `IHTCalculationService:2303` | `$property->mortgages` | `forUserByType()` | **was broken — fixed** |
| `IHTCalculationService:1273` | `$property->mortgages` | `forUserByType()` | same read, same fix |
| **`NetWorthService:372`** | `$property->mortgages` | **`forUserWithJointOwner()`** | **SECOND INSTANCE — proven to throw, fixed** |
| `IHTFormattingService:369` | `$mortgage->property` | `forUser()->load('property')` | safe |
| `ComprehensiveEstatePlanService:542` | `$mortgage->property` | `forUser()->load('property:id,...')` | safe |
| `NetWorthService:609` | `$account->jointOwner` | queries with `->with('jointOwner')` | safe |

**The sweep was worth doing: it found a second live instance.**
`forUserWithJointOwner()` loaded `jointOwner` and not `mortgages`, while
`NetWorthService` nets a property down by the debt secured on it. Proven to throw
before the fix, confirmed OK after.

**A pattern worth noticing, and worth CSJ's view.** The two safe `$mortgage->property`
sites are safe because each call site does its own `->load('property')` — the very
`load()`-at-the-call-site this item's acceptance 1 argues against. It works, and it
means the same knowledge lives in two consumers instead of one store. Deciding
whether stores should own eager-loading outright is bigger than this item.

758 passed across NetWorth, Stores, Estate and Estate services.

## Closed — 2026-08-29 (board reconciliation)

**Marked done from `dev` history, not from a fresh re-test.** Previous status was
`review`.

- **Delivered by:** Stoff73
- **Evidence:** commit `bde799b11` on `dev`

The board had drifted: the work landed on `dev` but the item was never restamped. This
records the evidence rather than deleting the item, so the fix can be re-checked against
it later. **If a re-test finds this unfixed, reopen it — a `done` here means "the change
is on `dev`", not "someone has re-verified the behaviour since."**
