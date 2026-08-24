---
id: F-0016
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/08-process.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-21T22:05:00Z
status: active
---

# F-0016 — Cycle 1: the missing chattels class, and the expenditure nobody entered

**Agent:** build-lead (`cycle1-surfaces`) · **Written:** 2026-08-21
**Branch:** `dev` (shared working tree — nothing committed, no PR, no deploy, by instruction)
**Number issued by team-lead.**

| Item | Status | Notes |
|---|---|---|
| W-0138 chattels absent from `/m`'s estate | **DONE (fault 1 of 3)** | `handoff` → quality-lead. Faults 2 and 3 untouched — **do not close the item** |
| W-0140 the plan states an expenditure never entered | **DONE** | `handoff` → quality-lead. All five surfaces. Browser verification outstanding, by instruction |
| Business interests, same aggregation gap | **DONE** | Follow-up issued by the team lead after the W-0138 census raised it. §7 |
| The two red `Stores` tests from a terminated batch | **DONE** | Caller fixed first, then the tests rewritten to the new contract. §8 |

Handoff notes: `ops/handoffs/W-0138/build-to-quality-2026-08-21.md` ·
`ops/handoffs/W-0140/build-to-quality-2026-08-21.md`.

---

## 1. What was actually wrong, in one line each

**W-0138.** `CrossModuleAssetAggregator` — the class whose docblock calls itself "a single
source of truth" — aggregated property, investments and cash, and had no chattels.

**W-0140.** `UserProfileService::expenditurePresentation()` already computed and published
an honest composition of the expenditure figure. The plans read past it and printed the
composed total under a label naming one of its two components.

Both are the same disease in different places: **the honest version already existed one
method away, and the surface took the number and dropped the meaning.**

---

## 2. W-0138 — chattels

### Prior art, six sources, outcome `extend`

`EstateAssetAggregatorService::gatherUserAssets()` already aggregates chattels — which is
why the web Inheritance Tax table itemises them and the tester saw £132,250/£60,750 there.
The `/m` estate screen does not read that service. It reads `/api/estate/net-worth` →
`EstateController::getNetWorth` → `NetWorthAnalyzer::generateSummary` →
`CrossModuleAssetAggregator::getAllAssets`, and that collection had no chattels. Acceptance
4 asked for one source, not a second `/m` aggregation, so the outcome was to extend the
aggregator that already exists.

The check also found **four** implementations of "this user's share of a chattel":
`EstateAssetAggregatorService:143`, `NetWorthService:123`, `UserProfileService:667`, and
`MobileDashboardAggregator:349`. Two now read the aggregator.

### The change

`app/Services/Shared/CrossModuleAssetAggregator.php`

- `:184-212` `getChattelAssets()` — `Chattel::forUserOrJoint()` + `calculateUserShare()`,
  built exactly like the property/investment/savings siblings beside it.
- `:75-79` `getAllAssets()` concatenates it · `:214-227` `calculateChattelTotal()` ·
  `:239` `getAssetTotals()` gains `chattel` · `:340-343` `getAssetBreakdown()` gains it.

Consolidation, per Rule 20:

- `app/Services/NetWorth/NetWorthService.php:123-126` — delegates. Same query, same trait,
  **identical result**; the method survives because `Phase03ArchitectureTest` pins its
  existence, and the `Chattel` import survives because `getAssetsSummary` still uses it.
- `app/Services/UserProfile/UserProfileService.php:667-671` — now reads
  `$breakdown['chattel']`. **This one changes arithmetic**, and it is a correction: the code
  it replaced read `$user->chattels` (a `user_id`-only relation) and multiplied by
  `ownership_percentage` unconditionally, so a *joint owner* saw £0 of a chattel and an
  individually-owned record stored below 100% was scaled down when it is wholly theirs.

### Why no client changed

Three clients read `/api/estate/net-worth`: `/m`
(`resources/mobile/views/modules/Estate.vue:133`), web (`services/estateService.js:54`) and
native iOS (`ios-native/Fynla/Features/Estate/EstateClient.swift:29`). **All three already
map `chattel → 'Possessions'`** (`Estate.vue:87`, `EstateView.swift:292`). They were never
sent the row. One server change, three surfaces, **no `/m` bundle rebuild, no iOS build.**

### Proof

