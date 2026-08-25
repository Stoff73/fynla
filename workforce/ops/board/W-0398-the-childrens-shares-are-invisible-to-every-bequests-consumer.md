---
id: W-0398
title: A residuary substitution beneficiary is invisible to every consumer of the bequests table — which is why this household reads as though its children are unprovided for
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0029-cycle4-wills-and-estate-figures.md
owner: null
reviewers: [product-lead, quality-lead]
status: queued
claimed_by: null
severity: medium
surfaces: [web, m]
created: 2026-08-23T02:30:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0023, W-0046, W-0394]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

The cycle-4 dispatch asked whether the persona's four percentage bequests to the
two children were **a save path silently dropping them** or **unfinished data
entry**, and said plainly: if it is missing data, say so and do not manufacture a
defect.

**It is missing data. The save path is sound.** This item exists for the second
cause, which is real and is not the missing data.

### The save path is proven sound, not assumed

`tests/Feature/Estate/BequestBeneficiaryTypeTest.php` posts a 60% and a 40%
percentage bequest through `POST /api/estate/bequests` and reads both back
through `GET /api/estate/bequests`, asserting each percentage, each name and each
priority order. **60/40 rather than 50/50 deliberately** — at 50/50 a path that
dropped one row, wrote one row twice, or overwrote the second with the first
would be indistinguishable from a correct one (`tests/CLAUDE.md` §4, Collision).
Both round-trip intact. **No defect. The tester's household simply never had them
entered.**

### The real finding — one absence, two causes

The children ARE recorded in both will documents, as the residuary's
**substitution beneficiary**: free text, *"William Jones and Charlotte Jones in
equal shares, held in trust until age 25"*. It renders on the will planning
screen. It reaches the `bequests` table **never**.

`WillDocumentService::syncBequests()` deliberately excludes residuary
beneficiaries, and its reason is sound and documented: the `bequests` table has
no way to express *"a share of what is left after the gifts"*. A residuary row
would have to be stored as `percentage`, and
`Will::getNonSpouseAllocationPercentage()` sums exactly those rows — **so a
mirror will leaving 100% to a partner would report a 100% NON-partner
allocation.** Recording it there would corrupt an existing answer to buy a
duplicate of one the document already holds.

**So the tester saw a real absence with two causes and could not separate them,
and neither could the household's owner.** The data entry is unfinished, AND the
provision the persona does record is invisible to every consumer of `bequests` —
the Estate module, `WillAnalysisService`, and `/m`'s bequests screen, which shows
"1 bequest" for a will that provides for three beneficiaries.

**That is why this household reads as though its children are unprovided for.**

## Acceptance

- [ ] Decide whether a residuary share should be representable in `bequests` at
      all. If it should, it needs a representation that
      `getNonSpouseAllocationPercentage()` can distinguish from an ordinary
      percentage gift — a schema decision, not a patch.
- [ ] If it should not, the surfaces that count bequests must stop implying the
      count is the whole of the will. `/m`'s "1 bequest" is accurate about the
      table and misleading about the document.
- [ ] Either way, both surfaces say the same thing (Rule 19, Rule 20).

## Working notes

- 2026-08-23 build-lead: raised from the cycle-4 wills batch. **Not a defect
  about the missing data** — that half is unfinished data entry and is recorded
  here only so the next person does not re-investigate it.
