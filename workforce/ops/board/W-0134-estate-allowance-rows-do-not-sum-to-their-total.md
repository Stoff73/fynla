---
id: W-0134
title: The estate column does not add up — four allowance rows summing to £1,000,000 sit beneath a £850,000 total, and the charitable deduction has no row at all
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0015-cycle1-estate-tax-figures.md
owner: build-lead
status: gated
severity: high
surfaces: [web, m, ios]
created: 2026-08-21T20:10:00Z
claimed: 2026-08-21T19:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: [W-0154, W-0132]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, local `localhost:8000`, driven in Playwright as
**David Jones `users.id 16`** and **Sarah Jones `users.id 17`**, both premium, married,
`SpousePermission` accepted both ways.

**Surface:** `/estate/inheritance-tax` — `IHTCalculationTable.vue` inside
`IHTPlanning.vue`. This is the surface half of **W-0154**; that item is the service
computing the wrong thing, this one is the screen failing to add up whatever it is given.

### Expected

An estate table that itemises its allowances must have those items sum to the total it
prints beneath them, and each row between "Net Estate" and "Taxable Estate" must account
for the difference. A user checking their own arithmetic must be able to.

### Actual

**David's screen, verbatim, with the table fully expanded** (screenshot
`70-web-david-iht-table-expanded.png`):

```
Net Estate                          £1,234,280
Less: Tax-Free Allowances            -£850,000
  David's Tax-Free Allowance         -£325,000
  Sarah's Tax-Free Allowance         -£325,000
  David's Home Allowance             -£175,000
  Sarah's Home Allowance             -£175,000
  Subtotal                           -£850,000
Taxable Estate                        £374,280
Inheritance Tax Liability (40%)       £149,712
```

Two independent arithmetic failures on six consecutive rows:

1. **The four component rows sum to £1,000,000. The subtotal directly beneath them says
   £850,000.** The £150,000 difference is a chargeable lifetime transfer within seven
   years (`gifts.id 10`, £150,000, 2020-09-01) which reduces the nil rate band. It is
   applied to the total and appears in **no row**.
2. **£1,234,280 − £850,000 = £384,280, not the £374,280 shown.** The missing £10,000 is
   the charitable legacy (`bequests.51`, Cancer Research UK). `IHTCalculationTable.vue`
   does have a "Less: Charitable Donation" row, but it is gated `v-if="charitableBequest"`
   — the never-loaded user toggle of **W-0132** — so a deduction the server applied is
   invisible on the page that applied it.

**On Sarah's screen** (`73-web-sarah-iht-table-expanded.png`) failure 1 does not occur —
her four rows sum to £1,000,000 and her subtotal says £1,000,000 — but failure 2 does:
£1,234,280 − £1,000,000 = £234,280 against **£224,280** shown.

**The footnote makes it worse rather than better.** Beneath David's table:

> "Combined Nil Rate Band of £650,000 available (£325,000 each). Transfers between
> spouses are exempt from IHT on first death. Reduced by £150,000 due to gifts made
> within the last 7 years."

So one page offers **three** nil-rate-band figures: £325,000 per row, £650,000 in the
prose as "available", and £500,000 in the arithmetic actually performed.

### Impact

This is an estate table on a premium financial-planning surface. Its entire purpose is
to show the user how their tax liability is arrived at, and it does not survive addition.
A user who checks it finds £150,000 of allowance and £10,000 of charitable relief that
exist in the answer and nowhere in the working — and no way to tell which number is
wrong. It is also what let **W-0154** stay invisible: the components that would have
exposed the wrong nil rate band are not shown as components.

### Repro

1. Log in as `david.jones@example.com` (premium, married, spouse linked and accepted).
2. `/estate/inheritance-tax`, wait ~12s for hydration, click **Expand All**.
3. Add the four allowance rows: £1,000,000. Read the subtotal: £850,000.
4. Subtract the stated allowances from the stated net estate: £384,280. Read the taxable
   estate: £374,280.
5. Repeat as `sarah.jones@example.com`: step 3 balances, step 4 is £10,000 out.

### Acceptance

1. The allowance rows sum to the allowance total on both accounts. The chargeable
   lifetime transfer appears as its own row — named, dated and valued — not only in prose.
