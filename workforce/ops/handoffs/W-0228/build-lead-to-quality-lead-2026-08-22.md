# W-0228 / W-0236 / W-0237 — build-lead → quality-lead

Branch document: `workforce/branches/fixes/F-0022-cycle4-dashboard-module-totals-and-cache.md` §10–19.
One note covers all three: they are one fix with three symptoms.

## Done

**A debt is shared exactly as the asset securing it is shared** (CSJ ruling,
W-0228). The property is authoritative; `calculateUserMortgageAmountShare` resolves
it through `SecuringPropertyResolver` → `PropertyStore::findOwnershipBasis()`.

| Figure | Before | After |
|---|---|---|
| David's share of mortgage 16 | £60,000 | **£48,000** |
| David's mortgages | £182,500 | **£170,500** |
| Sarah's mortgages | £122,500 | £122,500 |
| Household debt | £305,000 | **£293,000** |
| David's net worth | £1,477,500 | **£1,489,500** |

**Five mechanisms consolidated to one** — four backend
(`calculateUserMortgageAmountShare`, `EstateController`,
`PropertyService::calculateTaxPosition`, `PropertyService::calculateUserEquity`)
and one client-side (`LiabilityCard.vue`). **No new share arithmetic was written.**

**The reach moved with the fraction.** `MobileDashboardAggregator::sumMortgageShares`
/ `sumMortgageJointOwnerShares` reached by the mortgage row's owner columns and were
deleted for `CrossModuleAssetAggregator::calculateMortgageTotal()`.

## Not done, and why

- **`calculateUserEquity` has zero callers.** Routed to the one home rather than
  deleted — deleting a public helper is a call for someone else. Flagged so it is
  not mistaken for live code.
- **`EstateController`'s non-mortgage `liabilities` are still `where('user_id')`**
  at 100% for reach. `LiabilityResource` now emits a correct `user_share`, so the
  displayed figures are right, but the *reach* is W-0226's, which is queued and not
  mine. Zero rows on this household either way.
- **W-0236 on `/m` and native: no counterpart exists.** `resources/mobile/` has no
  property form; it reads and hands off to web for entry. Verified by search, not
  assumed.

## What you need that isn't obvious from the artefacts

1. **Mortgage 16 still stores `joint 50%`. That is not an oversight.** The reader
   resolves through the property, so the stored contradiction changes no figure.
   New and edited mortgages mirror their property (W-0236), so it stops being
   created. **Do not "repair" the row expecting a figure to move — nothing will.**
2. **`SecuringPropertyResolver` is `scoped()`, and a test that changes a property's
   ownership mid-process must call `->forget()`** or it measures the ownership it
   started with. There is a worked example in the suite.
3. **`PropertyStore::findOwnershipBasis()` is unscoped by user ON PURPOSE.** It will
   read a property the viewer does not own — that is the case the ruling exists for.
   It selects only the ownership columns so it cannot become a general bypass. The
   reasoning is in the method's docblock.
4. **F-0021's £305,000 sign-off is corrected in place.** `W-0187`, `W-0206`,
   `W-0136`, `W-0138` and `W-0172` still quote £182,500 / £305,000 and were
   deliberately **not** rewritten — they record what was observed at the time.
   **Do not re-verify £182,500 against W-0187.**
5. **`LiabilityCard`'s bug was a disclosure, not a rounding error** — see W-0237.

## Assumptions I made

- A mortgage with **no** property falls back to its own columns. It is the only
  information that exists; stated in the docblock so it is not read as the rule.
- Mirroring the property's ownership onto the mortgage on save is right even though
  no figure depends on it — a row that agrees with its property cannot mislead the
  next reader.
- `current_balance` stays the full balance on an individual row; only totals and
  the your-share line are the viewer's.

## Surfaces covered / not covered

- **web** — both accounts, browser-verified.
- **`/m`** — Sarah verified end to end (retirement £35,000/year, liabilities
  £122,500). **David's `/m` liabilities screen: I COULD NOT VERIFY THIS** — his
  `/m` logins bounced to the desktop SPA via the known bridge. Source figure
  confirmed at `NetWorthService` (170,500); the screen itself is unproven for him.
- **iOS** — not covered; no native property form or liabilities entry.
