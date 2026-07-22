---
fact_id: hv-additional-rate-avoidance
category: house_view
title: Additional-rate avoidance — pension contributions above the additional-rate threshold
version: 1
valid_to: null
---

## What this is, when it applies, who it is for

Income above the additional-rate threshold is taxed at the highest income tax
rate, and by that point the Personal Allowance has usually been tapered away
entirely. A personal pension contribution shifts the top slice of income out
of the additional-rate band, and a large enough contribution carries on down
through the taper band, reclaiming part of the lost allowance as it goes. The
strategy applies to anyone whose taxable income crosses the additional-rate
threshold in the current tax year and who still has pension annual allowance
to deploy; it is for high earners who want to save tax on the most expensive
slice of their income, and it is irrelevant to anyone below the threshold,
for whom the Personal Allowance taper rescue is the right strategy instead.

## Why Fynla quantifies it this way

Fynla builds the saving piecewise from the user's recorded income and the
band rates held in the live tax configuration. The slice of the contribution
that covers income above the additional-rate threshold is relieved at the
additional rate; the slice that continues into the taper band attracts the
higher effective rate produced by Personal Allowance restoration; any
remainder below the taper threshold is relieved at the plain higher rate. The
whole contribution is capped by the user's available annual allowance, so the
recommendation never suggests more than the relief rules can absorb. Fynla
quotes the blended result from the user's own numbers rather than a single
headline rate, because the saving genuinely differs slice by slice.

## Where it sits in sequence

This strategy leads the plan for additional-rate taxpayers for the same
reason the taper rescue leads it for those a band below: relief at the top
rates beats the long-run benefit of wrapping the same money in an ISA. It
is capped by what remains of the current year's pension annual allowance
after this year's contributions; unused allowance from earlier years is
quantified separately by the carry-forward strategy, with its own headroom
figure, and the two appear side by side in the plan rather than feeding one
another. The tapered annual allowance warning surfaces alongside them —
additional-rate earners are exactly the population whose true allowance is
most likely to be smaller than the headline.

## Claim tier and voicing

Mechanical tier: the piecewise arithmetic follows from recorded income,
published thresholds, and published rates, so Fyn states the working directly
— which slice is relieved at which rate and what the contribution adds up to.
Voicing quotes the user's own numbers and names each band rather than
collapsing the calculation into a single quoted percentage.
