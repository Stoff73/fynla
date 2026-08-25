---
id: F-0027
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/05-perimeter.md, core/constitution/08-process.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-22T00:00:00Z
status: active
---

# F-0027 — Cycle 4: one joint-life policy, two lives, four link states

**Agent:** build-lead (`fix-cycle4-lifecover`) · **Branch:** `dev` (shared working tree)
**Board items:** W-0341, W-0278, W-0342, W-0343, W-0344, W-0345, W-0346, W-0347, W-0348,
W-0349, W-0350, W-0381, W-0382, W-0383 · **ID blocks:** W-0341 – W-0350 (spent),
W-0381 – W-0390 (issued when the first ran out)
**F number:** taken after checking `fixes/` — F-0026 was the highest and was claimed by
`fix-cycle4-doublecount` while this batch was in flight, so **F-0027**. Doctrine
(`FORMATS.md` §"Branch-document numbers") says the coordinator issues it; team-lead was
told the number taken rather than the agent choosing silently.

**Predecessors, read before touching anything here:**
`W-0186` — built `LifeCoverReach`, the reader this batch routes to. `W-0272` /
`DependantsReach` — the same reach problem over `family_members`, and the source of the
`liveSpouseId()` precedent. `F-0024` §10 — the census entry that measured this defect.

---

## 1. The two questions, named

**This is the whole batch in one section.** Every figure below comes from the same
`life_insurance_policies` row, and the row gives different right answers to different
questions. Conflating them produces a missing £500,000 in one direction and a doubled
one in the other, and both have shipped.

| Question | Reader | Scope | The joint policy is… |
|---|---|---|---|
| *"Is this life covered?"* | `LifeCoverReach::policiesCovering()` | reaches across the live reciprocal link | **in both** answers |
| *"What does this household hold?"* | `LifeCoverReach::householdCoverInTrust()` | each account's OWN rows, unioned | counted **once** |
| *"Whose asset is it / who may edit it?"* | `user_id`, unchanged | owner only | the **owner's** |

**A joint-life policy covers two lives and pays out once.** Those two facts are what
make the table necessary: the first is why the per-life reader must reach, the second is
why the household reader must not.

**Which question each consumer is asking**, measured, not assumed:

| Consumer | Question | Reader | State |
|---|---|---|---|
| `ProtectionController:94` | is this life covered | `policiesCovering` | already correct (W-0186) |
| `ProtectionAgent:108`, `:408` | is this life covered | `policiesCovering` | already correct (W-0186) |
| `LifeInsurancePolicyResource:16` | is this life covered | `policiesCovering` + `isOwnedBy` | already correct (W-0186) |
| `EstateAssetAggregatorService::getExistingLifeCover():277` | is this life covered | was raw `user_id` | **fixed here** |
| `EstateAgent:119` | what does the household hold | `householdCoverInTrust` | already correct (W-0186) |
| `EstateAgent:109` (`$allLifePolicies`) | is this life covered | raw `user_id` | **W-0342, fixed here — scope granted mid-batch** |
| `IHTController::getExistingLifeCover():211` | what does the household hold | raw `user_id`, both sides | **dead code — W-0343** |

---

## 2. W-0341 — Sarah's cover figure, measured before and after

**Live persona, `peak_earners`, local dev database. Not read off the code.**

Fixture as it stands: policy 7 belongs to David (16), `joint_life = true`,
`in_trust = true`, sum assured £500,000. Sarah (17) holds no life policy of her own.
David also holds critical illness policy 2, £200,000. Both accounts are `married`,
reciprocally linked, both live.

| Figure | Before | After |
|---|---|---|
| `getExistingLifeCover(David, 16)` | £700,000 | **£700,000** (unchanged) |
| `getExistingLifeCover(Sarah, 17)` | **£0** | **£500,000** |
| `householdCoverInTrust(...)['total']`, either account | £500,000 | £500,000 (unchanged) |
| `householdCoverInTrust(...)['count']`, either account | 1 | 1 (unchanged) |

**The fix routes to the reader that already existed.** `LifeCoverReach` finds the second
life through `users.spouse_id` because `life_insurance_policies` carries no
`joint_owner_id` — verified against the live schema, whose columns are `in_trust`,
`joint_life`, `beneficiaries` and nothing else shareable. Prior-art outcome: **route**.

