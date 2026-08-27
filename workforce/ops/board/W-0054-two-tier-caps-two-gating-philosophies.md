---
id: W-0054
title: Two tier caps, two gating philosophies — life events block before entry, detailed expenditure blocks after submit with a silent 403
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: in-progress
severity: medium
surfaces: [web, m, ios]
created: 2026-08-21T14:15:00Z
claimed: 2026-08-26
blocked_by: []
gate: null
handoff_to: design-lead
prior_art_checked: 2026-08-21
prior_art_found: [W-0011]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **cap-lift test**, local `localhost:8000`.
Throwaway `users.id 31` (Tomas Weber), free tier. Raised at team-lead's direction as a
finding independent of the cap-lift result.

**Surface:** any free-tier capped module. Observed on `/goals?tab=events` and, via
W-0011, on `/valuable-info?section=expenditure`.

### Expected

A user at a tier cap learns one consistent thing about what "capped" means, whichever
module they are in.

### Actual

Two capped capabilities, two entirely different experiences:

| | `life_event` (cap 1) | `expenditure_detailed` (W-0011) |
|---|---|---|
| Where the block happens | **Before entry** | **After submit** |
| The Add control | visible and enabled | form fully editable |
| Clicking it | **form does not open**; an Upgrade affordance appears | form opens, accepts every field |
| On save | never reached | `PUT /api/user/profile/expenditure` → **403 `capability_denied`** |
| What the user sees | a prompt to upgrade | the form closes, **no error**, nothing saved |
| What the user learns | "this needs premium" | "I typed all that and the app lost it" |

Verified live at the cap: on `/goals?tab=events` with one life event recorded, the
"Add Life Event" button is `visible: true, enabled: true`, a **real pointer click**
leaves `formOpen: false`, and an Upgrade control renders.

The life-event behaviour is the **better** of the two — gate before entry, never after
work is done. W-0011 already calls for the expenditure gate to move before entry; this
item records that the inconsistency is the general problem, not a one-module bug, and
that the life-event path is the pattern to converge on.

### Impact

The cheaper harm is confusion. The real harm is the W-0011 shape: a user fills fifteen
expenditure categories and the app discards them silently. Someone who meets that once
has no reason to trust that any other form saved either.

There is also a commercial cost: a gate that appears *before* entry can offer an
upgrade at the moment of intent. A 403 after submit converts nobody and loses their
work.

### Repro

**Life events (gated correctly):**
1. Free-tier account. `/goals?tab=events` → add one life event (free cap is 1).
2. Click **Add Life Event** again. The form does not open; an Upgrade affordance shows.

**Detailed expenditure (gated incorrectly — W-0011):**
1. Same account. `/valuable-info?section=expenditure` → Detailed View.
2. Fill categories, Save. Form closes, no error, nothing persisted; the network shows
   403 `capability_denied`.

### Evidence

- `tests/Persona/20-08-2026_run/pass-a-web/40-web-FREE-tier-life-event-cap-baseline-user31.png`
- W-0011's own evidence for the expenditure path
- Free-tier `count_caps` read live: `property 1, investment 2, goal 2, life_event 1,
  pension_account 2, savings_account 2, mortgage 10`
- `TeaserGate::allows()` returned `false` for `life_events` at the cap; `mode` was
  `limited`

## Acceptance

- [ ] ONE gating philosophy across every tier-capped capability (Rule 20): **gate before
      entry**, never after submit, matching the life-event behaviour.
- [ ] Any capability at its cap presents the same shape of message and the same upgrade
      affordance, wherever it appears. `design-lead` owns that copy and pattern.
- [ ] No capped path can accept user input and then discard it. Where a 403 is still
      possible (a race, a cap reached in another tab), it surfaces as an error the user
      can see, and the entered data is not lost.
- [ ] Audit every capability in the matrix for which of the two shapes it currently
      uses — `property`, `investment`, `goal`, `savings_account`, `pension_account`,
      `expenditure_detailed`, `letter_to_spouse`, `estate`, `holistic_plan`,
      `document_upload`, `investments_exotic`, `joint_household_view`.
- [ ] Fixed alongside **W-0011**, which is the same problem seen from one module.
- [ ] `/m` and iOS carry the same single philosophy (Rule 19).
- [ ] Re-verified live in the browser by the persona run, at the cap, on at least two
      different capped capabilities.

## Working notes

Surfaced while establishing a baseline for the cap-lift test, not while looking for it.
The lift test itself passed cleanly (see R-15) — this is a separate observation about
what a capped user experiences before any upgrade happens.

---

## Acceptance 4 — the audit, done 2026-08-26

**Done by measurement, not by reading.** A first pass by grep gave the wrong answer
twice and both corrections matter, so the method is recorded with the result.

### The count-cap family

| Capability | Free cap | Gate BEFORE entry | Server enforcement |
|---|---|---|---|
| `goal` | 2 | ✔ `GoalsDashboard.vue` | ✔ `GoalStore` |
| `property` | 1 | ✔ `PropertyList.vue` | ✔ `PropertyStore` |
| `investment` | 2 | ✔ `InvestmentList.vue` | ✔ `InvestmentAccountStore` |
| `life_event` | 1 | ✔ `EventsTab.vue` | ✔ `LifeEventStore` |
| `pension_account` | 2 | ✔ `PensionList.vue` | ✔ `PensionStore` |
| `savings_account` | 2 | ✔ `CashOverview.vue` | ✔ `SavingsStore` |
| `mortgage` | 10 | ✘ **none** | ✔ `MortgageStore` |

