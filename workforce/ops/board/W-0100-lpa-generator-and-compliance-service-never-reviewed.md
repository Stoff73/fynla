---
id: W-0100
title: The Lasting Power of Attorney document generator and its compliance service have never been reviewed — the will builder's sibling, unexamined
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0008-batch-g-lpa.md
owner: build-lead
claimed_by: fix-batch-G
reviewers: [compliance-lead, product-lead]
status: gated
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
severity: medium
surfaces: [web, m, ios]
source: found by compliance-perimeter while anchoring the regime map, 2026-08-21; existence verified by coordinator
prior_art_checked: 2026-08-21
prior_art_found: [W-0019, W-0024, W-0044, F-0003-batch-b-estate-wills]
prior_art_outcome: none
constitution_refs: [05-perimeter, 07-quality-bar]
---

## Intent — an audit, not a defect claim

Fynla generates a **second legal instrument** nobody has examined.

Verified:

- `routes/api.php:977-985` — a full Lasting Power of Attorney surface behind the same
  `estate.full` (Premium) gate as the will builder: create, update, upload, register,
  and a `compliance` endpoint.
- `resources/js/utils/lpaDocumentRenderer.js` — **renders the document**, the direct
  sibling of `willDocumentRenderer.js`.
- `app/Services/Estate/LpaService.php` — records donors, attorneys, notification
  persons, and `markAsRegistered()`.
- `LpaComplianceService::checkCompliance()` — **assesses the instrument** and returns a
  result to the user.

**No defect is being asserted here. The point is that nobody has looked.** W-0019 and
W-0024 reviewed the will builder and found, among other things, a will appointing its
own testator as executor — a defect invisible until someone read the generator. The
Lasting Power of Attorney path has had no equivalent review, and it is the same shape
of machinery: user data in, legal instrument out, plus an automated assessment of it.

The persona carries **Has LPA: Yes**, so this is in scope for the persona run and will
be entered during a pass.

## Acceptance

1. **Read the generator the way W-0024 was read.** Does the rendered instrument contain
   any structural contradiction of the W-0024 class — a party appointed in a role they
   cannot hold, a donor who is also their own attorney, a certificate provider who is
   an attorney, replacement attorneys inconsistent with the primary appointment?
2. **Check both instrument types** where the model distinguishes them (property and
   financial affairs versus health and welfare) — the rules differ and a single
   renderer serving both is exactly where a conflation hides.
3. **Read `LpaComplianceService::checkCompliance()`.** It tells a user whether their
   instrument is compliant. Establish what it actually checks, what it silently does
   not, and whether a "compliant" result can be returned for an instrument that is not.
4. **Rule 19 / Rule 20:** establish which surfaces can reach this at all. W-0044 found
   the native app has no `estateWill` handoff case — check whether the same is true
   here rather than assuming.
5. `compliance-lead` reviews the perimeter question separately: whether generating and
   assessing this instrument sits inside what an unauthorised firm may do, and what the
   user-facing framing must therefore say. **That review is scoped as its own task and
   is not build-lead's to answer.**

## Working notes

