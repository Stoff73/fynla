---
id: W-0479
title: The dashboard's protection gap count reads a shape ProtectionAgent does not produce, so every household shows zero gaps
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [quality-lead]
status: queued
claimed_by: null
severity: high
surfaces: [m, native, web]
created: 2026-08-24T14:05:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-24
prior_art_found: [W-0473, W-0478, W-0471]
prior_art_outcome: extend
source: found while consolidating the two insight composers for W-0478 — a test fixture asserted a gaps shape the live agent has never emitted
---

## Intent

`MobileDashboardAggregator::extractModuleSummary()` counts protection gaps like this
(`app/Services/Mobile/MobileDashboardAggregator.php:195-198`):

```php
foreach ($gaps as $gapType => $gapData) {
    if (is_array($gapData) && ($gapData['gap'] ?? 0) > 0) {
        $criticalGaps++;
```

It expects `gaps` to be a map of category => `['gap' => n]`. **`ProtectionAgent` has
never emitted that.** Measured on user 14:

```
agent gaps keys: total_need, total_coverage, total_coverage_used, total_gap,
                 gaps_by_category, coverage_allocated, income_replacement_coverage,
                 coverage_percentage
```

No member of that structure carries a `gap` key, so **`$criticalGaps` is 0 for every
household, always.** Measured on the same user, who has a £21,000 income protection
shortfall:

```
protection card: {"status":"active","total_coverage":700000,"policy_count":2,
                  "critical_gaps":0,"has_income_protection":false}
```

`critical_gaps` is published in the dashboard payload, which is served to **`/m`, native
iOS and the web `GamifiedDashboard`** from one endpoint. Whatever each renders from it
shows no gaps for a household that has one.

**This is the phantom-read family again** (W-0471, W-0473): a read whose miss is silent
and indistinguishable from "this household is fully covered". The safe-looking
`?? 0` is what makes it invisible.

## Why it survived

`MobileDashboardAggregatorTest` mocked the agent with the **invented** shape
`['life' => ['gap' => 50000], 'income_protection' => ['gap' => 20000]]`, so the test
proved the reader against data no agent produces. **The fixture and the reader agreed
with each other and neither agreed with the agent.** Corrected in that one test as part
of W-0478; the reader itself is untouched and is what this item is for.

## Acceptance

1. `critical_gaps` counts what `ProtectionAgent` actually publishes — the non-zero
   entries of `gaps.gaps_by_category`, with `total_gap` considered, noting the two
   disagree by design (a household can have `total_gap: 0` and a real category
   shortfall).
2. Before/after on a household with a known income protection gap: the card shows it.
3. Check what each of the three surfaces renders from `critical_gaps` before changing
   it — a count that has always been 0 may have a template built around never firing.
4. A test built from the LIVE agent's shape, not a hand-written fixture.

## Working notes

- 2026-08-24 — Filed from W-0478. Not fixed there: that item's business was
  consolidating two insight composers, and this is a different published figure on
  three surfaces. The insight sentence about protection gaps is fixed (it now reads
  `gaps_by_category`); the CARD's count is not.
