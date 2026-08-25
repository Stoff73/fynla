---
id: F-0024
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/08-process.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-22T00:00:00Z
status: active
---

# F-0024 — Cycle 4: a projection that was not a projection of anything

**Agent:** build-lead (`fix-cycle4-projection`) · **Branch:** `dev` (shared working tree)
**Board items:** D-20, D-21, W-0217 → **W-0251 – W-0259** · **ID block issued by team-lead.**

**Predecessors, read before touching anything here:**
`F-0018-cycle2-projection.md` — established *a rule of thumb is evidence about people in
general; a recorded figure is evidence about this person, and it wins.* That principle
decides §4 below.
`F-0019-cycle2-ownership-applied-one-side-only.md` — the **reach / fraction** vocabulary.
§5 is a pure reach failure in that vocabulary.
`F-0022-cycle4-dashboard-module-totals-and-cache.md` — the immediately preceding cache
consolidation. §1 is the same disease one layer down, and deliberately did **not** extend
`CacheInvalidationService`; see §2.

**Not self-certified.** No evidence pack here — Quality writes that (`08-process.md` §2.4).

---

## 0. The sentence that matters most

> **The cache key named who was asking and over what horizon. It did not name a single
> thing that determines the answer.**

`user_{id}_portfolio_{years}y_e{eventHash}` — user, horizon, life-event hash. Not the
capital. Not the contributions. Not the expected return. Not the volatility. Not the
iteration count.

So a simulation of **£47,500 at 6.5%**, written at 20:11 on 21 August, was still being
served at 18:56 on 22 August against a portfolio of **£172,500 at 7.07%** — and no change
the user could make to their own data would dislodge it, because none of their data was in
the key.

**Every figure in the tester's D-20 table reproduces exactly from that cache**, which is
what proves the diagnosis rather than merely supporting it:

| Cached key | `start_value` | p20 extracted | Tester saw on screen |
|---|---:|---:|---:|
| `user_16_portfolio_5y_ed1a8c191` | £47,500 | **£4,650** | **£4,650** |
| `user_16_portfolio_10y_ed1a8c191` | £47,500 | **£217,451** | **£217,451** |
| `user_16_portfolio_20y_ed1a8c191` | £47,500 | **£528,482** | **£528,482** |
| `user_16_portfolio_30y_ed1a8c191` | £47,500 | **£767,649** | **£767,649** |
| `user_16_account_26_10y` | £95,000 | **£325,309** | **£325,309** |
| `user_17_portfolio_10y_enoevents` | £85,000 | **£316,777** | **£316,777** |

**The tell was in the table all along and is worth keeping as a diagnostic habit:** the
four horizons implied −58%, +2.34%, +5.76% and +5.10% a year. *One* portfolio cannot
produce four mutually inconsistent rates. Four horizons cached at four different moments,
from four different input states, can — and only that can. **When several figures that
must share a cause disagree with each other rather than with reality, suspect the store,
not the model.**

The £4,650 itself: £47,500 of capital, £396/month, less £55,000 at year 2 and £25,000 at
year 4 — the portfolio floors at zero in year 2 and the reading is one year of
contributions. Arithmetically impeccable, about a portfolio that no longer existed.

### Why the pension projection was healthy and the investment one was not

The tester noticed the pension projection behaved and the investment one did not, and
correctly could not say why. This is why — `RetirementProjectionService.php:109`, already
in the codebase, with the reasoning already written in its comment:

```php
// Cache key includes event hash AND simulation inputs so cache is self-invalidating
$inputHash = md5("{$totalCurrentValue}:{$totalMonthlyContribution}:{$expectedReturn}:{$volatility}");
```

**Someone had already solved this exact problem, in the same cache table, and the
investment path never learned about it.** That made the fix a *route/extend*, not a build.

---

## 1. Prior art

`prior_art_checked: 2026-08-22` · `prior_art_found: [RetirementProjectionService inputHash,
MonteCarloEngine, CalculatesOwnershipShare, HasJointOwnership, CacheInvalidationService,
ContributionEstimatorService]` · `prior_art_outcome: extend`

Six sources. What it found and what it changed:

