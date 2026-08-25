---
id: W-0423
title: "\"Earned Income £159,290 · NI Applies\" heads a card that is £145,000 of salary and £14,290 of rent"
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0030-cycle4-letter-and-income-labels.md
owner: build-lead (fix-cycle4-letter-income)
status: done
severity: low
surfaces: [web]
created: 2026-08-23T02:05:00Z
claimed: 2026-08-23T02:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0175]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Reported as D-28. `UKTaxCalculator::calculateDetailedNetIncome` builds one card
for everything taxed at the main rates — employment, self-employment, **rental
profit** and **pension income in payment** — and labelled it `'Earned Income'`
unconditionally, with `TaxIncomeCard.vue`'s flat **"NI Applies"** badge beside the
combined gross.

Rental profit is neither earned income nor liable to National Insurance. **The
computation beneath is correct** — labelled "Class 1 (Employment)" and working on
£145,000 — so only the header claimed anything false. On the one page whose whole
value is that a reader can check it by hand, a mislabelled header over a right
number is the claim they have no way to verify.

Same reasoning as W-0175, which renamed "Rental Income" to "Rental Profit" in the
components list of this very card. The header was missed then.

**The bands cannot answer what National Insurance is charged on:** they start at
the primary threshold, so `main_rate.earnings + additional_rate.earnings` is
£132,430 against pay of £145,000. A badge summing them would name a figure the
payslip does not hold — so the base is published by the calculator instead.

Also fixed while rewriting the badge: `NI` / `No NI` are acronyms in user-facing
text (Rule 9).

## Acceptance

1. The header no longer asserts National Insurance applies to rental profit.
2. The label names what the card holds and stays true as the mix changes.
3. `gross_amount` is unchanged — the number was never wrong.

---

## Outcome — 2026-08-23, build-lead (`fix-cycle4-letter-income`)

**FIXED.**

### What was done

`UKTaxCalculator::combinedIncomeLabel()` builds the card's label from the kinds actually
present, so it stays true as the mix changes: David's card reads **"Earned and Rental
Income £159,290"**. `TaxIncomeCard.vue`'s blanket **"NI Applies"** badge is replaced by
**"National Insurance on £145,000"**, naming its base.

**The base is published by the calculator (`ni_breakdown.class_1.base` /
`class_4.base`) because the bands cannot supply it** — they start at the primary threshold
and sum to £132,430 against pay of £145,000. A badge summing them would name a figure the
payslip does not hold.

`gross_amount` is unchanged; the number was never wrong. `NI` / `No NI` spelled out (Rule 9).

**Verified in the browser** on David (£145,000 base under a £159,290 card) and Sarah
(£120,000 under £128,880).

### What the receiver needs that is not obvious

- **`income_type_label` and `ni_breakdown` have exactly one consumer**, `TaxIncomeCard.vue`,
  and it branches on `income_type === 'earned'`, **not** on the label text. Checked before
  renaming; no `/m` consumer and no test asserted the old literal.
- The stale comment at `UKTaxCalculator:66` calling this the *"Earned Income" card* was
  corrected in the same change — it was made stale by this edit.

### Assumption made

That naming the kinds present ("Earned and Rental Income") is preferable to inventing a new
taxonomy such as "Income Taxed at Standard Rates". It is generated from the data, so it
cannot drift back, and it does not introduce a term the user has not already seen on the
page.

### Tests

`tests/Unit/Services/Tax/CombinedIncomeCardLabellingTest.php` — four label cases, three
National Insurance base cases. Mutations T1 (hardcoded label) and T2 (base inflated to the
combined gross) each redden exactly their own group.
