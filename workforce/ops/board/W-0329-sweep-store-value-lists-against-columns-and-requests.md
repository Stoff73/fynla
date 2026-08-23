---
id: W-0329
title: The fifth axis — sweep every Store's accepted-value lists against BOTH the column definitions and the matching request rules, because Fyn and /m write through the Stores
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0025-cycle4-validation-vs-schema-range.md
owner: build-lead
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-23T00:10:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0326, W-0263, W-0324]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

**`resources/mobile/api.js` has no post, put or patch helper anywhere in the
bundle. Fyn is not one of `/m`'s write paths — it is the ONLY one. So sweeping
`app/Http/Requests/` says nothing whatever about how `/m` writes.**

That is the whole item in one line, and it is easy to miss because the backend
*looks* shared. It is shared at the endpoint and diverges one layer down: Fyn's
capture handlers in `CoordinatingAgent` write through the **Stores**
(`InvestmentAccountStore::create`, `MortgageStore`), which carry their own
validation, not through the form requests.

**A rule that holds in `app/Http/Requests/` and not in `app/Services/Stores/`
therefore means the web form and the Fyn capture path accept different things —
and `/m` and native follow the Store, not the request.**

F-0025 measured four axes of rule-versus-schema disagreement and every one of them
lives in the request layer. This is the fifth, and it is the layer the mobile
surface actually writes through.

W-0326 was that, live and user-visible: `MortgageStore` alone of four layers
rejected `mixed`, so a part-fixed part-variable mortgage could not be recorded at
all, and the failure was swallowed by the calling component.

## What has already been measured

A first pass over the Stores is done, so this item starts from numbers rather
than a guess:

- **17** `in:` rules across the mapped Stores.
- **1 fixed** — `MortgageStore::rate_type` (W-0326), which was wrong in both
  directions at once.
- **2 remaining**, both the "column stores what the rule refuses" direction:

| Store | Field | Column stores, rule refuses | Verdict |
|---|---|---|---|
| `MortgageStore` | `ownership_type` | `tenants_in_common`, `trust` | **Deliberate.** `MortgageNormaliser:79-81` coerces tenants_in_common to joint before the Store sees it, and documents that mortgages do not support it. Not drift. |
| `InvestmentAccountStore:254` | `ownership_type` | `trust` | **Not recorded as deliberate.** `investment_accounts.ownership_type` stores `trust`, and Store/UpdateInvestmentAccountRequest BOTH allow it via `Rule::in(['individual','joint','trust'])` — but the Store refuses it, with no coercion of the kind that legitimises the mortgage case. |

**A drift guard already exists:** `tests/Unit/Database/StoreEnumRulesMatchColumnsTest.php`,
two tests, both proven to fail. It checks both directions and carries an explicit
exception list, because **a rule narrower than its column is not automatically a
defect** — see the mortgage row above.

## What is still open

1. **Resolve `InvestmentAccountStore::ownership_type`.** Latent rather than live —
   no form offers trust ownership on an investment account today — but the request
   layer permits it, so any client, including Fyn's tool schema, can send it. Then
   remove its line from `DELIBERATELY_NARROWER`.
2. **The sixth axis: numeric bounds at the Store layer.** This pass covered enum
   lists only, and the numeric half is a distinct axis rather than a detail of
   this one — **it is F-0025's headline axis (range) repeated one layer over**,
   where nobody has swept it.

   It is already known to diverge:

   | Store | Field | Bound | Consequence |
   |---|---|---|---|
   | `MortgageStore:306` | `interest_rate` | `max:100` | fine |
   | `MortgageStore` | `fixed_interest_rate`, `variable_interest_rate` | **none** | the column is the only limit Fyn's path meets |
   | `InvestmentAccountStore` | `platform_fee_percent` | **none** | **Fyn accepts a 12% platform fee where the form returns a 422** |

   Note the shape: these are the *same fields* F-0025 fixed in the request layer.
   The widened columns mean they no longer 500 — which is why this is now a
   divergence in bound rather than a crash — but the two surfaces still disagree
   about what a user may say, and **the unbounded ones are exactly the
   "absence of a bound" axis (§4.3) in a layer that was never swept for it.**
3. **Map the three unmapped Stores** the sweep skipped, and the pension Stores,
   which write several tables from one class.

## Acceptance

1. Every Store's accepted-value list reconciled against its column AND its
   matching request rule, with each intentional narrowing recorded **and naming
   the mechanism that guarantees the excluded value never arrives** — an
   exception without a reason is a place to hide drift, not a decision.
2. Numeric bounds swept as their own axis, not as a footnote to the enums.
3. The guard extended to cover whatever the sweep adds, so the next divergence
   fails a test rather than a user's save.

## Working notes

- 2026-08-23 build-lead (`fix-cycle4-columns`): raised on team-lead's instruction.
  The measured half is done and the guard is in place; the open half is the
  numeric bounds and the unmapped Stores.