| Instance | Prior art | Outcome |
|---|---|---|
| Cache identity | `RetirementProjectionService`'s two hand-rolled `$inputHash` lines | **extend** — moved into `MonteCarloSimulator::simulate()`, the one place that owns the cached artefact. Both hand-rolled copies deleted. |
| Probability bands | two near-identical private `extractProbabilityBands()` + `blendValue()` + `getPercentileValue()`, investment and retirement | **extend** — one public `MonteCarloEngine::extractProbabilityBands()`; both copies deleted |
| Percentile set | `MonteCarloEngine::calculatePercentiles()` | **extend** — optional `$points`; default unchanged so no existing caller moves |
| Joint reach | `HasJointOwnership::scopeForUserOrJoint()`, already on `InvestmentAccount` | **route** |
| Per-record share | `CalculatesOwnershipShare::calculateUserShare()` (F-0002's home) | **route** — the local copy is deleted, not edited |
| Cache invalidation | `CacheInvalidationService` (F-0022) | **none needed** — see §2 |
| Contribution rule | `ContributionEstimatorService` + the frontend's own copy | **extend** backend, **delete** the frontend copy |

**"Build a parallel one because the existing one is awkward" was available twice and
declined twice.** The obvious move on the cache was a bespoke invalidation hook for
investments; the obvious move on the bands was a fourth private helper. Neither was taken.

**Mechanisms answering each question, before and after:**

| Question | Before | After |
|---|---|---|
| Is this cached result still valid | 2 (retirement hashed inputs; investment did not) | **1** |
| What are a simulation's probability bands | 3 (investment, retirement, and a fallback in `MonteCarloResults.vue`) | **1** |
| How much does this person contribute monthly | 2, **already disagreeing on screen** | **1** |
| What is this user's share of a joint account | 2 (local copy, and the canonical trait) | **1** |

**Five fewer implementations than we started with, and no new file.**

---

## 2. Why this adds no cache invalidation, deliberately

F-0022 consolidated invalidation into `UserDataCacheObserver` → `CacheInvalidationService`,
and the instruction was to extend that rather than add a parallel path. **The right answer
turned out to be to add nothing at all.**

A key that names its own inputs does not need invalidating. A stale entry becomes
**unreachable** rather than wrong, and expires on its own 24-hour TTL. Adding an
invalidation hook would have been a second mechanism guarding an invariant the key already
guarantees — and the failure mode of *that* design is exactly what produced D-20: someone
must remember to fire it for every input, and the risk parameters were the one nobody
remembered.

**Correctness by construction beats correctness by remembering.** `CacheInvalidationService`
is untouched.

---

## 3. What is now true, and what changed to make it true

### 3.1 The key names the simulation — `MonteCarloSimulator::fingerprintKey()`

Callers still name *whose* projection it is (`user_16_portfolio_5y_e…`, kept verbatim so
`clearUserCache`'s `like 'user_{id}_%'` still matches and the table stays readable). The
simulator appends `_s<12 hex>` over capital, contribution, return, volatility, horizon,
iterations, the life-event map and the requested percentile set. Life-event maps are
sorted before hashing, so a map built in a different order is recognised as the same
simulation. `runMultiAssetSimulation()` gets the same treatment.

### 3.2 Every band is measured — no interpolation, no extrapolation, no smoothing

Three fabrications sat between the simulation and the screen, all now gone:

| Was | Now |
|---|---|
| p20 = p10 + (p25−p10) × 0.67, **sold to the user as "80% Probability"** | the measured 20th percentile |
| p5 = p10 − (p25−p10) × 0.33 — **below anything the simulation produced** | the measured 5th percentile |
| years 1 and 2 blended 30% / 10% toward the start value | the years as simulated |

`MonteCarloEngine::BAND_PERCENTILES = [5,10,15,20,25,50,75,90]` is requested by the two
projection services; every other caller keeps the 5-point summary, so the async
`RunMonteCarloSimulation` job's goal-probability calculation is byte-identical.

**The label "80% Probability" was false and is now true.** It was a straight line between
two neighbours; it is now the value 80% of simulated outcomes land at or above.

### 3.3 One set of inputs has one answer — `MonteCarloEngine::seedFromInputs()`

Sarah's ISA is her whole portfolio. It projected **£101,374** on the account card and
**£100,994** on the portfolio card: the same £85,000, simulated twice, £380 apart. The
same gap opens every time a cache entry expires, and a user cannot tell that apart from
something having changed.

The generator is now seeded from the inputs. **Same inputs, same sample, same answer** —
across modules, across surfaces, across cache expiry. Sampling error is what the bands
express; it must not also move the headline when nothing about the person moved.

Sarah's two cards now read **£103,229 and £103,229**.

---

## 4. The contribution nobody entered — and why this is not a modelling change

`ContributionEstimatorService` never read `monthly_contribution_amount`. For an ISA with
no recorded subscription it returned **the full ISA allowance ÷ 12 — £1,667 a month,
every month, for thirty years.** For a general investment account it invented 5% of the
balance annually (and did so on the **full** value of a joint account while the projection
started from the user's **half** — the F-0019 fraction failure, inside the contribution).

**This was not a defensible assumption we are overriding. It was a figure the same card
already contradicted.** `InvestmentProjections.vue` printed **"Monthly Contribution —"**
from `monthly_contribution_amount` while the chart beside it compounded £1,667 a month.
Two mechanisms, one question, opposite answers, eight pixels apart.

The chain is now: what-if override → recorded regular contribution at its stated frequency
→ contributions already made this tax year, annualised → **nothing**. The frontend copy is
deleted; the card reads the projection's own `estimated_monthly_contribution`.

**This is the single largest mover in the batch and W-0217's other half:** Sarah's £85,000
became **£1,577,731** over 36 years with no contributions recorded — £720,144 of it money
she never said she would save.

---

## 5. Sarah could not see her own joint account

`getPortfolioProjections()` opened with `InvestmentAccount::where('user_id', $user->id)`.
David is the primary owner of the joint AJ Bell account; Sarah is the `joint_owner_id`. So
her projection covered **£85,000** while the capital figure printed directly above it on
the same card read **£132,500** — the reach-complete number the dashboard and net worth
both use since F-0022.

A user reading her own card saw £132,500 of capital become £103,229 in ten years at a
stated 5% and had no way to know that £47,500 of her money was simply not in the
calculation. **A projection that silently drops an asset does not look wrong. It looks
pessimistic.**

`forUserOrJoint()` and `calculateUserShare()` both already existed. Routed to both; the
local share method is deleted.

---

## 5b. The per-product risk override that never applied (W-0264, investment side)

Added after the batch was first handed off, at the team lead's direction, with the
`has_custom_risk` finding offered as a **competing hypothesis for D-21**. It is not the
cause of D-21 — but it is a real defect of its own, and the discrimination is worth
recording because both looked plausible from the symptom alone.

### Discriminating the two

| | Cache key (W-0251) | `has_custom_risk` gate |
|---|---|---|
| Does the projection read the flag? | — | **No.** The column appears nowhere in `InvestmentProjectionService`; it reads `risk_preference` directly |
| Caption moves, value does not | **explained** — the caption is computed fresh at `:157` every request; only the simulation goes through the cache | not explained by a gate that would suppress both equally |
| Reproduced from evidence | **yes** — cached rows with `start_value = 47,500` yield all six figures | — |
| Fixed by the change actually made | **yes** — risk change now moves the value, browser-verified, and nothing in the fix touches the flag | — |

**The decisive experiment had already been run:** the fix changed the cache key and not
the flag, and the projection started responding. Had an always-false gate been suppressing
it, that could not have happened.

**Both defects were real; only one caused D-21.**

### What the flag did break

Three readers gated on `has_custom_risk && risk_preference` and so discarded the level the
user chose, falling back to their main profile: the **recommended allocation**
(`PortfolioPresentationService:204`), the **diversification analysis**
(`InvestmentController:1091`) and the **rebalancing target**
(`AccountRebalancingController:220`).

**16 investment accounts carry a `risk_preference`; 2 carry the flag**, both seeded. No
client writes it anywhere on the investment side.

### The route taken

**The readers now read the preference itself.** The team lead's design note governs here —
`risk_preference` is nullable, so it already encodes presence and absence, and the flag is
a second column holding one fact, which is how the two drifted. Reading the fact
**removes** three dependencies on the flag instead of adding a fourth writer, and needs no
backfill because the preference is already stored correctly.

One home: **`RiskPreferenceService::getProductRiskOverride()`** and
**`resolveProductRiskLevel()`**. `InvestmentProjectionService` carried **four** inline
copies of `risk_preference ?? main ?? 'medium'`; all four now call it, and the
`riskSourceFor()` helper keeps the level and its published provenance from disagreeing
about which branch was taken. **Seven expressions of one rule became one.**

**The pension readers are deliberately untouched** — W-0262's live, browser-verified work.
Routing them to the same home, and collapsing `has_custom_risk` entirely, is the follow-up.

### Verified

David (16), ISA 26 (`risk_preference = high`, `has_custom_risk = 0`), main profile
`medium`: recommended allocation **90% equities** where it was 50%, and the panel renders
**"Rebalancing Recommended — Equities 26.3% → 90.0%"**.

`tests/Feature/Investment/AccountRiskOverrideIsHonouredTest.php` guards it. The first case
is the discrimination: **setting the flag by hand must change nothing.** If the answer
moved with the flag, the flag would still be load-bearing.

**One fixture note, in the §4 family:** creating an `InvestmentAccount` fires
`RiskRecalculationObserver`, which recalculates and **overwrites** the stored profile — so
a main level set before the account exists is silently replaced, and the service caches per
user besides. Two tests failed on exactly that and the helpers now set the level after the
account and clear the cache. **The fixture, not the code, was wrong.**

---

## 6. Measured — before and after, both accounts, live browser

Local `laravel` DB, `localhost:8000`, both logins driven through the MFA gate (codes from
the database). Persona state restored afterwards: Sarah's ISA is back to `medium`.

### David (16) — portfolio, all four horizons

| Horizon | Before | After | Implied p20 rate |
|---|---:|---:|---:|
| **5 years** | **£4,650** | **£86,944** | −12.6% — this is the **£80,000 of life-event withdrawals** at years 2 and 4, annotated on the chart |
| 10 years | £217,451 | **£303,947** | +5.8% (a **+£200,000** inflow lands at year 9) |
| 20 years | £528,482 | **£598,168** | +6.3% |
| 30 years | £767,649 | **£858,733** | +5.5% |

**The chart's y-axis topped out at £50.0K and now scales to the data at every horizon**
(£180K / £350K / £800K / £800K), verified in the browser.

### The caption reconciles

With the life events removed, the same portfolio implies **5.36% – 6.09%** at the median
against a stated **7.07%**. That gap is volatility drag, σ²/2 = 0.1688²/2 = **1.42%**, and
it lands where theory says. The four horizons are now explicable by two disclosed things —
the stated rate and the life events drawn on the chart. **They were previously explicable
by nothing.**

### Sarah (17) — the joint account restored, and the risk change

| | Before | After |
|---|---:|---:|
| Capital used | £85,000 (card said £132,500) | **£132,500 — card and projection agree** |
| Caption | Medium, 5.00% | Medium, **5.54%** (blended with the joint account) |
| 10-year p20 | £103,229 | **£158,918** |
| 36-year p20 | **£1,577,731** | **£261,740** |

### W-0217 — the comparison, re-measured

| | Sarah (17) | David (16) |
|---|---:|---:|
| Capital | £132,500 | £172,500 |
| 36-year p20 **before** | **£1,577,731** | £1,025,964 |
| 36-year p20 **after** | **£261,740** | **£1,148,134** |

**The larger, higher-risk portfolio now out-projects the smaller one.** The inversion was
never a percentile artefact: it was David's £172,500 being projected from a cached £47,500
simulation while Sarah's £85,000 was inflated by £720,144 of invented contributions.

### Subset never exceeds the set

| Account | 10-year p20 | Portfolio containing it |
|---|---:|---:|
| David ISA 26 (£95,000) | £101,278 *(was £325,309)* | £303,947 |
| David GIA 14 (£47,500) | £55,257 | £303,947 |
| David VCT 27 (£30,000) | £35,947 | £303,947 |
| Sarah ISA 13 (£85,000) | £103,229 | £158,918 |

The joint account reads **£55,257 to both spouses** — W-0217 acceptance 5 (symmetry) holds
by construction, not by coincidence.

### D-21 — the risk change, driven through the real edit form

Sarah's ISA, Medium → High, saved in the browser (`risk_preference=high`, `updated_at
21:57:07`), page reloaded:

| | Medium | High |
|---|---:|---:|
| Caption | 5.54% | **7.46%** |
| Badge | Medium Risk | **High Risk** |
| **Projected Value (80%)** | **£158,918** | **£146,328** |

**It moved.** Previously it did not move by a single pound.

---

## 7. The finding that changes an acceptance criterion, not the code

W-0217 acceptance 2 asks that *"a higher risk preference produces a higher projected return
than a lower one, all else equal, **at every percentile reported**."*

**A correct Monte Carlo does not have that property, and building one that did would mean
breaking the model.** £100,000, no contributions, measured after the fix:

| Risk | 10y p20 | 10y p50 | 30y p20 | 30y p50 |
|---|---:|---:|---:|---:|
| Low (2%, 3%) | £112,065 | £121,556 | £155,506 | £176,553 |
| Medium (5%, 10%) | **£121,508** | £159,020 | £232,865 | £366,033 |
| Upper-Medium (6.5%, 15%) | £115,682 | £169,415 | **£258,336** | £481,183 |
| High (8%, 20%) | **£104,829** | **£177,379** | £216,768 | **£581,670** |

**The median and the upside rise monotonically with risk. The 20th percentile is
hump-shaped**, and the peak moves up the risk scale as the horizon lengthens — at ten
years it peaks at Medium, at thirty at Upper-Medium. Added volatility widens the downside
faster than added expected return lifts it. That is what a conservative percentile *is*.

**The consequence is a product question, and it is the reason Sarah's headline fell when
she took more risk:** the one number on the card is "Projected Value (80%)" — the single
percentile at which the risk/return relationship inverts. A user who increases their risk
and watches the headline drop is being shown something true and, without the median beside
it, unreadable. **Raised as W-0259 for CSJ. No code was changed on the strength of it.**

The tests assert what is actually guaranteed: **the median rises and the band widens** as
risk rises, and the p20 **moves**. Asserting a monotonic p20 would have been a test that
demanded a broken model.

---

## 8. Tests — written against movement, never against literals

`tests/CLAUDE.md` §4 is the reason none of these compare a projection to a number computed
by hand. **£4,650 survived a year of green suites precisely because nothing asserted that
the answer tracked the inputs.**

| File | Guards |
|---|---|
| `tests/Feature/Investment/AccountRiskOverrideIsHonouredTest.php` | 6 tests. The per-account risk override is read from the preference and **does not depend on `has_custom_risk`** — setting the flag by hand must change nothing. Plus the full resolution chain and the projection reading it. |
| `tests/Unit/Services/Investment/MonteCarloCacheIdentityTest.php` | 10 tests. Under **one shared key prefix**, the answer must change when capital, return, volatility, contributions or life events change — and must not when nothing does. Plus reproducibility both ways. |
| `tests/Unit/Services/Shared/ProbabilityBandExtractionTest.php` | 6 tests. A deliberately skewed distribution where the measured 20th is nowhere near the interpolant, so reinstating the interpolation turns it red. p5 not below the sample. Years 1–2 unblended. A band the simulation did not measure is **absent**, not invented. |
| `tests/Feature/Investment/PortfolioProjectionRespondsToInputsTest.php` | 8 tests. Risk / capital / contribution / horizon all move the figure. Median and spread rise with risk. Subset ≤ set at all four horizons. Single-account portfolio equals its account **to the penny**. |
| `tests/Unit/Services/Investment/ContributionEstimatorServiceTest.php` | Rewritten. The old suite **asserted the defect** — "estimates ISA contribution from allowance when no subscription data ... expect ~1666". Now: nothing recorded ⇒ nothing contributed, across four account types, and the estimate does not move with the balance. |

Applying §4's **Collision** test to each — *if the mechanism did nothing at all, would this
still pass?* — no: with the fingerprint removed the cache returns the first answer and
every movement assertion fails; with the seed removed the single-account equality fails.

**Suites run** (`DB_DATABASE=laravel_testing_c`): Investment, Shared, Retirement, Risk,
Estate, Goals, Contracts, Agents, Mobile, Api, plus `RetirementProjectionContractService`
— **1,374 passed, 0 failed** across the two final runs. `investmentHoldings.test.js` green.

**One contention episode worth recording rather than diagnosing.** Running two Pest
processes against `laravel_testing_c` at once produced **286 failures** — all
`DeadlockException` / `QueryException`, the exact fingerprint in `tests/CLAUDE.md` §5.
Re-run serially: **924 passed, 0 failed.** The doctrine is right and the discipline is to
re-run alone before believing a red, not to start bisecting.

### One fixture had to be corrected, and it is worth naming

`RetirementProjectionServiceTest` scripted a simulation carrying only the 5-point summary
and asserted on a 20th percentile that the old code **manufactured**. Under honest
extraction there is no 20th percentile in that fixture, so the value falls back to the
current pot. **The fixture had encoded the fabrication as the expected shape** — §4's
Fixture variant exactly. It now scripts what the real simulator returns.

---

## 9. Surfaces — Rule 19, named individually

| Surface | Reach | Verified |
|---|---|---|
| **Desktop web** | `/net-worth/investments` (portfolio panel, account panel), `/net-worth/investment-detail`, `/estate` and `/plans/estate` via `IHTCalculationService`'s p20, `/net-worth/retirement` via the shared band extractor | **Yes** — both logins, MFA, all four horizons, risk change end-to-end |
| **`/m`** | **No investment projection surface exists.** `Investment.vue`, `InvestmentAccountDetail.vue`, `NetWorth.vue` and `Estate.vue` render current values only; `NetWorthForecast.vue` is straight-line compounding with no Monte Carlo; the `/m` estate card shows current net estate, and `MobileDashboardAggregator` computes the projected block and discards it | **Not applicable, and stated rather than skipped.** Nothing to verify; nothing to rebuild. `public/m-build/` is untouched. |
| **iOS** | `RetirementModels.swift:329` decodes `percentile_20_at_retirement` and **never renders it**. No Swift file decodes an investment projection. | Not applicable |

**The backend is genuinely shared**, so where a surface does read these figures — the
estate projection on web — the fix reaches it without a second edit.

---

## 10. What the receiver needs, and would not otherwise know

### 10.1 The estate projection will move, and that is this fix reaching it

`IHTCalculationService.php:749-776` sums `percentiles.p20` at the 36-year horizon into
`projected_investments`. F-0018 pinned that line at **£2,603,695** and reconciled the whole
estate to it. **That number is now wrong and must not be treated as a regression baseline** —
it was £1,577,731 of Sarah's inflated projection plus £1,025,964 of David's stale one.
Re-derive it before comparing anything to F-0018 §0b.

### 10.2 `mt_srand()` is global, and is handed back unpredictable

`seedFromInputs()` seeds the global Mersenne Twister; both simulation loops call bare
`mt_srand()` afterwards to reseed randomly. Nothing outside a simulation inherits the seed.
`Str::random()` and anything on `random_bytes` are unaffected — those are a different
generator. **A future refactor that removes the trailing `mt_srand()` as "dead" would leave
the whole request deterministic.**

### 10.3 The percentile set is part of the cache identity

Two callers using the same key prefix but different `$percentilePoints` get different keys.
That is deliberate — a cached 5-point summary cannot serve a caller that needs bands — and
it is why `RunMonteCarloSimulation` is unaffected by the band change.

### 10.4 Old cache rows are unreachable, not deleted

Every pre-fix row has a key with no `_s…` suffix, so nothing can read one. They expire on
their own TTL. Both personas' rows were cleared during verification so measurements were
taken cold.

### 10.5 A blocker from a concurrent agent, and a latent defect it exposed

Verifying D-21 on **David** was impossible: at 21:28 another agent inserted a fourth
holding on account 26 ("Baillie Gifford Managed Fund", 5%), taking its allocations to
**105%**. Each allocation input's `max` is *100 minus the other holdings*, excluding its
own value, so at >100% **every** input is invalid, `form.reportValidity()` returns false,
and **"Update Account" silently does nothing — no message, no error, no request.** The
account is uneditable and nothing tells the user why.

That is a real defect independent of the contamination (**W-0257**), because the app
permits allocations to exceed 100% in the first place. D-21 was verified on **Sarah**
instead, whose holdings sum to exactly 100%. **Account 26 is still at 105% — I have not
deleted another agent's row.**

Also seen mid-run: `Target class [App\Services\Mobile\NetWorthService] does not exist`
500ing **all logins** for a few minutes while another agent was mid-edit in
`MobileDashboardAggregator.php`. Transient, self-resolved, not caused here — noted so the
next reader does not diagnose it.

### 10.6 An edit in `MonteCarloEngine` that is not mine

`simulate()` gained `?string $cacheKey` and `array $scheduledInjections` parameters in the
working tree during this session, from another agent or a hook. It is compatible with this
work and left as found, per instruction — flagged so it is attributed correctly.

---

### 10.7 A base-class signature change is invisible to every check we normally run

**Recorded because the standard checks are structurally blind to this, not because I was
careless with them.**

Adding `?array $percentilePoints` to `MonteCarloEngine::simulate()` at position 7 collided
with `?string $cacheKey` at position 7 on `MonteCarloSimulator`, which had carried it since
March. PHP compares overrides **position by position**, so appending the new parameter at
position 9 on the subclass could not satisfy it. The result was a hard fatal for anything
resolving the class — it killed another agent's test run outright.

**What did not catch it, and why:**

| Check | Why it passed |
|---|---|
| `php -l` on both files | each file is valid PHP on its own; the incompatibility only exists between them |
| Booting the app | bootstrapping never loads either class |
| Every Pest suite I had run | none instantiate the subclass, so its declaration is never compared against the parent's |

**What does catch it, in about a second:**

```bash
php -r "require 'vendor/autoload.php'; new ReflectionClass('App\Services\Investment\MonteCarloSimulator'); echo 'OK';"
```

**The rule: change a parent signature and its overrides in the same edit**, the same
discipline as an import and its first reference (`tests/CLAUDE.md` §2). And on a shared
working tree the blast radius is other agents, not just yourself — `MonteCarloEngine` and
`MonteCarloSimulator` are one hierarchy even though both files sit inside one agent's
scope, so **"inside my scope" is not the same as "local".**

Two options existed once it was fatal: reorder the subclass, or widen the parent. **Widening
the parent was correct** — the subclass's signature was the fuller contract and had been for
months, and widening also fixed a latent bug, since the parent's body had been discarding
`$scheduledInjections` silently.


## 11. Status

| Item | Outcome | One home |
|---|---|---|
| **W-0251** cache identity (D-20) | **DONE** · `handoff` → quality-lead | `MonteCarloSimulator::fingerprintKey()` |
| **W-0252** risk change does not move the projection (D-21) | **DONE — closed by W-0251** | same |
| **W-0253** subset outgrows the set / lower risk outgrows higher (W-0217) | **DONE — closed by W-0251 + W-0254** | same |
| **W-0254** contributions nobody entered | **DONE** · `handoff` → quality-lead | `ContributionEstimatorService` |
| **W-0255** interpolated, extrapolated and smoothed bands | **DONE** · `handoff` → quality-lead | `MonteCarloEngine::extractProbabilityBands()` |
| **W-0256** the projection excluded a joint account | **DONE** · `handoff` → quality-lead | `forUserOrJoint()` + `CalculatesOwnershipShare` |
| **W-0257** an account over 100% allocation cannot be saved, silently | **RAISED** — not built | — |
| **W-0258** "expected return" caption against a median | **RAISED** — needs CSJ | — |
| **W-0259** the headline is the percentile where risk inverts | **RAISED** — needs CSJ | — |
| **W-0264** (investment side) per-product risk override inert | **DONE** · `handoff` → quality-lead | `RiskPreferenceService::getProductRiskOverride()` |

**In flight: nothing.** Every edit is applied, formatted and covered.

**No commit, no PR, no deploy** — by instruction.
