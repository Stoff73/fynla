---
id: F-0022
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/08-process.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-22T00:00:00Z
status: active
---

# F-0022 — Cycle 4: the dashboard's second answer, and the cache that preserved it

**Agent:** build-lead (`cycle4-dashboard`) · **Branch:** `dev` (shared working tree)
**Board items:** W-0238, W-0239 · **ID block:** W-0241 – W-0250
**Number and ID block issued by team-lead.**

**Predecessors, read before touching anything here:**
`F-0019-cycle2-ownership-applied-one-side-only.md` — the direct predecessor; it
established the **reach / fraction** vocabulary this batch is written in and built
`CrossModuleAssetAggregator::calculateLiabilityTotals`.
`F-0002-batch-a-ownership-net-worth.md` — the single-record write rule and the two
homes, `SharedOwnership` and `ownership.js`.
Board items **W-0226**, **W-0187**, **W-0203**, **W-0015** — the same family.

---

## 1. The principle

**A figure and its own contradiction were being returned in the same HTTP response,
and a day-long cache made the disagreement survive the fix.**

Two items, and they are not independent: **W-0239 had to be fixed first because
W-0238 could not otherwise be verified.** Reading a dashboard while a 24-hour blob
can be served tells you what was true yesterday. That is not a sequencing
preference — it is the difference between measuring a fix and signing off a
coincidence.

### W-0238 in the predecessor's vocabulary

F-0019's principle holds exactly: *every derived figure must be computed from a
reach-complete set, at the user's fraction.* Both halves were missing, in the same
module, in opposite directions:

| Failure | Where | Consequence |
|---|---|---|
| **Fraction** | `PortfolioAnalyzer::calculateTotalValue`, `SavingsAgent`'s `$accounts->sum('current_balance')` | the recorder charged with the whole of a joint account |
| **Reach** | `InvestmentAgent`'s `where('user_id', …)`, `User::savingsAccounts()` | the co-owner shown none of it |

So the same £4,500 joint current account read as £4,500 to David and £0 to Sarah,
while `net_worth…assets.savings` in the same payload read £2,250 to each.

**Neither needed new arithmetic.** `CrossModuleAssetAggregator::calculateCashTotal()`
and `calculateInvestmentTotal()` already did both, and are what `/net-worth` reads.
The agents simply were not routed to them. **The count of implementations went
down**: `PortfolioAnalyzer::calculateTotalValue` is deleted.

### The third row is a different failure and worth naming separately

Sarah's retirement card is not a share problem. **The card could only render a pot,
and her provision is an income.** A defined benefit scheme has no balance. Three
mechanisms each returned zero for her at once — the agent refused to answer without
a `retirement_profiles` row, the aggregator's fallback read a column that does not
exist, and the two frontends could only format a currency amount. Fixing any one of
them alone would still have shown her £0.

### W-0239 — the comment was the defect

`CACHE_TTL = 86400; // 24 hours — invalidated on data change`, five lines below a
class docblock reading *"Uses a 5-minute cache per user."*

**This is W-0226's lesson again and it is worth stating as a rule: where code and
its own documentation disagree, the documentation is load-bearing, because it is
what the next reader checks the code against.** Nobody audited the invalidation
because the comment said there was some. There was — reached by three hops of
coincidence, and missing the two things that mattered most.

---

## 2. Prior art

Checked 2026-08-22 across `registry/capabilities.md`, the code, custom artisan
commands, open PRs and in-flight branches, the vault, and `.claude/skills|agents`.

