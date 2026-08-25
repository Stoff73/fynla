# Proposal — a regime map for `05-perimeter.md` §1

**Agent:** compliance-lead · **Date:** 2026-08-21
**Status:** **INSTALLED 2026-08-21** by the Archivist, at `05-perimeter.md` §1.1–1.3,
under CSJ's adoption ruling recorded at the foot of this report. Expanded on the same
ruling. See "Installation record" at the end for what was changed on the way in.
Superseded status: *Draft for CSJ. Not installed.*
**Origin:** recommended by me on W-0019 and again on W-0050, after hitting the same shape
twice; carried to CSJ by team-lead as my recommendation.

> **Naming a regime in this map asserts only that Fynla performs activities that regime is
> addressed to. It never asserts that the regime applies, or how.** That is the determination
> §7.3 forbids me, and the map is deliberately built so it does not require one — an entry
> reading "unmapped" is a statement about *this file's coverage*, not about the law.

---

## 1. The proposed text

Intended to **extend** §1, keeping its existing two paragraphs unchanged and adding the map
beneath them.

---

### §1. Status and regime map

*(existing §1 text on FCA status and fail-closed guidance mode stands unaltered, then:)*

**Fynla's activities touch more than one body of law. This file is written against the
financial-services regime and is largely silent on the others.** The table records which is
which. It maps *this file's coverage*, not the law: an entry asserts only that Fynla does
something the regime is addressed to.

| Regime | The Fynla activity that engages it | This file |
|---|---|---|
| **Financial services** — FSMA, FCA Handbook | guidance on pensions, investments, savings and tax; Fyn reasoning over the user's own figures | **Mapped** — §1–3, §5, §6.1–6.4, §7.2 |
| **Data protection** — UK GDPR, DPA 2018 | holds a household's entire financial position; infers health from smoking status | **Partially mapped** — erasure and retention only (§2), plus one Article 9 question (§6.5). Lawful basis, consent and subject rights are not covered. |
| **Privacy in electronic communications** — PECR, as amended by the Data (Use and Access) Act 2025 (in force 5 Feb 2026) | analytics and affiliate cookies; consent gathering | **Unmapped** — W-0050 |
| **Legal services** — Legal Services Act 2007 | generates wills, lasting powers of attorney and trust documents | **Unmapped** — W-0019, W-0024 |
| **Consumer protection** — DMCC Act 2024 Part 4 | a paid subscription with cancellation; withdrawing paid capabilities; claims made to acquire customers | **Unmapped** |
| **Advertising** — CAP Code, ASA | marketing pages, articles, social — **and affiliate publisher content Fynla does not write but pays for the results of** | **Unmapped.** §3 binds copy we write; it does not reach copy we incentivise. |
| **Accessibility** — Equality Act 2010 | web, `/m` and native iOS clients | **Unmapped.** `07-quality-bar.md` has no accessibility clause either. |
| **Payment services** — PSRs 2017, e-money | payments taken through Revolut; Fynla holds no client funds | **Believed out of scope. Unverified** — recorded so nobody re-derives it. |
| **Money laundering** — MLR 2017 | no regulated activity, no client money, no transaction execution | **Believed out of scope. Unverified.** |

**"Unmapped" is a status, not an accusation.** Most of these will need little. What matters is
that the silence is *visible* rather than discovered by an agent finding nothing.

#### What to do when you land on an unmapped regime

**You may not invent doctrine at the point of use.** That is what this table exists to stop.

1. **Stop and say so.** *"This regime is unmapped"* is a complete and acceptable finding
   (`index.md` rule 8; `07-quality-bar.md` — name every gap).
2. **Apply what you can from the mapped regimes**, and say plainly which you applied and which
   you could not reach.
3. **Do not reason across.** A financial-services instinct is not evidence about a
   legal-services or data-protection question — different bodies of law, different tests. The
   most likely error is reaching for the disclaimer you know rather than the one that fits.
4. **Write the §6 question, not the answer**, with the product fact that raised it attached.
5. **§7.3 applies with full force.** An unmapped regime is exactly where a confident answer
   does the most damage, because there is nothing for a reader to check it against.

**A ruling made against an unmapped regime is provisional on its face and must say so.**

*(end of proposed text)*

---

## 2. Why each row is there

Every row is anchored to something the product verifiably does. I have not listed a regime
because it seemed plausible for a fintech.

