---
id: W-0397
title: The mobile dashboard is the only per-user estate figure that does not exclude Inheritance-Tax-exempt assets — one user, two estates, £500,000 apart
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0029-cycle4-wills-and-estate-figures.md
owner: build-lead
reviewers: [quality-lead]
status: handoff
claimed_by: build-lead
severity: high
surfaces: [web, m]
created: 2026-08-23T01:45:00Z
claimed: 2026-08-23T02:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-23
prior_art_found: [W-0391, W-0154, W-0188]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Raised while fixing **W-0391**. The dispatch called for "one mechanism answers
what is this user's net estate, and every consumer reads it". Measured, the
landscape is narrower and sharper than that.

### The consequence first, because it is real money and not a display error

**An estate inflated by Inheritance-Tax-exempt assets raises the charitable 10%
threshold — which can deny a user the reduced 36% rate they have actually
earned.**

`summary.net_estate` is not only rendered. It is the baseline handed to
`WillAnalysisService::analyzeCharitableBequests()` (`EstateAgent:211`) and to
`calculateOptimalGiftingStrategy()` (`:183`). David Jones's £500,000 of defined
contribution pensions inflated that baseline by half a million pounds, so the
threshold he had to clear to reach the reduced rate was **£50,000 higher than his
estate actually warrants**. A user who gives enough to qualify is told they have
not, and pays 40% instead of 36%.

**That is W-0154's third defect — a charitable test run against a baseline that
does not correspond to the computation it qualifies — occurring in a second
place**, after being fixed in the engine.

**The visible symptom of it** is the narrative on the same data: *"X's estate of
£1,489,500 exceeds the combined allowances of £850,000, resulting in £343,512
Inheritance Tax"* — an estate figure that **cannot produce the tax stated beside
it**. A user checking the arithmetic finds it does not reconcile, and nothing on
the screen explains why.

**The dashboard disagreeing with the will page is how it was FOUND. The paragraph
above is why it mattered.**

### Measured 2026-08-23, caches cleared before every read (W-0381)

| Mechanism | David (16) | Sarah (17) | Consumer |
|---|---|---|---|
| `IHTCalculationService` → `user_net_estate` | **989,500** | **739,280** | Will Planning tab, since W-0391 |
| `NetWorthAnalyzer::generateSummary` → `net_worth` | **989,500** | **739,280** | `/m` estate screen |
| `EstateAgent` → `data.summary.net_estate` | **1,489,500** | 739,280 | `/api/v1/mobile/dashboard` |

Confirmed over live HTTP: the mobile dashboard returns `estate.net_estate =
1489500` for David while every other surface says 989,500.

### The whole difference is one missing filter

All three read the same source, `EstateAssetAggregatorService::gatherUserAssets()`.
The first two `reject()` rows flagged `is_iht_exempt`
(`IHTCalculationService.php:167-168`). `EstateAgent` does not. David's assets
include £180,000 and £320,000 of defined contribution pensions, both flagged
exempt — **exactly the £500,000 by which the dashboard differs.**

### Why Sarah cannot show it

Sarah's only exempt asset is an NHS defined benefit scheme valued at £0, so her
figure is identical under both. **Testing this household through Sarah alone can
never surface this defect.** Stated rather than discovered later.

## FIXED — the fence came down mid-batch

team-lead released `EstateAgent.php` and `EstateAssetAggregatorService.php` on
2026-08-23 after the agent holding them stood down, and instructed this be closed
in the same batch rather than leaving W-0391 half-agreeing with the dashboard.

### The fix

`EstateAgent::buildAssetSummary()` now rejects `is_iht_exempt` assets before
summing. **`gross_estate`, `net_estate` and the liquidity breakdown are all
filtered on the same set** — a breakdown that does not reconcile to its own total
is how this class of defect hides, and filtering only the total would have made
the estate card internally contradictory instead of contradicting another screen.

Filtered at the source rather than at each reader, because the figure is not only
displayed. It is the baseline for the charitable 10% test
(`analyzeCharitableBequests()`) and for `calculateOptimalGiftingStrategy()`, and
**an estate inflated by exempt assets raises the charitable threshold — which can
deny a user the reduced Inheritance Tax rate they have actually earned.** That is
W-0154's third defect appearing in a second place.

### There was no consumer for whom the unfiltered figure was right

Enumerated before changing it, because two of the five read it as `net_worth`
rather than as an estate:

| Reader | Reads it as | Verdict |
|---|---|---|
| `MobileDashboardAggregator:308` | `estate.net_estate` | wanted the filtered figure |
| `DashboardAggregator:407` | `net_worth` | **`NetWorthAnalyzer` — the module that actually answers "what am I worth" — already excluded exempt assets.** Filtering makes the estate card agree with it instead of diverging |
| `CoordinatingAgent:839` | `net_worth` → HolisticPlanner | same |
| `EstatePlanService:793` | `netEstate`, then `to_beneficiaries = netEstate − ihtLiability` | the two terms now describe the same estate |
| `EstateAgent:1295/462` narrative | *"X's estate of £N exceeds the combined allowances of £A, resulting in £L Inheritance Tax"* | was arithmetically incoherent — an estate figure that could not produce the stated tax |

