---
id: W-0350
title: 53 spouse_id consumers, five idioms, and only three use the rule the model calls THE authorization rule
mission: persona-run-peak_earners-2026-08-20
branch: fix/w-0350-spouse-link-authorization-one-helper
owner: build-lead
status: in_progress
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T23:55:00Z
claimed: null
blocked_by: []
gate: csj
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0186, W-0272, W-0278, W-0344, W-0347]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
---

## Intent

The census W-0344 acceptance criterion 3 asked for, delivered by a `security-reviewer`
pass. **53 consumer sites across `app/`.** Four gate shapes are in use:

(a) raw `$user->spouse_id` / `->spouse` · (b) `liveSpouseId()` / `liveSpouse()` ·
(c) `hasReciprocalSpouseLink()` · (d) `hasAcceptedSpousePermission()`

**Only three sites in the whole application use (c)**: `CoordinatingAgent.php:1261`,
`SavingsStore.php:362`, and `LifeCoverReach` as of W-0344.

**Two facts that reframe the list:**

1. **`User` uses `SoftDeletes`**, so `$user->spouse`, `$user->spouse()->first()` and
   `User::find($user->spouse_id)` are *all* already filtered against a deleted partner.
   **Gate (b) buys almost nothing over (a) for security** — its real value is the
   lazy-loading safety and caching its docblock describes. The live hole everywhere is
   the one-sided link, which neither (a) nor (b) addresses.
2. **`preventLazyLoading` is OFF in production** (`AppServiceProvider.php:216` —
   `Model::preventLazyLoading(! app()->isProduction())`). Several raw `$user->spouse`
   reads **throw a 500 on dev/staging and succeed in production**, so csjones testing
   will not surface them. Verify against production behaviour, not staging.

### Ranked, by what an unauthorised reader actually gets

**Tier 1 — reads of another person's financial data, gate (a).**

**Start with `app/Http/Controllers/Api/NetWorthController.php:74-82`, quoted verbatim,
because it is the most legible evidence in the whole report — a comment that names the
missing control, sitting directly above a complete net-worth and liabilities
disclosure:**

```php
// Add spouse net worth data if spouse exists and data sharing is enabled
$spouseData = null;
if ($user->spouse_id) {
    $spouse = $user->spouse;
    if ($spouse) {
        // Check if data sharing is enabled (you can add permission checks here)
        $spouseNetWorth = $this->netWorthService->getCachedNetWorth($spouse);
```

**The gate was marked and never installed.** The comment on the first line asserts a
condition — *"and data sharing is enabled"* — that the code below it never checks. What
ships is `total_assets`, `total_liabilities`, `net_worth`, and the full breakdown across
pensions, property, investments, cash, business and chattels, plus mortgages, loans and
credit cards.

