

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

