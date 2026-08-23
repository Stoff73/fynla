---
id: W-0336
title: Projected liabilities are taken at 100% for each member while the headline applies the share, so a third-party-shared debt understates tax
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: handoff
severity: low
surfaces: [web, m, ios]
created: 2026-08-22T23:25:00Z
claimed: 2026-08-23T00:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: [W-0331, W-0333, EstateAssetAggregatorService::calculateUserLiabilities, CalculatesOwnershipShare::calculateUserMortgageShare]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

The third member of the W-0331 / W-0333 family. `projectLiabilities():843-895`
iterates `$user->mortgages` and `$user->liabilities` — plain `user_id` relations —
at 100% for each pooled member, while the headline runs the same records through
`calculateUserShare` / `calculateUserMortgageShare`.

Unlike its two siblings this one **understates** tax: an over-counted liability
reduces the projected estate.

**£0 on this persona today** — the household has zero `liabilities` rows and three
mortgages, all David-primary, so 100%-of-primary and share-summed-across-members
give the same number. It bites on a debt shared with someone outside the household.

Noted per `tests/CLAUDE.md` §4: *a persona-derived fixture inherits the persona's
blind spots*, and `peak_earners` holds no liabilities at all.

## Acceptance

1. The projection's liability base equals the headline's, to the penny.
2. A test whose fixture contains a non-mortgage liability AND a third-party-shared
   debt — this persona produces neither.

## Working notes — build-lead, 2026-08-23

**DONE.** Team-lead folded it into the W-0333 batch: fixing property alone would have
corrected the estate on the asset side and left it wrong on the debt side.

Four loops (two per member) collapsed into one `projectMemberLiabilities()`, reading
joint-aware collections at the member's own share.

**The tax-compliance review caught a regression in the first version of this fix,
and it is the reason the reader is the two-leg one.** A mortgage is REACHED by the
mortgage row's own `user_id`/`joint_owner_id` but its share resolves from the
SECURING PROPERTY (W-0228). When those disagree — a home owned 50/50 with a mortgage
row naming one spouse only — that spouse gets 50% and the other sees nothing, so half
the debt is deducted by nobody and the estate comes out too big. The OLD code took
the row at 100% and accidentally recovered the whole debt for that shape, so applying
the share without widening the reach would have been a regression introduced by the
fix. `CrossModuleAssetAggregator::getMortgages()` exists for exactly this case.
**The headline still reads the one-leg version — W-0338.**

**Tax grounds confirmed:** IHTA 1984 s5(3) with s162(1) — joint borrowers are jointly
and severally liable but hold a right of contribution, so only the deceased's
proportion is deductible (IHTM28030).

£0 on this persona, as predicted: no liability rows, three mortgages all naming both
spouses, all maturing inside the horizon. Fixtures carry a debt running past the
horizon and a mortgage row naming one spouse on a jointly-owned home — neither shape
the persona can produce.
