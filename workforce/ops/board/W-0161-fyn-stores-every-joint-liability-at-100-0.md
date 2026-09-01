---
id: W-0161
title: Fyn stored every joint liability at 100/0 — half the debt attributed to nobody
mission: M-0002-persona-fidelity
owner: build-lead
claimed_by: fix-batch-F
status: done
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
claimed: 2026-08-21T19:30:00Z
branch: branches/fixes/F-0007-batch-f-analytics-consent.md
severity: medium
surfaces: [web, m, ios]
source: found by fix-batch-F during the W-0040 mechanism census, 2026-08-21
prior_art_checked: 2026-08-21
prior_art_found: [app/Support/SharedOwnership.php, app/Agents/CoordinatingAgent.php, app/Services/Stores/LiabilityStore.php]
prior_art_outcome: extend
---

## Intent

`CoordinatingAgent::handleCreateLiability` built its own share:

```php
'ownership_percentage' => isset($input['ownership_percentage']) ? (float) $input['ownership_percentage'] : 100.0,
```

and handed the payload straight to `LiabilityStore::create`, which carries its own
default:

```php
$canonical['ownership_percentage'] ??= $canonical['ownership_type'] === 'joint' ? 50 : 100;
```

**`??=` cannot fire on a key that is already set.** So a joint liability created
through Fyn — the only way most users create one — was stored at **100/0**: the
whole debt on the primary owner, nothing on the co-owner. Every joint read is
`WHERE user_id = ? OR joint_owner_id = ?`, so the other party sees a liability they
are jointly responsible for at a share of zero, and the household total is wrong.

This is W-0014's defect (a joint asset stored at 100/0) surviving on a path the
W-0015 consolidation never swept, because that sweep covered the controller and
normaliser paths and this one is Fyn → Store.

**The store's own copy had a second gap:** it knew only `'joint'`, so a
`tenants_in_common` liability defaulted to **100** — the same shape as the chattel
gap W-0025 closed.

## Why it is separate from W-0040

W-0040 is the *mechanism* — whether a stated share can be told from an inherited
one. This is a **behaviour bug** that the mechanism census uncovered: a figure
stored wrong on a live path, independent of how the 100/0 question is answered.
It would be wrong to bury it inside another item's working notes.

## Acceptance

1. A joint or tenants-in-common liability created through Fyn stores 50 when no
   share is stated. **Done.**
2. The share comes from `SharedOwnership` rather than a copy. **Done.**
3. Existing rows: not repaired here. See below.

## Working notes

### 2026-08-21 — fix-batch-F — fixed as part of the W-0040 convergence

- `app/Agents/CoordinatingAgent.php:4070` — the literal `100.0` replaced by
  `SharedOwnership::primaryOwnerPercentage()`.
- `app/Services/Stores/LiabilityStore.php:43` — the `??=` copy replaced by
  `SharedOwnership::applyTo()`, which closes the `tenants_in_common` gap at the
  same time.

**Existing rows are NOT repaired.** Any joint liability Fyn created before this is
still stored at 100/0, and correcting it changes a user's net worth — the same
class of decision as W-0043 (the orphaned shared mortgage), which is explicitly
CSJ's call and not a silent migration. **Sweep these two together**, not
separately: one query, one decision, one migration that reports rows touched and
their before/after values per the W-0030 standard.

**Not verified by me:** no browser verification — a persona-tester closes Rule 14's
loop independently.

- 2026-08-31 build-lead: **VERIFIED ALREADY FIXED AND TESTED — closed.**

  `LiabilityStore::create():47` now hands the payload to `SharedOwnership::applyTo()` before validating, so a shared liability takes the 50 default instead of the 100 that left half the debt attributed to nobody. **Fyn writes through this store like every other caller**, which is why fixing it here fixed Fyn rather than requiring a separate fix on the agent — Rule 20 in the shape that actually works.

  **The comment at `:44-46` records something worth keeping:** the local copy that used to live here *"knew only 'joint'"*, so a **tenants-in-common** liability fell through to 100 even after the joint case was handled. That is the same gap W-0025 closed for chattels, and it is why the answer was to delete the copy rather than extend it — an ownership rule with a hand-maintained list of shared types gets one more type wrong every time a type is added.

  **Tested:** 47 liability tests pass, 152 assertions.

  Related, closed today: **W-0014** fixed the same 100% default on investment accounts, and **W-0015** consolidated the read side so no surface computes the share for itself.
