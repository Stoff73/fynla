---
id: W-0239
title: A 24-hour cache served a wrong dashboard for 21 hours and hid a fix that had already shipped — the comment beside it said "invalidated on data change" and nothing did
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0022-cycle4-dashboard-module-totals-and-cache.md
owner: build-lead
status: handoff
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T18:30:00Z
claimed: 2026-08-22T19:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: [W-0186, W-0190, F-0019]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Raised by `peak-earners-c4` in cycle 4. Sarah Jones's cached dashboard, stamped
21:59:10 the previous evening, was still being served the following afternoon.
W-0186's fix had shipped that morning and was invisible to her; the moment the key
was cleared by hand her Protection card read £500,000.

### The defect

`MobileDashboardAggregator.php:37`:

```php
private const CACHE_TTL = 86400; // 24 hours — invalidated on data change
```

Three things were wrong at once, and the comment is the reason none of them were
noticed.

1. **The class docblock said "Uses a 5-minute cache per user"** — five lines above a
   constant of 86,400 seconds. The same disease as W-0226: the code was correct for
   the behaviour in its comment and wrong for the behaviour in the constant, so a
   reviewer checking one against the other would pass it.

2. **The invalidation that did exist was reached by coincidence.**
   `CacheInvalidationService::invalidateForUser()` clears `mobile_dashboard_{id}`
   and was called from ~17 controllers — but on the model-write path it was reached
   only because `RecommendationCacheObserver` always poked `CoordinatingAgent`,
   whose override happens to call the service. Three hops, none of them named the
   dashboard, and `investment_analysis_{id}` — InvestmentAgent's own 24-hour key —
   was in none of them. **No path in the application cleared it.**

3. **Household reach was missing.** W-0186 made a joint-life policy on one account
   change the *other* spouse's protection figure, and
   `life_insurance_policies` carries no `joint_owner_id` at all — `LifeCoverReach`
   finds the second life through `users.spouse_id`. Both observers followed only
   `user_id` and `joint_owner_id`, so **the exact write that produced the reported
   symptom could not invalidate the person it affected.**

### Impact

Every figure on the dashboard, on all three surfaces, could be up to a day stale
with nothing on screen to say so. It also blocks verification: a fix cannot be
confirmed against a blob that predates it, which is how a shipped fix was reported
as broken.

## Acceptance

1. A financial write invalidates the dashboard for the owner, the co-owner and both
   spouses.
2. `investment_analysis_{id}` and the date-keyed net worth blob are invalidated.
3. Invalidation lives in ONE place; the TTL is not simply shortened in its stead.
4. The docblock states what the code does.

## Working notes

**2026-08-22 — DONE, handed to quality-lead.** Branch document:
`workforce/branches/fixes/F-0022-cycle4-dashboard-module-totals-and-cache.md`.

**Prior art outcome: extend.** `CacheInvalidationService` is the home and already
knew the dashboard key. It was not called from the write path, and its key list was
incomplete. Three observers were consolidated into one caller.

### What changed

| File | Change |
|---|---|
| `app/Observers/UserDataCacheObserver.php` (NEW) | One observer, one call, for `user_id` + `joint_owner_id` + **each of their spouses**. Resolves a `Holding` through its `holdable`, which carries no `user_id`. |
| `app/Observers/{NetWorthCache,RecommendationCache,GoalCache}Observer.php` | **Deleted** — all three subsumed. The agent-instantiation loop (three to six agents per model write) goes with them. |
| `app/Services/Cache/CacheInvalidationService.php` | Module analysis keys derived from the module list instead of hand-written (this is what had been missing `investment_analysis`); `taxoptimisationagent` added; the date-keyed `net_worth:user_{id}:date_{today}` blob added. |
| `app/Providers/EventServiceProvider.php` | Registered on 26 models — adds `Holding`, `DBPension`, `StatePension`, `RetirementProfile`, `CashAccount`, `Gift`, `ProtectionProfile`, `ExpenditureProfile`, none of which invalidated anything before. |
| `app/Services/Mobile/MobileDashboardAggregator.php` | Docblock corrected; the TTL is labelled a backstop, and is unchanged. |

`User` is deliberately **not** observed — its rows are written on every login and
token refresh, and the profile fields that feed figures already invalidate in
`UserProfileController`.

### Evidence

`tests/Feature/Cache/DerivedFiguresInvalidateOnDataChangeTest.php` — 8 passing.
Every case asserts on the **key**, not on a rebuilt figure: a test that reads the
dashboard twice and compares numbers passes whenever the numbers happen not to have
moved.

Live, against the persona rows on `localhost` (`updated_at` touch only, no value
changed):

| Action | Sarah's `mobile_dashboard_17` |
|---|---|
| David touches the joint savings row (id 53) | **cleared** |
| David touches his joint-life policy (id 7, no `joint_owner_id`) | **cleared** |

The second is the case that could not have worked under either deleted observer.
