# W-0050 — cookie wall: consent validity ruling and delta

**Agent:** compliance-lead · **Date:** 2026-08-21 · **Item:** W-0050
(related, not duplicated: W-0047 — hardcoded production GA id, build-lead's)
**Trunk under review:** `workforce/core/constitution/05-perimeter.md`
**Trigger:** W-0050 acceptance item 4 — *"`compliance-lead` rules on whether consent
already collected through this gate is valid, and what that means for analytics data
gathered on its basis."*

> **This report is not an approval, and it is not a legal opinion.** Perimeter §7.3 allows
> me two outcomes: *no issues found within competence*, or *flagged, with the reason and a
> dated source*. I may apply a written rule; I may never determine what the law requires.
> Below I set out the elements, the verified facts, and which way each cuts — and I stop
> short of the determination every time. Where I am uncertain I say so.

---

## UPDATE — 2026-08-21, later the same day

**Three facts arrived after this report was first written. Two of them change its
weighting; one closes a limb of W-0019.** Recorded here rather than silently edited in, so
the original assessment and what moved it are both visible.

### The finding with no remedy, stated first

On re-reading, the **Art 7(1) defect was third in a list and should not have been.** It is
the only defect here that **cannot be repaired**, so it belongs at the top:

> **Consent to analytics and affiliate tracking exists only as the string `'accepted'` in the
> visitor's `localStorage`. There is no server-side record of any kind
> (`cookieConsent.js:5, 24-26`; `grep -rn "cookie_consent" app/ database/migrations/ routes/`
> → no matches). Article 7(1) requires the controller to be *able to demonstrate* that the
> data subject consented. Fynla cannot do so for a single user, on any day since 2026-04-07.
> Every other defect in this report is fixable going forward. This one has no record to
> reconstruct from, so it cannot be cured retrospectively — only stopped.**

### `AWIN_ENABLED` is **true** on production — the affiliate limb is live, not latent

Read directly on the production server, read-only, with CSJ's authorisation (team-lead,
2026-08-21). The conditional throughout Q2 below **resolves to the live branch.** Re-weighted:

- `CaptureAwcCookie` is **live in the global middleware stack on fynla.org**
  (`app/Http/Kernel.php:106`, also on `origin/main`). Every visitor arriving with `?awc=`
  receives a **365-day `HttpOnly` cookie**, with **no consent check anywhere in the path**.
- `declineCookies()` cannot clear it. The cookie is `HttpOnly`, so client-side code cannot
  reach it. **Declining is not merely ineffective against Awin — it is structurally incapable
  of being effective**, and no change to the banner alone can make it so.
- `FireAwinConversionJob` fires server-to-server conversions on the same live switch, also
  unchecked.

**This changes the character of the finding, not just its probability.** It is no longer
"a wall that over-collects consent". It is a wall that **extracts consent for processing that
is already happening to everyone who arrives** — including the large majority of visitors who
never reach a registration form, never see the wall, and have therefore never been asked
anything at all. The disclosure gap at `CookieBanner.vue:16-17`, which names only analytics,
is live for every one of them.

**I re-rank this as the sharpest item in the report**, above the registration wall itself, on
the grounds that it affects strictly more people and that the affected group was never
presented with a choice in the first place.

### Production Google Analytics has **no measurement ID configured**

So the W-0047 fallback (`cookieConsent.js:6` → hardcoded `G-3Y8DL3QB09`) is the path that
resolves **in production as well**. This confirms rather than complicates the point made
under Q1: the live property is the only property, receiving traffic from every environment,
and **a deletion scope cannot cleanly separate environments inside it.** Anyone scoping a
deletion must know this before starting. Still W-0047's fix, not duplicated here.

### W-0019's exposure limb is closed — and it was timing, not control

The production will-document count came back **zero for real users**: all four mirror wills
belong to seeded preview personas, and across 49 real accounts nobody has used the will
builder. **The notification question I raised on W-0019 falls away — there is nothing to
remediate.** Recorded in both places.

**It should not be read as a control having worked.** No gate existed on production; the
count is zero because nobody used the feature, not because anything stopped them. The trunk
finding on W-0019 — that `05-perimeter.md` has no clause governing a tool that drafts legal
instruments — stands undiminished, and so does the fact that production still defaults
married users to a one-sided will.

---

## 0. Before anything else — the law under this item changed six months ago