| Row | Product fact | Evidence |
|---|---|---|
| Financial services | the whole product | trunk §1–§3; `ComplianceRules.php` |
| Data protection | consent, export, erasure, consent history all built and live | `GDPRController` — `requestExport`, `downloadExport`, `requestErasure`, `getConsentHistory`, plus a verified-erasure flow; `UserConsent` with five consent types |
| PECR | analytics and affiliate cookies; `awc` set for 365 days | `cookieConsent.js`, `CaptureAwcCookie` (`Kernel.php:106`) — W-0050 |
| Legal services | **three** legal instruments, not one | wills (`WillDocumentService`), **lasting powers of attorney** (`routes/api.php:977`), trusts (`TrustController`) |
| Consumer protection | paid subscription, cancellation endpoint, capability withdrawal | `routes/api.php:1151` `cancel-subscription`; `EnsureFullEstateAccess` 403 `required_tier: premium`; W-0019 withdraws a paid capability |
| Advertising | affiliate acquisition is live on production | `AWIN_ENABLED` true on fynla.org (confirmed 2026-08-21); merchant 126105 |
| Accessibility | three clients | `resources/js/`, `resources/mobile/`, `ios-native/` |
| Payment services | Revolut is the processor | `config/services.php:60` |
| Money laundering | no regulated activity | trunk §1 — guidance only, fail-closed |

**The legal-services row grew while I was writing this.** W-0019 and W-0024 were about wills.
Checking the routes for this map turned up **lasting powers of attorney** as well
(`routes/api.php:977`), sitting behind the same `estate.full` gate. An LPA is a registered
instrument with its own statutory regime, and nothing in the trunk reaches it either. **That
is the map working before it has been adopted** — one grep found a second unmapped instrument
that two rulings had not.

## 3. What I could not settle, and where I stopped

- **The two "believed out of scope" rows are beliefs, and labelled as such.** They rest on
  Fynla holding no client funds and executing no transactions. I think recording a belief with
  its status attached is more useful than omitting the row — an omitted regime looks
  considered-and-excluded, which is exactly the invisibility the map exists to fix. But I have
  not verified either, and neither should be relied on.
- **DMCC Act 2024 commencement is staged and I could not pin the relevant date.** Part 4
  Chapter 2 covers subscription contracts. Provisions of the Act commenced on 6 April 2025,
  1 January 2026 and 6 April 2026 under successive commencement regulations
  (SI 2025/272, SI 2026/284), but **I could not confirm a commencement date for Chapter 2** and
  the material I found is search-derived rather than read from the instrument. **Verify before
  relying on that row's timing.** The row stands regardless — a paid subscription engages
  consumer-protection law whichever provisions are live.
- **I have not tried to say what any unmapped regime requires.** The map deliberately does not
  need that, which is why I think it is a safe thing for an agent to draft.
- **Accessibility may belong in `07-quality-bar.md` rather than here.** I have put it in the
  map because that is where the silence is visible; where the *clause* eventually lives is
  CSJ's call, and possibly the Archivist's.

## 4. The failure mode this prevents — including mine

Two worked examples, one of them my own error.

**W-0019.** The refusal copy needed a disclaimer. The trunk offered FCA-authorisation
language, because that is the only regime it knows. A will is a legal instrument, so I ruled
the right disclaimer was *"this tool doesn't provide legal advice"*. **That ruling was
correct and I should not have made it** — there was no clause to apply, so I authored doctrine
at the point of use, which `index.md` rule 8 forbids. The map turns that moment into a
one-line finding: *legal services is unmapped; here is the question*.

**W-0050.** The board item cited PECR reg 6(4) — a provision substituted out of existence on
5 February 2026. Nobody caught it because nothing in the trunk pointed at PECR at all, so
there was no source register to check the citation against. The map's PECR row, with §7.2's
sources behind it, is what makes that a routine check instead of a lucky one.

**The pattern in both:** the failure was never a wrong answer. It was **not knowing that the
question was outside what the file covers.** That is invisible by construction — an agent
that finds nothing concludes there is nothing to find. A map is the cheapest thing that makes
absence legible.

---

## Done

- Drafted the §1 regime map as text CSJ could adopt or edit — nine regimes, each anchored to a
  verified product fact, each marked mapped, partially mapped, unmapped, or believed out of
  scope.
- Drafted the five-step obligation for landing on an unmapped regime, with the rule that a
  ruling made there is provisional on its face.
- Found a third unmapped legal instrument while writing it — **lasting powers of attorney**
  (`routes/api.php:977`), which neither W-0019 nor W-0024 had reached.
- Kept it to one table and one short list, per the brief that a map nobody opens is another
  unmapped regime.

## Not done, and why

