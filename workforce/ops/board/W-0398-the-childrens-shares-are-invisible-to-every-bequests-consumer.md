---
id: W-0398
title: A residuary substitution beneficiary is invisible to every consumer of the bequests table — which is why this household reads as though its children are unprovided for
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0029-cycle4-wills-and-estate-figures.md
owner: null
reviewers: [product-lead, quality-lead]
status: done
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

---

## Closed 2026-09-01

**Acceptance 1 — decided, and it resolves in the code rather than as a preference.**
**A residuary share should NOT be representable in `bequests`.**
`Will::getNonSpouseAllocationPercentage():79-81` sums exactly the `bequest_type =
'percentage'` rows. A residuary is a share of what REMAINS after the specific gifts, not
a percentage of the estate, so storing one there reports a mirror will leaving everything
to a partner as a **100% non-partner allocation**. Making it distinguishable needs a new
enum value plus every consumer of that enum — a schema change bought to duplicate what
the will document already holds correctly. The existing exclusion stands.

**Acceptance 2 — the surfaces stop implying the count is the whole will.** That was the
real defect: the children ARE provided for, as the residuary's substitution beneficiary,
and the table saw nothing.

- `WillDocumentService::BEQUESTS_EXCLUDE_RESIDUARY_NOTE` — the sentence, beside the
  exclusion it explains.
- `WillController::getBequests():163-190` serves it, **including when there is no will**,
  so an empty list is not read as a complete one either.
- Web: `WillPlanning.vue:374-380`, `:508`, `:634`.
- `/m`: `EstateBequests.vue:24-30`, `:70`, `:101`.

**Acceptance 3 — Rule 20, and why the sentence is served rather than written twice.**
`/m` is an isolated bundle and cannot import from `resources/js`, so a frontend-held
sentence would be **two copies from the day it was written** — which is the arrangement
that produces one surface saying something the other does not. It has one home, in the
service that causes the exclusion.

Wording avoids requiring the reader to know what "residuary" means: *"This lists the
specific gifts in your will. Anything left over — the residue — is dealt with separately
in the will document itself, along with anyone named to inherit it."* Asserted by test.

### Tests — the diff only

`tests/Feature/Estate/BequestsStateWhatTheyExcludeTest.php` — 4: the note served with a
will, served without one, worded for a reader who does not know the term, and a **guard
on the exclusion itself** — if someone later "fixes" this by writing residuary rows into
`bequests` as percentages, the mirror-will allocation assertion goes red. That guard is
on the live answer that would be corrupted, not on a style rule.

**Regression:** 650 PHP tests across the estate suites; 43 frontend specs across the web
Estate components and the `/m` module views.

### Recorded, not re-investigated

The persona's four percentage bequests being absent is **unfinished data entry, not a
save-path defect** — `BequestBeneficiaryTypeTest` round-trips 60/40 percentage bequests
through POST and GET with names and priority order intact, deliberately not 50/50 so a
dropped, duplicated or overwritten row could not pass. Left as the item found it.
