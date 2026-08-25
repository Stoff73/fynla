# W-0101 — build-lead → quality-lead

Branch document: `workforce/branches/fixes/F-0008-batch-g-lpa.md` §3.
compliance-lead's ruling on acceptance 3 is in W-0101's own working notes — **read it**,
it is the reasoning behind half of this change.

## Done

- **No name is drawn on any signature line, in either renderer.** The testator's mark,
  both witnesses' marks, and the `.signed-name` script face are gone.
- **`signed_date` is consulted for nothing.** The attestation date and the witness Date
  field are always blank; Full Name, Address and Occupation are still filled.
- **compliance's replacement wording is in, at the top of the document**, rendered from
  `renderWillDocument()` so preview and print take it from one place. The old footer and
  its dead CSS are removed.
- **Rule 20:** `resources/js/utils/documentSignatures.js` is new and is the one home;
  `lpaDocumentRenderer.js` was converged onto it in the same change.
- **31 tests passed** across three renderer specs. ESLint clean on everything changed.
- **W-0143 raised** — the same overclaim one file away, in the signing step.

## Not done, and why

- **No browser verification.** `persona-passA3` was live on the estate surface, and Rule
  14's loop is a persona-tester's. I did not touch bequest sync, mirror generation or
  `WillPlanning.vue`.
- **W-0143 not fixed** — compliance wording, and `fix-batch-B` owns that file.
- **Two pre-existing lint findings left alone**, both verified present on `HEAD`:
  `WillBuilderReviewStep.vue:69` unused `index`, and an unused `eslint-disable` on the
  `document.write` line.
- No commit, no PR, no deploy.

## What you need that isn't obvious from the artefacts

1. **`documentSignatures.spec.js` is a register, not a spec — that is the point.** It
   enumerates renderers in an array and runs the same assertions over each.
   **If a third document generator is added and not added to that array, this suite
   still passes and the rule is unenforced for it.** That is the one weakness of the
   design and it is deliberate: the alternative was a lint rule, which cannot see
   rendered output. Reviewing a new renderer means checking it is in that array.
2. **`blankSignatureLine()` takes no arguments on purpose.** If you see a future change
   add a parameter to it, that is the defect coming back.
3. **The blank attestation date is a judgement call, not an oversight.** The user may
   have recorded a real signing date and the document no longer shows it. I decided the
   clause's first-person voice ("I have hereunto set my hand this …") makes a filled date
   an assertion of execution. If product disagrees, the date belongs somewhere that is
   not an operative clause.
4. **Do not "improve" this document by adding the sibling's disclaimer.** The Lasting
   Power of Attorney record says it is not one and cannot be used as one; that is correct
   there and **false here**, because a will has no prescribed form. A test asserts the
   absence of that wording — if it goes red, read compliance's ruling before changing it.
5. Compliance's ruling is marked **provisional** — legal services is an unmapped regime.
   The wording implements it; it is not an approval.

## Assumptions I made

- **That the operative clauses stay as they are.** `I APPOINT`, `I GIVE AND BEQUEATH`,
  `shall in their absolute discretion` are the draft instrument in the testator's voice —
  what a will is, not Fynla asserting a property of it. I applied the act-not-object test
  to every string and kept them deliberately; a reviewer could take a stricter line.
- That `.filled` (10pt, plain) does not read as a signature the way `.signed-name`
  (18pt cursive) did. I believe that is obviously true, but it is a visual judgement made
  without seeing it rendered.
- That removing the `disclaimer` div breaks no consumer. It was only ever emitted by
  `printWillDocument`, and its CSS is now unreferenced.

## Surfaces covered / not covered

- **Web — covered in code, unverified in a browser.** Both the will preview
  (`WillBuilderReviewStep`) and the print output (`printWillDocument`,
  `WillBuilderSigningStep`) take the change from the one renderer.
- **`/m` and iOS — not applicable.** Neither has a will document renderer;
  `resources/mobile/` and `ios-native/` contain none. `/m` hands off to the web will
  builder (`estate_will`), so it reaches this document through the surface that is fixed.
