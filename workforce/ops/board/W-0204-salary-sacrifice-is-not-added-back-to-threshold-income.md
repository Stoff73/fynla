---
id: W-0204
title: Salary sacrifice is not added back to threshold income, and nothing records whether the entered employment income is the pre- or post-sacrifice figure — so the deduction is wrong either way, and which way cannot be determined from the data
mission: persona-run-peak_earners-2026-08-20
branch: fix/w-0204-salary-sacrifice-add-back-to-threshold-income
owner: null
status: in_review
severity: high
surfaces: [web]
created: 2026-08-22T07:25:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0189]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by `cycle2-audit` while establishing whether **W-0189** was an arithmetic or a
presentation defect. It is a presentation defect — but the investigation turned up a
real arithmetic gap beside it, in the same method.

**Surface:** `/valuable-info?section=income` → "Your Income Definitions", and every
consumer of `IncomeDefinitionsService` (`AnnualAllowanceChecker`, `TaxStrategyMath`,
`TaperedAnnualAllowanceStrategy`, `ChildBenefitService`).

### Expected

FA 2004 s228ZA(3): threshold income is net income **plus** any employment income
given up under a relevant salary sacrifice arrangement made on or after 9 July 2015.
The add-back exists precisely so sacrifice cannot be used to duck the tapered Annual
Allowance.

### Actual

`IncomeDefinitionsService::getPensionContributions()` reads `annual_salary`,
`employee_contribution_percent` and `employer_contribution_percent`. It does not
consult `dc_pensions.salary_sacrifice` for the arithmetic at all — the flag exists,
is written by `capture_salary_sacrifice` (`CoordinatingAgent.php:5258`), is validated
in `PensionStore` and `TaxStrategyCalculateRequest`, and is read by
`SalarySacrificeNiStrategy` and `RetirementActionDefinitionService`, but never by the
service that decides threshold income.

So for a sacrificing user the service deducts `salary × employee_contribution_percent`
from total income and adds nothing back.

**Both readings of the data give a wrong answer, and the data cannot tell you which:**

| If `users.annual_employment_income` is… | Then the current code… |
|---|---|
| **pre-sacrifice** (gross, before the give-up) | deducts once, reaching post-sacrifice pay — and then omits the s228ZA(3) add-back, understating threshold income |
| **post-sacrifice** (what actually hits the payslip) | deducts the sacrificed amount a **second** time, understating threshold income by twice the contribution |

Nothing in the schema, the form, or the capture tool records which figure the user
entered. **Guessing moves a user's taper position, so W-0189 deliberately did not
guess.** The panel now names the arrangement (`pension_arrangement`, published by
`IncomeDefinitionsService`) so the treatment is visible rather than silent, and says
plainly that the pay given up is not added back.

Under sacrifice, the contribution is also legally the **employer's**, so it belongs
in adjusted income's employer figure rather than the employee one — a second
consequence of the same gap.

### Impact

**Latent today, not live.** Verified 2026-08-22 against the development database:
`dc_pensions.salary_sacrifice` is `true` on **0** rows, `false` on **0**, `null` on
**10**. No current user is affected.

It becomes live the moment a user answers the salary sacrifice question, and it bites
hardest exactly where it matters: someone sacrificing to stay under the £200,000
threshold-income gate is the person most likely to be near it. Understating threshold
income keeps the £60,000 Annual Allowance in place where a taper should apply, which
is an under-stated tax charge, not a cosmetic one.

### Repro

1. Any user with employment income and a workplace Defined Contribution pension.
2. `php artisan tinker` → set `salary_sacrifice = true` on their `DCPension`.
3. `app(IncomeDefinitionsService::class)->calculate($id)` — `threshold_income` is
   unchanged from the non-sacrifice case. No add-back, no reclassification.

### Acceptance

1. A decision, recorded, on whether `users.annual_employment_income` is the pre- or
   post-sacrifice figure — **or** a data change that records it, so the question
   stops being unanswerable. This is the blocking half; the arithmetic follows from it.
2. Threshold income applies the FA 2004 s228ZA(3) add-back for arrangements on or
   after 9 July 2015, and adjusted income counts sacrificed pay as an employer
   contribution.
3. The Income Definitions panel's arrangement sentence updated to state the treatment
   actually applied, replacing the current wording which was written to be truthful
   about the gap rather than to describe a fix.
4. Pinned by tests covering both a sacrificing and a non-sacrificing user at the
   £200,000 threshold-income gate, so the taper decision is exercised and not just
   the figure.
5. **`/m` and native have no Income Definitions counterpart** (verified by grep,
   2026-08-22) — but every consumer of the service is shared, so the Annual Allowance
   figures those surfaces show are affected. Confirm before assuming web-only.

## Working notes
(append-only)

- 2026-08-22 cycle2-audit (build-lead): raised from `F-0020`, not fixed. **The
  arithmetic is genuinely undecidable from the data**, which is why this is an item
  and not a line in W-0189. W-0189 shipped the honest interim: name the arrangement,
  claim nothing that was not applied. See `F-0020` §6.

## Working notes

