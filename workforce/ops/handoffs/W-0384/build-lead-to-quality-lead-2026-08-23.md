# W-0384 — build-lead → quality-lead, 2026-08-23

**Agent:** `fix-cycle4-mprotection` · **Branch doc:** `workforce/branches/fixes/F-0028-cycle4-m-protection-gap-reach.md`
**Branch:** `dev`, shared working tree · **No commit, no PR** (per dispatch)
**Covers:** W-0384 **and W-0401** (scope extended mid-batch by team-lead)

---

## What was done

**One defect, one file, two consumers of one read.**

`app/Services/Protection/ProtectionGapPresentationService.php` — `LifeCoverReach`
constructor-injected; a single `policiesCovering($user)` read now feeds **both**
`calculateTotalCoverage()` (`:53`) and `lifePolicyReferences()` (`:62`).

**The dispatched item named only the total. The second consumer is half the defect and
was not in the brief:** `:42` passed the same broken relation to `lifePolicyReferences()`,
which builds `relevant_policies` — rendered on `/m` (`Protection.vue:64-66`) **and** iOS
(`ProtectionView.swift:208-210`). Sarah's every category listed **zero** policies. Fixing
only the total would have left "£500,000 of cover" above a list of no policies: the same
card disagreeing with itself, one layer down.

`tests/Feature/Api/ProtectionGapPresentationTest.php` — **+5 cases**, added to the
existing home for this contract rather than a new parallel file.

### Measured on the live persona, both accounts

| Figure | Sarah (17) before | Sarah after | David (16) before | David after |
|---|---|---|---|---|
| `totals.cover` | **£0** | **£500,000** | £700,000 | **£700,000** |
| `totals.shortfall` | £130,000 | **£0** | £0 | £0 |
| debt protection | **HIGH, £122,500 short** | **covered, £0** | covered | covered |
| final expenses | **HIGH, £7,500 short** | **covered, £0** | covered | covered |
| income protection | HIGH, £72,000 | **HIGH, £72,000** | HIGH, £87,000 | HIGH, £87,000 |
| `relevant_policies` per lump-sum category | **0** | **1** | 1 | 1 |

**David is identical on every field — asserted, not assumed.**

---

## What was NOT done, and why

1. **iOS was NOT verified and is NOT claimed.** `ios-native/` decodes this same payload
   (`ProtectionModels.swift:87`, `ProtectionView.swift:208`) and the key shape is unchanged,
   so it should follow by architecture — but it was not built, not launched and not looked
   at. Saying so rather than letting silence imply coverage (Rule 19).

2. **The consolidation is raised, NOT done — deliberately.** `calculateTotalCoverage()`
   accepts any `Collection`, so **it is correct only when every caller remembers a
   convention**, and two callers forgot. **This one helper produced this one defect twice,
   found six weeks apart**, both times by a person looking at the second account rather
   than by the type system. The fix shape is to make the unreached collection
   unrepresentable at the boundary. That is a signature change across four call sites and
   needs its own work item and prior-art record — F-0028 §10.

3. **The two-mechanism duplication underneath all of this is not consolidated.** Web reads
   `ProtectionAgent::analyze()`; `/m` and iOS read `ProtectionGapPresentationService`.
   They answer the same question and W-0186 routed one of them — which is why this defect
   now exists three times. They are **not** exact duplicates (the presentation service adds
   the category/assumption/explanation contract iOS decodes), so collapsing them is a
   design item with its own prior-art record. Recorded in F-0028 §10, not smuggled into a
   fix batch.

4. **No `/m` rebuild.** The change is backend-only; no `resources/mobile/` file is touched,
   so `public/m-build/` is unaffected. The bundle step was skipped **deliberately** — build
   artefacts are team-lead's.

5. **No full test suite.** Rule 17 / `feedback_no_full_suite_per_small_change`. Targeted
   families only, listed below.

---

## What the receiver needs that is not obvious

- **`app/Services/Protection/LifeCoverReach.php` shows as modified in `git status`, and
  that is NOT this batch.** It is `fix-cycle4-lifecover`'s uncommitted F-0027 work in the
  shared tree. **This fix does not depend on it**: `policiesCovering()` existed before
  F-0027 and reached then too — F-0027 only tightened *which* spouse links qualify. If
  F-0027 were reverted, these tests still pass; only the link-state gating changes.

- **The test database is `laravel_testing_q`.** It did not exist and was created for this
  batch. Exclusive to this agent — do not run a second Pest process against it.

