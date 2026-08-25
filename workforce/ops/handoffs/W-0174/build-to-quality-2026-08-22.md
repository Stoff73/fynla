# W-0174 — build-lead (`cycle1-tax`) → quality-lead

## Done

The Personal Allowance taper now narrows the basic-rate band with it, in one place that
every consumer reads.

- **`app/Services/Tax/IncomeTaxBands.php`** — new, the one home. Derives absolute band
  limits from `effectiveAllowance + configured band width` rather than treating £50,270
  as fixed; holds the taper (reading the configured `personal_allowance_taper_rate`, not
  a literal `/2`) and the Gift Aid / pension band extension.
- **`app/Services/TaxBandTracker.php:31-56`** — parses no configuration of its own now;
  optional second constructor argument carries the effective allowance.
- **`app/Services/UKTaxCalculator.php:44-58`** — stopped writing the tapered allowance
  back over `personal_allowance`. That overwrite was the mechanism: the band width has to
  be derived from the FULL allowance and the overwrite destroyed the only record of it.
- **`app/Services/UKTaxCalculator.php:653-676`** — the simple path had the identical
  defect and now composes from the same object.

David £52,663.50 and Sarah £44,199 — the board's expected figures to the penny. Slices
37,700 / 87,440 / 22,550 and 37,700 / 87,440 / 3,740. National Insurance and the Section
24 credit unchanged, as the item asked.

Three taper points, both code paths agreeing: £99,000 → £27,032; £110,000 → £33,432;
£125,140 → £42,516; £159,290 → £57,883.50.

Tests: `tests/Unit/Services/Tax/IncomeTaxBandsTest.php` (15) and
`tests/Unit/Services/UKTaxCalculatorTaperedBandTest.php` (12), both green. Adjacent
families re-run green: the three other `UKTaxCalculator*` suites, `IncomeDefinitions`,
`UserProfileService`, `FinancialCommitments`, `PropertyService`, `PropertyTaxService`,
`ChildBenefit`, all of `tests/Unit/Services/Retirement/`, all of `tests/Architecture/`,
the tax-config feature suites, and the `UserProfile` vitest specs. `pint --dirty` clean.

## Not done, and why

- **No browser verification, on any surface.** By instruction — you close that loop.
- **No commit, no PR, no deploy, no bundle rebuild, no tool-schema capture.** By
  instruction.
- **Nothing written to users 16 or 17.** Every check against them was read-only.

## What you need that isn't obvious from the artefacts

**Three existing assertions in `tests/Unit/Services/UKTaxCalculatorAdjustedNetIncomeTest.php`
encoded the defect, and I changed them.** Each carried a comment reading
"basic band space = £50,270 − PA" — the inverted derivation written out longhand — and
one described itself as matching "pre-fix behaviour". They are now £37,432 (was £35,932),
£33,432 (was £32,432) and £42,432 (was £39,932). Every movement is exactly 20 points on
the allowance that case withdraws: £1,500, £1,000, £2,500.

**Please check those three independently against HMRC's published bands rather than
against the code.** I hand-derived them before running anything, but a changed test
assertion is the one thing in this diff that could hide a mistake, and it is exactly the
failure mode the item is about.

One consequence worth knowing: the Gift Aid case's relief moves from £1,000 to £2,000.
That is correct — the donation now sits wholly in the 40% band instead of the wrongly
widened 20% one, and it also restores £2,500 of allowance.

**Where to look for a regression.** Anything quoting net or disposable income for a
household above £100,000 will move by up to £2,514 per person, in the direction of more
tax. The dashboard's pension-relief recommendation is quantified off exactly these bands
and should now be larger; that is the fix working, not a new defect.

**`bands[1]['max']` is a trap.** It holds £125,140 — an absolute threshold — where
`bands[0]['max']` holds £37,700, a width. `IncomeTaxBands` never reads `bands[1]['max']`;
it takes the higher-rate limit from `additional_rate_threshold`. Do not "tidy" that into
symmetry.

## Assumptions I made

- **That the additional-rate threshold does not move with the allowance.** It is £125,140
  of income whether expressed against income or taxable income, because anyone reaching
  it has no allowance left. I believe this is unconditionally true under current rules; if
  a future configuration allowed both a nonzero allowance and income above the threshold,
  the model would need revisiting.
- **That `bands[0]['max']` is a band width in every configured year.** Verified for the
  active 2026/27 row by dumping it, and read the seeder's historical years, which derive
  from the 2025/26 base and override only `bands[1]`/`bands[2]` and the additional-rate
  threshold. A year seeded with a different basic-rate width would need `bands[0]['max']`
  set; the fallback reconstructs it from `higher_rate_threshold − full allowance`.
- **That `floor(excess × 0.5)` and `floor(excess / 2)` are interchangeable.** They are for
  the configured 0.5. I moved to the configured rate to remove a hardcoded value.

## Surfaces covered / not covered

- **web** — covered, server-side. Not browser-verified.
- **`/m`** — covered by inheritance; **no counterpart to build.** Grepped
  `resources/mobile/` for `detailed_tax_breakdown`, `income_breakdowns` and `basic_rate`:
  no tax-band breakdown screen exists. `/m` reads `income_occupation` and picks up the
  corrected figures.
- **iOS** — same, `ios-native/Fynla/` has no band breakdown.

The item's acceptance 5 asks for browser verification on both persona accounts and on
`/m`. That is yours, and on `/m` it is a display check of the inherited totals rather than
of the bands themselves.
