---
fact_id: hv-pa-taper-rescue
category: house_view
title: Personal Allowance taper rescue — pension contributions in the taper band
version: 1
valid_to: null
---

## What this is, when it applies, who it is for

When someone's adjusted net income sits inside the Personal Allowance taper
band, every extra pound of income costs more than the headline higher rate,
because the allowance is withdrawn at a rate of one pound for every two pounds
of income above the taper threshold. A personal pension contribution reduces
adjusted net income, so contributing enough to bring income back to the
threshold restores the allowance in full. It applies to anyone whose relevant
earnings put them inside the taper band in the current tax year; it is most
valuable to employees who can also use salary sacrifice, and it is irrelevant
below the threshold or once the allowance is fully gone and other reliefs
dominate.

## Why Fynla quantifies it this way

Fynla works from the user's recorded income and the current year's thresholds
held in the live tax configuration, never from assumed figures. The
contribution is sized to the slice of income inside the band, capped by the
year's remaining pension annual allowance, and the saving applies the
standard sixty per cent effective-relief treatment to that whole slice —
higher-rate relief plus the allowance restored at one pound for every two.
The rate is a property of the band itself, not of where in the band the user
sits, so Fynla quotes the user's own contribution and the saving computed
from it rather than leaving the effect as a folk figure.

## Where it sits in sequence

This strategy comes before discretionary ISA moves for users in the band,
because the effective relief rate inside the taper band beats the long-run
benefit of wrapping the same money. The contribution must fit inside what
remains of the current year's pension annual allowance after this year's
contributions — that is the cap Fynla applies when sizing it. Unused
allowance from earlier years is computed separately by the carry-forward
strategy, with its own headroom figure, and the two are presented side by
side rather than pipelined; the tapered annual allowance warning surfaces
for very high earners, whose true allowance can be smaller than the headline.

## Claim tier and voicing

Mechanical tier: the arithmetic follows from recorded income and published
thresholds, so Fyn states the working directly and plainly. Voicing quotes the
user's own numbers, names the restored allowance, and avoids folk shorthand
unless the user introduces it first.
