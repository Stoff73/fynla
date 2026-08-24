---
id: F-0028
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/05-perimeter.md, core/constitution/08-process.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-23T00:00:00Z
status: active
---

# F-0028 — Cycle 4: a £0 total above the £500,000 policy counted on the same card

**Agent:** build-lead (`fix-cycle4-mprotection`) · **Branch:** `dev` (shared working tree)
**Board items:** W-0384 (fixed), W-0401 (raised) · **ID block:** W-0401 – W-0410
**F number:** taken after checking `fixes/` — F-0027 was the highest, so **F-0028**.
Team-lead was told the number taken rather than the agent choosing silently
(`FORMATS.md` §"Branch-document numbers").

**Predecessors, read before touching anything here:** `W-0186` built `LifeCoverReach`,
the reader this routes to. `F-0027` / `W-0341` / `W-0342` routed the estate consumers to
it and **found this defect in the browser** while verifying that batch.

---

## 1. The defect, and the one sentence that explains it

On `/m/app/protection` as **Sarah Jones (17)**:

> **Total lump-sum cover £0** · Across **1** policy.
> Debt protection **HIGH** — £122,500 short · Final expenses **HIGH** — £7,500 short
> POLICIES — Vitality · *Joint life with David Jones — recorded on their account* · **£500,000**

**A £0 total, an accurate count of 1, and the £500,000 policy it is counting, on one
card** — with two HIGH shortfalls derived from the £0. Web told her at the same moment
that her debt-protection shortfall was **£0**.

**Two mechanisms behind one card.** The count came from the reach-aware policy list
W-0186 fixed (`resources/mobile/views/modules/Protection.vue:182`); the total came from
`coverage_gaps.totals.cover` (`:184`) ←
`ProtectionGapPresentationService.php:32-40`, which still passed
`$user->lifeInsurancePolicies` — **the exact relation `LifeCoverReach`'s own docblock
names as the one every consumer used and which stopped at the account that typed the
policy in.**

**This is W-0186's own defect shape in a second place. This time the total is the wrong
half** — W-0186 routed the LIST and left the TOTAL behind it.

---

## 2. Why web was right and `/m` was wrong — the root disease, named

Web did not agree with `/m` and then diverge. **Web was right by accident of path, and
the two surfaces have never read the same mechanism for this question:**

| Surface | Path | Reader | State |
|---|---|---|---|
| web `/protection` | `POST /api/protection/analyze` → `ProtectionAgent::analyze()` :108 | `policiesCovering()` | routed in W-0186 |
| `/m` + iOS | `GET /api/protection` → `ProtectionGapPresentationService::forUser()` :32 | raw `user_id` relation | **never routed** |

Verified: `grep -rn "coverage_gaps" resources/js/` returns **nothing**. Web reads
`state.analysis.data.gaps` (`store/modules/protection.js:128`); `/m` reads
`payload.coverage_gaps`. **Two mechanisms answer "what is this user's protection gap",
and W-0186 fixed one of them.** That is a Rule 20 finding about the architecture, not
about this fix, and it is raised in §7 because it will produce a third instance.

---

## 3. Why it survived every previous pass — and the rule it produces

**It is invisible from the owner's account.** David owns the policy, so the `user_id`
relation finds it and `/m` shows him £700,000, correctly. Every assertion, every fixture
and every manual check built from his side passes whether the reader reaches or not.

> **For any shared record, the non-owning side is the untested side.**

That is why this cycle's ownership defects have almost all been found from the second
account, and why **a fixture built only from the owner cannot fail.** It is the *Fixture*
variant of `tests/CLAUDE.md` §4 — the misconception lives in the data the test sets up,
so the branch is never entered and nothing in the test file says so.

---

## 4. The fix

**One line of behaviour, two consumers, one read.**

`app/Services/Protection/ProtectionGapPresentationService.php`

```php
$lifePoliciesCovering = $this->lifeCoverReach->policiesCovering($user);
// ...
$coverage = $this->gapAnalyzer->calculateTotalCoverage($lifePoliciesCovering, ...);
$lifePolicies = $this->lifePolicyReferences($lifePoliciesCovering);
```

**The second consumer was not in the dispatched item and is half the defect.** `:42`
passed the same broken relation to `lifePolicyReferences()`, which builds
`relevant_policies` — rendered on `/m` (`Protection.vue:64-66`) **and** iOS
(`ProtectionView.swift:208-210`). Sarah's every category listed **zero** policies.
Fixing only the total would have left "£500,000 of cover" sitting above a list of no
policies: the same card disagreeing with itself, one layer down. **Both halves now come
from one read**, so they cannot drift apart again — which is the point of Rule 20, not
merely a tidiness preference.

