---
type: architecture-audit
date: 2026-08-17
principle: "All surfaces share the same engines, database and math. /m and native are VIEWS ONLY — they render what the backend gives them and generate nothing." (CSJ, 2026-08-17)
verdict: Broadly true. Three specific leak classes found, plus one canonical engine no client can reach.
---

# Are `/m` and native really view-only?

Audit against CSJ's stated principle. Checked `resources/mobile/` (14 files with
arithmetic) and `ios-native/Fynla/` for anything that *derives* rather than
*renders*.

## Verdict

**The principle holds where it matters most.** There is no duplicated tax engine,
no hardcoded tax value, and no second source of truth for tax figures on either
view surface:

- **Zero hardcoded tax constants in `/m` production code.** The only matches for
  `12570`, `20000`, `60000`, `325000`, `0.04` etc. are **test fixtures** and one
  field-*name* list (`ModuleDetail.vue:110`). Nothing computes tax client-side.
- Tax figures are rendered straight from the server: `TaxStrategy.vue:31,54,187`
  only `Math.round()` a server-supplied `rec.estimated_annual_tax_saved`.
- The substantive emergency-fund figures come from the server payload
  (`emergency_fund_target` → `target_amount`, `target_months`, `rationale`).
- **Goals is the correct pattern on both surfaces** — server value first, e.g.
  `Goals.vue:88` `Number(this.overview?.total_target ?? ...)` and
  `GoalsView.swift:262` `?? snapshot.goals.reduce(...)`. The architecture knows
  the right shape.

**But it is not fully true today.** Three leak classes below.

## Leak 1 — hardcoded client-side fallbacks that diverge from the canonical engine

`resources/mobile/views/modules/Savings.vue:188`

```js
return Number(this.emergencyTargetData?.target_months || 6);
```

The client assumes **6 months** if the server omits `target_months`. The
canonical server engine does **not** use a flat 6 —
`app/Services/Savings/EmergencyFundCalculator.php:89-92` derives it from
employment status:

```php
'employed', 'part_time' => 6,
'self_employed', 'freelance' => 9,
'contractor' => 9,
'retired' => 3,
```

So for a self-employed or retired user, a missing field makes `/m` silently show
a target the engine would never produce. This is the dangerous shape: not an
error, just a quietly wrong number.

## Leak 2 — both view surfaces independently aggregate totals

Same calculation, implemented twice, with no server field as the source of truth:

| Figure | `/m` | native |
|---|---|---|
| Total cash | `Savings.vue:176` reduce | `SavingsModels.swift:22` `totalCash` reduce |
| Total investment value | `Investment.vue:104` reduce | `InvestmentModels.swift:16` `totalValue` reduce |
| Total DC pension | `Retirement.vue:233` reduce | `RetirementModels.swift:43` reduce |
| Net-worth category share | `NetWorth.vue:115` `(value / totalAssets) * 100` | — |

Each is a sum over an account list the server already sent. Individually trivial;
collectively it means "total cash" has three definitions (server, `/m`, native)
and nothing forces them to agree. `SavingsController.php:520` does emit a
`total_savings`, so at least one server total exists and is not being consumed.

## Leak 3 — native performs genuine money math on device

Beyond aggregation, native derives new financial values:

| Location | Calculation | Concern |
|---|---|---|
| `RetirementModels.swift:134` | `totalPercent * annualSalary / 100 / 12` | monthly pension contribution from a salary percentage — real domain math |
| `SavingsAccountView.swift:137` | `annualInterest(account) / 12` | monthly interest |
| `ProtectionModels.swift:255` | `premiumAmount * 12` | annualising a premium |
| `SavingsAccountView.swift:278` | `months / 12` | term conversion |

`/m` has no equivalent for the first three, so native and `/m` can present
different figures for the same account. Rule 19 parity is stated in terms of
screens, but this is a *numbers* divergence.

## Leak 4 — a canonical engine no client can reach

`EmergencyFundCalculator` returns `runway`, `target`, `adequacy_score`,
`shortfall`, with the employment-aware target above.

```
grep -rln "EmergencyFundCalculator" app/Http/   →   no results
```

**It is not referenced by any controller, resource, or route.** The canonical
engine is unreachable from every surface, which is *why* the clients compute
their own version. Fixing Leak 1 properly means exposing this, not patching the
fallback.

## Leak 5 — client-side eligibility decisions (this is BUG-01)

`resources/mobile/views/Subscription.vue:104-108`

```js
canUpgrade()        { return this.status?.tier === 'free' && this.status?.payment_enabled === true; },
paymentUnavailable(){ return this.status?.tier === 'free' && this.status?.payment_enabled !== true; },
```

The client decides whether the user may upgrade. And the endpoint it reads,
`GET /api/payment/subscription-status` (`PaymentController::subscriptionStatus`),
**does not emit `payment_enabled` at all** — verified with `array_key_exists`, it
is absent from the payload, not null.

Meanwhile `SubscriptionStatusService.php:86` *does* emit it
(`'payment_enabled' => $paymentEnabled && ! $user->is_preview_user`), and
`config('app.payment_enabled')` is **`true`** locally.

So: payments are enabled, the server knows it, a service exists that says so, and
`/m` shows **"Upgrades are temporarily unavailable"** permanently because it asks
an endpoint that omits the field. Two producers of one payload, one missing a
field its consumer depends on — the Rule 20 disease.

This is a **confirmed second root cause for BUG-01**, distinct from the web
failure, and it follows directly from the client deciding something the server
should have decided.

## Where the leaks are NOT

Worth stating so this isn't read as "everything is duplicated":

- No duplicated tax engine, no client tax constants, no client tax logic.
- Fyn/AI is genuinely one backend path — both surfaces post to the same
  `POST /api/ai-chat/conversations/{id}/messages`.
- Recommendations, tax-strategy savings and plan composition are rendered as
  received.
- Goals demonstrates the correct server-first pattern on both surfaces.

## What follows

Not started — this is the audit only.

1. Expose `EmergencyFundCalculator` and have both clients read `runway`,
   `target_months`, `adequacy` from it. Delete the `|| 6` fallback (Leaks 1, 4).
2. Add `payment_enabled` to `PaymentController::subscriptionStatus`, or route
   `/m` at the service that already emits it — one producer, not two. Then make
   upgrade eligibility a server-stated fact, not a client expression (Leak 5).
3. Emit module totals server-side and have both clients render them, following
   the Goals `??` pattern — or better, without the fallback (Leak 2).
4. Move native's four money calculations server-side, or accept them explicitly
   as presentation-only conversions and pin them with tests (Leak 3).
5. An architecture test asserting no client file computes a financial figure the
   server already provides would stop this regrowing — the same shape as
   `ClientParityLedgerTest`.
