---
id: W-0346
title: A granted spouse permission cannot be withdrawn — the status enum has no revoked value
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0027-cycle4-life-cover-reach.md
owner: build-lead
status: done
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

---

## Verified 2026-09-01 — already answered by W-0347. THIS ITEM'S DESCRIPTION IS STALE.

**No code was changed. Every claim in the Intent above is now false**, and anyone
reading it as current fact would rewrite a channel that was deliberately rebuilt.

**Acceptance 1 — withdrawal exists.** `SpousePermissionController::revoke():424`,
`DELETE /api/spouse-permission/revoke`. Callable by **either** party, in either
direction, and deliberately not gated on `spouse_id` (`:428-434`) because it is also the
requester's "Cancel Request" button, and an unanswered invitation has no link yet.

**The enum does not need a fourth value, and that is a decision rather than an
omission.** `revoke()` writes `rejected` and the reason is at `:465-471`: a **deleted**
row left no record that anyone had decided anything, and `hasAcceptedSpousePermission()`
reads the absence of a row as "this link predates the consent flow" and honours it — so
deleting the row switched sharing **back on**. Marking it also keeps the audit trail,
which an FCA-regulated product should not lose. Acceptance 2 is satisfied by that
comment being at the line.

**Acceptance 1's third clause — the gate honours it.**
`User::hasAcceptedSpousePermission():961-963` returns `$permission->status ===
'accepted'` whenever a row exists. The Intent's warning that it "returns `true` for any
married reciprocal pair **without reading the row at all**" describes the pre-W-0347
code.

**And the trap this item would have walked into is already closed.** W-0347 F5: a couple
could hold a row in each direction and both `revoke()` and the gate took `first()` with
no order, so a withdrawal could land on one row while the other still said yes. Both
ends now `orderBy('id')` (`:452` and `:955`).

**Acceptance 3 — compliance routing is moot**, because the withdrawal mechanism it was
to rule on already exists and is audited.

### One thing genuinely still open, named rather than fixed

`hasAcceptedSpousePermission()` **fails open** when no row exists at all (`:978`), and
the comment at `:973-977` says so explicitly and says why it was left: inverting it
would cut off seeded and test data, and the re-ask migration
(`2026_08_24_130000_reask_spouse_permissions_nobody_granted.php`) closes it for existing
data by giving every reciprocal pair a row. Any **future** path that creates a
reciprocal link without a row silently grants consent. That is already documented at the
line by whoever built it; it is not this item's, and it is not a defect introduced here.
