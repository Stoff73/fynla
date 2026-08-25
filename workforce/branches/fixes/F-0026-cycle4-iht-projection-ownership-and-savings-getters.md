---
id: F-0026
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/08-process.md]
surfaces: [web, m]
consistency_checked: 2026-08-23T00:00:00Z
status: active
---

# F-0026 — Cycle 4: the projected estate owned things the current estate did not, and `/savings` said £0

**Agent:** build-lead (`fix-cycle4-doublecount`) · **Branch:** `dev` (shared working tree)
**Board items:** W-0331, W-0274, W-0332, W-0333, W-0335, W-0338, W-0339 · **ID block:** W-0331 – W-0340
**Number and ID block issued by team-lead.** F-0026 taken after checking `fixes/` —
F-0025 was the highest; two agents collided on F-0023 and F-0024 earlier today.

**Predecessors, read before touching anything here:**
`F-0019-cycle2-ownership-applied-one-side-only.md` — the **reach / fraction**
vocabulary. `F-0024-cycle4-risk-engine-reach-and-fraction.md` §10 — the census that
raised this, **and whose characterisation of the first item is corrected below**.
Board items **W-0280**, **W-0274**, **W-0271**, **W-0228**.

---

## 1. The principle

**A figure and its projection must be about the same estate.**

`IHTCalculationService::calculate()` returns two estates in one response: the estate
as it stands and the estate projected to the second death. The user reads them side
by side and reasonably assumes the second is the first, grown.

It was not. The headline read `EstateAssetAggregatorService::gatherUserAssets()` —
`forUserOrJoint` reach at `calculateUserShare` fraction, each record once. The
projection read `where('user_id', …)` at 100%, for investments, for property and for
debts. **Two ownership rules, one response, no label on either.**

---

## 2. The correction — the named double count does not exist

**Stated plainly, because W-0280 §1 and F-0024 §10 both assert it and whoever sweeps
the remaining 59 `InvestmentAccount` sites will act on it.**

The claim: summing the user's investments then the spouse's counts a joint record
*"once from each side: £190,000 of a £95,000 record."*

**A row carries exactly one `user_id`.** `where('user_id', $user->id)` and
`where('user_id', $spouse->id)` are therefore **disjoint** — no row can match both.

| Reader | David | Sarah | Household |
|---|---|---|---|
| the code as it stood (`user_id` at 100%) | £220,000 | £85,000 | **£305,000** |
| `CrossModuleAssetAggregator::calculateInvestmentTotal` | £172,500 | £132,500 | **£305,000** |

Identical. **Fixing the five named sites moved this household's Inheritance Tax by
£0**, and §6 shows exactly that.

**What is actually wrong with summing each member at 100%.** It equals the household
only when every shared record is shared **between the two members**. It breaks three
ways, and all three are live:

1. **A third party's share is carried in.** A record shared with someone who has no
   account here (`joint_owner_id` NULL on a shared record) is taken whole.
2. **Data sharing off.** David's projection showed £220,000 against a headline of
   £172,500 — £47,500 of Sarah's money in his estate; hers showed £85,000 against
   £132,500, her own share missing entirely.
3. **Between two code paths.** The Monte Carlo simulation is share-aware; the
   fallback was not. A run where one member simulated and the other fell back counted
   a joint record at more than its value — a genuine double count, in the branch
   nobody named.

**A schema fact that bounds the whole class:** `investment_accounts.ownership_type`
is `enum('individual','joint','trust')` — **`tenants_in_common` is property-only**.
So the third-party over-count can only reach an estate through **property**, which is
precisely where the live £177,000 was. Found by a fixture the schema rejected.

---

## 3. Prior art

Checked 2026-08-22 across `registry/capabilities.md`, the code, custom artisan
commands, open PRs and in-flight branches, the vault, and `.claude/skills|agents`.

