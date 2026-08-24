---
id: W-0135
title: Two estate screens give different projected Inheritance Tax for the same user at the same moment, and the drill-down's figure reconciles to nothing on its own page
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0015-cycle1-estate-tax-figures.md
owner: build-lead
status: gated
severity: high
surfaces: [web, m, ios]
created: 2026-08-21T20:15:00Z
claimed: 2026-08-21T19:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: [W-0154, W-0136]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, local, as **David Jones `users.id 16`** and
**Sarah Jones `users.id 17`**.

**Surfaces:** `/estate/inheritance-tax` and `/plans/estate` — both render
`IHTCalculationTable.vue`, `/plans/estate` via `EstateCurrentSituation.vue`.

### Expected

One user, one moment, one projected estate and one projected tax. Two screens rendering
the same component from the same calculation must not differ.

### Actual

Read within two minutes of each other, no data touched between:

| Figure (age 84 column) | `/estate/inheritance-tax` | `/plans/estate` | Difference |
|---|---|---|---|
| David — net estate | £2,343,680 | £2,343,680 | — |
| David — **taxable estate** | **£1,474,691** | **£1,493,680** | £18,989 |
| David — **Inheritance Tax** | **£589,877** | **£597,472** | **£7,595** |
| Sarah — net estate | £2,431,937 | £2,431,937 | — |
| Sarah — **taxable estate** | **£1,412,234** | **£1,431,937** | £19,703 |
| Sarah — **Inheritance Tax** | **£564,893** | **£572,775** | **£7,882** |

**`/plans/estate` reconciles to what it prints.** David: 2,343,680 − 850,000 =
1,493,680, × 0.40 = 597,472. Sarah: 2,431,937 − 1,000,000 = 1,431,937, × 0.40 =
572,775. Both exact.

**`/estate/inheritance-tax` does not.** Its £1,474,691 is £18,989 below the figure its
own two rows above produce. Subtracting the £10,000 charitable legacy still leaves
**£8,989 unaccounted**; Sarah's leaves £9,703. No combination of the values printed on
that page yields the number it prints.

The current-year column agrees on both accounts (£374,280 / £149,712 and £224,280 /
£89,712), so the divergence is confined to the projection.

### Impact

The two screens exist precisely so a user can move from summary to detail. Moving
between them changes their projected tax bill by £7,595 with no explanation, and the
more detailed of the two is the one whose figure cannot be checked. Every downstream
recommendation on `/plans/estate` — life cover sizing, gifting, the charitable strategy —
is quantified against a number the drill-down contradicts.

### Repro

1. `sarah.jones@example.com` or `david.jones@example.com`, premium, married.
2. `/estate/inheritance-tax`, wait ~12s, read the age-84 Taxable Estate and Inheritance
   Tax Liability rows.
3. `/plans/estate`, wait ~15s, read the same two rows in "Current Situation".
4. They differ. Neither page was reloaded in between and no data was changed.

### Acceptance

1. Both surfaces show the same projected taxable estate and the same projected tax for
   the same user, because they read one calculation (Rule 20).
2. The projected taxable estate is derivable from the projected net estate and the
   allowances shown on the same page — and if a further adjustment applies, it appears as
   its own row (see W-0134).
3. Fixed together with **W-0136**, since the taper is one of the adjustments that ought
   to be moving these figures and currently moves neither.
4. Verified in a browser on both persona accounts.

## Working notes
(append-only)


- 2026-08-21 cycle1-estate (build-lead): **FIXED, handed to quality-lead. Branch
  document `F-0015`. Not browser-verified — Quality's loop.**

  **Root cause, and it is a clean Rule 20 case.** `EstatePlanService` **recomputed**
  the projected taxable estate and tax itself — projected net estate minus the CURRENT
  allowances at the CURRENT rate, **with the charitable exemption omitted entirely** —
  while `IHTController` has always let the service's own values stand
  (`IHTController.php:95`). One question, two mechanisms, two answers. The recompute is
  deleted; the values pass through untouched on both surfaces.

  **A second divergence in the same method.** `EstatePlanService` also composed its own
  allowance messages: `/plans/estate` said *"Individual Nil Rate Band. On second death,
  up to double may be available."* while `/estate/inheritance-tax` said *"Combined Nil
  Rate Band of £650,000 available … Reduced by £150,000 due to gifts made within the
  last 7 years."* Same household, same instant, two accounts of one allowance, and only
  one mentioned the deduction the arithmetic had already applied. Both now render the
  calculation's strings.

  **Measured, user 16, read-only, after `cache:clear`:** `/plans/estate` returns
  projected net £4,368,400.76, allowances £500,000, residence band £0.00, charitable
  £20,000, taxable £3,848,400.76, **tax £1,539,360.30** — byte-identical to the
  drill-down. Acceptance 1 and 2 met; acceptance 3 (fixed with W-0136) met.

  **Acceptance 2's second clause is now real rather than aspirational:** both surfaces
  publish the projected allowance components and the exemption, so the projected
  taxable estate is derivable from the rows printed beside it.

  ---

  **The £103,206 in the dispatch belongs to a DIFFERENT defect, and it is now located.**
  The dispatch attributed it to the two screens. Measured, it is R-18 §2.6 item 3 — the
  gap between the two LOGINS — not item 2. Both are real; the number belongs to the
  second, and it is deterministic rather than noise:

  ```
  David  age 49 | years_to_death 36 | age_at_death 84 | projected_cash -2,957,895
  Sarah  age 48 | years_to_death 36 | age_at_death 84 | projected_cash -2,854,689
  ```

  Properties, investments and liabilities are identical. **The entire £103,206 is in
  `projected_cash`.** `projectCashWithInflation()` loops
  `for ($age = $currentAge; $age < $deathAge; $age++)` where `$currentAge` is the
  **logged-in user's** age and `$deathAge` is the **second-death age of whoever dies
  later** — two age scales in one loop. David iterates 49→83 = **35** years, Sarah
  48→83 = **36**, while `years_to_death` is 36 for both, so David's projection is a
  year short of its own stated horizon. `projectLiabilities()` computes
  `$yearsToProject = $deathAge - $currentAge` and inherits the same error.

  It scales with the household's annual surplus, which is exactly why the tester
  measured it growing from £88,257 to £103,206 as the household was entered —
  proportional, not a fixed offset, as recorded.

  **NOT fixed, deliberately.** Fixing it means choosing an anchor, and that moves the
  projected estate — so the £1,539,360 this cycle was pinned to moves with it. This is
  W-0137's mechanism and needs W-0137's own pin. Raising it here rather than taking it
  unilaterally.

  **⚠️ Clear the cache before re-measuring.** `EstateAgent::analyze()` caches its whole
  result and the first `/plans/estate` read after this change returned the PRE-FIX
  figures (taxable £3,467,510.13, tax £1,387,004.05). `php artisan cache:clear`.