**Six of seven already use the good shape.** The before-entry mechanism is
`tierLimitMixin`'s `isAtTierCap()` / `tierCountCap()`, and every list surface with an
Add control calls it.

### Two corrections to a grep-first reading

1. **`enforceTierCap` is not the mechanism.** Grepping for it found four stores and
   suggested `goal`, `life_event` and `savings_account` had no server enforcement at
   all. They do — `tierGate->canCreate()` is the real call, and those three make it
   directly rather than through a private wrapper. Proven by driving `SavingsStore`
   past its cap in a test: refused on attempt 3 of 2. **The inconsistency is in the
   helper's name, not in the enforcement.**
2. **`mortgage`'s outlier status is real but almost certainly cannot bite.** There is
   no mortgage list surface to gate — mortgages are added inside
   `PropertyDetailInline` / `PropertyForm`. And the free tier permits **1 property
   and 10 mortgages**, so reaching the mortgage cap needs ten mortgages secured on a
   single permitted property. It is the one count cap that would produce the
   after-submit shape, and the one a user is least likely ever to meet.

### This reframes the item

The item reads as though the estate is broadly inconsistent. On the count-cap family
it is not: six of seven gate before entry and the seventh is effectively unreachable.

**The loud example is a different mechanism.** `expenditure_detailed` is a
**capability** gate (`TeaserGate`, full/teaser/none), not a count cap — which is why
it behaves nothing like the seven above and why fixing the count-cap family would not
have touched it. The remaining capability gates named in acceptance 4 —
`letter_to_spouse`, `estate`, `holistic_plan`, `document_upload`,
`investments_exotic`, `joint_household_view` — belong to that same second family and
have **not** been audited here.

## Not done

- **Acceptance 1, 2, 3** — the convergence itself, and the shared message and upgrade
  affordance. Acceptance 2 assigns that copy and pattern to `design-lead`, and this
  item's `handoff_to` says the same, so it is not build-lead's to invent.
- **The capability-gate half of acceptance 4** — the six teaser-gated capabilities
  above. That is the family `expenditure_detailed` belongs to and where the real
  inconsistency is likely to live.
- **Acceptance 5** — W-0011, untouched.
- **Acceptance 6** — `/m` and iOS not examined.
- **Acceptance 7** — no live browser verification.

## Acceptance 4 — the capability-gate family, done 2026-08-26

The second family, and the one `expenditure_detailed` belongs to. Free tier declares
six `limited` (the count caps above), one `teaser` and **twelve `none`** — more than
the six this item names.

### The architecture is coherent, which the item does not credit

`resources/js/constants/tierAccess.js` states the design in its own docblock:

> `limited` → usable, but count-capped (the cap is surfaced separately by the
> limit-reached modal — **NOT a nav gate**)
> `teaser` / `none` → reachable, shows the teaser/upgrade page

So there are two mechanisms **on purpose**, and both gate before entry:

- **`limited`** → the limit-reached modal at the Add control (`isAtTierCap`).
- **`teaser` / `none`** → `ROUTE_CAPABILITY` maps the destination to a capability and
  `isRouteGated()` shows the upgrade page instead of the module. The user never
  reaches a form.

That covers `estate`, `what_if`, `holistic_plan`, `letter_to_spouse` (a query-param
route, handled specially) and `investment_cost_analysis`.

### The item's headline example was fixed the day after it was raised

`expenditure_detailed` is the awkward case — a capability **inside** a page rather
than a destination, so no route can gate it. It now gates before entry anyway:
`ExpenditureForm.vue:1373-1376` computes `canUseDetailedExpenditure` from
`auth/hasCapability` and forces Simple View without it, and `:400` withholds the
detailed toggle entirely.

`git log -S` dates that to **`d5fe9f9f7`, 2026-08-22** — the day after this item was
written. **The "Actual" table above no longer reproduces**, and W-0011 with it.

### What IS still the W-0011 shape

**`document_upload` and `statement_upload`.** Enforced by route middleware
(`CheckSubscription:47` → `api/documents/upload-only`) and `DocumentAllowanceGate`,
with **no frontend awareness whatsoever** — `grep` across `resources/js` finds
neither key outside the admin `TierConfiguration.vue`. So a free user at the
allowance chooses a file, uploads it, and is refused afterwards.

These are **actions, not destinations**, so `tierAccess.js` structurally cannot cover
them — which is why they were missed. They need the third pattern: a before-entry
check on the control itself, the same shape `expenditure_detailed` just got.

### And one that is not a gating problem at all

**`investments_exotic` is advertised in the pricing comparison and enforced
nowhere.** Raised separately as **W-0499** — it is a missing gate on something sold
to customers, not an inconsistent one.

### Revised picture

| Family | Mechanism | State |
|---|---|---|
| `limited` (7 count caps) | limit-reached modal | 6 correct; `mortgage` outlier, effectively unreachable |
| `teaser` / `none` **destinations** | route → teaser page | correct |
| capability **within a page** | in-page check | `expenditure_detailed` fixed 2026-08-22 |
| capability **as an action** | none | **`document_upload`, `statement_upload` — still after-submit** |
| advertised, ungated | none | **`investments_exotic` → W-0499** |

The item's premise — two philosophies, one module against another — is not what the
estate looks like. There is one philosophy, gate before entry, implemented by three
mechanisms for three shapes of thing, with **uploads the one shape that never got
one**.
