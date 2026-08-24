---
id: W-0479
title: The dashboard's protection gap count reads a shape ProtectionAgent does not produce, so every household shows zero gaps
mission: persona-run-peak_earners-2026-08-20
branch: estate-copy-and-m-handoff
owner: main-inference
reviewers: [quality-lead]
status: gated
claimed_by: null
severity: critical
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

## Resolution — 2026-08-24

**Severity raised to CRITICAL on measurement.** The count is not only a card figure:
`MobileDashboardController:219` passes it into
`MilestoneDetectionService::detectProtectionAdequate()`, which awards a milestone
reading **"Your protection now covers what your family would need."** whenever
`policyCount >= 1 && criticalGaps === 0`. With the count permanently 0, **any household
holding a single policy was told its protection was adequate.**

**Three such milestones exist on the development database** —
users 16, 17 and 14, awarded 21–23 August. **User 14 holds a £21,000 income protection
shortfall.** The rows are NOT deleted here: like the forged consent rows in W-0347,
what happens to a false reassurance already given is a CSJ decision, and the same
question needs asking of production, which has never been measured.

### There were THREE readers, not two

| Reader | Counted | Emitted by the analyzer? |
|---|---|---|
| `MobileDashboardAggregator:195` (`/m`, native) | `$gap['gap']` | **no** |
| `DashboardAggregator:277` (web) | `$gap['shortfall']` | **no** |
| `ComprehensiveProtectionPlanService:144` | `total_gap` + `gaps_by_category` | yes |

Two consumers had invented **two different keys** for the same missing thing, and a
third read the real shape correctly one directory away. Rule 20 in miniature.

### The fix publishes the number instead of counting it three times

`CoverageGapAnalyzer` now emits `critical_gap_count` beside the categories it already
publishes, and both dashboards read it. **The producer answers the question**, which
ends the whole family: a consumer cannot guess a shape it is handed.

**The counting rule, and why it is not "count the non-zero categories":**
`disability_coverage_gap` and `sickness_illness_gap` carry the SAME shortfall as
`income_protection_gap` when there is no separate cover — the analyzer's own comment at
`:256` says "IP is primary; disability and sickness are supplementary". Counting all
three turns one uncovered income into "3 critical gaps". They are excluded. A
`total_gap` above zero with no category above zero still counts as one, so the fix
cannot reintroduce a silent under-count.

### Before / after, user 14 (£21,000 income protection shortfall)

| | before | after |
|---|---|---|
| `/m` + native card `critical_gaps` | **0** | **1** |
| web card `critical_gaps` | **0** | **1** |
| `detectProtectionAdequate` | awards "your protection now covers…" | does not fire |

Not 3 — the supplementary duplicates are correctly excluded.

### Tests

`CoverageGapAnalyzerTest` — three cases from the analyzer's own output: one distinct
shortfall counted once, three genuinely separate shortfalls counted three times, and a
covered household counted zero. `MobileDashboardAggregatorTest` — a payload in the real
shape, asserting the card publishes the analyzer's number rather than deriving one.
**Both mutation-checked**: restoring the old derivation reds the dashboard guard,
counting the supplementary categories reds two analyzer cases.

**416 tests / 1,381 assertions green** across protection, mobile, dashboard and
integration. Pint clean.

### Still open

- **The three awarded milestones.** A decision, not a fix — and production has not been
  measured. Same shape as W-0347's forged rows.
- The web protection card publishes `adequacy_score: 100` for this same household.
  Noted, not touched: it is a different figure, and Rule 12 has something to say about
  it being on screen at all.
