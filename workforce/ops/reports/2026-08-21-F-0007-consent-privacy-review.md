# F-0007 — the privacy half of the analytics and affiliate consent rebuild

**Agent:** compliance-lead · **Date:** 2026-08-21
**Reviewing:** `workforce/branches/fixes/F-0007-batch-f-analytics-consent.md` (W-0047, W-0049)
**Also:** populates the PECR rows in `core/registry/sources.md` (A11, A12, standing warning W4)

> ## Not an approval, and narrower than it may look.
>
> `05-perimeter.md` §7.3. **I have not ruled Fynla's cookie consent valid or invalid**, and
> this task did not ask me to. **PECR is `Unmapped`** (§1.1), so §1.3 governs and
> **everything here is provisional on its face.**
>
> **The cookie wall is out of scope and I have not reopened it.** CSJ parked W-0050 this
> morning — Accept gives consent and registration proceeds, and the "freely given" question
> waits for the functional board. Nothing below reopens it. Where a finding of mine touches
> the banner's *content*, I route it to W-0050 rather than ruling on it.
>
> What I reviewed is narrower and answerable: **does the mechanism that was just built do
> what it claims, and can the evidence it creates answer the questions that may later be
> asked of it.**

---

## 0. The headline, because two findings are cheap now and expensive after a deploy

Nothing is committed or deployed. Both of these cost little today.

1. **The fix correctly refuses to conflate cookie consent with email-marketing consent, and
   then reproduces exactly that conflation one level down, inside `TYPE_COOKIES`.** One
   record covers analytics **and** affiliate tracking, and cannot say which the visitor
   agreed to. **W-0049's own dispatch says historic consent is not repairable
   retrospectively** — this is about to create a fresh corpus of records with that same
   irreparability built in. §2.
2. **The fix built a working withdrawal mechanism with no route to it.** There is **no
   user-reachable way to withdraw cookie consent after accepting** — on web, on `/m`, or on
   the funnel. Before this batch that did not matter, because Decline did nothing anyway.
   **Now Decline is mechanically meaningful**, so the gap is new and it is this fix's. §4.

**Neither is a reason to hold the batch.** Both are additive and neither requires
re-litigating a decision already taken.

---

## 1. Commencement first — and PECR failed again, differently

**The standing warning applies here and it earned its place twice in one day.**

**PECR reg 6 was not amended on 5 February 2026. It was substituted in its entirety**, by
the Data (Use and Access) Act 2025 (c. 18) ss. 112(2) and 142(1), commenced by
**S.I. 2026/82 reg 2(w)**. Read on `legislation.gov.uk`, 2026-08-21, latest available
(revised). It now reads, in full:

> *"6.—(1) Subject to Schedule A1, a person must not store information, or gain access to
> information stored, in the terminal equipment of a subscriber or user. (2) In paragraph
> (1) and Schedule A1— (a) a reference … includes a reference to instigating the storage or
> access, and (b) … includes a reference to collecting or monitoring information
> automatically emitted by the terminal equipment."*

**Two paragraphs. That is the whole regulation.** Every exception now lives in **Schedule
A1**, inserted the same day by s.142(1) and Schedule 12, and **which did not exist before**.

### Why this is worse than the ordinary staleness the register was built for

`ops/reports/2026-08-21-W-0050-consent-validity-ruling.md` cites **reg 6(4)**.
**Reg 6 has no paragraph (4).** That is not a citation whose meaning has drifted — it is a
citation that **no longer resolves against the provision it names**. A reader checking it
finds nothing at all.

**Recorded as standing warning W4 in the register.** This is register maintenance, **not a
re-ruling of W-0050**, whose substance is parked.

---

## 2. Question 1 — does the `cookies`-not-`marketing` reasoning hold?

**Yes. The reasoning holds and the structure of the law supports it more strongly than the
agent had grounds to know.** And it stops one level short of where the same argument leads.

### The part that is right

`TYPE_COOKIES` governs **storing information in, or gaining access to information stored
in, terminal equipment** — which is what reg 6 is *about*, on its face. Email marketing
consent is not that; whatever governs it, reg 6 is not it. **One button standing for both
would make a single record the evidence for two different things**, and the agent's
instinct — *"one button must not stand for two lawful bases"* — is sound. Ruled **within
competence**: this is a statement about what the two decisions are, not about what the law
requires of Fynla.

### The part that stops short — and this is the finding

**Schedule A1 does not have one exception. It has several, and they are not the same
shape:**

- **para 2 — consent**, which *"may be signified through browser controls or other
  applications"*;
