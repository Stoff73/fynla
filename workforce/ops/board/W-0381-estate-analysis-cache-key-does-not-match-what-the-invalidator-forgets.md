---
id: W-0381
title: URGENT for anyone verifying estate figures — the estate analysis cache key does not match what the invalidator forgets
mission: persona-run-peak_earners-2026-08-20
branch: estate-copy-and-m-handoff
owner: null
status: done
closed: 2026-08-29
severity: high
surfaces: [web, m, ios]
created: 2026-08-23T00:40:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: []
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

**Read this before trusting any estate figure measured tonight.**

`EstateAgent::analyze()` caches under a key nothing ever forgets:

| | Key |
|---|---|
| Written by `analyze()` via `remember()` (`EstateAgent.php:104`, `BaseAgent.php:45-50` — `Cache::remember($key, …)`, key used **verbatim**) | `estate_analysis_{userId}` |
| Forgotten by `invalidateUserCache()` (`BaseAgent.php:86-103`) | `v1_estateagent_{userId}_analysis` |
| …and | `v1_estateagent_analysis_{userId}` |

**None of the three match.** A stale estate analysis therefore survives every
invalidation for the full time-to-live.

**The invalidator is not broken. It is correct, and it is pointing at nothing.** That is
why no amount of reviewing the invalidation *logic* would find this — it is a key-name
mismatch, and both halves read as reasonable in isolation.

### Why it is filed as urgent rather than tidy

**Anyone reading an estate figure right now may be reading a cache and concluding their
fix did nothing — or worse, that it worked.** It contaminated a live measurement in this
cycle: the before/after for W-0342 showed the *post*-fix values in the "before" run,
because `invalidateUserCache()` had been called and had cleared nothing. It only came
right after forgetting `estate_analysis_{id}` by hand. Two batches measured estate
numbers tonight and an agent is verifying figures in the browser.

**Third distinct cache-invalidation defect this cycle**, after the 24-hour dashboard blob
and the agent-analysis layer beneath it. The first two were about *when* things are
cleared; this one is about *what* — and it is invisible to both earlier sweeps.

## Acceptance

1. `analyze()` and `invalidateUserCache()` agree on one key, derived in one place —
   `getUserCacheKey()` already exists for exactly this and `analyze()` does not use it.
2. **Audit every other `remember()` call passing a hand-built string** rather than
   `rememberForUser()`. If `EstateAgent` does it, assume it is not alone; that is the
   census, not this item's fix.
3. A test that writes, invalidates, and asserts the next read is recomputed — asserting
   on the value, not on the fact that `Cache::forget` was called.

- 2026-08-24 — **FIXED.** `EstateAgent::analyze()` built its key by hand as
  `"estate_analysis_{$userId}"` while every invalidator cleared
  `getUserCacheKey($userId, 'analysis')` — `v1_estateagent_{id}_analysis`. The key is now
  DERIVED from that same helper, so the write and the clear name one thing.

- 2026-08-24 — **Measured end to end, not reasoned.** Flushed the cache, ran
  `analyze(14)`, confirmed `v1_estateagent_14_analysis` present, called
  `CacheInvalidationService::invalidateForUser(14)`, confirmed **CLEARED**. The legacy
  `estate_analysis_14` key is no longer written at all.

- 2026-08-24 — `CacheInvalidationService` already listed `estateagent` with an `analysis`
  suffix (`:34`, `:46`), so **nothing had to be added to the invalidator** — it was
  correct all along and pointing at a key nothing wrote. That is why reviewing the
  invalidation logic never found this.

- 2026-08-24 — The legacy string is kept in `invalidateCache()`'s additional keys, to drain
  entries written by the pre-fix code that would otherwise outlive the deploy by a full
  time-to-live. Nothing writes it now.

- 2026-08-24 — 580 tests pass across `tests/Unit/Agents`, `tests/Feature/Estate` and
  `tests/Unit/Services/Estate`.

## Closed — 2026-08-29 (board reconciliation)

**Marked done from `dev` history, not from a fresh re-test.** Previous status was
`handoff`.

- **Delivered by:** Stoff73
- **Evidence:** commit `04ecb0ee5` on `dev`

The board had drifted: the work landed on `dev` but the item was never restamped. This
records the evidence rather than deleting the item, so the fix can be re-checked against
it later. **If a re-test finds this unfixed, reopen it — a `done` here means "the change
is on `dev`", not "someone has re-verified the behaviour since."**
