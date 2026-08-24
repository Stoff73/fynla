---
id: W-0018
title: TierResolver docblock says "explicit users.tier wins" but resolve() never reads users.tier
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0001-batch-c-retirement-profile-gates.md
owner: build-lead
status: done
surfaces: [web, m, ios]
created: 2026-08-21T08:30:00Z
claimed: 2026-08-21T09:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21T09:10:00Z
prior_art_found: ["codex/plans/ios/2026-07-14-ios-04-storekit-entitlements.md:95-96 — the decision that users.tier is a cache, not the gate", "codex/plans/programme/2026-07-14-freemium-economic-contract-remediation.md:706 — same posture", "app/Services/Billing/PremiumEntitlementResolver.php — zero ->tier references (verified)"]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Found by: team-lead while provisioning premium for the `peak_earners` Pass A test
accounts. Independently re-verified by persona-tester before filing.

**Surface:** backend gating, all surfaces (web, `/m`, iOS) — not user-facing on its
own, but it governs what every surface unlocks.

Severity: **low** as a live bug (no user is currently mis-tiered by it), but it is a
**contract drift**: the documented rule and the implemented rule disagree, and a
future change made on the strength of the docblock would be wrong.

### Expected

`app/Services/Tiers/TierResolver.php:20-25`, the docblock on `resolve()`:

```
Canonical gating tier for $user. Spec §5.2: NO mechanical plan->tier
map. Explicit users.tier wins. Otherwise preview/no-sub/legacy-paid
all resolve to 'free' for gating arithmetic; ...
```

Read literally: setting `users.tier = 'premium'` should be sufficient to resolve
`premium`.

### Actual

`users.tier` is never consulted. `TierResolver.php:26-29`:

```php
public function resolve(User $user): string
{
    return $this->entitlements->resolve($user)->tier;
}
```

It delegates wholly to `PremiumEntitlementResolver::resolve()`
(`app/Services/Billing/PremiumEntitlementResolver.php:24-45`), which branches only on
`is_preview_user` and otherwise calls `resolveLiveProviders()` (`:69-77`) — live
**Revolut** candidates plus **Apple** entitlements. Grepping that file for `->tier`
returns **zero hits**; the column plays no part in the resolution at all.

**Proof (team-lead, during provisioning):** setting `users.tier = 'premium'` alone
left `TierResolver::resolve()` returning `free`. Only adding an active `Subscription`
row moved it to `premium`.

### The internal inconsistency

The same class reads the column ten lines later.
`TierResolver.php:36-40`, `isGrandfatheredLegacyPaid()`:

```php
if (in_array($user->tier, TierConfigurationStore::TIERS, true)) {
    return false;
}
```

So `users.tier` is authoritative enough to *disqualify* someone from
grandfathering, but is ignored when deciding their tier. One of those two is wrong.

That asymmetry is the reason to suspect the column was **meant** to win and lost it in
a refactor, rather than the column simply being dead — a genuinely dead column would
not still be gating grandfathering.

### Evidence

**No screenshot** — backend-only, nothing renders. Evidence is the code above plus
the provisioning experiment.

Report: `reports/R-05-premium-provisioned-sweep.md`.

### Repro

1. Pick a non-preview user with no active subscription.
2. Set `users.tier = 'premium'` and nothing else.
3. `app(TierResolver::class)->resolve($user)` returns `free`.
4. Add an active `Subscription` row (plan premium, status active, period covering now).
5. It now returns `premium` — with or without `users.tier` set.

## Needs — CSJ judgement before this can be fixed

Two defensible fixes, opposite in meaning:

- **(a) The column is meant to win.** Restore an explicit `users.tier` check at the
  top of `resolve()`. Consequence: a manual tier override becomes possible again,
  which is what the spec reference (§5.2) appears to describe — and what test-support
  provisioning would want.
- **(b) The column is dead for gating.** Correct the docblock, and then decide what to
  do about `isGrandfatheredLegacyPaid()` still reading it. Consequence: entitlement is
  provider-truth only, and any manual override must go through a Subscription row.

Do not guess. The wrong choice silently changes who gets premium.

## Acceptance

- [ ] CSJ decides (a) or (b).
- [ ] Code and docblock agree, whichever way it goes.
- [ ] `isGrandfatheredLegacyPaid()`'s use of `$user->tier` is made consistent with the
      decision rather than left as the odd one out.
- [ ] A test pins the chosen behaviour — currently nothing catches the drift.
- [ ] If (b): check every other reader of `users.tier` for the same stale assumption.

## Working notes

(append-only)

- 2026-08-21 persona-tester: raised at team-lead's request, from their provisioning
  experiment. Re-verified the code paths independently before filing (zero `->tier`
  hits in `PremiumEntitlementResolver`). Not fixed by me. Gated on CSJ.
