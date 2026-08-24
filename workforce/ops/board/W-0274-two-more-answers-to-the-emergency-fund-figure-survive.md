---
id: W-0274
title: Two more answers to "how big is the emergency fund" survive — and one of them is on screen right now saying £0 and "0.0 months"
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: gated
severity: high
surfaces: [web, m]
created: 2026-08-22T22:10:00Z
claimed: 2026-08-22T23:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0271, F-0019, F-0022]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Found while browser-verifying **W-0271**, outside its scope and **not fixed** — scope
discipline. Raised at HIGH because one of the two is a live, user-visible
contradiction, not a latent one.

### 1. `/savings` → Emergency Fund tab still says £0 (browser-verified, both accounts)

Measured in the live browser at 22:49 on 2026-08-22, **after** W-0271 landed, on the
same login, minutes apart:

| Surface | Sarah Jones (17) |
|---|---|
| `/dashboard` | **25.3 / 6 months**, £31,030, "Emergency fund on track" |
| `/m` dashboard | **25.3 / 6 months**, £31,030, "Emergency fund on track" |
| `/risk-profile` | **25.3 months**, Upper-Med |
| **`/savings` → Emergency Fund tab** | **"Months Runway 0.0"**, **"Current Fund £0"**, *"Priority: Build your emergency fund to at least 3-6 months of expenses."*, "Top up needed: £7,350" |

Identical shape on David (16): 79.8 months everywhere, **0.0 and £0** on that tab.
Screenshot: `W-0274-web-sarah-17-savings-emergency-tab-still-zero.png`.

The source is a **client-side** implementation in
`resources/js/store/modules/savings.js:34-53`:

```js
emergencyFundTotal: (state) => state.accounts
    .filter(account => account.is_emergency_fund)
    .reduce(...)
```

with `emergencyFundRunway` dividing it by `monthlyExpenditure`, consumed by
`components/Savings/EmergencyFund.vue`. It is the definition W-0271 retired, reborn
in JavaScript.

**It carries a second, independent ownership bug.** Its joint-share arithmetic applies
`ownership_percentage` **regardless of which side the viewer is on**:

```js
if (isJoint && account.ownership_percentage) return sum + (balance * (account.ownership_percentage / 100));
```

So the co-owner is charged the **primary owner's** share — F-0019's fraction failure,
in the store getter, with `ownership.js` sitting unused beside it. `totalSavings` at
`:12-21` has exactly the same defect.

### 2. `SavingsActionDefinitionService:436` and `:514`

```php
$currentBalance = $savingsAnalysis['emergency_fund']['current_balance']
    ?? $savingsAccounts->where('is_emergency_fund', true)->sum('current_balance');
```

Two problems in one line. The fallback is the retired definition at 100% of a joint
balance — and **`$savingsAnalysis['emergency_fund']['current_balance']` does not
exist.** `SavingsAgent` returns `runway_months`, `adequacy`, `category`,
`recommendation` and `target` under that key. So the `??` never sees a value, the
fallback runs **always**, and an action sizes a top-up against £0. That is the W-0241
`transfer_value` shape: a missing key swallowed silently, wrong forever, with no error.

`:622`, `:634`, `:636`, `:1862`, `:3641` also branch on the flag — but as a
**designation** ("has the user nominated an account"), which is a real question the
flag still answers after W-0271. Only `:436` and `:514` use it as a **figure**.

### Implementations of "months of runway", after W-0271

| # | Where | Definition |
|---|---|---|
| 1 | `SavingsAgent` + `EmergencyFundCalculator` | all cash ÷ resolved expenditure — **the home** |
| 2 | `AutoRiskCalculator` | routed to #1's inputs (W-0271) |
| 3 | `MobileDashboardAggregator` → `emergency_fund_months` | reads #1 |
| 4 | **`resources/js/store/modules/savings.js`** | **flagged accounts only — CONTRADICTS** |
| 5 | `resources/mobile/views/modules/Savings.vue:196` | recomputes client-side; right answer, second implementation |

## Acceptance

1. `/savings` → Emergency Fund agrees with `/dashboard`, `/m` and `/risk-profile` on
   both accounts.
2. The amount comes from the endpoint, not from a client-side re-derivation — Rule 20
   means the browser reads the figure, it does not compute its own.
3. The store getters' ownership arithmetic routes through `ownership.js`, or is
   deleted along with the getters.
