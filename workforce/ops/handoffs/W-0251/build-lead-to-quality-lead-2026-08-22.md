# W-0251, W-0252, W-0253/W-0217, W-0254, W-0255, W-0256 — build-lead → quality-lead

One batch, one branch document: `workforce/branches/fixes/F-0024-cycle4-investment-projection.md`.
Read §6 (measurements), §7 (the acceptance criterion that cannot be met) and §10 (what you
cannot see from the artefacts) before running anything.

## Done

- **W-0251** the cache key now names the simulation, not just the asker —
  `MonteCarloSimulator::fingerprintKey()`. Every one of the tester's six D-20 figures was
  reproduced from cached rows before the fix, so the diagnosis is demonstrated rather than
  argued.
- **W-0252** closed by the same mechanism; verified through the real edit form on Sarah.
- **W-0217** closed by W-0251 + W-0254; the item carries a full resolution section.
- **W-0254** contributions are read from what the user recorded, never invented.
- **W-0255** every band is a measured percentile; no interpolation, extrapolation or
  smoothing survives, in either module or on the frontend.
- **W-0256** the projection now reaches jointly owned accounts at the user's own share.

Five duplicate mechanisms removed; no new file. Suites run with
`DB_DATABASE=laravel_testing_c`: **1,533 passed, 0 failed**, plus the frontend store test.

## Not done, and why

- **W-0257, W-0258, W-0259 are raised and not built.** W-0257 is a distinct form defect
  found while verifying. W-0258 and W-0259 need a CSJ decision about what the card leads
  with — I have deliberately not chosen for him.
- **D-21 was not verified on David.** Account 26's holdings were pushed to 105% by another
  agent at 21:28, which silently disables the edit form (that is W-0257). Verified on Sarah
  instead. I did not delete another agent's holding row; account 26 is still at 105%.
- **No commit, no PR, no deploy**, by instruction.

## What you need that is not obvious from the artefacts

1. **`projected_investments` in the estate calculation has moved, and that is this fix
   arriving, not a regression.** F-0018 §0b pinned £2,603,695 and reconciled the whole
   projected estate to it. That figure was £1,577,731 of Sarah's inflated projection plus
   £1,025,964 of David's stale one. **Re-derive before comparing to F-0018.**
2. **Figures are now reproducible.** The simulation is seeded from its inputs, so a re-run
   gives the same answer — you can pin numbers in an evidence pack without a cache. If a
   figure moves between two runs with unchanged data, that is a real finding.
3. **Old cache rows are unreachable, not deleted.** Pre-fix keys lack the `_s…` suffix.
   Both personas' rows were cleared during verification, so measurements were cold.
4. **A `mt_srand()` with no argument follows each simulation loop** and is load-bearing —
   it hands the global generator back unpredictable. It looks like dead code and is not.
5. **`/m` has no investment projection surface at all.** Traced per screen in F-0024 §9.
   That is a stated finding, not a skipped check. `public/m-build/` is untouched — I have
   built nothing, per instruction.
6. **`MonteCarloEngine::simulate()` gained two parameters in the working tree that are not
   mine**, from another agent or a hook. Compatible; left as found.

## Assumptions I made

- **That the recorded contribution beats the rule of thumb.** F-0018 settled this in the
  same run, and the card already printed the recorded figure — so I treated adopting it as
  consolidation rather than a modelling decision. If CSJ intended "assume the user maxes
  their ISA" as product behaviour, this is the change to revisit, and it is the largest
  mover in the batch.
- **That "80% Probability" means 80% of outcomes at or above.** Same reading as the tester.
  Under this reading the label is now true; it previously was not.
- **That the 5-year figure being far below the starting capital is correct** — £80,000 of
  life-event withdrawals land at years 2 and 4 and are drawn on the chart. I did not treat
  the drop as a defect once the £47,500 cache was ruled out.
- **That the brief's "region of £105,000" target was indicative.** It came from the tester's
  interpolated p20 at an assumed 12% volatility; the true 20th percentile at the actual
  blended 16.88% is **£86,944**. I did not tune anything toward £105,000.

## Surfaces covered / not covered

| Surface | Status |
|---|---|
| Desktop web | **Covered and browser-verified** — both logins through MFA, all four horizons, risk change end to end |
| `/m` | **No such surface exists** — traced per screen, F-0024 §9. Nothing to verify or build |
| iOS | **No such surface exists** — no Swift file decodes an investment projection |
