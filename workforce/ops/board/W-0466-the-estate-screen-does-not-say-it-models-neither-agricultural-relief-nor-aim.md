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


- 2026-08-24 — **`compliance-lead`: FLAGGED, and the wording is changed.**
  - **(A) Rule 9 breach — "AIM" is an acronym**, used twice, and ISA is the only
    exception. Spelled out to "the Alternative Investment Market". The reviewer flagged
    the tension honestly: an investor may know these shares only as "AIM", so the
    spelled-out form is marginally less identifiable (PRIN 2A.5.3R(1) pulling against
    Rule 9). **Rule 9 wins on its own terms.** Writing "the Alternative Investment
    Market (AIM)" instead would be a **Rule 9 AMENDMENT and is CSJ's alone** — the test
    now asserts the acronym's ABSENCE so a well-meaning "(AIM)" fails rather than ships.
  - **(B) Rule 3 gap** — a household told its figure could be wrong by up to ~40% of its
    land value was informed and not equipped. The sentence now signposts a regulated
    financial adviser or a specialist solicitor.
  - **(C) The residual is NOT clearable as it stands**, and the reviewer's reasoning is
    sharper than the item's: **the sentence is addressed to "if your estate holds
    farmland" and is shown only to estates holding a company.** The cohort whose figure
    is most wrong never sees it. Suggested trigger: `business` **or** `other`, since
    `asset_type = 'other'` is where agricultural land lands today — no schema change
    needed. **Conditional on measuring how many estates hold an `other` asset.**
    **MEASURED: the `assets` table is EMPTY on dev — 0 rows, 0 users.** The count the
    decision needs cannot be taken here, so the trigger is UNCHANGED and this goes to
    CSJ with that stated. Note `gatherUserAssets()` does read that table
    (`EstateAssetAggregatorService:57`), single-leg, so the cohort is real in principle.
  - **Adjacent, filed separately as instructed:** Rule 5 is unmet on both estate screens
    — no tax-year or individual-circumstances caveat anywhere, and neither layout supplies
    one. Sharpened by this item: the treatment the caveat describes changes on
    6 April 2026 and the sentence is timeless.

- 2026-08-24 — **`tax-compliance-reviewer` round five: the caveat reached two more
  PAYLOADS and no more SCREENS.** Both died in enumerated frontend mappings — the fourth
  and fifth instance of that trap in this one work item (W-0134, W-0399, W-0465, G2, G3).
  *"The engine publishing a field is not the same as a surface showing it, and this
  codebase has enumerated mappings on three frontends."*
  - **G2 — `/plans/estate`** rendered `IHTCalculationTable` with no `businessRelief` prop
    and no caveat, so a business-owning household read a Net Estate up to £4,250,000 below
    gross − liabilities **with no row saying why**. The reviewer's sharpest point: before
    the engine fix that column at least *appeared* to add up — wrongly — so **the number
    became right and the column became less readable**. Fixed, and the **caveat markup
    moved INTO `IHTCalculationTable`**: two surfaces render that component and only one
    carried the sentence, so it now has one home rather than a copy per parent.
  - **G3 — `/m` `/module/estate`** renders only its enumerated `fields` list, which
    produces label/value rows. The caveat is a sentence, not a value, so it is read
    straight off the summary instead of being added to that list. Verified the payload
    carries it and that `removeScores()` does not strip it.

- 2026-08-24 — **Two defects in my own heuristic, both found by round five.**
  - **The comment documented behaviour the code does not have.** It claimed word-boundary
    matching meant `croft` would not fire on "Croftwood Ltd". **It does** — the boundary is
    leading only, and there cannot be a trailing one because `farm` must reach "farmland"
    and "farmhouse". Verified directly. Comment corrected to state what actually happens,
    and why those false positives are benign: the sentence is conditional, so a reader
    whose asset is not farmland does not meet the condition.
  - **The "discriminating" test discriminated nothing.** It asserted "Pharmacy fixtures"
    against `farm`, and "pharmacy" is p-h-a-r-m — **no "farm" substring at all**, so a
    plain `str_contains` would have passed it. Replaced with "Landcroft Holdings" against
    `croft`, which genuinely contains the term mid-word and is rejected by the leading
    boundary.
  - `acre` and `meadow` added, the two the reviewer called most defensible.

- 2026-08-24 — **The heuristic is defensible, and the reviewer said why more precisely than
  the item did:** it gates a SENTENCE, not a FIGURE. A miss leaves the pre-existing state; a
  false positive shows a conditional the reader does not meet. **"Leaving it business-only
  pending the schema change would have been worse, not more cautious."** With one hard
  boundary recorded: **`looksAgricultural()` must never become an input to a relief
  calculation** — Agricultural Property Relief turns on use and occupation (IHTA 1984
  s115(2), s116, s117), none of it inferable from a typed name. That is now in the docblock.

- 2026-08-24 — **Still missed, all overstating tax and all silent:** farmland recorded as a
  `properties` row (a working farmhouse is routinely both main residence and agricultural
  property — IHTM24050ff), and **shares listed on the Alternative Investment Market held in
  an investment account or ISA**. The second is the population round four named and **CSJ's
  direction did not address — the direction was about the `other` bucket, not about
  investment accounts.** Put back to CSJ as its own question.

- 2026-08-24 — **CSJ on the Alternative Investment Market population: it is not built into
  the app at this time, so a caveat goes where a user ENTERS HOLDINGS — a placeholder
  until the rest is working.** Built.
  - `resources/js/components/Investment/UnmodelledAimNotice.vue`, shown unconditionally on
    both holdings entry surfaces (`HoldingForm` and `InlineHoldingsEditor`). One component
    rather than a copy per form (Rule 20) — there are two entry points, and a second copy
    of the sentence would drift from the first the moment either was touched.
  - **Entry is the right surface, and for a reason worth keeping:** the estate caveat
    cannot reach these holders at all (it triggers on business interests and farmland,
    and these shares sit in an investment account), and nothing in the schema identifies
    them. **The user is the one party in the system who knows what they hold**, so the
    notice belongs at the moment they say so.
  - Rule 9: the market is spelled out. Rule 3: it signposts an adviser.
  - **The component is written to be DELETED, not edited**, when the treatment is
    modelled — its docblock says so, because a placeholder that quietly becomes permanent
    is the failure mode.
  - **Rule 19:** `/m` has no holdings ENTRY surface — `CanonicalPortfolio.vue` renders
    holdings read-only and there is no create path. Nothing to bring to parity, rather
    than parity skipped.
  - **Browser-verified** as `david.jones@example.com`: opened the AJ Bell account, clicked
    Edit, and the notice renders directly above "+ Add Holding".

