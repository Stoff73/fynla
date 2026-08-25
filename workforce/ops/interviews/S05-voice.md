# Session 5 — Voice

**Onboarding session 5 of 9.** Produces `core/constitution/04-voice.md`.
**Blocks Growth entirely** — `charter.md` §10 gates all public output until this exists.
**Date:** 2026-08-13 · **Interviewer:** Chief of Staff · **For:** CSJ

---

## Interview protocol — amended after session 7

**The interviewer's default source is the code, not CSJ.**

Sessions 4 and 7 both went wrong the same way: I asked CSJ about the subscription
model, twice, when prices, capability matrices, count caps, AI token budgets and
degrade behaviour were all implemented and readable. Session 7's entire question
list was answered by one seeder file whose own docblock said *"Prices and limits
are the approved Free and Premium commercial contract."*

**The rule, now applied to every remaining session:**

| Subject | Interview |
|---|---|
| Anything the product **already does** | **The code.** CSJ is not asked. |
| Intent, priorities, things not yet built | CSJ |
| A conflict between two implementations | CSJ, with both shown |

This is the same principle already ratified three times — `TaxConfigService`
canonical for tax, `PreviewUserSeeder` for personas, `TierResolver` for
entitlement. It was never generalised into how the interview itself runs. It is now.

**Corollary:** "CSJ said X in a previous session" is not a reason to ask again.
If it is implemented, go and read it.

## What I read first

`CoreIdentity.php` `<personality>` and `<response_format>` in full ·
`ComplianceRules.php` `<instructions>` in full · `docs/reference/Articles/pension-tracker.md` ·
`docs/reference/Articles/` index · `CLAUDE.md` Rules 9 and 12 · `02-values.md` as ratified

---

## ANSWERED 2026-08-13

> **CSJ:** Agree with the Q1 proposal.
>
> **Written to** `core/constitution/04-voice.md` — seven constants, six registers,
> and the acronym rule at §4.
>
> **One consequence that needs an action, not just a record.** The acronym rule
> amends `CLAUDE.md` Rule 9, and Rule 9's one home is `CLAUDE.md`. Leaving the
> exception only in the trunk creates a second home for the acronym rule, which is
> the Rule 20 disease this whole structure exists to prevent. **Until `CLAUDE.md`
> is amended, Rule 9 as written wins** (`00-precedence.md` §1) and `04-voice.md` §4
> is inert. Logged as a required gated amendment at `00-precedence.md` §2.6,
> alongside the six-versus-seven personas correction.
>
> **Written on assumption** (per the completeness check, correctable): constants
> C1–C7 as listed, registers as described. **C5 — no overclaiming, no false
> urgency, no manufactured scarcity — is the one to challenge now if it is wrong**,
> because Growth will test it immediately.
>
> **Q2 and Q3 answered same day.** Articles run through the automated pipeline:
> **dev fully automated, production behind an "approve to production" button** —
> the same shape as the code path, and no second approval mechanism. The back
> catalogue is **preserved as-is**; it is in use and doubles as pipeline test
> material. The old age-band model is not corrected in place — the catalogue gets
> mapped to the persona model as a deliberate exercise. Written to `04-voice.md`
> §5, with an explicit instruction that no agent re-targets or tidies an existing
> article as a side-effect of touching it.
>
> **C5 confirmed:** constants fine, genuine deadlines good, invented urgency out.
>
> **Correction to my own framing.** I presented the Rule 9 amendment as a blocker
> ("Rule 9 wins, §4 is inert"). CSJ: the upkeep regime already covers exactly this.
> He is right — it is an ordinary queued item, and I over-dramatised it. Reframed
> at `00-precedence.md` §2.6 as a visible queue rather than a problem.
>
> **His broader point absorbed:** rule files should be *lean for the latest models*,
> not merely accurate. Added as a sixth check to the quarterly review — *would a
> current model get this wrong without being told?* Rules written to scaffold a
> weaker model are now cost, since every unnecessary instruction dilutes the ones
> that matter. CSJ-owned rules are exempt unless CSJ raises them.
>
> **Session 5 closed. No open items.**

## The core finding

**Fynla already has two voices, and only one of them is written down.**

Fyn's voice is fully specified — warm, conversational, calm, always ends on a
question, never opens with filler, British English, no acronyms, no jargon, always
tied to the user's real figures.

The shipped marketing copy is **not** that voice, and correctly so:

> **Headline:** Every Pension. One Dashboard. Finally.
> — `docs/reference/Articles/pension-tracker.md`