Then: `RetirementIncomeService:237,556` (entire retirement register,
gated on a **plain request boolean** `include_spouse`); `UserProfileService:447,831`;
`UserProfileController:355`; `EstateAgent:131-133` (`$dataSharingEnabled = $spouse !== null`);
`AdvicePromptBuilder:394,416,990` (into Fyn's system prompt, recitable every turn);
`RecommendationPersonaliser:84`; `WillDocumentService:62`; `LetterToSpouseController:106`;
`SpousePermissionController:81`.

**Tier 2 — cross-account WRITES.** `OnboardingService:473,542` (replaces the other
person's 21 expenditure columns); `UserProfileController:414-508` (same, and returns
their full `UserResource`); `WillDocumentService:364` (**creates a will document inside
their account** carrying the caller's executors and guardians); `FamilyMembersController:317`
(their name, date of birth and **national insurance number**); `:364-376` (tears the link
down again); `UserProfileController:228`.

**Tier 3 — gate (b), live but not reciprocal.** `UserProfileController:300` (their entire
`UserResource`; note the detailed-expenditure tier check reads the **caller's** tier);
`UserProfileService:866-897` (their children's names, dates of birth and national
insurance numbers — **minors**); `UserContextBuilder:447`; `SavingsActionDefinitionService:2989`;
`DependantsReach:99`.

**On `DependantsReach` specifically:** its docblock argues the permission gate governs
*financial* data and children are not that. **That is an argument about permission, a
different axis from whether the link is genuine.** Rule 3 of the same docblock already
concedes `spouse_id` is untrustworthy on the deletion axis; it never considers the
unilateral-write axis. Reciprocity would not make children financial data and would not
hide a real parent's children. Recommend lifting to (c).

**Tier 7 — dead, do not raise as defects.** `SpouseNRBTrackerService:80`,
`TaxActionDefinitionService:170`, `EstateAgent::buildScenarios():1564`,
`E2EController:462` (double-guarded). Confirmed zero callers.

## Acceptance

1. **One helper.** Promote `LifeCoverReach::coveringSpouse()` onto the model as
   `User::reciprocalLiveSpouse(): ?User`, and route every reader through it. Five idioms
   for one question is why this census needed four agents instead of one grep, and
   `MilestoneDetectionService:712-714` already hand-rolls reciprocity rather than calling
   the canonical method (Rule 20).
2. Tier 2 writes require (c) — **before** the Tier 1 reads, because a write into someone
   else's account is worse than a read of it.
3. Tier 1 and Tier 3 lifted to (c), starting with `NetWorthController:78`.
4. `blocked_by: W-0347` — until linking stops writing both rows, (c) and (d) are
   decorative and this work cannot be verified as effective.

## Working notes

(append-only)

- 2026-08-23 — **`blocked` on a CSJ decision, deliberately unclaimed.** team-lead verified
  the four load-bearing lines against the code directly and is escalating tonight.
  **Do not attempt the fix.** `linkExistingSpouse` needs an accept/decline flow — invite,
  token, expiry, notification — touching onboarding, Fyn's `capture_spouse_details` tool
  and the email pipeline. That is specified work, not a patch, and **half-fixing it would
  leave a system that looks gated and is not.**


## Note — 2026-08-23

**The `W-0347` block is lifted: linking no longer writes both rows.** A reciprocal link
can now only arise from an explicit acceptance, so `hasReciprocalSpouseLink()` and
`hasAcceptedSpousePermission()` are load-bearing rather than decorative, and this census
can finally be verified as effective.

The work itself is **unchanged and still open**: 53 consumers, five idioms, one helper
(`User::reciprocalLiveSpouse()`), Tier 2 writes before Tier 1 reads. Nothing in this
census was fixed by W-0347 — the write path underneath the gates was.


## Note — 2026-08-29, build-lead: acceptance 1 and 2 delivered

**Unblocked.** The `csj-decision` block was on W-0347, and the note above already records
that W-0347 landed. Cleared rather than left sitting, because the item read as blocked on
CSJ when it was not.

**Acceptance 1 — one helper. DONE.** `User::reciprocalLiveSpouse(): ?self`, beside
`hasReciprocalSpouseLink()` which it enforces. Promoted from
`LifeCoverReach::coveringSpouse()` — the only reader in the application that got this
right first time — and `MilestoneDetectionService:712-714`, which got it right and wrote
it out again, now calls it instead of hand-rolling reciprocity. Both copies are gone
rather than aligned (Rule 20).

**Acceptance 2 — Tier 2 cross-account WRITES. DONE for every site that still exists:**

| Site | Was | What a one-sided link bought |
|---|---|---|
| `UserProfileController::updateSpouseExpenditure` | `liveSpouseId()` — gate (b) | overwrite 21 expenditure columns in the named account |
| `HouseholdExpenditureWriter::write` | `liveSpouse()` — gate (b) | half the caller's household figure written into the named account's row |
| `OnboardingService` ×2 | raw `$user->spouse` — gate (a) | the same 21 columns, during onboarding |
| `WillDocumentService::generateMirrorWill` | `User::find($user->spouse_id)` — gate (a) | **a will document created inside the named account**, carrying the caller's executors and guardians |

`HouseholdExpenditureWriter` was **not in the census** and is the same defect; found by
following `UserProfileController:228`, which the census cited without naming the writer
underneath it. A one-sided link now falls to the unshared branch — the same treatment an
unlinked user gets, with the whole share staying on the account that submitted it.

**`FamilyMembersController:317` and `:364-376` are SUPERSEDED, not fixed.** The census is
dated 2026-08-22 and W-0051 has since moved both to `FamilyMember::liveLinkedUser()`,
which resolves the account **that row** links to rather than whoever sits in
`users.spouse_id`. That is a different and narrower axis. Whether writing into a
`linked_user_id` account should ALSO require reciprocity is a real question and a separate
one; it is not the defect this census described, and it is recorded rather than guessed at.

**Acceptance 3 — Tier 1 READS. DONE for the financial surfaces.** Lifted to reciprocity:

| Site | Was | What a one-sided link disclosed |
|---|---|---|
| `NetWorthController::getOverview` | raw `$user->spouse_id` | total assets, total liabilities, net worth, the full breakdown across pensions, property, investments, cash, business and chattels, plus mortgages, loans and credit cards |
| `RetirementIncomeService` ×2 | `User::find($userId)?->spouse`, on a plain request boolean | the whole retirement register |
| `UserProfileController::getSpouseFinancialCommitments` | raw `$user->spouse` | the other account's financial commitments |
| `UserProfileService::incomeSources` | raw `$user->spouse` | their income sources |
| `AdvicePromptBuilder` ×3 | raw `$user->spouse` | their expenditure and family detail, into Fyn's system prompt, recitable every turn |
| `RecommendationPersonaliser` ×2 | raw `$user->spouse` | drives their figures into recommendations |
| `LetterToSpouseController::showSpouse` | `liveSpouseId()` | the named account |
| `EstateAgent` | raw `$user->spouse` | the pooled estate |

**Reciprocity, NOT consent — and that is a measurement, not a preference.** On the
development database: **12 reciprocal users, 1 one-sided (13 → 14), and 8 of the 12
reciprocal accounts have NO accepted permission row.** Gating these reads on
`hasAcceptedSpousePermission()` would take the spouse panel away from two-thirds of real
couples, so it is not a change to make as a side effect of an authorization fix.

**Raised, not fixed — the two mechanisms behind `$dataSharingEnabled`.** `EstateAgent`
derives it from the link's existence; `IHTController` derives it from
`hasAcceptedSpousePermission()`. One question, two answers, so **Fyn can quote a
different estate figure from the one on the screen**. Aligning them moves the pooled
figure for those 8 couples, which is a visible tax change and a CSJ decision.

**Acceptance 3 — Tier 3, gate (b). DONE.** `User` soft-deletes, so the live test was
already most of what `liveSpouseId()` bought at these sites; what it never answered is
whether the named account named this one back.

| Site | What a one-sided link reached |
|---|---|
| `UserProfileController::getUserById` | the named account's entire `UserResource` |
| `UserProfileService` | **the spouse's children — names, dates of birth and National Insurance numbers, of minors** |
| `UserContextBuilder` | their investment context |
| `SavingsActionDefinitionService` ×2 | their savings actions |
| `DependantsReach` | their dependants |

**On `DependantsReach`, whose docblock argues against exactly this:** it says the
permission gate governs *financial* data and children are not that. That is an argument
about CONSENT, and this is a different axis. Its own Rule 3 already concedes `spouse_id`
is untrustworthy on the deletion axis; it never considered the unilateral one.
Reciprocity does not make children financial data and does not hide a real parent's
children. Lifted, as the census recommended.

**Still open, deliberately:** `WillDocumentService:62` (`has_spouse` and the household id
list — a boolean and a scope rather than a disclosure, so it needs its own look) and
`SpousePermissionController`, which gates the CONSENT FLOW ITSELF — requiring an accepted
link to establish an accepted link would break the flow, so it must be assessed rather
than lifted blind.

### Verification

- 2,376 passed: 1,542 across `tests/Feature/Api`, `tests/Feature/UserProfile`,
  `tests/Unit/Services/Protection`, `tests/Unit/Services/Onboarding`,
  `tests/Feature/Onboarding`; 834 across `tests/Unit/Services/Estate`,
  `tests/Unit/Services/Mobile`, `tests/Feature/Estate`, `tests/Architecture`.
- `tests/Feature/Api/OneSidedSpouseLinkCannotWriteTest.php` — 5 tests.
  **Mutation-verified**: every refusal fails against the pre-fix code.
- **A decoy caught before it shipped.** The expenditure refusal test first passed on a
  403 from `guardDetailedExpenditure`, a TIER gate, not the link gate — it would have
  passed with the fix reverted. Both users are premium now, so the 403 is the one under
  test.
- Tier 1: `tests/Feature/Api/OneSidedSpouseLinkCannotReadTest.php`, 4 tests,
  **mutation-verified** — every disclosure refusal fails against the pre-fix code, checked
  one controller at a time so no test passes on another's gate. 2,444 further tests pass
  across Feature Api, Feature UserProfile, Unit Retirement, Unit Agents, Unit
  Coordination, Unit AI, Feature AI, Unit UserProfile, Feature Estate and Unit Estate.
- **Three existing tests changed, and they were fixtures rather than behaviour.** The
  mirror-will tests set a one-sided link because that was all the old code needed. A
  mirror will is written into the other account, so the fixture is now a reciprocal
  couple — which is what those tests always meant.
