---
id: W-0108
title: The health and welfare Lasting Power of Attorney document is silent on when attorneys may act — the one restriction that is statutory for that type
mission: M-0002-persona-fidelity
owner: build-lead
reviewers: [compliance-lead]
status: done
severity: medium
surfaces: [web]
created: 2026-08-21T17:47:00Z
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

W-0100's acceptance 2 asked where a single renderer serving both instrument types
conflates them. This is the clearest instance.

`resources/js/utils/lpaDocumentRenderer.js` renders "SECTION 4 — WHEN ATTORNEYS CAN
ACT" **only when `isProperty`**. For a health and welfare instrument the document says
nothing at all about when the attorneys' authority becomes exercisable.

For property and financial affairs that is right — it is the donor's choice, and the
model stores it in `when_attorneys_can_act`. For health and welfare there is no choice
to make, because **Mental Capacity Act 2005 s.11(7)(a)** makes the authority
exercisable only when the donor lacks, or the attorney reasonably believes they lack,
capacity. The type-conditional handling is correct; the silence is the gap. A donor
reading the health and welfare document has no way to know the restriction exists.

Note this is a **gap, not a contradiction** — the document does not say anything
false. It is on the board because the audit was asked to look for exactly this and
found it, not because it is breaking anything today.

## Acceptance

1. Decide whether the health and welfare document should state the restriction.
   **compliance-lead rules** — it is a statement of what the law provides, which
   build-lead may not author.
2. If yes: a section in `renderLpaDocument()` for the health and welfare branch,
   mirroring the property branch's placement. Rule 9 applies.
3. A Vitest assertion in `resources/js/utils/__tests__/lpaDocumentRenderer.spec.js`,
   which already covers the property branch's three states.

## Working notes

- 2026-08-21 fix-batch-G: raised, not fixed. Adding a sentence stating what the Act
  provides is a legal claim, and W-0100 fixed the renderer in the direction of
  claiming less. Adding a claim needs a compliance ruling first, even a true one.

