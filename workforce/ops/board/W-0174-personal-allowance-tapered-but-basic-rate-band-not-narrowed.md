---
id: W-0174
title: The Personal Allowance is correctly tapered to £0 but the basic-rate band is not narrowed with it — every affected user is under-taxed by £2,514
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0017-cycle1-tax-income-and-allowances.md
owner: build-lead
status: gated
severity: high
surfaces: [web, m, ios]
created: 2026-08-21T23:40:00Z
claimed: 2026-08-22T19:20:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22T19:20:00Z
prior_art_found: ["app/Services/TaxBandTracker.php:31-46 — first home of the inverted derivation", "app/Services/UKTaxCalculator.php:669-673 — second home of the same defect", "database/seeders/TaxConfigurationSeeder.php:104-111 — bands[0].max already seeded as the £37,700 band WIDTH, unread", "app/Services/Tax/TaxStrategyMath::bandThresholds() — band classification, checked and sound", "W-0175"]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, local `localhost:8000`, journey re-walk from the
beginning. Driven in Playwright on **both** persona accounts —
**David Jones `users.id 16`** and **Sarah Jones `users.id 17`**.

**Surface:** `/valuable-info?section=income` → "Estimated Tax and National Insurance".
Routed to `tax-compliance-reviewer`: this is the income tax computation itself.

### Expected

Where adjusted net income exceeds £100,000, the Personal Allowance tapers at £1 per £2
and is extinguished entirely by £125,140. **The basic-rate band is £37,700 wide.** It
does not grow when the allowance shrinks — the £50,270 figure is the *threshold* that
results from £12,570 of allowance plus £37,700 of basic-rate band, so removing the
allowance moves the threshold down to £37,700; it does not leave it at £50,270.

**David**, taxable income £147,690 (£159,290 less £11,600 pension), Personal Allowance £0:

| Slice | £ | Rate | Tax |
|---|---|---|---|
| 0 → 37,700 | 37,700 | 20% | 7,540 |
| 37,700 → 125,140 | 87,440 | 40% | 34,976 |
| 125,140 → 147,690 | 22,550 | 45% | 10,147.50 |
| **Total** | | | **£52,663.50** |

**Sarah**, taxable income £128,880, Personal Allowance £0: 7,540 + 34,976 + 1,683 =
**£44,199**.

### Actual

**Both accounts show the basic-rate band at £50,270.** Verbatim, David
(`/valuable-info?section=income`):

```
Taxable Income                £147,690
INCOME TAX
  Basic:      £50,270 @ 20%   -£10,054
  Higher:     £74,870 @ 40%   -£29,948
  Additional: £22,550 @ 45%   -£10,147
Tax Payable                   -£50,149
```

Sarah, same page, same shape:

```
  Basic:      £50,270 @ 20%   -£10,054
  Higher:     £74,870 @ 40%   -£29,948
  Additional:  £3,740 @ 45%    -£1,683
Tax Payable                   -£41,685
```

| | App | Correct | Under-charged |
|---|---|---|---|
| David | £50,149 | £52,663.50 | **£2,514.50** |
| Sarah | £41,685 | £44,199 | **£2,514.00** |
| **Household** | | | **£5,028** |

**The error is exactly £12,570 × 20% on both accounts** — the full Personal Allowance
taxed at 20% instead of 40%. The band widths confirm the model: 50,270 + 74,870 =
125,140, so the higher-rate ceiling is right and only the basic/higher boundary is wrong.

**The page knows the allowance is £0.** Its own "Your Allowances" panel, lower on the
same screen, reads **"Personal Allowance £0 (reduced from £12,570)"**. So the taper is
computed correctly and then not carried into the band arithmetic.

### Impact

This under-states income tax for **every user with adjusted net income above £100,000** —
by definition the higher-earning segment this persona represents, and the segment for
whom the pension-contribution advice on the same page is most valuable. It is a flat
£2,514 per person once the allowance is fully tapered, and a partial error for anyone in
the £100,000–£125,140 taper zone.

Everything downstream inherits it: net income (David £105,010, Sarah £83,564), disposable
income, affordability, and the dashboard's pension-relief recommendations, which are
quantified off exactly these bands.

It also runs in the direction that flatters — a user is told they keep more than they do.

### Repro

1. Log in as `david.jones@example.com` (premium; income £145,000 employment plus rental).
2. `/valuable-info?section=income`, wait ~14s for hydration.
3. Read "Estimated Tax and National Insurance": **Basic: £50,270 @ 20%**.
4. Read "Your Allowances" further down the same page: **Personal Allowance £0 (reduced
   from £12,570)**.
5. The two cannot both be right. Recompute by hand: with a £0 allowance the basic band is
   £37,700, and the total is £52,663.50 against the £50,149 shown.
6. Repeat as `sarah.jones@example.com`: identical shape, identical £2,514 gap.

### Acceptance

1. When the Personal Allowance is tapered, the basic-rate band narrows with it: the 20%
   slice is the allowance-adjusted band, not a fixed £50,270 threshold.
2. Band widths and thresholds come from `TaxConfigService`, not from constants in the
   calculator (Rule 2). Confirm the £37,700 basic-rate band width is configured and read,
   since a £50,270 threshold is only correct alongside a full allowance.
