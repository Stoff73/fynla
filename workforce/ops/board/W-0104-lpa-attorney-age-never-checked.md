---
id: W-0104
title: An attorney's age is never checked — a child can be appointed attorney on a Lasting Power of Attorney
mission: M-0002-persona-fidelity
owner: build-lead
status: done
severity: medium
surfaces: [web]
created: 2026-08-21T17:43:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0100 acceptance 1]
prior_art_outcome: none
constitution_refs: [05-perimeter]
source: W-0100 acceptance 1, fix-batch-G, 2026-08-21
---

## Intent

**Mental Capacity Act 2005, s.10(1)(a): a donee must have reached 18.**

`LpaComplianceService::checkDonorAge()` (`app/Services/Estate/LpaComplianceService.php:63`)
checks the **donor's** age and nothing else. There is no equivalent for attorneys.

Validation permits any past date: `attorneys.*.date_of_birth => 'nullable|date|before:today'`
(`app/Http/Requests/Estate/StoreLpaRequest.php:49`). A date of birth two years ago
passes, and `lpaDocumentRenderer.js` prints "Attorney 1: Tommy Smith, born 4 June 2024".

The requirement is statutory, the data is already collected, and the check is one
comparison — this is a gap rather than a hard problem.

## Acceptance

1. A check that fails when any attorney's date of birth puts them under 18.
2. Decide deliberately what happens when `date_of_birth` is null — it is nullable, so
   most attorneys will have none. A blanket failure would fire on almost every
   instrument; a silent pass hides the case the statute names. Record the choice.
3. Rule 9 in the message. Tests both sides.

## Working notes

- 2026-08-21 fix-batch-G: verified by reading, not fixed — new statutory validation,
  outside W-0100's dispatched scope.

