---
id: W-0137
title: Projected cash goes to minus £1.8m — a Cash ISA projects to −£854,179 and David's total assets fall below today's, so the age-84 estate projection is not credible
mission: persona-run-peak_earners-2026-08-20
branch: F-0018
owner: build-lead
status: handoff
severity: high
surfaces: [web]
created: 2026-08-21T20:25:00Z
claimed: 2026-08-22T06:40:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: [W-0135, W-0136, W-0154, F-0004, PensionProjector, FutureValueCalculator]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, local, both persona accounts.

**Surface:** `/estate/inheritance-tax`, the "AGE 84 / Life expectancy" column, expanded.

### Expected

A projected asset value is a value an asset could actually have. Cash accounts draw down
to zero and stop; they do not become liabilities. A Cash ISA in particular cannot hold a
negative balance — it is a deposit account, and an overdrawn ISA does not exist in UK law.
Whatever drawdown model is being applied, its floor is £0 per account.

### Actual

David's expanded table, age-84 column, verbatim (screenshot
`70-web-david-iht-table-expanded.png`):

```
Property: 15 Chestnut Lane (Joint)        £425,000      £1,231,768
Investment: AJ Bell - GIA (Joint)          £47,500        £681,625
Cash/Savings (2)                           £47,500     -£1,803,267
  HSBC - Current_account                   £25,000       -£949,088
  Nationwide - Cash_isa                    £22,500       -£854,179
Personal Valuables (5)                    £132,250        £132,250
Subtotal                                  £652,250        £242,377
```

- **A Cash ISA projected to −£854,179.**
- **A current account projected to −£949,088.**
- **David's total assets fall from £652,250 today to £242,377 at age 84**, while his
  property alone triples — the negative cash swallows the growth.
- Sarah's cash projects to **−£1,092,590** on the same basis.

The arithmetic is internally consistent (1,231,768 + 681,625 − 1,803,267 + 132,250 =
242,376), so this is a modelling floor that is missing, not a summing error. Personal
valuables are the only class held flat.

**It propagates.** These figures feed "Total Gross Assets" age 84 (£2,343,680 for David,
£2,431,937 for Sarah) and therefore every projected taxable estate, projected tax, and
the taper test in W-0136 — and the two accounts project **different** household estates
(£2,343,680 against £2,431,937) because each treats its own cash differently.

### Impact

The projection is the part of the estate module a user acts on: it is the number that
says whether to gift, to insure, or to do nothing. Presenting it as built from a
minus-£1.8m cash position makes it unusable, and because the negatives are hidden inside
a collapsed "Cash/Savings (2)" row, the headline is merely surprising rather than
obviously wrong — a user sees "assets fall by two thirds" and has no way to see why.

### Repro

1. `david.jones@example.com`, premium, married, with cash accounts and a recorded
   annual expenditure (£29,400).
2. `/estate/inheritance-tax`, wait ~12s, **Expand All**.
3. Read the age-84 column of "Cash/Savings" and of each account beneath it.
4. Compare "David's Assets" NOW (£652,250) with age 84 (£242,377).
5. Repeat as `sarah.jones@example.com`.

### Acceptance

1. No projected asset value is negative on any surface. Cash draws down to £0 and stops.
2. Where projected expenditure exceeds projected liquid assets, that shortfall is
   surfaced as a shortfall — which is a genuinely useful planning output — not folded
   into an asset line as a negative balance.
3. Projected total assets are explicable from their components, and a household's
   projected estate is the same figure from either spouse's login.
4. Fixed before or alongside W-0135 and W-0136, since both read these totals.
5. Verified in a browser on both persona accounts with the table expanded.
