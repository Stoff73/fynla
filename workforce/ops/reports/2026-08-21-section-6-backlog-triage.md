# §6 backlog — ordered and characterised

**Agent:** compliance-lead · **Date:** 2026-08-21
**Task:** team-lead — order and characterise the §6 questions. **Explicitly not to answer them.**

> **No question below is answered, and nothing here is an approval.** `05-perimeter.md` §7.3.
> Characterising a question — who can answer it, what it blocks, whether the conservative
> action has already been taken — is not answering it, and I have held that line throughout.
> Where characterising would have required a determination, I say so instead.

---

## 0. Two corrections before the triage, because both change what you are looking at

### There are seventeen questions, not fourteen. I undercounted.

I told you §6 grew from six to fourteen today. **The real figure is seventeen**, because the
W-0050 consent ruling raised **three** further §6-class questions that I did not count:

> *"New §6 questions: (a) is the consent collected through the registration wall since
> 2026-04-07 valid, and what follows for the data; (b) can Sch A1 para 4 reach any analytics
> deployment that shares with a third-party processor; (c) what disclosure attaches to
> affiliate-incentivised third-party acquisition content for an unauthorised firm."*

They are listed in that report's **Needs** as a CSJ gate — *"trunk amendments — §1 regime map,
§7.2 sources, three §6 questions"* — and **the gate has not been actioned, so they are not in
the trunk.**

### One of my own questions duplicates one of them, and the duplication has a cause

**My Q11** — *does Schedule A1's statistical-purposes exception reach Fynla's analytics* — **is
W-0050's (b)**, slightly narrower. I wrote it today without knowing (b) existed.

**I did not miss it through carelessness. I could not see it.** (b) lives in a report's Needs
section awaiting a gate; the trunk's §6 lists six questions and I numbered from six.

**That is the same disease as `G-0003`.** The dated source register did not exist, so every
artefact built its own inline copy and nothing accumulated. **§6 is in exactly that state
now:** questions live in the trunk, in ungated report Needs sections, and in individual
rulings — and an agent reading the trunk sees a third of them. The first duplicate appeared
within a day. **It will not be the last, and duplicates are the cheap symptom** — the
expensive one is a question everybody assumes was asked.

**Recommendation at §5.** It is the single most useful thing that could be done to this
backlog, and it is not ordering.

---

## 1. The ordering axis that matters most, and the trunk does not have it

Before the buckets you asked for, the distinction that actually ranks these:

> **Can answering this question reveal a risk Fynla is running right now, or can it only
> permit relaxing something Fynla is already doing conservatively?**

**Fynla's posture is fail-closed.** So for a large part of this backlog, **the conservative
action has already been taken and the answer could only loosen it.** Those questions cannot
surface an exposure — they are optimisation, and they belong at the back of any paid
engagement.

The questions that matter are the ones where **Fynla is doing something today and the answer
could say it should not be.**

| | Count | What it means |
|---|---|---|
| **Could reveal a live risk** | **5** | Fynla acts on these today with no answer |
| **Could only permit relaxation** | **5** | Conservative action already taken; answer can only loosen |
| **Parked by a CSJ decision** | **3** | Not live until CSJ unparks; do not pay a lawyer yet |
| **CSJ decision sits in front of the legal question** | **2** | Answering changes little because the mitigation was adopted unconditionally |
| **On track to be mooted by work in flight** | **1** | W-0155 |
| **Blocks a build decision now** | **1** | Narrow, cheap, highest value per pound |

**Nothing is fully moot.** I looked for that bucket because you asked for it and **I am not
going to manufacture one** — one question is on track to be mooted and none has got there.

---

## 2. Could reveal a live risk — ask these first

**Fynla does all five of these today, in production, without the answer.**

