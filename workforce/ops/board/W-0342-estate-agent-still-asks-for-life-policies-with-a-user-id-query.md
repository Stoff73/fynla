---
id: W-0342
title: EstateAgent still asks which life policies exist with a user_id query, so the estate plan itself is where the missing cover shows
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0027-cycle4-life-cover-reach.md
owner: build-lead
status: handoff
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T23:35:00Z
claimed: 2026-08-23T00:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: [W-0186, W-0341]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Found while fixing W-0341, by asking who actually reads the method being fixed.

`app/Agents/EstateAgent.php:109`:

```php
$allLifePolicies = LifeInsurancePolicy::where('user_id', $userId)->get();
```

For Sarah Jones (17) that collection is **empty**, and it feeds four things:

- `policyAssessment` — `LifeCoverCalculator::assessExistingPolicies()`, skipped entirely
  when the collection is empty (`:229-233`), so no policy is assessed for her.
- `itemisedPolicies` (`:254`) → `userContext` (`:299`) → the recommendation traces **and
  the LLM**, which is therefore told she holds no life policies.
- `findMissingForQualityAdvice(...)` (`:306`) — the structured gap list.

**This is where "Sarah has no life cover" actually enters her estate plan.**
`EstateAssetAggregatorService::getExistingLifeCover()`, the method W-0341 was dispatched
against, has **zero production callers** — fixing it was correct but cannot change what
she is shown.

`:119` already routes to `LifeCoverReach::householdCoverInTrust()`, so the household
in-trust figure on her plan is **already correct at £500,000**. It is the per-policy
assessment, the itemised list and the gap list that are blind. The W-0186 consolidation
reached one of the two reads in this method and not the other.

**Deliberately NOT to change in the same edit:** `total_cover_not_in_trust` and
`policies_not_in_trust_count` (`:332`, `:338`) are individual on purpose — they drive
"place this policy in trust", an action only the policy's owner can take.

## Acceptance

1. `:109` routes to `LifeCoverReach::policiesCovering($user)`.
2. The not-in-trust figures stay owner-scoped, with the reason kept in the comment.
3. Sarah's estate plan assesses the joint-life policy that covers her, and her gap list
   stops naming life cover she has.
4. David's plan is unchanged.
5. Browser-verified on both accounts, web and `/m`.

## Working notes

(append-only)

- 2026-08-22 — `EstateAgent.php` is outside the exclusive scope issued to
  `fix-cycle4-lifecover` (`EstateAssetAggregatorService` + `LifeCoverReach` + tests).
  Scope requested from team-lead; blocked pending that answer rather than taken.
- 2026-08-23 — **Scope granted by team-lead. Fixed and measured on the live persona.**
  Sarah (17), same estate, before and after:

  | | Before | After |
  |---|---|---|
  | `itemised_policies` (→ `userContext` → the LLM) | `[]` | the Vitality £500,000 policy |
  | `policy_assessment` | **0 entries** — `assessExistingPolicies` skipped entirely | **5 entries** |
  | `missing_for_quality_advice` | **`life_cover`: "Estate exceeds the Nil Rate Band but no life cover is recorded — a written-in-trust policy is a common Inheritance Tax mitigation route."** | **no gaps** |
  | `total_cover_not_in_trust` / count | £0 / 0 | £0 / 0 — unchanged, still owner-scoped |

  Her gross estate is **£861,780**, well over the Nil Rate Band, which is why the phantom
  gap fired. **That gap sentence is the acceptance criterion in the item's own words:
  her estate plan was recommending on the premise she has no life cover.** David (16) is
  identical before and after on every one of those fields.
- 2026-08-23 — `$lifePoliciesInTrust` (`:110`) was assigned and never read; removed with
  the block that produced it.
- 2026-08-23 — **Unrelated defect found while measuring, and it matters for anyone
  verifying estate figures: `EstateAgent::analyze()`'s cache is never cleared by
  `invalidateUserCache()`.** `analyze()` calls `remember("estate_analysis_{$userId}")`
  and `BaseAgent::remember()` uses the key verbatim (`BaseAgent.php:45-50`), while
  `invalidateUserCache()` forgets `v1_estateagent_{$userId}_analysis` and
  `v1_estateagent_analysis_{$userId}` (`BaseAgent.php:86-103`). **None of the three
  match.** A stale estate analysis therefore survives every invalidation for the full
  TTL, and my own first "before" measurement was contaminated by it until I forgot the
  raw key by hand. No board id left in the W-0341–W-0350 block; flagged to team-lead
  for its own item.

- 2026-08-23 — **Routing this collection to the reach opens a new wrong answer, measured
  and reported to team-lead before shipping.**
  `LifeCoverCalculator::assessExistingPolicies():453-461` ends its `not_in_trust`
  warning with **"Contact your provider to place this policy in trust."** Before the fix
  the non-owner never saw the assessment; now she does, so for a joint-life policy that
  is **not** in trust she is told to contact a provider about a contract she does not
  hold and cannot change. Proved in a rolled-back transaction (persona data untouched):

  > SARAH IS TOLD: *"Vitality (£500,000) is not written in trust. … Contact your
  > provider to place this policy in trust."* — Owns it? **NO**

  **The persona cannot show this**: policy 7 is in trust, so the branch never fires.
  Fixture variant, `tests/CLAUDE.md` §4 — joint-life-and-not-in-trust is ordinary in real
  data and absent from `peak_earners`. Same class W-0186 named: *"no surface offers an
  edit that cannot work"*.

  **The fix belongs in `LifeCoverCalculator`, one branch, ownership-aware via
  `LifeCoverReach::isOwnedBy()`** — NOT post-filtering the warnings in `EstateAgent`,
  which would put a second mechanism in charge of what the warning says. Scope requested.
  The whole-of-life and expiry warnings should still reach her: the cover on her life
  expiring is hers to know.
- 2026-08-23 — **Browser-verified on the surfaces that actually consume
  `EstateAgent::analyze()`** — `/api/plans/estate` (web estate plan) and
  `/api/v1/mobile/modules/estate`. Both carry the Vitality policy for Sarah with no
  phantom gap. **The `/m` estate screen renders no life-cover section at all**, so this fix
  has no visible surface there — stated rather than claimed. Estate caches cleared by hand
  before each reading (W-0381). Moving to `handoff`.