- **Not installed.** Draft only; `05-perimeter.md` is untouched. Amendments are CSJ's.
- **No regime's requirements stated.** The map records coverage, never content — which is what
  makes it safe for me to have written.
- **DMCC Act Chapter 2 commencement unconfirmed**, and my sources for it are search-derived.
  Flagged in §3 rather than presented as settled.
- **The two "out of scope" rows are unverified beliefs**, labelled as such rather than dropped.
- **Did not amend `07-quality-bar.md`** on accessibility, though the clause may belong there.
  Not my file and not my call.

## Assumptions

- That §1 is the right home. It is where status already lives, and the map is a statement of
  status. **If CSJ would rather it were a new §8, nothing in the drafting depends on the
  number.**
- That listing a regime cannot itself create an exposure. I believe recording "we do X, and
  this file does not cover X" is straightforwardly better than silence, but it is a judgement
  and CSJ may weigh it differently.
- That the nine rows are the ones worth listing. **This is the entry I am least confident
  about** — it is bounded by what I have had cause to look at. A tenth regime nobody has hit
  yet would be invisible to me for exactly the reason the map exists.

## Needs

- **Gate (CSJ):** adopt, edit, or decline. If adopted, the §6 questions from the W-0019 and
  W-0050 reports should land alongside it — the map points at them.
- **Review (Archivist):** where the accessibility clause belongs, and whether a map in §1
  creates a linkage obligation for branch documents that cite it.

## Noticed — outside my remit, routed

- **build-lead / product-lead:** lasting powers of attorney (`routes/api.php:977`) sit behind
  the same `estate.full` gate as the will builder and generate a registrable legal instrument.
  **They have had no equivalent of W-0019's review.** I am not asserting a defect — I have not
  looked at the generator. I am saying nobody has, and W-0024 is what an unreviewed document
  generator looked like.
- **chief-of-staff:** this is the third artefact in two days to carry its own inline source
  register because `workforce/` has none. The workaround is now the habit.

---

## CSJ DECISION — 2026-08-21: **ADOPTED, and to be expanded properly**

CSJ: *"agree with your proposal for number 5, we do need to expand this properly."*

