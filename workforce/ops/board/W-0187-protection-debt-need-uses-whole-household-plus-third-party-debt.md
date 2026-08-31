---
id: W-0187
title: Protection charges one person the entire household's mortgage debt including a third party's share — £365,000 where David's share is £182,500
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0019-cycle2-ownership-applied-one-side-only.md
owner: build-lead
status: done
severity: high
surfaces: [web, m]
created: 2026-08-22T00:30:00Z
claimed: 2026-08-22T01:40:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0172, W-0173]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Cycle 2 journey re-walk, local, `david.jones@example.com`, read-only.
**Surface:** `/protection` → "Protection Shortfall" → Debt Protection.

### Expected

Protection need is personal. David's share of the household's mortgages is
**£182,500** as entered (£170,500 persona-correct):

| Mortgage | Balance | Type | David's share |
|---|---|---|---|
| HSBC — The Willows | 65,000 | joint 50% | 32,500 |
| Barclays — City Centre Flat | 180,000 | joint 50% | 90,000 |
| NatWest — Manchester | 120,000 | tenants in common 40% | 48,000 (60,000 as entered — W-0172) |

**£72,000 of the Manchester loan is Mike Barrett's** and belongs in no figure on this
household's account — the same principle the property and estate modules honour exactly.

### Actual

```
Debt Protection            none
  Current Life Cover (for debt)   £365,000
  Mortgage Debt                   £365,000     ← the FULL household + third-party total
  Other Liabilities                    £0
  Total Debt                      £365,000
  Shortfall                            £0
```

£365,000 is 65,000 + 180,000 + 120,000 — **every mortgage at 100%**, including Sarah's
halves and Mike Barrett's 60%. David's actual exposure is £182,500, so the debt-protection
need is **overstated by £182,500**, of which **£72,000 is a third party's debt**.

The module is also inconsistent with itself: the income-replacement panel beside it uses
**David's income alone** (£145,000), so debt is computed household-wide and income
individually.

### Impact

The shortfall reads **£0** and the status reads **none** — so the overstatement currently
hides *behind* a pass. If his cover were lower, or the mortgage higher, the same
arithmetic would manufacture a shortfall out of debt he does not owe and sell him cover
he does not need. Counting a third party's mortgage as a protection need is the same
class as the £177,000 the persona exists to keep out.

### Repro

1. `david.jones@example.com` → `/protection`, wait ~14s.
2. Read "Mortgage Debt": **£365,000**.
3. `/net-worth/property` → the three cards show David's mortgage liabilities as £32,500,
   £90,000 and £60,000 — **£182,500**.

### Acceptance

1. Debt protection uses the user's own share of each liability, from the same ownership
   calculation the property and estate modules already use (Rule 20).
2. A third-party co-owner's share never appears.
3. Debt and income are computed on the same basis — both individual, or both household
   and labelled as such.
4. Verified in a browser on both accounts, hand-checked against the property cards.

## Working notes

**2026-08-22 — build-lead (`cycle2-ownership`). Fixed.** Branch document:
`workforce/branches/fixes/F-0019-cycle2-ownership-applied-one-side-only.md`.

### The £365,000 did not come from where the item assumed

The item traces the figure to the protection module, and the protection module was
indeed wrong — but the number **printed on `/protection`** comes from somewhere else
again. `GapAnalysis.vue:657` and `ProtectionModuleOverview.vue:764` both read
`liabilities_summary.mortgages.total` off `GET /api/user/profile`, i.e.
`UserProfileService::calculateLiabilitiesSummary()`. Fixing only the protection
services would have left the screen reading £365,000.

**Three mechanisms answered "what does this user owe", and none applied the share:**

