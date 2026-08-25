---
id: F-0024
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/08-process.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-22T00:00:00Z
status: active
---

# F-0024 — Cycle 4: the risk engine derived four figures for itself

**Agent:** build-lead (`fix-cycle4-riskengine`) · **Branch:** `dev` (shared working tree)
**Board items:** W-0271, W-0272, W-0273 · **ID block:** W-0271 – W-0280
**Number and ID block issued by team-lead.**

**Predecessors, read before touching anything here:**
`F-0019-cycle2-ownership-applied-one-side-only.md` — the **reach / fraction**
vocabulary this batch is written in, and `LifeCoverReach`, the shape `DependantsReach`
follows. `F-0022-cycle4-dashboard-module-totals-and-cache.md` — the same disease in
the agents, and the precedent for **deleting** a mechanism rather than parameterising
it. Board items **W-0238**, **W-0241**, **W-0244**, **W-0226**.

---

## 1. The principle

**A figure the risk engine derived for itself is a figure that can disagree with the
application, and this one derived four.**

F-0019 named two failures. This batch has both, in one class, plus a third thing
neither predecessor had to name:

| Failure | Where | What the user saw |
|---|---|---|
| **Reach** | `FamilyMember::where('user_id', …)` | a mother of two assessed as childless |
| **Reach** | `InvestmentAccount::where('user_id', …)` | a co-owner shown none of a joint portfolio |
| **Fraction** | the same query, at 100% | the recorder charged with all of it |
| **Definition** | `where('is_emergency_fund', true)` | £130,780 of cash reported as £0 |

The fourth is the one worth naming separately, because routing does not fix it and
no share is involved. **Two mechanisms held two different definitions of one
concept**, and the contradiction was total rather than marginal:

> `SavingsAgent` → dashboard and `/net-worth/cash`: David **79.8 months**, Sarah
> **25.3**, *"Your emergency fund is well-funded. Excellent!"*
> `AutoRiskCalculator` → `/risk-profile`: both **"0 months"**, *"Less than 3 months
> emergency fund suggests keeping investments more conservative."*

Same user, same session, same money, opposite advice.

### The denominator was routed too — and I overstated why, so here is the correction

**Correcting my own earlier characterisation, which team-lead repeated back to me.** I
wrote that the denominator "disagreed too", citing £1,250 against £1,225 "for the same
person on the same day". **Measured, that is wrong.** Per user, the raw column and the
resolved chain return the same figure today:

| | `users.monthly_expenditure` | `expenditure_profiles` | resolved |
|---|---|---|---|
| David (16) | £1,250 | £1,250 | £1,250 |
| Sarah (17) | £1,225 | *no row* | £1,225 |

The £1,250 / £1,225 gap is **between the two spouses**, not between two mechanisms —
it is **D-26** (Sarah's half never recomputed after David last edited; the household
figure is £2,500, so both halves should be £1,250). Routing the denominator therefore
**moved no number on this persona.**

**It is still the right call, for a structural reason rather than a corrective one.**
The chain prefers an `expenditure_profiles` row over the column, and those two *can*
diverge — they did historically, which is how a stale 41.6-month reading survived
beside a live 83.3. Leaving this factor on the raw column would have bought agreement
that held only until something wrote to a cashflow profile.

**The property that matters is that the two surfaces cannot disagree, not that a
number moved.** That is the justification for this half of the fix; the number I
originally cited was not.

**Consequence for whoever reads the figures next:** Sarah's **25.3 months is computed
from a denominator D-26 will change**. When it lands she should read ≈24.8 months, on
`/risk-profile`, the dashboard and `/m` together. That is D-26 taking effect, not a
regression in this work.

### Why this is not a display bug

The level these nine factors produce drives every projection in the application.
Sarah's dependants factor was pushing her risk level **up** on the premise that she
had no children, and her emergency-cash factor was pushing it **down** on the premise
that she had no cash. A wrong figure on this page does not stay on this page.

---

## 2. Prior art

Checked 2026-08-22 across `registry/capabilities.md`, the code, custom artisan
commands, open PRs and in-flight branches, the vault, and `.claude/skills|agents`.

| Instance | Prior art found | Outcome |
|---|---|---|
| W-0271 cash total | `CrossModuleAssetAggregator::calculateCashTotal()` — reach-complete, share-correct, already read by `/net-worth`, the dashboard and `SavingsAgent` | **route** |
| W-0271 expenditure | `ResolvesExpenditure` — the chain `SavingsAgent` resolves | **route** |
| W-0271 runway arithmetic | `EmergencyFundCalculator::calculateRunway()` | **route** |
| W-0273 investments + pensions | `NetWorthService::calculateNetWorth()` — already called by this very method for its denominator, and already carrying both terms in `breakdown` | **route** |
| W-0273 defined benefit exclusion | `NetWorthService::calculatePensionBreakdown()` + `has_db_pensions` (W-0241, landed by another agent this cycle) | **route** |
| W-0272 dependants reach | **none.** No mechanism answers "who depends on this user". `LifeCoverReach` answers the analogous question for a joint-life policy and is the shape to copy, but it is about policies and cannot be reused for people | **build**, one home |

