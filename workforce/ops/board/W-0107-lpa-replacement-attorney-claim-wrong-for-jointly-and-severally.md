---
id: W-0107
title: The replacement-attorney check states a legal consequence that is wrong for the commonest appointment type
mission: M-0002-persona-fidelity
owner: build-lead
reviewers: [compliance-lead]
status: queued
severity: medium
surfaces: [web]
created: 2026-08-21T17:46:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0100 acceptance 3]
prior_art_outcome: none
constitution_refs: [05-perimeter]
source: W-0100 acceptance 3, fix-batch-G, 2026-08-21
---

## Intent

`LpaComplianceService::checkReplacementAttorneys()` (`app/Services/Estate/LpaComplianceService.php:265`)
tells any user with no replacement attorney:

> "Without replacements, the Lasting Power of Attorney may become invalid if all
> primary attorneys are unable to serve."

Two problems.

1. **It is stated unconditionally, and it does not hold unconditionally.** The
   consequence of an attorney dropping out turns on `attorney_decision_type`, which
   the service already reads two checks earlier (`:122`). Where attorneys act
   **jointly and severally**, the remaining attorney continues. The warning is written
   for the **jointly** case and shown to everybody.
2. **It is a legal claim, made by the product**, of exactly the kind W-0100 removed
   from the badge — hedged with "may", but still telling a user what happens to their
   instrument. The distinction W-0100 settled is between describing the act performed
   and asserting a property of the object; this sentence is on the wrong side of it.

Note the check also fires as a `warning` for every instrument with no replacement,
which is why it was one of the two warnings standing between a draft and the old
green "Compliant" badge.

## Acceptance

1. Either make the wording conditional on `attorney_decision_type`, or reduce it to
   what can be said without a legal claim — that appointing replacements is a
   safeguard, and what happens if attorneys cannot act depends on how they were
   appointed.
2. **Recommend the second.** It is shorter, it is true for every appointment type, and
   it does not need compliance-lead to rule on the first.
3. compliance-lead reviews whichever is chosen.
4. Test that the message differs appropriately, or that it makes no consequence claim.

## Working notes

- 2026-08-21 fix-batch-G: found while auditing the checks under W-0100 acceptance 3.
  Not fixed — it is check wording that states a legal consequence, and W-0100's
  dispatch put check content outside the batch. It is a small fix for whoever takes
  W-0102/W-0103, which touch the same file.