4. `SavingsActionDefinitionService`'s dead `??` chain is fed a key that exists or
   removed — not left reading as a fallback nobody reaches.

## Working notes — build-lead (`fix-cycle4-doublecount`), 2026-08-22

**Part 1 DONE.** All three getters routed to `resources/js/utils/ownership.js`, which
sums the API's own per-record `user_share` — the browser transports a total rather
than re-deriving a rule. `emergencyFundTotal` no longer filters on
`is_emergency_fund`; `emergencyFundRunway` prefers the backend's `runway_months` and
divides only when the payload carries no analysis block.

**A third copy the item did not name:** `totalISABalance` at `:86-97` carried the
identical wrong-side arithmetic. Fixed with the other two — a rule with three
implementations has three chances to be edited into disagreement.

**A `/m` counterpart nobody had raised — filed as W-0332 and fixed here.** The `/m`
bank-accounts screen had the OPPOSITE fraction bug: `balanceOf()` preferred
`full_balance`, so a joint account was counted WHOLE against both spouses, and the
runway, the bar and "% of target" inherited it — while `/m`'s own account detail
screen one tap away has always shown the share.

**Acceptance 2 is met but not by the route the item specified.** The fund value comes
from the endpoint — per record, as `user_share` — but there is no endpoint figure for
the RUNWAY to read: `SavingsController::index` returns `'analysis' => null` as a
literal placeholder, nothing dispatches `analyzeSavings`, and the store then commits
a key that does not exist. Filed as **W-0335**; asked team-lead before touching a
controller outside the batch's declared scope.

**Acceptance 4 (`SavingsActionDefinitionService`'s dead `??` chain) NOT done** — that
file is outside this batch's declared scope. It is still true and still wrong: the
`??` fallback runs always, sizing a top-up against £0.

Tests: `tests/frontend/store/savingsEmergencyFundGetters.test.js` (10),
`tests/frontend/mobile/SavingsOwnershipShare.test.js` (4). 75/25 and 70/30 fixtures,
never 50/50. Mutation-tested: restoring the original getters turns 8/10 and 4/4 red.

Browser verification of both accounts on web and `/m` is **DONE** — see the section
below, added after the tab was released to me and `public/m-build/` rebuilt.

## Browser verification — build-lead, 2026-08-23 00:45

**Both accounts, web and `/m`, MFA codes fetched from the database throughout.**
Identity confirmed from `fynla-state.auth.user` (id 16 / id 17) rather than by
recognising a figure — the figures are the things under test.

| Surface | David (16) | Sarah (17) |
|---|---|---|
| `/dashboard` | 79.8 / 6 months · £99,750 | 25.3 / 6 months · £31,030 |
| **`/savings` → Emergency Fund** | **79.8 · £99,750** · *"Emergency fund target achieved!"* | **25.3 · £31,030** · *"Emergency fund target achieved!"* |
| `/risk-profile` | 79.8 months · Upper-Med | 25.3 months · Upper-Med |
| **`/m` bank accounts** | **£99,750**, 80 months of cover | **£31,030**, 25 months of cover |

Was `Months Runway 0.0`, `Current Fund £0`, *"Priority: Build your emergency fund"*
on both accounts. **`/m` reads £31,030 for Sarah, not £33,280**, and the joint
Nationwide account renders **£2,250** with **"Your 50.00% of £4,500"** beneath it on
both logins.

**Bundle proof, because a stale `/m` build fails by AGREEING.** `full_balance` is the
wrong discriminator — it legitimately survives, since `ownership.js`'s `VALUE_FIELDS`
lists it and `balanceOf()` still uses it for the context line. Grepped instead for
`ms-acct__share` (**present**; did not exist before) and the old
`reduce(...balanceOf` summing expression (**absent**). The page then confirmed it was
serving `main-BljqEql8.js`, the same file grepped.

**What this pass CANNOT prove.** The persona's joint account is **50/50**, so David's
half and Sarah's half are the same £2,250 — the browser cannot distinguish "shows the
viewer's share" from "shows the primary owner's share". It proves only that neither
spouse is charged the whole £4,500, which is the defect that was live. The asymmetric
discrimination lives in the tests, at 70/30. **The Collision, on the very screen under
test.**

Screenshots: `W-0274-web-david-16-savings-emergency-fixed.png`,
`W-0274-web-sarah-17-savings-emergency-fixed.png`,
`W-0332-m-sarah-17-savings-own-share.png`, `W-0332-m-david-16-savings-own-share.png`.

No persona row was written.
