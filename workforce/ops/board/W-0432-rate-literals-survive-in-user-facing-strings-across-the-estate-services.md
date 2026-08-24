---
id: W-0432
title: Rate literals survive in user-facing strings across the estate services — including a class that computes the same threshold from configuration two hundred lines above
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0032-cycle4-rate-literals-and-the-charitable-denominator.md
owner: build-lead
reviewers: [tax-compliance-reviewer, quality-lead]
status: gated
claimed_by: build-lead
severity: high
surfaces: [web, m]
created: 2026-08-23T03:15:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0431, W-0132, W-0399]
source_addition: tax-compliance-reviewer verdict 2026-08-23, condition C4 — four instances I missed, two of them not literals-in-prose at all
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

**Raised from W-0431's own "not swept beyond this method" note, then actually
swept.** `TaxConfigService`'s docblock names `WillAnalysisService`,
`GiftingStrategy` and `EstateAgent` as former duplicate readers of the charitable
rate, so they were the obvious place to look. They are worse than expected.

W-0431 fixed the three rate messages in `IHTCalculationService`. **The same
defect is live in at least three other places**, and the sharpest instance is in a
class that already knows better.

### 1. `WillAnalysisService` — the figure from configuration, the label a literal, in one class

`:55` computes the threshold correctly:

```php
$threshold = $baseline * $this->taxConfig->getCharitableThresholdPercent();
```

`:351-353` then describes that very threshold with a hardcoded literal:

```php
'above' => 'Your charitable bequests exceed the 10% threshold by £'.number_format($excess).'…'
'at'    => 'Your charitable bequests meet the 10% threshold of £'.number_format($threshold).'…'
'below' => '…£'.number_format($shortfall).' below the 10% threshold of £'.number_format($threshold).'…'
```

**The number in the sentence and the number in the calculation are the same
quantity, read two different ways, three hundred lines apart in one file.** Change
`charity_threshold_percent` in the admin Tax Settings screen — which
`getCharitableThresholdPercent()`'s docblock says was added *because that control
was inert* — and `£{$threshold}` moves while the words "10% threshold" do not.

**Second finding in the same method, `:348`:**

```php
$reducedRatePercent = round(((float) ($ihtConfig['reduced_rate_charity'] ?? 0.36)) * 100);
```

A raw array read with its own `?? 0.36` fallback, where
`getCharitableReducedRate()` exists and is the one home.
`TaxConfigService`'s docblock records that this exact duplication —
*"`?? 0.36` … duplicated across WillAnalysisService (×2), IHTCalculationService,
EstateAgent, GiftingStrategy and TaxSettingsController"* — was consolidated. **One
survivor is still here.**

**And that is worse than an ordinary duplicate, which is the part to act on.**
The consolidation left behind a record of itself saying the job is done. **A
reader who wants to know whether the `?? 0.36` duplication was dealt with finds a
docblock telling them it was, and stops** — the documentation is now the thing
preventing the discovery. An undocumented duplicate is found by the next grep; a
duplicate standing behind a completion note is not looked for at all.

**Whoever fixes this should correct the docblock in the same edit**, or the next
survivor will hide the same way.

### 2. `GiftingStrategy:219`

```php
'description' => "Leave 10% to charity to reduce IHT rate from {$standardRatePercent}% to {$reducedRatePercent}%",
```

Both rates correctly interpolated; **the threshold hardcoded**.

**This is the shape to warn about.** A reader checking *"does this file read
configuration?"* answers **yes** and moves on — the file demonstrably does, twice,
in the same string. The half-fixed instance is harder to see than a fully
hardcoded one, because the evidence of correctness is sitting immediately beside
the defect. **The question that finds it is not "does this read config?" but
"does EVERY rate in this sentence read config?"**

### 3. `ContributionWaterfallService:184` and `:193` — outside Estate

```php
'explanation' => 'First-time buyer goal found — Lifetime ISA prioritised for the 25% government bonus.',
'explanation' => '… Government bonus: £… × 25% = £…',
```

`TaxConfigurationSeeder:233` holds `government_bonus_rate => 0.25`. A government
bonus rate is a tax value under Rule 2 as much as an Inheritance Tax rate is.

### Also found — Rule 9, five instances in user-facing text

`WillAnalysisService:351-353` says **"IHT"** five times across three sentences
("the reduced X% IHT rate, saving £Y in IHT"), and `GiftingStrategy:219` says
"IHT rate". Rule 9 permits only ISA. These are strings a user reads.

### ADDED BY THE TAX-COMPLIANCE GATE — four I missed, and two are worse than literals

**Condition C4 of the 2026-08-23 verdict on W-0399. All four verified before
recording.** Severity raised medium → high on the strength of the first two.