- 2026-08-25 tax-compliance-reviewer, via Brett — **measured, and severity raised
  medium → high.** Reached independently while gating W-0205, written up as W-0487
  before that item was found to duplicate this one; W-0487 is closed and its evidence
  moved here.

  **The figures.** Measured through `AnnualAllowanceChecker`, not argued: a sacrificing
  earner is told their Annual Allowance is **£60,000** where FA 2004 s228ZA gives
  **£56,750**.

  **The proof the branch does not exist.** The runs are **byte-identical with the
  salary-sacrifice flag set and unset.** That is stronger than inferring the add-back is
  missing — the flag has no effect on the output at all, and the service already
  publishes `arrangement: 'salary_sacrifice'`, so the information is present and the
  arithmetic simply does not read it.

  **This item's central argument is narrower than it reads, and that matters.** The
  Intent says the deduction is "wrong either way, and which way cannot be determined
  from the data" — the pre/post-sacrifice ambiguity. True in general. But **in the
  measured fixture both readings clear the £200,000 threshold**, so the taper could be
  applied without resolving the ambiguity at all.

  So the ambiguity defence holds only in the band immediately around the threshold, and
  it is currently being applied **generally**. That converts this from "blocked on an
  unanswerable data question" to "answerable for most affected users, with a stated
  assumption for the rest" — which is why the severity moves.

  Direction of harm is the bad one: an overstated Annual Allowance invites a pension
  contribution that triggers an unexpected annual allowance charge.

  **Recorded so nobody re-derives it:** the reviewer nearly filed an adjacent false
  defect, reasoning from policy that net-pay contributions must also be added back to
  threshold income. They are added back to **adjusted** income under s228ZA(4), not to
  threshold. legislation.gov.uk and PTM057100 agree against that reading.

## Resolution — 2026-08-29

**Acceptance 1 — CSJ decided on 2026-08-28: ask, do not assume.**
`users.employment_income_basis` is `enum('gross','post_sacrifice')`, nullable, and null
means *not asked* rather than a default. It is put to a sacrificing user under the
Employment Income field itself, keyed off `pension_arrangement` — already published by the
service that needs the answer, so nothing new is plumbed to ask it. There was no legacy
data to migrate: `dc_pensions.salary_sacrifice` was null on every row, so no user's figures
move on the day this lands.

Where the question is unanswered the stated assumption is `gross`, published as
`assumed_gross` so it can be named rather than applied silently. The panel says so and asks.

**The ambiguity turns out to move net income, not the taper decision — and that is the
important finding.** The basis is applied *before* any definition is struck: if the recorded
figure is the pre-sacrifice one, the sacrificed pay comes out to reach what the user
actually earns; if it is the post-sacrifice figure it was never in. Either way the same
threshold income comes out the other side. `it reaches the same threshold income whichever
basis the user recorded` pins that with one person described two ways — £210,000 gross and
£193,200 post-sacrifice, landing on £210,000 of threshold income both times. **So the taper
decision never turns on the guess**, which is what the 2026-08-25 note suspected and this
now demonstrates.

**Acceptance 2 — the arithmetic.**

- **s228ZA(3) add-back applied.** `$thresholdIncome = $totalIncome − netPayEmployee +
  $sacrificed`.
- **Sacrificed pay is the employer's contribution, not the employee's.** It is removed from
  the employee total entirely — it was never a s24 relief, because the pay was given up
  before it was earned — and added to the employer figure, so it reaches adjusted income
  where the statute counts it.
- `getPensionContributions()` returns `sacrificed` as its own figure, and the deductions
  block publishes `salary_sacrificed` separately from the employer total it now sits inside,
  because it is the figure added back and the reader has to be able to find it.

**Acceptance 3 — the panel copy.** The old sentence ("the pay you give up is not added back
here") was written to be truthful about a gap. It now describes the treatment applied, and
takes a second form when the basis was assumed, which asks for the answer.

**Acceptance 4 — the tests.** Six, in
`tests/Unit/Services/Tax/SalarySacrificeReachesThresholdIncomeTest`, including the taper
DECISION at the £200,000 / £260,000 gates rather than only the figure, and a net-pay
control. **Mutation-verified:** removing the add-back turns three of the six red.

`IncomeDefinitionsServiceTest`'s `names salary sacrifice where a workplace pension uses it`
pinned the W-0189 interim — "naming it does NOT change the figures". It now asserts the
opposite, deliberately, and says why at the line.

**Acceptance 5 — Rule 19.** Confirmed rather than assumed. There is no Income Definitions
panel on `/m` or native, but **every consumer of the service is shared**
(`AnnualAllowanceChecker`, `TaxStrategyMath`, `TaperedAnnualAllowanceStrategy`,
`ChildBenefitService`), so the Annual Allowance figures those surfaces show are corrected by
the same change. The question itself is only asked on web, which is the only surface with an
Employment Income field to ask it beside.

## Not fixed here

The question is asked on the web profile form. **Fyn's `capture_salary_sacrifice` tool does
not yet ask it**, so a user who declares sacrifice through Fyn gets `assumed_gross` until
they visit the profile. Filed as W-0518 rather than carried silently.