`LifeCoverReach` is same-namespace, so no `use` import was added and the
formatter-strips-a-new-import trap (`tests/CLAUDE.md` §2) does not apply here.

**Critical illness deliberately stays the plain relation.** `SHOW COLUMNS FROM
critical_illness_policies` — run in this batch, not inherited from W-0341's note:

```
id, user_id, policy_type, provider, policy_number, sum_assured, premium_amount,
premium_frequency, policy_start_date, policy_end_date, policy_term_years,
conditions_covered, created_at, updated_at, deleted_at
```

**No `joint_life`, no `joint_owner_id`, no ownership columns at all.** A critical illness
policy covers only its owner and there is nothing to reach with. (`life_insurance_policies`
by contrast carries `in_trust`, `joint_life`, `beneficiaries` — and no `joint_owner_id`,
which is why the second life is found through `users.spouse_id`.)

---

## 5. Measured, on the live persona, both accounts

`php artisan tinker`, `ProtectionGapPresentationService::forUser()` directly.

| Figure | Sarah (17) before | Sarah after | David (16) before | David after |
|---|---|---|---|---|
| `totals.cover` | **£0** | **£500,000** | £700,000 | **£700,000** |
| `totals.need` | £130,000 | £130,000 | £178,000 | £178,000 |
| `totals.shortfall` | £130,000 | **£0** | £0 | £0 |
| `coverage_percentage` | 0 | 384.62 | 393.26 | 393.26 |
| debt protection | **gap HIGH, £122,500 short** | **covered, £0** | covered, £0 | covered, £0 |
| final expenses | **gap HIGH, £7,500 short** | **covered, £0** | covered, £0 | covered, £0 |
| income protection | gap HIGH, £72,000 short | **gap HIGH, £72,000 short** | gap HIGH, £87,000 | gap HIGH, £87,000 |
| `relevant_policies` per lump-sum category | **0** | **1** | 1 | 1 |

**David is identical on every field.** Asserted, not assumed — a fix that moved the
owner's answer would be a different and worse bug.

### The item's framing corrected: two of the three gaps were false, not three

W-0384 reports three HIGH shortfalls derived from the £0. **Only two were.** The
**income-protection £72,000 is genuine** — Sarah holds no income-protection policy, and
`income_protection_policies` has nothing to reach with either. It correctly survives the
fix unchanged, and there is a test pinning it so that a later reader does not "finish the
job" by widening the reach into a table that cannot support it.

---

## 6. The test design, and the traps it was built against

`tests/Feature/Api/ProtectionGapPresentationTest.php` — **+5 cases**, added to the
existing home for this contract rather than a new parallel file.

### Collision — the fixture is asymmetric, and includes the non-owning side

**One joint policy is one number seen from two directions, so £500,000 is the answer both
when the reach works and when each account sees only its own half.** A fixture built from
the policy's owner cannot fail — which is precisely how this shipped. Sarah therefore
holds **£120,000 of her own, single-life**, and David holds **£200,000 of critical
illness**, so every hypothesis lands on a different number:

| Hypothesis | Sarah's `totals.cover` | David's `totals.cover` |
|---|---|---|
| **Correct** — life reaches, critical illness does not | **£620,000** | **£700,000** |
| `user_id`-only (the shipped bug) | £120,000 | £700,000 |
| Critical illness reached too | £820,000 | £700,000 |
| Reach applied to the owner as well | £620,000 | £820,000 |

Her own single-life policy does second duty: it proves the reach is limited to
**joint-life** policies, because it must never appear in David's answer.

### Mutation-tested in both directions — three mutations

Each bug was restored and the suite re-run.

| # | Mutation | Went red | Stayed green |
|---|---|---|---|
| M1 | shipped bug restored — relation-only for **both** halves | non-owner total; critical-illness-not-reached; policy list | **owner control**; income protection |
| M2 | **half-fix** — total routed, `relevant_policies` left on the relation | policy list only | the other four |
| M3 | over-reach — critical illness widened to the spouse | non-owner total; critical-illness-not-reached | **owner control**; policy list; income protection |

**The owner's control case stayed green under every mutation.** That is the direction
that mattered: a fix that changed David's answer too would be wrong, and no mutation
could make his case fail without the reader over-reaching.

**M2 is why the policy-list assertion is separate from the total.** A half-fix — exactly
the shape W-0186 shipped, one of two consumers routed — is caught by one case and nothing
else. Without it the suite would accept the same mistake a second time.

