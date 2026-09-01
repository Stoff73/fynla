---
id: W-0178
title: Decide whether the monthly maintenance reserve and "other" property costs belong in the allowable-letting-expenses list that produces every user's rental profit
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: compliance-lead
status: done
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T21:05:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22T21:05:00Z
prior_art_found: ["app/Services/Property/PropertyService::calculateTaxPosition():85-97 — the one allowable-expense list, eight fields", "app/Services/Property/PropertyService::annualRentalTaxPosition() — the one aggregate every consumer now reads (W-0175)", "app/Models/Property — monthly_maintenance_reserve and the other cost columns", "app/Services/Property/PropertyService::calculateTotalMonthlyCosts() — a SEPARATE, wider list for cash-flow, deliberately not the tax list"]
prior_art_outcome: null
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

Raised by: build-lead (`cycle1-tax`) while fixing **W-0175**, at team-lead's direction —
separated out so it is decided rather than settled quietly inside a fix.

**This is a tax-accuracy question, not an engineering one.** No code change is proposed
here. The answer decides a number every buy-to-let user sees.

### The question

`PropertyService::calculateTaxPosition()` deducts eight monthly cost fields from rent to
produce the taxable rental profit:

| Deducted | Not deducted |
|---|---|
| gas · electricity · water · buildings insurance · contents insurance · service charge · ground rent · managing agent fee | **`monthly_maintenance_reserve`** · **`other_monthly_costs`** · `monthly_council_tax` · mortgage payments |

Mortgage interest is correctly outside the list — it is relieved as the Section 24 basic
rate tax credit, not as an expense — and council tax on a let property is normally the
tenant's. **The open items are `monthly_maintenance_reserve` and `other_monthly_costs`.**

### Why it is genuinely arguable

**For excluding them (the current behaviour):**

- A *reserve* is money set aside, not money spent. HMRC allows expenses **incurred**; a
  sinking fund contribution is not an incurred repair.
- Capital improvements are never allowable, and "maintenance" as captured by the form
  cannot distinguish a repair from an improvement.
- `other_monthly_costs` is uncategorised by definition, so nothing can establish it is
  wholly and exclusively for the property business.

**For including them, or part of them:**

- Most users entering a "maintenance" figure mean actual repairs, which **are**
  allowable. Excluding them overstates taxable profit, and this figure now feeds total
  income, adjusted net income and threshold income (W-0175) — so it can move a Personal
  Allowance or a pension annual allowance taper.
- The error runs against the user: they are shown more taxable income than they have.
  That is the opposite direction from W-0174 but it is still wrong.
- A leaseholder's service charge is already deducted, and a sinking-fund element inside
  it is not separated either — so the reserve is being treated inconsistently depending
  on which field the user happened to put it in.

### What is NOT in question

The other six fields, the Section 24 treatment, and the ownership-share arithmetic. All
verified correct during W-0175 and W-0174.

## Acceptance

1. A decision, attributed to a named founder or to `compliance-lead` within competence,
   on whether `monthly_maintenance_reserve` and `other_monthly_costs` are deductible in
   the rental tax computation — including "part of them", if the right answer is to split
   the capture rather than the list.
2. If the answer is "it depends on what the user meant", the decision covers **how the
   form should ask**, not just what the calculator should do — an uncategorised field
   cannot be made deductible after the fact.
3. Whatever is decided is reflected in **one place**, `PropertyService::calculateTaxPosition()`
   (Rule 20), and the user-facing note naming the deducted expenses is updated with it
   (`resources/js/components/UserProfile/IncomeOccupation.vue`).
4. If the list changes, W-0175's regression tests move with it —
   `tests/Unit/Services/Tax/RentalIncomeOneDefinitionTest.php` asserts explicitly that the
   maintenance reserve is **not** deducted, and that assertion encodes today's answer, not
   a law.

## Working notes

**2026-08-22 — build-lead (`cycle1-tax`). Raised, not claimed.**

Left deliberately undecided while fixing W-0175. The distinction I applied: *which figure
enters total income* is a question the statute answers, so I settled it; *which expenses
are allowable* is a judgement about incurred cost and intent, so it is not mine to settle
unilaterally.

**Impact if the current behaviour is wrong.** Measured on the `peak_earners` rows,
read-only:

| Property | reserve | other | annual | share | user's share |
|---|---|---|---|---|---|
| Flat 42, Riverside Apartments | £100 | £150 | £3,000 | 50% each | £1,500 David, £1,500 Sarah |
| Unit 12, Victoria Mill | £85 | £120 | £2,460 | 40% David | £984 David |

Both are additional-rate taxpayers once W-0174 is fixed, so at 45% the **upper bound** is
roughly **£1,118 a year for David and £675 for Sarah** — about £1,793 for the household,
in the direction of tax they may not owe. Upper bound because it assumes both fields are
wholly allowable, which is exactly what is in question; the honest answer is likely
somewhere between that and zero.

Every buy-to-let holder with those fields populated is affected, and since W-0175 the
figure also propagates into adjusted net income and threshold income, so at the margin it
can move an allowance as well as a tax bill.