| Instance | Prior art found | Outcome |
|---|---|---|
| household investment value | `CrossModuleAssetAggregator::calculateInvestmentTotal()` | **route** |
| household property value | `CrossModuleAssetAggregator::calculatePropertyTotal()` | **route** |
| mortgage reach | `CrossModuleAssetAggregator::getMortgages()` — the **two-leg** reader, built for the cross-link case | **route** |
| other liabilities reach | `EstateAssetAggregatorService::getUserLiabilities()` | **route** |
| the share rule itself | `CalculatesOwnershipShare`, `App\Support\SharedOwnership`; the mortgage half settled by CSJ's W-0228 ruling | **route** |
| savings getters, share | `resources/js/utils/ownership.js` | **route** |
| savings getters, emergency fund | `SavingsAgent` → `calculateCashTotal()`, via the API's per-record `user_share` | **route** |
| `/m` savings screen | the `/m` **investment** list already reads `ownership.js` by relative path | **route** |

**Nothing was built.** Every count of implementations goes down: five investment call
sites → one reader; four fallback copies → one method; four liability loops → one
method; three share copies in `savings.js` → one helper; one `/m` re-derivation →
the same helper.

---

## 4. What the Inheritance Tax figure counts, stated

**The union of the household's members' records, each record counted ONCE, at the
share each member actually owns.**

Household, not per-person — `calculate()` already pools both estates and the
projection models the second death. But the household is assembled by **adding two
share-correct member views**, not two 100% views, because a member's own total
already includes their share of records the OTHER member holds, and a share
belonging to someone outside the household is credited to **nobody**.

Now written in the `IHTCalculationService` class docblock, beside the two shapes that
look equivalent and are not — **and beside what is still open** (W-0340, §8).

**No tax value was touched (Rule 2.)** Nil Rate Band, Residence Nil Rate Band and the
40%/36% rates all still come from `TaxConfigService`. Nothing was "fixed" by moving a
threshold. Independently audited — see §7.

### 4.1 `5278a2457` is completed, not reversed

The commit that introduced the property defect was itself a **fix**. It found
`PropertyStore::forUser` is joint-aware, so calling it for both members matched a
joint property **twice**, and pinned each side to its own primary rows. That double
count was real and must not come back.

But primary rows were then taken at **100%**, which is where a third party gets in.
Three approaches, two failure modes:

| approach | joint counted twice | third party's share included |
|---|---|---|
| `forUser` on both sides (pre-May) | **yes** | no |
| `user_id` at 100% (May–now) | no | **yes — £177,000** |
| **reach + share, per member** | **no** | **no** |

**The commit named the third option itself.** It left
`EstateAssetAggregatorService` alone *"because that consumer applies
calculateUserShare on each row so joint properties correctly contribute the user's
share."* The right answer was written in the commit that introduced the defect; it
simply was not applied to the projection. Both of its properties are now asserted
against the service — see §9.

---

## 5. Status

