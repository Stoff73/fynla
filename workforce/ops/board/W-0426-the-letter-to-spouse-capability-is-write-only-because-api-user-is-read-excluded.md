---
id: W-0426
title: The letter_to_spouse capability gates writes only — every GET under api/user/ short-circuits before the capability check, so the letter has never been read-gated
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0030-cycle4-letter-and-income-labels.md
owner: unassigned — product/tier decision (CSJ)
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-23T03:20:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0421]
prior_art_outcome: none
constitution_refs: [05-perimeter, 06-commercials]
---

## Intent

Found while proving the **new** `GET /api/user/letter-to-spouse/financial-position`
(W-0421) is behind the same entitlement as the letter it belongs to. It is — and that
turns out to be less than it sounds.

`CheckSubscription::CAPABILITY_ROUTE_MAP` maps `api/user/letter-to-spouse` to the
`letter_to_spouse` capability, so the letter reads as premium-gated. But
`isExcludedPath()` runs **before** `checkCapability()`, and
`READ_ONLY_EXCLUDED_PATHS` contains **`api/user/`**:

```php
// Read-only excluded: only safe methods (GET, HEAD, OPTIONS)
if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
    foreach (self::READ_ONLY_EXCLUDED_PATHS as $excluded) {   // 'api/user/'
        if (str_starts_with($path, $excluded)) {
            return true;                                       // ← never reaches the capability
        }
    }
}
```

| Request | Gated? |
|---|---|
| `PUT /api/user/letter-to-spouse` | **yes** — this is what the green test asserts |
| `GET /api/user/letter-to-spouse` | **no** |
| `GET /api/user/letter-to-spouse/financial-position` | **no** |

**So the capability is write-only in practice**, and a Free user can read the whole
letter — including the generated prose carrying every figure — while being blocked from
editing it. The exclusion exists for a good reason (a churned user must still reach their
profile and the subscription tab), and `api/user/` is a very wide prefix to hang that on.

**This is why `PremiumCapabilityEnforcementTest` never caught it: every entry in its
dataset is a POST, PUT or a GET on a path outside `api/user/`.** The one letter entry is
a `PUT`. The dataset's shape and the exclusion's shape are complementary, so the gap sits
exactly where neither looks.

## Not a W-0421 regression

The new endpoint exposes **no data class the existing letter GET did not already return**
— the letter's own `real_estate_info` / `liabilities_info` prose carries the same figures.
It is consistent with the endpoint it belongs to, and a test pins that it can never become
*more* permissive than the letter:
`tests/Feature/Tiers/PremiumCapabilityEnforcementTest.php` — *"does not let the letter
financial position outrun the letter itself for a Free user"*.

That case deliberately asserts **parity with the letter**, not a flat 403, because
asserting a 403 would assert a behaviour the application does not have.

## Acceptance — needs a decision before it needs code

**Whether Letter to Loved Ones is premium-to-read is CSJ's call, not an engineering one.**
Two things follow whichever way it goes:

1. If reads should be gated: narrow `READ_ONLY_EXCLUDED_PATHS` from `api/user/` to the
   specific paths a churned user needs (profile, settings, subscription), rather than
   removing the entry — a churned PAID user losing their own profile is the defect that
   exclusion was added to prevent.
2. Either way, **add GET rows to `PremiumCapabilityEnforcementTest`'s datasets.** A
   dataset of writes cannot see a read-side hole, and this one has been open long enough
   for a test named after capability enforcement to have gone green over it throughout.
