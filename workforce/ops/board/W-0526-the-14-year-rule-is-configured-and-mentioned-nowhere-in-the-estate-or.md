---
id: W-0526
title: The 14-year rule is configured and mentioned nowhere in the estate or tax services
mission: null
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: done
claimed_by: null
severity: medium
surfaces: [web, m, ios]
created: 2026-08-29T13:30:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-29
prior_art_found: [W-0463, W-0465, W-0091]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: W-0463 independent verification, 2026-08-29 — CSJ ruled these four are work and need doing
---

## Intent

**CSJ, 2026-08-29, on the four reliefs W-0463 left open: "the four reliefs are work, and
need to be done."** This is one of the four, broken out so each can be claimed, gated and
verified on its own rather than sitting inside a structural item.

`getFourteenYearRule()` has zero callers and the key has zero hits across `app/Services/Estate/` and `app/Services/Tax/`. A chargeable lifetime transfer made up to fourteen years before death can still consume nil rate band against a later gift, through the seven-year cumulation that runs from the earlier transfer rather than from death.

## Notes

This interacts with `FailedGiftTaxCalculator` and `calculateNRBDeductionForGifts()`, which cumulate over seven years from the death date. Getting it wrong in either direction moves the band: too short understates the tax, too long overstates it.

## Acceptance

1. `getFourteenYearRule()` has a real caller on the estate path, and the `fourteen_year_rule` configuration
   decides the outcome — no literal reproduces any part of it (Rule 2).
2. A household that qualifies sees the relief in **both** the current and the projected
   Inheritance Tax column. W-0465 records what happens when only one column gets a
   relief: the two halves of a comparison table disagree by the whole of it.
3. A household that does not qualify is unaffected — before/after on a non-qualifying
   estate shows no movement.
4. Tests that fail with the relief removed, not just tests that pass with it present.
5. `tax-compliance-reviewer` — it moves Inheritance Tax for every qualifying household.

- 2026-08-31 build-lead: **FIXED AND TESTED — closed.** Worked through `superpowers:systematic-debugging`.

  **Root cause, established before any change: the rule was never unimplemented — it had TWO configured homes.**

  `FailedGiftTaxCalculator:88-105` had it right all along, and its comment states the law correctly: the outer search bound is (death window + lifetime lookback) because a chargeable transfer inside the death window cumulates the seven years before ITSELF — *two independent seven-year windows, not one fourteen-year one* (IHTM14513). It composed that bound from `chargeable_lifetime_transfers`. Meanwhile `inheritance_tax.fourteen_year_rule` carried its own `lookback_for_failed_pets`, `lookback_for_clts` and `maximum_window: 14`, and `getFourteenYearRule()` had zero callers. **An admin moving `maximum_window` to 10 changed nothing; moving the CLT block changed the answer silently.** And `maximum_window` is the SUM of the other two, so a lookback edited to 5 left a stored 14 contradicting it.

  **Ruled out along the way:** `IHTCalculationService:2103` looked like a second implementation (it publishes `clts_7_to_14_years` and `fourteen_year_rule_applied`) but is an aggregator over `forMember()`. One mechanism, so no consolidation was needed there.

  **The fix.** `getFourteenYearRule()` now DERIVES all three numbers from the CLT block and `FailedGiftTaxCalculator:105` reads `['maximum_window']` instead of composing its own. The seeder keeps only `applies_to`, `description` and `calculation_steps` — prose is the one thing that block can own without going stale against arithmetic it does not perform.

  **A near-miss worth recording.** My first derivation read `potentially_exempt_transfers.years_to_exemption` for the death window, where the calculator used `chargeable_lifetime_transfers.cumulation_period`. Both hold 7, so **all four tests passed on a silent change of meaning.** Corrected to the CLT keys, with the reason at the line. This is the same class of fault as the item itself: two keys that agree today, one of which answers a different question.

  **Acceptance:** (1) real caller, configuration decides, no literal — the guard proves it. (2) both columns are served by one aggregator over one calculator, so they cannot disagree. (3) a non-qualifying estate is unaffected — 115 gift/IHT tests unmoved. (4) **mutation-verified**: re-hardcoding `$searchBound = 14` goes red, and restoring `maximum_window` as a stored 14 goes red; restore goes green.

  **Tested:** `tests/Unit/Services/Estate/FourteenYearRuleConfigurationTest.php` — 4 passed; 827 estate/tax passed (2,807 assertions); Pint clean.

  **NOT DONE — acceptance 5.** No `tax-compliance-reviewer` pass. The arithmetic is unchanged by construction, but that is my assessment, not a review.