| Item | Outcome | One home |
|---|---|---|
| **W-0331** projection and headline disagreed about whose investments | **DONE** · `handoff` → quality-lead | `CrossModuleAssetAggregator::calculateInvestmentTotal` |
| **W-0333** projected estate carried a third party's £177,000 | **DONE** · `handoff` → quality-lead | `CrossModuleAssetAggregator::calculatePropertyTotal` |
| **W-0336** projected liabilities taken at 100% | **DONE** (folded into W-0333's batch by team-lead) | `getMortgages()` two-leg + `calculateUserMortgageShare` |
| **W-0339** the projection read a column `mortgages` does not have | **DONE** · `handoff` → quality-lead | `maturity_date`, both sites |
| **W-0274** `/savings` Emergency Fund read £0 and 0.0 months | **DONE** · `handoff` → quality-lead | `ownership.js` + the backend's `runway_months` |
| **W-0332** `/m` savings counted a joint account whole against both spouses | **DONE** · `handoff` → quality-lead | the same `ownership.js` |
| **W-0335** `/api/savings` returned `'analysis' => null` | **DONE** · `handoff` → quality-lead | `SavingsAgent::analyze()`, narrowed to two figures |

### Files

- `app/Services/Estate/IHTCalculationService.php` — five `InvestmentAccount` sites,
  two property sites, four liability loops and two phantom-column reads. Four
  fallback copies → one `projectMemberInvestments()`; four liability loops → one
  `projectMemberLiabilities()`. Class docblock states §4 **and** what is still open.
- `app/Http/Controllers/Api/SavingsController.php` — the `'analysis' => null`
  placeholder filled with `runway_months`, the fund value and the resolved
  expenditure source. **Deliberately narrow: `adequacy_score` stays server-side.**
- `resources/js/store/modules/savings.js` — three share copies routed to
  `ownership.js`; `is_emergency_fund` retired as a definition; the runway reads the
  backend figure; `setAnalysis` fed a key that exists.
- `resources/mobile/views/modules/Savings.vue` — `totalCash` at the viewer's share;
  rows show "Your 30.00% of £20,000" as the `/m` investment list does.
- `tests/Unit/Services/Estate/IHTProjectionOwnershipTest.php` (new, 10)
- `tests/Feature/Savings/SavingsEmergencyFundPayloadTest.php` (new, 3)
- `tests/frontend/store/savingsEmergencyFundGetters.test.js` (new, 10)
- `tests/frontend/mobile/SavingsOwnershipShare.test.js` (new, 4)
- `tests/Feature/Stores/PropertyReadConsumerParityTest.php` — one case rewritten; see §9.

### Implementations, before and after

| Rule | Before | After |
|---|---|---|
| The household's investment value, for the projection | 5 call sites, 2 unreachable | **1** reader |
| "simulate, else compound" | 4 copies, 2 per member | **1** method |
| One member's debts, amortised | 4 loops, 2 per member | **1** method |
| The household's property value, for the projection | 2 call sites at 100% | **1** reader |
| The viewer's share of a savings account (web store) | 3 copies | **1** helper |
| The viewer's cash total (`/m` savings screen) | 1 re-derivation contradicting the detail screen | **1**, the same helper |

---

## 6. Figures, before and after

Persona household, `localhost:8000`, read-only throughout. Data sharing **on** for
both, so both logins see one household.

| Figure | Before | After |
|---|---|---|
| current gross assets | £2,021,780.00 | £2,021,780.00 |
| current net estate | £1,728,780.00 | £1,728,780.00 |
| **current Inheritance Tax liability** | **£343,512.00** | **£343,512.00** |
| projected investments | £1,559,611.18 | £1,559,611.18 |
| **projected properties** | £4,550,296.97 | **£4,037,301.71** |
| projected liabilities | £0.00 | £0.00 |
| projected taxable estate | £7,128,374.21 | £6,615,378.95 |
| **projected Inheritance Tax liability** | **£2,851,349.69** | **£2,646,151.58** |

**A user's projected tax liability falls by £205,198.11, on both accounts.** It is
the same household seen from two logins, so it is one household's bill, not two.

**Predicted £205,198.10, measured £205,198.11.** The compliance review re-derived
both sides independently and found the **prediction** was the imprecise figure, not
the measurement: `0.4 × 512,995.26405647` (unrounded property delta) rounds to
`…11`, while `0.4 × 512,995.26` (pre-rounded) rounds to `…10`. The measurement is the
more accurate of the two. **Nothing moved for any other reason** — every other
component was reconstructed bit-identical (cash £1,345,466.06, investments
£1,559,611.18, chattels £193,000.00, allowances £500,000, charitable £20,000, rate
0.40).

**The current column is untouched.** The three fixes are entirely in the projection.

**What the persona could NOT demonstrate**, stated because a green run over this
household proves less than it looks:

- the **investment** fix — with sharing on, old and new both give £305,000;
- the **liability** fix — every mortgage matures inside the horizon, so
  `projected_liabilities` is £0 either way;
- the **phantom column** fix — same reason;
- the **mortgage two-leg reach** — all three mortgage rows name both spouses.

All four are exercised by fixtures instead. **When a persona is the fixture, its gaps
are the suite's gaps** (`tests/CLAUDE.md` §4).

---

## 7. Tax-compliance review — required by team-lead before landing

Run against the module vault docs and the live `TaxConfigService`, not a reference
table. **The reviewer's own standing table was stale** (headed 2025/26 against a live
`2026/27` config carrying the post-Budget business-relief cap) and it said so —
worth recording, because a review that trusts its own memory over the configuration
is the failure this gate exists to catch.

