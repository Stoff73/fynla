# tax-compliance-reviewer — Estate / Inheritance Tax verdict, round four

**Scope:** commits `88494e0fd` (W-0466 / W-0467 / W-0469) and `8f09eaddc` (W-0465) on
`estate-copy-and-m-handoff`, plus a ruling on **W-0470** (filed, not fixed).
**Mode:** report-only — no code, no migrations, no database.
**Written to disk deliberately.** The round-one/two verdict existed only in a conversation
and nearly evaporated with the session; that record is at
`workforce/ops/handoffs/W-0463/tax-compliance-reviewer-verdict-2026-08-23.md`.

## Headline

Both claims put to me **hold**. `$projectedEstateForTaper` mirrors the current column and
does **not** reintroduce R2. The present-day-relief reasoning is sound. But this round found
what the previous three each found: **W-0465 fixed two of the three implementations of its
own formula**, and **W-0466's stated premise — that the `/m` teaser is the only Inheritance
Tax figure `/m` prints — is false.**

Reviewer's agent definition remains **stale** (says 2025/26, "frozen until April 2028", no
relief cap). Every figure below was grepped from the live configuration, not from that table.
Active year is **2026/27** (`database/seeders/TaxConfigurationSeeder.php:1304`), band frozen
to 2031, relief cap **£2,500,000** from 2026-04-06 — all correct as configured.

---

## Direct answers to the five questions

### 1. Does `$projectedEstateForTaper` mirror `$estateForTaper`, and does it reintroduce R2?

**It mirrors it, and it does NOT reintroduce R2.** Verified line by line.

| | current column | projected column |
|---|---|---|
| base | `$totalGrossAssets` | `$projectedGrossAssets` |
| add back | `is_iht_exempt && iht_relief_qualifies` at `current_value` | identical predicate, identical `current_value` |
| subtract | `$totalLiabilities` | `$projectedLiabilities` |
| relief added back? | **no** | **no** |

`IHTCalculationService.php:262-265` against `:737-744`. `$projectedBusinessRelief` is **not**
added to E, so the R2 double count is absent. A partly relieved business is in
`$projectedBusiness` at full value exactly once, because `applyBusinessPropertyRelief()` sets
`is_iht_exempt` only when relief covers the whole value
(`EstateAssetAggregatorService.php:327`). Correct per **IHTM46023 on IHTA 1984 s8D(5)(d)** —
E is struck after liabilities, before exemptions and reliefs.

**One qualification, which is F7 below:** it mirrors in *form*, but the base it is added to is
not the projection of `$totalGrossAssets`.

### 2. Is relief struck on present-day business values correct?

**Yes — the reasoning holds.** `iht_relief_qualifies` is stamped on business rows only
(`EstateAssetAggregatorService.php:154`), business values are deliberately not projected
(`IHTCalculationService.php:681-686`), so the pro-rata denominator `$totalQualifying`
(`:307`) is unchanged and `projected_business_relief_deduction == business_relief_deduction`
by construction. That equality is what the new test asserts, and it is the right assertion.
The removed Vue fallback was right by accident, exactly as the commit says.

The in-code warning is also correct: if business growth is ever added, relief allocated on
today's value against a grown value would **UNDERSTATE** the charge, because the £2,500,000
allowance does not grow — the allocator must be re-run, never scaled.

### 3. W-0470 — which projected-liabilities figure is correct?

**The service's `projectLiabilities()`. Adopting the breakdown's would UNDERSTATE tax.**
Full reasoning at F5.

### 4. Does W-0466's trigger under-fire in a way that matters?

**Yes, and worse than the residual states.** Full reasoning at F4. The commit's residual names
only the farmer-with-no-company case; it also misses **every AIM holder who owns no company** —
the ordinary holding pattern.

### 5. Does W-0467's predicate match `pooledMembers()`?