- 2026-08-21 compliance-lead: **acceptance 5 answered —
  `workforce/ops/reports/2026-08-21-W-0100-lpa-perimeter-review.md`.** Perimeter half only;
  acceptance 1–4 untouched and still build-lead's. Not an approval, and no determination that
  generating or assessing an LPA is permissible.

  **Does the W-0019 wills analysis carry across? Established, not assumed: YES, and from the
  same sub-paragraph.** LSA 2007 Sch 2 para 5(3) excludes from "reserved instrument
  activities" both **(a) "a will or other testamentary instrument"** and **(c) "a letter or
  power of attorney"** — one line apart. Nothing in Sch 2 paras 3–8 reserves preparing a power
  of attorney. **But "not reserved" answers one question, not the set**, and I have not let it
  stand in for permission.

  **Two ways the LPA is NOT like a will, both cutting against us.** (1) **MCA 2005 Sch 1 para
  1 — a statutorily PRESCRIBED FORM.** A will has none. An LPA that departs from the
  prescribed form is not a defective LPA, **it is not an LPA** — a binary failure that surfaces
  at registration, possibly after the donor has lost capacity. That is why acceptance 1–2
  matters more here than it did for the will. (2) **MCA 2005 Sch 1 para 2(6): "The certificate
  may not be given by a person appointed as donee."** That is the W-0024 defect shape —
  a party in a role they cannot hold — **written into the statute.** Worth testing first,
  because it is the one the Act names. Para 2(1)(e) also requires the certificate provider to
  certify the donor understands, that "no fraud or undue pressure is being used", and that
  nothing else prevents a valid LPA.

  **The verdict, and this is the sharper half.** `LpaComplianceService.php:49` can return the
  literal string `'compliant'`; `LpaComplianceChecklist.vue:97` renders it as **"Compliant"**
  and `:88` renders it in `bg-spring-100 text-spring-800` — **the SUCCESS colour** (Rule 8).
  **Live on production since `1a3d17e99`, 2026-03-16 — five months.**

  > **QUALIFIED — see the `2026-08-21 build-lead` note below ("a correction to a
  > live-exposure assumption").** The badge is on production, and the overclaim is real.
  > But the checklist renders **only when `lpa.status === 'draft'`**
  > (`LpaDetailView.vue:48`, guard at `:135`), and an unregistered instrument always
  > carries at least one warning and reads "Review Needed". Reaching "Compliant" needs a
  > specific sequence: register, then re-open and Save Draft. **This changes how urgent
  > the five months looked, not how wrong the badge is.** Left as written.

  Two findings, **neither dependent on the code audit**, so they do not pre-empt acceptance 3:
  1. **The trunk already forbids this — of me.** Perimeter §7.3: may report "no issues found
     within my competence"; **may never "approve anything as legally compliant"**; "its output
     is never an approval"; and the stated failure mode is *"a confident-looking compliance
     sign-off that nobody questions… it stops a human from looking."* **That paragraph
     describes this service exactly.** The principle is present and correct in the trunk and
     binds the AGENT; it has never been read as binding the PRODUCT.
  2. **The object assessed is not the instrument.** The checks run on stored form data;
     validity turns on events the app never observes — actual capacity at signing, whether the
     para 2(1)(e) certificate was genuinely given, manner and order of execution, and whether
     the Public Guardian has registered it (Sch 1 paras 4–5). **A perfect checker would still
     not be entitled to the word.**

  **Framing requirements (must-disclose level, NOT drafted copy — premature until acceptance
  1–3 land, and design-lead's craft):** drop "compliant" and every synonym asserting a property
  of the instrument — describe the act, not the object; **name what was not checked at the
  point the result is shown** (trunk §4 already requires this, and this is the first time §4
  has been applied outside a currency figure); state that validity depends on execution and
  registration, which Fynla does not observe; **do not use the success colour**; signpost a
  "qualified solicitor" composing from one home per Rule 20 (`WillTypePolicy` precedent); and
  **no FCA-authorisation wording** — an LPA is a creature of the Mental Capacity Act.

  **Trunk gap — same AND distinct.** Same: legal-services regime unmapped (the regime map row
  absorbs LPAs unchanged, and now covers three instruments and a second statute). **Distinct
  and more useful: §7.3 contains exactly the right rule and applies it to the wrong half of the
  system.** The gap is scope, not content. Recommended: extend §7.3 so no Fynla surface tells a
  user that anything they hold is compliant, approved, valid or sufficient — it may report what
  was checked and what was not. **Otherwise the trunk forbids an agent from saying "compliant"
  while the application says it in green.**

  **LIVE vs documentation.** Live: the badge is on production and **is an overclaim even if the
  generator is perfect** — the two are independent, and the badge is far cheaper to fix. How
  many real users hold an LPA is unknown to me (prod not queried); both branches pre-stated in
  the report. Documentation: §7.3's scope, and the legal-services row. **Trap for whoever does
  acceptance 1–3: MCA Sch 1 carries pending amendments from the Powers of Attorney Act 2023
  (2023 c. 42), NOT in force as at 20 Aug 2026 — check commencement before relying on the
  current text.** Same shape as W-0050's stale PECR citation.


Do not sequence this ahead of the live defects. It is an audit of an unexamined
surface, not a known break — but it should not wait for someone to stumble into it
during a persona pass either. The reason it is on the board is that **an unreviewed
document generator is exactly what W-0024 turned out to be.**

---

- 2026-08-21 fix-batch-G (build-lead): **acceptance 1–4 answered and the overclaim
  fixed.** Branch document: `workforce/branches/fixes/F-0008-batch-g-lpa.md`.
  Handoff note: `workforce/ops/handoffs/W-0100/build-lead-to-quality-lead-2026-08-21.md`.
  Acceptance 5 was compliance-lead's and was not touched.

  **First, a correction to a live-exposure assumption, because it changes how urgent
  this looked and not how wrong it was.** The perimeter report listed as an unverified
  assumption that "Compliant" reaches the user as rendered. It does, but only down a
  narrow path, and the report was right not to assume it. The checklist renders **only
  when `lpa.status === 'draft'`** (`resources/js/components/Estate/LpaDetailView.vue:48`
  and the `mounted()` guard at `:135`), and `checkRegistrationStatus()` returns a
  `warning` for an unregistered instrument — so an ordinary draft always had at least
  one warning and read "Review Needed", never "Compliant". The reachable path is:
  register the instrument (`POST /api/estate/lpa/{id}/register`, which sets
  `is_registered_with_opg = true`), then re-open it in the wizard and press **Save
  Draft** (`LpaWizard.vue:341` → `save('draft')`). `LpaService::updateLpa()` never
  clears `is_registered_with_opg`, so the instrument is now a draft that passes the
  registration check. With a replacement attorney, one person to notify and a
  certificate provider of two years or more, every check passes and the badge is
  green. **Reachable, narrow, and real** — and the endpoint returned
  `overall_status: 'compliant'` in the JSON for any registered instrument regardless.

  **Acceptance 3 — what `checkCompliance()` actually checked.** Ten checks, two of them
  type-conditional (`LpaComplianceService.php:25-43`). It could return `'compliant'`
  (`:49`). What it silently did not check is now the substance of W-0102, W-0103,
  W-0104, W-0105, W-0106 and W-0107 — most sharply the one the statute names, MCA 2005
  Sch 1 para 2(6): **nothing compares `certificate_provider_name` to the attorneys**,
  so the generator will print the same person as Attorney 1 and as the certifier of
  the donor's capacity. That is the W-0024 shape, confirmed by reading, and raised as
  **W-0102** rather than fixed here, because adding statutory validation is new
  behaviour needing compliance-lead's read of the wording.

  **Acceptance 1 — the generator, and the thing I did not expect to find.**
  `resources/js/utils/lpaDocumentRenderer.js` **rendered facsimile signatures**. When
  `completed_at` was set it drew the donor's, every attorney's and the certificate
  provider's name onto the signature lines in `Brush Script MT` (`:191, :204, :218,
  :231`, styled at `:291`). `completed_at` is set by the user pressing "Complete" in
  the wizard — **no attorney and no certificate provider had done anything**, and the
  certificate provider's drawn signature sat directly beneath a block in which they
  "certify" the donor's understanding and the absence of fraud or undue pressure.
  It also asserted, on the strength of a self-declared checkbox, *"This instrument is
  now a valid Lasting Power of Attorney under the Mental Capacity Act 2005"* (`:248`)
  — a stronger claim than the badge, in the same feature, and "valid" is named
  explicitly in the perimeter report's §Q3 item 1. Both removed. Signature lines are
  now always blank.

  **Acceptance 2 — the type split.** Handled correctly in the checks (`isPropertyFinancial`
  / `isHealthWelfare`, `:37-43`) and in the renderer. One conflation and one gap:
  the property-only "when attorneys can act" clause fell through its `else` branch to
  *"only when I have lost mental capacity"* when the field was **null** (`:119-123`),
  putting a legally operative election into the document that the donor never made —
  fixed, and it now reads "Not specified.", matching how the same file already handled
  life-sustaining treatment. The gap is the health and welfare document's silence on
  MCA 2005 s.11(7)(a), raised as **W-0108**.

  **Acceptance 4 — surfaces, verified by absence-grep rather than inferred from
  W-0044.** Web only: `/estate/power-of-attorney` and `/estate/lpa/create/:type`.
  **`/m` has nothing**; `resources/mobile/` has no match for "attorney" outside
  unrelated fixtures. **iOS native has nothing.** `WebHandoffDestination` has
  `ESTATE_WILL` and **no Lasting Power of Attorney case** — W-0044's absence, confirmed
  for this instrument. The defect is that **Fyn writes here from every surface**:
  `create_power_of_attorney` / `update_power_of_attorney` (`CoordinatingAgent.php:4253,
  :4328`) are in both tool catalogues and stripped only from the read-only advice
  surface. A user on `/m` or native can create a Lasting Power of Attorney they can
  never see. Raised as **W-0110**.

  **The fix, and where it lives (Rule 20).** `app/Services/Estate/LpaCheckPolicy.php`
  is the one home for what Fynla is entitled to say about this instrument — outcome
  vocabulary, the "what we did not check" disclosure, and the solicitor signpost.
  `LpaComplianceService` composes its payload from it; the web component renders that
  payload and hardcodes no wording; a future `/m` or native surface gets the same words
  without a second decision. `overall_status` is **deleted**, not renamed, so every
  consumer had to be found — there were three, all updated.

  **The colour question did not need answering.** The outcome is now plain text, not a
  badge, so no palette token changes and the parked palette workstream is untouched.
  A green pill would have carried "approved" whatever the label said.

  **Raised, not fixed, and the reason each time:** W-0101 (the will renderer draws the
  testator's **and both witnesses'** signatures — same defect, sibling generator, still
  live; not touched because `fix-batch-B` holds those files today), W-0102–W-0108 (new
  statutory checks and legal statements, needing compliance-lead), W-0109 (registration
  fee and timescale in three copies, timescale looks stale, no sourced owner), W-0110
  (surfaces).

  **Not done:** no browser verification — a persona-tester closes Rule 14's loop, per
  the dispatch. No commit, no PR, no deploy. No production query on how many real users
  hold a Lasting Power of Attorney; both branches remain pre-stated in the perimeter
  report. **The Powers of Attorney Act 2023 commencement trap was respected rather than
  resolved** — nothing shipped here relies on the current text of MCA Sch 1, because
  everything shipped here claims *less* than before.

- 2026-08-21 compliance-lead: **The production count is in, and it closes the open Need from my
  acceptance-5 report.** Run read-only against `fynla.org` by team-lead, 2026-08-21:
  **6 `lasting_powers_of_attorney` rows, all preview personas. Zero real users hold one.**
  `NULL` was treated as real rather than preview, so the method errs toward over-reporting.

  My report pre-stated both branches. **This is the zero branch, and I hold to what I said it
  would mean:** *"no user has been given the verdict; the badge is a defect to fix before anyone
  is, and the audit proceeds at ordinary priority."*

  **It reduces the finding by nothing.** The green "Compliant" badge was on production from
  `1a3d17e99` (2026-03-16) and was **equally wrong on every day nobody looked**. This is
  **timing, not a control** — the same sentence that closed W-0019's exposure limb, and it
  applies for the same reason: no gate stopped anyone, the feature simply went unused. What it
  does mean is narrower and worth stating plainly: **no remediation, no disclosure, no affected
  cohort**, and `fix-batch-G`'s fix lands before anyone is reached rather than after.

  **Three limits on how far this may be read:**
  1. **It bounds the instrument, not the impression.** It counts rows, not people who saw the
     badge. A user could have reached the estate section, seen the feature, and created nothing.
  2. **It does not reach W-0101.** Different table, different feature. **The will renderer has
     been drawing testator and witness signatures on production the whole time**, and that is
     the sharper of the two defects. **Do not let this zero be read across to it** — quantifying
     that needs its own count.
  3. **It expires.** The argument reverses the day a real user creates one.

  **Recorded on `Q-13` in `ops/open-questions.md`**, which has moved out of the could-reveal-a-
  live-risk set into a CSJ product decision: **removing a feature nobody uses costs less than a
  legal opinion about it**, and W-0110 already records it is web-only.

  **This closes my Need. It does not close the item** — acceptances 1–4 and the evidence pack
  are not mine, and nothing here is an approval (`05-perimeter.md` §7.3).
