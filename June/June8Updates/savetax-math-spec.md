# SaveTax — dynamic tax math spec (draft for sign-off)

Branch: `saveTax` · Date: 2026-06-08 · Status: **awaiting CSJ confirmation of the math before coding**

All tax values are the canonical 2026/27 figures from `TaxConfigService` (Rule #2 —
the page will read these server-side, never hard-code them).

## Canonical values (2026/27, from TaxConfigService)

| Item | Value |
|------|-------|
| Personal Allowance (PA) | £12,570 |
| PA taper | £1 lost per £2 over £100,000 → £0 at £125,140 |
| Basic rate | 20% (£12,571–£50,270) |
| Higher rate | 40% (£50,271–£125,140) |
| Additional rate | 45% (£125,140+) |
| Marriage Allowance | £1,260 transfer (saves £252) |
| Personal Savings Allowance (PSA) | basic £1,000 / higher £500 / additional £0 |
| Starting rate for savings | £5,000 @ 0% (only if non-savings income low) |
| ISA allowance | £20,000 |
| Pension Annual Allowance | £60,000 |
| Dividend allowance | £500 |
| CGT allowance | £3,000 |

## Income bands (NEW) and assumed income

Per CSJ: **assume the UPPER number of the band**.

| Band | Label | Assumed income | Marginal rate | Notes |
|------|-------|----------------|---------------|-------|
| A | Up to £50,270 | £50,270 | 20% | top of basic rate |
| B | £50,271 – £100,000 | £100,000 | 40% | higher rate, full PA |
| C | £100,001 – £130,000 | £130,000 | 45% | **60% trap zone** (£100k–£125,140) |
| D | Above £130,000 | **£150,000 (assumed)** ⚠️ | 45% | no upper bound — see Q2 |

Spouse income question gets the same bands **plus a £0 option** at the top.

## Saving components (the headline "you could save £X / year")

Honest basis (Rule: never overstate). Each line only counts when it applies.

### 1. Personal Savings Allowance (own) — band-adjusted
`saving = PSA(band) × marginal rate`
- A: £1,000 × 20% = **£200**
- B: £500 × 40% = **£200**
- C/D: £0 (additional rate — PSA gone; shown greyed)

### 2. Pension contribution (only if NO pension asset selected)
Assume contribution = **10% of upper-band income**, relieved at marginal rate.
- A: £5,027 × 20% = **£1,005**
- B: £10,000 × 40% = **£4,000**
- C: £13,000 × **60%** (trap) = **£7,800** — see §3
- D: £15,000 × 45% = **£6,750**

### 3. 60% tax-trap (Band C only)
Income £100k–£125,140 loses PA at £1 per £2 → pension contributions there get
**~60% effective relief** (40% tax + reclaimed PA). Band C's pension line is shown
at 60% with the explanation "you also reclaim your Personal Allowance."

### 4. Married + spouse earns £0 — shift income/allowances to spouse
When `spouse = £0`, the spouse's unused allowances are headroom to move income/
savings into (saving = amount × **primary's** marginal rate):
- Spouse full PA: £12,570 × rate (e.g. B: £5,028)
- Spouse PSA (basic): £1,000 × rate (e.g. B: £400)
- Spouse starting-rate savings: £5,000 × rate (e.g. B: £2,000)
- Marriage Allowance: £252 ⚠️ (overlaps with "full PA" lever — see Q3)

## Allowances available (the "Your allowances £X" total)

Sum of the allowance **amounts** that apply to the user (this is capacity, not saving):
PA £12,570 + PSA(band) + Starting-rate £5,000 (if low/spouse) + ISA £20,000 +
Pension AA £60,000 + Dividend £500 (if investments) + CGT £3,000 (if investments/property)
\+ Marriage Allowance £1,260 (if married).
**If married → double the per-person allowances each partner qualifies for (2× ISA, 2× AA, 2× PA…).** ⚠️ Q4

## Worked examples

**Single, Band B (£100k), has savings, no pension:**
- PSA £200 + pension £4,000 = **save ≈ £4,200/yr**
- Allowances available: PA 12,570 + PSA 500 + ISA 20,000 + AA 60,000 = **£93,070**

**Married, primary Band B (£100k), spouse £0, has savings, no pension:**
- Own: PSA £200 + pension £4,000
- Spouse headroom: PA £5,028 + PSA £400 + starting-rate £2,000 + MA £252
- = **save ≈ £11,880/yr**
- Allowances available (household): ~£93,070 + spouse PA 12,570 + ISA 20,000 + AA 60,000 … 

**Single, Band C (£130k), no pension:**
- 60% trap pension: £13,000 × 60% = **save ≈ £7,800/yr**

## Decisions (CONFIRMED by CSJ 2026-06-08)

1. **ISA / dividend / CGT count in the headline saving**, using an assumed amount:
   ISA = 10% of upper × marginal rate; dividend = £500 × dividend rate; CGT = £3,000 × CGT rate.
2. **Band C = exact trap clearance** — contribute (assumed income − £100,000) to restore the
   Personal Allowance; saving = full tax relief incl. reclaimed PA (≈ 57–60% effective).
3. **Above £130k → assume £150,000** for the 10%-of-upper pension line.
4. **Spouse £0 → show BOTH** the formal Marriage Allowance (£252) AND the "use spouse's full
   PA + PSA + starting-rate" transfer lever, clearly separated as related routes.

### Canonical dividend/CGT rates used (from TaxConfigService)
- Dividend allowance £500; rates basic 10.75% / higher 35.75% / additional 39.35%.
- CGT annual exempt £3,000; rates basic 18% / higher 24%.

### Final per-band figures (single, all financial assets, no existing pension)

| Component | A (£50,270, 20%) | B (£100k, 40%) | C (£130k, trap) | D (£150k, 45%) |
|-----------|-----|-----|-----|-----|
| Pension relief | £1,005 | £4,000 | **£17,271** (exact, £30k) | £6,750 |
| ISA (10% × rate) | £1,005 | £4,000 | £5,850 | £6,750 |
| Own PSA × rate | £200 | £200 | £0 (add'l) | £0 |
| Dividend £500 × div-rate | £54 | £179 | £197 | £197 |
| CGT £3,000 × cgt-rate | £540 | £720 | £720 | £720 |

Married + spouse £0 adds (× primary rate): full PA £12,570, spouse PSA £1,000,
starting-rate £5,000, plus flat Marriage Allowance £252.
(e.g. primary B/40%: £5,028 + £400 + £2,000 + £252 = **£7,680** extra.)

Pension relief is computed by an exact income-tax engine (PA taper + band extension +
20% relief at source), which is why Band C's £30k contribution yields £17,271
(≈57.6% effective) — the 60% trap shows up naturally, not as a flat multiplier.