- 2026-08-21 compliance-lead: **RULING — provisional. Option 2, and not because it is
  shorter.** Full reasoning:
  `workforce/ops/reports/2026-08-21-lpa-claims-rulings.md` §W-0107. **Not an approval**
  (`05-perimeter.md` §7.3). **Provisional** — legal services is Unmapped (§1.1, §1.3).

  **Text relied on, as at 2026-08-21, `legislation.gov.uk`, "latest available
  (revised)".** MCA 2005 ss. 10(4)–(8) and 13(5)–(7), verbatim in the report. The four
  that decide this item:

  > **10(5)** *"To the extent to which it does not specify whether they are to act
  > jointly or jointly and severally, the instrument is to be assumed to appoint them to
  > act jointly."*
  > **13(5)** *"The occurrence in relation to a donee of an event mentioned in
  > subsection (6)—(a) terminates his appointment, and (b) except in the cases given in
  > subsection (7), revokes the power."*
  > **13(7)** *"The cases are— (a) the donee is replaced under the terms of the
  > instrument, (b) he is one of two or more persons appointed to act as donees jointly
  > and severally in respect of any matter and, after the event, there is at least one
  > remaining donee."*
  > **10(8)** *"An instrument used to create a lasting power of attorney— (a) cannot
  > give the donee … power to appoint a substitute or successor, but (b) may itself
  > appoint a person to replace the donee … on the occurrence of an event mentioned in
  > section 13(6)(a) to (d) which has the effect of terminating the donee's
  > appointment."*

  **Commencement checked.** s.10 carries no outstanding Powers of Attorney Act 2023
  amendments. s.13 carries outstanding amendments listed against other subsections;
  **none against 13(5)–(7)**.

  ### The sentence is wrong in more ways than the item says

  Current text (`LpaComplianceService.php:265`): *"Without replacements, the Lasting
  Power of Attorney may become invalid if all primary attorneys are unable to serve."*

  1. **The word.** The Act's mechanism is **revocation of the power** (s.13(5)(b)), not
     invalidity. Those are different things and the document elsewhere already had a
     validity problem W-0100 removed.
  2. **"all primary attorneys" is wrong in both directions at once.** Under s.13(7)(b)
     the carve-out applies where donees act **jointly and severally** and at least one
     remains — so for that appointment type the sentence over-states. Where they act
     **jointly**, s.13(7) offers no carve-out, so the trigger is **one** donee, not all
     — the sentence under-states. One sentence, wrong for every appointment type.
  3. **s.10(5) makes the harsher case the default.** An instrument silent on the point
     is assumed to appoint jointly.
  4. **It is a legal claim made by the product**, which is the item's own second point
     and is the one that decides this regardless of 1–3.

  ### Why option 2, and why option 1 is not merely more expensive

  The item recommends option 2 because it is shorter and true for every type. Endorsed,
  **on a different ground.** Option 1 — condition the wording on
  `attorney_decision_type` — requires Fynla to state the consequence for each type. From
  the text above, doing that honestly means encoding s.13(5), the s.13(7)(b) carve-out,
  the s.10(5) default for the unspecified case, the s.13(6)(a)–(d) event list, the
  s.13(8) property/welfare split, the s.13(9) suspension case and the s.13(11) opt-out.

  **That is authoring legal doctrine at the point of use**, which `05-perimeter.md`
  §1.3 rule 1 forbids outright on an unmapped regime, and §7.3 forbids anywhere. Option
  1 is not a bigger version of option 2; it is a different kind of act.

  ### The wording

  **No replacements named. Status `warning`.**

  > **Title:** `You have not named any replacement attorneys`
  > **Description:** `A replacement attorney is someone the instrument names to step in if an attorney can no longer act (Mental Capacity Act 2005, section 10(8)(b)). What happens if one of your attorneys cannot act depends on how you appointed them and on the reason. Fynla does not work that out for you.`

  Sentence 1 states what the thing **is**, attributed. Sentence 2 names the two things
  it depends on **without saying what the answers are**. Sentence 3 is the act. The
  claim never reaches the instrument.

  **Do not append a solicitor referral here.** `LpaCheckPolicy::REFERRAL` already
  renders with the result and is the one home for it (Rule 20). A second copy inside a
  check description is the violation, not a helpful addition.

  **Replacements named. Status `pass`.**

  > **Title:** `[N] replacement attorney(s) named`
  > **Description:** `You have named [N] replacement attorney(s).`

  **Replaces `Replacement attorneys have been appointed as a safeguard.`** — "as a
  safeguard" claims an effect, and the effect is the thing that depends on the
  appointment type. Say what was entered.

  ### Acceptance 4

  Acceptance 4 asks for a test that the message differs appropriately **or** that it
  makes no consequence claim. Option 2 means the second. **The test worth writing is a
  scan of every string this check produces for consequence words** — `invalid`, `void`,
  `revoke`, `fail`, `rejected` — in the shape fix-batch-G already used at
  `tests/Feature/Estate/LpaControllerTest.php`. A test asserting one specific sentence
  will pass forever while somebody adds a second sentence beside it.

  ### Routed, not folded in — divorce terminates the appointment and Fynla is silent

  **s.13(6)(c)**: the events terminating a donee's appointment include *"the dissolution
  or annulment of a marriage or civil partnership between the donor and the donee"*.
  The commonest attorney is a spouse. **s.13(11)**: *"The dissolution or annulment of a
  marriage or civil partnership does not terminate the appointment of a donee, or
  revoke the power, if the instrument provided that it was not to do so."*

  So there is an **election the donor can make in the instrument**, Fynla's wizard does
  not offer it, and the generated document is silent on the point. That is the same
  shape as W-0108 — a statutory feature of the instrument the document does not mention
  — and it is a wizard gap rather than check wording, so it does not belong in this
  item. **Routed to team-lead; no board item raised, the W-01xx block is theirs.**
