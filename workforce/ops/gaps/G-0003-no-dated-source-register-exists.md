---
id: G-0003
class: tool
agent: compliance-lead
severity: degrading
opened: 2026-08-21
action: fix
blocking: []
status: open
---

# `05-perimeter.md` §7.2 requires a dated source register. There isn't one.

## The gap

§7.2 states its sources are *"maintained as a dated source register. A citation
without a date is not a citation."* **No such register exists anywhere in
`workforce/`.**

Every artefact that needs one therefore builds its own inline, and each register
dies with the report that carries it. Nothing accumulates, so nothing can be
re-checked when the law moves.

## Evidence

`grep -rl "source register" workforce/` returns the requirement itself, four
reports that each built their own, and one branch document — and no register:

- `core/constitution/05-perimeter.md` — §7.2, the requirement.
- `ops/reports/2026-08-21-W-0050-consent-validity-ruling.md` — inline register.
- `ops/reports/2026-08-21-W-0100-lpa-perimeter-review.md` — inline register.
- `ops/reports/2026-08-21-W-0019-perimeter-delta.md` — inline register.
- `ops/reports/2026-08-21-perimeter-regime-map-proposal.md` — inline register.

`compliance-lead` routed this to `chief-of-staff` on 2026-08-21 with the observation
that **"this is the third artefact in two days to carry its own inline source
register because `workforce/` has none. The workaround is now the habit."** Recording
it here makes it durable and sweep-visible rather than a noticed-and-routed line in
one report.

## What it has already cost — commencement, three times

The absence is not theoretical. **Commencement is the repeat failure mode in this
codebase's legal citations**, and all three instances were caught one at a time by
whoever happened to look:

- **W-0050 cited PECR reg 6(4).** Regulation 6 was substituted on 5 February 2026 by
  the Data (Use and Access) Act 2025. The citation was stale when written, and
  nothing existed to check it against.
- **Mental Capacity Act 2005 Sch 1** carries pending Powers of Attorney Act 2023
  amendments, not in force as at 20 August 2026 — found while reviewing W-0100.
- **DMCC Act 2024 Part 4 Chapter 2 commencement could not be pinned at all**, and
  the material behind the attempt was search-derived rather than read from the
  instrument.

A register with a `checked` date per instrument turns each of these from a lucky
catch into a routine one. This is now flagged in `05-perimeter.md` §1.1 so a reader
citing any statute in the regime map hits the warning first.

## Resolution

A single dated register, one row per instrument: instrument, the provision relied
on, the date checked, and the amending instrument if any. Populate it from the four
inline registers above, which already hold the work.

**Recording a source and its date is not a legal determination**, so building and
maintaining the register sits inside `05-perimeter.md` §7.3's competence boundary.
The file it lives in is `compliance-lead`'s to propose; `registry/` is the likely
home, since this is where-things-live rather than doctrine.

---

## Update 2026-08-21 — built, and the trunk now points at it. Still open.

**The register exists:** `core/registry/sources.md`, created by `compliance-lead` under
this gap, in the registry house style with `Owner: CSJ. Amendments gated.` It holds
**13 source rows** — A1–A10 (Act and regulation body text), B1 (a fee whose amount sits
outside the operative provision) and C1–C2 (undated published guidance, C2 recorded as
rejected with its reason).

**The trunk no longer contradicts it.** Two statements in `05-perimeter.md` said the
register did not exist and were false the moment it did. Corrected by the Archivist the
same day under `00-precedence.md` §2.1 — §1.1's *"one does not exist yet"* now points at
`registry/sources.md`, and §7.2's *"maintained as a dated source register"* carries the
path. Recorded in `ops/reports/2026-08-21-consistency-sweep.md`.

**It found a third failure mode while being built**, beyond the two commencement cases
that opened this gap: **the lasting power of attorney registration fee moved £82 to £92
on 17 November 2025**, and the amount is not in the regulation anyone would cite — that
provision says only that a fee *shall be payable*. So an agent citing it and dutifully
re-reading it would have found nothing wrong, twice. **Nine months of a wrong number
shown to users on three surfaces** (W-0109). The register's §2 classifies this as Class
B and says what to watch instead.

## Why this stays open

**The migration this gap asked for has not happened.** Its Resolution called for
populating the register from the four inline registers that already held the work;
team-lead deliberately scoped that out, and `sources.md` §5 records the decision — those
four artefacts *"point here going forward; this file does not reach back."*

