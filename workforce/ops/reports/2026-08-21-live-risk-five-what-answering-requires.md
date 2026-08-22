# The live-risk five — what answering each would actually require

**Agent:** compliance-lead · **Date:** 2026-08-21
**Task:** team-lead — *"Do not answer them; characterise what answering each would actually
require — who, reading what, and whether it needs a lawyer or just a decision."*
**Subjects:** `Q-02`, `Q-03`, `Q-01`, `Q-08`, `Q-13` from `ops/open-questions.md`

> **No question is answered here.** `05-perimeter.md` §7.3. This is the procurement shape of the
> brief — who could answer it, what they would need in front of them, and what it would cost to
> assemble. **Describing how to buy an answer is not giving one.**

---

## 0. The three findings that change how this is bought

**1. This is three specialisms, not one lawyer.** The natural move is to bundle five questions
with one firm. **Two of them do not fit that firm.**

| Specialism | Questions |
|---|---|
| **Financial services perimeter** — FSMA, RAO, FCA Handbook | `Q-02`, `Q-01`, `Q-08` |
| **Data protection** — UK GDPR Article 9 | `Q-03` |
| **Legal services / private client** — LSA 2007, Mental Capacity Act | `Q-13` |

A firm that says it covers all three should be asked **which individual** answers each. This is
the same "do not reason across" point as §1.3 rule 3, applied to procurement.

**2. Two of the five may not need buying at all, and establishing that is free.**
`Q-03` and `Q-13` each have a **cheaper prior step** that could remove the need for the answer —
one a measurement an agent can run, one a production count that has been offered and never
taken. **Both are described below. Neither has been done.**

**3. The most expensive input already exists as a database table.** `Q-02` cannot be answered
from a description of Fynla — it needs **real Fyn outputs**. `AiAdviceLog` already records one
structured Advice Case per substantive response. **The corpus is a query, not an assembly job.**

> **SUPERSEDED — see the `2026-08-21 compliance-lead` correction at the foot of this report
> ("it does not hold. Three ways").**
> **`ai_advice_logs` stores no response text**, and it **only records responses Fynla's own
> classifier called advice** — so a corpus drawn from it would be **selected on the variable
> `Q-02` tests.** Preview personas have **zero conversations**, so the persona route is a run
> task, not a query. **The recommendation to use personas survives; "it is a query" does not.**
> Left as written — it is the claim the verification was ordered against.

---

## `Q-02` — Does the guidance posture stay outside the regulated-advice perimeter?

**The broadest question on the list, and the one where a cheap answer is worse than none.**

**Who:** A financial-services regulatory specialist — perimeter work specifically (whether an
activity is a regulated activity; advising on investments). **Not a generalist commercial
firm.**

**Reading what — and the composition of this bundle is the whole point:**

| Material | Why |
|---|---|
| `app/Services/AI/Prompts/ComplianceRules.php` `<regulatory_compliance>` | the seven rules **as executed**, not as described |
| `app/Services/AI/Prompts/FcaProcessInstructions.php` | the six-step process |
| **A corpus of real Fyn outputs** | **the load-bearing item — see below** |
| The tool catalogue, and `AdviceFyn::WRITE_TOOLS` | what Fyn can read, and what it is mechanically prevented from doing |
| `AiAdviceLog` structure | that every substantive response is already evidenced |

**Lawyer or decision? Lawyer, unambiguously.**

### Why the corpus decides whether the answer is worth anything

**A lawyer told *"we give guidance, not advice"* will answer the question they were asked, and
that answer is worthless.** The question is whether what Fyn *actually says*, to a user with
real figures in front of it, stays on the guidance side. That cannot be assessed from a policy
description — **it is a question about outputs.**

**This is `05-perimeter.md` §7.3's failure mode with the roles reversed.** §7.3 exists to stop an
agent producing a confident sign-off nobody questions. **A lawyer's "you're fine", given without
seeing the outputs, is the same artefact with a higher price and more authority.**

