---
id: W-0151
title: Fynla's own wording points the user at a certificate provider the regulations disqualify, and never mentions the other seven disqualifications
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0008-batch-g-lpa.md
owner: build-lead
status: done
folded_into: W-0103
claimed_by: fix-batch-G
severity: high
surfaces: [web]
created: 2026-08-21T18:30:00Z
claimed: 2026-08-21T19:40:00Z
blocked_by: []
gate: null
prior_art_checked: 2026-08-21
prior_art_found: ["W-0102 certificate provider can be a donee — same file, same check", "W-0103 party role conflicts unchecked — folded with W-0102", "W-0100 acceptance 1-4, fix-batch-G", "workforce/ops/reports/2026-08-21-W-0100-lpa-perimeter-review.md"]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
source: found by compliance-lead while sourcing the W-0102/W-0103 wording, 2026-08-21; routed to team-lead rather than self-filed because compliance does not hold an ID block
---

## Intent

**SI 2007/1253 (the Lasting Powers of Attorney, Enduring Powers of Attorney and Public
Guardian Regulations 2007) reg 8(3) lists eight people who may not give the certificate.
Fynla mentions none of them, and its own wording actively steers the user towards one.**

Compliance found this while reading reg 8 to source the W-0102 citation. It is
**adjacent to W-0102, not the same defect**: W-0102 is the attorney limb, which reg 8(3)(b)
and MCA 2005 Sch 1 para 2(6) both reach. These are the other seven limbs.

**Two of the eight are visible in Fynla's own data, and one of those is worse than a gap.**

1. **reg 8(3)(a) — a family member of the donor is disqualified.**
   `checkCertificateProviderKnownYears()` presents two years' acquaintance as **the**
   requirement. The person a donor has known two years and trusts is most often their
   spouse or their child. **So the application's own wording points the user at exactly
   the person the regulations disqualify.** That is not a missing check; it is a check
   that misleads in a specific, predictable direction.

2. **reg 8(3)(c) — a donee of any *other* lasting or enduring power of attorney executed
   by the same donor is disqualified.** Fynla holds both instrument types for one user,
   so "the attorney on the health and welfare instrument certifies the property and
   financial affairs instrument" is a state the application **can construct and can
   detect**. Of the eight limbs it is the only one Fynla has the data to check.

The remaining six turn on facts Fynla does not hold and cannot obtain.

## Acceptance

- [ ] The two-year framing no longer presents acquaintance as the whole requirement, and
      no longer steers the user towards a disqualified person.
- [ ] The cross-instrument case (reg 8(3)(c)) is detected, using **whatever check W-0102
      and W-0103 build — not a parallel one.** Same file, same check, sequenced with them.
- [ ] The remaining six limbs are `NOT_CHECKED` entries rather than silent omissions.
      **Claiming less is always available** (compliance-lead, 2026-08-21).
- [ ] Every string passes the act-not-object test: it names what Fynla did or did not
      check, never a property of the instrument or of the person.
- [ ] The pass wording follows the rule already set for W-0102: **"The names in each role
      are different", never "no conflict found"** — a string comparison finding no match
      is not evidence that no conflict exists.
- [ ] Wording carries the reliability limit from `LpaCheckPolicy::NOT_CHECKED` rather than
      restating it per message.

## Working notes

(append-only)

- 2026-08-21 team-lead: raised on compliance-lead's recommendation and in its words, from
  the W-0102/W-0103 sourcing pass. Filed at W-0151 because compliance holds no ID block;
  W-0101–W-0110 are `fix-batch-G`'s and exhausted, W-0111–W-0120 `fix-batch-I`,
  W-0121–W-0130 `fix-batch-J`, W-0131–W-0140 `persona-passA3`, W-0141–W-0150 reserved to
  `fix-batch-G` for the rest of its run.
- 2026-08-21 team-lead: **sequence this with W-0102/W-0103, do not start it separately.**
  Same file, same check, and splitting it invites the parallel mechanism the whole
  W-0100 family exists to remove.
- 2026-08-21 compliance-lead, recorded by team-lead: **"family member" is not defined in
  reg 8(4)**, which defines only *care home*, *registered health care professional* and
  *registered social worker*. That is a §6 question, not answerable by any agent here, and
  it bounds how far limb (a) can be checked even in principle.
- 2026-08-21 team-lead: text relied on is compliance's, as at 2026-08-21, "latest
  available (revised)" on legislation.gov.uk. **reg 8 carries no outstanding amendments
  and is not revoked.** Commencement was checked and does not bite this item — but it
  does bite W-0109 and anything touching registration, per the standing warning installed
  in `05-perimeter.md` §1 today. **Provisional on its face:** legal services is Unmapped
  under the regime map CSJ adopted this morning.

