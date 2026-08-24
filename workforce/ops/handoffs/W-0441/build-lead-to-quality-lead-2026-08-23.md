# W-0441 — build-lead (`fix-cycle4-retirement`) → quality-lead

## Done

A defined contribution pension's holdings can now be entered through the interface.
The Holdings tab on `PensionDetailInline` is unconditional for DC pensions; it writes
per holding through the **already existing** `dcPensionHoldingsService` to the
**already existing** `DCPensionHoldingsController`. `HoldingForm` gained an optional
`owner` prop so one form serves both an investment account and a pension.

`RetirementController::seedHoldingsForDcPension` became `syncHoldingsForDcPension` —
named rows updated in place, unnamed rows deleted, ids preserved.

Browser-verified end to end on David (user 16): all three persona holdings entered
and persisted with units, prices, purchase date, ongoing charge and `sub_type`; fee
figures moved from 0.00% to 0.31% / £976 a year; Edit and Delete exercised; and the
destruction test run and passed. Full evidence in the item's working notes and in
`workforce/branches/fixes/F-0032-cycle4-pension-holdings-entry-and-display.md`.

## Not done, and why

- **Sarah (17) has no defined contribution pension** — one defined benefit scheme
  only. There is nothing on her account for this item to exercise. Not a gap in the
  testing; a fact about the fixture.
- **`/m` has no counterpart to build.** `resources/mobile/api.js` has no post, put or
  patch helper anywhere, so `/m` cannot write. The backend half reaches it for free.
- **The persona's 0.25% platform fee is still not on record** for pension 10. The
  panel now says "Not recorded" rather than "0.00%", which is honest, but entering it
  is a data step and I did not invent it.

## What you need that isn't obvious from the artefacts

- **`GET /api/auth/user` does not return `target_retirement_age`.** This is why the
  old `this.user?.target_retirement_age || 67` always produced 67 — the field never
  reached the store for any user. Relevant if you re-test the retirement age.
- **The live data cannot discriminate the retirement-age fix.** David's
  `users.target_retirement_age` and his SIPP's `retirement_age` are both **60**. The
  browser showing "60" proves the hardcoded 67 is gone; it does **not** prove the
  source changed. `PensionDetailInline.test.js` uses 62 / 58 / 67 for that.
- **`Model::fresh()` queries without global scopes**, so it returns soft-deleted rows
  with unchanged ids. Any assertion of "the row survived" written against `fresh()`
  passes either way — this cost me a non-discriminating assertion that mutation
  testing caught.
- **Test database:** `DB_DATABASE=laravel_testing_u`, created by me.
- **Test data left in place:** holdings 73, 74, 75 on pension 10 are the persona's
  real figures and did not exist before. Pension 10's provider was changed to
  "AJ Bell Youinvest" during the destruction test and **restored to "AJ Bell"**.

## Assumptions I made

(stated as assumptions, never as facts)

- I assume `sub_type` should be **accepted but not required** on the pension holdings
  endpoint. I wrote it as `required_if:asset_type,fund` first, matching the two
  investment holding requests, and it turned an existing green 201 into a 422 —
  `DCPensionHoldingValuationTest` creates a fund holding with no sub-type. My
  reasoning is that those requests serve one form that always sends it while this
  endpoint has other callers, but the alternative reading is that the existing test
  was simply wrong. Worth a second opinion.
- I assume matching a holding on `security_name` + `asset_type` is adequate identity
  for the nested sync. The payload cannot carry an id because no rule admits one.
  Renaming a holding and re-saving the pension form in the same edit will read as a
  delete plus an insert rather than a rename. I judged that acceptable; it is a
  judgement, not a fact.
- I assume restating `current_value` only when the allocation moved is correct. The
  consequence is that if a user changes the pension's **fund value** without touching
  any allocation, holdings that carry units keep their unit-derived value and the
  allocation percentages become approximate. That is a real tension between two ways
  of valuing a holding and it belongs to whoever settles W-0322's contract question.

## Surfaces covered / not covered

- **web** — covered, browser-verified on David.
- **`/m`** — backend reaches it; no client counterpart exists to build.
- **iOS** — not exercised. The endpoint fix reaches it; nothing else here is native.
