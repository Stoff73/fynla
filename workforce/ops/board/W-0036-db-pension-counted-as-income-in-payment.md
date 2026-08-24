---
id: W-0036
title: A Defined Benefit pension is counted as income in payment from the day it is entered — a 48-year-old is treated as receiving her NHS pension
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: gated
claimed_by: fix-batch-C
claimed_at: 2026-08-21
branch: fixes/F-0001-batch-c-retirement-profile-gates.md
severity: high
surfaces: [web, m, ios]
created: 2026-08-21T12:25:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: [W-0017, W-0035]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, playbook preparation — while tracing why the
app derived a £116,250 retirement target for Sarah instead of her stated £55,000
(W-0035). Local `localhost:8000`, premium. Account **Sarah Jones (17)**.

**Surface:** `UserProfileService::calculateAnnualPensionIncome()`, and therefore every
figure keyed on total annual income — income tax, Child Benefit / High Income Child
Benefit Charge, the retirement target derivation, net income, and the income
statement on `/valuable-info?section=income`.

**Not in any current batch.** Batch C owns the Defined Benefit *form's* missing fields
(W-0017); this is about how the value the form already stores is later interpreted.

### Expected

Sarah is **48**. Her NHS Defined Benefit pension has a Normal Retirement Age of **60**
and pays **£35,000 a year from then**. She is not receiving it today.

Her total annual income should be her salary of **£120,000**, plus rental once the
Buy-to-Let properties are entered — never the pension.

The function's own docblock states the intended rule:

> "Includes DB pensions (**if in payment**) and state pension (if receiving)."

### Actual

`app/Services/UserProfile/UserProfileService.php:338-356` counts **any** Defined
Benefit pension with a non-zero `accrued_annual_pension` as income in payment. There
is no check against `normal_retirement_age`, no check against the user's age, and no
in-payment flag consulted:

```php
foreach ($user->dbPensions as $dbPension) {
    // Check if pension is in payment (accrued_annual_pension represents current annual amount)
    if ($dbPension->accrued_annual_pension > 0) {
        $pensionIncome += (float) $dbPension->accrued_annual_pension;
    }
}
```

The comment asserts the check that the code does not perform. The **state pension**
branch immediately below, in the same function, does gate correctly on
`$statePension->already_receiving` — so the two halves of one function disagree about
whether a future entitlement is current income.

The value it reads is unambiguously a future figure. `DBPensionForm.vue:89` labels the
input "**Annual Income at Retirement (£)**", and `dbPensionFields.js:56` maps it
straight to `accrued_annual_pension`. The table also carries a separate
`projected_annual_pension_at_nra_gbp` column, so the schema distinguishes accrued-to-
date from projected-at-Normal-Retirement-Age — the form writes the retirement figure
into the accrued column, and the service then reads the accrued column as income now.

**Observed consequence.** Sarah's total annual income resolves to **£155,000**
(£120,000 + £35,000). Fed through
`RequiredCapitalCalculator::calculateUserNetIncome()` × 75% that produces the
**£116,250** retirement target the previous pass observed (R-08 §4), against her
stated £55,000. The arithmetic reproduces exactly, which is what identified the cause.

### Impact

Income tax: an extra £35,000 of phantom income pushes Sarah from £120,000 to
£155,000, past the £125,140 additional-rate threshold and through the whole Personal
Allowance taper. Her modelled tax, net income, take-home and Personal Allowance are
all wrong, and the tax strategy page's recommendations for her are computed on it.

Child Benefit / High Income Child Benefit Charge is computed against
`$totalAnnualIncome` (`UserProfileService.php:609`) — with two children, the phantom
income changes that position too.

Retirement: her target income is inflated by 111% (W-0035 is the entry-point half of
that story; this is the derivation half).

The defect scales with how large the Defined Benefit entitlement is, and hits exactly
the users Fynla is for — public-sector professionals decades from their Normal
Retirement Age. Anyone with a Defined Benefit scheme is affected from the moment they
record it.

### Repro

1. Register a user aged well under any retirement age; set employment income £120,000.
2. `/net-worth/retirement` → Add Pension → Defined Benefit → "Annual Income at
   Retirement (£)" = 35000, Normal Retirement Age 60. Save.
3. `/valuable-info?section=income` — total annual income reads **£155,000**, not
   £120,000.
4. `GET /api/retirement/required-capital` → `required_income` 116250
   (155,000 × 0.75), `income_source: "calculated"`.