### How to make it cheap — and it is cheaper than it looks

`AiAdviceLog` already holds one structured Advice Case per substantive response. **The corpus
is a query.**

**But do not query real users for this.** Sending a sample of real households' financial
conversations to an external firm is a data-protection question in its own right, and it would
be self-defeating to create one while answering another.

**Recommend: generate the corpus against the preview personas** (`PreviewUserSeeder` — six
seeded households, `is_preview_user = true`, entirely synthetic). They cover the range
deliberately: young family, peak earners, entrepreneur, young saver, retired couple, student.
**Fyn's behaviour on them is representative; the data protection problem disappears entirely.**

**Nobody has produced this corpus. It is the single highest-value piece of preparation on this
list**, and it needs no legal input to assemble.

---

## `Q-03` — Article 9, special-category by inference from smoking status

**The sharpest unmitigated item — it runs today and nothing constrains it.**

**Who:** A **data protection** specialist. **Different discipline from `Q-02`** — do not assume
the perimeter firm covers Article 9, and if they say they do, ask who.

**Reading what:**

| Material | Why |
|---|---|
| `app/Services/Retirement/DecumulationPlanner.php`, `app/Services/Estate/LifeCoverCalculator.php` | **what smoking status actually does to the output** |
| The collection point — which form, and what the user is told there | consent is assessed where it is taken, not where it is used |
| `App\Models\UserConsent` — the six types | **which one is claimed to cover this**, and whether anything says so |
| The privacy notice | what the user was told the data was for |

**Lawyer or decision? Lawyer — but establish one product fact first, and it is free.**

### The cheaper prior step nobody has taken

**Nobody has measured how much the smoking flag actually changes the output.**

That is a code measurement, not a legal question, and it is within an agent's competence
because it measures behaviour rather than law. **It determines which conversation is worth
having:**

- **If the delta is material**, the data is load-bearing, the question must be bought, and the
  brief above is what to buy it with.
- **If the delta is small**, there is a product conversation to have first — and a product
  decision that changes what is collected changes the question. **Whether it removes it is still
  legal, and I am not saying it does.**

**Recommend running the measurement before buying anything.** It costs an afternoon and it may
change what is purchased. **Right now the question would be bought without anyone knowing which
case they are in.**

---

## `Q-01` + `Q-08` — financial promotions, and content Fynla pays for but does not write

**Ask together, in the same engagement as `Q-02`, and re-scope `Q-01` before asking it.**

**Who:** The **same** financial-services perimeter specialist as `Q-02`. s21 FSMA is that
discipline. **Bundling all three into one engagement should cost materially less than two**, and
it is the only genuine bundling opportunity on this list.

**Reading what:**

| Material | For |
|---|---|
| A representative sample of published articles (`InsightArticle`, `PipelineArticle`) | `Q-01` |
| The approval pipeline — **who approves, against what** | `Q-01` |
| The landing and marketing pages | `Q-01` |
| `config/awin.php` **and the commercial terms** — what Fynla actually pays for | `Q-08` — *"pays for the results of"* is the fact that separates it from `Q-01` |
| **Samples of live Awin publisher creatives** | `Q-08` — **see below** |

**Lawyer or decision? Both, in that order — and the decision must come first.**

### `Q-01` must be re-scoped, and that is a decision, not legal work

`Q-01` as written asks about **copy Fynla writes**. The regime map records a second category —
copy Fynla **pays for the results of** — and marks the advertising row Unmapped.

**Somebody must decide whether the question covers only what Fynla authors, or everything Fynla
benefits from.** That is CSJ's call and it takes a minute. **Asking it un-rescoped buys a
precise answer to the wrong question**, which is the most expensive possible outcome.

### `Q-08` has an input nobody has looked at

**Fynla pays for results from content it does not write, and nobody has read that content.**

