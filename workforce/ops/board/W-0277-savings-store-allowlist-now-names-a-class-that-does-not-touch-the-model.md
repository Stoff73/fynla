---
id: W-0277
title: The SavingsStore boundary allowlist names a class that no longer touches the model
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: quality-lead
status: done
closed: 2026-08-29
severity: low
surfaces: [web, m, ios]
created: 2026-08-22T22:10:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0271]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

**W-0271** removed the last `SavingsAccount` reference from
`App\Services\Risk\AutoRiskCalculator` — it now reads cash through
`CrossModuleAssetAggregator`, which reads through `SavingsStore`. The class is
therefore a genuine store consumer and no longer needs a bypass exemption.

Two places still grant it one:

- `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php:146`
- `app/Services/Stores/SavingsStore.md`, "Out-of-sub-project-1-scope read / infra
  references"

Neither is failing — an unused allowlist entry is inert. It is filed because a stale
allowlist is documentation that disagrees with the code, and the next reader checks
the code against it (the W-0226 / W-0239 lesson).

**Deliberately not fixed in W-0271:** both are shared boundary config, and editing
them while parallel batches are running is a collision, not a fix.

## Acceptance

The entry is removed from both, with the boundary suite still green.

## Done 2026-08-25

### Premise verified before touching anything

`App\Services\Risk\AutoRiskCalculator` contains **zero** `SavingsAccount` or
`savings_accounts` references. It takes `CrossModuleAssetAggregator` by constructor
injection and reads cash through `calculateCashTotal()`, which goes through
`SavingsStore`. The item is right: it is an ordinary consumer holding a bypass it
does not use.

### Every other entry was checked too

Since a stale allowlist is the defect, the obvious question is whether more than one
entry is stale. Counted `SavingsAccount` references in all fifteen allowlisted
classes:

| Entry | Refs |
|---|---|
| EventServiceProvider | 4 |
| User | 1 |
| SavingsGoal | 1 |
| PlanController | 2 |
| RateComparator | 5 |
| LiquidityAnalyzer | 4 |
| PersonalAccountsService | 2 |
| UserProfileService | 2 |
| DocumentTypeDetector | 2 |
| SavingsAccountMapper | 3 |
| EvalHttpDriver | 2 |
| NetWorthService | 3 |
| DocumentProcessor | 6 |
| LifeEventAllocationService | 3 |
| **AutoRiskCalculator** | **0** |

**One stale entry, exactly as filed.** No wider cleanup is warranted, and the item's
scope was correct.

### Changes

Removed from both places named in the item, each replaced with a dated note saying
why rather than a silent deletion — the next reader should not have to guess whether
the class was overlooked or deliberately dropped:

- `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php:146`
- `app/Services/Stores/SavingsStore.md`

### Verified — the removal has teeth

Acceptance asks for the boundary suite to stay green, which it does: **22 passed**
across all seven store boundary tests.

Green alone would not prove anything, though — an allowlist entry for a class that
never touches the model is inert either way, so deleting it and deleting nothing
look identical. So the guard was made to fire: a `use App\Models\SavingsAccount;`
was inserted into `AutoRiskCalculator` and the suite run again.

    FAILED  SavingsStoreBoundaryTest
    Expecting 'App\Models\SavingsAccount' not to be used on
    'App\Services\Risk\AutoRiskCalculator'.
    at app\Services\Risk\AutoRiskCalculator.php:9

The probe was reverted and the file is byte-identical to the committed version;
`git diff` on it is empty. Suite re-run: 22 passed. `php -l` and `pint` clean.

**So the class is now genuinely guarded**, not merely unlisted — anyone reaching
for the model directly will be stopped.

### Gaps

- Nothing user-facing changed, so no browser verification applies.
- Two earlier attempts to seed the probe silently failed — one regex missed, one
  had its backslashes eaten by `sed`, and both produced a green run that would have
  been reported as proof had it not been checked. Recorded because "the test passed"
  after a probe that never landed is exactly the false confidence this item is about.

## Closed — 2026-08-29 (board reconciliation)

**Marked done from `dev` history, not from a fresh re-test.** Previous status was
`review`.

- **Delivered by:** Phailanx
- **Evidence:** merged in #718; commit `3583fd01c` on `dev`

The board had drifted: the work landed on `dev` but the item was never restamped. This
records the evidence rather than deleting the item, so the fix can be re-checked against
it later. **If a re-test finds this unfixed, reopen it — a `done` here means "the change
is on `dev`", not "someone has re-verified the behaviour since."**
