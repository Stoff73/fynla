---
id: W-0350
title: 53 spouse_id consumers, five idioms, and only three use the rule the model calls THE authorization rule
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
status: blocked
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T23:55:00Z
claimed: null
blocked_by: [csj-decision]
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
