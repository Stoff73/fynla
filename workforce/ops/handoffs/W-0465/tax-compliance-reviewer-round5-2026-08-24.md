# tax-compliance-reviewer — Estate / Inheritance Tax verdict, round five

**Scope:** `e4aa4cdc9`, `484197e14`, `a8fa14e21` on `estate-copy-and-m-handoff` — the changes
made in response to round four.
**Mode:** report-only — no code, no migrations, no database.
**Prior rounds:** `W-0463/tax-compliance-reviewer-verdict-2026-08-23.md` (rounds 1–2),
`W-0465/tax-compliance-reviewer-round4-2026-08-24.md` (round 4).
**Not re-reviewed, at the coordinator's instruction:** W-0474 (civil partnership), W-0475
(projected gross omits asset types). Filed from F1/F7 with the reasoning intact.

## Headline

**The tax figures are right.** The W-0470 ruling was implemented correctly and in the correct
direction, no new derivation was introduced, and the caveat wording misstates nothing.

**The fifth-round finding is in the wording fix, not the arithmetic:** the new
married-but-not-pooled branch does **not** cover the group the commit message says it covers —
married users whose partner has no linked account — and that is the largest of the three, and
the one the original W-0467 defect is loudest for. **G1 below.**

**And the round-four F2/F3 fixes reach the payload but not the screen.** The relief row and the
caveat are published to `/plans/estate` and to the `/m` module summary, and both die in
enumerated frontend mappings — the exact W-0134 / W-0399 / W-0465 trap, twice. **G2, G3.**

---

## Answers to the four questions

### 1. F5 / W-0470 — overwrites deleted at both call sites

**Correct, complete, and in the right direction. Verified.**

- **Both call sites are gone.** `IHTController.php:78-105` is now comment only;
  `EstatePlanService.php:413-427` likewise. A grep for
  `['total_liabilities'] =` / `['projected_liabilities'] =` / `['projected_net_estate'] =`
  across `app/` returns one hit, `NetWorthForecastService.php:68`, unrelated to this path.
- **Nothing goes missing.** The engine publishes both keys in its own result —
  `total_liabilities` at `IHTCalculationService.php:450`, `projected_liabilities` at `:807`
  merged in at `:523` — so deleting the assignments leaves no undefined key behind them.
- **`projectMemberLiabilities()` now reaches every surface** that reads the calculation:
  `IHTController`'s `iht_summary`, `EstatePlanService`'s, and anything reading `$calculation`
  directly. Confirmed by grep.
- **The taper base is struck on the figure I ruled correct** — and was already.
  `$projectedEstateForTaper` uses `$projectedLiabilities` from `projectLiabilities()`; the
  deleted overwrites mutated the returned array *after* `calculate()` had finished, so they
  never fed the taper. This is why the change is safe.

**Your F6 question, answered precisely: deleting the `total_liabilities` overwrite changed the
DISPLAY only. It did not change the current column's tax at all.**
`total_net_estate`, `taxable_estate`, `iht_liability` and `$estateForTaper` are all computed
inside `calculate()` from the engine's own `$totalLiabilities` (`:225-265`), before the
controller ever sees the array. **Direction: the displayed Liabilities row RISES to the two-leg
`forUserOrJoint()` figure (or is unchanged where no jointly-owned debt exists); the tax does
not move.** The row and the net estate above it now agree.

**Residual you already flagged, and I agree with the framing:** the per-liability detail rows
still come from `formatLiabilitiesBreakdown()`, so the itemised panel can show −£3,500 against
a £0 total. Worth stating the direction on the board item: **those rows are the non-projected
figures, and the total is the correct one** — a reader adding the rows will not reach the
total, and the total is not what is wrong.

### 2. F2 — `EstatePlanService` relief key and caveat

**The payload is fixed and introduces no second derivation. The screen is not fixed.**

- Overwrite deleted (`:413-427`); `projected.business_relief_deduction` added at `:510`;
  `current.unmodelled_relief_caveat` at `:475`. The service still passes the engine's
  `projected_taxable_estate` and `projected_iht_liability` through untouched. **No new
  derivation anywhere in this commit** — verified across the controller, the plan service and
  the agent.
- **But `/plans/estate` does NOT reconcile the way the Inheritance Tax screen does.** See G2.

### 3. W-0466 farmland name heuristic — direction, and whether it is defensible

**Defensible as implemented, and only because it is quarantined to disclosure.** Full reasoning
and the populations still missed at G4. Short form: it changes no figure, its false positives
are benign, its misses leave the pre-existing state, and it strictly improves on business-only.
**The hard boundary to record: `looksAgricultural()` must never become an input to a relief
calculation.** Agricultural Property Relief turns on agricultural *use and occupation* —
IHTA 1984 **s115(2)**, **s116**, **s117** (two-year owner-occupation / seven-year let) — none
of which is inferable from a name. Business-only would have been strictly worse, not safer.