### Live, all three surfaces, caches cleared

```
user 16   mobile dashboard 989,500 | will page 989,500 | /m estate screen 989,500
user 17   mobile dashboard 739,280 | will page 739,280 | /m estate screen 739,280
```

David was 1,489,500 on the dashboard before this. **W-0391's last open acceptance
criterion is now met for both accounts.**

Breakdown reconciles: 99,750 + 172,500 + 887,750 = 1,160,000 = `gross_estate`.

### Observed, NOT introduced, NOT fixed here

`summary.effective_tax_rate` reads **19.87% for both spouses**, and
`summary.iht_liability` reads **£343,512 for both** — the household figures sitting
beside a now-per-user estate. Unchanged by this fix (both read 19.87 before and
after). That is W-0154 / W-0188's household-versus-individual split, which is
already at `handoff`, and widening into it mid-batch would have been scope creep
into a tax-reviewed area.

## Why it was not fixed at first pass in F-0029

`app/Agents/EstateAgent.php` and
`app/Services/Estate/EstateAssetAggregatorService.php` were both explicitly
fenced off from that batch, and one was live under another agent at the time.
Crossing the fence to change a shared aggregator mid-flight would be the
collision the fence exists to prevent.

**Consequence, stated plainly:** W-0391's acceptance criterion "the mobile
dashboard agrees with the will page for the same user" is **met for Sarah and
not met for David**, and cannot be met without this item.

## Acceptance

- [x] The definition is decided and stated in the code, not left to a missing
      `reject()`.
- [x] One user sees one own-estate figure across the will page, the `/m` estate
      screen and the mobile dashboard — verified live on both accounts.
- [x] Asserted against a fixture holding a **non-zero** exempt asset (£300,000 of
      pension against £400,000 of savings). **This is the crux:** Sarah's only
      exempt asset is valued at £0, so a test built on her shape passes whether
      the filter exists or not. A regression case with no exempt asset is kept
      alongside and is documented as unable to discriminate.
- [x] Every reader of the field enumerated before changing it — five, none of
      which wanted the unfiltered figure.
- [x] The breakdown reconciles to its own total.
- [x] Mutation-tested: removing the `reject()` turns exactly the two exempt-asset
      cases red and leaves the no-exempt-asset guard green.
- [x] Plans, dashboard and mobile suites re-run — 291 passed, no regressions.
- [x] **Rendered page read** for the will surface (David `£989,500`). The
      dashboard agreement itself is evidenced over live HTTP above.


### Browser verification — 2026-08-23, localhost:8000, Playwright

**Tab established as nobody** on arrival (both token stores empty) — checked
rather than assumed, and it was the state team-lead warned about. Logged in
through the real form on each account and confirmed identity with
`GET /api/auth/user` before reading anything: **id 16 David Jones**, then
**id 17 Sarah Jones**. `estate_analysis_16` / `_17` cleared by hand before each
read (W-0381).

Read verbatim off `/estate/will-builder`:

| | David (16) | Sarah (17) |
|---|---|---|
| Spouse line | `100% of your own estate to your spouse (£989,500)` | `100% of your own estate to your spouse (£739,280)` |
| Executors | Sarah Jones · Barclays Wealth | **David Jones** · Barclays Wealth |
| Specific Gifts | `£10,000 to Cancer Research UK` | `£10,000 to British Heart Foundation` |
| Residuary | Sarah Jones — 100% | David Jones — 100% |

The two estate figures **differ**, each is its owner's, and **neither £1,728,780
nor £1,716,780 appears anywhere on either page**. Nobody is their own executor.
Every gift names its recipient.

Screenshots:
`tests/Persona/20-08-2026_run/pass-a-web/150-web-david-will-own-estate-989500-executor-sarah-gift-named-W-0391.png`
`tests/Persona/20-08-2026_run/pass-a-web/151-web-sarah-will-own-estate-739280-executor-david-gift-named-W-0391-W-0393-W-0395.png`

## Working notes

- 2026-08-23 build-lead: fixed after team-lead released the fence.
  `tests/Feature/Estate/IHTPerUserNetEstateTest.php`, the W-0397 block.
  Both fenced files re-read before editing, per instruction — `EstateAgent:109`
  had been routed through `LifeCoverReach::policiesCovering()` and
  `LifeCoverCalculator` had gained ownership-aware branching; neither is touched
  by this change, which is confined to `buildAssetSummary()`.
  Not self-certified — handed to quality-lead.
