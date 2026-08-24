

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
