---
id: W-0339
title: The estate projection read $mortgage->end_date, a column the mortgages table does not have, so every mortgage was assumed cleared at retirement age
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: done
severity: medium
surfaces: [web, m, ios]
created: 2026-08-23T00:15:00Z
claimed: 2026-08-23T00:15:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0241, W-0274, W-0336]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Found by a fixture the database rejected while building the W-0336 test.

`mortgages` has **no `end_date` column**. Its columns are `start_date`,
`maturity_date`, `rate_fix_end_date`, `remaining_term_months`. Two sites read
`$mortgage->end_date`:

- `IHTCalculationService:676` — inside `projectMainResidenceNetValue()`, which feeds
  the **Residence Nil Rate Band cap** (IHTA 1984 s8E(2): the band is capped at the net
  value of the residence share). Treating a mortgage as cleared makes the residence
  net value too HIGH and the cap too generous — **understating** tax.
- `IHTCalculationService:983` — inside the liability projection.

The value was always `null`, so `projectSingleLiability()` fell through to its
*"assume the liability is cleared at retirement age"* default for **every mortgage in
the estate projection, always**, whatever its real term. Nothing threw, nothing
logged, and the resulting figure looked entirely plausible.

The sibling loop three lines away reads `$liability->maturity_date` correctly — the
mortgage loop was written by analogy and got the column name wrong.

This is the W-0241 `transfer_value` shape a third time this cycle, and the reason
`app/Services/CLAUDE.md` says it: **a float carries no units, no provenance and no
absence.**

## Acceptance

1. Both sites read `maturity_date`. ✅
2. A test that distinguishes a mortgage maturing BEFORE the horizon from one maturing
   after — under the phantom column both take the retirement-age default and no
   assertion can tell them apart. ✅ `IHTProjectionOwnershipTest`, mutation-verified.
3. Before/after stated. ✅ **£0 on this persona** — its mortgages mature inside the
   horizon, so the wrong branch and the right one both give £0, and the projected
   Residence Nil Rate Band is already tapered to £0. The defect is real and the
   persona cannot show it.

## Working notes

Whether `projectSingleLiability`'s retirement-age default is itself right for a
mortgage with no maturity date is a separate question and is NOT settled here — this
item only stops the default firing for mortgages that have one.

## Three phantom columns in one cycle is a pattern — and the shape is always the same

`db_pensions.transfer_value` in two places (W-0241), and now `mortgages.end_date`.

**The shape:** a **collection** read of a non-existent attribute returns `null`
silently, while the same name in a **query builder** throws `SQLSTATE[42S22] Unknown
column`. So the defect survives exactly where the code iterates models — which is the
readable, idiomatic, reviewed-looking half — and is caught instantly where it builds
SQL. Nothing in a `foreach` says a column is missing.

**How this one surfaced:** not by reading the code, and not by any test over it. A
**fixture** set `end_date` on a `Mortgage` factory and the *database* rejected the
insert. The write path threw where the read path never had.

**The countermeasure is cheap and mechanical**: a guard asserting that every attribute
name read off a model in a service exists as a column, a cast, an accessor or a
relation. Three instances in one cycle, all in financial projections, all silently
producing plausible figures. Raised as its own sweep in the W-0376 family rather than
left as three coincidences.

*A float carries no units, no provenance and no absence* — `app/Services/CLAUDE.md`.
