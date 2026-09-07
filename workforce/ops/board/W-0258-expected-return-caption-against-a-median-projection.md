---
id: W-0258
title: The card captions an arithmetic "expected return" beside a median projection that is lower by volatility drag, and the two cannot be reconciled by the reader
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: done
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T21:50:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [RiskPreferenceService, InvestmentProjectionChart]
prior_art_outcome: none
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

Surfaced by W-0251's acceptance criterion — *every horizon must imply an annual rate
consistent with the caption on the card.* After the fix they do, but only once volatility
drag is understood, and nothing on the card discloses it.

David's portfolio, life events removed so the comparison is clean: a stated
**7.07% expected return** produces a **median** implying **5.36% – 6.09% a year**. The gap
is σ²/2 = 0.1688²/2 = **1.42%** — the difference between the arithmetic mean return the
caption quotes and the geometric outcome a median path actually compounds at.

The figure is correct and the caption is correct. **They are simply not the same quantity,
and a user checking one against the other will conclude the projection is wrong.**

This is a disclosure question, not a calculation one. No code was changed on it.

## Acceptance

CSJ to decide one of:
1. Caption the geometric/median rate rather than the arithmetic expected return;
2. Keep the caption and add a line explaining why the projection compounds lower;
3. Leave as is, recorded as a deliberate choice.

## Working notes

- Measured after the W-0251 fix, `MonteCarloEngine` seeded, 1,000 iterations.
- Applies identically to the retirement pension projection, which shares the caption
  pattern and the band extractor.

---

## Closed 2026-09-01 — option 2 taken, and reversible in one line

**Option 2: keep the caption, add the line.** Reasoning, so CSJ can overturn it cheaply
if they prefer 1 or 3:

- **Option 3 (leave it, record as deliberate) is the one option that leaves a live
  defect.** The item's own finding is that a user checking the caption against the
  figure concludes the projection is wrong. Recording that as accepted does not stop it
  happening.
- **Option 1 (caption the geometric rate) moves what the card claims** and would then
  disagree with the risk profile page, which quotes the arithmetic expected-return band
  from configuration. That trades one inconsistency for another.
- Option 2 moves no figure, and the added sentence is written in plain terms rather than
  naming variance or geometric means.

### The sentence

> The line shows the middle outcome, which grows a little slower than this rate because
> returns vary from year to year.

No acronyms, no "σ²/2", no percentage the user then has to reconcile — Rule 9.

### One home, because the caption had two authors and a third site with none

`InvestmentProjectionChart:127-141` and `PensionPotProjectionChart:114-121` built this
string **independently and identically**, so the disclosure would have had to be added
twice or not at all. `resources/js/utils/projectionCaption.js` is now the one home, and
both read it.

Finding that turned up a **third** site the item did not list:
`PensionDetailInline.vue:463-471` quotes the same arithmetic expected return beside the
same median projection in prose, with no chart caption at all. It carries the same
sentence, from the same constant.

### Tests

`resources/js/utils/__tests__/projectionCaption.spec.js` — 4: the disclosure present,
the charges clause preserved alongside it, no charges clause when there are none, and a
guard that reads all three consumer files and fails if any reassembles the sentence for
itself.

**Regression:** 775 frontend tests across the utils and component suites.

**Rule 19:** `resources/mobile` quotes no expected return — `grep` returns nothing — so
there is no `/m` caption to carry this. iOS is out of scope for the board loop.

**If CSJ prefers option 1 or 3**, it is a one-line change in `projectionCaption.js`
rather than an edit to three components.