- Provisioning context, recorded so it is never mistaken for a finding: premium for
  users 16 and 17 was set up **by team-lead**, replicating the app's own sanctioned
  test-support shape from `app/Http/Controllers/TestSupport/E2EController.php:163-176`
  — `users.plan`, `users.tier`, plus an active `Subscription` row. persona-tester did
  not and must not provision tiers.

- 2026-08-21 build-lead: INVESTIGATED, NOT CHANGED. Still gated on CSJ per the
  brief — but the evidence points hard one way, so the gate should be quick.

  **Recommendation: option (b), the column is dead for gating — and deliberately
  so. The docblock is stale, not the code.**

  Re-verified independently:
  - `app/Services/Tiers/TierResolver.php:26-29` delegates wholly to
    `PremiumEntitlementResolver::resolve()`.
  - `grep -c '\->tier' app/Services/Billing/PremiumEntitlementResolver.php` → **0**.
    It branches on `is_preview_user` then `resolveLiveProviders()` (`:69-102`):
    live Revolut candidates plus live Apple entitlements. `users.tier` plays no part.
  - `app/Services/Tiers/TierResolver.php:38` is the only place in `app/` that reads
    `$user->tier` for a decision (verified by grep across `app/`).

  **The decisive evidence is not in the code, it is in the plan that produced it.**
  `codex/plans/ios/2026-07-14-ios-04-storekit-entitlements.md:95-96`:

  > - [ ] Make `TierResolver` use this resolver for paid access and otherwise
  >   return Free. **A stale `users.tier='premium'` without a live provider grant
  >   must not grant Premium.**
  > - [ ] Provider event handlers may maintain `users.tier` as a query cache, but
  >   capability checks use the resolver.

  Corroborated by
  `codex/plans/programme/2026-07-14-freemium-economic-contract-remediation.md:706`:
  "This does not grant paid access because `TierResolver` still resolves
  `users.tier='free'`".

  So the current behaviour is the intended one: `users.tier` is a **cache of the
  last provider outcome**, and gating is provider-truth only. The docblock's
  "Explicit `users.tier` wins" predates the StoreKit entitlements work and was never
  updated. The spec §5.2 the docblock cites is not the iOS-04 plan and I could not
  locate a §5.2 that says the column wins — worth CSJ confirming, because that is
  the one thread I could not pull.

  **On the "internal inconsistency" the item raises — it is not one.**
  `isGrandfatheredLegacyPaid()` reads `$user->tier` to ask *"has this user been
  assigned a new tier yet?"* (`in_array($user->tier, TierConfigurationStore::TIERS)`
  → not grandfathered). That is the column used as a **migration-cohort marker**,
  which is exactly what a cache is for. It never grants entitlement — it decides
  whether a legacy paid subscriber's existing-data creates should be spared the new
  cap. Reading a cache to answer "has this been migrated" is consistent with (b),
  so the asymmetry the item flagged is not evidence that the column was meant to win.

  **If CSJ confirms (b), the work is small:**
  1. Rewrite the `resolve()` docblock to say entitlement is provider-truth only and
     `users.tier` is a maintained cache, citing the iOS-04 decision.
  2. Leave `isGrandfatheredLegacyPaid()`'s read in place, with a comment naming it a
     migration-cohort marker rather than an entitlement source.
  3. Add a test: `users.tier = 'premium'` alone resolves `free`; adding a live
     entitlement resolves `premium`; and `users.tier` set does NOT flip
     `isGrandfatheredLegacyPaid()` for a user with no legacy plan.
  4. Sweep the other readers — already done: there are none in `app/` beyond
     `TierResolver`. Writers are `AuthController.php:638`,
     `SubscriptionRenewalService.php:237`, `SubscriptionExpiryService.php:76`,
     `PendingRegistration.php:109` and `E2EController.php:153`, all of which are
     cache maintenance and consistent with (b).

  **If CSJ says (a) instead, do NOT just add a check at the top of `resolve()`** —
  that directly reverses "a stale `users.tier='premium'` must not grant Premium",
  and every one of those writers would then become an entitlement grant, including
  the test-support endpoint. That is a security-shaped change and needs its own item.

  > **SUPERSEDED — see the `2026-08-21 team-lead` note below ("Found on arrival").**
  > Two of the four acceptance items were **already satisfied in the working tree** when
  > that note was written: the `resolve()` docblock had been rewritten and
  > `isGrandfatheredLegacyPaid()` had gained its cohort-marker paragraph. The claim below
  > was honestly made and is left as the record of what was believed.

  No code changed. `gate` left set.

- 2026-08-21 build-lead: batch branch document (also the Rule 22 context handover)
  written to `workforce/branches/fixes/F-0001-batch-c-retirement-profile-gates.md`.
  It carries the dispatch verbatim plus both amendments, per-item file:line
  evidence, test output, decisions taken with reasoning, dead ends ruled out,
  environment state (no throwaway user was created — nothing to tear down), and
  the full W-0018 argument. Every Pest run re-verified under
  `DB_DATABASE=laravel_testing_c` after the shared-database deadlocks.

