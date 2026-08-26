---
id: W-0501
title: The "estate exceeds the nil-rate band" recommendation estimates the estate by hand, counts no ownership share, and drops the non-primary side of every joint asset entirely
mission: w-0368-undivided-share-discount
branch: null
owner: build-lead
status: open
severity: high
surfaces: [web, m, ios]
created: 2026-08-26T00:00:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: quality-lead
prior_art_checked: 2026-08-26
prior_art_found: [W-0368, W-0015, W-0040]
prior_art_outcome: extends
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

Found by `tax-compliance-reviewer` during the W-0368 re-gate as "a fifth valuation
site", non-blocking because it overstates. **Measured on 2026-08-26, and it is not
only an overstatement.** It is wrong in both directions, it is not confined to
property, and the direction the reviewer did not see is the one that suppresses a
warning.

`EstateActionDefinitionService::estimateEstateValue()` (`:340-378`) re-implements the
estate total by hand. Every asset line sums the **full record value** and applies no
`ownership_percentage` at all, then filters each collection with
`->where('user_id', $user->id)`, which drops any asset where the user is the
`joint_owner_id` rather than the primary owner.

Rule 6 is the whole point of that column: a joint asset is a SINGLE record holding the
full value, with `ownership_percentage` as the primary owner's share. This method reads
the record as though the primary owner holds all of it and the other party holds none.

### Measured

One property, £295,000, tenants in common, A primary at 40% and B holding 60%:

| | True share | `estimateEstateValue()` |
|---|---|---|
| A — primary owner | £118,000 | **£295,000** (2.5×) |
| B — joint owner | £177,000 | **£0** |

Not property-specific. One joint savings account, £100,000 at 50/50:

| | True share | `estimateEstateValue()` |
|---|---|---|
| A — primary owner | £50,000 | **£100,000** |
| B — joint owner | £50,000 | **£0** |

The same shape applies to `InvestmentAccount`, `CashAccount`, `Asset`, DC pensions and
life policies — every line in the method scopes on `user_id` and sums a full value.

## Why this is high and not low

The figure is **user-facing money**. `evaluateIhtExceedsNrb()` (`:156-186`) puts four
formatted pound figures straight in front of the user — `estate_value`, `nrb`,
`excess_amount`, `iht_liability` — and sets `estimated_impact`, which ranks the
recommendation against every other action.

And the recommendation is gated on the same broken number:

```php
if ($estateValue <= $availableBand) {
    return [];
}
```

So for the £0 case the recommendation **never fires**. A user whose main asset is a
co-owned property they are not the primary owner of is told nothing about an
Inheritance Tax exposure they have. That is a suppressed warning, not a conservative
estimate, and it is the direction the re-gate's "non-blocking because it overstates"
assessment missed.

The overstating case is its own harm under Consumer Duty: a user is shown an inflated
estate and an inflated liability, and is recommended estate planning against a number
2.5× their actual share.

## Same eight lines, same class — the band is wrong too

`$availableBand = $nrb + $rnrb;` grants the residence nil-rate band **unconditionally**
— no check for a qualifying residence, none for direct descendants, and no £2M taper.
That inflates the band, which makes the gate above **less** likely to fire. Same
suppressing direction as the £0 case, and it should be fixed in the same pass rather
than raised separately.

## The fix is deletion, not repair (Rule 20)

Do not add `ownership_percentage` arithmetic to this method. The application already
has one home for this and it is the Inheritance Tax path:

- `EstateAssetAggregatorService::gatherUserAssets()` applies ownership shares and the
  W-0368 undivided-share discount, and `calculateUserLiabilities()` is its liability
  counterpart.
- `IHTCalculationService::calculate()` is the engine that produces the net estate,
  the allowances **with** the RNRB conditions and the £2M taper, and the liability.

`estimateEstateValue()` is a second, divergent implementation of a calculation the
application already does correctly elsewhere — the exact shape W-0368 spent three
rounds on, where two sites answered the same question differently and the wrong one
reached the user. Reading the engine fixes the shares, the dropped joint side and the
unconditional RNRB together, and removes the divergence permanently.

## Acceptance

1. `evaluateIhtExceedsNrb()` sources the estate value and the available band from the
   existing Inheritance Tax engine, not from a private re-sum.
2. `estimateEstateValue()` is **deleted**, not corrected — if anything still needs it,
   that caller reads the engine too.
3. Pinned by test in **both** directions, because only one of them was noticed:
   a primary owner of a joint asset is valued at their share and not the whole; a
   non-primary joint owner is valued at their share and **not at zero**.
4. A test asserting the recommendation **fires** for a user whose exposure sits
   entirely in a co-owned asset they are not primary owner of — the suppressed-warning
   case.
5. The RNRB in this path respects the residence and direct-descendant conditions and
   the £2M taper, consistent with the engine.
6. The four displayed figures and `estimated_impact` reconcile with the estate figure
   the Estate module shows the same user. A user must not be able to read two different
   estate values on two screens.
7. Verified on web, `/m` and native (Rule 19) — this is a recommendation, so it travels
   to every surface.

## Related

- **W-0368** — parent. The re-gate that found this site. C1–C3 discharged, C2 fixed in
  `7476ac5b8`; PR #719 awaiting re-gate. This item is the C5 "fifth site", promoted and
  re-scoped after measurement.
- **W-0500** — the other W-0368 follow-on: `/m` and native cannot answer the spouse
  question the discount turns on.
- **W-0015** — joint share computed three ways, surfaces disagree. Same disease, and
  the reason the fix here is deletion rather than a fourth implementation.
- **`PersonalizedGiftingStrategyService:328`** — recorded under W-0368 C4: a downsizing
  recommendation reading "Sell £X home, buy £Y property" where X is the **discounted
  share**, so it quotes a sale price that is neither the property's value nor the
  user's share of it. Adjacent, separately owned, and worth checking in the same sweep
  of user-facing estate figures.
