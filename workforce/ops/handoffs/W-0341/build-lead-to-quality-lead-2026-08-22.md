# W-0341 — build-lead (`fix-cycle4-lifecover`) → quality-lead

Companion note: `handoffs/W-0278/build-lead-to-quality-lead-2026-08-22.md`.
Branch document: `branches/fixes/F-0027-cycle4-life-cover-reach.md` — §1 is the whole
batch in one table and is the fastest way in.

## Done

- `EstateAssetAggregatorService::getExistingLifeCover()` routed to
  `LifeCoverReach::policiesCovering()`. Measured on the live persona:
  **Sarah (17) £0 → £500,000; David (16) £700,000 unchanged;** household in-trust
  £500,000 / count 1 from either account, unchanged.
- Critical illness left `user_id`-scoped, deliberately: `critical_illness_policies` has
  no `joint_life`, no `joint_owner_id` and no ownership columns. Verified with
  `SHOW COLUMNS`, not inferred from the model.
- `tests/Feature/Protection/LifeCoverReachSpouseLinkStatesTest.php` — new, 12 cases.
- `tests/Feature/Protection/JointLifePolicyReachesBothLivesTest.php` case 8 re-anchored
  (see "Assumptions", it is the one judgement call in this batch).
- **Mutation-tested in both directions: 5 mutations, each reddening the right case and
  only that case.** Table in `F-0027` §5.
- Green, final run after every fix: **488 passed / 1,759 assertions** across
  `tests/Feature/Protection`, `tests/Unit/Agents`, `tests/Unit/Services/Estate`,
  `ClientCompatibilityContractTest` and `ModuleSummaryTest`. Pint clean on all six files.

## Done — added after team-lead granted `app/Agents/EstateAgent.php`

- **`EstateAgent.php:109` routed to `policiesCovering()`.** This is where "Sarah has no
  life cover" actually entered her estate plan; `getExistingLifeCover()` has **zero
  production callers** and could not have. Measured before → after on the live persona:
  the gap *"Estate exceeds the Nil Rate Band but no life cover is recorded"* fired on her
  £861,780 estate and now does not; `policy_assessment` 0 entries → 5; the itemised list
  feeding `userContext` and the LLM `[]` → the £500,000 policy. David identical on every
  field. Not-in-trust figures stay owner-scoped, filtered through `isOwnedBy()`.
