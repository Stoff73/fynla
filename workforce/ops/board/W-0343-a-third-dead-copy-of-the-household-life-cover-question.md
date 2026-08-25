---
id: W-0343
title: IHTController carries a third, dead copy of the household life-cover question
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0027-cycle4-life-cover-reach.md
owner: build-lead
status: handoff
severity: low
surfaces: [web, m, ios]
created: 2026-08-22T23:35:00Z
claimed: 2026-08-25T13:20:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: [W-0186, W-0341]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

`app/Http/Controllers/Api/Estate/IHTController.php:211`:

```php
private function getExistingLifeCover(User $user, ?User $spouse): array
```

It sums each side's `in_trust` policies and returns `user` / `spouse` / `total` — the
same question `LifeCoverReach::householdCoverInTrust()` now owns (W-0186).

**Nothing calls it.** The name appears exactly once in the file, at the declaration.
No user impact today: the figure it computes never reaches a response.

It matters because it is a **live Rule 20 trap**. The next person needing a household
cover figure in that controller finds a private method that looks purpose-built, uses
it, and the application is back to two mechanisms — one of which does not pass through
the live/reciprocal spouse gate added in W-0278 and would disclose a deleted partner's
in-trust cover.

## Acceptance

1. [x] Deleted, or routed to `LifeCoverReach::householdCoverInTrust()` if a caller is wanted.
   — **DELETED**, with a pointer comment left where it sat naming the owner.
2. [x] Not left dead. A private method nothing calls is either an omission or a leftover;
   determine which before deleting, since the omission case means a figure the estate
   response was supposed to carry never shipped. — **LEFTOVER, established by evidence
   rather than assumed.** The figure is computed, published AND consumed elsewhere.

## Working notes

(append-only)

- 2026-08-22 — Found by `fix-cycle4-lifecover` while enumerating consumers for W-0341.
  Reported rather than fixed: `IHTController.php` is outside that agent's exclusive
  scope and the estate module had two other agents live at the time.

- 2026-08-25 (Brett, working alone per CSJ's 2026-08-24 standing instruction):
  **DELETED — and it was a leftover, not an omission. That was checked, not assumed.**

  **Acceptance 2 is the whole item, and here is the evidence for it.** A dead private
  method is only safely deletable if the figure it computed is not one the response was
  supposed to carry. Three steps, each measured:

  1. `LifeCoverReach::householdCoverInTrust()` **owns the question** (W-0186) —
     `LifeCoverReach.php:218`.
  2. `EstateAgent.php:140` **calls it**, and `:367-375` publishes a `life_cover` block
     carrying `user_cover_in_trust`, `spouse_cover_in_trust`, `total_cover_in_trust`
     and `policy_count`.
  3. `EstatePlanService.php:636` and `:871` **read** `total_cover_in_trust` and
     `user_cover_in_trust`.

  Computed, published **and** consumed. So nothing went missing with the deletion.
  Step 3 was not optional: W-0205 and the `charitable_rate_test_amount` case both
  turned on a value that was correctly computed and read by nobody, and "it is handled
  elsewhere" is exactly the claim that hides one.

  **The dead copy was also worse than the live one, in both of the ways the item
  predicted.** It ran `LifeInsurancePolicy::where('user_id', …)` on each side, so it
  would have missed a joint-life policy the spouse is also assured under — W-0186's
  original defect — and it bypassed the live/reciprocal spouse gate from W-0278, so it
  would have disclosed a deleted partner's in-trust cover. Anyone who found it and
  used it would have reintroduced two fixed defects at once. That is the Rule 20 trap
  the item was raised to close.

  **A detail that mattered on the way out.** `use App\Models\LifeInsurancePolicy;`
  at `IHTController.php:12` existed **solely** for this method — verified, it had no
  other reference in the file. It is removed in the same change. Per `tests/CLAUDE.md`
  §2, an import and its last reference must go together or the formatter silently
  strips one and leaves a same-namespace name that does not resolve; both were removed
  and the file re-checked after Pint.

  **A pointer comment is left where the method sat**, naming `LifeCoverReach` and
  saying not to re-derive the figure here. Deleting alone removes today's trap; the
  comment is what stops it growing back, which is the item's actual concern. Same
  reasoning as W-0146.

  **Note on the name.** There are two `getExistingLifeCover()` methods in the
  repository and only one is dead. `EstateAssetAggregatorService::getExistingLifeCover()`
  is **live**, was routed to `LifeCoverReach` under W-0341, and is covered by
  `LifeCoverReachSpouseLinkStatesTest` and `JointLifePolicyReachesBothLivesTest`.
  Untouched. A grep for the name alone does not distinguish them — the same collision
  shape as the two unrelated `net_income` keys found under W-0205.

  Also worth recording: the item cites `IHTController.php:211`; the method was at
  **:262** by the time this ran. Line references drift.

  **Verification.** Estate + Protection suites 569 tests / 1,865 assertions; the `IHT`
  filter 140 / 490; Architecture 177 / 4,296 — that last one is what would catch an
  unresolvable import. `php -l` clean, Pint clean. `POST /api/estate/calculate-iht`
  returns a proper JSON 403 from the `requireFullEstate` tier gate rather than a 500,
  which shows the controller class still loads and resolves with the import gone; the
  endpoint's real exercise is in the suites above.

  **No behaviour changed and no user's figure moves** — the method never reached a
  response.
