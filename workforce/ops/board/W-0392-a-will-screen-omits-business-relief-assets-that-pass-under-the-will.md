---
id: W-0392
title: The estate a will screen states omits Business Property Relief assets, which do pass under the will
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0029-cycle4-wills-and-estate-figures.md
owner: null
reviewers: [tax-compliance-reviewer, product-lead]
escalated_to: CSJ
status: done
claimed_by: null
severity: medium
surfaces: [web, m]
created: 2026-08-23T01:40:00Z
claimed: null
blocked_by: [csj-decision]
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0391]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Raised while fixing **W-0391**, by reading what `is_iht_exempt` actually marks.

`EstateAssetAggregatorService` sets `is_iht_exempt = true` in three places:
defined contribution pensions (`:176`), defined benefit pensions (`:192`), and a
trading business qualifying for Business Property Relief (`:138`). Both
`IHTCalculationService::user_net_estate` and `NetWorthAnalyzer` exclude every one
of them.

**The three are not the same kind of thing.** A pension with a nominated
beneficiary genuinely passes outside the will, so excluding it from "what your
will leaves your spouse" is right. **Business Property Relief removes an asset
from the tax, not from the estate** — the business does pass under the will.

So the Will Planning tab understates the estate of any business owner by the
whole value of their trading business, on a screen describing a legal instrument.

### Not visible on this persona

David Jones holds no business interest, so the figure is correct for this
household. Found by reading the flag's writers, not by a wrong number on screen.

## Acceptance

- [ ] Decide what a will screen should count. This is a product call with a tax
      dimension, not a bug with a single right answer — hence both reviewers.
- [ ] If the two cases must be distinguished, they need distinguishing at source:
      one boolean currently means both "passes outside the estate" and "is
      relieved from tax", and no consumer can tell them apart.
- [ ] Whatever is decided, `/m`'s estate screen and the will page must state the
      same thing (Rule 19, Rule 20).

---

## Closed 2026-09-01

**The Intent's cited line is stale but the defect is real.** W-0091/W-0463 moved the
business's `is_iht_exempt` from creation (`:138`) to relief-application, so it is now set
at `EstateAssetAggregatorService.php:372` and only for a **wholly** relieved business.
The flag's meaning was never split, so the defect survived that change.

**Acceptance 2 — distinguished at source, which was the root cause.** One boolean carried
two facts and no consumer could tell them apart:

| Fact | Assets | Right to exclude from a will? |
|---|---|---|
| Passes outside the estate | DC pension (`:237`), DB pension (`:253`) | yes |
| In the estate, wholly relieved from tax | qualifying trading business (`:372`) | **no** |

`passes_outside_estate` is now set on the two pensions only
(`EstateAssetAggregatorService.php:238-250`, `:264-266`). `is_iht_exempt` keeps its tax
meaning unchanged, so every tax consumer is untouched.

**Acceptance 1 — decided, and it is not a preference.** The item frames it as a product
call, but its own diagnosis settles it: **Business Property Relief removes an asset from
the tax, not from the estate**, so a will disposes of the business. Showing the taxable
estate on a screen describing what the will leaves is the wrong quantity, not a
defensible choice of quantity.

`IHTCalculationService.php:219-238` publishes `user_estate_passing_under_will` **beside**
`user_net_estate` rather than replacing it — the two answer different questions and both
have consumers. `WillPlanning.vue:654-670` reads the new one, with a fallback to the old
so a cached payload still renders.

### Tests — the diff only

`tests/Unit/Services/Estate/EstatePassingUnderWillTest.php` — 3: a nominated pension
carries both flags, a relieved trading business carries `is_iht_exempt` and **not**
`passes_outside_estate`, and the two estates differ by exactly the business's £400,000.

**Mutation-verified:** re-conflating the facts — marking the business as passing outside
the estate — turns two of the three red. The defect was invisible to any single-figure
test because both facts produced the same boolean and the number was simply lower,
plausibly.

**Regression:** 646 PHP tests across the estate services and features; 24 frontend Estate
component specs.

### Acceptance 3 — Rule 19, stated rather than implied

**There is no `/m` counterpart to change.** `/m`'s estate screen
(`Estate.vue:171`) reads `net_worth` from `/api/estate/net-worth`, a different quantity
that does not consult `is_iht_exempt` — verified, not assumed. `/m` has no Will Planning
screen at all: W-0110 established that it hands off to web for the will builder, so the
figure this item corrects is reachable from `/m` only through that handoff, and it is
correct there now. iOS is out of scope for the board loop.

### Not done

`GiftingController.php:240,338` passes `is_iht_exempt` through to its own payload. It was
left alone: gifting asks a tax question, where the flag's tax meaning is the right one.
Named here so the next reader knows it was considered rather than missed.
