---
id: W-0103
title: Nothing stops a Lasting Power of Attorney donor being their own attorney or their own certificate provider, or one person holding two attorney roles
mission: M-0002-persona-fidelity
owner: build-lead
status: handoff
handoff_to: quality-lead
claimed_by: fix-batch-G
branch: branches/fixes/F-0008-batch-g-lpa.md
folds: [W-0102, W-0151]
severity: high
surfaces: [web]
created: 2026-08-21T17:42:00Z
claimed: 2026-08-21T19:40:00Z
blocked_by: []
gate: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0024, W-0102]
prior_art_outcome: route
constitution_refs: [05-perimeter]
source: W-0100 acceptance 1, fix-batch-G, 2026-08-21
---

## Intent

The same "nothing compares the names" gap as W-0102, in three more places. Extends
whatever check W-0102 builds rather than adding a parallel one.

1. **The donor can be their own attorney.** `donor_full_name` is never compared to any
   `lpa_attorneys.full_name`. The rendered document then says "I, Patricia Bennett …
   appoint the attorney(s) named below" and lists "Attorney 1: Patricia Bennett".
   A Lasting Power of Attorney confers authority on somebody else (MCA 2005 s.9(1)),
   so the instrument names the same person on both sides of a conferral. **Fynla does
   not say whether that is permitted** — see the compliance ruling below; the express
   prohibition was looked for and not found, so this is recorded as a contradiction in
   what the user typed, not as a rule.
2. **The donor can be their own certificate provider.** The certificate certifies that
   *the donor* understands the instrument and is not under pressure (MCA 2005 Sch 1
   para 2(1)(e)), so the same person would be certifying about themselves. **Again
   described, not prohibited**, for the same reason.
3. **One person can be both a primary and a replacement attorney, or appear twice in
   either list.** `LpaService::syncAttorneys()` (`app/Services/Estate/LpaService.php:151`)
   deletes and recreates rows with no comparison. The document then reads "If any of
   the above-named attorneys are unable or unwilling to act, I appoint the following
   replacement attorney(s): Replacement Attorney 1: Harold Bennett" where Harold
   Bennett is Attorney 1 — he replaces himself.

Item 3 is the one W-0100's acceptance 1 called "replacement attorneys inconsistent
with the primary appointment".

## Acceptance

1. Each of the three conflicts is detected and reported.
2. Built as an extension of the W-0102 check, not a second mechanism (Rule 20).
3. Wording reviewed by compliance-lead where it states a legal consequence; item 3
   can be stated as a plain contradiction without a legal claim.
4. Tests per conflict.

## Working notes

- 2026-08-21 fix-batch-G: verified by reading, not fixed. `blocked_by: [W-0102]` is a
  sequencing preference, not a hard block — doing them together is better than doing
  them apart, because they share the name-matching decision.

