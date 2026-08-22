---
id: W-0206
title: Goals reports a "Current Net Worth" that is wrong on both accounts in opposite directions — David's subtracts the entire household plus third-party mortgage debt, Sarah's subtracts none
mission: persona-run-peak_earners-2026-08-20
branch: F-0021
owner: build-lead
status: handoff
severity: high
surfaces: [web]
created: 2026-08-22T01:40:00Z
claimed: 2026-08-22T08:40:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: [W-0187, W-0172, W-0173, F-0019]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Cycle 3 journey re-walk, local, both persona accounts, read-only.
**Surface:** `/goals` → Financial Projection → "Current Net Worth".

### Expected

Net worth is the user's own assets less **their own share** of liabilities — the figure
the dashboard already shows correctly on both accounts.

| | Assets | Own liabilities | Net worth |
|---|---|---|---|
| David | £1,660,000 | £182,500 | **£1,477,500** |
| Sarah | £861,780 | £122,500 | **£739,280** |

### Actual

| | Dashboard | `/goals` | Error |
|---|---|---|---|
| David | **£1,477,500** ✓ | **£1,295,000** | **−£182,500** |
| Sarah | **£739,280** ✓ | **£861,780** | **+£122,500** |

**The dashboard is right on both accounts and `/goals` is wrong on both, in opposite
directions.** Two figures for one user's net worth, at the same instant, two clicks apart.

**Each error is exact and identifies its own cause:**

- **David: £1,660,000 − £365,000 = £1,295,000.** £365,000 is the **entire household's
  mortgage debt** — `SELECT SUM(outstanding_balance) FROM mortgages WHERE user_id = 16`
  returns exactly that — including Sarah's halves **and Mike Barrett's 60% of the
  Manchester loan**. His own share is £182,500. This is precisely the failure
  **W-0187** just fixed in Protection, surviving here.
- **Sarah: £861,780 is her gross assets.** Her £122,500 of liabilities are subtracted
  **zero** times, so she is shown assets under a net-worth label.

### Impact

The figure is the base of the whole Goals projection — "Projected Net Worth at 60
£1,855,581" and "at 90 £6,226,169" for David, "£1,475,734" and "£3,908,784" for Sarah are
all built on it, so every goal trajectory and on-track judgement inherits the error.

The two errors run opposite ways, so one spouse is told they are poorer than they are and
the other richer, on the module whose purpose is judging whether their goals are
reachable. And a third party's mortgage is once again inside a household figure — the
same principle the persona exists to keep out.

### Repro

1. `david.jones@example.com` → `/dashboard` → net worth **£1,477,500**.
2. Same session → `/goals` → "Current Net Worth" **£1,295,000**.
3. `sarah.jones@example.com` → `/dashboard` → **£739,280**; `/goals` → **£861,780**.
4. `php artisan tinker --execute='echo DB::table("mortgages")->whereNull("deleted_at")->where("user_id",16)->sum("outstanding_balance");'` → `365000.00`.

### Acceptance

1. `/goals` shows the same net worth as the dashboard on both accounts, from the same
   calculation (Rule 20) — not a second net-worth aggregation in the Goals module.
2. Liabilities are applied once, at the user's own ownership share, and a third party's
   share never appears.
3. The projections rebase on the corrected figure.
4. Verified in a browser on both accounts against the dashboard, and on `/m`.

---

## Outcome — DONE

**One home:** `CrossModuleAssetAggregator::getMortgages()` (reach) +
`CalculatesOwnershipShare::calculateUserMortgageShare()` (fraction).
**Fixed at:** `app/Services/Goals/GoalsProjectionService.php::getMortgageParameters()`.

### The cause was one method holding both of F-0019's failures at once

The method read `mortgageStore->forUserPrimaryOnly($user)` and summed
`outstanding_balance` at face value. That is the *reach* failure and the *fraction*
failure in a single place, which is why one household produced two errors running in
opposite directions from one line of code:

- **David is the borrower on all three mortgages.** Reach found them all; the missing
  fraction charged him the lot. 65,000 + 180,000 + 120,000 = **£365,000**, the item's
  figure exactly, including the £60,000 of mortgage 16 belonging to Mike Barrett.
