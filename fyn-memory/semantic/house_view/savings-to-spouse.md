---
fact_id: hv-savings-to-spouse
category: house_view
title: Savings to spouse — gifting cash to use the lower earner's stacked allowances
version: 2
valid_to: null
---

## What this is, when it applies, who it is for

In a single-earner couple, interest on cash in the earning partner's name may
be taxed once that person's tax-free savings amounts are used. A non-earning
spouse may be able to combine their Personal Allowance, Starting Rate for
Savings and Personal Savings Allowance. The campaign calculates this only
when it has explicitly recorded that the spouse has no earnings and no
savings; otherwise the remaining capacity is unknown. The cash becomes the
spouse's property. Transfers between eligible spouses or civil partners
usually create no immediate Capital Gains Tax charge and may qualify for the
Inheritance Tax spouse exemption, subject to the relevant conditions.

## Why Fynla quantifies it this way

Fynla builds the spouse's possible tax-free interest capacity from the live
configuration. It calculates the user's annual interest account by account,
so the blended rate is weighted by balance rather than a simple average. The
transfer is capped by both the cash actually held and the spouse's capacity.
The tax-saving estimate counts only interest currently above the user's own
Personal Savings Allowance and any available Starting Rate for Savings; it
does not claim a saving on interest already tax-free. Exact spouse headroom
must be reconfirmed if any unrecorded income or savings exist.

## Where it sits in sequence

Wrapping the user's own cash in an ISA and gifting that same cash to a spouse
draw on the same pot and must not be counted twice. An outright gift also
conflicts with placing the same money in a joint account. Money already gifted
to the spouse could later be subscribed to the spouse's ISA only within their
confirmed allowance.

## Claim tier and voicing

Judgement tier because ownership changes and unrecorded spouse income would
change the result. Where zero spouse income and savings have been explicitly
captured, Fyn may state the account arithmetic and allowance working while
describing it as conditional on those facts remaining complete.
