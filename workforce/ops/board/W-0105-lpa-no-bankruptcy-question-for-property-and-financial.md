---
id: W-0105
title: No bankruptcy question exists for a property and financial affairs Lasting Power of Attorney
mission: M-0002-persona-fidelity
owner: build-lead
reviewers: [compliance-lead]
status: done
severity: medium
surfaces: [web]
created: 2026-08-21T17:44:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0100 acceptance 2]
prior_art_outcome: none
constitution_refs: [05-perimeter]
source: W-0100 acceptance 2, fix-batch-G, 2026-08-21
---

## Intent

**Mental Capacity Act 2005, s.10(2): "An individual who is bankrupt may not be
appointed as donee of a lasting power of attorney in relation to P's property and
affairs."**

This restriction applies to the property and financial affairs type and not to health
and welfare — so it is exactly the type-conditional rule W-0100's acceptance 2 was
looking for, and it is absent on both sides: there is no field on `lpa_attorneys`
recording it (`app/Models/Estate/LpaAttorney.php:17`) and no check.

Unlike W-0102 and W-0104, this one **cannot be answered from data Fynla already
holds** — it needs a new field, a migration, a wizard question and a check. That makes
it the largest of the Lasting Power of Attorney audit items and the one most worth
questioning before building.

## Acceptance

1. **First decide whether to ask at all.** Asking "is your attorney bankrupt?" in a
   wizard is intrusive and self-declared, and a wrong answer buys nothing. The
   alternative — naming the restriction in the "what we did not check" disclosure
   (`app/Services/Estate/LpaCheckPolicy.php`) — costs one line and claims nothing.
   **Recommend the disclosure route; put the decision to compliance-lead.**
2. If a field is added: migration by `--path=`, wizard step, check, tests, and the
   property/health split honoured.
3. If the disclosure route is taken: one entry in `LpaCheckPolicy::NOT_CHECKED`, and
   this item closes.

## Working notes

- 2026-08-21 fix-batch-G: raised with a recommendation rather than built. Note the
  trap compliance-lead flagged on W-0100 — MCA Sch 1 carries pending amendments from
  the Powers of Attorney Act 2023 (2023 c. 42), **not in force as at 20 Aug 2026**.
  s.10 is not Sch 1, but check commencement before relying on any current text here.

