---
id: W-0127
title: An imported holding can store units and a value that contradict each other, and reconciling silently overwrites one of them
mission: M-0002-persona-fidelity
owner: product-lead
reviewers: [build-lead]
status: queued
claimed: null
handoff_to: null
branch: null
severity: medium
surfaces: [web, m, ios]
source: split out of W-0126 by fix-batch-J on the team-lead's instruction, 2026-08-21 — a product decision, not a routing change
prior_art_checked: 2026-08-21
prior_art_found: [app/Support/HoldingValuation.php, W-0121, W-0122, W-0126, app/Services/Documents/HoldingsImportService.php:96]
prior_art_outcome: extend
constitution_refs: [00-precedence, 07-quality-bar]
---

## Intent — a decision, not a routing change

W-0126 routed every other holding write path through `App\Support\HoldingValuation`.
**Document import is deliberately excluded, because routing it is not obviously right.**

`app/Services/Documents/HoldingsImportService.php:96` passes `quantity`,
`current_price`, `current_value` and `cost_basis` straight from a parsed file into
`Holding::create()` with no reconciliation. So an imported row **can store units and a
value that contradict each other** — 351 units at £7.42 recorded alongside a £9,999
valuation, and nothing notices.

That is a real defect. But the obvious fix is not obviously correct:

**Reconciling means overwriting one of the two figures the file stated.** Under the
shared rule, units win when both are supplied — so the £9,999 the broker's own export
said would be replaced by £2,604.42, silently, at import. **That is W-0121 in a new
place**: a figure the user supplied, accepted, and discarded without telling them.

## Why this one is genuinely harder than the other four

The other four sites reconcile figures a **user typed into a Fynla form**, where the
server owning the derivation is plainly right. This one reconciles a **file the user
exported from their broker or platform**, and that file has a claim to authority the
form does not:

- The valuation may be the provider's official mid-price at a stated valuation date,
  while the price column is indicative or stale.
- Units and price may be correct to more decimal places than either column shows, so a
  small disagreement is rounding, not error — and units-win would replace an accurate
  value with a less accurate derived one.
- A genuinely large disagreement usually means the file was misparsed (a column offset,
  a pence/pounds mix-up), and silently "fixing" it destroys the evidence that would
  have shown the import was wrong.

## The options

| Option | Behaviour | Cost |
|---|---|---|
| **A — Units win, as everywhere else** | Route through `HoldingValuation` unchanged. One rule, no exceptions. | Silently overwrites a stated valuation. Exactly the defect W-0121 was raised about, at the one boundary where the source has independent authority. |
| **B — Refuse a contradicting row** | Import rejects rows where units × price and the stated value disagree beyond a tolerance. | Honest, but a whole import can fail on rounding unless the tolerance is right, and users cannot easily fix a broker's export. |
| **C — Import both, flag the disagreement** | Store what the file said, mark the holding as unreconciled, and show the user both figures to resolve. | Truthful and loses nothing. Needs a review surface on web and `/m`, so it is the most work. |

**Recommendation: C, with A as the tolerance case.** Where units × price and the stated
value agree within a small tolerance, reconcile silently — that is rounding and nobody
needs to see it. Where they disagree materially, **store both and ask**, because at
that point the disagreement is information: it usually means the import misread the
file, and that is worth surfacing rather than papering over. B is the wrong default —
refusing the row throws away data the user does have, and gives them no way forward
when the file is their broker's and not editable.

Whatever is chosen, the tolerance and the resolution rule live in
`App\Support\HoldingValuation` alongside the rest, not in the import service (Rule 20).

## Acceptance

1. A decision recorded here, by name, on which of A/B/C applies.
2. The behaviour implemented in `HoldingValuation`, with `HoldingsImportService` as a
   reader — never an import-only branch of the valuation rule.
3. If C: the flag is visible on web **and** `/m` (Rule 19), and an unreconciled holding
   is not silently counted as reconciled by anything that reads it.
4. Regression cover for an agreeing import, a rounding-level disagreement, and a
   material disagreement, all asserting the stored row.

## Working notes

Split out of W-0126 rather than absorbed, on the team-lead's instruction: *"the import
one is different and I want it handled separately, not squeezed in — that is W-0121 in
a new place and the answer is not obvious."*

W-0126 is complete without this. The four routing sites are done and this is the only
holding write path still not reading the shared class.
