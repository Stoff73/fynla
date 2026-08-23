---
id: W-0467
title: The Free-tier teaser says "your estate" of a pooled second-death household figure, to a user whose own first-death liability is typically nil
mission: persona-run-peak_earners-2026-08-20
branch: estate-copy-and-m-handoff
owner: null
reviewers: [tax-compliance-reviewer, compliance-lead, product-lead]
status: handoff
claimed_by: null
severity: medium
surfaces: [m, web]
created: 2026-08-23T19:20:00Z
claimed: null
blocked_by: []
gate: compliance-lead
handoff_to: null
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
