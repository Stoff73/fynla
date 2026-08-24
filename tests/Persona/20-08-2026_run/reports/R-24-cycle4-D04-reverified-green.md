# R-24 — Cycle 4: D-04 re-verified GREEN on both accounts and both surfaces

**Agent:** `peak-earners-c4` (persona-tester) · **Persona:** `peak_earners`
**Written:** 2026-08-22 ~19:07 · Re-test of [R-19](R-19-cycle4-batch-1.md) D-04
**Surfaces:** web (1440×900) and `/m` (414×896), both local · **Accounts:** David (16), Sarah (17)

A fix landed under **W-0238** while batches 2–5 were running. This is its verification —
the Rule 14 loop closed, not a report of new work.

---

## What the fix was

`app/Services/Investment/PortfolioAnalyzer.php:15-24` — `calculateTotalValue()` has been
**deleted**, and its docblock records exactly the reasoning R-19 D-04 gave:

> *"It summed `current_value` across a collection at 100%, which is the right answer only
> when nothing in the collection is shared. Because it took a collection rather than a
> user, it could not know whose portfolio it was being asked about, and so could not apply
> an ownership share even in principle. Its single caller now reads
> `CrossModuleAssetAggregator::calculateInvestmentTotal($userId)` — the one home the wealth
> summary, net worth and /m already used."*

That is a Rule 20 consolidation rather than a patch — the mechanism count went down, and
the surviving home is the one three other surfaces already agreed on. `SavingsAgent` now
carries a "Reach, then fraction (F-0019)" comment, and `InvestmentAgent` applies a
`$fraction` to holdings.

## Verification — all four cells green

Expected values are the persona's, recomputed by hand in R-19.

| | David — was | David — now | Sarah — was | Sarah — now |
|---|---|---|---|---|
| **SAVINGS** | £102,000 | **£99,750** ✓ | £28,780 | **£31,030** ✓ |
| **INVESTMENT** | £220,000 | **£172,500** ✓ | £85,000 | **£132,500** ✓ |
| **RETIREMENT** | £500,000 ✓ | £500,000 ✓ | £0 "Plan your retirement" | **£35,000/year "Guaranteed retirement income"** ✓ |
| Holdings count | "1 holding" | "4 holdings" ✓ | — | "2 holdings" ✓ |
| Emergency runway | 41.6 months | **81.4** (= 99,750 ÷ 1,225) ✓ | — | **25.3** (= 31,030 ÷ 1,225) ✓ |

**Both failure directions are closed.** The *fraction* half (David charged 100% of the
joint General Investment Account and joint current account) and the *reach* half (Sarah
unable to see either at all) are both correct now, and they reconcile to the same
household totals the Wealth Summary has had right all along.

**The Defined Benefit card is fixed as a bonus.** Sarah's retirement card read
"£0 · Plan your retirement" while her own retirement page said £35,000/year. It now reads
**"£35,000/year · Guaranteed retirement income"** — the right figure with a label that
suits a pension with no capital value.

## Cross-surface parity confirmed

`/m` at 414×896, logged in as David, reads the same endpoint and shows the same numbers:
**NET WORTH £1,477,500 · PROTECTION £700,000 · BANK ACCOUNTS £99,750 · RETIREMENT
£500,000 · INVESTMENT £172,500 · 4 holdings · 81.4 / 6 months.**

Payload check on `/api/v1/mobile/dashboard`, the two halves that used to contradict each
other now agree:

```
modules.savings.total_savings         = 99750     net_worth…assets.savings     = 99750
modules.investment.portfolio_value    = 172500    net_worth…assets.investments = 172500
```

Screenshots: `142-web-david-dashboard-D04-fixed-99750-172500.png`,
`143-web-sarah-dashboard-D04-fixed-31030-132500-db-pension.png`

## A correction to R-19 I want on the record

R-19 D-04 said the contradiction "sits inside a single API response" and cited Sarah's
payload showing `modules.savings.total_savings = 0` beside
`net_worth…savings = 31030`. **The zeros in that specific payload were a stale cache**
(R-19 D-05), not the reach bug — the reach bug's true live values were £28,780 and
£85,000, which I established and reported separately in the same batch. The defect was
real and is now fixed; the illustration I chose for it conflated two faults that happened
to be sitting on top of each other. Recording it because a fixer reading only that
paragraph would have gone looking for the wrong thing.

## Still open from R-19 D-05 — the cache

The 24-hour cache that produced those zeros is a **separate item and I have not re-tested
it.** `MobileDashboardAggregator.php:37` still reads
`CACHE_TTL = 86400; // 24 hours — invalidated on data change`. Today's `cached_at` values
are fresh because I cleared the keys at 18:09 and the data has changed many times since.
Whether invalidation now actually fires on a data change is untested, and worth a
deliberate test rather than an inference.

## Not re-tested

The `/m` equivalents of D-11 through D-21 — the projection, the goals status, the wills and
the mortgage share. `/m` parity for those is still outstanding.