Read-only against the persona household (no write to users 16 or 17):

| | property | investment | **chattel** | cash | assets | liabilities | net |
|---|---|---|---|---|---|---|---|
| David 16 | 755,500 | 172,500 | **132,250** | 99,750 | **1,160,000** | 182,500 | **977,500** |
| Sarah 17 | 637,500 | 132,500 | **60,750** | 31,030 | **861,780** | 122,500 | **739,280** |

Exactly R-18 §1.1/§2.8, ownership splits included — Manchester's 40% inside David's
£755,500 and **absent from Sarah's**, which was the run's most important isolation
requirement and still passes. R-18's expected net of 977,750 for David is a £250 slip
against its own inputs; 977,500 is correct.

`tests/Feature/Estate/EstateNetWorthChattelsTest.php` — 3 tests, 16 assertions, real records
through the real endpoint. **Confirmed red before the fix** (2 failed) and green after.

---

## 3. W-0140 — the expenditure nobody entered

### The decision this was built to

Recorded on the item by the team lead, and it is **not** the acceptance list at the top:

> The figure keeps its meaning — recorded entries **plus** financial commitments — and the
> plan carries the disclosure the profile already has. **No arithmetic value changes.**

Reasoning, so nobody re-litigates it: Disposable Income must subtract commitments to be
true. Changing the figure to "only what the user typed" would leave Disposable Income
subtracting less than the household is committed to, and every affordability statement in
the plan rests on it.

### The change

Backend, extending what existed:

- `UserProfileService::expenditurePresentation()` (`:512-548`) — adds
  `manual_annual_total`, `commitments_annual_total`, `has_recorded_expenditure`, and
  **stops the basis line naming a component the user does not have**. `summary_only_reason`
  corrected the same way.
- `DisposableIncomeAccessor::getForUser()` (`:30-64`) — the one method all four plan
  services already call — carries an `expenditure_composition` through. It recalculates
  nothing.
- Four plan services pass it into `personal_information`: `EstatePlanService:615`,
  `ProtectionPlanService:239`, `RetirementPlanService:295`, `InvestmentPlanService:508`.

Frontend — **the panel is repeated five times, so the disclosure is written once:**

- `resources/js/utils/expenditureComposition.js` **(new)** — labels, the `'None recorded'`
  string, and the decision about when the basis note shows.
- `resources/js/components/Plans/Shared/PlanExpenditureComposition.vue` **(new)** — one copy
  of the markup; `currencyMixin` formats (Rule 5), no icons (Rule 15), no scores (Rule 12).
- Four panels, one line each at `:69` — Estate, Investment, Retirement, Protection.
- `planPrintMixin.js:2029-2033, 2039` — the adviser pack builds from the **same util**, so
  the pack and the screens cannot drift.

### Proof

| | annual_expenditure | recorded | commitments | has_recorded |
|---|---|---|---|---|
| David 16 | 52,394.40 | **29,400.00** | **22,994.40** | true |
| Sarah 17 | 14,820.00 | **0.00** | **14,820.00** | **false** |

Both totals identical to before. Basis for Sarah: *"Financial commitments only — no
expenditure recorded"*.

Tests: `tests/Unit/Services/Plans/PlanExpenditureCompositionTest.php` (3, real records);
`resources/js/components/__tests__/Plans/PlanExpenditureComposition.spec.js` (7 — including
**one per panel**, so deleting the line from any one of the four goes red);
`.../planPrintExpenditureComposition.spec.js` (3, the fifth surface).

Two stale test doubles updated (`EstatePlanRefactorTest:42`,
`DisposableIncomeAccessorTest:29,54`) — they mocked the accessor without the new key. **No
`??` fallback was added in the plan services to paper over it**: the accessor is the one
home and always returns the key.

---

## 4. What a reader of this branch must not mistake for a defect

**The property-costs component openly carries W-0172's error** — the Manchester mortgage
charged at joint 50% where tenants-in-common 40% is due — until `fix-batch-F` lands. That
is deliberate and was decided before the work started: showing the composition makes the
error *visible* instead of buried inside one unreconcilable number, and it self-corrects
the moment W-0172 lands. **Tell the tester before they look.**

---

## 5. Raised, not fixed