**Cleared, all four questions asked:**

1. **Tenants-in-common third-party share correctly outside the estate.** IHTA 1984
   s5(1) — the estate is property to which the deceased was **beneficially
   entitled**; tenants in common hold distinct undivided shares. The stranger's 60%
   was never David's. `calculateUserShare` returns `0.0` for anyone who is neither
   party, so it is credited to **nobody** rather than falling to the spouse.
2. **Joint tenancy at full household value is correct for a second-death model** —
   survivorship puts the whole in the survivor's estate, and 50% + 50% reaches the
   same figure. Nothing here worsens first-death treatment, because **this service
   never produces a first-death figure**.
3. **The figure moves for exactly one reason** — independently reconstructed, both
   sides matching to the penny; the 1p is rounding, and in the direction that makes
   the measurement the accurate one.
4. **Share-based debt deduction is correct** — IHTA 1984 s5(3) with s162(1): joint
   borrowers are jointly and severally liable but hold a right of contribution, so
   only the deceased's proportion is deductible (IHTM28030).
5. **`DEFAULT_PROPERTY_GROWTH_RATE = 3.0` is a growth assumption, not a tax value.**
   Correctly a `private const`, not a Rule 2 breach.

**One finding ON this change, fixed before landing — L1.** The liability fix as first
written swapped a reader that *accidentally* recovered the full household mortgage
debt for one that could silently drop a co-owner's share. A mortgage is **reached**
by the mortgage row's own `user_id`/`joint_owner_id` but its share is resolved from
the **securing property** (W-0228); when those disagree — a home owned 50/50 with a
mortgage row naming one spouse — that spouse gets 50% and the other sees nothing, so
half the debt is deducted by nobody and the estate comes out too big. Routed to
`CrossModuleAssetAggregator::getMortgages()`, whose second leg exists for exactly
this case, and pinned by a test. **The persona cannot exercise it — all three of its
mortgage rows name both spouses — which is why only a review found it.** The headline
still reads the one-leg version: **W-0338**.

**Sixteen further findings, none introduced here, all pre-existing.** Filed as
**W-0361 – W-0376** (block issued by team-lead), **one item each rather than a single
ledger entry** — a "tax review findings" item gets triaged once; sixteen get triaged
sixteen times. Every one names `tax-compliance-reviewer` on the FIX, not only on the
review. The five graded HIGH:

| Ref | Finding | Direction |
|---|---|---|
| T1 | the 2027 pension scenario reuses the smaller estate's allowances and rate — W-0136's defect in the one place W-0136 did not reach | understates |
| T2 | the projected estate excludes defined contribution pensions, at a death decades after April 2027 | understates |
| T6 | Residence Nil Rate Band denied to a user who is only the **joint** owner of the main residence, against IHTA 1984 s8H(2) — while their share is counted into the s8E(2) cap | overstates, up to £70,000 |
| T8 | Business Property Relief applied as a flat uncapped 100% while the live config's £2.5m cap has been in force since 2026-04-06; Agricultural Property Relief entirely absent | understates, up to £700,000 |
| L2 | the projection pools on a different predicate than the headline (**W-0340**) | overstates for unmarried linked couples |

Medium and below: T3 (gifts measured from today, applied to a death 36 years away —
**£60,000 overstated on this very persona**), T4, T5, T7, T9 (flagged for
verification, not asserted), R1–R3 (hardcoded statutory periods and rates in
user-facing strings), L3, S1 (liability data logged at INFO).

**T3 is the one to read first of those:** `projected_nrb_available` is £500,000 where
£650,000 is correct, because a 2020 chargeable transfer still consumes the band at a
death in 2062 — thirty years after IHTA 1984 s7(1) drops it out of cumulation.

---

## 8. Raised while working

