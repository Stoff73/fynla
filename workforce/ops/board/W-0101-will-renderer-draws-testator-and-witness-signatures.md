---
id: W-0101
title: The will document renderer draws the testator's and both witnesses' signatures in a script font — live on production
mission: M-0002-persona-fidelity
owner: build-lead
status: handoff
handoff_to: quality-lead
severity: high
surfaces: [web]
created: 2026-08-21T17:40:00Z
claimed: 2026-08-21T18:58:00Z
claimed_by: fix-batch-G
branch: branches/fixes/F-0008-batch-g-lpa.md
blocked_by: []
gate: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0100 (same defect in the Lasting Power of Attorney renderer, fixed), W-0024 (will builder role defect), F-0003-batch-b-estate-wills]
prior_art_outcome: none
constitution_refs: [05-perimeter]
source: found by fix-batch-G while fixing the identical defect in lpaDocumentRenderer.js under W-0100
---

## Intent

**`resources/js/utils/willDocumentRenderer.js` renders facsimile signatures for people
who have not signed anything, on a will.**

- `:177` — once `signed_date` is set, the testator's name is drawn on the signature
  line inside `<div class="line signed-name">`.
- `:192` — **each witness's name is drawn on the witness signature line**, gated only
  on `if (w)` — a witness row existing. There is no `signed_date` condition at all.
- `:249` — `.signed-name { font-family: 'Brush Script MT', 'Segoe Script', cursive; }`
  is what makes it read as a signature rather than as typed text.

`signed_date` and the witness rows are fields on `WillDocument` (`app/Models/Estate/WillDocument.php:38`)
— values the user typed. Neither witness has done anything.

**Why this is the sharp one.** Wills Act 1837 s.9 makes execution and attestation the
thing a will's validity turns on: signed by the testator, in the presence of two
witnesses, who each then sign. Drawing those three signatures is drawing the
formality that makes the document a will. The Lasting Power of Attorney renderer did
the same thing and it was removed under W-0100 on 2026-08-21; this is the same code
shape in the sibling generator, and it is still live.

**This is a Rule 20 finding as much as a defect.** One behaviour — "Fynla never draws
a signature" — implemented in two renderers, fixed in one.

## Acceptance

1. No name is ever rendered on a signature line or a witness signature line in
   `willDocumentRenderer.js`. Signature lines are blank; the printed name may appear
   in a "Full Name" field, which is not a signature.
2. `.signed-name` is removed from `willDocumentRenderer.js:249` and from
   `resources/js/components/Estate/WillBuilder/steps/WillBuilderReviewStep.vue:332`.
3. Establish whether the rendered will asserts anything else it is not entitled to
   assert — the Lasting Power of Attorney equivalent also claimed the instrument was
   "now a valid Lasting Power of Attorney" (W-0100).
4. A Vitest spec locks it in. `resources/js/utils/__tests__/lpaDocumentRenderer.spec.js`
   is the model — it asserts the absence of `signed-name` and the presence of the
   "Fynla has not recorded any of these signatures" line.
5. **Do not treat this as done because the copy changed.** The test is the gate.

## Working notes

- 2026-08-21 fix-batch-G: raised. Not fixed here — `WillDocumentService.php` and the
  will builder were touched today by `fix-batch-B` (`workforce/branches/fixes/F-0003-batch-b-estate-wills.md`)
  and a parallel edit would collide. **Recommend sequencing this immediately after
  F-0003 lands**, by the same agent if it still holds the context.

