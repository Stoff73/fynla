# 05 — Perimeter

**Status:** Ratified by CSJ, 2026-08-13, session 6.
**Owner:** CSJ (regulatory is his domain — `registry/people.md` §3.2). Amendments gated.

> **Nothing in this file is legal advice, and no agent may treat it as such.**
> It records what Fynla has decided and what its code already enforces. Questions
> that require a lawyer are listed at §6 and stay listed until answered by one.

---

## 1. Status

**Fynla is not FCA-authorised.** It provides guidance, not regulated advice, and
operates in **mechanically fail-closed `guidance` mode** — targeted support and
regulated advice are disabled until separately permissioned, governed, tested and
approved (`docs/superpowers/plans/2026-07-10-online-readiness-programme.md:20`).

Fail-closed means the default is refusal, not permission. That is a code property,
not a policy statement.

## 2. The seven rules — canonical, by reference

`app/Services/AI/Prompts/ComplianceRules.php` `<regulatory_compliance>` is
**canonical and is never restated here.** It is executable, so it cannot drift
silently — the same principle that makes `TaxConfigService` canonical for tax and
`PreviewUserSeeder` canonical for personas.

In summary only, so this file is readable: mandatory hedging language · no product,
provider, fund or platform recommendations · signpost regulated advice on complex
matters · investment risk warnings · tax caveats · no market timing · never quote
tax figures from memory.

Supporting controls, all live: `AiAdviceLog` — one structured Advice Case per
substantive response, linked to a signed episodic record · `fyn:episodic:purge` —
six-year retention under FCA SYSC 9.1 · `fyn:user:erase` — GDPR erasure ·
`FcaProcessInstructions.php` — the six-step planning process.

## 3. The seven rules bind everything, not just Fyn

**Ratified 2026-08-13.** Until now those rules lived in a system prompt and
governed only what Fyn said in a chat window. They now bind **all outbound
communication**, whoever writes it:

landing and feature pages · articles and everything the content pipeline publishes ·
marketing and transactional email · app-store listings · social · **support
replies, by humans as well as agents** · anything else a user or prospect reads.

**Why extended rather than left to Fyn.** Fynla is unauthorised, and content about
pensions, ISAs, inheritance tax and investments sits near the financial promotions
regime (s21 FSMA). Whether any given article *is* a financial promotion is a legal
question (§6.1). Applying the rules everywhere is more conservative than the law
may require, costs almost nothing, and means nothing has to be retracted.

**Support replies are named explicitly** because they are the likeliest place a
well-meaning answer becomes a personal recommendation. "Should I move my pension?"
gets the same hedging and the same signposting in a Slack reply as it does in Fyn.

## 4. Positive disclosure when the picture is incomplete

**Ratified.** Where Fynla knows its picture is incomplete for a user, **it says so
at the point the affected figure is shown** — not in a footer, not in a blanket
disclaimer.

`ComplianceRules.php` already requires honesty about *missing* data. This extends
it to *unmodellable* data — asset classes the product does not represent at all.

**Live instance:** crypto and digital assets are not modelled (verified
2026-08-13; they appear only in Estate will and letter documents). A holder
currently receives an inheritance-tax figure that is silently incomplete.

This is a **V2 breach before it is a regulatory one**: the person does not
understand their own situation and does not know it. An incomplete figure presented
without qualification is worse than no figure.

The HNW survey (`03-hard-nos.md` §3) may find more — family investment companies,
non-domicile positions, complex trusts. Each finding lands here.

## 5. Positions held

| Question | Position | Basis |
|---|---|---|
| **PS25/22 targeted support** | **Not currently sought.** Posture remains fail-closed guidance. Revisit when §6 is answered. | CSJ, session 6 |
| **Consumer Duty** | Applicability to an unauthorised firm is a legal question (§6.4). **Its four outcomes are adopted as internal quality standards regardless** — products meeting needs, fair value, consumer understanding, consumer support. | Three of the four are already V1, V2 and V4. Costs nothing; groundwork exists if it does apply. |
| **Regulated advice** | Never given. Signposted instead. | Rule 3 |

## 6. Open — requires a qualified answer

**These are not agent-answerable.** They stay open until a lawyer answers them.

1. Are Fynla's marketing articles financial promotions under s21 FSMA? If some are,
   what distinguishes them?
2. Does the guidance posture stay outside the regulated-advice perimeter, given Fyn
   reasons over the user's own figures?
3. Does PS25/22 apply to what Fynla already does, or only to what it might do next?
4. Does Consumer Duty apply to an unauthorised firm operating this way?
5. **CJEU special-category-by-inference** (`audit-synthesis.md:133`) — a retirement
   projection adjusting for smoking status infers health data. Does the current
   consent model hold under Article 9?
6. What must be disclosed when a material asset class is not modelled?

**Status:** external review planned, not currently funded. Until then §7 applies.

## 7. Interim review — the Compliance lead, extended

**Ratified 2026-08-13: an agent performs first-pass review as a stopgap.**

### 7.1 Prior art — this is an extension, not a new agent

Per `charter.md` §11, checked before building. Found: the **Compliance lead**
already owns FCA perimeter, PS25/22, Consumer Duty and tax accuracy, with
hard-block authority on tax services, AI prompts and public claims (`charter.md`
§4). Also found: `tax-compliance-reviewer` and `security-reviewer` agents, and
`FcaProcessInstructions.php`.

**Outcome: `extend`.** A new compliance agent would duplicate an existing remit.
What the Compliance lead lacks is not authority but **source access and a defined
protocol**. Those are what get built.

### 7.2 Sources it must hold

Primary and public, read directly — never summarised from memory:

FCA Handbook, particularly **PERG** (perimeter guidance), **COBS**, and **PRIN 2A**
(Consumer Duty) · **PS25/22** and its near-final rules · **FSMA s21** and the
Financial Promotion Order exemptions · **ICO** guidance on special-category data ·
HMRC guidance where tax accuracy is in scope.

Maintained as a dated source register. A citation without a date is not a citation.

### 7.3 The competence boundary — the part that matters

**An agent may apply a written rule. It may never determine what the law requires.**

| May | May never |
|---|---|
| Apply the seven rules to a piece of content | Determine whether something is a financial promotion |
| Cite primary sources with paragraph references | Conclude that an exemption applies |
| Flag content matching a known risk pattern | Approve anything as legally compliant |
| Draft the precise question a lawyer should answer | Sign off, clear, or bless |
| Report "no issues found **within my competence**" | Report "this is fine" |

**Its output is never an approval.** Two outcomes only: *no issues found within
competence*, or *flagged, with the reason and the source*. Publication still
requires the human approve-to-production button. The agent narrows what reaches
that button; it never replaces it.

**The failure mode this exists to prevent** is a confident-looking compliance
sign-off that nobody questions. An agent that says "compliant" has done more damage
than one that says nothing, because it stops a human from looking.

### 7.4 What makes it worth having

Beyond screening: it **builds the brief**. Every flag it cannot resolve becomes a
precisely-worded, evidenced question for §6. A lawyer answering six specific
questions with the relevant content attached bills a fraction of one asked to
review a fintech — so the stopgap makes the eventual review cheaper, not merely
later.

**This is a stopgap and is labelled as one.** It does not close §6. It does not
reduce the need for the review. It reduces the volume of things the review must
look at, and improves the questions it is asked.