- **para 5 — statistical purposes**: permitted where the **sole purpose** is collecting
  information about how a service or website is used **to enable improvements**, subject to
  the user receiving clear information and having **a simple means to object at no cost**;
- **para 6 — appearance and functionality**: adapting to user preferences or enhancing
  appearance or functionality, on the same information-and-objection terms.

**Fynla's single `cookies` decision covers two materially different activities** — Google
Analytics measurement, and Awin affiliate click attribution. Those are not obviously the
same paragraph of Schedule A1, and para 5's structure is **objection**, not consent, which
is a different mechanism from the one that was built.

**What I am NOT saying, explicitly:**

- **I am not saying para 5 applies to Fynla's analytics.** "Sole purpose … to enable
  improvements" is a determination about Fynla's actual purposes, and analytics data going
  to a third party is exactly where that determination gets difficult. **§7.3. Not mine.**
- **I am not saying consent can be dropped for anything.** Nothing here supports that and
  it must not be read as support for it.
- **I am not saying the current single button is invalid.** That is W-0050 territory and it
  is parked.

### What IS within competence, and it is the actionable half

**A `cookies / v1.0 / consented = true` row cannot say what the visitor agreed to**, because
they were never asked separately. If analytics and affiliate tracking are ever separated —
for any reason, legal or product — **the rows already written cannot be re-read to answer
the new question.** They record an answer to a question that no longer exists.

**That is a records-design finding, not a legal one**, and it is the same lesson W-0049 was
dispatched with: *"Historic consent is not repairable retrospectively."* The batch honoured
that instruction about the past and is about to recreate the condition going forward.

**The asymmetry is what makes it worth acting on now.** Granularity added before anything is
committed costs a column or a second row. Granularity added after a corpus exists cannot be
backfilled — by the batch's own principle, which forbids fabricating records.

### Recommendation — proportionate, and it does not touch the parked decision

**Do not change the button.** The banner is one accept/decline and its wording is W-0050's.

**Change what the click records.** Either:

- **(a) two consent types** — `cookies_analytics` and `cookies_affiliate` — both written
  from the one click; or
- **(b) keep one type and make `version` carry the scope**, so `v1.0` is a statement of
  *what was asked* and not merely a number.

**(a) is the stronger and I recommend it**, because it survives the two being separated
later without any interpretation of old rows, and because the middleware already gates two
distinct behaviours (`gtag` loading, and the `awc` cookie) that would then map one-to-one
onto the record. **Two rows from one click is not a second write path** — it is the same
service, the same endpoint, the same moment, so Rule 20 is untouched.

**The banner-content implication is W-0050's, not mine.** If the two are ever asked
separately, that is a change to what the banner says, and W-0050 is parked. **(a) is
deliberately chosen so it does not require that** — the record can be granular while the
question stays single.

**§6 question written, not answered.** §6 below.

---

## 3. Question 2 — the `awc` field. **You were not wrong.**

**The agent's refusal was correct, and approving the alternative was correct.** Of the two
options actually available, the one taken is the more conservative.

**What happens now:** the visitor lands on `/?awc=X`. `CaptureAwcCookie` reads the decision
and, finding no acceptance, **sets nothing** (`app/Http/Middleware/CaptureAwcCookie.php:51`).
The banner carries the value in page memory and sends it with the acceptance; the server
writes the cookie at the moment of consent
(`app/Http/Controllers/Api/CookieConsentController.php`). On decline, `AwinClickCookie::forgetFrom`
expires it.

**Why the refusal was right.** Holding the value server-side pending consent would put an
affiliate click reference into Fynla's storage, against an identified visitor, **before**
they had answered. The chosen design keeps it in the visitor's own URL and page until the
moment they say yes. **The agent identified the right distinction and declined to work
around it, which is the behaviour to reinforce.**

**The "grants no new capability" argument is correct** — any visitor can already request
`/?awc=<value>` — and I note it is a **security** argument. It answers "can this be abused",
not "is the value processed before consent". Both answers happen to be satisfactory; they
are different questions and the note should not be read as covering the second.

### Two things to record rather than fix

**1. The value reaches Fynla's server regardless, and nobody should believe otherwise.**
`awc` arrives in the query string of the landing request and will appear in web server
access logs whatever the application does with it. **That is not a defect of this fix and
no design available here changes it** — but the branch document's framing ("nothing is
stored before consent") is true of the application and not of the whole system. Worth
stating so the next reader does not over-rely on it.

**2. The consent record does not evidence what was captured at that moment.** The endpoint
takes `awc` from the request body without tying it to the URL the visitor is on — correctly,
per the agent's reasoning — but the resulting `cookies` row says only that consent was given.
It does not record that an affiliate reference was captured under it. **Same theme as §2**,
and if §2's recommendation (a) is taken this becomes nearly free.

