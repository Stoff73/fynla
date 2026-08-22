---
id: W-0126
title: Seven more holding-valuation copies sat outside the one home, and three were in one controller
mission: M-0002-persona-fidelity
owner: build-lead
status: handoff
claimed: 2026-08-21T20:05:00Z
handoff_to: quality-lead
branch: workforce/branches/fixes/F-0010-batch-j-consolidation-red.md
severity: medium
surfaces: [web, m, ios]
source: found by fix-batch-J while closing W-0122, 2026-08-21 — the Fyn handler was not the only second copy
prior_art_checked: 2026-08-21
prior_art_found: [app/Support/HoldingValuation.php (W-0039), W-0121, W-0122]
prior_art_outcome: extend
constitution_refs: [00-precedence, 07-quality-bar]
---

## Intent

W-0122 routed `CoordinatingAgent::handleCreateHolding` through
`App\Support\HoldingValuation`, on the amended acceptance that the Fyn path must
**read** the one rule rather than agree with it. Enumerating every holding write site
to check that claim turned up **five more that still do not read it.**

| # | Site | What it does with units, price and value |
|---|---|---|
| 1 | `app/Http/Controllers/Api/Retirement/DCPensionHoldingsController.php:96-98` | **`cost_basis = quantity × purchase_price`, inline.** A line-for-line copy of `HoldingValuation::reconcile()`'s cost-basis branch. |
| 2 | `app/Http/Controllers/Api/InvestmentController.php:411-425` | Account-create-with-holdings: value from allocation percentage, no unit count derived. |
| 3 | `app/Http/Controllers/Api/InvestmentController.php:564-578` | Account-update-with-holdings: the same again. |
| 4 | `app/Http/Controllers/Api/RetirementController.php:409-424` | Defined contribution pension holdings: the same shape against `current_fund_value`. |
| 5 | `app/Services/Documents/HoldingsImportService.php:96` | Passes `quantity`, `current_price` and `current_value` straight from an uploaded file to `Holding::create()` with **no reconciliation at all**. |

Site 1 is the clearest: it is the same arithmetic as the shared class, written out
again. Sites 2–4 are the shape W-0122 just fixed on the Fyn path — an allocation
percentage producing a value, with the unit count never derived even where a price is
present. Site 5 is the one that can store a contradiction: an imported row may carry
units and a value that disagree, and nothing reconciles them.

**Why this is not just tidiness.** Rule 20 does not say the copies must agree, it says
there must not be copies — *"if more than one mechanism implements the behaviour,
consolidating them into ONE source that all consumers read is PART of the fix"*, and
editing copies in lockstep is named as a violation. W-0121 fixed a user's typed value
being silently discarded; W-0122 established that a second copy is *why such a fix does
not reach every write path*. These five are the remaining reason it still does not.

## Acceptance

1. Every site above builds its payload and passes it through
   `HoldingValuation::reconcile()` before creating or updating the row. Allocation
   percentages stay as an **input** to the shared rule — they are where the value comes
   from when nothing else is said, not a competing rule.
2. No arithmetic relating units, price, value or cost basis survives anywhere outside
   `app/Support/HoldingValuation.php`. Site 1's inline cost-basis line is deleted, not
   made to match.
3. `holdings.current_value` is NOT NULL with no database default. Where nothing values
   the holding, the column takes zero **after** reconciliation, as a storage constraint
   — never as a valuation rule inside it.
4. Regression cover per site asserting the **stored row**, since every one of these
   returns a response that looks identical either way.
5. Site 5 needs a decision, not just a routing change: an uploaded file can state units
   and a value that disagree. Reconciling means one of them is overwritten, which is
   the W-0121 defect in a new place. Decide whether an import refuses a contradicting
   row, flags it, or applies units-win — and record the choice.

## Working notes

**Not fixed by fix-batch-J.** W-0122's scope was the Fyn handler and it was taken at
the final gate before a large uncommitted consolidation; five more write paths across
three modules, one of which needs a product decision, is not a red-suite repair.

Sequencing note: this and W-0122 share one class, so doing them together is cheaper
than doing them apart — but W-0122 alone is what unblocks W-0121's sign-off, and it is
already done. This can follow at its own pace.


---

