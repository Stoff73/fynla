---
id: W-0341
title: A spouse's estate cover figure reads £0 while a joint-life policy insures her for £500,000
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0027-cycle4-life-cover-reach.md
owner: build-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T23:20:00Z
claimed: 2026-08-22T23:20:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0186, W-0272, W-0278, W-0280]
prior_art_outcome: route
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

Raised in the cycle 4 fix queue against `EstateAssetAggregatorService::getExistingLifeCover():277`.

### The defect

Policy 7 belongs to David Jones (16): `joint_life = true`, `in_trust = true`, sum
assured **£500,000**. `getExistingLifeCover(Sarah, 17)` returned **£0** while
`LifeCoverReach` — the reader built for exactly this question in W-0186 — correctly
reported her covered for £500,000.

**A joint-life policy is the one product whose purpose is covering both of them**, and
the figure the estate module carried for her said she had none.

The query was `where('user_id', $user->id)`. `life_insurance_policies` has no
`joint_owner_id`, so the second life assured is reachable only through
`users.spouse_id` — which is what `LifeCoverReach` already does. **Route to it; do not
build a second reader.**

### The other half — W-0186's criterion 8 had it backwards

W-0186 asserted this £0 *as correct*, on the reasoning that the same policy in both
estates would be a double count. **Measured: `gatherUserAssets()` never reads
`LifeInsurancePolicy` at all** — the class appears in that service only inside
`getExistingLifeCover()` itself, and the persona's estate asset types are
`investment, property, cash, chattel, dc_pension` (David) and
`investment, property, cash, chattel, db_pension` (Sarah). The double count that
criterion guarded against was not reachable by that method. See `F-0027` §3.

## Acceptance

1. `getExistingLifeCover(Sarah)` returns **£500,000**. — DONE, measured.
2. David is unchanged at **£700,000** (£500,000 life + £200,000 critical illness). — DONE, measured.
3. A household total counts the policy **once**, proven on an asymmetric fixture where
   the correct answer and the one-sided answer are different numbers. — DONE.
4. Critical illness stays with the life it was written on — `critical_illness_policies`
   has no `joint_life` column. — DONE, checked against the schema.
5. Sarah's estate plan stops recommending on the premise that she has none. — **DONE via
   W-0342**, after team-lead granted `app/Agents/EstateAgent.php`. Measured: the gap
   *"Estate exceeds the Nil Rate Band but no life cover is recorded"* fired on her
   £861,780 estate before, and does not fire after; `policy_assessment` went from 0
   entries to 5; the itemised list the LLM reads went from `[]` to the £500,000 policy.
   **This item alone could not have achieved it** — `getExistingLifeCover()` has zero
   production callers.
6. Browser-verified on `localhost:8000` for both accounts through the MFA gate.
   — **PENDING**, browser queued behind two other agents.

## Working notes

(append-only)

- 2026-08-22 — Repo-wide grep: the only caller of `getExistingLifeCover()` is the W-0186
  test. `IHTController:211` declares a same-named `private` method that nothing calls
  (W-0343). Fixed regardless — it is public API returning a wrong number.
- 2026-08-22 — Fixed, mutation-tested in both directions (5 mutations, `F-0027` §5).
- 2026-08-23 — **Why W-0186 asserted the opposite, recorded so the rewrite stays
  legible** (team-lead's instruction). Criterion 8 asserted `getExistingLifeCover(Sarah)
  === 0` under the heading "no double count": its author reasoned that a joint-life
  policy reaching both accounts' *protection* analysis is correct, while the same policy
  in both *estates* would be counted twice — and drew the line at this method because its
  name and its home in an estate service both say "estate". **The reasoning was right;
  the line was drawn in the wrong place.** `gatherUserAssets()` never reads
  `LifeInsurancePolicy` at all, so no life policy has ever entered an estate from either
  account, and the £0 was not preventing a double count — it was answering a per-life
  question with a per-owner query. The real double count lives in the household total,
  which is a different method and is now pinned by its own asymmetric test.
- 2026-08-23 — 167 + 362 tests green across the protection, agent, estate and mobile
  families after the `EstateAgent` change.
- 2026-08-23 — **Browser-verified, `localhost:8000`, both accounts through the MFA gate.**
  Sarah's web `/protection` reads **Total Life Insurance £500,000** and **Debt Protection:
  none, shortfall £0**; `/api/plans/estate` and `/api/v1/mobile/modules/estate` both carry
  the policy with **no phantom gap**, `total_cover_in_trust` **500000**, `policy_count`
  **1**. David unchanged at **£700,000**. Identity established from `GET /api/auth/user`,
  not from `fynla-state` (W-0385). Moving to `handoff`.
