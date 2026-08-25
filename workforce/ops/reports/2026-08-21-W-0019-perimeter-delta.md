# W-0019 / W-0024 — perimeter delta report

**Agent:** compliance-lead · **Date:** 2026-08-21 · **Items:** W-0019, W-0024
**Trunk under review:** `workforce/core/constitution/05-perimeter.md` (clean at HEAD —
`git status` reports no working-tree modification, so the text analysed is the ratified text)
**Trigger:** CSJ direction 2026-08-21 — *"If compliance raised issues these need to be
checked, and a report shown of the delta and why."*

> **This report is not an approval.** Perimeter §7.3 permits two outcomes: *no issues found
> within competence*, or *flagged, with the reason and a dated source*. Every recommendation
> below is a recommendation. **Trunk amendments are CSJ's**, and nothing here should be read
> as a legal determination — where I reach the edge of §7.3 I say so and stop.

---

## 0. Reading note — the four questions were never written down

The dispatch asked me to read the four §6 questions "as you recorded them on W-0019".
**They are not there, and they are not anywhere.**

`workforce/branches/fixes/F-0003-batch-b-estate-wills.md:373` states they were "drafted …
(recorded on W-0019)", and `:546` repeats it. The board item
`workforce/ops/board/W-0019-married-users-must-only-get-mirror-wills.md` contains no such
section — its only forward-looking list is "Open questions for CSJ" (`:71-78`), which holds
two product questions, one of them already answered. `grep` across `workforce/` and
`tests/Persona/` for `financial promotion|Consumer Duty|PERG|s21|fair value|Legal Services
Act|reserved legal` returns four hits, all of them narrative prose in the two files above.

So the questions below are **reconstructed** from what F-0003 §3a records that I raised
(items 1, 4, 5 and 6 of the `compliance-lead` verdict block, `:325-374`). I believe the
reconstruction is faithful to the topics; I cannot claim it is faithful to the original
wording, because the original wording no longer exists. **Recorded as finding 8 in §5.**

---

## Q1 — Which regulatory regime governs a tool that generates a will?

### The question
Fynla's perimeter doctrine is written end-to-end against the **financial services**
perimeter. The will builder generates a **legal instrument**. Does operating it engage the
Legal Services Act 2007 regime; if not, what obligations attach to an unregulated
will-writing provider; and is *"this tool doesn't provide legal advice"* an adequate and
accurate disclaimer for a tool that drafts the instrument rather than commenting on it?

