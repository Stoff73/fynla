---
id: W-0544
title: Onboarding discards every save error with 17 bare catch blocks — a tier limit is rendered as "Failed to save property. Please try again." while the same limit outside onboarding shows a proper upgrade modal
mission: new-user-run-2026-09-07
branch: fix/board-w0550-w0543-w0544-w0542-w0545-w0546-w0547
owner: build-lead
reviewers: [build-lead, growth-lead]
status: done
severity: high
surfaces: [web, m]
revised: 2026-09-09 — scoped correctly after testing on production; see Working notes
created: 2026-09-09
source: new-user run, csjones 2026-09-07
prior_art_checked: 2026-09-09
prior_art_found: [W-0011, W-0018]
prior_art_outcome: extends — W-0011 was one gate behaving wrongly; this is the gate's response never being read at all
constitution_refs: [07-quality-bar, 06-commercials]
---

## Intent

Free-tier account, adding a second property. `POST /api/properties` returns
**403** with a fully-formed, actionable payload:

```json
{"error":"tier_limit_reached","entity_key":"property","current_count":1,
 "hard_limit":1,"required_tier":"premium",
 "message":"Property limit reached for your current plan.",
 "action":"subscription_options",
 "destination":{"screen":"subscription","params":[],"fallback":"net_worth"}}
```

The UI rendered:

> "Failed to save property. Please try again."

Four separate problems:

1. **The advice is wrong** — retrying can never succeed against a hard cap.
2. **The cause is hidden** — the user is never told they hit a plan limit.
3. **Revenue leak** — the backend returned `action: subscription_options` and
   `destination.screen: subscription`. `router/index.js` already maps
   `subscription -> /settings/subscription`. The upgrade path exists and is
   never shown, at the exact moment the user has demonstrated intent to exceed
   the free tier.
4. **Work is lost** after a three-step modal, unexplained.

`grep -rn "tier_limit_reached" resources/js/ resources/mobile/` -> **zero
matches**. Emitted by `app/Http/Traits/TierLimitResponse.php`, consumed
correctly on the chat path. See W-0549.

Free caps for reference (`TierConfigurationSeeder.php:52-60`): property 1,
investment 2, pension_account 2, savings_account 2, goal 2, life_event 1.

## Acceptance

- [ ] A tier-limit response renders its own `message`, not a generic failure.
- [ ] The upgrade destination the backend returns is offered as a call to action.
- [x] "+ Add" is disabled or annotated once `current_count === hard_limit`, so the
      user is not invited to fill a form that cannot save.
- [ ] Same behaviour on `/m` from the same helper (Rule 20).
- [ ] Also correct for investment, pension, savings, goal and life_event.
- [ ] The premium benefits list mentions properties — currently it promises
      unlimited investments, savings and pensions and never mentions the limit
      most households hit first.


## Working notes

**2026-09-09 — corrected on production. The original framing was too broad.**

Tested the same limit on production from the **Net Worth** investments page
(`/net-worth/investments`, cap 2, adding a third). It behaves **correctly**:

> "You've reached your Free limit — Your Free plan includes up to 2 investment
> accounts. Upgrade your plan to add more."  [Maybe later] [Upgrade]

with Upgrade routing to `/settings/subscription?openPricing=1`. There is a shared
`resources/js/components/Shared/LimitReachedModal.vue`, already consumed by
`InvestmentList.vue`, **`PropertyList.vue`**, `PensionList.vue`,
`CashOverview.vue`, `GoalsDashboard.vue` and `EventsTab.vue`.

So the Net Worth surfaces are fine, **including property**. The generic message I
originally hit came from the **onboarding** Assets step, which does not use the
shared modal.

### The actual defect

`resources/js/components/Onboarding/steps/AssetsStep.vue:880`

```js
} catch {
  error.value = 'Failed to save property. Please try again.';
}
```

A **bare catch with no error binding** — it cannot read the response even in
principle. Compare `PropertyList.vue:342`:

