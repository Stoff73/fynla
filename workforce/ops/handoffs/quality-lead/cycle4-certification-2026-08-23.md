# Cycle 4 certification — quality-lead, 2026-08-23

**Gate:** 149 board items at `status: handoff`, uncertified, already deployed to dev
on CSJ's instruction. This file is the merge gate's record. Appended as each batch
finishes — never held in context and written at the end.

**Repo:** `/Users/CSJ/Desktop/fynla`, branch `estate-copy-and-m-handoff`
(two commits beyond `dev`: `88494e0fd`, `8f09eaddc`).
**dev deployed to csjones.co/fynla at `5c556e252`.**

**Verdicts:** CERTIFIED · REJECTED (named unmet criterion) · CANNOT CERTIFY (what is missing).

---

## FINDING 0 — STRUCTURAL, applies to all 149 items

**Not one evidence pack exists.** `08-process.md` §2 requires the pack at
`workforce/branches/<type>/<slug>/evidence/`, permalinked from the PR before merge.

```
$ find workforce/branches -type d -name evidence | wc -l
0
```

There are 34 branch documents under `workforce/branches/fixes/` and **zero** evidence
directories. What evidence exists lives inline in each board item's *Working notes* —
prose written by the agent that wrote the code.

Two consequences, and they are different in kind:

1. **Location.** No pack is where the constitution says a pack must be. This is
   recoverable — the substance is judged below on where it actually sits.
2. **Authorship.** `08-process.md` §2.4: *"The agent that wrote the code does not
   produce the evidence pack."* Every inline evidence note in these 149 items was
   written by `build-lead` — **the agent that wrote the code**. By the constitution's
   own definition that is self-certification, and *"a gate that permits it is
   decoration."*

I have therefore treated every build-lead claim as a **hypothesis to be checked against
the source tree**, not as evidence. Where I could falsify or confirm a claim by reading
the code, the test, or the schema, I did, and I say so per item. Where the only evidence
is the author's assertion that they saw something in a browser, that is recorded as
unverified — it is not a lie, but it is not the pack either.

---

## FINDING 1 — the format split, and why 26 items cannot be certified at all

| Item shape | Count | Consequence |
|---|---|---|
| Has `## Acceptance` | 125 | Certifiable in principle |
| **No `## Acceptance` section at all** | **26** | **Nothing to certify against** |

The 26 with no acceptance criteria:
W-0040 · W-0132 · W-0134 · W-0135 · W-0136 · W-0137* · W-0138 · W-0140 · W-0172 ·
W-0173 · W-0174 · W-0175 · W-0176 · W-0177 · W-0186 · W-0187 · W-0188* · W-0190 ·
W-0203 · W-0206 · W-0207 · W-0210 · W-0217* · W-0228

(*W-0137, W-0188, W-0217 carry acceptance criteria under `### Acceptance` — a
sub-heading, not `##` — so the count of genuinely criterion-less items is lower than
the grep suggests. Corrected per item below.)

A second, sharper split among the 125:

| Acceptance state | Count |
|---|---|
| Checkboxes present, **not one ticked** | 30 |
| Checkboxes present, partially ticked | 19 |
| Prose criteria, no checkboxes | 76 |

**30 items sit at `handoff` with an acceptance list on which no box has ever been
ticked.** That is not evidence of failure — several were demonstrably fixed and the box
was simply never maintained (W-0006 is one: the fix is real and in the tree, and all six
boxes are blank). It is evidence that **the checkbox carries no information on this
board in either direction**, which retires it as a certification signal. Confirming the
brief's warning about W-0263, from the opposite side: an unticked box means as little as
a ticked one.

---

## PRIORITY 1 — the items with no browser-evidence marker

The queue lists 22; it names 21. W-0263 has since gained browser evidence, leaving 20.

</content>