- 2026-08-21 compliance-lead: **RULING — provisional. The reg 8(3)(a) limb is unblocked, and
  it does NOT need `Q-12` answered.** Full context:
  `workforce/ops/reports/2026-08-21-section-6-backlog-triage.md` and
  `workforce/ops/open-questions.md` (`Q-12`). **Not an approval** (`05-perimeter.md` §7.3).
  **Provisional** — legal services is Unmapped (§1.1, §1.3).

  ### I searched for a definition of "family member". There is none.

  Team-lead pointed me at this as the question that decides whether the check is buildable.
  **I did what W-0106 taught: read the instruments rather than reason from what seems
  sensible.** Three places a definition would live, all read on `legislation.gov.uk`,
  **2026-08-21**, latest available (revised):

  | Where | Result |
  |---|---|
  | **S.I. 2007/1253 reg 8(4)** — the local interpretation for the very regulation that uses the term | Defines **`care home`**, **`registered health care professional`**, **`registered social worker`**. **Not `family member`.** |
  | **S.I. 2007/1253 reg 2** — the instrument's general interpretation | Defines `the 2017 Act`, `the Act`, `court`, `guardian`, `guardianship order`, `LPA certificate`, `person to notify`, `prescribed information`. **Not `family member`.** Version as at 31/07/2019 |
  | **MCA 2005 s.64** — the parent Act's interpretation | 24 defined terms including `lasting power of attorney`, `trust corporation`, `will`. **Neither `family member`, `family` nor `relative` is among them** |

  **That is a complete negative search across the obvious homes**, and it is a finding rather
  than a failure: **whoever eventually answers `Q-12` does not need to go looking for a
  statutory definition. There isn't one.** The question narrows from *"what is a family member
  for reg 8(3)"* to *"what does an undefined term mean on ordinary construction, here"* —
  cheaper, and better aimed.

  ### The build decision, which does not wait for that answer

  **Ruling: the reg 8(3)(a) limb is DISCLOSURE-ONLY. Do not build a family-member check.**

  This is within competence because it is a ruling about **what Fynla is entitled to assert**,
  not about what the law requires. The reasoning:

  **The regulation does not draw the boundary. If Fynla builds a check, Fynla draws it** —
  and that is authoring doctrine at the point of use, which `05-perimeter.md` §1.3 rule 1
  forbids outright on an unmapped regime. Fynla holds `relationship_to_donor` as free text
  plus spouse links and `FamilyMember` rows; **any check over those encodes a line between
  relatives who disqualify and relatives who do not, which the instrument leaves undrawn.**

  **A check that fires on a spouse but not a cousin would be telling the user something Fynla
  is not entitled to tell them** — and it would be *wrong in the direction that matters*,
  because a clean pass reads as clearance.

  **This holds whichever way `Q-12` is eventually answered**, which is why the item is not
  blocked. A favourable answer would only permit *adding* a check later.

  ### The wording

  **One entry in `LpaCheckPolicy::NOT_CHECKED`**, in the register of the existing entries —
  short, plain, no citation, no consequence:

  > `Whether anything disqualifies your certificate provider from giving the certificate — including being a member of your family.`

  **The trailing clause is deliberate and should not be trimmed to match the other entries'
  brevity.** Acceptance 1 records that Fynla's own two-year wording *steers* the user toward a
  spouse or child. A generic "anything disqualifies" line does not undo that steer; naming the
  family case does, and it is the one limb where Fynla's own copy created the problem.

  This replaces the shorter line I gave on W-0106 (*"Whether anything disqualifies your
  certificate provider from giving the certificate."*) — **one entry, not two.** Rule 20.

  ### What this does NOT change

  - **The reg 8(3)(c) limb still gets a real check.** A donee of any other lasting or enduring
    power of attorney executed by the same donor is the one limb Fynla can detect from its own
    data, and it needs no undefined term. Unaffected by this ruling.
  - **The remaining six limbs stay `NOT_CHECKED`**, as acceptance 3 has it.
  - **`Q-12` stays open**, and is reclassified from *blocks a build decision* to *could only
    permit relaxation* in `ops/open-questions.md`. **It should drop down any lawyer's queue
    accordingly** — I raised its priority earlier on the basis that it blocked this item, and
    it no longer does.

- 2026-08-21 fix-batch-G: **FOLDED INTO W-0103 and delivered there.** One check,
  `LpaComplianceService::checkPartyRoles()`, covers this item and the others — building
  it separately would have been the parallel mechanism the whole W-0100 family exists to
  remove. The compliance ruling above is unchanged and is the authority for the wording
  that shipped; it stays here rather than being copied. **No separate work remains.**
  See W-0103's working notes and `workforce/branches/fixes/F-0008-batch-g-lpa.md` §4.
