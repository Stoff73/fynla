---
id: W-0392
title: The estate a will screen states omits Business Property Relief assets, which do pass under the will
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0029-cycle4-wills-and-estate-figures.md
owner: null
reviewers: [tax-compliance-reviewer, product-lead]
escalated_to: CSJ
status: queued
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
