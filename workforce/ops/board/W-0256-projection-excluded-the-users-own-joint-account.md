---
id: W-0256
title: A spouse's investment projection silently excluded her share of the jointly owned account, so the card projected £85,000 under a capital figure of £132,500
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0024-cycle4-investment-projection.md
owner: build-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T20:00:00Z
claimed: 2026-08-22T20:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [HasJointOwnership, CalculatesOwnershipShare, F-0019, F-0022]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Found in the browser while verifying W-0251 on Sarah (17).

`getPortfolioProjections()` opened with `InvestmentAccount::where('user_id', $user->id)` —
only the accounts the user is the **primary owner** of. David is the primary owner of the
joint AJ Bell account; Sarah is the `joint_owner_id`, so her projection covered £85,000
while the capital figure printed directly above it on the same card read £132,500 — the
reach-complete number the dashboard and net worth have used since F-0022.

A user reading her own card saw £132,500 of capital become £103,229 in ten years at a
stated 5%, with no way to know that £47,500 of her money was not in the calculation.
**A projection that silently drops an asset does not look wrong. It looks pessimistic.**

This is F-0019's **reach** failure exactly, one module further down. The local
`getUserShareValue()` was also a second implementation of the share rule, and it gave the
co-owner the primary owner's percentage rather than the complement.

## Acceptance

1. The capital the projection uses equals the capital the card prints. ✓ (£132,500)
2. A joint account contributes the same share to both spouses' projections. ✓ (£55,257 each)
3. No new implementation of the ownership rule. ✓

## Working notes

- Routed to `HasJointOwnership::scopeForUserOrJoint()` (already on the model) and
  `CalculatesOwnershipShare::calculateUserShare()` (F-0002's home). The local share method
  is deleted, not edited — `CalculatesOwnershipShare` itself is untouched.
- Sarah's blended expected return moves 5.00% → 5.54% because the joint account is
  upper-medium; David is unchanged, being primary owner of all three of his.
- W-0217 acceptance 5 (spouse symmetry) now holds by construction.
