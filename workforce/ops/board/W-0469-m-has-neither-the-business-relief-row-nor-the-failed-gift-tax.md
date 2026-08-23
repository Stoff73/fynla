---
id: W-0469
title: The business relief row and the failed-gift tax reached web only — /m shows neither, so the same estate reconciles on one surface and not the other
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [quality-lead]
status: queued
claimed_by: null
severity: medium
surfaces: [m]
created: 2026-08-23T19:20:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0154, W-0463, W-0464]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: raised by the coordinator against its own work, 2026-08-23 — conditions C3 and C5 were satisfied on web and left undone on /m
---

## Intent

**Rule 19: done means web AND `/m`.** Two conditions from the tax-compliance review were
discharged on web in `33966d9e0` and not on `/m`:

- **C3** — the Business Property Relief row between Liabilities and Net Estate. Without
  it, gross assets less liabilities does not reach net estate for any household holding a
  qualifying business. `IHTCalculationTable.vue` has the row; `/m` has no allowance or
  estate breakdown at all.
- **C5** — `failed_gift_tax`, `failed_gift_taper_saving` and the per-gift `failed_gifts`
  breakdown. Published by `IHTController`, rendered on web, **absent from `/m`**. Taper
  relief on failed gifts is the item CSJ called critical, and on `/m` it is still
  invisible.

**What makes this a decision rather than a port:** `/m`'s estate screen shows an estate
value and a composition and **no allowance breakdown whatsoever**, and in Premium mode no
Inheritance Tax liability either. So this is not "add two rows" — it is a question about
what the `/m` estate screen is for, and that has not been settled.

## Acceptance

1. A decision, recorded: does `/m` gain an allowance and estate breakdown, or does it stay
   a summary that hands off to web for the detail? Either is defensible; leaving it
   undecided is not.
2. If a breakdown: the business relief row and the failed-gift figures both appear, and
   the columns add up on `/m` exactly as they now do on web.
3. If a handoff: the `/m` estate screen says the detail lives on the web app and links
   there, rather than silently showing a subset.
4. **`/m` computes nothing** — it renders `business_relief_deduction`, `failed_gift_tax`
   and `failed_gifts` as published (CSJ direction, 2026-08-23).

## Working notes

- 2026-08-23 — Raised by the coordinator against its own work rather than left buried in a
  commit message. The web half is deployed at `19bd1c83f`; this is the half that is not.