- **A security review of the reciprocity gate** (team-lead's instruction), which returned
  a critical finding — see below.
- Three findings in this batch's own files, fixed: an **ungated** non-owner branch in
  `otherLifeAssured()`; a comment this batch made false; and the payload itself narrowed —
  **`policy_number` and `beneficiaries` are now withheld from the non-owner** (W-0383).
  Nulled, not omitted: `/m` renders `policy_number || '—'` and hides the beneficiaries
  block on falsy, both native fields are `String?`, so the key shape is safe on all three
  surfaces. Premium, dates and rates still ship and are deliberately not guessed at.
- **W-0382 — a hole this batch opened itself, found and fixed before shipping.** Routing
  `EstateAgent:109` to the reach made `LifeCoverCalculator`'s `not_in_trust` warning
  reachable with a policy the reader does not hold, and it told her the proceeds fall
  into *"your taxable estate"* and to *"contact your provider"* — both wrong for her, and
  the second an action that does not exist for her. **The persona cannot show this**
  (its only joint-life policy is in trust), so no browser pass would have caught it;
  demonstrated in a rolled-back transaction. Fixed at its one home, ownership-aware.
- **Seven mutations now, not five** — M6 (resource ships the withheld fields) and M7
  (one trust message for both readers) each redden their own case and leave the owner-side
  control green.

## Not done, and why

- **The reciprocity gate is a raised bar, not a closure, and the evidence pack must not
  say otherwise.** `SpouseLinkingService::linkExistingSpouse():226-254` writes **both**
  accounts' rows and forges `accepted` permissions on both sides from one party's
  request, reachable at `POST /api/user/family-members` with no proof of control of the
  target email. **Every gate in the application is defeated by that one endpoint**,
  including this one. **W-0347**, critical, unclaimed and gated to CSJ. What this batch
  bought: "any account plus a column write" → "any account plus the target's email plus
  the target being unlinked".
- **The 53-site census is delivered but unfixed** — W-0350, `blocked_by: W-0347`.
- **Browser verification is DONE** — both accounts, `localhost:8000`, through the MFA
  gate, codes from the database. Full table in `F-0027` §6. Sarah's web `/protection`
  reads **Total Life Insurance £500,000** and **Debt Protection: none**; David unchanged
  at **£700,000**.
- **What the browser could NOT settle, and you should not let an evidence pack imply it
  did:** the household figure reads £500,000 from both accounts, and £500,000 is *also*
  what each side would show if it only ever saw its own half. One joint policy is one
  number seen from two directions, so **no screen can distinguish "counted once" from
  "each account sees only its own"**. What it did prove is that neither account is shown
  £1,000,000. The discrimination is in the tests at the asymmetric £620,000 fixture
  (M4/M5).
- **Three of the four link states have no screen at all** — the persona has no deleted,
  one-sided or unreciprocated link, and W-0382's branch never fires because its only
  joint-life policy is in trust. Those four properties are test-only, by necessity.
- Full test suite not run (Rule 17 / `feedback_no_full_suite_per_small_change`).

## What you need that isn't obvious from the artefacts

- **Two of the four link-state cases assert a DECISION, not a mechanism** — that a
  refused or never-granted `SpousePermission` still reaches. If product reverses that
  (W-0345), those two are where it lands, and they should be changed rather than deleted.
- **The £500,000 you will want to assert against is a Collision trap.** On the persona
  fixture it is simultaneously the correct household total and the total you get from
  counting one side. Any new assertion you add against the household figure needs the
  asymmetric fixture (Sarah's £120,000) or it cannot fail. Mutation M5 in `F-0027` §5
  demonstrates this against a real mutation.
- **`EstateAgent::analyze()`'s cache is never cleared by `invalidateUserCache()`** —
  `analyze()` remembers under `estate_analysis_{id}`, the invalidator forgets
  `v1_estateagent_{id}_analysis` and `v1_estateagent_analysis_{id}`, and none of the
  three match (`BaseAgent.php:45-50` vs `:86-103`). **If you verify an estate figure and
  it looks unchanged, forget the raw key by hand before concluding anything.** It
  contaminated my own first measurement. Needs its own board item; my id block is spent.
- **`preventLazyLoading` is OFF in production** (`AppServiceProvider.php:216`). Raw
  `$user->spouse` reads elsewhere in the app **throw on dev/staging and succeed in
  production** — a csjones pass will not surface them.
- **`liveSpouse()` caches per model instance.** Any test that changes a link and then
  re-reads must re-fetch the user; the new test file has a `reloaded()` helper for
  exactly this, and reusing a stale handle will produce a green test over broken code.
- **W-0186 acceptance criterion 8 now contradicts this item deliberately.** It asserted
  `getExistingLifeCover(Sarah) === 0` as correct. If you re-run W-0186 against its
  original text it will look like a regression. It is not — `F-0027` §3 has the
  measurement.
- `DB_DATABASE=laravel_testing_m` did not exist and was created empty for this batch.

## Assumptions I made

(stated as assumptions, never as facts)

- **That `getExistingLifeCover()` is asking "is this life covered?" and not "what is in
  this estate?"** It sums life *and critical illness* cover for one person, and a
  critical illness policy pays out to the living policyholder — that mix reads as a
  protection-need figure, not an asset. It also sits in a service whose asset
  aggregation never touches life policies at all. If the intended question is the estate
  one, the routing is wrong and the £500,000 should not be there.
- **That re-anchoring W-0186 case 8 rather than deleting it is the right call.** The
  original assertion guarded a double count I could not reproduce by any route — the
  estate aggregation does not read `LifeInsurancePolicy` from either account. I kept the
  test's name and intent and pointed it at `gatherUserAssets()`, which is what could
  actually double count. A reviewer who disagrees should read `F-0027` §3 first.
- **That reciprocity is required for disclosure, not merely liveness.** W-0278 asked
  only for liveness. I additionally blocked one-sided links, on the basis that
  `hasReciprocalSpouseLink()` is declared in the model as the single authorization rule
  for precisely this. Measured on the dev database: **12 live reciprocal links, 0
  one-sided, 0 dead** — so no existing user loses cover from the stricter gate. If a
  future onboarding flow writes one side before the other, this gate will hide a real
  joint-life policy during that window.

## Found during verification, not fixed — read before you plan your own pass

- **W-0384 (high).** `/m/app/protection` as Sarah shows **"Total lump-sum cover £0 ·
  Across 1 policy"** directly above the £500,000 policy it is counting, and derives three
  HIGH coverage gaps from that £0 — while web tells the same user at the same moment her
  debt-protection shortfall is **£0**. `ProtectionGapPresentationService:32-40` still
  passes `$user->lifeInsurancePolicies`. Measured £0 vs £500,000. **Invisible from
  David's account**, which is why it survived every previous pass — the defect only exists
  on the account that does not hold the contract.
- **W-0385 (medium).** `fynla-state.auth.user` named Sarah while the token authenticated
  as David. **The `/m` playbook's own identity check would have recorded David's £700,000
  screen as Sarah's** — a false pass on the very figure under test. Establish identity from
  `GET /api/auth/user`, never from the store.
- **W-0381 (high).** Clear `estate_analysis_{id}` by hand before every estate reading or
  you will verify against a blob that predates the fix.

## Surfaces covered / not covered

- **Backend-only; no frontend file touched on any surface.** Web, `/m` and native all
  read this data through one endpoint: `resources/mobile/views/modules/Protection.vue:249`
  and `ios-native/.../ProtectionClient.swift:18` both call `GET /api/protection`, which
  is `ProtectionController:94` → `LifeCoverReach`. `/m`'s estate view calls
  `GET /api/estate` (`Estate.vue:121`). The fix lands once and reaches all three.
- **No `/m` rebuild performed or required** — `public/m-build/` only changes when a
  `resources/mobile/` source file changes, and none did. Build artefacts are team-lead's.
- **Verified in the browser:** web `/protection` and `/m` protection + policy detail on
  both accounts; `/api/plans/estate` and `/api/v1/mobile/modules/estate` for both.
- **Not verified anywhere:** native iOS — no simulator run in this batch. The reach is
  server-side and native calls the same `api/protection` endpoint
  (`ProtectionClient.swift:18`), so it should follow, but **I did not look and do not
  claim it.**
- **No `/m` rebuild performed or required** — backend-only, no `resources/mobile/` source
  file touched, so `public/m-build/` is unaffected. The bundle-grep step in the playbook
  does not apply to this batch and would prove nothing about it.