5. `php artisan tinker` →
   `app(UserProfileService::class)->getCompleteProfile($user)['income_occupation']['annual_pension_income']`
   returns `35000` for a 48-year-old.

### Evidence

- `app/Services/UserProfile/UserProfileService.php:336-356` — the function, its
  docblock, and the asymmetry with the state pension branch
- `app/Services/UserProfile/UserProfileService.php:555, 597, 609, 628` — how
  `$pensionIncome` reaches `total_annual_income`, the detailed tax calculation and the
  Child Benefit position
- `resources/js/components/Retirement/DBPensionForm.vue:89` — the field is labelled
  "Annual Income at Retirement"
- `resources/js/components/Retirement/dbPensionFields.js:56` — maps it to
  `accrued_annual_pension`
- `db_pensions` schema — `accrued_annual_pension` **and**
  `projected_annual_pension_at_nra_gbp` both exist
- `app/Services/Retirement/RequiredCapitalCalculator.php:156-168` — where it becomes a
  retirement target
- Persona lines: `tests/Persona/peak_earners.md` — Sarah DOB 1978-04-22 (age 48);
  NHS Pension "Annual Pension £35,000", "Normal Retirement Age 60"

## Acceptance

- [ ] A Defined Benefit pension counts toward current income only when it is actually
      in payment — gated on the user's age against `normal_retirement_age`, or on an
      explicit in-payment flag, matching how the state pension branch already gates on
      `already_receiving`.
- [ ] The docblock and the code agree. Right now the docblock describes the correct
      behaviour and the code does something else.
- [ ] Decide deliberately which column the form should write. If "Annual Income at
      Retirement" is a projection, it belongs in
      `projected_annual_pension_at_nra_gbp`, and `accrued_annual_pension` should hold
      accrued-to-date — or the two columns should be reconciled into one meaning.
      Coordinate with W-0017, which is already reworking this form.
- [ ] Sarah's `total_annual_income` reads **£120,000** (plus rental once entered), her
      income tax and Personal Allowance are recomputed accordingly, and her Child
      Benefit / High Income Child Benefit Charge position is re-derived.
- [ ] `RequiredCapitalCalculator` then derives **£90,000** for her
      (120,000 × 0.75) rather than £116,250 — and once W-0035 lands, her stated
      **£55,000** takes precedence over both.
- [ ] A test pins this: a user below Normal Retirement Age with a Defined Benefit
      pension has zero pension income. Nothing currently catches it.
- [ ] Audit the other readers of `annual_pension_income` and `total_annual_income` for
      the same assumption (Rule 20 — one answer to "what does this household earn").
- [ ] `/m` and iOS income surfaces show the corrected figure (Rule 19).
- [ ] Re-verified live in the browser by the persona run, both accounts.

## Working notes

Found by arithmetic, not by looking: £116,250 ÷ 0.75 = £155,000, and £155,000 −
£120,000 = £35,000 is exactly the NHS pension. That is worth noting as a technique —
the observed figure was reverse-engineered to its inputs, which is what located a
defect two layers below the screen it showed up on.

Interaction with **W-0035**: fixing W-0035 alone would hide this, because an explicit
£55,000 target would override the derived £116,250 and the phantom income would stop
being visible on the retirement screen. It would still be corrupting income tax,
Personal Allowance and Child Benefit. **Fix both, and fix this one first** — otherwise
W-0035's fix masks it.

