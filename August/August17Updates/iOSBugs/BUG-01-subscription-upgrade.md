---
id: BUG-01
raised: 2026-08-17
surface: web (reproduced) · native + /m implicated, not yet reproduced
severity: blocker
status: open — root cause NOT fully isolated, see §5
fixed_in: null
testflight_build: null
---

# BUG-01 — Subscription / upgrade does nothing

CSJ: *"the subscription service and upgrade process is not working, it does not
show correctly, link through to the payment engine, or actually do anything
useful."*

No fix applied. This is the diagnosis only, as asked.

## 1. Verdict up front

**The backend is healthy. The failure is in the web front end.** The upgrade
journey dies at the very first step: the only control on the page does nothing,
so the payment engine is never reached — there is nothing wrong with the payment
engine itself that this bug proves either way.

I have **not** isolated the final root cause of the dead click. §5 states exactly
what is unconfirmed and the one experiment that settles it. I am not guessing at
a fix on top of that gap.

## 2. Reproduction (confirmed, live)

Local, `john@example.com`, logged in through the browser with a real MFA code.

1. Go to `/settings/subscription`.
2. The page renders a single card: heading **"Free"**, body *"Your Free plan
   includes Fynla's core financial planning features."*, and one button,
   **"Compare plans"**.
3. Click "Compare plans" → **nothing happens.** No modal, no navigation, no
   network request, no console error. URL unchanged.

Reproduced twice: once by coordinate click, once by clicking the resolved
element reference, to rule out a mis-aimed click.

Evidence: `screenshots/2026-08/` (subscription page before and after the click —
visually identical).

## 3. Confirmed findings

### 3.1 The click never reaches its handler — BLOCKER

`SubscriptionManagement.vue:42`

```vue
<button v-if="showUpgradeEntry" @click="showPlanModal = true" class="btn-primary w-full text-center block">
  Compare plans
</button>
```

The button *renders*, so `showUpgradeEntry` is true and
`presentation.state === 'free'`. The handler only sets a boolean. Yet the modal
never appears, and `PlanSelectionModal`'s root element carries **no `v-if` of its
own** (`PlanSelectionModal.vue:2` — `<div class="fixed inset-0 z-[70]">`), so if
`showPlanModal` were ever true the modal would be visible. It is therefore never
becoming true.

Ruled out by direct inspection:

| Candidate | Ruled out because |
|---|---|
| Component missing | `resources/js/components/Payment/PlanSelectionModal.vue` exists |
| Not imported/registered | imported `:414`, registered `:422` |
| Mis-scoped template block | modal at `:315` is a sibling *after* the `</template>` at `:312`, correctly outside the `v-else` |
| Implicit form submit | the button has no `type="button"`, but there is **no `<form>`** in the component, the view, the tab bar, or `AppLayout` |
| Preview-mode blocking | `john@example.com` has `is_preview_user = false`; no `v-preview-disabled` anywhere in the component |
| Prop-type crash in the modal | all four props are optional with defaults (`PlanSelectionModal.vue:12-33`) |
| Wrong component on screen | `SubscriptionSettings.vue` renders `<SubscriptionManagement />` directly (29-line file, read in full) |
| Backend failure | see 3.4 — backend returns 200 with real data |

### 3.2 `/api/payment/plans` is never called — BLOCKER (consequence of 3.1)

Across the whole session, the only payment request the page makes is
`subscription-status`. `plans` is fetched by the modal (`PlanSelectionModal.vue`
imports `@/services/api` and loads into `plans: []`), so it never fires. This is
independent corroboration that **the modal never mounts** — not that it mounts
and renders blank.

### 3.3 `/api/payment/subscription-status` is called 5× — DEFECT

Five identical `GET`s, all 200, for one page view. At minimum wasteful. It may
also be the mechanism behind 3.1 — see §5.

### 3.4 The backend is fine — NOT the bug

`PaymentController::plans()` invoked directly as John:

```
status 200
{"plans":[{"slug":"premium","name":"Premium","monthly_price":699,
  "yearly_price":5999, ... 10 features ...}]}
```

`subscriptionStatus()` returns 200 with `tier: free`, `status: free`, a full
capability matrix and limits. Both correct. £6.99/mo and £59.99/yr also match the
StoreKit prices, so pricing is consistent across surfaces.

