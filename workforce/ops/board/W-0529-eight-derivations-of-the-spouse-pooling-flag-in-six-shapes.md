---
id: W-0529
title: Eight derivations of the spouse data-pooling flag, in six shapes, so Fyn could quote a different estate figure from the screen
mission: null
branch: fix/w-0529-one-rule-for-pooling-a-spouse-estate
owner: build-lead
reviewers: [tax-compliance-reviewer]
status: done
claimed_by: null
severity: high
surfaces: [web, m, ios]
created: 2026-08-29T20:40:00Z
claimed: 2026-08-29T20:40:00Z
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-29
prior_art_found: [W-0350, W-0347, W-0154]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
source: raised out of W-0350 and decided by CSJ, 2026-08-29 — "Yes it should"
---

## Intent

**CSJ, 2026-08-29**, asked whether `EstateAgent` should derive `$dataSharingEnabled` from
the spouse permission the way `IHTController` does: ***"Yes it should."***

`EstateAgent` derived it as `$spouse !== null` — the link's existence, with no consent —
so **Fyn pooled an estate the screen would not have pooled, and could quote a different
inheritance tax figure from the one the user was looking at.**

Following it out found the flag derived in **eight places, in six shapes**:

| Shape | Sites | Wrong how |
|---|---|---|
| `$spouse !== null` | `EstateAgent` ×2 | no consent at all — the defect CSJ ruled on |
| `hasAcceptedSpousePermission()` alone | `HouseholdPlanningService` ×3 | consent with no check a spouse exists to give it |
| `$hasLinkedSpouse && permission` | `IHTController`, `EstatePlanService` | correct, written out again |
| `$spouse && permission` | `TrustController`, `ComprehensiveEstatePlanService` | correct, written out again |
| `$spouse !== null && permission` | `EstateActionDefinitionService` | correct, written out again |

## Acceptance

- [x] One derivation: `User::sharesFinancialDataWithSpouse()`, beside the reciprocity
      rule it composes.
- [x] All eight sites route through it; none derives the flag itself.
- [x] Reciprocity folded in — pooling reads the other account's records, so it needs both
      a link they made too and their consent.
- [x] Callers still resolve and pass `$spouse` when pooling is off, so a married couple
      never reports as single (the W-0154 near-miss).
- [x] An architecture test fails if a ninth derivation appears.
- [x] The verified `peak_earners` figures are unmoved.

## Working notes

(append-only)

- 2026-08-29 build-lead: **I gave CSJ a wrong number on the way here, and it argued the
  wrong way.** Advising on W-0350 I said *"8 of the 12 reciprocally linked accounts have
  no accepted permission row"*, and cautioned that requiring consent would take the spouse
  panel from two-thirds of real couples. Measured properly:

  | | count |
  |---|---|
  | reciprocal links | 12 |
  | **accepted** row | 4 |
  | **no row at all** | **0** |
  | explicit **`pending`** row | **8** |

  The 8 are invitations **asked and unanswered**, not couples who consented and would be
  cut off. So requiring consent is the gate working, and my caution pointed away from the
  right answer. CSJ decided correctly against it.

  The imprecision came from reading `!hasAcceptedSpousePermission()` as "no row" without
  checking which branch returned false.

- 2026-08-29 build-lead: **`hasAcceptedSpousePermission()` FAILS OPEN on a missing row**,
  deliberately (W-0347 G9): a reciprocal link predating the consent flow is honoured,
  because since W-0347 a reciprocal link cannot be created without an acceptance. **Zero
  such rows exist on the development database**, so nothing rests on it today. Asserted
  explicitly in the unit test so the default is visible rather than incidental — it is the
  branch a future write path could walk through, in the method whose whole job is the gate.

- 2026-08-29 build-lead: **terminology, not behaviour.** `hasAcceptedSpousePermission()`'s
  docblock discusses "withdrawal", while `spouse_permissions.status` is
  `enum('pending','accepted','rejected')`. Recorded because a test written from the
  docblock fails on a truncated-column error that reads like a code fault.

- 2026-08-29 build-lead: **`$hasLinkedSpouse` orphaned and caught.** Collapsing
  `EstatePlanService`'s derivation left the variable referenced at `:610`, where PHP would
  have evaluated it as false and silently flipped `has_linked_spouse` off for every
  household. `php -l` does not catch an undefined variable. Replaced with `$spouse !== null`,
  which is what the conjunction always meant.

- 2026-08-29 build-lead: **verification.** 1,133 passed across Feature Estate, Unit Estate,
  Unit Agents, Unit Coordination, Unit Plans, Architecture and Unit Models. The
  architecture test is mutation-verified — reverting `EstateAgent` and
  `HouseholdPlanningService` makes it name all five offending lines. **The verified
  `peak_earners` figures are unmoved at £343,512**, because that household holds an
  accepted row.

- 2026-08-29 build-lead: **`Unit/Models` is not bound in `tests/Pest.php`.** A factory call
  there dies with "A facade root has not been set" — a test that cannot run rather than one
  that fails. Bound in the file rather than widening the global binding, which would hand
  `RefreshDatabase` to every other file in that directory.

- 2026-08-29 build-lead: **open question, not taken here.** W-0350 lifted the Tier 1
  financial READS to reciprocity only, on my mistaken reading of the permission population.
  Now that the 8 are known to be `pending` rather than row-less, whether those reads should
  also require consent is worth re-asking — it is the same class of data this item just
  gated on consent.

- 2026-08-30 build-lead: **merged to `dev` as PR #755** — one derivation of the spouse pooling flag. Left `gated` rather than
  `done` because the reviewer gate named above has not run; `done` here would mean the
  change is on `dev`, which is true, and would hide that nobody has certified it.

- 2026-08-31 build-lead: **CLOSED — merged as PR #755, verified against `dev`.**
  `User::sharesFinancialDataWithSpouse()` (app/Models/User.php:587) is the single
  derivation, with an architecture test that fails if a ninth appears.