3. Verified against three points: full allowance (income < £100,000), partial taper
   (£100,000–£125,140), and full taper (above £125,140). The middle case is the one most
   likely to be missed.
4. `tax-compliance-reviewer` confirms the arithmetic and checks whether the same band
   construction is used anywhere else — the retirement and tax-strategy modules quote
   marginal rates too.
5. Verified in a browser on both persona accounts, hand-checked, and on `/m`.

### Notes

- **National Insurance on the same page is correct** and should not be disturbed:
  8% on £37,700 and 2% above the upper earnings limit, giving David £4,911 and Sarah
  £4,411 — both right.
- **The Section 24 tax credit is also correct**: £780 on each account, being 20% of the
  £3,900 share of the City Centre Flat's interest-only mortgage interest. Recorded so
  nobody "fixes" it while in the area.
- Related but distinct: **W-0175** — the rental figure feeding "Taxable Income" is stated
  two different ways on this same page. Fixing that changes the inputs here but not the
  band defect.

## Working notes

**2026-08-22 — build-lead (`cycle1-tax`). Fixed.**

### The defect had two homes, both with the derivation inverted

`app/Services/TaxBandTracker.php:38-46` and `app/Services/UKTaxCalculator.php:669-673`
each read `higher_rate_threshold` (£50,270) as a **fixed** absolute limit and then took
the basic band as `limit − personalAllowance`. That is correct only while the allowance is
whole. Tapered to £0 it yields a £50,270-wide 20% slice.

Worse, `calculateDetailedNetIncome` **wrote the tapered allowance back over
`personal_allowance` in the config array** before constructing the tracker, destroying the
only record of the full allowance the band width has to be derived from.

The constant was already in the configuration and simply not read —
`bands[0]['max'] = 37700`, commented in the seeder as "Calculator value: band width",
alongside `upper_limit = 50270`, "Display value: absolute threshold".

### One home

`app/Services/Tax/IncomeTaxBands.php` — new. Derives the limits the right way round
(`basicRateLimit = effectiveAllowance + configured band width`), holds the taper, and
carries the Gift Aid / pension band extension. It also reads
`personal_allowance_taper_rate` (0.5, already configured) rather than the literal `/2`
that had been written out separately in five services.

- `app/Services/TaxBandTracker.php:31-56` — parses no configuration of its own; takes an
  optional effective allowance as a second constructor argument.
- `app/Services/UKTaxCalculator.php:44-58` — no longer mutates the config array.
- `app/Services/UKTaxCalculator.php:653-676` — the simple path composes from the same
  object; the Gift Aid extension is `->extendedBy($giftAidGross)`.

### Measured

| | Before | After | Board expected |
|---|---|---|---|
| David | £50,149 | **£52,663.50** | £52,663.50 |
| Sarah | £41,685 | **£44,199** | £44,199 |

Slices now 37,700 / 87,440 / 22,550 for David and 37,700 / 87,440 / 3,740 for Sarah.
National Insurance unchanged at £4,910.60; the £780 Section 24 credit unchanged.

Acceptance 3 — three points, both code paths agreeing at each: £99,000 → £27,032 (full
allowance, £50,270 threshold); **£110,000 → £33,432** (allowance £7,570, higher rate
starting at £45,270 — the mid-taper case); £125,140 → £42,516 (zero allowance, £37,700
band); £159,290 → £57,883.50.

### Acceptance 4 — the same construction elsewhere

Checked, no second instance. `TaxStrategyMath::bandThresholds()`,
`PropertyTaxService.php:219` and `RetirementStrategyService.php:1236` all classify a
total income against 50,270/125,140 to pick a marginal rate — sound, because anyone
inside the taper zone is above £100,000 and lands in the same band on either arithmetic.
`RetirementIncomeService.php:740` constructs a `TaxBandTracker` from the untapered
configuration and is unaffected by the new optional argument.

### Tests

- `tests/Unit/Services/Tax/IncomeTaxBandsTest.php` — 15 passing. Every pinned number has
  a sibling that **moves the configured input and requires the output to follow**: band
  width, taper threshold, taper rate, full allowance, additional-rate threshold, rates.
- `tests/Unit/Services/UKTaxCalculatorTaperedBandTest.php` — 12 passing. Persona figures,
  the three taper points, both code paths agreeing, and five cases that rewrite the active
  `TaxConfiguration` row and require the answer to follow.

**Three existing assertions in `UKTaxCalculatorAdjustedNetIncomeTest` encoded the
defect** and are corrected — each carried a comment reading "basic band space = £50,270 −
PA", the inverted derivation written out longhand, and one described itself as matching
"pre-fix behaviour". Corrected to £37,432, £33,432 and £42,432; each moves by exactly 20
points on the allowance that case withdraws (£1,500, £1,000, £2,500). Hand-checked
against HMRC's published bands, not against the code.

### Surfaces

Wholly server-side in shared services. Neither `resources/mobile/` nor
`ios-native/Fynla/` renders a tax-band breakdown — grepped for `detailed_tax_breakdown`,
`income_breakdowns` and `basic_rate` across both — so there is no per-surface frontend
counterpart; `/m` and iOS inherit through `income_occupation`.

Not done: browser verification on either surface, by instruction — the tester closes that
loop.