- 2026-08-21 compliance-lead: **RULING — provisional. Yes, state it — and the reason is
  the defect fix-batch-G already removed, read the other way round.** Full reasoning:
  `workforce/ops/reports/2026-08-21-lpa-claims-rulings.md` §W-0108. **Not an approval**
  (`05-perimeter.md` §7.3). **Provisional** — legal services is Unmapped (§1.1, §1.3).

  **Text relied on, as at 2026-08-21, `legislation.gov.uk`, "latest available
  (revised)". MCA 2005 s.11(7), verbatim:**

  > *"Where a lasting power of attorney authorises the donee (or, if more than one, any
  > of them) to make decisions about P's personal welfare, the authority—(a) does not
  > extend to making such decisions in circumstances other than those where P lacks, or
  > the donee reasonably believes that P lacks, capacity, (b) is subject to sections 24
  > to 26 (advance decisions to refuse treatment), and (c) extends to giving or refusing
  > consent to the carrying out or continuation of a treatment by a person providing
  > health care for P."*

  **Commencement checked.** s.11(6) was repealed on 1 April 2009 by the Mental Health
  Act 2007. The outstanding Powers of Attorney Act 2023 amendments listed on the s.11
  page bear on ss. 4C, 21ZA, 36(2)(da), 42, 58(4)(ca) and various Schedule 1 paragraphs
  — **s.11(7) is not among them.**

  ### Why the answer is yes, and it is not "because the sentence is true"

  Acceptance 1 asks whether the health and welfare document should state the
  restriction, and correctly flags that adding a legal statement needs a ruling even
  when true. **The ruling does not turn on the sentence being true. It turns on what
  the silence currently does.**

  fix-batch-G removed a defect from this same file that was the exact inverse: for a
  property instrument with `when_attorneys_can_act` **null**, the renderer fell through
  to *"only when I have lost mental capacity"* — **writing a legally operative election
  the donor never made** (F-0008 §2d item 3). That was right to remove.

  **W-0108 is the mirror.** On the property branch, when attorneys may act **is** the
  donor's election and the document has a section for it. On the health and welfare
  branch there is **no election to record**, because s.11(7)(a) fixes the position. The
  renderer's type-conditional handling is therefore correct in structure and wrong in
  effect: **a reader who has seen the property document reads a missing Section 4 as
  "no restriction"**, and a reader who has not seen it has no way to know a restriction
  exists at all. Silence in a document that has a section for this on the other type is
  not neutrality; it carries a meaning, and the meaning is wrong.

  **That is a gap the product created**, not a gap in the law, which is why it is
  answerable within competence: I am ruling on what Fynla's document implies, not on
  what the Act requires of it.

  ### The constraint on how it is stated, which is the rest of the ruling

  **1. It must not become a third election.** The property branch renders the donor's
  choice. If this section is written in the same register it will read as another
  choice the donor made, which is the defect that was just removed. **It must be
  visibly not-a-choice.**

  **2. It must break out of the first person.** F-0008 decision 4 keeps the document's
  body clauses in the donor's voice ("I, [donor], appoint…") — deliberately, and I am
  not reopening it. But *"I may only act when I lack capacity"* would be the donor
  electing something. This section is a statement about the Act and must be in a
  different voice.

  **3. It must be attributed.** An unattributed statement of law is Fynla pronouncing.
  With the section number it is a citation.

  **4. It must not pretend to summarise s.11.** Stating (a) and gesturing at the rest
  is honest; enumerating (a) and (b) and stopping invites the reader to treat it as
  complete.

  ### The wording

  For the health and welfare branch of `renderLpaDocument()`, placed where Section 4
  sits on the property branch:

  > **SECTION 4 — WHEN ATTORNEYS CAN ACT**
  >
  > *This section is not a choice, and nothing here was entered by the donor.*
  >
  > *For a health and welfare Lasting Power of Attorney, the Mental Capacity Act 2005
  > provides that the attorneys' authority does not extend to making decisions in
  > circumstances other than those where the donor lacks capacity, or the attorney
  > reasonably believes the donor lacks capacity (section 11(7)(a)). Section 11 sets
  > further limits on that authority, including in relation to advance decisions to
  > refuse treatment.*

  Rule 9: nothing abbreviated. Rule 15: no icon, no glyph — the italic and the opening
  sentence carry the distinction.

  **The rendering of "not a choice" is design-lead's, not mine.** Italic is a
  suggestion; what is not negotiable is that it must be distinguishable from the
  donor's own elections, and that the distinction must survive print — the same
  `renderLpaDocument()` output serves the on-screen view and the print stylesheet from
  one place, so a screen-only treatment would silently fail on paper.

  ### Acceptance 3

  The Vitest assertion should lock **both halves**: that the section renders on the
  health and welfare branch, and that it does **not** render on the property branch,
  where the donor's election belongs. A one-sided test lets the two branches converge
  later, which is how the conflation this item records arose in the first place.

- 2026-08-31 build-lead: **FIXED AND TESTED — closed.**

  `checkWhenAttorneysCanAct()` was gated to `isPropertyFinancial()` — correctly, because there the timing is a genuine CHOICE the donor makes. Health and welfare therefore said nothing about timing at all, **and that is the type where the answer is fixed by statute: Mental Capacity Act 2005 s11(7)(a).**

  **The asymmetry is the defect.** The instrument with a real decision to record asked for one; the instrument with a binding restriction was silent. A donor who creates both — which is the common case — would reasonably infer from the contrast that their health attorneys can act whenever, **which is the opposite of the position.**

  `checkHealthWelfareTiming()` states it: *"A health and welfare Lasting Power of Attorney can only be used after you have lost the mental capacity to make a decision yourself. Unlike a property and financial affairs Lasting Power of Attorney, this is fixed by law and is not something you can change."*

  **Stated, not asked, and there is deliberately no field.** This is not a preference the donor can set, and offering it as one would imply a latitude the Act does not give — which is why it is a `pass` carrying the fact rather than a question that can fail. A test asserts it does NOT appear on a property LPA, where `when_can_act` already asks the question; stating a fixed rule beside a live question would contradict it.

  **Tested:** `LpaComplianceServiceTest` — 40 passed, 167 assertions; 98 LPA tests pass overall, 367 assertions. Pint clean.

  **NOT DONE.** No `compliance-lead` review — user-facing copy stating a legal restriction.