**Deliberately not fixed pending this decision**, so nobody reads the current list as
settled: `app/Services/Property/PropertyService.php:85-97`.

**Note for whoever claims it.** `calculateTotalMonthlyCosts()` in the same class uses a
**wider** list including council tax and mortgage payments. That is correct — it answers a
cash-flow question, not a tax one. The two lists differing is by design; do not "align"
them.

---

## 2026-09-01 — analysis done, calculator change gated, disclosure fixed

**Verified live first.** `app/Services/Property/PropertyService.php:134-141` deducts
exactly the eight fields the item names; the reserve and `other_monthly_costs` are
excluded. The item's description is accurate.

### The tax analysis, within competence

On the question as put — are these two deductible **as currently captured** — the
answer is **no, and the current behaviour is right**:

- **`monthly_maintenance_reserve`.** An expense must be *incurred* wholly and
  exclusively for the property business. A sinking-fund contribution is money set
  aside by the landlord for the landlord; nothing has been spent and no supplier has
  been paid. It becomes allowable when the repair is done, not when the reserve is
  funded — and only if the work is a repair rather than an improvement.
- **`other_monthly_costs`.** Uncategorised by definition, so the wholly-and-exclusively
  test cannot be satisfied by anything the application holds. Deducting it would be
  deducting an unknown.

**So no change to the deduction list is recommended.** The item's "for including them"
arguments are real, but they are arguments that the *capture* is wrong, not that the
*calculation* is.

### What that leaves — the actual defect, and why it is gated

A landlord's genuine repairs **are** allowable, and there is **no field for them**.
The user with £120 a month of real repair spending has only `monthly_maintenance_reserve`
to put it in, and that field is correctly excluded — so their taxable rental profit is
overstated, and since W-0175 that figure feeds total income, adjusted net income and
threshold income, where it can move a Personal Allowance or an annual-allowance taper.

Fixing that means **splitting the capture**: a new "repairs and maintenance actually
paid" field that is deducted, alongside the reserve that is not. That is acceptance 2,
and it changes the taxable income of every buy-to-let user — precisely the thing this
item exists to have *decided* rather than settled quietly inside a fix. Acceptance 1
requires a named founder or `compliance-lead`. **Gated on that, not on engineering.**

### Done now, because it is true under either answer

The user-facing note listed what IS deducted and never what is not, so a landlord who
entered a maintenance figure had no way to learn it had been left out.
`resources/js/components/UserProfile/IncomeOccupation.vue:455-468` now names the
exclusion and why. This satisfies the disclosure half of acceptance 3 and does not
pre-empt the decision — if the answer changes the list, this sentence changes with it.

**Untouched:** `PropertyService::calculateTaxPosition()` and
`tests/Unit/Services/Tax/RentalIncomeOneDefinitionTest.php`, whose assertion that the
reserve is not deducted still encodes today's answer correctly.

### For the decision-maker, the question in one line

*Should Fynla capture "repairs actually paid" separately from "maintenance reserve", so
that real repair spending is deducted and the sinking fund is not?* Recommended: yes.

- 2026-09-01 board-loop: **CLOSED — CSJ ruled, and the ruling is implemented.**
  CSJ's decision, recorded on the board: *"what use is it having a profit figure
  without expenses, this is stupid, include the expenses"*. That answers acceptance 1
  for both fields, in the "include them" direction.

  **Acceptance 3 — one place.** `app/Services/Property/PropertyService.php:133-155`.
  `monthly_maintenance_reserve` and `other_monthly_costs` join the allowable list; the
  reasoning on both sides is at the line, so the next reader sees why the argument for
  excluding them lost rather than assuming nobody made it. Mortgage interest and
  council tax stay out, unchanged and for the reasons already established.

  The user-facing note moved with it —
  `resources/js/components/UserProfile/IncomeOccupation.vue:450-465`. It named the
  exclusion; it now names the two fields among the expenses, and names mortgage
  payments as the one exclusion, with the Section 24 credit as the reason. Rule 19:
  `/m` and native carry no equivalent note — grep for "letting expenses" across
  `resources/mobile/` and `ios-native/` returns nothing — so there is no second copy
  to move, and the figure itself is the shared backend one.

  **Acceptance 4 — the test that encoded the old answer.**
  `tests/Unit/Services/Tax/RentalIncomeOneDefinitionTest.php:90` asserted the reserve
  was *not* deducted. Inverted rather than deleted, with the reasoning at the line, and
  a sibling case added for `other_monthly_costs`. Every figure in that file and in
  `tests/Feature/Property/JointRentalIncomeReachesBothOwnersTest.php` moved with the
  £100/month reserve now being allowable: half-share £8,880 → £8,280.

  Tests: 17 passed on the two files directly; **289 passed** across
  Property / Rental / IncomeDefinitions / Section24.

  **Acceptance 2 not done, deliberately.** Splitting the capture — asking the landlord
  whether a maintenance figure is a repair or an improvement — is a form redesign CSJ
  did not ask for and the ruling did not require. Deducting an improvement is the
  residual inaccuracy, and it runs in the user's favour rather than against them, which
  is the direction this item was raised to correct. Left as a stated limitation, not a
  silent one.
