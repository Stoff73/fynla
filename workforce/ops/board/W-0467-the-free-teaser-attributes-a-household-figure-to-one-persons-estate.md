---
id: W-0467
title: The Free-tier teaser says "your estate" of a pooled second-death household figure, to a user whose own first-death liability is typically nil
mission: persona-run-peak_earners-2026-08-20
branch: estate-copy-and-m-handoff
owner: null
reviewers: [tax-compliance-reviewer, compliance-lead, product-lead]
status: gated
claimed_by: null
severity: medium
surfaces: [m, web]
created: 2026-08-23T19:20:00Z
claimed: null
blocked_by: []
gate: compliance-lead
handoff_to: null
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0464]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 04-voice]
source: tax-compliance-reviewer finding F16, 2026-08-23 — raised while approving the W-0464 consolidation
---

## Intent

`EstateIhtExposureDetector::buildHeadline()` now reads the real engine (W-0464), which
the reviewer approved. The **wording** did not change with it:

> "Your estate could be subject to up to £X in Inheritance Tax"

For a married household with sharing on, `iht_liability` is a **pooled, second-death**
figure covering BOTH estates against doubled allowances. It is not "your estate", and it
is not payable on this user's death — **that same user's actual first-death liability,
with spouse exemption, is typically £0.**

"could be subject to up to" is properly hedged on **magnitude**. It is not hedged on
**whose** or **when**, and those are the two things wrong with it.

It is the **only** Inheritance Tax figure `/m` ever displays, on a Free-tier conversion
surface, to users who cannot open the calculation behind it.

## Acceptance

1. **CSJ decides the wording.** It must convey that the figure is for the household and
   arises on the second death, without becoming unreadable on a phone.
2. Consistent on `/m` and web.
3. `compliance-lead` on the copy — a Free user may act on this number, and a conversion
   surface is where an over-claim does the most harm.

## Working notes

- 2026-08-23 — The reviewer was explicit: "I can tell you it is inaccurate; what it
  should SAY is copy, and it is a Free-tier conversion surface, so it is CSJ's call."
- 2026-08-23 — **CSJ settled the wording:**

  > Your household could face up to £X in Inheritance Tax on the second death —
  > upgrading unlocks personalised planning to help reduce this.

  It fixes both defects the reviewer named — *whose* and *when* — and stays one phone
  line longer than what it replaces.

- 2026-08-23 — **Implemented in `EstateIhtExposureDetector::buildHeadline()`.** The
  single/unmarried branch KEEPS "your estate could be subject to up to", because for
  that user the figure genuinely is their own estate on their own death; swapping
  everyone to the household wording would trade one wrong sentence for another.

- 2026-08-23 — **Whether the figure is pooled is read back off the calculation**
  (`is_married && data_sharing_enabled`), not re-derived in the detector. Re-deriving
  it would be a second predicate that can drift from the one the figure was actually
  computed under — the shape of the W-0154 defect this engine has already been fixed
  for once.

- 2026-08-23 — Tested both branches. The pair discriminates: a detector that always
  took the single branch fails the first case, one that always took the household
  branch fails the second.

- 2026-08-23 — Still needs `compliance-lead` on the copy before this leaves `handoff`.


- 2026-08-24 — **`compliance-lead`: FLAGGED. Three findings, all fixed.**
  - **(D) "personalised" oversells what an unauthorised firm can deliver.** It is
    precisely the word separating generic guidance from a personal recommendation, and it
    appeared in **two homes for one claim** — the headline (twice) and the `/m` teaser
    note. Both changed together (Rule 20). Now "estate planning tools".
  - **(E) "to help reduce this" was an unhedged efficacy claim on a conversion surface**
    — the magnitude was hedged and the promise was not, and **the one clause with nothing
    qualifying it was the clause asking for money.** Now "tools you could use to explore
    ways of reducing it". The `/m` card also gains the Rule 3 signpost.
  - **(F) THE BRANCH BOUNDARY WAS NOT WHERE IT WAS RECORDED.** The board — and what I
    told CSJ — said the other branch keeps "your estate" for single or unmarried users
    "because for them the figure genuinely is their own". **The premise is right; the
    branch did not implement it.** The predicate is "not pooled", and `$isMarried` itself
    requires a linked account, so it also caught **married users whose partner has no
    Fynla account** and **married users with sharing off or revoked** — telling a married
    person "Your estate could be subject to up to £X" when their own first-death
    liability, with the spouse exemption, is typically £0. **The exact defect this item
    exists to fix, alive in the branch nobody changed.** And **W-0347 makes that group
    grow**, because sharing is now genuinely opt-in and revocable.
    A third branch now says: *"Based on your own records alone… This figure does not
    allow for anything passing to your partner. Linking your accounts gives a fuller
    picture."* The FIGURE was always right — W-0154 F3 moved the doubling onto
    `$poolsSpouse` — it was only ever the sentence.
  - **Not answered, and correctly so:** whether pairing a tax figure with an upgrade
    prompt is a financial promotion under s21 FSMA is a determination the perimeter
    document forbids the agent to make. Open as `Q-18`. Fair value not assessable from
    copy alone — recorded as a gap, not a pass.
