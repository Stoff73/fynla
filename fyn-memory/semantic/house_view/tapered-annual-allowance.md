---
fact_id: hv-tapered-annual-allowance
category: house_view
title: Tapered Annual Allowance — the pension allowance warning for very high earners
version: 1
valid_to: null
---

## What this is, when it applies, who it is for

For very high earners, HM Revenue and Customs reduces the pension Annual
Allowance by one pound for every two pounds of adjusted income above the
adjusted-income threshold, down to a fixed floor. The taper only bites when
two separate gates are both breached: threshold income — broadly all taxable
income with no pension addback — must exceed its gate, and adjusted income —
threshold income with employer pension contributions added back — must exceed
its own higher gate. Either gate alone is not enough; someone with heavy
employer contributions on a modest salary is not tapered. This is a warning
rather than a saving opportunity: it exists to stop a tapered user
contributing the standard allowance and triggering an Annual Allowance charge.
It is for the highest earners, particularly those with large employer pension
contributions.

## Why Fynla quantifies it this way

Fynla computes threshold income from the user's recorded income and tests it
first: anyone at or below that gate is not tapered, whatever their employer
contributes, so the check goes no further. Only when threshold income clears
its gate does Fynla add back recorded employer pension contributions to
reach adjusted income and test the second gate against the thresholds held
in the live tax configuration — because the dual gate is the actual rule,
not a simplification. It is the excess of adjusted income over its gate that
sets the taper: the standard allowance is reduced at the taper rate on that
excess, floored at the published minimum. The figure
Fynla attaches is the avoided charge: the tax that contributing the full
standard allowance would trigger at the user's marginal rate. Fynla presents
it that way because the value of the warning is the mistake it prevents.

## Where it sits in sequence

On the strategy dashboard this warning renders ahead of every saving
strategy, because it constrains all of them. The taper rescue, additional-rate
avoidance, and carry-forward strategies each depend on annual allowance
headroom, and the taper is what shrinks that headroom — so the user needs to
know their true allowance before acting on any pension recommendation. It
conflicts with nothing; it bounds everything.

## Claim tier and voicing

Mechanical tier: both gates, the taper rate, and the floor follow from
recorded income and published thresholds, so Fyn states the working directly
— which gates are breached, what the tapered allowance comes to, and what
charge contributing beyond it would trigger. Voicing is firm but
non-alarmist: the user has not done anything wrong, they simply have a
smaller allowance than the headline figure suggests, and every pension
suggestion Fynla makes must fit inside it.