- 2026-08-21 build-lead: FIXED. One gate, three services, verified on Sarah's real row.

  **The item found one copy of the bug. There were three.** Byte-identical private
  functions, each gating the State Pension correctly on `already_receiving` four
  lines below an ungated Defined Benefit loop, each with a docblock asserting the
  check its code did not do:
  - `app/Services/UserProfile/UserProfileService.php` — `calculateAnnualPensionIncome()`
  - `app/Services/Tax/IncomeDefinitionsService.php` — `calculatePensionIncome()`
  - `app/Services/UserProfile/PersonalAccountsService.php` — `calculateAnnualPensionIncome()`

  The second one is why this is a tax defect rather than a retirement display one.

  **Fixed as convergence, not a second check** (the team lead's read was right):
  - `app/Models/DBPension.php:82-115` — `isInPayment(?int $userAge)`, the per-record
    predicate, plus `DEFAULT_NORMAL_RETIREMENT_AGE = 67`, deliberately the same
    constant value as `PensionProjector::DEFAULT_RETIREMENT_AGE` so a pension cannot
    count as income from one age while being projected from another.
  - `app/Traits/ResolvesIncome.php:26-58` — `resolvePensionIncomeInPayment()`, the
    household sum including the State Pension gate. `ResolvesIncome` was already the
    documented shared home for income resolution; the three services now `use` it and
    their private copies are three lines each.

  **Behaviour:** a Defined Benefit pension counts only when the user's age has reached
  `normal_retirement_age`, falling back to 67 when the scheme records none. A null
  date of birth counts nothing — inventing income is the failure being fixed.

  The null-Normal-Retirement-Age fallback matters and is not cosmetic: Sarah's real
  row (`db_pensions.id 4`) has NULL there, and excluding-on-null would have stripped
  income from genuinely retired users with the same NULL. Both directions are pinned
  by test.

  **Verified on the live local row, not only in test:**
  ```
  Sarah (17): age=48, employment=£120,000
  pension 4: accrued=35000.00 nra=NULL  ->  isInPayment=FALSE
  annual_pension_income = 0.00        (was 35,000.00)
  total_annual_income   = 120,000.00  (was 155,000.00)
  RequiredCapitalCalculator: required_income = 90,000.00  (was 116,250.00)
  ```
  £90,000 is exactly the figure the acceptance predicted.

  **Acceptance, item by item**
  1. Gated on age vs Normal Retirement Age, matching the State Pension branch — done.
  2. Docblock and code agree — the docblocks were already right; the code now does
     what they said.
  3. **Which column the form writes — decided: leave it as `accrued_annual_pension`.**
     `projected_annual_pension_at_nra_gbp` is NOT a user-input column; it is derived,
     and `PensionDerivedColumnCalculator::calculateDb()` currently sets it to a
     rounded copy of `accrued_annual_pension`. Moving the form's write there would
     make a derived column user-authored and collide with W-0017's landed work. The
     real defect was never which column held the number — it was that nothing asked
     whether the number was payable yet. Recorded rather than silently left.
  4. Sarah reads £120,000 — verified above.
  5. £90,000 derived — verified above. Her stated £55,000 takes precedence once
     W-0035 lands.
  6. Test pinning it — `tests/Feature/Income/DbPensionNotInPaymentTest.php`, 7 tests:
     below age, at age, all three services agreeing, both null-Normal-Retirement-Age
     directions, null date of birth, the State Pension gate still working, and the
     per-record predicate.
  7. **Audit of other readers — done.** The three above were the only ones treating
     `accrued_annual_pension` as income now. The rest are legitimate and unchanged:
     `PensionProjector`, `RetirementProjectionService:630`,
     `RetirementIncomeService:295` and `RetirementProjectionContractService:90` all
     want the future figure by design; `NetWorthService:318` capitalises it (x20 plus
     lump sum) as an asset, not income; `ModuleDataRequirementsService:722` asks only
     whether a pension exists.
  8. **`/m` and iOS — fixed by the same change, no second edit.** `/m` Income reads
     `/api/user/profile` → `income_summary` → `incomeSources()` →
     `IncomeDefinitionsService`; `/m` Personal Information reads
     `income_occupation.total_annual_income` from the same payload. Verified for
     Sarah: `/m` income sources now return `{"employment":120000}` with no pension
     line, and `IncomeDefinitionsService` reports `pension_income = 0.00`.
  9. Browser verification — persona-tester's, not closed here.

  **Child Benefit — corrected mechanism, but no change to Sarah's own figure, and I
  am not claiming one.** Her position is computed from `total_annual_income`, which
  is now right. But her account currently records **zero** eligible children
  (`eligible_children: 0`, `annual_amount: 0`), so her Child Benefit figure is £0
  either way. The persona specifies two children; they have not been entered yet.
  Re-check once they are.

  **Tests:** `tests/Feature/Income/DbPensionNotInPaymentTest.php` 7 passed. Wider
  family (`Api/UserProfileControllerTest`, `Api/UserProfileIncomeSummaryTest`,
  `Unit/Services/UserProfileServiceTest`, `Unit/Services/UserProfile`,
  `Feature/Income`) — **76 passed, 368 assertions**, so nothing had baked in the old
  behaviour. Tax family run separately.

  **Sequencing respected:** this landed before W-0035, as the tester established. An
  explicit target would have hidden the phantom income on the retirement screen while
  it carried on corrupting tax, Personal Allowance and Child Benefit.

  **Noticed:** `getCompleteProfile()` emits a PHP deprecation from
  `vendor/.../BelongsTo.php:187` ("Using null as an array offset"). Pre-existing,
  unrelated to this change, visible on any tinker call — worth its own item.