**Critical illness deliberately stays `user_id`-scoped.** `critical_illness_policies` has
**no `joint_life` column, no `joint_owner_id` and no ownership fields** — checked with
`SHOW COLUMNS`, not inferred from the model. There is nothing to reach with, so a second
reader would have had nothing to read. Sarah's £500,000 is life cover only; David's
£700,000 keeps his £200,000 of critical illness on his own life, where it was written.

---

## 3. W-0341's second half — the acceptance criterion that had it backwards

**W-0186 acceptance criterion 8 asserts `getExistingLifeCover(Sarah) === 0` and calls it
"no double count".** The item dispatched to this batch calls the same £0 the defect.
Both cannot stand, so it was measured rather than argued.

**`gatherUserAssets()` never reads `LifeInsurancePolicy` at all.** The class appears in
`EstateAssetAggregatorService` in exactly one place — inside `getExistingLifeCover()`
itself. Measured on the persona, the asset types the estate aggregation returns are
`investment, property, cash, chattel, dc_pension` for David and
`investment, property, cash, chattel, db_pension` for Sarah. **No life policy enters
either estate from either account, by either route.**

So the double count criterion 8 was guarding against **was not reachable by that
method**, and the £0 it locked in was not protecting an estate — it was telling Sarah her
estate plan had no life cover behind it. The test has been re-anchored onto the
aggregation that could actually double count, and asserts the absence directly rather
than through a proxy.

The real double-count risk lives in the **household** figure, which is why §5 exists.

---

## 4. W-0278 — the four spouse-link states

The disclosure bug: `policiesCovering()` and `householdCoverInTrust()` read
`$user->spouse_id`, the raw column, which **deliberately survives the partner deleting
their account** (retained for regulatory purposes — CSJ decision D1/D2, 2026-08-19).

Read adversarially as instructed, the link has four states, and the raw column collapses
all four into "linked".

| State | Before | After | Mechanism |
|---|---|---|---|
| **1. Live and reciprocal** | reaches | **reaches** | — |
| **2. Partner deleted** | **reaches — the bug** | blocked | `User::liveSpouse()` |
| **3. Linked from one side only** | **reaches — also a bug, unraised** | blocked | `User::hasReciprocalSpouseLink()` |
| **4. Permission refused / never accepted** | reaches | **reaches — decided, see below** | none, deliberately |

**State 3 was not in the brief and is the more dangerous of the two.** `spouse_id` is a
claim its own account holder writes, unilaterally: any user could name another account as
their spouse and read back that person's joint-life sum assured. It is not hypothetical
plumbing — it is the same disclosure class as state 2 with a lower barrier, because it
needs no deletion, just an unreciprocated write. `User::hasReciprocalSpouseLink()` is
declared in the model as *"THE single authorization rule"* for an attached id granting
visibility of a record, so it is called rather than re-derived (Rule 20).

**State 4 reaches, and that is a decision, not an oversight — raised as W-0345.**
Three reasons, in order of weight:

1. **`hasAcceptedSpousePermission()` cannot express a refusal in the first place.** Its
   automatic branch returns `true` for any married, reciprocally linked, live pair
   *whatever the permission row says*. Gating on it would not have blocked state 4; it
   would have blocked something else.
2. **It would re-open W-0186 for unmarried couples.** That method additionally requires
   `marital_status === 'married'` on **both** accounts. A linked cohabiting couple would
   have had the joint-life policy hidden from the person it insures — the exact harm
   W-0186 fixed, reintroduced through a side door.
3. **`joint_life` is itself the disclosure marker.** It is a flag the owner set, on their
   own record, saying this contract covers two lives. A single-life policy never reaches,
   in any state, and that is the boundary that matters.

**W-0346, found while building the fixture: there is no `revoked` state to test.**
`spouse_permissions.status` is `enum('pending','accepted','rejected')`. A permission that
was granted **cannot presently be withdrawn at all** — there is no value to write. The
test asserts `rejected` and `pending` and says why in the file.

**`otherLifeAssured()` passes through the same gate**, so a dead or one-sided link names
nobody rather than naming a person this application may no longer disclose. That also
closes a lazy-loading hazard: it reached for `$viewer->spouse` under
`preventLazyLoading()`. **Not a live crash today** — the only call site passes
`$request->user()`, loaded singly — but it would throw the first time a caller handed it
a user loaded as part of a collection.

---

## 4b. W-0342 — where "Sarah has no life cover" actually entered her estate plan

**This section exists because the item as dispatched named a method, and the method was
dead.** `getExistingLifeCover()` has zero production callers. Fixing it was right — it is
public API returning a wrong number — but it could not have changed anything Sarah sees.