1. **Business interests are still absent from `CrossModuleAssetAggregator`.** The same hole
   as chattels, one class along. Invisible to `peak_earners`; `entrepreneur` would see it.
   One method, modelled on the chattel one. Not taken because the scope was set at chattels.
2. **`NetWorthService::getJointAssets()` (`:587`)** lists joint assets via
   `where('user_id', …)`, so an asset where the user is the *joint* owner is missing — every
   class, not just chattels.
3. **`NetWorthAnalyzer::generateSummary()` returns a `health_score`** (0-100) in a payload
   three surfaces read. No client renders it today. Rule 12 hazard sitting in the API.
4. **`ComprehensiveEstatePlanService` injects `NetWorthAnalyzer` and never calls it**
   (`:36`) — dead injection.
5. **`IncomeOccupation.vue:193`** shows the composed expenditure figure with no composition
   beside it, on the profile. Different surface from the five in scope.
6. **`planPrintMixin.js` has three pre-existing `no-unused-vars` errors** at `:133-135`.
   **Confirmed present at HEAD** by linting the stashed file. `npm run lint` lints changed
   files, so they will surface on this branch and are not from this work.
7. **`tests/Feature/Stores/PropertyHttpIntegrationTest.php` has 2 failures** (422 where 201
   is expected). `app/Http/Requests/StorePropertyRequest.php` and `PropertyController.php`
   are modified in the shared working tree by another agent — **another batch's in-flight
   work, not this one's.** Nothing here touches property validation.

---

## 6. Files changed

Backend: `Services/Shared/CrossModuleAssetAggregator.php` ·
`Services/NetWorth/NetWorthService.php` · `Services/UserProfile/UserProfileService.php` ·
`Services/Plans/DisposableIncomeAccessor.php` · `Services/Plans/{Estate,Protection,Retirement,Investment}PlanService.php`

Frontend: `utils/expenditureComposition.js` **(new)** ·
`components/Plans/Shared/PlanExpenditureComposition.vue` **(new)** ·
`components/Plans/Shared/planPrintMixin.js` ·
`components/Plans/{Estate,Investment,Retirement,Protection}/*PersonalInformation.vue`

Tests: `tests/Feature/Estate/EstateNetWorthChattelsTest.php` **(new)** ·
`tests/Unit/Services/Plans/PlanExpenditureCompositionTest.php` **(new)** ·
`resources/js/components/__tests__/Plans/*.spec.js` **(2 new)** ·
`tests/Unit/Services/Plans/{EstatePlanRefactorTest,DisposableIncomeAccessorTest}.php` (doubles)

**Green:** `Feature/Estate` + `Unit/Services/Estate` + `Architecture` — 488 passed, 1
skipped. `Unit/Services/Plans` + `Feature/Plans` + `Unit/Services/UserProfile*` +
`Feature/Api/UserProfileIncomeSummaryTest` — 108 passed. Vitest — 18 passed across the four
relevant specs. `pint` clean on every changed PHP file.

**Not done, by instruction:** no browser verification (the tester closes that loop), no
commit, no PR, no deploy, no bundle rebuild, no tool-schema capture.


---

## 7. Follow-up: business interests (issued by the team lead, 2026-08-21)

The identical hole one class along, raised in §5 above and then taken. Same shape as
chattels: `getBusinessAssets()` and `calculateBusinessTotal()` on
`CrossModuleAssetAggregator`, wired into `getAllAssets()`, `getAssetTotals()` and
`getAssetBreakdown()`; `NetWorthService::calculateBusinessValue()` delegates;
`UserProfileService::calculateAssetsSummary()` reads the breakdown, which closes the same
joint-owner hole its chattel line had. **`/m` and iOS already map
`business → 'Business interests'`** — no client edit, no rebuild.

**One deliberate deviation from the siblings, because the alternative was building a
relief model inside an aggregation fix.** Every sibling sets `is_iht_exempt => false`. On a
business that is not always true — Business Property Relief depends on `bpr_eligible`,
trading status and two years' ownership. **The key is omitted**, with a comment pointing at
`EstateAssetAggregatorService::gatherUserAssets()`, which models relief for the Inheritance
Tax path. Nothing reads that field on this collection (verified: neither `NetWorthAnalyzer`
nor `AdviserExportPackService` touches it), so omitting it changes no behaviour.

### A second defect closed by the consolidation, which nobody raised

