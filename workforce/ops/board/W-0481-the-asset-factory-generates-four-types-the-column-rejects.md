---
id: W-0481
title: AssetFactory randomly generates four asset types the column rejects, so any use without an explicit type fails about half the time
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [quality-lead]
status: queued
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
