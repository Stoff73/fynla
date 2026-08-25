---
type: bug-investigation
date: 2026-04-23
branch: prodHotFix
env: production (fynla.org)
reporter: CSJ
status: root-caused, fix planned, NOT YET IMPLEMENTED
---

# Expired-Trial Checkout Loop — Findings

## TL;DR

A user whose trial has expired (and is within the 30-day grace period) cannot complete checkout on production. They see the plan-selection modal on `/dashboard`, click a plan, the URL correctly changes to `/checkout?plan=X&cycle=Y`, and the Revolut widget does initialise in the DOM — but `AppLayout` re-renders **two** non-dismissable overlays on top of the checkout page. Every way a user can dismiss the top overlay strands them on a second overlay (`DataRetentionOverlay`), whose only CTA navigates back to `/checkout` (without query params) and leaves the same overlay visible. The checkout widget is therefore never reachable.

## Reproduction

**Setup**

- Production test user created via tinker:
  - email `bugrepro_expired_2026_04_23@fynla.org`
  - password `Password1!`
  - `users.id = 601`
  - `subscriptions.id = 61`, `status = expired`, `trial_ends_at = now-2d`, `data_retention_starts_at = now-1d`
  - Confirmed: `onTrial = no`, `hasActivePlan = no`, `isInGracePeriod = yes` (grace ends 2026-05-22)
- Production `app.payment_enabled = true`

**Steps**

1. `GET https://fynla.org/login` → fill credentials → verify code (retrieved via tinker from `email_verification_codes`: `392144`).
2. Land on `/dashboard`. Two overlays render:
   - `PlanSelectionModal` (z-70, dismissable=false, "Your Trial Has Ended")
   - `DataRetentionOverlay` (z-50 modal, z-40 backdrop, "Your Subscription Has Expired")
3. Click `Choose Plan` on **Fynla Student** card.
4. URL → `/checkout?plan=student&cycle=yearly`. Behind the overlays, `CheckoutPage` renders Order Summary + Revolut widget + iframe (confirmed by DOM snapshot). `POST /api/payment/create-order` is fired and returns 200.
5. BUT a **new** `PlanSelectionModal` (`ref=e543` in snapshot) re-appears on top of the checkout page, plus `DataRetentionOverlay` (`ref=e472`) again.
6. Click `Choose Plan` on the new modal → `PlanSelectionModal` dismisses → `DataRetentionOverlay` is now the top layer.
7. Click `Subscribe Now` on `DataRetentionOverlay` → URL changes to `/checkout` (query params dropped by the `<router-link to="/checkout">`) → `CheckoutPage` now shows the empty state *"No plan selected. Please choose a plan first."* → `DataRetentionOverlay` remains visible.
8. No remaining path lets the user reach the Revolut widget. From the user's POV this is a "loop".

**Screenshots (saved in this folder)**

- `01-after-choose-plan.png` — /checkout with Revolut widget loaded behind `PlanSelectionModal`.
- `02-after-second-choose-plan.png` — `PlanSelectionModal` gone, `DataRetentionOverlay` visible in front of Order Summary + Revolut widget.
- `03-after-subscribe-now.png` — URL is `/checkout` (no params), CheckoutPage shows the empty state, `DataRetentionOverlay` still on top.

**Network evidence (captured on the /checkout page)**

```
POST /api/auth/login        → 200
POST /api/auth/verify-code  → 200
GET  /api/auth/user         → 200
GET  /api/payment/trial-status × 4    (1 AppLayout + 1 DataRetentionOverlay on Dashboard,
                                        same two again on CheckoutPage)
GET  /api/payment/plans × 3           (1 PlanSelectionModal per mount — 3 mounts in this flow)
POST /api/payment/create-order → 200  (widget DID initialise behind the overlays)
```

**Browser console errors during the flow** — all 403s from `CheckSubscription` backend middleware blocking module analysis endpoints (`/api/estate/trusts`, `/api/estate/calculate-iht`, `/api/retirement/analyze`, `/api/investment/analyze`, `/api/protection/analyze`). These are **correct** behaviour for an expired user and are unrelated to the checkout loop; noted for completeness.

## Root cause

Three defects compound on `/checkout`:

### Defect A — `AppLayout` re-renders `PlanSelectionModal` on `/checkout`

File: `resources/js/layouts/AppLayout.vue` lines 88–93