**`EstateAgent::analyze()` reads life policies TWICE, and only one of the two was routed
in W-0186.** That is the whole defect, and it is why "her estate plan says she has no
cover" was true of three consumers and false of the fourth:

| Consumer | Source | Sarah, before |
|---|---|---|
| `life_cover.total_cover_in_trust` / `policy_count` (`:119`) | `householdCoverInTrust()` — **already routed** | **correct: £500,000, count 1** |
| `policy_assessment` (`:229-233`) | `$allLifePolicies` — raw `user_id` | **0 entries** — `assessExistingPolicies` skipped entirely on an empty collection |
| `itemised_policies` → `userContext` → the LLM (`:254`, `:299`) | same | **`[]`** — Fyn told she holds no policies |
| `missing_for_quality_advice` (`:306`) | same | **a `life_cover` gap fired** |

**That last row is the acceptance criterion in the plan's own words.** Measured, verbatim
from her analysis:

> **`life_cover`** — *"Estate exceeds the Nil Rate Band but no life cover is recorded — a
> written-in-trust policy is a common Inheritance Tax mitigation route."*

Her gross estate is **£861,780**, well over the Nil Rate Band, so the branch at
`findMissingForQualityAdvice():424` fired on `$lifePolicies->isEmpty()`. **After: no
gaps, 5 assessment entries, the Vitality £500,000 policy itemised.** David is identical
on every field before and after.

**`total_cover_not_in_trust` and `policies_not_in_trust_count` stay owner-scoped**, and
are now filtered from the reached set through `LifeCoverReach::isOwnedBy()` rather than a
second query — one answer to "is this mine". They drive "place this policy in trust",
which only the owner can do.

**Routing that collection to the reach opened a new wrong answer — found, and FIXED
before shipping, not shipped and reported (W-0382).** `LifeCoverCalculator::assessExistingPolicies():453-461`
ends its `not_in_trust` warning with **"Contact your provider to place this policy in
trust."** The non-owner never saw the assessment before; she does now, so for a
joint-life policy that is **not** in trust she is told to phone an insurer about a
contract she does not hold. Proved in a rolled-back transaction. **The persona cannot
show it** — policy 7 is in trust, so the branch never fires; joint-life-and-not-in-trust
is ordinary in real data and absent from `peak_earners`. Fixture variant again. The fix
was made in `LifeCoverCalculator`, ownership-aware via `isOwnedBy()` — **not** as a
warning filter in `EstateAgent`, which would have put a second mechanism in charge of what
the warning says, the disease this branch exists to treat. The non-owner now reads that
the cover on her life is not in trust and that **the policyholder** arranges it; the owner's
message is unchanged. It also corrected a factual error the reach exposed: the proceeds
fall into the policyholder's estate, not hers, and the old string said "your taxable
estate" to both readers.

**The principle, stated because the next person editing that branch needs it and not just
the string: what is TRUE about her cover and what she can ACT ON are two different things.**
The fact is hers to know; the action is the policyholder's.

**Note for anyone verifying estate figures by hand: `EstateAgent::analyze()`'s cache is
never cleared by `invalidateUserCache()`.** `analyze()` remembers under
`estate_analysis_{id}`; `invalidateUserCache()` forgets `v1_estateagent_{id}_analysis`
and `v1_estateagent_analysis_{id}`. **None of the three match**
(`BaseAgent.php:45-50` vs `:86-103`). My own first before/after measurement was
contaminated by it. Recorded in W-0342's notes; it needs its own item and I have no id
left in the block.

---

## 4c. The security review, and what it did to the claim this batch can make

**Commissioned at team-lead's direction because state 3 is an authorisation finding, not
a reach bug.** It was expected to confirm a narrow question. It found the mechanism
underneath it, and the honest reading of this batch changed as a result.

**`SpouseLinkingService::linkExistingSpouse():226-254` writes BOTH rows** — the caller's
and the named account's — sets the target's `marital_status` and income, and
`createSpousePermissions():476-486` writes `status => 'accepted'` on **both** permission
rows. No request, no acceptance, nothing from the target. Reachable at
`POST /api/user/family-members` with `auth:sanctum` and no proof of control of the email
address. **W-0347, critical, gated to CSJ.**

| Gate | Survives a raw one-sided `spouse_id`? | Survives that endpoint? |
|---|---|---|
| raw `spouse_id` | no | no |
| `liveSpouseId()` | no | no |
| **`hasReciprocalSpouseLink()` — this batch's gate** | **yes** | **no** |
| **`hasAcceptedSpousePermission()`** | **yes** | **no** |

