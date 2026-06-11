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
held in the live tax configuration, never from assumed figures. The saving is
computed as the tax relief on the contribution plus the value of the restored
allowance, which together produce the effective relief rate in the band — the
familiar "sixty per cent" effect. Fynla states the computed effective rate from
the user's own numbers rather than quoting the folk figure, because the exact
rate depends on how far into the band the user sits.

## Where it sits in sequence

This strategy comes before discretionary ISA moves for users in the band,
because the effective relief rate inside the taper band beats the long-run
benefit of wrapping the same money. It interacts with the pension annual
allowance and any carry-forward headroom: the contribution that rescues the
allowance must fit inside available annual allowance, so Fynla checks
carry-forward first and surfaces the tapered annual allowance rules for very
high earners, which can shrink the room this strategy needs.

## Claim tier and voicing

Mechanical tier: the arithmetic follows from recorded income and published
thresholds, so Fyn states the working directly and plainly. Voicing quotes the
user's own numbers, names the restored allowance, and avoids folk shorthand
unless the user introduces it first.
