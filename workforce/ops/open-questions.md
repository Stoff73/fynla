# Open questions — the consolidated §6 list

**Created:** 2026-08-21 by `compliance-lead`, on team-lead's authorisation.
**Maintained by:** `compliance-lead` (`core/constitution/05-perimeter.md` §7.4 — *"every flag it
cannot resolve becomes a precisely-worded, evidenced question for §6"*).
**Supersedes:** the practice of listing questions in whatever artefact raised them.

> **Nothing here is an answer, and nothing here is an approval.** `05-perimeter.md` §7.3
> allows the compliance function two outcomes and neither is a determination of what the law
> requires. **Every question below is open.** Characterising one — who can answer it, what it
> blocks, whether the conservative action has already been taken — is not answering it.
>
> **A question's absence from this file is not clearance.** It means nobody has written it
> down.

---

## 1. Why this file exists

**`05-perimeter.md` §6 held six questions. There were seventeen.** The rest lived in a
ruling's unactioned Needs section, an LPA rulings report, and a consent review — invisible to
anyone reading the trunk.

**Within one day that produced a duplicate.** `compliance-lead` wrote a question numbered 11
about the Schedule A1 statistical-purposes exception, having numbered from the trunk's six,
and it was already open as W-0050's question (b). **The agent did not miss it through
carelessness — it could not see it.**

**The duplicate is the cheap symptom. The expensive one is a question everybody assumes was
asked**, because it appears in a report someone remembers reading.

**This is the same failure as `ops/gaps/G-0003`** — the dated source register did not exist, so
every artefact built its own inline copy and nothing accumulated. Same disease: one list,
maintained, declared canonical by the gated file.

**The fix is not identical to the register's, and an earlier revision of this paragraph said it
was.** `05-perimeter.md` §7.2 points at `registry/sources.md` and restates nothing. **§6 points
at this file *and* restates `Q-01`–`Q-06`**, because seven citations inside `05-perimeter.md`
depend on that numbering resolving in place. **That is a better outcome than the pointer-only
form proposed, not a compromise** — see §6 of this file.

## 2. Adding a question — the rules that stop this recurring

1. **IDs are permanent and never reused.** `Q-01` … `Q-16` are allocated. **The next question
   is `Q-17`**, taken from the bottom of this file, never inferred from a count of anything.
2. **Never number from `05-perimeter.md` §6.** That is exactly how `Q-09` was duplicated. §6 is
   a pointer to this file, not a source of numbering.

   > **SUPERSEDED as to the second sentence — see the `2026-08-21 compliance-lead` correction at
   > the foot of this file ("it described the world I had just proposed").**
   > **§6 is not a pointer and was not one when this was written.** It holds a **non-canonical
   > restatement of `Q-01`–`Q-06`**, resolving in place, with **ties going to this file**, and it
   > carries the allocation rule above in full. **The pointer-only form is proposed and NOT
   > applied**, for three stated reasons.
   > **The rule in the first sentence is unaffected and is now in the trunk itself.**
   > Left as written because `05-perimeter.md` §6 cites this paragraph by name.
3. **A question raised in a report gets its ID here first**, then the report cites the ID.
   A report is where a question is *found*; this is where it *lives*.
4. **Attach the product fact that raised it.** §7.4's whole claim is that a lawyer answering
   specific questions with the content attached bills a fraction of one asked to review a
   fintech. A question with no product fact is not yet worth asking.
5. **Say what it blocks, or say that it blocks nothing.** "Blocks nothing" is a useful answer
   and it is what moves a question to §6 of this file.
6. **Merging is allowed; renumbering is not.** `Q-09` records that it absorbed two separately
   raised questions. The absorbed wording stays visible.

## 3. Legacy numbering — so existing cross-references still resolve

**Reports written before this file cite the old numbers. Do not edit them; use this table.**

| Cited as | Where | Now |
|---|---|---|
| `05-perimeter.md` §6 items 1–6 | trunk | `Q-01` … `Q-06` |
| "three new §6 questions (a)(b)(c)" | `2026-08-21-W-0050-consent-validity-ruling.md` | `Q-07`, **`Q-09`**, `Q-08` |
| "§6 questions 7–10" | `2026-08-21-lpa-claims-rulings.md` §7 | `Q-10` … `Q-13` |
| "§6 questions 11–14" | `2026-08-21-F-0007-consent-privacy-review.md` §6 | **`Q-09`**, `Q-14`, `Q-15`, `Q-16` |

**Note the collision:** W-0050's **(b)** and F-0007's **11** are one question, now **`Q-09`**.
Both citations resolve to it.

---

## 4. The ranking axis

**Fynla's posture is fail-closed.** So for a large part of this list the conservative action
has already been taken, and an answer could only permit relaxing it. **Those questions cannot
surface an exposure.**

> **The question that ranks these is: can answering reveal a risk Fynla is running right now,
> or can it only permit relaxing something already done conservatively?**

| Section | Count | Meaning |
|---|---|---|
| **A — could reveal a live risk** | **4** | Fynla acts on these today, with no answer. **Was 5** — `Q-13` moved to D on a production count |
| **B — blocks a build decision now** | **0** | `Q-12` was here for one day; see its entry |
| **C — could only permit relaxation** | 5 | Conservative action already taken |
| **D — a CSJ decision sits in front** | **4** | Not waiting on a lawyer |
| **E — parked by CSJ** | 1 | Do not pay for this until unparked |
| **F — on track to be mooted by work in flight** | 2 | May never need answering |

**Nothing is fully moot.** That bucket was looked for and is empty; it has not been populated
to look tidy.

---

## Section A — could reveal a live risk. Ask these first.

### `Q-01` — Are Fynla's marketing articles financial promotions under s21 FSMA? If some are, what distinguishes them?
**Regime:** financial services (**mapped**) · **Answerable by:** lawyer · **Blocks:** publication volume
**Product fact:** the content pipeline publishes (`InsightArticle`, `PipelineArticle`); §3 binds
all outbound communication Fynla writes.
⚠️ **This question has grown and its phrasing no longer covers the surface.** It was written
about copy Fynla **writes**. `AWIN_ENABLED` is true on production, so there is now copy Fynla
**pays for and does not write** — which is `Q-08`. **Ask them together and re-scope this one
when you do.**
*Source: trunk §6.1.*

### `Q-02` — Does the guidance posture stay outside the regulated-advice perimeter, given Fyn reasons over the user's own figures?
**Regime:** financial services (**mapped**) · **Answerable by:** lawyer · **Blocks:** nothing today; everything if answered badly
**Product fact:** Fyn reasons over every user's own figures, on web, `/m` and native, now.
Guidance mode is fail-closed and is the mitigation; whether it suffices is the question.
**The broadest question on this list.**
*Source: trunk §6.2.*

### `Q-03` — Special-category by inference: a retirement projection adjusting for smoking status infers health data. Does the current consent model hold under Article 9?
**Regime:** data protection (**partially mapped**) · **Answerable by:** lawyer · **Blocks:** nothing today
**Product fact:** `app/Services/Retirement/DecumulationPlanner.php` and
`app/Services/Estate/LifeCoverCalculator.php` read smoking status. **This runs today and is
mitigated by nothing.** F-0007 rebuilt cookie consent and did not touch this.
**The sharpest unmitigated item on the list.**
*Source: trunk §6.5 (`audit-synthesis.md:133`).*

### `Q-08` — What disclosure attaches to affiliate-incentivised third-party acquisition content for an unauthorised firm?
**Regime:** advertising (**unmapped**) · **Answerable by:** lawyer · **Blocks:** nothing today
**Product fact:** `config/awin.php`, **`AWIN_ENABLED` true on production**. §3 binds copy Fynla
writes; it does not reach copy Fynla incentivises but does not author, and nothing else does
either (regime map, advertising row).
**Ask with `Q-01`.**
*Source: W-0050 ruling, question (c). **Never installed into the trunk** — CSJ gate open.*

---

## Section B — blocks a build decision now.

**Empty as at 2026-08-21.** `Q-12` was here and was moved to Section C — see below. **Nothing
on this list currently blocks a build.**

---

## Section C — could only permit relaxation. Ask last, or never.

**In each of these the conservative thing is already done or being done. A favourable answer
would let Fynla claim more; an unfavourable one changes nothing.**

### `Q-09` — Does Schedule A1's statistical-purposes exception reach Fynla's analytics, including where it shares with a third-party processor?
**Regime:** PECR (**unmapped**) · **Answerable by:** lawyer · **Blocks:** nothing
**Already done:** Fynla **asks for consent**, which is more conservative than relying on the
exception. **Answering could only permit dropping it.**
**Product fact:** Sch A1 **para 5** — *sole purpose* of collecting information about how the
service is used to enable improvements, with clear information and **a simple means to object
at no cost** (objection, not consent). The analytics is Google Analytics, so data reaches a
third party. The W-0050 ruling already lined the elements up against the facts in a table and
found two of three difficult — **that work is done and should go to the lawyer with it.**
⚠️ **Newly askable.** The exception did not exist before **5 February 2026**.
**Merged.** Absorbs W-0050's question (b) — *"can Sch A1 para 4 reach any analytics deployment
that shares with a third-party processor"* — and F-0007's question 11. **Note (b)'s paragraph
number was wrong; it is para 5.** See register standing warning W5.
*Sources: W-0050 ruling (b) + F-0007 review §6.11. Register rows A11, A12.*

### `Q-14` — Does the affiliate click cookie sit under a different Schedule A1 paragraph from the analytics cookie?
**Regime:** PECR (**unmapped**) · **Answerable by:** lawyer · **Blocks:** nothing
**Already done:** the two-type consent record (`cookies_analytics` / `cookies_affiliate`) is
being built **regardless of the answer**.
**Product fact:** one accept/decline currently covers both, and one `user_consents` row records
the result, so the two cannot be told apart in the evidence.
*Source: F-0007 review §6.12.*

### `Q-10` — May the donor of a Lasting Power of Attorney give their own certificate?
**Regime:** legal services (**unmapped**) · **Answerable by:** lawyer · **Blocks:** nothing
**Already done:** W-0103's ruled wording **describes the overlap as a contradiction and asserts
no prohibition**, so it is correct whichever way this resolves.
**Product fact:** S.I. 2007/1253 reg 8(3) lists eight disqualifications and **the donor is not
among them**; reg 8(1) frames the provider as *"a person chosen by the donor"*; Sch 1 para
2(1)(e) requires the certificate to be about the donor's own understanding and freedom from
pressure. `certificate_provider_name` is free text and nothing compares it to
`donor_full_name`.
**Reads urgent and is not — and that is a direct consequence of the wording ruling. Had the
ruling asserted a prohibition, this would be in Section A.**
*Source: LPA rulings §7, cited there as question 7.*

### `Q-12` — What does "family member" mean for S.I. 2007/1253 reg 8(3)(a) and (d)?
**Regime:** legal services (**unmapped**) · **Answerable by:** lawyer · **Blocks: nothing**
**Already done:** W-0151's reg 8(3)(a) limb is ruled **disclosure-only**, and that ruling holds
whichever way this is answered. **A favourable answer would only permit adding a check later.**
⚠️ **Moved from Section B on 2026-08-21, the day it was written.** It was ranked *best value
per pound* because it appeared to block W-0151. **It does not, and the reason is the finding
below.**
**Product fact — a complete negative search, done rather than assumed.** A definition was
looked for in all three places one would live and **is in none of them**: **reg 8(4)** (the
local interpretation for the very regulation using the term) defines *care home*, *registered
health care professional* and *registered social worker*; **reg 2** (the instrument's general
interpretation) defines eight terms, none of them this; **MCA 2005 s.64** (the parent Act)
defines 24 terms and **neither *family member*, *family* nor *relative*** is among them.
**So the question narrows** from *"what is a family member for reg 8(3)"* to *"what does an
undefined term mean on ordinary construction, here"* — and **whoever answers it need not go
looking for a statutory definition.**
**And it stopped blocking, for a reason within competence:** the regulation does not draw the
boundary, so **if Fynla builds a check, Fynla draws it** — authoring doctrine at the point of
use, which §1.3 rule 1 forbids on an unmapped regime.
*Source: LPA rulings §7, cited there as question 9. Register row A10.*

### `Q-11` — May the donor be appointed a donee of their own Lasting Power of Attorney?
**Regime:** legal services (**unmapped**) · **Answerable by:** lawyer · **Blocks:** nothing
**Already done:** same as `Q-10` — described as a contradiction, no prohibition asserted.
**Product fact:** MCA 2005 s.9(1) and s.10(1) frame the relationship and state what a donee must
be; **neither excludes the donor in terms.** `donor_full_name` is never compared to any
`lpa_attorneys.full_name`, and the rendered document reads *"I, [name] … appoint the
attorney(s) named below: Attorney 1: [same name]"*.
*Source: LPA rulings §7, cited there as question 8.*

---

## Section D — a CSJ decision sits in front of the legal question

**These are not waiting on a lawyer.**

### `Q-04` — Does PS25/22 apply to what Fynla already does, or only to what it might do next?
**Answerable by:** **CSJ first, then a lawyer if the posture changes** · **Blocks:** nothing
§5 records **targeted support not currently sought**, posture stays fail-closed, *"revisit when
§6 is answered"*. **The live question is a CSJ one — does that deferral still hold?** It needs
no lawyer until the posture changes.
*Source: trunk §6.3.*

### `Q-05` — Does Consumer Duty apply to an unauthorised firm operating this way?
**Answerable by:** lawyer · **Blocks:** nothing · **Value: low**
§5 adopts the four outcomes **as internal quality standards regardless**. **The mitigation was
taken without the answer, so answering changes little operationally.**
*Source: trunk §6.4.*

### `Q-13` — Does generating a Lasting Power of Attorney, and reporting checks over it, carry exposure beyond the reserved-activity question W-0100 answered?
**Regime:** legal services (**unmapped**) · **Answerable by:** **CSJ first, then a lawyer only if the feature stays** · **Blocks:** nothing
⚠️ **MOVED FROM SECTION A on 2026-08-21, the day it was written**, on a production count. It was
ranked among the five that could reveal a live risk. **A CSJ product decision now sits in front
of it.**

**The count, stated as the basis rather than the conclusion.** Run read-only against
`fynla.org` by team-lead, **2026-08-21**:

| | |
|---|---|
| `lasting_powers_of_attorney` rows total | **6** |
| rows belonging to non-preview users | **0** |
| distinct real users holding one | **0** |

**All six belong to preview personas.** `NULL` was treated as real rather than preview, so the
method errs toward **over**-reporting real users. It still returned zero.

**What it settles:** the cheapest answer may be not to generate the instrument at all —
**removing a feature nobody uses costs less than a legal opinion about it**, and W-0110 already
records the feature is web-only with no `/m` or native surface. **That is a product decision,
not a legal question.**

⚠️ **This is a fact with an expiry, and the argument reverses the day a real user creates one.**
The count is not durable and nothing about it should be treated as a standing property of the
product.

**Three limits on how far it may be read, all of them team-lead's and all of them right:**
1. **It bounds the instrument, not the impression.** It counts rows, not people who saw the
   compliance badge. A user could have opened the estate section, seen the feature, and created
   nothing.
2. **It does not reach wills.** Different table, different feature. **The will renderer drew
   testator and witness signatures on production the whole time (W-0101), and this count says
   nothing about that** — which is the sharper defect of the two. Quantifying it is a separate
   count.
3. **It reduces the W-0100 defect by nothing.** The green "Compliant" badge was equally wrong on
   every day nobody looked. **This is timing, not a control** — the same sentence that closed
   W-0019's exposure limb. What it does mean: no remediation, no disclosure, no affected cohort,
   and `fix-batch-G`'s fix lands before anyone is reached rather than after.

**Product fact:** W-0100 established LSA 2007 Sch 2 para 5(3)(c) excludes powers of attorney
from reserved instrument activities, and stopped — *not reserved ≠ permitted*.
⚠️ **Grown since written.** The tool is now ruled to **state what the Mental Capacity Act
provides** (W-0108) and to **report checks against statutory requirements with paragraph
citations** (W-0102, W-0104, W-0106) — more than it did when this was written, and the increment
is in the direction the question is about. **If the feature stays, the question is bought; the
brief is the best-prepared on the list.**
*Source: LPA rulings §7, cited there as question 10.*

### `Q-06` — What must be disclosed when a material asset class is not modelled?
**Answerable by:** lawyer, **for the residue only** · **Blocks:** nothing
**Partly answered by CSJ's own ratification.** §4 already requires disclosure **at the point
the affected figure is shown** — not in a footer. The policy call is made; only whether it is
legally sufficient is open.
**Product fact:** crypto and digital assets are not modelled, so a holder receives a silently
incomplete inheritance-tax figure. §4 now also has a **worked precedent outside a currency
figure** — the Lasting Power of Attorney "what we did not check" block (W-0100).
*Source: trunk §6.6.*

---

## Section E — parked by CSJ. Do not pay for this until unparked.

### `Q-07` — Is the consent collected through the registration wall since 2026-04-07 valid, and what follows for the data?
**Regime:** data protection / PECR · **Status: PARKED by CSJ, 2026-08-21**
**The first limb is the parked cookie-wall question** — CSJ ruled that clicking Accept gives
consent and registration proceeds, and that the Article 7(4) "freely given" question waits
until the functional board is clear. **The second limb is consequential on the first.**
**Not reopened here.** Recorded so it is not re-raised as new, and so nobody buys an answer to
a question CSJ has deferred.
*Source: W-0050 ruling, question (a). **Never installed into the trunk** — CSJ gate open.*

---

## Section F — on track to be mooted by work in flight

### `Q-15` — Must withdrawal of cookie consent be reachable in-product, and how easily?
**Answerable by:** lawyer · **On track to be mooted by W-0155**
**If the withdrawal control ships, the conservative action is taken and nobody needs the
answer.**
**Product fact:** after accepting there is no control on web, `/m` or the funnel; `cookies` is
excluded from `PUT /api/auth/gdpr/consents` **by design**; `getConsentHistory` **displays** the
decision without offering any way to change it. **Visible and irreversible.**
*Source: F-0007 review §6.13.*

### `Q-16` — Does the anonymous `subject_token` consent row become personal data, and from when?
**Regime:** data protection (**partially mapped**) · **Operational half on track via W-0156**
**Product fact, established by reading rather than inferred:** `claimAnonymousConsents()` nulls
the token at registration (`app/Models/UserConsent.php:157`). **A visitor who never registers
keeps theirs indefinitely.** There is no purge, no expiry, and **neither retention path reaches
these rows** — `fyn:user:erase` operates on a user and these have none; the six-year episodic
purge is a different store. `05-perimeter.md` §2 records retention and erasure as the *covered*
half of data protection; **these rows sit outside both halves.**
**Not a criticism of F-0007** — the consequence of a good decision (record consent before an
account exists) whose retention question nobody had needed to ask, because nothing here
previously held a record with no user attached.
*Source: F-0007 review §6.14.*

---

## 5. Status

**External review planned, not currently funded** (trunk §6). **Until then §7 applies** — the
compliance function screens within competence and never approves.

**This list went from six to sixteen in a single day, and none was answered.** That makes the
funding decision the binding constraint rather than the question count. **The brief is also
much better than it was:** five questions that could actually find something, ranked, each with
its product fact attached, and one (`Q-12`) that converts straight into shipped code.

**§7.4's claim — that this makes the eventual review cheaper and better-aimed — is now
testable.**

## 6. Open gates that affect this file

- **CSJ:** install `Q-07`, `Q-08` and `Q-09` into the record properly. They were raised in the
  W-0050 ruling's Needs section and **the gate has never been actioned. Until it is, an agent
  reading only the trunk will duplicate them — which is exactly what happened.**
- **CSJ:** whether `05-perimeter.md` §6 becomes a **pointer only**, with the six removed.
  **Proposed by `compliance-lead`, relayed by team-lead, and correctly NOT applied by the
  archivist. Three reasons, any one sufficient:**
  1. **It deletes ratified content.** The `registry/sources.md` pointer replaced a sentence
     that had **become false**. This removes a list **CSJ ratified 2026-08-13** — a proposal
     that must state what breaks, not a factual correction.
  2. **Seven citations inside `05-perimeter.md` depend on §6's numbering** (§6.1, §6.4, §6.5
     ×3, §6.1–6.4 ×2), plus `registry/meetings.md` and `registry/sources.md`. **§3's legacy
     table resolves them — but at the cost of a hop, for citations that resolve in place
     today.** Applying it literally would have created the orphan-and-indirection damage the
     archivist had documented an hour earlier.
  3. **Where the open regulatory questions live is a standing decision, not a filing
     tidy-up.** `05-perimeter` is supreme for anything regulatory; `ops/` carries no
     precedence rank. **Moving them changes their authority, not just their address** — which
     is why §6 grants ties to this file explicitly rather than by implication.

  **The analysis is staged in `05-perimeter.md` §6 for CSJ.** What landed instead is better
  than what was proposed: the six resolve in place, labelled non-canonical with ties to this
  file, the factual defect is stated, and the allocation rule is in the trunk in full.
