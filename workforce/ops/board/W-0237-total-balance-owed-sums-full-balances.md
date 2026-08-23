---
id: W-0237
title: "Total Balance Owed" sums full balances with no share applied — £365,000 including £72,000 of an off-platform co-owner's debt, and one card showed a spouse the OTHER party's share
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0022-cycle4-dashboard-module-totals-and-cache.md
owner: build-lead
status: handoff
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T18:45:00Z
claimed: 2026-08-22T20:15:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: [W-0226, W-0228, W-0015]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Raised as D-03 by the tester. `LiabilitiesList.vue:195-197` summed
`current_balance` across every row with no share applied — **£365,000**, including
the whole of a jointly-held mortgage and the £72,000 belonging to Mike Barrett, an
off-platform co-owner. `totalMonthlyPayments` did the same: £1,950 against a real
£900.

**Distinct from W-0226**, which is `NetWorthService:132` over the `liabilities`
table — a table this household has **zero rows in**.

### The part that is worse than the total

`LiabilityCard.vue::userShare()` recomputed the share client-side as
`balance * ownership_percentage / 100`. **`ownership_percentage` is the PRIMARY
owner's share.** So the card showed the primary owner their share, and showed the
**co-owner the other party's share**.

At the persona's 50/50 splits that is right for both parties by symmetry, which is
why it survived. At the Manchester unit's 40/60 it is not: the co-owner reads a
figure describing **someone else's financial position**. That is a disclosure
issue, not an arithmetic slip, and it is why this item is `high` rather than
`medium`.

The card also printed **"Joint (40.00% yours)"** against a `tenants_in_common`
property — the exact distinction W-0228's ruling turns on.

## Acceptance

1. The totals are the viewer's share, not the full balances.
2. A third party's share is charged to nobody.
3. No co-owner is shown another party's share.
4. The ownership basis is named correctly.
5. Verified on both accounts, web and `/m`.

## Working notes — DONE 2026-08-22, handed to quality-lead

**The share moved to the server, where the one reader lives.**
`EstateController::index` now emits `user_share` and `user_monthly_payment_share`
per mortgage row, computed by `calculateUserMortgageShare` — which resolves the
securing property (W-0228). `LiabilityResource` emits `user_share` for
non-mortgage liabilities via `calculateUserShare`.

**It used to hand the frontend the property's ownership pair and leave the
arithmetic to the client — and nothing on the other end read it.**
`LiabilitiesList` summed `current_balance` whole; only `LiabilityCard` used the
pair, and it used it wrongly. Two halves of a mechanism, neither working.

`LiabilityCard` now routes through `resources/js/utils/ownership.js` — the one home
established by W-0015 — for the share (`calculateUserShare`, which already prefers
the API's `user_share`), the percentage (`userSharePercent`, **viewer-aware**) and
the label (`getOwnershipLabel`).

`current_balance` is still shown against an individual row, because that is what is
owed on the debt. Only the **totals** and the **your-share** line are the viewer's.

### Measured — browser-verified, David (16), `/net-worth/liabilities`

| | Before | After |
|---|---|---|
| TOTAL BALANCE OWED | £365,000 | **£170,500** |
| TOTAL MONTHLY PAYMENTS | £1,950/mo | **£900/mo** |
| Manchester unit label | "Joint (40.00% yours)" | **"Tenants in Common (40% yours)"** |
| Manchester unit Your Share | £48,000 | £48,000 |

Per-row shares read £90,000 / £48,000 / £32,500 and sum to the £170,500 total —
the figure and its own components now agree on one screen.

### Evidence

`tests/Feature/NetWorth/MortgageShareFollowsThePropertyTest.php` — 10 passing,
**including a real `liabilities` row created specifically because this persona has
none**, asserting the answer moves when the share moves (50% → 75% moves £10,000 →
£15,000). Without that row every assertion over the non-mortgage path would pass
trivially.