- 2026-08-21 compliance-lead: **RULING — provisional. The disclosure route is endorsed,
  and I have a reason fix-batch-G did not have.** Full reasoning:
  `workforce/ops/reports/2026-08-21-lpa-claims-rulings.md` §W-0105. **Not an approval**
  (`05-perimeter.md` §7.3). **Provisional** — legal services is Unmapped (§1.1, §1.3).

  **Text relied on, as at 2026-08-21, `legislation.gov.uk`, "latest available
  (revised)". Two provisions, and the second is the one that decides this item.**

  **MCA 2005 s.10(2), verbatim:**

  > *"An individual who is bankrupt or is a person in relation to whom a debt relief
  > order is made may not be appointed as donee of a lasting power of attorney in
  > relation to P's property and affairs."*

  **MCA 2005 s.13(5)–(6)(b), (8), (9), verbatim:**

  > **13(5)** *"The occurrence in relation to a donee of an event mentioned in
  > subsection (6)—(a) terminates his appointment, and (b) except in the cases given in
  > subsection (7), revokes the power."*
  > **13(6)(b)** *"… subject to subsections (8) and (9), the death or bankruptcy of the
  > donee or the making of a debt relief order (under Part 7A of the Insolvency Act
  > 1986) in respect of the donee or, if the donee is a trust corporation, its
  > winding-up or dissolution …"*
  > **13(8)** *"The bankruptcy of a donee or the making of a debt relief order … does
  > not terminate his appointment, or revoke the power, in so far as his authority
  > relates to P's personal welfare."*
  > **13(9)** *"Where the donee is bankrupt merely because an interim bankruptcy
  > restrictions order has effect … his appointment and the power are suspended, so far
  > as they relate to P's property and affairs, for so long as the order has effect."*

  **Commencement checked.** s.10 is not among the provisions carrying outstanding
  Powers of Attorney Act 2023 amendments; the F1 amendment to s.10(2) (debt relief
  orders) has been in force since 1 October 2012. s.13 does carry outstanding
  amendments, listed against other subsections; **none is listed against 13(5)–(9)**.
  Read as displayed and dated accordingly.

  ### The ruling

  **Take the disclosure route. Do not add the field, the migration or the wizard
  question.** fix-batch-G recommended this on the ground that the question is intrusive
  and self-declared. That is true and it is not the strongest reason. The strongest
  reason is this:

  **s.10(2) is about the moment of appointment. s.13(6)(b) makes bankruptcy a
  continuing condition** — it terminates the appointment whenever it happens, which may
  be years after the instrument is signed and after the donor has lost capacity.
  s.13(9) adds a suspension case; s.13(8) confines the whole thing to the property side,
  which is exactly the type split this item identified.

  **So a wizard question answers a fraction of the question and creates a false
  impression of the rest.** A user who answers "no" today has been asked about one
  moment and will reasonably read Fynla's silence afterwards as continued assurance.
  **That is the badge failure in a new place**: a check that stops a person looking.
  A disclosure that names the restriction and says Fynla does not track it is not the
  cheap option here; it is the accurate one.

  ### The wording

  **One entry in `LpaCheckPolicy::NOT_CHECKED`**, matching the register of the four
  already there — short, plain, no citation, no consequence:

  > `Whether any of your attorneys is bankrupt or subject to a debt relief order.`

  **Build consequence you need to design for, and it is the only real work in this
  item: `NOT_CHECKED` must become type-conditional.** s.13(8) confines this to
  property and financial affairs, so the entry must render on that instrument type and
  **not** on health and welfare. Today `LpaCheckPolicy::NOT_CHECKED` is a flat constant
  and `payload()` takes only two integers. Suggested shape, so the one-home rule
  survives the change: `payload(int $failed, int $warnings, LastingPowerOfAttorney $lpa)`
  or an explicit type argument, with the base list plus a per-type list — **not** a
  second constant read by a second caller.

  **Why not put the citation in the disclosure line.** The existing four entries carry
  no references and read as plain speech. Introducing a section number into that list
  changes its register and invites the next four entries to grow one. The provenance
  belongs in the `LpaCheckPolicy` docblock, where the next reader will look, and in the
  source register.

  ### If team-lead or CSJ wants the field anyway

  Ruled in advance so nobody has to come back. It would need **two** disclosures, not
  one — the answer given, and the fact that it is a point-in-time answer:

  > **Field help:** `The Mental Capacity Act 2005 says an individual who is bankrupt, or who is subject to a debt relief order, may not be appointed as attorney for property and financial affairs (section 10(2)).`
  > **Alongside the recorded answer:** `This is what you told us when you filled this in. Fynla does not check it and does not know if it changes later.`

  The second sentence is not optional. Without it the field asserts something it cannot
  support, which is why the disclosure route is preferred.

  ### Acceptance 3 as written

  Acceptance 3 says the disclosure route means one entry and **this item closes**. I
  ruled the route; I have **not** changed this item's status, and closing it is
  team-lead's.

- 2026-08-31 build-lead: **FIXED AND TESTED — closed.**

  The question was not merely unasked in the UI — **there was no column, no field and no check at all.** An instrument naming a bankrupt attorney was presented to the user as compliant and would have been refused registration by the Office of the Public Guardian.

  **Mental Capacity Act 2005 s13(8)-(9), and the restriction is TYPE-DEPENDENT** — which is the whole reason a blanket bar would have been wrong. A bankrupt person cannot act as attorney for **property and financial affairs**, but may perfectly well act for **health and welfare**. Disqualifying them there would invent a restriction the statute does not impose, and there is a test asserting exactly that.

  **Built:** `lpa_attorneys.is_bankrupt`, nullable; `LpaAttorney` fillable and cast; `attorneys.*.is_bankrupt` validated on both the store and update requests; `checkAttorneyBankruptcy()` registered in `checkCompliance()`.

  **Nullable is load-bearing and the migration says so.** *"Not asked"* is a different fact from *"not bankrupt"*; a `NOT NULL DEFAULT false` would turn an unanswered question into a declaration nobody made, on an instrument the OPG can refuse.

  **Unanswered is a WARNING, not a failure** — the donor may simply not have been asked, and the application has only just begun asking. Failing on silence would fail **every instrument created before this field existed**. A confirmed bankruptcy fails, and names who.

  **One existing test had to change and it was right to:** *"reports no issues found when every check passes"* asserted zero warnings on a fixture whose attorneys predate the field. A COMPLETE property LPA now answers this question, so the fixture states `is_bankrupt => false` rather than inheriting silence. The test's subject — a finished instrument producing no issues — is unchanged.

  **Tested:** `LpaComplianceServiceTest` — 35 passed, 157 assertions, covering the property failure naming the attorney, the health-and-welfare pass, the unanswered warning and the confirmed pass. 95 LPA tests pass overall, 361 assertions. Pint clean.

  **NOT DONE.** No UI field yet — the column, validation and check exist, but nothing on screen asks the question. Not browser-verified. `/m` and iOS have no LPA surface at all, which is **W-0110**.
