---
id: W-0152
title: Divorce terminates an attorney's appointment and the instrument may opt out of that — an election the wizard never offers and the document never mentions
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0008-batch-g-lpa.md
owner: build-lead
status: done
claimed_by: null
severity: medium
surfaces: [web]
created: 2026-08-21T18:55:00Z
claimed: null
blocked_by: [W-0108]
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: ["W-0108 health and welfare instrument silent on when attorneys may act — settles how a not-a-choice statement is written", "W-0107 replacement-attorney claim wrong for jointly and severally", "W-0100 acceptance 1-4, fix-batch-G", "workforce/ops/reports/2026-08-21-lpa-claims-rulings.md"]
prior_art_outcome: extend
constitution_refs: [05-perimeter]
source: recommended by compliance-lead while ruling W-0107/W-0108, 2026-08-21; routed to team-lead because compliance holds no ID block
---

## Intent

**Mental Capacity Act 2005 s.13(6)(c): the dissolution or annulment of a marriage or
civil partnership between donor and attorney terminates that attorney's appointment.
s.13(11) lets the instrument provide otherwise — an express opt-out. Fynla offers neither
the election nor any mention of the default.**

So a donor who appoints their spouse as attorney, and who wants that appointment to
survive a divorce, **has no way to say so** — and a donor who does not want it to survive
is never told that it already does not.

**Deliberately raised separately from W-0107**, on compliance-lead's routing: W-0107 is a
**check that states a wrong consequence**, this is a **wizard gap** — a choice the law
makes available and the product does not. Different half of the system, different fix.

**Sequenced after W-0108**, which is `blocked_by`. W-0108 settles how the product states
something that is **not a choice** (the statutory position on when attorneys may act),
and this item needs that pattern before it can state the s.13(6)(c) default alongside the
s.13(11) election. Building this first would mean inventing the register twice.

## Acceptance

- [ ] The s.13(6)(c) default is stated in the document, in the register W-0108 establishes
      — visibly not-a-choice, out of the document's first person, attributed.
- [ ] The s.13(11) opt-out is offered as an explicit election **the donor makes**, not a
      default the wizard writes for them. **This is the W-0100 defect that was already
      fixed once**: the generator silently defaulted an unanswered "when attorneys can
      act" to *"only when I have lost mental capacity"*, writing a legally operative
      election the donor never made. **An unanswered opt-out must not become an answer.**
- [ ] Nothing asserts what the law requires beyond the citation — the act, not the object.
- [ ] Rule 9: "Lasting Power of Attorney" spelled out; no acronyms in user-facing text.
- [ ] Rule 19: if the election reaches a stored field, say explicitly whether `/m` and iOS
      have a counterpart. Note W-0110 — **there is no powers-of-attorney surface on either
      today, while Fyn can create one** — so the honest answer is likely "no counterpart,
      tracked by W-0110" rather than new mobile work.

## Working notes

(append-only)

- 2026-08-21 team-lead: filed on compliance-lead's recommendation, in its framing, from
  the W-0107/W-0108 ruling pass. Filed at W-0152 from the coordinator block because
  compliance holds none; see the ledger in `workforce/ops/FORMATS.md`.
- 2026-08-21 compliance-lead, via team-lead: **commencement checked and it does not bite
  this item.** The Powers of Attorney Act 2023 amendments outstanding against MCA Sch 1
  sit on paras 4(1)(a), 4(2), 4(3), 5, 7, 8, 9, 10, 11, 13 and 14 — **the registration
  scheme**. ss.9, 10, 11(7) and 13(5)–(11) are clean as at 2026-08-21. Stated limit: the
  amendment status was read against the amended provisions, **not** against the 2023 Act's
  own commencement section — sufficient here, **not** sufficient for anyone building the
  registration scheme.
- 2026-08-21 team-lead: **provisional on its face.** Legal services is `Unmapped` under the
  regime map CSJ adopted 2026-08-21 (`05-perimeter.md` §1.1). Per §1.3, apply what is
  reachable, name what is not, and write the §6 question rather than the answer.

---

## Closed 2026-08-31 — the default stated, the election offered

`grep` for `13(6)`, `13(11)`, `dissolution` and `annulment` across `app/`, the
migrations and the estate frontend returned nothing before this change: neither half
existed.

**Acceptance 1 — the default is stated in W-0108's register.** W-0108 settled the
register but landed only its compliance-check half, so the register itself did not
exist in the document. It does now, once:
`resources/js/utils/lpaDocumentRenderer.js:33-43` (`statutoryNote()`), carrying the
`clause-statutory` class so the treatment survives print from the one place that
serves both the screen and the print stylesheet. Section 6 renders on **both**
instrument types — s13(6)(c) is not limited by type — at `:190-215`.

**Acceptance 2 — the opt-out is the donor's election, and an unanswered one stays
unanswered.** Three distinct states, never collapsed:
- `true` → the express s13(11) direction is written.
- `false` → the donor's recorded decision to leave the law as it stands.
- `null` → "Not specified", and the wizard says in as many words that leaving it
  unanswered is allowed (`DissolutionStep.vue:70-77`).

The column is nullable with no default (`2026_08_31_210000_add_dissolution_election_to_lpas.php`),
the model casts it without defaulting, and `LpaWizard.vue:226` seeds it `null` rather
than to a side. A Vitest case asserts that `undefined` and `null` produce **neither**
clause.

**Acceptance 3 — nothing asserts beyond the citation.** The register states what the
Act provides and attributes it; it does not tell the user their instrument is valid,
compliant or sufficient.

**Acceptance 4 — Rule 9.** "Lasting Power of Attorney" is spelled out at every
occurrence in the step and the document section; no acronym appears.

**Acceptance 5 — Rule 19: no `/m` or iOS counterpart, and that is the honest answer.**
The election reaches a stored field, but there is no powers-of-attorney form on either
surface to carry it — W-0110 (closed today) gave `/m` a read-back and a handoff, not a
form. Native remains out of scope entirely. So the field is writable on web and via
Fyn's `update_power_of_attorney`, and readable on `/m` through the web handoff.

### Tests — the diff only

- `resources/js/utils/__tests__/lpaDocumentRenderer.spec.js` — 4 new (register on both
  types, each election, and the unanswered case rendering neither clause). 13 passing.
- `tests/Feature/Estate/LpaControllerTest.php` — 3 new (true, false and null stored
  distinctly). 26 passing.
- Regression: 59 frontend tests across the utils and Estate component suites.

## Adjacent, reported not fixed

`LpaWizard.vue:218` seeds `when_attorneys_can_act: 'only_when_lost_capacity'` in the
form's initial state. The **renderer** no longer defaults it (W-0100 fixed that), but
the wizard still posts that value for a donor who never opened the step, so the
election can still be made for them one layer up. Same shape as this item, different
layer — worth its own board entry rather than a silent fix here.

## Not done

No `compliance-lead` review of the new user-facing copy stating a statutory rule —
the same gap W-0108 closed with.
