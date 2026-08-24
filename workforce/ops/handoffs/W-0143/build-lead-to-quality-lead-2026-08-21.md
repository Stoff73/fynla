# W-0143 and W-0157 — build-lead → quality-lead

One note for both: same component, same pass, two items because the mechanisms differ.
Branch document: `workforce/branches/fixes/F-0008-batch-g-lpa.md` §5.
**Read compliance's rulings on both items** — every string is theirs, and what is
deliberately *not* changed matters as much as what is.

## Done

- **W-0143:** heading and body replaced with compliance's wording. The old copy stated a
  sufficient condition where s.9(1) states necessary ones.
- **W-0157 (1):** storage fee **£75 → £24**, moved from an inline string to a constant
  whose docblock carries the full provenance and the instruction to update
  `sources.md` row C3 in the same edit.
- **W-0157 (3):** witness age softened — it could not be sourced, so it says less and
  says whose suggestion it is.
- New spec, 6 tests. **37 Vitest tests pass** across it and the three renderer specs.
  ESLint clean.
- **W-0144 raised** from applying limb 2 of the sharpened test to the renderers.

## Not done, and why

- **W-0157 (2), the "automatically void" sentence, is untouched — deliberately, on
  instruction from both compliance and team-lead.** It is W-0153's shape, and patching
  one instance leaves true the thing W-0153 exists to fix. **Interim wording exists and
  is ready to apply the moment W-0153 is answered.** Stated plainly: this leaves an
  unconditional legal consequence, for an amended provision (row A14), live in Fynla's
  own voice. That is a recorded exposure, not an oversight.
- **W-0144 not fixed** — every option changes the legal effect of the document or the
  flow of the builder.
- Not browser-verified. No commit, no PR, no deploy.

## What you need that isn't obvious from the artefacts

1. **Do not "improve" the copy by swapping "legally binding" for "legally valid" or back.**
   Both assert the object; the fix is the sentence shape. A comment above the copy says
   so, and a test asserts both old strings are absent — that is the trap, not neatness.
2. **The step-4 instruction is deliberately stricter than the law requires.** Compliance
   ruled that each witness signing in front of the other exceeds s.9(1)(d), and that
   over-compliance in an instruction is not a defect. **Do not "correct" it.**
3. **`Of sound mind`, in the same bullet list, is the same unsourced shape as the witness
   age.** It was not flagged, I did not touch it, and I am not asserting it is wrong —
   recorded so it is not lost.
4. **The fee's provenance is in the constant, not only in the register.** £75 survived
   because it was inline, unsourced and undated; a register entry alone would not have
   caught it, because nobody opens the register next to a string nobody suspects.
5. **Both renderer specs now pin the qualification above the title rule**, not merely
   present. That is a dependency compliance flagged: the witness's printed name is typed
   in advance — a plan, not a record — and is safe only while the document says what it
   is before the reader reaches it.

## Assumptions I made

- **That softening the age rather than deleting the bullet is the right "say less".**
  Deleting it would drop a useful steer; keeping a number would keep an unsourced legal
  assertion. Naming it as Fynla's guidance does both jobs, but it is my wording, not
  compliance's — they ruled "source it or soften it" and left the form to me.
- That the numbered circles are digits rather than icons, so acceptance 4 needed no
  change. They render as `1`–`5` in styled divs.
- That mounting the component with a stubbed `router-link` and a minimal `formData` is
  representative. The step reads almost nothing from `formData` except on print.

## Surfaces covered / not covered

- **Web — covered in code, unverified in a browser.**
- **`/m` and iOS — no will builder exists on either.** `/m` hands off to the web builder
  (`estate_will`), so it reaches this screen through the surface that is fixed.