- 2026-08-21 compliance-lead: **WORDING RULING — provisional.** Full reasoning:
  `workforce/ops/reports/2026-08-21-lpa-claims-rulings.md` §W-0104. **Not an approval**
  (`05-perimeter.md` §7.3). **Provisional** — legal services is Unmapped (§1.1, §1.3):
  I can reach the text of the provision and the wording test; I cannot reach whether
  any of this is required of Fynla, and have not asserted it.

  **Text relied on, as at 2026-08-21, `legislation.gov.uk`, "latest available
  (revised)". MCA 2005 s.10(1), verbatim:**

  > *"A donee of a lasting power of attorney must be—(a) an individual who has reached
  > 18, or (b) if the power relates only to P's property and affairs, either such an
  > individual or a trust corporation."*

  **Commencement checked.** The outstanding Powers of Attorney Act 2023 (2023 c. 42)
  amendments listed on the s.10 page bear on ss. 4C, 21ZA, 36(2)(da), 42, 58(4)(ca) and
  various Schedule 1 paragraphs — **s.10 is not among them.** The one textual amendment
  to s.10 (F1, to subsection (2)) has been in force since 1 October 2012. The text above
  is the text in force.

  **This item cites s.10(1)(a) and stops there. The full subsection matters** —
  see the trust-corporation note below, which changes how the check should be written.

  ### What may be said about why the question is asked

  Asked for the wizard-side framing. **The line is between reporting what the provision
  says (a citation, which §7.3 permits) and asserting a consequence for this user's
  instrument (which it forbids).** Everything below is on the first side.

  **Field help, wizard:**

  > `The Mental Capacity Act 2005 says an attorney must be an individual who has reached 18 (section 10(1)(a)). We use this date to check that.`

  Two sentences, and the split is the whole point: sentence one attributes, sentence two
  says what Fynla does with it. Neither says what happens if the date is wrong.
  **Do not add a third sentence saying what happens.** That is the consequence, it
  depends on facts Fynla does not hold, and it is the sentence W-0107 exists to remove
  from the sibling check.

  ### Acceptance 2 — the null date of birth. **Three states, not two.**

  **This is my ruling and it is the substantive part of this item.** `fail` and `pass`
  are both wrong for a missing date, for the same reason the green badge was wrong:
  each asserts a finding Fynla does not have. A `fail` asserts the attorney is under 18.
  A `pass` asserts they are not. Fynla knows neither.

  The honest states are three, and the service already has the vocabulary for all three:

  | Data | Status | What it asserts |
  |---|---|---|
  | Date entered, under 18 | `fail` | a finding Fynla has |
  | Date entered, 18 or over | `pass` | a finding Fynla has |
  | **No date entered** | **`warning`** | **that it could not check** |

  Third state:

  > **Title:** `We could not check every attorney's age`
  > **Description:** `You have not entered a date of birth for [NAMES]. The Mental Capacity Act 2005 says an attorney must be an individual who has reached 18 (section 10(1)(a)). Add their date of birth if you would like us to check it.`

  Under-18 finding:

  > **Title:** `[NAME] is under 18`
  > **Description:** `The date of birth you entered for [NAME] makes them [N] years old. The Mental Capacity Act 2005 says an attorney must be an individual who has reached 18 (section 10(1)(a)).`

  Pass:

  > **Title:** `Every attorney's age is recorded as 18 or over`
  > **Description:** `The dates of birth you entered put all [N] attorneys at 18 or over.`

  Note the pass names **what was compared** (the dates you entered), not a property of
  the appointment. Same rule as W-0102's pass wording, same reason.

  ### The trust corporation — a real limit, not a hypothetical

  **s.10(1)(b) permits a trust corporation as attorney where the power relates only to
  property and affairs.** A trust corporation has no date of birth, so a naive
  "no date entered ⇒ ask for one" would harass a user in a case the Act allows.

  **Checked before flagging it, and it does not bite today:** `lpa_attorneys` records
  `full_name` and `date_of_birth` and has **no entity-type column**
  (`app/Models/Estate/LpaAttorney.php:17-29`). Fynla cannot represent a trust
  corporation attorney at all, so today every attorney row is a person and the missing
  date is simply missing.

  **So: do not build a trust-corporation branch now.** Record it instead — the
  `warning` copy above already survives the case without lying (it says the date is
  absent, not that the attorney is unqualified), and if an entity type is ever added,
  this note is where the reader finds out why it matters. Raising a board item for a
  case the schema cannot express would be building for a hypothetical.

  ### Not ruled, because not mine

  - The `severity` on this item. Unchanged.
  - Whether the wizard should make `date_of_birth` required. That is a product call and
    it has a real cost — a donor may genuinely not know their attorney's date of birth,
    and forcing a guess into a document is worse than a `warning` that says so.

- 2026-08-31 build-lead: **FIXED AND TESTED — closed.**

  **A child could be appointed attorney.** `LpaComplianceService` ran `checkDonorAge()` and had no equivalent for attorneys, though `lpa_attorneys.date_of_birth` is captured for every one of them. The instrument was presented to the user as compliant right up to the point the Office of the Public Guardian refused to register it.

  **Mental Capacity Act 2005 s10(1)(a) sets both minimum ages — the donor's and the attorney's — and that is most of why this survived:** the donor check reads as though it covers "the age requirement", so a reader auditing the service for age handling finds one and stops.

  `checkAttorneyAges()` is now registered beside `checkDonorAge()` in `checkCompliance()` and names every attorney who fails, rather than reporting a bare count — an instrument with four attorneys needs to say which one.

  **Three judgements recorded at the line:**
  - **A missing date of birth FAILS.** An attorney whose age cannot be established is exactly the case the check exists for; treating unknown as acceptable would reproduce the defect for anyone who left the field blank.
  - **An empty attorney list PASSES here**, because `checkAtLeastOneAttorney()` owns the "none appointed" failure and reporting it twice would double-count it.
  - Under-18s and undated attorneys are reported separately, because they are different problems with different remedies.

  **Tested:** `LpaComplianceServiceTest` — 31 passed, 148 assertions, including a 12-year-old attorney named in the failure text, an adult attorney passing, and a null date of birth failing rather than passing quietly. 88 LPA tests pass overall. Pint clean.

  Sibling items still open on this service: **W-0105** (no bankruptcy question), **W-0106** (professional certificate provider), **W-0107**, **W-0108**, **W-0109**, and **W-0100**, which notes the whole generator has never been reviewed.
