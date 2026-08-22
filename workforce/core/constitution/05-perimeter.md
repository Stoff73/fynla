# 05 — Perimeter

**Status:** Ratified by CSJ, 2026-08-13, session 6.
**Owner:** CSJ (regulatory is his domain — `registry/people.md` §3.2). Amendments gated.

> **Nothing in this file is legal advice, and no agent may treat it as such.**
> It records what Fynla has decided and what its code already enforces. Questions
> that require a lawyer are listed at §6 and stay listed until answered by one.

---

## 1. Status and regime map

**Fynla is not FCA-authorised.** It provides guidance, not regulated advice, and
operates in **mechanically fail-closed `guidance` mode** — targeted support and
regulated advice are disabled until separately permissioned, governed, tested and
approved (`docs/superpowers/plans/2026-07-10-online-readiness-programme.md:20`).

Fail-closed means the default is refusal, not permission. That is a code property,
not a policy statement.

### 1.1 The map

*Adopted by CSJ 2026-08-21, on `compliance-lead`'s proposal
(`ops/reports/2026-08-21-perimeter-regime-map-proposal.md`), and expanded on the
same ruling. Installed by the Archivist 2026-08-21.*

**Fynla's activities touch more than one body of law. This file is written against
the financial-services regime and is largely silent on the others.** The table
records which is which. It maps *this file's coverage*, not the law.

> **Naming a regime in this map asserts only that Fynla performs activities that
> regime is addressed to. It never asserts that the regime applies, or how.** That
> is the determination §7.3 forbids, and the map is deliberately built so it does
> not require one — an entry reading "unmapped" is a statement about *this file's
> coverage*, not about the law.

| Regime | The Fynla activity that engages it | This file |
|---|---|---|
| **Financial services** — FSMA, FCA Handbook | guidance on pensions, investments, savings and tax; Fyn reasoning over the user's own figures | **Mapped** — §1–3, §5, §6.1–6.4, §7.2 |
| **Data protection** — UK GDPR, DPA 2018 | holds a household's entire financial position; infers health from smoking status | **Partially mapped** — erasure and retention only (§2), plus one Article 9 question (§6.5). Lawful basis, consent and subject rights are not covered. |
| **Privacy in electronic communications** — PECR, as amended by the Data (Use and Access) Act 2025 (in force 5 Feb 2026) | analytics and affiliate cookies; consent gathering | **Unmapped** — W-0050 |
| **Legal services** — Legal Services Act 2007 | generates wills, lasting powers of attorney and trust documents | **Unmapped** — W-0019, W-0024, W-0100 |
| **Consumer protection** — DMCC Act 2024 Part 4 | a paid subscription with cancellation; withdrawing paid capabilities; claims made to acquire customers | **Unmapped** |
| **Advertising** — CAP Code, ASA | marketing pages, articles, social — **and affiliate publisher content Fynla does not write but pays for the results of** | **Unmapped.** §3 binds copy we write; it does not reach copy we incentivise. |
| **Accessibility** — Equality Act 2010 | web, `/m` and native iOS clients | **Unmapped.** `07-quality-bar.md` has no accessibility clause either (verified 2026-08-21). |
| **Payment services** — PSRs 2017, e-money | payments taken through Revolut; Fynla holds no client funds | **Believed out of scope. Unverified** — recorded so nobody re-derives it. |
| **Money laundering** — MLR 2017 | no regulated activity, no client money, no transaction execution | **Believed out of scope. Unverified.** |

**"Unmapped" is a status, not an accusation.** Most of these will need little. What
matters is that the silence is *visible* rather than discovered by an agent finding
nothing.

**Commencement is the repeat failure mode in this codebase's legal citations. Check
it before you cite anything here.** Three rows already carry a live commencement
problem, each found separately rather than by a check:

- **PECR reg 6 was substituted on 5 February 2026** by the Data (Use and Access) Act
  2025, which made W-0050's own citation stale
  (`ops/reports/2026-08-21-W-0050-consent-validity-ruling.md`).
- **Mental Capacity Act 2005 Sch 1 carries pending Powers of Attorney Act 2023
  amendments, not in force as at 20 August 2026**
  (`ops/reports/2026-08-21-W-0100-lpa-perimeter-review.md`).
- **DMCC Act 2024 Part 4 Chapter 2's commencement could not be pinned at all** — see
  the consumer-protection row below.

A statute named in this map may not be the statute in force. §7.2 requires a **dated
source register**; one now exists at `registry/sources.md` (created 2026-08-21 under
`ops/gaps/G-0003`), and it carries the standing commencement warnings. These three were
each caught one at a time because it did not. **It is scoped to what has been read so
far — an absence there is not clearance.**