### 3.5 The page promises three things it never shows — DEFECT

`SubscriptionSettings.vue:6-8` subtitle: *"Manage your plan, billing, invoices,
and discount codes"*. In the `free` state the body renders **none** of billing,
invoices, or discount codes — only the plan card. Discount codes exist inside the
unreachable modal; `billingHistory` is declared (`:440`) and an
`/api/payment/billing-history` endpoint exists, but nothing renders it in this
state. Even with 3.1 fixed, the page does not deliver what its own heading
promises.

### 3.6 Revolut is pointed at PRODUCTION locally — RISK, needs your call

`config('services.revolut.sandbox')` resolves to **`false`**. `config/services.php:64`
defaults it to `true`, so this is an explicit `REVOLUT_SANDBOX` value in `.env`
overriding that. Per the deployment section, dev/staging is supposed to use the
**Revolut sandbox**. A live upgrade attempt from a dev machine would hit the
production payment API. I have **not** changed `.env`. Flagging for your decision.

## 4. Native / StoreKit — related, separate, unverified

Not reproduced (I still cannot sign in on the simulator), but found while tracing:

- **`SystemStoreKitClient.swift:63-67`** throws rather than degrading:
  ```swift
  let products = try await Product.products(for: productIDs)
  guard products.allSatisfy({ productIDs.contains($0.id) }) else { throw ... }
  guard Set(products.map(\.id)) == productIDs else { throw ... }
  ```
  If StoreKit returns **zero** products the set comparison fails and it throws
  `productUnavailable`. There is no partial-availability path: one missing product
  takes the whole paywall down.
- **Product IDs match** between `StoreKit/Fynla.storekit` and
  `StoreKitModels.swift:4-5` (`org.fynla.premium.monthly` / `.annual`), so the
  6 red StoreKit tests are **not** an ID mismatch — they are zero products loaded.
- **The 6 red tests may not be as benign as the docs say.** They are recorded as
  "red locally, green in CI", and the scheme *does* wire the config
  (`Fynla-Staging.xcscheme:22,35`). But the same failure mode — zero products →
  throw → no paywall — is exactly the symptom you are reporting. Worth not
  dismissing.
- **UNVERIFIED and important:** whether `org.fynla.premium.monthly` / `.annual`
  actually exist as in-app purchases on the `Fynla Dev` App Store Connect record
  (ID 6793193337). If they do not, the paywall is broken on TestFlight for this
  reason regardless of anything in 3.1. `altool` cannot list IAPs; this needs an
  App Store Connect API call.

## 5. What I could NOT determine, and the test that settles it

**Unconfirmed:** *why* the click does not set the flag.

**Leading hypothesis — the component re-mounts on click.** `showPlanModal` is
`ref(false)` in `setup()` (`:434`). If the component re-mounts, the flag resets to
`false` in the same tick it was set, and the modal never paints. The five
`subscription-status` fetches for one page view are consistent with repeated
mounting. This would explain every observation: flag set, no modal, no error, no
`plans` fetch.

**The experiment that decides it:** load
`/settings/subscription?openPricing=1`. `openPricingFromQuery()` (`:447-451`) sets
the *same* flag by a different route:

```js
if (!route.query.openPricing) return;
if (showUpgradeEntry.value) showPlanModal.value = true;
```

- Modal **opens** → the flag and modal are fine; the fault is in the click
  binding.
- Modal **does not open** → the flag is being reset or the render path is broken;
  the re-mount hypothesis moves to front.

I could not run it: the browser tab stopped responding (`navigate`, `screenshot`
and `javascript_tool` all timed out — `javascript_tool` failed twice before that,
which usually means a pending permission prompt in the extension side panel).
Needs a fresh tab, and possibly for you to clear that prompt.

## 6. Fix order once §5 is resolved

Not started. Proposed sequence, smallest first:

1. Fix the dead click (root cause per §5), with a failing frontend test first.
2. Fix the 5× `subscription-status` fetch — likely the same root cause.
3. Decide on 3.5: either deliver billing/invoices/discount codes in the free
   state, or correct the subtitle. A heading that promises absent features is its
   own bug.
4. Your call on 3.6 (`REVOLUT_SANDBOX`).
5. Native: verify the ASC in-app purchase products exist, then decide whether
   `SystemStoreKitClient` should degrade gracefully instead of throwing when a
   product is missing.