### 4. The caveat wording

**Nothing in it misstates the treatment.** Checked clause by clause against the live
configuration at G5. In particular the 50%-outside-the-allowance position is **not quantified**,
which is the right call — an unquantified sentence cannot misstate it.

---

## G1 — HIGH. The new headline branch misses the largest of the three groups it names

**Type:** Missing rule (attribution) · **Direction: OVERSTATES the reader's own first-death exposure**
**File:** `app/Services/Tiers/EstateIhtExposureDetector.php:72-74, 137-141`

The new predicate is `is_married && ! data_sharing_enabled`. But
`IHTCalculationService.php:125` defines

```php
$isMarried = in_array($user->marital_status, ['married']) && $spouse !== null;
```

so `is_married` **already requires a linked spouse account**. The new branch therefore reaches
married users **with** a linked account and sharing off or revoked — and does **not** reach the
group the commit message explicitly claims it also covers:

> "it also caught married users whose partner has no linked account"

Those users have `is_married = false`, fall past both new branches, and land on the final one at
`:141`:

> "Your estate could be subject to up to £X in Inheritance Tax."

That is the identical defect W-0467 exists to fix: a married person told their own estate faces
a figure that allows **nothing** for the spouse exemption on assets passing to the survivor
(**IHTA 1984 s18**). Their own first-death liability is typically £0.

**And it is the biggest of the three groups**, because it requires only a marital status and no
linking at all — most married users on the platform have never linked a partner. The commit
found the branch boundary was not where it was recorded, moved it, and stopped one group short.

**Fix shape:** the branch needs the user's marital status directly, or the engine must publish
it separately from `is_married`. `is_married` deliberately conflates "married" with "has a
linked partner" — the same conflation behind W-0474, so the two are worth fixing together.

## G2 — MEDIUM–HIGH. The `/plans/estate` table maps neither the relief nor the caveat

**Type:** Missing rule (unreadable estate) · **Direction: the tax figure is correct and unchanged; the displayed Net Estate is correct; the gap explaining it is the WHOLE relief, unlabelled**
**File:** `resources/js/components/Plans/Estate/EstateCurrentSituation.vue:131-243`

`tableProps()` enumerates `grossAssets`, `liabilities`, `netEstate`, `allowances`,
`allowancesProjected`, `charitableExemption` (`:204`), `estateAfterNRB`, `taxableEstate`,
`ihtLiability`. **There is no `businessRelief` key, in either column.**

So on `/plans/estate` a business-owning household now reads Gross, Liabilities, and a Net Estate
that is lower than gross − liabilities by up to £4,250,000 on the worked example, **with no row
saying why**. Before `e4aa4cdc9` the projected net estate was gross − liabilities and at least
appeared to add up — wrongly. The number is now right and the column is now *less* readable.

This is round one's **F3** ("`business_relief_deduction` published, zero frontend consumers —
the estate stops adding up") recurring on the second surface. F3 was fixed for `IHTPlanning.vue`
only.

`unmodelled_relief_caveat` has the same fate: published at `EstatePlanService.php:475`, and no
file under `resources/js` outside `IHTPlanning.vue` reads it. **`/plans/estate` still prints an
unqualified Inheritance Tax figure on screen.**

## G3 — MEDIUM–HIGH. The caveat dies in `/m`'s enumerated field list

**Type:** Missing disclosure · **Direction: overstates for farmland, understates for AIM shares held as a business interest — unchanged from round four on this surface**
**Files:** `app/Agents/EstateAgent.php:344-351`; `resources/mobile/views/ModuleDetail.vue:107-111`

`EstateAgent` publishes `unmodelled_relief_caveat` into `summary`, with a comment stating
"`/m` Insights and the `/m` module-detail screen both read it". **The module-detail screen does
not.** `ModuleDetail.vue:110` renders an enumerated list —

```js
fields: ['estate_value', 'iht_liability', 'nil_rate_band_used', 'rnrb_used', 'effective_iht_rate', 'status'],
```

— plus a hero (`:109`), and the template renders only `rows` built from that list
(`ModuleDetail.vue:26-31`). The caveat is in neither, so it renders nowhere. `/m`
`/module/estate` prints the same unqualified figure it printed before this commit.

Fourth instance of the same trap in this work item alone: W-0134, W-0399, W-0465, and now here.
The engine publishing a field is not the same as a surface showing it, and this codebase has
enumerated mappings on **three** frontends.

The `/m` Insights attachment (`InsightsController.php:151-172`) is correct in construction —
appended to the one string the insight contract allows, sentence still from the engine — and is
moot while W-0473 stands.

## G4 — MEDIUM. What the farmland heuristic still misses, and it all runs one way

