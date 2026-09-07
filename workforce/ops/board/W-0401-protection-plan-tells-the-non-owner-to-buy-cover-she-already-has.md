---
id: W-0401
title: The coordination plan tells the non-owning spouse to buy debt cover she already has, because a fourth caller still reads the user_id relation
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0028-cycle4-m-protection-gap-reach.md
owner: build-lead (fix-cycle4-mprotection)
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-23T03:40:00Z
claimed: 2026-08-23T03:45:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0186, W-0341, W-0342, W-0384]
prior_art_outcome: route
constitution_refs: [05-perimeter, 07-quality-bar]
---

## Intent

**Found by enumerating every mechanism while fixing W-0384**, as Rule 20 requires — not
predicted, and then measured before being raised.

`app/Services/Coordination/PlanSources/ProtectionStrategySource.php:68` is the **fourth**
caller of `CoverageGapAnalyzer::calculateTotalCoverage()`, and the second one still
passing the plain `user_id` relation:

```php
$coverage = $this->gapAnalyzer->calculateTotalCoverage(
    $user->lifeInsurancePolicies,     // ← the same relation W-0186 and W-0384 were about
    ...
);
```

### The census, complete

| Caller | life-policies argument | State |
|---|---|---|
| `ProtectionAgent:111` (`analyze`) | `LifeCoverReach::policiesCovering()` | correct (W-0186) |
| `ProtectionAgent:407` (`buildScenarios`) | `LifeCoverReach::policiesCovering()` | correct (W-0186) |
| `ProtectionGapPresentationService:32` | raw relation | **fixed in F-0028 (W-0384)** |
| **`ProtectionStrategySource:68`** | **raw relation** | **this item** |

### Measured, not inferred

`php artisan tinker`, live `peak_earners` persona, both accounts:

```
ProtectionStrategySource->recommendations(Sarah, 17) = 2
   * "Add decreasing term cover for debts"      <- phantom
   * "Add income protection insurance"
ProtectionStrategySource->recommendations(David, 16) = 1
   * "Add income protection insurance"
```

Against the **already-routed** path into the *same* `RecommendationEngine`, for the same
user at the same moment:

```
ProtectionAgent::analyze(17)  ->  gaps.total_coverage       = 500000
                                  gaps.debt_protection_gap  = 0
```

**One recommendation engine, two input paths, opposite advice.** Sarah is £500,000
covered by the joint-life policy on David's account and her debt-protection gap is zero,
so "add decreasing term cover for debts" is a recommendation to buy a product she already
holds — surfaced in the coordination plan, which is an advice surface, not a summary card.

### Why it survived

**The same reason W-0384 did: it is invisible from the owner's account.** David owns the
policy, so the relation finds it and his recommendation set is correct. The defect exists
only on the account that does not hold the contract.

> **For any shared record, the non-owning side is the untested side.**

## Acceptance

1. `ProtectionStrategySource:68` passes `LifeCoverReach::policiesCovering($user)` in place
   of `$user->lifeInsurancePolicies`. **Route to the existing reader — do not build a
   second.** Critical illness stays the plain relation: `critical_illness_policies` has no
   `joint_life`, no `joint_owner_id` and no ownership columns (verified by `SHOW COLUMNS`
   in F-0028, not inherited from a note).
2. `recommendations(Sarah)` no longer contains the debt-cover recommendation, and
   **`recommendations(David)` is unchanged at exactly one** — assert it, do not assume it.
3. **An asymmetric fixture including the non-owning side.** The non-owner must hold a
   policy of her own, or the correct answer and the buggy answer are the same number and
   the test cannot discriminate (`tests/CLAUDE.md` §4, Collision).
4. `ProtectionStrategySource::recommendations()` swallows every `Throwable` and returns
   `[]`. **Confirm the routed call cannot silently empty the plan** — a fix that throws
   here removes all protection recommendations and looks like "no gaps".

## Notes

**The deeper item, deliberately not folded in here.** `calculateTotalCoverage()` accepts
any `Collection`, so nothing stops the *next* caller passing the unreached relation —
which is what W-0384 and this item both are. Making that unrepresentable is a signature
change across four call sites and is its own work item with its own prior-art record. So
is the duplication one layer up: `ProtectionAgent::analyze()` and
`ProtectionGapPresentationService::forUser()` answer the same question for different
surfaces, which is why this defect has now appeared three times.

---

## Outcome — 2026-08-23, build-lead (`fix-cycle4-mprotection`)

**FIXED.** Scope granted mid-batch by team-lead; `ProtectionStrategySource:68` routed to
`LifeCoverReach::policiesCovering()`.

```
BEFORE  recommendations(Sarah, 17) = 2   ["Add decreasing term cover for debts", "Add income protection insurance"]
AFTER   recommendations(Sarah, 17) = 1   ["Add income protection insurance"]
        recommendations(David, 16) = 1   unchanged, before and after
```

**Verified at the consuming surface**, signed in as Sarah on `/m` with its own token:
`RecommendationsAggregatorService::getRecommendationsByModule(17, 'protection')` returns one
recommendation — *"Add income protection insurance — £72,000.00 per year"* — and no
debt-cover entry. David returns one, *"…£87,000.00 per year"*, unchanged.

**Criterion 1 — met**, and critical illness left on the plain relation with the schema
reason stated in the code.
**Criterion 2 — met**, David asserted rather than assumed, and pinned by the control case.
**Criterion 3 — met.** Sarah's own single-life policy is **£120,000 against a £150,000 debt
need**, so the bug and the fix produce different recommendation sets. **M4 (bug restored):
non-owner case red, owner control green, three pre-existing cases green.**
**Criterion 4 — met, and it shaped the test.** `recommendations()` swallows every
`Throwable` and returns `[]`, so a throw would empty the plan and **pass a naive absence
assertion**. The case also asserts the list is not empty and still contains her genuine
income-protection recommendation.

`tests/Unit/Services/Coordination/ComposedProtectionPlanTest.php` +2 cases — **5 passed,
12 assertions**. Pint clean; the new `use` import survived the formatter (verified).

Branch doc: `workforce/branches/fixes/F-0028-cycle4-m-protection-gap-reach.md` §11

- 2026-08-31 build-lead: **CLOSED — verified against `dev`.**
  `ProtectionStrategySource:76` records that this fourth caller of
  `CoverageGapAnalyzer::calculateTotalCoverage()` no longer passes the plain `user_id` relation
  and no longer reports `debt_protection_gap = 0` for a non-owning spouse. Pinned by
  `tests/Unit/Services/Coordination/ComposedProtectionPlanTest.php` — *"the plan recommended cover
  the non-owner already holds"*. The coordination plan stops telling her to buy what she has.