**"Build a parallel one because the existing one is awkward" was available twice and
declined twice.**

1. The emergency-fund runway could have kept its own division and simply changed its
   numerator. It would have left two implementations of "months of runway" with two
   no-expenditure rules — the existing one invents **12 months**, `EmergencyFundCalculator`
   returns **0.0** — and a household could still have been shown two answers.
2. `DependantsReach` could have been a private method on `AutoRiskCalculator`. Eight
   other consumers ask the same question with the same `user_id`-only query
   (**W-0275**), and a private method is one none of them can ever route to.

**No arithmetic was added.** Three figures were routed onto homes that already
existed; one home was built because the question had never been asked.

---

## 3. Constraints honoured

- **Rule 6** — single record. Nothing is duplicated per owner; `DependantsReach`
  de-duplicates a child both parents entered rather than counting two.
- **Rule 19** — web and `/m` named individually throughout, and **both verified in
  the browser** (§7). `/m` has **no risk-profile screen at all**; what it does show —
  the savings runway — now reads 25.3 months against the web risk page's 25.3.
  Raised as W-0279 rather than assumed or skipped.
- **Rule 20** — every figure has exactly one home, and the count of implementations
  went **down** by four.
- **Rule 12** — no scores. The factor levels and the ratio percentage are pre-existing
  and untouched; nothing numerical was added to any user-facing surface.
- **Rule 15** — no icons added. The one paragraph added to the detail page is text.
- **Rule 9** — "defined benefit" is written out in every string.
- **W-0241's CSJ ruling honoured exactly** — a defined benefit scheme is **excluded
  and disclosed**. No capitalisation multiple, no `transfer_value`, no valuation of
  any kind is introduced here, and a test asserts the ×20 figure specifically does
  not appear.
- **A third party is not a spouse.** Every share used comes from
  `calculateUserShare`, which credits a non-user co-owner's share to nobody.
- **W-0228 respected** — nothing assumes a joint split is 50/50. Every fixture in the
  new suite is 75/25 or 70/30 for exactly that reason.

---

## 4. Status — ALL THREE DONE

| Item | Outcome | One home |
|---|---|---|
| **W-0271** two definitions of an emergency fund | **DONE** · `handoff` → quality-lead | `CrossModuleAssetAggregator::calculateCashTotal()` + `ResolvesExpenditure` + `EmergencyFundCalculator` |
| **W-0272** a linked spouse assessed as childless | **DONE** · `handoff` → quality-lead | `app/Services/Shared/DependantsReach.php` (NEW) |
| **W-0273** wrong totals in capacity for loss | **DONE** · `handoff` → quality-lead | `NetWorthService::calculateNetWorth()`, one response for both terms |

### Mechanisms answering each question: before and after

| Question | Before | After |
|---|---|---|
| How much could this user cover in an emergency | 2, contradicting absolutely | **1** |
| What is this user's monthly spending | 2 (the resolver chain, and the raw column) | **1** |
| How many months of runway is that | 2, with different no-expenditure rules | **1** |
| What are this user's investments worth | 2 (the net worth breakdown, and a private sum) | **1** |
| Who depends on this user | 0 — nobody asked; 9 places guessed from `user_id` | **1**, with 8 consumers left to route (W-0275) |

**Four fewer implementations than we started with, and one new file that answers a
question nothing answered.**

### Measured, both accounts, through the real services

| | David (16) before → after | Sarah (17) before → after |
|---|---|---|
| Emergency fund | 0 months, Lower-Med → **79.8 months, Upper-Med** | 0 months, Lower-Med → **25.3 months, Upper-Med** |
| Dependants | 2, Lower-Med (already right) | **0, Upper-Med → 2, Lower-Med** |
| Capacity for loss — investments | £220,000 → **£172,500** | £85,000 → **£132,500** |
| Capacity for loss — ratio | 48.3% → **45.1%** | 11.5% → **17.9%** |
| Defined benefit scheme | n/a | £0 undisclosed → **£0 and disclosed on screen** |

79.8 = £99,750 ÷ £1,250 and 25.3 = £31,030 ÷ £1,225 — the savings module's own
numerator and denominator. The two surfaces now agree **by construction**.

---

## 5. In flight

**Nothing.** Every edit is applied, linted, covered and browser-verified on both
accounts, on web and `/m`.

