# R-28 — Cycle 4, batch 9

**Agent:** `peak-earners-c4` (persona-tester) · **Persona:** `peak_earners`
**Surface:** web, local · **Account:** David (16)
**Batch closed:** 2026-08-22 ~19:29 · Continues R-19 … [R-27](R-27-cycle4-batch-8.md)

Income. Five items at `handoff` live here, so this batch is mostly **verification of work
already done** rather than a hunt for new defects.

---

## Verified GREEN — four board items, with the arithmetic checked by hand

### W-0173 — rental income reaches the spouse, at the right share
The item was *"Rental income from a jointly-owned buy-to-let reaches only the primary owner."*
David's rental profit is now **£14,290**, itemised and correctly apportioned:

| Property | Gross/yr | Allowable | Net | David's share | Shown |
|---|---|---|---|---|---|
| Flat 42 (joint 50%) | £21,600 | £3,840 (insurance £35 + service £285) | £17,760 | 50% = £8,880 | **£8,880** ✓ |
| Manchester (tenants in common 40%) | £16,200 | £2,676 (insurance £28 + service £195) | £13,524 | 40% = £5,410 | **£5,410** ✓ |

Both shares correct, and the tenants-in-common 40% is applied properly here — which is
notable given D-01/D-02, where the *mortgage* on the same property is charged at 50%.

### W-0175 — rental is now stated once
One figure, £14,290, used in both the tax computation and the definitions panel. The
£1,920 discrepancy the item described is gone.

### W-0189 — the income definitions chain now adds up
> Total Income **£159,290** → Less employee pension contributions **−£11,600** → Net Income
> **£147,690**

159,290 − 11,600 = 147,690 ✓. The item's exact complaint ("£147,690 less £11,600 is
displayed as £147,690") is fixed.

Downstream figures all reconcile:
- Threshold Income £147,690 = 159,290 − 11,600 ✓
- Adjusted Income £170,890 = 159,290 + 11,600 ✓
- Personal Allowance **£0 (reduced from £12,570)** ✓ — correct, adjusted net income
  £147,690 is above £125,140
- Pension Annual Allowance **£60,000 (full)** ✓ — correct, adjusted income £170,890 is
  below £260,000

**The explanatory copy is the best I have seen in this app** and deserves saying so:
*"Threshold Income and Adjusted Income are each worked out from your Total Income above,
not from your Adjusted Net Income"*, and *"Your contributions are taken from your pay
before tax, so they come out of your Total Income once. The same amount is not deducted
again here."* That is exactly the auditability F-0020 was chasing — the user can check the
figure rather than take it on trust.

### W-0176 — the linked spouse's income displays
`/settings/family` shows **"Sarah Jones · Spouse · Account Linked · Annual Income
£120,000"** — the persona figure, not £0 — with a sensible guard: *"Linked account — can
only be edited or deleted by signing into their account."*

### W-0221 appears addressed (not formally verified against its acceptance)
The same page now carries a **Charitable Bequest** panel: *"Your will records one
charitable gift, totalling £10,000. Leaving 10% or more to charity can reduce your
Inheritance Tax rate from 40% to 36%."* The column is being read and surfaced, so it is no
longer write-only. Flagging for whoever owns the item rather than closing it myself.

### Income tax and National Insurance reconcile exactly
Taxable income £147,690, Personal Allowance £0:

| Band | Shown | Check |
|---|---|---|
| Basic £37,700 @ 20% | £7,540 | ✓ |
| Higher £87,440 @ 40% | £34,976 | ✓ (37,700 + 87,440 = 125,140, the additional-rate threshold) |
| Additional £22,550 @ 45% | £10,147 | ✓ (147,690 − 125,140 = 22,550) |
| **Tax payable** | **£52,663** | ✓ |
| Class 1 NI, 8% on £37,700 | £3,016 | ✓ |
| Class 1 NI, 2% on £94,730 | £1,895 | ✓ (145,000 − 50,270 = 94,730 — correctly excludes rental) |

---

## D-27 (MEDIUM) — "Net Income (after tax, pension contributions and tax credits)" does not deduct pension contributions

The Disposable Income panel states:

> **Net Income (after tax, pension contributions and tax credits): £102,496**
> Annual Expenditure: £37,994 → **Disposable Income: £64,501**

£102,496 = 159,290 − 52,663 + 780 − 3,016 − 1,895. Tax, the Section 24 credit and National
Insurance are all deducted. **The £11,600 of pension contributions is not**, despite the
label saying it is.

The same page deducts it correctly three panels earlier, in the definitions chain, to reach
Net Income £147,690. So the app knows the figure and applies it in one place and not the
other.

The consequence is not cosmetic: money paid into a pension is not available to spend, so
take-home is **£90,896**, and **Disposable Income is overstated by £11,600** — from £64,501
to about £52,901. Disposable income drives affordability judgements.

Screenshot: `147-web-david-income-net-income-102496-pension-not-deducted.png`

## D-28 (LOW) — "Earned Income £159,290 · NI Applies" includes rental income, which is neither

The National Insurance panel is headed **"Earned Income £159,290 · NI Applies"**. That
figure includes £14,290 of rental profit, which is not earned income and does not attract
National Insurance.

**The computation beneath it is right** — it is labelled "Class 1 (Employment)" and works
on £145,000, correctly excluding the rental. So this is a mislabelled header over a correct
calculation, not a wrong number. Low, but it is a factual mis-statement about tax on a page
whose whole value is that the user can check it.

---

## Could not verify

**The Section 24 tax credit of £780.** I could not reproduce it by hand. Mortgage interest
on the two buy-to-lets at David's shares is roughly £4,671 (Flat 42, 50% of £180,000 @
5.19%) plus £2,635 (Manchester, 40% of £120,000 @ 5.49%) = £7,306, and 20% of that is
£1,461. Restricting to the interest-only mortgage alone gives £934. Neither is £780, and
£780 implies £3,900 of qualifying interest, which I cannot derive from the persona.

**I am not raising this as a defect** — Section 24 is a tax-rules question and
`tax-compliance-reviewer` owns that surface. Recording it so someone competent can either
confirm the figure or correct it. Note `mortgages.monthly_interest_portion` is null on all
three rows, which may be the input the calculation actually wants.

---

## Not done, and why

- **W-0178 remains a decision, not a defect.** The allowable-expenses list excludes the
  maintenance reserve and "other" costs — the page says so plainly (*"utilities, buildings
  and contents insurance, service charge, ground rent and managing agent fees"*) — and my
  arithmetic above confirms that is exactly what it does. That item asks whether that is
  right; it is a product call and it is still `queued`.
- Sarah's income page not read this batch. Her figures appeared correctly in the risk
  factor breakdown (£128,880 = £120,000 + her £8,880 rental share), which is consistent
  with W-0173 being fixed on both sides.

## Assumptions

- I treated the persona's ages (William 17, Charlotte 14) as as-at the persona's authoring
  date. The app computes them live and shows 18 and 16, which is correct for 2026-08-22.
  Not a defect.

## Needs

- Board IDs for **D-27** and **D-28**.
- **A tax opinion on the £780 Section 24 credit** — route to `tax-compliance-reviewer`
  rather than `build-lead`.
- W-0221's owner should confirm whether the Charitable Bequest panel meets its acceptance;
  it looks like it does but that is not my call to close.