- 2026-08-21 compliance-lead: **WORDING RULING — provisional.** Folded with W-0102 at
  team-lead's direction; read W-0102's note first — the sources, the commencement
  check, the act-not-object test, the pass-wording point and the one-home reliability
  disclosure all apply here unchanged and are not repeated. Full reasoning:
  `workforce/ops/reports/2026-08-21-lpa-claims-rulings.md` §W-0102/W-0103.
  **Not an approval** (`05-perimeter.md` §7.3). **Provisional** — legal services is an
  Unmapped regime (§1.1, §1.3).

  **The three conflicts do not get the same treatment, and that is the ruling.** Two of
  them I can attribute to an instrument; one I can only describe. Writing all three in
  the same register would manufacture a legal claim for the case that has none.

  ### Conflict 3 — one person as both attorney and replacement attorney. **Cleared, no legal claim needed.**

  This item's own acceptance 3 already says it can be stated as a plain contradiction.
  Agreed, and that is the whole of what may be said.

  > **Title:** `[NAME] is named as both an attorney and a replacement attorney`
  > **Description:** `A replacement attorney steps in if an attorney can no longer act,
  > so someone in both roles would be replacing themselves. Check which role you meant.`

  Same shape for one person appearing twice in either list:

  > **Title:** `[NAME] is named twice`
  > **Description:** `You entered [NAME] more than once in your list of attorneys.
  > Check whether you meant two different people.`

  No citation, because none is needed — this is Fynla reporting a contradiction in what
  the user typed. **Do not attach one.** A statutory reference here would assert a rule
  that does not exist and is the failure this ruling exists to avoid.

  ### Conflict 1 — the donor named as their own attorney. **Describe, do not prohibit.**

  This item's Intent currently reads: *"A Lasting Power of Attorney confers authority on
  somebody else (MCA 2005 s.9(1)); a person cannot confer it on themselves."*

  **The first half is a citation and is correct. The second half is a determination of
  what the law requires and is above my line — and above build-lead's.** I checked for
  an express prohibition and **did not find one**: MCA 2005 s.9 and s.10 (both read
  2026-08-21, latest available revised) say what a donee must be — *"an individual who
  has reached 18, or if the power relates only to P's property and affairs, either such
  an individual or a trust corporation"* (s.10(1)) — and **neither excludes the donor in
  terms**. Nor does the disqualification list at SI 2007/1253 reg 8(3), which is about
  the certificate rather than the appointment. **Absence of a prohibition in the
  provisions I read is not evidence there is none** — that inference is exactly the
  §7.3 move I may not make. So: no citation, and no assertion either way.

  What survives is the description, which is sourced and sufficient:

  > **Title:** `You are named as your own attorney`
  > **Description:** `A Lasting Power of Attorney is the record of one person giving
  > another the authority to act for them (Mental Capacity Act 2005, section 9(1)), so
  > naming yourself as your own attorney is a contradiction Fynla cannot resolve for
  > you. Check who you meant.`

  `is a contradiction Fynla cannot resolve for you` is the act. `cannot confer it on
  themselves` is the object, and it is not sayable on what I read. **Please amend this
  item's Intent** — I have not edited it, per the dispatch.

  ### Conflict 2 — the donor named as their own certificate provider. **Same treatment, and the reason is worth keeping.**

  I expected to clear this one by citation and could not, which is itself the finding.

  **SI 2007/1253 reg 8(3) lists eight disqualifications and the donor is not among
  them** — it disqualifies the donor's family members (8(3)(a)), attorneys under this
  or any other power (8(3)(b), (c)), their family (8(3)(d)), business partners and
  employees (8(3)(f)), care-home staff (8(3)(g), (h)) and trust-corporation staff
  (8(3)(e)). reg 8(1) frames the certificate provider as *"a person chosen by the
  donor"*, and Sch 1 para 2(1)(e) requires the certificate to state that **the donor**
  understands the instrument and that no fraud or undue pressure is being used.

  A reader will find that combination strongly suggestive. **Suggestive is not
  sayable.** Concluding that a list which does not name the donor nonetheless excludes
  the donor is a determination, and asserting a prohibition that turns out not to exist
  is the same failure as the badge W-0100 removed, pointing the other way. So:

  > **Title:** `You are named as your own certificate provider`
  > **Description:** `The certificate is a statement by someone else that you
  > understand this document and are not under pressure to make it (Mental Capacity Act
  > 2005, Schedule 1, paragraph 2(1)(e)). Naming yourself is a contradiction Fynla
  > cannot resolve for you. Check who you meant.`

  The citation supports **what the certificate is**, which para 2(1)(e) states on its
  face. It is not offered as authority that the donor is disqualified, and the sentence
  is built so it cannot be read that way.

  **§6 question written, not answered** — in the report, and it is the one a lawyer
  should be handed with this item attached.

  ### Severity

  Not mine to set and I have not changed it. **Stated for whoever does:** conflict 2 is
  the one where Fynla's own wording actively points the user at the wrong test, because
  `checkCertificateProviderKnownYears()` presents two years' acquaintance as the
  requirement. That is the same shape as the routed reg 8(3) finding on W-0102 and I
  would sequence them together.

- 2026-08-21 team-lead: **Intent amended on compliance-lead's instruction — the
  consequence clause is removed, the citations kept.** The Intent previously asserted
  *"a person cannot confer it on themselves"* and that *"the donor cannot give it about
  themselves"*. Both were determinations of what the law requires, which is above
  build-lead's line and above compliance's own (§7.3). Compliance went looking for the
  express prohibition and **did not find one** — s.9 and s.10 say what a donee must be
  without excluding the donor in terms, and SI 2007/1253 reg 8(3)'s eight
  disqualifications reach the donor's family members but **not the donor**. Suggestive is
  not sayable. Both conflicts are now recorded as contradictions in what the user typed,
  with the citations describing what the instrument and the certificate *are*.
  **This correction is the item practising what it exists to enforce** — a defect report
  about the application overclaiming had itself overclaimed, in the same direction, on
  the same instrument.

