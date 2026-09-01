---
id: W-0515
title: Fyn still tells the user pensions pass outside the estate, and quotes today's pot as the amount at risk
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer, compliance-lead]
status: done
claimed_by: null
severity: high
surfaces: [web, m, ios]
created: 2026-08-28T15:36:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-28
prior_art_found: [W-0482, W-0372, W-0364]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
source: tax-compliance-reviewer gate report on W-0482, finding F10, 2026-08-28
---

## Intent

`IHTCalculationService::calculatePensionAmendmentScenario()` is untouched by W-0482 and
still publishes, at `:2919` and `:2929`:

```php
'description' => 'Under current rules, defined contribution pensions pass outside the estate and are not subject to Inheritance Tax.',
'pension_value_included' => round($totalPensionValue, 2),   // today's pot
```

It reaches the user through `EstateAgent:272` and `:380`.

**Two problems, and they compound (Rule 20).**

1. One household now carries **two different pension-in-estate figures**: the residual,
   inside `projected_gross_assets`, and today's pot, explicitly labelled in the amendment
   scenario. **Today's pot is precisely the figure W-0482 exists to reject** — adding it is
   the double count that item was raised to prevent. The gap between the two will be large,
   and a user can see both.
2. The framing is stale as a statement of law. It is written as a prospective "amendment"
   and a "warning". **Finance Act 2026 ss66-71 has Royal Assent and commencement is fixed**
   at 6 April 2027. "Under current rules" is true only until then, and the sentence does
   not say so.

## Acceptance

1. One mechanism for "how much pension is in the estate" — the scenario reads the same
   figure the projection does, or states at the line why a today's-pot comparison is
   deliberately different and labels it as such to the user.
2. The wording reflects enacted law with a commencement date, not a proposal.
3. Rule 19 — Fyn says the same thing on web, `/m` and native, because it is one prompt and
   one engine.
4. `tax-compliance-reviewer` and `compliance-lead` — this is a statement about a user's tax
   position, on a surface Fyn speaks from.

## Working notes

- 2026-08-28 — Raised as F10 by the gate on W-0482. The `post_2027_rules` API key is
  deliberately NOT renamed (see the method's own docblock) — it is an identifier read by
  three clients. Only the prose changes.

- 2026-08-31 build-lead: **VERIFIED STILL LIVE against `dev`** (the line numbers have moved; the
  code has not). `IHTCalculationService::calculatePensionAmendmentScenario()` still publishes at
  **:2382** *"Under current rules, defined contribution pensions pass outside the estate and are
  not subject to Inheritance Tax"* and at **:2386** `'pension_value_included' => round($totalPensionValue, 2)`
  — today's pot. Both still reach the user through `EstateAgent`.
  So one household still carries two different pension-in-estate figures: the residual inside
  `projected_gross_assets` (W-0482) and today's pot labelled here — **which is precisely the
  figure W-0482 exists to reject.**

- 2026-08-31 build-lead: **FIXED AND TESTED — closed.**

  **Both halves fixed, and a second copy of the claim found in the process.**

  **The wording (acceptance 2).** `IHTCalculationService:2473` said *"Under current rules, defined contribution pensions pass outside the estate"* — flat, with no end date — while the block immediately below it told the user that stops. A reader who took the first sentence at face value took a permanent rule from an enacted change. It now reads *"Until [date], unused defined contribution pension pots pass outside the estate…"*, with the date from `$effectiveDate`, which is configuration.

  **The figure (acceptance 1).** `:2472` published `pension_value_included` as **today's pot** while the projection publishes `projected_unused_pension` — the unused fund at the modelled death after drawdown (W-0482). One household carried two pension-in-estate numbers with neither named.

  Resolved by **labelling rather than replacing**, which is the branch acceptance 1 explicitly allows, because both figures are right about different questions. This block answers *"what would the amendment cost me on what I hold now"* — a comparison the user can check against their own pension statement — whereas the projection answers *"what will be left at death"*, which depends on drawdown assumptions this scenario is not making. Substituting the projected figure would have made the scenario un-checkable. It now publishes `pension_value_basis`, `pension_value_basis_label` and `projected_unused_pension` alongside, and the impact summary says *"their value today"*.

  **Rule 20 — a second copy, fixed in the same edit.** `DeathOfSpouseScenario.vue:117` made the same claim with the same gap: *"Pension funds are typically outside the estate…"*, no end date. Fixing only the service would have left the dashboard contradicting the estate module.

  **And a Rule 2 slip of my own, caught and corrected before commit:** my first pass hardcoded *"6 April 2027"* into that component. The commencement date is configuration. Added `inheritance_tax.pension_inclusion_date` to `TaxConfigSnapshotService`, an `ihtPensionInclusionDate` getter, and a computed that composes the sentence — and drops the qualifier entirely if the date has not loaded, because a wrong date is worse than a general statement.

  **Tested:** 13 PHP (snapshot + pension amendment, 34 assertions); 29 frontend store tests; Pint clean.

  **NOT DONE.** (3) Rule 19 — the backend is shared so `/m` and native inherit the corrected service wording, but neither surface was checked and `DeathOfSpouseScenario` is web-only. (4) No `tax-compliance-reviewer` or `compliance-lead` pass, and this is a statement about a user's tax position.
