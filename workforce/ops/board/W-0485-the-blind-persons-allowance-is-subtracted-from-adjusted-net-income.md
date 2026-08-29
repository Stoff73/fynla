---
id: W-0485
title: The Blind Person's Allowance is subtracted from adjusted net income, which ITA 2007 s58 does not do — and the app holds two contradictory answers
mission: M-0002-persona-fidelity
owner: null
status: done
closed: 2026-08-29
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

## Resolution — 2026-08-28

**Acceptance 1 — done.** `IncomeDefinitionsService:75` —
`$adjustedNetIncome = $netIncome - $giftAidGross;`. The Blind Person's Allowance is still
computed and still published under `deductions.blind_persons_allowance`, because the
allowance is real and the panel names it; it is simply not deducted on the way to s58.

**Acceptance 2 — done.** `UKTaxCalculator::calculateDetailedNetIncome()` tapers from total
income less net-pay pension relief through `IncomeTaxBands::taperedPersonalAllowance()`,
and has never deducted the allowance. `BlindPersonsAllowanceIsNotASection58DeductionTest`
asserts the definitions service lands on the same adjusted net income AND that the shared
taper helper turns it into the same Personal Allowance — the agreement is now required
rather than asserted in prose.

**Acceptance 3 — done.** `ChildBenefitService::calculateAdjustedNetIncome()`'s docblock
listed the allowance among the s58 deductions and claimed the two services matched. Both
statements are replaced with what is now true, and with a pointer to the test that holds
them to it.

**Acceptance 4 — done, both figures re-measured:**

| Case | Was | Now |
|---|---|---|
| Registered-blind, £110,000 | Personal Allowance **£9,195** (ANI pulled to £106,750) | **£7,570** |
| Registered-blind, £63,000, one child on Child Benefit | no charge (ANI pulled to £59,750) | charge applies |

**Verified by mutation:** with only `IncomeDefinitionsService` reverted, all four tests
fail; restored, all four pass.

**Acceptance 5 — done.** `registeredBlindUser()` in the new suite is the fixture, kept
deliberately plain — one income source, no donations, no pension — so the only thing
distinguishing it from any other user is the axis under test. The item's diagnosis was
right: the defect survived because no persona has this axis.

**Acceptance 6 — done.** `IncomeDefinitionsPanel.vue` no longer prints the allowance as a
deduction inside the adjusted-net-income block. It sits below the Adjusted Net Income line
with the note "An allowance against the income you are taxed on. It does not change your
Adjusted Net Income." **The position is what the new component tests assert** — a test
that only checked the row renders would pass just as well with it back above the line.

**Acceptance 7 — `tax-compliance-reviewer` still to run.**

### Rule 19

The item lists `[web, m, ios]`. **There is no `/m` or native counterpart to this panel** —
`resources/mobile/` contains no reference to `blind_persons_allowance` or the income
definitions. The arithmetic is shared by architecture, so both surfaces get the corrected
figure; only the desktop panel has a row to move.

### Verification

- `tests/Unit/Services/Tax` + `tests/Feature/Tax` + `tests/Unit/Services/Benefits` —
  **236 passed, 680 assertions.**
- Vitest on `IncomeDefinitionsPanel` — **13 passed**, including three new position tests.
- Pint clean.
- **NOT verified in a browser.**

### The question this change raised, and its answer — W-0511

If the allowance is no longer deducted at s58, **where is it given?** Sweeping every
consumer of `is_registered_blind` and `blind_persons_allowance`:

- a cast, a resource field, a validation rule, an onboarding capture
- `TaxConfigService::getBlindPersonsAllowance()`, whose only caller was the line removed here
- an admin screen that lets the rate be edited
- the income panel, which prints the amount

**Nothing computes tax with it.** `UKTaxCalculator` has never heard of it. The app asks
whether the user is registered blind, stores it, publishes it, maintains the rate and
prints it — then taxes them as though they had no allowance. Filed as **W-0511**, high:
the under-relief is £650 at the basic rate, £1,300 at higher and £1,462.50 at additional,
every year.

**This item did not cause that and does not fix it**, but the two interact and it should
be said plainly: **W-0485 alone moves a registered-blind user's computed tax UP.** They
lose the unearned Personal Allowance uplift (about £650 at £110,000) and still get no
allowance. That is a defensible interim — the Personal Allowance and the Child Benefit
charge become correct, which is what this item was raised for — but it is not the whole
answer, and CSJ may want the two to ship together.

## Gate findings closed — 2026-08-28

The three findings the tax-compliance gate left open on PR #741, and the decision that
unblocked it.

**F4 — both docblocks cited a test that does not exist.** `IncomeDefinitionsService:65` and
`ChildBenefitService:215` named `AdjustedNetIncomeAgreesAcrossServicesTest`. Acceptance 3
existed because a docblock asserted something untrue, and its replacement asserted a
different untrue thing. Both now name
`BlindPersonsAllowanceIsNotASection58DeductionTest`, which is the file that holds them.

**F2 — the cross-service test never constructed the calculator.** It asserted against
`$calculatorBase = 110000.00`, a hand-written literal, which agrees just as happily with a
service that has stopped running. `calculateDetailedNetIncome()` now publishes
`personal_allowance` and `blind_persons_allowance` in its summary, and the test constructs
`UKTaxCalculator`, reads them, and holds the two services to the same two figures. Neither
side of the comparison is a literal.

**F1(b) — the panel copy resolved itself.** "Blind Person's Allowance (applied to taxable
income)" was statutorily true and false about this application, because nothing applied it.
The gate said not to soften it: the copy was right and the behaviour was missing. W-0511
supplies the behaviour, so the copy is now true of the app as well as the statute.

**The blocker — CSJ decided W-0511 ships alongside** (2026-08-28). Merging this alone moved
a registered-blind user's computed tax UP: they lost the unearned Personal Allowance uplift,
worth about £650 at £110,000, and got no allowance in its place. The two land together, so
no household passes through that state. See [[W-0511-the-blind-persons-allowance-is-never-actually-given]].

## Closed — 2026-08-29 (board reconciliation)

**Marked done from `dev` history, not from a fresh re-test.** Previous status was
`review`.

- **Delivered by:** Stoff73
- **Evidence:** merged in #741; commit `9e304da01` on `dev`

The board had drifted: the work landed on `dev` but the item was never restamped. This
records the evidence rather than deleting the item, so the fix can be re-checked against
it later. **If a re-test finds this unfixed, reopen it — a `done` here means "the change
is on `dev`", not "someone has re-verified the behaviour since."**
