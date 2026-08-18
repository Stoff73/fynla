# Session 6 — Perimeter

**Onboarding session 6 of 9.** Produces `core/constitution/05-perimeter.md`.
**Date:** 2026-08-13 · **Interviewer:** Chief of Staff · **For:** CSJ

> **I am not a lawyer and this is not legal advice.** Everything below is a
> reading of what your own documents and code already say, plus questions that
> need a qualified answer. Where I flag a risk, the output is a question for your
> legal review — never a conclusion.

---

## ANSWERED 2026-08-13

> **CSJ:** Agree with the proposal. Instead of a lawyer, create an agent with the
> necessary skills and knowledge access to review — as a stopgap for now.
>
> **Written to** `core/constitution/05-perimeter.md`.
>
> **Prior-art check run on your own request, per `charter.md` §11 — outcome:
> `extend`, not `none`.** The **Compliance lead already owns** FCA perimeter,
> PS25/22, Consumer Duty and tax accuracy, with hard-block authority. A new
> compliance agent would duplicate it. What it lacks is not authority but **source
> access and a defined protocol**, so those are what get built (§7).
>
> **The competence boundary is the load-bearing part (§7.3): an agent may apply a
> written rule; it may never determine what the law requires.** So it can screen
> content against the seven rules and cite PERG with paragraph references — but it
> cannot decide whether something is a financial promotion, cannot conclude an
> exemption applies, and **cannot approve anything.** Two outcomes only: *no issues
> found within competence*, or *flagged with reason and source*.
>
> **The failure mode this guards against:** a confident-looking compliance sign-off
> that nobody questions. An agent saying "compliant" does more damage than one
> saying nothing, because it stops a human looking.
>
> **What makes it genuinely worth having (§7.4):** it builds the brief. Every flag
> it cannot resolve becomes a precise, evidenced question for §6 — so the stopgap
> makes the eventual legal review *cheaper*, not merely later. It does not close
> §6 and is labelled a stopgap throughout.
>
> **Session 6 closed.** §6 stays open by design until a lawyer answers it.

## What I read first

`ComplianceRules.php` `<regulatory_compliance>` in full (seven numbered rules) ·
`ComplianceRules.php` `<instructions>` · `CoreIdentity.php` ·
`docs/superpowers/plans/2026-07-10-online-readiness-programme.md` global
constraints · `FCA/sandbox.md` and the two sandbox application drafts ·
`AiAdviceLog.php` · `fynlaBrain/April/April24Updates/audit-{evidence,synthesis}.md` ·
`02-values.md`, `03-hard-nos.md`, `04-voice.md` as ratified

---

## Part A — What already exists, and is stronger than the audit verdict suggests

The vault's verdict "C1 — No FCA analysis | No doc exists" is genuinely out of
date, as `audit-synthesis.md:129` itself says. The operative perimeter is defined
in code and enforced mechanically:

| Control | Where | Status |
|---|---|---|
| **Seven regulatory rules** — mandatory hedging, no product recommendations, signposting to regulated advisers, risk warnings, tax caveats, no market timing, no tax figures from memory | `ComplianceRules.php` `<regulatory_compliance>` 1–7 | Live, in every Fyn turn |
| **Fail-closed guidance mode** — "targeted support and regulated advice remain disabled until separately permissioned, governed, tested and approved" | `online-readiness-programme.md:20` | **Mechanical**, not prose |
| **Advice audit trail** — one structured Advice Case per substantive response, linked to a signed episodic record | `AiAdviceLog.php`, `:21` | Live |
| **Six-year retention** (FCA SYSC 9.1) | `fyn:episodic:purge` | Live |
| **GDPR erasure** | `fyn:user:erase` | Live |
| **Not-regulated disclaimer** | Public pages | Live |

**Proposal: adopt all of this into `05-perimeter.md` by reference, not by
restatement.** `ComplianceRules.php` is executable and therefore canonical — same
principle as `TaxConfigService` for tax and `PreviewUserSeeder` for personas. The
trunk points at it and never copies it.

---

## Part B — The gap that matters most

**Every one of those seven rules binds Fyn. Nothing binds anything else.**

They live inside a system prompt. They govern what the AI says in a chat window.
They do not govern:

- Landing pages and feature pages
- The article catalogue and everything the new pipeline will publish
- Marketing email
- App-store listings
- Support replies
- Social

**And the article pipeline is about to start publishing to production
automatically-to-dev, one button to prod.** That is the exact surface with no
perimeter rules attached to it.

