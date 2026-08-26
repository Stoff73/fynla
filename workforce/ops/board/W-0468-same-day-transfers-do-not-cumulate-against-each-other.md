---
id: W-0468
title: Two gifts made on the same day each get the full nil rate band, because cumulation requires a strictly earlier date
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: review
claimed_by: null
severity: low
surfaces: [web, m]
created: 2026-08-23T19:20:00Z
claimed: 2026-08-26
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

---

## Fixed 2026-08-26 — commit `b5cae49fb`, merged in PR #721

`cumulationBefore()` filtered on `$gap > 0`, strictly earlier. `gifts.gift_date` is a
DATE, so two same-day transfers have an identical `years` and a gap of exactly zero:
each was excluded from the other's cumulation and measured against the whole band.
Two £300,000 gifts against a £325,000 band produced **nil tax where £110,000 is due**.

**Same-day transfers now share one band in proportion to their values.** The
alternative this item's title suggests — cumulating them mutually — is not neutral:
each would be measured against a band the other had already consumed, turning
£275,000 chargeable into £550,000 and overstating by as much as the bug understated.

The eligibility predicate was extracted to `cumulates()` and is shared by both
callers rather than copied (Rule 20) — it carries the potentially-exempt-transfer
rules that invented £110,000 once already.

Applied to **both** bases, with the asymmetry pinned: the lifetime basis cumulates
immediately chargeable transfers only, so a same-day PET must not shrink the band a
CLT is measured against for its lifetime credit.

| Acceptance | State |
|---|---|
| 1. Same-day transfers cumulate or are apportioned per a stated rule | Done |
| 2. The rule stated in the docblock with its authority | Done, including what it does NOT claim |
| 3. Test with two same-day transfers exceeding the band | Done, plus an asymmetric 450/150 pair — at 50/50 proportional and a tie-break are indistinguishable |
| 4. `tax-compliance-reviewer` on which treatment is correct | **STILL OPEN** |

**Acceptance 4 is deliberately open.** The authority for the *split* is the
reviewer's. IHTA 1984 s124D(5) carries a same-day apportionment rule but for the
relief allowance rather than cumulation generally — evidence Parliament treats
same-day as a live case, **not** authority for this particular split. The total is
not in doubt; the split is. Said in the docblock so nobody reads more into the
citation than it supports.

*Closed late: the code merged in PR #721 while this item stayed `queued`. Recorded
2026-08-26 on noticing.*