**A lawyer asked "is affiliate content a problem" with no samples will say "it depends", and
they will be right.** The answer turns entirely on what the publishers actually say about
pensions, ISAs and inheritance tax while linking to Fynla.

**Who does this:** somebody with Awin dashboard access pulls a sample of live publisher
creatives and landing pages. **Not an agent — this is outside the codebase.** It is a concrete,
small, assignable task and **it should happen before the question is asked, not after.**

---

## `Q-13` — Lasting Power of Attorney generation and assessment

**The one that may be answerable by deleting a feature.**

**Who:** **Not the financial services firm.** Legal services / private client — Legal Services
Act 2007, Mental Capacity Act 2005. **This is the question most likely to be mis-assigned**,
because it arrives in a bundle from a fintech and looks like it belongs with the others.

**Reading what — and most of it is already written:**

| Material | State |
|---|---|
| `2026-08-21-W-0100-lpa-perimeter-review.md` | **done.** The reserved-activity analysis is complete — LSA 2007 Sch 2 para 5(3)(c) |
| `2026-08-21-lpa-claims-rulings.md` | **done.** Seven rulings, the instruments quoted verbatim, commencement checked |
| `core/registry/sources.md` rows A1–A10 | **done.** Every provision, dated |
| A sample generated document | to produce |
| `LpaCheckPolicy` — what Fynla is now entitled to say | in flight |

**This is the best-prepared question on the list by a distance**, which is what §7.4 predicted
would happen. **A lawyer can be handed the statutes, the paragraph references, the commencement
position and the exact wording — and asked only the residual question.**

**Lawyer or decision? A CSJ decision could remove it entirely, and it should be put first.**

### The production count, which has been offered all day and never taken

**W-0100 asked how many real users hold a Lasting Power of Attorney on production. It is still
unanswered.**

It decides whether this question is worth buying:

- **Zero, or near zero** → the cheapest answer to `Q-13` is **not to generate the instrument at
  all**. W-0110 already records that `/m` and native have **no** Lasting Power of Attorney
  surface, so the feature is web-only. **Removing a feature nobody uses costs less than a legal
  opinion about it.**
- **Material usage** → the question must be bought, and the brief above is ready.

**I am requesting the query now** — it is the first time today I have actually needed the
standing offer, and it is the input to a decision rather than to a ruling.

---

## Summary — what to buy, what to establish first

| # | Specialism | Buy? | Do this first (free or cheap) |
|---|---|---|---|
| `Q-02` | financial services | **yes** | **Generate the output corpus from preview personas.** Highest-value preparation on the list |
| `Q-01` | financial services | **yes, with Q-02** | **CSJ re-scopes the question.** One minute; asking it un-rescoped buys the wrong answer |
| `Q-08` | financial services | **yes, with Q-02** | **Pull live Awin publisher samples.** The answer turns on them entirely |
| `Q-03` | data protection | **probably** | **Measure the smoking-status delta.** May change what is bought |
| `Q-13` | legal services | **maybe not** | **Run the production count.** May moot the question |

**Three of the five have a preparation step that costs nothing and changes what is purchased.
None of the three has been done.** That is the difference between buying deliberately and buying
by drift.

---

## Done

- Characterised all five: who, reading what, lawyer or decision — **without answering any.**
- Established that this is **three specialisms, not one engagement**, and which two do not fit
  the obvious firm.
- Identified the **only genuine bundling opportunity** (`Q-01` + `Q-08` with `Q-02`).
- Found that **the most expensive input to `Q-02` already exists as a table**, and a way to
  produce it that removes the data-protection problem rather than creating one.
- Identified **three free preparation steps** that change what is bought, none of them done.
- Named the question most likely to be **mis-assigned to the wrong specialism**.

## Not done, and why

- **No question answered.** Not one. §7.3.
- **The output corpus is not generated.** Recommended, not run — it is a build task and the
  preview-persona route needs someone with a running environment.