> **SUPERSEDED as to the Schedule A1 paragraph numbers used in this section — see the
> `2026-08-21 compliance-lead` correction at the foot of this document ("every Schedule A1
> paragraph number in the row below is off by one").**
> **Strictly necessary is para 4, not para 3. Statistical purposes is para 5, not para 4.**
> Consent is para 2; para 1 is Interpretation.
> **Everything this section says about what those exceptions CONTAIN, and the element-by-element
> table below, is unaffected — only the numbers are wrong, and no conclusion here is
> re-opened.** Left as written; the section is the record of what was believed.

**The board item's regulatory citation is out of date, and so is most published commentary.**

W-0050 cites *"PECR reg 6(4)"* for the strictly-necessary exemption. **Regulation 6 of PECR
was substituted on 5 February 2026 by the Data (Use and Access) Act 2025**, and the
exemptions were moved out of regulation 6 into a **new Schedule A1**
(legislation.gov.uk, current version). Regulation 6(1) now reads: *"Subject to Schedule A1,
a person must not store information, or gain access to information stored, in the terminal
equipment of a subscriber or user."*

The board item's **conclusion survives; its citation does not.** The strictly-necessary
exception is now **Schedule A1 para 3** — technical storage or access *"strictly necessary
for the provision of an information society service requested by the subscriber or user"*,
which the Schedule expressly extends to *"maintaining authentication records"*. Session and
CSRF cookies sit squarely in that language. The tester was right that they need no consent;
the provision they need no consent under has a different number now.

**More importantly, Schedule A1 created an exception that did not previously exist.**
Para 4 permits storage or access to collect information *"for statistical purposes about how
the service is used with a view to making improvements"* — **provided the information is not
shared**, and provided users receive notice and *"a simple means of objecting, free of
charge."*

That is directly load-bearing for this item, because it is the first time UK law has offered
any route to analytics without consent. **Whether it reaches Google Analytics as Fynla
deploys it is a determination I cannot make.** What I can do is line the elements up against
the verified facts:

| Sch A1 para 4 element | Fynla's GA deployment |
|---|---|
| statistical purposes about service use, to make improvements | plausible on its face — not verified against any stated purpose, because none is documented |
| **information is not shared** | GA transmits to Google. `cookieConsent.js:79-88` loads `googletagmanager.com/gtag/js`. |
| notice **and a simple means of objecting, free of charge** | the only mechanism is the binary banner, and on `/register` objecting removes the service |

Two of the three elements look difficult on the facts. **I am not concluding that para 4 is
unavailable** — that is exactly the call §7.3 forbids me. I am recording that the question is
*newly* worth asking, that it did not exist when this code was written in April, and that it
is cheap for a lawyer to answer with this table attached.

**Flagged for the source register:** every ICO cookie page I could reach carries a note that
its guidance is **under review following the Data (Use and Access) Act**. Any ICO material
cited in this area, including by me below, should be treated as pre-dating the change unless
its date says otherwise.

---

## Q1 (PRIMARY) — Is the consent already collected through this gate valid, and what follows for the analytics gathered on it?

### The question
Not "is the wall lawful going forward" — that one is comparatively easy and the acceptance
criteria already fix it. The question CSJ asked is **retrospective**: consent has been
collected through this gate by every account created on production since it shipped. Is that
consent valid, and if it is not, what follows for the data collected in reliance on it?

### What the trunk says today
**Nothing.** See Q3 — there is no data-protection or consent clause in the trunk at all.

### What the app does — verified
| Fact | Evidence |
|---|---|
| The wall shipped **2026-04-07** | `1ab710c1e` — commit message: *"feat: cookie consent banner with GA gating and registration block"*. The block was deliberate and named as such. |
| It is **on production** | `git show origin/main:resources/js/views/Register.vue` contains `cookiesAccepted` (5 occurrences). |
| The form is absent, not disabled | `Register.vue:90` — `<form v-if="cookiesAccepted">`. `Register.vue:254` — `const cookiesAccepted = ref(hasConsent())`. |
| The only escape accepts | `Register.vue:256-259` → `acceptCookies()`. |
| Consent governs **GA + Awin only** | `cookieConsent.js:31-45` (accept → GA + Awin MasterTag), `:54-62` (decline → no GA, unload MasterTag). Nothing else reads the flag except the Awin `router.afterEach` at `router/index.js:1864`. |
| **Consent is stored only in `localStorage`** | `cookieConsent.js:5` `STORAGE_KEY = 'cookie_consent'`, `:24-26` `hasConsent()`. `grep -rn "cookie_consent" app/ database/migrations/ routes/` → **no matches.** There is no server-side record of any kind. |
| The banner never mentions affiliate tracking | `CookieBanner.vue:16-17` — *"We use cookies to help analyse how you use our site. You can accept or decline."* |
| The decline interstitial names only GA | `CookieBanner.vue:45-47` — *"Without cookies, some features including registration will be unavailable. Google Analytics has been disabled."* |
| The banner is **binary** | `CookieBanner.vue:23-34, 52-63` — Accept / Decline, then Accept / Continue Without. No granular choice between analytics and affiliate. |

### The delta — three independent defects, and the third is the one nobody found

**1. Freely given.** UK GDPR **Art 7(4)** (legislation.gov.uk, Latest available (Revised) as
at 17 August 2026; pending amendments under S.I. 2026/386) requires that in assessing whether
consent is freely given, *"utmost account shall be taken of whether, inter alia, the
performance of a contract, including the provision of a service, is conditional on consent to
the processing of personal data that is not necessary for the performance of that
contract."* Registration is conditional on consent to analytics and affiliate tracking.
Neither is necessary to create an account — proven, not argued: the session cookie was
present throughout with consent declined.

The ICO's published position on tracking walls is that a model requiring the user to agree to
tracking or else be denied the service — the *"take it or leave it"* approach — *"in most
cases does not comply with the requirement for consent to be freely given."*
**Verification caveat:** ico.org.uk returns HTTP 403 to my fetcher, so I have this wording
from the search index of the ICO's *"How do the rules apply to online advertising?"* page and
**have not verified it against the page itself.** Treat it as strongly indicated, not as a
verified quotation, and note the under-review caveat in §0.

**2. Informed.** Sch A1 para 1 conditions consent on the subscriber having received *"clear
and comprehensive information"*. Two verified failures, and the second is broader than the
board item found:
- The register gate's stated reason is **false**. *"Cookies are required to create an
  account. They allow us to keep you securely signed in."* (`Register.vue:76-78`) They do no
  such thing; `XSRF-TOKEN` was present with consent declined.
- **The banner never discloses affiliate tracking at all.** It says *"analyse how you use our
  site"*. Awin is a commercial affiliate arrangement that transmits conversions to a third
  party who is paid commission on them. **Every visitor sees this banner, not just
  registrants** — so this defect is wider in scope than the register wall itself.

**3. Demonstrable — and this is the one I rate most serious.**
**Art 7(1):** *"Where processing is based on consent, the controller shall be able to
demonstrate that the data subject has consented."*

Consent exists **only** as the string `'accepted'` in the visitor's `localStorage`. It is
per-browser and per-device; it is destroyed when the user clears site data; it is never
transmitted to the server; and there is no table, column, log or audit record of it anywhere
(`grep` above returns nothing). **Even if the consent were freely given and perfectly
informed, Fynla could not demonstrate it for a single user, on any day since 2026-04-07.**

Defects 1 and 2 are arguable and a lawyer may take a view. Defect 3 is not arguable in the
same way — it is a question of whether a record exists, and it does not. It is also the
defect the board item did not identify, and the only one that **cannot be cured
retrospectively**: there is nothing to reconstruct a record from.

### What follows for the analytics gathered — recommendation

**I cannot rule that the consent was invalid.** That is the determination §7.3 forbids, and
it is the single most consequential sentence in this report, so I want to be exact about it:
what follows below is the decision structure and the facts each branch turns on, so that a
lawyer answers it quickly and CSJ knows the stakes before they do.

**Scope in time.** Every production account created through `/register` since **2026-04-07**
passed through this gate. The wall is still live and still collecting.

**Scope in data.** What sits in GA property `G-3Y8DL3QB09`. I cannot query it and have not
tried.

**A distinction a lawyer will draw, which I flag so it is not misused.** Art 7(3) provides
that *"the withdrawal of consent shall not affect the lawfulness of processing based on
consent before its withdrawal."* That protects processing carried out on **valid** consent
later withdrawn. It does not, on its face, cure consent that was defective when given. I
raise it because it is the provision most likely to be reached for as reassurance, and I do
not think it does that work here — **but whether it does is not my call.**

**Compounding factor, from W-0047 (build-lead's, not duplicated here).** The hardcoded
fallback at `cookieConsent.js:6` means environments that never configured GA have been
sending hits to the **live production property**. So that property contains development,
staging and test traffic — including, on the board item's own record, the persona tester's
forced acceptance. **This matters to remediation, not just tidiness:** an instruction to
"delete the data collected without valid consent" cannot cleanly separate environments inside
a single property. Whoever scopes a deletion needs to know this before they start.

**My recommendations, as recommendations:**
1. **Put the retrospective question to the lawyer now, with these facts attached** — the
   three defects, the date, the property contamination. This is precisely the §7.4 case: six
   specific questions with the evidence attached bills a fraction of a general review.
2. **Do not let it keep accruing.** Whatever the answer, the volume of data whose basis is in
   question grows daily while the wall stands. That is an argument about sequencing, not a
   deploy recommendation — the release decision is CSJ's.
3. **Get a view on whether the GA data collected since 2026-04-07 should be deleted.**
   I recommend CSJ obtain that view. **I explicitly cannot say whether it must be**, and I
   would distrust any agent that told you it could.

---

## Q2 — Awin specifically: affiliate tracking is not analytics, and declining does not stop it

### The question
Awin is a commercial arrangement, not measurement. Does that change the consent analysis, and
does it interact with any disclosure obligation?

### What the app does — verified, and it is not what the board item describes

**Declining cookies does not stop Awin affiliate tracking.** The consent flag governs only
the browser MasterTag. Two paths run underneath it with **no consent check at all**:

1. **`CaptureAwcCookie` is in the global middleware stack** — `app/Http/Kernel.php:106`,
   in `protected $middleware`, so it runs on **every request** to the application. Also
   present on `origin/main`. It sets a first-party `awc` cookie carrying the Awin click
   reference for **365 days** (`config('awin.cookie_lifetime_days', 365)`), gated **only** on
   `config('awin.enabled')`. It never consults consent. And `declineCookies()`
   (`cookieConsent.js:54-62`) cannot remove it — the cookie is `HttpOnly`, so client-side
   code cannot touch it.
2. **`FireAwinConversionJob`** fires a **server-to-server** conversion for a completed
   payment, gated only on `config('awin.enabled')`. `AwinTrackingService::fireServerToServer()`
   (`:109-134`) transmits amount, currency, order reference, commission group, voucher code,
   customer-acquisition status, and the `cks` click reference. **No name and no email** — I
   checked rather than assumed.

**~~Uncertainty I must state plainly~~ — RESOLVED, see the UPDATE above.** `config/awin.php`
sets `'enabled' => env('AWIN_ENABLED', false)` and the file's own comment says *"Keep false in
local/staging."* I originally could not read production and flagged the whole limb as
conditional. **team-lead read it on the production server on 2026-08-21, read-only, with CSJ's
authorisation: `config('awin.enabled')` is `true` on fynla.org.**

So this is **live, not latent**, and it is the branch I flagged as the sharper one. Every
visitor to fynla.org arriving with an `?awc=` parameter receives a 365-day `HttpOnly` cookie
with no consent check, and no banner choice can undo it — client-side code cannot reach an
`HttpOnly` cookie. Withdrawal must be implemented **server-side** or it will not work at all.

### The delta
The cookie wall **extracts consent for something that partly happens regardless.** The user
is made to accept in order to register, and their acceptance does not in fact govern the
affiliate click cookie or the conversion report. That is a defect in the opposite direction
from the wall itself, and it means the consent mechanism misdescribes its own effect both
ways: it overstates what refusing costs them, and overstates what accepting controls.

### Does the commercial character change the analysis?
**Yes, in one respect I can state within competence.** The new Sch A1 para 4 statistical
exception is expressly about statistics *"with a view to making improvements"* and expressly
requires that the information *"isn't shared"*. Affiliate attribution is neither statistical
nor unshared — transmitting the conversion to a commercial third party who pays commission on
it **is the entire purpose**. So the analytics limb and the affiliate limb are not the same
question and cannot share one answer.

Which makes the **binary banner** a problem in its own right: bundled behind one flag, a user
cannot accept measurement and refuse affiliate tracking. That bears on **Art 7(2)** —
a consent request must be *"clearly distinguishable from the other matters, in an intelligible
and easily accessible form"* — and on granularity generally. Whether it breaches anything is
not mine to say.

### Disclosure — flagged, not answered
Awin is an arrangement under which Fynla pays commission on acquisitions. **Whether that
requires disclosure is not a data-protection question at all**, and I am not going to pretend
it is: it sits closer to consumer-protection and advertising rules, and to the marketing
surface trunk §3 already governs.

What I will flag is a gap §3 does not reach. §3 binds the seven rules to *"marketing and
transactional email… social… anything else a user or prospect reads."* Affiliate acquisition
content is written by **publishers Fynla does not employ and does not review**, but does
commercially incentivise. **The trunk has no clause on content Fynla does not write but pays
for the results of.** For an unauthorised firm in a financial-promotions-adjacent market
(§6.1, still open), that strikes me as the most consequential of the three trunk gaps in this
report — but I am flagging it, not sizing it.

---

## Q3 — Does the trunk cover this at all? A second unnamed regime gap, and a third

### What the trunk says today
`grep -i "cookie|PECR|consent|marketing|GDPR|ICO|affiliate"` over `05-perimeter.md` returns
five hits. Every one of them is something else:

| Line | Hit | What it actually covers |
|---|---|---|
| 36 | `fyn:user:erase` — GDPR erasure | a **retention/erasure** control |
| 46 | "marketing and transactional email" | the **list of surfaces** the seven rules bind |
| 91 | §6.1 financial promotions | s21 FSMA |
| 99 | §6.5 Article 9 | special-category **by inference** from smoking status |
| 126 | §7.2 "ICO guidance on special-category data" | scoped to Art 9 |

`03-hard-nos.md` and `06-commercials.md`: **zero** hits on cookie, consent, tracking,
affiliate, analytics or privacy.

So the trunk's entire data-protection surface is **erasure, retention, and one Article 9
question.** There is no clause on consent validity, cookies, PECR, tracking, or marketing
permissions — nothing to apply to this item.

### The delta, and the pattern underneath it
**This is structurally identical to the gap I found on W-0019.** There, `05-perimeter.md` had
no clause on the legal-services regime governing a tool that drafts wills. Here it has no
clause on the data-protection regime governing a tool that sets tracking cookies. Both times
the file had nothing to apply, because **it is written against the FCA regime and assumes
that is the only one Fynla operates under.**

Two consecutive compliance rulings hitting the same shape makes it a pattern, not an
incident. **For the workforce, this is the most important finding in the report** — more than
any individual clause, because it predicts the next one.

### Recommended position
Rather than bolting on a clause per regime as each is discovered, I recommend **§1 state
which regimes Fynla operates under and which are unmapped** — so an agent meeting an
unmapped regime *sees that it is unmapped* rather than discovering it by finding nothing.
On present evidence that list is at least: financial services (mapped), legal services
(unmapped, W-0019), data protection and privacy in electronic communications (unmapped,
this item), and consumer/advertising rules around incentivised third-party content
(unmapped, Q2).

Then the specific additions:
- **§7.2 sources:** UK GDPR, PECR **as amended by the Data (Use and Access) Act 2025**, and
  ICO guidance on storage and access technologies — with the standing note that ICO cookie
  guidance is under review post-DUAA.
- **New §6 questions:** (a) is the consent collected through the registration wall since
  2026-04-07 valid, and what follows for the data; (b) can Sch A1 para 4 reach any analytics
  deployment that shares with a third-party processor; (c) what disclosure attaches to
  affiliate-incentivised third-party acquisition content for an unauthorised firm.

**A third gap, and it is a Rule 20 shape.** Three surfaces, three consent mechanisms:
web writes a string to `localStorage`; **native iOS has a full server-backed versioned
consent system** (`ios-native/Fynla/Features/Privacy/ConsentModels.swift` — `ConsentRecord`
with `type`, `version`, `consented`, `consentedAt`, `withdrawnAt`, plus history and
`needs_reconsent`); `/m` has **none** (`grep` over `resources/mobile/` for
`cookieConsent|hasConsent|cookie_consent` returns nothing). One rule, several mechanisms,
each fixed separately — the exact disease Rule 20 names. I flag it in those terms because
the workforce already has the vocabulary and the reflex for it.

---

## Q4 — What I would recommend building, and the catch

**The mechanism that would fix defect 3 already exists in the product.**

`app/Models/UserConsent.php` is a server-side, versioned, auditable consent record:
`TYPE_TERMS`, `TYPE_PRIVACY`, **`TYPE_MARKETING`**, `TYPE_DATA_PROCESSING`, `TYPE_AI_CHAT`,
each with a `CURRENT_VERSIONS` entry (`:16-32`), backed by `user_consents`
(`2026_01_19_140002_create_user_consents_table.php`), exposed at
`GET/PUT /api/…/consents` and `/consents/history` (`routes/api.php:199-201`), and **already
consumed by native iOS**, including withdrawal timestamps and re-consent prompting.

The cookie banner writes a string to `localStorage` instead. There is already a
`marketing` consent type sitting unused by the analytics and affiliate path.

Routing cookie, analytics and affiliate consent through `UserConsent` would make it
**demonstrable** under Art 7(1), **versionable** when the purposes change (which they did
when Awin was added in `3bbc336c6`, 2026-04-15, eight days after the banner shipped — with no
re-consent), and **withdrawable** under Art 7(3), using machinery already built, tested and
shipped to a client.

**The catch, stated so nobody builds the wrong thing on my recommendation:** `UserConsent` is
keyed to a `user_id`, and cookie consent must be capturable **before an account exists** —
which is the whole of this item. So this is **not a drop-in**; it needs a pre-account
identity (or a deferred record written at registration capturing what was chosen before it).
That is a design decision for build-lead and product-lead, not a compliance instruction. I am
naming the prior art and the obstacle, not specifying the solution.

---

## 5. Live exposure vs documentation gap

**Different urgencies. Not to be read at the same weight.**

### Live — re-ranked after the UPDATE

1. **Consent is not demonstrable for any user, on any day, since 2026-04-07.** `localStorage`
   only; no server record exists. **First because it is the only one that cannot be repaired**
   — there is nothing to reconstruct from. Everything else here is fixable going forward.
2. **Declining does not stop Awin, and cannot.** `AWIN_ENABLED` is **true on production**
   (confirmed). Global middleware (`Kernel.php:106`, also on `main`) sets a 365-day
   `HttpOnly` `awc` cookie with no consent check; server-to-server conversions fire with no
   consent check. Client-side code cannot reach an `HttpOnly` cookie, so **no banner change
   alone can fix this** — withdrawal must be server-side. **Second because it affects every
   visitor, including the majority who never see the registration wall and have never been
   asked anything.**
3. **The banner never discloses affiliate tracking to anyone** (`CookieBanner.vue:16-17`).
   Same population as item 2, and the two compound: undisclosed processing that declining
   would not stop even if it had been disclosed.
4. **The wall is on production and has been since 2026-04-07** (`1ab710c1e`, on
   `origin/main`), and it is still collecting. Every production account created through
   `/register` since then passed through it. **Fourth, not first — it is the narrowest
   population of the four**, though it is the one with the clearest defect.
5. **Google Analytics property contamination** (W-0047, build-lead's — not duplicated).
   Production has **no measurement ID configured**, so the hardcoded fallback resolves there
   too: one property, every environment. Live here only because it constrains any remediation
   of item 4.

### Documentation gaps

6. Trunk has no data-protection or consent clause — second unmapped regime (Q3).
7. Trunk has no clause on commercially-incentivised third-party acquisition content (Q2).
8. Three surfaces, three consent mechanisms, no single home — a Rule 20 shape (Q3).
9. **`/m` has no registration path and no consent code at all.** The Rule 19 answer to
   acceptance item 6 is *"no gate on `/m` because `/m` has no register view"* — parity by
   absence. Recorded as such, because "checked, fine" would hide that the surface simply
   isn't there, and a future `/m` registration would land with no consent machinery at all.

---

## 6. Acceptance item 3 — draft replacement copy

**Drafted 2026-08-21 at team-lead's instruction. Not final: public-facing wording is
`design-lead`'s remit** (the precedent my own W-0019 review set). Handoff note:
`workforce/ops/handoffs/W-0050/compliance-to-design-2026-08-21.md`.

### The condition that governs everything below — read before using any of it

**This copy describes a state the code is not yet in.** Specifically it tells the user that
declining stops analytics and affiliate tracking. Today that is **false for affiliate
tracking**: `CaptureAwcCookie` sets the `awc` cookie with no consent check, and being
`HttpOnly` it cannot be cleared from the browser.

> **The copy must not ship before the code it describes is true.** Publishing it against the
> current code would replace one false justification with a different false one — and the
> second would be worse, because it would be false about the very thing the first was hiding.
> The order is: gate the Awin paths on consent server-side, remove the wall, **then** the
> copy.

If build-lead concludes the server-side `awc` capture cannot be consent-gated, **tell me and
I will redraft** — the disclosure would then have to say what actually happens. I will not
paper over it.

### What copy can and cannot fix

| Defect | Fixed by copy? |
|---|---|
| **2. Informed** — false justification, undisclosed affiliate tracking | **Yes.** This is the defect copy exists to fix. |
| **1. Freely given** — registration conditional on consent | **No.** Only removing the condition fixes that, and that is CSJ's call. |
| **3. Demonstrable** — no record of consent | **No.** Copy cannot create a record. Needs the `UserConsent` work in Q4. |

Anyone reading "the copy is fixed" as "W-0050 is resolved" would be wrong on two of three
counts. Worth stating because it is the likeliest misreading.

### Draft A — cookie banner, request state
*(replaces `CookieBanner.vue:15-18`; register `Functional`)*

> **Cookies on Fynla**
>
> Some cookies are needed to run Fynla — they keep you signed in and keep your account
> secure. We don't ask permission for those, and they can't be turned off.
>
> We'd also like your permission for two things that aren't needed to run the site:
>
> **Measuring how the site is used** — Google Analytics, so we can see which pages people use
> and fix what isn't working.
>
> **Crediting partners** — Awin, so that if you came to Fynla through one of our partners,
> they're credited for the referral and we can pay them.
>
> Declining doesn't change anything you can do on Fynla.

Buttons: **Accept** · **Decline** — equal weight, side by side.
Link: **Privacy Policy** (already present at `CookieBanner.vue:18`).

**Why it is shaped this way.** The strictly-necessary sentence comes first and says
explicitly that we are *not* asking — acceptance item 2's requirement, and it also removes
the ground the old false justification stood on. The two non-essential purposes are named
separately, with the third party named in each, because Sch A1 para 1 conditions consent on
*"clear and comprehensive information"* and a user cannot be informed about a processor
whose name they have never seen. Affiliate is described commercially — *credited*, *pay* —
rather than as a technical detail, because that is what it is.

**Granularity — a build decision I am flagging, not making.** As drafted this is still one
binary choice covering two unlike purposes. Separate accept/decline per purpose would be
better: measurement and commercial attribution are different things, and Art 7(2) asks that a
consent request be *"clearly distinguishable"*. I have drafted the binary version because it
matches the current mechanism; **if build-lead makes it granular, the two bold lines become
two toggles and the copy needs no rewrite.**

### Draft B — the decline confirmation

**Recommend deleting it** (`CookieBanner.vue:38-63`). Once the wall is gone, every word of
*"Without cookies, some features including registration will be unavailable. Google Analytics
has been disabled"* is either untrue or unnecessary.

Beyond accuracy: the interstitial exists **only on the decline path**, so accepting takes one
click and declining takes two, under a heading reading **"Limited Functionality"**. That is
asymmetric friction on the privacy-preserving option, and the heading states the choice's
cost rather than the choice. I am flagging the shape, not ruling on it. **If a confirmation
step is kept, it should appear on both paths or neither.**

### Draft C — the registration block

**No replacement copy. Delete the block.** `Register.vue:69-86` (the entire
`v-if="!cookiesAccepted"` panel), the `v-if="cookiesAccepted"` guard on the form at `:90`,
and `cookiesAccepted` / `handleAcceptCookiesForRegistration` at `:254-259`.

**Rule 15 note for whoever does it:** that panel contains an inline SVG icon
(`Register.vue:70-72`). It is a grandfathered existing violation and it disappears with the
block — **do not carry it over** to anything that replaces it.

### If CSJ keeps the wall

Not my decision, and I am not drafting toward a preferred answer. If registration stays
conditional, the copy must at minimum stop being false — something like:

> To create an account you'll need to accept analytics and affiliate cookies. They aren't
> needed to keep you signed in — your account works without them — but we ask for them as a
> condition of registering.

**Say plainly what that would and would not achieve.** It fixes defect 2: the user would
finally be told the truth about what they are agreeing to and why. **It does not touch defect
1.** Article 7(4)'s test is about whether performance of a service is *conditional* on
consent to processing not necessary for it — not about whether the condition is disclosed.
An honestly described wall is still a wall. **Whether that wall is lawful is not mine to
determine**; that it remains a wall is simply what the provision is addressed to, and I would
be misleading you if I let better copy read as a fix.

### Public claims flagged, as asked

| Claim | Status |
|---|---|
| *"Declining doesn't change anything you can do on Fynla"* | **A claim about product behaviour, and the load-bearing one.** False today. True only once the wall is removed. **Must be re-verified against the code before publication**, and again if anything new starts reading the consent flag — today only `Register.vue` and `router/index.js:1864` do. |
| *"they're credited for the referral and we can pay them"* | **A claim about a commercial arrangement.** Consistent with `AwinTrackingService::fireServerToServer()` (commission group, sale amount, acquisition status). **I have not seen the Awin contract** and cannot confirm it against the actual commercial terms. Someone who has should check it. |
| *"keep you signed in and keep your account secure"* | **A security claim**, deliberately modest. Accurate for session and cross-site-request-forgery cookies. **Do not escalate** it to "keeps your data safe" or similar — that would be an overclaim under voice C5. |
| *(absent)* regulatory status | **Deliberately not written.** A cookie notice is the wrong place for anything about authorisation, the same reasoning I applied to the will refusal on W-0019. If anyone adds it, that is a change I would want to see. |

### Rule checks

- **Rule 9 / voice §4** — no acronyms in product copy. "Google Analytics" and "Awin" are
  proper nouns, not acronyms. Cross-site-request-forgery is never named to the user; it is
  covered by *"keep your account secure"*.
- **Rule 15** — no icons, glyphs or emoji added anywhere. Draft C removes one.
- **Voice C1** British English · **C2** not alarmist — the decline path carries no warning ·
  **C3** specific, both third parties named · **C4** no jargon — no "non-essential cookies",
  no "processing" · **C5** no overclaiming, and the deterrent framing removed · **C6** no
  advice · **C7** no currency involved.
- **Rule 19** — `/m` has no register view and no consent code, so Draft C has no `/m`
  counterpart. Native has its own server-backed consent system and does not read this. If
  `/m` ever gains registration, Draft A is the copy it should use, from one home.

---

## Done

- **Ruled on the primary retrospective question** as far as §7.3 permits: three independent
  defects in the consent (freely given, informed, demonstrable), the facts each turns on, and
  the decision structure for the data — without making the determination.
- **Corrected the item's own law.** PECR reg 6 was substituted on 5 February 2026 by the Data
  (Use and Access) Act 2025; the exemptions are now in Schedule A1. The conclusion survives;
  the citation does not. Flagged the **new** Sch A1 para 4 statistical exception, which did
  not exist when this code was written and which nobody has yet considered.
- **Found the defect the board item missed** — consent is not demonstrable under Art 7(1),
  and is the only defect here that cannot be cured retrospectively.
- **Found that declining does not stop Awin** — global middleware and a server-to-server job,
  neither consent-checked. This changes the shape of the item.
- **Found that the banner never discloses affiliate tracking at all**, to any visitor.
- Separated five live exposures from four documentation gaps.
- Identified the prior art (`UserConsent`, with `TYPE_MARKETING` already defined) **and the
  reason it is not a drop-in.**
- Named the pattern: two consecutive rulings have hit "the trunk has no clause for this
  regime."

## Not done, and why

- **No ruling on validity.** Three defects flagged with sources; the determination is a
  lawyer's. An agent that told you "this consent is invalid" would be doing the thing §7.3
  exists to prevent — and so would one that told you it was fine.
- **~~Acceptance item 3 — the replacement copy~~ — DONE, see §6**, drafted 2026-08-21 on
  instruction. **Not final:** the wording is `design-lead`'s remit and it is handed off, not
  finished. And it **must not ship before the code it describes is true** — §6's opening
  condition.
- **Did not resolve the cookie-wall question in the copy**, as instructed. §6 drafts for the
  unconditioned state and sets out separately what would have to change if CSJ keeps the
  wall — including that better copy would fix the *informed* defect and leave the *freely
  given* one exactly where it is.
- **Could not verify the ICO wording directly.** ico.org.uk returns HTTP 403 to my fetcher on
  every path tried. The cookie-wall position is cited from the search index of the named ICO
  page and marked as such rather than presented as a verified quotation.
- **Could not read `AWIN_ENABLED` in production.** `.env` is server-side and `ssh-fynla` is
  production; this analysis is local-only. The Awin limb is conditional on it.
- **Did not query the GA property.** No access, and did not seek it.
- **No code, no PR, no prod** — per dispatch. Trunk unamended.

## Assumptions

- `origin/main` is what runs on fynla.org, per `CLAUDE.md`'s deployment table. Production
  state inferred from the branch; I did not observe the server.
- `1ab710c1e` (2026-04-07) is when the wall reached production, inferred from the commit
  being on `main`. **I did not verify the deploy date**, which may be later than the commit.
  If the retrospective scope matters to a pound figure, that date needs confirming properly.
- The three facts the dispatch told me were established, I took as given and did not
  re-derive — except where re-reading the code turned up more (the Awin server paths, the
  banner's silence on affiliate, the absence of any consent record).

## Needs

- **Answer (lawyer, via CSJ):** the three new §6 questions in Q3, with this report attached.
  Q1's retrospective question is the one with a clock on it.
- **~~Answer: is `AWIN_ENABLED` true in production?~~ — ANSWERED: yes.** It resolved to the
  live branch. See the UPDATE. What this now needs instead is a **decision on whether the
  server-side `awc` capture and the conversion job get consent-gated** — because until they
  do, no banner wording can be made true, and §6's copy is blocked on it.
- **Review (design-lead):** §6's draft copy, handed off at
  `workforce/ops/handoffs/W-0050/compliance-to-design-2026-08-21.md`.
- **Gate (CSJ):** whether GA data collected since 2026-04-07 should be deleted, taking the
  property contamination into account.
- **Gate (CSJ):** trunk amendments — §1 regime map, §7.2 sources, three §6 questions.
- **Decision (CSJ):** whether I draft the replacement copy now (acceptance item 3).

## Noticed — outside my remit, routed

- **build-lead:** the banner is binary — `CookieBanner.vue:23-34, 52-63`. No way to accept
  measurement and refuse affiliate tracking. Bears on Art 7(2) granularity; more importantly
  it is why one flag governs two unlike purposes.
- **build-lead:** the decline interstitial is headed **"Limited Functionality"** and warns
  *"some features including registration will be unavailable"* (`CookieBanner.vue:45-47`).
  Framing the refusal path by its detriment is a nudge, and it is the kind of thing consent
  guidance looks at. Not a determination — worth design-lead's eye alongside the copy fix.
- **build-lead:** Awin was added in `3bbc336c6` (2026-04-15), **eight days after** the banner
  shipped, widening what the existing flag covered. **No re-consent, no version bump** —
  there is no version to bump, which is Q4's point. Anyone who accepted between 07 and 15
  April accepted something narrower than what they got.
- **product-lead / build-lead:** `declineCookies()` cannot clear the `awc` cookie because it
  is `HttpOnly`. Any "withdraw consent" feature must clear it **server-side** or it will
  silently fail to do the one thing its label promises.
- **quartermaster:** the dated source register is still unbuilt (raised on W-0019). This
  report is the second to carry its own inline register. Two is a habit forming around a
  missing tool.

---

### Dated source register — W-0050 additions

> **SUPERSEDED as to the Schedule A1 paragraph numbers — see the `2026-08-21 compliance-lead`
> correction at the foot of this document ("every Schedule A1 paragraph number in the row
> below is off by one").**
> Schedule A1 paragraph 1 is **Interpretation**, not consent. Consent is **para 2**,
> strictly necessary is **para 4**, statistical purposes is **para 5**.
> **The substance described is right; only the numbers are wrong, and nothing in this
> ruling's reasoning is re-opened.** Left as written — the row is the record of what was
> believed.

| Source | Provision | Date |
|---|---|---|
| PECR 2003 (SI 2003/2426) | reg 6(1) — prohibition, "subject to Schedule A1" | substituted **5 Feb 2026** by DUAA 2025 |
| PECR 2003 Sch A1 | para 1 consent · para 3 strictly necessary, incl. "maintaining authentication records" · **para 4 statistical purposes**, conditional on not sharing + free means of objecting | inserted **5 Feb 2026** by DUAA 2025 |
| UK GDPR (Reg 2016/679) | Art 7(1) demonstrate · 7(2) distinguishable · 7(3) withdrawal · 7(4) conditional on consent | Latest available (Revised) as at **17 Aug 2026**; pending amendments S.I. 2026/386 |
| ICO, "How do the rules apply to online advertising?" | tracking walls / "take it or leave it" — in most cases not freely given | **not directly verified** — ico.org.uk 403s the fetcher; cited from search index; page carries an under-review note post-DUAA |
| ICO, "Consent or pay" guidance | consent-or-pay models, factors for freely given consent | published post-2024 consultation; **under review** following DUAA |

---

## Correction — 2026-08-21, compliance-lead

**Citation only. Nothing in this ruling's reasoning is re-opened, and its substance stays
parked exactly as CSJ ruled.** Marker placed at the register above, per the marker rule in
`ops/FORMATS.md` ("Correcting an append-only document").

### What is wrong

**Every Schedule A1 paragraph number in this document's dated source register is off by
one.** Verified against `legislation.gov.uk`, Schedule A1 read in full and enumerated,
**2026-08-21**, latest available (revised):

| Para | Subject | This document said |
|---|---|---|
| **1** | **Interpretation** — *"In this Schedule, 'website' includes a mobile application…"* | — (it recorded para 1 as consent) |
| **2** | **Consent** | recorded as para 1 |
| **3** | Transmission of a communication | — |
| **4** | **Storage or access strictly necessary** | recorded as para 3 |
| **5** | **Collecting information for statistical purposes** | recorded as para 4 |
| **6** | Website appearance etc | — |
| **7** | Emergency assistance | — |

**The substance described in the register is right.** There is a consent exception, a
strictly-necessary exception, and a statistical-purposes exception, and this document
correctly identified that the exceptions had moved out of regulation 6 into a new
Schedule A1. **Only the numbering is wrong.**

### What is NOT wrong, and is worth stating because it was nearly recorded otherwise

**This document did not cite "reg 6(4)" as its own authority. It caught that citation and
corrected it** — §0 reads *"W-0050 cites 'PECR reg 6(4)' for the strictly-necessary
exemption"* and then *"The board item's conclusion survives; its citation does not."* **The
stale citation was the board item's, and this ruling is what found it.**

I initially reported to team-lead that this ruling carried a broken reg 6(4) citation. **That
was wrong and the record should not carry it.** The correction needed here is the paragraph
numbering, which is a different and more serious defect, for the reason below.

### Why this is worse than the thing I thought I had found

A stale citation in a board item is what a ruling exists to catch, and this ruling caught it
the same day.

**These wrong numbers are in the dated source register** — the artefact whose entire purpose
is to be re-checkable when the law moves — and they were written **today**, from a source read
**today**. A register that is itself wrong is worse than no register, because it is trusted.

**It was caught only because a second agent read Schedule A1 independently for a different
item (W-0049 / F-0007) and the two enumerations disagreed.** Nothing systematic caught it.
That is an argument for the central register (`registry/sources.md`, built today under
`ops/gaps/G-0003`) rather than against it: one row, read once, checked by whoever next relies
on it, rather than a private copy per artefact that nothing ever contradicts.

### Where the correct numbering now lives

`core/registry/sources.md` **row A12**, with the full paragraph enumeration, and standing
warning **W4** on the regulation 6 substitution. Anything relying on Schedule A1 should read
that row rather than this register.

### Not done

- **No re-ruling.** The conclusions of this document are untouched, and W-0050's substance
  is parked by CSJ.
- **The "conditional on not sharing + free means of objecting" description of the statistical
  exception is NOT verified by me.** I enumerated the paragraphs and read para 5's opening;
  I did not read its conditions in full. **Anyone relying on those conditions must read
  para 5 itself.**
- **The UK GDPR and ICO rows in the register above are untouched and unverified by me.**
