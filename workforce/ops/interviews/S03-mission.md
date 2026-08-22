# Session 3 — Mission & Who We Serve

**Onboarding session 3 of 9.** Produces `core/constitution/01-mission.md`.
Blocks anything customer-facing being judged for goal fit.
**Date:** 2026-08-13 · **Interviewer:** Chief of Staff · **For:** CSJ
**Present:** CSJ only. Azlan and Brett iterate later via the divergence register.

---

## What I read first

`April/April19Updates/marketing/04-product-strategy.md` §1–3 in full ·
`marketing/README.md` · `README.md` · `docs/reference/Persona Data.md` · `CLAUDE.md` preview
personas · `fynlaBrain/Personas/` · `docs/archive/CSJTODO-freemium-series.md` (title only)

**First correction to my own earlier framing.** I have been calling the strategy
doc "unsigned" and treating it as advisory. Having now read it properly: it is
authored **"Chris Jones (founder)"**, and §1–3 are specific, quantified and
opinionated — SAM figures, pain scores, current-cost ranges, a named acquisition
wedge. This is not generated filler. The only reason it lacks authority is that
`00-precedence.md` §1 ranked it advisory on the strength of a caveat in its
*sibling README*.

**So the real question of this session is narrow: does it still hold, four months
on?** If yes, most of it can be promoted to doctrine largely as written.

---

## Part A — Ratify or amend

### A1. The vision

> "**Every UK household should plan its money the way the wealthiest families do —
> seeing business, pension, property, and estate as one living picture — and pay
> £20/month for it, not £20,000/year.**"
>
> "Fyn… is the thing that makes that price point possible: the financial reasoning
> of a £500/hour advisor, always on, never scolding, never selling you a product."

**Proposal: adopt verbatim as `01-mission.md` §1.** It is testable, which is rare
in a vision — "one living picture", "£20/month not £20,000/year" and "never selling
you a product" are all things a piece of work can be measured against. The last is
already load-bearing: it is the same logic that rules out ads, AUM fees and product
recommendations.

### A2. Segments

**Proposal: adopt the priority ladder as written.**

| Priority | Segment | SAM |
|---|---|---|
| **P0 beachhead** | UK Ltd Co founder-directors, single director, £120k–£300k revenue, 35–55 | ~150k |
| P1 (year 2) | IHT-anxious mass-affluent couples, £800k–£2m, 50–70 | ~300k |
| P2 | LLP partners, professional-services firms | ~80k |
| P3 | NHS / Civil Service DB pensioners | ~200k |
| P4 | First-home LISA savers | ~1m |

**One thing to check.** The P0 rationale rests on "a one-time acquisition wedge
(Oct 2024 BPR cap) that is open *now*" — written 19 April. It is now 13 August.
**Is that wedge still open, narrowing, or gone?** If it has closed, the P0
justification weakens and the ladder may need reordering. This is the single
question in this session most likely to change the answer.

### A3. The not-serving list

> True HNW >£5m · sub-£30k earners with no assets · non-UK residents ·
> business-only customers · day-traders and crypto-native · under-18s

**Proposal: adopt as `03-hard-nos.md` rather than `01-mission.md`.** A
not-serving list is a hard no, and hard nos have their own home in session 4. It
should be one thing in one place, not restated in two.

---

## ANSWERED 2026-08-13

> **CSJ:** The personas represent the clients we are after — so the serving list
> must change. Any change to the personas (added, deleted, materially changed) must
> be represented. **"£150 is arbitrary and is not a level, meaningless. The level of
> income is not an indicator of client."**
>
> **Resolution of B1: reading 2** — the strategy was stale, the product is broader.
> The income-band segmentation in `04-product-strategy.md` §2 is **void**, along
> with the "sub-£30k earners" exclusion that contradicted `student` and
> `young_saver`.
>
> **Written to** `core/constitution/01-mission.md`: §1 vision verbatim, §2 income
> is not an indicator, §3 the personas define who we serve, §3.2 persona changes
> are mission amendments, §4 the capability-not-income exclusion principle.
>
> **Three findings while writing it:**
>
> 1. **There are seven personas, not six.** `widow` is in `PreviewUserSeeder.php`,
>    `docs/archive/appMapping/personaData/` and the vault. `CLAUDE.md` omits it. Logged to
>    `00-precedence.md` §3. It also strengthens the ruling — a widow with a
>    transferable nil-rate band is defined by situation, not income.
> 2. **Four sources define personas.** Ruled: `PreviewUserSeeder.php` is canonical
>    because it is executable and cannot drift silently — same principle as Rule 2
>    and `TaxConfigService`. Everything else describes.
> 3. **Two exclusions survive on capability, two are now questionable.** Non-UK
>    residents, business-only and under-18s are structural and stay. HNW >£5m is a
>    wealth band and cannot stand on that basis alone. Day-traders needs a "can't"
>    versus "haven't built" answer.
>
> **What §2 does not settle:** who to *acquire* first. The old P0–P4 ladder is void
> because it was built on income, but go-to-market sequencing is a separate and
> legitimate question — deferred to `06-commercials.md`, session 7.

