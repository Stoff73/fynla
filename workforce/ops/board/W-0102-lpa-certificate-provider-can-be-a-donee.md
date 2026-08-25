---
id: W-0102
title: The Lasting Power of Attorney generator can produce a certificate provider who is also an attorney — the defect the Mental Capacity Act names
mission: M-0002-persona-fidelity
owner: build-lead
status: done
folded_into: W-0103
claimed_by: fix-batch-G
severity: high
surfaces: [web]
created: 2026-08-21T17:41:00Z
claimed: 2026-08-21T19:40:00Z
blocked_by: []
gate: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0024 (WillDocumentService::isSameParty — the existing one name comparison, ROUTED to), W-0103, W-0151, W-0100 acceptance 1]
prior_art_outcome: route
constitution_refs: [05-perimeter]
source: W-0100 acceptance 1, fix-batch-G, 2026-08-21
---

## Intent

**Mental Capacity Act 2005, Sch 1, para 2(6): "The certificate may not be given by a
person appointed as donee."**

Nothing in Fynla compares the certificate provider to the attorneys.

- `certificate_provider_name` is free text (`app/Http/Requests/Estate/StoreLpaRequest.php:37`,
  `UpdateLpaRequest.php:37`); attorneys are separate `lpa_attorneys` rows.
- `LpaComplianceService::checkCertificateProvider()` (`app/Services/Estate/LpaComplianceService.php:170`)
  checks only that the field is non-empty.
- `resources/js/utils/lpaDocumentRenderer.js` then prints the same person as
  "Attorney 1" in Section 2 and as the certifier in the CERTIFICATE PROVIDER block,
  where they "certify" that they discussed the instrument with the donor, that the
  donor understands it, and that no fraud or undue pressure is being used
  (MCA 2005 Sch 1 para 2(1)(e)).

**This is the W-0024 shape — a party occupying a role they cannot hold — with a
statute behind it rather than an inference.** W-0024 was a mirror will appointing the
testator as her own executor, found only because somebody read the generator.

The failure mode is the one compliance-lead flagged as specific to this instrument:
an instrument departing from what Sch 1 requires is not a defective Lasting Power of
Attorney, it is not one at all, and it fails **at registration** — possibly after the
donor has lost capacity to make another.

## Acceptance

1. A check that fails when `certificate_provider_name` matches any attorney on the
   instrument. Name matching must be forgiving of case and surrounding whitespace;
   decide deliberately how far beyond that to go and record the decision.
2. The failure text explains **why** — the certificate provider cannot be one of the
   attorneys — rather than only that something is wrong. Rule 9: no acronyms.
3. Compose the wording through `app/Services/Estate/LpaCheckPolicy.php` if it is
   shared wording; check descriptions themselves stay in the service (Rule 20).
4. Tests in `tests/Unit/Services/Estate/LpaComplianceServiceTest.php`.
5. **compliance-lead reviews the check's wording before it ships** — it states what
   the law requires, which is above build-lead's competence line.

## Working notes

- 2026-08-21 fix-batch-G: verified by reading, not fixed. Adding statutory validation
  is new behaviour needing a compliance read of the wording, and W-0100's dispatch
  scoped the fix to the overclaim. Reproduction: create a Lasting Power of Attorney
  with an attorney named "John Smith" and `certificate_provider_name` "John Smith";
  `GET /api/estate/lpa/{id}/compliance` reports no issue.