| Item | Severity | State |
|---|---|---|
| **W-0334** `projectInvestments()` / `getCurrentInvestmentValue()` unreachable — **so a user who sets `investment_growth_method: custom` has it silently ignored by the estate projection** | medium | queued. Filed with the user-facing half leading, per team-lead: it is a defect, not a dead-code note. Ownership fixed in place; deleting is its own change with its own blast radius. |
| **W-0338** the headline's mortgage reader is the one-leg version | medium-high | queued. The other half of L1: `calculateUserLiabilities()` can drop a co-owner's share of a debt the mortgage row does not name, inflating the estate. |
| **W-0339** the projection read `$mortgage->end_date`, a column `mortgages` does not have (it is `maturity_date`) | medium | **DONE**, both sites. Every mortgage in the estate projection had always fallen through to "assume cleared at retirement age", whatever its real term — including in `projectMainResidenceNetValue`, which feeds the Residence Nil Rate Band cap. £0 on this persona. *A float carries no absence.* |
| **W-0340** the projection pools on `$dataSharingEnabled && $spouse`; the headline on `$isMarried && $dataSharingEnabled` | high | **`blocked_by: [csj-decision]` — escalated by team-lead, do not fix.** Neither `liveSpouse()` nor `hasAcceptedSpousePermission()` consults `marital_status`, so an unmarried couple with linked accounts gets a headline taxing one estate and a projection pooling two — against a **single** nil rate band and **no** spouse exemption, neither of which unmarried partners get. W-0154's F3 still alive in the projection path. **The class docblock names this as open rather than claiming the gap closed.** |
| **W-0361 – W-0376** the tax-compliance findings, 16 items | high | Filed individually. **W-0361 leads**: `projected_nrb_available` £500,000 where £650,000 is right, because a 2020 chargeable transfer still consumes the band at a death in 2062 — **£60,000 of overstated projected tax, live on this persona and reproducible today.** |
| **W-0337** W-0280 §1 states a mechanism that cannot occur | medium | queued; a correction note is on W-0280 itself, not only here. |
| **W-0336** projected liabilities at 100% | low | **DONE** — team-lead folded it into this batch. |

**W-0376** — `resources/js/components/Savings/SavingsOverviewCard.vue`
is mounted nowhere, **and it carries its own copy of the runway thresholds** (6 / 3
months, its own status colours and captions). Team-lead's ruling: raise it, do not
merely report it — **four dead sites in one day is a pattern, not a curiosity**, and
dead code that carries its own copy of a rule is how the next person copies the wrong
one. The four: this component · W-0334's unreachable projection pair, which swallows
a user's custom growth setting · a public life-cover method with zero production
callers · and the `analysis` action nothing dispatched (W-0335). *Reading finds
candidates; only running finds facts.*

---

## 9. Test design — what was deliberately avoided

Per `tests/CLAUDE.md` §4. Every trap named here cost someone time this cycle.

- **Asymmetric splits only — 75/25, 70/30, 40/60 — never 50/50.** At 50/50 the
  primary's share and the co-owner's are one number, so a getter that always returns
  the primary's share is correct for both parties. That symmetry is why the
  `savings.js` fraction bug survived every earlier sweep, **and the persona's own
  joint accounts are all 50/50**, so the persona cannot see the class at all.
- **Collision, named in the file.** "A joint record is counted once when sharing is
  on" **cannot fail against the original defect** — the pooled figure was already
  right. It earns its place by failing against the *opposite* error, and the file
  says so with the reason, so no future reader mistakes it for proof it is not.
- **Mutation-tested in five directions**, each restoring one real historical state:

  | Mutation | Result |
  |---|---|
  | original investment reader | 3 of 4 red — the Collision case survives, as documented |
  | opposite: primary's share only, co-owner's leg dropped | the Collision case goes red |
  | property back to primary-at-100% | third-party property case red |
  | property back to the **pre-May** joint-aware double count | **both** property cases red |
  | liabilities back to `user_id` relations at 100% | both liability cases red |
  | mortgage reach back to one leg | the W-0338 case red |
  | phantom column restored | two cases red |
  | `'analysis' => null` restored | payload case red |
  | whole analysis block shipped | **the Rule 12 guard goes red** |

  **Every test in the suite fails under at least one mutation.**