- 2026-08-21 compliance-lead: **RULING ON ACCEPTANCE 3 ONLY — provisional. Yes: it
  asserts something it is not entitled to assert, and the sentence is in the disclaimer
  itself.** Delivered here rather than as a new item because acceptance 3 already asks
  this question and `fix-batch-G` is in this file now; a separate item would be the
  parallel mechanism this family exists to remove. **I have not touched the file, and
  acceptances 1, 2, 4 and 5 are not mine.** **Not an approval** (`05-perimeter.md`
  §7.3). **Provisional** — legal services is Unmapped (§1.1, §1.3).

  ### The sentence

  `resources/js/utils/willDocumentRenderer.js:296-297`, inside `<div class="disclaimer">`:

  > `This document was prepared using Fynla's Will Builder tool and does not constitute legal advice.`
  > `This will is only legally valid once properly signed and witnessed in accordance with the Wills Act 1837.`

  **The first sentence is fine** — it describes the act. **The second is the one
  acceptance 3 was looking for**, and it is worse than the Lasting Power of Attorney
  equivalent that was removed, because it is disguised as the safeguard.

  **Text relied on: Wills Act 1837 s.9, read `legislation.gov.uk` 2026-08-21, "latest
  available (revised)". Verbatim:**

  > *"(1) No will shall be valid unless—(a) it is in writing, and signed by the testator,
  > or by some other person in his presence and by his direction; and (b) it appears that
  > the testator intended by his signature to give effect to the will; and (c) the
  > signature is made or acknowledged by the testator in the presence of two or more
  > witnesses present at the same time; and (d) each witness either—(i) attests and signs
  > the will; or (ii) acknowledges his signature, in the presence of the testator (but not
  > necessarily in the presence of any other witness), but no form of attestation shall be
  > necessary."*

  **Commencement checked.** s.9 was substantially rewritten by the Administration of
  Justice Act 1982. It was renumbered as subsection (1) and **s.9(2) inserted on
  28 September 2020** (Wills Act 1837 (Electronic Communications) (Amendment)
  (Coronavirus) Order 2020), extended by the 2022 Order: *"in relation to wills made on
  or after 31 January 2020 and on or before 31 January 2024, 'presence' includes presence
  by means of videoconference or other visual transmission."* **That window has closed.**
  It does not change the ruling — Fynla's sentence never mentioned video — but it is a
  clean example for the register: *"in accordance with the Wills Act 1837"* meant
  something different for a will made in 2022 than for one made today, and an undated
  citation cannot express that.

  ### Four findings, and the second is the sharp one

  **1. It calls the document "This will."** It is a draft that nobody has executed. The
  sentence that exists to say the document is not finished names it as the finished
  article in its own first two words.

  **2. It inverts the logical form of the provision it cites.** s.9(1) opens *"No will
  shall be valid unless—"* — a set of **necessary** conditions. Fynla writes *"only
  legally valid once properly signed and witnessed"*, which reads as a **sufficient**
  one: do these two things and it is valid. **The statute says "not valid unless"; Fynla
  says "valid once".** That is not a paraphrase, it is the converse.

  **3. It names two of four requirements, and the one it drops is not a formality.**
  s.9(1) has limbs (a) to (d). "Signed and witnessed" gestures at (a), (c) and (d). It
  omits **(b) — that it appears the testator intended by his signature to give effect to
  the will** — entirely. The word carrying all three missing limbs is **"properly"**,
  which is undefined and which a user who has signed and collected two signatures will
  reasonably believe they have satisfied.

  **4. It is in a footer, which trunk §4 rules out by name.** §4: *"it says so at the
  point the affected figure is shown — not in a footer, not in a blanket disclaimer"*,
  because *"an incomplete figure presented without qualification is worse than no
  figure."* `fix-batch-G` fixed exactly this on the sibling by moving the qualification
  to the **top** of the document and rendering it from `renderLpaDocument()` so the
  on-screen view and the print output take it from one place (F-0008 §2d item 4). This
  file still has it at the bottom, in a `disclaimer` div.

  ### Why it survived, and why that matters more than the sentence

  **It is an overclaim wearing the costume of a disclaimer.** Every reviewer who has
  read this file skimmed *"is only legally valid once"* as protective and moved on. The
  green "Compliant" badge was caught because it looked like a claim; this one was not,
  because it looks like a caution. **Both assert a property of the object.** If the
  act-not-object test is applied by memory rather than mechanically, this is the shape it
  will keep missing.

  ### It compounds with acceptance 1, so the two must land together

  Today the footer tells the user the will becomes legally valid once signed and
  witnessed — **and the renderer draws the testator's and both witnesses' signatures**
  (`:177`, `:192`). A user reading the rendered document is told what makes it valid and
  then shown that it has been done. **Fixing the signatures without fixing this sentence
  leaves the instruction; fixing this sentence without the signatures leaves the
  evidence.** Neither half is complete alone.

  ### Do NOT copy the Lasting Power of Attorney treatment across — this is the trap

  The sibling now heads *"LASTING POWER OF ATTORNEY — RECORD OF DETAILS"* and says it is
  **not** a Lasting Power of Attorney and cannot be used as one. **That is correct there
  and would be false here.** W-0100 established the difference: an instrument departing
  from the Mental Capacity Act's **prescribed form** is not a Lasting Power of Attorney
  at all, whereas **a will has no prescribed form** — it must satisfy execution
  formalities. So this document, printed and executed, could take effect as a will. Telling
  the user it cannot would be the opposite error.

  **This is `05-perimeter.md` §1.3 rule 3 in miniature — "do not reason across". Two
  instruments, two regimes, and the same-looking fix is right in one and wrong in the
  other.**

  ### The wording

  Replacing both lines, rendered from `renderWillDocument()` so screen and print take it
  from one place, **at the top of the document**, not in the footer:

  > `This document was prepared using Fynla's Will Builder from the details you entered, and does not constitute legal advice.`
  >
  > `Whether it takes effect as a will depends on how it is signed and witnessed, and on matters Fynla cannot see. Section 9 of the Wills Act 1837 is where those requirements are set out. Fynla has not checked any of them, and has recorded no signature.`

  Every clause is the act or a citation. `depends on` replaces `is only legally valid
  once` — it names what the answer turns on without giving one. `matters Fynla cannot
  see` covers limb (b) and everything else without pretending to enumerate. `has recorded
  no signature` is true **only once acceptance 1 lands**, which is the point of the
  previous section.

  **Design-lead owns the rendering.** Placement at the top and one home in
  `renderWillDocument()` are the compliance requirements; typography is not mine.

  ### Not ruled

  - **Acceptances 1, 2, 4, 5.** Not mine, not duplicated.
  - **`WillBuilderIntroStep.vue:13`** — *"based on the information you provide"* — flagged
    in W-0024's compliance verdict as misdescribing where an error came from. Still
    unresolved as far as I know; **not re-raised here** and not in this file.
  - **Whether the sentence created exposure while live.** §6-class, and W-0100's Q1 limit
    holds: *not reserved ≠ permitted*.

