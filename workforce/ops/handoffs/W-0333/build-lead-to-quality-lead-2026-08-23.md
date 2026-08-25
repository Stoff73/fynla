# W-0333 / W-0336 / W-0339 — build-lead → quality-lead

Three items, one edit set in `IHTCalculationService`, handed off together because
verifying one without the others gives a misleading answer.

## Done

- `projectProperties()` → `CrossModuleAssetAggregator::calculatePropertyTotal` per
  member. Projected properties £4,550,296.97 → £4,037,301.71; **projected
  Inheritance Tax liability £2,851,349.69 → £2,646,151.58 on both accounts.**
- `projectLiabilities()` → one `projectMemberLiabilities()`, joint-aware reach at the
  member's share, mortgages through the **two-leg** `getMortgages()`.
- Both `$mortgage->end_date` reads → `maturity_date`.
- Tax-compliance review completed and passed before landing (F-0026 §7).
- `tests/Unit/Services/Estate/IHTProjectionOwnershipTest.php` — 10 tests, and one
  rewritten case in `tests/Feature/Stores/PropertyReadConsumerParityTest.php`.
- 552 backend / 703 frontend green.

## Not done, and why

- **No browser verification.** The shared Playwright tab was held throughout.
- **W-0338, W-0340 and the 16-finding tax ledger are NOT fixed.** They are filed.
  W-0340 in particular means this batch closed *whose share* and not *which people*.
- `projectSingleLiability`'s retirement-age default for a mortgage with genuinely no
  maturity date is untouched — W-0339 only stops it firing for mortgages that have one.

## What you need that isn't obvious from the artefacts

- **Do not verify this against the persona alone.** Four of the five fixes move
  nothing on David and Sarah: the investment fix (with sharing on, old and new both
  give £305,000), the liability fix (£0 either way), the phantom column (same
  reason), and the two-leg mortgage reach (all three rows name both spouses). **Only
  the property fix is observable on this household.** The other four live entirely in
  fixtures, and the fixtures are the evidence.
- **The £205,198.11 vs the £205,198.10 I predicted is not a discrepancy.** The
  compliance review re-derived both sides: `0.4 × 512,995.26405647` rounds to `…11`,
  `0.4 × 512,995.26` rounds to `…10`. My prediction pre-rounded; the measurement is
  the accurate one. Every other component reconstructs bit-identical.
- **`5278a2457` is completed, not reversed.** If you read that commit and conclude
  this undid a deliberate fix, read F-0026 §4.1 — it stopped a joint property being
  counted twice by taking primary rows at 100%, which is how the third party got in.
  The aggregator prevents both, and that commit named the aggregator itself as the
  correct consumer.
- **One test in this area used to be a decoy.** `PropertyReadConsumerParityTest`
  carried a case named after `IHTCalculationService` that never called it — it
  reproduced the query inline. Rewritten to drive the service; verified red under the
  defect it is named for.
- Tests on `DB_DATABASE=laravel_testing_e`, not `_a`.
- One run in the middle showed a single failure at `sumMainResidenceNetShare` which
  did **not** reproduce across two clean runs of the same command. Discarded per
  `tests/CLAUDE.md` §5. Recorded because discarding silently is how a real flake hides.

## Assumptions I made

- **Assumption:** the projection should count the household the way the headline
  does. The headline was treated as the settled answer because it is the figure the
  user is quoted today and it is share-correct. If the intended answer is that the
  projection should be per-person, both halves are wrong, not one.
- **Assumption:** a third party's share is credited to nobody rather than falling
  through to the spouse. Confirmed against IHTA 1984 s5(1) by the review, but nobody
  had restated it for the estate path before.
- **Assumption:** the growth assumption being read from the signed-in user and
  applied to both members is intentional household behaviour. Preserved exactly;
  the review flagged the age-frame equivalent for spouse debts (L3), which I did not
  change either.

## Surfaces covered / not covered

- **Covered:** web, `/m` and iOS — a backend service behind one endpoint.
- **Not covered:** nothing surface-specific; no bundle rebuild needed for these three.