- **Sarah is `joint_owner_id` on two and borrower on none.** `forUserPrimaryOnly`
  returned an **empty collection**, so `original_balance` was 0 and
  `calculateMortgageBalance()` returned 0.0 for every year of her projection. Her
  liabilities were subtracted zero times — not under-applied, never applied.

A second decoy sat beside it: `generateYearlyData():137` assigned `$mortgage` from
`$netWorth['liabilities_breakdown']['mortgages']` — the correct dashboard figure — and
then **overwrote it unconditionally inside the loop before first read**. The right
answer was being computed and discarded, which is how the page could disagree with the
dashboard while appearing to read from it. Dead assignment removed.

### Measured, live database, both accounts

| | Dashboard | `/goals` before | `/goals` now |
|---|---|---|---|
| David net worth | £1,477,500 | £1,295,000 | **£1,477,500** |
| David mortgages | £182,500 | £365,000 | **£182,500** |
| Sarah net worth | £739,280 | £861,780 | **£739,280** |
| Sarah mortgages | £122,500 | £0 | **£122,500** |

**Third-party-safe:** £182,500 + £122,500 = **£305,000**, not £365,000. Mike Barrett's
£60,000 is charged to nobody and does not fall through to the spouse.

### Acceptance

1. **Met.** Both accounts equal the dashboard, and by construction rather than
   coincidence: summing `calculateUserMortgageShare` over `getMortgages` *is*
   `calculateMortgageTotal`, which is what the dashboard subtracts.
2. **Met.** Applied once, at the user's share; a third party's share appears nowhere.
3. **Met.** Every year's `net_worth` and `liabilities.mortgage` rebase, therefore
   `starting_net_worth`, `retirement_net_worth`, `ending_net_worth`, `peak_net_worth`
   and `peak_age`, across all three chart views. Sarah previously had no debt curve at
   all and now has one.
4. **Browser verification is quality-lead's** (this batch is forbidden from verifying
   its own work). **On `/m` there is nothing to verify: the surface does not exist.**
   Established by grep, not assumed — `grep -rn "goals/projection" resources/mobile`
   returns nothing; the /m goals screen reads `/api/goals`,
   `/api/goals/dashboard-overview` and `/api/life-events` only and has no net-worth or
   projection card. (`/m`'s `NetWorthForecast.vue` reads `/api/net-worth/forecast`, a
   different service, untouched.) Native likewise: `GoalsClient.swift` calls only
   `api/goals`, `api/goals/dashboard-overview`, `api/goals/{id}`.

### Implementations of "what does this user owe on mortgages"

**4 → 3.** Nothing new was written. The remaining third is
`NetWorthService::calculateLiabilitiesBreakdown():132`, the non-mortgage side already
filed as **W-0226** by F-0019 — deliberately not touched here.

### Raised, not fixed

**The projection picks the mortgage type by "last record wins."** `$primaryType` is
reassigned on every iteration, so whichever record iterates last decides whether the
**entire** household balance amortises or stays flat. Pre-existing, but this fix makes
it visible: Sarah's set is now non-empty and ends on the interest-only record, so her
£122,500 stays flat for ~15 years while David's £182,500 amortises — one household, two
debt curves. It does not touch year zero, so it is provably independent of the figure in
this item. A balance-weighted majority would change nothing on this persona and remove
the order dependence, but it changes behaviour for other users, so it is a decision, not
a tidy-up. Awaiting an ID from the block.

### Tests

`tests/Feature/Goals/GoalsNetWorthMatchesDashboardTest.php` — **5 passing**. The fixture
deliberately holds what `peak_earners` does not: a **non-mortgage liability** (without
it the `$otherLiabilities` branch is never entered) and a **mortgage co-owned with a
non-user** (without it the third-party harm cannot be observed). Two tests assert the
answer *moves* when the input moves, so agreement with the dashboard cannot pass by both
sides ignoring the same row.

---

## Addendum — the non-mortgage acceptance constraint

Issued by team-lead after the fix was built. Checked rather than assumed; the
constraint is **met**, and checking it turned up two things worth having.

### The constraint

> W-0206's own evidence is a `SUM(outstanding_balance) FROM mortgages` — so a
> liability that is **not** a mortgage is precisely what a fix to it could pass
> while still being wrong.

**Correct, and it is the right thing to have worried about.** The fix is not
mortgage-only: the projection subtracts non-mortgage liabilities through
`$otherLiabilities`, built from `liabilities_breakdown`'s `credit_cards`, `loans`
and `other` keys. That path pre-dates this work and was never broken.