Commencement is not the only way a source moves. `registry/sources.md` §2 classifies
how each kind goes stale and what to watch — **including the case where the value is not
in the provision you would cite, so re-reading that provision finds nothing wrong.** Read
it before adding a citation anywhere; it is not restated here.

### 1.2 The rows, expanded

**What follows is product fact and file reference only.** No row says what any
regime requires — that is §7.3's boundary, and it holds here with full force.

**Financial services — FSMA, FCA Handbook. Mapped.**
Guidance across all seven modules, with Fyn reasoning over the user's own figures.
Enforced by `app/Services/AI/Prompts/ComplianceRules.php` (the seven rules,
canonical) and `FcaProcessInstructions.php`; evidenced by `AiAdviceLog` and the
six-year `fyn:episodic:purge` retention. **This is the only regime the file is
written against** — §1–§3 posture, §5 positions, §6.1–6.4 questions, §7 review.

**Data protection — UK GDPR, DPA 2018. Partially mapped.**
Holds both spouses' entire financial position; retirement and protection modelling
reads smoking status (`app/Services/Retirement/DecumulationPlanner.php`,
`app/Services/Estate/LifeCoverCalculator.php`), which is what §6.5 asks about.
Built and live: `app/Http/Controllers/Api/GDPRController.php` — export
(`requestExport`, `downloadExport`), a verified erasure flow (`initiateErasure`,
`verifyErasure`, `executeErasure`), consent history (`getConsentHistory`);
`App\Models\UserConsent` with **six** consent types (`terms`, `privacy`,
`marketing`, `data_processing`, `ai_chat`, `cookies`); `fyn:user:erase`.
**Covered here:** erasure and retention (§2) and the Article 9 question (§6.5).
**Not covered here:** lawful basis, consent validity, subject rights other than
erasure, transfers, and retention outside the episodic store.

**Privacy in electronic communications — PECR. Unmapped.**
A binary accept-or-decline banner writes `cookie_consent` to `localStorage` and, on
accept, loads Google Analytics and the Awin MasterTag
(`resources/js/utils/cookieConsent.js`; `public/pages/js/cookie-consent.js` on the
server-rendered pages). The Awin click reference cookie `awc` is written by
`CaptureAwcCookie` (`app/Http/Kernel.php:106`) for `awin.cookie_lifetime_days`
(default 365); it is gated on `config('awin.enabled')` and does not read the
banner's state. W-0050 records that registration cannot be completed without
accepting. **This file says nothing about cookies, tracking, consent validity or
marketing** — a grep of it for those terms returns only §7.2's source list.

**Legal services — Legal Services Act 2007. Unmapped.**
**Three** legal instruments, not one.
Wills — `app/Services/Estate/WillDocumentService.php`,
`resources/js/utils/willDocumentRenderer.js` (W-0019, W-0024).
Lasting powers of attorney — the `Route::prefix('lpa')` group in `routes/api.php`, behind the same `estate.full`
gate, `app/Services/Estate/LpaService.php` including `markAsRegistered()`,
`LpaComplianceService::checkCompliance()`, `resources/js/utils/lpaDocumentRenderer.js`
(W-0100).
Trusts — `app/Http/Controllers/Api/Estate/TrustController.php`.
**This file says nothing about any of them.** The Mental Capacity Act 2005 is a
further statute the trunk does not cite at all
(`ops/reports/2026-08-21-W-0100-lpa-perimeter-review.md`).

**Consumer protection — DMCC Act 2024 Part 4. Unmapped.**
One paid upgrade over a permanently free tier, with a cancellation endpoint
(`POST /api/payment/cancel-subscription`, in `routes/api.php`); capabilities withdrawn behind
`app/Http/Middleware/EnsureFullEstateAccess.php:37-39`, which returns 403 with
`required_tier: premium`; pricing served publicly by `PricingConfigController`; and
claims made to acquire customers.
**Chapter 2 covers subscription contracts and its commencement could not be
pinned.** Provisions of the Act commenced on 6 April 2025, 1 January 2026 and
6 April 2026 under successive commencement regulations, but no commencement date for
Chapter 2 was confirmed and the material behind that is search-derived rather than
read from the instrument. **Verify before relying on this row's timing.** The row
stands regardless — a paid subscription engages consumer-protection law whichever
provisions are live.

