---
id: W-0454
title: The allowance message says "RNRB" to the user — Rule 9, on /plans/estate and in printed plans
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0032-cycle4-rate-literals-and-the-charitable-denominator.md
owner: null
reviewers: [design-lead, quality-lead]
status: queued
claimed_by: null
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
