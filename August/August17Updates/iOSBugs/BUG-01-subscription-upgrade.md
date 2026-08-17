---
id: BUG-01
raised: 2026-08-17
surface: native iOS (confirmed from CSJ's screenshot)
severity: blocker
status: ROOT CAUSE CONFIRMED — no code fix required for the primary cause
fixed_in: null
testflight_build: null
---

# BUG-01 — "Premium subscriptions are unavailable" on the iOS app

## Root cause

**There are no in-app purchase products in App Store Connect.** Queried the ASC
API directly with key `683FKHT7SL`:

| App record | ASC id | subscriptionGroups | inAppPurchasesV2 |
|---|---|---|---|
| Fynla Dev (`org.fynla.app.dev`) | 6793193337 | **0** | **0** |
| Fynla (`org.fynla.app`) | 6760545667 | **0** | **0** |

Both are empty. `org.fynla.premium.monthly` and `org.fynla.premium.annual` have
never been created on either record.

**This is a configuration gap, not a code defect.** The app is behaving correctly
given that Apple returns nothing.

## The chain

1. The paywall asks StoreKit for `StoreProductIdentifier.all` —
   `org.fynla.premium.monthly` and `org.fynla.premium.annual`
   (`StoreKitModels.swift:4-5`).
2. Apple returns **zero products**, because none exist on the app record.
3. `SubscriptionModel.swift:288-297`:

```swift
case (.free, _):
    let sorted = products.sorted { productRank($0.id) < productRank($1.id) }
    guard Set(sorted.map(\.id)) == StoreProductIdentifier.all,
          let selected = sorted.first?.id
    else {
        state = .unavailable(
            message: "Premium subscriptions are unavailable. Please try again later."
        )
        return
    }
```

4. The guard fails → the exact message in CSJ's screenshot.

Confirmed by the screenshot: full feature list renders (that comes from the
backend and is fine), then "Something went wrong / Premium subscriptions are
unavailable. Please try again later." with a "Try again" button.

## Secondary code defects — worth fixing alongside

1. **All-or-nothing product loading.** The guard demands the product set match
   `StoreProductIdentifier.all` **exactly**. One missing or mis-configured
   product takes the entire paywall down rather than offering the one that did
   load. The same pattern exists a layer below in
   `SystemStoreKitClient.swift:63-67`, which throws `productUnavailable` on any
   mismatch. Two layers, same brittleness.

2. **The message is misleading.** "Please try again later" and a "Try again"
   button imply a transient fault. Nothing about a missing ASC product is
   transient — retrying can never succeed. A user will tap "Try again" forever.

3. This is also why the six `Local StoreKit configuration` tests are red — the
   same zero-products condition, and the reason not to have dismissed them as
   benign.

## What is NOT broken — verified, so we don't chase it again

- **Backend:** `plans()` returns 200 (Premium £6.99/mo, £59.99/yr, 10 features);
  `subscriptionStatus()` returns 200 with the full capability matrix and
  `payment_enabled: true` at the top level.
- **Desktop web:** subscription page → "Compare plans" modal → "Choose Plan" →
  `/checkout` with a correct Order Summary. Works.
- **`/m`:** the agreed architecture is intact — `Subscription.vue` calls
  `issueWebHandoff('subscription')`, matching the specced "mobile routes the user
  to the web app for payment". `POST /api/v1/mobile/web-handoffs` returns **201**
  with a valid signed URL and expiry.
- **Local Revolut errors are environment-only.** Local holds production Revolut
  keys, so checkout cannot complete locally (`400 validation` against production,
  `401 unauthenticated` against sandbox). Dev completes checkout, so this is not
  a code path worth pursuing.

## Two corrections to my earlier reports

Recorded so the wrong versions do not get re-used:

1. **"The web Compare plans button does nothing" — WRONG.** The button works. The
   browser automation had stopped delivering mouse events; a control click on the
   "General" tab did nothing either, and instrumented document-capture listeners
   recorded zero events for both. A synthetic `.click()` opens the modal.
2. **"`payment_enabled` is absent from the payload" — WRONG.** It is present at
   the **top level** with value `true`, and `/m`'s merge at
   `Subscription.vue:131-133` (`{ ...response.data, ...response.data.data }`)
   does pull it through. I checked inside `data` instead of at the top level.

Both errors came from reasoning off code rather than exercising the surface.

## Fix

**Primary (CSJ / App Store Connect — not a code change):** create the subscription
group and the two auto-renewable subscriptions on the `Fynla Dev` record, product
IDs exactly:

```
org.fynla.premium.monthly   £6.99   P1M
org.fynla.premium.annual    £59.99  P1Y
```

matching `StoreKit/Fynla.storekit` and `StoreKitModels.swift:4-5`. Needs the paid
applications agreement to be active, plus pricing, localizations and review
information before they reach a state StoreKit will return. Once they exist, the
existing code should work unchanged.

**Secondary (code, once the above is decided):**

1. Degrade gracefully — offer whichever products loaded instead of failing the
   whole paywall when the set is incomplete.
2. Replace "Please try again later" for the empty-catalogue case with wording that
   does not promise a retry will help, and drop the "Try again" button on that
   branch.
