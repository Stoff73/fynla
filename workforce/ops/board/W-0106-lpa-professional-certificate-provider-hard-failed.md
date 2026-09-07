---
id: W-0106
title: A professional certificate provider is failed by the two-year rule, despite the field for the professional route already existing
mission: M-0002-persona-fidelity
owner: build-lead
reviewers: [compliance-lead]
status: done
severity: medium
surfaces: [web]
created: 2026-08-21T17:45:00Z
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

**A false negative, with the field that prevents it already in the schema.**

`LpaComplianceService::checkCertificateProviderKnownYears()` (`app/Services/Estate/LpaComplianceService.php:192`)
returns `fail` whenever `certificate_provider_known_years < 2`, unconditionally:

> "Your certificate provider has known you for 1 year(s). The minimum is 2 years."

But `LastingPowerOfAttorney` already carries **`certificate_provider_professional_details`**
(`app/Models/Estate/LastingPowerOfAttorney.php:51`) — the model knows there is a
professional route to being a certificate provider, and the check ignores it
completely. A donor whose certificate provider is their solicitor or their doctor is
told their Lasting Power of Attorney has a failure it does not have.

This is the "read the enforcing layer" shape in reverse: the schema records a
distinction the enforcing code never reads.

## Acceptance

1. **compliance-lead establishes the prescribed descriptions of a certificate
   provider first** — Mental Capacity Act 2005 Sch 1 para 2(1)(e) requires "a person
   of a prescribed description", and the descriptions are in secondary legislation
   that fix-batch-G did not read and would not rely on. Until that is established,
   nobody should encode a two-route rule.
2. Then: when `certificate_provider_professional_details` is populated, the two-year
   requirement is not asserted as failed.
3. Whatever cannot be established becomes an entry in `LpaCheckPolicy::NOT_CHECKED`
   rather than a check — claiming less is always available.
4. Tests for both routes.

## Working notes

- 2026-08-21 fix-batch-G: **found but deliberately not fixed.** The obvious fix —
  "skip the two-year rule when the professional field is set" — requires asserting
  what the prescribed descriptions are, which is a legal determination and above the
  competence line. Flagged rather than guessed.

