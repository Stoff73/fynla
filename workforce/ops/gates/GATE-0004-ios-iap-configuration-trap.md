---
id: GATE-0004
workstream: quality
item: BUG-01
action: Record the native in-app-purchase configuration trap in root CLAUDE.md and ios-native/CLAUDE.md, and correct the "never to be chased" line about the red StoreKit tests
raised: 2026-08-17T11:40:00Z
decided_by: null
decided_at: null
decision: null
status: proposed
---

## What is being asked

CSJ, 2026-08-17: *"update the readme and claude docs with this … so when I forget
that we have already looked at this, you have the solution to hand."*

The README half is **done** — not gated. Both `CLAUDE.md` edits are gated by
`oversight-guard.sh:39` (`(^|/)CLAUDE\.md` matches the root file *and*
`ios-native/CLAUDE.md`), so they are proposed here.

Full analysis: `August/August17Updates/iOSBugs/BUG-01-subscription-upgrade.md`.

## Why this is worth a gate rather than leaving it in the bug file

`ios-native/CLAUDE.md:56` currently says:

```
**Known and never to be chased:** 6 StoreKit hosted-configuration unit tests are red locally and green in CI.
```

**That line actively caused today's delay.** Those six tests are red because
StoreKit returns **zero products** — the same condition that produces the live
paywall failure. Instructed not to chase them, I dismissed the one signal that
pointed straight at the root cause and spent the session on the web and `/m`
surfaces instead. The line needs correcting, not just supplementing.

## Evidence

App Store Connect API, key `683FKHT7SL`, queried 2026-08-17:

| App record | ASC id | subscriptionGroups | inAppPurchasesV2 |
|---|---|---|---|
| Fynla Dev (`org.fynla.app.dev`) | 6793193337 | **0** | **0** |
| Fynla (`org.fynla.app`) | 6760545667 | **0** | **0** |

`org.fynla.premium.monthly` and `org.fynla.premium.annual` have never been
created on either record. `SubscriptionModel.swift:291` requires the returned set
to equal `StoreProductIdentifier.all` exactly, so zero products produces
"Premium subscriptions are unavailable. Please try again later."

## Edit 1 — `ios-native/CLAUDE.md`, replace line 56

```
OLD:
**Known and never to be chased:** 6 StoreKit hosted-configuration unit tests are red locally and green in CI.

NEW:
## In-app purchases — the trap that cost a session

**No in-app purchase products exist in App Store Connect.** Verified 2026-08-17
against the ASC API: both `Fynla Dev` (6793193337) and `Fynla` (6760545667)
return **zero** subscription groups and **zero** in-app purchases.

So StoreKit returns nothing, and `SubscriptionModel.swift:291` — which requires
the returned set to equal `StoreProductIdentifier.all` exactly — puts the paywall
into `.unavailable` with **"Premium subscriptions are unavailable. Please try
again later."** That is the app behaving correctly, not a bug in it.

To fix, create in App Store Connect, matching `StoreKit/Fynla.storekit` and
`StoreKitModels.swift:4-5` exactly:

    org.fynla.premium.monthly   £6.99    P1M
    org.fynla.premium.annual    £59.99   P1Y

No second Apple Developer account is required. In-app purchases do need the
**Paid Applications Agreement** Active (App Store Connect → Business): accept the
agreement, add banking details, complete tax forms.

**The 6 red `Local StoreKit configuration` tests are a real signal, not noise.**
They were previously documented here as "never to be chased"; that was wrong and
cost a session. They are red for the same zero-products reason. Treat them as
green only once the products exist and they pass.

Native cannot use the `/m` web-payment handoff — Apple requires in-app purchase
for digital goods — so this is the only route to a paid native subscription.
```

## Edit 2 — root `CLAUDE.md`, Mobile Clients → `ios-native/` subsection

Append after the existing "Production has no native endpoints" paragraph, which
is the other trap in the same area:

```
NEW paragraph:

**⚠️ The native paywall cannot work yet: there are no in-app purchase products in
App Store Connect.** Verified 2026-08-17 — both app records return zero
subscription groups and zero in-app purchases, so StoreKit returns nothing and
the paywall shows "Premium subscriptions are unavailable". This is configuration,
not code, and it needs the Paid Applications Agreement Active before the products
can be created. Web and `/m` are unaffected — `/m` hands off to the web app for
payment, which is the agreed architecture. Details:
`August/August17Updates/iOSBugs/BUG-01-subscription-upgrade.md`.
```

## What happens if held

The next session repeats today's: sees a red StoreKit suite, reads "never to be
chased", and searches the web and `/m` surfaces for a bug that is not there. The
bug file records it, but nothing routes an agent to that file — `CLAUDE.md` is
what gets read on every task.

## Decision and reasoning

_Pending CSJ._
