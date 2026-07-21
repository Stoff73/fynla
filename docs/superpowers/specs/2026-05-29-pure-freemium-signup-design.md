# Pure Freemium Signup — Implemented Current State

**Original decision:** 29 May 2026

**Current-state revision:** 16 July 2026

**Status:** Implemented; retained as design history

**Canonical contract:** `codex/plans/canonical/01-freemium-economic-contract.md`

This document records the implemented outcome of the pure-freemium signup decision. The canonical contract above is authoritative for current tiers, economics, lifecycle, API, and cross-client behaviour.

## Decision

Fynla uses permanent Free as the default account state. Registration does not create temporary paid access, a payment deadline, or a `Subscription` row. Users may write immediately within server-enforced Free capabilities and limits, then choose Premium when they need it.

The retired signup model, reminder campaigns, countdown presentation, extension codes, conversion commands, and trial-only database schema are not part of current runtime behaviour.

## Implemented registration contract

After email verification:

- `users.tier` and `users.plan` are `free`;
- no `Subscription` exists unless the user starts a Premium checkout;
- no retention or deletion countdown is attached to Free;
- onboarding and the one Fyn surface remain available;
- a persisted Premium registration intent may continue to checkout, but the account remains Free until payment is verified.

Registration accepts only Free or an explicit Premium checkout intent. Query-string or verification-request values cannot replace the intent saved on `PendingRegistration`.

## Implemented entitlement lifecycle

1. Free with no subscription is writable.
2. Store boundaries enforce Free count and capability limits.
3. A pending Premium checkout remains non-entitling; Free access continues.
4. A verified provider payment activates Premium.
5. Cancelling Premium preserves access through the purchased period end.
6. A terminal paid entitlement enters the paid-churn read-only, grace, and regulatory-retention path.
7. A permanent Free account that has not entered paid churn is not routed into retention.

The global subscription middleware distinguishes a normal Free account from terminal paid churn. It does not use absence of a subscription as a reason to block writes.

## Current commercial model

Only `free` and `premium` are live identities. `TierConfigurationStore` is authoritative for prices, capabilities, limits, Fyn budgets, document storage, and history surfacing.

Premium web billing is 699 pence monthly or 5999 pence annually. Free Store-boundary count limits are two savings accounts, two investment accounts, two pension accounts, one property, ten mortgages, two Goals, and one Life Event. Premium count limits are unbounded; Premium document count is unbounded within 1 GB storage, its weekly Fyn budget is 500,000 tokens, and it can surface the full retained balance history.

## Current API and client contract

`GET /api/payment/subscription-status` is the canonical endpoint. The status field is `subscription_status`; no response exposes trial-only dates, progress, or remaining-day fields.

Desktop and `/m` share the Laravel entitlement authority. `/m` may route an upgrade action into the shared web subscription surface, but it does not maintain a separate tier model. Future StoreKit products must map into the same Premium entitlement; native display prices remain StoreKit-authoritative.

## Historical compatibility boundary

The former commercial keys `student`, `standard`, `family`, `pro`, `tier1`, `tier2`, and `tier3` may remain only in historical migrations, rollback paths, financial-history preservation, and explicit migration tests. They are not current products or client choices.

The former time-limited signup state may likewise appear only in historical migrations or tests that prove destructive schema removal is safely blocked and reversible. It must not be written by current application code.

## Safety and verification

The implemented contract is protected by:

- registration tests proving Free plus no subscription;
- payment tests proving pending checkout does not grant Premium and provider verification does;
- tier and Store-boundary tests for the approved limits;
- middleware tests separating permanent Free from terminal paid churn;
- schema tests proving trial-only columns/table and status are removed;
- desktop and `/m` presentation tests using the canonical subscription response;
- cross-environment audit evidence before the destructive schema migration is remotely deployed.

The destructive migration may be prepared and tested locally, but it must not be merged or remotely deployed until the exact read-only audit is green and saved on both csjones and production.