| Instance | Prior art found | Outcome |
|---|---|---|
| W-0238 savings total | `CrossModuleAssetAggregator::calculateCashTotal()` — reach-complete, share-correct, already read by `/net-worth` | **route** |
| W-0238 investment total | `calculateInvestmentTotal()`, same | **route** + delete the superseded `PortfolioAnalyzer::calculateTotalValue` |
| W-0238 per-record share | `CalculatesOwnershipShare` (F-0002's home) | **extend** — two collection-level methods added to it, no new home |
| W-0238 retirement headline | none — web and `/m` each had their own fallback chain, and they had drifted | **build**, one home, both surfaces |
| W-0239 invalidation | `CacheInvalidationService` — already knew `mobile_dashboard_{id}` | **extend** — one observer calls it; three observers deleted |

**"Build a parallel one because the existing one is awkward" was available twice and
declined twice.** `PortfolioAnalyzer::calculateTotalValue` takes a `Collection`, so
it cannot know whose portfolio it is and cannot apply a share *even in principle* —
the temptation was to add a `$userId` parameter. Routing to the aggregator and
deleting it was the smaller change and left one home instead of two.

---

## 3. Constraints honoured

- **Rule 6** — single record; nothing duplicated per owner. The joint savings row
  53 stays one row; row 29 is soft-deleted and correctly invisible.
- **Rule 19** — web and `/m` named individually throughout; iOS parity filed as
  W-0243 rather than assumed or skipped.
- **Rule 20** — the endpoint is genuinely shared, so the backend fix is one fix for
  three surfaces. Where a rule was duplicated client-side (the retirement headline)
  it was consolidated as part of this work, not deferred.
- **W-0228 respected** — nothing here assumes a shared record's
  `ownership_percentage` is 50. Every path reads the stored value through
  `calculateUserShare`.
- **A third party is not a spouse.** `calculateUserShare` returns 0.0 for anyone who
  is neither party, so a non-user co-owner's share reduces the figure without being
  credited to anybody.
- Rules 9 / 12 / 15 — no acronyms, no scores, no icons added.

---

## 4. Status — BOTH DONE

| Item | Outcome | One home |
|---|---|---|
| **W-0239** the cache | **DONE** · `handoff` → quality-lead | `CacheInvalidationService`, called by `UserDataCacheObserver` |
| **W-0238** the module cards | **DONE** · `handoff` → quality-lead | `CrossModuleAssetAggregator` (totals) · `CalculatesOwnershipShare` (shares) · `retirementHeadline.js` (the card's headline rule) |

### Mechanisms answering each question: before and after

| Question | Before | After |
|---|---|---|
| What is this user's cash worth | 2 (`SavingsAgent`, the aggregator) | **1** |
| What is this user's portfolio worth | 2 (`PortfolioAnalyzer`, the aggregator) | **1** |
| Which number does the retirement card lead with | 2, **already drifted** (web vs `/m`) | **1** |
| A record changed — what is stale | 3 observers + 1 accidental agent hop | **1** |

**Four fewer implementations than we started with, and one new file that replaces
two.**

### Measured, both accounts, after clearing every per-user cache key

| | David (16) shown → now | Sarah (17) shown → now |
|---|---|---|
| SAVINGS | £102,000 → **£99,750** | £28,780 → **£31,030** |
| INVESTMENT | £220,000 → **£172,500** | £85,000 → **£132,500** |
| RETIREMENT | £500,000 pot (unchanged) | £0 "Plan your retirement" → **£35,000/year "Guaranteed retirement income"** |
| `modules` vs `net_worth` in one response | disagreed | **agree to the penny** |

Live browser, desktop web on `localhost:8000`, both logins driven through the MFA
gate. Screenshots `W-0238-web-david-16-after.png`, `W-0238-web-sarah-17-after.png`.

`/m` at `localhost:8000/m/app/dashboard` reads David's corrected £99,750 / £172,500
from the shared endpoint — see §6.3 for the one thing `/m` cannot yet show.

---

## 5. In flight

**Nothing.** Every edit is applied, linted and covered.

---

## 6. What the receiver needs, and would not otherwise know

### 6.1 The share view hands the analyzers CLONED models, and they must never be saved

`CalculatesOwnershipShare::atUserShare()` returns `clone`d models whose value column
holds **this user's share**, not the stored balance. That is how the liquidity
ladder, the deposit-protection exposure and the rate comparison became share-correct
without each of them learning about ownership.

**Saving one would write a half-balance over a whole one.** Every current consumer
was checked and is a pure reader — `LiquidityAnalyzer`, `RateComparator`,
`FSCSAssessor`, `PortfolioAnalyzer`, `DiversificationAnalyzer`, `FeeAnalyzer` contain
no `save`, `update`, `delete`, `forceFill`, `increment` or `decrement`. **A future
consumer that persists would corrupt data silently.** The constraint is stated in
the method's docblock; it is not enforced by the type system, and enforcing it
(a read-only value object) would mean rewriting six analyzers' type hints.

`atUserShare` deliberately preserves the caller's collection class — an Eloquent
collection maps to an Eloquent collection, which is what `LiquidityAnalyzer`
type-hints. A bare `collect()` returns a base collection and TypeErrors.

### 6.2 Holdings are scaled by their ACCOUNT's share, and the two figures are scaled together

A holding carries no ownership columns; the account is what is jointly held. So
`InvestmentAgent::holdingsAtUserShare()` scales `current_value` **and** `cost_basis`
by the same fraction. That is deliberate: scaling only the value would change the
gain, and the gain is what the capital gains position is read from. A share of a
gain is the gain of a share.

`quantity` is **not** scaled — half a unit is not a fact about anything, and nothing
in the read path derives a price from value ÷ quantity.

`userShareFraction()` asks the one home for the share of a **unit-valued probe**
rather than dividing the share by the value. Dividing breaks on an account valued at
zero; the probe cannot. It mirrors the business-interest marker fields so that
rule still fires.

### 6.3 `/m` cannot show the guaranteed-income headline until the bundle is rebuilt

`/m` serves `public/m-build/` and **never** Vite —
`resources/views/mobile-app.blade.php:9` picks the build directory unconditionally.
The local bundle dates from **2026-08-21 13:45**, before this work.

Consequences, precisely:

- David's `/m` cards are **already correct** (£99,750 / £172,500) because those
  figures come from the endpoint, and his retirement card takes the pot path the
  old code also handled. Verified in the browser.
- **Sarah's `/m` retirement card will still read £0** until `public/m-build/` is
  rebuilt, because the `guaranteed_income` rule is new frontend code.
- The `/m` code path itself is covered:
  `tests/frontend/mobile/Dashboard.test.js` drives `Dashboard.computed.finances`
  directly and asserts `£35,000/year` / "Guaranteed retirement income".

**The rebuild is the coordinator's — requested, not run.** Do not treat Sarah's `/m`
retirement card as a regression before the bundle is rebuilt.

### 6.4 Three observers were deleted, and nothing they did was lost

`NetWorthCacheObserver`, `RecommendationCacheObserver` and `GoalCacheObserver` are
gone. Each was checked key by key against `CacheInvalidationService`:

| Deleted observer | Its keys | Where they are now |
|---|---|---|
| `NetWorthCacheObserver` | `net_worth:user_{id}:date_{today}` | **added** to the service — it was the only key the service lacked |
| `RecommendationCacheObserver` | `v1_{agent}_{id}_{suffix}` for 3–6 agents + coordinating | the service's `AGENTS` × `AGENT_SUFFIXES` loop, with `taxoptimisationagent` **added** — it was only reachable through the deleted observer |
| `GoalCacheObserver` | `goals_projection_{id}_{scope}` | already in the service |

`RecommendationCacheObserver` instantiated three to six agents **per model write**,
each with a dozen constructor dependencies, to end up calling `Cache::forget`.
Replacing that with a direct service call makes writes cheaper, not dearer, which
matters because the observer now also does a spouse lookup.

### 6.5 The spouse hop is a behaviour change, and it is the point

The observer follows `user_id`, `joint_owner_id` **and each of their spouses**.
That last one costs one indexed `users` query per financial write, and it is what
makes the reported symptom impossible: `life_insurance_policies` has no
`joint_owner_id` at all, so a joint-life policy reaches the other life only through
`users.spouse_id` (`LifeCoverReach`, W-0186). Following the two owner columns alone,
David could change the policy covering Sarah's life and her protection figure would
stay wrong for a day. **Verified live:** touching policy 7 on David's account clears
`mobile_dashboard_17`.

### 6.6 `User` is deliberately NOT observed

Its rows are written on every login, token refresh and verification code. Observing
it would clear sixty-odd keys per sign-in to catch profile changes that
`UserProfileController` already invalidates explicitly. Stated in the observer's
docblock so it does not read as an oversight and get "fixed".

### 6.7 The TTL was not shortened

Per the dispatch. 86,400 seconds remains, now labelled a backstop, with the class
docblock corrected to describe the mechanism that actually provides freshness. If
the backstop is later judged too long that is a separate, deliberate decision.

Note for context: the deploy procedure already runs `php artisan cache:clear`
(`deploy/DEPLOY.md:56,111`), so **code-change staleness is handled on csjones and
prod and is a local-development exposure only.** The 21-hour stale dashboard was
observed locally, where nothing clears the cache after an edit.

### 6.8 The retirement card reads pension records only when the agent declines to answer

`extractRetirementSummary()` takes `pot_value` from the agent's own
`summary.current_dc_value` **whenever the agent answered**, and reads the pension
store directly only when there is no `retirement_profiles` row and the agent returns
nothing at all. This is not a second mechanism by preference — it is the workaround
for **W-0244**, and it should be deleted when that is fixed.

`guaranteed_income` always comes from
`PensionProjector::projectTotalRetirementIncome()`, the same home `RetirementAgent`
reads.

**Basis caveat, stated at the call site** per `app/Services/CLAUDE.md`: that
projector returns the defined benefit component **nominal at retirement** and the
State Pension component **in today's money**, and `guaranteed_income` sums them.
Every existing consumer of that projector already sums them the same way, so this
does not introduce the mixing — but it does not fix it either, and it is invisible
on this persona (Sarah has no State Pension record, David has no defined benefit
scheme, so neither user has both components).

### 6.9 Assumptions made, stated plainly

- **A defined benefit scheme's `accrued_annual_pension` is a retirement figure, not
  income today.** W-0036 and `DBPension::isInPayment` are explicit about this, and
  the card is labelled "Guaranteed retirement income" accordingly. It is not counted
  as current income anywhere by this work.
- **Sarah's £35,000 is the projector's `db_annual_income`, not the raw column.** They
  are equal here only because her scheme has `inflation_protection = 'none'`. A
  scheme with revaluation would show the revalued figure, which is the right one for
  a retirement card and differs from what the retirement page's client-side
  computed shows. Flagged rather than reconciled — reconciling them is W-0245's
  territory.
- **A user with pension records but no target has "active" retirement provision.**
  The previous behaviour returned `not_configured` beside a non-zero pot, which is
  self-contradictory. One existing test asserted that contradiction and was updated,
  with the reason recorded in the test.

### 6.10 Live persona rows were touched, and how

Two `->touch()` calls on `localhost` to prove invalidation: savings account 53 and
life policy 7, both on David's account. **`updated_at` only — no value, no
ownership field, no row created or deleted.** Every test figure comes from factory
fixtures in `laravel_testing_b`, never from the persona.

**A state pension row for David (id 15, £11,502.40) appeared at 19:43 during this
session**, created by another agent or the tester, not by me. It is why his
`guaranteed_income` reads £11,502.40 rather than £0. His card is unaffected — he has
a pot, so the pot leads.

---

## 7. Environment

- Branch `dev`, shared working tree, other agents editing concurrently.
  **No commits, no PR, no deploy, no bundle rebuild.**
- Tests: `DB_DATABASE=laravel_testing_b ./vendor/bin/pest <paths>`.
  `phpunit.xml` and `Pest.php` untouched — shared config while batches run.
- Persona household David Jones id 16, Sarah Jones id 17 on the local `laravel`
  database; read-only apart from §6.10.
- Local dev server on `localhost:8000` with **Vite live on :5173**, so the web SPA
  picks up frontend changes without a build. **No web `vite build` was run** — it
  would delete `public/hot` and break the running server.
- The Playwright browser is shared. It was **not** closed.

---

## 8. Files this batch owns

**New:** `app/Observers/UserDataCacheObserver.php` ·
`resources/js/utils/retirementHeadline.js` ·
`tests/Feature/Cache/DerivedFiguresInvalidateOnDataChangeTest.php` ·
`tests/Feature/Dashboard/ModuleTotalsMatchNetWorthTest.php` ·
`tests/frontend/utils/retirementHeadline.test.js`

**Deleted:** `app/Observers/NetWorthCacheObserver.php` ·
`app/Observers/RecommendationCacheObserver.php` ·
`app/Observers/GoalCacheObserver.php` ·
`PortfolioAnalyzer::calculateTotalValue()` and its three tests

**Modified — backend:** `app/Agents/SavingsAgent.php` ·
`app/Agents/InvestmentAgent.php` · `app/Agents/CoordinatingAgent.php` (docblock) ·
`app/Traits/CalculatesOwnershipShare.php` ·
`app/Services/Cache/CacheInvalidationService.php` ·
`app/Services/Mobile/MobileDashboardAggregator.php` ·
`app/Services/Investment/PortfolioAnalyzer.php` ·
`app/Providers/EventServiceProvider.php` ·
`app/Services/Stores/PropertyStore.php` (docblock) ·
`app/Services/Stores/PensionStore.md`

**Modified — frontend:** `resources/js/views/GamifiedDashboard.vue`

**Modified — `/m`:** `resources/mobile/views/Dashboard.vue`

**Modified — tests:** `tests/Unit/Agents/SavingsAgentTest.php` ·
`tests/Feature/Stores/SavingsReadConsumerParityTest.php` ·
`tests/Unit/Services/Mobile/MobileDashboardAggregatorTest.php` ·
`tests/Unit/Services/Investment/PortfolioAnalyzerTest.php` ·
`tests/frontend/mobile/Dashboard.test.js` ·
`tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php`

---

## 9. Test evidence

| Run | Result |
|---|---|
| `tests/Feature/Cache/DerivedFiguresInvalidateOnDataChangeTest.php` | **8 passing** |
| `tests/Feature/Dashboard/ModuleTotalsMatchNetWorthTest.php` | **8 passing** |
| `tests/frontend/utils/retirementHeadline.test.js` | **6 passing** |
| Agents, traits, investment, savings, observers (unit) | **448 passing** (1,310 assertions) |
| Architecture + all store boundary suites | **345 passing**, 28 deprecated, 1 skipped |
| Dashboard, cache, mobile, net worth, shared | **186 passing** |
| `Unit/Services/Mobile` | **106 passing** |
| Investment, savings, agents, plans, retirement (feature+unit) | **183 passing** |
| Investment, savings, estate, protection, goals, api | **1,193 passing** (6,387 assertions) |
| Fyn, onboarding, gamification | **600 passing**, 3 skipped |
| Full vitest suite | **1,142 passing**, 112 files |
| `./vendor/bin/pint` on every touched path | passed |

**Three tests were changed rather than made to pass, each with the reason recorded
in the test file:**

1. `MobileDashboardAggregatorTest` — "retains the known pension pot when retirement
   goals are not configured" asserted `not_configured` **beside a pot of £47,500**.
   It was pinning the contradiction. Now asserts `active`.
2. `MobileDashboardAggregatorTest` — the mocked-analysis pot test; `pot_value` still
   follows the agent's summary whenever the agent answers, so this passes unchanged
   after §6.8's correction.
3. `tests/frontend/mobile/Dashboard.test.js` — pinned the `/m`-only reach into
   `net_worth…assets.pensions`, which is the drift W-0245 is about. Rewritten around
   the payload the backend actually sends, plus a new case for the guaranteed-income
   headline.

**None of the three was a red test made green.** Each asserted a behaviour this work
deliberately changed, and each now asserts the new one explicitly.