Three sentence fragments. Fyn would never write that, and a landing page should.

**So the question this session answers is not "what is our tone" — it is "what
stays constant when the register changes."** A single flat rule set would either
make marketing sound like a chatbot or license Fyn to sound like an advert.

---

## Proposal — a constant core, then registers

### Part 1 — The constants (never vary, any surface, any author)

| # | Constant | Source |
|---|---|---|
| C1 | **British English**, always | `ComplianceRules.php` |
| C2 | **Never patronising, never alarmist, never condescending** | `CoreIdentity.php`; **V4** |
| C3 | **Specific over vague.** Real figures, real gaps, never an abstraction that hides the detail | **V2** |
| C4 | **No internal jargon** — "waterfall", "opportunity cost", "phased approach", "allocation framework" | `ComplianceRules.php` |
| C5 | **No overclaiming, no false urgency, no manufactured scarcity** | **V2, V3** |
| C6 | **Never imply advice we cannot give** — hedged language, no "you should" | `ComplianceRules.php` `<regulatory_compliance>` |
| C7 | **Currency in £**, formatted consistently | `ComplianceRules.php` |

C5 is the one that will bite marketing hardest, and it is the one I would most
expect to be argued with. Say now if you want it softened, because Growth will
test it in week one.

### Part 2 — Registers

Same voice, different rhythm. **Only rhythm and formality vary. The constants never do.**

| Register | Surface | Character |
|---|---|---|
| **Companion** | Fyn in-product | Warm, conversational, calm. Ends on a question. Never filler openers. Fully specified already in `CoreIdentity.php` — this register is closed. |
| **Functional** | UI labels, buttons, empty states, errors | Terse. No personality. Says what happened and what to do. |
| **Persuasive** | Landing pages, articles, ads, app store | Punchy. Fragments allowed. Benefit-led. Still bound by C1–C7. |
| **Direct** | Transactional email, notifications | Brief, one job per message, obvious action. |
| **Plain** | Support, incidents, bad news | No spin, no apology inflation, no hiding behind passive voice. |
| **Formal** | Regulatory filings, legal | Precise. The only register where jargon is correct. |

---

## Three things that need your answer

### Q1 — Acronyms versus search. The real conflict.

Rule 9 bans acronyms in user-facing text, ISA excepted. `ComplianceRules.php`
enumerates twenty of them.

But `docs/reference/Articles/pension-tracker.md` targets **"SIPP"** as a keyword — because that is
what people type into Google. Nobody searches "Self-Invested Personal Pension". And
a title tag and meta description *are* user-facing: they appear in search results.

**Proposal:** acronyms are permitted where the user brought them — search keywords,
meta descriptions, and headlines answering an acronym query — and **expanded on
first use in body copy**. Everywhere else Rule 9 stands unchanged.

**This is a genuine amendment to Rule 9**, which is CSJ-owned, so it needs your
explicit yes rather than my inference.

### Q2 — Existing marketing targets segments you overturned

`docs/reference/Articles/pension-tracker.md` states its audience as **"Mid-Career Professionals
(35-50), Pre-Retirees (55-65)"** — age bands.

Session 3 ruled that clients are defined by situation, not income, and the personas
are the definition. Age bands are the same class of error.

**So the existing article set is targeted against a model that is now void.** Not a
voice problem, but I found it here: does the back catalogue get re-targeted, left
alone, or reviewed as it is touched? Azlan owns marketing now, so this may be his
call — but the *doctrine* question of whether old copy must comply is yours.

### Q3 — Who may write in the Persuasive register?

Currently nobody, because `charter.md` §10 gates all public output until this file
exists. Once it exists, does the gate relax?

**Proposal:** Growth may draft freely in any register. **Publishing stays gated**
until Azlan has ratified the register definitions as marketing's owner — voice is
design-adjacent and he owns it. Then low-risk categories may go autonomous.

---

## Completeness check

**Ready to write on your confirmation:** all of `04-voice.md` — seven constants,
six registers, and the Q1 acronym rule.

**Cannot write without you:** Q1, because it amends a CSJ-owned rule.

**What I'll assume otherwise:** constants as listed, registers as described,
Rule 9 unamended — which would mean marketing cannot target acronym keywords, and
the SEO strategy needs rethinking rather than the rule.

**Note for the divergence register:** voice is the most likely place Azlan will
disagree, since he owns marketing and the Persuasive register is his working
surface. Expect this file to be revisited when he is interviewed.
