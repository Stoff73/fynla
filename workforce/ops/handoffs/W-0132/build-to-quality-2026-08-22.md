# W-0132 — build-lead (`cycle2-audit`) → quality-lead

Branch document: `branches/fixes/F-0020-cycle2-auditability-figures-the-user-cannot-check.md`

## Done

**Both halves. The item is complete.**

### The settings card (`/settings/family`)

Read `users.charitable_bequest`, a fourth answer to a question the will already
answers, and said "Not set" for a user with a recorded £10,000 legacy. Now reads the
will via `WillAnalysisService::charitableBequestSummary()` — a new method in the class
that already owns `Bequest::isCharitable()` — served on the profile the page already
loads (`UserProfileService::getCompleteProfile()` → `charitable_bequests`).

### The estate surfaces (`/estate`, `/estate/inheritance-tax`)

Both are the same component, so they can no longer disagree with each other.

- **Acceptance 1** — `effectiveIHTRateLabel()` returned `charitableBequest ? '36%' : '40%'`.
  Replaced by `ihtRateLabel`, built from the server's `iht_rate_percent`, stating the
  current and projected columns separately when they differ.
- **The mapping was also substituting a different quantity.** `loadIHTCalculation()`
  set `iht_rate: effective_rate / 100`; `effective_rate` is the liability as a
  percentage of the whole net estate, nearer 12% than 40%. `iht_rate_percent`,
  `iht_rate_type` and `iht_rate_message` were dropped. `IHTController` now publishes
  the type and the message on `iht_summary.current`.
- **Acceptance 2** — the assumed-donation recomputation is **deleted**, not corrected:
  the `charitableBequest` branches in `taxableEstate` and `ihtLiability` in both
  table-prop builders, the entire alternate table layout in `IHTCalculationTable.vue`,
  the `charitableDonation` prop and row, and the three computeds that sized the
  assumed gift. The what-if survives as a clearly labelled scenario on the card that
  changes no figure on the page.
- **Acceptance 3** — the toggle is removed. `users.charitable_bequest` is now read by
  nothing in the application.

Tests: 6 appended to `IHTCalculationTable.test.js` (15 passed), 8 in the new
`IHTPlanningRateLabel.test.js`, 5 in the new
`tests/Feature/Api/IhtRateIsPublishedWithItsFigureTest.php`, plus the settings-card
suites. Regression 310 Pest / 993 assertions and 319 frontend across 25 files.

## Not done, and why

- **No browser verification.** By instruction.
- **The `users.charitable_bequest` column still exists.** Nothing reads it and nothing
  writes it any more. Dropping it is a migration and belongs in its own item — I did
  not want a schema change riding inside a display fix.
- **`userProfileService.updateCharitableBequest()` is now unreferenced.** Left in
  place; removing it is the same cleanup as the column.

## What you need that isn't obvious from the artefacts

- **Priya's two columns legitimately carry DIFFERENT rates: 36% today, 40% at age 84.**
  The projection re-runs the 10% test against a much larger estate where her £10,000
  no longer clears the threshold. Verified read-only 2026-08-22:
  `projected_iht_liability` £472,662 ÷ `projected_taxable_estate` £1,181,656 = 40%.
  **The label reading "36% today, 40% at age 84" is correct, not a bug.** The old
  single "40%" was accidentally right for the projected column and wrong for the
  current one.
- **Her `charitable_deduction` is £20,000, not the £10,000 this item's Intent quotes.**
  W-0154 pooled the household's legacies for the IHTA 1984 s23 exemption while keeping
  the 10% rate test on the survivor's will alone — which is why the rate message still
  cites £10,000 against a £20,000 deduction. Both are correct and they are answering
  different questions.
- **The check to run is a division, not a comparison.** Divide the printed Inheritance
  Tax Liability by the printed Taxable Estate beside it. The answer must be the
  percentage in the label. That is the defect in one operation, and it is what the
  tests do on both the rendered table and the API response.
- **Her `users.charitable_bequest` is `true` in the local database**, set through the
  interface by the tester while evidencing this item. It is now inert — if the screen
  changes when you flip it, something still reads it and that is a regression.
- **"Has a legacy" is not "qualifies for the reduced rate".** A £500 charitable legacy
  is deducted under s23 and leaves the rate at 40%. There is a test for exactly this;
  if a future change makes the card infer qualification from the deduction, it breaks.

## Assumptions I made

- **That the what-if should survive somewhere rather than be deleted outright.**
  Acceptance 2 says "if a 'what if I left 10%?' projection is wanted it is a clearly
  labelled scenario", so I kept the saving figure on the card, labelled as a scenario,
  and made it change nothing else. If CSJ would rather it disappeared entirely, it is
  one block.
- **That linking to the will builder is the right replacement for the toggle**, on the
  basis that the will is the instrument. The user can still express "I want to leave
  something to charity" — by recording it where it counts. I did **not** invent a
  replacement control.
- **That a per-column rate label beats a per-column rate cell.** Stating "36% today,
  40% at age 84" in the row header follows the existing `residenceBandNote` precedent
  in the same component rather than adding a rate row.
- **That the `charitable_bequest` column should be left in place for now.** Removing a
  column is a migration; the display defect is fixed either way.

## Surfaces covered / not covered

- **Web:** covered. `/estate` and `/estate/inheritance-tax` (one component),
  `/settings/family`.
- **`/m`:** **no counterpart.** Renders no Inheritance Tax rate and no Inheritance Tax
  figure at all (W-0138), and no charitable card. Zero hits for `iht_rate` or "charit"
  in `resources/mobile` beyond an unrelated expenditure row.
- **Native iOS:** **no counterpart.** Zero hits for either in `ios-native`.
- The `iht_rate_type` / `iht_rate_message` addition is server-side and reaches any
  surface reading the summary; `/plans/estate` already rendered `iht_rate_message`
  from the raw calculation and is unaffected.
