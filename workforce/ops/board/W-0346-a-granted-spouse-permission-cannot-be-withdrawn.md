---
id: W-0346
title: A granted spouse permission cannot be withdrawn — the status enum has no revoked value
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0027-cycle4-life-cover-reach.md
owner: build-lead
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T23:35:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0278, W-0345]
prior_art_outcome: none
constitution_refs: [05-perimeter, 03-hard-nos]
---

## Intent

Found while building the W-0278 fixture: the brief named "revoked" as one of four
spouse-link states, and **there is no such state to build.**

```
SHOW COLUMNS FROM spouse_permissions LIKE 'status'
-> enum('pending','accepted','rejected')  NOT NULL  default 'pending'
```

`rejected` is the answer to a request that was never granted. **Once `accepted` is
written there is no value that means "withdrawn"**, and `SpousePermission` exposes
`isAccepted()` and `isPending()` and nothing else.

So a user who has shared their financial position with a partner **has no mechanism to
stop**, short of unlinking the accounts entirely or deleting their own. Whether that is
acceptable is a data-protection question, not a modelling nicety: consent that cannot be
withdrawn is the part regulators look at.

Note the interaction, which is why this is not merely cosmetic:
`User::hasAcceptedSpousePermission()` returns `true` for any married reciprocal pair
**without reading the row at all** — so even after adding a `revoked` value, writing it
would change nothing until that automatic branch also consults it.

## Acceptance

1. Determine whether withdrawal is meant to exist. If it is, it needs an enum value, a
   write path, and `hasAcceptedSpousePermission()` honouring it.
2. If it is deliberately absent, the reason is recorded and the four-state vocabulary
   used in briefs stops naming a state the schema cannot hold.
3. Route through compliance-lead — consent withdrawal is perimeter, not engineering.

## Working notes

(append-only)

- 2026-08-22 — Measured on the local dev database: 10 `spouse_permissions` rows, all
  `accepted`. No row has ever held any other value there.