**Decoy check (`tests/CLAUDE.md` §4, fifth variant): M1 IS the check.** Removing the
reach call reddens three cases, which proves the cases resolve and exercise
`LifeCoverReach` through the container rather than merely naming it in a docblock. A
`grep` would have shown the name; the mutation shows the call.

### W-0383 re-checked on this second route, not assumed

`relevant_policies` is a **second route to the same policy**, so the withholding W-0383
applied to `LifeInsurancePolicyResource` was verified here rather than trusted. The
reference shape is exactly `['id','type','provider','name','cover']` — asserted as a
whole-key-list equality, so a future field added to `lifePolicyReferences()` fails the
test rather than silently shipping a contract detail to the non-owner.

### What the fixture does NOT contain

No deleted spouse, no one-sided link, no permission rows — all four spouse-link states
are already covered by `LifeCoverReachSpouseLinkStatesTest` (F-0027, 16 cases) and are
not re-tested here. This file tests the **presentation contract**; the gate underneath it
has its own home.

---

## 7. Surfaces (Rule 19)

**Backend-only. No `resources/js/`, `resources/mobile/` or `ios-native/` file is touched,
and none needs to be.**

The `/m` component is innocent: `Protection.vue:184` reading `coverage_gaps.totals.cover`
is correct behaviour over a payload that was wrong. Web, `/m` and iOS all read
`GET /api/protection`, so the fix lands once in the reader and reaches all three by
architecture.

**No `/m` rebuild is required.** `/m` serves `public/m-build/`, which only changes when a
`resources/mobile/` source file changes. Nothing here does. Build artefacts remain
team-lead's; the bundle step was skipped deliberately, not forgotten.

### The cost this adds, measured rather than waved past

`ProtectionController::index` now computes the reach **twice** per request — once at `:94`
for the policy list (W-0186) and once at `:49` of the presentation service. Counted with
`DB::enableQueryLog()` on a linked user:

```
1st policiesCovering()          3 queries   (spouse row, reciprocity exists, joint policies)
2nd, same model instance        2 queries   (reciprocity exists, joint policies)
```

**Two extra queries per request**, both single-key indexed lookups; `liveSpouse()` caches
per model instance so the spouse row is not re-read. Not a defect, and not worth trading
correctness for — but it is real and it is recorded rather than discovered later.

**The clean fix is to memoise `policiesCovering()` per user instance inside
`LifeCoverReach`**, which would serve every one of its consumers at once. That file is
F-0027's and was not in the scope issued to this batch, so it is noted here and belongs
with the consolidation item in §10 rather than being taken unilaterally.

### The double-count risk, checked rather than assumed

`LifeCoverReach` carries an explicit warning that its per-life answer must **never** be
summed across a household: one joint-life policy is in both accounts' answers on purpose,
so adding them reports one £500,000 payout as £1,000,000. Making this payload per-life
therefore had to be checked against every consumer, not just the one that was wrong.

`grep -rn "gapPresentation\|ProtectionGapPresentation\|coverage_gaps" app/` returns
**one production consumer**:

| Site | Question | Verdict |
|---|---|---|
| `ProtectionController::index:111` → web, `/m`, iOS protection screens | *"is this life covered?"* — per-user | **per-life is correct** |
| `WhatIfScenarioService:41` | a metric-**name** allowlist string (`'total_coverage'`), not a reader of this payload — the service does not inject it | not a consumer |

**No household view aggregates `coverage_gaps`**, so the reach cannot double-count through
this route. The household question has its own reader, `householdCoverInTrust()`, which is
untouched here.

### Browser verification — DONE, `localhost:8000`, both accounts, both surfaces

**Every identity below was taken from `GET /api/auth/user` with the token that surface was
actually using**, never from `fynla-state.auth.user`, and never by recognising a figure —
the figures are the thing under test.

**The relayed session state was wrong, which is why the check exists.** Team-lead reported
the tab as signed in as David on both surfaces; it was signed in as **nobody** — `/protection`
redirected to `/login`. Had that hint been trusted, the first screen read would have been
attributed to the wrong account.

**The two surfaces hold genuinely separate tokens, confirmed concretely:**
`sessionStorage.auth_token` = token **115** (desktop) and `localStorage.m_scaffold_token` =
token **116** (`/m`) — two different Sanctum tokens on one origin. Each was authenticated
deliberately and each verified against the server on its own token.