**Advertising — CAP Code, ASA. Unmapped in the part that matters.**
Marketing and landing pages, the article pipeline
(`app/Models/Insights/InsightArticle.php`, `app/Models/Pipeline/PipelineArticle.php`),
marketing and transactional email, social — **and affiliate publisher content Fynla
does not write but pays for the results of** (`config/awin.php`, live on production).
**§3 binds all outbound communication Fynla writes**, support replies included. It
does not reach copy Fynla incentivises but does not author, and nothing else here
does either.

**Accessibility — Equality Act 2010. Unmapped.**
Three clients, all shipping: `resources/js/` (web), `resources/mobile/` (`/m`),
`ios-native/` (SwiftUI). **Neither this file nor `07-quality-bar.md` contains an
accessibility clause** — verified 2026-08-21 by grepping both for accessibility,
WCAG and ARIA, which returns nothing. **Where the clause should live is open and is
not the Archivist's to settle** (`ops/gaps/G-0002`). The map row stays here because
here is where the silence is visible.

**Payment services — PSRs 2017, e-money. Believed out of scope. Unverified.**
Card payments are processed by Revolut (`config/services.php:60`); Fynla holds no
client funds and executes no transactions. **This is a belief, recorded so nobody
re-derives it, and it has not been verified.** Do not rely on it. An omitted regime
looks considered-and-excluded, which is exactly the invisibility the map exists to
fix — hence the row.

**Money laundering — MLR 2017. Believed out of scope. Unverified.**
Rests on the same facts plus §1's guidance-only, fail-closed posture: no regulated
activity, no client money, no transaction execution. **Same status, same warning.**

### 1.3 What to do when you land on an unmapped regime

**You may not invent doctrine at the point of use.** That is what this table exists
to stop (`index.md` rule 8).

1. **Stop and say so.** *"This regime is unmapped"* is a complete and acceptable
   finding (`07-quality-bar.md` — name every gap).
2. **Apply what you can from the mapped regimes**, and say plainly which you applied
   and which you could not reach.
3. **Do not reason across.** A financial-services instinct is not evidence about a
   legal-services or data-protection question — different bodies of law, different
   tests. The most likely error is reaching for the disclaimer you know rather than
   the one that fits.
4. **Write the §6 question, not the answer**, with the product fact that raised it
   attached.
5. **§7.3 applies with full force.** An unmapped regime is exactly where a confident
   answer does the most damage, because there is nothing for a reader to check it
   against.

**A ruling made against an unmapped regime is provisional on its face and must say
so.**

**The failure mode, in one worked example.** On W-0019 the refusal copy needed a
disclaimer. The trunk offered FCA-authorisation language, because that is the only
regime it knows. A will is a legal instrument, so the reviewing agent ruled the right
disclaimer was *"this tool doesn't provide legal advice"*. **That ruling was correct
and should not have been made** — there was no clause to apply, so doctrine was
authored at the point of use. The map turns that moment into a one-line finding:
*legal services is unmapped; here is the question*. The second example, W-0050's
stale PECR citation, is in the proposal report §4.

**The map worked before it was installed.** Grepping the routes to anchor these rows
turned up **lasting powers of attorney** (`routes/api.php:977`) — a second legal
instrument behind the same gate as the will builder, which two rulings about wills
had not reached. That became W-0100.

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

**These six are not all of them, and this section is no longer where they live.**
`ops/open-questions.md` is **canonical** and holds **sixteen**, `Q-01` to `Q-16`; the ten
beyond these six were raised in reports and rulings and never reached the trunk. The six
below are `Q-01` to `Q-06` in that order, restated here only so this file stays readable —
the same arrangement §2 has with `ComplianceRules.php`. **Where the two differ, the
consolidated file wins.**

**Numbering comes from there, never from here.** IDs are permanent and never reused; the
next is **`Q-17`**, taken from the bottom of that file. **Do not number from this section**
— counting the six below and continuing from seven is exactly how `Q-09` came to be raised
twice by two agents who could not see each other's work.

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
Ranking, blocking status and the product facts behind each question are in
`ops/open-questions.md`, not here.

**Proposed and not applied — CSJ's:** that this section become a pointer only, with the
six removed. **Not done, because it deletes ratified content and because seven citations
inside this file depend on the numbering** (§6.1, §6.4, §6.5, §6.1–6.4), as do
`registry/meetings.md` and `registry/sources.md`. `ops/open-questions.md` §2.2 already
describes §6 as a pointer; **until CSJ rules, it is not one, and this paragraph is the
difference.**

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

Maintained as a dated source register (`registry/sources.md`). A citation without a date
is not a citation.

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
