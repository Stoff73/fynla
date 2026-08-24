# W-0138 — build-lead (`cycle1-surfaces`) → quality-lead

**Scope as dispatched by the team lead: the missing chattels class only.** The item as
originally written carries three faults; the tester's re-measurement against the complete
household narrowed it. Read `## Done` and `## Not done` together before judging it.

## Done

**One backend change. No frontend change on any surface, and no bundle rebuild.**

`CrossModuleAssetAggregator` — the service whose own docblock calls itself "a single
source of truth for cross-module assets" — aggregated property, investments and cash, and
no chattels. `NetWorthAnalyzer` builds `asset_composition` and `total_assets` from that
collection, `EstateController::getNetWorth` returns it, and **three clients read it**:
`/m` (`resources/mobile/views/modules/Estate.vue:133`), web
(`resources/js/services/estateService.js:54`) and native iOS
(`ios-native/Fynla/Features/Estate/EstateClient.swift:29`). So the class was absent for
all three from one place, and is now present for all three from that same place.

- `app/Services/Shared/CrossModuleAssetAggregator.php:184-212` — new `getChattelAssets()`,
  built exactly like its property/investment/savings siblings: `Chattel::forUserOrJoint()`
  plus `CalculatesOwnershipShare::calculateUserShare()`.
- `:75-79` — `getAllAssets()` concatenates them.
- `:214-227` `calculateChattelTotal()`; `:239` `getAssetTotals()` gains `chattel`;
  `:340-343` `getAssetBreakdown()` gains `chattel`.

**Every client already had the label.** `/m` maps `chattel → 'Possessions'`
(`resources/mobile/views/modules/Estate.vue:87`) and so does iOS
(`ios-native/Fynla/Features/Estate/EstateView.swift:292`). They were never sent the row.
**`/m` therefore needs no rebuild for this fix** — confirm that before deciding whether one
is wanted for other reasons.

### Rule 20 — the consolidation is part of the fix

Four mechanisms computed "this user's share of a chattel". Two are now one:

- `NetWorthService::calculateChattelValue()` (`app/Services/NetWorth/NetWorthService.php:123-126`)
  delegates to the aggregator. Same query, same trait, **identical result** — this is not
  an arithmetic change.
- `UserProfileService::calculateAssetsSummary()`
  (`app/Services/UserProfile/UserProfileService.php:667-671`) now reads
  `$breakdown['chattel']`. **This one does change arithmetic** — see Assumptions.

Two remain and I did **not** touch them, deliberately — see `## Not done`.

### Verified against the persona household, read-only

No write touched users 16 or 17. Invoking the real service:

| | property | investment | **chattel** | cash | total assets | liabilities | net |
|---|---|---|---|---|---|---|---|
| David 16 | 755,500 | 172,500 | **132,250** | 99,750 | **1,160,000** | 182,500 | **977,500** |
| Sarah 17 | 637,500 | 132,500 | **60,750** | 31,030 | **861,780** | 122,500 | **739,280** |

Every figure matches R-18 §1.1/§2.8 exactly, **including both ownership splits the tester
verified green** — Manchester's 40% inside David's £755,500 and absent from Sarah's.

**One correction to R-18, not to the code:** §2.8 gives David's expected net estate as
977,750. Its own inputs give 1,160,000 − 182,500 = **977,500**. £250 arithmetic slip in the
report; Sarah's 739,280 is exact.

### Tests

`tests/Feature/Estate/EstateNetWorthChattelsTest.php` — 3 tests, 16 assertions, through the
real HTTP endpoint with real records. Pins: the owner's chattels reach the endpoint at
their share; the joint owner gets the complement and not the individually-owned record; a
third account's £999,000 chattel reaches neither surface. **Confirmed red before the fix**
(2 failed) and green after — no mock supplies any asserted value.

## Not done, and why

1. **`/m` shows an individual estate where web shows a household one** (item fault 2) —
   out of the scope the team lead set. Still open.
2. **`/m` shows no Inheritance Tax figure under an "Inheritance tax exposure" subtitle**
   (fault 3) — same. Still open. **The item should not be closed on this handoff.**
3. **Business interests are still absent from the same aggregation.** `getAllAssets()` now
   has property, investments, cash and chattels; `EstateAssetAggregatorService` (the IHT
   path) also has business. `peak_earners` has no business interests so the run could not
   see it; `entrepreneur` would. **Reported, not fixed** — same hole, different class, and
   widening the scope silently is not mine to do. One method, modelled on the chattel one.
4. **Two chattel-share implementations survive.** `EstateAssetAggregatorService:143-163`
   (same query, same trait — a duplicate call site, not divergent arithmetic; refactoring
   it touches the IHT figures the tester just verified green at £338,712) and
   `MobileDashboardAggregator:349-350` (`sumUserShares` + `sumJointOwnerShares`, the /m
   dashboard, already includes chattels).
5. **`NetWorthService::getJointAssets()` (`:587`) reads `Chattel::where('user_id', …)`** so a
   joint asset where the user is the *joint* owner is missing from that list. Applies to
   every asset type there, not just chattels. **Adjacent, unraised, not fixed.**
6. **No browser verification** — by instruction; the tester closes that loop.

## What you need that isn't obvious from the artefacts

- **`/api/estate/net-worth` is gated `estate.full`.** A test user needs
  `withActivePremiumSubscription()` **and** `TierConfigurationSeeder`, or the endpoint 403s.
- **`generateSummary()` also returns `health_score`** — a 0-100 rating. No client renders it
  and I did not touch it, but it is a Rule 12 hazard sitting in a payload three surfaces read.
- **`ComprehensiveEstatePlanService` injects `NetWorthAnalyzer` and never calls it**
  (`:36`) — dead injection, so the change does not reach the comprehensive estate plan.
- Values come back from JSON as ints when whole; cast before `toBe(115000.0)`.

## Assumptions I made

- **That including chattels in the profile's `assets_summary` is a correction, not a
  regression.** The code it replaced read `$user->chattels` (a `user_id`-only relation) and
  multiplied by `ownership_percentage` unconditionally, so a user who was the *joint* owner
  of a chattel saw £0 of it, and an individually-owned record with a stored percentage below
  100 was scaled down when it is wholly theirs. `profile.net_worth` moves for such users.
  Nothing in the persona household is affected (all six chattels are primary-owned).
- That `chattel → 'Possessions'` is the intended label on `/m` and iOS. It was already
  written in both clients; I did not introduce or change it.
- That chattels belong in the adviser export pack's asset list —
  `AdviserExportPackService:60` consumes `getAllAssets()` and has no separate chattel
  section, so they now appear there once.

## Surfaces covered / not covered

- **Covered — `/m`, web, iOS**, all three from the one server change. No client edit, no
  `/m` bundle rebuild, no iOS rebuild.
- **Not covered — none by design.** Every surface that reads this endpoint is fixed.