- 2026-08-21 compliance-lead: **RULING — provisional. fix-batch-G's refusal was correct
  and is now LIFTED: I read the instrument.** Full reasoning:
  `workforce/ops/reports/2026-08-21-lpa-claims-rulings.md` §W-0106. **Not an approval**
  (`05-perimeter.md` §7.3). **Provisional** — legal services is Unmapped (§1.1, §1.3).

  Acceptance 1 asked compliance-lead to establish the prescribed descriptions before
  anyone encodes a two-route rule. **Established, from the instrument, with the source
  named and dated. Nothing below is reasoned from what seems sensible.**

  ### The source

  **The Lasting Powers of Attorney, Enduring Powers of Attorney and Public Guardian
  Regulations 2007 (S.I. 2007/1253), regulation 8** — "Persons who may provide an LPA
  certificate". Read on **`legislation.gov.uk`, 2026-08-21, "latest available
  (revised)"**. Four amendments applied, the most recent in force **2 December 2019**.
  **No amendments outstanding against regulation 8; the instrument is not revoked.**
  This is the secondary legislation Sch 1 para 2(1)(e) points at when it requires the
  certificate to be given by *"a person of a prescribed description"*.

  **reg 8(1), verbatim:**

  > *"Subject to paragraph (3), the following persons may give an LPA certificate—
  > (a) a person chosen by the donor as being someone who has known him personally for
  > the period of at least two years which ends immediately before the date on which
  > that person signs the LPA certificate;
  > (b) a person chosen by the donor who, on account of his professional skills and
  > expertise, reasonably considers that he is competent to make the judgments necessary
  > to certify the matters set out in paragraph (2)(1)(e) of Schedule 1 to the Act."*

  **reg 8(2), verbatim:**

  > *"The following are examples of persons within paragraph (1)(b)— (a) a registered
  > health care professional; (b) a barrister, solicitor or advocate called or admitted
  > in any part of the United Kingdom; (c) a registered social worker; or (d) an
  > independent mental capacity advocate."*

  reg 8(4) defines *registered health care professional* by reference to s.25(3) of the
  National Health Service Reform and Health Care Professions Act 2002, and *registered
  social worker* by reference to Social Work England, Social Care Wales, the Scottish
  Social Services Council and the Northern Ireland Social Care Council.

  ### Three findings, and each one constrains the build differently

  **1. There are two routes, and the current check knows about one.** Confirmed with a
  source, which is what acceptance 1 required. The unconditional `fail` at
  `LpaComplianceService.php:192` is a false negative against route (b).

  **2. reg 8(2) is a list of EXAMPLES, not a closed class.** The regulation says *"The
  following are examples of persons within paragraph (1)(b)"*. **Do not encode "is a
  solicitor or a doctor" as the test.** A dropdown of the four examples would be
  narrower than the regulation and would fail people the regulation reaches. If a
  structured field is wanted, it must permit "someone else" without penalty.

  **3. The decisive finding, and the one that shapes the whole fix: route (b) turns on
  the certificate provider's own judgement.** The test in reg 8(1)(b) is that the
  person *"reasonably considers that he is competent"*. That is a state of mind of a
  third party Fynla never communicates with. **Fynla cannot verify route (b) at all.**

  So the fix is **not** "check the other route". It is: **stop asserting a failure
  Fynla does not have.** That is acceptance 3's "claiming less is always available",
  reached from the instrument rather than from caution.

  ### The wording

  Rule 20 — check descriptions stay in the service; the `NOT_CHECKED` addition goes in
  `LpaCheckPolicy`. Rule 9 — nothing abbreviated.

  **(a) `certificate_provider_professional_details` populated. Status `pass`.**

  > **Title:** `Professional grounds are recorded for your certificate provider`
  > **Description:** `You have recorded professional grounds for your certificate provider. The 2007 regulations describe this as one of two routes to giving the certificate (regulation 8(1)(b)). That route rests on their own judgement that they are competent to give it, which Fynla cannot check.`

  A `pass` here means only "this check found nothing to raise", which is what
  `LpaCheckPolicy`'s vocabulary already says a pass is. The second sentence keeps it
  from being read as verification.

  **(b) Not populated, and `certificate_provider_known_years >= 2`. Status `pass`.**

  > **Title:** `Your certificate provider has known you for [N] years`
  > **Description:** `You entered that they have known you personally for [N] years. The 2007 regulations describe this as one of two routes to giving the certificate (regulation 8(1)(a)). The regulation counts the two years ending on the day they sign the certificate; Fynla has the number you entered, not the dates.`

  **Replaces `The minimum 2-year relationship requirement is met.`** — that sentence
  asserts a requirement is met, which is the object claim W-0100 removed.

  **(c) Not populated, `known_years` under 2 or null. Status `warning`, NOT `fail`.**

  > **Title:** `Check who can give your certificate`
  > **Description:** `You entered that your certificate provider has known you for [N] year(s), and no professional grounds are recorded for them. The 2007 regulations describe two routes to giving the certificate: someone who has known you personally for at least two years, or someone who considers themselves competent on account of their professional skills and expertise (regulation 8(1)). Neither is recorded here.`

  **The status change from `fail` to `warning` is part of the ruling, not a detail.**
  `fail` asserts Fynla found something wrong. What Fynla has found is that neither
  route is *recorded* — an absence in its own data, not a finding about the person.
  `Neither is recorded here` is the act; `The minimum is 2 years` is the object, and it
  is wrong besides.

  Where `known_years` is null the same wording holds with `[N] year(s)` replaced by
  `you have not said how long they have known you`.

  ### Also add to `LpaCheckPolicy::NOT_CHECKED` — the half neither route covers

  > `Whether anything disqualifies your certificate provider from giving the certificate.`

  This is reg 8(1)'s opening words — *"Subject to paragraph (3)"* — and reg 8(3)
  disqualifies eight categories **regardless of which route applies**, including a
  family member of the donor. See the routed finding on W-0102; team-lead holds the
  decision on whether that becomes its own item. **The disclosure line above is worth
  adding either way**, because without it a `pass` on this check reads as clearance.

  ### What I did NOT establish

  - **Whether Fynla is required to check any of this.** §7.3. Not asserted anywhere
    above.
  - **What "reasonably considers" requires of the person.** Not answerable from the
    regulation and not needed — Fynla cannot observe it either way.
  - **Whether `certificate_provider_professional_details` being non-empty is adequate
    evidence of route (b).** It is a free-text field. Fynla is recording what the user
    typed and the wording above says exactly that and no more.

- 2026-08-31 build-lead: **FIXED AND TESTED — closed.**

  **There are TWO routes to being a certificate provider, and the two-year rule belongs to only one of them.** The Lasting Powers of Attorney Regulations 2007 admit either someone who has known the donor **personally for at least two years**, or a person with **relevant professional skills** — a GP, a solicitor, a social worker — for whom no prior relationship is required at all. A solicitor met last month is a perfectly good certificate provider.

  `checkCertificateProviderKnownYears()` applied the two-year rule unconditionally, so **the professional route was failed** — while `certificate_provider_professional_details` **already existed as a column to record it**. That is what makes this an oversight rather than a decision: the field for the exception was there and the exception was not.

  Now: a populated `certificate_provider_professional_details` passes on that basis and quotes the detail back, so the user can see WHICH capacity was accepted. The years question does not arise on that route — null years is a warning on the personal route only.

  **The exception does not swallow the rule.** A provider with no professional details and one year known still fails, and the message now names the alternative — *"unless they are acting in a professional capacity — a GP, solicitor or similar — in which case record their professional details instead"* — so a user failing on the personal route learns the other route exists. A test asserts that failure still occurs.

  **Tested:** `LpaComplianceServiceTest` — 43 passed, 172 assertions, covering the professional pass with details quoted, the professional route with null years, and the personal route still failing at one year. Pint clean.

  **NOT DONE.** No UI field for the professional details, and no `compliance-lead` review.
