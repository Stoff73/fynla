# Canonical Freemium Economic Contract

**Status:** Current contract

**Effective:** 16 July 2026

**Applies to:** desktop web, `/m`, shared Laravel APIs, and future native iOS clients

This document is the concise source of truth for Fynla's live Free and Premium economics. Implementation plans may explain how the contract was reached, but they do not override it.

## Identity and lifecycle

| State | Entitlement | Write access | Required behaviour |
|---|---|---|---|
| Registration verified | Free; no `Subscription` row | Writable | Start onboarding and normal use immediately. |
| Premium checkout pending | Free; pending row does not confer Premium | Writable | Preserve Free access until the provider payment is verified. |
| Verified payment | Premium; active paid entitlement | Writable | Enable the canonical Premium capability matrix. |
| Paid cancellation inside purchased period | Premium until `current_period_end` | Writable | Show the exact access-end date; do not revoke early. |
| Terminal paid entitlement | No live Premium entitlement | Read-only | Apply the paid-churn grace and regulatory-retention process. |
| Permanent Free with no paid-churn state | Free | Writable | Keep data and enforce Free limits at Store boundaries. |

Free users do not expire, enter a retention countdown, or require payment to write. A checkout request is never an entitlement. Only a provider-verified payment activates Premium.

## Canonical tiers and economics

The only live tier identities are `free` and `premium`.

The live `TierConfigurationStore` is authoritative for web/Revolut prices and all limits:

| Contract item | Free | Premium |
|---|---:|---:|
| Monthly web price | £0 | 699 pence |
| Annual web price | £0 | 5999 pence |
| Savings accounts | 2 | Unlimited |
| Investment accounts | 2 | Unlimited |
| Pension accounts | 2 | Unlimited |
| Properties | 1 | Unlimited |
| Mortgages | 10 | Unlimited |
| Goals | 2 | Unlimited |
| Life Events | 1 | Unlimited |
| Document uploads | None | Unlimited count within 1 GB storage |
| Weekly Fyn token budget | 100,000 | 500,000 |
| Retained balance history shown | 90 days | Full retained history |

Count limits are enforced on the server at Store write boundaries. Clients may explain a limit or offer Premium, but hiding a control is not enforcement.

## Canonical API and surfaces

`GET /api/payment/subscription-status` is the canonical subscription and entitlement endpoint. Its current response uses `subscription_status` and includes the resolved tier, capability matrix, count limits, billing period, grace state, and payment availability.

Desktop web and `/m` consume the same server contract. `/m` must not implement a separate entitlement model; its upgrade entry routes to the shared subscription surface. Future native clients must also treat Laravel as the entitlement authority.

## Provider rules

Revolut is the current web payment provider. The web prices above are read from the live tier store rather than duplicated in client code.

Future StoreKit products map to the same server-authoritative Premium entitlement. Native purchase UI must display StoreKit's localised price and period; static iOS copy must not present the web price as the App Store price. Apple and Revolut may supply entitlement evidence, but neither creates a separate Premium tier.

## Historical models

The commercial identities `student`, `standard`, `family`, `pro`, `tier1`, `tier2`, and `tier3` are retired historical keys. The former time-limited signup model and its countdown, reminder, extension, and conversion machinery are also historical. They must not appear as current choices, runtime states, client branches, or user-facing claims.

Historical database migrations and explicit migration tests may retain retired keys to prove safe upgrade and rollback behaviour. Those references do not define the live contract.

## Change control

Any change to tier identity, prices, limits, lifecycle semantics, provider authority, or the canonical API requires an approved product decision, code and migration tests, desktop and `/m` parity verification, and an update to this document in the same release.
