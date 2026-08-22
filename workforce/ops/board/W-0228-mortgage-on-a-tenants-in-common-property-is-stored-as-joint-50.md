---
id: W-0228
title: A mortgage secured on a tenants-in-common property is stored as joint 50% instead of matching the property's 40% share — overstating the owner's debt by £12,000
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: queued
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T18:40:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0216, W-0226, W-0203, W-0187, W-0015]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Found by: coordinator DB reconciliation during persona run `peak_earners`, cycle 4
pre-pass, local `laravel` database. Accounts **David Jones (16)** / **Sarah Jones (17)**.

**A debt secured on an asset must be shared on the same basis as the asset.**

### Observed

The Manchester property and the mortgage secured on it declare two different
ownership bases:

```
properties 20  Victoria Mill    £295,000  tenants_in_common  40%  joint_owner_id NULL
mortgages  16  (on property 20) £120,000  joint              50%  joint_owner_id NULL
```

The property is correctly `tenants_in_common` at **40%** — the persona's Manchester
unit is co-owned with **Mike Barrett**, an off-platform third party. The mortgage
secured on that same property is stored as `joint` at **50%**.

### Consequence

| Figure | Shown | Correct | Error |
|---|---|---|---|
| David's share of mortgage 16 | £60,000 | £48,000 | **£12,000** |
| Household debt | £305,000 | £293,000 | **£12,000** |

**This has already been signed off wrong.** `F-0021` measured household debt at
**£305,000** and recorded it as correct. Any cycle-3 conclusion resting on that
figure — protection debt need, net worth, estate liabilities — inherits the error.

### Not a defect, do not "fix" it

`joint_owner_id = NULL` on **both** rows is expected here, not a bug. The co-owner is
off-platform, and off-platform co-owners are first-class in this codebase (there is a
`joint_owner_name` column; see W-0025's `prior_art_found`). Do not conflate the NULL
owner id with the ownership-type mismatch. **Only the `ownership_type` /
`ownership_percentage` pair on the mortgage is wrong.**

### Expected

A mortgage's ownership basis derives from, or is validated against, the property it is
secured on. `tenants_in_common 40%` on the property means `tenants_in_common 40%` on
its mortgage. Where they disagree, the property is authoritative.

### Scope note

Same family as **W-0216** (property projection counts a tenants-in-common share at
100%) and **W-0226** (liabilities breakdown ignores the ownership share). Check
whether one shared reader fixes all three before writing a fourth copy of the rule —
Rule 20 applies: if more than one mechanism computes a debt share, consolidating them
is part of the fix, not a follow-up.

### Verification

Not browser-verified — raised from the database. The fix must be confirmed on web
**and** `/m`, on **both** accounts, per Rule 19.