| Mechanism | What it read |
|---|---|
| `UserProfileService::calculateLiabilitiesSummary():694-700` | `mortgageStore->forUserPrimaryOnly()` + `$user->liabilities`, both at 100% — **the printed £365,000** |
| `CoverageGapAnalyzer::calculateDebtProtectionNeed():63-66` | `$user->mortgages()->sum(...)` + `$user->liabilities()->sum(...)`, both at 100% |
| `ProtectionAgent::analyze():147-148` | `mortgageStore->forUserPrimaryOnly()->sum(...)` + `$user->liabilities()->sum(...)` |

All three are scoped to `user_id` alone: they charge the primary owner the whole of
every shared debt and show the joint owner none of it. A fourth mechanism —
`CrossModuleAssetAggregator::calculateMortgageTotal()` — already had it right, and it
is what the property cards and the estate module use.

### One home

**`CrossModuleAssetAggregator::calculateLiabilityTotals(int $userId): array{mortgages, other, total}`**
(`app/Services/Shared/CrossModuleAssetAggregator.php:380-419`). All three mechanisms
above now read it. It is:

- **Reach-complete** — it picks up a mortgage secured on a jointly-owned property
  even where the user is not the named borrower (the two-leg pattern
  `calculateMortgageTotal` already had), and `Liability::forUserOrJoint` for the rest.
- **Fraction-correct** — `calculateUserShare` / `calculateUserMortgageShare`, the same
  helpers the property cards use, so the figures agree by construction rather than by
  coincidence.
- **Third-party-safe** — those helpers return `0.0` for anyone who is neither party,
  so a share belonging to someone with no account reduces the user's figure without
  being credited to anybody.

**One thing was consolidated beyond the share, and it is a SECOND DEFECT, recorded as
one.** A `liabilities` row of `liability_type = 'mortgage'` is a second way to record
the same borrowing. The profile has always presented those alongside the mortgages
table; **protection summed them into "other debt" AND counted the mortgages table
separately**, so anyone who recorded their mortgage that way **has been shown roughly
twice their debt** — on a figure the module converts directly into "buy this much
cover". `calculateLiabilityTotals` puts them with the mortgages, once.

It is **W-0203**, filed separately at team-lead's instruction rather than left as a
side effect of this one: different cause, different wrong number, fixed in the same
change. **Invisible on this persona**, which holds zero `liabilities` rows — it was
found by reading the two code paths against each other, not by testing them, which is
exactly why it needed finding rather than waiting for a repro.

### Measured against the live persona rows (read-only)

```
liabilities_summary (David, id 16)
  HSBC     £32,500      (joint 50% of £65,000)
  Barclays £90,000      (joint 50% of £180,000)
  NatWest  £60,000      (as entered — see W-0172)
  mortgages total       £182,500      was £365,000
  other                 £0
  total                 £182,500

calculateLiabilityTotals(17)  →  mortgages £122,500, other £0, total £122,500
```

**£182,500 — exactly the figure the item expected**, and exactly what the three
property cards show. Sarah's £122,500 is her halves of the two joint mortgages and
nothing from Manchester, where she is not a party. £182,500 + £122,500 = £305,000
against a record total of £365,000: the £60,000 difference is the third party's share
as entered, **charged to neither account**.

The item's £170,500 "persona-correct" figure is what these tests assert for the
tenants-in-common case at 40%. The live rows still store that mortgage as `joint`
50/50, which is **W-0172's** item, not this one — the share mechanism honours whatever
the record says, so W-0172's fix will move this figure to £170,500 with no further
change here.

### Acceptance 3 — debt and income on the same basis

Both are now individual: debt is the user's share of each liability, income is their
own. That was the inconsistency the item named — debt household-wide beside
income-replacement on his income alone.

### Items and totals can no longer disagree — the W-0134 principle, in a second module

`liabilities_summary` items previously printed full balances under a full total; both
are now the user's share, so **the list adds up to the figure above it**. That is
W-0134's principle arriving in the profile and protection modules: a total and the
items beneath it are one statement, and a user who adds up the rows must reach the
number printed above them or neither figure can be trusted. `monthly_payment`
on a mortgage item is likewise the user's share, via
`calculateUserMortgageMonthlyPaymentShare` — the same helper `PropertyController`
already uses for the property cards. The consumers are read-only
(`LiabilitiesOverview.vue` displays; nothing posts these values back), checked before
changing them.

