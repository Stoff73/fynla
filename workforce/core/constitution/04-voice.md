# 04 — Voice

**Status:** Ratified by CSJ, 2026-08-13, session 5. §5 open.
**Owner:** CSJ. Amendments gated.

**Scope: everything Fynla says, to anyone, in any medium, written by anyone.**
Product copy, Fyn, marketing, email, support, app-store listings, social,
regulatory filings. Human-authored and agent-authored alike.

Before this file, tone rules existed only inside Fyn's system prompts and bound
the AI. Everything a person wrote was ungoverned.

---

## 1. One voice, several registers

Fynla already speaks in two distinguishable ways, and correctly so. Fyn writes
calm conversational prose; the pension landing page opens *"Every Pension. One
Dashboard. Finally."* Three fragments — wrong for Fyn, right for a headline.

**So the rule is not one flat tone. It is a constant core, plus registers that
vary only in rhythm and formality.** A single flat rule set would either make
marketing sound like a chatbot or license Fyn to sound like an advert.

## 2. The constants — never vary, any surface, any author

| # | Constant | Why |
|---|---|---|
| **C1** | **British English**, always | Consistency, and the audience is UK-only by design |
| **C2** | **Never patronising, never alarmist, never condescending** | **V4.** Money is what people are most ashamed about; a product that judges gets closed |
| **C3** | **Specific over vague.** Real figures, real gaps, never an abstraction that hides the detail | **V2.** Summarising until nothing actionable survives is the failure mode |
| **C4** | **No internal jargon** — "waterfall", "opportunity cost", "phased approach", "allocation framework", "sequential phases" | **V2.** Jargon is abstraction wearing a suit |
| **C5** | **No overclaiming, no false urgency, no manufactured scarcity** | **V2 and V3.** Pressure is a form of selling, and we do not sell |
| **C6** | **Never imply advice we cannot give.** Hedged language; never "you should" | Regulatory perimeter — `ComplianceRules.php`, and `05-perimeter.md` |
| **C7** | **Currency in £**, formatted consistently | `ComplianceRules.php` |

**C5 binds marketing hardest and is not negotiable per-campaign.** "Only 3 days
left", "don't miss out", "the #1 tool" and similar are out. A genuine deadline
stated plainly is fine; an invented one is not.

## 3. The registers

Rhythm and formality vary. **The constants never do.**

| Register | Surface | Character |
|---|---|---|
| **Companion** | Fyn, in-product | Warm, conversational, calm. Ends on a question. No filler openers. **Closed** — fully specified in `CoreIdentity.php`; do not re-specify here. |
| **Functional** | UI labels, buttons, empty states, errors | Terse. No personality. What happened, and what to do about it. |
| **Persuasive** | Landing pages, articles, ads, app-store listings | Punchy. Fragments allowed. Benefit-led. Still bound by C1–C7. |
| **Direct** | Transactional email, notifications | Brief. One job per message. The action obvious. |
| **Plain** | Support, incidents, bad news | No spin, no apology inflation, no passive voice hiding who did what. |
| **Formal** | Regulatory filings, legal | Precise. The only register where technical vocabulary is correct. |

## 4. Acronyms — ratified 2026-08-13

**Amends `CLAUDE.md` Rule 9.** Rule 9 bans acronyms in user-facing text, ISA
excepted. That rule was written for the product and is right there. It broke on
discovery surfaces: nobody searches "Self-Invested Personal Pension", and
`Articles/pension-tracker.md` already targets "SIPP" as a keyword. A title tag and
meta description are user-facing — they appear in search results.

**The rule:**

| Where | Acronyms |
|---|---|
| Search keywords, meta titles and descriptions, headlines answering an acronym query | **Permitted** — the user brought the acronym; meeting them in their own words is not jargon |
| Body copy, anywhere | **Expanded on first use**, then the acronym may be used |
| Product UI, Fyn, email, support | **Banned**, exactly as Rule 9 states. ISA excepted. |

**The principle:** an acronym the user typed is their vocabulary. An acronym we
introduce is ours, and ours needs explaining.

Rule 9's one home stays `CLAUDE.md`. This exception is queued into the ordinary
doctrine-upkeep process (`00-precedence.md` §2.6) and applied there — not a
blocker, since publishing is gated regardless.

## 5. Publication — ratified 2026-08-13

**Articles run through the automated pipeline** CSJ is currently building.

| Target | Gate |
|---|---|
| **Dev** | **Fully automated.** No approval. |
| **Production** | **"Approve to production" button.** A human presses it. |

This mirrors the code path exactly — autonomous to dev, gated at prod
(`08-process.md` §1) — and it satisfies §10 of the charter without a second
approval mechanism, per the session 2 ruling to route into the existing approver
rather than build alongside it.

**The back catalogue is preserved as-is.** CSJ, session 5: the existing articles
are in use and are also the pipeline's test material. **The old age-band audience
model is not to be corrected in place** — the catalogue will be mapped to the
persona audience model instead. No agent rewrites, re-targets or "tidies" an
existing article; mapping is a deliberate exercise, not a side-effect of touching
one.

## 6. Open

None.

## 7. Expect revision

Azlan owns marketing (`registry/people.md` §3.2), and the Persuasive register is
his working surface. This is the file most likely to gain a divergence when he is
interviewed. Ratified now so Growth is unblocked — not because it is settled.