## Working notes — fixed 2026-08-21 by fix-batch-J

**The count in the title changed while fixing it: seven sites, not five.**
`DCPensionHoldingsController` held **three** copies, not the one this item named:

| Method | What it held | Now |
|---|---|---|
| `store():97-99` | `cost_basis = quantity x purchase_price` inline | Deleted; reads `HoldingValuation::reconcile($validated)` |
| `update():145-152` | **A hand-rolled supplied-versus-inherited fallback** — `$validated['quantity'] ?? $holding->quantity` | Deleted; reads `reconcile($validated, $holding)` |
| `bulkUpdate():220-224` | **No reconciliation at all** — wrote a new `current_value` and `current_price` and left the stored unit count beside them | Reads `reconcile($payload, $holding)` |

`update()` is worth naming precisely: it wrote out by hand the exact construct W-0121
was raised about — an inherited unit count standing in for one the caller never
mentioned. `bulkUpdate()` is worth naming too, because it was a **live** instance of
the same defect: a bulk re-valuation to £60,000 left 100 units and a £500 price stored
beside it, so the row contradicted itself on save. Both are now decided by the shared
class instead of by whoever wrote the endpoint.

The other four sites — `InvestmentController:411` and `:564`, `RetirementController:409`,
and the three cash-remainder writes alongside them — now read the shared class too.
**Be honest about what that changed: nothing, today.** Their requests accept only
`security_name`, `asset_type`, `allocation_percent`, `cost_basis` and `ocf_percent`
(`StoreInvestmentAccountRequest:93-100`, `UpdateInvestmentAccountRequest:104-111`,
`StoreDCPensionRequest:77-81`) — no units, no prices — so there is nothing for the
reconciliation to do. It is worth doing anyway, and not only for tidiness: the day
someone adds a price field to one of those forms, the units rule applies without anyone
remembering that it should. That is the difference between a reader and a copy.

The allocation percentage stays where it is at every one of those sites. It is where
the value comes from when nothing else is stated — an **input** to the shared rule, not
a competing one, exactly as settled in W-0122.

## Evidence

`tests/Feature/Retirement/DCPensionHoldingValuationTest.php` — **NEW**, 5 passed
(16 assertions). These endpoints had no feature cover at all before. Every case asserts
the stored row, because all three return a response that looks identical either way:

- a create with a value and a price back-calculates the units and derives cost basis
  from them (£45,000 at £450 → 100 units, £35,000 basis);
- a create with units and a purchase price still derives cost basis — the behaviour the
  deleted inline copy provided, proving the consolidation kept it;
- an update stating a value and no units is **not** overwritten by the 100 units on
  record (£60,000 stays £60,000, units become 120);
- an update stating only a price revalues the stored units (£45,000 → £50,000);
- a bulk re-valuation re-derives the unit count instead of leaving it contradicting.

Wider regression: `tests/Feature/Retirement`, `RetirementModuleTest`,
`RetirementIntegrationTest`, `InvestmentModuleTest`, `tests/Feature/Investment`,
`Api/InvestmentControllerTest`, `Api/RetirementControllerTest`, `tests/Feature/Stores`,
`tests/Feature/AI/DirectWrite`, `tests/Unit/Support` — **507 passed (1,655 assertions)**.

Two failures in that run are **not from this work** and are flagged to the team-lead:
`InvestmentAccountHttpIntegrationTest:95` and `PropertyHttpIntegrationTest:129`, both
422s on **ownership** validation from `fix-batch-F`'s in-flight W-0040 change. Nothing
here touches ownership; every holding test passes.

Pint clean on all four files. Imports re-checked after the formatter ran.

**Not browser-verified by the fixing agent** — a fix agent does not close Rule 14's loop
on its own work. The defined contribution pension holdings editor is the surface to
exercise: enter a value with no units, and a bulk re-valuation, and confirm the figures
that come back are the figures typed.

**The import site is NOT fixed and is deliberately not part of this item.** It needs a
product decision rather than a routing change — an uploaded file can state units and a
value that contradict, and reconciling silently overwrites one of them, which is W-0121
in a new place at the one boundary where the source has independent authority. Split out
as **W-0127** with a recommendation, on the team-lead's instruction.