The shared Playwright tab was occupied by another agent mid-run when I first reached
for it (measured: the page moved from `/login` to `/net-worth/investments` between two
of my calls, with no navigation of mine in between). I stopped, escalated, and waited
until the tab had been silent for over 100 seconds before taking it. **Taking it
required signing out of the David session that agent had left open** — there is no way
to reach the login form otherwise. That is recorded in §7 so the displaced agent can
re-authenticate rather than diagnose a mystery logout.

---

## 6. What the receiver needs, and would not otherwise know

### 6.1 `is_emergency_fund` now means a designation, not a definition — and that is a decision, not a side effect

Stated explicitly because the dispatch asked for it to be.

The flag **survives** and keeps every job it already does: the badge on the account
card on web and `/m`, the "designate an emergency fund" action in
`SavingsActionDefinitionService`, and which account `LifeEventAllocationService`
draws from first. What it no longer does is decide whether the user has **any**
runway at all.

The argument is not that the flag is useless — it is that **money in an
instant-access account is available in an emergency whether or not somebody ticked a
box about it**, and that a household cannot simultaneously have 81 months and 0
months of the same cash. On the household that surfaced this, **all six accounts held
the flag at 0**, including a £50,000 National Savings holding and two Cash ISAs.

**The known cost, stated rather than buried:** the runway now counts cash the user
could not actually reach quickly — a five-year fixed-rate bond counts the same as a
current account. That is not a regression (the savings module has always counted it
that way, and this change makes the risk page agree with it), but it is wrong in a
way `LiquidityAnalyzer` already has the information to fix. **Raised as W-0276**, not
fixed, because changing what "available" means would move the savings module's
figure too and that is a product call.

### 6.2 The capacity-for-loss ratio now takes both terms from ONE response, and that is the point

`calculateCapacityForLoss` already called `calculateNetWorth()` for its denominator.
The numerator it computed itself, from different queries, over a different set of
records. So the ratio described one set of assets over a *different* set of assets —
and nothing about a percentage on screen says so.

Both terms now come from the same `calculateNetWorth()` response. This **deletes two
queries and two imports** (`InvestmentAccount`, `PensionStore`) rather than adding
anything, and it makes the two halves of the ratio structurally incapable of
describing different sets.

### 6.3 The Defined Benefit disclosure is READ from `PensionDisclosure`, and it is its own field because appended it was measurably clipped

**I wrote my own wording first, and then deleted it.** `App\Constants\PensionDisclosure`
landed mid-batch as the one home for what the application says about an excluded
Defined Benefit scheme, and this factor now reads
`PensionDisclosure::DEFINED_BENEFIT_EXCLUDED_SHORT` rather than keeping a copy. The
short constant is not a truncation — it is a complete sentence sized for a caption.

`has_db_pensions` is **not a second flag**: it is
`NetWorthService::calculatePensionBreakdown()['has_db']`, returned inside the
`calculateNetWorth()` response this method already reads. Asking the breakdown
separately would be a second query for a value already in hand, and inferring it from
`pensions_total === 0` would be inferring provision from a zero — the precise mistake
the disclosure exists to prevent.

**The disclosure is returned as its own `disclosure` field, not appended to
`description`, and that was measured rather than assumed.** Appended, it rendered
**clipped** on the summary card: `FactorBreakdownCard` applies `line-clamp-2`, and
ratio-sentence-plus-disclosure is three lines — `scrollHeight` **48** against
`clientHeight` **32**, read from the live DOM. The user saw the ratio and lost the
reason for the £0. A clipped disclosure is not a disclosure, which is exactly what the
constant's own docblock warns about, and appending it would have failed W-0241's
acceptance on the surface most users see first.

So: **all nine factors now return a `disclosure` key** (null for the eight with
nothing to say), the card renders it on its own unclamped line, and the detail page
renders it beneath the formula — where the "£0 pensions" term actually appears. The
detail page previously did not render the factor's `description` **at all**, so an
explanation put only there would have existed and been unreadable.

One string, two surfaces, no second wording to drift, and nothing clipped.

### 6.4 Three frontend strings were changed because the fix made them false

Not polish. `RiskFactorDetailPage.vue` said, under the number:

> *"Source: Savings accounts marked as emergency fund & your monthly expenditure"*

After this fix that sentence describes a rule the code no longer applies — the exact
W-0226 / W-0239 disease, where a reader checking the code against its own
documentation passes it. Also changed: the numerator's label (`emergency fund` →
`cash savings`), the dependants source line (now says "across your household"), and
the static explanation `what` string.

### 6.5 `DependantsReach` makes three decisions a naive union gets wrong

Each is in the class docblock with its reasoning; the short version:

