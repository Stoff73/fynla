---
id: W-0466
title: An estate holding farmland or AIM shares is shown a figure that models neither, and is told nothing
mission: persona-run-peak_earners-2026-08-20
branch: estate-copy-and-m-handoff
owner: null
reviewers: [tax-compliance-reviewer, compliance-lead]
status: gated
claimed_by: null
severity: medium
surfaces: [web, m]
created: 2026-08-23T19:20:00Z
claimed: null
blocked_by: []
gate: compliance-lead
handoff_to: null
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
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
- 2026-08-23 — **CSJ settled the wording.** Both directions stated, because the two
  exclusions bend the figure opposite ways and a caveat that says only "may not be
  accurate" leaves the reader unable to tell which:

  > This figure does not include Agricultural Property Relief, and does not apply the
  > special treatment of AIM-listed shares. If your estate holds farmland or AIM
  > shares, your actual liability could be higher or lower than shown.

- 2026-08-23 — **Implemented.** The sentence and the flag are published together by
  `IHTCalculationService` (`unmodelled_relief_caveat`, null when it does not apply),
  because `/m` computes nothing and the two frontends ship separate bundles that share
  no constants — a copy in each is the Rule 20 drift this avoids. Rendered on the web
  Inheritance Tax screen (`IHTPlanning.vue`) and on the `/m` Free teaser, which is the
  only Inheritance Tax figure `/m` prints. NOT on the `/m` Premium estate card: after
  W-0469 that card shows no Inheritance Tax figure, so there is nothing on it to
  qualify.

- 2026-08-23 — **RESIDUAL, stated rather than buried: the trigger is business
  interests only.** Agricultural property has no representation in the schema —
  `assets.asset_type` is `enum('property','pension','investment','business','other')`
  and `properties.property_type` is the three canonical residences — so **a farmer
  holding land and no company still sees nothing.** Widening the trigger to every
  estate would breach acceptance 3 and desensitise the households the caveat is for.
  Closing this residual needs the schema change already registered as a dead end in the
  2026-08-23 handover; it is not closeable here.

- 2026-08-23 — Added to `isCurrentResultShape()`. The caveat is legitimately null for
  most estates, so a consumer's `?? null` cannot distinguish "this engine did not
  publish it" from "this estate does not need it" — without the shape guard a cached
  row would suppress the caveat until the user's assets happened to change.

- 2026-08-23 — Still needs `compliance-lead` on the copy before this leaves `handoff`.

- 2026-08-24 — **Round-four review: the stated premise was FALSE.** "The `/m` teaser is
  the only Inheritance Tax figure `/m` prints" appears three times in code and commit
  message and does not survive a grep. At least four surfaces print one:

  1. **`/plans/estate`** — full figure, no caveat. **Fixed:** the plan payload now carries
     `unmodelled_relief_caveat`.
  2. **`/m` Insights** — the caveat is now attached to the Inheritance Tax insight, but
     **that whole feature is dead**: every reader looks one level above where the agent
     puts its data, so no insight has ever been produced. Measured, not read. Filed as
     **W-0473**; the caveat line is deliberately left in place so the figure and its
     qualification arrive together when the reader is corrected.
  3. **`/m` `/module/estate`** — a live route with an "Estimated IHT liability" hero and
     an allowance breakdown, reachable by URL or `/m?to=` deep link though nothing links
     to it. **`EstateAgent` now publishes the caveat in its summary**, which is what that
     screen reads.
  4. The web breakdown and the `/m` teaser, already done.

  This was a Rule 20 point rather than a copy-the-sentence point: the engine publishes the
  sentence once and three consumers of its tax figure did not read it.

- 2026-08-24 — **The recorded residual understated its own reach.** It named only the
  farmer with no company. It also misses **every AIM holder who owns no company** — the
  ordinary pattern, an `InvestmentAccount` row, expressible in the schema today, taking
  **0% relief where FA 2026 gives 50% outside the allowance (IHTM25570)**. Tax
  **overstated by ~20% of the holding**, and the caveat fires only for the *other* AIM
  case. The trigger is unchanged pending CSJ, but the residual now says what it really is.

- 2026-08-24 — **F8 fixed:** `--violet-800` is not defined in `resources/mobile/style.css`
  (only `--violet-400` and `--violet-500`), so the `/m` caveat text fell back to the
  browser default. `--eggshell-500` IS defined and was left alone — the reviewer flagged
  both; only one was real.
