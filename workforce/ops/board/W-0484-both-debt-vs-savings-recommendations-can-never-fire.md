---
id: W-0484
title: Both Debt vs Savings recommendations can never fire — the seeded condition strings match no dispatcher arm
mission: M-0002-persona-fidelity
owner: null
status: done
closed: 2026-08-29
severity: medium
surfaces: [web, m, ios]
created: 2026-08-25T14:10:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-25
prior_art_found: [W-0328 (found while investigating it; the finding voids one of that item's arguments)]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
source: found by Brett while costing W-0328, 2026-08-25
---

## Intent

**Two seeded, enabled recommendations with written user-facing copy can never fire.**
`SavingsActionDefinitionService::evaluate()` dispatches on
`trigger_config['condition']` through a `match` whose final arm is `default => []`.
Neither Debt vs Savings definition's seeded condition string matches any arm, so both
fall through and return no recommendation, silently.

| Definition | Seeded condition | Dispatcher arm | Match? |
|---|---|---|---|
| `debt_rate_exceeds_savings` | `debt_rate_exceeds_savings_rate` | `debt_rate_exceeds_savings` | **No** — differs by a `_rate` suffix |
| `offset_mortgage_better` | `mortgage_rate_exceeds_after_tax_savings_rate` | `mortgage_rate_comparison` | **No** — unrelated strings |

Seeder: `database/seeders/SavingsActionDefinitionSeeder.php:509` and `:528`.
Dispatcher: `app/Services/Savings/SavingsActionDefinitionService.php:177-178`.
Default arm: `:204`.

**Both are `is_enabled = 1` in the live database** — verified, not inferred:

```
debt_rate_exceeds_savings   "debt_rate_exceeds_savings_rate"                 1
offset_mortgage_better      "mortgage_rate_exceeds_after_tax_savings_rate"   1
```

**And `evaluateMortgageRateComparison()` is unreachable.** The string
`mortgage_rate_comparison` appears exactly once in the repository — at the dispatcher
arm that calls it. No seeder produces it, so the method has no reachable caller. It is
the mirror image of the dead code in W-0146 and W-0343: there, a method nothing called;
here, a method nothing *can* call, sitting behind a live-looking arm.

Checked and ruled out: the second `match` in this service
(`evaluateGoalTrigger():3155`) handles goal-sourced triggers only and contains neither
condition, and no normalisation maps one string to the other.

## Why it matters

The copy is written, approved-looking and completely invisible:

> **"Consider an Offset Mortgage Arrangement"** — *"Your mortgage rate of
> {mortgage_rate}% exceeds your after-tax savings rate — consider an offset mortgage
> arrangement."* → *"Speak to your mortgage provider about offset options."*

A user paying more on debt than they earn on savings is told nothing. That is the
whole point of the Debt vs Savings category, and the category has two members.

**It is invisible from every direction.** The seeder looks right. The service looks
right. The database row says enabled. Nothing errors, nothing logs, and the user simply
never sees a recommendation they would have no way of knowing to expect. A `match` whose
default is `[]` cannot report that it matched nothing.

**It already misled an investigation.** W-0328's brief to CSJ argued that "Fynla already
advises the product it cannot record", citing the seeded offset recommendation as the
strongest reason to support offset mortgages. That argument is void — the
recommendation never reaches anyone. The brief was written from the definition rather
than the execution path.

## Acceptance

1. Both conditions dispatch. Fix the mismatch at whichever end is wrong — the seeded
   string or the arm — and say which was chosen and why.
2. `evaluateMortgageRateComparison()` is reachable, or deleted. **Determine which
   before deleting** (W-0343's lesson): if it is the intended evaluator for
   `offset_mortgage_better`, the seeder is the wrong end and the method is fine.
3. **A guard that makes this class of defect impossible to reintroduce.** Every seeded
   `trigger_config['condition']` must resolve to a dispatcher arm. A test that
   enumerates the seeded definitions and asserts each condition is handled would have
   caught both of these, and will catch the next one — the categories are large
   (Emergency Fund, PSA, Rate Optimisation, FSCS, Children, Spouse Coordination) and
   this is the failure mode they all share.
4. **Sweep the other categories with that guard before assuming these two are the only
   ones.** They were found by accident while costing an unrelated item; nothing
   suggests they are special.
5. Verify a real user with qualifying data now receives each recommendation — the
   dispatch fix is necessary, not sufficient, because the evaluators themselves have
   never run.

## Working notes

- 2026-08-25 Brett: found while costing W-0328. Not fixed in that item's branch —
  it is a separate defect in a different module, and acceptance 3 and 4 make it larger
  than a two-string correction. Raised so the two-string version is not mistaken for
  the whole job.

## Closed — 2026-08-29 (board reconciliation)

**Marked done from `dev` history, not from a fresh re-test.** Previous status was
`queued`.

- **Delivered by:** Stoff73
- **Evidence:** merged in #734,#736; commit `71c858cfe` on `dev`

The board had drifted: the work landed on `dev` but the item was never restamped. This
records the evidence rather than deleting the item, so the fix can be re-checked against
it later. **If a re-test finds this unfixed, reopen it — a `done` here means "the change
is on `dev`", not "someone has re-verified the behaviour since."**