- **A test named after a service that never called it.** `PropertyReadConsumerParityTest`
  carried a case titled *"IHTCalculationService projected properties does NOT
  double-count joint property across spouse pair"* which **reproduced the query
  pattern inline** and asserted arithmetic the test itself had written. It would have
  stayed green through any change to the service — including the one that put
  £177,000 of a stranger's money in an estate. It now drives the service and asserts
  **both** properties: counted once, and the third party's share excluded. Verified
  red under the defect it is named for.
- **The Clamp trap, avoided by construction.** `projected_liabilities` is £0 for
  anything maturing inside the horizon, so a liability assertion can pass while
  measuring nothing. Every liability case asserts `toBeGreaterThan(0.0)` first, and
  one household is deliberately built with a debt running past the horizon.
- **The Fixture trap, caught in the act.** The first payload test returned a null
  analysis and I nearly filed it as a defect — it was `SavingsDataReadinessService`
  blocking on a fixture with no date of birth and no income. The fixture now sets all
  three blocking fields and says why.
- **The Mock line.** The investment simulation is stubbed to force the fallback
  branch. That is not the Mock variant: the stub supplies no ownership figure and
  asserts nothing about one. Every assertion compares two households differing only
  in the ownership of one record, so growth rate and horizon cancel and no literal is
  pinned.

---

## 10. Environment

- Branch `dev`, shared working tree, other agents editing concurrently.
  **No commit, no PR, no deploy, no bundle rebuild.** `/m` serves
  `public/m-build/`, never Vite, so the `resources/mobile/` change is invisible until
  team-lead rebuilds — **asked, not built.**
- Tests: `DB_DATABASE=laravel_testing_e`. **Not `laravel_testing_a` as originally
  issued** — team-lead had double-assigned it; the first run died with `Unknown
  table` mid-migration and 0 assertions, the `tests/CLAUDE.md` §5 fingerprint.
  `phpunit.xml` and `Pest.php` untouched.
- Families green: `tests/Unit/Services/Estate/` + `tests/Feature/Stores/` +
  `tests/Feature/Savings/` + `tests/Unit/Services/Shared/` — **552 passed, 1,992
  assertions**. Frontend — **703 passed across 60 files**.
  One run in the middle showed a single failure at `sumMainResidenceNetShare` that
  **did not reproduce across two subsequent clean runs of the same command**;
  discarded per §5 rather than diagnosed, and recorded here rather than omitted.
- **Cache exposure checked, not assumed.** A key-name mismatch was found elsewhere
  this cycle — `EstateAgent::analyze()` remembers under `estate_analysis_{id}` while
  `invalidateUserCache()` forgets `v1_estateagent_{id}_analysis` and
  `v1_estateagent_analysis_{id}`, **none of which match**, so a stale estate analysis
  survives every invalidation for the full TTL. It had already contaminated another
  agent's before/after measurement. **Every figure in §6 was measured against
  `IHTCalculationService` directly, never through `EstateAgent`**, and `iht_calculations`
  holds **zero** rows for users 16 and 17, so the service's own cache could not have
  served a stale result either. Re-measured after forgetting all four candidate keys by
  hand: **identical to the penny.** A cache that survives invalidation does not fail
  loudly — it agrees with whatever it stored — so this was proven rather than argued.

- **Pick a stale-bundle discriminator that can only be present if your change shipped.**
  The obvious token was `full_balance`, and it would have given a **false negative**: it
  legitimately survives the rebuild, because `ownership.js`'s `VALUE_FIELDS` lists it and
  `balanceOf()` still uses it for the "of £4,500" context line. The change removed the
  `reduce(...balanceOf` summing expression and added the class `ms-acct__share`. Both
  were grepped — one absent, one present — and then the page was confirmed to be serving
  the same bundle file that was grepped. **That last step is the one people skip.**

- Persona household David Jones id 16, Sarah Jones id 17 on the local `laravel`
  database — **read-only throughout**. No row created, updated or deleted; the
  calculation persists nothing (`$persist` defaults false).