#### 1. `WillAnalysisService:74` — a rate literal in a COMPUTED FIGURE, not a label

```php
// Potential saving = 4% of baseline (difference between 40% and 36%)
$potentialSaving = $baseline * 0.04;
```

**This is the strongest Rule 2 case in the set and it is not a label at all.**
The 40−36 differential is baked into arithmetic. Under the reviewer's own test
configuration — a 31% reduced rate — the true differential is 9 points, so the
application would report `baseline × 0.04` where `baseline × 0.09` applies:
**understating the saving by more than half**, in a figure a user acts on.

Every other instance in this item is a sentence that disagrees with a correct
calculation. **This one is the calculation.**

#### 2. `EstatePlanService:517` — a wrong statement of law, live today

```php
sprintf('Reduced rate of %d%% applies as 10%% or more of the net estate is left to charity.', …)
```

Two defects in one sentence: the threshold is hardcoded, **and the base is
wrong.** Schedule 1A compares the donated amount against the **baseline** (net
estate less the available nil rate band), not against the net estate. As a
statement of law it is incorrect as written.

**And this is what `/plans/estate` renders, and what printed plans carry.** So
W-0431's rate fix and its Rule 9 fix reached `/estate` and **not**
`/plans/estate`. **One sentence about one rule, two mechanisms, one of them
fixed** — which is exactly the layer lesson this batch has now hit three times.

#### 3. `PlanConfigService:168` — a SECOND configuration home for the same threshold

```php
public function getCharitableGivingThreshold(): float
{
    return (float) $this->get('estate.charitable_giving_threshold_percent', 10.0);
}
```

Independent of `TaxConfigService::getCharitableThresholdPercent()`. **Move the
admin setting and the two surfaces disagree.** W-0431 is therefore only
half-closed at application level while this stands: one Schedule 1A threshold,
two config keys, two defaults.

#### 4. `IHTPlanning.vue:599` — in the component this batch just fixed

```html
<li>Charitable giving (can reduce Inheritance Tax rate from 40% to 36% if ≥10% to charity)</li>
```

Three literals in one line. **Under the 31%/12% test configuration this card
would read "Reduced Inheritance Tax rate of 31% applies" and "from 40% to 36%" at
the same time.** F-0031 did not cause it; F-0031 made it newly visible by making
the sentence above it move.

### A NINTH instance, and it may be a seventh axis — the control-flow literal

**`GiftingStrategy:214`**, found while fixing `:219` and worth more than the
sentence beside it:

```php
if ($charitablePercent < 10 && $currentIHTLiability > 0) {
```

**The literal governs whether the user is ADVISED, not what a sentence says.**
Move `charity_threshold_percent` to 12% and the calculation moves, the message
moves — and a household sitting between 10% and 12% is **silently never told it
could qualify for the reduced rate**, because the recommendation is never
generated. There is no wrong sentence to notice; there is no sentence at all.

**Neither sweep pass finds it.** It is not a percentage in prose — no `%`, no
user-facing string. It is not a rate in arithmetic — it multiplies nothing, it
compares. Every axis in `app/Http/CLAUDE.md` concerns a value's journey between
layers; **this is a value deciding whether the journey happens.**

**Recorded for the reviewer to see, not asserted as an axis.** One instance is an
observation. If a sweep for rate literals in comparisons finds siblings, it is a
pattern and belongs beside the other seven.

### What this says about my sweep, recorded so the next one is better

**I found instances 3 and 4's siblings and missed 1 and 2 entirely, and the
reason is structural rather than carelessness.** My sweep grepped for percentage
literals *inside quoted strings*:

```
grep -rnE "'[^']*\b(3[0-9]|4[0-9]|…)% "
```

`$baseline * 0.04` contains no `%` and no string. **A rate expressed as a decimal
in arithmetic is invisible to a sweep for rates expressed as percentages in
prose** — and the arithmetic instance is the more damaging of the two, because it
changes a figure rather than a caption.

**A Rule 2 sweep needs both passes:** rate-shaped literals in strings, and
rate-shaped decimals in expressions (`* 0.04`, `* 0.36`, `0.4 -`). The second is
noisier and needs the same exclusion discipline as the first.

### Deliberately NOT included

Percentages that are **not tax values** were found and excluded rather than swept
up: `NetWorthAnalyzer:190` ("over 30% of total wealth", a concentration
threshold), `LifePolicyStrategyService:271` ("60% equities, 40% bonds", an
allocation description), `ScenarioService` scenario descriptions, and
`ContributionWaterfallService`'s allocation heuristics ("20% of surplus", "30% of
remaining surplus"). **Rule 2 governs tax values, not every number in a
sentence** — a guard written to "no percentage literals" would manufacture
defects, which is the failure mode `app/Http/CLAUDE.md` warns about for
rule-versus-schema guards.