## Part B — The tension worth a real answer

### B1. The product's personas contradict the strategy's segments

Fynla ships six seeded preview personas. Mapped against the priority ladder:

| Persona | Focus | Segment |
|---|---|---|
| `entrepreneur` — Alex Chen | SIPP, business interests | **P0** — the only one |
| `peak_earners` — David & Sarah Mitchell | Properties, SIPP + NHS pension | P1 / P3 |
| `retired_couple` — Patricia & Harold Bennett | Decumulation, estate | P1 |
| `student` — Janice Taylor | LISA, student loan, early career | **P4**, the lowest priority |
| `young_saver` — John Morgan | Emergency fund, first-time savings | **Not on the ladder at all** |
| `young_family` — James & Emily Carter | Mortgage, workplace pensions | **Not on the ladder at all** |

**One of six represents the beachhead. Two of six represent segments the strategy
does not list**, and `young_saver` — emergency fund, first-time savings — sits
close to "sub-£30k earners with no assets", which is on the *explicitly not
serving* list.

**The fair counter-argument:** preview personas are demonstration tools showing
breadth of capability, not a statement of who is being sold to. That is legitimate,
and if it is the answer I will record it.

**But it matters for judgement.** When Growth proposes a campaign, or Product
proposes a feature, the Chief of Staff has to answer "is this in line with who we
serve?" Right now the strategy says founder-directors and the product's own demo
surface says something much broader. **Which one is the truth?**

Three ways this can go:

1. **The strategy is right; the personas are demo breadth.** Record it, and the
   Chief of Staff judges against the ladder alone.
2. **The strategy is stale; the product has moved broader.** Rewrite the ladder to
   match what is actually being built.
3. **Both are true because there are two motions** — a narrow paid beachhead and a
   broad free/low-tier funnel. If so, that needs saying explicitly, because it
   changes every downstream judgement. `docs/archive/CSJTODO-freemium-series.md` exists, which
   hints at this.

### B2. "Every UK household" versus a 150k beachhead

The vision is universal. The segment discipline is narrow and has an explicit
exclusion list. Both can be true — universal ambition, sequenced execution — but
the Chief of Staff will meet cases where they pull apart.

**Proposal for the tie-break, to write into `01-mission.md`:** the vision sets
direction; **the ladder governs decisions.** Work aimed outside the current
priority segments needs a stated reason, not just a mission quote. Say if you'd
rather it ran the other way.

---

## Part C — Open questions

1. **Is the BPR wedge still open?** (A2) — most likely to change the answer.
2. **Which of the three readings in B1 is true?**
3. **Is there a freemium motion**, and if so, does it change who we serve or only
   how they arrive?
4. **Has anything material changed since 19 April** — competitors, regulation,
   a segment that responded better than expected?
5. **Are the SAM figures yours or estimated?** They will be quoted in filings and
   marketing; I should record their provenance and confidence.
6. **P1 was "year 2 expansion".** Where are we against that clock?

---

## Completeness check

**What I can draft without further input:** all of `01-mission.md` §1 (vision) and
§2 (segment ladder), pending only the BPR answer.

**What I cannot:** the B1 resolution. It is the difference between a Chief of Staff
that rejects a young-saver feature as off-strategy and one that welcomes it, and
guessing would produce months of subtly wrong judgements.

**What I'll assume until told otherwise:** reading 1 — strategy governs, personas
are demo breadth — because it is the more conservative default and the easier of
the three to correct.

**Blocking:** nothing in Phase 1. But `01-mission.md` cannot be ratified without
B1, and until it is ratified the Chief of Staff has no goal-fit standard for
customer-facing work.
