---
id: W-0515
title: Fyn still tells the user pensions pass outside the estate, and quotes today's pot as the amount at risk
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer, compliance-lead]
status: queued
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
