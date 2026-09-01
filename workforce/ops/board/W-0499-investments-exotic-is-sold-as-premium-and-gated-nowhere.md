---
id: W-0499
title: investments_exotic is advertised as a Premium feature and enforced nowhere, so a free user is not actually prevented from using it
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [product-lead, compliance-lead]
status: done
claimed_by: null
severity: medium
surfaces: [web, m, ios]
created: 2026-08-26T00:00:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-26
prior_art_found: [W-0054, W-0463, W-0498]
prior_art_outcome: extends
source: found while auditing the capability-gate family for W-0054, 2026-08-26
---

## Intent

`investments_exotic` is `none` on free and `full` on premium in the capability
matrix, and it is **sold**: `PaymentController:116` offers it to prospective
customers as *"Advanced investment types"* and `TierComparisonService:22` lists it in
the tier comparison as *"Alternative investments"*.

**Nothing enforces it.** Every mention in the repository:

| Location | What it does |
|---|---|
| `PaymentController.php:116` | advertises it in the upgrade copy |
| `TierComparisonService.php:22` | lists it in the tier comparison table |
| `TierConfigurationSeeder.php:44,87` | sets it `none` / `full` |

There is no route in `tierAccess.js`'s `ROUTE_CAPABILITY`, no entry in
`CheckSubscription`'s route map, no `TeaserGate` consumer, and no store check. A
free-tier user is not prevented from doing whatever this names.

## Why this is not W-0054

W-0054 is about **which shape** a gate takes — before entry or after submit. This
capability has **no gate of either shape**, so it is a different defect that the
W-0054 audit happened to walk into.

It is the shape of W-0463 and W-0498 — a configured rule nothing reads — with one
difference that makes it worse than either: **this one is quoted to customers in the
pricing comparison.** A feature named in a paid tier's differentiators and available
without paying is a commercial and arguably a fair-trading problem, not only a tidiness
one.

## The question this has to answer first

**What does `investments_exotic` actually mean?** Nothing in the code says. Candidates,
from the labels: an `account_type` or holding class on `InvestmentAccount` (VCT, EIS,
unlisted, crypto), or `business_interests`, or `chattels`. Until that is settled it
cannot be gated, and a guess would gate the wrong thing for paying and non-paying
users alike.

That is why `product-lead` is a reviewer here. `compliance-lead` too, because the
answer decides whether the pricing page currently describes the product accurately.

## Acceptance

1. `investments_exotic` is defined — which records or actions it covers, recorded
   where the next reader will find it.
2. Either it is enforced consistently with the family (route-level teaser via
   `tierAccess.js` where it is a destination; a before-entry check where it is an
   action), **or** it is removed from the capability matrix AND from both pieces of
   customer-facing copy. It must not keep being sold while ungated.
3. Whichever way, `ConfiguredRulesHaveConsumersTest` covers it afterwards — asserting
   a consumer exists, or listing it as a deliberate exception with the reason.
4. If it has been ungated in production, establish for how long and whether any free
   user relied on it, before anything is switched off under them.

## Related

- **W-0054** — the gating-philosophy audit that found this.
- **W-0463 / W-0498** — the same "configured and unread" shape, without the
  customer-facing half.

## 2026-09-01 — CLOSED. Defined from what was recorded, then gated.

**Acceptance 1 — defined, and not guessed.** The item said nothing in the code says what
`investments_exotic` means. Something does, one directory over:
`tests/Persona/20-08-2026_run/PASS-PLAYBOOK.md:51` records it as **Venture Capital Trust
and Enterprise Investment Scheme**, and `R-12-batch-a-confirmation-and-regression.md:136`
uses that reading against a persona's VCT holding. The definition now lives where the
next reader will find it — `InvestmentAccountStore::EXOTIC_ACCOUNT_TYPES`, with the
provenance at the line. Nothing was added beyond what was recorded: `private_company`
and `crowdfunding` are plausible members and are **not** included, because gating on a
guess restricts paying and non-paying users alike.

`tax_relief_type` is read alongside `account_type`, because the relief is the thing being
claimed and it can be attached to an account of any type — gating only the type would
leave the door open. `EstateController:148` already reads the pair the same way.

**Acceptance 2 — enforced, in the Store.** `InvestmentAccountStore::enforceExoticCapability()`,
called from `create()`. Not a route gate: this is a property of the record, not a
destination, and web, `/m` and Fyn all write through the Store and through nothing else —
a route gate would have to be added three times and would still miss Fyn. It composes
from `TeaserGate::allows()`, the existing one home for "may this user use this
capability", including its admin and preview bypass.

**Create only, deliberately** (acceptance 4). Nothing gated this until now, so a Free
user may already hold such a record; refusing an *update* would strand a record they
could then neither correct nor delete. Switching the gate on takes nothing away from
anyone, which is the answer to "establish whether any free user relied on it" — the
question is moot when the gate only refuses new writes.

**Acceptance 3 — a guard, generalised rather than a single-key exception.**
`tests/Feature/Tiers/EveryCapabilityHasAConsumerTest.php` compares the capability matrix
against the application and fails on any capability nothing reads. It would have caught
this on the day it landed.

**It found two more of the same defect, and they are worse than the tidy kind.**
`family_module` and `benefits_child` have **zero consumers** and are **named in the
pricing comparison** (`TierComparisonService:28-29`) — sold and ungated, exactly as
`investments_exotic` was. `future_value_projections` and `property_buy_to_let_analysis`
are also unconsumed but are not advertised. All four are listed as known exceptions with
that reasoning at the line, and **reported rather than fixed here** — defining a
capability nobody has defined is how the wrong thing gets gated.

Tests: `ExoticInvestmentsAreGatedTest` 6 passed (free refused on type, free refused on
relief, premium allowed, ordinary types untouched, Fyn gated by the same rule, and the
exception naming the capability so the client can offer the upgrade);
`EveryCapabilityHasAConsumerTest` 2 passed. Suites: **171 passed** Tiers + Investment
feature, **569 passed** across the wider Investment filter.

**Not done:** no browser drive, and no change to either piece of customer-facing copy —
it is now accurate, because the capability it names is enforced.