- **Browser pass DONE** (2026-08-23 00:45, both accounts, web and `/m`). The tab was
  handed over with `public/m-build/` rebuilt. Codes fetched from the database; no
  persona row written; the browser was not closed.

  **`full_balance` is the wrong discriminator for a stale-bundle check** — it
  legitimately survives, because `ownership.js`'s `VALUE_FIELDS` lists it and
  `balanceOf()` still uses it for the "of £4,500" context line. What the change
  actually removed is the `reduce(...balanceOf` summing expression, and what it added
  is the class `ms-acct__share`. Both were grepped, and the page confirmed serving the
  same bundle file. **A stale `/m` build fails by AGREEING with you** — the figures
  come from the backend either way — so the discriminator has to be something only
  the new code contains.

  **What the pass cannot prove, stated because the table looks stronger than it is:**
  the persona's joint account is **50/50**, so David's half and Sarah's half are the
  same £2,250. The browser cannot distinguish "shows the viewer's share" from "shows
  the primary owner's share" — the Collision, live on the screen under test. It proves
  only that neither spouse is charged the whole £4,500, which was the live defect. The
  asymmetric discrimination is in the tests, at 70/30.

---

## 11. A fifth blind-test variant, for `tests/CLAUDE.md` §4

**Drop-in text, at team-lead's request.** The four variants in §4 are Mock, Clamp,
Fixture and Collision. **All four assume the test at least invokes the code.** This one
does not, and it is worse than all four — because its title is a promise.

> ### The fifth variant — Decoy — added 2026-08-23, from the W-0333 property fix
>
> **The test never calls the code it is named after.**
>
> `PropertyReadConsumerParityTest` carried a case titled *"IHTCalculationService
> projected properties does NOT double-count joint property across spouse pair"*. It
> created a household, then **reproduced the service's query pattern inline**:
>
> ```php
> $userPrimary   = (float) $store->forUser($user)->where('user_id', $user->id)->sum('current_value');
> $spousePrimary = (float) $store->forUser($spouse)->where('user_id', $spouse->id)->sum('current_value');
> expect($userPrimary + $spousePrimary)->toBe(500000.0);
> ```
>
> `IHTCalculationService` is **never constructed and never called.** The assertion is
> over arithmetic the test itself wrote, so it would have stayed green through **any**
> change to the service — including the one that put **£177,000 of a third party's
> money into a household's Inheritance Tax figure** and left it there for three months.
>
> | Variant | The misconception lives in | Why it cannot fail |
> |---|---|---|
> | Mock | the value the test supplies | it asserts what the mock was told |
> | Clamp | the value the code can return | the output cannot vary |
> | Fixture | the data the test sets up | the branch is never entered |
> | Collision | nothing — the test is fine | the right answer and the wrong answer are the same number |
> | **Decoy** | **the test's NAME** | **the code under test is never executed** |
>
> **Why it is worse than the other four.** A mock, a clamp and a fixture are all
> visible in the test file — you can read them and ask what they hide. A Collision is
> invisible but the test is at least *wired up*, so a later fixture change can rescue
> it. A Decoy is wired to nothing and **actively misleads**: its name is a claim about
> a service, it appears in the green count beside that service's real tests, and a
> reviewer scanning titles reads it as coverage. **It is worth less than no test,
> because no test does not tell you the service is guarded.**
>
> **How it gets written, in good faith.** Nobody sets out to write one. This case was
> born in a refactor that changed a *query pattern* across five consumers, and it was
> written to lock the **pattern**. That was a reasonable thing to test, and the file's
> other six cases still legitimately test the store. The defect is the **name**: it
> promised a service-level guarantee it was never built to give, and once the name
> existed nobody re-read the body.
>
> **How to spot it:** *does this test file import, construct, or resolve the class in
> its own title?* If it does not, either rename it after what it actually tests, or
> point it at the thing it claims to cover. **A test named after a class it never
> touches is a claim, not a test.**
>
> **The rewritten case now drives the service** and asserts both of its properties —
> a joint property counted once, and a third party's share excluded — and was verified
> **red** under the defect it is named for. The store-contract observations were kept,
> because they are true of the store and are why the primary-only filter existed.

**A sixth, if you want it later, is already visible in this batch.** W-0339's phantom
column has the same signature at a different layer: a **collection** read of a
non-existent attribute returns `null` silently while a **query-builder** read of the
same name throws. Every test over that method passed, and the fixture that finally
caught it did so at the **write** boundary, not the read one.