- 2026-08-21 compliance-lead: **WORDING RULING — provisional.** Delivered ahead of the
  other six because fix-batch-G reaches this item next. Full reasoning and the sources
  table: `workforce/ops/reports/2026-08-21-lpa-claims-rulings.md` §W-0102/W-0103.
  **This is not an approval** (`05-perimeter.md` §7.3) and does not determine that
  generating or assessing a Lasting Power of Attorney is permissible.

  **Provisional on its face.** Legal services is an **Unmapped** regime on the regime
  map installed today (`05-perimeter.md` §1.1, citing W-0019, W-0024 and this audit's
  parent W-0100). Per §1.3 I apply what I can reach, name what I cannot, and mark the
  ruling provisional. What I can reach is the text of the instruments and the wording
  test W-0100 settled. What I cannot reach is whether any of this is *required* of
  Fynla — that is §7.3's line and it is not crossed below.

  **Text relied on, and as at when.** Two instruments, both read on **2026-08-21**,
  both **"latest available (revised)"** on `legislation.gov.uk`:

  1. **Mental Capacity Act 2005, Sch 1 para 2(6)** — *"The certificate may not be given
     by a person appointed as donee."* **Commencement checked, as the perimeter's
     standing warning requires (§1.1).** The Powers of Attorney Act 2023 (2023 c. 42)
     amendments outstanding against Schedule 1 are listed against **paras 4(1)(a), 4(2),
     4(3), 5, 7, 8, 9, 10, 11, 13 and 14**. **Paras 1 and 2 are not among them.** So
     para 2(6) as quoted is the text in force and W-0100's trap does not bite this item.
     It does still bite W-0109 and anything touching registration.
  2. **Lasting Powers of Attorney, Enduring Powers of Attorney and Public Guardian
     Regulations 2007 (SI 2007/1253), reg 8(3)(b)** — a person is **disqualified** from
     giving the certificate if that person is *"a donee of that power"*. Four amendments
     applied (latest 2 Dec 2019); **none outstanding**; instrument not revoked.

  This is the secondary-legislation limb of the same prohibition, and it matters here
  for a reason beyond corroboration — see the routed finding at the end.

  ### The wording

  The test is the one this batch already found and must not be replaced with another:
  **the claim describes the act Fynla performed, not a property of the instrument.**
  Nothing below says valid, invalid, compliant, sufficient, will be rejected, or will
  fail at registration.

  **Check id:** `party_roles` (one check, W-0102 + W-0103 together, per team-lead).

  **Fail — certificate provider also named as an attorney.**

  > **Title:** `Your certificate provider is also named as an attorney`
  > **Description:** `You entered [NAME] as your certificate provider and as an
  > attorney. The Mental Capacity Act 2005 does not allow the certificate to be given
  > by someone appointed as an attorney (Schedule 1, paragraph 2(6)), and the 2007
  > regulations disqualify an attorney from giving it (regulation 8(3)(b)). Check which
  > person you meant in each role.`

  **Pass — deliberately not "no conflict".**

  > **Title:** `The names in each role are different`
  > **Description:** `The certificate provider and attorney names you entered do not
  > match each other.`

  The pass wording is load-bearing and is the half most likely to get "tidied" later.
  A string comparison that finds no match is **not** evidence that no conflict exists,
  and the pass must not be written as though it were. `The names ... do not match each
  other` is the act. `No conflict` is the object.

  ### The reliability limit — one home, not one line per check

  `certificate_provider_name` is free text; attorneys are rows. The comparison is
  therefore string matching over typed input, and **"Dave Jones" against "David Jones"
  passes**. A check defeated by a spelling must not be described to the user as though
  it cannot be.

  **Do not solve this by qualifying each message** — four hedged sentences is worse
  copy and four places to drift. Put it once, where the disclosure already lives
  (Rule 20, and trunk §4's at-the-point-of-the-result requirement):

  **Add to `LpaCheckPolicy::NOT_CHECKED`:**

  > `Whether two people whose names you typed differently are the same person, or two people with the same name are different people. We compare only the names you entered.`

  That covers W-0102, all three of W-0103, and any future name comparison, from one
  place. Ruled **within competence**: it is a statement about what the software did.

  ### Matching W-0024's register

  Asked to match, and it does on the axis that matters — a check that fires when the
  conflict is present and clears when corrected, worded to explain **why** rather than
  only that something is wrong. **One deliberate divergence, stated rather than
  hidden.** `WillDocumentService::EXECUTOR_IS_TESTATOR_MESSAGE` opens *"A will cannot
  appoint its own testator as executor"* — a bare rule with no source, which was
  approved before today's act-not-object test existed. Here the equivalent sentence is
  **attributed and paragraph-referenced**, because the source exists and naming it is
  what makes the sentence a citation rather than Fynla's own pronouncement.
  **`WillTypePolicy` / `WillDocumentService` copy is not mine to reopen and I have not**
  — F-0003 holds those files and the divergence is not a defect in them.

  ### Name matching — how far to go (acceptance 1)

  Not a legal question, so not mine to settle, but the compliance-relevant half is:
  **whatever is chosen must be describable truthfully in the disclosure above.**
  `WillDocumentService::isSameParty()` is deliberately case-and-whitespace only, with
  the recorded reasoning that two people can share a name and guessing at nicknames in
  a legal document would be worse. That reasoning holds here and reusing it keeps one
  behaviour rather than two. Fuzzy matching would need the disclosure line rewritten.

  ### Routed, not folded in — reg 8(3) disqualifies more than attorneys

  Reading reg 8 to source this ruling turned up **seven further disqualifications the
  application never mentions**, two of which Fynla can see in its own data:

  - **8(3)(a) — a family member of the donor.** The donor's most likely
    two-years-known person is their spouse or their child. `checkCertificateProviderKnownYears()`
    currently tells the user the requirement is two years, which reads as the whole
    test, and the wizard offers nothing else.
  - **8(3)(c) — a donee of any other lasting power of attorney or enduring power of
    attorney executed by the donor.** Fynla holds both instruments for one user, so
    the health and welfare attorney certifying the property instrument is a state the
    application can construct and can detect.
  - Also 8(3)(d) family member of an attorney, (e) director or employee of a trust
    corporation acting as attorney, (f) business partner or employee of donor or
    attorney, (g) care-home owner, director, manager or employee where the donor lives,
    (h) their family member.

  **"Family member" is not defined in reg 8(4)**, which defines only *care home*,
  *registered health care professional* and *registered social worker*. That is a §6
  question, not a build decision, and it is in the report.

  **Not added to this item's acceptance and no board item raised** — team-lead holds
  the W-01xx block and status changes are not mine. Routed to team-lead with a
  recommendation.

- 2026-08-21 fix-batch-G: **FOLDED INTO W-0103 and delivered there.** One check,
  `LpaComplianceService::checkPartyRoles()`, covers this item and the others — building
  it separately would have been the parallel mechanism the whole W-0100 family exists to
  remove. The compliance ruling above is unchanged and is the authority for the wording
  that shipped; it stays here rather than being copied. **No separate work remains.**
  See W-0103's working notes and `workforce/branches/fixes/F-0008-batch-g-lpa.md` §4.
