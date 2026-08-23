# W-0274 — build-lead → quality-lead

## Done

- `resources/js/store/modules/savings.js`: `totalSavings`, `emergencyFundTotal` and
  `totalISABalance` routed to `calculateTotalUserShare` from
  `resources/js/utils/ownership.js`, which prefers the API's per-record
  `user_share`. The `is_emergency_fund` filter is gone from the figure.
- `emergencyFundRunway` prefers `analysis.emergency_fund.runway_months` and divides
  only when the payload carries no analysis block.
- `tests/frontend/store/savingsEmergencyFundGetters.test.js` — 10 tests, green,
  mutation-tested (restoring the originals turns 8 of 10 red).
- Frontend families green: 60 files, 702 tests.

## Not done, and why

- **No browser verification.** Shared Playwright tab, held by another agent.
- **Acceptance 4 NOT done.** `SavingsActionDefinitionService:436` and `:514` are
  outside this batch's declared file scope. The defect stands exactly as the item
  describes: `$savingsAnalysis['emergency_fund']['current_balance']` does not exist,
  so the `??` fallback runs always and an action sizes a top-up against £0.
- **`SavingsController::index` not touched** — outside declared scope, asked
  team-lead. Filed as W-0335.

## What you need that isn't obvious from the artefacts

- **Acceptance 2 is met by a different route than the item assumed.** The fund value
  does come from the endpoint — as `user_share`, computed per record by the same
  backend trait — so the browser transports a figure rather than re-deriving a rule.
  But there is **no endpoint figure for the runway to read**: `/api/savings` returns
  `'analysis' => null` as a literal placeholder, nothing dispatches `analyzeSavings`,
  and the store would commit an undefined key if it did. The division therefore still
  happens client-side when no analysis block arrives. It agrees today
  (Sarah 25.33, David 79.8) **only because `users.monthly_expenditure` happens to
  equal what the backend resolver picks** — and the persona proves the resolver
  branches: David resolves from `expenditure_profile`, Sarah from `user_monthly`.
  If you want acceptance 2 satisfied structurally rather than incidentally, W-0335
  is the item.
- **A third copy the item did not name:** `totalISABalance`. Fixed.
- If you re-check the numbers: Sarah £31,030 / 25.33 months, David £99,750 / 79.8
  months, both from `CrossModuleAssetAggregator::calculateCashTotal()`.

## Assumptions I made

- **Assumption:** post-W-0271 the emergency fund IS all cash, so `emergencyFundTotal`
  and `totalSavings` are currently the same figure. I wrote them as two getters
  returning the same thing rather than aliasing one to the other, because they answer
  different questions and W-0276 may narrow one of them. If you disagree, the place
  to narrow it is `calculateCashTotal()`, not the store.
- **Assumption:** `is_emergency_fund` remains meaningful as a DESIGNATION — the `/m`
  screen still badges accounts with it and I left that alone.
- **Assumption:** exposing `adequacy.adequacy_score` to the client would breach
  Rule 12, so the runway getter reads `runway_months` only and never the adequacy
  block.

## Surfaces covered / not covered

- **Covered:** web (this item) and `/m` (raised and fixed as W-0332 — the `/m`
  counterpart had the opposite fraction bug and no tester had raised it).
- **Not covered:** iOS. `ios-native/` was not examined for this figure; if it
  computes cash totals client-side it will carry one of the same two faults.
- **`/m` needs `public/m-build/` rebuilt before it can be verified** — team-lead's to
  run, per the batch instructions.