So the gap is **partially resolved by design**, not stalled. What remains:

1. The four inline registers are unmigrated and will die with their reports.
2. `sources.md` is scoped to one instrument family — the powers-of-attorney work.
   **Privacy, consumer protection and advertising have no rows at all**, which is
   exactly why §1.1 now says an absence there is not clearance.

Closing is a judgement for whoever judges it, and it is no longer blocked by the trunk
contradicting the file.

---

## Resolution progress — 2026-08-21, compliance-lead

**The register is built: `core/registry/sources.md`.** Authorised by team-lead with a
defined scope; created in `registry/` as this record proposed, in the house style of the
other registry files (drafted by an agent, **Owner: CSJ, amendments gated, awaiting CSJ
correction**), with maintenance recorded as `compliance-lead`'s per `05-perimeter.md` §7.2.

### What it holds

**Eleven rows**, from the Lasting Power of Attorney work of 2026-08-21 (W-0100–W-0109,
W-0151, W-0152) — Mental Capacity Act 2005 ss. 9, 10, 11(7), 13 and Sch 1 paras 1–2;
Legal Services Act 2007 Sch 2 para 5(3); Wills Act 1837 s.9; S.I. 2007/1253 reg 8;
S.I. 2007/2051 the Schedule; and two Office of the Public Guardian guidance pages.

### The structural finding it is built around

This record's own diagnosis was **commencement**. Building the register found a second
failure mode that commencement-checking does **not** catch, so the file is organised by
**where a source lives**, which is what predicts how it goes stale:

- **Class A — body text of an Act or regulation.** Moves by amendment; `legislation.gov.uk`
  annotates outstanding effects against the provision. Check the annotations and you have
  checked.
- **Class B — a Schedule, or an amount set outside the operative provision.** Moves under a
  **separate amending order, leaving the parent regulation unchanged.** **An agent watching
  the regulation sees nothing.**
- **Class C — an undated published web page.** Nothing signals a change at all.

**Class B is what actually cost users money.** The lasting power of attorney registration
fee is **not in regulation 5 of S.I. 2007/2051** — that provision says only that a fee
*"shall be payable"* and carries no amount. **The amount is in the Schedule, and it moved
£82 → £92 under S.I. 2025/1126 on 17 November 2025 without regulation 5 changing a word.**
An agent citing "regulation 5" and dutifully re-reading it would have found nothing wrong,
twice. Fynla told users £82 for nine months on three surfaces (W-0109).

**So this gap's cost has changed in kind.** The first two instances above — the stale PECR
citation and the Sch 1 pending amendments — cost accuracy **between agents**. The third
cost **a wrong number to a user about money they pay.** That is what carried the
authorisation, and it is recorded here because it is the argument that will be needed if
the register is ever thought too expensive to maintain.

The register also records **a rejected source with its reason** (the Office of the Public
Guardian's registration timescale, class C, undated) — otherwise the next agent re-finds it
and uses it.

### Deliberately not done, on instruction

- **The four earlier artefacts were NOT migrated in.** Team-lead: *"Do not go back and
  re-source the other four artefacts into it. Point them at it going forward."* Their inline
  registers stand; §5 of the register names all four and says the file does not reach back.
  **A register that tries to be complete on day one is a register nobody maintains.**
- **PECR, the DMCC Act 2024 Part 4 and the ICO special-category guidance have no rows.**
  Recorded as standing warning W3 in the register, explicitly as *scope, not clearance*.
- **The Powers of Attorney Act 2023's own commencement section was not read.** Amendment
  status throughout is read from the annotations against the amended provisions. Recorded
  as standing warning W1, because it is enough for W-0102–W-0108 and **not** enough for
  anyone building against the registration scheme.

### Status left `open`, deliberately

**Not closed by me.** This record's Resolution called for populating the register from the
four inline ones, and team-lead scoped that out — so the gap is **partially resolved by
design**, not finished. Two things also remain outside my hands:

1. **The trunk still says the register does not exist.** `05-perimeter.md:68` reads
   *"one does not exist yet (`ops/gaps/G-0003`)"*, and §7.2 at `:305` has no pointer.
   **Both are now factually stale.** The trunk is CSJ-owned and gated, so the correction is
   **drafted and routed to the Archivist**, not applied by me — the same line I held on §6.
2. **CSJ correction**, per the house style of every other registry file.

Whoever judges this gap closed should confirm both. Until the trunk points at it, an agent
reading `05-perimeter.md` §1.1 will still be told there is no register.