## Why it is not fixed here

Out of F-0031's scope: that batch is `IHTCalculationService`, `IHTController` and
`IHTPlanning.vue`. `WillAnalysisService` and `GiftingStrategy` are Estate but not
in the dispatch; `ContributionWaterfallService` is Investment. **Filing with
file:line evidence rather than reaching across three services unasked.**

## Acceptance

- [ ] Every tax rate, band and threshold rendered into a user-facing string comes
      from `TaxConfigService` (Rule 2).
- [ ] **`WillAnalysisService:74`'s `* 0.04` derived from the configured rates**,
      not hardcoded. Highest priority of this item — it is a figure, not a label.
- [ ] **`EstatePlanService:517`'s statement of law corrected**: Schedule 1A
      compares against the baseline, not the net estate. It reaches printed plans.
- [ ] **`PlanConfigService::getCharitableGivingThreshold()` retired** in favour of
      `TaxConfigService::getCharitableThresholdPercent()`, or the two reconciled
      explicitly — one threshold cannot have two config homes.
- [ ] `IHTPlanning.vue:599` interpolated from configuration like the sentence
      above it.
- [x] The sweep is run in **both** passes — literals in strings AND rate-shaped
      decimals in expressions. The first pass alone missed the worst instance.
- [ ] **A third pass for control-flow literals** — rate-shaped values in
      comparisons that gate whether advice is produced. One instance found
      (`GiftingStrategy:214`, fixed); whether there are siblings is unswept.

## Fix — IN SCOPE closed, 2026-08-23. NOT "all instances" — that claim was wrong.

**I wrote "all instances closed / nine routings". The tax-compliance gate found
seven remaining, three of them in files I had edited. The claim was inaccurate
and is corrected here rather than quietly amended.**

**Closed:** eleven routings onto homes that already existed, one config home
retired, six Rule 9 instances spelled out, one `const` converted to a method, and
one completion note corrected twice. **No amount changed.** Detail, mutations and
fixtures: `F-0032`.

### Still open, with the reason each survived

| Instance | Why it survived |
|---|---|
| **`EstateAgent:710/713/716/717/728`** — five "10% threshold" instances | **`$reducedRatePercent` is correctly interpolated beside them** — the half-fixed shape this item's own text warns about. A reader checking "does this file read config?" answers yes. `EstateAgent` is outside F-0032's scope. |
| **`EstateAgent:694`** — a fourth `?? 0.36` | Found by `grep`, not by any of the three counts. Outside scope. |
| **`TaxSettingsController:330`** — `'%g%% (if 10%%+ to charity)'` | **Inside the admin screen that displays the tax settings themselves**, hardcoding the threshold in the sentence describing the configured rate. Outside scope. |
| second tier across trust and cross-module services | Unswept. |

### Closed here, and worth naming because each was a different blind spot

- **`WillAnalysisService:27`** — the threshold inside a **`const`**, in the same
  `message` key and the same return array as three sentences fixed beside it.
  **A `const` cannot interpolate**, so a sweep reading declarations sees a fixed
  string and moves on — which is what a constant is for. **The moment a sentence
  needs a configured value it stops being a constant.** Converted to a method.
- **`GiftingStrategy:214`** — the threshold inside the **comparison that decides
  whether the recommendation is produced**. See the control-flow section above.
- **`ContributionWaterfallService:210`** — a **third** hardcoded bonus rate in
  the same step, in the headline the user actually reads. Found only by writing
  a test that drives the service.

### The count has now been wrong three times, by three authors

The original docblock said the consolidation was complete. My first correction
named one survivor. The gate named two. **`grep -rn '?? 0.36' app/` finds four.**

`TaxConfigService`'s note no longer asserts a number — **it records the two grep
commands instead of a conclusion**, because the number is not the durable thing
and every statement of it so far has been confident and wrong.

**Left deliberately:** `potential_saving`'s base stays the baseline. This made
the RATE configuration-driven; re-deriving the formula would move a published
figure inside a Rule 2 fix. Reviewer's Q2.
- [ ] `WillAnalysisService:348` routed onto `getCharitableReducedRate()` — the
      last survivor of a consolidation that already happened — **and
      `TaxConfigService`'s docblock corrected in the same edit**, because that
      note currently asserts a completeness it does not have.
- [ ] Rule 9: "IHT" spelled out in all five user-facing instances.
- [ ] Tested the way W-0431 was: **move the configuration to a value nothing else
      uses and assert the sentence follows.** Asserting the current strings proves
      nothing, because the literals and the configuration agree today — which is
      the whole reason this survived.
- [ ] The exclusions above are preserved. A guard that bans percentage literals
      outright would break correct code; if one is written, it needs an exception
      list naming why each excluded number is not a tax value.