**Status changes from "Draft for CSJ. Not installed." to APPROVED FOR INSTALLATION.**
The gate at `05-perimeter.md:4` (amendments are CSJ's) is satisfied by this ruling.

Two things are authorised, not one:

1. **Install the §1 regime map as proposed** — the existing two paragraphs of §1 stand
   unaltered, the map is added beneath them, and the "what to do when you land on an
   unmapped regime" procedure comes with it. The guard clause is load-bearing and must
   survive verbatim: naming a regime asserts only that Fynla performs activities that
   regime is addressed to, never that the regime applies or how — that determination is
   what §7.3 forbids.

2. **Expand it properly.** CSJ's wording is explicit that the table as drafted is a
   starting point, not the finished article. Expansion means: each row gets enough
   substance that an agent landing on it knows what it is standing on, rather than only
   that the ground is unmapped. It does **not** mean ruling on what any regime requires —
   that remains forbidden by §7.3 and is not what was approved.

Carried forward unchanged from the draft, and still true after adoption:
- The two "believed out of scope" rows (payment services, money laundering) remain
  **unverified beliefs**, labelled as such. Adoption does not convert them into findings.
- **DMCC Act 2024 Chapter 2 commencement could not be pinned** and the material was
  search-derived rather than read from the instrument. Verify before relying on that
  row's timing. The row stands regardless — a paid subscription engages consumer
  protection law whichever provisions are live.
- Where the accessibility **clause** eventually lives (here or `07-quality-bar.md`) is
  still open; the map row stays here either way, because here is where the silence is
  visible.

---

## INSTALLATION RECORD — Archivist, 2026-08-21

Installed at `core/constitution/05-perimeter.md` **§1.1 (the map)**, **§1.2 (the rows,
expanded)** and **§1.3 (the procedure)**. §1's existing two paragraphs on FCA status and
fail-closed guidance mode stand **unaltered**; only the heading changed, from
"1. Status" to "1. Status and regime map".

**The guard clause survives verbatim** as a blockquote at the head of §1.1. So do the
nine table rows, the "unmapped is a status, not an accusation" line, the five-step
procedure, and the provisional-on-its-face rule.

### What was expanded, and on what evidence

Every expansion is a **product fact or a file reference, verified in the working tree on
2026-08-21**. No row states what any regime requires — §7.3 holds throughout.

| Row | Added |
|---|---|
| Financial services | the enforcing files by name: `ComplianceRules.php`, `FcaProcessInstructions.php`, `AiAdviceLog`, `fyn:episodic:purge` |
| Data protection | the specific `GDPRController` methods, the **six** `UserConsent` types, `fyn:user:erase`, the two services that read smoking status, and an explicit covered / not-covered split |
| PECR | the banner's actual mechanism (binary accept-or-decline, `localStorage`, GA + Awin MasterTag on accept), both banner implementations, and that `CaptureAwcCookie` is gated on `config('awin.enabled')` rather than on the banner's state |
| Legal services | all three instruments with their services and renderers, `markAsRegistered()`, `LpaComplianceService::checkCompliance()`, and the MCA 2005 as an uncited statute |
| Consumer protection | the corrected cancellation route, the 403 `required_tier` line reference, `PricingConfigController`, and the commencement caveat carried in full |
| Advertising | the article-pipeline models, and the incentivised-not-authored boundary stated against §3 |
| Accessibility | the three client directories, the verified double silence, and a pointer to `G-0002` |
| Payment services | `config/services.php:60`, and the reasoning for keeping an unverified row rather than omitting it |
| Money laundering | the facts the belief rests on, tied to §1's posture |

### Two mechanical corrections made on the way in

Both are verifiable facts, corrected autonomously under `00-precedence.md` §2.1. **The
report body above is left as written** — it is a dated record — so the corrections are
recorded here rather than edited into it.

- **`routes/api.php:1151` for `cancel-subscription` is wrong.** The route is at
  **`routes/api.php:1155`**. Line 1151 is `billing-history`. The installed text uses 1155.
- **"`UserConsent` with five consent types" undercounts.** There are **six**:
  `terms`, `privacy`, `marketing`, `data_processing`, `ai_chat`, `cookies`
  (`app/Models/UserConsent.php:16-32`). `TYPE_COOKIES` carries a docblock noting it may
  be keyed to a `subject_token` rather than a `user_id`, since it is given before an
  account exists. The installed text says six and names them.

`routes/api.php:977` was checked and is **correct** — it is exactly the `lpa` prefix
group. The installed text cites `977-986`, the full group.

### Carried forward unchanged, as CSJ directed

- Both "believed out of scope" rows keep their **unverified** label, with an explicit
  "do not rely on it" in the expanded text. Adoption did not convert them into findings.
- The **DMCC Chapter 2 commencement caveat** travels with its row, including that the
  material was search-derived rather than read from the instrument.
- The **W-0019 worked example** is installed in §1.3 in compressed form, with a pointer
  to §4 of this report for W-0050's stale-citation example.

### Added beyond the draft

- **A standing commencement warning in §1.1**, placed immediately after the table so a
  reader citing any statute hits it first. It names all three live instances — PECR
  reg 6, MCA Sch 1, DMCC Chapter 2 — and points at `G-0003`. CSJ's instruction was to
  say this once, where a reader will hit it.
- **W-0100 added to the legal-services row's board references**, alongside W-0019 and
  W-0024. The row's own draft text had found the LPA surface but the table still cited
  only the two will items.

### Deliberately not done

- **No §6 question was added.** The proposal's "Needs" asked that the §6 questions from
  the W-0019 and W-0050 reports land alongside the map. **That is a separate amendment
  to a gated section and CSJ has not ruled on it** — the ruling authorised installing
  and expanding the map. Outstanding for CSJ.
- **No regime's requirements stated anywhere**, including the accessibility row where a
  standard would have been easy to write and would have been doctrine invented at the
  point of use.
- **`07-quality-bar.md` untouched.** Where the accessibility clause lives is recorded as
  `G-0002`, not resolved.
- **`00-precedence.md` untouched.** Its §2.7 size-budget table is a dated measurement
  from 2026-08-13; adding the new figure would be an amendment to a CSJ-gated file, not
  a correction. See the size note below.

### Size — a finding for the review, not a fault

`05-perimeter.md` was **8,329 characters** against an 8,000 budget. It is now
**19,758** — roughly **2.5x budget**, and now the largest file in `core/`, ahead of
`charter.md` at 15,021.

Under `00-precedence.md` §2.4 budgets are advisory and crossing one **triggers a review,
not a cut**, so nothing is blocked. Flagged because bloat is invisible without a number,
and because the map's own premise is that a map nobody opens is another unmapped regime.

**Option for CSJ, not taken:** §1.2 could move to a sibling file referenced from §1.1,
leaving the table and procedure in the trunk file. That is a structural change to a
ratified document and was not what was approved, so it is offered rather than done.
