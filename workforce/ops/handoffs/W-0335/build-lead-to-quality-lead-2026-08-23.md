# W-0335 — build-lead → quality-lead

## Done

- `SavingsController::index()` returns a real `analysis` block: `runway_months`,
  `total_savings`, `monthly_expenditure` and `expenditure_source`.
- The store's `analyzeSavings` action commits `responseData` rather than
  `responseData.analysis`, which was `undefined` on every call.
- `tests/Feature/Savings/SavingsEmergencyFundPayloadTest.php` — 3 tests, mutation-verified.

## Not done, and why

- **No browser verification** — shared tab.
- **`SavingsActionDefinitionService:436` and `:514` still stand** (W-0274 acceptance
  4). Outside this batch's declared files. The `??` fallback there still runs always
  and sizes a top-up against £0.

## What you need that isn't obvious from the artefacts

- **The narrowness is the design, not laziness.** `emergency_fund.adequacy` carries an
  `adequacy_score`. Shipping the whole block would have put a numerical rating on the
  wire (Rule 12). The guard asserts against the **whole response body**, not one key,
  because that is how the score would actually arrive — someone widening the block,
  not someone adding that key by name. Mutation-verified.
- **Why the runway had to be read rather than divided:** the denominator is a resolver
  chain, not a column. This one household takes **both** branches — David resolves
  from `expenditure_profile`, Sarah from `user_monthly`. They agree today only because
  `users.monthly_expenditure` happens to equal both.
- The store keeps a fallback division for payloads with no analysis block, so a
  readiness-gated user still sees a coherent fund value and runway rather than zero.
- **A fixture trap worth knowing about if you extend these tests:**
  `SavingsDataReadinessService` blocks the analysis without a date of birth, income
  AND expenditure. My first version returned a null analysis and looked like a defect;
  it was the gate working on an under-built fixture.

## Assumptions I made

- **Assumption:** `expenditure_source` is safe to expose. It names which branch of the
  resolver produced the denominator, which makes the figure checkable by a user. It is
  not a score and not a rating.
- **Assumption:** `emergency_fund.target` is already user-facing — the `/m` screen
  reads `emergency_fund_target` from the same payload — so including it changes no
  exposure.

## Surfaces covered / not covered

- **Covered:** web. The endpoint is shared, so `/m` and native receive the block too;
  neither reads it yet.
- **Not covered:** no `/m` or native consumer was pointed at the new figures.
