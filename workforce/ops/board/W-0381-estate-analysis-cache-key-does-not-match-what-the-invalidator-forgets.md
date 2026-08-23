---
id: W-0381
title: URGENT for anyone verifying estate figures — the estate analysis cache key does not match what the invalidator forgets
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0027-cycle4-life-cover-reach.md
owner: null
status: queued
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