Covered from the first version of the suite by *"it subtracts a non-mortgage
liability, and still agrees with the dashboard when it does"* — a £12,000 credit
card, asserting both agreement with the dashboard **and** that the figure moves by
exactly £12,000, so agreement cannot pass by both sides ignoring the same row.

Now strengthened to the exact shape the tester will enter next cycle: **a joint
hire purchase beside the third-party mortgage**, on both accounts.

### One correction to the constraint as written

> `calculateLiabilityTotals($userId)` returns `{mortgages, other, total}` — it
> already separates the two, which is why routing to it is the fix. If you find
> yourself adding a mortgage-only path, you have taken a wrong turn.

**Routing `getMortgageParameters()` to `calculateLiabilityTotals()` would have been
the wrong turn.** That method returns three floats. `getMortgageParameters()` has
to produce `{original_balance, annual_rate, remaining_years, mortgage_type}` to run
a forty-year amortisation curve — it needs the **rate, the term and the type**,
per record, none of which a total carries.

The projection has two liability paths **by design**, and correctly so: mortgages
amortise on a schedule, everything else decays as a bucket. That is not a
mortgage-only fix; it is the mortgage half of a two-half structure whose other half
was already present and already agreeing with the dashboard. The correct routing
was to the aggregator's **reach** primitive, `getMortgages()`, plus the trait's
**fraction**, which is what was done.

### Measured — what the tester will see next cycle

Fixture: joint mortgage £180,000 (50/50), **joint hire purchase £24,000 (50/50)**,
joint mortgage-typed liability row £50,000 (50/50).

| | dashboard net | `/goals` net | breakdown mortgages | breakdown loans | aggregator says |
|---|---|---|---|---|---|
| Borrower | £270,478 | **£270,478** | £90,000 | **£24,000** | mortgages £115,000, other **£12,000** |
| Joint owner | £294,478 | **£294,478** | £90,000 | **£0** | mortgages £115,000, other **£12,000** |

**`/goals` agrees with the dashboard on both accounts — the contract holds.** But
the £24,000 hire purchase is charged **wholly to the borrower and not at all to the
joint owner**, where the aggregator says £12,000 each. That is **W-0226**, visible
identically on both surfaces, and it is why this test asserts *agreement* and
deliberately does **not** pin the split: doing so would bake W-0226's bug into a
goals test and turn its eventual fix red.

**So the tester will raise this next cycle and it is not a goals defect.** The
£24,000 asymmetry between the two accounts is already filed.

### New finding — a mortgage recorded as a liability row is valued at zero

Not W-0226, not previously raised, found by the probe above.

`NetWorthService::calculateLiabilitiesBreakdown()` skips `case 'mortgage'` on the
stated grounds that property mortgages come from the mortgages table.
`getMortgages()` reads the mortgages table. So a **second charge recorded as a
`liabilities` row is counted by neither** — £0 on the dashboard and £0 on `/goals`.
`CrossModuleAssetAggregator::calculateLiabilityTotals()` disagrees with both and
counts the user's share of it (£25,000 each on the fixture above), which is what
protection uses.

**Protection charges it; net worth omits it — a £25,000-per-spouse disagreement
between two modules about the same debt.** The mirror image of W-0203, which was a
double count of the same shape. Reported, not fixed; needs an ID.

### A divergence hazard, now tripwired

The goals projection derives its mortgage figure from `getMortgages()` and **never
reads `liabilities_breakdown['mortgages']`** — the dead assignment that appeared to
was removed as part of this fix. So the day anybody teaches `NetWorthService` to
count mortgage-typed liability rows, **the dashboard will move and `/goals` will
not**: W-0206 reintroduced by a fix to something else.

`it('keeps agreeing with the dashboard when a mortgage is recorded as a liability
row rather than a mortgage row')` passes today because both sides are zero, and
goes red the moment that changes. It is a tripwire, not an assertion that zero is
correct, and its docblock says so.

### W-0226 was read from, never written to

As instructed. `$netWorth['liabilities_breakdown']` is consumed exactly as before;
`calculateLiabilitiesBreakdown()` is untouched. **Nothing in this batch absorbs any
part of W-0226.**

**Suite now 7 passing (13 assertions).**