**Neither blocks. Neither is a criticism of the decision you approved.**

---

## 4. What the mechanism does not do — and this one is new

**There is no user-reachable way to withdraw cookie consent after accepting.** Verified by
reading, on all three surfaces:

- **The banner is the only control, and it only appears when there is no decision.**
  `resources/js/components/Shared/CookieBanner.vue:72` renders from `getConsentStatus()`;
  once `fyn_cookie_consent` is set, the banner is gone.
- **`cookies` is deliberately excluded from `PUT /api/auth/gdpr/consents`**
  (`app/Http/Controllers/Api/GDPRController.php:71`). **That exclusion is correct** and I
  am not asking for it to be reversed — a record written there alone would be a preference
  the middleware never reads, which is the defect W-0049 fixed.
- **No cookie-settings control exists anywhere.** Grepped `resources/js`,
  `resources/mobile` and `public/pages/js`: every hit is the banner, the utility, the
  router guard, `Register.vue`, or tests. There is no screen a user can return to.
- **`getConsentHistory` does not filter `cookies`** (`:338-354`), so an authenticated user
  can **see** the decision they cannot **change**. Visible and irreversible is a worse
  combination than invisible.

**Why this is new, and why it belongs to this batch rather than to W-0050.** Before F-0007,
Decline did nothing — `awc` was HttpOnly and set on every visitor regardless, so the absence
of a withdrawal route changed no outcome. **F-0007 made Decline mechanically meaningful**:
it now expires the affiliate cookie and stops `gtag` loading. So the batch built a real
withdrawal capability and shipped **no interface that can reach it.** The only route left is
the visitor clearing their own browser cookies, which is them working around Fynla rather
than Fynla providing withdrawal.

**Whether that matters legally is a §6 question and I have not answered it.** As a product
observation it stands without any legal premise: **the system has a state no interface can
move a user into.**

**The fix is small and stays inside Rule 20.** Give the privacy screen a control that calls
`POST /api/cookie-consent` — the existing one home, one write path, one service. **Do not
re-open the GDPR PUT.**

**Rule 19:** `/m` has no banner of its own and reaches this through the funnel iframe, so it
inherits both the mechanism and the gap. Any control added must reach `/m`, not just web.

---

## 5. Where I agree with the batch, on the record

Stated because a review that only lists problems misrepresents the work.

- **Anonymous subject on `user_consents` rather than a second store** is right, and the
  reasoning — one store, one versioning model, one history, one withdrawal path — is the
  reasoning I would have given.
- **Claiming at registration rather than re-recording**, so the row keeps the moment consent
  was actually given, is the correct instinct about evidence, and **leaving a row unclaimed
  rather than deleting it when the user already holds the same type and version** is
  precisely right: no evidence is destroyed.
- **The subject token being server-issued and never taken from a request body** is the right
  shape.
- **Absence of a decision is not consent** (`CookieConsentService::allowsTracking`) — the
  default is refusal, which is the fail-closed posture §1 requires of the rest of the system.
- **Expire-on-decline and on any later unaccepted request** is what actually clears the
  365-day cookies already on production visitors, and it is server-side because it has to be.
- **Finding the third independent copy in the server-rendered funnel and converging it** was
  not in the item and was found by the prior-art check. That is Rule 20 done properly.
- **`AwinClickCookie` as one declaration of the cookie's attributes** is the detail that
  makes withdrawal actually work — a second copy of the attributes would produce a clear that
  silently fails to match.

---

## 6. §6 questions — written, not answered

Continuing the numbering from `2026-08-21-lpa-claims-rulings.md` §7 (which added 7–10).
**Not agent-answerable. I have not answered them.**

11. **Does Schedule A1 paragraph 5 (statistical purposes) reach Fynla's analytics, and if
    so what follows?** Para 5 requires the **sole purpose** to be collecting information
    about how the service is used **to enable improvements**, with clear information and a
    **simple means to object at no cost** — an objection mechanism, not a consent one.
    **Product fact:** the analytics is Google Analytics, so data reaches a third party; and
    Fynla's current mechanism asks for consent, which is a different paragraph and a
    different interaction.
12. **Does the affiliate click cookie (`awc`) sit under a different paragraph of Schedule A1
    from the analytics cookie?** **Product fact:** one accept/decline currently covers both,
    and one `user_consents` row records the result, so the two cannot be told apart in the
    evidence.
13. **Must withdrawal of cookie consent be reachable from within the product, and how
    easily?** **Product fact:** after accepting there is no control on web, `/m` or the
    funnel; `cookies` is excluded from the consent PUT by design; `getConsentHistory`
    displays the decision without offering any way to change it.