```js
} catch (error) {
  this.errorMessage = error.response?.data?.message || 'Failed to save property. Please try again.';
}
```

`AssetsStep.vue` has **13 bare `} catch {`** against 3 with a binding, each
substituting a hardcoded string: "Failed to save pension / property / savings
account. Please try again.", "Failed to delete pension / property / investment
account / savings account".

Across the onboarding steps: AssetsStep 13, ProtectionPoliciesStep 2,
ExpenditureStep 1, GoalSetupStep 1 — **17 bare catches**.

This lands during onboarding, the user's first experience, and every free-tier
cap is reachable there (property 1, pension 2, savings 2, investment 2). The user
is told to retry something that cannot succeed, and the upgrade path the backend
returns is discarded — while the same limit, hit two screens later, is handled
properly.

### Revised acceptance

- [ ] Onboarding steps bind the error (`catch (error)`) and read
      `error.response?.data?.message` rather than hardcoding a string.
- [ ] Onboarding uses the shared `LimitReachedModal` for tier limits, as the Net
      Worth surfaces already do — one home, not a second mechanism (Rule 20).
- [ ] The 17 bare catches are audited; a bare catch on a user-initiated save is
      the defect behind the defect.
- [ ] `/m` onboarding checked for the same pattern.

## Outcome — done, 2026-09-10

Confirmed live in the onboarding assets step only (the Net Worth pages already use
`LimitReachedModal` from the capability payload): four bare `catch {}` blocks and eight
`err.message ||` catches that printed axios's status string.

- `utils/apiErrors.js` — the one reader of a failed save on the form surfaces:
  `tierLimitFrom(error)` (entity label, cap, required tier, message, destination off a
  `tier_limit_reached` 403) and `apiErrorMessage(error, fallback)` (the server's
  `message`, never "Request failed with status code 403"). Test:
  `utils/__tests__/apiErrors.spec.js`.
- `AssetsStep.vue` — property, pension, investment and savings saves that hit a cap open
  the shared `LimitReachedModal` labelled with the user's current tier (`TIER_LABELS`,
  now exported from `tierLimitMixin`; Free when the subscription payload is not loaded
  mid-onboarding, since only Free carries caps); other failures show the server message.
- The nine other onboarding steps read the server message through `apiErrorMessage`.
- Browser-verified locally as a Free account: third savings account → 403 → modal
  "You've reached your Free limit. Your Free plan includes up to 2 savings accounts." →
  Upgrade → `/settings/subscription`. No "Please try again".
- `/m` onboarding is the Fyn conversation, which already consumes `tier_limit_reached`
  (no form counterpart).
- Not done: disabling "+ Add" at the cap inside onboarding (the Net Worth pages do this
  from `subscriptionData.count_caps`, which is not loaded during onboarding); the premium
  benefits copy about properties. Both stay open as follow-ups on this item's list.
- Adjacent, not fixed: `AssetsStep.vue` lines 220/290/366 call `window.scrollTo` inside a
  template handler, where `window` is undefined — a console TypeError on every "+ Add".

## Follow-up — done, 2026-09-10 (CSJ: tell the user before the form, not after)

The onboarding assets step now refuses a capped "+ Add" up front, as the Net Worth pages
do: `auth/fetchSubscriptionData` (new, the one loader for `/payment/subscription-status`;
`AppLayout` now goes through it too) is requested on mount, and `revealPropertyForm` /
`revealInvestmentForm` / `revealSavingsForm` / `togglePensionTypeSelector` open the shared
`LimitReachedModal` instead of the form when `atTierCap` says the count is at the cap.
The cap arithmetic lives once in `tierLimitMixin.js` (`countCapFor`, `atTierCap`), used by
the mixin and the step. Preview personas are exempt, as on those pages. Test:
`mixins/__tests__/tierLimit.spec.js`. Browser-verified locally: a Free account with two
savings accounts → "+ Add Account" → modal, no form, no request; a Premium account →
"+ Add Pension" → the pension chooser.