- **Run the mutations before trusting the suite.** The value of these cases is in what
  they redden, and M2 in particular exists because a *half*-fix — one of two consumers
  routed, exactly the shape W-0186 shipped — is caught by one case and nothing else.

- **`relevant_policies` is a second route to the same policy**, so W-0383's withholding of
  `policy_number` and `beneficiaries` from the non-owner was re-verified here rather than
  assumed. The reference shape is asserted as a **whole-key-list equality**
  (`['id','type','provider','name','cover']`), so a future field added to
  `lifePolicyReferences()` fails the test rather than silently shipping a contract detail
  to the other life assured.

- **Two extra queries per `GET /api/protection` request**, measured with
  `DB::enableQueryLog()`: the controller computes the reach at `:94` and the presentation
  service again at `:49`. Both are single-key indexed lookups; `liveSpouse()` caches per
  model instance. Not a defect; the clean fix is memoising inside `LifeCoverReach`, which
  is F-0027's file and out of scope here.

---

## Assumptions made

1. **The per-life question is the right one for this payload.** Checked, not assumed:
   `grep -rn "gapPresentation\|coverage_gaps" app/` returns exactly **one** production
   consumer, `ProtectionController::index:111` → the per-user protection screens on web,
   `/m` and iOS. **No household view aggregates it**, so the reach cannot double-count
   through this route — the trap `LifeCoverReach`'s docblock warns about.

2. **Critical illness must not be widened.** Verified in this batch with `SHOW COLUMNS FROM
   critical_illness_policies` rather than inherited from W-0341's note: no `joint_life`, no
   `joint_owner_id`, no ownership columns at all. Mutation M3 pins it.

3. **The item's own framing is corrected: two of the three HIGH gaps were false, not
   three.** The **income-protection £72,000 is genuine** — Sarah holds no income-protection
   policy and that table has nothing to reach with either. It correctly survives the fix
   unchanged, and a test pins it so a later reader does not "finish the job" by widening
   the reach into a table that cannot support it.

4. **The `/m` component is innocent.** `Protection.vue:184` reading
   `coverage_gaps.totals.cover` is correct behaviour over a payload that was wrong. The
   backend was checked first, as dispatched.

---

## Evidence

**Mutation-tested in both directions — three mutations, each caught by the right case:**

| # | Mutation | Went red | Stayed green |
|---|---|---|---|
| M1 | shipped bug restored — relation-only for **both** halves | non-owner total; critical-illness-not-reached; policy list | **owner control**; income protection |
| M2 | **half-fix** — total routed, `relevant_policies` left on the relation | policy list only | the other four |
| M3 | over-reach — critical illness widened to the spouse | non-owner total; critical-illness-not-reached | **owner control**; policy list; income protection |

**The owner's control case stayed green under every mutation** — the direction that
mattered, since a fix that moved David's answer would be a different and worse bug.
**M1 doubles as the decoy check:** removing the reach call reddens three cases, proving
they resolve and call `LifeCoverReach` through the container rather than merely naming it.

**Fixture is asymmetric and includes the non-owning side** (`tests/CLAUDE.md` §4,
Collision). The non-owner holds **£120,000 of her own single-life**, the owner **£200,000
of critical illness**, so all four hypotheses separate: correct **£620,000** · shipped bug
**£120,000** · critical illness over-reached **£820,000** · reach applied to the owner too
**£820,000 on David**. A fixture built from the policy's owner cannot fail — which is
exactly how this shipped.

**Test runs** (`DB_DATABASE=laravel_testing_q`):

- `ProtectionGapPresentationTest` — **6 passed, 65 assertions**
- \+ `tests/Feature/Protection` + `tests/Unit/Services/Protection` + `ProtectionAgentTest`
  \+ `ProtectionAgentGoalsTest` — **200 passed, 620 assertions**
- `ClientCompatibilityContractTest` + `ModuleSummaryTest` + `MobileDashboardAggregatorTest`
  (the `/m` and iOS contract shape) — **45 passed, 323 assertions**
- Pint clean on both changed files. Final run performed on the restored tree **after** all
  mutations were reverted.

---

## Files

| File | Change |
|---|---|
| `app/Services/Protection/ProtectionGapPresentationService.php` | `LifeCoverReach` injected; one read feeds the total and the policy references; critical illness left `user_id`-scoped with the schema reason stated |
| `tests/Feature/Api/ProtectionGapPresentationTest.php` | +5 cases, asymmetric two-account fixture, mutation-tested three ways |
| `workforce/branches/fixes/F-0028-cycle4-m-protection-gap-reach.md` | new branch doc |
| `workforce/ops/board/W-0401-...md` | new — the fourth call site |


