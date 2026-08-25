# W-0189 — build-lead (`cycle2-audit`) → quality-lead

Branch document: `branches/fixes/F-0020-cycle2-auditability-figures-the-user-cannot-check.md`

## Done

**The question the item asked has an answer: the figures are right, the presentation
was the lie. No number changed anywhere in this fix.**

`IncomeDefinitionsService` computes **three derivations sharing one base**, not one
running column:

| Figure | Base | Line |
|---|---|---|
| Net Income → Adjusted Net Income | Total Income | `:35`, `:39` |
| Threshold Income | **Total Income** | `:44` |
| Adjusted Income | **Total Income** | `:57` |

Threshold Income never continues from Adjusted Net Income, and the employee
contribution it deducts is the same one already taken out at Net Income — deducted
**once across the two**, which under a net pay arrangement is correct. Deducting it
again would have been the bug.

Changed:

- `app/Services/Tax/IncomeDefinitionsService.php` — publishes `pension_arrangement`
  (`net_pay` / `salary_sacrifice` / `none`), derived from the user's own Defined
  Contribution pensions. Docblocks now name the base each definition branches from.
- `resources/js/components/UserProfile/IncomeDefinitionsPanel.vue` — the two floating
  step rows are gone. Each figure states its own working from Total Income. One
  quantity now carries one name (`pension_relief` and
  `employee_pension_contributions` are the same £11,600 and were printed under two
  labels). Adjusted Net Income states what it is for.

Tests: 8 new frontend (`tests/frontend/components/UserProfile/IncomeDefinitionsPanel.test.js`),
5 new Pest (`tests/Unit/Services/Tax/IncomeDefinitionsServiceTest.php`, 20 passed).
Regression `tests/Unit/Services/Tax/` + `AnnualAllowanceCheckerTest`: 209 passed, 591
assertions.

## Not done, and why

- **No browser verification.** By instruction — Quality's loop, not mine.
- **Salary sacrifice add-back (FA 2004 s228ZA(3)) — raised as W-0204, not fixed.**
  Deliberate. Nothing records whether `users.annual_employment_income` is the pre- or
  post-sacrifice figure, so both readings of the data give a wrong answer and the
  data cannot say which. Guessing moves a user's taper position. Latent: **zero** rows
  have `dc_pensions.salary_sacrifice` set. The panel names the arrangement without
  claiming a treatment that was not applied.
- **`net_income` deducts the Gift Aid gross-up — raised as W-0205, not fixed.** Scope
  discipline. The displayed chain still adds up as printed, so acceptance 1 is met;
  the row's **label** is wrong for a donor (ITA 2007 s23 Step 2 vs s58).

## What you need that isn't obvious from the artefacts

- **The panel's own working sentences are the thing to check, not just the totals.**
  Read "Your Total Income of £159,290, less the £11,600 you paid into your pension"
  and do the subtraction against the Threshold Income beside it. That is what a user
  would do and it is what the tests do.
- **Sarah (users.id 17) is the more interesting account, not the boring one.** She has
  no Defined Contribution pension, so `pension_arrangement` is `none` and the panel
  must say "The same as your Total Income of £128,880 — you have no employee pension
  contributions to deduct" rather than showing steps that do nothing. A regression
  here would look like a correct-but-empty panel.
- **Do not "fix" a re-deduction back in.** If the column looks like it should subtract
  £11,600 again to reach £136,090, that is the original defect. £147,690 is correct.
- **`pension_arrangement` is additive.** Nothing existing reads it; the panel is the
  only consumer.
- **Live figures for David (users.id 16), read-only 2026-08-22:** total £159,289.60 ·
  net £147,689.60 · adjusted net £147,689.60 · threshold £147,689.60 · adjusted
  £170,889.60 · employee £11,600 · employer £11,600.

## Assumptions I made

- **That "the arrangement is named" (acceptance 2) means naming the treatment the
  code applied, not asserting a tax regime the application does not record.** There is
  no relief-at-source flag anywhere in the schema. I took net pay to be the honest
  name for "deducted from total income once" and flagged the sacrifice case rather
  than describing it as handled.
- **That relabelling "Less pension relief" was in scope.** It is the same £11,600
  under a second name and, in my reading, most of why the column read as two
  deductions. If Quality considers that scope creep it is one label, trivially
  reverted.
- **That Total Income, Net Income, Adjusted Net Income, Threshold Income and Adjusted
  Income keep their existing title-case labels.** They are defined statutory terms and
  churning the casing would be noise.

## Surfaces covered / not covered

- **Web:** covered. `/valuable-info?section=income`, `IncomeOccupation.vue` →
  `IncomeDefinitionsPanel.vue`.
- **`/m`:** **no counterpart exists.** Zero hits for "Threshold Income",
  `threshold_income` or "Adjusted Net Income" in `resources/mobile`. Nothing to build,
  nothing to verify.
- **Native iOS:** **no counterpart exists.** Same grep, zero hits in `ios-native`.
- Every consumer of the service (`AnnualAllowanceChecker`, `TaxStrategyMath`,
  `TaperedAnnualAllowanceStrategy`, `ChildBenefitService`) is shared across surfaces
  and is unaffected — `pension_arrangement` is additive and no existing key changed
  value.