2. The charitable deduction appears as a row whenever it is applied, independent of the
   `charitable_bequest` toggle (which is W-0132's territory; this must not wait on it).
3. Every row between Net Estate and Taxable Estate accounts for the difference, so the
   column can be added by hand and reaches the printed answer.
4. The nil-rate-band footnote states the figure actually applied, not a pre-deduction one.
5. Verified in a browser on both persona accounts, with the arithmetic checked by hand.
6. Re-verify on `/m` once it shows an Inheritance Tax figure at all (see W-0138).

## Working notes
(append-only)


- 2026-08-21 cycle1-estate (build-lead): **FIXED, handed to quality-lead. Branch
  document `F-0015`. Not browser-verified — Quality's loop.**

  **The £150,000 row already existed in the template.** `fix-batch-G` added it under
  W-0154 F2 and `standardTableProps` passed `nrbGiftDeduction` correctly. It never
  rendered because the hand-written mapping in `IHTPlanning.vue:loadIHTCalculation()`
  copied `iht_summary.current` field by field and **omitted `nrb_spouse_modelled` and
  `nrb_gift_deduction`** — so the prop was fed `undefined || 0` and the `v-if` was
  false. A row present in the markup, published by the server, invisible on the page,
  because of an unrelated mapping in between. Worth recording: the previous fix was
  correct and shipped dark.

  **Acceptance 1 — the rows sum.** Fourteen hand-written rows across four marital
  branches became one row model (`nilRateBandRows()` + `residenceBandRows()`), each row
  carrying its own value per column. Two pre-existing reconciliation holes closed on
  the way, both invisible while the gift row was: the single and widowed branches
  printed `totalNrb` — already net of the deduction — and then rendered the deduction
  again beneath it; and the widowed branch printed gross residence components that
  exceed what is available once the cap or taper bites, which now gets its own named
  row.

  **Acceptance 2 — the charitable row is no longer gated on the toggle**, so a
  deduction the server applied is visible on the page that applied it. It sits BELOW
  the allowance block, not inside it: IHTA 1984 s23 removes the legacy from the
  estate's transferable value, so folding it into the allowance subtotal would make the
  column add up **while misstating the law**.

  **Acceptance 4 — the £325,000 spouse row is labelled as a modelled second-death
  transfer**, not an allowance held today. `nrb_transferred` stays 0: there is no
  transferable band while both spouses are alive (IHTA 1984 s8A). The £175,000 nobody
  could account for was two unlabelled effects netting out, and both now have rows.

  **One thing this item did not anticipate.** Since W-0136 the projected residence band
  is genuinely a different number from today's, so a single allowance figure printed
  beside both columns is wrong in at least one of them. The table now takes an
  `allowancesProjected` prop, defaulting to `allowances` so an un-updated caller
  behaves exactly as before.

  **Acceptance 5 and 6 are Quality's** — browser verification on both accounts with the
  arithmetic checked by hand. **Acceptance 6 (`/m`) has nothing to verify against:**
  `resources/mobile/views/modules/Estate.vue` renders no allowance itemisation and no
  Inheritance Tax figure in the premium view at all. That is W-0138 and this work does
  not change it.

  **Pinned by 10 frontend tests** in `tests/frontend/components/Estate/IHTCalculationTable.test.js`,
  asserting the ARITHMETIC of the rendered rows rather than the presence of a label —
  a label can be present and still not add up, which is how this survived a browser
  pass.

- 2026-08-22 cycle2-audit (build-lead): **acceptance 4 (the footnote) now done too.
  Branch document `F-0020`. Still with quality-lead; nothing about the handoff
  changes.**

  Cycle 1 made the rows add up. This sentence was the **last figure on the page a
  reader could not reconcile with them** — it opened "Combined Nil Rate Band of
  £650,000 available" above rows itemising £500,000, and a reader who trusts prose
  over tables took away the wrong band.

  **Cause: the message was built BEFORE the gift deduction**
  (`IHTCalculationService.php:176-186` as it was), then had "Reduced by £150,000…"
  appended after it. Construction moved after the deduction, into a new private
  `buildNrbMessage()`, which leads with the **applied** band and shows the deduction
  as the working that reaches it:

  > Combined Nil Rate Band of **£500,000 applied**: £325,000 each, less £150,000 of
  > allowance used by gifts made within the last 7 years. Your spouse's £325,000 is
  > modelled on second death — there is no transferable allowance while you are both
  > alive. Transfers between spouses are exempt from Inheritance Tax on the first
  > death.

  Three things worth recording:

  - **"available" is gone, "applied" is in** — widowed and single branches too, not
    just the married one this item was raised against.
  - **The second-death clause is cycle 1's `nrb-spouse-modelled` row note, verbatim,
    not a paraphrase.** One behaviour, one wording (Rule 20). Prose must not describe
    as held today what the row directly above it describes as modelled.
  - **"IHT" became "Inheritance Tax"** (Rule 9). The old string carried the acronym;
    the line was being rewritten anyway.

  Verified read-only against the live household: users 16 and 17 both return
  `nrb_available = 500000` with that sentence — the two accounts now agree, which
  they did not when this item was raised (W-0154's pooling).

  **Pinned by 5 tests** appended to `IHTHouseholdConsistencyTest.php` (17 passed, 57
  assertions), which **parse the figure back out of the prose with a regex and assert
  it equals `nrb_available`** — so the sentence cannot be updated in lockstep with a
  wrong number and still pass. Also covers the no-gift case (£650,000 applied,
  "available" absent), the single-person case, and that "£650,000" appears nowhere in
  the married-with-gifts sentence.

  **Surfaces:** server-side, so it reaches any surface rendering `nrb_message`; web
  renders it at `IHTPlanning.vue:360-362`. Zero hits for `nrb_message` in
  `resources/mobile` or `ios-native` — **acceptance 6 still has nothing to verify
  against**, unchanged from cycle 1's finding (W-0138).

  Regression: `tests/Unit/Services/Estate/` 284 passed (922 assertions).