---

## Browser verification — DONE, both accounts, both surfaces

**Every identity below came from `GET /api/auth/user` on the token that surface was actually
using.** Never `fynla-state.auth.user`, never by recognising a figure.

**The relayed session state was wrong and the check caught it.** The tab was reported as
signed in as David on both surfaces; it was signed in as **nobody** — `/protection`
redirected to `/login`. **The two surfaces hold separate Sanctum tokens on one origin**,
confirmed concretely: `sessionStorage.auth_token` = **115** (desktop),
`localStorage.m_scaffold_token` = **116** (`/m`). Each was authenticated deliberately and
verified on its own token.

| Surface | Account (server-confirmed) | Reading |
|---|---|---|
| `/m/app/protection` | **Sarah, 17** | **£500,000 · Across 1 policy**; only gap = Income protection HIGH £72,000 p.a. |
| web `/protection` | **Sarah, 17** | **Total Life Insurance £500,000**; **Debt Protection none, Shortfall £0** |
| `/m/app/protection` | **David, 16** | **£700,000 · Across 2 policies** — unchanged; only gap = Income protection HIGH £87,000 p.a. |
| web `/protection` | **David, 16** | Total Life Insurance £500,000 + Critical Illness £200,000; Shortfall £0 — unchanged |

Screenshots alongside this note: `W-0384-sarah-m-protection-after.png`,
`W-0384-david-m-protection-control.png`.

**A label difference that is NOT a disagreement.** Web's *"Total Life Insurance"* is life
cover only; `/m`'s *"Total lump-sum cover"* is life **plus** critical illness. They coincide
at £500,000 for Sarah (no critical illness) and differ by exactly £200,000 for David. **The
surfaces agree wherever they measure the same thing** — which is the criterion, stated
precisely rather than as a bare "£500,000 on both".

### What the browser settled, and what it could NOT

**Settled:** the £0 is gone; Sarah reads £500,000; both false HIGH gaps are gone; the genuine
income-protection gap survives; the owner is unchanged on both surfaces; web and `/m` agree.

**NOT settled, and no screen can settle it.** One joint-life policy is one number seen from
two directions, so **£500,000 is the answer both when the reach works and when each account
merely sees its own half.** The browser proves neither account is shown £1,000,000, and that
Sarah — who owns no policy at all — is shown £500,000, which a pure `user_id` read cannot
produce. **The discrimination lives in the fixture at the asymmetric £620,000 and in
mutations M1–M3, not on any screen.**

---

## W-0401 — also fixed, in this batch

`app/Services/Coordination/PlanSources/ProtectionStrategySource.php:68` routed to the same
reader. **Its own docblock says it "Mirrors `ProtectionAgent::analyze()`" — the agent was
routed in W-0186 and the mirror was not.**

```
BEFORE  recommendations(Sarah, 17) = 2   ["Add decreasing term cover for debts", "Add income protection insurance"]
AFTER   recommendations(Sarah, 17) = 1   ["Add income protection insurance"]
        recommendations(David, 16) = 1   unchanged
```

**This is not a wrong total on a card — it is a recommendation to purchase a financial
product she does not need, generated from a figure the application itself knows is wrong.**
Verified at the consuming surface as Sarah on `/m`:
`RecommendationsAggregatorService::getRecommendationsByModule(17, 'protection')` returns one
recommendation, income protection £72,000, with no debt-cover entry.

`tests/Unit/Services/Coordination/ComposedProtectionPlanTest.php` **+2 cases** — **5 passed,
12 assertions**. **M4 (bug restored): non-owner red, owner control green, three pre-existing
cases green.**

**The absence assertion is guarded, and this matters for review.** `recommendations()` wraps
everything in `catch (Throwable) { return []; }`, so an exception in the routed call would
empty the plan and **pass a naive "the phantom is absent" assertion**. The case also asserts
the list is not empty and still contains her genuine income-protection recommendation, so a
silent throw fails rather than reading as success.

**Files added to the change set:**

| File | Change |
|---|---|
| `app/Services/Coordination/PlanSources/ProtectionStrategySource.php` | `LifeCoverReach` injected; `:68` routed; critical illness left `user_id`-scoped |
| `tests/Unit/Services/Coordination/ComposedProtectionPlanTest.php` | +2 cases, asymmetric fixture, mutation-tested |