**Yes. Exactly.** `$isMarried` at `IHTCalculationService.php:125` already encodes
`$spouse !== null`, so `is_married && data_sharing_enabled` is identical to
`count(pooledMembers()) > 1` (`:2330`). No second predicate, no drift. The single/unmarried
branch keeping "your estate" is right. **No error in this claim.**

---

## HIGH

### F1 — A civil partnership's projection pools two people's assets against one person's allowances

**Type:** Incorrect calculation · **Direction: OVERSTATES projected tax**
**File:** `app/Services/Estate/IHTCalculationService.php:125`

```php
$isMarried = in_array($user->marital_status, ['married']) && $spouse !== null;
```

`civil_partnership` is a live enum value
(`database/migrations/2026_04_15_091500_add_civil_partnership_to_users_marital_status.php`),
accepted by `UpdatePersonalInfoRequest:63` and captured by Fyn onboarding. **That migration's
own docblock states `IHTCalculationService` branches on `['married','civil_partnership']`. It
does not.** Nine siblings do — `EstatePlanService:687`, `IntestacyCalculator:27`,
`WillTypePolicy:37`, `ProfileCompletenessChecker:29`, `UserContextBuilder:443`,
`TaxStrategyCalculator:163`. This service is the outlier.

The damage is asymmetric because the two columns use **different predicates**:

| | predicate | file:line |
|---|---|---|
| Current assets / liabilities / allowances | `$isMarried && $dataSharingEnabled` → `pooledMembers()` | `:146`, `:161-163`, `:2330` |
| Projected assets + relief | `$dataSharingEnabled && $spouse` | `:674-676` **(rewritten by `8f09eaddc`)** |
| Projected liabilities | `$dataSharingEnabled && $spouse` | `:1295` |
| Projected properties | `$dataSharingEnabled && $spouse` | `:1264` |
| Projected investments | `$dataSharingEnabled && $spouse` | `:1170` |

`$dataSharingEnabled` is `$hasLinkedSpouse && $user->hasAcceptedSpousePermission()`
(`IHTController.php:51`), and `User::hasAcceptedSpousePermission()` (`User.php:797-843`)
applies **no** marital-status test. So for a civil partnership: `$dataSharingEnabled = true`,
`$spouse !== null`, `$isMarried = false`.

The projected column therefore takes **both partners' assets, liabilities, properties,
investments, cash, business value and business relief** and assesses them against **one
person's £325,000 + £175,000**. The new `$projectedEstateForTaper` (`:741-744`) is struck on
the same doubled estate, so it crosses £2,000,000 roughly twice as fast and strips the
residence band. This is the W-0154 F3 shape the engine was already fixed for once, still live
on one marital status.