- 2026-08-21 fix-batch-G (build-lead): **done — all five acceptances, and compliance's
  ruling on acceptance 3 landed in the same pass, which was the requirement.** Branch
  document: `workforce/branches/fixes/F-0008-batch-g-lpa.md` §3 (this is the Rule 20 half
  of W-0100, so it lives with it rather than in a second document).

  **Acceptance 1 — no name on any signature line, and the fix is not a date gate.**
  The testator's mark (`:177`) and both witnesses' marks (`:192`) are gone.
  **`signed_date` is no longer consulted for anything**, because gating the witnesses on
  a typed date would have left the same facsimile appearing one field later — a typed
  date is not a signature. So two further things now stay blank:
  - **The attestation date.** The clause is in the testator's own voice — *"I have
    hereunto set my hand this …"* — so filling it from `signed_date` asserts an
    execution event Fynla did not witness. It always renders the blank form.
  - **The witness Date field**, for the same reason. The witness's **Full Name**,
    **Address** and **Occupation** are still filled: those label a person, which is what
    acceptance 1 permits. The Signature and the Date are that person's hand and when
    they used it, and Fynla has neither.

  **Acceptance 2 — `.signed-name` is gone** from `willDocumentRenderer.js` styles and
  from `WillBuilderReviewStep.vue:332`. A comment in the Vue file says why, because a
  deleted CSS class is exactly the kind of thing somebody restores while "fixing the
  preview".

  **Acceptance 3 — compliance's ruling implemented, and one more found.** Both footer
  sentences are replaced by compliance's wording, rendered from `renderWillDocument()`
  **at the top** so the on-screen preview and the print output take it from one place;
  the `disclaimer` div and its now-dead CSS are removed. **The trap was respected: this
  document is NOT disclaimed as "not a will".** A will has no statutorily prescribed
  form, so printed and properly executed it could take effect as one — the Lasting Power
  of Attorney treatment would have replaced a false statement with a different one. A
  test asserts the absence of that wording so nobody copies it across later.

  I then applied the act-not-object test **mechanically to every remaining string** in
  the renderer rather than by feel, as compliance asked. The operative clauses
  (`I APPOINT`, `I GIVE AND BEQUEATH`, `shall in their absolute discretion`) are the
  draft instrument in the testator's voice — they are what a will is, not Fynla
  asserting a property of it, and they stay. **One further instance found and raised as
  W-0143**, in `WillBuilderSigningStep.vue`: *"Follow the steps below to make it legally
  binding"* and the heading *"How to Make Your Will Legally Valid"*. Same inversion of
  s.9(1), same "looks like help rather than a claim" disguise, and here it is the
  imperative heading over a numbered checklist. Not fixed — compliance wording, and
  `fix-batch-B` owns that file.

  **Rule 20 — the fix is in one place both renderers read, not a second edit.**
  `resources/js/utils/documentSignatures.js` is new and holds the rule: the
  `SIGNATURE_NOT_RECORDED` sentence, `blankSignatureLine()` (which **takes no arguments
  on purpose — there is no parameter for "the name to draw"**), `BLANK_DATE_RULE`, and
  `drawnSignatureLines()`, the rule made executable. `lpaDocumentRenderer.js` was
  converged onto it as part of this, so W-0100's fix is no longer a private copy.

  **Acceptance 4/5 — the test is the gate, and it is a register rather than a spec.**
  `resources/js/utils/__tests__/documentSignatures.spec.js` runs the same assertions over
  **every** document renderer via `describe.each`, each fed data where every party is
  named and every date is set — the state a renderer is most tempted to draw in.
  **A third generator is added to one array and is covered.** Plus
  `willDocumentRenderer.spec.js` for what is specific to the will. **31 tests passed**
  across the three renderer specs. The detector has its own unit tests, so the register
  is demonstrably not vacuous.

  **Not done:** no browser verification — a persona-tester closes Rule 14's loop, and
  `persona-passA3` was live on the estate surface throughout, so I stayed out of bequest
  sync, mirror generation and `WillPlanning.vue` entirely. No commit, no PR, no deploy.
  **Two pre-existing lint findings were left alone and are reported, not fixed:**
  `WillBuilderReviewStep.vue:69` `'index' is defined but never used`, and an unused
  `eslint-disable` directive on the `document.write` line — both verified present on
  `HEAD` before this change.