- **The smoking-status delta is not measured.** Within an agent's competence, outside this
  task's scope, and it should be assigned deliberately rather than picked up.
- **No production query run.** Requested, not taken.
- **No Awin publisher content reviewed.** Outside the codebase and outside my access.
- **`Q-01` not re-scoped.** That is CSJ's decision and I have said what it is.

## Assumptions

- That `AiAdviceLog` records enough of each response to constitute a corpus. **Read from
  `05-perimeter.md` §2's description, not verified against the table.** Worth checking before
  anyone plans on it.
- That the preview personas exercise Fyn representatively. They are the six seeded households
  and cover the intended range; **I have not compared their conversations to real ones.**

## Needs

- **Production query (team-lead):** how many real users on `fynla.org` hold a Lasting Power of
  Attorney. **Input to a CSJ decision that could moot `Q-13` entirely.**
- **Decision (CSJ):** re-scope `Q-01` before it is asked.
- **Assignment (team-lead):** the output corpus, the smoking-status delta, the Awin samples.
  **All three are cheap; none is mine.**

---

## Result — 2026-08-21, later the same day

**One of the two "may not need buying" branches resolved, and it resolved the cheap way.**

**`Q-13`: the production count is zero.** 6 `lasting_powers_of_attorney` rows on `fynla.org`,
all preview personas, **no real user holds one**. `NULL` treated as real rather than preview, so
the method errs toward over-reporting. Run read-only by team-lead, 2026-08-21.

**So this report's own recommendation applies:** *"if real usage is zero or near zero, the
cheapest answer is not to generate the instrument."* **`Q-13` has moved out of the live-risk
five and into a CSJ product decision** — a materially cheaper question than the one it replaces.
Recorded on `Q-13` in `ops/open-questions.md` with the count, the date, the method and its
limits, **so the next reader sees the basis rather than the conclusion.**

**The live-risk five are now four.** `Q-02`, `Q-03`, `Q-01`, `Q-08`.

**Three limits, none of which this report should be read as ignoring:** the count bounds the
instrument and not the impression; **it does not reach the will renderer's drawn signatures,
which are the sharper defect and a different table entirely**; and it expires the day a real
user creates one.

**The standing offer was used once, at the end, on the one question where the answer changed a
decision rather than supported a ruling.** Every ruling made today stood without it. That is the
argument for the remaining two preparation steps — the output corpus and the smoking-status
delta — being run before anything is purchased rather than after.

---

## Correction — 2026-08-21, compliance-lead

**§0 finding 3 was wrong, and the assumption behind it was flagged in this report's own
Assumptions section and then not checked.** Verified at team-lead's instruction; full working in
`2026-08-21-aiadvicelog-corpus-assumption-verified.md`. Marker placed at the stale claim.

**What is wrong:** `ai_advice_logs` has **no response-text column**, its link to the text
(`message_id`) carries **no foreign-key constraint**, and — the finding that matters — **it only
fires for responses Fynla's own classifier called advice** (`HasAiChat.php:1416`). A corpus drawn
from it would be **filtered by Fynla's answer to the question `Q-02` asks**, excluding precisely
the borderline cases the question is about. **Sampling defect, not a legal one.**

**What survives:** the corpus is still the load-bearing input, and **preview personas are still
the right source** — `user_data_snapshot` embeds income, expenditure, employment and marital
status in every row, so the disclosure problem it was proposed to avoid is real.

**What changes:** the correct basis is **unfiltered assistant messages with the classification as
a field rather than a filter**, and **personas have zero conversations on dev**, so the material
must be **generated, not selected**. **Preparation step, not free** — though possibly small, if
the existing eval harness can drive it. **That check has not been done and is the first task.**

**Why this matters beyond the fact:** this report told you a preparation step was cheap. **Had it
been assigned on that basis, whoever took it would have discovered mid-task that the cheap option
never existed** — which is the outcome the report itself was written to prevent.
