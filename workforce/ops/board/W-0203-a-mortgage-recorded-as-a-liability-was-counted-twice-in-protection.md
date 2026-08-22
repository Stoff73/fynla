---
id: W-0203
title: A mortgage recorded in the liabilities table was counted twice in the protection debt need — once as a liability and again from the mortgages table
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0019-cycle2-ownership-applied-one-side-only.md
owner: build-lead
status: handoff
severity: high
surfaces: [web, m]
created: 2026-08-22T03:40:00Z
claimed: 2026-08-22T03:40:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: [W-0187]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by `cycle2-ownership` while fixing **W-0187**, and **recorded as its own defect
rather than as a side effect of that fix**, on team-lead's instruction — because it is
a separate wrong number with a separate cause, and it was fixed in the same change.

**It is invisible on the peak_earners persona**, which holds zero `liabilities` rows.
It was found by reading the two code paths against each other, not by testing them.

### The defect

There are two ways to record a mortgage:

1. A `mortgages` row, linked to a property. What the property forms create.
2. A `liabilities` row with `liability_type = 'mortgage'`. What the liabilities form
   and Fyn's liability capture create.

The profile has always presented them together —
`UserProfileService::calculateLiabilitiesSummary()` sums both into
`liabilities_summary.mortgages`, which is correct.

**Protection counted the second kind twice:**

```php
// CoverageGapAnalyzer::calculateDebtProtectionNeed()  (pre-fix)
$totalMortgageDebt = (float) $user->mortgages()->sum('outstanding_balance');
$totalOtherDebt    = (float) $user->liabilities()->sum('current_balance');   // <- ALL of them
return $totalMortgageDebt + $totalOtherDebt;
```

`$user->liabilities()` is unfiltered, so a `liability_type = 'mortgage'` row was summed
into "other debt" **and** the mortgages table was summed beside it. `ProtectionAgent::analyze()`
(`:147-148`, pre-fix) had the identical pair.

**Anyone who recorded their mortgage as a liability has been shown roughly twice their
debt in the protection module** — and debt protection need is a figure the module
converts directly into "you should buy this much cover".

### Impact

Direction matters: this **overstates** the need, so it manufactures a shortfall and
sells cover the user does not need. It compounds with W-0187 — that charged the whole
of every shared debt, this counted one kind of debt twice — and the two are
multiplicative on a household that recorded a joint mortgage in the liabilities table.

### Repro (from the code; no persona data exercises it)

1. A user with a `liabilities` row of `liability_type = 'mortgage'`, balance £200,000,
   and no `mortgages` row.
2. Pre-fix: `calculateDebtProtectionNeed` returns £200,000 from "other debt" and
   `ProtectionAgent`'s `debt_breakdown` shows `mortgage: 0, other: 200000`.
3. Add the same borrowing as a `mortgages` row as well — the shape the property form
   produces — and the total becomes £400,000 for one £200,000 debt.

### Fix

Landed with W-0187. `CrossModuleAssetAggregator::calculateLiabilityTotals()`
(`app/Services/Shared/CrossModuleAssetAggregator.php:380-419`) is the one home for
"what does this user owe", and it classifies deliberately:

- **mortgage-type liability rows count with the mortgages, once** — never as "other".
- non-mortgage liabilities are "other".
- both at the user's share.

The docblock states the rule where it is enforced: *"Mortgage-type liability rows count
as mortgages, not as 'other'. They are a second way to record the same debt and the
profile has always presented them alongside the mortgages table. Summing them into
'other' would have counted the same borrowing twice."*

### Acceptance

1. A mortgage recorded as a liability appears once in the protection debt need. **Pinned:**
   `tests/Feature/Protection/ProtectionDebtUsesUserShareTest.php` — *"it counts a mortgage
   recorded as a liability with the mortgages, not twice"* — a £100,000 joint mortgage-type
   liability yields `mortgages: 50000, other: 0, total: 50000`.
2. Protection and the profile classify it the same way. **Pinned:** *"it shows the same
   debt figure to protection and to the profile"*.
3. Verified in a browser with a mortgage recorded through the liabilities form, on both
   accounts — **NOT done, by instruction. This is the one acceptance criterion with no
   persona coverage**, because the household holds no liability rows. It needs a
   deliberately constructed case.