**So what this batch bought is a raised bar, not a closure:** from *"any account plus a
column write"* to *"any account plus the target's email plus the target being unlinked"*.
That is written into the `coveringSpouse()` docblock so the next reader does not trust
the gate further than it goes, and it is why W-0350 is `blocked_by: W-0347` — lifting 53
readers to reciprocity cannot be verified as effective until reciprocity means something.

**Three findings in this batch's own files, all fixed:**

1. **`otherLifeAssured()`'s non-owner branch was ungated** — `User::find($policy->user_id)`,
   safe only because the one path that hands a viewer a policy they do not own is itself
   gated. **An implicit invariant is not an enforced one**; the first caller to pass a
   policy fetched by id would have got a free disclosure of its owner's name. Both
   branches now gate on `coveringSpouse()`.
2. **The docblock understated what passes the gate — and the payload itself is now
   narrower.** It is not "sum assured, provider, in-trust status":
   `LifeInsurancePolicyResource:28-48` shipped the non-owner the **entire contract**,
   including `policy_number` and free-text `beneficiaries`. W-0186 made the policy reach
   her and **nobody asked what she should be able to read once it did.**
   **`policy_number` and `beneficiaries` are now withheld from the non-owner**
   (team-lead's direction): a policy number is effectively a credential for phoning the
   insurer, and `beneficiaries` commonly names the couple's children. Neither is needed
   to know you are covered. **Nulled, not omitted**, so the key shape is constant on every
   surface — `/m` renders `policy_number || '—'` and hides the beneficiaries block on
   falsy; both native fields are `String?`, so a null decodes cleanly rather than breaking
   the policy list. Premium, dates and rates still ship and are **deliberately not guessed
   at** — W-0383, for CSJ.
3. **A comment that this batch made false.** The class docblock said "the estate asset
   aggregation is untouched" — still true of `gatherUserAssets()`, no longer true of
   `getExistingLifeCover()`. Corrected rather than left to be trusted later.

**Two facts from the review that change how the whole census reads:** `User` uses
`SoftDeletes`, so gate (b) buys almost nothing over (a) *for security* — the live hole
everywhere is the one-sided link, which neither addresses. And **`preventLazyLoading` is
OFF in production** (`AppServiceProvider.php:216`), so several raw `$user->spouse` reads
**throw on dev/staging and succeed in production** — csjones testing will not surface
them. It also means this batch's own lazy-loading note was a dev/staging concern only,
which is how it is now worded.

---

## 5. The test design, and the two traps it was built against

`tests/Feature/Protection/LifeCoverReachSpouseLinkStatesTest.php` — **16 cases** (12 for
the reach and the four link states, 4 for the two disclosure fixes in §4c).
`tests/Feature/Protection/JointLifePolicyReachesBothLivesTest.php` — case 8 re-anchored.

### Collision — the fixture is asymmetric on purpose

The persona has **one** policy, so £500,000 is *both* the correct household total *and*
the total you get by counting David's side alone. **No assertion built on that fixture
can tell the two hypotheses apart** (`tests/CLAUDE.md` §4, Collision). The fixture
therefore gives Sarah **£120,000 of her own, single-life**, which separates every
hypothesis onto its own number:

| Hypothesis | Sarah's cover | Household in trust |
|---|---|---|
| Correct | £620,000 | £620,000 |
| `user_id`-only (the bug) | £120,000 | £120,000 or £500,000 |
| Reach applied to both sides | £620,000 | **£1,120,000** |

It does second duty as the control: David's answer must stay £500,000, and £620,000
there would mean the reader pulled everything his spouse holds rather than only what
covers him.

### Fixture — what the persona does not contain, built here

`peak_earners` has **no deleted spouse, no one-sided link, no permission row of any
status, and no critical illness policy on the second life**. Every one of those branches
is unreachable from persona-derived data, so each is constructed in the test file.

### Mutation-tested in both directions — five mutations, each caught by the right case

Every bug was restored and the suite re-run. A fix that no test can detect is not
covered, and a test that reddens under an unrelated mutation is not precise.

| # | Mutation | Went red | Stayed green |
|---|---|---|---|
| M1 | `getExistingLifeCover` back to `where('user_id')` | the 3 estate-figure cases | the 17 others |
| M2 | `policiesCovering` back to raw `spouse_id` | deleted-partner reach, one-sided reach | all household cases |
| M3 | `householdCoverInTrust` back to raw `spouse_id` | deleted-partner household, one-sided household | all reach cases |
| M4 | household built from `policiesCovering` on both sides (**the double count**) | "counts a joint-life policy once" | the reach cases |
| M5 | household counts the user's own side only | "counts a joint-life policy once" | the reach cases |
| M6 | resource ships `policy_number` / `beneficiaries` to the non-owner | "withholds the contract details…" | "still gives the owner every field" |
| M7 | one `not_in_trust` message for owner and non-owner alike | "does not tell the other life assured to phone an insurer…" | "still tells the policyholder…" |

**M5 is the one that justifies the asymmetric fixture.** On the persona's single-policy
shape, dropping the spouse's side entirely leaves David reading £500,000 — the correct
answer — and the case passes while the household reader is broken. With Sarah's £120,000
present it reads £500,000 against an expected £620,000 and fails. **That is the Collision
variant caught inside its own suite, exactly as `tests/CLAUDE.md` §4 describes.**

---

## 6. Surfaces (Rule 19)

**Backend-only. No `resources/js/`, `resources/mobile/` or `ios-native/` file is touched,
and none needs to be.**

Web, `/m` and native all read life-cover data through the same server-side readers:
`GET /api/protection` → `ProtectionController` → `LifeCoverReach`, and the estate figures
through `EstateAgent` / `EstateAssetAggregatorService`. The fix lands once, in the
readers, and reaches all three surfaces by architecture — which is the shape Rule 20 asks
for.

**No `/m` rebuild is required.** `/m` serves `public/m-build/`, a compiled bundle that
only changes when a `resources/mobile/` source file changes. Nothing here does. Build
artefacts remain team-lead's.

### Browser verification — done, `localhost:8000`, both accounts through the MFA gate

Codes fetched from `email_verification_codes` by hand; no rebuild performed or required.
**Identity established from `GET /api/auth/user` with the live token, not from
`fynla-state`** — see W-0385 for why that matters.

| Surface | Sarah (17) | David (16) |
|---|---|---|
| `/m` protection — policy list | Vitality £500,000, *"Joint life with David Jones — recorded on their account"* | Vitality £500,000 *"Joint life with Sarah Jones"* + Critical Illness £200,000; **£700,000 total, unchanged** |
| `/m` policy detail | `policy_number` **null**, `beneficiaries` **null** | `VIT-LT-456789`, *"Sarah Jones: 34%, William Jones: 33%, Charlotte Jones: 33%"* |
| web `/protection` | **Total Life Insurance £500,000**, Debt Protection **none**, shortfall £0 | Total Life Insurance £500,000, Critical Illness £200,000 |
| `/api/plans/estate` (web estate plan) | Vitality present, **no phantom gap** | — |
| `/api/v1/mobile/modules/estate` | `total_cover_in_trust` **500000**, `policy_count` **1**, no phantom gap | same: **500000**, **1** |
| `/m` estate screen | £861,780 assets — **carries no life-cover section at all** | £1,160,000 assets, likewise none |

**W-0383 is confirmed from both sides in one session:** the owner sees the policy number
and the beneficiaries — which name the couple's two children, exactly the payload the rule
exists for — and the other life assured sees neither, while still seeing she is covered
for £500,000.

**The `/m` estate screen renders no life-cover section**, so the W-0342 fix has no visible
`/m` estate surface. It reaches `/m` through the module-summary endpoint, Fyn's context and
the web estate plan — verified above — not through that screen.

### What the browser CANNOT settle here, stated rather than glossed

**The household figure reads £500,000 from both accounts — and £500,000 is also what each
side would show if it only ever saw its own half.** One joint-life policy is one number
seen from two directions, so **the screen cannot distinguish "counted once" from "each
account sees only its own"**. What the browser did prove is that neither account is shown
£1,000,000. The discrimination between those two hypotheses lives in the tests, at the
asymmetric £620,000 fixture, and in mutations M4 and M5 — not on any screen.

### The pass found a defect the code reading did not — W-0384

`/m/app/protection` as Sarah shows **"Total lump-sum cover £0 · Across 1 policy"** directly
above the £500,000 policy it is counting, and derives **three HIGH coverage gaps** from that
£0 — while web tells the same user at the same moment that her debt protection shortfall is
**£0**. Two mechanisms behind one card: the count from the reach-aware list
(`Protection.vue:182`), the total from `coverage_gaps.totals.cover`
(`:184`) ← `ProtectionGapPresentationService:32-40`, which still passes
`$user->lifeInsurancePolicies`. Measured: **£0 as shipped, £500,000 with the reach.**
**Invisible from David's account, because he owns the policy** — which is why no previous
pass caught it.

---

## 7. Files

| File | Change |
|---|---|
| `app/Services/Protection/LifeCoverReach.php` | `coveringSpouse()` gate (live + reciprocal); `policiesCovering`, `householdCoverInTrust`, `otherLifeAssured` routed through it; the two-questions invariant documented on the class |
| `app/Services/Estate/EstateAssetAggregatorService.php` | `getExistingLifeCover()` routed to `LifeCoverReach::policiesCovering()`; `LifeCoverReach` constructor-injected; critical illness left `user_id`-scoped, with the schema reason stated |
| `app/Agents/EstateAgent.php` | `:109` routed to `policiesCovering()`; not-in-trust figures filtered from that set through `isOwnedBy()` and still owner-scoped; dead `$lifePoliciesInTrust` removed |
| `tests/Feature/Protection/LifeCoverReachSpouseLinkStatesTest.php` | **new** — 12 cases |
| `app/Services/Estate/LifeCoverCalculator.php` | `not_in_trust` warning ownership-aware via `isOwnedBy()`; `LifeCoverReach` constructor-injected (W-0382) |
| `app/Http/Resources/Protection/LifeInsurancePolicyResource.php` | `policy_number` and `beneficiaries` withheld from the non-owner, nulled not omitted (W-0383) |
| `tests/Feature/Protection/JointLifePolicyReachesBothLivesTest.php` | case 8 re-anchored onto `gatherUserAssets()` |

**Not covered by a test, deliberately:** the `EstateAgent` change is verified by
measurement against the live persona (§4b), not by a new unit test. Its inputs are a
cached 700-line `analyze()` whose cache key `invalidateUserCache()` does not clear —
asserting on it would be asserting on a cache. The reader underneath it is what the 12
new cases cover.

**Test runs** (`DB_DATABASE=laravel_testing_m`, exclusive to this agent — the database
did not exist and was created for this batch):

- `tests/Feature/Protection` + `tests/Unit/Agents` + `tests/Unit/Services/Estate` +
  `ClientCompatibilityContractTest` + `ModuleSummaryTest` — **488 passed,
  1,759 assertions** (the final run, after every fix in this document)
- every suite naming `EstateAssetAggregatorService` or `EstateAgent`, plus
  `tests/Unit/Services/Estate` and the two mobile/contract suites
  (`EstatePlanRefactorTest`, `MobileDashboardAggregatorTest`,
  `ClientCompatibilityContractTest`, `ModuleSummaryTest`) — **362 passed,
  1,413 assertions**
- earlier, before the `EstateAgent` change landed: `IHTProjectionOwnershipTest`,
  `EstateAssetAggregatorDbPensionIncomeTest`, `MortgageReadConsumerParityTest`,
  `SavingsReadConsumerParityTest` — **90 passed, 340 assertions**
- Pint clean on all five files.

**Not run:** the full suite. Rule 17 / `feedback_no_full_suite_per_small_change`.

---

## 8. Raised, not fixed here

- **W-0342 — `EstateAgent:109`. NOW FIXED**, scope granted mid-batch — see §4b. Left
  here for the record of why it was raised separately: `$allLifePolicies = LifeInsurancePolicy::where('user_id', $userId)->get()`
  is where "Sarah has no life cover" actually enters her estate plan: it feeds
  `policyAssessment`, the itemised policy list that reaches the LLM, and
  `findMissingForQualityAdvice`. **`getExistingLifeCover()` — the method this batch was
  dispatched to fix — has zero production callers**, so fixing it alone cannot satisfy
  "her estate plan stops recommending on the premise that she has none". Scope requested
  from team-lead; `EstateAgent.php` is outside the exclusive scope issued.
- **W-0343 — `IHTController::getExistingLifeCover():211`.** A `private` method nothing
  calls: a third copy of the household in-trust question, now owned by
  `householdCoverInTrust()`. No user impact; a live Rule 20 trap for the next reader.
- **W-0345** — should an explicit permission refusal suppress joint-life reach? Product
  call, argued in §4, pinned by a test so a reversal lands somewhere visible.
- **W-0346** — `spouse_permissions.status` has no `revoked` member; a granted permission
  cannot be withdrawn.
