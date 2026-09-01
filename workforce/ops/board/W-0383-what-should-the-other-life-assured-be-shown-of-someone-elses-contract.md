---
id: W-0383
title: Product call — how much of someone else's contract should the other life assured see
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0027-cycle4-life-cover-reach.md
owner: build-lead
status: done
severity: medium
surfaces: [web, m, ios]
created: 2026-08-23T00:40:00Z
claimed: 2026-08-23T00:40:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0186, W-0344]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
---

## Intent

W-0186 made a joint-life policy reach the other life assured. **Nobody asked what she
should be able to READ once it does** — the whole resource simply shipped.

`LifeInsurancePolicyResource:28-48` sends the non-owner `policy_number`,
`premium_amount`, `premium_frequency`, free-text `beneficiaries`, start and end dates,
term, indexation rate, start value and decreasing rate — the entire contract.

**Two of those are the sharp ones**, and both are now withheld from the non-owner on
team-lead's direction:

- **`policy_number`** — effectively a credential for phoning the insurer. It is not
  needed to know you are covered.
- **`beneficiaries`** — free text, commonly the couple's children's names.

Nulled rather than omitted so the key shape stays constant on every surface: `/m` renders
`policy_number || '—'` (`ProtectionPolicy.vue:34`) and hides the beneficiaries block on
falsy (`:126`); both native fields are `String?` (`ProtectionModels.swift:205,220`), so a
null decodes cleanly and does not break the policy list.

## Acceptance — the open question for CSJ

1. **What SHOULD the second life assured see?** Delivered against team-lead's answer:
   insurer, sum assured, policy type, in-trust status, that they are covered, and who the
   other life is.
2. **Still shipping to the non-owner and NOT ruled on: `premium_amount`,
   `premium_frequency`, the policy dates, `policy_term_years`, `indexation_rate`,
   `start_value`, `decreasing_rate`.** Premium is the arguable one — it is what the other
   person pays for a policy that covers them both, so there is a reasonable case either
   way. **Left as-is deliberately rather than guessed at** (Rule 16).
3. If premium is withheld too, `/m`'s `ProtectionPolicy.vue` and the native detail view
   need checking for a layout that assumes it is present.
- 2026-08-23 — **Browser-verified from both sides in one session.** David's `/m` policy
  detail shows `VIT-LT-456789` and beneficiaries *"Sarah Jones: 34%, William Jones: 33%,
  Charlotte Jones: 33%"* — **the free-text field naming the couple's two children, which is
  exactly the payload this rule exists for.** Sarah's `/api/protection` returns
  `policy_number: null`, `beneficiaries: null`, `sum_assured: 500000`,
  `is_own_policy: false`. Moving to `handoff`; the open half (premium, dates, rates) stays
  for CSJ.

## 2026-09-01 — CLOSED. CSJ ruled: all of it.

CSJ's ruling, on the board: *"CSJ says all, if there is a shared account, show the
life policy to the other user."* That answers acceptance 1 and 2 together, and in the
opposite direction to team-lead's interim line.

**What changed.** `app/Http/Resources/Protection/LifeInsurancePolicyResource.php` no
longer nulls `policy_number` or `beneficiaries` for the other life assured. The reason
the line was drawn is kept at the code — a policy number is effectively a credential,
and `beneficiaries` is free text commonly naming the couple's children — so the next
reader sees that it was overruled rather than never considered. Acceptance 2's open
list (premium, dates, term, indexation, start value, decreasing rate) was already
shipping and stays shipping; "all" closes that question rather than leaving it.

**Read is now total; write is unchanged.** `is_own_policy` still separates them, and
every surface gate that reads it — `PolicyDetail.vue:360`, `PolicyCard.vue:132`,
`/m` `ProtectionPolicy.vue:224` — controls an **edit affordance**, not a field. So no
surface hides the newly-visible fields and none needed changing. Acceptance 3 is
answered by inspection: `/m` rendered `policy_number || '—'` and hid the beneficiaries
block on falsy, both of which simply populate now; the native fields are `String?`.

**The test that encoded the old answer** —
`tests/Feature/Protection/LifeCoverReachSpouseLinkStatesTest.php:233` asserted the two
fields were null for the non-owner. Inverted rather than deleted, and widened: it now
pins that read reaches everything **and** that `is_own_policy` is still false, because
those two are exactly what a careless "show her the policy" would conflate. The
premium note at `:195` recorded a question W-0383 had left open; it now records that
CSJ closed it.

Tests: **95 passed** across `tests/Feature/Protection/` and the protection gap
presentation test.

**Not done:** no browser drive from the spouse's account.