1. **The viewer is not their own dependant.** A non-earning spouse flagged
   `is_dependent` on their partner's account is a genuine dependant *of that partner*.
   Reached from the other side, a plain union would have counted the reader as
   depending on themselves. Rows reached **through** the spouse that describe the
   spouse relationship are dropped; the user's own record of their partner is kept.
2. **A child both parents entered is one child.** Identity is `linked_user_id` where
   present, otherwise name + date of birth — the same key
   `UserProfileService::buildFamilyMembers()` already de-duplicates spouse children
   on. A row with neither keeps its own id as its key, so an unidentifiable row is
   never silently merged with another.
3. **The link must be LIVE.** `liveSpouseId()`, not raw `spouse_id`, which survives
   the partner deleting their account (CSJ decision D1/D2, 2026-08-19).

**No spouse-permission gate, deliberately.** `hasAcceptedSpousePermission()` governs
disclosing a partner's *financial* data. The count of children in a household is not
that, and the application already treats it as jointly known without a gate —
`ProfileCompletenessChecker::hasDependants()` reads the spouse's children to decide
whether the **user's own** profile is complete. Gating here would have made this the
one place in the application where a linked parent's children stop being theirs.

### 6.6 The "expenditure not recorded" branch changed behaviour, and it was a choice

Old behaviour: cash > 0 and no expenditure recorded → **"12 months"**, Upper-Medium.
That figure is invented. It is printed to the user as though measured, and it is not
derived from anything.

Routing to `EmergencyFundCalculator::calculateRunway()` returns `0.0` in that state,
which would have printed *"Less than 3 months emergency fund"* — the opposite lie, to
someone who might have £130,000 in cash. Neither is knowable, so the factor now says
**"Not calculated"** at level `medium`, following the rule **this class already
applies to an unknown age** ("Age not specified; a balanced approach is assumed").

No new convention was invented; an existing one was applied to a second unknown.

### 6.7 W-0273 was NOT already fixed upstream — the dispatch's premise was wrong, and it mattered

The dispatch said the upstream totals were already fixed by W-0238 and asked me to
confirm the calculator reads the corrected figures, then decide whether stored
`factor_breakdown` rows need recomputing or expiring.

**Measured before touching anything: it did not read them.** W-0238 fixed the
*agents*; `AutoRiskCalculator:129` ran its own `InvestmentAccount::where('user_id', …)`
sum. So `factor_breakdown` was not stale — it was being **recomputed wrong on every
page load**.

Two consequences for the receiver:

- **No migration is needed and none was written.**
  `RiskPreferenceService::getRiskProfile()` recalculates the factors live for display
  (`:216-227`), so the stored row never reaches the screen. It is an audit artefact
  that refreshes on the next write. Recomputing it would have been work with no
  user-visible effect; expiring it would have destroyed the audit trail of what was
  assessed when.
- **Had I taken the premise on trust and only checked the stored row, I would have
  found it "stale", written a recompute migration, and left the live defect in
  place** — a fix that measures as green and changes nothing.

### 6.8 What I did NOT do, and why

- **No commit, no PR, no deploy, no bundle rebuild.** Per the dispatch: build
  artefacts are the coordinator's.
- **No `/m` bundle rebuild is needed for this work.** `/m` has no risk screen; its
  savings runway comes from the shared endpoint and needs no new frontend code. The
  web SPA picks the Vue changes up from the running Vite on :5173.
- **No edit to `phpunit.xml` or `Pest.php`.** Tests ran on `laravel_testing_a`.
- **No persona row was written.** Every measurement above is a read; every test
  figure comes from factory fixtures.
- **`SavingsStore.md` and `SavingsStoreBoundaryTest`'s allowlist were left alone**
  even though this work makes `App\Services\Risk\AutoRiskCalculator` stale in both:
  it no longer references `SavingsAccount` at all. Editing shared boundary config
  while parallel batches run is a collision, not a fix. **Raised as W-0277.**

### 6.9 Assumptions made, stated plainly

- **A dependant recorded on a linked spouse's account is a dependant of both parents.**
  The schema offers no other reading, and `ProfileCompletenessChecker` already assumes
  it. A household with a genuinely one-sided dependant — a child from a previous
  relationship whom only one partner supports — cannot currently be expressed, and
  this change does not make that worse or better.
- **The savings module's definition is the one to converge on**, because it is what
  the dashboard, `/net-worth/cash` and `/m` all show. It was not independently
  re-derived from first principles; §6.1 records its known weakness.
- **`has_db_pensions` from the net worth response is the right disclosure trigger.**
  It answers "does this user hold a defined benefit scheme", which is exactly the
  question the £0 raises.

---

## 7. Verification — done, and what was read off the screen

