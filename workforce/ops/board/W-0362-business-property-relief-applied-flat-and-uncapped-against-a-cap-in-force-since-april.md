---
id: W-0362
title: Business Property Relief is applied flat and uncapped while the configured £2.5m cap has been in force since 6 April 2026, and Agricultural Property Relief is absent entirely
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
duplicate_of: W-0091
closed: 2026-08-23T12:45:00Z
status: closed_duplicate
severity: high
surfaces: [web, m, ios]
created: 2026-08-23T01:05:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [F-0026, W-0333, tax-compliance-review]
prior_art_outcome: extend
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

From the tax-compliance review of W-0333. **This is live law being missed today, not
an upcoming change.**

`EstateAssetAggregatorService:116-138` models Business Property Relief as a **binary
flag**:

```php
$ihtExempt = false;
if ($business->bpr_eligible && $business->trading_status === 'trading') { ... $ihtExempt = true; }
```

Binary exempt / not-exempt means **100% relief, uncapped**. The **active**
`TaxConfigService` disagrees, and it is dated and switched on:

```json
"business_relief": { "allowance_cap": 2500000, "cap_in_effect": true,
                     "relief_above_cap": 0.5, "aim_shares_outside_cap": true,
                     "rates": { "aim_shares": 0.5 },
                     "allowance_cap_effective_date": "2026-04-06" }
```

**The cap has been in force for over four months.** A £6m trading business is modelled
as wholly exempt where the correct treatment is 100% on the first £2.5m and 50% above
— £1.75m chargeable, **~£700,000 of understated tax**. AIM shares (50%, outside the
cap) and the spouse-transferable cap are likewise unmodelled.
`TaxConfigService::getBusinessRelief()` exists and **nothing on this path calls it.**

**Agricultural Property Relief is worse: entirely absent.**
`getAgriculturalRelief()` is populated with the same capped structure and has **no
caller in the estate path at all**. Agricultural property receives no relief
whatsoever.

Reaches the Inheritance Tax figure through `$userGrossAssets` (`IHTCalculationService:158`)
and `$projectedBusiness` (`:485-496`), both of which read `is_iht_exempt` straight
from the flag.

## Acceptance

1. Relief is computed from `getBusinessRelief()` / `getAgriculturalRelief()`, cap and
   rates included — never a boolean.
2. The `allowance_cap_effective_date` decides whether the cap applies, so the answer
   follows the configuration rather than the calendar in someone's head (Rule 2).
3. AIM shares at 50% outside the cap.
4. Before/after on a household holding a business above the cap — **the persona holds
   no business interests at all** and cannot exercise any of this.
5. **`tax-compliance-reviewer` on the fix.**


## Closed as a duplicate of W-0091 — 2026-08-23

CSJ's direction. W-0091 raised the same defect on 2026-08-21 from the W-0154 fix batch,
two days before this item was raised from the tax-compliance review of W-0333; the
earlier id survives so the prior-art chain back to W-0154 F6 stays intact.

**Everything unique to this item has been merged into W-0091** — the Agricultural
Property Relief absence, the shared cap (`cap_shared_with_bpr`), the
`IHTCalculationService` reach, and the acceptance criteria including the
`tax-compliance-reviewer` gate, which is now set on W-0091.

**Work W-0091. Nothing is lost by ignoring this file.**