**Statute:** IHTA 1984 **s18** (spouse exemption, extended to civil partners by Civil
Partnership Act 2004 s.246 and SI 2005/3229), **s8A** (transferable nil rate band — "spouse
**or civil partner**"), **s8G** (transferred residence band).

Pre-existing — the predicate predates this commit. But `8f09eaddc` rewrote those exact lines
and newly routed **the relief and the taper base** through them, so it is now load-bearing on
two tax-moving figures. Fix is small (`['married','civil_partnership']` at `:125`, plus
`$isMarried &&` on the five projection predicates) but it moves tax and needs its own item.

### F2 — W-0465 fixed two of three implementations; `/plans/estate` still drops the relief

**Type:** Incorrect calculation · **Direction: OVERSTATES the projected net estate by the whole relief**
**File:** `app/Services/Plans/EstatePlanService.php:424`

```php
$ihtCalc['projected_net_estate'] = ($ihtCalc['projected_gross_assets'] ?? 0) - $projectedLiabilities;
```

The identical overwrite that `8f09eaddc` repaired at `IHTController.php:100`, on the other
surface, untouched. On the commit's own £6,000,000 example the `/plans/estate` projected Net
Estate is **£4,250,000 too high**, sitting above a `projected_taxable_estate` (`:499`, passed
through from the service) that *does* net the relief — precisely the non-reconciling column
W-0465 exists to close.

Compounding it: the plan's `projected` block (`:477-508`) publishes **no business-relief key
at all** — `business_relief_deduction` appears only in `current` (`:466`). Even with the net
estate corrected there is no row to explain the gap. Same shape as W-0134 / W-0399, which that
file already carries comments about.

The comment at `:409-437` says "Do not reintroduce arithmetic here: a second derivation of a
figure the service already publishes is the defect, not a safeguard." The line four above it
is exactly that.

---

## MEDIUM–HIGH

### F3 — The W-0466 caveat reaches two of at least four surfaces that print an Inheritance Tax figure

**Type:** Missing disclosure · **Direction: overstates for farmland, understates for AIM held as a business interest — the direction follows the estate's holdings**

The premise stated three times in code and message (`EstateIhtExposureDetector.php:73-74`,
"the teaser is the ONLY Inheritance Tax figure `/m` shows") does not survive a grep:

1. **`/plans/estate`** — `EstatePlanService.php:441-475` builds `iht_summary.current` carrying
   `iht_liability`, `taxable_estate`, `business_relief_deduction` and **no
   `unmodelled_relief_caveat` key**. A business-owning household reads a full figure there,
   unqualified.
2. **`/m` Insights** — `app/Http/Controllers/Api/V1/Mobile/InsightsController.php:151-159`
   prints *"Your estimated Inheritance Tax liability is £X…"*. No caveat.
3. **`/m` `/module/estate`** — `resources/mobile/router.js:48` registers a live `/module/:slug`
   route; `resources/mobile/views/ModuleDetail.vue:109-110` configures the estate screen with a
   hero of **"Estimated IHT liability"** and fields `estate_value, iht_liability,
   nil_rate_band_used, rnrb_used, effective_iht_rate`. Fed by
   `Api/V1/Mobile/ModuleSummaryController::show()` → `EstateAgent::analyze()`, which publishes
   `iht_liability`, `taxable_estate`, `total_allowances`, `nrb_available`
   (`EstateAgent.php:1490-1500`). No caveat — **and it is an allowance breakdown on `/m`,
   which is the thing W-0469 says does not exist.** Nothing in the nav links to it (only the
   router and one spec reference `module-detail`), so it is reachable by URL or a
   `/m?to=/module/estate` deep link. Dormant, not dead.

This is a Rule 20 point, not a copy-the-sentence point: the caveat is correctly published once
by the engine, and three consumers of the engine's tax figure do not read it.

### F4 — W-0466 under-triggers exactly where the error is largest

**Type:** Missing disclosure · **Direction: OVERSTATES tax, silently, in both under-triggered populations**
**File:** `app/Services/Estate/IHTCalculationService.php:212-217` (trigger is `asset_type === 'business'`)

- **AIM shares held in an investment account or ISA.** The ordinary holding pattern, and
  expressible in the schema today (an `InvestmentAccount` row). They take **0% relief** where
  FA 2026 gives **50% outside the allowance** — tax **overstated by ~20% of the holding**
  (50% relief × 40%). The caveat fires only for the *other* AIM case, the one recorded as a
  business interest, which is the case that understates.
- **A pure farming estate.** Land recorded as an `assets` row of type `other` or `property`
  (`database/schema/mysql-schema.sql:332`) carries no Agricultural Property Relief: tax
  **overstated by up to 40% of the land value** — the largest single overstatement this module
  can produce — and the household is told nothing, because it holds no company.

Not proposing to implement either relief (registered dead end). The finding is the *disclosure
scope*: the residual as written understates its own reach, because it names only the farmer
and misses every AIM holder who is not a company owner.

**Refs:** IHTM24000ff (APR); **IHTM25570** and **FA 2026 Sch 12** (unlisted/AIM at 50%,
outside the allowance, from 6 April 2026).

---

## MEDIUM

### F5 — W-0470 ruling: the **service's** figure is correct; the breakdown's is not a projection

**Direction: choosing the breakdown's figure UNDERSTATES tax. The current on-screen mismatch is display-only.**

The breakdown does not project:

- `IHTFormattingService.php:376` — mortgages: `$projectedBalance = ($ageAtDeath >= 70) ? 0 : $userShare`.
  A **binary cliff** on a hardcoded age 70, off a hardcoded horizon of **85** (`:207`, `:232`),
  reading no maturity date. Below 70 the whole balance survives to death; at 70 it vanishes.
- `IHTFormattingService.php:406, 415` — every non-mortgage liability:
  `'projected_balance' => $userShare`. **Never amortised.** This is the source of the £3,500.

The service does:

- `IHTCalculationService::projectSingleLiability():1383-1418` reads the real `maturity_date`,
  or estimates a payoff from balance/payment/rate (`estimatePayoffDate():1423`), amortises
  linearly, returns 0 when the debt ends before death.
- `projectMemberLiabilities():1343-1378` uses the **two-leg** mortgage reader plus ownership
  shares (the W-0336 / W-0228 fixes), where the breakdown uses `mortgageStore->forUser()` and
  `Liability::where('user_id')`.
- It runs on the **household horizon** — `max()` of the two actuarial life expectancies
  (`:620-627`) — the same horizon the projected assets are grown to. The breakdown's age-85
  constant is not that horizon.

**Law:** the deductible liability is the debt outstanding **at death** — IHTA 1984 **s5(3)**,
**s162**, and **s175A** on liabilities actually repaid. A £3,500 balance carried unchanged for
thirty-five years is neither the debt at death nor an estimate of it.

**Why the direction decides the fix:** the breakdown's figure is systematically the **larger**
(nothing amortises). Larger liabilities → smaller taper base E → less taper → more residence
band survives → **less tax**. Adopting it to make the column reconcile moves tax the wrong way.

**Fix:** delete the controller's overwrite of **both** `projected_liabilities`
(`IHTController.php:86`) and `projected_net_estate` (`:100`); rebuild the display breakdown on
`projectMemberLiabilities()`. **Do both call sites** — the identical overwrite pair is at
`EstatePlanService.php:422-424` — or the two surfaces disagree again.

### F6 — The same disease on the **current** column, which W-0470 does not mention

**Direction: display-only (the tax is struck on the engine's figure), but the Liabilities row UNDERSTATES the debt**

`IHTController.php:85` overwrites `$calculation['total_liabilities']` with the breakdown's
total. The breakdown reads `Liability::where('user_id', $user->id)`
(`IHTFormattingService.php:203, 228`) — **one leg**. The engine reads
`Liability::forUserOrJoint()` (`EstateAssetAggregatorService.php:362`). A liability on which
the user is the `joint_owner_id` is inside `total_net_estate` and missing from the Liabilities
row printed beside it, so Gross − Liabilities ≠ Net Estate on the current column too. Whatever
fixes W-0470 should take this row with it.

### F7 — The projected taper base mirrors the current one in form; the estate it is built on does not

**Direction: UNDERSTATES the projected estate, and through the taper base UNDERSTATES projected tax**

`$totalGrossAssets` is every non-exempt row from `gatherUserAssets()`. `$projectedGrossAssets`
(`IHTCalculationService.php:717`) is assembled from five projected category totals. Any row in
the `assets` table — enum `('property','pension','investment','business','other')`,
`database/schema/mysql-schema.sql:332` — whose type is not `business` is in the current gross
and **absent from the projected gross entirely**, hence absent from the projected taper base.
Those rows are user-creatable (`EstateController.php:288`, `CoordinatingAgent.php:4065`).

Pre-existing, outside W-0465's scope, but it is the honest completion of question 1: the
mirror is exact, the surface it reflects is not.

---

## LOW

**F8** — `resources/mobile/views/modules/Estate.vue:184`: `.me-caveat { color: var(--violet-800) }`.
`--violet-800` is not defined in `resources/mobile/style.css` (only `--violet-400`,
`--violet-500`), so the declaration is invalid at computed-value time and the text falls back
to the inherited colour. `HolisticPlan.vue:207` carries the same undefined token, so this
copies an existing pattern rather than inventing one. Background `var(--eggshell-500)` is
defined and fine.

**F9** — `EstateIhtExposureDetector.php:106-108`: `if ($estimatedLiabilityGbp <= 0.0)` is
unreachable, since `$exposed = $estimatedLiabilityGbp > 0.0` (`:60`) and the `! $exposed`
return has already fired. Pre-existing dead branch, now sitting between two live ones.

---

## Verified correct — claims that survived attack

- **No R2 regression, and the mirror is structurally exact.** `:262-265` vs `:737-744`.
- **Present-day relief pairing is sound**, and the growth trap comment is right.
- **An unremarked second correction.** `assessTaxPosition()` passes `$netEstate` to
  `determineIHTRate()` for the **Schedule 1A** baseline, which **IHTM45031** strikes *after*
  reliefs. Before this fix the projected baseline was inflated by the entire relief, making the
  10% test harder to pass and holding at 40% an estate that qualified for 36%. That
  **OVERSTATED** tax and is now fixed. Nothing claims it; recording it here.
- **W-0467's predicate is exact** — see question 5. (Note F1: for a civil partnership the
  predicate is false and the wording faithfully follows the engine; the engine is what is wrong.)
- **No hardcoded tax values in either commit (Rule 2 clean).** `allowance_cap` 2500000 /
  `allowance_cap_effective_date` 2026-04-06 (seeder `:500-501`), `relief_above_cap` 0.5 and
  `aim_shares_outside_cap` true (`:1366-1367`), `rnrb_taper_threshold` 2000000,
  `nil_rate_band` 325000 (`:324-326`), active year 2026/27 (`:1304`). The `£2,500,000` and
  `£2,000,000` in the new code appear only inside comments.
- **W-0469's handoff resolves.** `WebHandoffDestination::ESTATE_IHT → '/estate/inheritance-tax'`
  matches a real route at `resources/js/router/index.js:958`. Swift enum mirrored in the same
  commit.
- **`isCurrentResultShape()` additions are sound but dormant** — `$persist` is false at every
  call site (the method's own docblock, W-0131). Correct in principle; not a live guard.

---

## What I could NOT verify

- **Did not run the test suite** (report-only, no database writes; Pest uses `RefreshDatabase`).
  The four new cases in `tests/Feature/Estate/BusinessPropertyReliefCapTest.php` and four in
  `tests/Unit/Services/Tiers/EstateIhtExposureDetectorTest.php` read as well-constructed — the
  taper pair correctly requires a main residence **and** a `FamilyMember` child, without which
  `rnrb_status` is `'none'` and the assertions are vacuous; the pooled-headline case is green
  because `hasAcceptedSpousePermission()` honours a reciprocal link with no permission row
  (`User.php:838-842`). But I ran none of them.
- **Did not drive the endpoint or the browser.** The £6,000,000 / £4,250,000 / £6,416,253
  figures are the author's; I checked the arithmetic against the code path, not a response.
- **`/m` `/module/estate` end to end.** `EstateAgent::analyze()` publishes `iht_liability` and
  `ModuleDetail.vue` reads that key, but I did not trace the response envelope's nesting, so I
  cannot say whether the screen renders the figure or a dash. Route, config and published key
  are all real.
- **Trust allowances ss.124G–124K** remain unexamined, as the previous round noted —
  `business_interests.trust_id` still makes them reachable.
- **No fixture exercises any of this** outside `BusinessPropertyReliefCapTest.php`. Unchanged:
  the largest business on the dev database is £750,000, under the cap.

## Citations read this round

legislation.gov.uk: IHTA 1984 ss.5(3), 8A, 8D, 8E, 8G, 18, 162, 175A, 124D; Civil Partnership
Act 2004 s.246; SI 2005/3229; Finance Act 2026 Sch 12.
HMRC IHT Manual: IHTM24000ff, IHTM25524, IHTM25570, IHTM45031, IHTM46023.
