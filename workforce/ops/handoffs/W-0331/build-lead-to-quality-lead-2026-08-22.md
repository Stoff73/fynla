# W-0331 — build-lead → quality-lead

## Done

- Five `InvestmentAccount::where('user_id', …)->sum('current_value')` call sites in
  `app/Services/Estate/IHTCalculationService.php` routed to
  `CrossModuleAssetAggregator::calculateInvestmentTotal()` via one private
  `memberInvestmentValue()`. The model import went with them.
- The four try/catch fallback copies in `projectInvestmentsMonteCarlo()` collapsed
  into one `projectMemberInvestments()`.
- Class docblock now states which records the calculation covers and at what
  fraction, beside the two shapes that look equivalent and are not.
- `tests/Unit/Services/Estate/IHTProjectionOwnershipTest.php` — 4 tests, green,
  mutation-tested in both directions.
- Families green: `tests/Unit/Services/Estate/` 293 · `tests/Feature/Stores/` +
  `tests/Unit/Services/Shared/` 218.

## Not done, and why

- **No browser verification.** The shared Playwright tab was held by another agent
  for the whole batch and team-lead asked me not to touch it. This is the one piece
  of acceptance without evidence.
- **`projectProperties` NOT changed** — that is W-0333, a £205,198.10 fall in a
  projected tax figure. Flagged to team-lead, awaiting a decision and the
  tax-compliance review they asked for. **Do not treat W-0331 as covering it.**
- **`projectLiabilities` NOT changed** — W-0336, same family, £0 on this persona.
- The dead `projectInvestments()` / `getCurrentInvestmentValue()` pair was fixed in
  place, not deleted — W-0334.

## What you need that isn't obvious from the artefacts

- **The item's own premise is wrong and the board says so.** W-0280 §1 and F-0024
  §10 describe a double count that cannot occur — two `where('user_id', …)` queries
  are disjoint. If you verify by looking for a doubled figure you will find nothing
  and conclude the fix is a no-op. It is not; it closes three other cases. Read
  `F-0026` §2 first. A correction note is on W-0280 itself.
- **The persona cannot demonstrate this fix.** With data sharing on, the old and new
  readers give the same £305,000. Before/after on David and Sarah is deliberately
  identical: £343,512 current, £2,851,349.69 projected. **That is the expected
  result, not a failed fix.** The divergent cases are sharing-off, a third-party
  share, and a mixed simulation/fallback run — all in the test file, none in the
  household.
- Tests run on `DB_DATABASE=laravel_testing_e`, **not `_a`** as issued — `_a` was
  held by another batch and produced `Unknown table` with 0 assertions.
- `tests/Unit/Services/Estate/` already defines a global `projectionHousehold()` in
  `IHTProjectedAssessmentTest.php`; mine is `ownershipProjectionHousehold()`. Running
  a single file will not catch that class of collision — run the directory.

## Assumptions I made

- **Assumption:** the Inheritance Tax projection wants the HOUSEHOLD view, not the
  per-person view, because `calculate()` already pools both estates on
  `$isMarried && $dataSharingEnabled` and models the second death. I did not change
  that; I made the projection assemble the household the way the headline does.
- **Assumption:** a share belonging to someone with no account here should be
  credited to nobody rather than falling through to the spouse. This follows
  `CalculatesOwnershipShare` and F-0019 §3, but nobody restated it for the estate
  path, so I am stating it as an assumption.
- **Assumption:** `getFallbackGrowthRate($user)` being read from the signed-in user
  and applied to both members is intentional household behaviour. I preserved it
  exactly rather than "fixing" it while passing.

## Surfaces covered / not covered

- **Covered:** web, `/m` and iOS — this is a backend service behind one endpoint, so
  all three read the fix without a client change.
- **Not covered:** nothing surface-specific. No bundle rebuild needed for this item.