| Layer | Status |
|---|---|
| Service-level, both accounts, real services, no mocks | **DONE** |
| Automated tests | **DONE** — §9, including two mutation runs proving the new suite can fail |
| Live browser, David (16), web | **DONE** |
| Live browser, Sarah (17), web, through the MFA gate | **DONE** — code fetched from the database, never asked for |
| Live browser, Sarah (17), `/m` | **DONE** — separate `/m` login + MFA |

### Read off the screen, `localhost:8000`, 2026-08-22

**David Jones (16)**

| Surface | Figure |
|---|---|
| `/risk-profile` | Emergency Fund **79.8 months · Upper-Med** · Dependants **2 · Lower-Med** · Capacity for Loss **45.1% · Medium** |
| `/risk-profile/factor/emergency_cash` | **£99,750 cash savings ÷ £1,250 monthly expenditure = 79.8 months**, "Source: All your cash savings, at your share of any joint accounts, & your monthly expenditure" |
| `/risk-profile/factor/capacity_for_loss` | **£172,500 investments + £500,000 pensions ÷ £1,489,500 net worth = 45.1%** |
| `/dashboard` | **79.8 / 6 months** · £99,750 · "Emergency fund on track" · Investment **£172,500** |
| `/net-worth/cash` | £25,000 + £2,250 (joint, his share of £4,500) + £22,500 + £50,000 = **£99,750** |

**Sarah Jones (17)**

