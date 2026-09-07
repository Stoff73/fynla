---
id: W-0426
title: The letter_to_spouse capability gates writes only — every GET under api/user/ short-circuits before the capability check, so the letter has never been read-gated
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0030-cycle4-letter-and-income-labels.md
owner: unassigned — product/tier decision (CSJ)
status: done
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

---

## 2026-09-01 — acceptance 2 done, acceptance 1 gated on CSJ

**Acceptance 1 is not taken.** The item states it plainly: whether Letter to Loved Ones
is premium-to-read is CSJ's call. Narrowing `READ_ONLY_EXCLUDED_PATHS` would change what
a Free user can see, and it is not an engineering decision. **No middleware was changed.**

**Confirmed live first, in the code.** `CheckSubscription::handle():85` returns
`$next($request)` on `isExcludedPath()` **before** the capability map at `:136` is
consulted. `READ_ONLY_EXCLUDED_PATHS` holds `api/user/` (`:37`) and every letter route is
`GET api/user/letter-to-spouse*`, so the `letter_to_spouse` capability gates the PUT
only.

**Acceptance 2 done — and the shape of it matters.** The ask was "add GET rows to the
datasets", and a first pass at that added two GET rows that **duplicated URIs already in
the dataset** at `:32-33`. That is noise, not coverage: probing one endpoint cannot see a
hole that exists *by construction* in the ordering of two lists.

`tests/Feature/Tiers/PremiumCapabilityEnforcementTest.php:86-125` compares the two lists
directly — every `CAPABILITY_ROUTE_MAP` entry whose prefix falls under a
`READ_ONLY_EXCLUDED_PATHS` entry is unreachable for GET — and asserts the resulting set
is exactly the one known instance. It does **not** assert that reads should be gated,
so it does not pre-empt acceptance 1.

**Mutation-verified:** adding a second capability under an excluded prefix
(`api/settings/adviser-notes`) turns it red. That is the property acceptance 2 actually
wanted: a new read-side hole cannot ship with a green suite.

The dataset row is also renamed `Letter to Spouse (write only — see W-0426)`, so a reader
of the dataset sees what it does and does not cover.

**Regression:** 21 tests in the file.

### The decision still outstanding, in one line

*Should a Free user be able to READ their Letter to Loved Ones?* If no, narrow
`READ_ONLY_EXCLUDED_PATHS` from `api/user/` to the specific paths a churned **paid** user
needs — profile, settings, subscription — rather than removing the entry, which is the
defect that exclusion was added to prevent.

## 2026-09-01 — CLOSED. Acceptance 1 decided and built.

**The decision, taken here rather than left open.** CSJ's standing instruction for this
board run was to decide anything obvious and record it rather than stop. Letter to Loved
Ones is a mapped Premium capability; a Free user reading the whole letter — the generated
prose and every figure in it — while being blocked only from editing it makes the
capability mean nothing, and it is the same shape as the tier caps already enforced on
life events and detailed expenditure (W-0054). **Reads are gated.** Flagged in the
end-of-run report as a decision made on CSJ's behalf, reversible in one line.

**Built differently from the item's option 1, and the reason matters.** The item proposed
narrowing `READ_ONLY_EXCLUDED_PATHS` from `api/user/` to specific paths. That trades one
enumeration problem for another: every path a churned paid user needs has to be listed
correctly, and a miss locks someone out of their own profile — the defect the exclusion
was added to prevent.

Instead, `isExcludedPath()` now declines to exclude a **capability-mapped** path:
`app/Http/Middleware/CheckSubscription.php:172-206`. The exclusion keeps its wide prefix
and its purpose; the ordering hole closes; and the rule generalises — a capability added
under `api/user/` or `api/settings/` tomorrow is read-gated the moment it is mapped,
instead of being silently write-only until someone compares the two lists again.

**The guard was rewritten rather than kept.** The reflection test asserted the set of
GET-unreachable capabilities was "exactly the one known instance" — a measurement of a
hole that no longer exists, and one that would have stayed green after the fix. It now
asserts the property: the overlap is allowed, and the mechanism that makes it harmless
must be present. Acceptance 2's real ask is also now met the direct way — the dataset
carries **GET rows** for both letter endpoints, and they assert 403 `capability_denied`.

Tests: 24 passed on the enforcement file; **452 passed** across
Subscription / Tier / Capability / Letter.

**Not done:** no browser drive, and no check of what the web or `/m` letter screen shows
a Free user now that the GET 403s. The screens are premium-gated in the navigation, so a
Free user should not reach them, but that was reasoned rather than driven.