| Surface | Account (server-confirmed) | Reading |
|---|---|---|
| `/m/app/protection` | **Sarah Jones, id 17** | **Total lump-sum cover £500,000 · Across 1 policy** · one gap only: Income protection HIGH £72,000 p.a. · Vitality £500,000 *"Joint life with David Jones — recorded on their account"* |
| web `/protection` | **Sarah Jones, id 17** | **Total Life Insurance £500,000** · allocated to debts £122,500 · **Debt Protection: none, Shortfall £0** |
| `/m/app/protection` | **David Jones, id 16** | **Total lump-sum cover £700,000 · Across 2 policies** — unchanged · one gap only: Income protection HIGH £87,000 p.a. |
| web `/protection` | **David Jones, id 16** | **Total Life Insurance £500,000** · Critical Illness £200,000 · **Debt Protection: none, Shortfall £0** — unchanged |

Screenshots: `workforce/ops/handoffs/W-0384/W-0384-sarah-m-protection-after.png`,
`W-0384-david-m-protection-control.png`.

**The £0 is gone and both false HIGH gaps with it.** Sarah's `/m` card previously read
"Total lump-sum cover £0 · Across 1 policy" with Debt protection HIGH £122,500 and Final
expenses HIGH £7,500. Both are now absent from the screen; **the only gap remaining is the
income-protection one, which is genuine.**

### One thing that looks like a disagreement and is not — read the labels

Web says **"Total Life Insurance £500,000"** for David while `/m` says **"Total lump-sum
cover £700,000"** for the same man at the same moment. **These are two different metrics,
not two answers to one question:** web's figure is life cover only; `/m`'s is life *plus*
critical illness. For Sarah they coincide at £500,000 because she holds no critical illness;
for David they differ by exactly his £200,000 critical illness policy.

**So the surfaces agree wherever they measure the same thing** — which is the acceptance
criterion, stated precisely rather than as a bare "£500,000 on both".

### What the browser settled, and what it could NOT

**Settled:** the non-owner's total is no longer £0; it is £500,000; the two false HIGH gaps
are gone; the genuine income-protection gap survives; the owner's screens are unchanged on
both surfaces; and web and `/m` agree for the same user at the same instant.

**NOT settled, and it cannot be from any screen.** One joint-life policy is one number seen
from two directions, so **£500,000 is the answer both when the reach works and when each
account merely sees its own half.** What the browser proves is that neither account is shown
£1,000,000 and that Sarah — who owns no policy at all — is shown £500,000, which a pure
`user_id` read cannot produce. **The discrimination between "reached" and "each side sees
its own" lives in the fixture, at the asymmetric £620,000, and in mutations M1–M3 — not on
any screen.**

### iOS — not verified, not claimed

`ios-native/` decodes this same payload (`ProtectionModels.swift:87`, `ProtectionView.swift:208`)
and the key shape is unchanged, so it should follow by architecture. **It was not built, not
launched and not looked at.** Stating it rather than letting silence imply coverage (Rule 19).

---

## 8. Test runs

`DB_DATABASE=laravel_testing_q` — exclusive to this agent; the database did not exist and
was created for this batch.

- `ProtectionGapPresentationTest` — **6 passed, 65 assertions**
- `ProtectionGapPresentationTest` + `tests/Feature/Protection` +
  `tests/Unit/Services/Protection` + `ProtectionAgentTest` + `ProtectionAgentGoalsTest` —
  **200 passed, 620 assertions**
- `ClientCompatibilityContractTest` + `ModuleSummaryTest` +
  `MobileDashboardAggregatorTest` (the `/m` and iOS contract shape) — **45 passed,
  323 assertions**
- Pint clean on both changed files.

**Not run:** the full suite. Rule 17 / `feedback_no_full_suite_per_small_change`.

---

## 9. Files

| File | Change |
|---|---|
| `app/Services/Protection/ProtectionGapPresentationService.php` | `LifeCoverReach` constructor-injected; one read of `policiesCovering()` feeds **both** `calculateTotalCoverage()` and `lifePolicyReferences()`; critical illness left `user_id`-scoped with the schema reason stated |
| `tests/Feature/Api/ProtectionGapPresentationTest.php` | **+5 cases**, asymmetric two-account fixture, mutation-tested three ways |

---

## 10. Raised, not fixed here

- **W-0401 — NOW FIXED**, scope granted mid-batch by team-lead. See §11.
- **The duplication in §2 — two mechanisms for one question.** `ProtectionAgent::analyze()`
  and `ProtectionGapPresentationService::forUser()` both answer "what is this user's
  protection gap", for different surfaces, and W-0186 routed one. They are **not** exact
  duplicates — the presentation service adds the category / assumption / explanation
  contract that `/m` and iOS decode — so collapsing them is a design item with its own
  prior-art record, not something to smuggle into a fix batch. It is the reason this
  defect exists twice and it will produce a third instance.