| Surface | Figure |
|---|---|
| `/risk-profile` | Emergency Fund **25.3 months · Upper-Med** · Dependants **2 · Lower-Med**, *"Multiple dependants means financial stability is a priority"* · Capacity for Loss **17.9% · Medium** |
| `/risk-profile/factor/dependants` | "Dependants found **2** — **William** Child, **Charlotte** Child" (her husband's records, reaching her) |
| `/risk-profile/factor/capacity_for_loss` | **£132,500 + £0 pensions ÷ £739,280 = 17.9%**, with the disclosure rendered directly beneath the £0 |
| `/dashboard` | **25.3 / 6 months** · £31,030 · Investment **£132,500** |
| **`/m` dashboard** | **25.3 / 6 months** · £31,030 · "Emergency fund on track" · Investment **£132,500** |

**The acceptance criterion is met and was read, not inferred:** `/risk-profile`,
`/dashboard` and `/m` agree to the decimal on both accounts — 79.8 and 25.3.

Screenshots (repo root, matching this cycle's convention):
`W-0271-web-david-16-dashboard-79.8-months.png` ·
`W-0271-web-david-16-risk-profile-after.png` ·
`W-0272-web-sarah-17-risk-profile-after.png` ·
`W-0272-web-sarah-17-dependants-william-charlotte.png` ·
`W-0273-web-sarah-17-capacity-for-loss-db-disclosed.png` ·
`W-0271-m-sarah-17-dashboard-25.3-months.png` (retaken on `main-DTjymbsC.js`) ·
`W-0273-web-sarah-17-card-disclosure-unclamped.png` ·
`W-0274-web-sarah-17-savings-emergency-tab-still-zero.png`

### Found ON the screen, and NOT fixed — W-0274

The browser pass found what the arithmetic could not: **`/savings` → Emergency Fund
tab still reads "Months Runway 0.0", "Current Fund £0", "Priority: Build your
emergency fund"** — on both accounts, minutes after the same login's dashboard read
79.8 and 25.3 months. It is a **client-side** fourth implementation
(`resources/js/store/modules/savings.js:34-53`) filtering on `is_emergency_fund`,
carrying its own joint-share bug that charges the co-owner the primary owner's share.

**Raised as W-0274 at HIGH and deliberately not fixed** — it is savings frontend, not
this batch's scope. It is the clearest possible argument for browser verification:

> Six service-level measurements and sixteen feature tests all said W-0271 was
> complete, and it is for everything they cover — **a person opening the savings page
> still sees zero.**

A service-level consolidation cannot reach a fourth implementation that lives in
JavaScript. Nothing in the backend, the tests, or the payload was wrong; the browser
was the only instrument that could see it.

### The two re-reads — DONE, measured rather than eyeballed

Both were outstanding because the tab changed hands mid-batch. Both are now closed, and
neither was signed off by looking at it.

**1. The summary card's disclosure line — measured, not glanced at.** My own finding was
that a disclosure can be present, correct, and invisible, so the check asserts geometry:

| Element (Sarah's capacity-for-loss card) | `clientHeight` | `scrollHeight` | clipped | `webkitLineClamp` | `overflow` |
|---|---|---|---|---|---|
| ratio description | 32 | 32 | **no** | 2 | hidden |
| disclosure | 32 | 32 | **no** | **none** | **visible** |

Re-measured at **390 × 844**: the disclosure wraps to four lines (`clientHeight` 64 =
`scrollHeight` 64), `white-space: normal`, still uncut. The appended version measured
**48 against 32** — clipped — which is what prompted the change.

**The negative case, asserted rather than assumed.** Across all nine of Sarah's factor
cards: **exactly one** carries a disclosure node, it is `capacity_for_loss`, and
**zero** cards render an empty one. A hidden-but-present or empty-but-rendered node
fails that check; "I did not notice one" would not. David's null case is covered by test
(`disclosure` is null, and exactly one factor of nine is non-null for a Defined Benefit
holder).

**2. `/m` on the rebuilt bundle — retaken.** Confirmed serving **`main-DTjymbsC.js`**,
not the 20:26 build my first reading used. Sarah's `/m` dashboard: **25.3 / 6 months ·
£31,030 · £132,500**, identical to her web risk page.

**Identity was verified independently of the figures**, because the two surfaces keep
separate token stores and the session had web as Sarah and `/m` as David at handover.
`fynla-state.auth.user` reads **id 17, sarah.jones@example.com**. Recognising the
numbers as "hers" would have been circular — the numbers are what was under test.


### Environment note for whoever verifies next

Released to team-lead by explicit handshake, left on `/dashboard` at **1440 × 900**.
**Web and `/m` are BOTH signed in as Sarah Jones (17)** — I logged `/m` in as Sarah to
retake the bundle reading, so the split identity the session arrived with (web Sarah,
`/m` David) no longer applies. Password `Password1!`; MFA codes from
`email_verification_codes`, fetched from the database, never requested from a person.

**The two surfaces keep separate token stores**, so who you appear to be on one says
nothing about the other. Check identity on each before believing a figure —
`fynla-state.auth.user` on both.

---

## 8. Files this batch owns

**New:** `app/Services/Shared/DependantsReach.php` ·
`tests/Feature/Risk/RiskFactorsReachTheHouseholdTest.php`

**Modified — backend:** `app/Services/Risk/AutoRiskCalculator.php`

**Modified — frontend (web):** `resources/js/views/Risk/RiskFactorDetailPage.vue` ·
`resources/js/components/Risk/FactorBreakdownCard.vue`

**Consumed, not modified:** `app/Constants/PensionDisclosure.php` — another agent's
file and the one home for the Defined Benefit sentence. Read, never re-typed.

**Modified — `/m`:** none. `/m` has no risk-profile screen (W-0279); its savings
runway reads the shared endpoint and needed no change.

**Modified — tests:** `tests/Unit/Services/Risk/AutoRiskCalculatorTest.php` ·
`tests/Unit/Services/Investment/AutoRiskCalculatorEnhancementTest.php`

---

## 9. Test evidence

| Run | Result |
|---|---|
| `tests/Feature/Risk/RiskFactorsReachTheHouseholdTest.php` (new) | **16 passing** |
| Risk families after the disclosure rework (`Feature/Risk`, `Unit/Services/Risk`, enhancement) | **79 passing** (343 assertions) |
| `npx vitest run tests/frontend/components` | **542 passing**, 41 files |
| `tests/Unit/Services/Risk`, `tests/Feature/Risk`, `tests/Unit/Services/Investment`, family-member alias | **360 passing** (1,088 assertions) |
| `tests/Unit/Services/Savings`, net worth, `tests/Feature/NetWorth`, `tests/Unit/Agents`, `tests/Unit/Services/Shared` | **204 passing** (877 assertions) |
| `--testsuite=Architecture` | **149 passing** (4,296 assertions), 28 deprecated, 1 skipped |
| `tests/Architecture/StoreBoundary` | **20 passing** |
| `./vendor/bin/pint` on every touched path | passed |

Run with `DB_DATABASE=laravel_testing_a`, then re-run on **`laravel_testing_j`** at the
end — see below.

**A final run on `_a` came back 71 failed / 8 passed and was NOT a real failure.** The
fingerprint is `tests/CLAUDE.md` §5 exactly: `SQLSTATE[42S02] ... Unknown table` on
`drop table`, during `RefreshDatabase` teardown, with almost no assertions — two
processes migrating one schema. Another batch had started using `_a`. Re-run in
isolation on `laravel_testing_j`: **79 passed, 343 assertions.**

Recorded because the §5 instinct is the whole point: **re-run the file on its own before
believing it.** A real failure reproduces; contention does not. Thirty seconds of
re-running instead of an hour diagnosing a phantom regression in my own work — and had
I taken the red at face value, the honest-looking move would have been to "fix"
something that was never broken.

### The four variants, applied rather than cited

**Mock.** The existing unit suite mocked `NetWorthService` to return
`['net_worth' => N]` and nothing else. That mock is now a full net-worth shape — but
a mock cannot prove a breakdown is reach-complete, so **the reach and fraction claims
are tested nowhere near it**, against real records, in the new feature suite. The
mock file now says so in a docblock, pointing at the file that does the proving.

**Fixture.** The four capacity-for-loss tests created real `InvestmentAccount` and
`DCPension` rows that, after the fix, **nothing reads**. Left in place they would
have implied the test exercised a path it does not. They were deleted and the figures
moved into the mock, with the reason recorded in the test.

**Collision — the one that would have cost a day.** The three emergency-cash unit
tests each created a savings account **flagged** `is_emergency_fund`. Flagged, the
old rule and the new rule return the **same number**, so all three would have passed
identically before and after the fix and proved nothing about it. Every one is now
`'is_emergency_fund' => false`, which is the state the defect actually occurs in:
0 months under the old rule, the right answer under the new one.

**Symmetry.** The persona splits its joint accounts 50/50, which makes a primary
owner's share and a co-owner's share the same number. **Every fixture in the new
suite is 75/25 or 70/30**, and every share assertion also states what the defect
would have produced (`->not->toBe(24000.0)`, `->not->toBe(0.0)`, `->not->toBe(200000.0)`).

**Payload width** is not applicable: this batch touches no save path and posts no
form. Nothing here changes what a request body must contain.

### Proof the new suite can fail — two mutations, run and reverted

A green suite over a fix proves nothing unless breaking the fix breaks the suite.

| Mutation | Result |
|---|---|
| `DependantsReach` reaches only `[$user->id]` (the defect restored) | **3 of 5 dependants tests fail** |
| `->unique(...)` removed from the reach | **the duplicate-child test fails** |

Both reverted; `php -l` clean and the full suite re-run green afterwards.

The second mutation is worth noting for a different reason: under the **first**
mutation, the duplicate-child test still passed — with own-only reach each parent
sees their own row and the count is 1 either way. That is the Collision variant
appearing inside my own suite, and it is why the two properties are tested by
separate cases that each fail for their own reason.

---

## 10. The `where('user_id', $user` census — requested by team-lead

`grep -rn "where('user_id', \$user" app/Services app/Agents` returns **486 hits
across 128 files**. The raw count is not the finding: most are correctly user-scoped
(a user's own profile, their own pension — pensions are individual by law — their own
ISA subscriptions, write paths, tier caps).

**The finding is the subset that queries a model which can be SHARED.** Filtered to
the twelve models carrying `joint_owner_id`, plus `FamilyMember` and
`LifeInsurancePolicy` (shared through `users.spouse_id` instead):

| Model | `user_id`-only sites in services/agents |
|---|---|
| `InvestmentAccount` | **59** |
| `LifeInsurancePolicy` | 30 |
| `FamilyMember` | 20 |
| `Goal` | 15 |
| `Liability` | 8 |
| `SavingsAccount` | 6 |
| `Property` | 6 |
| `Mortgage` | 6 |
| `BusinessInterest` / `Chattel` | 3 each |
| `CashAccount` / `LifeEvent` | 2 each |

### Every line in this census is a code-read HYPOTHESIS until measured — and one of mine was wrong

**Read this before acting on anything below.** A `grep` finds a shape, not a defect. I
published three "sharpest" findings from reading code; team-lead escalated the first to
a priority batch with a tax-compliance review attached; and **when I finally measured
it, it was false.** The correction stays here in full, because how it failed is worth
more than the finding would have been.

**1. `IHTCalculationService::getCurrentInvestmentValue()` — I claimed a household
DOUBLE COUNT. THERE IS NONE. The claim was wrong.**

I wrote that summing the user's investments at 100% and then the spouse's at 100%
counts a joint account from both sides — "£190,000 of a £95,000 record", inside an
Inheritance Tax figure.

**`where('user_id', $user->id)` never matches `joint_owner_id`.** A row has exactly one
`user_id`, so the two queries are **disjoint**. Measured on the live persona, sharing
on:

| | Figure |
|---|---|
| `getCurrentInvestmentValue(David, Sarah, sharing on)` | £305,000 |
| `getCurrentInvestmentValue(Sarah, David, sharing on)` | £305,000 |
| Correct household total, both members at their share | **£305,000** |
| Full value of every record | **£305,000** |

**The household figure was already correct.** No inflated liability; nothing for a tax
review to find.

**What was actually wrong** — and another agent has since fixed it, whose docblock puts
it better than my grep did: *"those two queries are disjoint, so nothing was ever
counted twice — but a member's own account was taken whole regardless of who else owns
it, and their share of an account the OTHER member records was not taken at all.
Married with sharing on, the two errors cancel; with sharing off they do not."* The same
reach/fraction disease as the rest of this cycle, with the two errors **cancelling** at
household level. The live exposure is a household with sharing **off**.

**Why I got it wrong, and it is the trap this very document lectures about.** At the
level I looked, **the right answer and the wrong answer were the same number** —
£305,000 either way. That is §9's **Collision** variant applied to an analysis instead
of a test. I wrote three paragraphs on it and then walked into it, because I never
asked the question the variant demands: *if the mechanism I am accusing did nothing at
all, would the figure differ?* Six lines of `tinker` answer that. **A claim from reading
code is a hypothesis; a claim from running it is a finding.** Nothing in a census is the
second until somebody does the second.

**2. `EstateAssetAggregatorService::getExistingLifeCover():277` — MEASURED, confirmed,
unfixed.** Policy 7 is David's, `joint_life = true`, £500,000. `getExistingLifeCover`
returns **£0 for Sarah**, while `LifeCoverReach` correctly reports her covered for
**£500,000**. Her estate plan is built on the premise that she has no life cover, on the
one product whose purpose is covering them both. This is the exact reach `LifeCoverReach`
was built for (W-0186), and it deserves the priority slot the first entry was wrongly
given. Same module as **W-0278**.

**3. `EstateActionDefinitionService::estateValue():353-375` — re-read, unchanged, NOT
measured.** Investments, cash accounts, estate assets and life cover at 100%, while
property and savings are deliberately filtered **back down** to primary-only after a
joint-aware store read. Two ownership semantics in one method, one explicit and one
accidental. It needs a decision about what an estate contains before it needs a sweep.

**Also confirmed while comparing implementations:** `LifeCoverReach` uses raw
`$user->spouse_id`, not `liveSpouseId()`, so it keeps reaching a deleted partner's
policies — the trap `DependantsReach` avoids. Filed as **W-0278**.

Full item: **W-0280**. The tester predicted more sites existed; there are far more than
three, and `InvestmentAccount` at 59 is still the sensible place to start — **as a place
to look, not as 59 known defects.** After the failure above, that distinction is the
most valuable thing this census carries.

---

### Raised while working — seven items, all inside my block

| Item | Severity | Why it is not folded in |
|---|---|---|
| **W-0274** two more answers to the emergency-fund figure, one live on `/savings` | high | Savings frontend + savings actions. Found by the browser pass, not by the tests. |
| **W-0275** eight consumers still ask "who depends on this user" with the old query | medium | Protection, Fyn's memory facts, savings, estate, the advice prompt. Each needs routing deliberately — intestacy wants children, not dependants. |
| **W-0276** runway counts cash the user cannot reach | low | Product call: narrowing "available" moves the dashboard headline for every user. |
| **W-0277** the SavingsStore allowlist now names a class that does not touch the model | low | Shared boundary config; editing it mid-run is a collision. |
| **W-0278** `LifeCoverReach` reads a deleted partner's policies | medium | Found by reading it as the model for `DependantsReach`. Not raised by any tester. |
| **W-0279** `/risk-profile` has no `/m` counterpart | low | Rule 19 gap in the product, not in these items. `/m` shows the risk level with no route to the nine factors behind it. |
| **W-0280** the `where('user_id', $user` census | high | Contains an inheritance tax **double count** (§10) that is a different failure from the rest. |

## 11. Environment

- Branch `dev`, shared working tree, other agents editing concurrently.
  **No commits, no PR, no deploy, no bundle rebuild.**
- Tests: `DB_DATABASE=laravel_testing_a ./vendor/bin/pest <paths>` for most of the
  batch; **`laravel_testing_j` at the end**, after another batch started sharing `_a`
  and produced §5 contention. `phpunit.xml` and `Pest.php` untouched — shared config
  while batches run.
- **`app/Services/Risk/RiskPreferenceService.php` was modified by another agent**
  during this batch (the `has_custom_risk` retirement — two additive methods,
  `getProductRiskOverride` and `resolveProductRiskLevel`). It is inside the directory
  this batch was scoped to, but it touches nothing this batch changed: purely additive,
  no existing method altered, and `getRiskProfile()` — the path the risk page reads —
  is untouched. No conflict; recorded so the overlap is not discovered later as a
  surprise.
- Local dev server on `localhost:8000`, Vite live on `:5173`. **No `vite build` was
  run** — it would delete `public/hot` and break the running server for every other
  agent.
- Persona household David Jones id 16, Sarah Jones id 17 on the local `laravel`
  database — **read-only throughout**. No row was created, updated, touched or
  deleted.
- The Playwright browser is shared and was **not** closed. It was released without
  being used; see §5 and §7.