**Direction: every remaining miss OVERSTATES tax and says nothing about it**
**File:** `app/Services/Estate/IHTCalculationService.php:77-86, 246-249, 2410-2430`

1. **`other` rows named without a listed term.** "Manor Farm" and "Home Farm" match (the
   boundary is a leading one, and a space precedes "Farm"); "20 acres, Ludlow", "Lower Meadow",
   "Ty Newydd" do not. **`acre`/`acres` and `meadow` are the two most defensible additions.**
   `land` is correctly absent — `\bland` would fire on "Land Rover".
2. **Farmland recorded as a `properties` row.** `looksAgricultural()` returns false for anything
   that is not `asset_type === 'other'` (`:2412-2414`). A working farmhouse is routinely both
   the main residence and agricultural property — HMRC treats the agricultural value of a
   farmhouse under **IHTM24050 ff** — and `properties.property_type` has no agricultural member.
   A farmer who records the farm as their home sees nothing. **Overstates by up to 40% of the
   agricultural value.**
3. **Shares listed on the Alternative Investment Market held in an investment account or ISA.**
   Unchanged, still no caveat. **This is the population round four's F4 named and CSJ's direction
   did not address** — the direction was about the `other` bucket, not about investment
   accounts. **Overstates by ~20% of the holding** (50% relief × 40%). Worth putting back to CSJ
   as a distinct question, because the two were conflated in the response.

**On defensibility, for the record.** A name heuristic is acceptable here for one reason: it
gates a *sentence*, not a *figure*. Its two failure modes are asymmetric in the right direction —
a miss leaves the pre-existing unqualified state; a false positive shows a conditional sentence
("If your estate holds farmland or shares listed on that market…") whose condition a non-farmer
simply does not meet, so it misleads nobody about their tax. It strictly increases the correctly
warned population over business-only, which showed a farmland sentence to company owners and
withheld it from farmers. **Leaving it business-only pending the schema change would have been
worse, not more cautious.** The commit is right to label it a heuristic and right to name the
agricultural asset type as the durable fix.

**Two documentation/test defects that make it less auditable than it reads:**

- `:2405` claims word-boundary matching means "'croft' does not fire on 'Croftwood Ltd'".
  **It does fire.** `preg_match('/\bcroft/', 'croftwood ltd')` matches — the boundary is at the
  start of the term only, with no trailing `\b`. Same for `orchard` → "Orchardson",
  `paddock` → "Paddockhurst". Benign in direction (a disclosure false positive), but the comment
  is wrong and should not stand as documentation of the behaviour.
- The word-boundary test case is **vacuous**. "Pharmacy fixtures" contains no substring "farm"
  at all — p-h-a-r-m — so `str_contains` would reject it too. The test named "the discriminating
  half" discriminates nothing. A case that actually exercises the boundary is a term appearing
  mid-word: "Landcroft Holdings" against `croft`, which `\bcroft` correctly rejects.

## G5 — Caveat wording: verified, nothing misstated

Text (`IHTCalculationService.php:264`):

> "This figure does not include Agricultural Property Relief, and does not apply the special
> treatment of shares listed on the Alternative Investment Market. If your estate holds farmland
> or shares listed on that market, your actual liability could be higher or lower than shown —
> it is worth discussing with a regulated financial adviser or a specialist solicitor."

Checked against the live configuration, not against my own table:

| Clause | Verdict | Evidence |
|---|---|---|
| "does not include Agricultural Property Relief" | **True** | no APR code path exists anywhere |
| "does not apply the special treatment of shares listed on the Alternative Investment Market" | **True** | `applyBusinessPropertyRelief()` gives every qualifying asset 100%-to-allowance then 50%, with no market branch; configured `aim_shares` 0.5 and `aim_shares_outside_cap` true have **no reader** (`TaxConfigurationSeeder:1364-1367`) |
| "higher or lower than shown" | **True, both directions live** | APR absent → overstates; AIM inside the allowance → understates |
| Adviser signpost | **Appropriate** | perimeter-safe, no efficacy claim |

- **The 50%-outside-the-allowance position from 6 April 2026 is not quantified, and should not
  be.** An unquantified sentence cannot misstate it. Nothing in the wording contradicts
  **FA 2026 Sch 12** or **IHTM25570**.
- "shares listed on the Alternative Investment Market" is accurate as a description. For the
  record: that market is **not** a recognised stock exchange for Inheritance Tax, which is
  precisely why the shares qualify as unquoted — the sentence does not assert otherwise, so
  there is no error to correct.
- Rule 9 satisfied; the test asserts the acronym's absence
  (`EstateIhtExposureDetectorTest.php:226`).