- **Make the wrong call impossible — do not go looking for the fifth caller.**
  `calculateTotalCoverage()` accepts any `Collection`, so **it is correct only when every
  caller remembers a convention.** Two callers forgot. **This same helper produced this
  same defect twice, found six weeks apart** — W-0384 on the `/m` and iOS card, W-0401 on
  the advice surface — and both were found by accident rather than by the type system.

  **A mechanism that depends on every caller remembering will fail again**, and the next
  instance will be found the same way: by a person looking at the second account. The fix
  shape is therefore to make the unreached collection unrepresentable at the boundary —
  not to audit for the next caller and route it, which is what this batch and F-0027 have
  now done four times between them.

  It is a signature change across four call sites, so **it is raised, not done**: it needs
  its own work item and its own prior-art record, and smuggling it into a fix batch is how
  a batch stops being reviewable.

---

## 11. W-0401 — the plan told the non-owner to buy cover she already has

**Scope for `app/Services/Coordination/PlanSources/ProtectionStrategySource.php` was
requested and granted mid-batch.** It was found by enumerating every mechanism as Rule 20
requires, not predicted, and measured before being raised.

### The census, complete — four callers of `calculateTotalCoverage()`

| Caller | life-policies argument | State |
|---|---|---|
| `ProtectionAgent:111` (`analyze`) | `policiesCovering()` | correct (W-0186) |
| `ProtectionAgent:407` (`buildScenarios`) | `policiesCovering()` | correct (W-0186) |
| `ProtectionGapPresentationService:32` | raw relation | **fixed — W-0384** |
| `ProtectionStrategySource:68` | raw relation | **fixed — W-0401** |

**The class's own docblock says it *"Mirrors `ProtectionAgent::analyze()` for the gap +
profile build"*.** The agent was routed to the reach in W-0186; **the mirror was not.**
That sentence is the defect, written down in the file, six weeks before it was found.

### Measured on the live persona

```
BEFORE                                          AFTER
recommendations(Sarah, 17) = 2                  recommendations(Sarah, 17) = 1
   * "Add decreasing term cover for debts"  <- phantom, GONE
   * "Add income protection insurance"           * "Add income protection insurance"
recommendations(David, 16) = 1                  recommendations(David, 16) = 1
   * "Add income protection insurance"           * "Add income protection insurance"
```

Against the already-routed path into the **same** `RecommendationEngine`, for the same user
at the same moment: `ProtectionAgent::analyze(17)` → `debt_protection_gap = 0`.

**One recommendation engine, two input paths, opposite advice.** This is not a wrong total
on a card — **it is a recommendation to purchase a financial product she does not need,
generated from a figure the application itself knows is wrong.** That is why it is filed
high and framed as advice rather than as a display bug.

**Verified at the consuming surface too**, signed in as Sarah with `/m`'s own token:
`RecommendationsAggregatorService::getRecommendationsByModule(17, 'protection')` returns
**one** recommendation — *"Add income protection insurance — £72,000.00 per year"* — with no
debt-cover entry. David returns one, *"…£87,000.00 per year"*, unchanged.

### Test and mutation

`tests/Unit/Services/Coordination/ComposedProtectionPlanTest.php` — **+2 cases**, same
asymmetric household. Sarah's own single-life policy is deliberately **£120,000 against a
£150,000 debt need**, so the bug and the fix land on different recommendation sets rather
than the same one:

| Hypothesis | Sarah's life cover | `protection_life_cover_gap` |
|---|---|---|
| **Correct** — the joint policy reaches her | **£620,000** | **absent** |
| `user_id`-only (the shipped bug) | £120,000 vs £150,000 of debt | **present** |

**M4 — the shipped bug restored:** the non-owner case went **red**, the **owner control
stayed green**, and the three pre-existing cases in the file stayed green — no collateral.

**The absence assertion is guarded.** `recommendations()` wraps everything in
`catch (Throwable) { return []; }`, so an exception in the routed call would empty the plan
entirely and **pass a naive "the phantom is absent" assertion**. The case therefore also
asserts the list is **not empty** and still contains her genuine income-protection
recommendation, so a silent throw fails rather than reads as success.

**Run:** `ComposedProtectionPlanTest` — **5 passed, 12 assertions**. Pint clean; the
`LifeCoverReach` import survived the formatter (checked with `grep -n '^use '`, since this
file is in a different namespace and did need one).