### Tests

`tests/Feature/Protection/ProtectionDebtUsesUserShareTest.php` — **7 passing.** Real
records, real services; the aggregator is the real one, not a mock, so nothing asserts
a figure a mock supplied.

1. A joint mortgage charges each spouse £150,000 of £300,000.
2. **A third party's share is charged to nobody** — a tenants-in-common 40% of
   £120,000 gives the owner £48,000, the linked spouse **£0.00**, and the two together
   strictly less than the record. The invariant.
3. The protection debt need over the persona's three mortgages is **£170,500**, not
   the £365,000 the records total.
4. A joint personal loan splits £10,000 / £10,000.
5. A mortgage-type liability counts with the mortgages, once, not twice.
6. **The profile's liability list sums to the total printed above it.**
7. Protection and the profile return the identical debt figure.

`CoverageGapAnalyzerTest` was updated to construct with the **real** aggregator, and
the reason is recorded in the test itself rather than only here: *"the asset aggregator
is the REAL one — these tests use real records, and a mocked debt total would assert
only what the mock was told to say."* That is the trap this codebase has now hit
three times. `ProtectionAgentTest` and `ProtectionAgentGoalsTest` swap their `MortgageStore`
mock for the aggregator — arity unchanged. Families re-run green: `Unit/Services/Protection`,
`Feature/Protection`, `ProtectionGapPresentationTest`, `Unit/Agents` — **248 passing**;
`NetWorthServiceTest`, `Unit/Services/Shared`, `PersonalAccountsService(+Controller)`,
`Unit/Services/Mobile` — **172 passing**; `UserProfileServiceTest`,
`Unit/Services/UserProfile`, `UserProfileControllerTest` — **77 passing**.
`./vendor/bin/pint` on the touched paths: passed.

### Surfaces

Server-side in shared services behind `GET /api/user/profile` and `GET /api/protection`
— the **same endpoints `/m` uses**, so `/m`'s protection gaps and its
`PersonalInformation.vue` total inherit the fix with no client change.
`ProtectionGapPresentationService` reaches it through
`CoverageGapAnalyzer::calculateProtectionNeeds():397`. `ios-native/` reads the same
endpoints. **Surfaces widened from `[web]` to `[web, m]`** — `/m` shows the same total.

### Adjacent, NOT fixed — reported per scope discipline

1. **`NetWorthService::calculateLiabilitiesBreakdown():132`** reads
   `Liability::where('user_id', $userId)` at 100% — the same disease in the net worth
   module, outside these four items. Not touched.
2. **`UserProfileService::$mortgageStore` is now an unused constructor dependency.**
   Removing it means editing two test files that another agent currently has modified
   in the shared tree, so I left it rather than collide. One-line removal when that
   settles.
3. `ProtectionGapPresentationService:80` still emits `profile->mortgage_balance` (the
   user-entered override) as a gap component. It is `0.00` for this household so it
   contradicts nothing today, but the override and the computed share are two sources
   for one number.

**Not done: browser verification, by instruction.**

- 2026-08-31 build-lead: **CLOSED — verified against `dev`.**
  `CoverageGapAnalyzer::calculateDebtProtectionNeed():76` returns
  `CrossModuleAssetAggregator::calculateLiabilityTotals($profile->user_id)['total']`, which reaches
  each liability through `forUserOrJoint()` and takes `calculateUserShare()` of it — so a third
  party's share reduces the figure without being credited to anyone, and David is charged his own
  £182,500 rather than the household's whole debt.
  **The profile-override branch above it (:71-73) is deliberately left standing and is W-0227's
  subject, not this item's.**
