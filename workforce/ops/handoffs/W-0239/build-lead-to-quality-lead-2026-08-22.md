# W-0239 — build-lead → quality-lead

Branch document: `workforce/branches/fixes/F-0022-cycle4-dashboard-module-totals-and-cache.md`

## Done

One observer (`UserDataCacheObserver`) calls one service
(`CacheInvalidationService`) for the owner, the co-owner **and each of their
spouses**, on 26 models. Three overlapping observers deleted; every key they held
checked into the service first.

Two gaps closed that no path covered before:

- `investment_analysis_{id}` — InvestmentAgent's own 24-hour key, cleared by
  nothing in the application. The module keys are now derived from the module list
  rather than hand-written, which is how it went missing.
- The **spouse** hop. `life_insurance_policies` has no `joint_owner_id`;
  `LifeCoverReach` finds the second life through `users.spouse_id` (W-0186). Both
  deleted observers followed only the two owner columns, so the write that produced
  the reported symptom could not invalidate the person it affected.

Verified live on the persona rows (`updated_at` touch only, no value changed):
David touching the joint savings row 53 → `mobile_dashboard_17` **cleared**; David
touching joint-life policy 7 → `mobile_dashboard_17` **cleared**.

`tests/Feature/Cache/DerivedFiguresInvalidateOnDataChangeTest.php` — 8 passing.

## Not done, and why

- **The TTL is unchanged at 86,400s**, per the dispatch. It is now labelled a
  backstop and the class docblock — which said "5-minute cache" beside a 24-hour
  constant — states what the code does.
- **Code-change staleness is not addressed.** A blob written before a deploy is not
  invalidated by the deploy. `deploy/DEPLOY.md:56,111` already runs
  `php artisan cache:clear`, so this is a **local development** exposure only; the
  21-hour stale dashboard was observed locally, where nothing clears after an edit.
  Raising it as an item would be raising a documented deploy step.
- **`User` is deliberately not observed** — written on every login and token
  refresh, and `UserProfileController` already invalidates the fields that matter.
  Stated in the observer docblock so it is not "fixed" later.

## What you need that isn't obvious from the artefacts

1. **Assert on the KEY, not on a rebuilt figure.** A test that reads the dashboard
   twice and compares numbers passes whenever the numbers happen not to have moved —
   including when nothing was invalidated. All 8 cases assert `Cache::has(...)`.
2. **Writes got cheaper, not dearer.** The deleted `RecommendationCacheObserver`
   instantiated three to six agents per model write to end up calling
   `Cache::forget`. The spouse lookup it replaces is one indexed query.
3. **`spouse_id` is not in `User::$fillable`.** A test that links spouses with
   `update(['spouse_id' => …])` silently does nothing and its assertions then pass
   for the wrong reason — this cost a diagnosis. Assign the property and `save()`;
   the suite asserts the link took.
4. **A stale blob written before this landed is still stale** until it expires or is
   cleared. Clear by hand before your first reading.

## Assumptions I made

- Household invalidation is correct, not over-broad: joint expenditure (W-0190) and
  joint-life cover (W-0186) both make one spouse's write change the other's figures.
- The 26-model list covers what feeds a derived figure. Anything writing outside a
  model event still needs an explicit call — `CoordinatingAgent::invalidateUserCache`
  is kept for that reason and its docblock, which claimed Fyn was the only writer of
  the mobile keys, is corrected.

## Surfaces covered / not covered

Backend-only; the endpoint is shared, so **web, `/m` and iOS** all benefit from the
one fix. No frontend change was needed for this item.
