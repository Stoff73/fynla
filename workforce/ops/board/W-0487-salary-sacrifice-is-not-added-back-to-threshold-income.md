---
id: W-0487
title: A salary-sacrificing high earner keeps a full Annual Allowance they are not entitled to — the sacrifice is never added back to threshold income
mission: M-0002-persona-fidelity
owner: null
status: queued
severity: high
surfaces: [web, m, ios]
created: 2026-08-25T16:10:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: null
prior_art_checked: 2026-08-25
prior_art_found: [W-0189, W-0205 (raised as an unverified suspicion there; measured here)]
prior_art_outcome: none
source: reported as a suspicion by Brett under W-0205, ruled a real defect by tax-compliance-reviewer, 2026-08-25
---

## Intent

**FA 2004 s228ZA(5) adds a post-8-July-2015 salary sacrifice BACK to threshold income**,
specifically to stop sacrifice being used to duck the tapered Annual Allowance.
`IncomeDefinitionsService` never adds it back.

The service already **knows** which arrangement applies —
`getPensionContributions()` publishes `arrangement: 'salary_sacrifice'` when a DC
pension carries the flag. The information is present; the arithmetic does not branch
on it.

## Impact — measured through `AnnualAllowanceChecker`, not argued

A sacrificing earner is told their Annual Allowance is **£60,000** where s228ZA gives
**£56,750**. The runs are **byte-identical with the sacrifice flag on and off**, which
is the proof the branch does not exist rather than an inference that it might not.

The consequence is the wrong direction for the user: an overstated Annual Allowance
invites a pension contribution that triggers an unexpected annual allowance charge.

## A defence that is narrower than it looks

The standing argument for not attempting this is that the app cannot know whether a
stated salary is pre- or post-sacrifice, so adding the sacrifice back might
double-count.

**In the measured fixture both readings clear the £200,000 threshold**, so the taper
could be applied without needing to resolve the ambiguity at all. The defence holds for
the band immediately around the threshold; it does not hold generally, and it is
currently being applied generally.

## Acceptance

1. Threshold income adds back a post-8-July-2015 salary sacrifice per FA 2004
   s228ZA(5), for pensions the service already flags as `salary_sacrifice`.
2. **Where both readings of a stated salary fall the same side of the threshold, the
   taper is applied** — the ambiguity defence is used only where it actually bites.
3. Where the ambiguity does bite, the surface says what it assumed rather than choosing
   silently (`database/CLAUDE.md`: a default is a convenience for the schema, not a
   statement by the user).
4. Pinned by a fixture with the sacrifice flag on and off producing **different**
   allowances — the current runs are byte-identical, which is what let this survive.
5. `AnnualAllowanceChecker` re-measured at the two figures above.
6. Statutory gate: `tax-compliance-reviewer`.

## Working notes

- 2026-08-25: raised as an explicitly unverified suspicion in W-0205's working note
  ("I have not verified this against a fixture and am not claiming it as a defect; it
  needs a tax-compliance eye"). The reviewer measured it and ruled it real, high.
- Same reviewer nearly filed a **false** defect adjacent to this one — reasoning from
  policy that net-pay contributions must be added back to threshold income too. They
  are added back to **adjusted** income under s228ZA(4), not threshold. Checked against
  legislation.gov.uk and PTM057100, which agree against that reading. **Recorded so
  nobody re-derives the wrong version.**