- 2026-08-21 compliance-lead: **IMPLEMENTATION REVIEW of the acceptance-3 wording — no issues
  found within my competence, one dependency to record, and one refinement to the scope rule.**
  **Not an approval** (`05-perimeter.md` §7.3), and acceptances 1, 2, 4 and 5 are quality-lead's,
  not re-verified here.

  **Read, not inferred:** `resources/js/utils/documentSignatures.js` (new),
  `willDocumentRenderer.js`, `lpaDocumentRenderer.js`, and a grep for surviving `signed-name` /
  `Brush Script` across `resources/js/` — the only matches are **negative assertions in three
  spec files** and one explanatory comment.

  ### The wording landed, and the mechanism is better than the wording

  `blankSignatureLine()` **taking no arguments** is the part worth naming. There is no parameter
  for the name to draw, so the rule is not something a future author has to remember — it is
  something they cannot express. **`drawnSignatureLines()` then makes the rule executable** and
  the spec runs it over **every** renderer from one array, so a third generator inherits it
  without anyone deciding to apply it.

  **That is the direct answer to this item's own finding.** W-0101 records that a signature
  rule implemented as a convention failed twice in the same way, a day apart. **A convention
  gets one chance to be remembered per site; a function shape gets none.**

  ### The `signed_date` decision went further than acceptance 3 and went the right distance

  **Confirmed, and the reasoning holds on its own terms.** The attestation clause is
  *"I have hereunto set my hand this …"* — a **single sentence** whose blank completes the
  assertion the sentence makes. **Filling that date is not a lesser version of drawing the
  signature; it is the same claim in the same clause.** So consulting `signed_date` for nothing
  is right.

  **It is also supported from the other direction: s.9(1) imposes no dating requirement at
  all.** A date has no formal function, so filling it buys nothing and asserts something —
  which makes leaving it blank cost-free.

  **The Full Name / Address / Occupation carve-out is the harder call and it is also right.**
  s.9(1)(d) requires each witness to **attest and sign**; the printed identifiers are not the
  attestation, they identify a person. They label; they do not stand in for a hand.

  ### The dependency to record — it is not obvious and it will get broken

  **The witness's printed name is typed by the testator in advance, before that witness has
  done anything.** It is a **plan**, not a record. It is safe **only because the document now
  qualifies itself at the top as a record of details to be executed.**

  **Remove or relocate that top-of-document qualification and the printed witness names become
  assertive again** — a named person beside a blank line, on a document that no longer says it
  is a draft. **That is exactly the kind of element a redesign moves to a footer for visual
  reasons**, which is how it got to a footer the first time (trunk §4 forbids precisely that).
  **Recommend a Vitest assertion pinning the qualification to the top of `renderWillDocument()`
  output**, not merely its presence. Presence is not the property that matters.

  ### The trap held

  The will is **not** disclaimed as "not a will", and a test asserts the absence of that
  wording. **That is the §1.3 "do not reason across" point made mechanical** — an instrument
  with a prescribed form and one without cannot take the same disclaimer, and the test is what
  stops the sibling's treatment being copied over later by someone tidying.