### B1 — The question I most want a lawyer to answer

Fynla is **not FCA-authorised**. Content marketing about pensions, ISAs,
inheritance tax and investments — which is what the article catalogue is — sits
close to the **financial promotions regime (s21 FSMA)**, under which an
unauthorised person may not communicate an invitation or inducement to engage in
investment activity unless it is approved by an authorised person or an exemption
applies.

**I am not qualified to say whether Fynla's articles are financial promotions.**
Plenty of financial content is not. But the question is real, it is answerable
only by a lawyer, and the pipeline is being built now.

**Proposal, pending that answer:** the seven regulatory rules are extended to
*all* outbound content, not just Fyn — hedging, no product or provider naming, no
market timing, risk warnings and tax caveats where relevant. That is more
conservative than the law may require, costs little, and means nothing has to be
retracted later.

### B2 — Support replies are unmodelled

If a founder answers "should I move my pension?" in an email or a Slack DM, no rule
covers it. **Proposal:** the same seven rules bind every human and agent reply on
any Fynla channel. Worth a line in the trunk because it is the likeliest place a
well-meaning answer becomes a personal recommendation.

---

## Part C — Three decisions

### C1 — PS25/22 targeted support: seek it, or stay in guidance?

Live since 6 April 2026. It creates a category between guidance and full advice,
explicitly designed for AI-assisted consumer guidance — mandatory labelling,
segment disclosure, conduct standards.

Fynla's *technical* posture is settled: fail-closed, disabled until permissioned.
**The strategic question is not:** do you intend to seek targeted-support
authorisation, and on what horizon?

It matters to the workforce because it determines whether "we cannot say that yet"
is temporary or permanent — which changes what Product may plan and what Growth
may imply.

**Proposal:** record as *not currently sought; posture remains fail-closed
guidance; revisit when the legal review is funded.* Say if the intent is stronger
than that.

### C2 — Consumer Duty

No framework exists. **I don't know whether Consumer Duty applies to an
unauthorised firm** — that is a legal question, and it is on the list for review.

**Proposal:** rather than assert applicability, adopt its four outcomes as
*internal quality standards* regardless — products meeting needs, fair value,
consumer understanding, consumer support. Three of the four are already values
(V1 access, V2 understanding, V4 dignity). Costs nothing, and if the Duty does
apply the groundwork exists.

### C3 — Disclosure when the picture is incomplete

Carried from session 4. Crypto is not modelled; a holder gets a silently
incomplete inheritance-tax figure in a product whose premise is "one living
picture". The HNW survey may find more.

This is a **V2 violation** before it is a regulatory one — the person does not
understand their situation and does not know it.

**Proposal — a positive obligation:** where Fynla knows its picture is incomplete
for a given user, it says so at the point the affected figure is shown. Not a
blanket disclaimer in a footer; a specific statement next to the specific number.
`ComplianceRules.php` rule "if you do not have sufficient data… say so honestly"
already does this for *missing* data. This extends it to *unmodellable* data.

---

## Part D — Scope the legal review now, even though it's unfunded

The review is parked (planned, not funded). **Scoping it now costs nothing and
makes it cheaper when it happens** — a lawyer answering six precise questions bills
less than one asked to review a fintech.

Proposed brief:

1. Are Fynla's marketing articles financial promotions under s21 FSMA? If some
   are, what distinguishes them?
2. Does the current guidance posture stay outside the regulated-advice perimeter,
   given Fyn uses the user's own figures?
3. Does PS25/22 targeted support apply to what Fynla already does, or only to what
   it might do next?
4. Does Consumer Duty apply to an unauthorised firm operating this way?
5. **CJEU special-category-by-inference** (`audit-synthesis.md:133`): retirement
   projections adjusting for smoking status infer health data. Does the current
   consent model hold?
6. What must be disclosed when a material asset class is not modelled?

---

## Completeness check

**Ready to write:** Part A by reference, B1/B2 conservative extension, C1–C3 as
proposed.

**Cannot resolve here, by design:** anything needing a lawyer. Those become the
Part D brief and a parked entry, not an assumption.

**What I'll assume otherwise:** the conservative reading throughout — seven rules
bind all output, targeted support not sought, Consumer Duty outcomes adopted as
internal standards, incompleteness disclosed at the point of the number.

**Blocking:** B1 does not block Phase 1, but it should be answered before the
article pipeline pushes anything to production.