| # | Question | Why it is live | Changed today? |
|---|---|---|---|
| **2** | Does the guidance posture stay outside the regulated-advice perimeter, given Fyn reasons over the user's own figures? | **The broadest question in the backlog.** Fyn reasons over every user's figures on every surface, now. Guidance mode is the mitigation; whether it suffices is the question | no |
| **5** | Special-category by inference — retirement projection adjusts for smoking status. Does the current consent model hold under Article 9? | Runs today and **is not mitigated by anything**. `DecumulationPlanner`, `LifeCoverCalculator`. Data protection is only **partially mapped** | no — F-0007 was cookies, not this |
| **1** | Are Fynla's marketing articles financial promotions under s21 FSMA? | The content pipeline publishes | ⚠️ **GROWN.** See below |
| **10** | Does generating a Lasting Power of Attorney, and reporting checks over it, carry exposure beyond the reserved-activity question W-0100 answered? | The tool is live and its output is about to get **more** assertive | ⚠️ **GROWN.** See below |
| **(c)** | What disclosure attaches to affiliate-incentivised third-party acquisition content for an unauthorised firm? | `AWIN_ENABLED` is **true on production** | new today, ungated |

### Q1 has grown, and the phrasing no longer covers the surface

Q1 asks about **articles Fynla writes**. Today's regime map records a second category:
**affiliate publisher content Fynla does not write but pays for the results of**, with the
advertising row marked **Unmapped** and §3 explicitly not reaching it. W-0050's **(c)** is
that gap stated as a question.

**So Q1 and (c) should be asked together, and Q1 should be re-scoped when they are.** As
phrased it invites an answer about a surface that is no longer the whole surface.

### Q10 has grown — today, and by my own rulings

W-0100 established *not reserved ≠ permitted* and stopped. Since then the tool has been ruled
to **state what the Mental Capacity Act provides** (W-0108) and to **report checks against
statutory requirements** with paragraph citations (W-0102, W-0104, W-0106). **That is more
than it did when Q10 was written**, and the increment is in the direction the question is
about. Recorded as a change since writing, not as a reason to stop — the wording rulings are
more conservative than what they replaced.

---

## 3. Could only permit relaxation — ask these last, or never

**In every one of these, the conservative thing is already done or being done. A favourable
answer would let Fynla claim more; an unfavourable one changes nothing.**

| # | Question | What is already done |
|---|---|---|
| **7** | May the donor give their own certificate? | W-0103's wording describes the overlap as a contradiction and **asserts no prohibition**. Safe whichever way it resolves |
| **8** | May the donor be a donee of their own instrument? | Same |
| **11 = (b)** | Does Schedule A1's statistical-purposes exception reach Fynla's analytics? | Fynla **asks for consent**, which is more conservative than relying on the exception. Answering could only permit dropping it |
| **12** | Does the affiliate cookie sit under a different Schedule A1 paragraph from analytics? | The two-type record split is being built **regardless** |
| **4** | Does Consumer Duty apply to an unauthorised firm operating this way? | §5 records the four outcomes **adopted as internal quality standards regardless**. See §4 |

**Q7 and Q8 deserve one note.** They read as urgent because they are about a legal
instrument. They are not, **and that is a direct consequence of the wording ruling** — because
Fynla describes the contradiction rather than asserting a prohibition, it is correct either
way. **Had the ruling gone the other way and asserted a prohibition, these would be in §2.**

---

## 4. Where a CSJ decision sits in front of the legal question

**These are not waiting on a lawyer. They are waiting on CSJ, or already answered by CSJ.**

| # | Question | The CSJ decision in front of it |
|---|---|---|
| **3** | Does PS25/22 apply to what Fynla already does, or only to what it might do next? | §5: **targeted support not currently sought**, posture stays fail-closed, *"revisit when §6 is answered"*. **The live question is a CSJ one — does that deferral still hold?** It does not need a lawyer until the posture changes |
| **4** | Consumer Duty applicability | §5 adopts the four outcomes **unconditionally**. **Answering changes little operationally**, because the mitigation was taken without the answer. Low value for money |
| **6** | What must be disclosed when a material asset class is not modelled? | **Partly answered by CSJ's own ratification.** §4 already requires disclosure at the point the figure is shown. The residue is legal; the policy call is made — and §4 now has a **worked precedent outside a currency figure** (the Lasting Power of Attorney "what we did not check" block) |
| **13** | Must withdrawal of cookie consent be reachable in-product, and how easily? | **On track to be mooted by W-0155.** If the control ships, the conservative action is taken and nobody needs the answer |