- **Completeness note, not a defect:** the sentence does not say the £2,500,000 allowance is
  **shared** between agricultural and business relief (`cap_shared_with_bpr`). For a household
  holding both farmland and a company, modelling Agricultural Property Relief would not simply
  reduce the bill — the two reliefs would compete for one allowance. "Higher or lower" covers
  it; stating it would be better still.

---

## Adjacent — pre-existing, and the caveat is now bound to it

### A1 — MEDIUM–HIGH. `EstateAgent` uses a FOURTH pooling predicate, ignoring consent

**Direction: OVERSTATES relative to the consented calculation, and reads a partner's assets without consent**
**File:** `app/Agents/EstateAgent.php:145-147`

```php
$spouse = $user->spouse;
$dataSharingEnabled = $spouse !== null;
```

No `hasAcceptedSpousePermission()`, no marital status. So the module summary — and through it
`/m` `/module/estate` and `/m` Insights — computes the household's Inheritance Tax by **pooling
the spouse's assets whenever an account is linked, including when sharing is off or has been
revoked**. Two consequences:

- The same user is quoted a different figure here from `/estate/inheritance-tax`. That is the
  **W-0464** defect the detector was rewritten to close, still live one surface along.
- It reads a partner's assets without consent, which is the state **W-0347** exists to prevent.

A pooled second-death figure on two estates is typically larger than the same person's own
estate against single allowances, so the agent's figure typically **overstates**. Not introduced
by these commits — but `e4aa4cdc9:351` attached the caveat to this summary, so the newly
published sentence is now bound to a figure computed on a predicate none of the other three
surfaces use. Deserves its own item alongside W-0474.

*(I checked and discarded a lazy-loading concern here: `analyze()` eager-loads `spouse` at
`EstateAgent.php:65-75`, so `$user->spouse` does not violate `preventLazyLoading`.)*

### A2 — LOW. The new branch's call to action is for a state it cannot reach

`EstateIhtExposureDetector.php:138` ends "Linking your accounts gives a fuller picture." The
branch requires `is_married`, which requires a linked spouse account — so it tells an
already-linked user to link. **The action for this branch is to turn sharing on.** Copy only,
no tax movement.

### A3 — LOW. Round four's F9 is still open

`EstateIhtExposureDetector.php:110-112` — `if ($estimatedLiabilityGbp <= 0.0)` is unreachable,
because `$exposed` is defined as `> 0.0` at `:63` and the `! $exposed` return has already fired.
Not claimed as fixed; recorded so it is not lost.

---

## Verified correct — what survived attack this round

- The W-0470 ruling is implemented faithfully and in the direction that does not move tax the
  wrong way. Both overwrites gone, both keys still published, taper base untouched.
- **F6 answered: display only, no tax movement.** The overwrite ran after `calculate()` returned.
- No second derivation introduced in `IHTController`, `EstatePlanService` or `EstateAgent`.
  (`EstateCurrentSituation.vue:212-216` derives `estateAfterNRB` client-side — pre-existing,
  unchanged, and not a tax figure.)
- The caveat sentence misstates nothing, and is right not to quantify.
- **F8 fixed correctly:** `--violet-500` is defined (`resources/mobile/style.css:32`) where
  `--violet-800` was not; `--eggshell-500` (`:39`) correctly left alone.
- The farmland trigger's implementation matches its stated intent — `other` rows only,
  prefix-at-word-boundary, narrow term list, Bitcoin excluded.
- The two headline branches that *were* changed say true things: the pooled sentence correctly
  attributes a pooled second-death figure, and the married-but-not-pooled sentence correctly
  states that the figure allows nothing for anything passing to a partner.

## What I could NOT verify

- **Did not run the test suite** (report-only, no database writes). The commit reports 4,664
  passing with one timing-sensitive red in `TaxStrategyCalculator`; I did not observe either.
- **Did not drive the browser.** The £6,000,000 reconciliation in `e4aa4cdc9` is the author's.
- **`/plans/estate` and `/m` `/module/estate` rendering** is read from the components, not
  observed on screen. G2 and G3 are grounded in the enumerated mappings at
  `EstateCurrentSituation.vue:131-243` and `ModuleDetail.vue:107-111`; I did not load either page.
- **The farmland trigger has no production data behind it** — the commit measured the `assets`
  table as empty on dev, so the heuristic's real-world hit rate is unmeasured in both directions.
- **Trust allowances ss.124G–124K** remain unexamined, three rounds running.

## Citations read this round

legislation.gov.uk: IHTA 1984 ss.5(3), 18, 115(2), 116, 117, 125, 162, 175A, 124D; Finance Act
2026 Sch 12.
HMRC IHT Manual: IHTM24050 ff, IHTM25524, IHTM25570, IHTM45031, IHTM46023.
Live configuration: `database/seeders/TaxConfigurationSeeder.php:324-326, 500-502, 1304,
1364-1371`.
