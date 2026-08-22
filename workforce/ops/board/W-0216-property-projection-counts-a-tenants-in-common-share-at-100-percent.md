---
id: W-0216
title: The projected estate includes a property this household owns 40% of, at 100% — £512,995 at the horizon and £205,198 of tax on somebody else's house
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: queued
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T08:10:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0137, W-0188, F-0018, F-0013]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by `cycle2-projection` while reconciling the projected estate from its parts for
W-0137. **It is not visible from the total** — the total reconciles perfectly. It is
visible only from the implied growth rate.

### The finding, in one line

**The growth rate is right. The base is wrong.**

Projected property implies **3.34% a year** over 36 years against a configured **3.00%**.
That is not an aggressive growth model. The projection compounds at exactly 3.00% —
verified, the factor is 2.89828 and `1.03^36 = 2.89828` — on a base **£177,000 larger
than the household owns**.

### Where the £177,000 comes from

`IHTCalculationService::projectProperties()` sums `current_value` at **full value** for
rows the user is primary owner of:

```php
$currentPropertyValue = (float) $this->propertyStore
    ->forUser($user)
    ->where('user_id', $user->id)
    ->sum('current_value');
```

**That is correct for a joint property** — a 50/50 property between the two spouses is
counted once at full value, and the full value *is* the household's. The primary-owner
filter exists precisely to stop it being counted twice.

**It is wrong for tenants in common with a third party**, where the household owns a
stated percentage and the rest belongs to somebody who is not in the application.

Live, users 16 and 17:

| id | Type | Ownership | Percentage | Value | Household's share | Projection uses |
|---|---|---|---|---:|---:|---:|
| 9 | main residence | joint | 50 | £850,000 | £850,000 | £850,000 ✓ |
| 19 | buy to let | joint | 50 | £425,000 | £425,000 | £425,000 ✓ |
| 20 | buy to let | **tenants in common** | **40** | £295,000 | **£118,000** | **£295,000** ✗ |
| | | | | | **£1,393,000** | **£1,570,000** |

### The arithmetic

```
excess base            £177,000   (£295,000 counted, £118,000 owned)
growth factor 36 years  2.89828   (= 1.03^36, exactly the configured rate)
excess at the horizon  £512,995
tax at 40%             £205,198
```

Projected property should be **£4,037,302**. It is **£4,550,297**.

### Impact

**£205,198 of Inheritance Tax charged on 60% of a house this household does not own**,
and it propagates into everything the projection feeds: the projected estate, the
residence-band taper test (W-0136), life-cover sizing, and any gifting strategy
quantified against the future estate.

### The detail that makes it findable, and hides it

**The current-year column is correct.** It reads `EstateAssetAggregatorService`, which
applies `ownership_percentage` properly and returns £118,000. **Only the projection
carries the error**, so a user comparing today against age 84 sees a plausible number
beside a wrong one, with no way to tell which.

That is also why the reconciliation was needed to find it: the parts sum to the
published total to the penny, because the wrong base is consistently wrong all the way
through.

### This is the ownership work reaching a fourth module

**A share applied on one side and dropped on the other** — the same principle the
ownership boundary work is fixing elsewhere. The aggregator applies it; the projection
does not. Not touched by `cycle2-projection` because that work is live and this belongs
with it rather than beside it.

### Repro

1. `david.jones@example.com` → `/estate/inheritance-tax`.
2. Property row, age-84 column: **£4,550,297**.
3. `SELECT ownership_type, ownership_percentage, current_value FROM properties WHERE id = 20;`
   → `tenants_in_common`, `40.00`, `295000.00`.
4. £1,393,000 × 1.03^36 = £4,037,302, not £4,550,297.

### Acceptance

1. The projection's property base is the household's **share**, not the full value,
   for every ownership type — and still counts a joint property exactly once.
2. Projected property equals the aggregator's current total compounded at the
   configured rate. The implied rate equals the configured rate.
3. `tenants_in_common` is property-only, per existing convention — do not widen it.
4. The projected estate, taper test and projected tax all move with it, and W-0135's
   two-screen agreement holds afterwards.
5. Verified on both persona logins with the table expanded.
