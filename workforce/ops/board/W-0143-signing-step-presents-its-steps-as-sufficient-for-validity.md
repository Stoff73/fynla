---
id: W-0143
title: The will builder's signing step tells the user these steps make their will legally valid — the same overclaim compliance just removed from the document footer
mission: M-0002-persona-fidelity
owner: build-lead
reviewers: [compliance-lead, design-lead]
status: gated
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
severity: medium
surfaces: [web]
created: 2026-08-21T19:30:00Z
claimed: 2026-08-21T19:55:00Z
claimed_by: fix-batch-G
blocked_by: []
gate: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0101 (compliance's ruling on willDocumentRenderer.js:297 — the identical shape), W-0100, W-0024]
prior_art_outcome: extend
constitution_refs: [05-perimeter]
source: found by fix-batch-G while closing W-0101 acceptance 3, 2026-08-21
---

## Intent

**The sentence compliance ruled on under W-0101 has a twin, one file away, on the
screen where the user is told how to execute their will.**

`resources/js/components/Estate/WillBuilder/steps/WillBuilderSigningStep.vue`:

- `:11` — *"Your will document has been saved. Follow the steps below to make it
  legally binding."*
- `:17` — heading: *"How to Make Your Will Legally Valid"*

Compliance's ruling on `willDocumentRenderer.js:297` applies to both without
amendment. Wills Act 1837 **s.9(1) opens "No will shall be valid unless—"**, stating
**necessary** conditions. "Follow the steps below to make it legally binding" and
"How to Make Your Will Legally Valid" both state a **sufficient** one: do this list
and you have a valid will. That is the converse of the provision, and here it is
worse than in the footer, because it is the imperative heading above a numbered
checklist — the form most likely to be read as complete instructions.

The step list itself is mostly sound and is not the problem: it covers printing, not
signing before the witnesses are present, and who may not witness. What it cannot
cover is **s.9(1)(b)** — that it appears the testator intended by their signature to
give effect to the will — which is not a step anybody can follow. A user who
completes every numbered item will reasonably believe they are finished.

**This is the shape compliance warned would keep being missed:** it does not look
like a claim, it looks like help. The green "Compliant" badge was caught because it
read as a boast; these read as guidance, and both assert a property of the object.

## Acceptance

1. **compliance-lead rules on the replacement wording**, as they did for W-0101. This
   is a statement about what the law requires and is not build-lead's to author. The
   W-0101 ruling is the template and most of its reasoning transfers verbatim.
2. The heading and the intro line describe **the act** — what the user needs to do —
   without asserting the outcome. "How to sign and witness your will" claims nothing;
   "How to Make Your Will Legally Valid" claims the result.
3. **Re-read the whole file against the act-not-object test**, mechanically rather
   than by feel. `fix-batch-G` read `:11` and `:17` while closing W-0101 acceptance 3
   and did not audit the remaining steps, the button copy or the completion state.
4. Rule 9 applies. The step list currently uses numbered circles rather than icons,
   which is fine — do not introduce icons on this surface.

## Working notes

- 2026-08-21 fix-batch-G: **found while applying acceptance 3's test to the file next
  door, deliberately not fixed.** W-0101's acceptance 3 is scoped to *the rendered
  will*, and this is wizard chrome; more importantly it is compliance wording, which
  team-lead has routed to compliance-lead for this whole family. Raising it with the
  statutory analysis already attached is the useful half.
- **Sequencing caution:** `persona-passA3` was driving the estate surface in a live
  browser when this was raised, and `fix-batch-B` owns the will builder. Check both
  before editing this file.

- 2026-08-21 compliance-lead: **RULING — provisional. The analysis holds, it is the third
  instance, and scanning the component found two more claims that are not the one you asked
  about.** **Not an approval** (`05-perimeter.md` §7.3). **Provisional** — legal services is
  Unmapped (§1.1, §1.3). Sources registered as `core/registry/sources.md` rows **A9, A14, C3**.

  ### The claim as raised — confirmed, and I tested it rather than agreed with it

  `WillBuilderSigningStep.vue:11,17` — *"Follow the steps below to make it legally binding"*
  under *"How to Make Your Will Legally Valid"*.

  **Wills Act 1837 s.9(1)**, read `legislation.gov.uk` **2026-08-21**, latest available
  (revised), opens *"No will shall be valid unless—"* and sets four limbs. **I walked the
  checklist against all four rather than accepting the summary:**

  | s.9(1) | Checklist |
  |---|---|
  | **(a)** in writing, signed by the testator | step 4 ✅ |
  | **(b)** *"it appears that the testator intended by his signature to give effect to the will"* | **absent, and cannot be present** |
  | **(c)** signature made or acknowledged before two witnesses present at the same time | steps 2, 4 ✅ |
  | **(d)** each witness attests and signs in the testator's presence | step 4 ✅ — in fact **stricter than required**, since it asks each witness to sign in front of the other, which (d) expressly does not require |

  **So the analysis is right on both limbs.** The same necessary-to-sufficient inversion as the
  footer sentence, **and worse here**, because an imperative heading over a numbered list is
  the form most likely to be read as complete instructions. **And (b) is not a step anybody can
  follow** — it is a state of mind, so no checklist can reach it and the framing promises
  something the format cannot deliver.

  **This is the third instance of one inversion** — the removed footer sentence, this heading,
  this body line. **That is the argument for enforcing the act-not-object test by structure
  rather than by review**, exactly as `documentSignatures.js` now does for signatures. A rule
  applied by reading gets three chances to be missed; a rule applied by a function shape gets
  none.

  ### The wording

  > **Heading:** `Before your will can take effect`
  > **Body:** `Your will has been saved. It is a draft until it is signed and witnessed. Section 9 of the Wills Act 1837 sets out what the law requires; the steps below cover the parts you can prepare for, and Fynla does not check any of them.`

  `until it is signed and witnessed` states a **necessary** condition, matching the statute's
  own *"no will shall be valid unless"*. **`the parts you can prepare for` is the clause that
  does the work** — it stops the list reading as exhaustive without enumerating what it omits,
  and it is true, because limb (b) is not preparable.

  **Do not replace "legally binding" with "legally valid" or vice versa.** Both assert the
  object. The fix is the sentence shape, not the adjective.

  ### Two further claims in the same component — found by scanning every string, as the item's method requires

  **1. `:50` — the will storage fee is wrong by more than a factor of three, and this is
  W-0109's shape again.**

  > Current: *"store your will with the Probate Service for a fee of **£75**"*

  **HM Courts and Tribunals Service publishes £24**, verbatim: *"There is a one-off charge of
  **£24** to deposit a will or its codicil, payable by cheque or postal order."*
  (`gov.uk/government/publications/store-a-will-with-the-probate-service/how-to-store-a-will-with-the-probate-service`,
  **page displays "Updated 13 July 2026"**, read 2026-08-21, service confirmed available.)

  **I verified this against the page rather than a search result**, because a search summary is
  not a source and I have said so about other people's citations today.

  **Register row C3.** Note this page **carries a last-updated date**, unlike the Office of the
  Public Guardian's registration timescale — so unlike that figure it *is* re-checkable, and
  route 1 (source it) is available rather than route 3 (say less). **Recommend stating £24 with
  the provenance in the constant's docblock**, exactly as ruled for the £92 on W-0109.

  **2. `:44` — a flat legal consequence stated in Fynla's own voice, and the provision is
  amended.**

  > Current: *"If a beneficiary or their spouse witnesses your will, their inheritance is
  > **automatically** void."*

  **Wills Act 1837 s.15** does make such a gift *"utterly null and void"* so far as concerns
  that witness. **But s.15 as displayed carries amendments — Wills Act 1968 (c. 28) s.1, plus
  2000 c. 29 ss. 28(4)(a), 33 (in force 1 Feb 2001) and Civil Partnership Act 2004 Sch 4
  para 3.** Register row A14.

  **I am not adjudicating what the 1968 amendment does to any particular gift — that is a
  determination and it is not mine.** What is within competence: **Fynla states an
  unconditional consequence for a provision that has been amended, unsourced, in its own
  voice.** The word carrying the risk is **"automatically"**.

  **This is `W-0153`'s shape** — a legal rule pronounced in Fynla's own unattributed voice —
  and it should be handled under that item's eventual answer rather than patched here alone.
  **Interim wording that claims only what is safe:**

  > `Choose witnesses who are not beneficiaries and not married to or in a civil partnership with a beneficiary. A gift to a witness or their spouse can fail (Wills Act 1837, section 15). Fynla does not check who witnessed your will.`

  `can fail` replaces `automatically void`. The citation makes it a reference rather than a
  pronouncement.

  **3. Flagged, not ruled: `:41` states witnesses must be "18 years or older".** **s.9 states
  no witness age**, and I have not established where that requirement comes from. **I am not
  saying it is wrong** — I am saying it is unsourced and I did not verify it. Whoever takes
  this item should source it or soften it; do not simply keep it because it sounds right.

  ### What I did NOT flag

  The step-4 instruction that each witness sign in front of the other is **stricter than
  s.9(1)(d) requires**. **Over-compliance in an instruction is not a defect** and I am not
  asking for it to be relaxed — a user who follows it satisfies the limb.

- 2026-08-21 fix-batch-G (build-lead): **done, in the same pass as W-0157** — same file,
  same window, kept as two items because the mechanisms differ. Branch document:
  `workforce/branches/fixes/F-0008-batch-g-lpa.md` §5.

  **Compliance's wording verbatim.** Heading → `Before your will can take effect`. Body →
  `Your will has been saved. It is a draft until it is signed and witnessed. Section 9 of
  the Wills Act 1837 sets out what the law requires; the steps below cover the parts you
  can prepare for, and Fynla does not check any of them.`

  **The instruction not to swap the adjective was followed and is recorded in the file**,
  in a comment above the copy, because "legally binding" → "legally valid" is the obvious
  wrong fix and the next person to touch this will reach for it. The fix is the sentence
  shape: *until* restores s.9(1)'s necessary form, and *the parts you can prepare for*
  stops the list reading as exhaustive without enumerating what it omits — limb (b) is a
  state of mind and no checklist reaches it.

  **Acceptance 3 — the whole file re-read mechanically, not by feel.** That is what
  produced W-0157 (compliance's own scan) and it produced nothing further of this shape
  in the remaining strings: the step bodies instruct rather than assert, and the
  step-4 instruction that each witness signs in front of the other is **stricter than
  s.9(1)(d) requires**, which compliance ruled is over-compliance and not a defect. **I
  did not relax it.** Rule 9 clean; the numbered circles are digits, not icons, so
  acceptance 4 needed no change.

  **New spec, because the test is the gate:**
  `resources/js/components/__tests__/Estate/WillBuilderSigningStep.spec.js` — 6 tests
  asserting the necessary-condition form, the absence of both old strings, the
  "parts you can prepare for" clause, and no acronyms. **37 Vitest tests pass** across
  this and the three renderer specs. ESLint clean.

  **Not done:** not browser-verified — a persona-tester closes Rule 14's loop, and
  `persona-passA3` has been on this surface. No commit, no PR, no deploy.
