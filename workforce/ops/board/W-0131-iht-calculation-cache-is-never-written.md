---
id: W-0131
title: The Inheritance Tax calculation cache is never written — `persist` is never passed true, so `iht_calculations` is empty for every user and every estate view recomputes in full
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-21T15:10:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0020, W-0046]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **batch B regression pass**, local
`localhost:8000`. Raised while verifying batch B's cache-staleness fix
(`charitableBequestFingerprint()`), which is itself correct.

**Surface:** `app/Services/Estate/IHTCalculationService.php` — reaches every estate
figure on web, `/m` and iOS, because all three read the same calculation.

### Expected

`iht_calculations` exists to cache the second-death Inheritance Tax projection, and
`getCachedCalculation()` (`:1493`) exists to serve it. A user loading the estate
surfaces repeatedly should be served the stored result while their assets, liabilities
and charitable bequests are unchanged, and a fresh calculation when any of those move.

### Actual

**The cache is never populated, so it is never read.** Not stale — dead.

1. Persistence is opt-in. `calculate()` writes only when `$persist === true`
   (`app/Services/Estate/IHTCalculationService.php:83` for the parameter, `:257` for
   the branch).
2. **No caller anywhere in `app/` passes it.** `grep -rn "persist: true" app/` returns
   exactly one hit, and it is the docblock at `:68` describing the intended usage.
   Every real caller uses the three-argument form:

   | Caller | Line |
   |---|---|
   | `Api/Estate/IHTController::calculateIHT` | `:55` |
   | `Api/Estate/TrustController` | `:204` |
   | `Services/Estate/ComprehensiveEstatePlanService` | `:86` |
   | `Agents/EstateAgent` | `:129`, `:1558` |
   | `Console/Commands/BackfillWillBequests` | `:192` |

3. `saveCalculation()` (`:1578`) is the only writer of the table in the entire
   codebase — `grep -rn "IHTCalculation::create\|new IHTCalculation" app/` returns
   nothing outside it.
4. The database agrees. **`iht_calculations` holds 0 rows**, across every user, not
   only the persona accounts:

   ```
   php artisan tinker --execute='echo DB::table("iht_calculations")->count();'
   → 0
   ```

So `getCachedCalculation()` returns `null` on every request, and every estate page view
recomputes the whole projection — asset aggregation for both spouses, the residence
nil-rate-band taper, the pension-amendment dual scenario and the year-by-year
projection to second death.

### Impact

Two distinct consequences, and the first is the reason this was found.

**1. It masks whether batch B's cache fix works.** W-0046 correctly identified that the
cache key was built from asset and liability hashes only, so a charitable legacy could
qualify an estate for the 36% rate while the user kept being served 40% from cache —
W-0020's fix silently defeated by cache. The fix
(`charitableBequestFingerprint()`, `:1535`, folded into `generateHashes()` at `:1554`
and `saveCalculation()` at `:1588`) is right, and its unit pin genuinely fails without
it. But **the described user harm cannot occur on any current path**, because there is
never a cached row to be served. Any browser check of the form "change a bequest and
watch the rate move" therefore passes for the wrong reason: it proves recalculation,
not invalidation. The fingerprint becomes load-bearing the moment anyone passes
`persist: true`, which is exactly why it should stay.

**2. The caching the table exists to provide is not happening.** Every estate view pays
the full computation. This is a latent performance cost rather than a correctness one,
but it is invisible: the table exists, the read path exists, the invalidation logic
exists, and none of it runs.

### Repro

```bash
grep -rn "persist: true" app/          # 1 hit, the docblock at IHTCalculationService.php:68
grep -rn "IHTCalculation::create" app/ # only saveCalculation()
php artisan tinker --execute='echo DB::table("iht_calculations")->count();'   # 0
```

Then load `/estate/inheritance-tax` as any user with estate data and re-run the count:
still 0.

### Acceptance

1. A decision is recorded on whether the Inheritance Tax calculation should be cached
   at all. Both answers are defensible — the honest outcomes are *turn the cache on* or
   *delete the table, the read path and the hashes*. What is not defensible is leaving
   a cache that is written by nothing.
2. If cached: at least one live path passes `persist: true`, `iht_calculations` gains
   rows for a user who has viewed the estate surfaces, and a second identical request
   is served from the row (evidenced by no recomputation, e.g. a log or a query count).
3. If cached: the staleness journey is then verified end to end in a browser — record a
   charitable legacy of 10%+ of the baseline, confirm the displayed rate moves 40% → 36%
   **with no other edit**, which is the check `charitableBequestFingerprint()` exists to
   make pass.
4. If deleted: `getCachedCalculation()`, `generateHashes()`, `saveCalculation()`, the
   fingerprint and the `iht_calculations` table go together, and no orphan remains.
5. Whichever way it goes, W-0046's fingerprint is not reverted while the cache lives.

### Notes

- Batch B's own document records the fingerprint fix at
  `workforce/branches/fixes/F-0003-batch-b-estate-wills.md` §2a. It also records
  **F17 (Low): cache invalidation is not spouse-aware on a mirror completion** — the
  fingerprint reads only the calculating user's own will, so a spouse's charitable
  bequest would not invalidate. That is dormant for the same reason and should be
  settled by the same decision.
- Nothing here argues the fingerprint was wasted work. It is the difference between a
  cache that would be wrong when switched on and one that would be right.