### What the trunk says today
Nothing. §1 states the status in FCA terms only ("not FCA-authorised … guidance, not
regulated advice"). §2's seven rules are financial-advice and financial-promotion controls.
§3 extends them to all outbound communication — but they remain *those* rules. §7.2's
mandated source list is **FCA Handbook, PS25/22, FSMA s21, ICO, HMRC**: no legal-services
source of any kind.

**What it does cover:** anything the will builder *says about money* — an IHT figure, a
charitable-rate instruction. §2 Rule 7 caught exactly that during W-0019.
**What it does not cover:** the will builder as a generator of legal documents. No clause in
the file reaches it.

### What the app now does — verified
- `app/Services/Estate/WillTypePolicy.php` is the single home for the decision and the copy
  (`isMarried()` `:76`, `canBuildMirror()` `:94`, `allowedWillTypes()` `:105`,
  `refusalFor()` `:129`).
- The approved copy says *"This tool doesn't provide legal advice"* twice
  (`REFUSAL_MARRIED` ¶3, `REFUSAL_NO_MIRROR_PARTNER` ¶3) and *"qualified solicitor"* in both.
  No FCA-authorisation wording anywhere in the strings — as I ruled in F-0003 §3a item 4.
- The app generates full instrument text, not commentary. W-0024's evidence quotes a
  produced document reading *"LAST WILL AND TESTAMENT of Sarah Jones … I APPOINT Sarah
  Jones … to be the Executor and Trustee of this my Will."*

### The delta
**The trunk is silent on the app's most legally-consequential output.** Every control in §2
is a financial-advice control, and none of them would have caught W-0024 — a will appointing
its own testator as executor is not a hedging failure, a promotion, or a tax assertion. It
is a defect in a legal instrument, and the perimeter file has no vocabulary for it.

Worse for process: my own §3a item 4 ruling ("a will is a legal instrument; the right
disclaimer is about legal advice, not FCA authorisation") was **doctrine invented at the
point of use.** It was, I still think, the right call. But `workforce/core/index.md` rule 8
says *"Never guess at doctrine. No answer in the trunk = a trunk gap. Raise it."* I applied
a rule that does not exist rather than raising the gap. That is the delta.

### Dated sources
- **Legal Services Act 2007, Schedule 2** (legislation.gov.uk, "Latest available (Revised)";
  earliest listed version 07/03/2008). Six reserved legal activities, paras 3–8: rights of
  audience; conduct of litigation; reserved instrument activities; probate activities;
  notarial activities; administration of oaths. **Will-writing and will-drafting appear
  nowhere in the Schedule.** Para 5(3)(a) expressly *excludes* wills from "reserved
  instrument activities". Para 6 reserves *"preparing any probate papers for the purposes of
  the law of England and Wales."*
- **Legal Services Board, Feb 2013** — recommended to the Lord Chancellor that will-writing
  be made a reserved activity, finding significant risk of consumer detriment.
  **May 2013** — the Lord Chancellor declined, accepting that detriment existed but not that
  reservation was the right remedy.

**I do not conclude from this that the will builder is outside regulation.** That is exactly
the determination §7.3 forbids. What I can say is: Schedule 2 is the document a lawyer starts
from, the trunk cites none of it, and Fynla is generating instruments in a market a statutory
regulator formally judged to carry consumer detriment.

### Recommended position
1. Add to **§7.2**: the Legal Services Act 2007 and its Schedule 2, plus SRA and Legal
   Services Board material, to the sources the compliance lead must hold.
2. Add a **§5 row**: *"Will drafting — which regime governs a tool that generates a legal
   instrument is a legal question (§6.7). Interim position, applied and recorded during
   W-0019: the disclaimer is 'this tool doesn't provide legal advice' and a referral to a
   qualified solicitor. FCA-authorisation wording is wrong here and must not be used — it
   points at the wrong regime."*
3. Add **§6.7**: *"Does operating a will-generation tool engage the Legal Services Act 2007
   regime? If will-writing is not reserved, what obligations attach to an unregulated
   provider, and does 'this tool doesn't provide legal advice' hold where the tool drafts the
   instrument?"*

**Adopting costs** three short edits. **Not adopting** means the next agent to touch will
copy invents doctrine again — and the first instinct will be to reach for FCA wording, which
is the thing I had to block once already.

---

## Q2 — Consumer Duty: does it apply, and who may trade it away?

### The question
§6.4 already asks whether Consumer Duty applies to an unauthorised firm. W-0019 forces a
sharper second question: when the workforce records a Consumer Duty concern and then lets it
be **outweighed** by a product decision, what is being weighed — a legal obligation or an
internal standard — and **who is competent to do the weighing?**

### What the trunk says today
§5, row 2: *"Applicability to an unauthorised firm is a legal question (§6.4). **Its four
outcomes are adopted as internal quality standards regardless** — products meeting needs,
fair value, consumer understanding, consumer support."* §6.4 remains open.

**What it covers:** that the outcomes are held to voluntarily.
**What it does not cover:** any procedure for what happens when one is knowingly traded
against — no gate, no record, no named decider.

### What the app now does — verified
The refusal ships with its Consumer Duty mitigation embedded and the reason recorded in code:
`WillTypePolicy::REFUSAL_NO_MIRROR_PARTNER` docblock — *"The closing clause is a Consumer
Duty mitigation, not a flourish: without it the likely misreading is 'I cannot have a will at
all', and the user does nothing."* The clause is *"including where only one of you is making
a will"* (¶3).

### Dated sources
- **PRIN 2A.1.3G (26/06/2026)** — the Duty applies to *firms* conducting retail market
  business, or communicating/approving financial promotions to retail customers.
- **FCA Handbook glossary, "firm"**, definition (1): *"an authorised person, but not a
  professional firm unless it is an authorised professional firm."*
- **PRIN 2A.6.2R (31/07/2023)** — support must meet retail customers' needs including those
  with characteristics of vulnerability, ensure customers "can use their product as
  reasonably anticipated", and ensure they "do not face unreasonable barriers".
- **PRIN 2A.6.3G (31/07/2023)** — unreasonable barriers include steps "unreasonably onerous
  or time consuming" or "complex for a retail customer to carry out".

The glossary definition is the sentence a lawyer answering §6.4 would start from. **I am not
answering §6.4 with it** — the definition has fourteen contextual subsections and reading one
clause of a Handbook glossary is not how the perimeter question gets settled.

### The delta
**Two deltas, and the second is about me.**

First: §5 adopts four outcomes as standards but gives no procedure for overriding one. In
W-0019 the override was decided on the merits, correctly in my view — but it was recorded on
a branch document (`F-0003 §3a item 5`) which will be archived, not in any durable place, and
no gate was raised.

Second, and I am flagging it against my own output: **I wrote that "CSJ's side of the trade
wins."** That is a determination, not a flag. §7.3 permits me to *"flag content matching a
known risk pattern"* and forbids me to *"sign off, clear, or bless"* — and concluding that an
identified foreseeable harm is outweighed is a sign-off with a different verb. The correct
output was: state the harm, state precisely what would discharge it, and raise the gate for
CSJ. I am correcting that here, and the trade is now stated as open in §6 below.

### Recommended position
Add a procedure sentence to **§5**: *"Where a decision knowingly trades against one of the
four outcomes, the trade is a founder gate, not an agent judgement. The compliance lead
states the harm and states what would discharge it; it does not decide whether the trade is
acceptable."*

**Adopting** costs one gate per occurrence — this is rare; W-0019 is the first.
**Not adopting** means the pattern repeats, agents keep resolving trades they are not
competent to resolve, and the reasoning for accepting a known harm lives in branch documents
that nobody reads after the batch ships.

---

## Q3 — What is owed to users holding documents built under a withdrawn flow?

### The question
W-0019 acceptance 6 asks the product question (leave, warn, or migrate). Underneath it sits a
perimeter question the trunk has never been asked: **when Fynla withdraws a capability on the
grounds that it was outside its competence, what disclosure is owed to the users who already
used it?** And its sharper twin from W-0024: **a generated document already in a user's hands
is now known to be defective — what follows?**

### What the trunk says today
**§4 is the nearest clause and it does not reach this.** §4 requires positive disclosure
*"where Fynla knows its picture is incomplete for a user … at the point the affected figure
is shown"*, and its live instance is unmodelled crypto producing a silently incomplete IHT
figure. Its reasoning reads across perfectly — *"an incomplete figure presented without
qualification is worse than no figure"* — but a defective generated legal instrument is not a
figure, and it is not shown at a point we control. Once downloaded, printed, signed and
witnessed, it has left the product entirely.

**What §4 covers:** in-app figures, at the point of display.
**What it does not cover:** artefacts the product has generated and handed over.

### What the app now does — verified against production, not the board item
| Fact | Evidence |
|---|---|
| The W-0024 executor defect **is on production** | `git show origin/main:app/Services/Estate/WillDocumentService.php` — `generateMirrorWill()` at `:267`, `'executors' => $primary->executors` at `:309`, copied verbatim. `9cfeadb46` (2026-03-16) is on `origin/main`. |
| Production has **no mirror-only gate at all** | `git show origin/main:app/Services/Estate/WillTypePolicy.php` → **absent on main.** |
| Production **defaults a married user to a one-sided will** | `origin/main:.../WillBuilderIntroStep.vue:114` — `willType: this.formData.will_type \|\| 'simple'`. Both buttons render at `:69` and `:81` for `has_spouse`. A married user who never touches the block proceeds with `simple` **having made no choice.** |
| The dev fix is **prospective only** | Nothing in `WillTypePolicy`, `WillDocumentController` or `WillDocumentService` rewrites, flags or blocks an existing completed document. `show` and the document view carry no gate. |
| Local count of non-mirror documents held by married users | **0** (build-lead, F-0003 §9.2). |
| Production count | **Unknown.** `ssh-fynla` is production; this analysis is local-only and I did not connect to it. |

### The delta
Two exposures at **materially different urgencies**, which the trunk conflates because it
addresses neither:

**(a) Live and unquantified.** Production may hold generated mirror wills in which the spouse
appoints herself her own executor and is described as her own spouse. The user cannot detect
this — the document reads as a finished will. A corrected generator does not reach a document
already in someone's hands.

**(b) A documentation gap.** No trunk clause on withdrawal, defect discovery, or notification.

### Recommended position
**(a) is not a trunk amendment — it is a gate, and the production count is the determining
fact.** I understand that count is already being put to CSJ separately, so I will not
duplicate the ask. What I will do is state what follows from each answer, so the decision is
immediate either way:

- **Count is 0** → the exposure closes. The fix ships with the release on its own merits. The
  trunk clause is still wanted, at ordinary priority.
- **Count is > 0** → each affected household holds a document Fynla generated and Fynla now
  knows is wrong. **My recommendation is direct notification of the affected users**, naming
  the specific defect and recommending a solicitor review the document they hold — not a
  generic "we've improved the will builder" release note, which will not cause anyone to
  re-read a will they believe is finished.

  **I cannot tell you whether notification is legally required.** That is precisely the
  §6-class question I am recommending be added. What is within my competence: the trunk's own
  §4 reasoning points at disclosure, and the cost of notifying when it was not required is
  small beside the cost of not notifying when it was.

**(b) For the trunk:** extend **§4** from figures to generated artefacts — *"Where Fynla has
generated a document a user holds outside the product, and later learns it is defective, the
disclosure obligation does not end at the point of display."* And add **§6.8**: *"What
disclosure is owed to users holding a legal or financial document Fynla generated and has
since found defective?"*

---

## Q4 — Fair value, when a paid capability is withdrawn from a subset of subscribers

### The question
W-0019 withdraws the will builder **entirely** from one class of user — married, with no
linked partner account. If the will builder is a paid capability, that is a benefit removed
from paying subscribers with no price change and no refund path. Does that engage the fair
value outcome, and if the Duty does not apply, what is the internal standard?

I raised this during W-0019 **as a question because I had not verified the gating**
(F-0003 §3a item 6). **It is now verified.**

### What the trunk says today
Nothing. §5 has no commercial row. §6 has no fair-value question. `06-commercials.md` is a
separate file the perimeter does not cross-reference on this point, and amending it is not
mine.

### What the app now does — verified
- **The will builder is Premium-only.** `routes/api.php:950` puts the entire will-builder
  route group behind `Route::middleware('estate.full')`.
  `app/Http/Middleware/EnsureFullEstateAccess.php` returns **403** with
  `{"required_tier":"premium","message":"Full Estate Planning is part of Premium."}`.
  The route comment records that this is deliberate and that legacy `feature:pro` was
  insufficient. **So yes — this is a capability people pay for.**
- **Who loses it.** `WillTypePolicy::allowedWillTypes()` (`:105`) returns `[]` — no will of
  any kind — for a married user with no `liveSpouseId()`. `payloadFor()` sets
  `can_build: false`.
- **A second affected group nobody has named.** `refusalFor()` (`:129`) tests
  `canBuildMirror()` *before* it tests `$requestedType === MIRROR`. So a married user whose
  partner account is later deleted or unlinked **can no longer complete a mirror draft they
  already started** — `markComplete()` (`WillDocumentService.php:411-413`) throws
  `REFUSAL_NO_MIRROR_PARTNER`. **This group appears nowhere on W-0019.**

### Dated sources
- **PRIN 2A.4.1R (31/07/2023)** — fair value: *"the amount paid for the product is reasonable
  relative to the benefits of the product."*
- **PRIN 2A.4.2R (31/07/2023)** — a manufacturer must ensure its products provide fair value
  and conduct regular value assessments.
- **PRIN 2A.4.24R (31/07/2023)** — must *"regularly review the value assessment throughout
  the life of the product."*
- **PRIN 2A.4.25R (31/07/2023)** — where a product *"no longer provides fair value"*, the
  manufacturer must act to mitigate and remediate existing customer harm and prevent new harm.

Whether these bind Fynla is §6.4, unanswered.

### The delta
**The trunk holds no position on withdrawing a paid capability.** This is the first time it
has happened, and there was nothing to apply — which is why it surfaced as a question rather
than a flag. The gap is not that the wrong answer was given; it is that no clause exists.

There is also an interaction the analysis has not yet made explicit: refusing the no-partner
user is defensible **as a refusal** (that is Q2's trade). Refusing them **while continuing to
charge them for the feature** is a different question, and it is the one that has not been
asked.

### Recommended position
Add a **§5 row**: *"Withdrawing or narrowing a paid capability — a value review runs before
the change ships, and affected subscribers are told directly what changed for them and what
it means for what they pay. Silent withdrawal is not available."*

**Adopting** costs a review step on a rare event. **Not adopting** means the change ships
silently and a subscriber discovers at the point of use that a feature they pay for will not
run for them — at the worst possible moment, because they came to write a will.

---

## 4a. UPDATE 2026-08-21 — the production count came back, and exposure 1 is closed

**The determining fact arrived.** Production will-document count: **zero for real users.**
All four mirror wills on fynla.org belong to seeded preview personas, and across **49 real
accounts nobody has used the will builder at all** (read on the production server, read-only,
with CSJ's authorisation; relayed by team-lead).

**That is the "count is 0" branch I pre-stated in §Q3. It resolves as written:** the exposure
closes, there is no notification question, and there is nothing to remediate. The
recommendation I made for the ">0" branch — direct notification naming the specific defect —
**does not arise.** No user holds a defective generated will.

**Two things this does not do, and I want them on the record rather than assumed:**

1. **It is not a control working.** No gate existed on production; W-0024's executor defect is
   still in `origin/main:app/Services/Estate/WillDocumentService.php:309`, and production
   still defaults a married user to a one-sided will
   (`origin/main:.../WillBuilderIntroStep.vue:114`). The count is zero because nobody used the
   feature, not because anything stopped them. **The same defect with a hundred users would
   have produced a hundred defective wills**, and nothing in the system would have known.
2. **It does not touch the trunk findings.** Q1's gap — no clause anywhere on the
   legal-services regime governing a tool that drafts wills — is unaffected by how many people
   used it. Nor is Q2's missing procedure for Consumer Duty trades, or Q4's silence on
   withdrawing a paid capability. Those were never contingent on the count.

**Exposures 2 and 3 in §5 below stand unchanged.** Production still produces the artefact
W-0019 exists to prevent, and the paid-capability withdrawal still needs deciding before
release.

---

## 5. Live exposure vs documentation gap

**These are different urgencies and must not be read at the same weight.**

### Live — needs a decision, not a document

1. **~~W-0024 is on production, unquantified~~ — CLOSED, see §4a.** The count came back **zero
   for real users** (four mirror wills, all seeded preview personas; 49 real accounts, no will
   builder use). No user holds a defective will; no notification arises. The defect is still in
   `origin/main:app/Services/Estate/WillDocumentService.php:309` and still ships with the
   release — it is simply no longer an exposure to anyone.

2. **Production has no mirror-only gate and silently defaults married users to a one-sided
   will.** `origin/main:.../WillBuilderIntroStep.vue:114`. **Every day the dev branch is not
   released, production continues to produce the exact artefact W-0019 exists to prevent** —
   and the board item under-reported this: it said the two options were equally reachable;
   in fact one was the silent default. This is a release-timing fact and it is the strongest
   argument for shipping the batch. It is not an argument for anything in the trunk.
   *(Per standing instruction I am not recommending a deploy — I am stating the exposure's
   clock. The release decision is CSJ's.)*

3. **On release, Premium subscribers in the no-partner class lose a paid capability with no
   notification path.** Q4. Needs a decision **before** the release, not after — afterwards
   it is a remediation rather than a disclosure.

### Documentation gaps — real, not urgent

4. Trunk silent on the legal-services regime governing generated wills (Q1).
5. Trunk gives no procedure for knowingly trading against a Consumer Duty outcome (Q2) — and
   I breached §7.3 in that absence.
6. Trunk silent on defect notification for generated documents (Q3b) and on withdrawal of a
   paid capability (Q4).
7. **`workforce/` still has no dated source register.** §7.2 requires one and states *"a
   citation without a date is not a citation."* None exists. §6 of this report seeds one as
   an interim; it needs a permanent home in the trunk or `registry/`. **Routed to the
   Quartermaster** as an equipment gap — it is my own kit that is missing, which is the
   Quartermaster's remit, not the Chief of Staff's.
8. **The four questions were drafted and lost in the handoff.** `F-0003:373` and `:546` both
   assert they are "recorded on W-0019"; they are not, and no other file holds them
   (verified by grep across `workforce/` and `tests/Persona/`). **Routed to the Chief of
   Staff:** a handoff asserted an artefact existed and nothing checked. The content survived
   only because F-0003 §3a happened to narrate it.

---

## 6. Consumer Duty read, carried through

I said previously that what decides whether residual harm is real is **the quality of the
referral**. Carrying that through means saying what "good enough" is, concretely, and then
scoring what we actually ship. A referral is good enough if it does four things.

**1. Says what *we* cannot do, not what the *user* cannot have.** ✅ **Met.**
`REFUSAL_NO_MIRROR_PARTNER` ¶2: *"That's a limit of this tool, not a comment on your
situation."* This is the sentence that stops the refusal reading as a judgement on the user's
marriage.

**2. Leaves no impression that a will is unavailable to them.** ✅ **Met** — this is the
clause I required as a compliance change: *"including where only one of you is making a
will"* (¶3). Without it the predictable misreading is *"I can't have a will"*, and the user
does nothing. Doing nothing is the intestacy path, and intestacy is materially worse than the
one-sided will we are declining to build. This clause is load-bearing; it must not be edited
out as wordy.

**3. Names the kind of professional, and is specific enough to act on.** ⚠️ **Partially
met.** *"Please speak to a qualified solicitor"* names the profession — better than
"seek advice" — but it does not give a route. A user who does not already know that the Law
Society maintains a free public register of solicitors, or that many firms quote fixed fees
for a straightforward will, has been given a **direction** rather than a **route**. The
distance between those two is where the user stops.

**4. Does not leave the user worse off than when they asked.** ⚠️ **Not established.** They
arrived inside a Premium feature they pay for, and leave with nothing built, nothing changed
about what they pay, and no acknowledgement that anything changed. That is Q4, and it is
unresolved.

**So: the copy meets the two tests that matter most and misses the two that convert a refusal
into an action.** On the strength of tests 1 and 2 I would say the referral is materially
better than what a refusal usually looks like. On tests 3 and 4 it stops one step short of
the user being able to do the thing we just told them to do.

**My recommendation, within competence:** add a signposting line to
`REFUSAL_NO_MIRROR_PARTNER` ¶3 pointing at a named, free, public directory of solicitors.
**I am deliberately not drafting it.** Naming a specific external service is a public-claim
decision and a design-lead copy decision, and §2 Rule 3's own phrasing is *"suggest the user
speaks with"* rather than a directive — getting that register right is design-lead's craft,
not mine. What is mine is the statement that referral quality is the whole load-bearing
element of the Consumer Duty argument, and it currently stops one step short.

**And the correction I owe on my own earlier output:** I concluded that CSJ's side of the
trade wins. Under §7.3 that was not mine to conclude. The harm is stated; what would
discharge it is stated above; **whether the trade is acceptable is CSJ's**, and it is open.

### Interim dated source register (finding 7 — needs a permanent home)

| Source | Provision | Date on source |
|---|---|---|
| Legal Services Act 2007 | Sch 2 paras 3–8 (reserved activities); para 5(3)(a) excludes wills; para 6 probate | Latest available (Revised); earliest listed 07/03/2008 |
| Legal Services Board | Recommendation to reserve will-writing | Feb 2013 |
| Lord Chancellor | Decision declining that recommendation | May 2013 |
| FCA Handbook PRIN | 2A.1.3G — application to firms | 26/06/2026 |
| FCA Handbook glossary | "firm" (1) — "an authorised person…" | no date shown on entry |
| FCA Handbook PRIN | 2A.4.1R / 2A.4.2R / 2A.4.24R / 2A.4.25R — fair value | 31/07/2023 |
| FCA Handbook PRIN | 2A.6.2R / 2A.6.3G — consumer support, unreasonable barriers | 31/07/2023 |

---

## Done

- Reconstructed the four §6 questions (originals lost — §0) and answered each on the six
  axes asked for: question, trunk position, verified app behaviour, delta, recommendation
  with consequences, Consumer Duty read.
- Verified the app against **code and `origin/main`**, not against the board item's
  description of the code — which was wrong twice more (the production default to `simple`;
  the Premium gating I had previously only been able to raise as a question).
- Separated three live exposures from five documentation gaps (§5).
- Carried the Consumer Duty read through to four concrete tests and scored what ships: two
  met, two not (§6).
- Seeded the dated source register §7.2 requires and `workforce/` lacks.
- **Corrected my own §7.3 overstep** on the foreseeable-harm trade.

## Not done, and why

- **The trunk is unamended.** Recommendations only — §4 of the perimeter file and its owner
  line make amendments CSJ's, gated.
- **§6.1–§6.6 remain unanswered**, and I did not attempt them. §7.4: the compliance lead does
  not close §6; it makes the eventual review cheaper and better-aimed.
- **No production query.** `ssh-fynla` is production and this analysis is local-only. The
  production count is the determining fact for exposure 1 and it is already with CSJ.
- **No code changes, no PR, no deploy** — per dispatch.
- **No draft of the signposting line** for the referral (§6 test 3) — a public-claim and
  design-lead decision, deliberately left to them.
- **I could not verify the original wording of the four questions.** They do not exist.

## Assumptions

- `origin/main` is what runs on fynla.org, per `CLAUDE.md`'s deployment table. I inferred
  production state from the branch; I did not observe the server.
- `05-perimeter.md` at HEAD is the ratified text — `git status` shows it unmodified while
  four sibling constitution files are dirty, so I read the clean one deliberately.
- The four reconstructed questions match what I originally drafted. **Stated as an
  assumption, not a fact** — the originals are gone.
- Reading one glossary clause does not settle §6.4. I cite it as a lawyer's starting point,
  not an answer.

## Needs

- **Answer (CSJ):** production count of mirror wills → determines whether exposure 1 is live
  or closed. Already raised separately; §Q3 pre-states both branches.
- **Gate (CSJ):** four trunk amendments — §7.2 sources, §5 rows for will drafting / Consumer
  Duty trades / paid-capability withdrawal, and §6.7–§6.8.
- **Gate (CSJ):** the Q2 trade itself, which I should have raised rather than resolved.
- **Gate (CSJ):** notification for the no-partner Premium class, decided **before** the
  release rather than after.
- **Provisioning (Quartermaster):** a permanent home for the dated source register.

## Noticed — outside my remit, routed

- **build-lead / product-lead:** the `update` gate fires only on `step === 'intro'` with
  `will_type` filled (`WillDocumentController.php:154-158`). A married user with an existing
  **simple draft** can keep editing every other step and meets the refusal only at Complete.
  The approved copy does reach them intact — `\RuntimeException` is caught explicitly at
  `:192` and returned as a 422 carrying `$e->getMessage()` — so this is **not** a Rule 20
  copy-drift breach. But it is the shape PRIN 2A.6.3G calls "unreasonably onerous or time
  consuming", if the Duty applied. Worth a decision, not a block.
- **build-lead:** a married user whose partner account is deleted or unlinked **after** a
  mirror draft is started can no longer complete it (`refusalFor()` `:129` tests
  `canBuildMirror()` before the requested type). Not on W-0019, not intended as far as I can
  tell, and it removes a paid capability from someone mid-task.
- **Nobody — recorded so it is not "fixed":** `allowedWillTypes()` `:105` gives an
  **unmarried** user with a `liveSpouseId()` both simple and mirror. That is correct per
  W-0019 acceptance 3. Leave it.
- **Chief of Staff:** finding 8 — the lost questions. A handoff asserted an artefact existed;
  nothing verified it.