- **CSJ:** extend §7.3 so the no-approval rule binds **the product**, not only the agent.
  Unrelated to this file's content, related to why several of its questions exist.

---

## Correction — 2026-08-21, compliance-lead

**Found by the archivist, relayed by team-lead.** Marker placed at §2 rule 2, per the marker
rule in `ops/FORMATS.md`.

### What was wrong

§2 rule 2 asserted *"§6 is a pointer to this file, not a source of numbering."*
**§6 is not a pointer, and was not one when that sentence was written.**

**It described the world I had just proposed rather than the world that existed.** The proposal
had been made and relayed; it had not been ruled on, and it was then correctly refused.

### Why the sentence stays rather than being deleted

`ops/FORMATS.md` prefers removing stale text to marking it, *"when the original wording is
[not] itself evidence"*. **Here it is evidence twice over.**

**`05-perimeter.md` §6 cites this paragraph by name** — *"`ops/open-questions.md` §2.2 already
describes §6 as a pointer; until CSJ rules, it is not one, and this paragraph is the
difference."* **Deleting it would dangle the trunk's reference.** And the sentence is the
worked example of the failure mode below.

### What is actually true

`05-perimeter.md` §6 holds a **non-canonical restatement of `Q-01`–`Q-06`**, resolving in
place, declaring this file **canonical** with **ties going to this file**, stating the factual
defect (sixteen exist; ten never reached the trunk), and carrying the **allocation rule in
full** — including that the next is `Q-17` and that numbering from §6 is what produced the
duplicate. **The pointer-only form is proposed and not applied**; the three reasons are at §6
of this file and the analysis is staged in the trunk for CSJ.

**The rule itself — never number from §6 — was correct and is now in the trunk.** Only the
description of the trunk was false.

### The failure mode, because it is worth more than the sentence

**I wrote a file describing a state I had proposed, as though the proposal had landed.** That
is the same shape as an acceptance criterion written against a fix that has not shipped: it
reads as a statement of fact, it is invisible unless someone checks the other side, and it
would have been quietly relied on.

**It is also the shape I spent the day finding in other people's work** — a claim that
describes something as settled when it is not. Worth recording that the test does not stop
applying to the person applying it, and that **the check that caught it was somebody reading
the other artefact**, not the author re-reading their own.

**The general rule this earns:** a document may state what another document *says*, or state
that a change to it has been *proposed*. **It may never state what another document will say
once a pending proposal is accepted** — and if it needs to, the sentence belongs in the
proposal, not the document.
