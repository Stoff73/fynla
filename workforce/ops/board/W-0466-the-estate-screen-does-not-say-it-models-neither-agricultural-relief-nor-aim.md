---
id: W-0466
title: An estate holding farmland or AIM shares is shown a figure that models neither, and is told nothing
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer, compliance-lead]
status: queued
claimed_by: null
severity: medium
surfaces: [web, m]
created: 2026-08-23T19:20:00Z
claimed: null
blocked_by: [csj-decision]
gate: compliance-lead
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0091, W-0463]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
source: tax-compliance-reviewer condition C12 / finding F12, 2026-08-23. The requirement is the reviewer's; the WORDING is CSJ's, which is why this is blocked on a decision rather than queued for engineering
---

## Intent

The reviewer's verdict on the Agricultural Property Relief and AIM exclusions, verbatim
in substance: **defensible as a documented exclusion — yes. Defensible as a silent gap —
no.** They are registered in `UNIMPLEMENTED_RULES`, which tells the test suite. It tells
no user.

**The two errors run in OPPOSITE directions, and both are large:**

- **Agricultural property** — no agricultural asset type exists in the schema, so
  farmland is modelled as an ordinary asset with **no relief**. **Overstates tax**,
  potentially by ~40% of the land value.
- **AIM shares** — from 6 April 2026 the correct treatment is 50% relief **outside** the
  allowance (IHTM25570: "relief is available at 50% only under IHTA84/S104 ... such
  property is not relevant to the 100% relief allowance"). Recorded as a business
  interest they take 100% to the cap — **understates tax**. Held in an
  `InvestmentAccount` they get nothing — **overstates tax**. `rates.aim_shares = 0.5`
  and `aim_shares_outside_cap = true` are both configured and unread.

## Acceptance

1. **CSJ decides the wording.** An estate holding a business interest or agricultural
   property carries a caveat on the Inheritance Tax screen stating the figure models
   neither Agricultural Property Relief nor AIM treatment. The requirement is settled;
   the words are not.
2. Shown on web **and** `/m` (Rule 19).
3. Shown only where it applies — a household with neither must not see it.
4. `compliance-lead` on the copy: this is a figure a user may act on.

## Working notes

- 2026-08-23 — Blocked on CSJ deliberately. Engineering can place the caveat the moment
  the words exist; guessing at regulated copy is not this item's to do.
