# 07 — Quality Bar

**Status:** Written 2026-08-13, session 8. §4 open.
**Owner:** CSJ. Amendments gated.

What "done" means, per artefact type. This is the standard the Chief of Staff
judges against on axis 3 of every review.

**Most of it already existed** — in `CLAUDE.md`, the evidence gate, and the
`plan-and-build` and `prd-writer` skills. This file names the constant that runs
through them and fills the gaps for artefacts nobody had written a standard for.

---

## 1. The constant

**Nothing is claimed that has not been demonstrated, and every gap is named.**

That single rule is already stated six separate ways across the codebase, which is
how you know it is the real standard:

| Where | How it appears |
|---|---|
| `CLAUDE.md` Rule 14 | Loop until green per the plan; no partial success, no apologies instead of fixes |
| `CLAUDE.md` browser rules `:449–456` | "Browser tested" means you interacted. **"If you can't test something, say I COULD NOT TEST THIS"** — never "verified" for untested items |
| `08-process.md` §2.3 | Evidence must contain artefacts that cannot be written from imagination |
| `prd-writer` | "Do not invent content to fill a section — write `_Not applicable — {reason}_`" |
| `ComplianceRules.php` | "If you do not have sufficient data… say so honestly." Never speculate |
| `plan-and-build` | "If the agent claims 'all done' — verify at least one specific claim" |

**A named gap is a pass. A hidden gap is a failure.** An artefact that says "I could
not verify X" is complete; one that quietly omits X is not, however polished.

## 2. Four questions, every artefact

1. **Demonstrated?** Is every claim backed by something checkable — output, a
   citation, a figure, a screenshot?
2. **Gaps named?** Is what is missing, untested, assumed or out of scope stated
   explicitly?
3. **Trunk-checked?** Goal fit (`01`), values (`02`), hard nos (`03`), voice (`04`),
   perimeter (`05`).
4. **Surfaces explicit?** Web, `/m`, iOS named individually — never "the app"
   (Rule 19).

## 3. Per artefact

Existing standards are **referenced, never restated.**

| Artefact | Standard | Additionally |
|---|---|---|
| **Code** | `08-process.md` §2 evidence pack | Author never verifies own work (§2.4) |
| **Spec / plan** | `plan-and-build` — browser checkpoints written into the spec, tasks grouped, checkpoint markers between groups | Acceptance stated before implementation starts, not after |
| **PRD** | `prd-writer` — canonical 9 sections | Prerequisite spec *and* plan must exist |
| **Tests** | `tests/CLAUDE.md` | A skipped browser stub is not a pass |
| **Marketing copy** | `04-voice.md` constants + register; `05-perimeter.md` §3 | Targeted at a persona (`01-mission.md` §3), never an age or income band. Compliance screen before the approve-to-production button. |
| **Analysis / data report** | — **new** | States its source, its method, its confidence, and **what it does not show**. A figure without provenance is not a finding. Privacy-first analytics cannot yield per-user claims (`06-commercials.md`). |
| **Compliance review** | `05-perimeter.md` §7.3 | Two outcomes only — *no issues within competence*, or *flagged with reason and dated source*. Never an approval. |
| **Knowledge / vault doc** | — **new** | Parent link resolves, `verified` date present, one home only. An orphan is invalid (§4.3 of the workforce design). |
| **Meeting note** | `registry/rhythm.md` §4bis | Decisions and actions extracted; anything doctrinal raised as a trunk amendment, never left in the note |

## 4. Open

**Does non-code work need independent verification, as code does?**

`08-process.md` §2.4 is firm for code: the author never verifies their own work.
Applying that everywhere would be expensive — a second agent reviewing every vault
doc is not obviously worth it.

**Proposal — independent verification where a mistake is costly or hard to
reverse:** marketing copy before publication, compliance reviews, analyses that
inform a decision, and any trunk amendment proposal. Self-verification is
acceptable for specs, plans, meeting notes and knowledge docs, which are cheap to
correct and get reviewed in use.

## 5. What this file is not

It is not a checklist to be completed and filed. **The four questions in §2 are the
whole test**, and an artefact that passes them without ceremony is done. Adding
process is not the same as adding quality, and a quality bar that becomes paperwork
gets routed around.
