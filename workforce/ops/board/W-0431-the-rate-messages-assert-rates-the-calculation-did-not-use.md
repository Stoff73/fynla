---
id: W-0431
title: The Inheritance Tax rate messages asserted 40%, 36% and 10% as literals while the calculation beside them read the real figures from configuration
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0031-cycle4-charitable-figures.md
owner: build-lead
reviewers: [tax-compliance-reviewer, quality-lead]
status: done
claimed_by: build-lead
severity: medium
surfaces: [web, m]
created: 2026-08-23T02:55:00Z
claimed: 2026-08-23T02:55:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0132, W-0399]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

**Found by editing the sentences for Rule 9 and reading what they said.**

`IHTCalculationService::determineIHTRate()` builds three user-facing messages.
Every rate in all three was a **hardcoded literal**:

```
'Reduced IHT rate of 36% applies. … meets the 10% threshold of £…'
'Standard IHT rate of 40% applies. … below the 10% threshold … qualify for 36% rate.'
'Standard IHT rate of 40% applies. Leave 10%+ of your baseline estate …'
```

while the calculation two lines above reads the real values:

```php
$standardRate = $ihtConfig['standard_rate'];
$reducedRate  = $this->taxConfig->getCharitableReducedRate();
$threshold    = $baseline * $this->taxConfig->getCharitableThresholdPercent();
```

**So the sentence asserts a rate the figure beside it was not necessarily
computed at.** Change `reduced_rate_charity` in configuration — which the admin
Tax Settings screen offers, and which `getCharitableThresholdPercent()`'s own
docblock records was added precisely because that control was inert — and the
message keeps saying 36% while the estate is charged something else.

### This is W-0132, one layer over

`IHTPlanning.vue` already carries the scar: *"Was `charitableBequest ? '36%' :
'40%'` — two hardcoded strings decided by a user toggle that never loaded, so the
label read 40% permanently while the figure next to it had been computed at 36%.
£397,651 sat under a label saying it should have been £441,834."*

That was fixed in the component. **The same pattern survived in the server
message the component renders underneath it.** Fixing the label and leaving the
sentence is how this class of defect persists.

### Rule 2

Root `CLAUDE.md` Rule 2 admits no exception: UK tax values come from
`TaxConfigService`. A rate rendered to a user is a tax value. team-lead restated
it on dispatch — *"Every rate, band and threshold from `TaxConfigService` — Rule
2, no exceptions."*

## Fix

One local formatter beside the rates it describes, and three sentences that quote
what was actually used:

```php
$asPercent = static fn (float $rate): string => rtrim(rtrim(number_format($rate * 100, 2), '0'), '.').'%';
$standardRateLabel = $asPercent($standardRate);
$reducedRateLabel  = $asPercent($reducedRate);
$thresholdLabel    = $asPercent($this->taxConfig->getCharitableThresholdPercent());
```

**No user-visible change today**, because configuration holds 0.40, 0.36 and 0.10
— which is exactly why this could sit unnoticed, and exactly why the test moves
the configuration rather than asserting the current strings.

## Acceptance

- [x] Every rate in every branch of the message comes from configuration.
- [x] Proven by moving the real input: configuration set to a **31%** reduced rate
      and a **12%** threshold — values nothing else in the codebase uses, so
      neither can be produced by a fallback or a coincidence — and the message
      follows, while the old literals are asserted absent.
- [x] Mutation-tested: re-hardcoding `36%` turns exactly that case red and
      nothing else.
- [x] The trailing `%` formatting drops trailing zeros, so a 36% rate reads "36%"
      and not "36.00%".
- [ ] `tax-compliance-reviewer` to confirm the rounding of a displayed rate is
      acceptable (`number_format(…, 2)` then trimmed), and that no branch can
      now render a rate that differs from the one applied.

## Working notes

- 2026-08-23 build-lead: found and fixed inside W-0399. Test:
  `tests/Unit/Services/Estate/CharitableExemptionVersusRateTestTest.php`,
  the "Rule 2 (W-0431)" block. Not self-certified.
- **Not swept beyond this method.** Other services almost certainly quote rates
  in prose the same way — `WillAnalysisService`, `GiftingStrategy` and
  `EstateAgent` all appear in `TaxConfigService`'s docblock as former duplicate
  readers of the charitable rate. **A sweep for rate literals inside user-facing
  strings is worth its own item and is not this one.**

- 2026-08-31 build-lead: **VERIFIED ALREADY FIXED AND TESTED — closed. This is the parent of the family finished today.**

  No rate literal survives in `IHTCalculationService`: every 40, 36 and 10 in the file is inside a comment explaining a fix. The three rate messages this item raised are configuration-driven.

  **The guard, `tests/Unit/Services/Estate/RateLiteralsComeFromConfigurationTest.php`, is the durable half and its structure is the lesson** — 6 passed, 23 assertions, organised by the THREE ways a rate hides rather than by the three messages that were wrong:

  - *the arithmetic pass* — a rate hidden in a calculation
  - *the prose pass* — a rate quoted in a sentence, moved and re-read
  - *the const pass* — a rate in a declaration that cannot interpolate

  Plus one home for the Schedule 1A threshold, asserted against the plans surface.

  **What this guard could not see is what took the family four sweeps to close, and it is now closed.** Every case here drives PHP and asserts on SERVICE OUTPUT, so nine literals stood in Vue templates untouched — re-hardcoding a caption left the whole suite green. **W-0461**, closed today, adds the missing shape: it moves a configured rate and asserts on a MOUNTED TEMPLATE, mutation-verified. **W-0432** closed with it.

  The family now has both halves: PHP and rendered markup.