14. **Does the anonymous `subject_token` consent row become personal data, and from when?**
    **Product fact:** it is a server-issued 64-character token in an HttpOnly cookie, held
    for 365 days, unlinked to any account until `claimAnonymousConsents()` attaches it to a
    user at registration — at which point rows written before the account existed become
    attributable to a named person.

**Question 14 is mine, not the batch's** — it arises from the design being *better* than the
alternative, not worse. Recording it because the anonymous-then-claimed pattern is new to
this codebase and nothing else in the trunk describes it.

---

## Done

- Reviewed the privacy half of F-0007, which shipped without a compliance read.
- **Checked commencement first** and found PECR reg 6 **substituted, not amended**, on
  5 Feb 2026, with all exceptions moved into a **Schedule A1 that did not previously exist**.
- **Answered question 1:** the reasoning holds; Schedule A1 supports it more strongly than
  the agent knew; **and the same conflation is reproduced one level down inside
  `TYPE_COOKIES`.** Recommendation given, proportionate, not touching the parked decision.
- **Answered question 2:** the refusal was correct and approving the alternative was
  correct. Two things recorded rather than fixed.
- **Found the withdrawal gap** by reading all three surfaces, and established it is **this
  batch's and new**, because the batch is what made Decline mean anything.
- **Populated the PECR rows** in `core/registry/sources.md` — A11, A12 — and added standing
  warning **W4**, recording that a reg 6(4) citation no longer resolves.
- Wrote four §6 questions with product facts attached.
- Recorded what the batch got right, in detail, on the record.

## Not done, and why

- **The cookie wall / "freely given" question. Not reopened, by instruction.** CSJ parked it
  and nothing here depends on it.
- **No ruling that the consent is valid or invalid.** Not asked, and outside §7.3.
- **No determination that Schedule A1 para 5 applies to anything.** The temptation was real
  and it is exactly the §1.3 rule-3 move.
- **The banner's wording is untouched** — it is W-0050's, and my §2 recommendation is built
  so it does not need to change.
- **No code, no tests, no board status changes.** W-0047 and W-0049 remain `handoff` to
  quality-lead.
- **W-0050's ruling document not amended** despite its broken citation. Its substance is
  parked; the citation failure is recorded in the register instead. **Whether that report
  gets a correction note is team-lead's call, not mine.**
- **Did not read PECR reg 22 or the pre-2026 reg 6.** Neither was needed: §2's argument is
  made from what reg 6 *is about* on its face, not from a comparison.
- **Did not verify anything in a browser.** Reading only.

## Assumptions

- **That `legislation.gov.uk`'s substitution and commencement annotations are accurate as
  displayed on 2026-08-21.** I read them; I did not cross-check S.I. 2026/82 itself.
- **That Schedule A1's paragraphs 2, 5 and 6 are as summarised back to me.** Para 5 and 6
  I have in summary rather than full verbatim — **stated as a limit, because §2's
  recommendation rests on the paragraphs being structurally different, not on their exact
  words.** Anyone relying on para 5 specifically must read it in full first.
- That `config('awin.enabled')` is true on production, per the W-0049 dispatch. Not verified
  by me; no production access used or needed.

## Needs

- **Decision (team-lead):** §2's recommendation (a) — two consent types written from one
  click — before anything is committed. **This is the one that stops being possible later.**
- **Decision (team-lead):** whether the withdrawal control (§4) is added to this batch or
  raised as its own item. I hold no block.
- **Answer (team-lead):** whether W-0050's ruling document gets a correction note for its
  reg 6(4) citation.
- **Gate (CSJ), unchanged and still open:** extend §7.3 so the no-approval rule binds the
  product. Unrelated to this batch, still batched.

## Noticed — outside my remit, routed

- **archivist:** `05-perimeter.md` §1.1's PECR row describes the banner and `CaptureAwcCookie`
  as they were **before** F-0007, and cites *"a binary accept-or-decline banner writes
  `cookie_consent` to `localStorage`"* — `localStorage` is gone and the middleware is now
  consent-gated. **The row is out of date as of today. I am not proposing wording**; flagging
  that the fact changed under it.
- **quality-lead:** the evidence pack for W-0049 should cover the **decline** path on all
  three surfaces, not only accept. The batch's own tests do, but the batch is not the one
  that verifies itself.
- **intelligence-lead:** W-0047 means production analytics has been contaminated by
  development and test traffic for an unknown period. **Any historical figure derived from
  that property is affected**, and fixing the fallback does not repair the history.
