---
id: W-0189
title: The Income Definitions panel shows a chain of labelled steps whose arithmetic does not work — £147,690 less £11,600 is displayed as £147,690
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0020-cycle2-auditability-figures-the-user-cannot-check.md
owner: build-lead
status: gated
severity: medium
surfaces: [web]
created: 2026-08-22T00:35:00Z
claimed: 2026-08-22T06:45:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0134, W-0175]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Cycle 2 journey re-walk, local, `david.jones@example.com`, read-only.
**Surface:** `/valuable-info?section=income` → "Your Income Definitions".

Found while verifying **W-0175**, which is fixed — the rental figure is now one value in
both places and labelled "Rental profit". This is a different defect on the same panel.

### Expected

A panel that presents a running calculation must produce the number it prints. Threshold
income is net income less relievable pension contributions; adjusted income is net income
plus employer contributions.

### Actual

David's panel, verbatim, top to bottom:

```
Total Income                            £159,290
  Employment £145,000 · Rental profit £14,290
Less pension relief                     -£11,600
Net Income                              £147,690     ✓ 159,290 − 11,600
Adjusted Net Income                     £147,690
Less employee pension contributions     -£11,600
Threshold Income                        £147,690     ✗ 147,690 − 11,600 = 136,090
Plus employer pension contributions     +£11,600
Adjusted Income                         £170,890     ✗ 147,690 + 11,600 = 159,290
```

**Two of the three steps do not produce the figure beneath them.** The first subtraction
works; the second is displayed and not applied; the addition is applied to a different
base than the line above it (£159,290 + £11,600, not £147,690 + £11,600).

**Sarah's panel is internally consistent** — she has no Defined Contribution pension, so
the pension lines are absent and every figure reads £128,880. **The defect is specific to
the pension steps.**

### Impact

No outcome changes for this household: £147,690 and £136,090 are both below the £200,000
threshold-income taper, and £170,890 and £159,290 are both below the £260,000 adjusted-income
taper, so the Annual Allowance is £60,000 either way. **But the panel exists precisely to
show a user how their allowance was decided**, and for someone near either threshold the
displayed step and the applied step disagreeing is the difference between a £60,000 and a
tapered allowance.

It is the same auditability failure as **W-0134** — a printed chain that cannot be
followed — in the module W-0134's sibling fix has just made auditable.

Note the deduction may well be *correctly* not applied: under a net-pay arrangement
employee contributions are already out of taxable pay, so subtracting again would be
wrong. If so, **the line should not be displayed**, or should read as an explanation
rather than an operation.

### Repro

1. `david.jones@example.com` → `/valuable-info?section=income`, wait ~15s.
2. Scroll to "Your Income Definitions".
3. Read the three steps and check each subtraction and addition by hand.

### Acceptance

1. Every displayed step produces the figure beneath it, or is not displayed as a step.
2. Threshold income and adjusted income are correct under the arrangement in use, and the
   arrangement is named.
3. Verified against a user with a Defined Contribution pension and one without.

## Working notes
(append-only)

- 2026-08-22 cycle2-audit (build-lead): **FIXED, handed to quality-lead. Branch
  document `F-0020`. Not browser-verified — Quality's loop.**

  **The verdict this item asked for: the figures are right, the presentation is a
  lie. No number changed.** `IncomeDefinitionsService` does not compute one running
  column. It computes **three derivations that share one base**: net income and
  adjusted net income run down from total income; **threshold income branches from
  TOTAL income** (`:44`); **adjusted income branches from TOTAL income** (`:57`).
  Neither continues the column above it, and the employee contribution threshold
  income deducts is the same one already taken out at net income — **once across the
  two, never twice.** Deducting it again would have been the bug.

  Corroboration that it is not the arithmetic: the tests at `:129-188` of
  `IncomeDefinitionsServiceTest.php` already pinned these formulas, with comments
  naming the old **double-deduction** as the defect a previous pass fixed. The
  arithmetic was corrected then. The panel was never told.

  **Acceptance 1 — the two floating steps are gone.** Each figure now states its own
  working from the base it actually uses ("Your Total Income of £159,290, less the
  £11,600 you paid into your pension"), under one line saying both are worked out
  from Total Income and not from Adjusted Net Income. Every figure named appears
  elsewhere on the panel, so both can be checked by hand.

  **Two more instances of the same disease, fixed on the way.** The identical
  £11,600 was printed under **two different names** — "Less pension relief" high in
  the column and "Less employee pension contributions" lower down — which is most of
  why the column read as two deductions; `pension_relief` **is** the employee
  contribution (`:33`), so it is now one quantity under one name. And Adjusted Net
  Income had no stated purpose, so a reader could not tell why the column stopped
  there; it now says "Used to work out your Personal Allowance."

  **Acceptance 2 — the arrangement is named, from data, without over-claiming.** The
  service publishes `pension_arrangement` (`net_pay` / `salary_sacrifice` / `none`)
  derived from the user's own Defined Contribution pensions. **Zero rows in the
  database have `salary_sacrifice` set** (0 true, 0 false, 10 null) and the
  application has no relief-at-source flag at all, so every current user is net pay.
  **A genuine arithmetic gap for sacrifice users is raised as W-0204 and deliberately
  NOT fixed here** — FA 2004 s228ZA(3) requires an add-back, and nothing records
  whether `annual_employment_income` is the pre- or post-sacrifice figure, so both
  readings are wrong and the data cannot say which. Guessing moves a user's taper
  position. The panel names the arrangement without claiming a treatment that was not
  applied, which makes the gap visible rather than hidden.

  **Acceptance 3 — both sides covered.** David (Defined Contribution pension) and
  Sarah (none, every figure £128,880, who now reads "The same as your Total Income of
  £128,880 — you have no employee pension contributions to deduct" rather than
  growing steps that do nothing).

  **Adjacent, raised not fixed: W-0205.** `net_income` deducts the Gift Aid gross-up
  (`:35`), which net income does not — that belongs at adjusted net income (ITA 2007
  s23 Step 2 vs s58). Every end figure is right and the printed chain still adds up,
  so acceptance 1 is met either way; the row's **label** is wrong for a donor.

  **Pinned by 8 frontend tests** (`tests/frontend/components/UserProfile/IncomeDefinitionsPanel.test.js`,
  new) that **parse the figures back out of the rendered working sentence and do the
  arithmetic against the rendered rows** — the base named must equal the printed
  Total Income row and must NOT equal the printed Adjusted Net Income row. Asserting
  "the rendered total equals `definitions.total_income`" would have passed on the
  broken layout, which is the trap. **Plus 5 Pest tests** whose base case uses a Gift
  Aid donor, so net income and threshold income are provably different numbers and
  the test proves which base was used rather than restating the fixture.

  **Browser verification is Quality's.** Acceptance 3 is covered by tests on both
  sides, but "verified" here means a live browser with the arithmetic checked by
  hand on David and on Sarah — and I have not done that, by instruction.

  **Surfaces: web only.** Zero hits for "Threshold Income", `threshold_income` or
  "Adjusted Net Income" in `resources/mobile` or `ios-native` — **no counterpart
  exists**, so there is nothing to build and nothing to verify there. Stated rather
  than skipped (Rule 19).

  Regression: `tests/Unit/Services/Tax/` + `AnnualAllowanceCheckerTest` 209 passed
  (591 assertions); `pension_arrangement` is an additive key.
