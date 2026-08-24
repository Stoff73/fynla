---
id: W-0468
title: Two gifts made on the same day each get the full nil rate band, because cumulation requires a strictly earlier date
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
claimed_by: null
severity: low
surfaces: [web, m]
created: 2026-08-23T19:20:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0463]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: tax-compliance-reviewer finding R7, re-review of a1d36b90b, 2026-08-23
---

## Intent

`FailedGiftTaxCalculator::cumulationBefore()` selects transfers with `gap > 0` — strictly
earlier than the subject. Two transfers dated the same day therefore cumulate against
neither, and **each is measured against the full nil rate band**. Understates tax.

`gifts.gift_date` is a DATE, so same-day transfers are indistinguishable by time and
there is no ordering to fall back on.

**Not hypothetical:** IHTA 1984 **s124D(5)** carries an explicit same-day apportionment
rule for the relief allowance, which is Parliament confirming same-day transfers are a
live case needing a stated answer.

## Acceptance

1. Same-day chargeable transfers cumulate against each other, or are apportioned, per a
   stated rule — not left to the accident of a strict inequality.
2. The rule is stated in the docblock with its authority, since it cannot be inferred
   from the data.
3. A test with two same-day transfers whose combined value exceeds the band.
4. `tax-compliance-reviewer` on which treatment is correct — apportionment as in
   s124D(5), or simple mutual cumulation.

## Working notes

- 2026-08-23 — Low severity because the shape is uncommon, not because the arithmetic is
  small: two same-day £300,000 gifts currently attract nil tax where £275,000 is
  chargeable. No persona holds same-day gifts.