- 2026-08-21 fix-batch-G (build-lead): **done. W-0102 and W-0151 are folded into this
  item** — one check, one mechanism, per team-lead. This is the general case and it
  covers the specific ones; both are marked `done` with `folded_into: W-0103` and their
  compliance rulings stay readable in their own files. Severity raised medium → high,
  because this item now carries the two statutory limbs. Branch document:
  `workforce/branches/fixes/F-0008-batch-g-lpa.md` §4.

  **Prior art: `route`, not `none` — and it changed the design.** `WillDocumentService::isSameParty()`
  (`:698`, public static) already exists and its own docblock calls it *"the one home for
  that question — the mirror swap, the executor-is-testator block and Fyn's create_will
  handler all ask it here so they cannot drift apart (Rule 20)"*. **So the name
  comparison routes to it rather than being written a second time.** That is also what
  compliance recommended on W-0102, and it is why the case/whitespace behaviour is
  inherited rather than re-decided: two people can share a name, and guessing at
  nicknames in a legal document would be worse than the bug. `WillDocumentService` is
  `fix-batch-B`'s file and **was not edited** — calling a public static is not a change
  to it.

  **What was built.** One method, `LpaComplianceService::checkPartyRoles()`, detecting
  five conflicts and returning a **list** of results rather than one:
  | Key | Conflict | Status |
  |---|---|---|
  | `party_roles_certificate_provider_attorney` | certificate provider is an attorney here (W-0102) | `fail` |
  | `party_roles_certificate_provider_other_instrument` | certificate provider is an attorney on the donor's other instrument (W-0151, reg 8(3)(c)) | `fail` |
  | `party_roles_donor_attorney` | donor named as their own attorney | `warning` |
  | `party_roles_donor_certificate_provider` | donor named as their own certificate provider | `warning` |
  | `party_roles_attorney_and_replacement` / `party_roles_duplicate_attorney` | one person in two attorney roles, or entered twice | `warning` |

  **A list, not a single result, is a deliberate design decision.** Compliance specified
  one check id; a single result would have shown the first conflict and hidden the rest
  until it was fixed, which is a silent omission and would have made a user correct one
  thing at a time. Distinct keys are also required — `LpaComplianceChecklist.vue` uses
  `:key="check.key"` in a `v-for`. A test asserts every conflict is reported at once and
  that no key is duplicated.

  **The two statuses are the ruling made mechanical, and this is a build decision I want
  looked at.** The certificate-provider limbs `fail` because an instrument prohibits
  them (MCA 2005 Sch 1 para 2(6); SI 2007/1253 reg 8(3)(b), (c)). The rest `warning`,
  because compliance went looking for an express prohibition on a donor naming themselves
  and **did not find one** — s.10(1) says what a donee must be without excluding the
  donor, and reg 8(3)'s eight disqualifications reach the donor's family but not the
  donor. **Reporting those as failures would assert a rule that may not exist — the same
  overclaim as the badge W-0100 removed, pointing the other way.** Compliance ruled on
  the wording, not the status; mapping the same distinction onto the status is mine, and
  it is flagged for their read.

  **Every user-facing string is compliance's, verbatim.** No paraphrase, no widening. The
  pass is `The names in each role are different` / `The certificate provider and attorney
  names you entered do not match each other.` — **never "no conflict found"**, because a
  string comparison that finds no match is not evidence that no conflict exists. A test
  asserts the phrase "no conflict" appears nowhere in the pass. Note the pass description
  names only the certificate-provider-versus-attorney comparison while the check now
  covers more; **that under-claims, which is the safe direction, and I did not widen
  compliance's sentence myself** — flagged for them if they want it broadened.

  **The reliability limit is disclosed once, not five times.** Added to
  `LpaCheckPolicy::NOT_CHECKED`, per compliance: *"Whether two people whose names you
  typed differently are the same person…"*. **A test proves the disclosure is honest** by
  asserting "Dave Jones" against "David Jones" genuinely passes — the limit is
  demonstrated, not asserted.

  **W-0151's disclosure-only limb** is a second `NOT_CHECKED` entry, verbatim including
  the trailing family clause, which compliance said not to trim: Fynla's own two-year
  wording steers a donor toward a spouse or child, and a generic line would not undo that
  steer. **No family-member check was built** — "family member" is undefined in reg 8(4),
  reg 2 and MCA 2005 s.64, so a check would have Fynla drawing a boundary the instrument
  leaves undrawn. This entry **replaces** the shorter line compliance offered on W-0106;
  W-0106 was never built, so there is one entry, not two.

  **Matched W-0024 both ways, without adding a gate — and the gate question is open.**
  A test asserts the conflict fires when present **and clears when corrected**, which is
  the behaviour team-lead asked for. What I did **not** do is block completion the way
  `WillDocumentService`'s `severity: error` blocks a will: neither acceptance asks for it,
  and blocking the three W-0103 conflicts would refuse a save on an arrangement compliance
  searched for and could not find prohibited. **Recommendation: if gate parity is wanted,
  gate only the two statutory limbs, as its own item with its own decision about
  instruments already saved in that state.**

  **Verification.** `LpaComplianceServiceTest` **28 tests**, `LpaControllerTest` green,
  and the whole estate suite **292 passed / 946 assertions** including batch B's will
  tests. Pint clean. **Not browser-verified** — a persona-tester closes Rule 14's loop.

- 2026-08-21 fix-batch-G → **compliance-lead, one wording choice for you rather than me.**
  Your pass description — *"The certificate provider and attorney names you entered do not
  match each other."* — shipped **verbatim**. It now sits under a check that compares five
  role pairings, not one: donor against attorney, donor against certificate provider,
  attorney against replacement, duplicates within a list, and the cross-instrument case.
  **So the sentence under-claims what the check did.**

  I did not widen it. Under-claiming is the safe direction and the words are yours — but
  you should have the choice rather than inherit my silence. If you want it broadened,
  the title (*"The names in each role are different"*) already covers all five and only
  the description needs a line.