**Parked by CSJ, and should not be paid for until unparked:**

| # | Question | Status |
|---|---|---|
| **(a)** | Is consent collected through the registration wall since 2026-04-07 valid, and what follows for the data? | **The first limb is the parked cookie-wall question.** The second limb is consequential on it. **Not reopened here** — CSJ parked it this morning and it stays parked |

**That is the honest read and it moves (a) out of the urgent set**, which is the right outcome
and respects the park rather than working around it.

---

## 5. The one that blocks a build decision — best value per pound

| # | Question | What it blocks |
|---|---|---|
| **9** | What is a "family member" for S.I. 2007/1253 reg 8(3)(a) and (d)? | **W-0151.** reg 8(3)(a) disqualifies a family member of the donor; Fynla holds `relationship_to_donor` on `lpa_attorneys` plus spouse links and `FamilyMember` rows. **The definition decides whether a check is buildable at all, or whether this is disclosure-only.** reg 8(4) defines *care home*, *registered health care professional* and *registered social worker* — **and not this** |

**Narrow, self-contained, and answerable from one instrument.** If a lawyer is engaged for one
hour, this is the question that converts directly into shipped code. Everything in §2 is more
important and none of it is cheaper.

---

## 6. The remaining one — data protection, and a fact that sharpens it

| # | Question | Product fact established today |
|---|---|---|
| **14** | Does the anonymous `subject_token` consent row become personal data, and from when? | **Nothing erases or expires an unclaimed row** |

**I checked, and this is worth your attention independently of the legal question.**
`UserConsent::claimAnonymousConsents()` nulls the token when a visitor registers
(`app/Models/UserConsent.php:157`). **A visitor who never registers keeps theirs
indefinitely.** There is no scheduled purge, no expiry, and no retention rule reaching these
rows:

- **`fyn:user:erase` cannot reach them** — it operates on a user, and these rows have none.
- **The six-year episodic purge does not reach them** — different store.

So the design that was rightly praised for preserving evidence has **no other end of its
lifecycle**. `05-perimeter.md` §2 records retention and erasure as the *covered* half of the
data-protection regime; **these rows sit outside both halves.**

**Not a criticism of F-0007** — it is the consequence of a good decision (record consent
before an account exists) whose retention question nobody has had to ask before, because
nothing in this codebase previously held a record with no user attached. **Q14 is the right
question; it now has a fact attached, and there is an operational half CSJ can act on without
waiting for a lawyer.**

---

## 7. Recommendation — the backlog's problem is not that it is unordered

You said fourteen unordered questions is a list nobody reads. **It is worse than unordered.
It is unconsolidated, and the duplication has already started.**

**Three homes, and the trunk holds a third of them:**

| Where | Count | Visible to an agent reading the trunk? |
|---|---|---|
| `05-perimeter.md` §6 | 6 | yes |
| W-0050 ruling, Needs section, gate not actioned | 3 | **no** |
| LPA rulings report §7 | 4 | **no** |
| F-0007 review §6 | 4 | **no** |

**I duplicated one within a day of the others being written.** The cheap symptom is a
duplicate. **The expensive one is a question everybody assumes was asked**, because it appears
in a report someone remembers reading.

**Recommendation: §6 becomes a pointer to one consolidated list, the way §7.2 now points at
`registry/sources.md`.** Same fix, same reason, and G-0003's argument applies unchanged — an
artefact-local copy dies with the artefact. **Where it lives is not mine to settle**; `ops/`
is the natural home since this is live workstream state rather than doctrine.

**And it should not be a flat list.** The buckets above are the readable structure: *what is
live and unanswered* · *what can only loosen* · *what waits on CSJ* · *what a build is about
to moot*. **The trunk's current framing — "these are not agent-answerable, they stay open
until a lawyer answers them" — is no longer true of all of them**, and lumping them together
is what makes it unreadable.

