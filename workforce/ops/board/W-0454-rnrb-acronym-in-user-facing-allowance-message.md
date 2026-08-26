---
id: W-0454
title: The allowance message says "RNRB" to the user — Rule 9, on /plans/estate and in printed plans
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0032-cycle4-rate-literals-and-the-charitable-denominator.md
owner: null
reviewers: [design-lead, quality-lead]
status: done
claimed_by: build-lead
severity: low
surfaces: [web]
created: 2026-08-23T04:05:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0431, W-0432]
prior_art_outcome: extend
---

## Intent

Read verbatim off `/plans/estate` as Sarah Jones (17), 2026-08-23:

> "Residence Nil Rate Band at age 84: Residence Nil Rate Band fully tapered away.
> Your estate of £11,294,811 exceeds the taper threshold of £2,000,000 by
> £9,294,811, **eliminating all RNRB of £350,000**."

**Rule 9 permits only ISA.** The same sentence spells the term out twice and then
abbreviates it, so it is not a vocabulary gap — it is one unconverted instance in
an otherwise-correct message.

**This reaches printed plans**, like the statement of law fixed in F-0032.

## Why it was not fixed here

Outside what the tax-compliance gate cleared for F-0032, which covered the
charitable-figures and rate-literal hunks. **Reporting rather than touching** — a
Rule 9 sweep of the allowance messages is its own small piece of work and should
be done as one, not one string at a time by whoever happens to be looking.

## Acceptance

- [ ] "RNRB" spelled out wherever it reaches a user.
- [ ] The allowance messages swept as a set — `nrb_message`, `rnrb_message` and
      their projected variants — rather than the one instance that was noticed.
- [ ] `/m` checked (Rule 19).

---

## Verified already satisfied — 2026-08-26

Claimed and checked rather than fixed, because nothing here needed changing. All
three acceptance criteria hold on `dev` today.

**The quoted instance was fixed the same day this item was raised.** The string now
reads `', eliminating all £'.number_format($rnrbGross).' of it.'` —
`IHTCalculationService.php:1869`, no acronym. `git log -S"eliminating all RNRB"`
puts its removal in **`19bd1c83f`** (Stoff73, 2026-08-23, "re-review findings
R1-R5"), which landed after this item was written at 04:05 that morning. Incidental
to that work, not a response to this item.

**The set is swept, which is what this item actually asked for.** Every variant
spells the term out:

| Message | State |
|---|---|
| `rnrb_message` — no residence / no descendants (`:1741`, `:1754`) | "Residence Nil Rate Band", twice each |
| `rnrb_message` — tapered / residence-capped (`:1867-1869`) | spelled out |
| `rnrb_message` — assembled sentences (`:1875-1899`) | spelled out in every branch |
| `nrb_message` (`buildNrbMessage`) | "Nil Rate Band", "Chargeable Lifetime Transfers", "Inheritance Tax" |
| `projected_rnrb_message` | the same builder, so the same strings |

**`/m` checked (acceptance 3).** `grep` for `RNRB` and `\bNRB\b` across
`resources/mobile/` returns nothing — the pathway carries no literal of its own and
renders the API strings, which are the ones above. The web labels around them
(`IHTPlanning.vue:411,415`) read "Tax-Free Allowance" and "Home Allowance".

## What the sweep did turn up — raised as W-0497

Not fixed here, for this item's own stated reason: *"a Rule 9 sweep ... is its own
small piece of work and should be done as one, not one string at a time by whoever
happens to be looking."* That applies to what was found as much as to what was
reported.

**44 user-facing strings** across `PersonalizedTrustStrategyService` (32),
`EstateOnboardingFlow` (8) and `PersonalizedGiftingStrategyService` (4) meet the
user cold with **RNRB, NRB, IHT, PET, CLT and GROB**. Fixing only the RNRB
instances would have left the same sentence reading *"Immediate Discretionary Trust
(CLT)"* and *"No immediate Inheritance Tax charge (within NRB of £325,000)"* — the
exact half-done state this item exists to prevent.