`UserProfileService::calculateAssetsSummary()` summed `$user->businessInterests` — a
`user_id`-only relation — exactly as its chattel line summed `$user->chattels`. **So a
co-owner's share of a business, or of a chattel, was worth nothing on the profile's assets
summary and in the `net_worth` derived from it.** Not a consequence of the aggregation fix
worth leaving implicit: it is a **second defect**, on two asset classes, fixed because the
share arithmetic now has one home. Nothing on the board raised it; the persona household
happens to hold every chattel as primary owner, which is why no run has seen it.

### The share rule is NOT uniform across asset classes — read this before any future consolidation

`ownership_percentage` on a business interest is a **shareholding**. It therefore applies
even to an **individually** held record — owning 60% of a company you hold in your own name
means 60% of its value is yours. For property, cash, investments and chattels, "individual"
means **all of it** and the percentage is ignored.

`CalculatesOwnershipShare` already encodes this (it detects a business interest by the
presence of `current_valuation` **and** `business_name`), so this fix added no arithmetic.
**A future consolidation that assumes the classes are uniform — or a refactor that
simplifies that detection away — breaks exactly this**, silently, by valuing a 60%
shareholding at 100%. `EstateNetWorthBusinessInterestsTest` pins it, and it is written here
because a test only fails after someone has already made the change.

`tests/Feature/Estate/EstateNetWorthBusinessInterestsTest.php` — 60% of a £500,000
individually-held company plus 70% of a £200,000 shared one = **£440,000**; the partner
gets the **£60,000** complement; a third account's £777,000 company reaches neither.

**Persona household unchanged** — David 1,160,000 / net 977,500, Sarah 861,780 / net
739,280. It holds no business interests, which is exactly why the run could not see this.

---

## 8. Follow-up: the two red `Stores` tests (a terminated batch's unresolved conflict)

**The decision was CSJ's, taken before this work: a 100/0 split IS individual ownership, so
a stated 100 on a shared asset is refused rather than silently rewritten to 50/50.** These
two tests asserted the old coercion and passed a literal `100`.

### The caller first, and most of it was already done

Of the five form callers, the terminated batch had already fixed two — `PropertyForm.vue`
(states a share only for tenants in common, where the input exists, and never for the
mortgage section) and `ChattelFormModal.vue` (joint only). Checked and found already
correct: `SaveAccountModal.vue` never sends the field at all;
`BusinessInterestForm.vue` has a real share input and a watcher that clears the individual
default; `ExpenditureForm.vue:2057` is a display computation, not a payload.

**The gap was `resources/js/components/Investment/AccountForm.vue`** (`:1050-1059`). It has
**no** ownership-share input anywhere, but keeps `ownership_percentage` in `allowedFields`,
so on edit it forwarded whatever the account resource carried. Switching an individual
account (100) to joint therefore sent a **stated** 100 on a joint payload — now refused,
with no field on screen for the user to correct. It now omits the field on a shared type,
matching its two siblings.

**Rule 19 — checked, nothing to do.** Neither `/m` nor `ios-native/` states a share on any
write; every reference on both is read or display. They already say nothing and get the
default.

### Then the tests — rewritten, not deleted

- `PropertyHttpIntegrationTest` — the wizard test now sends **no** share, which is what the
  form now sends, and still asserts 50/50. What it used to assert, and why that changed, is
  recorded in the test.
- `InvestmentAccountHttpIntegrationTest` — renamed from *"…even though the form always sends
  100"* to *"…when the form states no share"*, payload updated, outcome assertions unchanged
  (one row, full value, 50/50, both spouses shown £47,500). Its W-0014 history and the
  W-0040 change are both recorded in the test body.
- **Two new tests, one per file**, pinning the other half of the rule: a stated 100 on a
  joint payload is **refused** with a validation error on `ownership_percentage`, and
  **nothing is stored** — refused means refused, not rewritten.

**Green:** `tests/Feature/Stores` 196 passed; `InvestmentControllerTest` +
`PropertyControllerTest` 29 passed; consolidated run across everything this branch touched
253 passed (14 deprecated, no failures). `pint` clean on both test files. **`AccountForm.vue`
carries one pre-existing `no-unused-vars` at `:908`** — confirmed present at HEAD by linting
the stashed file, same situation as `planPrintMixin.js` in §5.