**I have not amended the trunk.** §6 is CSJ-gated and this is a recommendation.

**One thing worth putting to CSJ with it:** §6 went from six to seventeen in a day, none was
answered, and the status line still reads *"external review planned, not currently funded."*
**That is now the binding constraint.** The brief is also far better than it was — five
questions that matter, ranked, each with the product fact attached, and one that converts
straight into code. **§7.4's whole claim is that this makes the review cheaper and
better-aimed. It is now testable.**

---

## 8. Your G-0005 generalisation — I agree, with one refinement

You wrote that a scope defined by **where a file lives** drifts silently when the architecture
moves, because the directory still exists and still has files in it; a scope defined by **what
a file does** does not. **Agreed, and the Class B analogy is exact** — in both cases the thing
you would naturally check is not the thing that moved, so checking it returns a clean result.

**The refinement:** a substance-defined scope is only as durable as **the definition of the
substance**. Mine now depends on `CLAUDE.md`'s canonical Fyn contract staying accurate — if
that contract drifts, my scope drifts with it, silently, in the same way. **That is a better
dependency than a directory path** and it is not free. Worth naming in G-0005 so nobody reads
"defined by what it does" as self-maintaining.

---

## Done

- Triaged all §6-class questions into the buckets asked for, **without answering any**.
- **Corrected my own count from fourteen to seventeen** and found the three I had missed.
- **Found that one of my own questions duplicates one of them**, and established the cause.
- Introduced the ranking axis the trunk lacks — **live risk vs relaxation-only** — and applied
  it.
- Established a product fact that sharpens Q14 and has an operational half CSJ can act on now.
- Identified the single question that converts directly into shipped code (**Q9**).
- Answered your G-0005 generalisation with one refinement.

## Not done, and why

- **No question answered.** Not one, including the ones where I could see the shape of an
  answer. §7.3.
- **The parked cookie-wall question is not reopened.** (a) is characterised as parked and
  moved *out* of the urgent set because of it.
- **No trunk amendment.** §6 is CSJ-gated; §7 is a recommendation.
- **No consolidated list built.** That is the recommendation, not the task, and where it lives
  is not mine to settle. **Say the word and I will build it** — I hold the seventeen.
- **No board items raised.** I hold no block. The Q14 retention finding may warrant one.
- **W-0050's three questions were not installed into the trunk by me.** They are gated to CSJ
  and that gate is still open.

## Assumptions

- **That the seventeen are all of them.** I found three I had missed by grepping reports;
  **there may be more in artefacts I did not read** — which is the §7 argument, not a caveat
  against it.
- That `05-perimeter.md` §5's positions (PS25/22 not sought, Consumer Duty outcomes adopted)
  still hold. Read from the trunk today, not confirmed with CSJ.

## Needs

- **Decision (team-lead / CSJ):** consolidate §6 into one list, per §7.
- **Answer (team-lead):** whether the Q14 retention finding becomes a board item. I hold no
  block.
- **Gate (CSJ), already open and unactioned:** W-0050's three §6 questions into the trunk.
  **Until that lands, the next agent numbering §6 questions will duplicate them again** — as I
  did.

## Noticed — outside my remit, routed

- **team-lead:** W-0050's ruling also records a **Rule 20 finding on consent** — *"three
  surfaces, three consent mechanisms"*, and it notes **native iOS already has a full
  server-backed versioned consent system** (`ios-native/.../ConsentModels.swift`). F-0007
  converged web and the funnel. **Whether native was converged with them, or is now a fourth
  mechanism, I have not checked and it is not in F-0007's notes.** Rule 19/20 shaped.
- **archivist:** §6's framing sentence — *"These are not agent-answerable. They stay open until
  a lawyer answers them"* — is no longer true of Q3, Q4, Q6 or Q13, each of which has a CSJ
  decision or a build in front of it. **No wording proposed.**