```vue
<!-- Trial Expired — non-dismissable plan selection -->
<PlanSelectionModal
  v-if="showTrialExpiredModal"
  :dismissable="false"
  @select="handlePlanSelect"
/>
```

`showTrialExpiredModal` is set in `checkTrialStatus()` (line 310) whenever `/payment/trial-status` returns `status !== 'trialing' && status !== 'active'`. Because `CheckoutPage.vue` line 2 wraps itself in `<AppLayout>`, every navigation to `/checkout` unmounts the previous AppLayout instance and mounts a new one. `mounted()` (line 213) re-calls `checkTrialStatus()`, re-fetches trial status, and re-shows the modal on top of the checkout page — defeating the whole point of navigating to checkout in the first place.

### Defect B — `DataRetentionOverlay` renders on every authenticated route, including `/checkout`

File: `resources/js/layouts/AppLayout.vue` line 36

```vue
<DataRetentionOverlay v-if="isAuthenticated && !isPreviewMode" />
```

File: `resources/js/components/Payment/DataRetentionOverlay.vue` lines 151–156

```js
const visible = computed(() => {
  if (!subscriptionData.value) return false;
  return subscriptionData.value.status === 'expired'
    && subscriptionData.value.is_in_grace_period === true
    && subscriptionData.value.payment_enabled === true;
});
```

Same problem as A: the overlay is unconditionally mounted inside `AppLayout`, so it also appears on `/checkout`. Its own `fetchSubscriptionStatus` fires separately and renders the backdrop + modal over the checkout widget.

### Defect C — `DataRetentionOverlay`'s "Subscribe Now" link drops query params

File: `resources/js/components/Payment/DataRetentionOverlay.vue` line 48

```vue
<router-link to="/checkout" class="...">Subscribe Now</router-link>
```

Navigating to bare `/checkout` from `/checkout?plan=student&cycle=yearly` clears the query, which in turn causes `CheckoutPage.vue` (lines 276–282) to see `this.plan === undefined` and render the "No plan selected. Please choose a plan first." empty state. This is the final step of the loop — even if Defect A were fixed, Defect C alone would strand the user.

### Contributing / structural issue (noted, not required to fix hotfix)

`AppLayout` is mounted *inside* each view (`Dashboard.vue`, `CheckoutPage.vue`, `UserProfile.vue`, etc.) rather than above `<router-view>`. Every route change triggers a full AppLayout unmount/mount cycle, which re-runs `checkTrialStatus`, re-shows modals, and generally re-fetches status that could be cached. The 22 Apr session handover already flagged this ("AppLayout architectural refactor still outstanding"). It amplifies Defects A and B but is not the trigger.

## Why the bug exists in code

The intent of both overlays is correct for a user viewing *normal* app pages with an expired subscription: prompt them to pay before consuming data-earning actions. But on `/checkout` the user has *already* responded to the prompt; re-prompting there is not just redundant, it blocks the flow they were redirected into. The current `AppLayout` has no route-awareness for either overlay. It appears nobody manually QA'd a real expired-trial login against Revolut production after the overlays were introduced.

## What is NOT broken (important for scoping the fix)

- The Revolut checkout widget itself — it initialises, `/api/payment/create-order` returns 200, payment methods (Revolut Pay, Card, Google Pay) render in the iframe.
- `/api/payment/trial-status` — returns correct data.
- `CheckSubscription` backend middleware — correctly excludes payment endpoints so the widget can call `create-order`.
- Router guards — `/checkout` is not feature-gated and the guard does not redirect expired users.
- `PlanSelectionModal` itself when rendered on Dashboard — clicking `Choose Plan` correctly navigates to `/checkout?plan=X&cycle=Y`.

## Test-user teardown

The test user (`bugrepro_expired_2026_04_23@fynla.org`, user id 601, subscription id 61) is still on production. It is clearly flagged by name. It should be deleted once the fix is verified. Suggested command for later:

```bash
php artisan tinker --execute="
\$u = \App\Models\User::where('email','bugrepro_expired_2026_04_23@fynla.org')->first();
if (\$u) { \$u->subscription()->delete(); \$u->delete(); echo 'deleted'; } else { echo 'already gone'; }
"
```

## Next doc

See `fix-plan.md` for the proposed fix, touched files, and verification plan.