- 2026-08-21 team-lead: **CSJ DECISION — option (b). The column is dead for gating.**
  CSJ: *"b is good."*

  So the current code is correct and the docblock is stale. **Entitlement is
  provider-truth only** — a live Revolut subscription or a live Apple entitlement, via
  `PremiumEntitlementResolver`. `users.tier` is a **cache of the last provider outcome**,
  never a grant. This confirms, rather than reverses,
  `codex/plans/ios/2026-07-14-ios-04-storekit-entitlements.md:95-96` ("a stale
  `users.tier='premium'` without a live provider grant must not grant Premium").

  **The "Spec §5.2" thread is closed as unresolvable and no longer blocks anything.** No
  such spec exists in this repo; it was the only written authority pointing at option (a),
  and CSJ has ruled without it. Do not spend further time looking for it. When the
  docblock is rewritten, **delete the §5.2 citation** rather than reproducing a reference
  to a document nobody can find.

  Work authorised, exactly as scoped in build-lead's note above:
  1. Rewrite the `resolve()` docblock: entitlement is provider-truth only, `users.tier` is
     a maintained cache. Cite the iOS-04 decision, not §5.2.
  2. **Leave `isGrandfatheredLegacyPaid()`'s read of `$user->tier` in place**, with a
     comment naming it a migration-cohort marker rather than an entitlement source. It is
     not the inconsistency the item originally alleged.
  3. Add a test pinning the behaviour: `users.tier = 'premium'` alone resolves `free`; a
     live entitlement resolves `premium`; and `users.tier` being set does NOT flip
     `isGrandfatheredLegacyPaid()` for a user with no legacy plan.
  4. The other-readers sweep is already done — there are none in `app/` beyond
     `TierResolver`. The five writers (`AuthController:638`,
     `SubscriptionRenewalService:237`, `SubscriptionExpiryService:76`,
     `PendingRegistration:109`, `E2EController:153`) are cache maintenance and are
     consistent with (b); they stay as they are.

  Taken by team-lead directly rather than dispatched — it is a docblock, a comment and one
  test, and it collides with nothing in flight.

- 2026-08-21 team-lead: **DONE. Handed to quality-lead.** Option (b) implemented.

  **Found on arrival: two of the four acceptance items were already satisfied in the
  working tree**, contrary to this item's own note ("No code changed"). The board lagged
  the tree, as it does by design. `git diff app/Services/Tiers/TierResolver.php` shows the
  `resolve()` docblock had already been rewritten to provider-truth-only with the iOS-04
  citation, and `isGrandfatheredLegacyPaid()` had already gained its cohort-marker
  paragraph. Both are correct under (b) and were left as written.

  **What I actually did:**

  1. **Removed the phantom citation.** `TierResolver.php` — the grandfathering docblock
     still carried "spec §5.2/§22 A9". Since no §5.2 exists in this repo and it was the
     sole written authority for the abandoned reading, it is deleted rather than
     reproduced, with a note saying so and why. The same phantom reference survives in
     three comments in `tests/Unit/Services/Tiers/TierResolverTest.php` — **left
     deliberately**, because those comments describe the tests' own history rather than
     asserting a rule; the docblock now says so, so the next reader is not sent chasing it.

  2. **Added the missing pin** — `TierResolverTest.php`, `it treats a canonical users tier
     as a migration-cohort marker, not a grant (W-0018)`. The `resolve()` half of the
     contract was **already** pinned before today by "does not resolve a stale explicit
     users tier without a live provider grant" and "resolves a live Revolut Premium grant
     regardless of the users tier cache", so this item's claim that "nothing catches the
     drift" was only ever true of the grandfathering half. The new test takes two
     otherwise identical legacy paid subscribers — one with `tier = null`, one with
     `tier = 'premium'` — and pins that the marker stops grandfathering **and** still
     grants nothing (`resolve()` stays `free` on both sides of it).

  **Evidence:** `DB_DATABASE=laravel_testing_f ./vendor/bin/pest tests/Feature/Tiers
  tests/Unit/Services/Tiers tests/Architecture/StoreBoundary` → **163 passed, 568
  assertions**. `TierResolverTest` alone → 8 passed, 11 assertions. Pint clean on both
  files; `use` imports verified intact after the formatter ran.

  **Not done, deliberately:** the five writers of `users.tier` (`AuthController:638`,
  `SubscriptionRenewalService:237`, `SubscriptionExpiryService:76`,
  `PendingRegistration:109`, `E2EController:153`) are untouched — they are cache
  maintenance and consistent with (b). No behaviour changed anywhere; this was a
  documentation-and-pin item by design, which is why it needs no browser verification.
