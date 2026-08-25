---
id: W-0485
title: The Blind Person's Allowance is subtracted from adjusted net income, which ITA 2007 s58 does not do — and the app holds two contradictory answers
mission: M-0002-persona-fidelity
owner: null
status: queued
severity: high
surfaces: [web, m, ios]
created: 2026-08-25T16:00:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: null
prior_art_checked: 2026-08-25
prior_art_found: [W-0205 (the item whose gate found it), W-0189]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
source: found by tax-compliance-reviewer discharging the W-0205 / W-0008 gate, 2026-08-25
---

## Intent

**`IncomeDefinitionsService` subtracts the Blind Person's Allowance when computing
adjusted net income. ITA 2007 s58 does not.**

s58 has four steps and none of them is the Blind Person's Allowance. The BPA is an
**s38 allowance, deducted at s23 Step 3** — *downstream* of net income — so it cannot
reduce adjusted net income by construction. Adjusted net income is computed at s58 from
net income (s23 Step 2), before any allowance is applied.

**The application already holds the right answer somewhere else.**
`UKTaxCalculator:720` computes adjusted net income **without** the BPA and gets it
right. So two services answer one statutory question differently — and
`ChildBenefitService`'s docblock asserts that they agree.

## Impact — measured, not argued

| Case | Shown | Correct |
|---|---|---|
| Registered-blind user on £110,000 | Personal Allowance **£9,195** | **£7,570** |
| Registered-blind user on £63,000 | No High Income Child Benefit Charge — ANI pushed to £59,750 | Charge is due |

Both are live money errors on a tax-facing surface: one overstates a Personal
Allowance, the other suppresses a statutory charge.

Only registered-blind users are affected, which is why it has survived — the persona
suite has no registered-blind household, so **every test built on the personas is
silently blind to this axis** (`tests/CLAUDE.md` §4, the persona-gaps corollary).

## Why it was not fixed in W-0205

W-0205's acceptance 3 requires **adjusted net income to be unchanged** by that item —
that is how it proves the Gift Aid relabel moved only the mislabelled intermediate.
Correcting the BPA arithmetic moves adjusted net income, so doing it inside W-0205
would have made that item unfalsifiable.

W-0205 shipped with the **claims** corrected — three comments and a test name that
asserted the BPA is a s58 deduction — and the arithmetic left deliberately intact and
pointed here.

## Acceptance

1. Adjusted net income is computed per ITA 2007 s58: net income, less grossed-up Gift
   Aid donations, less grossed-up relief-at-source pension contributions, plus the
   add-backs s58 lists. **The Blind Person's Allowance is not among them.**
2. **`UKTaxCalculator` and `IncomeDefinitionsService` return the same adjusted net
   income for the same user.** Two services answering one statutory question is the
   defect; one correct service beside one wrong one is not a fix (Rule 20).
3. `ChildBenefitService`'s docblock is true, or corrected — it currently asserts an
   agreement that does not exist.
4. The Personal Allowance taper and the High Income Child Benefit Charge are both
   re-measured for a registered-blind user at the two figures above.
5. **A registered-blind fixture exists** in the tax suite. The defect survived because
   no persona has one; fixing the arithmetic without adding the fixture leaves the next
   one equally invisible.
6. The `IncomeDefinitionsPanel` row and its explanatory comment are updated — the panel
   currently renders the BPA inside the adjusted-net-income block.
7. Statutory gate: `tax-compliance-reviewer`.

## Working notes

- 2026-08-25 tax-compliance-reviewer: found while discharging the W-0205 gate. The
  claim that the BPA is a s58 deduction appears **five times** in the codebase; W-0205
  added three of them, including a test comment reading "Both are s58 deductions."
  Those three are corrected; the arithmetic and the two pre-existing claims are not.
