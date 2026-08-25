---
id: W-0152
title: Divorce terminates an attorney's appointment and the instrument may opt out of that — an election the wizard never offers and the document never mentions
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0008-batch-g-lpa.md
owner: build-lead
status: queued
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
