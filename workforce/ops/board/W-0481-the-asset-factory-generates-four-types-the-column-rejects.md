---
id: W-0481
title: AssetFactory randomly generates four asset types the column rejects, so any use without an explicit type fails about half the time
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [quality-lead]
status: done
claimed_by: null
severity: low
surfaces: []
created: 2026-08-24T15:50:00Z
claimed: null
blocked_by: []
gate: null
prior_art_checked: 2026-08-24
prior_art_found: [W-0475, W-0329]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: found while writing the W-0475 guard, 2026-08-24 — the factory offered types the enum has never accepted
---

## Intent

`database/factories/Estate/AssetFactory.php:20-29` picks `asset_type` at random from
eight values:

```
property, investment, pension, cash, business_interest, personal_possession,
life_insurance, other
```

The column holds five (measured, not read from a migration):

```
enum('property','pension','investment','business','other')
```

**Four of the eight do not exist in the column** — `cash`, `business_interest`,
`personal_possession`, `life_insurance` — and `business` is missing from the factory
entirely. So `Asset::factory()->create()` without an explicit `asset_type` writes an
illegal value roughly **half** the time and dies at the insert.

This is direction one from `app/Http/CLAUDE.md` — *"rule wider than the column: always
a defect. The value passes validation and dies at the write."* Here it is a factory
rather than a request rule, and the failure lands in a test run rather than a user's
face.

## Why it has never fired

**Nothing uses it.** `grep` across `tests/`, `app/` and `database/` finds exactly one
caller — the W-0475 guard written on 2026-08-24, which passes `asset_type` explicitly
and is therefore unaffected. The factory has sat unused since it was written, which is
also why the drift was never noticed.

**Latent, not live.** Filed at LOW because nothing breaks today. It is a trap laid for
whoever next writes a test against the `assets` table, and it will present as a
random, roughly-one-in-two failure with a `SQLSTATE` truncation error rather than as
anything pointing at the factory.

## Acceptance

1. The factory's pool is the column's five values.
2. `generateAssetName()` is checked at the same time — it switches on the same
   type strings and carries the same stale vocabulary.
3. A guard that a factory's enum-valued defaults are members of the column's enum.
   This is the third instance of the family (`StoreEnumRulesMatchColumnsTest` covers
   request rules, W-0329 covers Store numeric bounds); factories are a third layer
   nobody sweeps.

## Working notes

- 2026-08-24 — Found while writing `IHTProjectedEstateCoversEveryAssetTypeTest`. Not
  fixed there: that item's business is the projection, and changing a shared factory
  under an unrelated fix is how unrelated suites go red.
- 2026-08-24 — **Wider than filed: TWO fields, not one.** `ownership_type` is also
  randomised and can emit `tenants_in_common`, which `assets.ownership_type` rejects
  outright (it is property-only). Hit while running the W-0475 guard. Acceptance 1
  covers both fields.

- 2026-08-31 build-lead: **FIXED AND TESTED — closed, and there were TWO columns wrong, not one.**

  **`asset_type`, as filed.** The factory drew at random from eight values; the enum accepts five (`property`, `pension`, `investment`, `business`, `other`). Four were rejected outright — `cash`, `business_interest`, `personal_possession`, `life_insurance` — and `business`, which the column DOES accept, was never generated at all.

  **`ownership_type`, found by the guard.** `:53` drew `['individual', 'joint', 'tenants_in_common']` against `enum('individual','joint','trust')`. `tenants_in_common` is not in it and never could be: **Rule 4 makes that value PROPERTY-only**, and this is the estate `assets` table. `trust`, which the column does accept, was missing. Same fault, same file, one line apart — and it only surfaced because the new test persisted rows rather than building them.

  **Why this was worse than a factory that always fails:** it failed at RANDOM, roughly half the time, so it read as a flaky test rather than a factory that cannot produce a valid row — and the usual response to a flaky test is to re-run it.

  Also removed: the `cash()` state, which wrote `asset_type => 'cash'` and would therefore have failed on **every** call. It had zero callers. The name-map arms for the three departed types went with them — cash lives in the savings tables and policies in the protection ones; neither is an `assets` row, which is why the enum never had them.

  **Deliberately kept as literal lists rather than read from the schema**, and recorded because it looks like an improvement: a factory that derives its values from the column can never disagree with it, and so can never fail when the column changes — which is exactly the warning a suite is supposed to give.

  **Tested:** `tests/Feature/Database/AssetFactoryProducesValidRowsTest.php` — 2 passed, **85 assertions over 40 random draws**, every row persisted. A single reintroduced invalid value would have to dodge all forty.
