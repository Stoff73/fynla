---
id: W-0276
title: Emergency runway counts cash the user cannot actually reach, and LiquidityAnalyzer already knows which is which
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: product-lead
status: queued
severity: low
surfaces: [web, m, ios]
created: 2026-08-22T22:10:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0271]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Recorded as the **known cost** of W-0271 rather than left implicit.

Both the savings module and (now) the risk engine compute runway from **all** of a
user's cash. A five-year fixed-rate bond counts exactly like a current account, so a
household can be told it has nine months of runway when most of it cannot be reached
without breaking a term.

This is **not a regression** — the savings module has always counted it this way, and
W-0271 made the risk page agree with it rather than inventing a third rule. But
`app/Services/Savings/LiquidityAnalyzer.php` already categorises accounts by access
and builds a liquidity ladder, so the information to do better is present and unused
for this figure.

**It is a product call, not a bug fix**, because narrowing "available" would move the
dashboard's headline months for every existing user.

## Acceptance

A decision, recorded: either runway uses accessible cash on **every** surface at once,
or the current definition is deliberate and the copy says "cash savings" rather than
implying immediacy.

## Analysis 2026-08-25 — the finding holds; here is what the change would cost

### The mechanism, confirmed

`SavingsAgent:101` calls `CrossModuleAssetAggregator::calculateCashTotal()`, which
sums **every** savings account for the user (ownership-adjusted via
`CalculatesOwnershipShare`) with no reference to `access_type`. That total goes
straight into `EmergencyFundCalculator::calculateRunway($totalSavings,
$monthlyExpenditure)` — a plain division, no liquidity notion anywhere.

`LiquidityAnalyzer::categorizeLiquidity()` already splits the same accounts into
`immediate` / `notice` / `fixed`. The item is right that the information exists and
is unused for this figure.

### What the change would actually cost, measured

Computed through the same store and the same ownership rule the aggregator uses,
so the two columns differ only by `access_type`:

| Persona | All cash | Accessible | Locked | Monthly | Runway now | Accessible-only |
|---|---|---|---|---|---|---|
| young_family | £11,700 | £11,700 | £0 | £2,400 | 4.88 | 4.88 |
| peak_earners | £74,750 | £74,750 | £0 | £1,250 | 59.8 | 59.8 |
| **entrepreneur** | £169,180 | £129,180 | **£40,000** | £5,500 | **30.76** | **23.49** |
| young_saver | £10,700 | £10,700 | £0 | £1,833 | 5.84 | 5.84 |
| retired_couple | £74,250 | £74,250 | £0 | £0 | 0 | 0 |
| student | £1,200 | £1,200 | £0 | £750 | 1.6 | 1.6 |

**One persona in six moves**, by 7.3 months (−24%). The rest are unchanged because
they hold only immediate-access cash.

### The motivating example does not exist in the data

`savings_accounts` holds **24 `immediate` and 1 `notice`. Zero `fixed`.** The
five-year bond the item describes is not represented in any seeded persona, so the
worst case cannot currently be demonstrated on any surface. Anyone verifying a
change here has to create the account first.

### Current copy, per surface

| Surface | Wording |
|---|---|
| `/m` | "Cash held" for the amount; runway as "N months of cover" |
| Web | "Emergency Fund Status", gauge in months, "…months of expenses" |
| iOS | "Your cash, emergency-fund runway and ISA allowance" |

`/m` already says **"Cash held"**, which is neutral about access. The phrase that
implies immediacy is **"months of cover"**, shared by `/m` and the web gauge.

### Two things found alongside, both worse than this item

Neither is W-0276 and neither is fixed here, but both distort the same headline:

1. **`retired_couple` shows 0 months of runway while holding £74,250.** Monthly
   expenditure resolves to £0, and `calculateRunway` returns `0.0` on a
   non-positive divisor. A household with £74k cash is told it has no runway at
   all. That is a false statement about someone's finances, not a definitional
   nicety.
2. **`peak_earners` shows 59.8 months** — £74,750 against £1,250/month for a
   household the persona describes as earning ~£220k. The expenditure resolution
   looks understated, which inflates the headline far more than counting a notice
   account ever could.

Both deserve their own items.
