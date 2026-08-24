---
id: W-0422
title: "\"Net Income (after tax, pension contributions and tax credits)\" sits over a figure that never deducts the pension, and omits the National Insurance it does deduct"
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0030-cycle4-letter-and-income-labels.md
owner: build-lead (fix-cycle4-letter-income)
status: done
severity: medium
surfaces: [web]
created: 2026-08-23T02:05:00Z
claimed: 2026-08-23T02:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0174, W-0175, W-0176]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
---

## Intent

Reported as D-27. Measured live on David (16):

```
total income        159,289.60
income tax           51,883.32   (52,663.32 before the £780 Section 24 credit)
National Insurance    4,910.60
pension contribution 11,600.00
net_income          102,495.68  = 159,289.60 - 51,883.32 - 4,910.60
```

The label named three deductions. The figure makes two of them and adds the
credit; **the pension is not among them**, and **National Insurance, which is
always deducted, was named in no variant of the label.** The pension reduces the
TAX — income tax here is charged on Threshold Income of £147,690, three panels
above — which is precisely why it does not also reduce this line.

The code carried the same false claim as a comment:
`UserProfileService` — *"Use detailed net_income (includes pension
contributions) for consistency with TaxSummaryCard"*. It does not include them.
That is the third comment this cycle asserting a relationship the code beneath it
does not honour.

## Acceptance

1. The label states the deductions the figure makes: tax, National Insurance, and
   the credit where one applies.
2. The false comment is corrected, not left beside a corrected label.
3. A test pins the arithmetic identity the label now claims, on a fixture where
   the pension is non-zero so the two hypotheses differ by £11,600.

## Notes — the part deliberately NOT done here, and why

The report's second half is right: pension money is not available to spend, so
take-home is £90,896 and **Disposable Income is overstated by £11,600**
(£64,501.28 measured, against roughly £52,901). **But deducting the pension from
`net_income` would double-count**, and silently.

"What does this person pay into their pension" already has **two** mechanisms:

| Mechanism | Source | David |
|---|---|---|
| tax path — `calculateAnnualPensionContributions` | `employee_contribution_percent × annual_salary`, workplace schemes | £11,600 |
| spending path — `getFinancialCommitments()` `retirement` | `monthly_contribution_amount × 12`, **already inside `annual_expenditure`** | £0 |

David's DC#9 has the percentage and **`monthly_contribution_amount = NULL`**, so
his contribution is in the tax path and nowhere in the spending path. Subtracting
it from `net_income` would fix him and **charge every user who records the same
contribution as a monthly amount twice**. No seeded user has both fields
populated (verified: 0 rows), so **no test would go red and it would ship
invisible.**

The root cause is one missing bridge, and it lives in the expenditure path, which
is another agent's scope in this batch. **Raised separately as W-0424.**

---

## Outcome — 2026-08-23, build-lead (`fix-cycle4-letter-income`)

**FIXED — the label branch, deliberately not the arithmetic branch.** Team-lead confirmed
this reading before implementation.

### What was done

`IncomeOccupation.vue` — the label now states the deductions the figure makes:
*"Net Income (after tax and National Insurance, including tax credits):"*. **National
Insurance appeared in no variant of the old label and is always deducted**, so it is named
unconditionally. The false comment in `UserProfileService` (*"includes pension
contributions"*) is corrected rather than left beside a corrected label.

A note beneath discloses what the figure does not do:
*"Your pension contributions of £11,600 are not deducted here. They reduce the tax above;
the money itself goes into your pension rather than your bank account, so it is not
available to spend."*

**Verified in the browser on David (16):** label, £102,496, and the note. On Sarah (17) the
note is correctly **absent** — she has no employee contributions. The praised explanatory
copy on Threshold Income is intact, word for word.

### What was NOT done, and why — this is the important half

**`net_income` and `disposable_income` are untouched.** Subtracting
`calculateAnnualPensionContributions` would double-count for any user recording the same
contribution as `dc_pensions.monthly_contribution_amount`, which already reaches
`annual_expenditure` through `getFinancialCommitments()`. **Zero seeded pensions populate
both fields**, so nothing would have gone red and it would have shipped invisible.

Root cause and the full consumer census are **W-0424**, routed to the expenditure path.

### What the receiver needs that is not obvious

- **Disposable Income still reads £64,501 for David** and is still overstated by £11,600.
  That is expected, not a miss — the note now discloses it to the reader.
- **Three consumers bypass `DisposableIncomeAccessor`** and re-derive `net − expenditure`
  themselves: `UserContextBuilder`, `RetirementStrategyService:437`, and
  `IncomeOccupation.vue:554` client-side. Any definition change must reach all three.

### Assumption made

That "Net Income" on a tax-computation page means income after tax and National Insurance —
a well-defined figure — rather than take-home pay. The label now says exactly that, and a
test pins the arithmetic identity so it cannot drift back.

### Tests

`tests/Unit/Services/Tax/CombinedIncomeCardLabellingTest.php` — the net-income identity on a
fixture where the pension is non-zero, so the two hypotheses differ by £11,600.
